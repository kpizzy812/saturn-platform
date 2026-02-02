<?php

namespace App\Models;

use App\Traits\Auditable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class GitlabApp extends BaseModel
{
    use Auditable, LogsActivity;

    // Security: Encrypt secrets at rest
    protected $casts = [
        'webhook_token' => 'encrypted',
        'app_secret' => 'encrypted',
    ];

    protected $hidden = [
        'webhook_token',
        'app_secret',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'api_url', 'is_system_wide'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public static function ownedByCurrentTeam()
    {
        return GitlabApp::whereTeamId(currentTeam()->id);
    }

    public function applications()
    {
        return $this->morphMany(Application::class, 'source');
    }

    public function privateKey()
    {
        return $this->belongsTo(PrivateKey::class);
    }
}
