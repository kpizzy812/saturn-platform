<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * All traffic arrives through Traefik on the Docker 'saturn' network.
     * The app port (8080) is never exposed to the host directly.
     * Loaded from config('app.trusted_proxies') at runtime (see config/app.php).
     *
     * NEVER set to '*' in production — it allows X-Forwarded-* header spoofing.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies = [
        '127.0.0.1',
        '172.16.0.0/12', // Docker bridge and custom networks
        '10.0.0.0/8',    // Docker overlay / swarm networks
    ];

    /**
     * The headers that should be used to detect proxies.
     * AWS_ELB header is excluded — we use Traefik, not AWS ELB.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO;

    /**
     * Handle the incoming request.
     * Resolves trusted proxy CIDRs from config so the value can be overridden
     * via the TRUSTED_PROXIES environment variable without touching code.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $configured = config('app.trusted_proxies');
        if (is_string($configured) && $configured !== '') {
            $this->proxies = array_filter(array_map('trim', explode(',', $configured)));
        }

        return parent::handle($request, $next);
    }
}
