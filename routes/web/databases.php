<?php

/**
 * Database routes for Saturn Platform
 *
 * These routes handle database management (PostgreSQL, MySQL, MongoDB, Redis, etc.).
 * All routes require authentication and email verification.
 */

use App\Actions\Database\RestartDatabase;
use App\Models\ScheduledDatabaseBackup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Databases
Route::get('/databases', function () {
    // Collect all database types
    $formatDb = fn ($db, $type) => [
        'id' => $db->id,
        'uuid' => $db->uuid,
        'name' => $db->name,
        'description' => $db->description,
        'database_type' => $type,
        'status' => $db->status,
        'environment_id' => $db->environment_id,
        'created_at' => $db->created_at,
        'updated_at' => $db->updated_at,
    ];

    $databases = collect()
        ->concat(\App\Models\StandalonePostgresql::ownedByCurrentTeam()->get()->map(fn ($db) => $formatDb($db, 'postgresql')))
        ->concat(\App\Models\StandaloneMysql::ownedByCurrentTeam()->get()->map(fn ($db) => $formatDb($db, 'mysql')))
        ->concat(\App\Models\StandaloneMariadb::ownedByCurrentTeam()->get()->map(fn ($db) => $formatDb($db, 'mariadb')))
        ->concat(\App\Models\StandaloneMongodb::ownedByCurrentTeam()->get()->map(fn ($db) => $formatDb($db, 'mongodb')))
        ->concat(\App\Models\StandaloneRedis::ownedByCurrentTeam()->get()->map(fn ($db) => $formatDb($db, 'redis')))
        ->concat(\App\Models\StandaloneKeydb::ownedByCurrentTeam()->get()->map(fn ($db) => $formatDb($db, 'keydb')))
        ->concat(\App\Models\StandaloneDragonfly::ownedByCurrentTeam()->get()->map(fn ($db) => $formatDb($db, 'dragonfly')))
        ->concat(\App\Models\StandaloneClickhouse::ownedByCurrentTeam()->get()->map(fn ($db) => $formatDb($db, 'clickhouse')))
        ->sortByDesc('updated_at')
        ->values();

    return Inertia::render('Databases/Index', [
        'databases' => $databases,
    ]);
})->name('databases.index');

Route::get('/databases/create', function () {
    // Get projects with environments for database creation
    $projects = \App\Models\Project::ownedByCurrentTeam()
        ->with('environments')
        ->get()
        ->map(fn ($project) => [
            'uuid' => $project->uuid,
            'name' => $project->name,
            'environments' => $project->environments->map(fn ($env) => [
                'uuid' => $env->uuid,
                'name' => $env->name,
            ]),
        ]);

    // Get available servers
    $servers = \App\Models\Server::ownedByCurrentTeam()
        ->with('settings')
        ->get()
        ->map(fn ($server) => [
            'uuid' => $server->uuid,
            'name' => $server->name,
            'is_reachable' => $server->settings?->is_reachable ?? false,
        ]);

    return Inertia::render('Databases/Create', [
        'projects' => $projects,
        'servers' => $servers,
    ]);
})->name('databases.create');

