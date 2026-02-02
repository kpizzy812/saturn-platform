<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ProjectSetting extends Model
{
    use Auditable, LogsActivity;

    protected $guarded = [];

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
