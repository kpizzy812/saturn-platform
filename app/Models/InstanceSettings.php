<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Url\Url;

class InstanceSettings extends Model
{
    use Auditable, LogsActivity;

    /**
     * SECURITY: Using $fillable instead of $guarded = [] to prevent mass assignment vulnerabilities.
     * Note: This is a singleton model (id=0), but we still use $fillable for security
     */
    protected $fillable = [
        'fqdn',
        'instance_name',
        'public_ipv4',
        'public_ipv6',
        'smtp_enabled',
        'smtp_from_address',
        'smtp_from_name',
        'smtp_recipients',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'smtp_timeout',
        'smtp_encryption',
        'resend_enabled',
        'resend_api_key',
        'allowed_ip_ranges',
        'is_auto_update_enabled',
        'auto_update_frequency',
        'update_check_frequency',
        'sentinel_token',
        'is_wire_navigate_enabled',
        'is_registration_enabled',
        'is_dns_validation_enabled',
        'is_ai_code_review_enabled',
        'is_ai_error_analysis_enabled',
        'is_ai_chat_enabled',
        'resource_warning_cpu_threshold',
        'resource_critical_cpu_threshold',
        'resource_warning_memory_threshold',
        'resource_critical_memory_threshold',
        'resource_warning_disk_threshold',
        'resource_critical_disk_threshold',
        'resource_monitoring_enabled',
        'resource_check_interval_minutes',
        'auto_provision_enabled',
        'auto_provision_api_key',
        'auto_provision_max_servers_per_day',
        'auto_provision_cooldown_minutes',
        // AI Provider settings
        'ai_default_provider',
        'ai_anthropic_api_key',
        'ai_openai_api_key',
        'ai_claude_model',
        'ai_openai_model',
        'ai_ollama_base_url',
        'ai_ollama_model',
        'ai_max_tokens',
        'ai_cache_enabled',
        'ai_cache_ttl',
        // Global S3 storage
        's3_enabled',
        's3_endpoint',
        's3_bucket',
        's3_region',
        's3_key',
        's3_secret',
        's3_path',
        // Application global defaults
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
        'app_default_build_pack',
        'app_default_build_timeout',
        'app_default_static_image',
        'app_default_requires_approval',
        // Infrastructure: SSH
        'ssh_mux_enabled',
        'ssh_mux_persist_time',
        'ssh_mux_max_age',
        'ssh_connection_timeout',
        'ssh_command_timeout',
        'ssh_max_retries',
        'ssh_retry_base_delay',
        'ssh_retry_max_delay',
        // Infrastructure: Docker Registry
        'docker_registry_url',
        'docker_registry_username',
        'docker_registry_password',
        // Infrastructure: Default Proxy
        'default_proxy_type',
        // Rate Limiting & Queue
        'api_rate_limit',
        'horizon_balance',
        'horizon_min_processes',
        'horizon_max_processes',
        'horizon_worker_memory',
        'horizon_worker_timeout',
        'horizon_max_jobs',
        'horizon_trim_recent_minutes',
        'horizon_trim_failed_minutes',
        'horizon_queue_wait_threshold',
        // Cloudflare Protection
        'cloudflare_api_token',
        'cloudflare_account_id',
        'cloudflare_zone_id',
        'cloudflare_tunnel_id',
        'cloudflare_tunnel_token',
        'is_cloudflare_protection_enabled',
        'cloudflare_last_synced_at',
    ];

