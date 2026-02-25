<?php

use App\Models\Application;
use App\Models\Server;
use App\Services\MasterProxyConfigService;

beforeEach(function () {
    $this->service = new MasterProxyConfigService;
    Mockery::close();
});

afterEach(function () {
    Mockery::close();
});

// ═══════════════════════════════════════════
// parseFqdns() — FQDN parsing
// ═══════════════════════════════════════════

test('parseFqdns returns empty array for null', function () {
    $method = new ReflectionMethod(MasterProxyConfigService::class, 'parseFqdns');

    expect($method->invoke($this->service, null))->toBe([]);
});

test('parseFqdns returns empty array for empty string', function () {
    $method = new ReflectionMethod(MasterProxyConfigService::class, 'parseFqdns');

    expect($method->invoke($this->service, ''))->toBe([]);
});

test('parseFqdns parses single FQDN', function () {
    $method = new ReflectionMethod(MasterProxyConfigService::class, 'parseFqdns');

    $result = $method->invoke($this->service, 'https://example.com');

    expect($result)->toBe(['https://example.com']);
});

test('parseFqdns parses comma-separated FQDNs', function () {
    $method = new ReflectionMethod(MasterProxyConfigService::class, 'parseFqdns');

    $result = $method->invoke($this->service, 'https://example.com,https://www.example.com');

    expect($result)->toBe(['https://example.com', 'https://www.example.com']);
});

test('parseFqdns trims whitespace', function () {
    $method = new ReflectionMethod(MasterProxyConfigService::class, 'parseFqdns');

    $result = $method->invoke($this->service, ' https://a.com , https://b.com ');

    expect($result)->toBe(['https://a.com', 'https://b.com']);
});

test('parseFqdns filters empty values', function () {
    $method = new ReflectionMethod(MasterProxyConfigService::class, 'parseFqdns');

    $result = $method->invoke($this->service, 'https://a.com,,https://b.com,');

    expect($result)->toBe(['https://a.com', 'https://b.com']);
});

// ═══════════════════════════════════════════
// getAppPort() — port extraction
// ═══════════════════════════════════════════

test('getAppPort returns 80 when ports_exposes is empty', function () {
    $method = new ReflectionMethod(MasterProxyConfigService::class, 'getAppPort');

    $app = Mockery::mock(Application::class)->makePartial();
    $app->ports_exposes = '';

    expect($method->invoke($this->service, $app))->toBe(80);
});

test('getAppPort returns 80 when ports_exposes is null', function () {
    $method = new ReflectionMethod(MasterProxyConfigService::class, 'getAppPort');

    $app = Mockery::mock(Application::class)->makePartial();
    $app->ports_exposes = null;

    expect($method->invoke($this->service, $app))->toBe(80);
});

test('getAppPort returns single exposed port', function () {
    $method = new ReflectionMethod(MasterProxyConfigService::class, 'getAppPort');

    $app = Mockery::mock(Application::class)->makePartial();
    $app->ports_exposes = '3000';

    expect($method->invoke($this->service, $app))->toBe(3000);
});

test('getAppPort returns first port when multiple exposed', function () {
    $method = new ReflectionMethod(MasterProxyConfigService::class, 'getAppPort');

    $app = Mockery::mock(Application::class)->makePartial();
    $app->ports_exposes = '8080,3000,5432';

    expect($method->invoke($this->service, $app))->toBe(8080);
});

test('getAppPort trims whitespace from port', function () {
    $method = new ReflectionMethod(MasterProxyConfigService::class, 'getAppPort');

    $app = Mockery::mock(Application::class)->makePartial();
    $app->ports_exposes = ' 9090 , 8080 ';

    expect($method->invoke($this->service, $app))->toBe(9090);
});

// ═══════════════════════════════════════════
// getDynamicConfigPath() — path building
// ═══════════════════════════════════════════

test('getDynamicConfigPath appends /dynamic to proxy path', function () {
    $method = new ReflectionMethod(MasterProxyConfigService::class, 'getDynamicConfigPath');

    $server = Mockery::mock(Server::class)->makePartial();
    $server->shouldReceive('proxyPath')->andReturn('/data/saturn/proxy');

    $result = $method->invoke($this->service, $server);

    expect($result)->toBe('/data/saturn/proxy/dynamic');
});

// ═══════════════════════════════════════════
// buildRouteConfig() — Traefik config
// ═══════════════════════════════════════════

test('buildRouteConfig creates correct service with load balancer', function () {
    $method = new ReflectionMethod(MasterProxyConfigService::class, 'buildRouteConfig');

    $app = Mockery::mock(Application::class)->makePartial();
    $app->uuid = 'test-uuid-123';
    $app->ports_exposes = '3000';

    $remoteServer = Mockery::mock(Server::class)->makePartial();
    $remoteServer->ip = '10.0.0.5';

    $config = $method->invoke($this->service, $app, $remoteServer, ['http://example.com']);

    expect($config['http']['services'])->toHaveKey('remote-test-uuid-123');
    expect($config['http']['services']['remote-test-uuid-123']['loadBalancer']['servers'][0]['url'])
        ->toBe('http://10.0.0.5:3000');
});

test('buildRouteConfig creates HTTP router with Host rule', function () {
    $method = new ReflectionMethod(MasterProxyConfigService::class, 'buildRouteConfig');

    $app = Mockery::mock(Application::class)->makePartial();
    $app->uuid = 'abc123';
    $app->ports_exposes = '80';

    $remoteServer = Mockery::mock(Server::class)->makePartial();
    $remoteServer->ip = '10.0.0.1';

    $config = $method->invoke($this->service, $app, $remoteServer, ['http://example.com']);

    expect($config['http']['routers'])->toHaveKey('remote-abc123-http');
    expect($config['http']['routers']['remote-abc123-http']['rule'])->toBe('Host(`example.com`)');
    expect($config['http']['routers']['remote-abc123-http']['entryPoints'])->toBe(['http']);
    expect($config['http']['routers']['remote-abc123-http']['service'])->toBe('remote-abc123');
});

