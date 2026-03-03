<?php

/**
 * Unit tests for Application Actions.
 *
 * Tests cover:
 * - Class structure (AsAction trait, handle method, job queue)
 * - ScanEnvExample (plain class, no AsAction)
 * - Early-exit when server is not functional
 */

use App\Actions\Application\CleanupPreviewDeployment;
use App\Actions\Application\ScanEnvExample;
use App\Actions\Application\StopApplication;
use App\Models\Application;
use App\Models\Server;
use Lorisleiva\Actions\Concerns\AsAction;
use Mockery;

afterEach(fn () => Mockery::close());

// ─── StopApplication ──────────────────────────────────────────────────────────

test('StopApplication uses AsAction trait', function () {
    expect(in_array(AsAction::class, class_uses_recursive(StopApplication::class)))->toBeTrue();
});

test('StopApplication has handle method', function () {
    expect(method_exists(StopApplication::class, 'handle'))->toBeTrue();
});

test('StopApplication job queue is high', function () {
    $action = new StopApplication;
    expect($action->jobQueue)->toBe('high');
});

test('StopApplication returns server not functional when server is not functional', function () {
    $server = Mockery::mock(Server::class)->makePartial();
    $server->shouldReceive('isFunctional')->andReturn(false);

    $destination = (object) ['server' => $server];

    $application = Mockery::mock(Application::class)->makePartial();
    $application->setRelation('destination', $destination);
    $application->setRelation('additional_servers', collect([]));

    $result = (new StopApplication)->handle($application);

    expect($result)->toBe('Server is not functional');
});

// ─── CleanupPreviewDeployment ─────────────────────────────────────────────────

test('CleanupPreviewDeployment uses AsAction trait', function () {
    expect(in_array(AsAction::class, class_uses_recursive(CleanupPreviewDeployment::class)))->toBeTrue();
});

test('CleanupPreviewDeployment has handle method', function () {
    expect(method_exists(CleanupPreviewDeployment::class, 'handle'))->toBeTrue();
});

test('CleanupPreviewDeployment job queue is high', function () {
    $action = new CleanupPreviewDeployment;
    expect($action->jobQueue)->toBe('high');
});

test('CleanupPreviewDeployment returns failed status when server is not functional', function () {
    $server = Mockery::mock(Server::class)->makePartial();
    $server->shouldReceive('isFunctional')->andReturn(false);

    $destination = (object) ['server' => $server];

    $application = Mockery::mock(Application::class)->makePartial();
    $application->setRelation('destination', $destination);

    $result = (new CleanupPreviewDeployment)->handle($application, 42);

    expect($result['status'])->toBe('failed');
    expect($result['message'])->toBe('Server is not functional');
});

// ─── ScanEnvExample ───────────────────────────────────────────────────────────

test('ScanEnvExample class exists', function () {
    expect(class_exists(ScanEnvExample::class))->toBeTrue();
});

test('ScanEnvExample has handle method', function () {
    expect(method_exists(ScanEnvExample::class, 'handle'))->toBeTrue();
});

test('ScanEnvExample does not use AsAction trait', function () {
    expect(in_array(AsAction::class, class_uses_recursive(ScanEnvExample::class)))->toBeFalse();
});
