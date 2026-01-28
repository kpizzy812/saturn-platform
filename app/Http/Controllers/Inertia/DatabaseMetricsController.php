<?php

namespace App\Http\Controllers\Inertia;

use App\Http\Controllers\Controller;
use App\Models\DatabaseMetric;
use App\Models\StandaloneClickhouse;
use App\Models\StandaloneDragonfly;
use App\Models\StandaloneKeydb;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use App\Models\StandaloneRedis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DatabaseMetricsController extends Controller
{
    /**
     * Get real-time metrics for a database.
     */
    public function getMetrics(string $uuid): JsonResponse
    {
        return $this->fetchCurrentMetrics($uuid);
    }

    /**
     * Get historical metrics for charts and analytics.
     */
    public function getHistoricalMetrics(Request $request, string $uuid): JsonResponse
    {
        [$database, $type] = $this->findDatabase($uuid);

        if (! $database) {
            return response()->json(['available' => false, 'error' => 'Database not found'], 404);
        }

        $timeRange = $request->input('timeRange', '24h');
        $validRanges = ['1h', '6h', '24h', '7d', '30d'];
        if (! in_array($timeRange, $validRanges)) {
            $timeRange = '24h';
        }

        $aggregatedMetrics = DatabaseMetric::getAggregatedMetrics($uuid, $timeRange);

        // Check if we have any historical data
        $hasData = ! empty($aggregatedMetrics['cpu']['data']) ||
                   ! empty($aggregatedMetrics['memory']['data']);

        return response()->json([
            'available' => true,
            'hasHistoricalData' => $hasData,
            'timeRange' => $timeRange,
            'metrics' => $aggregatedMetrics,
        ]);
    }

    /**
     * Fetch current real-time metrics.
     */
    protected function fetchCurrentMetrics(string $uuid): JsonResponse
    {
        [$database, $type] = $this->findDatabase($uuid);

        if (! $database) {
            return response()->json(['available' => false, 'error' => 'Database not found'], 404);
        }

        $server = $database->destination?->server;

        if (! $server) {
            return response()->json(['available' => false, 'error' => 'Server not configured']);
        }

        if (! $server->isFunctional()) {
            return response()->json(['available' => false, 'error' => 'Server not reachable']);
        }

        try {
            $metrics = $this->collectMetrics($database, $server, $type);

            return response()->json([
                'available' => true,
                'metrics' => $metrics,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'available' => false,
                'error' => 'Failed to collect metrics: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Collect metrics based on database type.
     */
    protected function collectMetrics(mixed $database, mixed $server, string $type): array
    {
        return match ($type) {
            'postgresql' => $this->collectPostgresMetrics($server, $database),
            'mysql', 'mariadb' => $this->collectMysqlMetrics($server, $database),
            'redis', 'keydb', 'dragonfly' => $this->collectRedisMetrics($server, $database),
            'mongodb' => $this->collectMongoMetrics($server, $database),
            'clickhouse' => $this->collectClickhouseMetrics($server, $database),
            default => ['error' => 'Unsupported database type'],
        };
    }

    /**
     * Collect PostgreSQL metrics via SSH.
     */
    protected function collectPostgresMetrics(mixed $server, mixed $database): array
    {
        $containerName = $database->uuid;
        $user = $database->postgres_user ?? 'postgres';
        $dbName = $database->postgres_db ?? 'postgres';

        $metrics = [
            'activeConnections' => null,
            'maxConnections' => 100,
            'databaseSize' => 'N/A',
            'queriesPerSec' => null,
            'cacheHitRatio' => null,
        ];

        try {
            // Get active connections count
            $connCommand = "docker exec {$containerName} psql -U {$user} -d {$dbName} -t -c \"SELECT count(*) FROM pg_stat_activity WHERE state = 'active';\" 2>/dev/null || echo 'N/A'";
            $activeConnections = trim(instant_remote_process([$connCommand], $server, false) ?? '');
            if (is_numeric($activeConnections)) {
                $metrics['activeConnections'] = (int) $activeConnections;
            }

            // Get database size
            $sizeCommand = "docker exec {$containerName} psql -U {$user} -d {$dbName} -t -c \"SELECT pg_size_pretty(pg_database_size('{$dbName}'));\" 2>/dev/null || echo 'N/A'";
            $databaseSize = trim(instant_remote_process([$sizeCommand], $server, false) ?? 'N/A');
            if ($databaseSize && $databaseSize !== 'N/A') {
                $metrics['databaseSize'] = $databaseSize;
            }

            // Get max connections
            $maxConnCommand = "docker exec {$containerName} psql -U {$user} -d {$dbName} -t -c \"SHOW max_connections;\" 2>/dev/null || echo '100'";
            $maxConnections = trim(instant_remote_process([$maxConnCommand], $server, false) ?? '100');
            if (is_numeric($maxConnections)) {
                $metrics['maxConnections'] = (int) $maxConnections;
            }
        } catch (\Exception $e) {
            // Metrics will remain as defaults
        }

        return $metrics;
    }

    /**
     * Collect MySQL/MariaDB metrics via SSH.
     */
    protected function collectMysqlMetrics(mixed $server, mixed $database): array
    {
        $containerName = $database->uuid;
        $password = $database->mysql_root_password ?? $database->mysql_password ?? '';

        $metrics = [
            'activeConnections' => null,
            'maxConnections' => 150,
            'databaseSize' => 'N/A',
            'queriesPerSec' => null,
            'slowQueries' => null,
        ];

        try {
            // Get active connections
            $connCommand = "docker exec {$containerName} mysql -u root -p'{$password}' -N -e \"SHOW STATUS LIKE 'Threads_connected';\" 2>/dev/null | awk '{print \$2}' || echo 'N/A'";
            $connections = trim(instant_remote_process([$connCommand], $server, false) ?? '');
            if (is_numeric($connections)) {
                $metrics['activeConnections'] = (int) $connections;
            }

            // Get max connections
            $maxConnCommand = "docker exec {$containerName} mysql -u root -p'{$password}' -N -e \"SHOW VARIABLES LIKE 'max_connections';\" 2>/dev/null | awk '{print \$2}' || echo '150'";
            $maxConnections = trim(instant_remote_process([$maxConnCommand], $server, false) ?? '150');
            if (is_numeric($maxConnections)) {
                $metrics['maxConnections'] = (int) $maxConnections;
            }

            // Get slow queries
            $slowCommand = "docker exec {$containerName} mysql -u root -p'{$password}' -N -e \"SHOW STATUS LIKE 'Slow_queries';\" 2>/dev/null | awk '{print \$2}' || echo 'N/A'";
            $slowQueries = trim(instant_remote_process([$slowCommand], $server, false) ?? '');
            if (is_numeric($slowQueries)) {
                $metrics['slowQueries'] = (int) $slowQueries;
            }
        } catch (\Exception $e) {
            // Metrics will remain as defaults
        }

        return $metrics;
    }

    /**
     * Collect Redis/KeyDB/Dragonfly metrics via SSH.
     */
    protected function collectRedisMetrics(mixed $server, mixed $database): array
    {
        $containerName = $database->uuid;
        $password = $database->redis_password ?? $database->keydb_password ?? $database->dragonfly_password ?? '';

        $metrics = [
            'totalKeys' => null,
            'memoryUsed' => 'N/A',
            'opsPerSec' => null,
            'hitRate' => null,
        ];

        try {
            $authFlag = $password ? "-a '{$password}'" : '';

            // Get Redis INFO
            $infoCommand = "docker exec {$containerName} redis-cli {$authFlag} INFO 2>/dev/null || echo ''";
            $info = instant_remote_process([$infoCommand], $server, false) ?? '';

            // Parse used memory
            if (preg_match('/used_memory_human:(\S+)/', $info, $matches)) {
                $metrics['memoryUsed'] = $matches[1];
            }

            // Parse total keys (db0)
            if (preg_match('/db0:keys=(\d+)/', $info, $matches)) {
                $metrics['totalKeys'] = (int) $matches[1];
            }

            // Parse ops per sec
            if (preg_match('/instantaneous_ops_per_sec:(\d+)/', $info, $matches)) {
                $metrics['opsPerSec'] = (int) $matches[1];
            }

            // Parse hit rate
            if (preg_match('/keyspace_hits:(\d+)/', $info, $hitsMatch) &&
                preg_match('/keyspace_misses:(\d+)/', $info, $missesMatch)) {
                $hits = (int) $hitsMatch[1];
                $misses = (int) $missesMatch[1];
                $total = $hits + $misses;
                if ($total > 0) {
                    $metrics['hitRate'] = round(($hits / $total) * 100, 1).'%';
                }
            }
        } catch (\Exception $e) {
            // Metrics will remain as defaults
        }

        return $metrics;
    }

    /**
     * Collect MongoDB metrics via SSH.
     */
    protected function collectMongoMetrics(mixed $server, mixed $database): array
    {
        $containerName = $database->uuid;
        $dbName = $database->mongo_initdb_database ?? 'admin';

        $metrics = [
            'collections' => null,
            'documents' => null,
            'databaseSize' => 'N/A',
            'indexSize' => 'N/A',
        ];

        try {
            // Get database stats
            $statsCommand = "docker exec {$containerName} mongosh --quiet --eval \"JSON.stringify(db.stats())\" {$dbName} 2>/dev/null || echo '{}'";
            $statsJson = trim(instant_remote_process([$statsCommand], $server, false) ?? '{}');
            $stats = json_decode($statsJson, true);

            if ($stats && is_array($stats)) {
                $metrics['collections'] = $stats['collections'] ?? null;
                $metrics['documents'] = $stats['objects'] ?? null;

                if (isset($stats['dataSize'])) {
                    $metrics['databaseSize'] = $this->formatBytes($stats['dataSize']);
                }
                if (isset($stats['indexSize'])) {
                    $metrics['indexSize'] = $this->formatBytes($stats['indexSize']);
                }
            }
        } catch (\Exception $e) {
            // Metrics will remain as defaults
        }

        return $metrics;
    }

    /**
     * Collect ClickHouse metrics via SSH.
     */
    protected function collectClickhouseMetrics(mixed $server, mixed $database): array
    {
        $containerName = $database->uuid;
        $password = $database->clickhouse_admin_password ?? '';

        $metrics = [
            'totalTables' => null,
            'totalRows' => null,
            'databaseSize' => 'N/A',
            'queriesPerSec' => null,
        ];

        try {
            $authFlag = $password ? "--password '{$password}'" : '';

            // Get total tables
            $tablesCommand = "docker exec {$containerName} clickhouse-client {$authFlag} -q \"SELECT count() FROM system.tables WHERE database NOT IN ('system', 'INFORMATION_SCHEMA', 'information_schema')\" 2>/dev/null || echo 'N/A'";
            $tables = trim(instant_remote_process([$tablesCommand], $server, false) ?? '');
            if (is_numeric($tables)) {
                $metrics['totalTables'] = (int) $tables;
            }

            // Get current queries
            $queriesCommand = "docker exec {$containerName} clickhouse-client {$authFlag} -q \"SELECT count() FROM system.processes\" 2>/dev/null || echo 'N/A'";
            $queries = trim(instant_remote_process([$queriesCommand], $server, false) ?? '');
            if (is_numeric($queries)) {
                $metrics['queriesPerSec'] = (int) $queries;
            }
        } catch (\Exception $e) {
            // Metrics will remain as defaults
        }

        return $metrics;
    }

    /**
     * Find database by UUID across all database types.
     */
    protected function findDatabase(string $uuid): array
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

        $database = StandaloneKeydb::ownedByCurrentTeam()->where('uuid', $uuid)->first();
        if ($database) {
            return [$database, 'keydb'];
        }

        $database = StandaloneDragonfly::ownedByCurrentTeam()->where('uuid', $uuid)->first();
        if ($database) {
            return [$database, 'dragonfly'];
        }

        $database = StandaloneClickhouse::ownedByCurrentTeam()->where('uuid', $uuid)->first();
        if ($database) {
            return [$database, 'clickhouse'];
        }

        return [null, null];
    }

    /**
     * Format bytes to human readable string.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;
        $value = $bytes;

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        return round($value, 2).' '.$units[$unitIndex];
    }

    /**
     * Get PostgreSQL extensions.
     */
    public function getExtensions(string $uuid): JsonResponse
    {
        [$database, $type] = $this->findDatabase($uuid);

        if (! $database || $type !== 'postgresql') {
            return response()->json(['available' => false, 'error' => 'PostgreSQL database not found'], 404);
        }

        $server = $database->destination?->server;

        if (! $server || ! $server->isFunctional()) {
            return response()->json(['available' => false, 'error' => 'Server not reachable']);
        }

        try {
            $containerName = $database->uuid;
            $user = $database->postgres_user ?? 'postgres';
            $dbName = $database->postgres_db ?? 'postgres';

            // Get installed extensions
            $command = "docker exec {$containerName} psql -U {$user} -d {$dbName} -t -A -F '|' -c \"SELECT e.extname, e.extversion, 'installed' as status, c.comment FROM pg_extension e LEFT JOIN pg_available_extensions c ON e.extname = c.name ORDER BY e.extname;\" 2>/dev/null || echo ''";
            $result = trim(instant_remote_process([$command], $server, false) ?? '');

            $extensions = [];
            if ($result) {
                foreach (explode("\n", $result) as $line) {
                    $parts = explode('|', trim($line));
                    if (count($parts) >= 3 && ! empty($parts[0])) {
                        $extensions[] = [
                            'name' => $parts[0],
                            'version' => $parts[1] ?? 'N/A',
                            'enabled' => true,
                            'description' => $parts[3] ?? '',
                        ];
                    }
                }
            }

            // Get available but not installed extensions
            $availableCommand = "docker exec {$containerName} psql -U {$user} -d {$dbName} -t -A -F '|' -c \"SELECT name, default_version, comment FROM pg_available_extensions WHERE installed_version IS NULL ORDER BY name LIMIT 20;\" 2>/dev/null || echo ''";
            $availableResult = trim(instant_remote_process([$availableCommand], $server, false) ?? '');

            if ($availableResult) {
                foreach (explode("\n", $availableResult) as $line) {
                    $parts = explode('|', trim($line));
                    if (count($parts) >= 2 && ! empty($parts[0])) {
                        $extensions[] = [
                            'name' => $parts[0],
                            'version' => $parts[1] ?? 'N/A',
                            'enabled' => false,
                            'description' => $parts[2] ?? '',
                        ];
                    }
                }
            }

            return response()->json([
                'available' => true,
                'extensions' => $extensions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'available' => false,
                'error' => 'Failed to fetch extensions: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Get database users.
     */
    public function getUsers(string $uuid): JsonResponse
    {
        [$database, $type] = $this->findDatabase($uuid);

        if (! $database) {
            return response()->json(['available' => false, 'error' => 'Database not found'], 404);
        }

        $server = $database->destination?->server;

        if (! $server || ! $server->isFunctional()) {
            return response()->json(['available' => false, 'error' => 'Server not reachable']);
        }

        try {
            $users = match ($type) {
                'postgresql' => $this->getPostgresUsers($server, $database),
                'mysql', 'mariadb' => $this->getMysqlUsers($server, $database),
                'mongodb' => $this->getMongoUsers($server, $database),
                default => [],
            };

            return response()->json([
                'available' => true,
                'users' => $users,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'available' => false,
                'error' => 'Failed to fetch users: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Get PostgreSQL users.
     */
    protected function getPostgresUsers(mixed $server, mixed $database): array
    {
        $containerName = $database->uuid;
        $user = $database->postgres_user ?? 'postgres';
        $dbName = $database->postgres_db ?? 'postgres';

        $command = "docker exec {$containerName} psql -U {$user} -d {$dbName} -t -A -F '|' -c \"SELECT r.rolname, CASE WHEN r.rolsuper THEN 'Superuser' WHEN r.rolcreaterole THEN 'Admin' ELSE 'Standard' END as role_type, (SELECT count(*) FROM pg_stat_activity WHERE usename = r.rolname AND state = 'active') as connections FROM pg_roles r WHERE r.rolcanlogin = true ORDER BY r.rolname;\" 2>/dev/null || echo ''";
        $result = trim(instant_remote_process([$command], $server, false) ?? '');

        $users = [];
        if ($result) {
            foreach (explode("\n", $result) as $line) {
                $parts = explode('|', trim($line));
                if (count($parts) >= 2 && ! empty($parts[0])) {
                    $users[] = [
                        'name' => $parts[0],
                        'role' => $parts[1] ?? 'Standard',
                        'connections' => (int) ($parts[2] ?? 0),
                    ];
                }
            }
        }

        return $users;
    }

    /**
     * Get MySQL/MariaDB users.
     */
    protected function getMysqlUsers(mixed $server, mixed $database): array
    {
        $containerName = $database->uuid;
        $password = $database->mysql_root_password ?? $database->mysql_password ?? '';

        $command = "docker exec {$containerName} mysql -u root -p'{$password}' -N -e \"SELECT user, 'Standard' as role, 0 as connections FROM mysql.user WHERE user NOT IN ('root', 'mysql.sys', 'mysql.session', 'mysql.infoschema');\" 2>/dev/null || echo ''";
        $result = trim(instant_remote_process([$command], $server, false) ?? '');

        $users = [
            ['name' => 'root', 'role' => 'Superuser', 'connections' => 0],
        ];

        if ($result) {
            foreach (explode("\n", $result) as $line) {
                $parts = preg_split('/\s+/', trim($line));
                if (count($parts) >= 1 && ! empty($parts[0])) {
                    $users[] = [
                        'name' => $parts[0],
                        'role' => 'Standard',
                        'connections' => 0,
                    ];
                }
            }
        }

        return $users;
    }

    /**
     * Get MongoDB users.
     */
    protected function getMongoUsers(mixed $server, mixed $database): array
    {
        $containerName = $database->uuid;
        $dbName = $database->mongo_initdb_database ?? 'admin';

        $command = "docker exec {$containerName} mongosh --quiet --eval \"JSON.stringify(db.getUsers().users.map(u => ({user: u.user, roles: u.roles.map(r => r.role).join(', ')})))\" {$dbName} 2>/dev/null || echo '[]'";
        $result = trim(instant_remote_process([$command], $server, false) ?? '[]');

        $users = [];
        $parsed = json_decode($result, true);
        if (is_array($parsed)) {
            foreach ($parsed as $u) {
                $users[] = [
                    'name' => $u['user'] ?? 'unknown',
                    'role' => $u['roles'] ?? 'Standard',
                    'connections' => 0,
                ];
            }
        }

        return $users;
    }

    /**
     * Get database logs.
     */
    public function getLogs(Request $request, string $uuid): JsonResponse
    {
        [$database, $type] = $this->findDatabase($uuid);

        if (! $database) {
            return response()->json(['available' => false, 'error' => 'Database not found'], 404);
        }

        $server = $database->destination?->server;

        if (! $server || ! $server->isFunctional()) {
            return response()->json(['available' => false, 'error' => 'Server not reachable']);
        }

        try {
            $lines = (int) $request->input('lines', 100);
            $lines = min(max($lines, 10), 1000); // Clamp between 10 and 1000

            $containerName = $database->uuid;

            // Check if container exists before fetching logs
            $checkCommand = "docker inspect --format='{{.State.Status}}' {$containerName} 2>&1";
            $containerStatus = trim(instant_remote_process([$checkCommand], $server, false) ?? '');

            if (str_contains($containerStatus, 'No such') || str_contains($containerStatus, 'Error')) {
                return response()->json([
                    'available' => true,
                    'logs' => [[
                        'timestamp' => date('Y-m-d H:i:s'),
                        'level' => 'WARNING',
                        'message' => 'Container is not running. The database may need to be started first.',
                    ]],
                ]);
            }

            $command = "docker logs --tail {$lines} {$containerName} 2>&1 | tail -{$lines}";
            $result = trim(instant_remote_process([$command], $server, false) ?? '');

            $logs = [];
            if ($result) {
                foreach (explode("\n", $result) as $line) {
                    $line = trim($line);
                    if (empty($line)) {
                        continue;
                    }

                    // Try to parse timestamp and level
                    $timestamp = date('Y-m-d H:i:s');
                    $level = 'INFO';
                    $message = $line;

                    // PostgreSQL format: 2024-01-03 10:23:45 UTC [123]: LOG: message
                    if (preg_match('/^(\d{4}-\d{2}-\d{2}[T\s]\d{2}:\d{2}:\d{2}).*?(LOG|WARNING|ERROR|FATAL|PANIC|INFO|DEBUG|NOTICE):?\s*(.*)$/i', $line, $matches)) {
                        $timestamp = $matches[1];
                        $level = strtoupper($matches[2]);
                        $message = $matches[3];
                    }
                    // MySQL format: 2024-01-03T10:23:45.123456Z 0 [Warning] message
                    elseif (preg_match('/^(\d{4}-\d{2}-\d{2}[T\s]\d{2}:\d{2}:\d{2}).*?\[(Note|Warning|Error|System)\]\s*(.*)$/i', $line, $matches)) {
                        $timestamp = $matches[1];
                        $level = strtoupper($matches[2]);
                        if ($level === 'NOTE' || $level === 'SYSTEM') {
                            $level = 'INFO';
                        }
                        $message = $matches[3];
                    }

                    $logs[] = [
                        'timestamp' => $timestamp,
                        'level' => $level,
                        'message' => $message,
                    ];
                }
            }

            return response()->json([
                'available' => true,
                'logs' => array_slice($logs, -100), // Return last 100 parsed logs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'available' => false,
                'error' => 'Failed to fetch logs: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Execute SQL query on database.
     * Supports PostgreSQL, MySQL/MariaDB, ClickHouse.
     * MongoDB and Redis are not supported for raw SQL queries.
     */
    public function executeQuery(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|max:10000',
        ]);

        [$database, $type] = $this->findDatabase($uuid);

        if (! $database) {
            return response()->json(['success' => false, 'error' => 'Database not found'], 404);
        }

        // Only allow SQL-capable databases
        $supportedTypes = ['postgresql', 'mysql', 'mariadb', 'clickhouse'];
        if (! in_array($type, $supportedTypes)) {
            return response()->json([
                'success' => false,
                'error' => 'Query execution is only supported for PostgreSQL, MySQL, MariaDB, and ClickHouse databases',
            ], 400);
        }

        $server = $database->destination?->server;

        if (! $server || ! $server->isFunctional()) {
            return response()->json(['success' => false, 'error' => 'Server not reachable']);
        }

        $query = trim($request->input('query'));

        // Basic security checks - block dangerous operations
        $dangerousPatterns = [
            '/^\s*(DROP\s+DATABASE|DROP\s+USER|DROP\s+ROLE|TRUNCATE\s+ALL)/i',
            '/;\s*(DROP|TRUNCATE)/i',
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $query)) {
                return response()->json([
                    'success' => false,
                    'error' => 'This query contains potentially dangerous operations and has been blocked',
                ], 400);
            }
        }

        try {
            $startTime = microtime(true);
            $result = match ($type) {
                'postgresql' => $this->executePostgresQuery($server, $database, $query),
                'mysql', 'mariadb' => $this->executeMysqlQuery($server, $database, $query),
                'clickhouse' => $this->executeClickhouseQuery($server, $database, $query),
                default => ['error' => 'Unsupported database type'],
            };
            $executionTime = round(microtime(true) - $startTime, 3);

            if (isset($result['error'])) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                ]);
            }

            return response()->json([
                'success' => true,
                'columns' => $result['columns'] ?? [],
                'rows' => $result['rows'] ?? [],
                'rowCount' => $result['rowCount'] ?? 0,
                'executionTime' => $executionTime,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Query execution failed: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Execute PostgreSQL query.
     */
    protected function executePostgresQuery(mixed $server, mixed $database, string $query): array
    {
        $containerName = $database->uuid;
        $user = $database->postgres_user ?? 'postgres';
        $dbName = $database->postgres_db ?? 'postgres';

        // Escape single quotes in query for shell
        $escapedQuery = str_replace("'", "'\"'\"'", $query);

        // Execute query with pipe-delimited output format
        $command = "docker exec {$containerName} psql -U {$user} -d {$dbName} -t -A -F '|' -c '{$escapedQuery}' 2>&1";
        $result = instant_remote_process([$command], $server, false, false, 30);

        if ($result === null) {
            return ['error' => 'No response from database'];
        }

        // Check for errors
        if (stripos($result, 'ERROR:') !== false || stripos($result, 'FATAL:') !== false) {
            return ['error' => trim($result)];
        }

        return $this->parseDelimitedResult($result, '|');
    }

    /**
     * Execute MySQL/MariaDB query.
     */
    protected function executeMysqlQuery(mixed $server, mixed $database, string $query): array
    {
        $containerName = $database->uuid;
        $password = $database->mysql_root_password ?? $database->mysql_password ?? '';

        // Escape single quotes in query for shell
        $escapedQuery = str_replace("'", "'\"'\"'", $query);

        // Execute query with tab-delimited output
        $command = "docker exec {$containerName} mysql -u root -p'{$password}' -N -B -e '{$escapedQuery}' 2>&1";
        $result = instant_remote_process([$command], $server, false, false, 30);

        if ($result === null) {
            return ['error' => 'No response from database'];
        }

        // Check for errors
        if (stripos($result, 'ERROR') !== false) {
            return ['error' => trim($result)];
        }

        return $this->parseDelimitedResult($result, "\t");
    }

    /**
     * Execute ClickHouse query.
     */
    protected function executeClickhouseQuery(mixed $server, mixed $database, string $query): array
    {
        $containerName = $database->uuid;
        $password = $database->clickhouse_admin_password ?? '';

        // Escape single quotes in query for shell
        $escapedQuery = str_replace("'", "'\"'\"'", $query);
        $authFlag = $password ? "--password '{$password}'" : '';

        // Execute query with TabSeparated format
        $command = "docker exec {$containerName} clickhouse-client {$authFlag} -q '{$escapedQuery}' 2>&1";
        $result = instant_remote_process([$command], $server, false, false, 30);

        if ($result === null) {
            return ['error' => 'No response from database'];
        }

        // Check for errors
        if (stripos($result, 'Exception') !== false || stripos($result, 'DB::Exception') !== false) {
            return ['error' => trim($result)];
        }

        return $this->parseDelimitedResult($result, "\t");
    }

    /**
     * Parse delimiter-separated query result into columns and rows.
     */
    protected function parseDelimitedResult(string $result, string $delimiter): array
    {
        $lines = array_filter(explode("\n", trim($result)), fn ($line) => trim($line) !== '');

        if (empty($lines)) {
            return ['columns' => [], 'rows' => [], 'rowCount' => 0];
        }

        $rows = [];
        $columns = [];

        foreach ($lines as $index => $line) {
            $values = explode($delimiter, $line);

            // First row determines column count, use generic column names
            if ($index === 0 && empty($columns)) {
                $columns = array_map(fn ($i) => "column_{$i}", range(0, count($values) - 1));
            }

            // Build row as associative array
            $row = [];
            foreach ($values as $i => $value) {
                $colName = $columns[$i] ?? "column_{$i}";
                $row[$colName] = $value;
            }
            $rows[] = $row;
        }

        // Limit results to prevent memory issues
        $maxRows = 1000;
        if (count($rows) > $maxRows) {
            $rows = array_slice($rows, 0, $maxRows);
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
            'rowCount' => count($rows),
        ];
    }

    /**
     * Get ClickHouse query log.
     */
    public function getClickhouseQueryLog(Request $request, string $uuid): JsonResponse
    {
        [$database, $type] = $this->findDatabase($uuid);

        if (! $database || $type !== 'clickhouse') {
            return response()->json(['available' => false, 'error' => 'ClickHouse database not found'], 404);
        }

        $server = $database->destination?->server;

        if (! $server || ! $server->isFunctional()) {
            return response()->json(['available' => false, 'error' => 'Server not reachable']);
        }

        try {
            $containerName = $database->uuid;
            $password = $database->clickhouse_admin_password ?? '';
            $authFlag = $password ? "--password '{$password}'" : '';
            $limit = min((int) $request->input('limit', 50), 100);

            // Get recent queries from system.query_log
            $command = "docker exec {$containerName} clickhouse-client {$authFlag} -q \"SELECT query, query_duration_ms/1000 as duration_sec, read_rows, formatReadableSize(read_bytes) as read_size, event_time, user FROM system.query_log WHERE type = 'QueryFinish' AND query NOT LIKE '%system.%' ORDER BY event_time DESC LIMIT {$limit} FORMAT JSONEachRow\" 2>/dev/null || echo ''";
            $result = trim(instant_remote_process([$command], $server, false) ?? '');

            $queries = [];
            if ($result) {
                foreach (explode("\n", $result) as $line) {
                    $line = trim($line);
                    if (empty($line)) {
                        continue;
                    }
                    $row = json_decode($line, true);
                    if ($row) {
                        $queries[] = [
                            'query' => mb_substr($row['query'] ?? '', 0, 200),
                            'duration' => round($row['duration_sec'] ?? 0, 3).'s',
                            'rows' => number_format($row['read_rows'] ?? 0),
                            'timestamp' => $row['event_time'] ?? '',
                            'user' => $row['user'] ?? 'default',
                        ];
                    }
                }
            }

            return response()->json([
                'available' => true,
                'queries' => $queries,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'available' => false,
                'error' => 'Failed to fetch query log: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Get ClickHouse merge status.
     */
    public function getClickhouseMergeStatus(string $uuid): JsonResponse
    {
        [$database, $type] = $this->findDatabase($uuid);

        if (! $database || $type !== 'clickhouse') {
            return response()->json(['available' => false, 'error' => 'ClickHouse database not found'], 404);
        }

        $server = $database->destination?->server;

        if (! $server || ! $server->isFunctional()) {
            return response()->json(['available' => false, 'error' => 'Server not reachable']);
        }

        try {
            $containerName = $database->uuid;
            $password = $database->clickhouse_admin_password ?? '';
            $authFlag = $password ? "--password '{$password}'" : '';

            // Get active merges count
            $mergesCommand = "docker exec {$containerName} clickhouse-client {$authFlag} -q \"SELECT count() FROM system.merges\" 2>/dev/null || echo '0'";
            $activeMerges = (int) trim(instant_remote_process([$mergesCommand], $server, false) ?? '0');

            // Get parts count
            $partsCommand = "docker exec {$containerName} clickhouse-client {$authFlag} -q \"SELECT count() FROM system.parts WHERE active = 1\" 2>/dev/null || echo '0'";
            $partsCount = (int) trim(instant_remote_process([$partsCommand], $server, false) ?? '0');

            // Get merge rate (merges in last minute from system.part_log)
            $rateCommand = "docker exec {$containerName} clickhouse-client {$authFlag} -q \"SELECT count() FROM system.part_log WHERE event_type = 'MergeParts' AND event_time > now() - INTERVAL 1 MINUTE\" 2>/dev/null || echo '0'";
            $mergeRate = (int) trim(instant_remote_process([$rateCommand], $server, false) ?? '0');

            return response()->json([
                'available' => true,
                'activeMerges' => $activeMerges,
                'partsCount' => $partsCount,
                'mergeRate' => $mergeRate,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'available' => false,
                'error' => 'Failed to fetch merge status: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Get ClickHouse replication status.
     */
    public function getClickhouseReplicationStatus(string $uuid): JsonResponse
    {
        [$database, $type] = $this->findDatabase($uuid);

        if (! $database || $type !== 'clickhouse') {
            return response()->json(['available' => false, 'error' => 'ClickHouse database not found'], 404);
        }

        $server = $database->destination?->server;

        if (! $server || ! $server->isFunctional()) {
            return response()->json(['available' => false, 'error' => 'Server not reachable']);
        }

        try {
            $containerName = $database->uuid;
            $password = $database->clickhouse_admin_password ?? '';
            $authFlag = $password ? "--password '{$password}'" : '';

            // Check if replication is configured by querying system.replicas
            $replicasCommand = "docker exec {$containerName} clickhouse-client {$authFlag} -q \"SELECT database, table, replica_name, replica_path, is_leader, is_readonly, absolute_delay, last_queue_update FROM system.replicas FORMAT JSONEachRow\" 2>/dev/null || echo ''";
            $result = trim(instant_remote_process([$replicasCommand], $server, false) ?? '');

            $replicas = [];
            $enabled = false;

            if ($result && ! str_contains($result, 'DB::Exception')) {
                foreach (explode("\n", $result) as $line) {
                    $line = trim($line);
                    if (empty($line)) {
                        continue;
                    }
                    $row = json_decode($line, true);
                    if ($row) {
                        $enabled = true;
                        $status = 'Healthy';
                        if ($row['is_readonly'] ?? false) {
                            $status = 'Read-only';
                        }
                        $delay = ($row['absolute_delay'] ?? 0);
                        if ($delay > 60) {
                            $status = 'Delayed';
                        }

                        $replicas[] = [
                            'host' => $row['replica_name'] ?? 'unknown',
                            'database' => $row['database'] ?? '',
                            'table' => $row['table'] ?? '',
                            'status' => $status,
                            'delay' => $delay > 0 ? $delay.'s' : '0ms',
                            'isLeader' => (bool) ($row['is_leader'] ?? false),
                        ];
                    }
                }
            }

            return response()->json([
                'available' => true,
                'enabled' => $enabled,
                'replicas' => $replicas,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'available' => false,
                'error' => 'Failed to fetch replication status: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Get ClickHouse settings.
     */
    public function getClickhouseSettings(string $uuid): JsonResponse
    {
        [$database, $type] = $this->findDatabase($uuid);

        if (! $database || $type !== 'clickhouse') {
            return response()->json(['available' => false, 'error' => 'ClickHouse database not found'], 404);
        }

        $server = $database->destination?->server;

        if (! $server || ! $server->isFunctional()) {
            return response()->json(['available' => false, 'error' => 'Server not reachable']);
        }

        try {
            $containerName = $database->uuid;
            $password = $database->clickhouse_admin_password ?? '';
            $authFlag = $password ? "--password '{$password}'" : '';

            // Get important performance settings
            $settingsToFetch = [
                'max_threads',
                'max_memory_usage',
                'max_concurrent_queries',
                'max_parts_in_total',
                'background_pool_size',
            ];
            $settingsQuery = implode("','", $settingsToFetch);

            $command = "docker exec {$containerName} clickhouse-client {$authFlag} -q \"SELECT name, value FROM system.settings WHERE name IN ('{$settingsQuery}') FORMAT JSONEachRow\" 2>/dev/null || echo ''";
            $result = trim(instant_remote_process([$command], $server, false) ?? '');

            $settings = [
                'maxThreads' => null,
                'maxMemoryUsage' => null,
                'maxConcurrentQueries' => null,
                'maxPartsInTotal' => null,
                'backgroundPoolSize' => null,
                'compressionMethod' => 'LZ4', // Default
            ];

            if ($result) {
                foreach (explode("\n", $result) as $line) {
                    $line = trim($line);
                    if (empty($line)) {
                        continue;
                    }
                    $row = json_decode($line, true);
                    if ($row) {
                        $name = $row['name'] ?? '';
                        $value = $row['value'] ?? '';

                        match ($name) {
                            'max_threads' => $settings['maxThreads'] = (int) $value,
                            'max_memory_usage' => $settings['maxMemoryUsage'] = $this->formatBytes((int) $value),
                            'max_concurrent_queries' => $settings['maxConcurrentQueries'] = (int) $value,
                            'max_parts_in_total' => $settings['maxPartsInTotal'] = (int) $value,
                            'background_pool_size' => $settings['backgroundPoolSize'] = (int) $value,
                            default => null,
                        };
                    }
                }
            }

            // Get compression method
            $compressionCommand = "docker exec {$containerName} clickhouse-client {$authFlag} -q \"SELECT value FROM system.settings WHERE name = 'network_compression_method'\" 2>/dev/null || echo 'LZ4'";
            $compression = trim(instant_remote_process([$compressionCommand], $server, false) ?? 'LZ4');
            $settings['compressionMethod'] = $compression ?: 'LZ4';

            return response()->json([
                'available' => true,
                'settings' => $settings,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'available' => false,
                'error' => 'Failed to fetch settings: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Get MongoDB collections with statistics.
     */
    public function getMongoCollections(string $uuid): JsonResponse
    {
        [$database, $type] = $this->findDatabase($uuid);

        if (! $database || $type !== 'mongodb') {
            return response()->json(['available' => false, 'error' => 'MongoDB database not found'], 404);
        }

        $server = $database->destination?->server;

        if (! $server || ! $server->isFunctional()) {
            return response()->json(['available' => false, 'error' => 'Server not reachable']);
        }

        try {
            $containerName = $database->uuid;
            $dbName = $database->mongo_initdb_database ?? 'admin';

            // Get collections with stats
            $command = "docker exec {$containerName} mongosh --quiet --eval \"JSON.stringify(db.getCollectionInfos().map(c => { const stats = db.getCollection(c.name).stats(); return { name: c.name, documentCount: stats.count || 0, size: stats.size || 0, avgObjSize: stats.avgObjSize || 0 }; }))\" {$dbName} 2>/dev/null || echo '[]'";
            $result = trim(instant_remote_process([$command], $server, false) ?? '[]');

            $collections = [];
            $parsed = json_decode($result, true);

            if (is_array($parsed)) {
                foreach ($parsed as $c) {
                    $collections[] = [
                        'name' => $c['name'] ?? 'unknown',
                        'documentCount' => $c['documentCount'] ?? 0,
                        'size' => $this->formatBytes($c['size'] ?? 0),
                        'avgDocSize' => $this->formatBytes($c['avgObjSize'] ?? 0),
                    ];
                }
            }

            return response()->json([
                'available' => true,
                'collections' => $collections,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'available' => false,
                'error' => 'Failed to fetch collections: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Get MongoDB indexes.
     */
    public function getMongoIndexes(string $uuid): JsonResponse
    {
        [$database, $type] = $this->findDatabase($uuid);

        if (! $database || $type !== 'mongodb') {
            return response()->json(['available' => false, 'error' => 'MongoDB database not found'], 404);
        }

        $server = $database->destination?->server;

        if (! $server || ! $server->isFunctional()) {
            return response()->json(['available' => false, 'error' => 'Server not reachable']);
        }

        try {
            $containerName = $database->uuid;
            $dbName = $database->mongo_initdb_database ?? 'admin';

            // Get indexes for all collections
            $command = "docker exec {$containerName} mongosh --quiet --eval \"
                const indexes = [];
                db.getCollectionNames().forEach(collName => {
                    db.getCollection(collName).getIndexes().forEach(idx => {
                        indexes.push({
                            collection: collName,
                            name: idx.name,
                            fields: Object.keys(idx.key),
                            unique: idx.unique || false,
                            size: 0
                        });
                    });
                });
                JSON.stringify(indexes);
            \" {$dbName} 2>/dev/null || echo '[]'";
            $result = trim(instant_remote_process([$command], $server, false) ?? '[]');

            $indexes = [];
            $parsed = json_decode($result, true);

            if (is_array($parsed)) {
                foreach ($parsed as $idx) {
                    $indexes[] = [
                        'collection' => $idx['collection'] ?? 'unknown',
                        'name' => $idx['name'] ?? '',
                        'fields' => $idx['fields'] ?? [],
                        'unique' => $idx['unique'] ?? false,
                        'size' => 'N/A',
                    ];
                }
            }

            return response()->json([
                'available' => true,
                'indexes' => $indexes,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'available' => false,
                'error' => 'Failed to fetch indexes: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Get MongoDB replica set status.
     */
    public function getMongoReplicaSet(string $uuid): JsonResponse
    {
        [$database, $type] = $this->findDatabase($uuid);

        if (! $database || $type !== 'mongodb') {
            return response()->json(['available' => false, 'error' => 'MongoDB database not found'], 404);
        }

        $server = $database->destination?->server;

        if (! $server || ! $server->isFunctional()) {
            return response()->json(['available' => false, 'error' => 'Server not reachable']);
        }

        try {
            $containerName = $database->uuid;

            // Check replica set status
            $command = "docker exec {$containerName} mongosh --quiet --eval \"
                try {
                    const status = rs.status();
                    JSON.stringify({
                        enabled: true,
                        name: status.set,
                        members: status.members.map(m => ({
                            host: m.name,
                            state: m.stateStr,
                            health: m.health
                        }))
                    });
                } catch (e) {
                    JSON.stringify({ enabled: false, name: null, members: [] });
                }
            \" 2>/dev/null || echo '{\"enabled\": false}'";
            $result = trim(instant_remote_process([$command], $server, false) ?? '{"enabled": false}');

            $replicaSet = json_decode($result, true) ?? ['enabled' => false, 'name' => null, 'members' => []];

            return response()->json([
                'available' => true,
                'replicaSet' => $replicaSet,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'available' => false,
                'error' => 'Failed to fetch replica set status: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Get Redis keys with details.
     */
    public function getRedisKeys(Request $request, string $uuid): JsonResponse
    {
        [$database, $type] = $this->findDatabase($uuid);

        if (! $database || ! in_array($type, ['redis', 'keydb', 'dragonfly'])) {
            return response()->json(['available' => false, 'error' => 'Redis database not found'], 404);
        }

        $server = $database->destination?->server;

        if (! $server || ! $server->isFunctional()) {
            return response()->json(['available' => false, 'error' => 'Server not reachable']);
        }

        try {
            $containerName = $database->uuid;
            $password = $database->redis_password ?? $database->keydb_password ?? $database->dragonfly_password ?? '';
            $authFlag = $password ? "-a '{$password}'" : '';
            $pattern = $request->input('pattern', '*');
            $limit = min((int) $request->input('limit', 100), 500);

            // Get keys matching pattern
            $command = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning KEYS '{$pattern}' 2>/dev/null | head -n {$limit}";
            $result = trim(instant_remote_process([$command], $server, false) ?? '');

            $keys = [];
            if ($result) {
                $keyNames = array_filter(explode("\n", $result), fn ($k) => ! empty(trim($k)));

                foreach (array_slice($keyNames, 0, $limit) as $keyName) {
                    $keyName = trim($keyName);
                    if (empty($keyName)) {
                        continue;
                    }

                    // Get key type
                    $typeCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning TYPE '{$keyName}' 2>/dev/null";
                    $keyType = trim(instant_remote_process([$typeCmd], $server, false) ?? 'unknown');

                    // Get TTL
                    $ttlCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning TTL '{$keyName}' 2>/dev/null";
                    $ttl = (int) trim(instant_remote_process([$ttlCmd], $server, false) ?? '-1');
                    $ttlDisplay = $ttl === -1 ? 'none' : ($ttl === -2 ? 'expired' : $this->formatSeconds($ttl));

                    // Get memory usage
                    $memCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning MEMORY USAGE '{$keyName}' 2>/dev/null";
                    $memUsage = trim(instant_remote_process([$memCmd], $server, false) ?? '0');
                    $size = is_numeric($memUsage) ? $this->formatBytes((int) $memUsage) : 'N/A';

                    $keys[] = [
                        'name' => $keyName,
                        'type' => $keyType,
                        'ttl' => $ttlDisplay,
                        'size' => $size,
                    ];
                }
            }

            return response()->json([
                'available' => true,
                'keys' => $keys,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'available' => false,
                'error' => 'Failed to fetch keys: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Get Redis extended memory info.
     */
    public function getRedisMemory(string $uuid): JsonResponse
    {
        [$database, $type] = $this->findDatabase($uuid);

        if (! $database || ! in_array($type, ['redis', 'keydb', 'dragonfly'])) {
            return response()->json(['available' => false, 'error' => 'Redis database not found'], 404);
        }

        $server = $database->destination?->server;

        if (! $server || ! $server->isFunctional()) {
            return response()->json(['available' => false, 'error' => 'Server not reachable']);
        }

        try {
            $containerName = $database->uuid;
            $password = $database->redis_password ?? $database->keydb_password ?? $database->dragonfly_password ?? '';
            $authFlag = $password ? "-a '{$password}'" : '';

            // Get memory info
            $command = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning INFO memory 2>/dev/null";
            $result = instant_remote_process([$command], $server, false) ?? '';

            $memory = [
                'usedMemory' => 'N/A',
                'peakMemory' => 'N/A',
                'fragmentationRatio' => 'N/A',
                'maxMemory' => 'N/A',
                'evictionPolicy' => 'N/A',
            ];

            if (preg_match('/used_memory_human:(\S+)/', $result, $matches)) {
                $memory['usedMemory'] = $matches[1];
            }
            if (preg_match('/used_memory_peak_human:(\S+)/', $result, $matches)) {
                $memory['peakMemory'] = $matches[1];
            }
            if (preg_match('/mem_fragmentation_ratio:(\S+)/', $result, $matches)) {
                $memory['fragmentationRatio'] = $matches[1];
            }

            // Get max memory and eviction policy
            $configCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning CONFIG GET maxmemory maxmemory-policy 2>/dev/null";
            $configResult = instant_remote_process([$configCmd], $server, false) ?? '';

            if (preg_match('/maxmemory\n(\d+)/', $configResult, $matches)) {
                $maxMem = (int) $matches[1];
                $memory['maxMemory'] = $maxMem > 0 ? $this->formatBytes($maxMem) : 'No limit';
            }
            if (preg_match('/maxmemory-policy\n(\S+)/', $configResult, $matches)) {
                $memory['evictionPolicy'] = $matches[1];
            }

            return response()->json([
                'available' => true,
                'memory' => $memory,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'available' => false,
                'error' => 'Failed to fetch memory info: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Execute Redis FLUSHDB or FLUSHALL command.
     */
    public function redisFlush(Request $request, string $uuid): JsonResponse
    {
        [$database, $type] = $this->findDatabase($uuid);

        if (! $database || ! in_array($type, ['redis', 'keydb', 'dragonfly'])) {
            return response()->json(['success' => false, 'error' => 'Redis database not found'], 404);
        }

        $server = $database->destination?->server;

        if (! $server || ! $server->isFunctional()) {
            return response()->json(['success' => false, 'error' => 'Server not reachable']);
        }

        $flushType = $request->input('type', 'db'); // 'db' or 'all'

        if (! in_array($flushType, ['db', 'all'])) {
            return response()->json(['success' => false, 'error' => 'Invalid flush type']);
        }

        try {
            $containerName = $database->uuid;
            $password = $database->redis_password ?? $database->keydb_password ?? $database->dragonfly_password ?? '';
            $authFlag = $password ? "-a '{$password}'" : '';

            $flushCommand = $flushType === 'all' ? 'FLUSHALL' : 'FLUSHDB';
            $command = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning {$flushCommand} 2>&1";
            $result = trim(instant_remote_process([$command], $server, false) ?? '');

            if (stripos($result, 'OK') !== false) {
                return response()->json([
                    'success' => true,
                    'message' => $flushType === 'all' ? 'All databases flushed' : 'Current database flushed',
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => $result ?: 'Flush command failed',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to flush: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Execute PostgreSQL VACUUM or ANALYZE command.
     */
    public function postgresMaintenace(Request $request, string $uuid): JsonResponse
    {
        [$database, $type] = $this->findDatabase($uuid);

        if (! $database || $type !== 'postgresql') {
            return response()->json(['success' => false, 'error' => 'PostgreSQL database not found'], 404);
        }

        $server = $database->destination?->server;

        if (! $server || ! $server->isFunctional()) {
            return response()->json(['success' => false, 'error' => 'Server not reachable']);
        }

        $operation = $request->input('operation', 'vacuum'); // 'vacuum' or 'analyze'

        if (! in_array($operation, ['vacuum', 'analyze'])) {
            return response()->json(['success' => false, 'error' => 'Invalid operation']);
        }

        try {
            $containerName = $database->uuid;
            $user = $database->postgres_user ?? 'postgres';
            $dbName = $database->postgres_db ?? 'postgres';

            $sql = strtoupper($operation);
            $command = "docker exec {$containerName} psql -U {$user} -d {$dbName} -c \"{$sql};\" 2>&1";
            $result = trim(instant_remote_process([$command], $server, false) ?? '');

            if (stripos($result, 'ERROR') !== false) {
                return response()->json([
                    'success' => false,
                    'error' => $result,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => "{$sql} completed successfully",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to run maintenance: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Format seconds to human readable string.
     */
    protected function formatSeconds(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.'s';
        }
        if ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $secs = $seconds % 60;

            return $secs > 0 ? "{$minutes}m {$secs}s" : "{$minutes}m";
        }
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        return $minutes > 0 ? "{$hours}h {$minutes}m" : "{$hours}h";
    }

    /**
     * Get MySQL/MariaDB settings.
     */
    public function getMysqlSettings(string $uuid): JsonResponse
    {
        [$database, $type] = $this->findDatabase($uuid);

        if (! $database || ! in_array($type, ['mysql', 'mariadb'])) {
            return response()->json(['available' => false, 'error' => 'MySQL/MariaDB database not found'], 404);
        }

        $server = $database->destination?->server;

        if (! $server || ! $server->isFunctional()) {
            return response()->json(['available' => false, 'error' => 'Server not reachable']);
        }

        try {
            $containerName = $database->uuid;
            $password = $database->mysql_root_password ?? $database->mysql_password ?? '';

            $settings = [
                'slowQueryLog' => false,
                'binaryLogging' => false,
                'maxConnections' => null,
                'innodbBufferPoolSize' => null,
                'queryCacheSize' => null,
                'queryTimeout' => null,
            ];

            // Get slow_query_log status
            $slowQueryCmd = "docker exec {$containerName} mysql -u root -p'{$password}' -N -e \"SHOW VARIABLES LIKE 'slow_query_log';\" 2>/dev/null | awk '{print \$2}'";
            $slowQueryLog = trim(instant_remote_process([$slowQueryCmd], $server, false) ?? '');
            $settings['slowQueryLog'] = strtoupper($slowQueryLog) === 'ON';

            // Get log_bin status (binary logging)
            $logBinCmd = "docker exec {$containerName} mysql -u root -p'{$password}' -N -e \"SHOW VARIABLES LIKE 'log_bin';\" 2>/dev/null | awk '{print \$2}'";
            $logBin = trim(instant_remote_process([$logBinCmd], $server, false) ?? '');
            $settings['binaryLogging'] = strtoupper($logBin) === 'ON';

            // Get max_connections
            $maxConnCmd = "docker exec {$containerName} mysql -u root -p'{$password}' -N -e \"SHOW VARIABLES LIKE 'max_connections';\" 2>/dev/null | awk '{print \$2}'";
            $maxConnections = trim(instant_remote_process([$maxConnCmd], $server, false) ?? '');
            if (is_numeric($maxConnections)) {
                $settings['maxConnections'] = (int) $maxConnections;
            }

            // Get innodb_buffer_pool_size
            $bufferPoolCmd = "docker exec {$containerName} mysql -u root -p'{$password}' -N -e \"SHOW VARIABLES LIKE 'innodb_buffer_pool_size';\" 2>/dev/null | awk '{print \$2}'";
            $bufferPoolSize = trim(instant_remote_process([$bufferPoolCmd], $server, false) ?? '');
            if (is_numeric($bufferPoolSize)) {
                $settings['innodbBufferPoolSize'] = $this->formatBytes((int) $bufferPoolSize);
            }

            // Get query_cache_size (deprecated in MySQL 8.0 but still available in MariaDB)
            $queryCacheCmd = "docker exec {$containerName} mysql -u root -p'{$password}' -N -e \"SHOW VARIABLES LIKE 'query_cache_size';\" 2>/dev/null | awk '{print \$2}'";
            $queryCacheSize = trim(instant_remote_process([$queryCacheCmd], $server, false) ?? '');
            if (is_numeric($queryCacheSize)) {
                $settings['queryCacheSize'] = $this->formatBytes((int) $queryCacheSize);
            }

            // Get wait_timeout (query timeout)
            $timeoutCmd = "docker exec {$containerName} mysql -u root -p'{$password}' -N -e \"SHOW VARIABLES LIKE 'wait_timeout';\" 2>/dev/null | awk '{print \$2}'";
            $timeout = trim(instant_remote_process([$timeoutCmd], $server, false) ?? '');
            if (is_numeric($timeout)) {
                $settings['queryTimeout'] = (int) $timeout;
            }

            return response()->json([
                'available' => true,
                'settings' => $settings,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'available' => false,
                'error' => 'Failed to fetch MySQL settings: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Get Redis persistence settings.
     */
    public function getRedisPersistence(string $uuid): JsonResponse
    {
        [$database, $type] = $this->findDatabase($uuid);

        if (! $database || ! in_array($type, ['redis', 'keydb', 'dragonfly'])) {
            return response()->json(['available' => false, 'error' => 'Redis database not found'], 404);
        }

        $server = $database->destination?->server;

        if (! $server || ! $server->isFunctional()) {
            return response()->json(['available' => false, 'error' => 'Server not reachable']);
        }

        try {
            $containerName = $database->uuid;
            $password = $database->redis_password ?? $database->keydb_password ?? $database->dragonfly_password ?? '';
            $authFlag = $password ? "-a '{$password}'" : '';

            $persistence = [
                'rdbEnabled' => false,
                'rdbSaveRules' => [],
                'aofEnabled' => false,
                'aofFsync' => 'everysec',
                'rdbLastSaveTime' => null,
                'rdbLastBgsaveStatus' => 'N/A',
            ];

            // Get CONFIG for persistence settings
            $configCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning CONFIG GET save 2>/dev/null";
            $saveConfig = instant_remote_process([$configCmd], $server, false) ?? '';

            // Parse RDB save rules
            if (preg_match('/save\n(.+)$/m', $saveConfig, $matches)) {
                $saveValue = trim($matches[1]);
                if (! empty($saveValue) && $saveValue !== '""' && $saveValue !== "''") {
                    $persistence['rdbEnabled'] = true;
                    // Parse save rules (e.g., "900 1 300 10 60 10000")
                    $rules = preg_split('/\s+/', $saveValue);
                    $rdbRules = [];
                    for ($i = 0; $i < count($rules) - 1; $i += 2) {
                        if (is_numeric($rules[$i]) && is_numeric($rules[$i + 1])) {
                            $rdbRules[] = [
                                'seconds' => (int) $rules[$i],
                                'changes' => (int) $rules[$i + 1],
                            ];
                        }
                    }
                    $persistence['rdbSaveRules'] = $rdbRules;
                }
            }

            // Get AOF settings
            $aofCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning CONFIG GET appendonly 2>/dev/null";
            $aofConfig = instant_remote_process([$aofCmd], $server, false) ?? '';
            if (preg_match('/appendonly\n(\w+)/m', $aofConfig, $matches)) {
                $persistence['aofEnabled'] = strtolower(trim($matches[1])) === 'yes';
            }

            // Get AOF fsync policy
            $fsyncCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning CONFIG GET appendfsync 2>/dev/null";
            $fsyncConfig = instant_remote_process([$fsyncCmd], $server, false) ?? '';
            if (preg_match('/appendfsync\n(\w+)/m', $fsyncConfig, $matches)) {
                $persistence['aofFsync'] = trim($matches[1]);
            }

            // Get last save time from INFO
            $infoCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning INFO persistence 2>/dev/null";
            $info = instant_remote_process([$infoCmd], $server, false) ?? '';

            if (preg_match('/rdb_last_save_time:(\d+)/', $info, $matches)) {
                $timestamp = (int) $matches[1];
                if ($timestamp > 0) {
                    $persistence['rdbLastSaveTime'] = date('Y-m-d H:i:s', $timestamp);
                }
            }

            if (preg_match('/rdb_last_bgsave_status:(\w+)/', $info, $matches)) {
                $persistence['rdbLastBgsaveStatus'] = $matches[1];
            }

            return response()->json([
                'available' => true,
                'persistence' => $persistence,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'available' => false,
                'error' => 'Failed to fetch Redis persistence settings: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Get MongoDB storage settings.
     */
    public function getMongoStorageSettings(string $uuid): JsonResponse
    {
        [$database, $type] = $this->findDatabase($uuid);

        if (! $database || $type !== 'mongodb') {
            return response()->json(['available' => false, 'error' => 'MongoDB database not found'], 404);
        }

        $server = $database->destination?->server;

        if (! $server || ! $server->isFunctional()) {
            return response()->json(['available' => false, 'error' => 'Server not reachable']);
        }

        try {
            $containerName = $database->uuid;

            $settings = [
                'storageEngine' => 'WiredTiger',
                'cacheSize' => null,
                'journalEnabled' => true,
                'directoryPerDb' => false,
            ];

            // Get server status including storage engine info
            $command = "docker exec {$containerName} mongosh --quiet --eval \"
                const status = db.serverStatus();
                const params = db.adminCommand({getParameter: '*'});
                JSON.stringify({
                    storageEngine: status.storageEngine?.name || 'WiredTiger',
                    cacheSize: status.wiredTiger?.cache ? status.wiredTiger.cache['maximum bytes configured'] : null,
                    journalEnabled: status.dur?.journalEnabled ?? true,
                    directoryPerDb: params.directoryperdb ?? false
                });
            \" 2>/dev/null || echo '{}'";

            $result = trim(instant_remote_process([$command], $server, false) ?? '{}');
            $parsed = json_decode($result, true);

            if ($parsed && is_array($parsed)) {
                $settings['storageEngine'] = $parsed['storageEngine'] ?? 'WiredTiger';

                if (isset($parsed['cacheSize']) && is_numeric($parsed['cacheSize'])) {
                    $settings['cacheSize'] = $this->formatBytes((int) $parsed['cacheSize']);
                } else {
                    $settings['cacheSize'] = 'Default (50% RAM)';
                }

                $settings['journalEnabled'] = $parsed['journalEnabled'] ?? true;
                $settings['directoryPerDb'] = $parsed['directoryPerDb'] ?? false;
            }

            return response()->json([
                'available' => true,
                'settings' => $settings,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'available' => false,
                'error' => 'Failed to fetch MongoDB storage settings: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Toggle PostgreSQL extension.
     */
    public function toggleExtension(Request $request, string $uuid): JsonResponse
    {
        [$database, $type] = $this->findDatabase($uuid);

        if (! $database || $type !== 'postgresql') {
            return response()->json(['success' => false, 'error' => 'PostgreSQL database not found'], 404);
        }

        $server = $database->destination?->server;

        if (! $server || ! $server->isFunctional()) {
            return response()->json(['success' => false, 'error' => 'Server not reachable']);
        }

        $extensionName = $request->input('extension');
        $enable = $request->boolean('enable', true);

        if (! $extensionName || ! preg_match('/^[a-z_][a-z0-9_]*$/i', $extensionName)) {
            return response()->json(['success' => false, 'error' => 'Invalid extension name']);
        }

        try {
            $containerName = $database->uuid;
            $user = $database->postgres_user ?? 'postgres';
            $dbName = $database->postgres_db ?? 'postgres';

            $sql = $enable ? "CREATE EXTENSION IF NOT EXISTS \"{$extensionName}\"" : "DROP EXTENSION IF EXISTS \"{$extensionName}\"";
            $command = "docker exec {$containerName} psql -U {$user} -d {$dbName} -c \"{$sql};\" 2>&1";
            $result = trim(instant_remote_process([$command], $server, false) ?? '');

            // Check for error in result
            if (stripos($result, 'ERROR') !== false) {
                return response()->json([
                    'success' => false,
                    'error' => $result,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => $enable ? "Extension {$extensionName} enabled" : "Extension {$extensionName} disabled",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to toggle extension: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Get active connections for a database.
     */
    public function getActiveConnections(string $uuid): JsonResponse
    {
        [$database, $type] = $this->findDatabase($uuid);

        if (! $database) {
            return response()->json(['available' => false, 'error' => 'Database not found'], 404);
        }

        $server = $database->destination?->server;

        if (! $server || ! $server->isFunctional()) {
            return response()->json(['available' => false, 'error' => 'Server not reachable']);
        }

        try {
            $connections = match ($type) {
                'postgresql' => $this->getPostgresActiveConnections($server, $database),
                'mysql', 'mariadb' => $this->getMysqlActiveConnections($server, $database),
                default => [],
            };

            return response()->json([
                'available' => true,
                'connections' => $connections,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'available' => false,
                'error' => 'Failed to fetch active connections: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Get PostgreSQL active connections.
     */
    protected function getPostgresActiveConnections(mixed $server, mixed $database): array
    {
        $containerName = $database->uuid;
        $user = $database->postgres_user ?? 'postgres';
        $dbName = $database->postgres_db ?? 'postgres';

        $command = "docker exec {$containerName} psql -U {$user} -d {$dbName} -t -A -F '|' -c \"SELECT pid, usename, datname, state, COALESCE(query, '<IDLE>'), COALESCE(EXTRACT(EPOCH FROM (now() - query_start))::text, '0'), COALESCE(client_addr::text, 'local') FROM pg_stat_activity WHERE pid <> pg_backend_pid() ORDER BY query_start DESC NULLS LAST LIMIT 50;\" 2>/dev/null || echo ''";
        $result = trim(instant_remote_process([$command], $server, false) ?? '');

        $connections = [];
        if ($result) {
            $id = 1;
            foreach (explode("\n", $result) as $line) {
                $parts = explode('|', trim($line));
                if (count($parts) >= 5 && ! empty($parts[0])) {
                    $duration = (float) ($parts[5] ?? 0);
                    $connections[] = [
                        'id' => $id++,
                        'pid' => (int) $parts[0],
                        'user' => $parts[1] ?? '',
                        'database' => $parts[2] ?? '',
                        'state' => $parts[3] ?? 'idle',
                        'query' => $parts[4] ?? '<IDLE>',
                        'duration' => $duration < 60 ? round($duration, 3).'s' : round($duration / 60, 1).'m',
                        'clientAddr' => $parts[6] ?? 'local',
                    ];
                }
            }
        }

        return $connections;
    }

    /**
     * Get MySQL/MariaDB active connections.
     */
    protected function getMysqlActiveConnections(mixed $server, mixed $database): array
    {
        $containerName = $database->uuid;
        $password = $database->mysql_root_password ?? $database->mysql_password ?? '';

        $command = "docker exec {$containerName} mysql -u root -p'{$password}' -N -e \"SELECT ID, USER, DB, COMMAND, TIME, INFO, HOST FROM INFORMATION_SCHEMA.PROCESSLIST WHERE COMMAND != 'Daemon' ORDER BY TIME DESC LIMIT 50;\" 2>/dev/null || echo ''";
        $result = trim(instant_remote_process([$command], $server, false) ?? '');

        $connections = [];
        if ($result) {
            $id = 1;
            foreach (explode("\n", $result) as $line) {
                $parts = preg_split('/\t/', trim($line));
                if (count($parts) >= 5 && ! empty($parts[0])) {
                    $time = (int) ($parts[4] ?? 0);
                    $connections[] = [
                        'id' => $id++,
                        'pid' => (int) $parts[0],
                        'user' => $parts[1] ?? '',
                        'database' => $parts[2] ?? '',
                        'state' => strtolower($parts[3] ?? 'idle') === 'query' ? 'active' : 'idle',
                        'query' => $parts[5] ?? '<IDLE>',
                        'duration' => $time < 60 ? $time.'s' : round($time / 60, 1).'m',
                        'clientAddr' => explode(':', $parts[6] ?? '')[0] ?? 'local',
                    ];
                }
            }
        }

        return $connections;
    }

    /**
     * Kill a database connection by PID.
     */
    public function killConnection(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'pid' => 'required|integer',
        ]);

        [$database, $type] = $this->findDatabase($uuid);

        if (! $database) {
            return response()->json(['success' => false, 'error' => 'Database not found'], 404);
        }

        $server = $database->destination?->server;

        if (! $server || ! $server->isFunctional()) {
            return response()->json(['success' => false, 'error' => 'Server not reachable']);
        }

        $pid = (int) $request->input('pid');

        try {
            $containerName = $database->uuid;
            $result = match ($type) {
                'postgresql' => $this->killPostgresConnection($server, $database, $pid),
                'mysql', 'mariadb' => $this->killMysqlConnection($server, $database, $pid),
                default => false,
            };

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => "Connection PID {$pid} terminated",
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => "Failed to terminate connection PID {$pid}",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to kill connection: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Kill PostgreSQL connection.
     */
    protected function killPostgresConnection(mixed $server, mixed $database, int $pid): bool
    {
        $containerName = $database->uuid;
        $user = $database->postgres_user ?? 'postgres';
        $dbName = $database->postgres_db ?? 'postgres';

        $command = "docker exec {$containerName} psql -U {$user} -d {$dbName} -t -c \"SELECT pg_terminate_backend({$pid});\" 2>&1";
        $result = trim(instant_remote_process([$command], $server, false) ?? '');

        return stripos($result, 't') !== false;
    }

    /**
     * Kill MySQL connection.
     */
    protected function killMysqlConnection(mixed $server, mixed $database, int $pid): bool
    {
        $containerName = $database->uuid;
        $password = $database->mysql_root_password ?? $database->mysql_password ?? '';

        $command = "docker exec {$containerName} mysql -u root -p'{$password}' -e \"KILL {$pid};\" 2>&1";
        $result = instant_remote_process([$command], $server, false) ?? '';

        return stripos($result, 'ERROR') === false;
    }

    /**
     * Create a database user.
     */
    public function createUser(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'username' => ['required', 'string', 'max:63', 'regex:/^[a-zA-Z_][a-zA-Z0-9_]*$/'],
            'password' => 'required|string|min:8',
        ]);

        [$database, $type] = $this->findDatabase($uuid);

        if (! $database) {
            return response()->json(['success' => false, 'error' => 'Database not found'], 404);
        }

        $server = $database->destination?->server;

        if (! $server || ! $server->isFunctional()) {
            return response()->json(['success' => false, 'error' => 'Server not reachable']);
        }

        $username = $request->input('username');
        $password = $request->input('password');

        try {
            $result = match ($type) {
                'postgresql' => $this->createPostgresUser($server, $database, $username, $password),
                'mysql', 'mariadb' => $this->createMysqlUser($server, $database, $username, $password),
                default => ['success' => false, 'error' => 'User creation not supported for this database type'],
            };

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to create user: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Create PostgreSQL user.
     */
    protected function createPostgresUser(mixed $server, mixed $database, string $username, string $password): array
    {
        $containerName = $database->uuid;
        $adminUser = $database->postgres_user ?? 'postgres';
        $dbName = $database->postgres_db ?? 'postgres';

        // Escape password for SQL
        $escapedPassword = str_replace("'", "''", $password);

        $command = "docker exec {$containerName} psql -U {$adminUser} -d {$dbName} -c \"CREATE ROLE \\\"{$username}\\\" WITH LOGIN PASSWORD '{$escapedPassword}';\" 2>&1";
        $result = trim(instant_remote_process([$command], $server, false) ?? '');

        if (stripos($result, 'ERROR') !== false) {
            return ['success' => false, 'error' => $result];
        }

        // Grant connect privilege
        $grantCmd = "docker exec {$containerName} psql -U {$adminUser} -d {$dbName} -c \"GRANT CONNECT ON DATABASE \\\"{$dbName}\\\" TO \\\"{$username}\\\";\" 2>&1";
        instant_remote_process([$grantCmd], $server, false);

        return ['success' => true, 'message' => "User {$username} created successfully"];
    }

    /**
     * Create MySQL user.
     */
    protected function createMysqlUser(mixed $server, mixed $database, string $username, string $password): array
    {
        $containerName = $database->uuid;
        $rootPassword = $database->mysql_root_password ?? $database->mysql_password ?? '';

        // Escape password for SQL
        $escapedPassword = str_replace("'", "''", $password);

        $command = "docker exec {$containerName} mysql -u root -p'{$rootPassword}' -e \"CREATE USER '{$username}'@'%' IDENTIFIED BY '{$escapedPassword}';\" 2>&1";
        $result = trim(instant_remote_process([$command], $server, false) ?? '');

        if (stripos($result, 'ERROR') !== false) {
            return ['success' => false, 'error' => $result];
        }

        // Grant basic privileges
        $dbName = $database->mysql_database ?? '';
        if ($dbName) {
            $grantCmd = "docker exec {$containerName} mysql -u root -p'{$rootPassword}' -e \"GRANT ALL PRIVILEGES ON \`{$dbName}\`.* TO '{$username}'@'%'; FLUSH PRIVILEGES;\" 2>&1";
            instant_remote_process([$grantCmd], $server, false);
        }

        return ['success' => true, 'message' => "User {$username} created successfully"];
    }

    /**
     * Delete a database user.
     */
    public function deleteUser(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'username' => ['required', 'string'],
        ]);

        [$database, $type] = $this->findDatabase($uuid);

        if (! $database) {
            return response()->json(['success' => false, 'error' => 'Database not found'], 404);
        }

        $server = $database->destination?->server;

        if (! $server || ! $server->isFunctional()) {
            return response()->json(['success' => false, 'error' => 'Server not reachable']);
        }

        $username = $request->input('username');

        // Prevent deleting admin users
        $protectedUsers = match ($type) {
            'postgresql' => ['postgres'],
            'mysql', 'mariadb' => ['root', 'mysql.sys', 'mysql.session', 'mysql.infoschema'],
            default => [],
        };

        if (in_array($username, $protectedUsers)) {
            return response()->json([
                'success' => false,
                'error' => "Cannot delete system user: {$username}",
            ]);
        }

        try {
            $result = match ($type) {
                'postgresql' => $this->deletePostgresUser($server, $database, $username),
                'mysql', 'mariadb' => $this->deleteMysqlUser($server, $database, $username),
                default => ['success' => false, 'error' => 'User deletion not supported for this database type'],
            };

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to delete user: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Delete PostgreSQL user.
     */
    protected function deletePostgresUser(mixed $server, mixed $database, string $username): array
    {
        $containerName = $database->uuid;
        $adminUser = $database->postgres_user ?? 'postgres';
        $dbName = $database->postgres_db ?? 'postgres';

        // Revoke and drop
        $command = "docker exec {$containerName} psql -U {$adminUser} -d {$dbName} -c \"REVOKE ALL PRIVILEGES ON ALL TABLES IN SCHEMA public FROM \\\"{$username}\\\"; REVOKE ALL ON DATABASE \\\"{$dbName}\\\" FROM \\\"{$username}\\\"; DROP ROLE IF EXISTS \\\"{$username}\\\";\" 2>&1";
        $result = trim(instant_remote_process([$command], $server, false) ?? '');

        if (stripos($result, 'ERROR') !== false) {
            return ['success' => false, 'error' => $result];
        }

        return ['success' => true, 'message' => "User {$username} deleted successfully"];
    }

    /**
     * Delete MySQL user.
     */
    protected function deleteMysqlUser(mixed $server, mixed $database, string $username): array
    {
        $containerName = $database->uuid;
        $rootPassword = $database->mysql_root_password ?? $database->mysql_password ?? '';

        $command = "docker exec {$containerName} mysql -u root -p'{$rootPassword}' -e \"DROP USER IF EXISTS '{$username}'@'%';\" 2>&1";
        $result = trim(instant_remote_process([$command], $server, false) ?? '');

        if (stripos($result, 'ERROR') !== false) {
            return ['success' => false, 'error' => $result];
        }

        return ['success' => true, 'message' => "User {$username} deleted successfully"];
    }

    /**
     * Create a MongoDB index.
     */
    public function createMongoIndex(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'collection' => 'required|string|max:255',
            'fields' => 'required|string',
            'unique' => 'boolean',
        ]);

        [$database, $type] = $this->findDatabase($uuid);

        if (! $database || $type !== 'mongodb') {
            return response()->json(['success' => false, 'error' => 'MongoDB database not found'], 404);
        }

        $server = $database->destination?->server;

        if (! $server || ! $server->isFunctional()) {
            return response()->json(['success' => false, 'error' => 'Server not reachable']);
        }

        try {
            $containerName = $database->uuid;
            $dbName = $database->mongo_initdb_database ?? 'admin';
            $collection = preg_replace('/[^a-zA-Z0-9_.]/', '', $request->input('collection'));
            $fieldsStr = $request->input('fields');
            $unique = $request->boolean('unique', false);

            // Parse fields string (e.g., "field1:1,field2:-1")
            $fieldsParts = explode(',', $fieldsStr);
            $indexSpec = [];
            foreach ($fieldsParts as $part) {
                $kv = explode(':', trim($part));
                $fieldName = trim($kv[0]);
                $direction = isset($kv[1]) ? (int) trim($kv[1]) : 1;
                if (! empty($fieldName) && preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $fieldName)) {
                    $indexSpec[$fieldName] = $direction;
                }
            }

            if (empty($indexSpec)) {
                return response()->json(['success' => false, 'error' => 'Invalid field specification']);
            }

            $indexSpecJson = json_encode($indexSpec);
            $options = $unique ? ', { unique: true }' : '';

            $command = "docker exec {$containerName} mongosh --quiet --eval \"db.getCollection('{$collection}').createIndex({$indexSpecJson}{$options})\" {$dbName} 2>&1";
            $result = trim(instant_remote_process([$command], $server, false) ?? '');

            if (stripos($result, 'error') !== false || stripos($result, 'exception') !== false) {
                return response()->json(['success' => false, 'error' => $result]);
            }

            return response()->json([
                'success' => true,
                'message' => "Index created on {$collection}",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to create index: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Delete a Redis key.
     */
    public function deleteRedisKey(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'key' => 'required|string|max:1024',
        ]);

        [$database, $type] = $this->findDatabase($uuid);

        if (! $database || ! in_array($type, ['redis', 'keydb', 'dragonfly'])) {
            return response()->json(['success' => false, 'error' => 'Redis database not found'], 404);
        }

        $server = $database->destination?->server;

        if (! $server || ! $server->isFunctional()) {
            return response()->json(['success' => false, 'error' => 'Server not reachable']);
        }

        try {
            $containerName = $database->uuid;
            $password = $database->redis_password ?? $database->keydb_password ?? $database->dragonfly_password ?? '';
            $authFlag = $password ? "-a '{$password}'" : '';
            $keyName = $request->input('key');

            // Escape key name for shell
            $escapedKey = str_replace("'", "'\"'\"'", $keyName);

            $command = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning DEL '{$escapedKey}' 2>&1";
            $result = trim(instant_remote_process([$command], $server, false) ?? '');

            if (is_numeric($result) && (int) $result > 0) {
                return response()->json([
                    'success' => true,
                    'message' => "Key '{$keyName}' deleted",
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => "Key not found or could not be deleted: {$result}",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to delete key: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Get list of tables/collections for a database.
     */
    public function getTablesList(string $uuid): JsonResponse
    {
        [$database, $type] = $this->findDatabase($uuid);

        if (! $database) {
            return response()->json(['available' => false, 'error' => 'Database not found'], 404);
        }

        $server = $database->destination?->server;

        if (! $server || ! $server->isFunctional()) {
            return response()->json(['available' => false, 'error' => 'Server not reachable']);
        }

        try {
            $tables = match ($type) {
                'postgresql' => $this->getPostgresTables($server, $database),
                'mysql', 'mariadb' => $this->getMysqlTables($server, $database),
                'mongodb' => $this->getMongoTables($server, $database),
                'clickhouse' => $this->getClickhouseTables($server, $database),
                default => [],
            };

            return response()->json([
                'available' => true,
                'tables' => $tables,
                'type' => $type,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'available' => false,
                'error' => 'Failed to fetch tables: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Get PostgreSQL tables with row count and size.
     */
    protected function getPostgresTables(mixed $server, mixed $database): array
    {
        $containerName = $database->uuid;
        $user = $database->postgres_user ?? 'postgres';
        $dbName = $database->postgres_db ?? 'postgres';

        $command = "docker exec {$containerName} psql -U {$user} -d {$dbName} -t -A -F '|' -c \"SELECT schemaname || '.' || relname, n_live_tup, pg_size_pretty(pg_total_relation_size(schemaname || '.' || relname)) FROM pg_stat_user_tables ORDER BY n_live_tup DESC LIMIT 100;\" 2>/dev/null || echo ''";
        $result = trim(instant_remote_process([$command], $server, false) ?? '');

        $tables = [];
        if ($result) {
            foreach (explode("\n", $result) as $line) {
                $parts = explode('|', trim($line));
                if (count($parts) >= 3 && ! empty($parts[0])) {
                    $tables[] = [
                        'name' => $parts[0],
                        'rows' => (int) ($parts[1] ?? 0),
                        'size' => $parts[2] ?? '0 bytes',
                    ];
                }
            }
        }

        return $tables;
    }

    /**
     * Get MySQL/MariaDB tables with row count and size.
     */
    protected function getMysqlTables(mixed $server, mixed $database): array
    {
        $containerName = $database->uuid;
        $password = $database->mysql_root_password ?? $database->mysql_password ?? '';
        $dbName = $database->mysql_database ?? 'mysql';

        $command = "docker exec {$containerName} mysql -u root -p'{$password}' -N -e \"SELECT TABLE_NAME, TABLE_ROWS, CONCAT(ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024, 1), ' KB') AS size FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '{$dbName}' ORDER BY TABLE_ROWS DESC LIMIT 100;\" 2>/dev/null || echo ''";
        $result = trim(instant_remote_process([$command], $server, false) ?? '');

        $tables = [];
        if ($result) {
            foreach (explode("\n", $result) as $line) {
                $parts = preg_split('/\t/', trim($line));
                if (count($parts) >= 3 && ! empty($parts[0])) {
                    $tables[] = [
                        'name' => $parts[0],
                        'rows' => (int) ($parts[1] ?? 0),
                        'size' => $parts[2] ?? '0 KB',
                    ];
                }
            }
        }

        return $tables;
    }

    /**
     * Get MongoDB collections as "tables".
     */
    protected function getMongoTables(mixed $server, mixed $database): array
    {
        $containerName = $database->uuid;
        $dbName = $database->mongo_initdb_database ?? 'admin';

        $command = "docker exec {$containerName} mongosh --quiet --eval \"JSON.stringify(db.getCollectionInfos().map(c => { const stats = db.getCollection(c.name).stats(); return { name: c.name, count: stats.count || 0, size: stats.size || 0 }; }))\" {$dbName} 2>/dev/null || echo '[]'";
        $result = trim(instant_remote_process([$command], $server, false) ?? '[]');

        $tables = [];
        $parsed = json_decode($result, true);
        if (is_array($parsed)) {
            foreach ($parsed as $c) {
                $tables[] = [
                    'name' => $c['name'] ?? 'unknown',
                    'rows' => $c['count'] ?? 0,
                    'size' => $this->formatBytes($c['size'] ?? 0),
                ];
            }
        }

        return $tables;
    }

    /**
     * Get ClickHouse tables with row count and size.
     */
    protected function getClickhouseTables(mixed $server, mixed $database): array
    {
        $containerName = $database->uuid;
        $password = $database->clickhouse_admin_password ?? '';
        $authFlag = $password ? "--password '{$password}'" : '';

        $command = "docker exec {$containerName} clickhouse-client {$authFlag} -q \"SELECT name, total_rows, formatReadableSize(total_bytes) FROM system.tables WHERE database = currentDatabase() AND total_rows IS NOT NULL ORDER BY total_rows DESC LIMIT 100 FORMAT TabSeparated\" 2>/dev/null || echo ''";
        $result = trim(instant_remote_process([$command], $server, false) ?? '');

        $tables = [];
        if ($result) {
            foreach (explode("\n", $result) as $line) {
                $parts = preg_split('/\t/', trim($line));
                if (count($parts) >= 3 && ! empty($parts[0])) {
                    $tables[] = [
                        'name' => $parts[0],
                        'rows' => (int) ($parts[1] ?? 0),
                        'size' => $parts[2] ?? '0 B',
                    ];
                }
            }
        }

        return $tables;
    }

    /**
     * Test S3 connection for backup storage.
     */
    public function testS3Connection(Request $request): JsonResponse
    {
        $request->validate([
            'bucket' => 'required|string',
            'region' => 'required|string',
            'access_key' => 'required|string',
            'secret_key' => 'required|string',
            'endpoint' => 'nullable|string',
        ]);

        try {
            $config = [
                'driver' => 's3',
                'key' => $request->input('access_key'),
                'secret' => $request->input('secret_key'),
                'region' => $request->input('region'),
                'bucket' => $request->input('bucket'),
            ];

            if ($request->input('endpoint')) {
                $config['endpoint'] = $request->input('endpoint');
                $config['use_path_style_endpoint'] = true;
            }

            // Test by trying to list objects (limit 1)
            $disk = \Illuminate\Support\Facades\Storage::build($config);
            $disk->files('/', false);

            return response()->json([
                'success' => true,
                'message' => 'S3 connection successful',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'S3 connection failed: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Regenerate password for a database.
     *
     * Generates a new random password, updates the database model,
     * and restarts the container to apply the new credentials.
     */
    public function regeneratePassword(string $uuid): JsonResponse
    {
        [$database, $type] = $this->findDatabase($uuid);

        if (! $database) {
            return response()->json(['success' => false, 'error' => 'Database not found'], 404);
        }

        $server = $database->destination?->server;

        if (! $server || ! $server->isFunctional()) {
            return response()->json(['success' => false, 'error' => 'Server not reachable']);
        }

        try {
            $newPassword = \Illuminate\Support\Str::password(32);

            // Update password field based on database type
            $passwordField = match ($type) {
                'postgresql' => 'postgres_password',
                'mysql' => 'mysql_password',
                'mariadb' => 'mariadb_password',
                'mongodb' => 'mongo_initdb_root_password',
                'redis' => 'redis_password',
                'keydb' => 'keydb_password',
                'dragonfly' => 'dragonfly_password',
                'clickhouse' => 'clickhouse_admin_password',
                default => null,
            };

            if (! $passwordField) {
                return response()->json(['success' => false, 'error' => 'Unsupported database type']);
            }

            $database->{$passwordField} = $newPassword;
            $database->save();

            // Restart the database container to apply new credentials
            $containerName = $database->uuid;
            $restartCommand = "docker restart {$containerName} 2>&1";
            instant_remote_process([$restartCommand], $server, false);

            return response()->json([
                'success' => true,
                'message' => 'Password regenerated and database restarting. Services using this database will need redeployment.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to regenerate password: '.$e->getMessage(),
            ]);
        }
    }
}
