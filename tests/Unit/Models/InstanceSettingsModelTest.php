<?php

use App\Models\InstanceSettings;

// Fillable Security Tests
test('fillable attributes are defined and not empty', function () {
    $settings = new InstanceSettings;
    expect($settings->getFillable())->not->toBeEmpty();
});

test('fillable does not include id', function () {
    $fillable = (new InstanceSettings)->getFillable();
    expect($fillable)->not->toContain('id');
});

test('fillable includes core fields', function () {
    $fillable = (new InstanceSettings)->getFillable();

    expect($fillable)
        ->toContain('fqdn')
        ->toContain('instance_name')
        ->toContain('public_ipv4')
        ->toContain('public_ipv6')
        ->toContain('is_registration_enabled')
        ->toContain('is_dns_validation_enabled');
});

test('fillable includes SMTP fields', function () {
    $fillable = (new InstanceSettings)->getFillable();

    expect($fillable)
        ->toContain('smtp_enabled')
        ->toContain('smtp_from_address')
        ->toContain('smtp_host')
        ->toContain('smtp_port')
        ->toContain('smtp_username')
        ->toContain('smtp_password');
});

test('fillable includes AI provider fields', function () {
    $fillable = (new InstanceSettings)->getFillable();

    expect($fillable)
        ->toContain('ai_default_provider')
        ->toContain('ai_anthropic_api_key')
        ->toContain('ai_openai_api_key')
        ->toContain('ai_claude_model')
        ->toContain('ai_openai_model')
        ->toContain('ai_max_tokens');
});

test('fillable includes SSH infrastructure fields', function () {
    $fillable = (new InstanceSettings)->getFillable();

    expect($fillable)
        ->toContain('ssh_mux_enabled')
        ->toContain('ssh_connection_timeout')
        ->toContain('ssh_command_timeout')
        ->toContain('ssh_max_retries');
});

test('fillable includes Cloudflare protection fields', function () {
    $fillable = (new InstanceSettings)->getFillable();

    expect($fillable)
        ->toContain('cloudflare_api_token')
        ->toContain('cloudflare_account_id')
        ->toContain('cloudflare_zone_id')
        ->toContain('cloudflare_tunnel_id')
        ->toContain('cloudflare_tunnel_token')
        ->toContain('is_cloudflare_protection_enabled');
});

test('fillable includes Horizon queue fields', function () {
    $fillable = (new InstanceSettings)->getFillable();

    expect($fillable)
        ->toContain('api_rate_limit')
        ->toContain('horizon_balance')
        ->toContain('horizon_min_processes')
        ->toContain('horizon_max_processes')
        ->toContain('horizon_worker_memory');
});

test('fillable includes resource monitoring fields', function () {
    $fillable = (new InstanceSettings)->getFillable();

    expect($fillable)
        ->toContain('resource_warning_cpu_threshold')
        ->toContain('resource_critical_cpu_threshold')
        ->toContain('resource_monitoring_enabled')
        ->toContain('resource_check_interval_minutes');
});

// Casts Tests - SMTP
test('smtp_enabled is cast to boolean', function () {
    $casts = (new InstanceSettings)->getCasts();
    expect($casts['smtp_enabled'])->toBe('boolean');
});

test('smtp_password is cast to encrypted', function () {
    $casts = (new InstanceSettings)->getCasts();
    expect($casts['smtp_password'])->toBe('encrypted');
});

test('smtp_port is cast to integer', function () {
    $casts = (new InstanceSettings)->getCasts();
    expect($casts['smtp_port'])->toBe('integer');
});

test('smtp_host is cast to encrypted', function () {
    $casts = (new InstanceSettings)->getCasts();
    expect($casts['smtp_host'])->toBe('encrypted');
});

// Casts Tests - Resend
test('resend_enabled is cast to boolean', function () {
    $casts = (new InstanceSettings)->getCasts();
    expect($casts['resend_enabled'])->toBe('boolean');
});

test('resend_api_key is cast to encrypted', function () {
    $casts = (new InstanceSettings)->getCasts();
    expect($casts['resend_api_key'])->toBe('encrypted');
});

// Casts Tests - AI
test('ai_anthropic_api_key is cast to encrypted', function () {
    $casts = (new InstanceSettings)->getCasts();
    expect($casts['ai_anthropic_api_key'])->toBe('encrypted');
});

test('ai_openai_api_key is cast to encrypted', function () {
    $casts = (new InstanceSettings)->getCasts();
    expect($casts['ai_openai_api_key'])->toBe('encrypted');
});

test('ai_max_tokens is cast to integer', function () {
    $casts = (new InstanceSettings)->getCasts();
    expect($casts['ai_max_tokens'])->toBe('integer');
});

test('ai_cache_enabled is cast to boolean', function () {
    $casts = (new InstanceSettings)->getCasts();
    expect($casts['ai_cache_enabled'])->toBe('boolean');
});

test('is_ai_chat_enabled is cast to boolean', function () {
    $casts = (new InstanceSettings)->getCasts();
    expect($casts['is_ai_chat_enabled'])->toBe('boolean');
});