Route::post('/databases', function (Request $request) {
    $request->validate([
        'name' => 'required|string|max:255',
        'database_type' => 'required|string|in:postgresql,mysql,mariadb,mongodb,redis,keydb,dragonfly,clickhouse',
        'version' => 'required|string',
        'description' => 'nullable|string',
        'environment_uuid' => 'nullable|string',
        'server_uuid' => 'nullable|string',
    ]);

    // Get environment - either from request or use default (first project's production env)
    $environment = null;
    if ($request->environment_uuid) {
        $environment = \App\Models\Environment::whereHas('project', function ($query) {
            $query->where('team_id', currentTeam()->id);
        })->where('uuid', $request->environment_uuid)->first();
    }

    if (! $environment) {
        // Use default: first project's production environment or first environment
        $project = \App\Models\Project::ownedByCurrentTeam()->first();
        if (! $project) {
            // Create a default project if none exists
            $project = \App\Models\Project::create([
                'name' => 'Default Project',
                'team_id' => currentTeam()->id,
            ]);
        }
        $environment = $project->environments()->where('name', 'production')->first()
            ?? $project->environments()->first();
        if (! $environment) {
            $environment = $project->environments()->create(['name' => 'production']);
        }
    }

    // Get destination - either from request or use default server's destination
    $destination = null;
    if ($request->server_uuid) {
        $server = \App\Models\Server::ownedByCurrentTeam()->where('uuid', $request->server_uuid)->first();
        if ($server) {
            $destination = $server->destinations()->first();
        }
    }

    if (! $destination) {
        // Use default: first functional server's destination
        $server = \App\Models\Server::ownedByCurrentTeam()->whereRelation('settings', 'is_reachable', true)->first()
            ?? \App\Models\Server::ownedByCurrentTeam()->first();
        if (! $server) {
            return redirect()->back()->withErrors(['server' => 'No server available. Please add a server first.']);
        }
        $destination = $server->destinations()->first();
        if (! $destination) {
            return redirect()->back()->withErrors(['destination' => 'Server has no destination configured.']);
        }
    }

    // Build extra data for database creation
    $otherData = array_filter([
        'name' => $request->name,
        'description' => $request->description,
    ]);

    // Create the appropriate database type using helper functions
    $database = match ($request->database_type) {
        'postgresql' => create_standalone_postgresql($environment->id, $destination->uuid, $otherData),
        'mysql' => create_standalone_mysql($environment->id, $destination->uuid, $otherData),
        'mariadb' => create_standalone_mariadb($environment->id, $destination->uuid, $otherData),
        'mongodb' => create_standalone_mongodb($environment->id, $destination->uuid, $otherData),
        'redis' => create_standalone_redis($environment->id, $destination->uuid, $otherData),
        'keydb' => create_standalone_keydb($environment->id, $destination->uuid, $otherData),
        'dragonfly' => create_standalone_dragonfly($environment->id, $destination->uuid, $otherData),
        'clickhouse' => create_standalone_clickhouse($environment->id, $destination->uuid, $otherData),
    };

    return redirect()->route('databases.show', $database->uuid);
})->name('databases.store');

Route::get('/databases/{uuid}', function (string $uuid) {
    [$database, $type] = findDatabaseByUuid($uuid);

    // Fetch scheduled backups with latest execution
    $scheduledBackups = $database->scheduledBackups()
        ->with(['latest_log', 's3'])
        ->get()
        ->map(function ($backup) {
            return [
                'id' => $backup->id,
                'uuid' => $backup->uuid,
                'enabled' => $backup->enabled,
                'frequency' => $backup->frequency,
                'save_s3' => $backup->save_s3,
                's3_storage_id' => $backup->s3_storage_id,
                'databases_to_backup' => $backup->databases_to_backup,
                'latest_log' => $backup->latest_log ? [
                    'id' => $backup->latest_log->id,
                    'status' => $backup->latest_log->status,
                    'message' => $backup->latest_log->message,
                    'size' => $backup->latest_log->size,
                    'created_at' => $backup->latest_log->created_at,
                ] : null,
            ];
        });

    return Inertia::render('Databases/Show', [
        'database' => formatDatabaseForView([$database, $type]),
        'scheduledBackups' => $scheduledBackups,
    ]);
})->name('databases.show');

Route::patch('/databases/{uuid}', function (string $uuid, Request $request) {
    [$database, $type] = findDatabaseByUuid($uuid);

    $validated = $request->validate([
        // General
        'name' => 'sometimes|string|max:255',
        'description' => 'sometimes|nullable|string',
        // Resources
        'limits_memory' => 'sometimes|string',
        'limits_memory_swap' => 'sometimes|string',
        'limits_memory_swappiness' => 'sometimes|numeric',
        'limits_memory_reservation' => 'sometimes|string',
        'limits_cpus' => 'sometimes|string',
        'limits_cpuset' => 'sometimes|nullable|string',
        'limits_cpu_shares' => 'sometimes|numeric',
        'is_public' => 'sometimes|boolean',
        'public_port' => 'sometimes|nullable|numeric',
        // Security
        'enable_ssl' => 'sometimes|boolean',
        // Configuration (stored as custom_docker_run_options or postgres_conf etc.)
        'postgres_conf' => 'sometimes|nullable|string',
        'custom_docker_run_options' => 'sometimes|nullable|string',
    ]);

    $database->update($validated);

    return redirect()->back()->with('success', 'Database updated successfully');
})->name('databases.update');

Route::delete('/databases/{uuid}', function (string $uuid) {
    [$database, $type] = findDatabaseByUuid($uuid);
    $database->delete();

    return redirect()->route('databases.index');
})->name('databases.destroy');

