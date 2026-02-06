<?php

/**
 * Admin Settings routes
 *
 * Instance settings management.
 */

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/settings', function () {
    $settings = \App\Models\InstanceSettings::get();

    // Mask encrypted secrets - only indicate presence, don't expose values
    $settingsArray = $settings->toArray();

    // For encrypted fields, send a placeholder if they have a value
    $secretFields = ['smtp_password', 'smtp_username', 'resend_api_key', 'sentinel_token', 'auto_provision_api_key', 'ai_anthropic_api_key', 'ai_openai_api_key', 's3_key', 's3_secret', 'docker_registry_username', 'docker_registry_password'];
    foreach ($secretFields as $field) {
        if (! empty($settings->{$field})) {
            $settingsArray[$field] = '••••••••';
        }
    }

    // Encrypted fields that we do want to show (from/host/recipients are needed for display)
    $showableEncryptedFields = ['smtp_from_address', 'smtp_from_name', 'smtp_recipients', 'smtp_host'];
    foreach ($showableEncryptedFields as $field) {
        $settingsArray[$field] = $settings->{$field} ?? '';
    }

    return Inertia::render('Admin/Settings/Index', [
        'settings' => $settingsArray,
    ]);
})->name('admin.settings.index');

Route::post('/settings', function (\Illuminate\Http\Request $request) {
    $settings = \App\Models\InstanceSettings::get();

    $validated = $request->validate([
        // General
        'settings.instance_name' => 'nullable|string|max:255',
        'settings.fqdn' => 'nullable|string|max:255',
        'settings.public_ipv4' => 'nullable|ip',
        'settings.public_ipv6' => 'nullable|string|max:255',
        'settings.allowed_ip_ranges' => 'nullable|string',
        // Security
        'settings.is_registration_enabled' => 'nullable|boolean',
        'settings.is_dns_validation_enabled' => 'nullable|boolean',
        // Updates
        'settings.is_auto_update_enabled' => 'nullable|boolean',
        'settings.auto_update_frequency' => 'nullable|string|max:100',
        'settings.update_check_frequency' => 'nullable|string|max:100',
        // AI Features
        'settings.is_ai_code_review_enabled' => 'nullable|boolean',
        'settings.is_ai_error_analysis_enabled' => 'nullable|boolean',
        'settings.is_ai_chat_enabled' => 'nullable|boolean',
        // SMTP
        'settings.smtp_enabled' => 'nullable|boolean',
        'settings.smtp_host' => 'nullable|string|max:255',
        'settings.smtp_port' => 'nullable|integer|min:1|max:65535',
        'settings.smtp_username' => 'nullable|string|max:255',
        'settings.smtp_password' => 'nullable|string|max:255',
        'settings.smtp_from_address' => 'nullable|string|max:255',
        'settings.smtp_from_name' => 'nullable|string|max:255',
        'settings.smtp_recipients' => 'nullable|string|max:1000',
        'settings.smtp_timeout' => 'nullable|integer|min:1|max:300',
        'settings.smtp_encryption' => 'nullable|string|in:tls,ssl,none',
        // Resend
        'settings.resend_enabled' => 'nullable|boolean',
        'settings.resend_api_key' => 'nullable|string|max:255',
        // Sentinel
        'settings.sentinel_token' => 'nullable|string|max:255',
        // Resource Monitoring
        'settings.resource_monitoring_enabled' => 'nullable|boolean',
        'settings.resource_check_interval_minutes' => 'nullable|integer|min:1|max:1440',
        'settings.resource_warning_cpu_threshold' => 'nullable|integer|min:1|max:100',
        'settings.resource_critical_cpu_threshold' => 'nullable|integer|min:1|max:100',
        'settings.resource_warning_memory_threshold' => 'nullable|integer|min:1|max:100',
        'settings.resource_critical_memory_threshold' => 'nullable|integer|min:1|max:100',
        'settings.resource_warning_disk_threshold' => 'nullable|integer|min:1|max:100',
        'settings.resource_critical_disk_threshold' => 'nullable|integer|min:1|max:100',
        // Auto-Provisioning
        'settings.auto_provision_enabled' => 'nullable|boolean',
        'settings.auto_provision_api_key' => 'nullable|string|max:255',
        'settings.auto_provision_max_servers_per_day' => 'nullable|integer|min:1|max:100',
        'settings.auto_provision_cooldown_minutes' => 'nullable|integer|min:1|max:1440',
        // AI Provider
        'settings.ai_default_provider' => 'nullable|string|in:claude,openai,ollama',
        'settings.ai_anthropic_api_key' => 'nullable|string|max:500',
        'settings.ai_openai_api_key' => 'nullable|string|max:500',
        'settings.ai_claude_model' => 'nullable|string|max:100',
        'settings.ai_openai_model' => 'nullable|string|max:100',
        'settings.ai_ollama_base_url' => 'nullable|string|max:500',
        'settings.ai_ollama_model' => 'nullable|string|max:100',
        'settings.ai_max_tokens' => 'nullable|integer|min:256|max:32000',
        'settings.ai_cache_enabled' => 'nullable|boolean',
        'settings.ai_cache_ttl' => 'nullable|integer|min:60|max:604800',
        // Global S3
        'settings.s3_enabled' => 'nullable|boolean',
        'settings.s3_endpoint' => 'nullable|string|max:500',
        'settings.s3_bucket' => 'nullable|string|max:255',
        'settings.s3_region' => 'nullable|string|max:100',
        'settings.s3_key' => 'nullable|string|max:255',
        'settings.s3_secret' => 'nullable|string|max:500',
        'settings.s3_path' => 'nullable|string|max:500',
        // Application global defaults
        'settings.app_default_auto_deploy' => 'nullable|boolean',
        'settings.app_default_force_https' => 'nullable|boolean',
        'settings.app_default_preview_deployments' => 'nullable|boolean',
        'settings.app_default_pr_deployments_public' => 'nullable|boolean',
        'settings.app_default_git_submodules' => 'nullable|boolean',
        'settings.app_default_git_lfs' => 'nullable|boolean',
        'settings.app_default_git_shallow_clone' => 'nullable|boolean',
        'settings.app_default_use_build_secrets' => 'nullable|boolean',
        'settings.app_default_inject_build_args' => 'nullable|boolean',
        'settings.app_default_include_commit_in_build' => 'nullable|boolean',
        'settings.app_default_docker_images_to_keep' => 'nullable|integer|min:1|max:50',
        'settings.app_default_auto_rollback' => 'nullable|boolean',
        'settings.app_default_rollback_validation_sec' => 'nullable|integer|min:10|max:3600',
        'settings.app_default_rollback_max_restarts' => 'nullable|integer|min:1|max:20',
        'settings.app_default_rollback_on_health_fail' => 'nullable|boolean',
        'settings.app_default_rollback_on_crash_loop' => 'nullable|boolean',
        'settings.app_default_debug' => 'nullable|boolean',
        'settings.app_default_build_pack' => 'nullable|string|in:nixpacks,static,dockerfile,dockercompose',
        'settings.app_default_build_timeout' => 'nullable|integer|min:60|max:86400',
        'settings.app_default_static_image' => 'nullable|string|max:255',
        'settings.app_default_requires_approval' => 'nullable|boolean',
        // SSH Configuration
        'settings.ssh_mux_enabled' => 'nullable|boolean',
        'settings.ssh_mux_persist_time' => 'nullable|integer|min:60|max:86400',
        'settings.ssh_mux_max_age' => 'nullable|integer|min:60|max:86400',
        'settings.ssh_connection_timeout' => 'nullable|integer|min:5|max:300',
        'settings.ssh_command_timeout' => 'nullable|integer|min:60|max:86400',
        'settings.ssh_max_retries' => 'nullable|integer|min:0|max:10',
        'settings.ssh_retry_base_delay' => 'nullable|integer|min:1|max:60',
        'settings.ssh_retry_max_delay' => 'nullable|integer|min:1|max:300',
        // Docker Registry
        'settings.docker_registry_url' => 'nullable|string|max:500',
        'settings.docker_registry_username' => 'nullable|string|max:255',
        'settings.docker_registry_password' => 'nullable|string|max:500',
        // Default Proxy
        'settings.default_proxy_type' => 'nullable|string|in:TRAEFIK,CADDY,NONE',
        // Rate Limiting & Queue
        'settings.api_rate_limit' => 'nullable|integer|min:10|max:10000',
        'settings.horizon_balance' => 'nullable|string|in:false,simple,auto',
        'settings.horizon_min_processes' => 'nullable|integer|min:1|max:20',
        'settings.horizon_max_processes' => 'nullable|integer|min:1|max:50',
        'settings.horizon_worker_memory' => 'nullable|integer|min:64|max:2048',
        'settings.horizon_worker_timeout' => 'nullable|integer|min:60|max:86400',
        'settings.horizon_max_jobs' => 'nullable|integer|min:10|max:10000',
        'settings.horizon_trim_recent_minutes' => 'nullable|integer|min:10|max:10080',
        'settings.horizon_trim_failed_minutes' => 'nullable|integer|min:60|max:43200',
        'settings.horizon_queue_wait_threshold' => 'nullable|integer|min:10|max:600',
    ]);

    $data = $validated['settings'] ?? [];

    // Placeholder value for masked secrets - skip update if unchanged
    $placeholder = '••••••••';

    $updateData = [
        // General
        'instance_name' => $data['instance_name'] ?? $settings->instance_name,
        'fqdn' => $data['fqdn'] ?? $settings->fqdn,
        'public_ipv4' => $data['public_ipv4'] ?? $settings->public_ipv4,
        'public_ipv6' => $data['public_ipv6'] ?? $settings->public_ipv6,
        'allowed_ip_ranges' => isset($data['allowed_ip_ranges'])
            ? array_filter(array_map('trim', explode(',', $data['allowed_ip_ranges'])))
            : $settings->allowed_ip_ranges,
        // Security
        'is_registration_enabled' => $data['is_registration_enabled'] ?? $settings->is_registration_enabled,
        'is_dns_validation_enabled' => $data['is_dns_validation_enabled'] ?? $settings->is_dns_validation_enabled,
        // Updates
        'is_auto_update_enabled' => $data['is_auto_update_enabled'] ?? $settings->is_auto_update_enabled,
        'auto_update_frequency' => $data['auto_update_frequency'] ?? $settings->auto_update_frequency,
        'update_check_frequency' => $data['update_check_frequency'] ?? $settings->update_check_frequency,
        // AI Features
        'is_ai_code_review_enabled' => $data['is_ai_code_review_enabled'] ?? $settings->is_ai_code_review_enabled,
        'is_ai_error_analysis_enabled' => $data['is_ai_error_analysis_enabled'] ?? $settings->is_ai_error_analysis_enabled,
        'is_ai_chat_enabled' => $data['is_ai_chat_enabled'] ?? $settings->is_ai_chat_enabled,
        // SMTP (non-secret)
        'smtp_enabled' => $data['smtp_enabled'] ?? $settings->smtp_enabled,
        'smtp_host' => $data['smtp_host'] ?? $settings->smtp_host,
        'smtp_port' => $data['smtp_port'] ?? $settings->smtp_port,
        'smtp_from_address' => $data['smtp_from_address'] ?? $settings->smtp_from_address,
        'smtp_from_name' => $data['smtp_from_name'] ?? $settings->smtp_from_name,
        'smtp_recipients' => $data['smtp_recipients'] ?? $settings->smtp_recipients,
        'smtp_timeout' => $data['smtp_timeout'] ?? $settings->smtp_timeout,
        'smtp_encryption' => $data['smtp_encryption'] ?? $settings->smtp_encryption,
        // Resend
        'resend_enabled' => $data['resend_enabled'] ?? $settings->resend_enabled,
        // Resource Monitoring
        'resource_monitoring_enabled' => $data['resource_monitoring_enabled'] ?? $settings->resource_monitoring_enabled,
        'resource_check_interval_minutes' => $data['resource_check_interval_minutes'] ?? $settings->resource_check_interval_minutes,
        'resource_warning_cpu_threshold' => $data['resource_warning_cpu_threshold'] ?? $settings->resource_warning_cpu_threshold,
        'resource_critical_cpu_threshold' => $data['resource_critical_cpu_threshold'] ?? $settings->resource_critical_cpu_threshold,
        'resource_warning_memory_threshold' => $data['resource_warning_memory_threshold'] ?? $settings->resource_warning_memory_threshold,
        'resource_critical_memory_threshold' => $data['resource_critical_memory_threshold'] ?? $settings->resource_critical_memory_threshold,
        'resource_warning_disk_threshold' => $data['resource_warning_disk_threshold'] ?? $settings->resource_warning_disk_threshold,
        'resource_critical_disk_threshold' => $data['resource_critical_disk_threshold'] ?? $settings->resource_critical_disk_threshold,
        // Auto-Provisioning
        'auto_provision_enabled' => $data['auto_provision_enabled'] ?? $settings->auto_provision_enabled,
        'auto_provision_max_servers_per_day' => $data['auto_provision_max_servers_per_day'] ?? $settings->auto_provision_max_servers_per_day,
        'auto_provision_cooldown_minutes' => $data['auto_provision_cooldown_minutes'] ?? $settings->auto_provision_cooldown_minutes,
        // AI Provider (non-secret)
        'ai_default_provider' => $data['ai_default_provider'] ?? $settings->ai_default_provider,
        'ai_claude_model' => $data['ai_claude_model'] ?? $settings->ai_claude_model,
        'ai_openai_model' => $data['ai_openai_model'] ?? $settings->ai_openai_model,
        'ai_ollama_base_url' => $data['ai_ollama_base_url'] ?? $settings->ai_ollama_base_url,
        'ai_ollama_model' => $data['ai_ollama_model'] ?? $settings->ai_ollama_model,
        'ai_max_tokens' => $data['ai_max_tokens'] ?? $settings->ai_max_tokens,
        'ai_cache_enabled' => $data['ai_cache_enabled'] ?? $settings->ai_cache_enabled,
        'ai_cache_ttl' => $data['ai_cache_ttl'] ?? $settings->ai_cache_ttl,
        // Global S3 (non-secret)
        's3_enabled' => $data['s3_enabled'] ?? $settings->s3_enabled,
        's3_endpoint' => $data['s3_endpoint'] ?? $settings->s3_endpoint,
        's3_bucket' => $data['s3_bucket'] ?? $settings->s3_bucket,
        's3_region' => $data['s3_region'] ?? $settings->s3_region,
        's3_path' => $data['s3_path'] ?? $settings->s3_path,
        // Application global defaults
        'app_default_auto_deploy' => $data['app_default_auto_deploy'] ?? $settings->app_default_auto_deploy,
        'app_default_force_https' => $data['app_default_force_https'] ?? $settings->app_default_force_https,
        'app_default_preview_deployments' => $data['app_default_preview_deployments'] ?? $settings->app_default_preview_deployments,
        'app_default_pr_deployments_public' => $data['app_default_pr_deployments_public'] ?? $settings->app_default_pr_deployments_public,
        'app_default_git_submodules' => $data['app_default_git_submodules'] ?? $settings->app_default_git_submodules,
        'app_default_git_lfs' => $data['app_default_git_lfs'] ?? $settings->app_default_git_lfs,
        'app_default_git_shallow_clone' => $data['app_default_git_shallow_clone'] ?? $settings->app_default_git_shallow_clone,
        'app_default_use_build_secrets' => $data['app_default_use_build_secrets'] ?? $settings->app_default_use_build_secrets,
        'app_default_inject_build_args' => $data['app_default_inject_build_args'] ?? $settings->app_default_inject_build_args,
        'app_default_include_commit_in_build' => $data['app_default_include_commit_in_build'] ?? $settings->app_default_include_commit_in_build,
        'app_default_docker_images_to_keep' => $data['app_default_docker_images_to_keep'] ?? $settings->app_default_docker_images_to_keep,
        'app_default_auto_rollback' => $data['app_default_auto_rollback'] ?? $settings->app_default_auto_rollback,
        'app_default_rollback_validation_sec' => $data['app_default_rollback_validation_sec'] ?? $settings->app_default_rollback_validation_sec,
        'app_default_rollback_max_restarts' => $data['app_default_rollback_max_restarts'] ?? $settings->app_default_rollback_max_restarts,
        'app_default_rollback_on_health_fail' => $data['app_default_rollback_on_health_fail'] ?? $settings->app_default_rollback_on_health_fail,
        'app_default_rollback_on_crash_loop' => $data['app_default_rollback_on_crash_loop'] ?? $settings->app_default_rollback_on_crash_loop,
        'app_default_debug' => $data['app_default_debug'] ?? $settings->app_default_debug,
        'app_default_build_pack' => $data['app_default_build_pack'] ?? $settings->app_default_build_pack,
        'app_default_build_timeout' => $data['app_default_build_timeout'] ?? $settings->app_default_build_timeout,
        'app_default_static_image' => $data['app_default_static_image'] ?? $settings->app_default_static_image,
        'app_default_requires_approval' => $data['app_default_requires_approval'] ?? $settings->app_default_requires_approval,
        // SSH Configuration
        'ssh_mux_enabled' => $data['ssh_mux_enabled'] ?? $settings->ssh_mux_enabled,
        'ssh_mux_persist_time' => $data['ssh_mux_persist_time'] ?? $settings->ssh_mux_persist_time,
        'ssh_mux_max_age' => $data['ssh_mux_max_age'] ?? $settings->ssh_mux_max_age,
        'ssh_connection_timeout' => $data['ssh_connection_timeout'] ?? $settings->ssh_connection_timeout,
        'ssh_command_timeout' => $data['ssh_command_timeout'] ?? $settings->ssh_command_timeout,
        'ssh_max_retries' => $data['ssh_max_retries'] ?? $settings->ssh_max_retries,
        'ssh_retry_base_delay' => $data['ssh_retry_base_delay'] ?? $settings->ssh_retry_base_delay,
        'ssh_retry_max_delay' => $data['ssh_retry_max_delay'] ?? $settings->ssh_retry_max_delay,
        // Docker Registry (non-secret)
        'docker_registry_url' => $data['docker_registry_url'] ?? $settings->docker_registry_url,
        // Default Proxy
        'default_proxy_type' => $data['default_proxy_type'] ?? $settings->default_proxy_type,
        // Rate Limiting & Queue
        'api_rate_limit' => $data['api_rate_limit'] ?? $settings->api_rate_limit,
        'horizon_balance' => $data['horizon_balance'] ?? $settings->horizon_balance,
        'horizon_min_processes' => $data['horizon_min_processes'] ?? $settings->horizon_min_processes,
        'horizon_max_processes' => $data['horizon_max_processes'] ?? $settings->horizon_max_processes,
        'horizon_worker_memory' => $data['horizon_worker_memory'] ?? $settings->horizon_worker_memory,
        'horizon_worker_timeout' => $data['horizon_worker_timeout'] ?? $settings->horizon_worker_timeout,
        'horizon_max_jobs' => $data['horizon_max_jobs'] ?? $settings->horizon_max_jobs,
        'horizon_trim_recent_minutes' => $data['horizon_trim_recent_minutes'] ?? $settings->horizon_trim_recent_minutes,
        'horizon_trim_failed_minutes' => $data['horizon_trim_failed_minutes'] ?? $settings->horizon_trim_failed_minutes,
        'horizon_queue_wait_threshold' => $data['horizon_queue_wait_threshold'] ?? $settings->horizon_queue_wait_threshold,
    ];

    // Only update secret fields if the value is not the placeholder
    $secretMappings = [
        'smtp_username' => 'smtp_username',
        'smtp_password' => 'smtp_password',
        'resend_api_key' => 'resend_api_key',
        'sentinel_token' => 'sentinel_token',
        'auto_provision_api_key' => 'auto_provision_api_key',
        'ai_anthropic_api_key' => 'ai_anthropic_api_key',
        'ai_openai_api_key' => 'ai_openai_api_key',
        's3_key' => 's3_key',
        's3_secret' => 's3_secret',
        'docker_registry_username' => 'docker_registry_username',
        'docker_registry_password' => 'docker_registry_password',
    ];

    foreach ($secretMappings as $formField => $dbField) {
        $value = $data[$formField] ?? null;
        if ($value !== null && $value !== $placeholder && $value !== '') {
            $updateData[$dbField] = $value;
        }
    }

    $settings->update($updateData);

    return back()->with('success', 'Settings updated successfully.');
})->name('admin.settings.update');

