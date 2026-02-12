<?php

use App\Models\SlackNotificationSettings;
use App\Models\Team;

// Fillable Security Tests
test('fillable attributes are defined and not empty', function () {
    $model = new SlackNotificationSettings;
    expect($model->getFillable())->not->toBeEmpty();
});

test('fillable does not include id', function () {
    $fillable = (new SlackNotificationSettings)->getFillable();

    expect($fillable)->not->toContain('id');
});

test('fillable includes team_id', function () {
    $fillable = (new SlackNotificationSettings)->getFillable();

    expect($fillable)->toContain('team_id');
});

test('fillable includes Slack configuration fields', function () {
    $fillable = (new SlackNotificationSettings)->getFillable();

    expect($fillable)
        ->toContain('slack_enabled')
        ->toContain('slack_webhook_url');
});

test('fillable includes notification preference fields', function () {
    $fillable = (new SlackNotificationSettings)->getFillable();

    expect($fillable)
        ->toContain('deployment_success_slack_notifications')
        ->toContain('deployment_failure_slack_notifications')
        ->toContain('deployment_approval_required_slack_notifications')
        ->toContain('status_change_slack_notifications')
        ->toContain('backup_success_slack_notifications')
        ->toContain('backup_failure_slack_notifications')
        ->toContain('scheduled_task_success_slack_notifications')
        ->toContain('scheduled_task_failure_slack_notifications')
        ->toContain('docker_cleanup_slack_notifications')
        ->toContain('server_disk_usage_slack_notifications')
        ->toContain('server_reachable_slack_notifications')
        ->toContain('server_unreachable_slack_notifications')
        ->toContain('server_patch_slack_notifications')
        ->toContain('traefik_outdated_slack_notifications');
});

// Cast Tests
test('boolean fields are cast to boolean', function () {
    $casts = (new SlackNotificationSettings)->getCasts();

    expect($casts)
        ->toHaveKey('slack_enabled', 'boolean')
        ->toHaveKey('deployment_success_slack_notifications', 'boolean')
        ->toHaveKey('deployment_failure_slack_notifications', 'boolean')
        ->toHaveKey('deployment_approval_required_slack_notifications', 'boolean')
        ->toHaveKey('status_change_slack_notifications', 'boolean')
        ->toHaveKey('backup_success_slack_notifications', 'boolean')
        ->toHaveKey('backup_failure_slack_notifications', 'boolean')
        ->toHaveKey('scheduled_task_success_slack_notifications', 'boolean')
        ->toHaveKey('scheduled_task_failure_slack_notifications', 'boolean')
        ->toHaveKey('docker_cleanup_slack_notifications', 'boolean')
        ->toHaveKey('server_disk_usage_slack_notifications', 'boolean')
        ->toHaveKey('server_reachable_slack_notifications', 'boolean')
        ->toHaveKey('server_unreachable_slack_notifications', 'boolean')
        ->toHaveKey('server_patch_slack_notifications', 'boolean')
        ->toHaveKey('traefik_outdated_slack_notifications', 'boolean');
});

test('sensitive fields are encrypted', function () {
    $casts = (new SlackNotificationSettings)->getCasts();

    expect($casts)->toHaveKey('slack_webhook_url', 'encrypted');
});

// Trait Tests
test('uses Notifiable trait', function () {
    $traits = class_uses(SlackNotificationSettings::class);

    expect($traits)->toContain(\Illuminate\Notifications\Notifiable::class);
});

// Relationship Tests
test('team relationship returns belongsTo', function () {
    $relation = (new SlackNotificationSettings)->team();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Team::class);
});

// Method Tests
test('isEnabled method exists and works', function () {
    $model = new SlackNotificationSettings;
    expect(method_exists($model, 'isEnabled'))->toBeTrue();

    $model->slack_enabled = false;
    expect($model->isEnabled())->toBeFalse();

    $model->slack_enabled = true;
    expect($model->isEnabled())->toBeTrue();
});

// Timestamps Tests
test('timestamps are disabled', function () {
    $model = new SlackNotificationSettings;
    expect($model->timestamps)->toBeFalse();
});

// Attribute Tests
test('slack_enabled attribute works', function () {
    $model = new SlackNotificationSettings;
    $model->slack_enabled = true;

    expect($model->slack_enabled)->toBeTrue();
});

test('deployment_success_slack_notifications attribute works', function () {
    $model = new SlackNotificationSettings;
    $model->deployment_success_slack_notifications = true;

    expect($model->deployment_success_slack_notifications)->toBeTrue();
});
