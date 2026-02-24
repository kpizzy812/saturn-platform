<?php

use App\Services\MasterProxyConfigService;

test('parseFqdns splits comma-separated domains', function () {
    $service = new MasterProxyConfigService;
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseFqdns');

    $result = $method->invoke($service, 'https://app.saturn.io,http://app2.saturn.io');

    expect($result)->toBe(['https://app.saturn.io', 'http://app2.saturn.io']);
});

test('parseFqdns handles empty input', function () {
    $service = new MasterProxyConfigService;
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseFqdns');

    expect($method->invoke($service, null))->toBe([]);
    expect($method->invoke($service, ''))->toBe([]);
});

test('parseFqdns trims whitespace', function () {
    $service = new MasterProxyConfigService;
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseFqdns');

    $result = $method->invoke($service, ' https://app.saturn.io , http://app2.saturn.io ');

    expect($result)->toBe(['https://app.saturn.io', 'http://app2.saturn.io']);
});

test('getAppPort returns first port from ports_exposes', function () {
    $service = new MasterProxyConfigService;
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('getAppPort');

    $app = Mockery::mock(\App\Models\Application::class)->makePartial();
    $app->ports_exposes = '3000,8080';

    expect($method->invoke($service, $app))->toBe(3000);
});

test('getAppPort returns 80 for empty ports', function () {
    $service = new MasterProxyConfigService;
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('getAppPort');

    $app = Mockery::mock(\App\Models\Application::class)->makePartial();
    $app->ports_exposes = '';

    expect($method->invoke($service, $app))->toBe(80);
});

test('buildRouteConfig generates valid Traefik config', function () {
    $service = new MasterProxyConfigService;
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('buildRouteConfig');

    $app = Mockery::mock(\App\Models\Application::class)->makePartial();
    $app->uuid = 'test-app-uuid';
    $app->ports_exposes = '3000';

    $server = Mockery::mock(\App\Models\Server::class)->makePartial();
    $server->ip = '10.0.0.2';

    $fqdns = ['https://myapp.saturn.io'];

    $config = $method->invoke($service, $app, $server, $fqdns);

    // Should have http section
    expect($config)->toHaveKey('http');
    expect($config['http'])->toHaveKey('routers');
    expect($config['http'])->toHaveKey('services');

    // Should have remote service pointing to server IP
    $serviceName = 'remote-test-app-uuid';
    expect($config['http']['services'][$serviceName]['loadBalancer']['servers'][0]['url'])
        ->toBe('http://10.0.0.2:3000');

    // Should have HTTP router
    expect($config['http']['routers'])->toHaveKey('remote-test-app-uuid-http');

    // Should have HTTPS router (since fqdn is https)
    expect($config['http']['routers'])->toHaveKey('remote-test-app-uuid-https');

    // HTTPS router should have certResolver
    expect($config['http']['routers']['remote-test-app-uuid-https']['tls']['certResolver'])
        ->toBe('letsencrypt');

    // Should have redirect middleware
    expect($config['http'])->toHaveKey('middlewares');
});

test('buildRouteConfig generates HTTP-only config for http domains', function () {
    $service = new MasterProxyConfigService;
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('buildRouteConfig');

    $app = Mockery::mock(\App\Models\Application::class)->makePartial();
    $app->uuid = 'http-app-uuid';
    $app->ports_exposes = '80';

    $server = Mockery::mock(\App\Models\Server::class)->makePartial();
    $server->ip = '10.0.0.3';

    $fqdns = ['http://myapp.saturn.io'];

    $config = $method->invoke($service, $app, $server, $fqdns);

    // Should NOT have HTTPS router
    expect($config['http']['routers'])->not->toHaveKey('remote-http-app-uuid-https');

    // Should NOT have middleware section
    expect($config['http'])->not->toHaveKey('middlewares');
});
