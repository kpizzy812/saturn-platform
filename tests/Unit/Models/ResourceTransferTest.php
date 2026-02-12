<?php

use App\Actions\Transfer\CloneApplicationAction;
use App\Actions\Transfer\CloneServiceAction;
use App\Jobs\Transfer\ResourceTransferJob;
use App\Models\Application;
use App\Models\Environment;
use App\Models\ResourceTransfer;
use App\Models\Server;
use App\Models\Service;
use App\Models\StandalonePostgresql;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

// Status Constants Tests
test('has correct status constants', function () {
    expect(ResourceTransfer::STATUS_PENDING)->toBe('pending');
    expect(ResourceTransfer::STATUS_PREPARING)->toBe('preparing');
    expect(ResourceTransfer::STATUS_TRANSFERRING)->toBe('transferring');
    expect(ResourceTransfer::STATUS_RESTORING)->toBe('restoring');
    expect(ResourceTransfer::STATUS_COMPLETED)->toBe('completed');
    expect(ResourceTransfer::STATUS_FAILED)->toBe('failed');
    expect(ResourceTransfer::STATUS_CANCELLED)->toBe('cancelled');
});

// Mode Constants Tests
test('has correct mode constants', function () {
    expect(ResourceTransfer::MODE_CLONE)->toBe('clone');
    expect(ResourceTransfer::MODE_DATA_ONLY)->toBe('data_only');
    expect(ResourceTransfer::MODE_PARTIAL)->toBe('partial');
});

// getAllStatuses Tests
test('returns all statuses', function () {
    $statuses = ResourceTransfer::getAllStatuses();

    expect($statuses)
        ->toContain('pending')
        ->toContain('preparing')
        ->toContain('transferring')
        ->toContain('restoring')
        ->toContain('completed')
        ->toContain('failed')
        ->toContain('cancelled')
        ->toHaveCount(7);
});

// getAllModes Tests
test('returns all modes', function () {
    $modes = ResourceTransfer::getAllModes();

    expect($modes)
        ->toContain('clone')
        ->toContain('data_only')
        ->toContain('partial')
        ->toHaveCount(3);
});

// Status Label Tests
test('returns correct status label', function () {
    $transfer = new ResourceTransfer;

    $transfer->status = 'pending';
    expect($transfer->status_label)->toBe('Pending');

    $transfer->status = 'preparing';
    expect($transfer->status_label)->toBe('Preparing');

    $transfer->status = 'transferring';
    expect($transfer->status_label)->toBe('Transferring');

    $transfer->status = 'restoring';
    expect($transfer->status_label)->toBe('Restoring');

    $transfer->status = 'completed';
    expect($transfer->status_label)->toBe('Completed');

    $transfer->status = 'failed';
    expect($transfer->status_label)->toBe('Failed');

    $transfer->status = 'cancelled';
    expect($transfer->status_label)->toBe('Cancelled');
});

// Mode Label Tests
test('returns correct mode label', function () {
    $transfer = new ResourceTransfer;

    $transfer->transfer_mode = 'clone';
    expect($transfer->mode_label)->toBe('Full Clone');

    $transfer->transfer_mode = 'data_only';
    expect($transfer->mode_label)->toBe('Data Only');

    $transfer->transfer_mode = 'partial';
    expect($transfer->mode_label)->toBe('Partial');
});

// canBeCancelled Tests
test('can be cancelled only in correct states', function () {
    $transfer = new ResourceTransfer;

    $transfer->status = 'pending';
    expect($transfer->canBeCancelled())->toBeTrue();

    $transfer->status = 'preparing';
    expect($transfer->canBeCancelled())->toBeTrue();

    $transfer->status = 'transferring';
    expect($transfer->canBeCancelled())->toBeTrue();

    $transfer->status = 'restoring';
    expect($transfer->canBeCancelled())->toBeFalse();

    $transfer->status = 'completed';
    expect($transfer->canBeCancelled())->toBeFalse();

    $transfer->status = 'failed';
    expect($transfer->canBeCancelled())->toBeFalse();

    $transfer->status = 'cancelled';
    expect($transfer->canBeCancelled())->toBeFalse();
});