// Casts Tests - S3
test('s3_enabled is cast to boolean', function () {
    $casts = (new InstanceSettings)->getCasts();
    expect($casts['s3_enabled'])->toBe('boolean');
});

test('s3_key is cast to encrypted', function () {
    $casts = (new InstanceSettings)->getCasts();
    expect($casts['s3_key'])->toBe('encrypted');
});

test('s3_secret is cast to encrypted', function () {
    $casts = (new InstanceSettings)->getCasts();
    expect($casts['s3_secret'])->toBe('encrypted');
});

// Casts Tests - SSH
test('ssh_mux_enabled is cast to boolean', function () {
    $casts = (new InstanceSettings)->getCasts();
    expect($casts['ssh_mux_enabled'])->toBe('boolean');
});

test('ssh_connection_timeout is cast to integer', function () {
    $casts = (new InstanceSettings)->getCasts();
    expect($casts['ssh_connection_timeout'])->toBe('integer');
});

test('ssh_max_retries is cast to integer', function () {
    $casts = (new InstanceSettings)->getCasts();
    expect($casts['ssh_max_retries'])->toBe('integer');
});

// Casts Tests - Docker Registry
test('docker_registry_username is cast to encrypted', function () {
    $casts = (new InstanceSettings)->getCasts();
    expect($casts['docker_registry_username'])->toBe('encrypted');
});

test('docker_registry_password is cast to encrypted', function () {
    $casts = (new InstanceSettings)->getCasts();
    expect($casts['docker_registry_password'])->toBe('encrypted');
});

// Casts Tests - Cloudflare
test('cloudflare_api_token is cast to encrypted', function () {
    $casts = (new InstanceSettings)->getCasts();
    expect($casts['cloudflare_api_token'])->toBe('encrypted');
});

test('is_cloudflare_protection_enabled is cast to boolean', function () {
    $casts = (new InstanceSettings)->getCasts();
    expect($casts['is_cloudflare_protection_enabled'])->toBe('boolean');
});

test('cloudflare_last_synced_at is cast to datetime', function () {
    $casts = (new InstanceSettings)->getCasts();
    expect($casts['cloudflare_last_synced_at'])->toBe('datetime');
});

// Casts Tests - Allowed IPs
test('allowed_ip_ranges is cast to array', function () {
    $casts = (new InstanceSettings)->getCasts();
    expect($casts['allowed_ip_ranges'])->toBe('array');
});

// Casts Tests - Resource Monitoring
test('resource_monitoring_enabled is cast to boolean', function () {
    $casts = (new InstanceSettings)->getCasts();
    expect($casts['resource_monitoring_enabled'])->toBe('boolean');
});

test('resource_warning_cpu_threshold is cast to integer', function () {
    $casts = (new InstanceSettings)->getCasts();
    expect($casts['resource_warning_cpu_threshold'])->toBe('integer');
});

// Casts Tests - Horizon Queue
test('api_rate_limit is cast to integer', function () {
    $casts = (new InstanceSettings)->getCasts();
    expect($casts['api_rate_limit'])->toBe('integer');
});

test('horizon_worker_memory is cast to integer', function () {
    $casts = (new InstanceSettings)->getCasts();
    expect($casts['horizon_worker_memory'])->toBe('integer');
});

// hasCloudflareProtection Tests
test('hasCloudflareProtection returns true when all fields set', function () {
    $settings = new InstanceSettings;
    $settings->cloudflare_api_token = 'token123';
    $settings->cloudflare_account_id = 'acc123';
    $settings->cloudflare_zone_id = 'zone123';

    expect($settings->hasCloudflareProtection())->toBeTrue();
});

test('hasCloudflareProtection returns false when api_token is empty', function () {
    $settings = new InstanceSettings;
    $settings->cloudflare_api_token = '';
    $settings->cloudflare_account_id = 'acc123';
    $settings->cloudflare_zone_id = 'zone123';

    expect($settings->hasCloudflareProtection())->toBeFalse();
});

test('hasCloudflareProtection returns false when account_id is empty', function () {
    $settings = new InstanceSettings;
    $settings->cloudflare_api_token = 'token123';
    $settings->cloudflare_account_id = '';
    $settings->cloudflare_zone_id = 'zone123';

    expect($settings->hasCloudflareProtection())->toBeFalse();
});

test('hasCloudflareProtection returns false when zone_id is empty', function () {
    $settings = new InstanceSettings;
    $settings->cloudflare_api_token = 'token123';
    $settings->cloudflare_account_id = 'acc123';
    $settings->cloudflare_zone_id = '';

    expect($settings->hasCloudflareProtection())->toBeFalse();
});

// isCloudflareProtectionActive Tests
test('isCloudflareProtectionActive returns true when fully configured', function () {
    $settings = new InstanceSettings;
    $settings->is_cloudflare_protection_enabled = true;
    $settings->cloudflare_api_token = 'token123';
    $settings->cloudflare_account_id = 'acc123';
    $settings->cloudflare_zone_id = 'zone123';
    $settings->cloudflare_tunnel_id = 'tunnel123';

    expect($settings->isCloudflareProtectionActive())->toBeTrue();
});

