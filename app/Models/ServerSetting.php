<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[OA\Schema(
    description: 'Server Settings model',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'concurrent_builds', type: 'integer'),
        new OA\Property(property: 'deployment_queue_limit', type: 'integer'),
        new OA\Property(property: 'dynamic_timeout', type: 'integer'),
        new OA\Property(property: 'force_disabled', type: 'boolean'),
        new OA\Property(property: 'force_server_cleanup', type: 'boolean'),
        new OA\Property(property: 'is_build_server', type: 'boolean'),
        new OA\Property(property: 'is_cloudflare_tunnel', type: 'boolean'),
        new OA\Property(property: 'is_jump_server', type: 'boolean'),
        new OA\Property(property: 'is_logdrain_axiom_enabled', type: 'boolean'),
        new OA\Property(property: 'is_logdrain_custom_enabled', type: 'boolean'),
        new OA\Property(property: 'is_logdrain_highlight_enabled', type: 'boolean'),
        new OA\Property(property: 'is_logdrain_newrelic_enabled', type: 'boolean'),
        new OA\Property(property: 'is_metrics_enabled', type: 'boolean'),
        new OA\Property(property: 'is_reachable', type: 'boolean'),
        new OA\Property(property: 'is_sentinel_enabled', type: 'boolean'),
        new OA\Property(property: 'is_swarm_manager', type: 'boolean'),
        new OA\Property(property: 'is_swarm_worker', type: 'boolean'),
        new OA\Property(property: 'is_terminal_enabled', type: 'boolean'),
        new OA\Property(property: 'is_usable', type: 'boolean'),
        new OA\Property(property: 'logdrain_axiom_api_key', type: 'string'),
        new OA\Property(property: 'logdrain_axiom_dataset_name', type: 'string'),
        new OA\Property(property: 'logdrain_custom_config', type: 'string'),
        new OA\Property(property: 'logdrain_custom_config_parser', type: 'string'),
        new OA\Property(property: 'logdrain_highlight_project_id', type: 'string'),
        new OA\Property(property: 'logdrain_newrelic_base_uri', type: 'string'),
        new OA\Property(property: 'logdrain_newrelic_license_key', type: 'string'),
        new OA\Property(property: 'sentinel_metrics_history_days', type: 'integer'),
        new OA\Property(property: 'sentinel_metrics_refresh_rate_seconds', type: 'integer'),
        new OA\Property(property: 'sentinel_token', type: 'string'),
        new OA\Property(property: 'docker_cleanup_frequency', type: 'string'),
        new OA\Property(property: 'docker_cleanup_threshold', type: 'integer'),
        new OA\Property(property: 'server_id', type: 'integer'),
        new OA\Property(property: 'wildcard_domain', type: 'string'),
        new OA\Property(property: 'created_at', type: 'string'),
        new OA\Property(property: 'updated_at', type: 'string'),
        new OA\Property(property: 'delete_unused_volumes', type: 'boolean', description: 'The flag to indicate if the unused volumes should be deleted.'),
        new OA\Property(property: 'delete_unused_networks', type: 'boolean', description: 'The flag to indicate if the unused networks should be deleted.'),
    ]
)]
class ServerSetting extends Model
{
    use Auditable, LogsActivity;

    /**
     * SECURITY: Using $fillable instead of $guarded = [] to prevent mass assignment vulnerabilities.
     * Excludes: id, is_reachable, is_usable (system-managed),
     * sentinel_token (auto-generated), sentinel_custom_url (auto-generated)
     */
    protected $fillable = [
        'server_id',
        'concurrent_builds',
        'deployment_queue_limit',
        'dynamic_timeout',
        'force_disabled',
        'force_docker_cleanup',
        'docker_cleanup_frequency',
        'docker_cleanup_threshold',
        'delete_unused_volumes',
        'delete_unused_networks',
        'is_build_server',
        'is_master_server',
        'is_cloudflare_tunnel',
        'is_jump_server',
        'is_logdrain_axiom_enabled',
        'is_logdrain_custom_enabled',
        'is_logdrain_highlight_enabled',
        'is_logdrain_newrelic_enabled',
        'is_metrics_enabled',
        'is_sentinel_enabled',
        'is_swarm_manager',
        'is_swarm_worker',
        'is_terminal_enabled',
        'logdrain_axiom_api_key',
        'logdrain_axiom_dataset_name',
        'logdrain_custom_config',
        'logdrain_custom_config_parser',
        'logdrain_highlight_project_id',
        'logdrain_newrelic_base_uri',
        'logdrain_newrelic_license_key',
        'sentinel_metrics_history_days',
        'sentinel_metrics_refresh_rate_seconds',
        'sentinel_push_interval_seconds',
        'wildcard_domain',
        'disable_application_image_retention',
    ];

    protected $casts = [
        'force_docker_cleanup' => 'boolean',
        'docker_cleanup_threshold' => 'integer',
        'is_master_server' => 'boolean',
        'sentinel_token' => 'encrypted',
        'is_reachable' => 'boolean',
        'is_usable' => 'boolean',
        'is_terminal_enabled' => 'boolean',
        'disable_application_image_retention' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['is_build_server', 'is_metrics_enabled', 'is_sentinel_enabled', 'force_docker_cleanup'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected static function booted()
    {
        static::creating(function ($setting) {
            try {
                if (str($setting->sentinel_token)->isEmpty()) {
                    $setting->generateSentinelToken(save: false, ignoreEvent: true);
                }
                if (str($setting->sentinel_custom_url)->isEmpty()) {
                    $setting->generateSentinelUrl(save: false, ignoreEvent: true);
                }
            } catch (\Throwable $e) {
                Log::error('Error creating server setting: '.$e->getMessage());
            }
        });
        static::updated(function ($settings) {
            if (
                $settings->wasChanged('sentinel_token') ||
                $settings->wasChanged('sentinel_custom_url') ||
                $settings->wasChanged('sentinel_metrics_refresh_rate_seconds') ||
                $settings->wasChanged('sentinel_metrics_history_days') ||
                $settings->wasChanged('sentinel_push_interval_seconds')
            ) {
                $settings->server->restartSentinel();
            }
        });
    }

    public function generateSentinelToken(bool $save = true, bool $ignoreEvent = false)
    {
        $data = [
            'server_uuid' => $this->server->uuid,
        ];
        $token = json_encode($data);
        $encrypted = encrypt($token);
        $this->sentinel_token = $encrypted;
        if ($save) {
            if ($ignoreEvent) {
                $this->saveQuietly();
            } else {
                $this->save();
            }
        }

        return $token;
    }

    public function generateSentinelUrl(bool $save = true, bool $ignoreEvent = false)
    {
        $domain = null;
        $settings = InstanceSettings::get();
        if ($this->server->checkIsLocalhost()) {
            $domain = 'http://host.docker.internal:8000';
        } elseif ($settings->fqdn) {
            $domain = $settings->fqdn;
        } elseif ($settings->public_ipv4) {
            $domain = 'http://'.$settings->public_ipv4.':8000';
        } elseif ($settings->public_ipv6) {
            $domain = 'http://'.$settings->public_ipv6.':8000';
        }
        $this->sentinel_custom_url = $domain;
        if ($save) {
            if ($ignoreEvent) {
                $this->saveQuietly();
            } else {
                $this->save();
            }
        }

        return $domain;
    }

    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    public function dockerCleanupFrequency(): Attribute
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
}
