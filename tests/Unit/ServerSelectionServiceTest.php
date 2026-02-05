<?php

use App\Services\ServerSelectionService;

test('calculateScore returns float between 0 and 100', function () {
    // Use partial mock to avoid DB calls
    $service = Mockery::mock(ServerSelectionService::class)->makePartial();
    $service->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('getQueuedDeployments')->andReturn(0);

    $server = Mockery::mock(\App\Models\Server::class)->makePartial();
    $server->id = 1;
    $server->shouldReceive('getAttribute')
        ->with('latestHealthCheck')
        ->andReturn(null);

    $score = $service->calculateScore($server);

    expect($score)->toBeFloat();
    expect($score)->toBeGreaterThanOrEqual(0);
    expect($score)->toBeLessThanOrEqual(100);
});

test('calculateScore penalizes high resource usage', function () {
    $service = Mockery::mock(ServerSelectionService::class)->makePartial();
    $service->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('getQueuedDeployments')->andReturn(0);

    // Server with low usage
    $lowUsageServer = Mockery::mock(\App\Models\Server::class)->makePartial();
    $lowUsageServer->id = 1;
    $lowUsageHealth = Mockery::mock(\App\Models\ServerHealthCheck::class)->makePartial();
    $lowUsageHealth->cpu_usage_percent = 10;
    $lowUsageHealth->memory_usage_percent = 20;
    $lowUsageHealth->disk_usage_percent = 30;
    $lowUsageHealth->container_counts = ['running' => 5];
    $lowUsageServer->shouldReceive('getAttribute')
        ->with('latestHealthCheck')
        ->andReturn($lowUsageHealth);

    // Server with high usage
    $highUsageServer = Mockery::mock(\App\Models\Server::class)->makePartial();
    $highUsageServer->id = 2;
    $highUsageHealth = Mockery::mock(\App\Models\ServerHealthCheck::class)->makePartial();
    $highUsageHealth->cpu_usage_percent = 90;
    $highUsageHealth->memory_usage_percent = 85;
    $highUsageHealth->disk_usage_percent = 80;
    $highUsageHealth->container_counts = ['running' => 40];
    $highUsageServer->shouldReceive('getAttribute')
        ->with('latestHealthCheck')
        ->andReturn($highUsageHealth);

    $lowScore = $service->calculateScore($lowUsageServer);
    $highScore = $service->calculateScore($highUsageServer);

    expect($lowScore)->toBeGreaterThan($highScore);
});

test('calculateScore handles zero usage correctly', function () {
    $service = Mockery::mock(ServerSelectionService::class)->makePartial();
    $service->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('getQueuedDeployments')->andReturn(0);

    $server = Mockery::mock(\App\Models\Server::class)->makePartial();
    $server->id = 1;
    $health = Mockery::mock(\App\Models\ServerHealthCheck::class)->makePartial();
    $health->cpu_usage_percent = 0;
    $health->memory_usage_percent = 0;
    $health->disk_usage_percent = 0;
    $health->container_counts = ['running' => 0];
    $server->shouldReceive('getAttribute')
        ->with('latestHealthCheck')
        ->andReturn($health);

    $score = $service->calculateScore($server);

    // Perfect score = 100 (all weights * 100)
    expect($score)->toBe(100.0);
});

test('ServerSelectionService scoring weights sum to 1', function () {
    $reflection = new ReflectionClass(ServerSelectionService::class);

    $weights = [
        $reflection->getConstant('WEIGHT_CPU'),
        $reflection->getConstant('WEIGHT_MEMORY'),
        $reflection->getConstant('WEIGHT_DISK'),
        $reflection->getConstant('WEIGHT_CONTAINERS'),
        $reflection->getConstant('WEIGHT_QUEUED'),
    ];

    expect(array_sum($weights))->toBe(1.0);
});

test('selectOptimalServer priority order is documented', function () {
    // Verify the service exists and has the selectOptimalServer method
    $service = new ServerSelectionService;
    expect(method_exists($service, 'selectOptimalServer'))->toBeTrue();

    // Verify the source code documents the priority order
    $content = file_get_contents(app_path('Services/ServerSelectionService.php'));
    expect($content)->toContain('Environment default server');
    expect($content)->toContain('Project default server');
    expect($content)->toContain('Score-based selection');
});
