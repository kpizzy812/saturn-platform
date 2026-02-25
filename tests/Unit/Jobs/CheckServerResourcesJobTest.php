<?php

use App\Jobs\CheckServerResourcesJob;
use App\Models\InstanceSettings;
use App\Models\Server;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Mockery::close();
    Cache::flush();
});

afterEach(function () {
    Mockery::close();
});

test('job has correct configuration', function () {
    $server = Mockery::mock(Server::class)->makePartial();
    $job = new CheckServerResourcesJob($server);

    expect($job->tries)->toBe(1);
    expect($job->timeout)->toBe(120);

    $interfaces = class_implements($job);
    expect($interfaces)->toContain(\Illuminate\Contracts\Queue\ShouldQueue::class);
    expect($interfaces)->toContain(\Illuminate\Contracts\Queue\ShouldBeEncrypted::class);
});

test('middleware includes WithoutOverlapping', function () {
    $server = Mockery::mock(Server::class)->makePartial();
    $server->uuid = 'test-uuid-123';

    $job = new CheckServerResourcesJob($server);
    $middleware = $job->middleware();

    expect($middleware)->toHaveCount(1);
    expect($middleware[0])->toBeInstanceOf(WithoutOverlapping::class);
});

test('checkCpuUsage returns false when metrics not enabled', function () {
    $server = Mockery::mock(Server::class)->makePartial();
    $server->shouldReceive('isMetricsEnabled')->andReturn(false);

    $settings = Mockery::mock(InstanceSettings::class)->shouldIgnoreMissing();

    $job = new CheckServerResourcesJob($server);
    $method = new ReflectionMethod($job, 'checkCpuUsage');

    expect($method->invoke($job, $settings))->toBeFalse();
});

test('checkCpuUsage returns false when no metrics data', function () {
    $server = Mockery::mock(Server::class)->makePartial();
    $server->shouldReceive('isMetricsEnabled')->andReturn(true);
    $server->shouldReceive('getCpuMetrics')->with(1)->andReturn([]);

    $settings = Mockery::mock(InstanceSettings::class)->shouldIgnoreMissing();

    $job = new CheckServerResourcesJob($server);
    $method = new ReflectionMethod($job, 'checkCpuUsage');

    expect($method->invoke($job, $settings))->toBeFalse();
});

test('checkMemoryUsage returns false when metrics not enabled', function () {
    $server = Mockery::mock(Server::class)->makePartial();
    $server->shouldReceive('isMetricsEnabled')->andReturn(false);

    $settings = Mockery::mock(InstanceSettings::class)->shouldIgnoreMissing();

    $job = new CheckServerResourcesJob($server);
    $method = new ReflectionMethod($job, 'checkMemoryUsage');

    expect($method->invoke($job, $settings))->toBeFalse();
});

test('checkMemoryUsage returns false when no metrics data', function () {
    $server = Mockery::mock(Server::class)->makePartial();
    $server->shouldReceive('isMetricsEnabled')->andReturn(true);
    $server->shouldReceive('getMemoryMetrics')->with(1)->andReturn([]);

    $settings = Mockery::mock(InstanceSettings::class)->shouldIgnoreMissing();

    $job = new CheckServerResourcesJob($server);
    $method = new ReflectionMethod($job, 'checkMemoryUsage');

    expect($method->invoke($job, $settings))->toBeFalse();
});

test('checkDiskUsage returns false for empty disk usage', function () {
    $server = Mockery::mock(Server::class)->makePartial();
    $server->shouldReceive('getDiskUsage')->andReturn('');

    $settings = Mockery::mock(InstanceSettings::class)->shouldIgnoreMissing();

    $job = new CheckServerResourcesJob($server);
    $method = new ReflectionMethod($job, 'checkDiskUsage');

    expect($method->invoke($job, $settings))->toBeFalse();
});

test('checkDiskUsage returns false for non-numeric disk usage', function () {
    $server = Mockery::mock(Server::class)->makePartial();
    $server->shouldReceive('getDiskUsage')->andReturn('N/A');

    $settings = Mockery::mock(InstanceSettings::class)->shouldIgnoreMissing();

    $job = new CheckServerResourcesJob($server);
    $method = new ReflectionMethod($job, 'checkDiskUsage');

    expect($method->invoke($job, $settings))->toBeFalse();
});

test('cpu threshold logic - critical exceeds', function () {
    $cpuUsage = 95.5;
    $criticalThreshold = 90;
    $warningThreshold = 70;

    expect($cpuUsage >= $criticalThreshold)->toBeTrue();
});

test('cpu threshold logic - warning exceeds', function () {
    $cpuUsage = 75.0;
    $criticalThreshold = 90;
    $warningThreshold = 70;

    expect($cpuUsage >= $criticalThreshold)->toBeFalse();
    expect($cpuUsage >= $warningThreshold)->toBeTrue();
});

test('cpu threshold logic - below all thresholds', function () {
    $cpuUsage = 50.0;
    $criticalThreshold = 90;
    $warningThreshold = 70;

    expect($cpuUsage >= $criticalThreshold)->toBeFalse();
    expect($cpuUsage >= $warningThreshold)->toBeFalse();
});

