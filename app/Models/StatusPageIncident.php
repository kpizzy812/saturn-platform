<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StatusPageIncident extends Model
{
    protected $fillable = [
        'title',
        'severity',
        'status',
        'started_at',
        'resolved_at',
        'is_visible',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'resolved_at' => 'datetime',
        'is_visible' => 'boolean',
    ];

    /** @return HasMany<StatusPageIncidentUpdate, $this> */
    public function updates(): HasMany
    {
        return $this->hasMany(StatusPageIncidentUpdate::class, 'incident_id')->orderByDesc('posted_at');
    }

    /**
     * Active (unresolved) incidents.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_visible', true)
            ->whereNull('resolved_at')
            ->orderByDesc('started_at');
    }

    /**
     * Recently resolved incidents (within N days).
     */
    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('is_visible', true)
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', now()->subDays($days))
            ->orderByDesc('resolved_at');
    }
}
