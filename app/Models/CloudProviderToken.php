<?php

namespace App\Models;

use App\Traits\Auditable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CloudProviderToken extends BaseModel
{
    use Auditable, LogsActivity;

    /**
     * SECURITY: Using $fillable instead of $guarded = [] to prevent mass assignment vulnerabilities.
     * Excludes: id, team_id (security)
     */
    protected $fillable = [
        'uuid',
        'name',
        'provider',
        'token',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'provider'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $casts = [
        'token' => 'encrypted',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function servers()
    {
        return $this->hasMany(Server::class);
    }

    public function hasServers(): bool
    {
        return $this->servers()->exists();
    }

    public static function ownedByCurrentTeam(array $select = ['*'])
    {
        $selectArray = collect($select)->concat(['id']);

        return self::whereTeamId(currentTeam()->id)->select($selectArray->all());
    }

    public function scopeForProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }
}
