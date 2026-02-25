<?php

namespace App\Services\DatabaseMetrics;

use App\Traits\FormatHelpers;
use Illuminate\Support\Facades\Log;

/**
 * Service for ClickHouse database metrics and operations.
 */
class ClickhouseMetricsService
{
    use FormatHelpers;

    /**
     * Collect ClickHouse metrics via SSH.
     */
    public function collectMetrics(mixed $server, mixed $database): array
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
            Log::debug('Failed to collect ClickHouse metrics', [
                'database_uuid' => $database->uuid ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return $metrics;
    }

    /**
     * Get ClickHouse query log.
     */
    public function getQueryLog(mixed $server, mixed $database, int $limit = 50): array
    {
        $containerName = escapeshellarg($database->uuid);
        $password = $database->clickhouse_admin_password ?? '';
        $authFlag = $password ? '--password '.escapeshellarg($password) : '';

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

        return $queries;
    }

    /**
     * Get ClickHouse merge status.
     */
    public function getMergeStatus(mixed $server, mixed $database): array
    {
        $containerName = escapeshellarg($database->uuid);
        $password = $database->clickhouse_admin_password ?? '';
        $authFlag = $password ? '--password '.escapeshellarg($password) : '';

        // Get active merges count
        $mergesCommand = "docker exec {$containerName} clickhouse-client {$authFlag} -q \"SELECT count() FROM system.merges\" 2>/dev/null || echo '0'";
        $activeMerges = (int) trim(instant_remote_process([$mergesCommand], $server, false) ?? '0');

        // Get parts count
        $partsCommand = "docker exec {$containerName} clickhouse-client {$authFlag} -q \"SELECT count() FROM system.parts WHERE active = 1\" 2>/dev/null || echo '0'";
        $partsCount = (int) trim(instant_remote_process([$partsCommand], $server, false) ?? '0');

        // Get merge rate (merges in last minute from system.part_log)
        $rateCommand = "docker exec {$containerName} clickhouse-client {$authFlag} -q \"SELECT count() FROM system.part_log WHERE event_type = 'MergeParts' AND event_time > now() - INTERVAL 1 MINUTE\" 2>/dev/null || echo '0'";
        $mergeRate = (int) trim(instant_remote_process([$rateCommand], $server, false) ?? '0');

        return [
            'activeMerges' => $activeMerges,
            'partsCount' => $partsCount,
            'mergeRate' => $mergeRate,
        ];
    }

    /**
     * Get ClickHouse replication status.
     */
    public function getReplicationStatus(mixed $server, mixed $database): array
    {
        $containerName = escapeshellarg($database->uuid);
        $password = $database->clickhouse_admin_password ?? '';
        $authFlag = $password ? '--password '.escapeshellarg($password) : '';

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

        return [
            'enabled' => $enabled,
            'replicas' => $replicas,
        ];
    }

    /**
     * Get ClickHouse settings.
     */
    public function getSettings(mixed $server, mixed $database): array
    {
        $containerName = escapeshellarg($database->uuid);
        $password = $database->clickhouse_admin_password ?? '';
        $authFlag = $password ? '--password '.escapeshellarg($password) : '';

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

        return $settings;
    }

    /**
     * Execute ClickHouse query.
     */
    public function executeQuery(mixed $server, mixed $database, string $query): array
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
     * Get ClickHouse tables with row count and size.
     */
    public function getTables(mixed $server, mixed $database): array
    {
        $containerName = escapeshellarg($database->uuid);
        $password = $database->clickhouse_admin_password ?? '';
        $authFlag = $password ? '--password '.escapeshellarg($password) : '';

        $command = "docker exec {$containerName} clickhouse-client {$authFlag} -q \"SELECT name, total_rows, formatReadableSize(total_bytes) FROM system.tables WHERE database = currentDatabase() AND total_rows IS NOT NULL ORDER BY total_rows DESC LIMIT 100 FORMAT TabSeparated\" 2>/dev/null || echo ''";
        $result = trim(instant_remote_process([$command], $server, false) ?? '');

        $tables = [];
        if ($result) {
            foreach (explode("\n", $result) as $line) {
                $parts = preg_split('/\t/', trim($line));
                if (count($parts) >= 3 && ! empty($parts[0])) {
                    $tables[] = [
                        'name' => $parts[0],
                        'rows' => (int) $parts[1],
                        'size' => $parts[2],
                    ];
                }
            }
        }

        return $tables;
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
}