Route::get('/databases/{uuid}/backups', function (string $uuid) {
    [$database, $type] = findDatabaseByUuid($uuid);

    // Fetch scheduled backups with all executions
    $scheduledBackups = $database->scheduledBackups()
        ->with(['executions' => function ($query) {
            $query->orderBy('created_at', 'desc')->limit(50);
        }, 's3'])
        ->get()
        ->map(function ($backup) {
            return [
                'id' => $backup->id,
                'uuid' => $backup->uuid,
                'enabled' => $backup->enabled,
                'frequency' => $backup->frequency,
                'save_s3' => $backup->save_s3,
                's3_storage_id' => $backup->s3_storage_id,
                'databases_to_backup' => $backup->databases_to_backup,
                'executions' => $backup->executions->map(function ($execution) {
                    return [
                        'id' => $execution->id,
                        'status' => $execution->status,
                        'message' => $execution->message,
                        'size' => $execution->size,
                        'filename' => $execution->filename,
                        's3_uploaded' => $execution->s3_uploaded,
                        'created_at' => $execution->created_at,
                        'updated_at' => $execution->updated_at,
                    ];
                }),
            ];
        });

    return Inertia::render('Databases/Backups', [
        'database' => formatDatabaseForView([$database, $type]),
        'scheduledBackups' => $scheduledBackups,
    ]);
})->name('databases.backups');

Route::get('/databases/{uuid}/logs', function (string $uuid) {
    $database = findDatabaseByUuid($uuid);

    return Inertia::render('Databases/Logs', [
        'database' => formatDatabaseForView($database),
    ]);
})->name('databases.logs');

Route::get('/databases/{uuid}/metrics', function (string $uuid) {
    $database = findDatabaseByUuid($uuid);

    return Inertia::render('Databases/Metrics', [
        'database' => formatDatabaseForView($database),
    ]);
})->name('databases.metrics');

// API endpoint for real-time database metrics (JSON)
Route::get('/api/databases/{uuid}/metrics', [\App\Http\Controllers\Inertia\DatabaseMetricsController::class, 'getMetrics'])
    ->name('databases.metrics.api');

// API endpoint for historical database metrics (JSON)
Route::get('/api/databases/{uuid}/metrics/history', [\App\Http\Controllers\Inertia\DatabaseMetricsController::class, 'getHistoricalMetrics'])
    ->name('databases.metrics.history.api');

// API endpoint for database extensions (PostgreSQL)
Route::get('/api/databases/{uuid}/extensions', [\App\Http\Controllers\Inertia\DatabaseMetricsController::class, 'getExtensions'])
    ->name('databases.extensions.api');

// API endpoint for toggling database extensions (PostgreSQL)
Route::post('/api/databases/{uuid}/extensions/toggle', [\App\Http\Controllers\Inertia\DatabaseMetricsController::class, 'toggleExtension'])
    ->name('databases.extensions.toggle.api');

// API endpoint for database users
Route::get('/api/databases/{uuid}/users', [\App\Http\Controllers\Inertia\DatabaseMetricsController::class, 'getUsers'])
    ->name('databases.users.api');

// API endpoint for database logs
Route::get('/api/databases/{uuid}/logs', [\App\Http\Controllers\Inertia\DatabaseMetricsController::class, 'getLogs'])
    ->name('databases.logs.api');

// API endpoint for executing SQL queries
Route::post('/api/databases/{uuid}/query', [\App\Http\Controllers\Inertia\DatabaseMetricsController::class, 'executeQuery'])
    ->name('databases.query.api');

// ClickHouse specific endpoints
Route::get('/api/databases/{uuid}/clickhouse/queries', [\App\Http\Controllers\Inertia\DatabaseMetricsController::class, 'getClickhouseQueryLog'])
    ->name('databases.clickhouse.queries.api');

Route::get('/api/databases/{uuid}/clickhouse/merge-status', [\App\Http\Controllers\Inertia\DatabaseMetricsController::class, 'getClickhouseMergeStatus'])
    ->name('databases.clickhouse.merge-status.api');

Route::get('/api/databases/{uuid}/clickhouse/replication', [\App\Http\Controllers\Inertia\DatabaseMetricsController::class, 'getClickhouseReplicationStatus'])
    ->name('databases.clickhouse.replication.api');

Route::get('/api/databases/{uuid}/clickhouse/settings', [\App\Http\Controllers\Inertia\DatabaseMetricsController::class, 'getClickhouseSettings'])
    ->name('databases.clickhouse.settings.api');