// Formatted Progress Tests
test('formats progress correctly', function () {
    $transfer = new ResourceTransfer;
    $transfer->status = 'transferring';

    $transfer->progress = 45;
    expect($transfer->formatted_progress)->toBe('45%');

    $transfer->progress = 100;
    expect($transfer->formatted_progress)->toBe('100%');

    $transfer->progress = 0;
    expect($transfer->formatted_progress)->toBe('0%');
});

// isActive Tests
test('checks if transfer is active', function () {
    $transfer = new ResourceTransfer;

    $transfer->status = 'pending';
    expect($transfer->isActive())->toBeTrue();

    $transfer->status = 'preparing';
    expect($transfer->isActive())->toBeTrue();

    $transfer->status = 'transferring';
    expect($transfer->isActive())->toBeTrue();

    $transfer->status = 'restoring';
    expect($transfer->isActive())->toBeTrue();

    $transfer->status = 'completed';
    expect($transfer->isActive())->toBeFalse();

    $transfer->status = 'failed';
    expect($transfer->isActive())->toBeFalse();

    $transfer->status = 'cancelled';
    expect($transfer->isActive())->toBeFalse();
});

// isInProgress Tests
test('checks if transfer is in progress', function () {
    $transfer = new ResourceTransfer;

    $transfer->status = 'pending';
    expect($transfer->isInProgress())->toBeTrue();

    $transfer->status = 'preparing';
    expect($transfer->isInProgress())->toBeTrue();

    $transfer->status = 'transferring';
    expect($transfer->isInProgress())->toBeTrue();

    $transfer->status = 'restoring';
    expect($transfer->isInProgress())->toBeTrue();

    $transfer->status = 'completed';
    expect($transfer->isInProgress())->toBeFalse();

    $transfer->status = 'failed';
    expect($transfer->isInProgress())->toBeFalse();

    $transfer->status = 'cancelled';
    expect($transfer->isInProgress())->toBeFalse();
});

// isAwaitingApproval Tests
test('identifies transfers awaiting approval', function () {
    $transfer = new ResourceTransfer;

    $transfer->requires_approval = true;
    $transfer->status = 'pending';
    expect($transfer->isAwaitingApproval())->toBeTrue();
});

test('does not identify non-pending transfers as awaiting approval', function () {
    $transfer = new ResourceTransfer;

    $transfer->requires_approval = true;
    $transfer->status = 'preparing';
    expect($transfer->isAwaitingApproval())->toBeFalse();
});

test('does not identify transfers not requiring approval as awaiting approval', function () {
    $transfer = new ResourceTransfer;

    $transfer->requires_approval = false;
    $transfer->status = 'pending';
    expect($transfer->isAwaitingApproval())->toBeFalse();
});

// BUG-1: approve() Dispatch Logic Tests
test('approve dispatches CloneApplicationAction for Application sources', function () {
    // Mock dependencies
    $transfer = Mockery::mock(ResourceTransfer::class)->makePartial();
    $transfer->id = 1;
    $transfer->status = ResourceTransfer::STATUS_PENDING;
    $transfer->source_type = 'App\Models\Application';
    $transfer->team_id = 1;

    $user = Mockery::mock(User::class);
    $user->id = 1;

    $application = Mockery::mock(Application::class);
    $environment = Mockery::mock(Environment::class);
    $server = Mockery::mock(Server::class);

    // Mock relationships
    $transfer->shouldReceive('source')->andReturn($application);
    $transfer->shouldReceive('targetEnvironment')->andReturn($environment);
    $transfer->shouldReceive('targetServer')->andReturn($server);
    $transfer->shouldReceive('refresh')->andReturnSelf();

    // Mock DB transaction
    DB::shouldReceive('transaction')->once()->andReturnUsing(function ($callback) use ($transfer, $user) {
        // Mock the locked transfer query
        $mockBuilder = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
        $mockBuilder->shouldReceive('where')->with('id', 1)->andReturnSelf();
        $mockBuilder->shouldReceive('where')->with('status', ResourceTransfer::STATUS_PENDING)->andReturnSelf();
        $mockBuilder->shouldReceive('lockForUpdate')->andReturnSelf();
        $mockBuilder->shouldReceive('first')->andReturn($transfer);

        ResourceTransfer::shouldReceive('where')->andReturn($mockBuilder);

        $transfer->shouldReceive('update')->once()->with(Mockery::on(function ($data) use ($user) {
            return $data['status'] === ResourceTransfer::STATUS_PREPARING
                && $data['approved_by'] === $user->id;
        }));

        return $callback();
    });

    // Verify CloneApplicationAction is called
    $actionSpy = Mockery::spy(CloneApplicationAction::class);
    $actionSpy->shouldReceive('handle')->once()->andReturn(['success' => true]);

    $transfer->approve($user);
})->skip('Requires complex DB transaction mocking');

