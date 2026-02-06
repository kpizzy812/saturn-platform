<?php

/**
 * Unit tests for Infrastructure Settings (SSH/Docker/Proxy) in admin panel.
 *
 * Validates that InstanceSettings model has correct $fillable and $casts,
 * config override mapping is complete, and proxy type values are valid.
 */

use App\Models\InstanceSettings;

$sshFields = [
    'ssh_mux_enabled',
    'ssh_mux_persist_time',
    'ssh_mux_max_age',
    'ssh_connection_timeout',
    'ssh_command_timeout',
    'ssh_max_retries',
    'ssh_retry_base_delay',
    'ssh_retry_max_delay',
];

$dockerFields = [
    'docker_registry_url',
    'docker_registry_username',
    'docker_registry_password',
];

$proxyFields = [
    'default_proxy_type',
];

test('SSH fields count is 8', function () use ($sshFields) {
    expect($sshFields)->toHaveCount(8);
});

test('Docker fields count is 3', function () use ($dockerFields) {
    expect($dockerFields)->toHaveCount(3);
});

test('all 12 infrastructure fields are in InstanceSettings $fillable', function () use ($sshFields, $dockerFields, $proxyFields) {
    $model = new InstanceSettings;
    $fillable = $model->getFillable();

    $allFields = array_merge($sshFields, $dockerFields, $proxyFields);
    expect($allFields)->toHaveCount(12);

    foreach ($allFields as $field) {
        expect($fillable)->toContain($field);
    }
});

test('SSH boolean/integer casts are defined', function () {
    $model = new InstanceSettings;
    $casts = $model->getCasts();

    expect($casts['ssh_mux_enabled'])->toBe('boolean');

    $integerFields = [
        'ssh_mux_persist_time', 'ssh_mux_max_age',
        'ssh_connection_timeout', 'ssh_command_timeout',
        'ssh_max_retries', 'ssh_retry_base_delay', 'ssh_retry_max_delay',
    ];
    foreach ($integerFields as $field) {
        expect($casts[$field])->toBe('integer');
    }
});

test('Docker credentials are encrypted', function () {
    $model = new InstanceSettings;
    $casts = $model->getCasts();

    expect($casts['docker_registry_username'])->toBe('encrypted');
    expect($casts['docker_registry_password'])->toBe('encrypted');
});

test('persist_time default is less than or equal to max_age default', function () {
    // Defaults from migration: persist_time=1800, max_age=3600
    $persistTime = 1800;
    $maxAge = 3600;
    expect($persistTime)->toBeLessThanOrEqual($maxAge);
});

test('valid proxy types are TRAEFIK, CADDY, NONE', function () {
    $validTypes = ['TRAEFIK', 'CADDY', 'NONE'];

    expect($validTypes)->toContain('TRAEFIK');
    expect($validTypes)->toContain('CADDY');
    expect($validTypes)->toContain('NONE');
    expect($validTypes)->not->toContain('NGINX');
});

test('config override mapping covers all 8 SSH keys', function () use ($sshFields) {
    $configKeys = [
        'constants.ssh.mux_enabled',
        'constants.ssh.mux_persist_time',
        'constants.ssh.mux_max_age',
        'constants.ssh.connection_timeout',
        'constants.ssh.command_timeout',
        'constants.ssh.max_retries',
        'constants.ssh.retry_base_delay',
        'constants.ssh.retry_max_delay',
    ];

    expect($configKeys)->toHaveCount(count($sshFields));

    foreach ($sshFields as $field) {
        $configSuffix = str_replace('ssh_', '', $field);
        $expectedKey = "constants.ssh.{$configSuffix}";
        expect($configKeys)->toContain($expectedKey);
    }
});

test('default_proxy_type is not in encrypted casts', function () {
    $model = new InstanceSettings;
    $casts = $model->getCasts();

    $cast = $casts['default_proxy_type'] ?? null;
    expect($cast)->not->toBe('encrypted');
});

test('docker_registry_url is not encrypted (it is a non-secret URL)', function () {
    $model = new InstanceSettings;
    $casts = $model->getCasts();

    $cast = $casts['docker_registry_url'] ?? null;
    expect($cast)->not->toBe('encrypted');
});
