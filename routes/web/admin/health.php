<?php

/**
 * Admin Health routes
 *
 * System health dashboard with service status, server health, and queue statistics.
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/health', function () {
    // Core services health checks
    $services = [];

    // PostgreSQL check
    try {
        $startTime = microtime(true);
        DB::connection()->getPdo();
        $responseTime = round((microtime(true) - $startTime) * 1000, 2);
        $services[] = [
            'service' => 'PostgreSQL',
            'status' => 'healthy',
            'lastCheck' => now()->toISOString(),
            'responseTime' => $responseTime,
        ];
    } catch (\Exception $e) {
        $services[] = [
            'service' => 'PostgreSQL',
            'status' => 'down',
            'lastCheck' => now()->toISOString(),
            'details' => $e->getMessage(),
        ];
    }

    // Redis check
    try {
        $startTime = microtime(true);
        Redis::ping();
        $responseTime = round((microtime(true) - $startTime) * 1000, 2);
        $services[] = [
            'service' => 'Redis',
            'status' => 'healthy',
            'lastCheck' => now()->toISOString(),
            'responseTime' => $responseTime,
        ];
    } catch (\Exception $e) {
        $services[] = [
            'service' => 'Redis',
            'status' => 'down',
            'lastCheck' => now()->toISOString(),
            'details' => $e->getMessage(),
        ];
    }

    // Queue worker check (simplified - check if jobs are processing)
    // Note: jobs table only exists when using database queue driver
    $healthPendingJobs = 0;
    $healthFailedJobs = 0;
    try {
        $healthPendingJobs = DB::table('jobs')->count();
    } catch (\Exception $e) {
        // Table doesn't exist - using Redis queue driver
    }
    try {
        $healthFailedJobs = DB::table('failed_jobs')->count();
    } catch (\Exception $e) {
        // Table doesn't exist
    }
    $services[] = [
        'service' => 'Queue Worker',
        'status' => $healthFailedJobs > 10 ? 'degraded' : 'healthy',
        'lastCheck' => now()->toISOString(),
        'details' => "{$healthPendingJobs} pending, {$healthFailedJobs} failed",
    ];

    // Servers health
    $servers = \App\Models\Server::with(['settings'])
        ->get()
        ->map(function ($server) {
            $metrics = null;
            if ($server->settings?->is_metrics_enabled && $server->isFunctional()) {
                try {
                    $diskUsage = $server->getDiskUsage();
                    $cpuUsage = null;
                    $memoryUsage = null;

                    // Try to get CPU and Memory metrics from Sentinel
                    if ($server->isServerApiEnabled()) {
                        try {
                            $cpuData = $server->getCpuMetrics(5);
                            if ($cpuData && $cpuData->isNotEmpty()) {
                                $cpuUsage = (float) ($cpuData->last()[1] ?? 0);
                            }
                        } catch (\Exception $e) {
                            // CPU metrics unavailable
                        }

                        try {
                            $memoryData = $server->getMemoryMetrics(5);
                            if ($memoryData && count($memoryData) > 0) {
                                $lastMemory = end($memoryData);
                                $memoryUsage = (float) ($lastMemory[1] ?? 0);
                            }
                        } catch (\Exception $e) {
                            // Memory metrics unavailable
                        }
                    }

                    $metrics = [
                        'cpu_usage' => $cpuUsage,
                        'memory_usage' => $memoryUsage,
                        'disk_usage' => $diskUsage ? (float) $diskUsage : null,
                    ];
                } catch (\Exception $e) {
                    // Metrics unavailable
                }
            }

            // Count resources on server
            $resourcesCount = 0;
            $resourcesCount += \App\Models\Application::whereHas('destination', function ($query) use ($server) {
                $query->where('server_id', $server->id);
            })->count();
            $resourcesCount += \App\Models\Service::where('server_id', $server->id)->count();

            return [
                'id' => $server->id,
                'uuid' => $server->uuid,
                'name' => $server->name,
                'ip' => $server->ip,
                'is_reachable' => $server->settings?->is_reachable ?? false,
                'is_usable' => $server->settings?->is_usable ?? false,
                'metrics' => $metrics,
                'resources_count' => $resourcesCount,
                'last_check' => now()->toISOString(),
            ];
        });

    // Queue statistics
    // Note: jobs table only exists when using database queue driver
    $queuePending = 0;
    $queueFailed = 0;
    try {
        $queuePending = DB::table('jobs')->count();
    } catch (\Exception $e) {
        // Table doesn't exist - using Redis queue driver
    }
    try {
        $queueFailed = DB::table('failed_jobs')->count();
    } catch (\Exception $e) {
        // Table doesn't exist
    }
    $queues = [
        'pending' => $queuePending,
        'processing' => 0,
        'failed' => $queueFailed,
        'workers' => 1, // Simplified - would need Horizon for accurate count
    ];

    return Inertia::render('Admin/Health/Index', [
        'services' => $services,
        'servers' => $servers,
        'queues' => $queues,
        'lastUpdated' => now()->toISOString(),
    ]);
})->name('admin.health.index');