Route::post('/settings/test-email', function () {
    $settings = \App\Models\InstanceSettings::get();

    if (! $settings->smtp_enabled && ! $settings->resend_enabled) {
        return back()->with('error', 'No email provider is configured. Enable SMTP or Resend first.');
    }

    try {
        $recipients = $settings->smtp_recipients;
        if (empty($recipients)) {
            return back()->with('error', 'No recipients configured. Add default recipients in SMTP settings.');
        }

        $emailList = array_filter(array_map('trim', explode(',', $recipients)));
        if (empty($emailList)) {
            return back()->with('error', 'No valid email addresses found in recipients.');
        }

        \Illuminate\Support\Facades\Mail::raw(
            'This is a test email from Saturn Platform. If you received this, your email configuration is working correctly.',
            function ($message) use ($emailList, $settings) {
                $message->to($emailList)
                    ->subject('[Saturn] Test Email - '.now()->format('Y-m-d H:i:s'));

                if ($settings->smtp_from_address) {
                    $message->from($settings->smtp_from_address, $settings->smtp_from_name ?: 'Saturn Platform');
                }
            }
        );

        return back()->with('success', 'Test email sent successfully to: '.implode(', ', $emailList));
    } catch (\Exception $e) {
        return back()->with('error', 'Failed to send test email: '.$e->getMessage());
    }
})->name('admin.settings.test-email');

