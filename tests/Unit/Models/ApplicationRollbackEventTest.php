<?php

use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\ApplicationRollbackEvent;
use App\Models\User;

// Fillable Security Tests
test('fillable attributes are defined and not empty', function () {
    $event = new ApplicationRollbackEvent;
    expect($event->getFillable())->not->toBeEmpty();
});

test('fillable does not include id', function () {
    $fillable = (new ApplicationRollbackEvent)->getFillable();
    expect($fillable)->not->toContain('id');
});

test('fillable includes all required fields', function () {
    $fillable = (new ApplicationRollbackEvent)->getFillable();

    expect($fillable)
        ->toContain('application_id')
        ->toContain('failed_deployment_id')
        ->toContain('rollback_deployment_id')
        ->toContain('triggered_by_user_id')
        ->toContain('trigger_reason')
        ->toContain('trigger_type')
        ->toContain('metrics_snapshot')
        ->toContain('status')
        ->toContain('error_message')
        ->toContain('from_commit')
        ->toContain('to_commit')
        ->toContain('triggered_at')
        ->toContain('completed_at');
});

// Casts Tests
test('metrics_snapshot is cast to array', function () {
    $casts = (new ApplicationRollbackEvent)->getCasts();
    expect($casts['metrics_snapshot'])->toBe('array');
});

test('datetime fields are cast correctly', function () {
    $casts = (new ApplicationRollbackEvent)->getCasts();
    expect($casts['triggered_at'])->toBe('datetime')
        ->and($casts['completed_at'])->toBe('datetime');
});

// Constants Tests
test('has valid trigger reason constants', function () {
    expect(ApplicationRollbackEvent::REASON_CRASH_LOOP)->toBe('crash_loop')
        ->and(ApplicationRollbackEvent::REASON_HEALTH_CHECK_FAILED)->toBe('health_check_failed')
        ->and(ApplicationRollbackEvent::REASON_CONTAINER_EXITED)->toBe('container_exited')
        ->and(ApplicationRollbackEvent::REASON_MANUAL)->toBe('manual')
        ->and(ApplicationRollbackEvent::REASON_ERROR_RATE)->toBe('error_rate_exceeded');
});

test('has valid status constants', function () {
    expect(ApplicationRollbackEvent::STATUS_TRIGGERED)->toBe('triggered')
        ->and(ApplicationRollbackEvent::STATUS_IN_PROGRESS)->toBe('in_progress')
        ->and(ApplicationRollbackEvent::STATUS_SUCCESS)->toBe('success')
        ->and(ApplicationRollbackEvent::STATUS_FAILED)->toBe('failed')
        ->and(ApplicationRollbackEvent::STATUS_SKIPPED)->toBe('skipped');
});

// Relationship Tests
test('application relationship returns belongsTo Application', function () {
    $relation = (new ApplicationRollbackEvent)->application();
    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Application::class);
});

test('failedDeployment relationship returns belongsTo', function () {
    $relation = (new ApplicationRollbackEvent)->failedDeployment();
    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(ApplicationDeploymentQueue::class);
});

test('rollbackDeployment relationship returns belongsTo', function () {
    $relation = (new ApplicationRollbackEvent)->rollbackDeployment();
    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(ApplicationDeploymentQueue::class);
});

test('triggeredByUser relationship returns belongsTo User', function () {
    $relation = (new ApplicationRollbackEvent)->triggeredByUser();
    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(User::class);
});

// UI Helper Tests
test('getReasonLabel returns human readable labels', function () {
    $event = new ApplicationRollbackEvent;

    $event->trigger_reason = ApplicationRollbackEvent::REASON_CRASH_LOOP;
    expect($event->getReasonLabel())->toBe('Crash Loop Detected');

    $event->trigger_reason = ApplicationRollbackEvent::REASON_HEALTH_CHECK_FAILED;
    expect($event->getReasonLabel())->toBe('Health Check Failed');

    $event->trigger_reason = ApplicationRollbackEvent::REASON_CONTAINER_EXITED;
    expect($event->getReasonLabel())->toBe('Container Exited');

    $event->trigger_reason = ApplicationRollbackEvent::REASON_MANUAL;
    expect($event->getReasonLabel())->toBe('Manual Rollback');

    $event->trigger_reason = ApplicationRollbackEvent::REASON_ERROR_RATE;
    expect($event->getReasonLabel())->toBe('Error Rate Exceeded');
});

test('getReasonLabel handles unknown reasons gracefully', function () {
    $event = new ApplicationRollbackEvent;
    $event->trigger_reason = 'some_new_reason';

    expect($event->getReasonLabel())->toBe('Some new reason');
});

test('getStatusBadgeClass returns correct CSS classes', function () {
    $event = new ApplicationRollbackEvent;

    $event->status = ApplicationRollbackEvent::STATUS_SUCCESS;
    expect($event->getStatusBadgeClass())->toContain('green');

    $event->status = ApplicationRollbackEvent::STATUS_FAILED;
    expect($event->getStatusBadgeClass())->toContain('red');

    $event->status = ApplicationRollbackEvent::STATUS_IN_PROGRESS;
    expect($event->getStatusBadgeClass())->toContain('yellow');

    $event->status = ApplicationRollbackEvent::STATUS_TRIGGERED;
    expect($event->getStatusBadgeClass())->toContain('blue');

    $event->status = ApplicationRollbackEvent::STATUS_SKIPPED;
    expect($event->getStatusBadgeClass())->toContain('neutral');
});

// Application model rollback relationships
test('Application model has rollbackEvents relationship', function () {
    $relation = (new Application)->rollbackEvents();
    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(ApplicationRollbackEvent::class);
});

test('Application model has lastSuccessfulDeployment relationship', function () {
    $relation = (new Application)->lastSuccessfulDeployment();
    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(ApplicationDeploymentQueue::class);
});

test('Application fillable includes rollback tracking fields', function () {
    $fillable = (new Application)->getFillable();
    expect($fillable)
        ->toContain('restart_count')
        ->toContain('last_restart_at')
        ->toContain('last_restart_type')
        ->toContain('last_successful_deployment_id');
});
