<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerHealthCheck extends Model
{
    protected $fillable = [
        'server_id',
        'status',
        'is_reachable',
        'is_usable',
        'response_time_ms',
        'disk_usage_percent',
        'cpu_usage_percent',
        'memory_usage_percent',
        'memory_total_bytes',
        'error_message',
        'uptime_seconds',
        'docker_version',
        'container_counts',
        'checked_at',
    ];

    protected $casts = [
        'is_reachable' => 'boolean',
        'is_usable' => 'boolean',
        'response_time_ms' => 'integer',
        'disk_usage_percent' => 'float',
        'cpu_usage_percent' => 'float',
        'memory_usage_percent' => 'float',
        'memory_total_bytes' => 'integer',
        'uptime_seconds' => 'integer',
        'container_counts' => 'array',
        'checked_at' => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Determine overall health status based on checks
     */
    public static function determineStatus(
        bool $isReachable,
        bool $isUsable,
        ?float $diskUsage = null,
        ?float $cpuUsage = null,
        ?float $memoryUsage = null
    ): string {
        if (! $isReachable) {
            return 'unreachable';
        }

        if (! $isUsable) {
            return 'down';
        }

        // Check for degraded state (high resource usage)
        $isDegraded = false;
        if ($diskUsage !== null && $diskUsage > 90) {
            $isDegraded = true;
        }
        if ($cpuUsage !== null && $cpuUsage > 90) {
            $isDegraded = true;
        }
        if ($memoryUsage !== null && $memoryUsage > 90) {
            $isDegraded = true;
        }

        return $isDegraded ? 'degraded' : 'healthy';
    }
}
