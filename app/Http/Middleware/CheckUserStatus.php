<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckUserStatus
{
    /**
     * Handle an incoming request.
     *
     * Check if the authenticated user's account is active.
     * Suspended, banned, or pending users are logged out with an error message.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip check if user is not authenticated
        if (! Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        // Allow root user (id=0) to bypass status checks
        if ($user->id === 0) {
            return $next($request);
        }

        // Check user status
        if ($user->isSuspended()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $reason = $user->suspension_reason
                ? "Reason: {$user->suspension_reason}"
                : 'Please contact support for more information.';

            return redirect()->route('auth.login')
                ->withErrors([
                    'email' => "Your account has been suspended. {$reason}",
                ]);
        }

        if ($user->isBanned()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $reason = $user->suspension_reason
                ? "Reason: {$user->suspension_reason}"
                : 'This ban is permanent.';

            return redirect()->route('auth.login')
                ->withErrors([
                    'email' => "Your account has been permanently banned. {$reason}",
                ]);
        }

        if ($user->isPending() && $user->email_verified_at === null) {
            // Allow access to email verification routes
            if (! $request->routeIs('verify.*') && ! $request->routeIs('auth.verify-email')) {
                return redirect()->route('auth.verify-email')
                    ->with('message', 'Please verify your email address to continue.');
            }
        }

        return $next($request);
    }
}
