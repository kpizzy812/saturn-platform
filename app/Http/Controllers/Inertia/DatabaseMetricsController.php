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
}
