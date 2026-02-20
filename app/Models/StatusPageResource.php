<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StatusPageResource extends Model
{
    protected $fillable = [
        'team_id',
        'resource_type',
        'resource_id',
        'display_name',
        'display_order',
        'is_visible',
        'group_name',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
        'display_order' => 'integer',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function resource(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Resolve the current status of the linked resource.
     * Returns only safe public information (no IPs, server names, etc.).
     */
    public function resolveStatus(): string
    {
        $resource = $this->resource;
        if (! $resource) {
            return 'unknown';
        }

        $status = $resource->status ?? 'unknown';

        return self::normalizeStatus($status);
    }

    /**
     * Normalize internal status to public-facing status.
     */
    public static function normalizeStatus(string $status): string
    {
        return match ($status) {
            'running', 'healthy' => 'operational',
            'degraded' => 'degraded',
            'exited', 'stopped', 'down', 'unreachable', 'failed' => 'major_outage',
            'restarting', 'in_progress' => 'maintenance',
            default => 'unknown',
        };
    }

    /**
     * Compute overall status from an array of individual statuses.
     */
    public static function computeOverallStatus(array $statuses): string
    {
        if (empty($statuses)) {
            return 'unknown';
        }

        $hasMajor = in_array('major_outage', $statuses, true);
        $hasDegraded = in_array('degraded', $statuses, true);
        $hasMaintenance = in_array('maintenance', $statuses, true);

        if ($hasMajor) {
            return 'major_outage';
        }

        if ($hasDegraded) {
            return 'partial_outage';
        }

        if ($hasMaintenance) {
            return 'maintenance';
        }

        return 'operational';
    }
}
