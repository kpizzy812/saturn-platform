<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ProjectSetting extends Model
{
    use Auditable, LogsActivity;

    /**
     * SECURITY: Using $fillable instead of $guarded = [] to prevent mass assignment vulnerabilities.
     * Excludes: id, project_id (relationship)
     */
    protected $fillable = [
        'default_server_id',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['default_server_id'])
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
