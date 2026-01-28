<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiSensitiveData
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->user()?->currentAccessToken();

        // Session auth (TransientToken or no token) - allow full access to sensitive data
        // Token auth (PersonalAccessToken) - check for root or read:sensitive permission
        // Note: Sanctum creates TransientToken for cookie-based SPA auth
        $isSessionAuth = ! $token || $token instanceof \Laravel\Sanctum\TransientToken;
        $canReadSensitive = $isSessionAuth || $token->can('root') || $token->can('read:sensitive');

        $request->attributes->add([
            'can_read_sensitive' => $canReadSensitive,
        ]);

        return $next($request);
    }
}
