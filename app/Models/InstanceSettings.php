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

    protected $guarded = [];

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
