<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StatusPageDailySnapshot extends Model
{
    protected $fillable = [
        'resource_type',
        'resource_id',
        'snapshot_date',
        'status',
        'uptime_percent',
        'total_checks',
        'healthy_checks',
        'degraded_checks',
        'down_checks',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'uptime_percent' => 'float',
        'total_checks' => 'integer',
        'healthy_checks' => 'integer',
        'degraded_checks' => 'integer',
        'down_checks' => 'integer',
    ];

    /**
     * Scope to filter by resource type and id.
     */
    public function scopeForResource(Builder $query, string $type, int $id): Builder
    {
        return $query->where('resource_type', $type)->where('resource_id', $id);
    }

    /**
     * Scope to get snapshots for the last N days.
     */
    public function scopeLastDays(Builder $query, int $days = 90): Builder
    {
        return $query->where('snapshot_date', '>=', now()->subDays($days)->toDateString());
    }
}
