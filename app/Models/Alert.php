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

    protected $guarded = [];

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
