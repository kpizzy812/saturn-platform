<?php

use App\Models\Service;

// type() Tests
test('type returns service', function () {
    $service = new Service;
    expect($service->type())->toBe('service');
});

// isRunning Tests - need to mock getStatusAttribute since it queries DB
test('isRunning returns true when status contains running', function () {
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('getStatusAttribute')->andReturn('running:healthy');
    expect($service->isRunning())->toBeTrue();
});

test('isRunning returns true when status contains running among others', function () {
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('getStatusAttribute')->andReturn('running');
    expect($service->isRunning())->toBeTrue();
});

test('isRunning returns false when status is exited', function () {
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('getStatusAttribute')->andReturn('exited');
    expect($service->isRunning())->toBeFalse();
});

test('isRunning returns false when status is stopped', function () {
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('getStatusAttribute')->andReturn('stopped');
    expect($service->isRunning())->toBeFalse();
});

// isExited Tests
test('isExited returns true when status contains exited', function () {
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('getStatusAttribute')->andReturn('exited');
    expect($service->isExited())->toBeTrue();
});

test('isExited returns true when status has exited in mixed status', function () {
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('getStatusAttribute')->andReturn('exited:0');
    expect($service->isExited())->toBeTrue();
});

test('isExited returns false when status is running', function () {
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('getStatusAttribute')->andReturn('running:healthy');
    expect($service->isExited())->toBeFalse();
});

// project() and team() Tests
test('project returns environment project', function () {
    $service = new Service;
    $project = (object) ['id' => 1, 'name' => 'Test Project'];
    $service->environment = (object) ['project' => $project];

    expect($service->project())->toBe($project);
});

test('project returns null when no environment', function () {
    $service = new Service;
    $service->environment = null;

    expect($service->project())->toBeNull();
});

test('team returns environment project team', function () {
    $service = new Service;
    $team = (object) ['id' => 1, 'name' => 'Test Team'];
    $service->environment = (object) ['project' => (object) ['team' => $team]];

    expect($service->team())->toBe($team);
});
