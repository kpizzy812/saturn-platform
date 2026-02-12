<?php

use App\Models\Team;
use App\Models\TelegramNotificationSettings;

// Fillable Security Tests
test('fillable attributes are defined and not empty', function () {
    $model = new TelegramNotificationSettings;
    expect($model->getFillable())->not->toBeEmpty();
});

test('fillable does not include id', function () {
    $fillable = (new TelegramNotificationSettings)->getFillable();

    expect($fillable)->not->toContain('id');
});

test('fillable includes team_id', function () {
    $fillable = (new TelegramNotificationSettings)->getFillable();

    expect($fillable)->toContain('team_id');
});

test('fillable includes Telegram configuration fields', function () {
    $fillable = (new TelegramNotificationSettings)->getFillable();

    expect($fillable)
        ->toContain('telegram_enabled')
        ->toContain('telegram_token')
        ->toContain('telegram_chat_id');
});

test('fillable includes notification preference fields', function () {
    $fillable = (new TelegramNotificationSettings)->getFillable();

    expect($fillable)
        ->toContain('deployment_success_telegram_notifications')
        ->toContain('deployment_failure_telegram_notifications')
        ->toContain('deployment_approval_required_telegram_notifications')
        ->toContain('status_change_telegram_notifications')
        ->toContain('backup_success_telegram_notifications')
        ->toContain('backup_failure_telegram_notifications')
        ->toContain('scheduled_task_success_telegram_notifications')
        ->toContain('scheduled_task_failure_telegram_notifications')
        ->toContain('docker_cleanup_telegram_notifications')
        ->toContain('server_disk_usage_telegram_notifications')
        ->toContain('server_reachable_telegram_notifications')
        ->toContain('server_unreachable_telegram_notifications')
        ->toContain('server_patch_telegram_notifications')
        ->toContain('traefik_outdated_telegram_notifications');
});

test('fillable includes thread ID fields', function () {
    $fillable = (new TelegramNotificationSettings)->getFillable();

    expect($fillable)
        ->toContain('telegram_notifications_deployment_success_thread_id')
        ->toContain('telegram_notifications_deployment_failure_thread_id')
        ->toContain('telegram_notifications_deployment_approval_required_thread_id')
        ->toContain('telegram_notifications_status_change_thread_id')
        ->toContain('telegram_notifications_backup_success_thread_id')
        ->toContain('telegram_notifications_backup_failure_thread_id')
        ->toContain('telegram_notifications_scheduled_task_success_thread_id')
        ->toContain('telegram_notifications_scheduled_task_failure_thread_id')
        ->toContain('telegram_notifications_docker_cleanup_thread_id')
        ->toContain('telegram_notifications_server_disk_usage_thread_id')
        ->toContain('telegram_notifications_server_reachable_thread_id')
        ->toContain('telegram_notifications_server_unreachable_thread_id')
        ->toContain('telegram_notifications_server_patch_thread_id')
        ->toContain('telegram_notifications_traefik_outdated_thread_id');
});

// Cast Tests
test('boolean fields are cast to boolean', function () {
    $casts = (new TelegramNotificationSettings)->getCasts();

    expect($casts)
        ->toHaveKey('telegram_enabled', 'boolean')
        ->toHaveKey('deployment_success_telegram_notifications', 'boolean')
        ->toHaveKey('deployment_failure_telegram_notifications', 'boolean')
        ->toHaveKey('deployment_approval_required_telegram_notifications', 'boolean')
        ->toHaveKey('status_change_telegram_notifications', 'boolean')
        ->toHaveKey('backup_success_telegram_notifications', 'boolean')
        ->toHaveKey('backup_failure_telegram_notifications', 'boolean')
        ->toHaveKey('scheduled_task_success_telegram_notifications', 'boolean')
        ->toHaveKey('scheduled_task_failure_telegram_notifications', 'boolean')
        ->toHaveKey('docker_cleanup_telegram_notifications', 'boolean')
        ->toHaveKey('server_disk_usage_telegram_notifications', 'boolean')
        ->toHaveKey('server_reachable_telegram_notifications', 'boolean')
        ->toHaveKey('server_unreachable_telegram_notifications', 'boolean')
        ->toHaveKey('server_patch_telegram_notifications', 'boolean')
        ->toHaveKey('traefik_outdated_telegram_notifications', 'boolean');
});

test('sensitive fields are encrypted', function () {
    $casts = (new TelegramNotificationSettings)->getCasts();

    expect($casts)
        ->toHaveKey('telegram_token', 'encrypted')
        ->toHaveKey('telegram_chat_id', 'encrypted');
});

test('thread ID fields are encrypted', function () {
    $casts = (new TelegramNotificationSettings)->getCasts();

    expect($casts)
        ->toHaveKey('telegram_notifications_deployment_success_thread_id', 'encrypted')
        ->toHaveKey('telegram_notifications_deployment_failure_thread_id', 'encrypted')
        ->toHaveKey('telegram_notifications_deployment_approval_required_thread_id', 'encrypted')
        ->toHaveKey('telegram_notifications_status_change_thread_id', 'encrypted')
        ->toHaveKey('telegram_notifications_backup_success_thread_id', 'encrypted')
        ->toHaveKey('telegram_notifications_backup_failure_thread_id', 'encrypted')
        ->toHaveKey('telegram_notifications_scheduled_task_success_thread_id', 'encrypted')
        ->toHaveKey('telegram_notifications_scheduled_task_failure_thread_id', 'encrypted')
        ->toHaveKey('telegram_notifications_docker_cleanup_thread_id', 'encrypted')
        ->toHaveKey('telegram_notifications_server_disk_usage_thread_id', 'encrypted')
        ->toHaveKey('telegram_notifications_server_reachable_thread_id', 'encrypted')
        ->toHaveKey('telegram_notifications_server_unreachable_thread_id', 'encrypted')
        ->toHaveKey('telegram_notifications_server_patch_thread_id', 'encrypted')
        ->toHaveKey('telegram_notifications_traefik_outdated_thread_id', 'encrypted');
});

// Trait Tests
test('uses Notifiable trait', function () {
    $traits = class_uses(TelegramNotificationSettings::class);

    expect($traits)->toContain(\Illuminate\Notifications\Notifiable::class);
});

// Relationship Tests
test('team relationship returns belongsTo', function () {
    $relation = (new TelegramNotificationSettings)->team();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Team::class);
});

// Method Tests
test('isEnabled method exists and works', function () {
    $model = new TelegramNotificationSettings;
    expect(method_exists($model, 'isEnabled'))->toBeTrue();

    $model->telegram_enabled = false;
    expect($model->isEnabled())->toBeFalse();

    $model->telegram_enabled = true;
    expect($model->isEnabled())->toBeTrue();
});

// Timestamps Tests
test('timestamps are disabled', function () {
    $model = new TelegramNotificationSettings;
    expect($model->timestamps)->toBeFalse();
});

// Attribute Tests
test('telegram_enabled attribute works', function () {
    $model = new TelegramNotificationSettings;
    $model->telegram_enabled = true;

    expect($model->telegram_enabled)->toBeTrue();
});

test('deployment_success_telegram_notifications attribute works', function () {
    $model = new TelegramNotificationSettings;
    $model->deployment_success_telegram_notifications = true;

    expect($model->deployment_success_telegram_notifications)->toBeTrue();
});
