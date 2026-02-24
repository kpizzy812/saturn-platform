<?php

use App\Models\Server;
use App\Models\ServerHealthCheck;
use App\Services\ServerSelectionService;

beforeEach(function () {
    $this->service = new ServerSelectionService;
    Mockery::close();
});

afterEach(function () {
    Mockery::close();
});

// ═══════════════════════════════════════════
// calculateScore() — scoring algorithm
// ═══════════════════════════════════════════

test('calculateScore returns 100 for idle server with no health data', function () {
    $server = Mockery::mock(Server::class)->makePartial();
    $server->latestHealthCheck = null;

    // Mock getQueuedDeployments to return 0
    $service = Mockery::mock(ServerSelectionService::class)->makePartial();
    $service->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('getQueuedDeployments')->andReturn(0);

    $score = $service->calculateScore($server);

    // All zeroes: (100*0.3) + (100*0.3) + (100*0.2) + (100*0.1) + (100*0.1) = 100
    expect($score)->toBe(100.0);
});

test('calculateScore returns 0 for fully loaded server', function () {
    $health = Mockery::mock(ServerHealthCheck::class)->makePartial();
    $health->cpu_usage_percent = 100;
    $health->memory_usage_percent = 100;
    $health->disk_usage_percent = 100;
    $health->container_counts = ['running' => 50];

    $server = Mockery::mock(Server::class)->makePartial();
    $server->latestHealthCheck = $health;

    $service = Mockery::mock(ServerSelectionService::class)->makePartial();
    $service->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('getQueuedDeployments')->andReturn(10);

    $score = $service->calculateScore($server);

    // All maxed: (0*0.3) + (0*0.3) + (0*0.2) + (0*0.1) + (0*0.1) = 0
    expect($score)->toBe(0.0);
});

test('calculateScore weights CPU at 30%', function () {
    $health = Mockery::mock(ServerHealthCheck::class)->makePartial();
    $health->cpu_usage_percent = 50;
    $health->memory_usage_percent = 0;
    $health->disk_usage_percent = 0;
    $health->container_counts = [];

    $server = Mockery::mock(Server::class)->makePartial();
    $server->latestHealthCheck = $health;

    $service = Mockery::mock(ServerSelectionService::class)->makePartial();
    $service->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('getQueuedDeployments')->andReturn(0);

    $score = $service->calculateScore($server);

    // CPU 50%: cpuScore=50, rest=100
    // (50*0.3) + (100*0.3) + (100*0.2) + (100*0.1) + (100*0.1) = 15 + 30 + 20 + 10 + 10 = 85
    expect($score)->toBe(85.0);
});

test('calculateScore weights memory at 30%', function () {
    $health = Mockery::mock(ServerHealthCheck::class)->makePartial();
    $health->cpu_usage_percent = 0;
    $health->memory_usage_percent = 80;
    $health->disk_usage_percent = 0;
    $health->container_counts = [];

    $server = Mockery::mock(Server::class)->makePartial();
    $server->latestHealthCheck = $health;

    $service = Mockery::mock(ServerSelectionService::class)->makePartial();
    $service->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('getQueuedDeployments')->andReturn(0);

    $score = $service->calculateScore($server);

    // Memory 80%: memScore=20
    // (100*0.3) + (20*0.3) + (100*0.2) + (100*0.1) + (100*0.1) = 30 + 6 + 20 + 10 + 10 = 76
    expect($score)->toBe(76.0);
});

test('calculateScore caps container score at 0 when 50+ containers', function () {
    $health = Mockery::mock(ServerHealthCheck::class)->makePartial();
    $health->cpu_usage_percent = 0;
    $health->memory_usage_percent = 0;
    $health->disk_usage_percent = 0;
    $health->container_counts = ['running' => 60];

    $server = Mockery::mock(Server::class)->makePartial();
    $server->latestHealthCheck = $health;

    $service = Mockery::mock(ServerSelectionService::class)->makePartial();
    $service->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('getQueuedDeployments')->andReturn(0);

    $score = $service->calculateScore($server);

    // Containers: max(0, 100 - 60*2) = max(0, -20) = 0
    // (100*0.3) + (100*0.3) + (100*0.2) + (0*0.1) + (100*0.1) = 30 + 30 + 20 + 0 + 10 = 90
    expect($score)->toBe(90.0);
});

