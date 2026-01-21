<?php

/**
 * Tests for ApplicationSetting model boolean casting
 *
 * These tests verify that the $casts property correctly defines boolean
 * casting for various fields. We use reflection to verify the casts
 * configuration without triggering Attribute mutators that have side effects.
 */

use App\Models\ApplicationSetting;

beforeEach(function () {
    $this->reflection = new ReflectionClass(ApplicationSetting::class);
    $this->setting = new ApplicationSetting;
});

it('has casts array property defined correctly', function () {
    // Verify the casts property exists and is configured via getCasts()
    $casts = $this->setting->getCasts();

    expect($casts)->toHaveKey('is_static')
        ->and($casts['is_static'])->toBe('boolean');
});

it('casts all boolean fields correctly', function () {
    // Get all casts
    $casts = $this->setting->getCasts();

    // Verify all expected boolean fields are cast
    $expectedBooleanCasts = [
        'is_static',
        'is_spa',
        'is_build_server_enabled',
        'is_preserve_repository_enabled',
        'is_container_label_escape_enabled',
        'is_container_label_readonly_enabled',
        'use_build_secrets',
        'is_auto_deploy_enabled',
        'is_force_https_enabled',
        'is_debug_enabled',
        'is_preview_deployments_enabled',
        'is_pr_deployments_public_enabled',
        'is_git_submodules_enabled',
        'is_git_lfs_enabled',
        'is_git_shallow_clone_enabled',
    ];

    foreach ($expectedBooleanCasts as $field) {
        expect($casts)->toHaveKey($field)
            ->and($casts[$field])->toBe('boolean');
    }
});

it('casts is_spa to boolean when true', function () {
    // Use is_spa instead of is_static to avoid Attribute mutator side effects
    $this->setting->is_spa = true;

    expect($this->setting->is_spa)->toBeTrue()
        ->and($this->setting->is_spa)->toBeBool();
});

it('casts is_spa to boolean when false', function () {
    $this->setting->is_spa = false;

    expect($this->setting->is_spa)->toBeFalse()
        ->and($this->setting->is_spa)->toBeBool();
});

it('casts is_spa from string "1" to boolean true', function () {
    $this->setting->is_spa = '1';

    expect($this->setting->is_spa)->toBeTrue()
        ->and($this->setting->is_spa)->toBeBool();
});

it('casts is_spa from string "0" to boolean false', function () {
    $this->setting->is_spa = '0';

    expect($this->setting->is_spa)->toBeFalse()
        ->and($this->setting->is_spa)->toBeBool();
});

it('casts is_spa from integer 1 to boolean true', function () {
    $this->setting->is_spa = 1;

    expect($this->setting->is_spa)->toBeTrue()
        ->and($this->setting->is_spa)->toBeBool();
});

it('casts is_spa from integer 0 to boolean false', function () {
    $this->setting->is_spa = 0;

    expect($this->setting->is_spa)->toBeFalse()
        ->and($this->setting->is_spa)->toBeBool();
});

it('has isStatic Attribute mutator defined', function () {
    // Verify the isStatic method exists (it's a setter-only Attribute)
    expect($this->reflection->hasMethod('isStatic'))->toBeTrue();

    // Verify it returns an Attribute instance
    $method = $this->reflection->getMethod('isStatic');
    $returnType = $method->getReturnType();

    expect($returnType->getName())->toBe('Illuminate\Database\Eloquent\Casts\Attribute');
});

it('has correct casts property type', function () {
    // Verify $casts property is an array
    $property = $this->reflection->getProperty('casts');
    $defaultProps = $this->reflection->getDefaultProperties();

    expect($defaultProps['casts'])->toBeArray();
});

it('has application relationship defined', function () {
    expect($this->reflection->hasMethod('application'))->toBeTrue();
});

it('casts docker_images_to_keep to integer', function () {
    $casts = $this->setting->getCasts();

    expect($casts)->toHaveKey('docker_images_to_keep')
        ->and($casts['docker_images_to_keep'])->toBe('integer');
});

it('correctly casts integer fields', function () {
    $this->setting->docker_images_to_keep = '10';

    expect($this->setting->docker_images_to_keep)->toBeInt()
        ->and($this->setting->docker_images_to_keep)->toBe(10);
});
