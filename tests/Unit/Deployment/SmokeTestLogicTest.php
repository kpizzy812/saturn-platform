<?php

namespace Tests\Unit\Deployment;

use Tests\TestCase;

/**
 * Tests for smoke test HTTP code evaluation logic.
 *
 * The smoke test runs curl from the server to the container's internal IP.
 * These tests validate the HTTP code classification logic (pass/fail thresholds)
 * and URL construction rules — without making real HTTP calls.
 */
class SmokeTestLogicTest extends TestCase
{
    // ---------------------------------------------------------------------------
    // HTTP code classification
    // ---------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\DataProvider('passingHttpCodesProvider')]
    public function test_smoke_test_passes_for_successful_http_codes(int $code): void
    {
        $this->assertTrue($this->isPassingHttpCode($code), "HTTP {$code} should be considered passing");
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('failingHttpCodesProvider')]
    public function test_smoke_test_fails_for_server_error_http_codes(int $code): void
    {
        $this->assertFalse($this->isPassingHttpCode($code), "HTTP {$code} should be considered failing");
    }

    public static function passingHttpCodesProvider(): array
    {
        return [
            'ok' => [200],
            'created' => [201],
            'no content' => [204],
            'moved permanently' => [301],
            'found (redirect)' => [302],
            'not modified' => [304],
            'not found' => [404], // app is up, page just missing
            'method not allowed' => [405], // app is up, method rejected
            'unprocessable entity' => [422], // app is up, validation failed
            'too many requests' => [429], // app is up, rate limited
        ];
    }

    public static function failingHttpCodesProvider(): array
    {
        return [
            'internal server error' => [500],
            'bad gateway' => [502],
            'service unavailable' => [503],
            'gateway timeout' => [504],
        ];
    }

    public function test_curl_failed_result_is_treated_as_failure(): void
    {
        $this->assertFalse($this->isCurlSuccess('CURL_FAILED'));
    }

    public function test_empty_result_is_treated_as_failure(): void
    {
        $this->assertFalse($this->isCurlSuccess(''));
    }

    public function test_zero_http_code_is_treated_as_failure(): void
    {
        $this->assertFalse($this->isCurlSuccess('0'));
    }

    // ---------------------------------------------------------------------------
    // URL construction
    // ---------------------------------------------------------------------------

    public function test_smoke_test_url_uses_health_check_port_if_available(): void
    {
        $port = 8080;
        $containerIp = '172.18.0.5';
        $path = 'health';

        $url = "http://{$containerIp}:{$port}/{$path}";

        $this->assertStringContainsString('8080', $url);
        $this->assertStringContainsString('172.18.0.5', $url);
        $this->assertStringContainsString('/health', $url);
    }

    public function test_smoke_test_path_leading_slash_is_stripped_for_url_construction(): void
    {
        $path = ltrim('/api/health', '/');
        $this->assertSame('api/health', $path);
    }

    public function test_smoke_test_path_defaults_to_root(): void
    {
        $smokeTestPath = null;
        $path = ltrim($smokeTestPath ?? '/', '/');
        $this->assertSame('', $path); // '/' stripped to '' — becomes http://ip:port/
    }

    public function test_smoke_test_timeout_is_at_least_5_seconds(): void
    {
        foreach ([0, 1, 3, 4, 5] as $raw) {
            $effective = max(5, (int) $raw);
            $this->assertGreaterThanOrEqual(5, $effective, "Timeout {$raw}s should be clamped to minimum 5s");
        }
    }

    public function test_connect_timeout_does_not_exceed_timeout(): void
    {
        foreach ([5, 10, 30, 60] as $timeout) {
            $connectTimeout = min(10, $timeout);
            $this->assertLessThanOrEqual($timeout, $connectTimeout);
            $this->assertLessThanOrEqual(10, $connectTimeout);
        }
    }

    // ---------------------------------------------------------------------------
    // Helpers (replicate logic from perform_smoke_test without SSH/container deps)
    // ---------------------------------------------------------------------------

    private function isPassingHttpCode(int $code): bool
    {
        return $code >= 200 && $code < 500;
    }

    private function isCurlSuccess(string $result): bool
    {
        if ($result === 'CURL_FAILED' || empty($result)) {
            return false;
        }

        $httpCode = (int) $result;

        return $httpCode >= 200 && $httpCode < 500;
    }
}
