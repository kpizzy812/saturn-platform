<?php

namespace App\Http\Middleware;

use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

class ApiAbility extends CheckForAnyAbility
{
    public function handle($request, $next, ...$abilities)
    {
        try {
            $user = $request->user();

            // If user is authenticated via session (no token), allow access
            // Session auth is already validated by Sanctum's EnsureFrontendRequestsAreStateful
            if ($user && ! $user->currentAccessToken()) {
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
