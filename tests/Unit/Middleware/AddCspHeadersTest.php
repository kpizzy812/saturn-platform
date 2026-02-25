<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\AddCspHeaders;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

class AddCspHeadersTest extends TestCase
{
    private AddCspHeaders $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new AddCspHeaders;
    }

    public function test_adds_csp_header_to_html_responses(): void
    {
        $request = Request::create('/dashboard');

        $response = $this->middleware->handle($request, function () {
            return new Response('<html></html>', 200, ['Content-Type' => 'text/html']);
        });

        $this->assertTrue($response->headers->has('Content-Security-Policy'));

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("style-src 'self' 'unsafe-inline'", $csp);
    }

    public function test_production_uses_strict_dynamic(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $request = Request::create('/dashboard');

        $response = $this->middleware->handle($request, function () {
            return new Response('<html></html>', 200, ['Content-Type' => 'text/html']);
        });

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("'strict-dynamic'", $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
        $this->assertStringNotContainsString('unsafe-inline', explode(';', explode('script-src', $csp)[1])[0]);
    }

    public function test_non_production_allows_unsafe_inline(): void
    {
        app()->detectEnvironment(fn () => 'local');

        $request = Request::create('/dashboard');

        $response = $this->middleware->handle($request, function () {
            return new Response('<html></html>', 200, ['Content-Type' => 'text/html']);
        });

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("'unsafe-inline'", $csp);
        $this->assertStringContainsString("'unsafe-eval'", $csp);
        $this->assertStringNotContainsString("'strict-dynamic'", $csp);
    }

    public function test_does_not_add_csp_to_json_responses(): void
    {
        $request = Request::create('/api/v1/test');

        $response = $this->middleware->handle($request, function () {
            return new Response('{"ok":true}', 200, ['Content-Type' => 'application/json']);
        });

        $this->assertFalse($response->headers->has('Content-Security-Policy'));
    }

    public function test_nonce_is_unique_per_request_in_production(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $nonces = [];
        for ($i = 0; $i < 3; $i++) {
            // Reset Vite nonce for each simulated request
            Vite::useCspNonce();
            $request = Request::create('/');

            $response = $this->middleware->handle($request, function () {
                return new Response('<html></html>', 200, ['Content-Type' => 'text/html']);
            });

            $csp = $response->headers->get('Content-Security-Policy');
            preg_match("/nonce-([a-zA-Z0-9+\/=]+)/", $csp, $matches);
            $nonces[] = $matches[1] ?? '';
        }

        // All nonces should be different
        $this->assertCount(3, array_unique($nonces));
    }

    public function test_non_production_does_not_include_nonce(): void
    {
        app()->detectEnvironment(fn () => 'local');

        $request = Request::create('/');

        $response = $this->middleware->handle($request, function () {
            return new Response('<html></html>', 200, ['Content-Type' => 'text/html']);
        });

        $csp = $response->headers->get('Content-Security-Policy');

        // Non-production should NOT include nonce (CSP Level 3: nonce causes browsers to ignore unsafe-inline)
        $this->assertStringNotContainsString('nonce-', $csp);
    }

    public function test_csp_includes_required_directives(): void
    {
        $request = Request::create('/');

        $response = $this->middleware->handle($request, function () {
            return new Response('<html></html>', 200, ['Content-Type' => 'text/html']);
        });

        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString("style-src 'self'", $csp);
        $this->assertStringContainsString("img-src 'self' data: https:", $csp);
        $this->assertStringContainsString("connect-src 'self' wss: ws:", $csp);
        $this->assertStringContainsString("font-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("form-action 'self' https://github.com", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
    }

    public function test_allows_bunny_fonts(): void
    {
        $request = Request::create('/');

        $response = $this->middleware->handle($request, function () {
            return new Response('<html></html>', 200, ['Content-Type' => 'text/html']);
        });

        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('https://fonts.bunny.net', $csp);
    }

    public function test_production_injects_nonce_into_inline_scripts(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $request = Request::create('/');

        $html = '<html><head><style>body{color:red}</style></head><body><script>alert(1)</script></body></html>';

        $response = $this->middleware->handle($request, function () use ($html) {
            return new Response($html, 200, ['Content-Type' => 'text/html']);
        });

        $content = $response->getContent();

        // Inline script and style should have nonce injected
        $this->assertMatchesRegularExpression('/<script nonce="[^"]+"/', $content);
        $this->assertMatchesRegularExpression('/<style nonce="[^"]+"/', $content);
    }

    public function test_production_does_not_double_nonce_existing_tags(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $request = Request::create('/');

        $html = '<html><head></head><body><script nonce="existing123">alert(1)</script></body></html>';

        $response = $this->middleware->handle($request, function () use ($html) {
            return new Response($html, 200, ['Content-Type' => 'text/html']);
        });

        $content = $response->getContent();

        // Should keep original nonce, not double it
        $this->assertStringContainsString('nonce="existing123"', $content);
        $this->assertEquals(1, substr_count($content, 'nonce='));
    }

    public function test_production_does_not_nonce_external_scripts(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $request = Request::create('/');

        $html = '<html><head></head><body><script src="/app.js"></script></body></html>';

        $response = $this->middleware->handle($request, function () use ($html) {
            return new Response($html, 200, ['Content-Type' => 'text/html']);
        });

        $content = $response->getContent();

        // External scripts with src should NOT get nonce (they're covered by strict-dynamic)
        $this->assertStringNotContainsString('nonce=', $content);
    }

    public function test_non_production_does_not_inject_nonces_into_tags(): void
    {
        app()->detectEnvironment(fn () => 'local');

        $request = Request::create('/');

        $html = '<html><head><style>body{color:red}</style></head><body><script>alert(1)</script></body></html>';

        $response = $this->middleware->handle($request, function () use ($html) {
            return new Response($html, 200, ['Content-Type' => 'text/html']);
        });

        $content = $response->getContent();

        // Non-production should not inject nonces (unsafe-inline handles it)
        $this->assertStringNotContainsString('nonce=', $content);
    }
}
