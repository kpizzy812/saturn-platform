<?php

/**
 * Unit tests for GetProxyConfiguration action.
 *
 * Tests cover:
 * - NONE proxy type returns 'OK' immediately without SSH
 * - Class structure and AsAction trait usage
 */

use App\Actions\Proxy\GetProxyConfiguration;
use App\Models\Server;
use Lorisleiva\Actions\Concerns\AsAction;
use Mockery;

afterEach(fn () => Mockery::close());

// ─── Class structure ──────────────────────────────────────────────────────────

test('GetProxyConfiguration uses AsAction trait', function () {
    expect(in_array(AsAction::class, class_uses_recursive(GetProxyConfiguration::class)))->toBeTrue();
});

test('GetProxyConfiguration has handle method', function () {
    expect(method_exists(GetProxyConfiguration::class, 'handle'))->toBeTrue();
});

// ─── NONE proxy type ──────────────────────────────────────────────────────────

test('GetProxyConfiguration returns OK for NONE proxy type', function () {
    $server = Mockery::mock(Server::class)->makePartial();
    $server->shouldReceive('proxyType')->andReturn('NONE');

    $result = (new GetProxyConfiguration)->handle($server);

    expect($result)->toBe('OK');
});

test('GetProxyConfiguration returns OK for NONE proxy type with forceRegenerate true', function () {
    $server = Mockery::mock(Server::class)->makePartial();
    $server->shouldReceive('proxyType')->andReturn('NONE');

    $result = (new GetProxyConfiguration)->handle($server, forceRegenerate: true);

    expect($result)->toBe('OK');
});
