<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class DatabaseMetric extends Model
{
    protected $fillable = [
        'database_uuid',
        'database_type',
        'cpu_percent',
        'memory_bytes',
        'memory_limit_bytes',
        'network_rx_bytes',
        'network_tx_bytes',
        'metrics',
        'recorded_at',
    ];

    protected $casts = [
        'cpu_percent' => 'float',
        'memory_bytes' => 'integer',
        'memory_limit_bytes' => 'integer',
        'network_rx_bytes' => 'integer',
        'network_tx_bytes' => 'integer',
        'metrics' => 'array',
        'recorded_at' => 'datetime',
    ];

    /**
     * Scope to filter by database UUID.
     */
    public function scopeForDatabase(Builder $query, string $uuid): Builder
    {
        return $query->where('database_uuid', $uuid);
    }

    /**
     * Scope to filter by time range.
     */
    public function scopeInTimeRange(Builder $query, string $range): Builder
    {
        $from = match ($range) {
            '1h' => Carbon::now()->subHour(),
            '6h' => Carbon::now()->subHours(6),
            '24h' => Carbon::now()->subDay(),
            '7d' => Carbon::now()->subWeek(),
            '30d' => Carbon::now()->subMonth(),
            default => Carbon::now()->subDay(),
        };

        return $query->where('recorded_at', '>=', $from);
    }

    /**
     * Get aggregated metrics for chart display.
     * Groups data into appropriate intervals based on time range.
     */
    public static function getAggregatedMetrics(string $uuid, string $timeRange = '24h'): array
    {
        $interval = match ($timeRange) {
            '1h' => 'minute',    // 1-minute intervals for 1 hour (60 points max)
            '6h' => '5 minutes', // 5-minute intervals for 6 hours (72 points max)
            '24h' => '15 minutes', // 15-minute intervals for 24 hours (96 points max)
            '7d' => '1 hour',    // 1-hour intervals for 7 days (168 points max)
            '30d' => '6 hours',  // 6-hour intervals for 30 days (120 points max)
            default => '15 minutes',
        };

        $metrics = self::forDatabase($uuid)
            ->inTimeRange($timeRange)
            ->orderBy('recorded_at', 'asc')
            ->get();

        if ($metrics->isEmpty()) {
            return [
                'cpu' => ['data' => [], 'current' => 0, 'average' => 0, 'peak' => 0],
                'memory' => ['data' => [], 'current' => 0, 'total' => 0, 'percentage' => 0],
                'network' => ['data' => [], 'in' => 0, 'out' => 0],
                'connections' => ['data' => [], 'current' => 0, 'max' => 0, 'percentage' => 0],
                'queries' => ['data' => [], 'perSecond' => 0, 'total' => 0, 'slow' => 0],
                'storage' => ['data' => [], 'used' => 0, 'total' => 0, 'percentage' => 0],
            ];
        }

        // Group metrics by interval
        $grouped = self::groupByInterval($metrics, $interval);

        // Get the latest record for current values
        $latest = $metrics->last();
        $latestMetrics = $latest->metrics ?? [];

        // Calculate aggregated data
        return [
            'cpu' => self::aggregateCpuMetrics($grouped, $latest),
            'memory' => self::aggregateMemoryMetrics($grouped, $latest),
            'network' => self::aggregateNetworkMetrics($grouped, $latest),
            'connections' => self::aggregateConnectionMetrics($grouped, $latestMetrics),
            'queries' => self::aggregateQueryMetrics($grouped, $latestMetrics),
            'storage' => self::aggregateStorageMetrics($latestMetrics),
        ];
    }

    /**
     * Group metrics by time interval.
     */
    protected static function groupByInterval(Collection $metrics, string $interval): Collection
    {
        $format = match ($interval) {
            'minute' => 'Y-m-d H:i',
            '5 minutes' => fn ($date) => $date->format('Y-m-d H:').str_pad((int) floor($date->format('i') / 5) * 5, 2, '0', STR_PAD_LEFT),
            '15 minutes' => fn ($date) => $date->format('Y-m-d H:').str_pad((int) floor($date->format('i') / 15) * 15, 2, '0', STR_PAD_LEFT),
            '1 hour' => 'Y-m-d H:00',
            '6 hours' => fn ($date) => $date->format('Y-m-d ').str_pad((int) floor($date->format('H') / 6) * 6, 2, '0', STR_PAD_LEFT).':00',
            default => 'Y-m-d H:i',
        };

        return $metrics->groupBy(function ($metric) use ($format) {
            $date = $metric->recorded_at;
            if (is_callable($format)) {
                return $format($date);
            }

            return $date->format($format);
        });
    }

    /**
     * Aggregate CPU metrics.
     */
    protected static function aggregateCpuMetrics(Collection $grouped, DatabaseMetric $latest): array
    {
        $data = $grouped->map(function ($group, $key) {
            return [
                'timestamp' => $key,
                'value' => round($group->avg('cpu_percent') ?? 0, 2),
            ];
        })->values()->all();

        $allCpu = $grouped->flatten()->pluck('cpu_percent')->filter();

        return [
            'data' => $data,
            'current' => round($latest->cpu_percent ?? 0, 2),
            'average' => round($allCpu->avg() ?? 0, 2),
            'peak' => round($allCpu->max() ?? 0, 2),
        ];
    }

    /**
     * Aggregate memory metrics.
     */
    protected static function aggregateMemoryMetrics(Collection $grouped, DatabaseMetric $latest): array
    {
        $data = $grouped->map(function ($group, $key) {
            $avgBytes = $group->avg('memory_bytes') ?? 0;

            return [
                'timestamp' => $key,
                'value' => round($avgBytes / (1024 * 1024 * 1024), 2), // Convert to GB
            ];
        })->values()->all();

        $currentBytes = $latest->memory_bytes ?? 0;
        $limitBytes = $latest->memory_limit_bytes ?? 0;

        $currentGb = round($currentBytes / (1024 * 1024 * 1024), 2);
        $totalGb = $limitBytes > 0 ? round($limitBytes / (1024 * 1024 * 1024), 2) : 4; // Default 4GB
        $percentage = $totalGb > 0 ? round(($currentGb / $totalGb) * 100, 1) : 0;

        return [
            'data' => $data,
            'current' => $currentGb,
            'total' => $totalGb,
            'percentage' => $percentage,
        ];
    }

    /**
     * Aggregate network metrics.
     */
    protected static function aggregateNetworkMetrics(Collection $grouped, DatabaseMetric $latest): array
    {
        $data = $grouped->map(function ($group, $key) {
            $rxBytes = $group->avg('network_rx_bytes') ?? 0;
            $txBytes = $group->avg('network_tx_bytes') ?? 0;

            return [
                'timestamp' => $key,
                'value' => round(($rxBytes + $txBytes) / (1024 * 1024), 2), // MB/s
            ];
        })->values()->all();

        $rxMb = round(($latest->network_rx_bytes ?? 0) / (1024 * 1024), 2);
        $txMb = round(($latest->network_tx_bytes ?? 0) / (1024 * 1024), 2);

        return [
            'data' => $data,
            'in' => $rxMb,
            'out' => $txMb,
        ];
    }

    /**
     * Aggregate connection metrics from DB-specific data.
     */
    protected static function aggregateConnectionMetrics(Collection $grouped, array $latestMetrics): array
    {
        $data = $grouped->map(function ($group, $key) {
            $connections = $group->map(fn ($m) => $m->metrics['activeConnections'] ?? 0)->avg();

            return [
                'timestamp' => $key,
                'value' => round($connections),
            ];
        })->values()->all();

        $current = $latestMetrics['activeConnections'] ?? 0;
        $max = $latestMetrics['maxConnections'] ?? 100;
        $percentage = $max > 0 ? round(($current / $max) * 100, 1) : 0;

        return [
            'data' => $data,
            'current' => $current,
            'max' => $max,
            'percentage' => $percentage,
        ];
    }

    /**
     * Aggregate query metrics from DB-specific data.
     */
    protected static function aggregateQueryMetrics(Collection $grouped, array $latestMetrics): array
    {
        $data = $grouped->map(function ($group, $key) {
            $qps = $group->map(fn ($m) => $m->metrics['queriesPerSec'] ?? $m->metrics['opsPerSec'] ?? 0)->avg();

            return [
                'timestamp' => $key,
                'value' => round($qps),
            ];
        })->values()->all();

        return [
            'data' => $data,
            'perSecond' => $latestMetrics['queriesPerSec'] ?? $latestMetrics['opsPerSec'] ?? 0,
            'total' => $latestMetrics['totalQueries'] ?? 0,
            'slow' => $latestMetrics['slowQueries'] ?? 0,
        ];
    }

    /**
     * Aggregate storage metrics (no historical data, just current).
     */
    protected static function aggregateStorageMetrics(array $latestMetrics): array
    {
        $sizeStr = $latestMetrics['databaseSize'] ?? '0 MB';
        $usedGb = self::parseSizeToGb($sizeStr);

        // Estimate total based on server disk (default 50GB)
        $totalGb = 50;
        $percentage = round(($usedGb / $totalGb) * 100, 1);

        return [
            'data' => [], // Storage doesn't have historical trend
            'used' => $usedGb,
            'total' => $totalGb,
            'percentage' => $percentage,
        ];
    }

    /**
     * Parse size string to GB.
     */
    protected static function parseSizeToGb(string $sizeStr): float
    {
        if (preg_match('/^([\d.]+)\s*(B|KB|MB|GB|TB)$/i', trim($sizeStr), $matches)) {
            $value = (float) $matches[1];
            $unit = strtoupper($matches[2]);

            return match ($unit) {
                'B' => $value / (1024 * 1024 * 1024),
                'KB' => $value / (1024 * 1024),
                'MB' => $value / 1024,
                'GB' => $value,
                'TB' => $value * 1024,
                default => 0,
            };
        }

        return 0;
    }

    /**
     * Clean up old metrics beyond retention period.
     */
    public static function cleanupOldMetrics(int $retentionDays = 30): int
    {
        return self::where('recorded_at', '<', Carbon::now()->subDays($retentionDays))->delete();
    }
}