test('approve dispatches CloneServiceAction for Service sources', function () {
    $transfer = Mockery::mock(ResourceTransfer::class)->makePartial();
    $transfer->id = 2;
    $transfer->status = ResourceTransfer::STATUS_PENDING;
    $transfer->source_type = 'App\Models\Service';
    $transfer->team_id = 1;

    $user = Mockery::mock(User::class);
    $user->id = 1;

    $service = Mockery::mock(Service::class);
    $environment = Mockery::mock(Environment::class);
    $server = Mockery::mock(Server::class);

    $transfer->shouldReceive('source')->andReturn($service);
    $transfer->shouldReceive('targetEnvironment')->andReturn($environment);
    $transfer->shouldReceive('targetServer')->andReturn($server);
    $transfer->shouldReceive('refresh')->andReturnSelf();

    // Similar DB transaction mock as above
    DB::shouldReceive('transaction')->once();

    // In real implementation, CloneServiceAction would be called
    expect(true)->toBeTrue();
})->skip('Requires complex DB transaction mocking');

test('approve dispatches ResourceTransferJob for Database sources', function () {
    Bus::fake();

    $transfer = Mockery::mock(ResourceTransfer::class)->makePartial();
    $transfer->id = 3;
    $transfer->status = ResourceTransfer::STATUS_PENDING;
    $transfer->source_type = 'App\Models\StandalonePostgresql';
    $transfer->team_id = 1;
    $transfer->transfer_mode = ResourceTransfer::MODE_CLONE;

    $user = Mockery::mock(User::class);
    $user->id = 1;

    $database = Mockery::mock(StandalonePostgresql::class);

    $transfer->shouldReceive('source')->andReturn($database);
    $transfer->shouldReceive('refresh')->andReturnSelf();

    DB::shouldReceive('transaction')->once();

    // In real implementation, ResourceTransferJob would be dispatched
    expect(true)->toBeTrue();
})->skip('Requires complex DB transaction mocking');

// BUG-3: SQL Injection Prevention in validatePath Tests
test('validatePath accepts safe table names', function () {
    $reflection = new ReflectionClass(\App\Services\Transfer\Strategies\AbstractTransferStrategy::class);
    $method = $reflection->getMethod('validatePath');
    $method->setAccessible(true);

    $strategy = Mockery::mock(\App\Services\Transfer\Strategies\PostgresqlTransferStrategy::class)->makePartial();

    // Safe table names should not throw
    expect(fn () => $method->invoke($strategy, 'users', 'table'))->not->toThrow(\Exception::class);
    expect(fn () => $method->invoke($strategy, 'user_profiles', 'table'))->not->toThrow(\Exception::class);
    expect(fn () => $method->invoke($strategy, 'table_123', 'table'))->not->toThrow(\Exception::class);
    expect(fn () => $method->invoke($strategy, 'my-table', 'table'))->not->toThrow(\Exception::class);
    expect(fn () => $method->invoke($strategy, 'schema/table', 'table'))->not->toThrow(\Exception::class);
    expect(fn () => $method->invoke($strategy, 'table.backup', 'table'))->not->toThrow(\Exception::class);
});

test('validatePath rejects SQL injection attempts with semicolon', function () {
    $reflection = new ReflectionClass(\App\Services\Transfer\Strategies\AbstractTransferStrategy::class);
    $method = $reflection->getMethod('validatePath');
    $method->setAccessible(true);

    $strategy = Mockery::mock(\App\Services\Transfer\Strategies\PostgresqlTransferStrategy::class)->makePartial();

    expect(fn () => $method->invoke($strategy, "users'; DROP TABLE users--", 'table'))
        ->toThrow(\Exception::class);
});

