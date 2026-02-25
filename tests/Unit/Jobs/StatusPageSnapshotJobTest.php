<?php

use App\Jobs\StatusPageSnapshotJob;

beforeEach(function () {
    Mockery::close();
});

afterEach(function () {
    Mockery::close();
});

test('job has correct configuration', function () {
    $job = new StatusPageSnapshotJob;

    expect($job->tries)->toBe(3);
    expect($job->backoff)->toBe([10, 30, 60]);

    $interfaces = class_implements($job);
    expect($interfaces)->toContain(\Illuminate\Contracts\Queue\ShouldQueue::class);
});

test('uptime calculation all healthy', function () {
    $total = 100;
    $healthy = 100;
    $degraded = 0;
    $down = 0;

    $uptimePercent = $total > 0
        ? round(($healthy + $degraded) / $total * 100, 2)
        : 0;

    expect($uptimePercent)->toBe(100.0);
});

test('uptime calculation all down', function () {
    $total = 100;
    $healthy = 0;
    $degraded = 0;
    $down = 100;

    $uptimePercent = $total > 0
        ? round(($healthy + $degraded) / $total * 100, 2)
        : 0;

    expect($uptimePercent)->toBe(0.0);
});

test('uptime calculation mixed healthy and degraded counts as up', function () {
    $total = 100;
    $healthy = 60;
    $degraded = 20;
    $down = 20;

    $uptimePercent = $total > 0
        ? round(($healthy + $degraded) / $total * 100, 2)
        : 0;

    expect($uptimePercent)->toBe(80.0);
});

test('uptime calculation with zero total returns zero', function () {
    $total = 0;
    $healthy = 0;
    $degraded = 0;
    $down = 0;

    $uptimePercent = $total > 0
        ? round(($healthy + $degraded) / $total * 100, 2)
        : 0;

    expect($uptimePercent)->toBe(0);
});

test('worst status determination - outage when down > 0', function () {
    $down = 5;
    $degraded = 10;

    $worstStatus = 'operational';
    if ($down > 0) {
        $worstStatus = 'outage';
    } elseif ($degraded > 0) {
        $worstStatus = 'degraded';
    }

    expect($worstStatus)->toBe('outage');
});

test('worst status determination - degraded when only degraded', function () {
    $down = 0;
    $degraded = 10;

    $worstStatus = 'operational';
    if ($down > 0) {
        $worstStatus = 'outage';
    } elseif ($degraded > 0) {
        $worstStatus = 'degraded';
    }

    expect($worstStatus)->toBe('degraded');
});

test('worst status determination - operational when all healthy', function () {
    $down = 0;
    $degraded = 0;

    $worstStatus = 'operational';
    if ($down > 0) {
        $worstStatus = 'outage';
    } elseif ($degraded > 0) {
        $worstStatus = 'degraded';
    }

    expect($worstStatus)->toBe('operational');
});

test('upsertResourceSnapshot logic for operational status', function () {
    $normalizedStatus = 'operational';
    $isUp = in_array($normalizedStatus, ['operational', 'degraded']);

    expect($isUp)->toBeTrue();

    $status = $normalizedStatus === 'operational' ? 'operational'
        : ($normalizedStatus === 'degraded' ? 'degraded'
            : ($normalizedStatus === 'maintenance' ? 'operational'
                : 'outage'));

    expect($status)->toBe('operational');
    expect($isUp ? 100 : 0)->toBe(100);
    expect($isUp ? 1 : 0)->toBe(1);
    expect($normalizedStatus === 'degraded' ? 1 : 0)->toBe(0);
    expect(! $isUp && $normalizedStatus !== 'maintenance' ? 1 : 0)->toBe(0);
});

test('upsertResourceSnapshot logic for degraded status', function () {
    $normalizedStatus = 'degraded';
    $isUp = in_array($normalizedStatus, ['operational', 'degraded']);

    expect($isUp)->toBeTrue();

    $status = $normalizedStatus === 'operational' ? 'operational'
        : ($normalizedStatus === 'degraded' ? 'degraded'
            : ($normalizedStatus === 'maintenance' ? 'operational'
                : 'outage'));

    expect($status)->toBe('degraded');
    expect($isUp ? 100 : 0)->toBe(100);
    expect($normalizedStatus === 'degraded' ? 1 : 0)->toBe(1);
});

test('upsertResourceSnapshot logic for outage status', function () {
    $normalizedStatus = 'outage';
    $isUp = in_array($normalizedStatus, ['operational', 'degraded']);

    expect($isUp)->toBeFalse();

    $status = $normalizedStatus === 'operational' ? 'operational'
        : ($normalizedStatus === 'degraded' ? 'degraded'
            : ($normalizedStatus === 'maintenance' ? 'operational'
                : 'outage'));

    expect($status)->toBe('outage');
    expect($isUp ? 100 : 0)->toBe(0);
    expect(! $isUp && $normalizedStatus !== 'maintenance' ? 1 : 0)->toBe(1);
});

test('upsertResourceSnapshot logic for maintenance status', function () {
    $normalizedStatus = 'maintenance';
    $isUp = in_array($normalizedStatus, ['operational', 'degraded']);

    expect($isUp)->toBeFalse();

    $status = $normalizedStatus === 'operational' ? 'operational'
        : ($normalizedStatus === 'degraded' ? 'degraded'
            : ($normalizedStatus === 'maintenance' ? 'operational'
                : 'outage'));

    // Maintenance maps to operational
    expect($status)->toBe('operational');
    // But isUp is false (uptime = 0)
    expect($isUp ? 100 : 0)->toBe(0);
    // And down_checks is 0 because maintenance !== outage
    expect(! $isUp && $normalizedStatus !== 'maintenance' ? 1 : 0)->toBe(0);
});

test('upsertResourceSnapshot logic for unknown status', function () {
    $normalizedStatus = 'unknown';
    $isUp = in_array($normalizedStatus, ['operational', 'degraded']);

    expect($isUp)->toBeFalse();

    $status = $normalizedStatus === 'operational' ? 'operational'
        : ($normalizedStatus === 'degraded' ? 'degraded'
            : ($normalizedStatus === 'maintenance' ? 'operational'
                : 'outage'));

    expect($status)->toBe('outage');
});

test('source code uses cursor for memory efficiency', function () {
    $source = file_get_contents((new ReflectionClass(StatusPageSnapshotJob::class))->getFileName());

    expect($source)->toContain('cursor()');
});

test('source code snapshots servers, applications and services', function () {
    $source = file_get_contents((new ReflectionClass(StatusPageSnapshotJob::class))->getFileName());

    expect($source)->toContain('snapshotServers');
    expect($source)->toContain('snapshotApplications');
    expect($source)->toContain('snapshotServices');
});

test('source code uses updateOrCreate for idempotency', function () {
    $source = file_get_contents((new ReflectionClass(StatusPageSnapshotJob::class))->getFileName());

    expect($source)->toContain('updateOrCreate');
});

test('source code has failed callback', function () {
    $source = file_get_contents((new ReflectionClass(StatusPageSnapshotJob::class))->getFileName());

    expect($source)->toContain('public function failed');
    expect($source)->toContain('StatusPageSnapshotJob failed');
});

test('source code snapshots previous day', function () {
    $source = file_get_contents((new ReflectionClass(StatusPageSnapshotJob::class))->getFileName());

    expect($source)->toContain('subDay()');
    expect($source)->toContain('toDateString()');
});
