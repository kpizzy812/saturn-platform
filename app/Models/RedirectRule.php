<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class RedirectRule extends BaseModel
{
    use Auditable, LogsActivity;

    /**
     * SECURITY: Using $fillable instead of $guarded = [] to prevent mass assignment vulnerabilities.
     * Excludes: id, team_id (security), application_id (relationship), hits (system-managed)
     */
    protected $fillable = [
        'uuid',
        'source',
        'destination',
        'type',
        'enabled',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['source', 'destination', 'type', 'enabled'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'hits' => 'integer',
        ];
    }

    public static function ownedByCurrentTeam()
    {
        return RedirectRule::where('team_id', currentTeam()->id)->orderBy('created_at', 'desc');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
