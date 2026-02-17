<?php

namespace App\Models;

use App\Traits\Auditable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class GitlabApp extends BaseModel
{
    use Auditable, LogsActivity;

    protected $fillable = [
        'name',
        'organization',
        'api_url',
        'html_url',
        'custom_port',
        'custom_user',
        'is_system_wide',
        'is_public',
        'app_id',
        'app_secret',
        'oauth_id',
        'group_name',
        'public_key',
        'webhook_token',
        'deploy_key_id',
        'private_key_id',
    ];

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
