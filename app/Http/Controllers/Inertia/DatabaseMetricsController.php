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
        $containerName = escapeshellarg($database->uuid);
        $user = escapeshellarg($database->postgres_user ?? 'postgres');
        $dbName = escapeshellarg($database->postgres_db ?? 'postgres');
        $dbNameRaw = $database->postgres_db ?? 'postgres';

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

            // Get database size - use escaped dbName in SQL query
            $escapedDbNameSql = addslashes($dbNameRaw);
            $sizeCommand = "docker exec {$containerName} psql -U {$user} -d {$dbName} -t -c \"SELECT pg_size_pretty(pg_database_size('{$escapedDbNameSql}'));\" 2>/dev/null || echo 'N/A'";
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
        $containerName = escapeshellarg($database->uuid);
        $password = $database->mysql_root_password ?? $database->mysql_password ?? '';
        $escapedPassword = escapeshellarg($password);

        $metrics = [
            'activeConnections' => null,
            'maxConnections' => 150,
            'databaseSize' => 'N/A',
            'queriesPerSec' => null,
            'slowQueries' => null,
        ];

        try {
            // Get active connections
            $connCommand = "docker exec {$containerName} mysql -u root -p{$escapedPassword} -N -e \"SHOW STATUS LIKE 'Threads_connected';\" 2>/dev/null | awk '{print \$2}' || echo 'N/A'";
            $connections = trim(instant_remote_process([$connCommand], $server, false) ?? '');
            if (is_numeric($connections)) {
                $metrics['activeConnections'] = (int) $connections;
            }

            // Get max connections
            $maxConnCommand = "docker exec {$containerName} mysql -u root -p{$escapedPassword} -N -e \"SHOW VARIABLES LIKE 'max_connections';\" 2>/dev/null | awk '{print \$2}' || echo '150'";
            $maxConnections = trim(instant_remote_process([$maxConnCommand], $server, false) ?? '150');
            if (is_numeric($maxConnections)) {
                $metrics['maxConnections'] = (int) $maxConnections;
            }

            // Get slow queries
            $slowCommand = "docker exec {$containerName} mysql -u root -p{$escapedPassword} -N -e \"SHOW STATUS LIKE 'Slow_queries';\" 2>/dev/null | awk '{print \$2}' || echo 'N/A'";
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
            $escapedContainerName = escapeshellarg($containerName);
            $authFlag = $password ? '-a '.escapeshellarg($password) : '';

            // Get Redis INFO
            $infoCommand = "docker exec {$escapedContainerName} redis-cli {$authFlag} INFO 2>/dev/null || echo ''";
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
        $containerName = escapeshellarg($database->uuid);
        $user = escapeshellarg($database->postgres_user ?? 'postgres');
        $dbName = escapeshellarg($database->postgres_db ?? 'postgres');

        // Escape query for shell using escapeshellarg for safety
        $escapedQuery = escapeshellarg($query);

        // Execute query with pipe-delimited output format
        $command = "docker exec {$containerName} psql -U {$user} -d {$dbName} -t -A -F '|' -c {$escapedQuery} 2>&1";
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
        $containerName = escapeshellarg($database->uuid);
        $password = $database->mysql_root_password ?? $database->mysql_password ?? '';
        $escapedPassword = escapeshellarg($password);

        // Escape query for shell using escapeshellarg for safety
        $escapedQuery = escapeshellarg($query);

        // Execute query with tab-delimited output
        $command = "docker exec {$containerName} mysql -u root -p{$escapedPassword} -N -B -e {$escapedQuery} 2>&1";
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
        $containerName = escapeshellarg($database->uuid);
        $password = $database->clickhouse_admin_password ?? '';

        // Escape query for shell using escapeshellarg for safety
        $escapedQuery = escapeshellarg($query);
        $authFlag = $password ? '--password '.escapeshellarg($password) : '';

        // Execute query with TabSeparated format
        $command = "docker exec {$containerName} clickhouse-client {$authFlag} -q {$escapedQuery} 2>&1";
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
            $containerName = escapeshellarg($database->uuid);
            $password = $database->redis_password ?? $database->keydb_password ?? $database->dragonfly_password ?? '';
            $authFlag = $password ? '-a '.escapeshellarg($password) : '';
            $pattern = $request->input('pattern', '*');
            // Validate pattern to prevent command injection - allow only safe Redis glob patterns
            if (! preg_match('/^[a-zA-Z0-9_:.*?\[\]-]+$/', $pattern)) {
                return response()->json(['available' => false, 'error' => 'Invalid pattern format']);
            }
            $escapedPattern = escapeshellarg($pattern);
            $limit = min((int) $request->input('limit', 100), 500);

            // Get keys matching pattern
            $command = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning KEYS {$escapedPattern} 2>/dev/null | head -n {$limit}";
            $result = trim(instant_remote_process([$command], $server, false) ?? '');

            $keys = [];
            if ($result) {
                $keyNames = array_filter(explode("\n", $result), fn ($k) => ! empty(trim($k)));

                foreach (array_slice($keyNames, 0, $limit) as $keyName) {
                    $keyName = trim($keyName);
                    if (empty($keyName)) {
                        continue;
                    }

                    $escapedKeyName = escapeshellarg($keyName);

                    // Get key type
                    $typeCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning TYPE {$escapedKeyName} 2>/dev/null";
                    $keyType = trim(instant_remote_process([$typeCmd], $server, false) ?? 'unknown');

                    // Get TTL
                    $ttlCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning TTL {$escapedKeyName} 2>/dev/null";
                    $ttl = (int) trim(instant_remote_process([$ttlCmd], $server, false) ?? '-1');
                    $ttlDisplay = $ttl === -1 ? 'none' : ($ttl === -2 ? 'expired' : $this->formatSeconds($ttl));

                    // Get memory usage
                    $memCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning MEMORY USAGE {$escapedKeyName} 2>/dev/null";
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
            $containerName = escapeshellarg($database->uuid);
            $password = $database->redis_password ?? $database->keydb_password ?? $database->dragonfly_password ?? '';
            $authFlag = $password ? '-a '.escapeshellarg($password) : '';

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
            $containerName = escapeshellarg($database->uuid);
            $password = $database->redis_password ?? $database->keydb_password ?? $database->dragonfly_password ?? '';
            $authFlag = $password ? '-a '.escapeshellarg($password) : '';

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
            $containerName = escapeshellarg($database->uuid);
            $user = escapeshellarg($database->postgres_user ?? 'postgres');
            $dbName = escapeshellarg($database->postgres_db ?? 'postgres');

            $sql = strtoupper($operation);
            $escapedSql = escapeshellarg("{$sql};");
            $command = "docker exec {$containerName} psql -U {$user} -d {$dbName} -c {$escapedSql} 2>&1";
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
            $containerName = escapeshellarg($database->uuid);
            $password = $database->redis_password ?? $database->keydb_password ?? $database->dragonfly_password ?? '';
            $authFlag = $password ? '-a '.escapeshellarg($password) : '';

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
        $containerName = escapeshellarg($database->uuid);
        $adminUser = escapeshellarg($database->postgres_user ?? 'postgres');
        $dbName = $database->postgres_db ?? 'postgres';
        $escapedDbName = escapeshellarg($dbName);

        // Validate username format (alphanumeric, underscore only)
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $username)) {
            return ['success' => false, 'error' => 'Invalid username format'];
        }

        // Escape password for SQL (double single quotes)
        $escapedPasswordSql = str_replace("'", "''", $password);

        // Build SQL command and escape for shell
        $createSql = "CREATE ROLE \"{$username}\" WITH LOGIN PASSWORD '{$escapedPasswordSql}';";
        $escapedCreateSql = escapeshellarg($createSql);

        $command = "docker exec {$containerName} psql -U {$adminUser} -d {$escapedDbName} -c {$escapedCreateSql} 2>&1";
        $result = trim(instant_remote_process([$command], $server, false) ?? '');

        if (stripos($result, 'ERROR') !== false) {
            return ['success' => false, 'error' => $result];
        }

        // Grant connect privilege
        $grantSql = "GRANT CONNECT ON DATABASE \"{$dbName}\" TO \"{$username}\";";
        $escapedGrantSql = escapeshellarg($grantSql);
        $grantCmd = "docker exec {$containerName} psql -U {$adminUser} -d {$escapedDbName} -c {$escapedGrantSql} 2>&1";
        instant_remote_process([$grantCmd], $server, false);

        return ['success' => true, 'message' => "User {$username} created successfully"];
    }

    /**
     * Create MySQL user.
     */
    protected function createMysqlUser(mixed $server, mixed $database, string $username, string $password): array
    {
        $containerName = escapeshellarg($database->uuid);
        $rootPassword = $database->mysql_root_password ?? $database->mysql_password ?? '';
        $escapedRootPassword = escapeshellarg($rootPassword);

        // Validate username format (alphanumeric, underscore only)
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $username)) {
            return ['success' => false, 'error' => 'Invalid username format'];
        }

        // Escape password for MySQL (escape single quotes and backslashes)
        $escapedPasswordSql = str_replace(['\\', "'"], ['\\\\', "\\'"], $password);

        // Build SQL command and escape for shell
        $createSql = "CREATE USER '{$username}'@'%' IDENTIFIED BY '{$escapedPasswordSql}';";
        $escapedCreateSql = escapeshellarg($createSql);

        $command = "docker exec {$containerName} mysql -u root -p{$escapedRootPassword} -e {$escapedCreateSql} 2>&1";
        $result = trim(instant_remote_process([$command], $server, false) ?? '');

        if (stripos($result, 'ERROR') !== false) {
            return ['success' => false, 'error' => $result];
        }

        // Grant basic privileges
        $dbName = $database->mysql_database ?? '';
        if ($dbName) {
            // Validate database name format
            if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $dbName)) {
                return ['success' => true, 'message' => "User {$username} created (grant skipped - invalid db name format)"];
            }
            $grantSql = "GRANT ALL PRIVILEGES ON \`{$dbName}\`.* TO '{$username}'@'%'; FLUSH PRIVILEGES;";
            $escapedGrantSql = escapeshellarg($grantSql);
            $grantCmd = "docker exec {$containerName} mysql -u root -p{$escapedRootPassword} -e {$escapedGrantSql} 2>&1";
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
        $containerName = escapeshellarg($database->uuid);
        $adminUser = escapeshellarg($database->postgres_user ?? 'postgres');
        $dbName = $database->postgres_db ?? 'postgres';
        $escapedDbName = escapeshellarg($dbName);

        // Validate username format (alphanumeric, underscore only)
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $username)) {
            return ['success' => false, 'error' => 'Invalid username format'];
        }

        // Revoke and drop - build SQL and escape for shell
        $sql = "REVOKE ALL PRIVILEGES ON ALL TABLES IN SCHEMA public FROM \"{$username}\"; REVOKE ALL ON DATABASE \"{$dbName}\" FROM \"{$username}\"; DROP ROLE IF EXISTS \"{$username}\";";
        $escapedSql = escapeshellarg($sql);

        $command = "docker exec {$containerName} psql -U {$adminUser} -d {$escapedDbName} -c {$escapedSql} 2>&1";
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
        $containerName = escapeshellarg($database->uuid);
        $rootPassword = $database->mysql_root_password ?? $database->mysql_password ?? '';
        $escapedRootPassword = escapeshellarg($rootPassword);

        // Validate username format (alphanumeric, underscore only)
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $username)) {
            return ['success' => false, 'error' => 'Invalid username format'];
        }

        $sql = "DROP USER IF EXISTS '{$username}'@'%';";
        $escapedSql = escapeshellarg($sql);

        $command = "docker exec {$containerName} mysql -u root -p{$escapedRootPassword} -e {$escapedSql} 2>&1";
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
            $containerName = escapeshellarg($database->uuid);
            $dbName = $database->mongo_initdb_database ?? 'admin';
            $escapedDbName = escapeshellarg($dbName);
            $collection = preg_replace('/[^a-zA-Z0-9_.]/', '', $request->input('collection'));
            $fieldsStr = $request->input('fields');
            $unique = $request->boolean('unique', false);

            // Validate collection name
            if (empty($collection) || ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $collection)) {
                return response()->json(['success' => false, 'error' => 'Invalid collection name']);
            }

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

            // Build mongosh command and escape for shell
            $mongoCommand = "db.getCollection('{$collection}').createIndex({$indexSpecJson}{$options})";
            $escapedMongoCommand = escapeshellarg($mongoCommand);

            $command = "docker exec {$containerName} mongosh --quiet --eval {$escapedMongoCommand} {$escapedDbName} 2>&1";
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
            $containerName = escapeshellarg($database->uuid);
            $password = $database->redis_password ?? $database->keydb_password ?? $database->dragonfly_password ?? '';
            $authFlag = $password ? '-a '.escapeshellarg($password) : '';
            $keyName = $request->input('key');

            // Escape key name for shell using escapeshellarg for safety
            $escapedKey = escapeshellarg($keyName);

            $command = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning DEL {$escapedKey} 2>&1";
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

    /**
     * Get table columns schema.
     */
    public function getTableColumns(string $uuid, string $tableName): JsonResponse
    {
        [$database, $type] = $this->findDatabase($uuid);

        if (! $database) {
            return response()->json(['success' => false, 'error' => 'Database not found'], 404);
        }

        $server = $database->destination?->server;
        if (! $server || ! $server->isFunctional()) {
            return response()->json(['success' => false, 'error' => 'Server not reachable'], 503);
        }

        try {
            $columns = match ($type) {
                'postgresql' => $this->getPostgresColumns($server, $database, $tableName),
                'mysql', 'mariadb' => $this->getMysqlColumns($server, $database, $tableName),
                'mongodb' => $this->getMongoColumns($server, $database, $tableName),
                default => [],
            };

            return response()->json([
                'success' => true,
                'columns' => $columns,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch columns: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get table data with pagination and filtering.
     */
    public function getTableData(Request $request, string $uuid, string $tableName): JsonResponse
    {
        [$database, $type] = $this->findDatabase($uuid);

        if (! $database) {
            return response()->json(['success' => false, 'error' => 'Database not found'], 404);
        }

        $server = $database->destination?->server;
        if (! $server || ! $server->isFunctional()) {
            return response()->json(['success' => false, 'error' => 'Server not reachable'], 503);
        }

        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(100, max(10, (int) $request->input('per_page', 50)));
        $search = $request->input('search', '');
        $orderBy = $request->input('order_by', '');
        $orderDir = $request->input('order_dir', 'asc') === 'desc' ? 'desc' : 'asc';

        try {
            $result = match ($type) {
                'postgresql' => $this->getPostgresData($server, $database, $tableName, $page, $perPage, $search, $orderBy, $orderDir),
                'mysql', 'mariadb' => $this->getMysqlData($server, $database, $tableName, $page, $perPage, $search, $orderBy, $orderDir),
                'mongodb' => $this->getMongoData($server, $database, $tableName, $page, $perPage, $search, $orderBy, $orderDir),
                default => ['rows' => [], 'total' => 0, 'columns' => []],
            };

            return response()->json([
                'success' => true,
                'data' => $result['rows'],
                'columns' => $result['columns'],
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $result['total'],
                    'last_page' => ceil($result['total'] / $perPage),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch data: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a table row.
     */
    public function updateTableRow(Request $request, string $uuid, string $tableName): JsonResponse
    {
        [$database, $type] = $this->findDatabase($uuid);

        if (! $database) {
            return response()->json(['success' => false, 'error' => 'Database not found'], 404);
        }

        $server = $database->destination?->server;
        if (! $server || ! $server->isFunctional()) {
            return response()->json(['success' => false, 'error' => 'Server not reachable'], 503);
        }

        $request->validate([
            'primary_key' => 'required|array',
            'updates' => 'required|array',
        ]);

        try {
            $success = match ($type) {
                'postgresql' => $this->updatePostgresRow($server, $database, $tableName, $request->input('primary_key'), $request->input('updates')),
                'mysql', 'mariadb' => $this->updateMysqlRow($server, $database, $tableName, $request->input('primary_key'), $request->input('updates')),
                'mongodb' => $this->updateMongoRow($server, $database, $tableName, $request->input('primary_key'), $request->input('updates')),
                default => false,
            };

            if ($success) {
                return response()->json(['success' => true, 'message' => 'Row updated successfully']);
            }

            return response()->json(['success' => false, 'error' => 'Failed to update row'], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to update row: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a table row.
     */
    public function deleteTableRow(Request $request, string $uuid, string $tableName): JsonResponse
    {
        [$database, $type] = $this->findDatabase($uuid);

        if (! $database) {
            return response()->json(['success' => false, 'error' => 'Database not found'], 404);
        }

        $server = $database->destination?->server;
        if (! $server || ! $server->isFunctional()) {
            return response()->json(['success' => false, 'error' => 'Server not reachable'], 503);
        }

        $request->validate([
            'primary_key' => 'required|array',
        ]);

        try {
            $success = match ($type) {
                'postgresql' => $this->deletePostgresRow($server, $database, $tableName, $request->input('primary_key')),
                'mysql', 'mariadb' => $this->deleteMysqlRow($server, $database, $tableName, $request->input('primary_key')),
                default => false,
            };

            if ($success) {
                return response()->json(['success' => true, 'message' => 'Row deleted successfully']);
            }

            return response()->json(['success' => false, 'error' => 'Failed to delete row'], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to delete row: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new table row.
     */
    public function createTableRow(Request $request, string $uuid, string $tableName): JsonResponse
    {
        [$database, $type] = $this->findDatabase($uuid);

        if (! $database) {
            return response()->json(['success' => false, 'error' => 'Database not found'], 404);
        }

        $server = $database->destination?->server;
        if (! $server || ! $server->isFunctional()) {
            return response()->json(['success' => false, 'error' => 'Server not reachable'], 503);
        }

        $request->validate([
            'data' => 'required|array',
        ]);

        try {
            $success = match ($type) {
                'postgresql' => $this->createPostgresRow($server, $database, $tableName, $request->input('data')),
                'mysql', 'mariadb' => $this->createMysqlRow($server, $database, $tableName, $request->input('data')),
                default => false,
            };

            if ($success) {
                return response()->json(['success' => true, 'message' => 'Row created successfully']);
            }

            return response()->json(['success' => false, 'error' => 'Failed to create row'], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to create row: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get PostgreSQL column schema.
     */
    protected function getPostgresColumns(mixed $server, mixed $database, string $tableName): array
    {
        $containerName = $database->uuid;
        $user = $database->postgres_user ?? 'postgres';
        $dbName = $database->postgres_db ?? 'postgres';

        // Escape table name for schema.table format
        $escapedTable = str_contains($tableName, '.') ? $tableName : "public.{$tableName}";
        [$schema, $table] = str_contains($tableName, '.') ? explode('.', $tableName, 2) : ['public', $tableName];

        $query = "SELECT
            column_name,
            data_type,
            is_nullable,
            column_default,
            (SELECT count(*) FROM information_schema.key_column_usage kcu
             JOIN information_schema.table_constraints tc
             ON kcu.constraint_name = tc.constraint_name
             WHERE tc.constraint_type = 'PRIMARY KEY'
             AND kcu.table_schema = '{$schema}'
             AND kcu.table_name = '{$table}'
             AND kcu.column_name = c.column_name) as is_primary
        FROM information_schema.columns c
        WHERE table_schema = '{$schema}' AND table_name = '{$table}'
        ORDER BY ordinal_position";

        $command = "docker exec {$containerName} psql -U {$user} -d {$dbName} -t -A -F '|' -c \"{$query}\" 2>/dev/null || echo ''";
        $result = trim(instant_remote_process([$command], $server, false) ?? '');

        $columns = [];
        if ($result) {
            foreach (explode("\n", $result) as $line) {
                $parts = explode('|', trim($line));
                if (count($parts) >= 5) {
                    $columns[] = [
                        'name' => $parts[0],
                        'type' => $parts[1],
                        'nullable' => $parts[2] === 'YES',
                        'default' => $parts[3] !== '' ? $parts[3] : null,
                        'is_primary' => (int) $parts[4] > 0,
                    ];
                }
            }
        }

        return $columns;
    }

    /**
     * Get PostgreSQL table data with pagination.
     */
    protected function getPostgresData(mixed $server, mixed $database, string $tableName, int $page, int $perPage, string $search, string $orderBy, string $orderDir): array
    {
        $containerName = $database->uuid;
        $user = $database->postgres_user ?? 'postgres';
        $dbName = $database->postgres_db ?? 'postgres';
        $offset = ($page - 1) * $perPage;

        // Get columns first
        $columns = $this->getPostgresColumns($server, $database, $tableName);
        $columnNames = array_map(fn ($c) => $c['name'], $columns);

        // Build WHERE clause for search if provided
        $whereClause = '';
        if ($search !== '') {
            $searchConditions = array_map(fn ($col) => "CAST({$col} AS TEXT) ILIKE '%{$search}%'", $columnNames);
            $whereClause = 'WHERE '.implode(' OR ', $searchConditions);
        }

        // Build ORDER BY clause
        $orderClause = '';
        if ($orderBy !== '' && in_array($orderBy, $columnNames)) {
            $orderClause = "ORDER BY \"{$orderBy}\" {$orderDir}";
        }

        // Get total count
        $countQuery = "SELECT COUNT(*) FROM {$tableName} {$whereClause}";
        $countCommand = "docker exec {$containerName} psql -U {$user} -d {$dbName} -t -A -c \"{$countQuery}\" 2>/dev/null || echo '0'";
        $total = (int) trim(instant_remote_process([$countCommand], $server, false) ?? '0');

        // Get data
        $dataQuery = "SELECT * FROM {$tableName} {$whereClause} {$orderClause} LIMIT {$perPage} OFFSET {$offset}";
        $dataCommand = "docker exec {$containerName} psql -U {$user} -d {$dbName} -t -A -F '|' -c \"{$dataQuery}\" 2>/dev/null || echo ''";
        $result = trim(instant_remote_process([$dataCommand], $server, false) ?? '');

        $rows = [];
        if ($result) {
            foreach (explode("\n", $result) as $line) {
                $values = explode('|', $line);
                if (count($values) === count($columnNames)) {
                    $row = [];
                    foreach ($columnNames as $i => $colName) {
                        $row[$colName] = $values[$i] !== '' ? $values[$i] : null;
                    }
                    $rows[] = $row;
                }
            }
        }

        return [
            'rows' => $rows,
            'total' => $total,
            'columns' => $columns,
        ];
    }

    /**
     * Update PostgreSQL row.
     */
    protected function updatePostgresRow(mixed $server, mixed $database, string $tableName, array $primaryKey, array $updates): bool
    {
        $containerName = $database->uuid;
        $user = $database->postgres_user ?? 'postgres';
        $dbName = $database->postgres_db ?? 'postgres';

        // Build SET clause
        $setClauses = [];
        foreach ($updates as $column => $value) {
            $escapedValue = str_replace("'", "''", (string) $value);
            $setClauses[] = "\"{$column}\" = '{$escapedValue}'";
        }
        $setClause = implode(', ', $setClauses);

        // Build WHERE clause from primary key
        $whereClauses = [];
        foreach ($primaryKey as $column => $value) {
            $escapedValue = str_replace("'", "''", (string) $value);
            $whereClauses[] = "\"{$column}\" = '{$escapedValue}'";
        }
        $whereClause = implode(' AND ', $whereClauses);

        $query = "UPDATE {$tableName} SET {$setClause} WHERE {$whereClause}";
        $command = "docker exec {$containerName} psql -U {$user} -d {$dbName} -c \"{$query}\" 2>&1";
        $result = instant_remote_process([$command], $server, false);

        return str_contains($result ?? '', 'UPDATE');
    }

    /**
     * Delete PostgreSQL row.
     */
    protected function deletePostgresRow(mixed $server, mixed $database, string $tableName, array $primaryKey): bool
    {
        $containerName = $database->uuid;
        $user = $database->postgres_user ?? 'postgres';
        $dbName = $database->postgres_db ?? 'postgres';

        // Build WHERE clause from primary key
        $whereClauses = [];
        foreach ($primaryKey as $column => $value) {
            $escapedValue = str_replace("'", "''", (string) $value);
            $whereClauses[] = "\"{$column}\" = '{$escapedValue}'";
        }
        $whereClause = implode(' AND ', $whereClauses);

        $query = "DELETE FROM {$tableName} WHERE {$whereClause}";
        $command = "docker exec {$containerName} psql -U {$user} -d {$dbName} -c \"{$query}\" 2>&1";
        $result = instant_remote_process([$command], $server, false);

        return str_contains($result ?? '', 'DELETE');
    }

    /**
     * Create PostgreSQL row.
     */
    protected function createPostgresRow(mixed $server, mixed $database, string $tableName, array $data): bool
    {
        $containerName = $database->uuid;
        $user = $database->postgres_user ?? 'postgres';
        $dbName = $database->postgres_db ?? 'postgres';

        // Build columns and values
        $columns = array_keys($data);
        $values = array_map(function ($value) {
            if ($value === null) {
                return 'NULL';
            }
            $escapedValue = str_replace("'", "''", (string) $value);

            return "'{$escapedValue}'";
        }, array_values($data));

        $columnsClause = '"'.implode('", "', $columns).'"';
        $valuesClause = implode(', ', $values);

        $query = "INSERT INTO {$tableName} ({$columnsClause}) VALUES ({$valuesClause})";
        $command = "docker exec {$containerName} psql -U {$user} -d {$dbName} -c \"{$query}\" 2>&1";
        $result = instant_remote_process([$command], $server, false);

        return str_contains($result ?? '', 'INSERT');
    }

    /**
     * Get MySQL column schema (placeholder for future implementation).
     */
    protected function getMysqlColumns(mixed $server, mixed $database, string $tableName): array
    {
        $containerName = $database->uuid;
        $password = $database->mysql_root_password ?? $database->mysql_password ?? '';
        $dbName = $database->mysql_database ?? 'mysql';

        // Escape table and database names to prevent SQL injection
        $escapedDbName = str_replace("'", "''", $dbName);
        $escapedTableName = str_replace("'", "''", $tableName);

        $query = "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, (CASE WHEN COLUMN_KEY = 'PRI' THEN 1 ELSE 0 END) as is_primary FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '{$escapedDbName}' AND TABLE_NAME = '{$escapedTableName}' ORDER BY ORDINAL_POSITION";

        $command = "docker exec {$containerName} mysql -u root -p'{$password}' -D {$dbName} -N -e \"{$query}\" 2>/dev/null | awk -F'\\t' '{print \$1\"|\"\$2\"|\"\$3\"|\"\$4\"|\"\$5}' || echo ''";
        $result = trim(instant_remote_process([$command], $server, false) ?? '');

        $columns = [];
        if ($result) {
            foreach (explode("\n", $result) as $line) {
                $parts = explode('|', trim($line));
                if (count($parts) >= 5) {
                    $columns[] = [
                        'name' => $parts[0],
                        'type' => $parts[1],
                        'nullable' => $parts[2] === 'YES',
                        'default' => $parts[3] !== 'NULL' && $parts[3] !== '' ? $parts[3] : null,
                        'is_primary' => (int) $parts[4] > 0,
                    ];
                }
            }
        }

        return $columns;
    }

    /**
     * Get MySQL table data (placeholder for future implementation).
     */
    protected function getMysqlData(mixed $server, mixed $database, string $tableName, int $page, int $perPage, string $search, string $orderBy, string $orderDir): array
    {
        $containerName = $database->uuid;
        $password = $database->mysql_root_password ?? $database->mysql_password ?? '';
        $dbName = $database->mysql_database ?? 'mysql';
        $offset = ($page - 1) * $perPage;

        // Get columns first
        $columns = $this->getMysqlColumns($server, $database, $tableName);
        $columnNames = array_map(fn ($c) => $c['name'], $columns);

        // Escape table name to prevent SQL injection
        $escapedTableName = str_replace('`', '``', $tableName);

        // Build WHERE clause for search (MySQL uses LIKE, case-insensitive via LOWER)
        $whereClause = '';
        if ($search !== '') {
            $escapedSearch = str_replace("'", "''", $search);
            $searchConditions = array_map(fn ($col) => "LOWER(CAST(`{$col}` AS CHAR)) LIKE LOWER('%{$escapedSearch}%')", $columnNames);
            $whereClause = 'WHERE '.implode(' OR ', $searchConditions);
        }

        // Build ORDER BY clause
        $orderClause = '';
        if ($orderBy !== '' && in_array($orderBy, $columnNames)) {
            $orderClause = "ORDER BY `{$orderBy}` {$orderDir}";
        }

        // Get total count
        $countQuery = "SELECT COUNT(*) FROM `{$escapedTableName}` {$whereClause}";
        $countCommand = "docker exec {$containerName} mysql -u root -p'{$password}' -D {$dbName} -N -e \"{$countQuery}\" 2>/dev/null || echo '0'";
        $total = (int) trim(instant_remote_process([$countCommand], $server, false) ?? '0');

        // Get data
        $dataQuery = "SELECT * FROM `{$escapedTableName}` {$whereClause} {$orderClause} LIMIT {$perPage} OFFSET {$offset}";
        $dataCommand = "docker exec {$containerName} mysql -u root -p'{$password}' -D {$dbName} -N -e \"{$dataQuery}\" 2>/dev/null | awk -F'\\t' 'BEGIN{OFS=\"|\"} {for(i=1;i<=NF;i++) printf \"%s%s\", \$i, (i==NF?\"\\n\":OFS)}' || echo ''";
        $result = trim(instant_remote_process([$dataCommand], $server, false) ?? '');

        $rows = [];
        if ($result) {
            foreach (explode("\n", $result) as $line) {
                $values = explode('|', $line);
                if (count($values) === count($columnNames)) {
                    $row = [];
                    foreach ($columnNames as $i => $colName) {
                        $row[$colName] = $values[$i] !== '' && $values[$i] !== 'NULL' ? $values[$i] : null;
                    }
                    $rows[] = $row;
                }
            }
        }

        return [
            'rows' => $rows,
            'total' => $total,
            'columns' => $columns,
        ];
    }

    /**
     * Update MySQL row (placeholder for future implementation).
     */
    protected function updateMysqlRow(mixed $server, mixed $database, string $tableName, array $primaryKey, array $updates): bool
    {
        $containerName = $database->uuid;
        $password = $database->mysql_root_password ?? $database->mysql_password ?? '';
        $dbName = $database->mysql_database ?? 'mysql';

        // Escape table name to prevent SQL injection
        $escapedTableName = str_replace('`', '``', $tableName);

        // Build SET clause
        $setClauses = [];
        foreach ($updates as $column => $value) {
            $escapedValue = str_replace("'", "\\'", (string) $value);
            $setClauses[] = "`{$column}` = '{$escapedValue}'";
        }
        $setClause = implode(', ', $setClauses);

        // Build WHERE clause from primary key
        $whereClauses = [];
        foreach ($primaryKey as $column => $value) {
            $escapedValue = str_replace("'", "\\'", (string) $value);
            $whereClauses[] = "`{$column}` = '{$escapedValue}'";
        }
        $whereClause = implode(' AND ', $whereClauses);

        $query = "UPDATE `{$escapedTableName}` SET {$setClause} WHERE {$whereClause}";
        $command = "docker exec {$containerName} mysql -u root -p'{$password}' -D {$dbName} -e \"{$query}\" 2>&1";
        $result = instant_remote_process([$command], $server, false);

        return ! str_contains($result ?? '', 'ERROR');
    }

    /**
     * Delete MySQL row (placeholder for future implementation).
     */
    protected function deleteMysqlRow(mixed $server, mixed $database, string $tableName, array $primaryKey): bool
    {
        $containerName = $database->uuid;
        $password = $database->mysql_root_password ?? $database->mysql_password ?? '';
        $dbName = $database->mysql_database ?? 'mysql';

        // Escape table name to prevent SQL injection
        $escapedTableName = str_replace('`', '``', $tableName);

        // Build WHERE clause from primary key
        $whereClauses = [];
        foreach ($primaryKey as $column => $value) {
            $escapedValue = str_replace("'", "\\'", (string) $value);
            $whereClauses[] = "`{$column}` = '{$escapedValue}'";
        }
        $whereClause = implode(' AND ', $whereClauses);

        $query = "DELETE FROM `{$escapedTableName}` WHERE {$whereClause}";
        $command = "docker exec {$containerName} mysql -u root -p'{$password}' -D {$dbName} -e \"{$query}\" 2>&1";
        $result = instant_remote_process([$command], $server, false);

        return ! str_contains($result ?? '', 'ERROR');
    }

    /**
     * Create MySQL row (placeholder for future implementation).
     */
    protected function createMysqlRow(mixed $server, mixed $database, string $tableName, array $data): bool
    {
        $containerName = $database->uuid;
        $password = $database->mysql_root_password ?? $database->mysql_password ?? '';
        $dbName = $database->mysql_database ?? 'mysql';

        // Escape table name to prevent SQL injection
        $escapedTableName = str_replace('`', '``', $tableName);

        // Build columns and values
        $columns = array_keys($data);
        $values = array_map(function ($value) {
            if ($value === null) {
                return 'NULL';
            }
            $escapedValue = str_replace("'", "\\'", (string) $value);

            return "'{$escapedValue}'";
        }, array_values($data));

        $columnsClause = '`'.implode('`, `', $columns).'`';
        $valuesClause = implode(', ', $values);

        $query = "INSERT INTO `{$escapedTableName}` ({$columnsClause}) VALUES ({$valuesClause})";
        $command = "docker exec {$containerName} mysql -u root -p'{$password}' -D {$dbName} -e \"{$query}\" 2>&1";
        $result = instant_remote_process([$command], $server, false);

        return ! str_contains($result ?? '', 'ERROR');
    }

    /**
     * Get MongoDB collection schema (dynamic - inferred from documents).
     */
    protected function getMongoColumns(mixed $server, mixed $database, string $collectionName): array
    {
        $containerName = $database->uuid;
        $password = $database->mongo_initdb_root_password ?? '';
        $username = $database->mongo_initdb_root_username ?? 'root';
        $dbName = $database->mongo_initdb_database ?? 'admin';

        // Get unique fields from first 100 documents to infer schema
        $command = "docker exec {$containerName} mongosh -u {$username} -p '{$password}' --authenticationDatabase admin {$dbName} --quiet --eval \"db.{$collectionName}.findOne()\" 2>/dev/null || echo '{}'";
        $result = trim(instant_remote_process([$command], $server, false) ?? '{}');

        // Parse JSON to extract field names
        $columns = [];
        try {
            $doc = json_decode($result, true);
            if ($doc && is_array($doc)) {
                foreach ($doc as $key => $value) {
                    $columns[] = [
                        'name' => $key,
                        'type' => $this->inferMongoType($value),
                        'nullable' => true, // MongoDB fields are always optional
                        'default' => null,
                        'is_primary' => $key === '_id',
                    ];
                }
            }
        } catch (\Exception $e) {
            // Fallback: basic _id field
            $columns = [
                ['name' => '_id', 'type' => 'ObjectId', 'nullable' => false, 'default' => null, 'is_primary' => true],
            ];
        }

        return $columns;
    }

    /**
     * Infer MongoDB field type from value.
     */
    private function inferMongoType(mixed $value): string
    {
        if (is_array($value)) {
            return isset($value['$oid']) ? 'ObjectId' : 'Array';
        }
        if (is_string($value)) {
            return 'String';
        }
        if (is_int($value)) {
            return 'Int';
        }
        if (is_float($value)) {
            return 'Double';
        }
        if (is_bool($value)) {
            return 'Boolean';
        }
        if (is_null($value)) {
            return 'Null';
        }

        return 'Mixed';
    }

    /**
     * Get MongoDB collection data with pagination.
     */
    protected function getMongoData(mixed $server, mixed $database, string $collectionName, int $page, int $perPage, string $search, string $orderBy, string $orderDir): array
    {
        $containerName = $database->uuid;
        $password = $database->mongo_initdb_root_password ?? '';
        $username = $database->mongo_initdb_root_username ?? 'root';
        $dbName = $database->mongo_initdb_database ?? 'admin';
        $skip = ($page - 1) * $perPage;

        // Get columns first
        $columns = $this->getMongoColumns($server, $database, $collectionName);

        // Build search query (text search across all fields)
        $searchQuery = '{}';
        if ($search !== '') {
            $escapedSearch = str_replace("'", "\\'", $search);
            $searchQuery = '{$or: ['.implode(',', array_map(fn ($col) => "{{$col['name']}: /.*{$escapedSearch}.*/i}}", $columns)).']}';
        }

        // Build sort query
        $sortQuery = $orderBy !== '' ? "{{$orderBy}: ".($orderDir === 'asc' ? '1' : '-1').'}' : '{}';

        // Get total count
        $countCommand = "docker exec {$containerName} mongosh -u {$username} -p '{$password}' --authenticationDatabase admin {$dbName} --quiet --eval \"db.{$collectionName}.countDocuments({$searchQuery})\" 2>/dev/null || echo '0'";
        $total = (int) trim(instant_remote_process([$countCommand], $server, false) ?? '0');

        // Get data
        $dataCommand = "docker exec {$containerName} mongosh -u {$username} -p '{$password}' --authenticationDatabase admin {$dbName} --quiet --eval \"JSON.stringify(db.{$collectionName}.find({$searchQuery}).sort({$sortQuery}).skip({$skip}).limit({$perPage}).toArray())\" 2>/dev/null || echo '[]'";
        $result = trim(instant_remote_process([$dataCommand], $server, false) ?? '[]');

        $rows = [];
        try {
            $docs = json_decode($result, true);
            if ($docs && is_array($docs)) {
                foreach ($docs as $doc) {
                    $row = [];
                    foreach ($columns as $col) {
                        $key = $col['name'];
                        if (isset($doc[$key])) {
                            // Handle MongoDB ObjectId and other special types
                            if (is_array($doc[$key]) && isset($doc[$key]['$oid'])) {
                                $row[$key] = $doc[$key]['$oid'];
                            } else {
                                $row[$key] = is_array($doc[$key]) ? json_encode($doc[$key]) : $doc[$key];
                            }
                        } else {
                            $row[$key] = null;
                        }
                    }
                    $rows[] = $row;
                }
            }
        } catch (\Exception $e) {
            // Return empty result on error
        }

        return [
            'rows' => $rows,
            'total' => $total,
            'columns' => $columns,
        ];
    }

    /**
     * Update MongoDB document.
     */
    protected function updateMongoRow(mixed $server, mixed $database, string $collectionName, array $primaryKey, array $updates): bool
    {
        $containerName = $database->uuid;
        $password = $database->mongo_initdb_root_password ?? '';
        $username = $database->mongo_initdb_root_username ?? 'root';
        $dbName = $database->mongo_initdb_database ?? 'admin';

        // Build filter (usually by _id)
        $filterParts = [];
        foreach ($primaryKey as $key => $value) {
            if ($key === '_id') {
                $filterParts[] = "_id: ObjectId('{$value}')";
            } else {
                $escapedValue = str_replace("'", "\\'", (string) $value);
                $filterParts[] = "{$key}: '{$escapedValue}'";
            }
        }
        $filter = '{'.implode(', ', $filterParts).'}';

        // Build update
        $updateParts = [];
        foreach ($updates as $key => $value) {
            if ($key === '_id') {
                continue; // Skip _id updates
            }
            $escapedValue = str_replace("'", "\\'", (string) $value);
            $updateParts[] = "{$key}: '{$escapedValue}'";
        }
        $update = '{\$set: {'.implode(', ', $updateParts).'}}';

        $command = "docker exec {$containerName} mongosh -u {$username} -p '{$password}' --authenticationDatabase admin {$dbName} --quiet --eval \"db.{$collectionName}.updateOne({$filter}, {$update})\" 2>&1";
        $result = instant_remote_process([$command], $server, false);

        return str_contains($result ?? '', 'modifiedCount') && ! str_contains($result ?? '', 'error');
    }

    /**
     * Delete MongoDB document.
     */
    protected function deleteMongoRow(mixed $server, mixed $database, string $collectionName, array $primaryKey): bool
    {
        $containerName = $database->uuid;
        $password = $database->mongo_initdb_root_password ?? '';
        $username = $database->mongo_initdb_root_username ?? 'root';
        $dbName = $database->mongo_initdb_database ?? 'admin';

        // Build filter (usually by _id)
        $filterParts = [];
        foreach ($primaryKey as $key => $value) {
            if ($key === '_id') {
                $filterParts[] = "_id: ObjectId('{$value}')";
            } else {
                $escapedValue = str_replace("'", "\\'", (string) $value);
                $filterParts[] = "{$key}: '{$escapedValue}'";
            }
        }
        $filter = '{'.implode(', ', $filterParts).'}';

        $command = "docker exec {$containerName} mongosh -u {$username} -p '{$password}' --authenticationDatabase admin {$dbName} --quiet --eval \"db.{$collectionName}.deleteOne({$filter})\" 2>&1";
        $result = instant_remote_process([$command], $server, false);

        return str_contains($result ?? '', 'deletedCount') && ! str_contains($result ?? '', 'error');
    }

    /**
     * Create MongoDB document.
     */
    protected function createMongoRow(mixed $server, mixed $database, string $collectionName, array $data): bool
    {
        $containerName = $database->uuid;
        $password = $database->mongo_initdb_root_password ?? '';
        $username = $database->mongo_initdb_root_username ?? 'root';
        $dbName = $database->mongo_initdb_database ?? 'admin';

        // Build document
        $docParts = [];
        foreach ($data as $key => $value) {
            if ($key === '_id') {
                continue; // Let MongoDB generate _id
            }
            if ($value === null) {
                $docParts[] = "{$key}: null";
            } else {
                $escapedValue = str_replace("'", "\\'", (string) $value);
                $docParts[] = "{$key}: '{$escapedValue}'";
            }
        }
        $document = '{'.implode(', ', $docParts).'}';

        $command = "docker exec {$containerName} mongosh -u {$username} -p '{$password}' --authenticationDatabase admin {$dbName} --quiet --eval \"db.{$collectionName}.insertOne({$document})\" 2>&1";
        $result = instant_remote_process([$command], $server, false);

        return str_contains($result ?? '', 'insertedId') && ! str_contains($result ?? '', 'error');
    }
}
