<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property-read Application|null $application

 * @property bool $is_pr_deployments_public_enabled
 * @property bool $disable_build_cache
 * @property bool $is_preserve_repository_enabled
 * @property string|null $custom_internal_name
 * @property bool $is_consistent_container_name_enabled
 * @property bool $include_source_commit_in_build
 * @property bool $is_static
 * @property bool $use_build_secrets
 * @property bool $auto_rollback_enabled
 * @property bool $rollback_on_crash_loop
 * @property bool $rollback_on_health_check_fail
 * @property bool $is_auto_deploy_enabled
 * @property bool $is_preview_deployments_enabled
 * @property bool $inject_build_args_to_dockerfile
 * @property bool $is_git_submodules_enabled
 * @property bool $is_git_lfs_enabled
 * @property bool $is_raw_compose_deployment_enabled
 * @property bool $is_env_sorting_enabled
 * @property bool $is_container_label_readonly_enabled
 * @property bool $is_container_label_escape_enabled
 * @property bool $is_gpu_enabled
 * @property bool $is_spa
 */
class ApplicationSetting extends Model
{
    use Auditable, LogsActivity;

    /**
     * SECURITY: Using $fillable instead of $guarded = [] to prevent mass assignment vulnerabilities.
     * Excludes: id
     */
    protected $fillable = [
        'application_id',
        'is_static',
        'is_spa',
        'is_build_server_enabled',
        'is_preserve_repository_enabled',
        'is_container_label_escape_enabled',
        'is_container_label_readonly_enabled',
        'use_build_secrets',
        'inject_build_args_to_dockerfile',
        'include_source_commit_in_build',
        'is_auto_deploy_enabled',
        'is_force_https_enabled',
        'is_debug_enabled',
        'is_preview_deployments_enabled',
        'is_pr_deployments_public_enabled',
        'is_git_submodules_enabled',
        'is_git_lfs_enabled',
        'is_git_shallow_clone_enabled',
        'docker_images_to_keep',
        'auto_rollback_enabled',
        'rollback_validation_seconds',
        'rollback_max_restarts',
        'rollback_on_health_check_fail',
        'rollback_on_crash_loop',
    ];

    protected $casts = [
        'is_static' => 'boolean',
        'is_spa' => 'boolean',
        'is_build_server_enabled' => 'boolean',
        'is_preserve_repository_enabled' => 'boolean',
        'is_container_label_escape_enabled' => 'boolean',
        'is_container_label_readonly_enabled' => 'boolean',
        'use_build_secrets' => 'boolean',
        'inject_build_args_to_dockerfile' => 'boolean',
        'include_source_commit_in_build' => 'boolean',
        'is_auto_deploy_enabled' => 'boolean',
        'is_force_https_enabled' => 'boolean',
        'is_debug_enabled' => 'boolean',
        'is_preview_deployments_enabled' => 'boolean',
        'is_pr_deployments_public_enabled' => 'boolean',
        'is_git_submodules_enabled' => 'boolean',
        'is_git_lfs_enabled' => 'boolean',
        'is_git_shallow_clone_enabled' => 'boolean',
        'docker_images_to_keep' => 'integer',
        // Auto-rollback settings
        'auto_rollback_enabled' => 'boolean',
        'rollback_validation_seconds' => 'integer',
        'rollback_max_restarts' => 'integer',
        'rollback_on_health_check_fail' => 'boolean',
        'rollback_on_crash_loop' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['is_auto_deploy_enabled', 'is_force_https_enabled', 'is_debug_enabled', 'auto_rollback_enabled'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function isStatic(): Attribute
    {
        return Attribute::make(
            set: function ($value) {
                if ($value) {
                    $this->application->ports_exposes = 80;
                }
                $this->application->save();

                return $value;
            }
        );
    }

    public function application()
    {
        return $this->belongsTo(Application::class);
    }
}