test('calculateScore caps queued score at 0 when 10+ queued deployments', function () {
    $health = Mockery::mock(ServerHealthCheck::class)->makePartial();
    $health->cpu_usage_percent = 0;
    $health->memory_usage_percent = 0;
    $health->disk_usage_percent = 0;
    $health->container_counts = [];

    $server = Mockery::mock(Server::class)->makePartial();
    $server->latestHealthCheck = $health;

    $service = Mockery::mock(ServerSelectionService::class)->makePartial();
    $service->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('getQueuedDeployments')->andReturn(15);

    $score = $service->calculateScore($server);

    // Queued: max(0, 100 - 15*10) = max(0, -50) = 0
    // (100*0.3) + (100*0.3) + (100*0.2) + (100*0.1) + (0*0.1) = 30 + 30 + 20 + 10 + 0 = 90
    expect($score)->toBe(90.0);
});

test('calculateScore with realistic mixed usage', function () {
    $health = Mockery::mock(ServerHealthCheck::class)->makePartial();
    $health->cpu_usage_percent = 40;
    $health->memory_usage_percent = 60;
    $health->disk_usage_percent = 30;
    $health->container_counts = ['running' => 10];

    $server = Mockery::mock(Server::class)->makePartial();
    $server->latestHealthCheck = $health;

    $service = Mockery::mock(ServerSelectionService::class)->makePartial();
    $service->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('getQueuedDeployments')->andReturn(2);

    $score = $service->calculateScore($server);

    // CPU: 60*0.3=18, Mem: 40*0.3=12, Disk: 70*0.2=14, Containers: 80*0.1=8, Queue: 80*0.1=8
    expect($score)->toBe(60.0);
});

// ═══════════════════════════════════════════
// isCriticallyOverloaded() — threshold checks
// ═══════════════════════════════════════════

test('server without health data is not critically overloaded', function () {
    $server = Mockery::mock(Server::class)->makePartial();
    $server->latestHealthCheck = null;

    $method = new ReflectionMethod(ServerSelectionService::class, 'isCriticallyOverloaded');

    expect($method->invoke($this->service, $server))->toBeFalse();
});

test('server with CPU above 90% is critically overloaded', function () {
    $health = Mockery::mock(ServerHealthCheck::class)->makePartial();
    $health->cpu_usage_percent = 95;
    $health->memory_usage_percent = 50;
    $health->disk_usage_percent = 50;

    $server = Mockery::mock(Server::class)->makePartial();
    $server->latestHealthCheck = $health;

    $method = new ReflectionMethod(ServerSelectionService::class, 'isCriticallyOverloaded');

    expect($method->invoke($this->service, $server))->toBeTrue();
});

test('server with memory above 90% is critically overloaded', function () {
    $health = Mockery::mock(ServerHealthCheck::class)->makePartial();
    $health->cpu_usage_percent = 50;
    $health->memory_usage_percent = 91;
    $health->disk_usage_percent = 50;

    $server = Mockery::mock(Server::class)->makePartial();
    $server->latestHealthCheck = $health;

    $method = new ReflectionMethod(ServerSelectionService::class, 'isCriticallyOverloaded');

    expect($method->invoke($this->service, $server))->toBeTrue();
});

test('server with disk above 90% is critically overloaded', function () {
    $health = Mockery::mock(ServerHealthCheck::class)->makePartial();
    $health->cpu_usage_percent = 50;
    $health->memory_usage_percent = 50;
    $health->disk_usage_percent = 92;

    $server = Mockery::mock(Server::class)->makePartial();
    $server->latestHealthCheck = $health;

    $method = new ReflectionMethod(ServerSelectionService::class, 'isCriticallyOverloaded');

    expect($method->invoke($this->service, $server))->toBeTrue();
});

