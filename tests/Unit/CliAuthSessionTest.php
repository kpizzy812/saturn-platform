<?php

use App\Models\CliAuthSession;
use Carbon\Carbon;

test('CliAuthSession model has correct fillable attributes', function () {
    $model = new CliAuthSession;

    expect($model->getFillable())->toBe([
        'code',
        'secret',
        'status',
        'user_id',
        'team_id',
        'token_plain',
        'ip_address',
        'user_agent',
        'expires_at',
    ]);
});

test('CliAuthSession casts expires_at to datetime', function () {
    $model = new CliAuthSession;
    $casts = $model->getCasts();

    expect($casts)->toHaveKey('expires_at')
        ->and($casts['expires_at'])->toBe('datetime');
});

test('CliAuthSession casts token_plain to encrypted', function () {
    $model = new CliAuthSession;
    $casts = $model->getCasts();

    expect($casts)->toHaveKey('token_plain')
        ->and($casts['token_plain'])->toBe('encrypted');
});

test('isExpired returns true for past dates', function () {
    $session = new CliAuthSession;
    $session->expires_at = Carbon::now()->subMinutes(10);

    expect($session->isExpired())->toBeTrue();
});

test('isExpired returns false for future dates', function () {
    $session = new CliAuthSession;
    $session->expires_at = Carbon::now()->addMinutes(10);

    expect($session->isExpired())->toBeFalse();
});

test('scopePending filters by pending status', function () {
    $builder = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $builder->shouldReceive('where')->with('status', 'pending')->once()->andReturnSelf();

    $session = new CliAuthSession;
    $result = $session->scopePending($builder);

    expect($result)->toBe($builder);
});

test('scopeNotExpired filters by future expires_at', function () {
    Carbon::setTestNow(Carbon::create(2026, 2, 20, 12, 0, 0));

    $builder = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $builder->shouldReceive('where')
        ->withArgs(function ($column, $operator, $value) {
            return $column === 'expires_at' && $operator === '>' && $value instanceof Carbon;
        })
        ->once()
        ->andReturnSelf();

    $session = new CliAuthSession;
    $result = $session->scopeNotExpired($builder);

    expect($result)->toBe($builder);

    Carbon::setTestNow();
});

test('prunable returns query for expired sessions older than 1 hour', function () {
    $session = new CliAuthSession;
    $query = $session->prunable();

    // Prunable should create a builder with where clause
    expect($query)->toBeInstanceOf(\Illuminate\Database\Eloquent\Builder::class);
});

test('CliAuthSession has user relationship', function () {
    $session = new CliAuthSession;

    expect($session->user())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

test('CliAuthSession has team relationship', function () {
    $session = new CliAuthSession;

    expect($session->team())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});
