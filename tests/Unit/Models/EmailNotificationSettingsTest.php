<?php

use App\Models\EmailNotificationSettings;
use App\Models\Team;

// Fillable Security Tests
test('fillable attributes are defined and not empty', function () {
    $model = new EmailNotificationSettings;
    expect($model->getFillable())->not->toBeEmpty();
});

test('fillable does not include id', function () {
    $fillable = (new EmailNotificationSettings)->getFillable();

    expect($fillable)->not->toContain('id');
});

test('fillable includes team_id', function () {
    $fillable = (new EmailNotificationSettings)->getFillable();

    expect($fillable)->toContain('team_id');
});

test('fillable includes SMTP configuration fields', function () {
    $fillable = (new EmailNotificationSettings)->getFillable();

    expect($fillable)
        ->toContain('smtp_enabled')
        ->toContain('smtp_from_address')
        ->toContain('smtp_from_name')
        ->toContain('smtp_recipients')
        ->toContain('smtp_host')
        ->toContain('smtp_port')
        ->toContain('smtp_encryption')
        ->toContain('smtp_username')
        ->toContain('smtp_password')
        ->toContain('smtp_timeout');
});

test('fillable includes Resend configuration fields', function () {
    $fillable = (new EmailNotificationSettings)->getFillable();

    expect($fillable)
        ->toContain('resend_enabled')
        ->toContain('resend_api_key');
});

test('fillable includes notification preference fields', function () {
    $fillable = (new EmailNotificationSettings)->getFillable();

    expect($fillable)
        ->toContain('use_instance_email_settings')
        ->toContain('deployment_success_email_notifications')
        ->toContain('deployment_failure_email_notifications')
        ->toContain('deployment_approval_required_email_notifications')
        ->toContain('status_change_email_notifications')
        ->toContain('backup_success_email_notifications')
        ->toContain('backup_failure_email_notifications')
        ->toContain('scheduled_task_success_email_notifications')
        ->toContain('scheduled_task_failure_email_notifications')
        ->toContain('server_disk_usage_email_notifications')
        ->toContain('server_patch_email_notifications')
        ->toContain('traefik_outdated_email_notifications');
});

// Cast Tests
test('boolean fields are cast to boolean', function () {
    $casts = (new EmailNotificationSettings)->getCasts();

    expect($casts)
        ->toHaveKey('smtp_enabled', 'boolean')
        ->toHaveKey('resend_enabled', 'boolean')
        ->toHaveKey('use_instance_email_settings', 'boolean')
        ->toHaveKey('deployment_success_email_notifications', 'boolean')
        ->toHaveKey('deployment_failure_email_notifications', 'boolean')
        ->toHaveKey('deployment_approval_required_email_notifications', 'boolean')
        ->toHaveKey('status_change_email_notifications', 'boolean')
        ->toHaveKey('backup_success_email_notifications', 'boolean')
        ->toHaveKey('backup_failure_email_notifications', 'boolean')
        ->toHaveKey('scheduled_task_success_email_notifications', 'boolean')
        ->toHaveKey('scheduled_task_failure_email_notifications', 'boolean')
        ->toHaveKey('server_disk_usage_email_notifications', 'boolean')
        ->toHaveKey('server_patch_email_notifications', 'boolean')
        ->toHaveKey('traefik_outdated_email_notifications', 'boolean');
});

test('sensitive fields are encrypted', function () {
    $casts = (new EmailNotificationSettings)->getCasts();

    expect($casts)
        ->toHaveKey('smtp_from_address', 'encrypted')
        ->toHaveKey('smtp_from_name', 'encrypted')
        ->toHaveKey('smtp_recipients', 'encrypted')
        ->toHaveKey('smtp_host', 'encrypted')
        ->toHaveKey('smtp_username', 'encrypted')
        ->toHaveKey('smtp_password', 'encrypted')
        ->toHaveKey('resend_api_key', 'encrypted');
});

test('integer fields are cast to integer', function () {
    $casts = (new EmailNotificationSettings)->getCasts();

    expect($casts)
        ->toHaveKey('smtp_port', 'integer')
        ->toHaveKey('smtp_timeout', 'integer');
});

// Relationship Tests
test('team relationship returns belongsTo', function () {
    $relation = (new EmailNotificationSettings)->team();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Team::class);
});

// Method Tests
test('isEnabled method exists and works', function () {
    $model = new EmailNotificationSettings;
    expect(method_exists($model, 'isEnabled'))->toBeTrue();

    $model->smtp_enabled = false;
    $model->resend_enabled = false;
    $model->use_instance_email_settings = false;
    expect($model->isEnabled())->toBeFalse();

    $model->smtp_enabled = true;
    expect($model->isEnabled())->toBeTrue();
});

// Timestamps Tests
test('timestamps are disabled', function () {
    $model = new EmailNotificationSettings;
    expect($model->timestamps)->toBeFalse();
});

// Attribute Tests
test('smtp_enabled attribute works', function () {
    $model = new EmailNotificationSettings;
    $model->smtp_enabled = true;

    expect($model->smtp_enabled)->toBeTrue();
});

test('use_instance_email_settings attribute works', function () {
    $model = new EmailNotificationSettings;
    $model->use_instance_email_settings = true;

    expect($model->use_instance_email_settings)->toBeTrue();
});
