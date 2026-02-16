<?php

namespace App\Http\Controllers\Inertia;

use App\Http\Controllers\Controller;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use App\Models\StandaloneRedis;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DatabaseController extends Controller
{
    /**
     * Display a listing of all databases.
     */
    public function index(): Response
    {
        return Inertia::render('Databases/Index', [
            'databases' => $this->getAllDatabases(),
        ]);
    }

    /**
     * Show the form for creating a new database.
     */
    public function create(): Response
    {
        return Inertia::render('Databases/Create');
    }

    /**
     * Store a newly created database in storage.
     */
    public function store(Request $request): RedirectResponse
    {
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
            default => throw new \InvalidArgumentException("Unsupported database type: {$request->database_type}"),
        };

        return redirect()->route('databases.show', $database->uuid);
    }

    /**
     * Display the specified database.
     */
    public function show(string $uuid): Response
    {
        $database = $this->findDatabaseByUuid($uuid);

        return Inertia::render('Databases/Show', [
            'database' => $this->formatDatabaseForView($database),
        ]);
    }

    /**
     * Remove the specified database from storage.
     */
    public function destroy(string $uuid): RedirectResponse
    {
        [$database, $type] = $this->findDatabaseByUuid($uuid);
        $database->delete();

        return redirect()->route('databases.index');
    }

    /**
     * Display database backups.
     */
    public function backups(string $uuid): Response
    {
        $database = $this->findDatabaseByUuid($uuid);

        return Inertia::render('Databases/Backups', [
            'database' => $this->formatDatabaseForView($database),
        ]);
    }

    /**
     * Display database logs.
     */
    public function logs(string $uuid): Response
    {
        $database = $this->findDatabaseByUuid($uuid);

        return Inertia::render('Databases/Logs', [
            'database' => $this->formatDatabaseForView($database),
        ]);
    }

    /**
     * Display database metrics.
     */
    public function metrics(string $uuid): Response
    {
        $database = $this->findDatabaseByUuid($uuid);

        return Inertia::render('Databases/Metrics', [
            'database' => $this->formatDatabaseForView($database),
        ]);
    }

    /**
     * Display database settings.
     */
    public function settings(string $uuid): Response
    {
        $database = $this->findDatabaseByUuid($uuid);

        return Inertia::render('Databases/Settings/Index', [
            'database' => $this->formatDatabaseForView($database),
        ]);
    }

    /**
     * Display database backup settings.
     */
    public function backupSettings(string $uuid): Response
    {
        $database = $this->findDatabaseByUuid($uuid);

        return Inertia::render('Databases/Settings/Backups', [
            'database' => $this->formatDatabaseForView($database),
        ]);
    }

    /**
     * Update database backup settings.
     */
    public function updateBackupSettings(string $uuid, Request $request): RedirectResponse
    {
        [$database, $type] = $this->findDatabaseByUuid($uuid);

        $validated = $request->validate([
            'backup_enabled' => 'boolean',
            'backup_frequency' => 'nullable|string',
            'backup_retention' => 'nullable|integer|min:1',
            's3_storage_id' => 'nullable|integer',
        ]);

        // Update backup settings based on database type
        if (isset($validated['backup_enabled'])) {
            // Check if database has scheduled backup relationship
            if (method_exists($database, 'scheduledBackups')) {
                $backup = $database->scheduledBackups()->first();
                if ($backup) {
                    $backup->enabled = $validated['backup_enabled'];
                    if (isset($validated['backup_frequency'])) {
                        $backup->frequency = $validated['backup_frequency'];
                    }
                    if (isset($validated['backup_retention'])) {
                        $backup->number_of_backups_to_keep = $validated['backup_retention'];
                    }
                    if (isset($validated['s3_storage_id'])) {
                        $backup->s3_storage_id = $validated['s3_storage_id'];
                    }
                    $backup->save();
                }
            }
        }

        return redirect()->back()->with('success', 'Backup settings saved successfully');
    }

    /**
     * Display database connections.
     */
    public function connections(string $uuid): Response
    {
        $database = $this->findDatabaseByUuid($uuid);

        return Inertia::render('Databases/Connections', [
            'database' => $this->formatDatabaseForView($database),
        ]);
    }

    /**
     * Display database users.
     */
    public function users(string $uuid): Response
    {
        $database = $this->findDatabaseByUuid($uuid);

        return Inertia::render('Databases/Users', [
            'database' => $this->formatDatabaseForView($database),
        ]);
    }

    /**
     * Display query interface.
     */
    public function query(string $uuid): Response
    {
        $database = $this->findDatabaseByUuid($uuid);

        return Inertia::render('Databases/Query', [
            'database' => $this->formatDatabaseForView($database),
            'databases' => $this->getAllDatabases(),
        ]);
    }

    /**
     * Display database tables.
     */
    public function tables(string $uuid): Response
    {
        $database = $this->findDatabaseByUuid($uuid);

        return Inertia::render('Databases/Tables', [
            'database' => $this->formatDatabaseForView($database),
        ]);
    }

    /**
     * Display database extensions.
     */
    public function extensions(string $uuid): Response
    {
        $database = $this->findDatabaseByUuid($uuid);

        return Inertia::render('Databases/Extensions', [
            'database' => $this->formatDatabaseForView($database),
        ]);
    }

    /**
     * Display database import interface.
     */
    public function import(string $uuid): Response
    {
        $database = $this->findDatabaseByUuid($uuid);

        return Inertia::render('Databases/Import', [
            'database' => $this->formatDatabaseForView($database),
        ]);
    }

    /**
     * Display database overview.
     */
    public function overview(string $uuid): Response
    {
        $database = $this->findDatabaseByUuid($uuid);

        return Inertia::render('Databases/Overview', [
            'database' => $this->formatDatabaseForView($database),
        ]);
    }

    /**
     * Find a database by UUID across all database types.
     */
    protected function findDatabaseByUuid(string $uuid): array
    {
        $database = StandalonePostgresql::ownedByCurrentTeam()->where('uuid', $uuid)->first();
        if ($database) {
            return [$database, 'postgresql'];
        }

        $database = StandaloneMysql::ownedByCurrentTeam()->where('uuid', $uuid)->first();
        if ($database) {
            return [$database, 'mysql'];
        }

        $database = StandaloneMariadb::ownedByCurrentTeam()->where('uuid', $uuid)->first();
        if ($database) {
            return [$database, 'mariadb'];
        }

        $database = StandaloneMongodb::ownedByCurrentTeam()->where('uuid', $uuid)->first();
        if ($database) {
            return [$database, 'mongodb'];
        }

        $database = StandaloneRedis::ownedByCurrentTeam()->where('uuid', $uuid)->first();
        if ($database) {
            return [$database, 'redis'];
        }

        abort(404);
    }

    /**
     * Format database data for view.
     */
    protected function formatDatabaseForView(mixed $databaseWithType): array
    {
        [$database, $type] = is_array($databaseWithType) ? $databaseWithType : [$databaseWithType, 'postgresql'];

        // Get connection details based on database type
        $connectionDetails = $this->getConnectionDetails($database, $type);

        // Parse status into state and health
        $statusString = $database->status();
        $statusParts = explode(':', $statusString);
        $state = $statusParts[0];
        $health = $statusParts[1] ?? 'unknown';

        // Load environment relationship
        $database->load('environment');

        return [
            'id' => $database->id,
            'uuid' => $database->uuid,
            'name' => $database->name,
            'description' => $database->description,
            'database_type' => $type,
            'status' => [
                'state' => $state,
                'health' => $health,
            ],
            'environment_id' => $database->environment_id,
            'environment' => $database->environment ? [
                'id' => $database->environment->id,
                'name' => $database->environment->name,
                'type' => $database->environment->type ?? 'production',
            ] : null,
            'created_at' => $database->created_at,
            'updated_at' => $database->updated_at,
            // Connection URLs for Railway-like experience
            'internal_db_url' => $database->internal_db_url ?? null,
            'external_db_url' => $database->external_db_url ?? null,
            // Connection details
            'connection' => $connectionDetails,
        ];
    }

    /**
     * Get connection details for a database.
     */
    protected function getConnectionDetails(mixed $database, string $type): array
    {
        $port = match ($type) {
            'postgresql' => '5432',
            'mysql', 'mariadb' => '3306',
            'mongodb' => '27017',
            'redis', 'keydb', 'dragonfly' => '6379',
            'clickhouse' => '9000',
            default => '5432',
        };

        // Internal hostname is the container UUID
        $internalHost = $database->uuid;

        // External host depends on server configuration
        $server = $database->destination?->server;
        $externalHost = $server ? ($server->ip ?? 'localhost') : 'localhost';
        $publicPort = $database->public_port ?? null;

        // Get credentials based on database type
        $credentials = match ($type) {
            'postgresql' => [
                'username' => $database->postgres_user ?? 'postgres',
                'password' => $database->postgres_password ?? null,
                'database' => $database->postgres_db ?? 'postgres',
            ],
            'mysql' => [
                'username' => $database->mysql_user ?? 'root',
                'password' => $database->mysql_password ?? $database->mysql_root_password ?? null,
                'database' => $database->mysql_database ?? 'mysql',
            ],
            'mariadb' => [
                'username' => $database->mariadb_user ?? 'root',
                'password' => $database->mariadb_password ?? $database->mariadb_root_password ?? null,
                'database' => $database->mariadb_database ?? 'mariadb',
            ],
            'mongodb' => [
                'username' => $database->mongo_initdb_root_username ?? 'root',
                'password' => $database->mongo_initdb_root_password ?? null,
                'database' => $database->mongo_initdb_database ?? 'admin',
            ],
            'redis', 'keydb', 'dragonfly' => [
                'username' => null,
                'password' => $database->redis_password ?? null,
                'database' => '0',
            ],
            'clickhouse' => [
                'username' => $database->clickhouse_admin_user ?? 'default',
                'password' => $database->clickhouse_admin_password ?? null,
                'database' => 'default',
            ],
            default => [
                'username' => null,
                'password' => null,
                'database' => null,
            ],
        };

        return [
            'internal_host' => $internalHost,
            'external_host' => $externalHost,
            'port' => $port,
            'public_port' => $publicPort,
            ...$credentials,
        ];
    }

    /**
     * Get all databases across all types.
     */
    protected function getAllDatabases()
    {
        $databases = StandalonePostgresql::ownedByCurrentTeamCached()
            ->map(fn ($db) => $this->formatDatabaseForView([$db, 'postgresql']));

        $mysqlDatabases = StandaloneMysql::ownedByCurrentTeamCached()
            ->map(fn ($db) => $this->formatDatabaseForView([$db, 'mysql']));

        $mariadbDatabases = StandaloneMariadb::ownedByCurrentTeamCached()
            ->map(fn ($db) => $this->formatDatabaseForView([$db, 'mariadb']));

        $mongodbDatabases = StandaloneMongodb::ownedByCurrentTeamCached()
            ->map(fn ($db) => $this->formatDatabaseForView([$db, 'mongodb']));

        $redisDatabases = StandaloneRedis::ownedByCurrentTeamCached()
            ->map(fn ($db) => $this->formatDatabaseForView([$db, 'redis']));

        return $databases
            ->concat($mysqlDatabases)
            ->concat($mariadbDatabases)
            ->concat($mongodbDatabases)
            ->concat($redisDatabases)
            ->sortByDesc('updated_at')
            ->values();
    }
}
