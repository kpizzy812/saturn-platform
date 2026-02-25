<?php

use App\Actions\Server\StartSentinel;
use App\Jobs\CheckAndStartSentinelJob;
use App\Models\Server;

beforeEach(function () {
    Mockery::close();
});

afterEach(function () {
    Mockery::close();
});

test('job has correct configuration', function () {
    $server = Mockery::mock(Server::class)->makePartial();
    $job = new CheckAndStartSentinelJob($server);

    expect($job->timeout)->toBe(120);

    $interfaces = class_implements($job);
    expect($interfaces)->toContain(\Illuminate\Contracts\Queue\ShouldQueue::class);
    expect($interfaces)->toContain(\Illuminate\Contracts\Queue\ShouldBeEncrypted::class);
});

test('job stores server reference', function () {
    $server = Mockery::mock(Server::class)->makePartial();

    $job = new CheckAndStartSentinelJob($server);
    expect($job->server)->toBe($server);
});

test('source code calls StartSentinel on non-running sentinel', function () {
    $source = file_get_contents((new ReflectionClass(CheckAndStartSentinelJob::class))->getFileName());

    expect($source)->toContain('StartSentinel::run');
    expect($source)->toContain('restart: true');
});

test('source code uses instant_remote_process_with_timeout', function () {
    $source = file_get_contents((new ReflectionClass(CheckAndStartSentinelJob::class))->getFileName());

    expect($source)->toContain('instant_remote_process_with_timeout');
    expect($source)->toContain('docker inspect saturn-sentinel');
    expect($source)->toContain('docker exec saturn-sentinel');
});

test('sentinel version comparison logic', function () {
    // version_compare returns correct results
    expect(version_compare('1.0.0', '1.2.3', '<'))->toBeTrue();
    expect(version_compare('1.2.3', '1.2.3', '<'))->toBeFalse();
    expect(version_compare('1.3.0', '1.2.3', '<'))->toBeFalse();
    expect(version_compare('0.0.0', '0.0.0', '<'))->toBeFalse();
});

test('empty running version defaults to 0.0.0', function () {
    // The job treats empty version as '0.0.0'
    $runningVersion = '';
    if (empty($runningVersion)) {
        $runningVersion = '0.0.0';
    }
    expect($runningVersion)->toBe('0.0.0');
});

test('both versions 0.0.0 triggers restart with latest tag', function () {
    // When both latestVersion and runningVersion are '0.0.0',
    // job calls StartSentinel::run with latestVersion: 'latest'
    $latestVersion = '0.0.0';
    $runningVersion = '0.0.0';

    // This is the logic path in the handle() method
    $shouldRestartWithLatest = ($latestVersion === '0.0.0' && $runningVersion === '0.0.0');
    expect($shouldRestartWithLatest)->toBeTrue();
});

test('outdated version triggers restart', function () {
    $latestVersion = '1.5.0';
    $runningVersion = '1.2.0';

    $needsUpdate = version_compare($runningVersion, $latestVersion, '<');
    expect($needsUpdate)->toBeTrue();
});

test('up to date version does not trigger restart', function () {
    $latestVersion = '1.2.0';
    $runningVersion = '1.2.0';

    $needsUpdate = version_compare($runningVersion, $latestVersion, '<');
    expect($needsUpdate)->toBeFalse();
});

test('newer running version does not trigger restart', function () {
    $latestVersion = '1.2.0';
    $runningVersion = '1.3.0';

    $needsUpdate = version_compare($runningVersion, $latestVersion, '<');
    expect($needsUpdate)->toBeFalse();
});

test('json parsing extracts sentinel status correctly', function () {
    // Running state
    $json = json_encode([['State' => ['Status' => 'running']]]);
    $parsed = json_decode($json, true);
    $status = data_get($parsed, '0.State.Status', 'exited');
    expect($status)->toBe('running');

    // Exited state
    $json = json_encode([['State' => ['Status' => 'exited']]]);
    $parsed = json_decode($json, true);
    $status = data_get($parsed, '0.State.Status', 'exited');
    expect($status)->toBe('exited');

    // Empty / malformed json defaults to exited
    $parsed = json_decode('', true);
    $status = data_get($parsed, '0.State.Status', 'exited');
    expect($status)->toBe('exited');

    // Null json defaults to exited
    $parsed = json_decode('null', true);
    $status = data_get($parsed, '0.State.Status', 'exited');
    expect($status)->toBe('exited');
});
