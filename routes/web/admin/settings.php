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
    $secretFields = ['smtp_password', 'smtp_username', 'resend_api_key', 'sentinel_token', 'auto_provision_api_key', 'ai_anthropic_api_key', 'ai_openai_api_key'];
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