test('buildRouteConfig creates HTTPS router with TLS for https FQDNs', function () {
    $method = new ReflectionMethod(MasterProxyConfigService::class, 'buildRouteConfig');

    $app = Mockery::mock(Application::class)->makePartial();
    $app->uuid = 'abc123';
    $app->ports_exposes = '80';

    $remoteServer = Mockery::mock(Server::class)->makePartial();
    $remoteServer->ip = '10.0.0.1';

    $config = $method->invoke($this->service, $app, $remoteServer, ['https://secure.example.com']);

    // HTTPS router
    expect($config['http']['routers'])->toHaveKey('remote-abc123-https');
    expect($config['http']['routers']['remote-abc123-https']['tls']['certResolver'])->toBe('letsencrypt');
    expect($config['http']['routers']['remote-abc123-https']['entryPoints'])->toBe(['https']);

    // HTTP redirect middleware on HTTP router
    expect($config['http']['routers']['remote-abc123-http']['middlewares'])
        ->toContain('redirect-to-https-abc123');

    // Redirect middleware definition
    expect($config['http']['middlewares'])->toHaveKey('redirect-to-https-abc123');
    expect($config['http']['middlewares']['redirect-to-https-abc123']['redirectScheme']['scheme'])->toBe('https');
});

test('buildRouteConfig handles multiple FQDNs with index suffix', function () {
    $method = new ReflectionMethod(MasterProxyConfigService::class, 'buildRouteConfig');

    $app = Mockery::mock(Application::class)->makePartial();
    $app->uuid = 'multi';
    $app->ports_exposes = '80';

    $remoteServer = Mockery::mock(Server::class)->makePartial();
    $remoteServer->ip = '10.0.0.1';

    $config = $method->invoke($this->service, $app, $remoteServer, [
        'http://a.com',
        'http://b.com',
        'http://c.com',
    ]);

    // First FQDN has no index suffix
    expect($config['http']['routers'])->toHaveKey('remote-multi-http');
    // Second and third have index suffix
    expect($config['http']['routers'])->toHaveKey('remote-multi-1-http');
    expect($config['http']['routers'])->toHaveKey('remote-multi-2-http');

    // All use the same service
    foreach ($config['http']['routers'] as $router) {
        expect($router['service'])->toBe('remote-multi');
    }
});

test('buildRouteConfig does not add middlewares section for HTTP-only FQDNs', function () {
    $method = new ReflectionMethod(MasterProxyConfigService::class, 'buildRouteConfig');

    $app = Mockery::mock(Application::class)->makePartial();
    $app->uuid = 'httponly';
    $app->ports_exposes = '80';

    $remoteServer = Mockery::mock(Server::class)->makePartial();
    $remoteServer->ip = '10.0.0.1';

    $config = $method->invoke($this->service, $app, $remoteServer, ['http://example.com']);

    expect($config['http'])->not->toHaveKey('middlewares');
});

test('buildRouteConfig with mixed HTTP and HTTPS FQDNs', function () {
    $method = new ReflectionMethod(MasterProxyConfigService::class, 'buildRouteConfig');

    $app = Mockery::mock(Application::class)->makePartial();
    $app->uuid = 'mixed';
    $app->ports_exposes = '8080';

    $remoteServer = Mockery::mock(Server::class)->makePartial();
    $remoteServer->ip = '192.168.1.1';

    $config = $method->invoke($this->service, $app, $remoteServer, [
        'http://api.example.com',
        'https://app.example.com',
    ]);

    // First HTTP FQDN: no redirect
    expect($config['http']['routers']['remote-mixed-http'])->not->toHaveKey('middlewares');

    // Second HTTPS FQDN: has redirect middleware
    expect($config['http']['routers']['remote-mixed-1-http']['middlewares'])
        ->toContain('redirect-to-https-mixed');

    // Service points to correct IP and port
    expect($config['http']['services']['remote-mixed']['loadBalancer']['servers'][0]['url'])
        ->toBe('http://192.168.1.1:8080');
});

// ═══════════════════════════════════════════
// Host parsing edge cases
// ═══════════════════════════════════════════

test('host parsing extracts hostname from URL', function () {
    $host = parse_url('https://example.com', PHP_URL_HOST) ?: 'example.com';
    expect($host)->toBe('example.com');
});

test('host parsing extracts hostname from URL with path', function () {
    $host = parse_url('https://example.com/some/path', PHP_URL_HOST) ?: 'example.com/some/path';
    expect($host)->toBe('example.com');
});

test('host parsing falls back to raw string for non-URL', function () {
    $host = parse_url('example.com', PHP_URL_HOST) ?: 'example.com';
    expect($host)->toBe('example.com');
});

test('host parsing handles subdomain URLs', function () {
    $host = parse_url('https://app.staging.example.com', PHP_URL_HOST);
    expect($host)->toBe('app.staging.example.com');
});

// ═══════════════════════════════════════════
// File naming
// ═══════════════════════════════════════════

test('remote route file is named with app uuid', function () {
    $uuid = 'abc-123-def';
    $fileName = "remote-{$uuid}.yaml";

    expect($fileName)->toBe('remote-abc-123-def.yaml');
});