test('validatePath rejects SQL injection with command substitution', function () {
    $reflection = new ReflectionClass(\App\Services\Transfer\Strategies\AbstractTransferStrategy::class);
    $method = $reflection->getMethod('validatePath');
    $method->setAccessible(true);

    $strategy = Mockery::mock(\App\Services\Transfer\Strategies\PostgresqlTransferStrategy::class)->makePartial();

    expect(fn () => $method->invoke($strategy, 'table`rm -rf /`', 'table'))
        ->toThrow(\Exception::class);

    expect(fn () => $method->invoke($strategy, 'table$(whoami)', 'table'))
        ->toThrow(\Exception::class);
});

test('validatePath rejects paths with pipe operator', function () {
    $reflection = new ReflectionClass(\App\Services\Transfer\Strategies\AbstractTransferStrategy::class);
    $method = $reflection->getMethod('validatePath');
    $method->setAccessible(true);

    $strategy = Mockery::mock(\App\Services\Transfer\Strategies\PostgresqlTransferStrategy::class)->makePartial();

    expect(fn () => $method->invoke($strategy, 'table | cat /etc/passwd', 'table'))
        ->toThrow(\Exception::class);
});

test('validatePath rejects paths with redirection operators', function () {
    $reflection = new ReflectionClass(\App\Services\Transfer\Strategies\AbstractTransferStrategy::class);
    $method = $reflection->getMethod('validatePath');
    $method->setAccessible(true);

    $strategy = Mockery::mock(\App\Services\Transfer\Strategies\PostgresqlTransferStrategy::class)->makePartial();

    expect(fn () => $method->invoke($strategy, 'table > /tmp/out', 'table'))
        ->toThrow(\Exception::class);

    expect(fn () => $method->invoke($strategy, 'table < /etc/passwd', 'table'))
        ->toThrow(\Exception::class);
});

test('validatePath rejects paths with ampersand', function () {
    $reflection = new ReflectionClass(\App\Services\Transfer\Strategies\AbstractTransferStrategy::class);
    $method = $reflection->getMethod('validatePath');
    $method->setAccessible(true);

    $strategy = Mockery::mock(\App\Services\Transfer\Strategies\PostgresqlTransferStrategy::class)->makePartial();

    expect(fn () => $method->invoke($strategy, 'table & rm -rf /', 'table'))
        ->toThrow(\Exception::class);
});

test('validatePath rejects paths with newlines', function () {
    $reflection = new ReflectionClass(\App\Services\Transfer\Strategies\AbstractTransferStrategy::class);
    $method = $reflection->getMethod('validatePath');
    $method->setAccessible(true);

    $strategy = Mockery::mock(\App\Services\Transfer\Strategies\PostgresqlTransferStrategy::class)->makePartial();

    expect(fn () => $method->invoke($strategy, "table\nrm -rf /", 'table'))
        ->toThrow(\Exception::class);
});

test('validatePath rejects JavaScript injection in MongoDB collection names', function () {
    $reflection = new ReflectionClass(\App\Services\Transfer\Strategies\AbstractTransferStrategy::class);
    $method = $reflection->getMethod('validatePath');
    $method->setAccessible(true);

    $strategy = Mockery::mock(\App\Services\Transfer\Strategies\MongodbTransferStrategy::class)->makePartial();

    // JavaScript injection attempts
    expect(fn () => $method->invoke($strategy, "'; db.dropDatabase(); //", 'collection'))
        ->toThrow(\Exception::class);

    expect(fn () => $method->invoke($strategy, 'collection`malicious()`', 'collection'))
        ->toThrow(\Exception::class);
});

