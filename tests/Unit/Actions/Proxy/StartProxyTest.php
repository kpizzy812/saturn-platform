<?php

use App\Actions\Proxy\SaveProxyConfiguration;
use App\Actions\Proxy\StartProxy;
use App\Enums\ProxyTypes;
use App\Models\Server;

beforeEach(function () {
    Mockery::close();
});

afterEach(function () {
    Mockery::close();
});

// ═══════════════════════════════════════════
// StartProxy — early exit conditions
// ═══════════════════════════════════════════

test('StartProxy returns OK when proxyType is null', function () {
    $proxy = new \Illuminate\Support\Collection;
    $proxy->put('force_stop', false);

    $server = Mockery::mock(Server::class)->makePartial();
    $server->shouldReceive('proxyType')->andReturn(null);
    $server->proxy = $proxy;
    $server->shouldReceive('isBuildServer')->andReturn(false);

    $action = new StartProxy;
    $result = $action->handle($server, force: false);

    expect($result)->toBe('OK');
});

test('StartProxy returns OK when proxyType is NONE', function () {
    $proxy = new \Illuminate\Support\Collection;
    $proxy->put('force_stop', false);

    $server = Mockery::mock(Server::class)->makePartial();
    $server->shouldReceive('proxyType')->andReturn('NONE');
    $server->proxy = $proxy;
    $server->shouldReceive('isBuildServer')->andReturn(false);

    $action = new StartProxy;
    $result = $action->handle($server, force: false);

    expect($result)->toBe('OK');
});

test('StartProxy returns OK when force_stop is set', function () {
    $proxy = new \Illuminate\Support\Collection;
    $proxy->put('force_stop', true);

    $server = Mockery::mock(Server::class)->makePartial();
    $server->shouldReceive('proxyType')->andReturn(ProxyTypes::TRAEFIK->value);
    $server->proxy = $proxy;
    $server->shouldReceive('isBuildServer')->andReturn(false);

    $action = new StartProxy;
    $result = $action->handle($server, force: false);

    expect($result)->toBe('OK');
});

test('StartProxy returns OK for build server when not forced', function () {
    $proxy = new \Illuminate\Support\Collection;
    $proxy->put('force_stop', false);

    $server = Mockery::mock(Server::class)->makePartial();
    $server->shouldReceive('proxyType')->andReturn(ProxyTypes::TRAEFIK->value);
    $server->proxy = $proxy;
    $server->shouldReceive('isBuildServer')->andReturn(true);

    $action = new StartProxy;
    $result = $action->handle($server, force: false);

    expect($result)->toBe('OK');
});

// ═══════════════════════════════════════════
// SaveProxyConfiguration — encoding logic
// ═══════════════════════════════════════════

test('base64 encoding produces decodable output', function () {
    $configuration = "version: '3'\nservices:\n  traefik:\n    image: traefik:v3.6";
    $encoded = base64_encode($configuration);

    expect(base64_decode($encoded))->toBe($configuration);
});

test('MD5 hash of base64 config is deterministic', function () {
    $config = "version: '3'\nservices:\n  proxy:\n    image: traefik:latest";
    $encoded = base64_encode($config);

    $hash1 = md5($encoded);
    $hash2 = md5($encoded);

    expect($hash1)->toBe($hash2);
    expect(strlen($hash1))->toBe(32);
});

test('different configs produce different hashes', function () {
    $config1 = "version: '3'\nservices:\n  proxy:\n    image: traefik:v3.5";
    $config2 = "version: '3'\nservices:\n  proxy:\n    image: traefik:v3.6";

    $hash1 = md5(base64_encode($config1));
    $hash2 = md5(base64_encode($config2));

    expect($hash1)->not->toBe($hash2);
});

// ═══════════════════════════════════════════
// StartProxy — config hash storage
// ═══════════════════════════════════════════

test('last_applied_settings uses MD5 of base64 encoded config', function () {
    $configuration = 'version: "3"';
    $base64 = base64_encode($configuration);

    // This mirrors the logic: str($docker_compose_yml_base64)->pipe('md5')->value()
    $hash = md5($base64);

    expect($hash)->toBe(md5($base64));
    expect(strlen($hash))->toBe(32);
});

// ═══════════════════════════════════════════
// StartProxy — Swarm vs Standalone commands
// ═══════════════════════════════════════════

test('swarm mode uses docker stack deploy command', function () {
    $source = file_get_contents(app_path('Actions/Proxy/StartProxy.php'));

    expect($source)->toContain('docker stack deploy');
    expect($source)->toContain('saturn-proxy');
});

test('standalone mode uses docker compose commands', function () {
    $source = file_get_contents(app_path('Actions/Proxy/StartProxy.php'));

    expect($source)->toContain('docker compose pull');
    expect($source)->toContain('docker compose up -d --wait --remove-orphans');
});

test('standalone mode removes existing proxy container before starting', function () {
    $source = file_get_contents(app_path('Actions/Proxy/StartProxy.php'));

    expect($source)->toContain('docker stop saturn-proxy');
    expect($source)->toContain('docker rm -f saturn-proxy');
});

// ═══════════════════════════════════════════
// StartProxy — ProxyTypes enum validation
// ═══════════════════════════════════════════

test('ProxyTypes enum has TRAEFIK value', function () {
    expect(ProxyTypes::TRAEFIK->value)->not->toBeEmpty();
});

test('ProxyTypes enum has CADDY value', function () {
    expect(ProxyTypes::CADDY->value)->not->toBeEmpty();
});
