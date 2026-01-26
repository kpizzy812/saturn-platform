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
            $command = "docker exec {$containerName} psql -U {$user} -d {$dbName} -t -A -F '|' -c \"SELECT e.extname, e.extversion, 'installed' as status, c.description FROM pg_extension e LEFT JOIN pg_available_extensions c ON e.extname = c.name ORDER BY e.extname;\" 2>/dev/null || echo ''";
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
}
