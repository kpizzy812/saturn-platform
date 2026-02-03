<?php

namespace App\Http\Middleware;

use App\Services\Authorization\ResourceAuthorizationService;
use Closure;
use Illuminate\Http\Request;

class ApiSensitiveData
{
    public function __construct(
        protected ResourceAuthorizationService $authService
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        // Default: no sensitive data access
        $canReadSensitive = false;

        if ($user) {
            // Check token permissions for API auth
            // Note: Sanctum creates TransientToken for cookie-based SPA auth
            $isSessionAuth = ! $token || $token instanceof \Laravel\Sanctum\TransientToken;
            $hasTokenPermission = $isSessionAuth || $token->can('root') || $token->can('read:sensitive');

            // Only grant sensitive access if:
            // 1. Token has permission (root or read:sensitive) OR is session auth
            // 2. AND user is admin+ in the current team
            if ($hasTokenPermission) {
                $team = currentTeam();
                if ($team) {
                    // Check if user is admin+ in current team
                    $canReadSensitive = $this->authService->canAccessSensitiveData($user, $team->id);
                }
            }
        }

        $request->attributes->add([
            'can_read_sensitive' => $canReadSensitive,
        ]);

        return $next($request);
    }
}
