<?php

use App\Models\PushoverNotificationSettings;
use App\Models\Team;

// Fillable Security Tests
test('fillable attributes are defined and not empty', function () {
    $model = new PushoverNotificationSettings;
    expect($model->getFillable())->not->toBeEmpty();
});

test('fillable does not include id', function () {
    $fillable = (new PushoverNotificationSettings)->getFillable();

    expect($fillable)->not->toContain('id');
});

test('fillable includes team_id', function () {
    $fillable = (new PushoverNotificationSettings)->getFillable();

    expect($fillable)->toContain('team_id');
});

test('fillable includes Pushover configuration fields', function () {
    $fillable = (new PushoverNotificationSettings)->getFillable();

    expect($fillable)
        ->toContain('pushover_enabled')
        ->toContain('pushover_user_key')
        ->toContain('pushover_api_token');
});

test('fillable includes notification preference fields', function () {
    $fillable = (new PushoverNotificationSettings)->getFillable();

    expect($fillable)
        ->toContain('deployment_success_pushover_notifications')
        ->toContain('deployment_failure_pushover_notifications')
        ->toContain('deployment_approval_required_pushover_notifications')
        ->toContain('status_change_pushover_notifications')
        ->toContain('backup_success_pushover_notifications')
        ->toContain('backup_failure_pushover_notifications')
        ->toContain('scheduled_task_success_pushover_notifications')
        ->toContain('scheduled_task_failure_pushover_notifications')
        ->toContain('docker_cleanup_pushover_notifications')
        ->toContain('server_disk_usage_pushover_notifications')
        ->toContain('server_reachable_pushover_notifications')
        ->toContain('server_unreachable_pushover_notifications')
        ->toContain('server_patch_pushover_notifications')
        ->toContain('traefik_outdated_pushover_notifications');
});

// Cast Tests
test('boolean fields are cast to boolean', function () {
    $casts = (new PushoverNotificationSettings)->getCasts();

    expect($casts)
        ->toHaveKey('pushover_enabled', 'boolean')
        ->toHaveKey('deployment_success_pushover_notifications', 'boolean')
        ->toHaveKey('deployment_failure_pushover_notifications', 'boolean')
        ->toHaveKey('deployment_approval_required_pushover_notifications', 'boolean')
        ->toHaveKey('status_change_pushover_notifications', 'boolean')
        ->toHaveKey('backup_success_pushover_notifications', 'boolean')
        ->toHaveKey('backup_failure_pushover_notifications', 'boolean')
        ->toHaveKey('scheduled_task_success_pushover_notifications', 'boolean')
        ->toHaveKey('scheduled_task_failure_pushover_notifications', 'boolean')
        ->toHaveKey('docker_cleanup_pushover_notifications', 'boolean')
        ->toHaveKey('server_disk_usage_pushover_notifications', 'boolean')
        ->toHaveKey('server_reachable_pushover_notifications', 'boolean')
        ->toHaveKey('server_unreachable_pushover_notifications', 'boolean')
        ->toHaveKey('server_patch_pushover_notifications', 'boolean')
        ->toHaveKey('traefik_outdated_pushover_notifications', 'boolean');
});

test('sensitive fields are encrypted', function () {
    $casts = (new PushoverNotificationSettings)->getCasts();

    expect($casts)
        ->toHaveKey('pushover_user_key', 'encrypted')
        ->toHaveKey('pushover_api_token', 'encrypted');
});

// Trait Tests
test('uses Notifiable trait', function () {
    $traits = class_uses(PushoverNotificationSettings::class);

    expect($traits)->toContain(\Illuminate\Notifications\Notifiable::class);
});

// Relationship Tests
test('team relationship returns belongsTo', function () {
    $relation = (new PushoverNotificationSettings)->team();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Team::class);
});

// Method Tests
test('isEnabled method exists and works', function () {
    $model = new PushoverNotificationSettings;
    expect(method_exists($model, 'isEnabled'))->toBeTrue();

    $model->pushover_enabled = false;
    expect($model->isEnabled())->toBeFalse();

    $model->pushover_enabled = true;
    expect($model->isEnabled())->toBeTrue();
});

// Timestamps Tests
test('timestamps are disabled', function () {
    $model = new PushoverNotificationSettings;
    expect($model->timestamps)->toBeFalse();
});

// Attribute Tests
test('pushover_enabled attribute works', function () {
    $model = new PushoverNotificationSettings;
    $model->pushover_enabled = true;

    expect($model->pushover_enabled)->toBeTrue();
});

test('deployment_success_pushover_notifications attribute works', function () {
    $model = new PushoverNotificationSettings;
    $model->deployment_success_pushover_notifications = true;

    expect($model->deployment_success_pushover_notifications)->toBeTrue();
});