    protected $casts = [
        'smtp_enabled' => 'boolean',
        'smtp_from_address' => 'encrypted',
        'smtp_from_name' => 'encrypted',
        'smtp_recipients' => 'encrypted',
        'smtp_host' => 'encrypted',
        'smtp_port' => 'integer',
        'smtp_username' => 'encrypted',
        'smtp_password' => 'encrypted',
        'smtp_timeout' => 'integer',

        'resend_enabled' => 'boolean',
        'resend_api_key' => 'encrypted',

        'allowed_ip_ranges' => 'array',
        'is_auto_update_enabled' => 'boolean',
        'auto_update_frequency' => 'string',
        'update_check_frequency' => 'string',
        'sentinel_token' => 'encrypted',
        'is_wire_navigate_enabled' => 'boolean',

        // AI features
        'is_ai_code_review_enabled' => 'boolean',
        'is_ai_error_analysis_enabled' => 'boolean',
        'is_ai_chat_enabled' => 'boolean',

        // Resource monitoring
        'resource_warning_cpu_threshold' => 'integer',
        'resource_critical_cpu_threshold' => 'integer',
        'resource_warning_memory_threshold' => 'integer',
        'resource_critical_memory_threshold' => 'integer',
        'resource_warning_disk_threshold' => 'integer',
        'resource_critical_disk_threshold' => 'integer',
        'resource_monitoring_enabled' => 'boolean',
        'resource_check_interval_minutes' => 'integer',

        // Auto-provisioning
        'auto_provision_enabled' => 'boolean',
        'auto_provision_api_key' => 'encrypted',
        'auto_provision_max_servers_per_day' => 'integer',
        'auto_provision_cooldown_minutes' => 'integer',

        // AI Provider settings
        'ai_anthropic_api_key' => 'encrypted',
        'ai_openai_api_key' => 'encrypted',
        'ai_max_tokens' => 'integer',
        'ai_cache_enabled' => 'boolean',
        'ai_cache_ttl' => 'integer',

        // Global S3 storage
        's3_enabled' => 'boolean',
        's3_key' => 'encrypted',
        's3_secret' => 'encrypted',

        // Application global defaults
        'app_default_auto_deploy' => 'boolean',
        'app_default_force_https' => 'boolean',
        'app_default_preview_deployments' => 'boolean',
        'app_default_pr_deployments_public' => 'boolean',
        'app_default_git_submodules' => 'boolean',
        'app_default_git_lfs' => 'boolean',
        'app_default_git_shallow_clone' => 'boolean',
        'app_default_use_build_secrets' => 'boolean',
        'app_default_inject_build_args' => 'boolean',
        'app_default_include_commit_in_build' => 'boolean',
        'app_default_docker_images_to_keep' => 'integer',
        'app_default_auto_rollback' => 'boolean',
        'app_default_rollback_validation_sec' => 'integer',
        'app_default_rollback_max_restarts' => 'integer',
        'app_default_rollback_on_health_fail' => 'boolean',
        'app_default_rollback_on_crash_loop' => 'boolean',
        'app_default_debug' => 'boolean',
        'app_default_build_timeout' => 'integer',
        'app_default_requires_approval' => 'boolean',

        // Infrastructure: SSH
        'ssh_mux_enabled' => 'boolean',
        'ssh_mux_persist_time' => 'integer',
        'ssh_mux_max_age' => 'integer',
        'ssh_connection_timeout' => 'integer',
        'ssh_command_timeout' => 'integer',
        'ssh_max_retries' => 'integer',
        'ssh_retry_base_delay' => 'integer',
        'ssh_retry_max_delay' => 'integer',

        // Infrastructure: Docker Registry
        'docker_registry_username' => 'encrypted',
        'docker_registry_password' => 'encrypted',

        // Rate Limiting & Queue
        'api_rate_limit' => 'integer',
        'horizon_min_processes' => 'integer',
        'horizon_max_processes' => 'integer',
        'horizon_worker_memory' => 'integer',
        'horizon_worker_timeout' => 'integer',
        'horizon_max_jobs' => 'integer',
        'horizon_trim_recent_minutes' => 'integer',
        'horizon_trim_failed_minutes' => 'integer',
        'horizon_queue_wait_threshold' => 'integer',

        // Cloudflare Protection
        'cloudflare_api_token' => 'encrypted',
        'cloudflare_tunnel_token' => 'encrypted',
        'is_cloudflare_protection_enabled' => 'boolean',
        'cloudflare_last_synced_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updated(function ($settings) {
            // Clear trusted hosts cache when FQDN changes
            if ($settings->wasChanged('fqdn')) {
                Cache::forget('instance_settings_fqdn_host');
            }
        });
    }

    public function hasCloudflareProtection(): bool
    {
        return ! empty($this->cloudflare_api_token)
            && ! empty($this->cloudflare_account_id)
            && ! empty($this->cloudflare_zone_id);
    }

    public function isCloudflareProtectionActive(): bool
    {
        return $this->is_cloudflare_protection_enabled
            && $this->hasCloudflareProtection()
            && ! empty($this->cloudflare_tunnel_id);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['fqdn', 'is_auto_update_enabled', 'is_registration_enabled', 'is_dns_validation_enabled'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function fqdn(): Attribute
    {
        return Attribute::make(
            set: function ($value) {
                if ($value) {
                    $url = Url::fromString($value);
                    $host = $url->getHost();

                    return $url->getScheme().'://'.$host;
                }
            }
        );
    }

    public function updateCheckFrequency(): Attribute
    {
        return Attribute::make(
            set: function ($value) {
                return translate_cron_expression($value);
            },
            get: function ($value) {
                return translate_cron_expression($value);
            }
        );
    }

    public function autoUpdateFrequency(): Attribute
    {
        return Attribute::make(
            set: function ($value) {
                return translate_cron_expression($value);
            },
            get: function ($value) {
                return translate_cron_expression($value);
            }
        );
    }

    public static function get()
    {
        return InstanceSettings::findOrFail(0);
    }

    // public function getRecipients($notification)
    // {
    //     $recipients = data_get($notification, 'emails', null);
    //     if (is_null($recipients) || $recipients === '') {
    //         return [];
    //     }

    //     return explode(',', $recipients);
    // }

    /**
     * Get application defaults mapped to ApplicationSetting field names.
     */
    public function getApplicationDefaults(): array
    {
        return [
            'is_auto_deploy_enabled' => $this->app_default_auto_deploy,
            'is_force_https_enabled' => $this->app_default_force_https,
            'is_preview_deployments_enabled' => $this->app_default_preview_deployments,
            'is_pr_deployments_public_enabled' => $this->app_default_pr_deployments_public,
            'is_git_submodules_enabled' => $this->app_default_git_submodules,
            'is_git_lfs_enabled' => $this->app_default_git_lfs,
            'is_git_shallow_clone_enabled' => $this->app_default_git_shallow_clone,
            'use_build_secrets' => $this->app_default_use_build_secrets,
            'inject_build_args_to_dockerfile' => $this->app_default_inject_build_args,
            'include_source_commit_in_build' => $this->app_default_include_commit_in_build,
            'docker_images_to_keep' => $this->app_default_docker_images_to_keep,
            'auto_rollback_enabled' => $this->app_default_auto_rollback,
            'rollback_validation_seconds' => $this->app_default_rollback_validation_sec,
            'rollback_max_restarts' => $this->app_default_rollback_max_restarts,
            'rollback_on_health_check_fail' => $this->app_default_rollback_on_health_fail,
            'rollback_on_crash_loop' => $this->app_default_rollback_on_crash_loop,
            'is_debug_enabled' => $this->app_default_debug,
            'build_pack' => $this->app_default_build_pack,
        ];
    }

    public function getTitleDisplayName(): string
    {
        $instanceName = $this->instance_name;
        if (! $instanceName) {
            return '';
        }

        return "[{$instanceName}]";
    }

    // public function helperVersion(): Attribute
    // {
    //     return Attribute::make(
    //         get: function ($value) {
    //             if (isDev()) {
    //                 return 'latest';
    //             }

    //             return $value;
    //         }
    //     );
    // }
}
