<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\TransientToken;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenRateLimiter
{
    private const REDIS_KEY_PREFIX = 'saturn:token_ratelimit:';

    private const WINDOW_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        /** @var \Laravel\Sanctum\Contracts\HasAbilities|null $token */
        $token = $user?->currentAccessToken();

        // Skip for unauthenticated or session-authenticated (SPA) users — only token auth is rate-limited here
        if (! $user || ! $token || $token instanceof TransientToken) {
            return $next($request);
        }

        $limit = (int) config('api.token_rate_limit', 60);

        // Use a hash of the token's database ID as the Redis key discriminator.
        // We hash the ID so Redis keys don't expose even indirect token metadata.
        $key = self::REDIS_KEY_PREFIX.hash('sha256', (string) $token->id);

        $current = (int) Redis::incr($key);

        // Set expiry on first request in the window
        if ($current === 1) {
            Redis::expire($key, self::WINDOW_SECONDS);
        }

        if ($current > $limit) {
            $ttl = (int) Redis::ttl($key);
            $retryAfter = max(1, $ttl);

            return response()->json(
                ['message' => 'Too Many Requests.'],
                Response::HTTP_TOO_MANY_REQUESTS,
                [
                    'Retry-After' => $retryAfter,
                    'X-RateLimit-Limit' => $limit,
                    'X-RateLimit-Remaining' => 0,
                ]
            );
        }

        $remaining = max(0, $limit - $current);

        /** @var \Illuminate\Http\Response $response */
        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit', (string) $limit);
        $response->headers->set('X-RateLimit-Remaining', (string) $remaining);

        return $response;
    }
}
