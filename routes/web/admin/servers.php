<?php

/**
 * Admin Servers routes
 *
 * Server management including listing, viewing, validation, docker cleanup,
 * deletion, health history, and tags management.
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/servers', function (Request $request) {
    // Fetch all servers across all teams (admin view)
    $query = \App\Models\Server::with(['team', 'settings']);

    // Search filter
    if ($search = $request->get('search')) {
        $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('ip', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }

    // Status filter
    if ($status = $request->get('status')) {
        $query->whereHas('settings', function ($q) use ($status) {
            if ($status === 'reachable') {
                $q->where('is_reachable', true)->where('is_usable', true);
            } elseif ($status === 'unreachable') {
                $q->where('is_reachable', false);
            } elseif ($status === 'degraded') {
                $q->where('is_reachable', true)->where('is_usable', false);
            }
        });
    }

    // Tag filter
    if ($tag = $request->get('tag')) {
        $query->whereJsonContains('tags', $tag);
    }

    $servers = $query->latest()
        ->paginate(50)
        ->through(function ($server) {
            return [
                'id' => $server->id,
                'uuid' => $server->uuid,
                'name' => $server->name,
                'description' => $server->description,
                'ip' => $server->ip,
                'port' => $server->port,
                'user' => $server->user,
                'is_reachable' => $server->settings?->is_reachable ?? false,
                'is_usable' => $server->settings?->is_usable ?? false,
                'tags' => $server->tags ?? [],
                'team_name' => $server->team?->name ?? 'Unknown',
                'team_id' => $server->team_id,
                'created_at' => $server->created_at,
                'updated_at' => $server->updated_at,
            ];
        });

    // Get all unique tags for filter dropdown
    $allTags = \App\Models\Server::whereNotNull('tags')
        ->get()
        ->pluck('tags')
        ->flatten()
        ->unique()
        ->values();

    return Inertia::render('Admin/Servers/Index', [
        'servers' => $servers,
        'allTags' => $allTags,
        'filters' => [
            'search' => $request->get('search'),
            'status' => $request->get('status'),
            'tag' => $request->get('tag'),
        ],
    ]);
})->name('admin.servers.index');

// Bulk server settings management
Route::get('/servers/bulk', function (Request $request) {
    $query = \App\Models\Server::with(['team', 'settings']);

    // Search filter (same as index)
    if ($search = $request->get('search')) {
        $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('ip', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }

    // Status filter
    if ($status = $request->get('status')) {
        $query->whereHas('settings', function ($q) use ($status) {
            if ($status === 'reachable') {
                $q->where('is_reachable', true)->where('is_usable', true);
            } elseif ($status === 'unreachable') {
                $q->where('is_reachable', false);
            } elseif ($status === 'degraded') {
                $q->where('is_reachable', true)->where('is_usable', false);
            }
        });
    }

    // Tag filter
    if ($tag = $request->get('tag')) {
        $query->whereJsonContains('tags', $tag);
    }

    $servers = $query->latest()
        ->paginate(100)
        ->through(function ($server) {
            return [
                'id' => $server->id,
                'uuid' => $server->uuid,
                'name' => $server->name,
                'ip' => $server->ip,
                'is_reachable' => $server->settings?->is_reachable ?? false,
                'is_usable' => $server->settings?->is_usable ?? false,
                'team_name' => $server->team?->name ?? 'Unknown',
                'settings' => [
                    'concurrent_builds' => $server->settings?->concurrent_builds ?? 2,
                    'deployment_queue_limit' => $server->settings?->deployment_queue_limit ?? 0,
                    'dynamic_timeout' => $server->settings?->dynamic_timeout ?? 3600,
                    'is_build_server' => $server->settings?->is_build_server ?? false,
                    'force_docker_cleanup' => (bool) ($server->settings?->force_docker_cleanup ?? false),
                    'docker_cleanup_frequency' => $server->settings?->getRawOriginal('docker_cleanup_frequency') ?? '0 0 * * *',
                    'docker_cleanup_threshold' => $server->settings?->docker_cleanup_threshold ?? 80,
                    'delete_unused_volumes' => (bool) ($server->settings?->delete_unused_volumes ?? false),
                    'delete_unused_networks' => (bool) ($server->settings?->delete_unused_networks ?? false),
                    'disable_application_image_retention' => (bool) ($server->settings?->disable_application_image_retention ?? false),
                    'is_metrics_enabled' => $server->settings?->is_metrics_enabled ?? false,
                    'is_sentinel_enabled' => $server->settings?->is_sentinel_enabled ?? false,
                    'is_terminal_enabled' => (bool) ($server->settings?->is_terminal_enabled ?? false),
                    'sentinel_metrics_history_days' => $server->settings?->sentinel_metrics_history_days ?? 7,
                    'sentinel_metrics_refresh_rate_seconds' => $server->settings?->sentinel_metrics_refresh_rate_seconds ?? 10,
                    'sentinel_push_interval_seconds' => $server->settings?->sentinel_push_interval_seconds ?? 60,
                ],
            ];
        });

    $allTags = \App\Models\Server::whereNotNull('tags')
        ->get()
        ->pluck('tags')
        ->flatten()
        ->unique()
        ->values();

    return Inertia::render('Admin/Servers/Bulk', [
        'servers' => $servers,
        'allTags' => $allTags,
        'filters' => [
            'search' => $request->get('search'),
            'status' => $request->get('status'),
            'tag' => $request->get('tag'),
        ],
    ]);
})->name('admin.servers.bulk');

Route::post('/servers/bulk', function (Request $request) {
    // Allowlisted settings fields
    $allowedFields = [
        'concurrent_builds',
        'deployment_queue_limit',
        'dynamic_timeout',
        'is_build_server',
        'force_docker_cleanup',
        'docker_cleanup_frequency',
        'docker_cleanup_threshold',
        'delete_unused_volumes',
        'delete_unused_networks',
        'disable_application_image_retention',
        'is_metrics_enabled',
        'is_sentinel_enabled',
        'is_terminal_enabled',
        'sentinel_metrics_history_days',
        'sentinel_metrics_refresh_rate_seconds',
        'sentinel_push_interval_seconds',
    ];

    $validated = $request->validate([
        'server_ids' => 'required|array|min:1',
        'server_ids.*' => 'integer|exists:servers,id',
        'settings' => 'required|array|min:1',
        'settings.concurrent_builds' => 'sometimes|integer|min:1|max:100',
        'settings.deployment_queue_limit' => 'sometimes|integer|min:0|max:1000',
        'settings.dynamic_timeout' => 'sometimes|integer|min:0|max:86400',
        'settings.is_build_server' => 'sometimes|boolean',
        'settings.force_docker_cleanup' => 'sometimes|boolean',
        'settings.docker_cleanup_frequency' => 'sometimes|string|max:100',
        'settings.docker_cleanup_threshold' => 'sometimes|integer|min:0|max:100',
        'settings.delete_unused_volumes' => 'sometimes|boolean',
        'settings.delete_unused_networks' => 'sometimes|boolean',
        'settings.disable_application_image_retention' => 'sometimes|boolean',
        'settings.is_metrics_enabled' => 'sometimes|boolean',
        'settings.is_sentinel_enabled' => 'sometimes|boolean',
        'settings.is_terminal_enabled' => 'sometimes|boolean',
        'settings.sentinel_metrics_history_days' => 'sometimes|integer|min:1|max:365',
        'settings.sentinel_metrics_refresh_rate_seconds' => 'sometimes|integer|min:5|max:3600',
        'settings.sentinel_push_interval_seconds' => 'sometimes|integer|min:5|max:3600',
    ]);

    // Filter to only allowed fields
    $settingsData = array_intersect_key(
        $validated['settings'],
        array_flip($allowedFields)
    );

    if (empty($settingsData)) {
        return back()->withErrors(['settings' => 'No valid settings provided.']);
    }

    $serverIds = $validated['server_ids'];
    $updatedCount = 0;

    \Illuminate\Support\Facades\DB::transaction(function () use ($serverIds, $settingsData, &$updatedCount) {
        $servers = \App\Models\Server::whereIn('id', $serverIds)->with('settings')->get();

        foreach ($servers as $server) {
            if ($server->settings) {
                $server->settings->update($settingsData);
                $updatedCount++;
            }
        }
    });

    \App\Models\AuditLog::log(
        'server_settings_bulk_updated',
        null,
        "Bulk updated settings for {$updatedCount} servers",
        [
            'server_ids' => $serverIds,
            'settings_changed' => array_keys($settingsData),
            'settings_values' => $settingsData,
        ]
    );

    return back()->with('success', "Settings updated for {$updatedCount} servers.");
})->name('admin.servers.bulk.update');

Route::get('/servers/{uuid}', function (string $uuid) {
    // Fetch specific server with all resources
    $server = \App\Models\Server::with(['team', 'settings'])
        ->where('uuid', $uuid)
        ->firstOrFail();

    // Get metrics if available
    $metrics = null;
    if ($server->settings?->is_metrics_enabled) {
        try {
            $diskUsage = $server->getDiskUsage();
            $metrics = [
                'cpu_usage' => null,
                'memory_usage' => null,
                'disk_usage' => $diskUsage ? (float) $diskUsage : null,
            ];
        } catch (\Exception $e) {
            // Metrics unavailable
        }
    }

    // Get applications on this server
    $applications = \App\Models\Application::whereHas('destination', function ($query) use ($server) {
        $query->where('server_id', $server->id);
    })->get()->map(function ($app) {
        return [
            'id' => $app->id,
            'uuid' => $app->uuid,
            'name' => $app->name,
            'status' => $app->status ?? 'unknown',
        ];
    });

    // Get databases on this server (all 8 types)
    $databases = collect();
    $dbTypes = [
        \App\Models\StandalonePostgresql::class => 'PostgreSQL',
        \App\Models\StandaloneMysql::class => 'MySQL',
        \App\Models\StandaloneMariadb::class => 'MariaDB',
        \App\Models\StandaloneMongodb::class => 'MongoDB',
        \App\Models\StandaloneRedis::class => 'Redis',
        \App\Models\StandaloneKeydb::class => 'KeyDB',
        \App\Models\StandaloneDragonfly::class => 'Dragonfly',
        \App\Models\StandaloneClickhouse::class => 'ClickHouse',
    ];

    foreach ($dbTypes as $model => $typeName) {
        $dbs = $model::whereHas('destination', function ($query) use ($server) {
            $query->where('server_id', $server->id);
        })->get()->map(function ($db) use ($typeName) {
            return [
                'id' => $db->id,
                'uuid' => $db->uuid,
                'name' => $db->name,
                'type' => $typeName,
                'status' => $db->status ?? 'unknown',
            ];
        });
        $databases = $databases->concat($dbs);
    }

    // Get services on this server
    $services = \App\Models\Service::where('server_id', $server->id)
        ->get()
        ->map(function ($service) {
            return [
                'id' => $service->id,
                'uuid' => $service->uuid,
                'name' => $service->name,
                'status' => 'running',
            ];
        });

    return Inertia::render('Admin/Servers/Show', [
        'server' => [
            'id' => $server->id,
            'uuid' => $server->uuid,
            'name' => $server->name,
            'description' => $server->description,
            'ip' => $server->ip,
            'port' => $server->port,
            'user' => $server->user,
            'is_reachable' => $server->settings?->is_reachable ?? false,
            'is_usable' => $server->settings?->is_usable ?? false,
            'is_build_server' => $server->settings?->is_build_server ?? false,
            'is_localhost' => $server->is_localhost,
            'team_id' => $server->team_id,
            'team_name' => $server->team?->name ?? 'Unknown',
            'tags' => $server->tags ?? [],
            'settings' => [
                'is_reachable' => $server->settings?->is_reachable ?? false,
                'is_usable' => $server->settings?->is_usable ?? false,
                'concurrent_builds' => $server->settings?->concurrent_builds ?? 2,
                'is_metrics_enabled' => $server->settings?->is_metrics_enabled ?? false,
                'docker_version' => $server->settings?->docker_version,
                'docker_compose_version' => $server->settings?->docker_compose_version,
            ],
            'metrics' => $metrics,
            'resources' => [
                'applications' => $applications,
                'databases' => $databases,
                'services' => $services,
            ],
            'created_at' => $server->created_at,
            'updated_at' => $server->updated_at,
        ],
    ]);
})->name('admin.servers.show');

Route::post('/servers/{uuid}/validate', function (string $uuid) {
    $server = \App\Models\Server::where('uuid', $uuid)->firstOrFail();

    try {
        $result = $server->validateConnection();

        if ($result['uptime']) {
            return back()->with('success', 'Server connection validated successfully');
        } else {
            return back()->with('error', 'Server validation failed: '.($result['error'] ?? 'Unknown error'));
        }
    } catch (\Exception $e) {
        return back()->with('error', 'Server validation failed: '.$e->getMessage());
    }
})->name('admin.servers.validate');

Route::post('/servers/{uuid}/docker-cleanup', function (string $uuid) {
    $server = \App\Models\Server::where('uuid', $uuid)->firstOrFail();

    try {
        instant_remote_process(['docker system prune -af'], $server);

        return back()->with('success', 'Docker cleanup completed successfully');
    } catch (\Exception $e) {
        return back()->with('error', 'Docker cleanup failed: '.$e->getMessage());
    }
})->name('admin.servers.docker-cleanup');

Route::delete('/servers/{uuid}', function (string $uuid) {
    $server = \App\Models\Server::where('uuid', $uuid)->firstOrFail();
    $serverName = $server->name;

    // Check if it's localhost - prevent deletion
    if ($server->is_localhost) {
        return back()->with('error', 'Cannot delete localhost server');
    }

    $server->delete();

    return redirect()->route('admin.servers.index')->with('success', "Server '{$serverName}' deleted");
})->name('admin.servers.delete');

// Server health history API
Route::get('/servers/{uuid}/health-history', function (string $uuid, Request $request) {
    $server = \App\Models\Server::where('uuid', $uuid)->firstOrFail();

    $period = $request->get('period', '24h');
    $limit = match ($period) {
        '1h' => 60,
        '6h' => 72,
        '24h' => 288,
        '7d' => 336,
        '30d' => 720,
        default => 288,
    };

    $history = \App\Models\ServerHealthCheck::where('server_id', $server->id)
        ->orderByDesc('checked_at')
        ->limit($limit)
        ->get()
        ->map(function ($check) {
            return [
                'status' => $check->status,
                'cpu_usage' => $check->cpu_usage_percent,
                'memory_usage' => $check->memory_usage_percent,
                'disk_usage' => $check->disk_usage_percent,
                'response_time_ms' => $check->response_time_ms,
                'checked_at' => $check->checked_at?->toISOString(),
            ];
        })
        ->reverse()
        ->values();

    return response()->json([
        'server_id' => $server->id,
        'server_name' => $server->name,
        'period' => $period,
        'data' => $history,
    ]);
})->name('admin.servers.health-history');

// Server tags management
Route::put('/servers/{uuid}/tags', function (string $uuid, Request $request) {
    $server = \App\Models\Server::where('uuid', $uuid)->firstOrFail();

    $validated = $request->validate([
        'tags' => 'nullable|array',
        'tags.*' => 'string|max:50',
    ]);

    $server->update([
        'tags' => array_values(array_unique($validated['tags'] ?? [])),
    ]);

    \App\Models\AuditLog::log(
        action: 'server_tags_updated',
        resourceType: 'server',
        resourceId: $server->id,
        resourceName: $server->name,
        description: 'Updated server tags',
        metadata: ['tags' => $server->tags]
    );

    return back()->with('success', 'Server tags updated');
})->name('admin.servers.update-tags');
