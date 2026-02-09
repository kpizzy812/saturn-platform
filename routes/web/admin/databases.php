<?php

/**
 * Admin Databases routes
 *
 * Database management including listing, viewing, restart, stop, start, and deletion.
 * Supports all 8 database types: PostgreSQL, MySQL, MariaDB, MongoDB, Redis, KeyDB, Dragonfly, ClickHouse.
 */

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/**
 * Database model classes mapping for admin routes
 */
if (! defined('ADMIN_DATABASE_MODELS')) {
    define('ADMIN_DATABASE_MODELS', [
        'postgresql' => \App\Models\StandalonePostgresql::class,
        'mysql' => \App\Models\StandaloneMysql::class,
        'mariadb' => \App\Models\StandaloneMariadb::class,
        'mongodb' => \App\Models\StandaloneMongodb::class,
        'redis' => \App\Models\StandaloneRedis::class,
        'keydb' => \App\Models\StandaloneKeydb::class,
        'dragonfly' => \App\Models\StandaloneDragonfly::class,
        'clickhouse' => \App\Models\StandaloneClickhouse::class,
    ]);
}

/**
 * Find a database by UUID across all types (admin version - no team restriction)
 */
if (! function_exists('adminFindDatabaseByUuid')) {
    function adminFindDatabaseByUuid(string $uuid): ?object
    {
        foreach (ADMIN_DATABASE_MODELS as $model) {
            $db = $model::where('uuid', $uuid)->first();
            if ($db) {
                return $db;
            }
        }

        return null;
    }
}

Route::get('/databases', function (\Illuminate\Http\Request $request) {
    $search = $request->get('search');
    $status = $request->get('status');
    $type = $request->get('type');
    $page = max(1, (int) $request->get('page', 1));
    $perPage = 50;

    $databases = collect();
    $dbModels = ADMIN_DATABASE_MODELS;
    $typeCounts = [];
    $statusCounts = ['running' => 0, 'stopped' => 0, 'error' => 0];

    // If type filter set, only query that model
    $modelsToQuery = $type && $type !== 'all' && isset($dbModels[$type])
        ? [$type => $dbModels[$type]]
        : $dbModels;

    foreach ($dbModels as $dbType => $model) {
        $query = $model::with(['environment.project.team']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhereHas('environment.project.team', function ($tq) use ($search) {
                        $tq->where('name', 'ilike', "%{$search}%");
                    });
            });
        }

        // Count for type tabs (before type filter)
        $typeCounts[$dbType] = $query->count();

        // Count for stats
        $dbs = $query->get();
        foreach ($dbs as $db) {
            $dbStatus = $db->status();
            if (str_starts_with($dbStatus, 'running')) {
                $statusCounts['running']++;
            } elseif (str_starts_with($dbStatus, 'stopped') || str_starts_with($dbStatus, 'exited')) {
                $statusCounts['stopped']++;
            } elseif (str_starts_with($dbStatus, 'error') || str_starts_with($dbStatus, 'degraded')) {
                $statusCounts['error']++;
            }
        }

        // Only include in results if matches type filter
        if (! isset($modelsToQuery[$dbType])) {
            continue;
        }

        $mapped = $dbs->map(function ($db) use ($dbType) {
            return [
                'id' => $db->id,
                'uuid' => $db->uuid,
                'name' => $db->name,
                'description' => $db->description,
                'database_type' => $dbType,
                'status' => $db->status(),
                'team_name' => $db->environment?->project?->team?->name ?? 'Unknown',
                'team_id' => $db->environment?->project?->team?->id,
                'project_name' => $db->environment?->project?->name,
                'environment_name' => $db->environment?->name,
                'created_at' => $db->created_at,
                'updated_at' => $db->updated_at,
            ];
        });

        // Apply status filter
        if ($status && $status !== 'all') {
            $mapped = $mapped->filter(function ($db) use ($status) {
                return str_starts_with($db['status'], $status);
            });
        }

        $databases = $databases->concat($mapped);
    }

    $sorted = $databases->sortByDesc('updated_at')->values();
    $total = $sorted->count();
    $lastPage = max(1, (int) ceil($total / $perPage));
    $paginatedItems = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

    return Inertia::render('Admin/Databases/Index', [
        'databases' => [
            'data' => $paginatedItems,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $lastPage,
        ],
        'stats' => [
            'runningCount' => $statusCounts['running'],
            'stoppedCount' => $statusCounts['stopped'],
            'errorCount' => $statusCounts['error'],
        ],
        'typeCounts' => $typeCounts,
        'filters' => [
            'search' => $search,
            'status' => $status ?? 'all',
            'type' => $type ?? 'all',
        ],
    ]);
})->name('admin.databases.index');

