<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ProjectNotificationOverride extends Model
{
    use Auditable, LogsActivity;

    protected $fillable = [
        'project_id',
        'deployment_success',
        'deployment_failure',
        'backup_success',
        'backup_failure',
        'status_change',
        'custom_discord_webhook',
        'custom_slack_webhook',
        'custom_webhook_url',
    ];

    protected $casts = [
        'deployment_success' => 'boolean',
        'deployment_failure' => 'boolean',
        'backup_success' => 'boolean',
        'backup_failure' => 'boolean',
        'status_change' => 'boolean',
    ];

    protected $hidden = [
        'custom_discord_webhook',
        'custom_slack_webhook',
        'custom_webhook_url',
    ];

    protected $encrypted = [
        'custom_discord_webhook',
        'custom_slack_webhook',
        'custom_webhook_url',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'deployment_success', 'deployment_failure',
                'backup_success', 'backup_failure', 'status_change',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Resolve whether a notification event is enabled.
     * Returns the override value if set, otherwise falls back to the team default.
     */
    public function isEnabled(string $event, bool $teamDefault): bool
    {
        $value = $this->getAttribute($event);

        // null = inherit from team
        if ($value === null) {
            return $teamDefault;
        }

        return (bool) $value;
    }
}
