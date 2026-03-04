<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Check if the authenticated API user's account is active.
 * Returns JSON 403 for suspended/banned users instead of redirect.
 */
class CheckApiUserStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Allow root user (id=0) to bypass status checks
        if ($user->id === 0) {
            return $next($request);
        }

        if ($user->isSuspended()) {
            return response()->json([
                'message' => 'Your account has been suspended.',
                'reason' => $user->suspension_reason,
            ], 403);
        }

        if ($user->isBanned()) {
            return response()->json([
                'message' => 'Your account has been permanently banned.',
                'reason' => $user->suspension_reason,
            ], 403);
        }

        return $next($request);
    }
}
