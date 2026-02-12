<?php

use App\Models\Server;
use App\Models\ServerSetting;

// Fillable Security Tests
test('fillable attributes are defined and not empty', function () {
    $setting = new ServerSetting;
    expect($setting->getFillable())->not->toBeEmpty();
});

test('fillable does not include id', function () {
    $fillable = (new ServerSetting)->getFillable();

    expect($fillable)->not->toContain('id');
});

test('fillable does not include system-managed fields', function () {
    $fillable = (new ServerSetting)->getFillable();

    expect($fillable)
        ->not->toContain('is_reachable')
        ->not->toContain('is_usable')
        ->not->toContain('sentinel_token')
        ->not->toContain('sentinel_custom_url');
});

test('fillable includes expected fields', function () {
    $fillable = (new ServerSetting)->getFillable();

    expect($fillable)
        ->toContain('server_id')
        ->toContain('concurrent_builds')
        ->toContain('deployment_queue_limit')
        ->toContain('dynamic_timeout')
        ->toContain('force_disabled')
        ->toContain('docker_cleanup_frequency')
        ->toContain('docker_cleanup_threshold')
        ->toContain('is_build_server')
        ->toContain('is_cloudflare_tunnel')
        ->toContain('is_metrics_enabled')
        ->toContain('is_sentinel_enabled')
        ->toContain('is_terminal_enabled')
        ->toContain('wildcard_domain');
});

// Casts Tests
test('force_docker_cleanup is cast to boolean', function () {
    $casts = (new ServerSetting)->getCasts();
    expect($casts['force_docker_cleanup'])->toBe('boolean');
});

test('docker_cleanup_threshold is cast to integer', function () {
    $casts = (new ServerSetting)->getCasts();
    expect($casts['docker_cleanup_threshold'])->toBe('integer');
});

test('is_reachable is cast to boolean', function () {
    $casts = (new ServerSetting)->getCasts();
    expect($casts['is_reachable'])->toBe('boolean');
});

test('is_usable is cast to boolean', function () {
    $casts = (new ServerSetting)->getCasts();
    expect($casts['is_usable'])->toBe('boolean');
});

test('is_terminal_enabled is cast to boolean', function () {
    $casts = (new ServerSetting)->getCasts();
    expect($casts['is_terminal_enabled'])->toBe('boolean');
});

test('sentinel_token is cast to encrypted', function () {
    $casts = (new ServerSetting)->getCasts();
    expect($casts['sentinel_token'])->toBe('encrypted');
});

test('is_master_server is cast to boolean', function () {
    $casts = (new ServerSetting)->getCasts();
    expect($casts['is_master_server'])->toBe('boolean');
});

test('disable_application_image_retention is cast to boolean', function () {
    $casts = (new ServerSetting)->getCasts();
    expect($casts['disable_application_image_retention'])->toBe('boolean');
});

// Relationship Tests
test('server relationship returns belongsTo', function () {
    $relation = (new ServerSetting)->server();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Server::class);
});

// Trait Tests
test('uses Auditable trait', function () {
    expect(class_uses_recursive(new ServerSetting))->toContain(\App\Traits\Auditable::class);
});

test('uses LogsActivity trait', function () {
    expect(class_uses_recursive(new ServerSetting))->toContain(\Spatie\Activitylog\Traits\LogsActivity::class);
});

// Activity Log Tests
test('getActivitylogOptions returns LogOptions', function () {
    $setting = new ServerSetting;
    $options = $setting->getActivitylogOptions();

    expect($options)->toBeInstanceOf(\Spatie\Activitylog\LogOptions::class);
});

// Attribute Tests
test('concurrent_builds attribute works', function () {
    $setting = new ServerSetting;
    $setting->concurrent_builds = 5;

    expect($setting->concurrent_builds)->toBe(5);
});

test('deployment_queue_limit attribute works', function () {
    $setting = new ServerSetting;
    $setting->deployment_queue_limit = 10;

    expect($setting->deployment_queue_limit)->toBe(10);
});

test('wildcard_domain attribute works', function () {
    $setting = new ServerSetting;
    $setting->wildcard_domain = '*.example.com';

    expect($setting->wildcard_domain)->toBe('*.example.com');
});