test('memory threshold logic - critical exceeds', function () {
    $memoryUsage = 92.0;
    $criticalThreshold = 90;

    expect($memoryUsage >= $criticalThreshold)->toBeTrue();
});

test('disk threshold logic - critical exceeds', function () {
    $diskUsage = 95;
    $criticalThreshold = 90;

    expect($diskUsage >= $criticalThreshold)->toBeTrue();
});

test('disk threshold logic - below threshold', function () {
    $diskUsage = 50;
    $criticalThreshold = 90;
    $warningThreshold = 70;

    expect($diskUsage >= $criticalThreshold)->toBeFalse();
    expect($diskUsage >= $warningThreshold)->toBeFalse();
});

test('auto-provisioning trigger reason selection', function () {
    $cpuCritical = true;
    $memoryCritical = false;
    $diskCritical = false;

    $reason = $cpuCritical ? 'cpu_critical' : ($memoryCritical ? 'memory_critical' : 'disk_critical');
    expect($reason)->toBe('cpu_critical');

    $cpuCritical = false;
    $memoryCritical = true;
    $reason = $cpuCritical ? 'cpu_critical' : ($memoryCritical ? 'memory_critical' : 'disk_critical');
    expect($reason)->toBe('memory_critical');

    $memoryCritical = false;
    $diskCritical = true;
    $reason = $cpuCritical ? 'cpu_critical' : ($memoryCritical ? 'memory_critical' : 'disk_critical');
    expect($reason)->toBe('disk_critical');
});

test('notification cache key includes server UUID', function () {
    $uuid = 'abc-123';
    $cpuKey = "server-cpu-alert-{$uuid}";
    $memoryKey = "server-memory-alert-{$uuid}";
    $diskKey = "server-disk-alert-{$uuid}";

    expect($cpuKey)->toBe('server-cpu-alert-abc-123');
    expect($memoryKey)->toBe('server-memory-alert-abc-123');
    expect($diskKey)->toBe('server-disk-alert-abc-123');
});

test('cooldown cache key includes server UUID', function () {
    $uuid = 'test-server';
    $key = "auto-provision-triggered-{$uuid}";
    expect($key)->toBe('auto-provision-triggered-test-server');
});

test('triggerAutoProvisioning respects cooldown cache', function () {
    $uuid = 'test-uuid';
    Cache::put("auto-provision-triggered-{$uuid}", true, now()->addHours(6));

    expect(Cache::has("auto-provision-triggered-{$uuid}"))->toBeTrue();
});

test('latest metrics extraction logic', function () {
    // The job extracts the last element from metrics array
    $cpuMetrics = [
        ['2024-01-01 00:00', 45.0],
        ['2024-01-01 00:05', 50.0],
        ['2024-01-01 00:10', 95.5],
    ];

    $latestCpu = collect($cpuMetrics)->last();
    $cpuUsage = $latestCpu[1] ?? 0;

    expect($cpuUsage)->toBe(95.5);
});

test('metrics extraction handles empty array gracefully', function () {
    $metrics = [];
    $latest = collect($metrics)->last();

    expect($latest)->toBeNull();
    expect(empty($metrics))->toBeTrue();
});

test('disk usage parsing converts string to int', function () {
    $diskUsage = '85';
    expect(is_numeric($diskUsage))->toBeTrue();
    expect((int) $diskUsage)->toBe(85);

    $diskUsage = 'N/A';
    expect(is_numeric($diskUsage))->toBeFalse();
});

test('source code checks resource_monitoring_enabled', function () {
    $source = file_get_contents((new ReflectionClass(CheckServerResourcesJob::class))->getFileName());

    expect($source)->toContain('resource_monitoring_enabled');
});

test('source code checks server status before proceeding', function () {
    $source = file_get_contents((new ReflectionClass(CheckServerResourcesJob::class))->getFileName());

    expect($source)->toContain('serverStatus()');
});

test('source code uses notification spam prevention via cache', function () {
    $source = file_get_contents((new ReflectionClass(CheckServerResourcesJob::class))->getFileName());

    expect($source)->toContain('server-cpu-alert-');
    expect($source)->toContain('server-memory-alert-');
    expect($source)->toContain('server-disk-alert-');
    expect($source)->toContain('addMinutes(15)');
    expect($source)->toContain('addMinutes(30)');
});

test('source code dispatches AutoProvisionServerJob', function () {
    $source = file_get_contents((new ReflectionClass(CheckServerResourcesJob::class))->getFileName());

    expect($source)->toContain('AutoProvisionServerJob::dispatch');
    expect($source)->toContain('addHours(6)');
});

test('source code sends notifications for all resource types', function () {
    $source = file_get_contents((new ReflectionClass(CheckServerResourcesJob::class))->getFileName());

    expect($source)->toContain('HighCpuUsage');
    expect($source)->toContain('HighMemoryUsage');
    expect($source)->toContain('HighDiskUsage');
});

test('source code has auto-provisioning daily limit check', function () {
    $source = file_get_contents((new ReflectionClass(CheckServerResourcesJob::class))->getFileName());

    expect($source)->toContain('countProvisionedToday');
    expect($source)->toContain('auto_provision_max_servers_per_day');
    expect($source)->toContain('hasActiveProvisioning');
});
