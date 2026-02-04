<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Alert extends BaseModel
{
    use Auditable, LogsActivity;

    /**
     * SECURITY: Using $fillable instead of $guarded = [] to prevent mass assignment vulnerabilities.
     * Excludes: id, team_id (security), triggered_count, last_triggered_at (system-managed)
     */
    protected $fillable = [
        'uuid',
        'name',
        'type',
        'enabled',
        'channels',
        'threshold',
        'duration',
        'message_template',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'channels' => 'array',
            'threshold' => 'float',
            'duration' => 'integer',
            'triggered_count' => 'integer',
            'last_triggered_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'type', 'threshold', 'enabled', 'channels'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public static function ownedByCurrentTeam()
    {
        return Alert::where('team_id', currentTeam()->id)->orderBy('created_at', 'desc');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(AlertHistory::class)->orderBy('triggered_at', 'desc');
    }
}
