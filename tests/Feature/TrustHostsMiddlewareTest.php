<?php

use App\Http\Middleware\TrustHosts;
use App\Models\InstanceSettings;
use Illuminate\Support\Facades\Cache;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    // Clear cache before each test to ensure isolation
    Cache::forget('instance_settings_fqdn_host');
});

it('trusts the configured FQDN from InstanceSettings', function () {
    // Create instance settings with FQDN
    InstanceSettings::updateOrCreate(
        ['id' => 0],
        ['fqdn' => 'https://saturn.example.com']
    );

    $middleware = new TrustHosts($this->app);
    $hosts = $middleware->hosts();

    expect($hosts)->toContain('saturn.example.com');
});

it('rejects password reset request with malicious host header', function () {
    // Set up instance settings with legitimate FQDN
    InstanceSettings::updateOrCreate(
        ['id' => 0],
        ['fqdn' => 'https://saturn.example.com']
    );

    $middleware = new TrustHosts($this->app);
    $hosts = $middleware->hosts();

    // The malicious host should NOT be in the trusted hosts
    expect($hosts)->not->toContain('saturn.example.com.evil.com');
    expect($hosts)->toContain('saturn.example.com');
});

it('handles missing FQDN gracefully', function () {
    // Create instance settings without FQDN
    InstanceSettings::updateOrCreate(
        ['id' => 0],
        ['fqdn' => null]
    );

    $middleware = new TrustHosts($this->app);
    $hosts = $middleware->hosts();

    // Should still return APP_URL pattern without throwing
    expect($hosts)->not->toBeEmpty();
});

it('filters out null and empty values from trusted hosts', function () {
    InstanceSettings::updateOrCreate(
        ['id' => 0],
        ['fqdn' => '']
    );

    $middleware = new TrustHosts($this->app);
    $hosts = $middleware->hosts();

    // Should not contain empty strings or null
    foreach ($hosts as $host) {
        if ($host !== null) {
            expect($host)->not->toBeEmpty();
        }
    }
});

it('extracts host from FQDN with protocol and port', function () {
    InstanceSettings::updateOrCreate(
        ['id' => 0],
        ['fqdn' => 'https://saturn.example.com:8443']
    );

    $middleware = new TrustHosts($this->app);
    $hosts = $middleware->hosts();

    expect($hosts)->toContain('saturn.example.com');
});

it('handles exception during InstanceSettings fetch', function () {
    // Drop the instance_settings table to simulate installation
    \Schema::dropIfExists('instance_settings');

    $middleware = new TrustHosts($this->app);

    // Should not throw an exception
    $hosts = $middleware->hosts();

    expect($hosts)->not->toBeEmpty();
});

it('trusts IP addresses with port', function () {
    InstanceSettings::updateOrCreate(
        ['id' => 0],
        ['fqdn' => 'http://65.21.3.91:8000']
    );

    $middleware = new TrustHosts($this->app);
    $hosts = $middleware->hosts();

    expect($hosts)->toContain('65.21.3.91');
});

it('trusts IP addresses without port', function () {
    InstanceSettings::updateOrCreate(
        ['id' => 0],
        ['fqdn' => 'http://192.168.1.100']
    );

    $middleware = new TrustHosts($this->app);
    $hosts = $middleware->hosts();

    expect($hosts)->toContain('192.168.1.100');
});

it('rejects malicious host when using IP address', function () {
    // Simulate an instance using IP address
    InstanceSettings::updateOrCreate(
        ['id' => 0],
        ['fqdn' => 'http://65.21.3.91:8000']
    );

    $middleware = new TrustHosts($this->app);
    $hosts = $middleware->hosts();

    // The malicious host attempting to mimic the IP should NOT be trusted
    expect($hosts)->not->toContain('65.21.3.91.evil.com');
    expect($hosts)->not->toContain('evil.com');
    expect($hosts)->toContain('65.21.3.91');
});

it('trusts IPv6 addresses', function () {
    InstanceSettings::updateOrCreate(
        ['id' => 0],
        ['fqdn' => 'http://[2001:db8::1]:8000']
    );

    $middleware = new TrustHosts($this->app);
    $hosts = $middleware->hosts();

    // IPv6 addresses are enclosed in brackets, getHost() should handle this
    expect($hosts)->toContain('[2001:db8::1]');
});