test('isCloudflareProtectionActive returns false when not enabled', function () {
    $settings = new InstanceSettings;
    $settings->is_cloudflare_protection_enabled = false;
    $settings->cloudflare_api_token = 'token123';
    $settings->cloudflare_account_id = 'acc123';
    $settings->cloudflare_zone_id = 'zone123';
    $settings->cloudflare_tunnel_id = 'tunnel123';

    expect($settings->isCloudflareProtectionActive())->toBeFalse();
});

test('isCloudflareProtectionActive returns false when tunnel_id is empty', function () {
    $settings = new InstanceSettings;
    $settings->is_cloudflare_protection_enabled = true;
    $settings->cloudflare_api_token = 'token123';
    $settings->cloudflare_account_id = 'acc123';
    $settings->cloudflare_zone_id = 'zone123';
    $settings->cloudflare_tunnel_id = '';

    expect($settings->isCloudflareProtectionActive())->toBeFalse();
});

test('isCloudflareProtectionActive returns false when protection fields missing', function () {
    $settings = new InstanceSettings;
    $settings->is_cloudflare_protection_enabled = true;
    $settings->cloudflare_api_token = '';
    $settings->cloudflare_account_id = '';
    $settings->cloudflare_zone_id = '';
    $settings->cloudflare_tunnel_id = 'tunnel123';

    expect($settings->isCloudflareProtectionActive())->toBeFalse();
});

// getTitleDisplayName Tests
test('getTitleDisplayName returns formatted name', function () {
    $settings = new InstanceSettings;
    $settings->instance_name = 'Saturn Production';

    expect($settings->getTitleDisplayName())->toBe('[Saturn Production]');
});

test('getTitleDisplayName returns empty string when no name', function () {
    $settings = new InstanceSettings;
    $settings->instance_name = null;

    expect($settings->getTitleDisplayName())->toBe('');
});

test('getTitleDisplayName returns empty string for empty name', function () {
    $settings = new InstanceSettings;
    $settings->instance_name = '';

    expect($settings->getTitleDisplayName())->toBe('');
});

// FQDN Accessor Tests
test('fqdn accessor strips path from URL', function () {
    $settings = new InstanceSettings;
    $settings->fqdn = 'https://saturn.example.com/path';

    expect($settings->fqdn)->toBe('https://saturn.example.com');
});

test('fqdn accessor preserves scheme', function () {
    $settings = new InstanceSettings;
    $settings->fqdn = 'http://saturn.example.com';

    expect($settings->fqdn)->toBe('http://saturn.example.com');
});

// Trait Tests
test('uses Auditable trait', function () {
    expect(class_uses_recursive(new InstanceSettings))->toContain(\App\Traits\Auditable::class);
});

test('uses LogsActivity trait', function () {
    expect(class_uses_recursive(new InstanceSettings))->toContain(\Spatie\Activitylog\Traits\LogsActivity::class);
});

// Activity Log Tests
test('getActivitylogOptions returns LogOptions', function () {
    $settings = new InstanceSettings;
    $options = $settings->getActivitylogOptions();

    expect($options)->toBeInstanceOf(\Spatie\Activitylog\LogOptions::class);
});

// Attribute Tests
test('instance_name attribute works', function () {
    $settings = new InstanceSettings;
    $settings->instance_name = 'My Saturn';

    expect($settings->instance_name)->toBe('My Saturn');
});

test('public_ipv4 attribute works', function () {
    $settings = new InstanceSettings;
    $settings->public_ipv4 = '1.2.3.4';

    expect($settings->public_ipv4)->toBe('1.2.3.4');
});

// Sensitive fields in casts Tests
test('all sensitive fields are encrypted', function () {
    $casts = (new InstanceSettings)->getCasts();

    $encryptedFields = [
        'smtp_from_address', 'smtp_from_name', 'smtp_recipients',
        'smtp_host', 'smtp_username', 'smtp_password',
        'resend_api_key', 'sentinel_token',
        'ai_anthropic_api_key', 'ai_openai_api_key',
        's3_key', 's3_secret',
        'docker_registry_username', 'docker_registry_password',
        'cloudflare_api_token', 'cloudflare_tunnel_token',
        'auto_provision_api_key',
    ];

    foreach ($encryptedFields as $field) {
        expect($casts[$field])->toBe('encrypted', "Field $field should be encrypted");
    }
});

// Boolean fields in casts Tests
test('all boolean feature flags are cast correctly', function () {
    $casts = (new InstanceSettings)->getCasts();

    $booleanFields = [
        'smtp_enabled', 'resend_enabled', 'is_auto_update_enabled',
        'is_wire_navigate_enabled', 'is_ai_code_review_enabled',
        'is_ai_error_analysis_enabled', 'is_ai_chat_enabled',
        'resource_monitoring_enabled', 'auto_provision_enabled',
        'ai_cache_enabled', 's3_enabled', 'ssh_mux_enabled',
        'is_cloudflare_protection_enabled',
    ];

    foreach ($booleanFields as $field) {
        expect($casts[$field])->toBe('boolean', "Field $field should be boolean");
    }
});
