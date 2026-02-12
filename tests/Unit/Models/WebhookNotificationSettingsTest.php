<?php

use App\Models\Team;
use App\Models\WebhookNotificationSettings;

// Fillable Security Tests
test('fillable attributes are defined and not empty', function () {
    $model = new WebhookNotificationSettings;
    expect($model->getFillable())->not->toBeEmpty();
});

test('fillable does not include id', function () {
    $fillable = (new WebhookNotificationSettings)->getFillable();

    expect($fillable)->not->toContain('id');
});

test('fillable includes team_id', function () {
    $fillable = (new WebhookNotificationSettings)->getFillable();

    expect($fillable)->toContain('team_id');
});

test('fillable includes Webhook configuration fields', function () {
    $fillable = (new WebhookNotificationSettings)->getFillable();

    expect($fillable)
        ->toContain('webhook_enabled')
        ->toContain('webhook_url');
});

test('fillable includes notification preference fields', function () {
    $fillable = (new WebhookNotificationSettings)->getFillable();

    expect($fillable)
        ->toContain('deployment_success_webhook_notifications')
        ->toContain('deployment_failure_webhook_notifications')
        ->toContain('deployment_approval_required_webhook_notifications')
        ->toContain('status_change_webhook_notifications')
        ->toContain('backup_success_webhook_notifications')
        ->toContain('backup_failure_webhook_notifications')
        ->toContain('scheduled_task_success_webhook_notifications')
        ->toContain('scheduled_task_failure_webhook_notifications')
        ->toContain('docker_cleanup_success_webhook_notifications')
        ->toContain('docker_cleanup_failure_webhook_notifications')
        ->toContain('server_disk_usage_webhook_notifications')
        ->toContain('server_reachable_webhook_notifications')
        ->toContain('server_unreachable_webhook_notifications')
        ->toContain('server_patch_webhook_notifications')
        ->toContain('traefik_outdated_webhook_notifications');
});

// Cast Tests
test('boolean fields are cast to boolean', function () {
    $casts = (new WebhookNotificationSettings)->getCasts();

    expect($casts)
        ->toHaveKey('webhook_enabled', 'boolean')
        ->toHaveKey('deployment_success_webhook_notifications', 'boolean')
        ->toHaveKey('deployment_failure_webhook_notifications', 'boolean')
        ->toHaveKey('deployment_approval_required_webhook_notifications', 'boolean')
        ->toHaveKey('status_change_webhook_notifications', 'boolean')
        ->toHaveKey('backup_success_webhook_notifications', 'boolean')
        ->toHaveKey('backup_failure_webhook_notifications', 'boolean')
        ->toHaveKey('scheduled_task_success_webhook_notifications', 'boolean')
        ->toHaveKey('scheduled_task_failure_webhook_notifications', 'boolean')
        ->toHaveKey('docker_cleanup_success_webhook_notifications', 'boolean')
        ->toHaveKey('docker_cleanup_failure_webhook_notifications', 'boolean')
        ->toHaveKey('server_disk_usage_webhook_notifications', 'boolean')
        ->toHaveKey('server_reachable_webhook_notifications', 'boolean')
        ->toHaveKey('server_unreachable_webhook_notifications', 'boolean')
        ->toHaveKey('server_patch_webhook_notifications', 'boolean')
        ->toHaveKey('traefik_outdated_webhook_notifications', 'boolean');
});

test('sensitive fields are encrypted', function () {
    $casts = (new WebhookNotificationSettings)->getCasts();

    expect($casts)->toHaveKey('webhook_url', 'encrypted');
});

// Trait Tests
test('uses Notifiable trait', function () {
    $traits = class_uses(WebhookNotificationSettings::class);

    expect($traits)->toContain(\Illuminate\Notifications\Notifiable::class);
});

// Relationship Tests
test('team relationship returns belongsTo', function () {
    $relation = (new WebhookNotificationSettings)->team();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Team::class);
});

// Method Tests
test('isEnabled method exists and works', function () {
    $model = new WebhookNotificationSettings;
    expect(method_exists($model, 'isEnabled'))->toBeTrue();

    $model->webhook_enabled = false;
    expect($model->isEnabled())->toBeFalse();

    $model->webhook_enabled = true;
    expect($model->isEnabled())->toBeTrue();
});

// Timestamps Tests
test('timestamps are disabled', function () {
    $model = new WebhookNotificationSettings;
    expect($model->timestamps)->toBeFalse();
});

// Casts Method Tests
test('casts method returns array', function () {
    $model = new WebhookNotificationSettings;
    expect(method_exists($model, 'casts'))->toBeTrue();
    expect($model->getCasts())->toBeArray();
});

// Attribute Tests
test('webhook_enabled attribute works', function () {
    $model = new WebhookNotificationSettings;
    $model->webhook_enabled = true;

    expect($model->webhook_enabled)->toBeTrue();
});

test('deployment_success_webhook_notifications attribute works', function () {
    $model = new WebhookNotificationSettings;
    $model->deployment_success_webhook_notifications = true;

    expect($model->deployment_success_webhook_notifications)->toBeTrue();
});
