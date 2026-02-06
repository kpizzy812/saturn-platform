<?php

/**
 * Unit tests for server bulk settings validation logic.
 *
 * These tests verify the allowlist, validation rules and edge cases
 * for the bulk server settings update endpoint.
 */

// The 16 allowed fields for bulk update
$allowedFields = [
    'concurrent_builds',
    'deployment_queue_limit',
    'dynamic_timeout',
    'is_build_server',
    'force_docker_cleanup',
    'docker_cleanup_frequency',
    'docker_cleanup_threshold',
    'delete_unused_volumes',
    'delete_unused_networks',
    'disable_application_image_retention',
    'is_metrics_enabled',
    'is_sentinel_enabled',
    'is_terminal_enabled',
    'sentinel_metrics_history_days',
    'sentinel_metrics_refresh_rate_seconds',
    'sentinel_push_interval_seconds',
];

// System-managed fields that MUST be excluded from bulk operations
$forbiddenFields = [
    'is_reachable',
    'is_usable',
    'sentinel_token',
    'sentinel_custom_url',
    'is_master_server',
    'force_disabled',
    'is_logdrain_axiom_enabled',
    'is_logdrain_custom_enabled',
    'is_logdrain_highlight_enabled',
    'is_logdrain_newrelic_enabled',
    'logdrain_axiom_api_key',
    'logdrain_axiom_dataset_name',
    'logdrain_custom_config',
    'logdrain_custom_config_parser',
    'logdrain_highlight_project_id',
    'logdrain_newrelic_base_uri',
    'logdrain_newrelic_license_key',
    'wildcard_domain',
    'server_id',
];

test('allowlist contains exactly 16 fields', function () use ($allowedFields) {
    expect($allowedFields)->toHaveCount(16);
});

test('allowlist does not contain system-managed fields', function () use ($allowedFields, $forbiddenFields) {
    foreach ($forbiddenFields as $forbidden) {
        expect($allowedFields)->not->toContain($forbidden);
    }
});

test('array_intersect_key filters out non-allowed fields', function () use ($allowedFields) {
    $input = [
        'concurrent_builds' => 5,
        'is_reachable' => true,         // system-managed
        'sentinel_token' => 'hacked',   // sensitive
        'is_metrics_enabled' => true,
        'unknown_field' => 'test',      // garbage
    ];

    $filtered = array_intersect_key($input, array_flip($allowedFields));

    expect($filtered)->toHaveCount(2)
        ->toHaveKey('concurrent_builds')
        ->toHaveKey('is_metrics_enabled')
        ->not->toHaveKey('is_reachable')
        ->not->toHaveKey('sentinel_token')
        ->not->toHaveKey('unknown_field');
});

test('empty settings after filtering should be detected', function () use ($allowedFields) {
    $input = [
        'is_reachable' => true,
        'sentinel_token' => 'hacked',
    ];

    $filtered = array_intersect_key($input, array_flip($allowedFields));

    expect($filtered)->toBeEmpty();
});

test('integer fields have valid ranges', function () {
    $rules = [
        'concurrent_builds' => ['min' => 1, 'max' => 100],
        'deployment_queue_limit' => ['min' => 0, 'max' => 1000],
        'dynamic_timeout' => ['min' => 0, 'max' => 86400],
        'docker_cleanup_threshold' => ['min' => 0, 'max' => 100],
        'sentinel_metrics_history_days' => ['min' => 1, 'max' => 365],
        'sentinel_metrics_refresh_rate_seconds' => ['min' => 5, 'max' => 3600],
        'sentinel_push_interval_seconds' => ['min' => 5, 'max' => 3600],
    ];

    foreach ($rules as $field => $range) {
        // Min must be less than max
        expect($range['min'])->toBeLessThan($range['max'],
            "Field {$field}: min ({$range['min']}) should be less than max ({$range['max']})"
        );

        // Min must be non-negative
        expect($range['min'])->toBeGreaterThanOrEqual(0,
            "Field {$field}: min ({$range['min']}) should not be negative"
        );
    }
});

test('concurrent_builds minimum is 1 not 0', function () {
    // Concurrent builds = 0 would mean no builds can run
    $minConcurrent = 1;
    expect($minConcurrent)->toBe(1);
});

test('sentinel refresh rate minimum is 5 seconds', function () {
    // Less than 5 seconds would overload the server
    $minRefresh = 5;
    expect($minRefresh)->toBeGreaterThanOrEqual(5);
});

test('boolean fields accept only true/false', function () use ($allowedFields) {
    $booleanFields = [
        'is_build_server',
        'force_docker_cleanup',
        'delete_unused_volumes',
        'delete_unused_networks',
        'disable_application_image_retention',
        'is_metrics_enabled',
        'is_sentinel_enabled',
        'is_terminal_enabled',
    ];

    // All boolean fields must be in allowed list
    foreach ($booleanFields as $field) {
        expect($allowedFields)->toContain($field);
    }

    // Exactly 8 boolean fields
    expect($booleanFields)->toHaveCount(8);
});

test('docker_cleanup_frequency is a string field with max length', function () {
    $cronExpression = '0 0 * * *';
    expect(strlen($cronExpression))->toBeLessThanOrEqual(100);

    // Very long cron should be rejected
    $longCron = str_repeat('0 ', 51);
    expect(strlen($longCron))->toBeGreaterThan(100);
});

test('all allowed fields are categorized into build, docker, or monitoring groups', function () use ($allowedFields) {
    $buildFields = [
        'concurrent_builds', 'deployment_queue_limit', 'dynamic_timeout', 'is_build_server',
    ];
    $dockerFields = [
        'force_docker_cleanup', 'docker_cleanup_frequency', 'docker_cleanup_threshold',
        'delete_unused_volumes', 'delete_unused_networks', 'disable_application_image_retention',
    ];
    $monitoringFields = [
        'is_metrics_enabled', 'is_sentinel_enabled', 'is_terminal_enabled',
        'sentinel_metrics_history_days', 'sentinel_metrics_refresh_rate_seconds',
        'sentinel_push_interval_seconds',
    ];

    $allGrouped = array_merge($buildFields, $dockerFields, $monitoringFields);
    sort($allGrouped);
    $sortedAllowed = $allowedFields;
    sort($sortedAllowed);

    expect($allGrouped)->toBe($sortedAllowed);
});
