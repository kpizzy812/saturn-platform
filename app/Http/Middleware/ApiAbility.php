<?php

namespace App\Http\Middleware;

use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

class ApiAbility extends CheckForAnyAbility
{
    public function handle($request, $next, ...$abilities): \Symfony\Component\HttpFoundation\Response
    {
        try {
            $user = $request->user();
            /** @var \Laravel\Sanctum\Contracts\HasAbilities|null $token */
            $token = $user?->currentAccessToken();

            // If user is authenticated via session (TransientToken or no token), allow access
            // Session auth is already validated by Sanctum's EnsureFrontendRequestsAreStateful
            // Note: Sanctum creates TransientToken for cookie-based SPA auth, not null
            if ($user && (! $token || $token instanceof \Laravel\Sanctum\TransientToken)) {
                return $next($request);
            }

            // Token auth - check for root ability
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
}