Route::get('/settings/export', function () {
    $settings = \App\Models\InstanceSettings::get();
    $data = $settings->toArray();

    // Remove sensitive fields from export
    $sensitiveFields = [
        'id', 'created_at', 'updated_at',
        'smtp_password', 'smtp_username', 'resend_api_key', 'sentinel_token',
        'auto_provision_api_key', 'ai_anthropic_api_key', 'ai_openai_api_key',
        's3_key', 's3_secret', 'docker_registry_username', 'docker_registry_password',
    ];
    foreach ($sensitiveFields as $field) {
        unset($data[$field]);
    }

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    return response($json, 200, [
        'Content-Type' => 'application/json',
        'Content-Disposition' => 'attachment; filename="saturn-settings-'.date('Y-m-d').'.json"',
    ]);
})->name('admin.settings.export');

Route::post('/settings/import', function (\Illuminate\Http\Request $request) {
    $request->validate([
        'file' => 'required|file|mimes:json|max:1024',
    ]);

    try {
        $content = file_get_contents($request->file('file')->getRealPath());
        $data = json_decode($content, true);

        if (! is_array($data)) {
            return back()->with('error', 'Invalid JSON format.');
        }

        $settings = \App\Models\InstanceSettings::get();
        $fillable = $settings->getFillable();

        // Only import fields that are in $fillable and not sensitive
        $sensitiveFields = [
            'smtp_password', 'smtp_username', 'resend_api_key', 'sentinel_token',
            'auto_provision_api_key', 'ai_anthropic_api_key', 'ai_openai_api_key',
            's3_key', 's3_secret', 'docker_registry_username', 'docker_registry_password',
        ];

        $importData = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $fillable) && ! in_array($key, $sensitiveFields)) {
                $importData[$key] = $value;
            }
        }

        if (empty($importData)) {
            return back()->with('error', 'No valid settings found in the file.');
        }

        $settings->update($importData);

        return back()->with('success', 'Settings imported successfully. '.count($importData).' fields updated.');
    } catch (\Exception $e) {
        return back()->with('error', 'Failed to import settings: '.$e->getMessage());
    }
})->name('admin.settings.import');
