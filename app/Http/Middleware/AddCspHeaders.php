<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Add Content-Security-Policy header with per-request nonce.
 *
 * Replaces the static Nginx CSP header with a dynamic one that uses
 * Vite-generated nonces for inline scripts/styles, eliminating the
 * need for 'unsafe-inline' and 'unsafe-eval'.
 */
class AddCspHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        $response = $next($request);

        // Only add CSP to HTML responses (not API/JSON)
        if ($this->isHtmlResponse($response)) {
            $nonce = Vite::cspNonce();
            $response->headers->set('Content-Security-Policy', $this->buildCspPolicy($nonce));
        }

        return $response;
    }

    private function buildCspPolicy(string $nonce): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'nonce-{$nonce}' https://fonts.bunny.net",
            "img-src 'self' data: https:",
            "connect-src 'self' wss: ws:",
            "font-src 'self' data: https://fonts.bunny.net",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
    }

    private function isHtmlResponse(Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');

        return str_contains($contentType, 'text/html');
    }
}
