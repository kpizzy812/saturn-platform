<?php

use App\Models\InstanceSettings;

test('getApplicationDefaults returns correct mapping with default values', function () {
    $settings = new InstanceSettings([
        'app_default_auto_deploy' => true,
        'app_default_force_https' => true,
        'app_default_preview_deployments' => false,
        'app_default_pr_deployments_public' => false,
        'app_default_git_submodules' => true,
        'app_default_git_lfs' => true,
        'app_default_git_shallow_clone' => true,
        'app_default_use_build_secrets' => false,
        'app_default_inject_build_args' => true,
        'app_default_include_commit_in_build' => false,
        'app_default_docker_images_to_keep' => 2,
        'app_default_auto_rollback' => false,
        'app_default_rollback_validation_sec' => 300,
        'app_default_rollback_max_restarts' => 3,
        'app_default_rollback_on_health_fail' => true,
        'app_default_rollback_on_crash_loop' => true,
        'app_default_debug' => false,
    ]);

    $defaults = $settings->getApplicationDefaults();

    // Verify all 18 keys are present (17 ApplicationSetting fields + build_pack on Application)
    expect($defaults)->toHaveCount(18);

    // Verify mapping to ApplicationSetting field names + build_pack
    expect($defaults)->toHaveKeys([
        'is_auto_deploy_enabled',
        'is_force_https_enabled',
        'is_preview_deployments_enabled',
        'is_pr_deployments_public_enabled',
        'is_git_submodules_enabled',
        'is_git_lfs_enabled',
        'is_git_shallow_clone_enabled',
        'use_build_secrets',
        'inject_build_args_to_dockerfile',
        'include_source_commit_in_build',
        'docker_images_to_keep',
        'auto_rollback_enabled',
        'rollback_validation_seconds',
        'rollback_max_restarts',
        'rollback_on_health_check_fail',
        'rollback_on_crash_loop',
        'is_debug_enabled',
        'build_pack',
    ]);

    // Verify values match
    expect($defaults['is_auto_deploy_enabled'])->toBeTrue();
    expect($defaults['is_force_https_enabled'])->toBeTrue();
    expect($defaults['is_preview_deployments_enabled'])->toBeFalse();
    expect($defaults['is_pr_deployments_public_enabled'])->toBeFalse();
    expect($defaults['is_git_submodules_enabled'])->toBeTrue();
    expect($defaults['is_git_lfs_enabled'])->toBeTrue();
    expect($defaults['is_git_shallow_clone_enabled'])->toBeTrue();
    expect($defaults['use_build_secrets'])->toBeFalse();
    expect($defaults['inject_build_args_to_dockerfile'])->toBeTrue();
    expect($defaults['include_source_commit_in_build'])->toBeFalse();
    expect($defaults['docker_images_to_keep'])->toBe(2);
    expect($defaults['auto_rollback_enabled'])->toBeFalse();
    expect($defaults['rollback_validation_seconds'])->toBe(300);
    expect($defaults['rollback_max_restarts'])->toBe(3);
    expect($defaults['rollback_on_health_check_fail'])->toBeTrue();
    expect($defaults['rollback_on_crash_loop'])->toBeTrue();
    expect($defaults['is_debug_enabled'])->toBeFalse();
});

test('getApplicationDefaults reflects custom values', function () {
    $settings = new InstanceSettings([
        'app_default_auto_deploy' => false,
        'app_default_force_https' => false,
        'app_default_preview_deployments' => true,
        'app_default_pr_deployments_public' => true,
        'app_default_git_submodules' => false,
        'app_default_git_lfs' => false,
        'app_default_git_shallow_clone' => false,
        'app_default_use_build_secrets' => true,
        'app_default_inject_build_args' => false,
        'app_default_include_commit_in_build' => true,
        'app_default_docker_images_to_keep' => 10,
        'app_default_auto_rollback' => true,
        'app_default_rollback_validation_sec' => 60,
        'app_default_rollback_max_restarts' => 5,
        'app_default_rollback_on_health_fail' => false,
        'app_default_rollback_on_crash_loop' => false,
        'app_default_debug' => true,
    ]);

    $defaults = $settings->getApplicationDefaults();

    expect($defaults['is_auto_deploy_enabled'])->toBeFalse();
    expect($defaults['is_force_https_enabled'])->toBeFalse();
    expect($defaults['is_preview_deployments_enabled'])->toBeTrue();
    expect($defaults['is_pr_deployments_public_enabled'])->toBeTrue();
    expect($defaults['is_git_submodules_enabled'])->toBeFalse();
    expect($defaults['is_git_lfs_enabled'])->toBeFalse();
    expect($defaults['is_git_shallow_clone_enabled'])->toBeFalse();
    expect($defaults['use_build_secrets'])->toBeTrue();
    expect($defaults['inject_build_args_to_dockerfile'])->toBeFalse();
    expect($defaults['include_source_commit_in_build'])->toBeTrue();
    expect($defaults['docker_images_to_keep'])->toBe(10);
    expect($defaults['auto_rollback_enabled'])->toBeTrue();
    expect($defaults['rollback_validation_seconds'])->toBe(60);
    expect($defaults['rollback_max_restarts'])->toBe(5);
    expect($defaults['rollback_on_health_check_fail'])->toBeFalse();
    expect($defaults['rollback_on_crash_loop'])->toBeFalse();
    expect($defaults['is_debug_enabled'])->toBeTrue();
});