// MongoDB specific endpoints
Route::get('/api/databases/{uuid}/mongodb/collections', [\App\Http\Controllers\Inertia\DatabaseMetricsController::class, 'getMongoCollections'])
    ->name('databases.mongodb.collections.api');

Route::get('/api/databases/{uuid}/mongodb/indexes', [\App\Http\Controllers\Inertia\DatabaseMetricsController::class, 'getMongoIndexes'])
    ->name('databases.mongodb.indexes.api');

Route::get('/api/databases/{uuid}/mongodb/replica-set', [\App\Http\Controllers\Inertia\DatabaseMetricsController::class, 'getMongoReplicaSet'])
    ->name('databases.mongodb.replica-set.api');

// Redis specific endpoints
Route::get('/api/databases/{uuid}/redis/keys', [\App\Http\Controllers\Inertia\DatabaseMetricsController::class, 'getRedisKeys'])
    ->name('databases.redis.keys.api');

Route::get('/api/databases/{uuid}/redis/memory', [\App\Http\Controllers\Inertia\DatabaseMetricsController::class, 'getRedisMemory'])
    ->name('databases.redis.memory.api');

Route::post('/api/databases/{uuid}/redis/flush', [\App\Http\Controllers\Inertia\DatabaseMetricsController::class, 'redisFlush'])
    ->name('databases.redis.flush.api');

// PostgreSQL maintenance endpoints
Route::post('/api/databases/{uuid}/postgres/maintenance', [\App\Http\Controllers\Inertia\DatabaseMetricsController::class, 'postgresMaintenace'])
    ->name('databases.postgres.maintenance.api');

// MySQL/MariaDB settings endpoint
Route::get('/api/databases/{uuid}/mysql/settings', [\App\Http\Controllers\Inertia\DatabaseMetricsController::class, 'getMysqlSettings'])
    ->name('databases.mysql.settings.api');

// Redis persistence settings endpoint
Route::get('/api/databases/{uuid}/redis/persistence', [\App\Http\Controllers\Inertia\DatabaseMetricsController::class, 'getRedisPersistence'])
    ->name('databases.redis.persistence.api');

// MongoDB storage settings endpoint
Route::get('/api/databases/{uuid}/mongodb/storage-settings', [\App\Http\Controllers\Inertia\DatabaseMetricsController::class, 'getMongoStorageSettings'])
    ->name('databases.mongodb.storage-settings.api');

// Active connections endpoint
Route::get('/api/databases/{uuid}/connections', [\App\Http\Controllers\Inertia\DatabaseMetricsController::class, 'getActiveConnections'])
    ->name('databases.connections.api');

// Kill connection endpoint
Route::post('/api/databases/{uuid}/connections/kill', [\App\Http\Controllers\Inertia\DatabaseMetricsController::class, 'killConnection'])
    ->name('databases.connections.kill.api');

// Create user endpoint
Route::post('/api/databases/{uuid}/users/create', [\App\Http\Controllers\Inertia\DatabaseMetricsController::class, 'createUser'])
    ->name('databases.users.create.api');

// Delete user endpoint
Route::post('/api/databases/{uuid}/users/delete', [\App\Http\Controllers\Inertia\DatabaseMetricsController::class, 'deleteUser'])
    ->name('databases.users.delete.api');

// MongoDB create index endpoint
Route::post('/api/databases/{uuid}/mongodb/indexes/create', [\App\Http\Controllers\Inertia\DatabaseMetricsController::class, 'createMongoIndex'])
    ->name('databases.mongodb.indexes.create.api');

// Redis delete key endpoint
Route::post('/api/databases/{uuid}/redis/keys/delete', [\App\Http\Controllers\Inertia\DatabaseMetricsController::class, 'deleteRedisKey'])
    ->name('databases.redis.keys.delete.api');

// Database tables/collections list endpoint
Route::get('/api/databases/{uuid}/tables', [\App\Http\Controllers\Inertia\DatabaseMetricsController::class, 'getTablesList'])
    ->name('databases.tables.api');

// S3 connection test endpoint
Route::post('/api/databases/s3/test', [\App\Http\Controllers\Inertia\DatabaseMetricsController::class, 'testS3Connection'])
    ->name('databases.s3.test.api');

Route::get('/databases/{uuid}/settings', function (string $uuid) {
    $database = findDatabaseByUuid($uuid);

    return Inertia::render('Databases/Settings/Index', [
        'database' => formatDatabaseForView($database),
    ]);
})->name('databases.settings');

