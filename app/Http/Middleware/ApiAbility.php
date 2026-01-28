<?php

namespace App\Http\Middleware;

use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

class ApiAbility extends CheckForAnyAbility
{
    public function handle($request, $next, ...$abilities)
    {
        try {
            $user = $request->user();
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
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Missing required permissions: '.implode(', ', $abilities),
            ], 403);
        }
    }
}
