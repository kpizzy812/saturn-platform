<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class DecideWhatToDoWithUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->user()?->teams?->count() === 0) {
            $currentTeam = auth()->user()->recreate_personal_team();
            refreshSession($currentTeam);
        }
        if (auth()->user()?->currentTeam()) {
            refreshSession(auth()->user()->currentTeam());
        }
        if (! auth()->user() || ! isCloud() || isInstanceAdmin()) {
            if (showBoarding() && ! $this->isAllowedForBoarding($request->path())) {
                return redirect()->route('boarding.index');
            }

            return $next($request);
        }
        if (! auth()->user()->hasVerifiedEmail()) {
            if ($request->path() === 'verify' || in_array($request->path(), allowedPathsForInvalidAccounts()) || $request->routeIs('verify.verify')) {
                return $next($request);
            }

            return redirect()->route('verify.email');
        }
        if (! isSubscriptionActive() && ! isSubscriptionOnGracePeriod()) {
            if (! in_array($request->path(), allowedPathsForUnsubscribedAccounts())) {
                if (Str::startsWith($request->path(), 'invitations')) {
                    return $next($request);
                }

                return redirect()->route('subscription.index');
            }
        }
        if (showBoarding() && ! $this->isAllowedForBoarding($request->path())) {
            if (Str::startsWith($request->path(), 'invitations')) {
                return $next($request);
            }

            return redirect()->route('boarding.index');
        }
        if (auth()->user()->hasVerifiedEmail() && $request->path() === 'verify') {
            return redirect(RouteServiceProvider::HOME);
        }
        if (isSubscriptionActive() && $request->routeIs('subscription.index')) {
            return redirect(RouteServiceProvider::HOME);
        }

        return $next($request);
    }

    private function isAllowedForBoarding(string $path): bool
    {
        foreach (allowedPathsForBoardingAccounts() as $allowedPath) {
            if ($path === $allowedPath || Str::startsWith($path, $allowedPath.'/')) {
                return true;
            }
        }

        return false;
    }
}