Route::get('/databases/{uuid}/settings/backups', function (string $uuid) {
    $database = findDatabaseByUuid($uuid);

    return Inertia::render('Databases/Settings/Backups', [
        'database' => formatDatabaseForView($database),
    ]);
})->name('databases.settings.backups');

Route::patch('/databases/{uuid}/settings/backups', function (string $uuid, Request $request) {
    [$database, $type] = findDatabaseByUuid($uuid);

    $request->validate([
        'backup_id' => 'nullable|exists:scheduled_database_backups,id',
        'enabled' => 'boolean',
        'frequency' => 'nullable|string',
        'save_s3' => 'boolean',
        's3_storage_id' => 'nullable|exists:s3_storages,id',
        'databases_to_backup' => 'nullable|string',
    ]);

    try {
        if ($request->has('backup_id') && $request->backup_id) {
            // Update existing backup configuration
            $backup = ScheduledDatabaseBackup::where('id', $request->backup_id)
                ->where('database_id', $database->id)
                ->where('database_type', $database->getMorphClass())
                ->firstOrFail();

            $backup->update($request->only([
                'enabled',
                'frequency',
                'save_s3',
                's3_storage_id',
                'databases_to_backup',
            ]));
        } else {
            // Create new backup configuration
            ScheduledDatabaseBackup::create([
                'database_id' => $database->id,
                'database_type' => $database->getMorphClass(),
                'team_id' => currentTeam()->id,
                'enabled' => $request->input('enabled', true),
                'frequency' => $request->input('frequency', 'daily'),
                'save_s3' => $request->input('save_s3', false),
                's3_storage_id' => $request->input('s3_storage_id'),
                'databases_to_backup' => $request->input('databases_to_backup'),
            ]);
        }

        return redirect()->back()->with('success', 'Backup settings saved successfully');
    } catch (\Exception $e) {
        return redirect()->back()->withErrors(['error' => 'Failed to save backup settings: '.$e->getMessage()]);
    }
})->name('databases.settings.backups.update');

Route::get('/databases/{uuid}/connections', function (string $uuid) {
    $database = findDatabaseByUuid($uuid);

    return Inertia::render('Databases/Connections', [
        'database' => formatDatabaseForView($database),
    ]);
})->name('databases.connections');

Route::get('/databases/{uuid}/users', function (string $uuid) {
    $database = findDatabaseByUuid($uuid);

    return Inertia::render('Databases/Users', [
        'database' => formatDatabaseForView($database),
    ]);
})->name('databases.users');

Route::get('/databases/{uuid}/query', function (string $uuid) {
    $database = findDatabaseByUuid($uuid);

    // Get all databases for the selector
    $allDatabases = getAllDatabases();

    return Inertia::render('Databases/Query', [
        'database' => formatDatabaseForView($database),
        'databases' => $allDatabases,
    ]);
})->name('databases.query');

Route::get('/databases/{uuid}/tables', function (string $uuid) {
    $database = findDatabaseByUuid($uuid);

    return Inertia::render('Databases/Tables', [
        'database' => formatDatabaseForView($database),
    ]);
})->name('databases.tables');

Route::get('/databases/{uuid}/extensions', function (string $uuid) {
    $database = findDatabaseByUuid($uuid);

    return Inertia::render('Databases/Extensions', [
        'database' => formatDatabaseForView($database),
    ]);
})->name('databases.extensions');

Route::get('/databases/{uuid}/import', function (string $uuid) {
    $database = findDatabaseByUuid($uuid);

    return Inertia::render('Databases/Import', [
        'database' => formatDatabaseForView($database),
    ]);
})->name('databases.import');

Route::get('/databases/{uuid}/overview', function (string $uuid) {
    $database = findDatabaseByUuid($uuid);

    return Inertia::render('Databases/Overview', [
        'database' => formatDatabaseForView($database),
    ]);
})->name('databases.overview');

Route::post('/databases/{uuid}/restart', function (string $uuid) {
    [$database, $type] = findDatabaseByUuid($uuid);

    try {
        RestartDatabase::run($database);

        return redirect()->back()->with('success', 'Database restart initiated successfully');
    } catch (\Exception $e) {
        return redirect()->back()->withErrors(['error' => 'Failed to restart database: '.$e->getMessage()]);
    }
})->name('databases.restart');