test('all app_default fields are in fillable array', function () {
    $settings = new InstanceSettings;
    $fillable = $settings->getFillable();

    $appDefaultFields = [
        'app_default_auto_deploy',
        'app_default_force_https',
        'app_default_preview_deployments',
        'app_default_pr_deployments_public',
        'app_default_git_submodules',
        'app_default_git_lfs',
        'app_default_git_shallow_clone',
        'app_default_use_build_secrets',
        'app_default_inject_build_args',
        'app_default_include_commit_in_build',
        'app_default_docker_images_to_keep',
        'app_default_auto_rollback',
        'app_default_rollback_validation_sec',
        'app_default_rollback_max_restarts',
        'app_default_rollback_on_health_fail',
        'app_default_rollback_on_crash_loop',
        'app_default_debug',
    ];

    foreach ($appDefaultFields as $field) {
        expect($fillable)->toContain($field);
    }
});

test('all app_default fields are in casts array', function () {
    $settings = new InstanceSettings;
    $casts = $settings->getCasts();

    $booleanFields = [
        'app_default_auto_deploy',
        'app_default_force_https',
        'app_default_preview_deployments',
        'app_default_pr_deployments_public',
        'app_default_git_submodules',
        'app_default_git_lfs',
        'app_default_git_shallow_clone',
        'app_default_use_build_secrets',
        'app_default_inject_build_args',
        'app_default_include_commit_in_build',
        'app_default_auto_rollback',
        'app_default_rollback_on_health_fail',
        'app_default_rollback_on_crash_loop',
        'app_default_debug',
    ];

    $integerFields = [
        'app_default_docker_images_to_keep',
        'app_default_rollback_validation_sec',
        'app_default_rollback_max_restarts',
    ];

    foreach ($booleanFields as $field) {
        expect($casts[$field])->toBe('boolean');
    }

    foreach ($integerFields as $field) {
        expect($casts[$field])->toBe('integer');
    }
});

test('getApplicationDefaults keys match ApplicationSetting fillable fields or Application model fields', function () {
    $settings = new InstanceSettings([
        'app_default_auto_deploy' => true,
        'app_default_force_https' => true,
        'app_default_preview_deployments' => false,
        'app_default_pr_deployments_public' => false,
        'app_default_git_submodules' => true,
        'app_default_git_lfs' => true,
        'app_default_git_shallow_clone' => true,
        'app_default_use_build_secrets' => false,
        'app_default_inject_build_args' => true,
        'app_default_include_commit_in_build' => false,
        'app_default_docker_images_to_keep' => 2,
        'app_default_auto_rollback' => false,
        'app_default_rollback_validation_sec' => 300,
        'app_default_rollback_max_restarts' => 3,
        'app_default_rollback_on_health_fail' => true,
        'app_default_rollback_on_crash_loop' => true,
        'app_default_debug' => false,
    ]);

    $defaults = $settings->getApplicationDefaults();
    $appSettingFillable = (new \App\Models\ApplicationSetting)->getFillable();

    // Keys that belong to the Application model directly (not ApplicationSetting)
    $applicationModelFields = ['build_pack'];

    foreach (array_keys($defaults) as $key) {
        if (in_array($key, $applicationModelFields)) {
            // These fields are set on Application model, not ApplicationSetting
            continue;
        }
        expect($appSettingFillable)->toContain($key);
    }
});
