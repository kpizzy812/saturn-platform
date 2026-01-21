<?php

/**
 * Unit tests for GetContainersStatus service aggregation logic (SSH path).
 *
 * These tests verify that the SSH-based status updates (GetContainersStatus)
 * correctly aggregates container statuses for services with multiple containers,
 * using the centralized ContainerStatusAggregator service.
 *
 * This ensures consistency across both status update paths and prevents
 * race conditions where the last container processed wins.
 */
it('implements service multi-container aggregation in SSH path', function () {
    $actionFile = file_get_contents(__DIR__.'/../../app/Actions/Docker/GetContainersStatus.php');

    // Verify service container collection property exists
    expect($actionFile)
        ->toContain('protected ?Collection $serviceContainerStatuses;');

    // Verify aggregateServiceContainerStatuses method exists
    expect($actionFile)
        ->toContain('private function aggregateServiceContainerStatuses($services)')
        ->toContain('$this->aggregateServiceContainerStatuses($services);');

    // Verify service aggregation uses ContainerStatusAggregator
    expect($actionFile)
        ->toContain('use App\Services\ContainerStatusAggregator;')
        ->toContain('new ContainerStatusAggregator');
});

it('services use ContainerStatusAggregator for status aggregation', function () {
    $actionFile = file_get_contents(__DIR__.'/../../app/Actions/Docker/GetContainersStatus.php');

    // Should use ContainerStatusAggregator for consistent aggregation
    expect($actionFile)
        ->toContain('$aggregator = new ContainerStatusAggregator')
        ->toContain('$aggregator->aggregateFromStrings(');

    // Verify ContainerStatusAggregator has the priority logic
    $aggregatorFile = file_get_contents(__DIR__.'/../../app/Services/ContainerStatusAggregator.php');
    expect($aggregatorFile)
        ->toContain('$hasUnhealthy')
        ->toContain('$hasUnknown')
        ->toContain("return 'running:unhealthy';")
        ->toContain("return 'running:unknown';")
        ->toContain("return 'running:healthy';");
});

it('collects service containers before aggregating in SSH path', function () {
    $actionFile = file_get_contents(__DIR__.'/../../app/Actions/Docker/GetContainersStatus.php');

    // Verify service containers are collected, not immediately updated
    expect($actionFile)
        ->toContain('$key = $serviceLabelId.\':\'.$subType.\':\'.$subId;')
        ->toContain('$this->serviceContainerStatuses->get($key)->put($containerName, $containerStatus);');

    // Verify aggregation happens before ServiceChecked dispatch
    expect($actionFile)
        ->toContain('$this->aggregateServiceContainerStatuses($services);')
        ->toContain('ServiceChecked::dispatch($this->server->team->id);');
});

it('SSH and Sentinel paths use ContainerStatusAggregator', function () {
    $jobFile = file_get_contents(__DIR__.'/../../app/Jobs/PushServerUpdateJob.php');
    $actionFile = file_get_contents(__DIR__.'/../../app/Actions/Docker/GetContainersStatus.php');

    // Both should use ContainerStatusAggregator service
    expect($jobFile)->toContain('ContainerStatusAggregator');
    expect($actionFile)->toContain('ContainerStatusAggregator');

    // Both should use the aggregator for status aggregation
    expect($jobFile)->toContain('$aggregator->aggregateFromStrings');
    expect($actionFile)->toContain('$aggregator->aggregateFromStrings');
});

it('handles service status updates consistently', function () {
    $jobFile = file_get_contents(__DIR__.'/../../app/Jobs/PushServerUpdateJob.php');
    $actionFile = file_get_contents(__DIR__.'/../../app/Actions/Docker/GetContainersStatus.php');

    // Both should parse service key with same format
    expect($jobFile)->toContain('[$serviceId, $subType, $subId] = explode(\':\', $key);');
    expect($actionFile)->toContain('[$serviceId, $subType, $subId] = explode(\':\', $key);');

    // Both should handle excluded containers via trait method
    expect($jobFile)->toContain('$excludedContainers = $this->getExcludedContainersFromDockerCompose');
    expect($actionFile)->toContain('$excludedContainers = $this->getExcludedContainersFromDockerCompose');
});
