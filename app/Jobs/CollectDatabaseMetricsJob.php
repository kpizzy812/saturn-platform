<?php

namespace App\Jobs;

use App\Models\DatabaseMetric;
use App\Models\Server;
use App\Models\StandaloneClickhouse;
use App\Models\StandaloneDragonfly;
use App\Models\StandaloneKeydb;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use App\Models\StandaloneRedis;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Laravel\Horizon\Contracts\Silenced;

class CollectDatabaseMetricsJob implements ShouldBeEncrypted, ShouldQueue, Silenced
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 60;

    public function __construct(public Server $server) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('collect-db-metrics-'.$this->server->uuid))->expireAfter(60)->dontRelease()];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('CollectDatabaseMetricsJob permanently failed', [
            'server_id' => $this->server->id,
            'server_name' => $this->server->name,
            'error' => $exception->getMessage(),
        ]);
    }

    public function handle(): void
    {
        if (! $this->server->isFunctional()) {
            return;
        }

        $databases = $this->server->databases();

        foreach ($databases as $database) {
            try {
                $this->collectMetricsForDatabase($database);
            } catch (\Exception $e) {
                // Log error but continue with other databases
                Log::warning('Failed to collect metrics for database: '.$database->uuid, ['error' => $e->getMessage()]);
            }
        }
    }

    protected function collectMetricsForDatabase($database): void
    {
        $containerName = $database->uuid;
        $type = $this->getDatabaseType($database);

        // Get container stats (CPU, memory, network)
        $containerStats = $this->getContainerStats($containerName);

        // Get database-specific metrics
        $dbMetrics = $this->getDatabaseSpecificMetrics($database, $type);

        // Store the metrics
        DatabaseMetric::create([
            'database_uuid' => $database->uuid,
            'database_type' => $type,
            'cpu_percent' => $containerStats['cpu_percent'] ?? null,
            'memory_bytes' => $containerStats['memory_bytes'] ?? null,
            'memory_limit_bytes' => $containerStats['memory_limit_bytes'] ?? null,
            'network_rx_bytes' => $containerStats['network_rx_bytes'] ?? null,
            'network_tx_bytes' => $containerStats['network_tx_bytes'] ?? null,
            'metrics' => $dbMetrics,
            'recorded_at' => Carbon::now(),
        ]);
    }

    protected function getDatabaseType($database): string
    {
        return match (true) {
            $database instanceof StandalonePostgresql => 'postgresql',
            $database instanceof StandaloneMysql => 'mysql',
            $database instanceof StandaloneMariadb => 'mariadb',
            $database instanceof StandaloneMongodb => 'mongodb',
            $database instanceof StandaloneRedis => 'redis',
            $database instanceof StandaloneKeydb => 'keydb',
            $database instanceof StandaloneDragonfly => 'dragonfly',
            $database instanceof StandaloneClickhouse => 'clickhouse',
            default => 'unknown',
        };
    }

    protected function getContainerStats(string $containerName): array
    {
        try {
            // Use docker stats with --no-stream for instant snapshot
            $safeContainerName = escapeshellarg($containerName);
            $command = "docker stats {$safeContainerName} --no-stream --format '{{json .}}' 2>/dev/null || echo '{}'";
            $output = trim(instant_remote_process([$command], $this->server, false) ?? '{}');

            if (empty($output) || $output === '{}') {
                return [];
            }

            $stats = json_decode($output, true);
            if (! $stats) {
                return [];
            }

            return [
                'cpu_percent' => $this->parseCpuPercent($stats['CPUPerc'] ?? '0%'),
                'memory_bytes' => $this->parseMemoryBytes($stats['MemUsage'] ?? '0B'),
                'memory_limit_bytes' => $this->parseMemoryLimit($stats['MemUsage'] ?? '0B / 0B'),
                'network_rx_bytes' => $this->parseNetworkBytes($stats['NetIO'] ?? '0B / 0B', 'rx'),
                'network_tx_bytes' => $this->parseNetworkBytes($stats['NetIO'] ?? '0B / 0B', 'tx'),
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function parseCpuPercent(string $value): float
    {
        return (float) str_replace('%', '', $value);
    }

    protected function parseMemoryBytes(string $value): int
    {
        // Format: "1.2GiB / 4GiB" - get the first part
        $parts = explode('/', $value);
        $used = trim($parts[0]);

        return $this->convertToBytes($used);
    }

    protected function parseMemoryLimit(string $value): int
    {
        // Format: "1.2GiB / 4GiB" - get the second part
        $parts = explode('/', $value);
        $limit = trim($parts[1] ?? '0B');

        return $this->convertToBytes($limit);
    }

    protected function parseNetworkBytes(string $value, string $direction): int
    {
        // Format: "1.2MB / 500KB" - rx is first, tx is second
        $parts = explode('/', $value);

        $bytes = match ($direction) {
            'rx' => trim($parts[0]),
            'tx' => trim($parts[1] ?? '0B'),
            default => '0B',
        };

        return $this->convertToBytes($bytes);
    }

    protected function convertToBytes(string $value): int
    {
        $value = trim($value);
        if (preg_match('/^([\d.]+)\s*(B|KB|KiB|MB|MiB|GB|GiB|TB|TiB)$/i', $value, $matches)) {
            $num = (float) $matches[1];
            $unit = strtoupper($matches[2]);

            return (int) match ($unit) {
                'B' => $num,
                'KB' => $num * 1000,
                'KIB' => $num * 1024,
                'MB' => $num * 1000 * 1000,
                'MIB' => $num * 1024 * 1024,
                'GB' => $num * 1000 * 1000 * 1000,
                'GIB' => $num * 1024 * 1024 * 1024,
                'TB' => $num * 1000 * 1000 * 1000 * 1000,
                'TIB' => $num * 1024 * 1024 * 1024 * 1024,
                default => 0,
            };
        }

        return 0;
    }

    protected function getDatabaseSpecificMetrics($database, string $type): array
    {
        return match ($type) {
            'postgresql' => $this->collectPostgresMetrics($database),
            'mysql', 'mariadb' => $this->collectMysqlMetrics($database),
            'redis', 'keydb', 'dragonfly' => $this->collectRedisMetrics($database),
            'mongodb' => $this->collectMongoMetrics($database),
            'clickhouse' => $this->collectClickhouseMetrics($database),
            default => [],
        };
    }

    protected function collectPostgresMetrics($database): array
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
            // Active connections
            $connCommand = "docker exec {$containerName} psql -U {$user} -d {$dbName} -t -c \"SELECT count(*) FROM pg_stat_activity WHERE state = 'active';\" 2>/dev/null || echo 'N/A'";
            $activeConnections = trim(instant_remote_process([$connCommand], $this->server, false) ?? '');
            if (is_numeric($activeConnections)) {
                $metrics['activeConnections'] = (int) $activeConnections;
            }

            // Database size — SQL-escape the db name for use inside the query string
            $escapedDbNameSql = str_replace("'", "''", $dbNameRaw);
            $sizeCommand = "docker exec {$containerName} psql -U {$user} -d {$dbName} -t -c \"SELECT pg_size_pretty(pg_database_size('{$escapedDbNameSql}'));\" 2>/dev/null || echo 'N/A'";
            $databaseSize = trim(instant_remote_process([$sizeCommand], $this->server, false) ?? 'N/A');
            if ($databaseSize && $databaseSize !== 'N/A') {
                $metrics['databaseSize'] = $databaseSize;
            }

            // Max connections
            $maxConnCommand = "docker exec {$containerName} psql -U {$user} -d {$dbName} -t -c \"SHOW max_connections;\" 2>/dev/null || echo '100'";
            $maxConnections = trim(instant_remote_process([$maxConnCommand], $this->server, false) ?? '100');
            if (is_numeric($maxConnections)) {
                $metrics['maxConnections'] = (int) $maxConnections;
            }

            // Transactions per second (approximate via xact_commit)
            $tpsCommand = "docker exec {$containerName} psql -U {$user} -d {$dbName} -t -c \"SELECT sum(xact_commit + xact_rollback) FROM pg_stat_database WHERE datname = '{$escapedDbNameSql}';\" 2>/dev/null || echo 'N/A'";
            $totalTxn = trim(instant_remote_process([$tpsCommand], $this->server, false) ?? '');
            if (is_numeric($totalTxn)) {
                $metrics['totalQueries'] = (int) $totalTxn;
            }
        } catch (\Exception $e) {
            // Metrics remain as defaults
        }

        return $metrics;
    }

    protected function collectMysqlMetrics($database): array
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
            // Active connections
            $connCommand = "docker exec {$containerName} mysql -u root -p{$escapedPassword} -N -e \"SHOW STATUS LIKE 'Threads_connected';\" 2>/dev/null | awk '{print \$2}' || echo 'N/A'";
            $connections = trim(instant_remote_process([$connCommand], $this->server, false) ?? '');
            if (is_numeric($connections)) {
                $metrics['activeConnections'] = (int) $connections;
            }

            // Max connections
            $maxConnCommand = "docker exec {$containerName} mysql -u root -p{$escapedPassword} -N -e \"SHOW VARIABLES LIKE 'max_connections';\" 2>/dev/null | awk '{print \$2}' || echo '150'";
            $maxConnections = trim(instant_remote_process([$maxConnCommand], $this->server, false) ?? '150');
            if (is_numeric($maxConnections)) {
                $metrics['maxConnections'] = (int) $maxConnections;
            }

            // Slow queries
            $slowCommand = "docker exec {$containerName} mysql -u root -p{$escapedPassword} -N -e \"SHOW STATUS LIKE 'Slow_queries';\" 2>/dev/null | awk '{print \$2}' || echo 'N/A'";
            $slowQueries = trim(instant_remote_process([$slowCommand], $this->server, false) ?? '');
            if (is_numeric($slowQueries)) {
                $metrics['slowQueries'] = (int) $slowQueries;
            }

            // Queries per second
            $qpsCommand = "docker exec {$containerName} mysql -u root -p{$escapedPassword} -N -e \"SHOW STATUS LIKE 'Queries';\" 2>/dev/null | awk '{print \$2}' || echo 'N/A'";
            $totalQueries = trim(instant_remote_process([$qpsCommand], $this->server, false) ?? '');
            if (is_numeric($totalQueries)) {
                $metrics['totalQueries'] = (int) $totalQueries;
            }
        } catch (\Exception $e) {
            // Metrics remain as defaults
        }

        return $metrics;
    }

    protected function collectRedisMetrics($database): array
    {
        $containerName = escapeshellarg($database->uuid);
        $password = $database->redis_password ?? $database->keydb_password ?? $database->dragonfly_password ?? '';

        $metrics = [
            'totalKeys' => null,
            'memoryUsed' => 'N/A',
            'opsPerSec' => null,
            'hitRate' => null,
        ];

        try {
            $authFlag = $password ? '-a '.escapeshellarg($password) : '';

            // Get Redis INFO
            $infoCommand = "docker exec {$containerName} redis-cli {$authFlag} INFO 2>/dev/null || echo ''";
            $info = instant_remote_process([$infoCommand], $this->server, false) ?? '';

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
            // Metrics remain as defaults
        }

        return $metrics;
    }

    protected function collectMongoMetrics($database): array
    {
        $containerName = escapeshellarg($database->uuid);
        $dbName = escapeshellarg($database->mongo_initdb_database ?? 'admin');

        $metrics = [
            'collections' => null,
            'documents' => null,
            'databaseSize' => 'N/A',
            'indexSize' => 'N/A',
        ];

        try {
            $statsCommand = "docker exec {$containerName} mongosh --quiet --eval ".escapeshellarg('JSON.stringify(db.stats())')." {$dbName} 2>/dev/null || echo '{}'";
            $statsJson = trim(instant_remote_process([$statsCommand], $this->server, false) ?? '{}');
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
            // Metrics remain as defaults
        }

        return $metrics;
    }

    protected function collectClickhouseMetrics($database): array
    {
        $containerName = escapeshellarg($database->uuid);
        $password = $database->clickhouse_admin_password ?? '';

        $metrics = [
            'totalTables' => null,
            'totalRows' => null,
            'databaseSize' => 'N/A',
            'queriesPerSec' => null,
        ];

        try {
            $authFlag = $password ? '--password '.escapeshellarg($password) : '';

            // Get total tables
            $tablesCommand = "docker exec {$containerName} clickhouse-client {$authFlag} -q \"SELECT count() FROM system.tables WHERE database NOT IN ('system', 'INFORMATION_SCHEMA', 'information_schema')\" 2>/dev/null || echo 'N/A'";
            $tables = trim(instant_remote_process([$tablesCommand], $this->server, false) ?? '');
            if (is_numeric($tables)) {
                $metrics['totalTables'] = (int) $tables;
            }

            // Get current queries
            $queriesCommand = "docker exec {$containerName} clickhouse-client {$authFlag} -q \"SELECT count() FROM system.processes\" 2>/dev/null || echo 'N/A'";
            $queries = trim(instant_remote_process([$queriesCommand], $this->server, false) ?? '');
            if (is_numeric($queries)) {
                $metrics['queriesPerSec'] = (int) $queries;
            }
        } catch (\Exception $e) {
            // Metrics remain as defaults
        }

        return $metrics;
    }

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
