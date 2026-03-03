<?php

/**
 * Unit tests for StartSentinel and StopSentinel actions.
 *
 * These tests verify class structure, trait usage, and early-exit conditions
 * without requiring a real SSH connection or database.
 */

use App\Actions\Server\StartSentinel;
use App\Actions\Server\StopSentinel;
use App\Models\Server;
use Lorisleiva\Actions\Concerns\AsAction;
use Mockery;

uses(Tests\TestCase::class);

afterEach(fn () => Mockery::close());

// ─── StartSentinel: class structure ──────────────────────────────────────────

test('StartSentinel uses AsAction trait', function () {
    expect(in_array(AsAction::class, class_uses_recursive(StartSentinel::class)))->toBeTrue();
});

test('StartSentinel has handle method', function () {
    expect(method_exists(StartSentinel::class, 'handle'))->toBeTrue();
});

// ─── StartSentinel: swarm / build server returns early ───────────────────────

test('StartSentinel returns early for swarm server without SSH call', function () {
    $server = Mockery::mock(Server::class)->makePartial();
    $server->shouldReceive('isSwarm')->andReturn(true);
    $server->shouldReceive('isBuildServer')->andReturn(false);

    // handle() returns null (void) early — no RuntimeException thrown
    $action = new StartSentinel;
    $result = $action->handle($server);

    expect($result)->toBeNull();
});

test('StartSentinel returns early for build server without SSH call', function () {
    $server = Mockery::mock(Server::class)->makePartial();
    $server->shouldReceive('isSwarm')->andReturn(false);
    $server->shouldReceive('isBuildServer')->andReturn(true);

    $result = (new StartSentinel)->handle($server);

    expect($result)->toBeNull();
});

// ─── StartSentinel: throws when FQDN / endpoint not set ──────────────────────

test('StartSentinel throws RuntimeException when sentinel endpoint is not set', function () {
    $settings = new stdClass;
    $settings->sentinel_metrics_history_days = 7;
    $settings->sentinel_metrics_refresh_rate_seconds = 5;
    $settings->sentinel_push_interval_seconds = 60;
    $settings->sentinel_token = 'tok';
    $settings->sentinel_custom_url = null; // endpoint not set
    $settings->is_sentinel_debug_enabled = false;

    $server = Mockery::mock(Server::class)->makePartial();
    $server->shouldReceive('isSwarm')->andReturn(false);
    $server->shouldReceive('isBuildServer')->andReturn(false);
    // data_get() will pull from $server->settings
    $server->settings = $settings;

    expect(fn () => (new StartSentinel)->handle($server))
        ->toThrow(\RuntimeException::class, 'You should set FQDN in Instance Settings.');
});

// ─── StopSentinel: class structure ───────────────────────────────────────────

test('StopSentinel uses AsAction trait', function () {
    expect(in_array(AsAction::class, class_uses_recursive(StopSentinel::class)))->toBeTrue();
});

test('StopSentinel has handle method', function () {
    expect(method_exists(StopSentinel::class, 'handle'))->toBeTrue();
});