Route::get('/databases/{uuid}', function (string $uuid) {
    // Try to find database by UUID across all database types
    $database = null;
    $databaseType = null;
    $dbModels = ADMIN_DATABASE_MODELS;

    foreach ($dbModels as $type => $model) {
        $db = $model::with(['environment.project.team', 'destination.server', 'scheduledBackups'])
            ->where('uuid', $uuid)
            ->first();
        if ($db) {
            $database = $db;
            $databaseType = $type;
            break;
        }
    }

    if (! $database) {
        abort(404, 'Database not found');
    }

    $server = $database->destination?->server;

    // Get backup schedules
    $backups = $database->scheduledBackups?->map(function ($backup) {
        $lastExecution = $backup->executions()->latest()->first();

        return [
            'id' => $backup->id,
            'uuid' => $backup->uuid,
            'frequency' => $backup->frequency,
            'enabled' => $backup->enabled,
            'last_execution_status' => $lastExecution?->status,
            'last_execution_at' => $lastExecution?->created_at,
        ];
    }) ?? collect();

    return Inertia::render('Admin/Databases/Show', [
        'database' => [
            'id' => $database->id,
            'uuid' => $database->uuid,
            'name' => $database->name,
            'description' => $database->description,
            'database_type' => ucfirst($databaseType),
            'status' => $database->status(),
            'internal_db_url' => $database->internal_db_url ?? null,
            'public_port' => $database->public_port ?? null,
            'is_public' => $database->is_public ?? false,
            'team_id' => $database->environment?->project?->team?->id,
            'team_name' => $database->environment?->project?->team?->name ?? 'Unknown',
            'project_id' => $database->environment?->project?->id,
            'project_name' => $database->environment?->project?->name ?? 'Unknown',
            'environment_id' => $database->environment?->id,
            'environment_name' => $database->environment?->name ?? 'Unknown',
            'server_id' => $server?->id,
            'server_name' => $server?->name,
            'server_uuid' => $server?->uuid,
            'image' => $database->image ?? null,
            'limits_memory' => $database->limits_memory ?? null,
            'limits_cpus' => $database->limits_cpus ?? null,
            'backups' => $backups,
            'created_at' => $database->created_at,
            'updated_at' => $database->updated_at,
        ],
    ]);
})->name('admin.databases.show');

Route::post('/databases/{uuid}/restart', function (string $uuid) {
    $database = adminFindDatabaseByUuid($uuid);

    if (! $database) {
        return back()->with('error', 'Database not found');
    }

    try {
        \App\Actions\Database\RestartDatabase::run($database);

        return back()->with('success', 'Database restart initiated');
    } catch (\Exception $e) {
        return back()->with('error', 'Failed to restart database: '.$e->getMessage());
    }
})->name('admin.databases.restart');

Route::post('/databases/{uuid}/stop', function (string $uuid) {
    $database = adminFindDatabaseByUuid($uuid);

    if (! $database) {
        return back()->with('error', 'Database not found');
    }

    try {
        \App\Actions\Database\StopDatabase::run($database);

        return back()->with('success', 'Database stopped');
    } catch (\Exception $e) {
        return back()->with('error', 'Failed to stop database: '.$e->getMessage());
    }
})->name('admin.databases.stop');

Route::post('/databases/{uuid}/start', function (string $uuid) {
    $database = adminFindDatabaseByUuid($uuid);

    if (! $database) {
        return back()->with('error', 'Database not found');
    }

    try {
        \App\Actions\Database\StartDatabase::run($database);

        return back()->with('success', 'Database started');
    } catch (\Exception $e) {
        return back()->with('error', 'Failed to start database: '.$e->getMessage());
    }
})->name('admin.databases.start');

Route::delete('/databases/{uuid}', function (string $uuid) {
    $database = adminFindDatabaseByUuid($uuid);

    if (! $database) {
        return back()->with('error', 'Database not found');
    }

    $dbName = $database->name;
    $database->delete();

    return redirect()->route('admin.databases.index')->with('success', "Database '{$dbName}' deleted");
})->name('admin.databases.delete');
