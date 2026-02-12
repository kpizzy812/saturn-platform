<?php

use App\Models\Application;
use App\Models\ApplicationSetting;

// Fillable Security Tests
test('fillable attributes are defined and not empty', function () {
    $setting = new ApplicationSetting;
    expect($setting->getFillable())->not->toBeEmpty();
});

test('fillable does not include id', function () {
    $fillable = (new ApplicationSetting)->getFillable();
    expect($fillable)->not->toContain('id');
});

test('fillable includes application_id', function () {
    $fillable = (new ApplicationSetting)->getFillable();
    expect($fillable)->toContain('application_id');
});

test('fillable includes deployment settings', function () {
    $fillable = (new ApplicationSetting)->getFillable();

    expect($fillable)
        ->toContain('is_auto_deploy_enabled')
        ->toContain('is_force_https_enabled')
        ->toContain('is_preview_deployments_enabled')
        ->toContain('is_pr_deployments_public_enabled')
        ->toContain('is_debug_enabled');
});

test('fillable includes build settings', function () {
    $fillable = (new ApplicationSetting)->getFillable();

    expect($fillable)
        ->toContain('is_static')
        ->toContain('is_spa')
        ->toContain('is_build_server_enabled')
        ->toContain('use_build_secrets')
        ->toContain('inject_build_args_to_dockerfile')
        ->toContain('include_source_commit_in_build');
});

test('fillable includes git settings', function () {
    $fillable = (new ApplicationSetting)->getFillable();

    expect($fillable)
        ->toContain('is_git_submodules_enabled')
        ->toContain('is_git_lfs_enabled')
        ->toContain('is_git_shallow_clone_enabled');
});

test('fillable includes rollback settings', function () {
    $fillable = (new ApplicationSetting)->getFillable();

    expect($fillable)
        ->toContain('auto_rollback_enabled')
        ->toContain('rollback_validation_seconds')
        ->toContain('rollback_max_restarts')
        ->toContain('rollback_on_health_check_fail')
        ->toContain('rollback_on_crash_loop');
});

test('fillable includes container label settings', function () {
    $fillable = (new ApplicationSetting)->getFillable();

    expect($fillable)
        ->toContain('is_container_label_escape_enabled')
        ->toContain('is_container_label_readonly_enabled')
        ->toContain('is_preserve_repository_enabled');
});

test('fillable includes docker_images_to_keep', function () {
    $fillable = (new ApplicationSetting)->getFillable();
    expect($fillable)->toContain('docker_images_to_keep');
});

// Casts Tests - Boolean fields
test('all boolean settings are cast to boolean', function () {
    $casts = (new ApplicationSetting)->getCasts();

    $booleanFields = [
        'is_static', 'is_spa', 'is_build_server_enabled',
        'is_preserve_repository_enabled', 'is_container_label_escape_enabled',
        'is_container_label_readonly_enabled', 'use_build_secrets',
        'inject_build_args_to_dockerfile', 'include_source_commit_in_build',
        'is_auto_deploy_enabled', 'is_force_https_enabled', 'is_debug_enabled',
        'is_preview_deployments_enabled', 'is_pr_deployments_public_enabled',
        'is_git_submodules_enabled', 'is_git_lfs_enabled',
        'is_git_shallow_clone_enabled', 'auto_rollback_enabled',
        'rollback_on_health_check_fail', 'rollback_on_crash_loop',
    ];

    foreach ($booleanFields as $field) {
        expect($casts[$field])->toBe('boolean', "Field $field should be boolean");
    }
});

// Casts Tests - Integer fields
test('integer settings are cast to integer', function () {
    $casts = (new ApplicationSetting)->getCasts();

    expect($casts['docker_images_to_keep'])->toBe('integer')
        ->and($casts['rollback_validation_seconds'])->toBe('integer')
        ->and($casts['rollback_max_restarts'])->toBe('integer');
});

// Relationship Tests
test('application relationship returns belongsTo', function () {
    $relation = (new ApplicationSetting)->application();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Application::class);
});

// Trait Tests
test('uses Auditable trait', function () {
    expect(class_uses_recursive(new ApplicationSetting))->toContain(\App\Traits\Auditable::class);
});

test('uses LogsActivity trait', function () {
    expect(class_uses_recursive(new ApplicationSetting))->toContain(\Spatie\Activitylog\Traits\LogsActivity::class);
});

// Activity Log Tests
test('getActivitylogOptions returns LogOptions', function () {
    $setting = new ApplicationSetting;
    $options = $setting->getActivitylogOptions();

    expect($options)->toBeInstanceOf(\Spatie\Activitylog\LogOptions::class);
});