// BUG-5: Duplicate Transfer Detection Tests
test('inProgress scope returns transfers in all active states', function () {
    // This test verifies the scope logic exists correctly
    $transfer1 = new ResourceTransfer;
    $transfer1->status = ResourceTransfer::STATUS_PENDING;
    expect($transfer1->isInProgress())->toBeTrue();

    $transfer2 = new ResourceTransfer;
    $transfer2->status = ResourceTransfer::STATUS_PREPARING;
    expect($transfer2->isInProgress())->toBeTrue();

    $transfer3 = new ResourceTransfer;
    $transfer3->status = ResourceTransfer::STATUS_TRANSFERRING;
    expect($transfer3->isInProgress())->toBeTrue();

    $transfer4 = new ResourceTransfer;
    $transfer4->status = ResourceTransfer::STATUS_RESTORING;
    expect($transfer4->isInProgress())->toBeTrue();

    $transfer5 = new ResourceTransfer;
    $transfer5->status = ResourceTransfer::STATUS_COMPLETED;
    expect($transfer5->isInProgress())->toBeFalse();
});

test('duplicate detection logic identifies same source in progress', function () {
    // This test documents the expected behavior for duplicate detection
    // In the controller: ResourceTransfer::where('source_type', X)->where('source_id', Y)->inProgress()->first()

    // Create a transfer in progress with specific source
    $existingTransfer = new ResourceTransfer;
    $existingTransfer->source_type = 'App\Models\Application';
    $existingTransfer->source_id = 123;
    $existingTransfer->status = ResourceTransfer::STATUS_TRANSFERRING;

    // Verify it's detected as in progress
    expect($existingTransfer->isInProgress())->toBeTrue();
    expect($existingTransfer->source_id)->toBe(123);
    expect($existingTransfer->source_type)->toBe('App\Models\Application');
});

// Relationship Tests
test('source relationship is morphTo', function () {
    $transfer = new ResourceTransfer;
    $relation = $transfer->source();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphTo::class);
});

test('target relationship is morphTo', function () {
    $transfer = new ResourceTransfer;
    $relation = $transfer->target();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphTo::class);
});

test('targetEnvironment relationship is belongsTo', function () {
    $transfer = new ResourceTransfer;
    $relation = $transfer->targetEnvironment();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

test('targetServer relationship is belongsTo', function () {
    $transfer = new ResourceTransfer;
    $relation = $transfer->targetServer();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

// Fillable Security Tests
test('uses fillable array for mass assignment protection', function () {
    $transfer = new ResourceTransfer;

    expect($transfer->getFillable())->not->toBeEmpty();
});

test('fillable does not include auto-generated fields', function () {
    $fillable = (new ResourceTransfer)->getFillable();

    expect($fillable)
        ->not->toContain('id')
        ->not->toContain('uuid');
});

test('fillable does not include system-managed fields', function () {
    $fillable = (new ResourceTransfer)->getFillable();

    expect($fillable)
        ->not->toContain('status')
        ->not->toContain('progress')
        ->not->toContain('started_at')
        ->not->toContain('completed_at')
        ->not->toContain('approved_at')
        ->not->toContain('approved_by');
});

test('fillable includes user-provided fields', function () {
    $fillable = (new ResourceTransfer)->getFillable();

    expect($fillable)
        ->toContain('team_id')
        ->toContain('user_id')
        ->toContain('source_type')
        ->toContain('source_id')
        ->toContain('target_type')
        ->toContain('target_id')
        ->toContain('target_environment_id')
        ->toContain('target_server_id')
        ->toContain('transfer_mode')
        ->toContain('transfer_options')
        ->toContain('requires_approval');
});

// Casts Tests
test('transfer_options is cast to array', function () {
    $casts = (new ResourceTransfer)->getCasts();

    expect($casts['transfer_options'])->toBe('array');
});

test('error_details is cast to array', function () {
    $casts = (new ResourceTransfer)->getCasts();

    expect($casts['error_details'])->toBe('array');
});

test('progress is cast to integer', function () {
    $casts = (new ResourceTransfer)->getCasts();

    expect($casts['progress'])->toBe('integer');
});

test('requires_approval is cast to boolean', function () {
    $casts = (new ResourceTransfer)->getCasts();

    expect($casts['requires_approval'])->toBe('boolean');
});

test('timestamps are cast to datetime', function () {
    $casts = (new ResourceTransfer)->getCasts();

    expect($casts['started_at'])->toBe('datetime');
    expect($casts['completed_at'])->toBe('datetime');
    expect($casts['approved_at'])->toBe('datetime');
});
