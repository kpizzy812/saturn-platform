<?php

/**
 * Unit tests for Database Actions.
 *
 * Tests cover:
 * - Class structure (AsAction trait, handle method)
 * - Job queue configuration
 * - Early-exit when server is not functional
 */

use App\Actions\Database\RestartDatabase;
use App\Actions\Database\StartDatabase;
use App\Actions\Database\StopDatabase;
use App\Models\Server;
use App\Models\StandalonePostgresql;
use Lorisleiva\Actions\Concerns\AsAction;

afterEach(fn () => Mockery::close());

// ─── StopDatabase ─────────────────────────────────────────────────────────────

test('StopDatabase uses AsAction trait', function () {
    expect(in_array(AsAction::class, class_uses_recursive(StopDatabase::class)))->toBeTrue();
});

test('StopDatabase has handle method', function () {
    expect(method_exists(StopDatabase::class, 'handle'))->toBeTrue();
});

test('StopDatabase returns server not functional message when server is not functional', function () {
    $server = Mockery::mock(Server::class)->makePartial();
    $server->shouldReceive('isFunctional')->andReturn(false);

    $destination = (object) ['server' => $server];

    $database = Mockery::mock(StandalonePostgresql::class)->makePartial();
    $database->setRelation('destination', $destination);
    $database->setRelation('environment', null); // prevents DB call in finally block

    $result = (new StopDatabase)->handle($database);

    expect($result)->toBe('Server is not functional');
});

// ─── StartDatabase ────────────────────────────────────────────────────────────

test('StartDatabase uses AsAction trait', function () {
    expect(in_array(AsAction::class, class_uses_recursive(StartDatabase::class)))->toBeTrue();
});

test('StartDatabase has handle method', function () {
    expect(method_exists(StartDatabase::class, 'handle'))->toBeTrue();
});

test('StartDatabase job queue is high', function () {
    $action = new StartDatabase;
    expect($action->jobQueue)->toBe('high');
});

test('StartDatabase returns server not functional message when server is not functional', function () {
    $server = Mockery::mock(Server::class)->makePartial();
    $server->shouldReceive('isFunctional')->andReturn(false);

    $destination = (object) ['server' => $server];

    $database = Mockery::mock(StandalonePostgresql::class)->makePartial();
    $database->setRelation('destination', $destination);

    $result = (new StartDatabase)->handle($database);

    expect($result)->toBe('Server is not functional');
});

// ─── RestartDatabase ──────────────────────────────────────────────────────────

test('RestartDatabase uses AsAction trait', function () {
    expect(in_array(AsAction::class, class_uses_recursive(RestartDatabase::class)))->toBeTrue();
});

test('RestartDatabase has handle method', function () {
    expect(method_exists(RestartDatabase::class, 'handle'))->toBeTrue();
});

test('RestartDatabase returns server not functional message when server is not functional', function () {
    $server = Mockery::mock(Server::class)->makePartial();
    $server->shouldReceive('isFunctional')->andReturn(false);

    $destination = (object) ['server' => $server];

    $database = Mockery::mock(StandalonePostgresql::class)->makePartial();
    $database->setRelation('destination', $destination);

    $result = (new RestartDatabase)->handle($database);

    expect($result)->toBe('Server is not functional');
});