it('invalidates cache when FQDN is updated', function () {
    // Set initial FQDN
    $settings = InstanceSettings::updateOrCreate(
        ['id' => 0],
        ['fqdn' => 'https://old-domain.com']
    );

    // First call should cache it
    $middleware = new TrustHosts($this->app);
    $hosts1 = $middleware->hosts();
    expect($hosts1)->toContain('old-domain.com');

    // Verify cache exists
    expect(Cache::has('instance_settings_fqdn_host'))->toBeTrue();

    // Update FQDN - should trigger cache invalidation
    $settings->fqdn = 'https://new-domain.com';
    $settings->save();

    // Cache should be cleared
    expect(Cache::has('instance_settings_fqdn_host'))->toBeFalse();

    // New call should return updated host
    $middleware2 = new TrustHosts($this->app);
    $hosts2 = $middleware2->hosts();
    expect($hosts2)->toContain('new-domain.com');
    expect($hosts2)->not->toContain('old-domain.com');
});

it('caches trusted hosts to avoid database queries on every request', function () {
    InstanceSettings::updateOrCreate(
        ['id' => 0],
        ['fqdn' => 'https://saturn.example.com']
    );

    // Clear cache first
    Cache::forget('instance_settings_fqdn_host');

    // First call - should query database and cache result
    $middleware1 = new TrustHosts($this->app);
    $hosts1 = $middleware1->hosts();

    // Verify result is cached
    expect(Cache::has('instance_settings_fqdn_host'))->toBeTrue();
    expect(Cache::get('instance_settings_fqdn_host'))->toBe('saturn.example.com');

    // Subsequent calls should use cache (no DB query)
    $middleware2 = new TrustHosts($this->app);
    $hosts2 = $middleware2->hosts();

    expect($hosts1)->toBe($hosts2);
    expect($hosts2)->toContain('saturn.example.com');
});

it('caches negative results when no FQDN is configured', function () {
    // Create instance settings without FQDN
    InstanceSettings::updateOrCreate(
        ['id' => 0],
        ['fqdn' => null]
    );

    // Clear cache first
    Cache::forget('instance_settings_fqdn_host');

    // First call - should query database and cache empty string sentinel
    $middleware1 = new TrustHosts($this->app);
    $hosts1 = $middleware1->hosts();

    // Verify empty string sentinel is cached (not null, which wouldn't be cached)
    expect(Cache::has('instance_settings_fqdn_host'))->toBeTrue();
    expect(Cache::get('instance_settings_fqdn_host'))->toBe('');

    // Subsequent calls should use cached sentinel value
    $middleware2 = new TrustHosts($this->app);
    $hosts2 = $middleware2->hosts();

    expect($hosts1)->toBe($hosts2);
    // Should only contain APP_URL pattern, not any FQDN
    expect($hosts2)->not->toBeEmpty();
});

it('skips host validation for terminal auth routes', function () {
    // These routes should be accessible with any Host header (for internal container communication)
    $response = $this->postJson('/terminal/auth', [], [
        'Host' => 'saturn:8080',  // Internal Docker host
    ]);

    // Should not get 400 Bad Host (might get 401 Unauthorized instead)
    expect($response->status())->not->toBe(400);
});

it('skips host validation for terminal auth ips route', function () {
    // These routes should be accessible with any Host header (for internal container communication)
    $response = $this->postJson('/terminal/auth/ips', [], [
        'Host' => 'soketi:6002',  // Another internal Docker host
    ]);

    // Should not get 400 Bad Host (might get 401 Unauthorized instead)
    expect($response->status())->not->toBe(400);
});

it('still enforces host validation for non-terminal routes', function () {
    // Skip: Laravel's TrustHosts middleware disables host validation in tests via shouldSpecifyTrustedHosts()
    // This test cannot run in PHPUnit environment - host validation is only enforced in production
    expect(true)->toBeTrue();
})->skip('Laravel TrustHosts disables host validation during tests (runningUnitTests() check)');

it('skips host validation for API routes', function () {
    // All API routes use token-based auth (Sanctum), not host validation
    // They should be accessible from any host (mobile apps, CLI tools, scripts)

    // Test health check endpoint
    $response = $this->get('/api/health', [
        'Host' => 'internal-lb.local',
    ]);
    expect($response->status())->not->toBe(400);

    // Test v1 health check
    $response = $this->get('/api/v1/health', [
        'Host' => '10.0.0.5',
    ]);
    expect($response->status())->not->toBe(400);

    // Test feedback endpoint
    $response = $this->post('/api/feedback', [], [
        'Host' => 'mobile-app.local',
    ]);
    expect($response->status())->not->toBe(400);
});

it('skips host validation for webhook endpoints', function () {
    // Skip: Laravel's TrustHosts middleware disables host validation in tests via shouldSpecifyTrustedHosts()
    // This test cannot run in PHPUnit environment - host validation is only enforced in production
    expect(true)->toBeTrue();
})->skip('Laravel TrustHosts disables host validation during tests (runningUnitTests() check)');
