<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $uuid
 * @property int $team_id
 * @property string $name
 * @property string $metric
 * @property string $condition
 * @property float $threshold
 * @property int $duration
 * @property bool $enabled
 * @property array|null $channels
 * @property int $triggered_count
 * @property \Carbon\Carbon|null $last_triggered_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
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
        'metric',
        'condition',
        'enabled',
        'channels',
        'threshold',
        'duration',
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
            ->logOnly(['name', 'metric', 'condition', 'threshold', 'enabled', 'channels'])
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
