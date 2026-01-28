<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiSensitiveData
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->user()?->currentAccessToken();

        // Session auth (no token) - allow full access to sensitive data
        // Token auth - check for root or read:sensitive permission
        $canReadSensitive = ! $token || $token->can('root') || $token->can('read:sensitive');

        $request->attributes->add([
            'can_read_sensitive' => $canReadSensitive,
        ]);

        return $next($request);
    }
}
