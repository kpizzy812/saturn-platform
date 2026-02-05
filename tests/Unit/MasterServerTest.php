<?php

use App\Models\Server;
use App\Models\ServerSetting;

test('ServerSetting has is_master_server in fillable', function () {
    $setting = new ServerSetting;
    expect($setting->getFillable())->toContain('is_master_server');
});

test('ServerSetting casts is_master_server to boolean', function () {
    $setting = new ServerSetting;
    $casts = $setting->getCasts();
    expect($casts['is_master_server'])->toBe('boolean');
});

test('Server has isMasterServer method', function () {
    expect(method_exists(Server::class, 'isMasterServer'))->toBeTrue();
});

test('Server has static masterServer method', function () {
    expect(method_exists(Server::class, 'masterServer'))->toBeTrue();
});

test('isMasterServer returns bool', function () {
    $server = Mockery::mock(Server::class)->makePartial();
    $settings = Mockery::mock(ServerSetting::class)->makePartial();
    $settings->is_master_server = false;
    $server->shouldReceive('getAttribute')
        ->with('settings')
        ->andReturn($settings);

    expect($server->isMasterServer())->toBeFalse();

    $settings->is_master_server = true;
    expect($server->isMasterServer())->toBeTrue();
});

test('proxy config generates HTTP-only for remote servers', function () {
    // Check that the generateDefaultProxyConfiguration function
    // uses isMasterServer() to decide ports
    $proxyHelperPath = base_path('bootstrap/helpers/proxy.php');
    $content = file_get_contents($proxyHelperPath);

    expect($content)->toContain('$isMasterServer = $server->isMasterServer()');
    expect($content)->toContain("'80:80'");
    expect($content)->toContain("'--entrypoints.https.address=:443'");
});

test('network helper uses resolveWildcardDomain', function () {
    $networkHelperPath = base_path('bootstrap/helpers/network.php');
    $content = file_get_contents($networkHelperPath);

    expect($content)->toContain('function resolveWildcardDomain');
    expect($content)->toContain('Server::masterServer()');
});
