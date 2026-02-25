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
        $nonce = Vite::cspNonce();

        // Share nonce with Livewire's script tag so it passes CSP in production
        if (class_exists(\Livewire\Livewire::class)) {
            \Livewire\Livewire::useScriptTagAttributes(['nonce' => $nonce]);
        }

        $response = $next($request);

        // Only add CSP to HTML responses (not API/JSON)
        if ($this->isHtmlResponse($response)) {
            $isProduction = app()->environment('production');

            // In production, inject nonce into any <script>/<style> tags missing it
            // (e.g., Livewire auto-injected inline styles, third-party libraries)
            if ($isProduction) {
                $this->injectNonceIntoTags($response, $nonce);
            }

            $response->headers->set('Content-Security-Policy', $this->buildCspPolicy($nonce));
        }

        return $response;
    }

    private function buildCspPolicy(string $nonce): string
    {
        // In non-production environments, use unsafe-inline WITHOUT nonce.
        // CSP Level 3: when a nonce is present, browsers IGNORE 'unsafe-inline',
        // which breaks legacy Blade inline scripts that don't have nonce attributes.
        $isProduction = app()->environment('production');

        $scriptSrc = $isProduction
            ? "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic'"
            : "script-src 'self' 'unsafe-inline' 'unsafe-eval'";

        return implode('; ', [
            "default-src 'self'",
            $scriptSrc,
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
            "img-src 'self' data: https:",
            "connect-src 'self' wss: ws:",
            "font-src 'self' data: https://fonts.bunny.net",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self' https://github.com",
            "frame-ancestors 'self'",
        ]);
    }

    /**
     * Add nonce to <script> and <style> tags that don't already have one.
     * Handles Livewire auto-injected styles and any other third-party inline tags.
     */
    private function injectNonceIntoTags(Response $response, string $nonce): void
    {
        $content = $response->getContent();
        if (! $content) {
            return;
        }

        // Add nonce to <script> tags without nonce attribute (but not <script src="..."> external scripts)
        $content = preg_replace(
            '/<script(?![^>]*\bnonce\b)(?![^>]*\bsrc\b)([^>]*)>/i',
            '<script nonce="'.$nonce.'"$1>',
            $content
        );

        // Add nonce to <style> tags without nonce attribute
        $content = preg_replace(
            '/<style(?![^>]*\bnonce\b)([^>]*)>/i',
            '<style nonce="'.$nonce.'"$1>',
            $content
        );

        $response->setContent($content);
    }

    private function isHtmlResponse(Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');

        return str_contains($contentType, 'text/html');
    }
}
