<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int|null $default_server_id
 * @property int $max_applications
 * @property int $max_services
 * @property int $max_databases
 * @property int $max_environments
 */
class ProjectSetting extends Model
{
    use Auditable, LogsActivity;

    /**
     * SECURITY: Using $fillable instead of $guarded = [] to prevent mass assignment vulnerabilities.
     * Excludes: id
     */
    protected $fillable = [
        'project_id',
        'default_server_id',
        // Quotas
        'max_applications',
        'max_services',
        'max_databases',
        'max_environments',
        // Deployment defaults
        'default_build_pack',
        'default_git_branch',
        'default_auto_deploy',
        'default_force_https',
        'default_preview_deployments',
        'default_auto_rollback',
    ];

    protected $casts = [
        'max_applications' => 'integer',
        'max_services' => 'integer',
        'max_databases' => 'integer',
        'max_environments' => 'integer',
        'default_auto_deploy' => 'boolean',
        'default_force_https' => 'boolean',
        'default_preview_deployments' => 'boolean',
        'default_auto_rollback' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'default_server_id',
                'max_applications', 'max_services', 'max_databases', 'max_environments',
                'default_build_pack', 'default_git_branch', 'default_auto_deploy',
                'default_force_https', 'default_preview_deployments', 'default_auto_rollback',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function defaultServer()
    {
        return $this->belongsTo(Server::class, 'default_server_id');
    }
}
