<?php

use App\Models\DiscordNotificationSettings;
use App\Models\Team;

// Fillable Security Tests
test('fillable attributes are defined and not empty', function () {
    $model = new DiscordNotificationSettings;
    expect($model->getFillable())->not->toBeEmpty();
});

test('fillable does not include id', function () {
    $fillable = (new DiscordNotificationSettings)->getFillable();

    expect($fillable)->not->toContain('id');
});

test('fillable includes team_id', function () {
    $fillable = (new DiscordNotificationSettings)->getFillable();

    expect($fillable)->toContain('team_id');
});

test('fillable includes Discord configuration fields', function () {
    $fillable = (new DiscordNotificationSettings)->getFillable();

    expect($fillable)
        ->toContain('discord_enabled')
        ->toContain('discord_webhook_url')
        ->toContain('discord_ping_enabled');
});

test('fillable includes notification preference fields', function () {
    $fillable = (new DiscordNotificationSettings)->getFillable();

    expect($fillable)
        ->toContain('deployment_success_discord_notifications')
        ->toContain('deployment_failure_discord_notifications')
        ->toContain('deployment_approval_required_discord_notifications')
        ->toContain('status_change_discord_notifications')
        ->toContain('backup_success_discord_notifications')
        ->toContain('backup_failure_discord_notifications')
        ->toContain('scheduled_task_success_discord_notifications')
        ->toContain('scheduled_task_failure_discord_notifications')
        ->toContain('docker_cleanup_discord_notifications')
        ->toContain('server_disk_usage_discord_notifications')
        ->toContain('server_reachable_discord_notifications')
        ->toContain('server_unreachable_discord_notifications')
        ->toContain('server_patch_discord_notifications')
        ->toContain('traefik_outdated_discord_notifications');
});

// Cast Tests
test('boolean fields are cast to boolean', function () {
    $casts = (new DiscordNotificationSettings)->getCasts();

    expect($casts)
        ->toHaveKey('discord_enabled', 'boolean')
        ->toHaveKey('discord_ping_enabled', 'boolean')
        ->toHaveKey('deployment_success_discord_notifications', 'boolean')
        ->toHaveKey('deployment_failure_discord_notifications', 'boolean')
        ->toHaveKey('deployment_approval_required_discord_notifications', 'boolean')
        ->toHaveKey('status_change_discord_notifications', 'boolean')
        ->toHaveKey('backup_success_discord_notifications', 'boolean')
        ->toHaveKey('backup_failure_discord_notifications', 'boolean')
        ->toHaveKey('scheduled_task_success_discord_notifications', 'boolean')
        ->toHaveKey('scheduled_task_failure_discord_notifications', 'boolean')
        ->toHaveKey('docker_cleanup_discord_notifications', 'boolean')
        ->toHaveKey('server_disk_usage_discord_notifications', 'boolean')
        ->toHaveKey('server_reachable_discord_notifications', 'boolean')
        ->toHaveKey('server_unreachable_discord_notifications', 'boolean')
        ->toHaveKey('server_patch_discord_notifications', 'boolean')
        ->toHaveKey('traefik_outdated_discord_notifications', 'boolean');
});

test('sensitive fields are encrypted', function () {
    $casts = (new DiscordNotificationSettings)->getCasts();

    expect($casts)->toHaveKey('discord_webhook_url', 'encrypted');
});

// Trait Tests
test('uses Notifiable trait', function () {
    $traits = class_uses(DiscordNotificationSettings::class);

    expect($traits)->toContain(\Illuminate\Notifications\Notifiable::class);
});

// Relationship Tests
test('team relationship returns belongsTo', function () {
    $relation = (new DiscordNotificationSettings)->team();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Team::class);
});

// Method Tests
test('isEnabled method exists and works', function () {
    $model = new DiscordNotificationSettings;
    expect(method_exists($model, 'isEnabled'))->toBeTrue();

    $model->discord_enabled = false;
    expect($model->isEnabled())->toBeFalse();

    $model->discord_enabled = true;
    expect($model->isEnabled())->toBeTrue();
});

test('isPingEnabled method exists and works', function () {
    $model = new DiscordNotificationSettings;
    expect(method_exists($model, 'isPingEnabled'))->toBeTrue();

    $model->discord_ping_enabled = false;
    expect($model->isPingEnabled())->toBeFalse();

    $model->discord_ping_enabled = true;
    expect($model->isPingEnabled())->toBeTrue();
});

// Timestamps Tests
test('timestamps are disabled', function () {
    $model = new DiscordNotificationSettings;
    expect($model->timestamps)->toBeFalse();
});

// Attribute Tests
test('discord_enabled attribute works', function () {
    $model = new DiscordNotificationSettings;
    $model->discord_enabled = true;

    expect($model->discord_enabled)->toBeTrue();
});

test('discord_ping_enabled attribute works', function () {
    $model = new DiscordNotificationSettings;
    $model->discord_ping_enabled = true;

    expect($model->discord_ping_enabled)->toBeTrue();
});

test('deployment_success_discord_notifications attribute works', function () {
    $model = new DiscordNotificationSettings;
    $model->deployment_success_discord_notifications = true;

    expect($model->deployment_success_discord_notifications)->toBeTrue();
});
