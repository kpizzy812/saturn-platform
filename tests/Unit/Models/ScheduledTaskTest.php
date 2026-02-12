<?php

use App\Models\Application;
use App\Models\ScheduledTask;
use App\Models\ScheduledTaskExecution;
use App\Models\Service;

// Fillable Security Tests
test('fillable attributes are defined and not empty', function () {
    $task = new ScheduledTask;
    expect($task->getFillable())->not->toBeEmpty();
});

test('fillable does not include id', function () {
    $fillable = (new ScheduledTask)->getFillable();

    expect($fillable)->not->toContain('id');
});

test('fillable does not include relationship fields', function () {
    $fillable = (new ScheduledTask)->getFillable();

    expect($fillable)
        ->not->toContain('service_id')
        ->not->toContain('application_id');
});

test('fillable includes expected fields', function () {
    $fillable = (new ScheduledTask)->getFillable();

    expect($fillable)
        ->toContain('uuid')
        ->toContain('name')
        ->toContain('command')
        ->toContain('frequency')
        ->toContain('container')
        ->toContain('enabled')
        ->toContain('timeout');
});

// Casts Tests
test('enabled is cast to boolean', function () {
    $casts = (new ScheduledTask)->getCasts();
    expect($casts['enabled'])->toBe('boolean');
});

test('timeout is cast to integer', function () {
    $casts = (new ScheduledTask)->getCasts();
    expect($casts['timeout'])->toBe('integer');
});

// Relationship Tests
test('service relationship returns belongsTo', function () {
    $relation = (new ScheduledTask)->service();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Service::class);
});

test('application relationship returns belongsTo', function () {
    $relation = (new ScheduledTask)->application();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Application::class);
});

test('latest_log relationship returns hasOne', function () {
    $relation = (new ScheduledTask)->latest_log();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasOne::class)
        ->and($relation->getRelated())->toBeInstanceOf(ScheduledTaskExecution::class);
});

test('executions relationship returns hasMany', function () {
    $relation = (new ScheduledTask)->executions();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(ScheduledTaskExecution::class);
});

// Trait Tests
test('uses Auditable trait', function () {
    expect(class_uses_recursive(new ScheduledTask))->toContain(\App\Traits\Auditable::class);
});

test('uses HasSafeStringAttribute trait', function () {
    expect(class_uses_recursive(new ScheduledTask))->toContain(\App\Traits\HasSafeStringAttribute::class);
});

test('uses LogsActivity trait', function () {
    expect(class_uses_recursive(new ScheduledTask))->toContain(\Spatie\Activitylog\Traits\LogsActivity::class);
});

// Activity Log Tests
test('getActivitylogOptions returns LogOptions', function () {
    $task = new ScheduledTask;
    $options = $task->getActivitylogOptions();

    expect($options)->toBeInstanceOf(\Spatie\Activitylog\LogOptions::class);
});

// Attribute Tests
test('name attribute works', function () {
    $task = new ScheduledTask;
    $task->name = 'Daily Backup';

    expect($task->name)->toBe('Daily Backup');
});

test('command attribute works', function () {
    $task = new ScheduledTask;
    $task->command = 'php artisan backup:run';

    expect($task->command)->toBe('php artisan backup:run');
});

test('frequency attribute works', function () {
    $task = new ScheduledTask;
    $task->frequency = '0 0 * * *';

    expect($task->frequency)->toBe('0 0 * * *');
});

test('container attribute works', function () {
    $task = new ScheduledTask;
    $task->container = 'app-container';

    expect($task->container)->toBe('app-container');
});

test('enabled attribute works', function () {
    $task = new ScheduledTask;
    $task->enabled = true;

    expect($task->enabled)->toBeTrue();
});

test('timeout attribute works', function () {
    $task = new ScheduledTask;
    $task->timeout = 3600;

    expect($task->timeout)->toBe(3600);
});
