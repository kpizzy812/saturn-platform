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
        $this->assertStringContainsString('nonce-', $csp);
        $this->assertStringContainsString("style-src 'self' 'unsafe-inline'", $csp);
        $this->assertStringContainsString("'strict-dynamic'", $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
    }

    public function test_does_not_add_csp_to_json_responses(): void
    {
        $request = Request::create('/api/v1/test');

        $response = $this->middleware->handle($request, function () {
            return new Response('{"ok":true}', 200, ['Content-Type' => 'application/json']);
        });

        $this->assertFalse($response->headers->has('Content-Security-Policy'));
    }

    public function test_nonce_is_unique_per_request(): void
    {
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
        $this->assertStringContainsString("form-action 'self'", $csp);
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
}