test('server with all metrics at exactly 90% is not critically overloaded', function () {
    $health = Mockery::mock(ServerHealthCheck::class)->makePartial();
    $health->cpu_usage_percent = 90;
    $health->memory_usage_percent = 90;
    $health->disk_usage_percent = 90;

    $server = Mockery::mock(Server::class)->makePartial();
    $server->latestHealthCheck = $health;

    $method = new ReflectionMethod(ServerSelectionService::class, 'isCriticallyOverloaded');

    expect($method->invoke($this->service, $server))->toBeFalse();
});

test('server with moderate usage is not critically overloaded', function () {
    $health = Mockery::mock(ServerHealthCheck::class)->makePartial();
    $health->cpu_usage_percent = 60;
    $health->memory_usage_percent = 70;
    $health->disk_usage_percent = 45;

    $server = Mockery::mock(Server::class)->makePartial();
    $server->latestHealthCheck = $health;

    $method = new ReflectionMethod(ServerSelectionService::class, 'isCriticallyOverloaded');

    expect($method->invoke($this->service, $server))->toBeFalse();
});

// ═══════════════════════════════════════════
// Score weights sum to 1.0
// ═══════════════════════════════════════════

test('scoring weights sum to 1.0', function () {
    $class = new ReflectionClass(ServerSelectionService::class);

    $cpu = $class->getConstant('WEIGHT_CPU');
    $memory = $class->getConstant('WEIGHT_MEMORY');
    $disk = $class->getConstant('WEIGHT_DISK');
    $containers = $class->getConstant('WEIGHT_CONTAINERS');
    $queued = $class->getConstant('WEIGHT_QUEUED');

    expect($cpu + $memory + $disk + $containers + $queued)->toBe(1.0);
});

// ═══════════════════════════════════════════
// Edge cases
// ═══════════════════════════════════════════

test('calculateScore handles null container_counts gracefully', function () {
    $health = Mockery::mock(ServerHealthCheck::class)->makePartial();
    $health->cpu_usage_percent = 0;
    $health->memory_usage_percent = 0;
    $health->disk_usage_percent = 0;
    $health->container_counts = null;

    $server = Mockery::mock(Server::class)->makePartial();
    $server->latestHealthCheck = $health;

    $service = Mockery::mock(ServerSelectionService::class)->makePartial();
    $service->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('getQueuedDeployments')->andReturn(0);

    $score = $service->calculateScore($server);

    expect($score)->toBe(100.0);
});

test('calculateScore handles empty container_counts array', function () {
    $health = Mockery::mock(ServerHealthCheck::class)->makePartial();
    $health->cpu_usage_percent = 0;
    $health->memory_usage_percent = 0;
    $health->disk_usage_percent = 0;
    $health->container_counts = [];

    $server = Mockery::mock(Server::class)->makePartial();
    $server->latestHealthCheck = $health;

    $service = Mockery::mock(ServerSelectionService::class)->makePartial();
    $service->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('getQueuedDeployments')->andReturn(0);

    $score = $service->calculateScore($server);

    expect($score)->toBe(100.0);
});

test('calculateScore never returns negative value', function () {
    $health = Mockery::mock(ServerHealthCheck::class)->makePartial();
    $health->cpu_usage_percent = 200; // Extreme value
    $health->memory_usage_percent = 200;
    $health->disk_usage_percent = 200;
    $health->container_counts = ['running' => 200];

    $server = Mockery::mock(Server::class)->makePartial();
    $server->latestHealthCheck = $health;

    $service = Mockery::mock(ServerSelectionService::class)->makePartial();
    $service->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('getQueuedDeployments')->andReturn(100);

    $score = $service->calculateScore($server);

    expect($score)->toBeGreaterThanOrEqual(0.0);
});
