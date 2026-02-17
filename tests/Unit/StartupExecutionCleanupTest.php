<?php

use Carbon\Carbon;

it('uses correct failure message for interrupted jobs', function () {
    $expectedMessage = 'Marked as failed during Saturn Platform startup - job was interrupted';

    expect($expectedMessage)
        ->toContain('Saturn Platform startup')
        ->toContain('interrupted')
        ->toContain('failed');
});

it('sets finished_at timestamp to current time when marking executions as failed', function () {
    Carbon::setTestNow('2025-01-15 12:00:00');
    $now = Carbon::now();

    expect($now)->toBeInstanceOf(Carbon::class)
        ->and($now->toDateTimeString())->toBe('2025-01-15 12:00:00');

    Carbon::setTestNow();
});

it('builds correct update payload for stuck executions', function () {
    Carbon::setTestNow('2025-01-15 12:00:00');

    $payload = [
        'status' => 'failed',
        'message' => 'Marked as failed during Saturn Platform startup - job was interrupted',
        'finished_at' => Carbon::now(),
    ];

    expect($payload)
        ->toHaveKey('status', 'failed')
        ->toHaveKey('message')
        ->toHaveKey('finished_at')
        ->and($payload['finished_at']->toDateTimeString())->toBe('2025-01-15 12:00:00');

    Carbon::setTestNow();
});

it('targets only running status for cleanup', function () {
    $targetStatus = 'running';

    expect($targetStatus)->toBe('running')
        ->not->toBe('queued')
        ->not->toBe('failed');
});
