<?php

namespace App\Http\Middleware;

use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

class ApiAbility extends CheckForAnyAbility
{
    /**
     * Team roles that are allowed for each API ability.
     * Session-authenticated (browser SPA) users are checked against these role lists.
     * Token-authenticated users are checked against their token abilities as usual.
     *
     * Role hierarchy: owner > admin > developer > member > viewer
     */
    private const ROLE_REQUIREMENTS = [
        'read' => ['viewer', 'member', 'developer', 'admin', 'owner'],
        'write' => ['member', 'developer', 'admin', 'owner'],
        'deploy' => ['member', 'developer', 'admin', 'owner'],
        'read:sensitive' => ['developer', 'admin', 'owner'],
        'root' => ['admin', 'owner'],
    ];

    public function handle($request, $next, ...$abilities): \Symfony\Component\HttpFoundation\Response
    {
        try {
            $user = $request->user();
            /** @var \Laravel\Sanctum\Contracts\HasAbilities|null $token */
            $token = $user?->currentAccessToken();

            // Session-authenticated SPA user (TransientToken or null token).
            // Sanctum's EnsureFrontendRequestsAreStateful already validated the session.
            // Enforce team-role-based ability check instead of passing through unconditionally.
            if ($user && (! $token || $token instanceof \Laravel\Sanctum\TransientToken)) {
                return $this->handleSessionAbilityCheck($request, $next, $user, $abilities);
            }

            // Token auth: check for root ability shortcut
            if ($user && $user->tokenCan('root')) {
                return $next($request);
            }

            return parent::handle($request, $next, ...$abilities);
        } catch (\Illuminate\Auth\AuthenticationException $e) {
            return new \Illuminate\Http\Response(
                json_encode(['message' => 'Unauthenticated.']),
                401,
                ['Content-Type' => 'application/json']
            );
        } catch (\Exception $e) {
            return new \Illuminate\Http\Response(
                json_encode(['message' => 'Missing required permissions: '.implode(', ', $abilities)]),
                403,
                ['Content-Type' => 'application/json']
            );
        }
    }

    /**
     * Check whether a session-authenticated user's team role grants the required abilities.
     */
    private function handleSessionAbilityCheck(
        $request,
        $next,
        $user,
        array $abilities
    ): \Illuminate\Http\Response {
        // Superadmins bypass all checks
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return $next($request);
        }

        $role = null;
        try {
            $role = $user->role();
        } catch (\Throwable) {
            // Cannot resolve team role — deny access for safety
            return new \Illuminate\Http\Response(
                json_encode(['message' => 'Unable to determine team membership.']),
                403,
                ['Content-Type' => 'application/json']
            );
        }

        foreach ($abilities as $ability) {
            $allowedRoles = self::ROLE_REQUIREMENTS[$ability] ?? [];
            if (! in_array($role, $allowedRoles, true)) {
                return new \Illuminate\Http\Response(
                    json_encode(['message' => 'Missing required permissions: '.implode(', ', $abilities)]),
                    403,
                    ['Content-Type' => 'application/json']
                );
            }
        }

        return $next($request);
    }
}
