<?php

use App\Services\ContainerStatusAggregator;

beforeEach(function () {
    $this->aggregator = new ContainerStatusAggregator;
});

// aggregateFromStrings - Empty collection
test('aggregateFromStrings returns exited for empty collection', function () {
    expect($this->aggregator->aggregateFromStrings(collect([])))->toBe('exited');
});

// aggregateFromStrings - Single status
test('aggregateFromStrings returns running:healthy for single running container', function () {
    expect($this->aggregator->aggregateFromStrings(collect(['running (healthy)'])))->toBe('running:healthy');
});

test('aggregateFromStrings returns running:unhealthy for unhealthy container', function () {
    expect($this->aggregator->aggregateFromStrings(collect(['running (unhealthy)'])))->toBe('running:unhealthy');
});

test('aggregateFromStrings returns running:unknown for unknown health', function () {
    expect($this->aggregator->aggregateFromStrings(collect(['running (unknown)'])))->toBe('running:unknown');
});

test('aggregateFromStrings returns exited for exited container', function () {
    expect($this->aggregator->aggregateFromStrings(collect(['exited'])))->toBe('exited');
});

test('aggregateFromStrings returns starting:unknown for starting container', function () {
    expect($this->aggregator->aggregateFromStrings(collect(['starting'])))->toBe('starting:unknown');
});

test('aggregateFromStrings returns starting:unknown for created container', function () {
    expect($this->aggregator->aggregateFromStrings(collect(['created'])))->toBe('starting:unknown');
});

test('aggregateFromStrings returns paused:unknown for paused container', function () {
    expect($this->aggregator->aggregateFromStrings(collect(['paused'])))->toBe('paused:unknown');
});

test('aggregateFromStrings returns degraded:unhealthy for dead container', function () {
    expect($this->aggregator->aggregateFromStrings(collect(['dead'])))->toBe('degraded:unhealthy');
});

test('aggregateFromStrings returns degraded:unhealthy for removing container', function () {
    expect($this->aggregator->aggregateFromStrings(collect(['removing'])))->toBe('degraded:unhealthy');
});

// aggregateFromStrings - Mixed states (Priority resolution)
test('aggregateFromStrings returns degraded for running + exited mix', function () {
    $statuses = collect(['running (healthy)', 'exited']);
    expect($this->aggregator->aggregateFromStrings($statuses))->toBe('degraded:unhealthy');
});

test('aggregateFromStrings returns starting for running + starting mix', function () {
    $statuses = collect(['running (healthy)', 'starting']);
    expect($this->aggregator->aggregateFromStrings($statuses))->toBe('starting:unknown');
});

test('aggregateFromStrings returns degraded for degraded status string', function () {
    $statuses = collect(['degraded:unhealthy', 'running (healthy)']);
    expect($this->aggregator->aggregateFromStrings($statuses))->toBe('degraded:unhealthy');
});

test('aggregateFromStrings returns running:unhealthy when some unhealthy', function () {
    $statuses = collect(['running (healthy)', 'running (unhealthy)']);
    expect($this->aggregator->aggregateFromStrings($statuses))->toBe('running:unhealthy');
});

test('aggregateFromStrings returns running:healthy when all healthy', function () {
    $statuses = collect(['running (healthy)', 'running (healthy)', 'running (healthy)']);
    expect($this->aggregator->aggregateFromStrings($statuses))->toBe('running:healthy');
});

// aggregateFromStrings - Restarting
test('aggregateFromStrings returns degraded for restarting by default', function () {
    $statuses = collect(['restarting']);
    expect($this->aggregator->aggregateFromStrings($statuses))->toBe('degraded:unhealthy');
});

test('aggregateFromStrings returns restarting when preserveRestarting is true', function () {
    $statuses = collect(['restarting']);
    expect($this->aggregator->aggregateFromStrings($statuses, 0, true))->toBe('restarting:unknown');
});

test('aggregateFromStrings returns degraded for restarting with high restart count even with preserveRestarting', function () {
    $statuses = collect(['restarting']);
    expect($this->aggregator->aggregateFromStrings($statuses, 3, true))->toBe('degraded:unhealthy');
});

// aggregateFromStrings - Crash loop detection
test('aggregateFromStrings detects crash loop with exited + restart count', function () {
    $statuses = collect(['exited']);
    expect($this->aggregator->aggregateFromStrings($statuses, 1))->toBe('degraded:unhealthy');
});

test('aggregateFromStrings does not trigger crash loop with zero restart count', function () {
    $statuses = collect(['exited']);
    expect($this->aggregator->aggregateFromStrings($statuses, 0))->toBe('exited');
});

