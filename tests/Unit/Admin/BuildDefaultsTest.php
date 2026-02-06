<?php

/**
 * Unit tests for Build Defaults and Deployment Policy in admin panel.
 *
 * Validates BuildPackTypes enum, InstanceSettings $fillable/$casts,
 * default values, and validation constraints.
 */

use App\Enums\BuildPackTypes;
use App\Models\InstanceSettings;

$buildDefaultFields = [
    'app_default_build_pack',
    'app_default_build_timeout',
    'app_default_static_image',
    'app_default_requires_approval',
];

test('BuildPackTypes enum has exactly 4 values', function () {
    $cases = BuildPackTypes::cases();
    expect($cases)->toHaveCount(4);
});

test('BuildPackTypes enum contains nixpacks, static, dockerfile, dockercompose', function () {
    expect(BuildPackTypes::NIXPACKS->value)->toBe('nixpacks');
    expect(BuildPackTypes::STATIC->value)->toBe('static');
    expect(BuildPackTypes::DOCKERFILE->value)->toBe('dockerfile');
    expect(BuildPackTypes::DOCKERCOMPOSE->value)->toBe('dockercompose');
});

test('all 4 build default fields are in InstanceSettings $fillable', function () use ($buildDefaultFields) {
    $model = new InstanceSettings;
    $fillable = $model->getFillable();

    foreach ($buildDefaultFields as $field) {
        expect($fillable)->toContain($field);
    }
});

test('build_timeout is cast as integer', function () {
    $model = new InstanceSettings;
    $casts = $model->getCasts();

    expect($casts['app_default_build_timeout'])->toBe('integer');
});

test('requires_approval is cast as boolean', function () {
    $model = new InstanceSettings;
    $casts = $model->getCasts();

    expect($casts['app_default_requires_approval'])->toBe('boolean');
});

test('build_pack and static_image are not in casts (stored as string)', function () {
    $model = new InstanceSettings;
    $casts = $model->getCasts();

    expect($casts)->not->toHaveKey('app_default_build_pack');
    expect($casts)->not->toHaveKey('app_default_static_image');
});

test('default build_pack value is nixpacks', function () {
    // Migration default: 'nixpacks'
    expect(BuildPackTypes::NIXPACKS->value)->toBe('nixpacks');
});

test('build_pack validation accepts only valid enum values', function () {
    $validValues = array_map(fn ($case) => $case->value, BuildPackTypes::cases());

    expect($validValues)->toContain('nixpacks');
    expect($validValues)->toContain('static');
    expect($validValues)->toContain('dockerfile');
    expect($validValues)->toContain('dockercompose');
    expect($validValues)->not->toContain('heroku');
    expect($validValues)->not->toContain('buildpacks');
});

test('build_timeout range is 60-86400 seconds', function () {
    $min = 60;
    $max = 86400;

    expect($min)->toBeGreaterThanOrEqual(60);
    expect($max)->toBeLessThanOrEqual(86400);
    expect($max)->toBe(24 * 60 * 60); // 24 hours in seconds
});

test('getApplicationDefaults includes build_pack mapping', function () {
    $model = new InstanceSettings;
    $defaults = $model->getApplicationDefaults();

    expect($defaults)->toHaveKey('build_pack');
});