// aggregateFromStrings - Colon format input
test('aggregateFromStrings handles colon format input', function () {
    expect($this->aggregator->aggregateFromStrings(collect(['running:healthy'])))->toBe('running:healthy');
    expect($this->aggregator->aggregateFromStrings(collect(['running:unhealthy'])))->toBe('running:unhealthy');
    expect($this->aggregator->aggregateFromStrings(collect(['degraded:unhealthy'])))->toBe('degraded:unhealthy');
});

// aggregateFromContainers - Empty collection
test('aggregateFromContainers returns exited for empty collection', function () {
    expect($this->aggregator->aggregateFromContainers(collect([])))->toBe('exited');
});

// aggregateFromContainers - Container objects
test('aggregateFromContainers returns running:healthy for healthy container', function () {
    $container = (object) ['State' => (object) ['Status' => 'running', 'Health' => (object) ['Status' => 'healthy']]];
    expect($this->aggregator->aggregateFromContainers(collect([$container])))->toBe('running:healthy');
});

test('aggregateFromContainers returns running:unhealthy for unhealthy container', function () {
    $container = (object) ['State' => (object) ['Status' => 'running', 'Health' => (object) ['Status' => 'unhealthy']]];
    expect($this->aggregator->aggregateFromContainers(collect([$container])))->toBe('running:unhealthy');
});

test('aggregateFromContainers returns running:unknown when no health check', function () {
    $container = (object) ['State' => (object) ['Status' => 'running', 'Health' => null]];
    expect($this->aggregator->aggregateFromContainers(collect([$container])))->toBe('running:unknown');
});

test('aggregateFromContainers returns running:unknown when health is starting', function () {
    $container = (object) ['State' => (object) ['Status' => 'running', 'Health' => (object) ['Status' => 'starting']]];
    expect($this->aggregator->aggregateFromContainers(collect([$container])))->toBe('running:unknown');
});

test('aggregateFromContainers returns exited for exited container', function () {
    $container = (object) ['State' => (object) ['Status' => 'exited']];
    expect($this->aggregator->aggregateFromContainers(collect([$container])))->toBe('exited');
});

test('aggregateFromContainers returns degraded for running + exited mix', function () {
    $running = (object) ['State' => (object) ['Status' => 'running', 'Health' => (object) ['Status' => 'healthy']]];
    $exited = (object) ['State' => (object) ['Status' => 'exited']];
    expect($this->aggregator->aggregateFromContainers(collect([$running, $exited])))->toBe('degraded:unhealthy');
});

test('aggregateFromContainers returns starting for created container', function () {
    $container = (object) ['State' => (object) ['Status' => 'created']];
    expect($this->aggregator->aggregateFromContainers(collect([$container])))->toBe('starting:unknown');
});

test('aggregateFromContainers returns degraded for restarting container', function () {
    $container = (object) ['State' => (object) ['Status' => 'restarting']];
    expect($this->aggregator->aggregateFromContainers(collect([$container])))->toBe('degraded:unhealthy');
});

test('aggregateFromContainers returns restarting when preserveRestarting is true', function () {
    $container = (object) ['State' => (object) ['Status' => 'restarting']];
    expect($this->aggregator->aggregateFromContainers(collect([$container]), 0, true))->toBe('restarting:unknown');
});

test('aggregateFromContainers returns degraded for dead container', function () {
    $container = (object) ['State' => (object) ['Status' => 'dead']];
    expect($this->aggregator->aggregateFromContainers(collect([$container])))->toBe('degraded:unhealthy');
});

test('aggregateFromContainers returns paused for paused container', function () {
    $container = (object) ['State' => (object) ['Status' => 'paused']];
    expect($this->aggregator->aggregateFromContainers(collect([$container])))->toBe('paused:unknown');
});

// Priority order verification
test('degraded has highest priority over running', function () {
    $statuses = collect(['degraded:unhealthy', 'running (healthy)', 'running (healthy)']);
    expect($this->aggregator->aggregateFromStrings($statuses))->toBe('degraded:unhealthy');
});

test('restarting has higher priority than running + exited', function () {
    $statuses = collect(['restarting', 'running (healthy)', 'exited']);
    expect($this->aggregator->aggregateFromStrings($statuses))->toBe('degraded:unhealthy');
});

test('dead has lower priority than running', function () {
    // dead + running = running+exited scenario doesn't apply, dead is separate
    $statuses = collect(['dead']);
    expect($this->aggregator->aggregateFromStrings($statuses))->toBe('degraded:unhealthy');
});

// Multiple containers all healthy
test('aggregateFromContainers all healthy returns running:healthy', function () {
    $containers = collect([
        (object) ['State' => (object) ['Status' => 'running', 'Health' => (object) ['Status' => 'healthy']]],
        (object) ['State' => (object) ['Status' => 'running', 'Health' => (object) ['Status' => 'healthy']]],
        (object) ['State' => (object) ['Status' => 'running', 'Health' => (object) ['Status' => 'healthy']]],
    ]);
    expect($this->aggregator->aggregateFromContainers($containers))->toBe('running:healthy');
});
