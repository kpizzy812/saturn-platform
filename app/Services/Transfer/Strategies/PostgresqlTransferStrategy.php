<?php

namespace App\Services\Transfer\Strategies;

use App\Models\Server;
use App\Models\StandalonePostgresql;

/**
 * Transfer strategy for PostgreSQL databases.
 *
 * Uses pg_dump for creating dumps and pg_restore for restoring.
 */
class PostgresqlTransferStrategy extends AbstractTransferStrategy
{
    public function getDatabaseType(): string
    {
        return 'postgresql';
    }

    public function getContainerName(mixed $database): string
    {
        return $database->uuid;
    }

    public function getDumpExtension(): string
    {
        return '.dump';
    }

    /**
     * Create a dump file on the source server.
     *
     * @param  StandalonePostgresql  $database
     */
    public function createDump(
        mixed $database,
        Server $server,
        string $dumpPath,
        ?array $options = null
    ): array {
        try {
            $containerName = $this->escapeContainerName($this->getContainerName($database));
            $user = $database->postgres_user;
            $dbName = $database->postgres_db;
            $password = $database->postgres_password;

            // Ensure dump directory exists
            $dumpDir = dirname($dumpPath);
            $this->ensureDirectory($server, $dumpDir);

            // Build pg_dump command
            $tableFlags = '';
            if ($options && ! empty($options['tables'])) {
                foreach ($options['tables'] as $table) {
                    $this->validatePath($table, 'table name');
                    $escapedTable = escapeshellarg($table);
                    $tableFlags .= " -t {$escapedTable}";
                }
            }

            // Use custom format for full dump, plain text for partial
            $format = empty($tableFlags) ? '-Fc' : '-Fp';

            $command = "docker exec -e PGPASSWORD=\"{$password}\" {$containerName} pg_dump {$format} --no-acl --no-owner --username {$user}{$tableFlags} {$dbName} > {$dumpPath}";

            $commands = [$command];
            $this->executeCommand($commands, $server, true, 3600);

            // Get dump file size
            $size = $this->getFileSize($server, $dumpPath);

            if ($size === 0) {
                return [
                    'success' => false,
                    'size' => 0,
                    'error' => 'Dump file is empty',
                ];
            }

            return [
                'success' => true,
                'size' => $size,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'size' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Restore a dump file on the target server.
     *
     * @param  StandalonePostgresql  $database
     */
    public function restoreDump(
        mixed $database,
        Server $server,
        string $dumpPath,
        ?array $options = null
    ): array {
        try {
            $containerName = $this->escapeContainerName($this->getContainerName($database));
            $user = $database->postgres_user;
            $dbName = $database->postgres_db;
            $password = $database->postgres_password;

            // Check if dump file exists
            if (! $this->fileExists($server, $dumpPath)) {
                return [
                    'success' => false,
                    'error' => 'Dump file not found on target server',
                ];
            }

            // Determine if it's a custom format dump or plain text
            $fileType = $this->executeCommand(
                ["file {$dumpPath} | grep -q 'PostgreSQL custom database dump' && echo 'custom' || echo 'plain'"],
                $server,
                false
            );

            if (trim($fileType) === 'custom') {
                // Use pg_restore for custom format
                $command = "docker exec -i -e PGPASSWORD=\"{$password}\" {$containerName} pg_restore --no-acl --no-owner --clean --if-exists --username {$user} -d {$dbName} < {$dumpPath}";
            } else {
                // Use psql for plain text format
                $command = "docker exec -i -e PGPASSWORD=\"{$password}\" {$containerName} psql --username {$user} -d {$dbName} < {$dumpPath}";
            }

            $this->executeCommand([$command], $server, true, 3600);

            return [
                'success' => true,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get database structure (tables with sizes).
     *
     * @param  StandalonePostgresql  $database
     */
    public function getStructure(mixed $database, Server $server): array
    {
        try {
            $containerName = $this->escapeContainerName($this->getContainerName($database));
            $user = $database->postgres_user;
            $dbName = $database->postgres_db;
            $password = $database->postgres_password;

            // Query to get tables with their sizes
            $query = "SELECT table_name, pg_size_pretty(pg_total_relation_size(quote_ident(table_name))) as size, pg_total_relation_size(quote_ident(table_name)) as size_bytes FROM information_schema.tables WHERE table_schema = 'public' ORDER BY pg_total_relation_size(quote_ident(table_name)) DESC;";

            $command = "docker exec -e PGPASSWORD=\"{$password}\" {$containerName} psql -U {$user} -d {$dbName} -t -A -F '|' -c \"{$query}\"";

            $output = $this->executeCommand([$command], $server, false);

            $tables = [];
            $lines = explode("\n", trim($output));

            foreach ($lines as $line) {
                if (empty(trim($line))) {
                    continue;
                }

                $parts = explode('|', $line);
                if (count($parts) >= 3) {
                    $tables[] = [
                        'name' => trim($parts[0]),
                        'size_formatted' => trim($parts[1]),
                        'size_bytes' => (int) trim($parts[2]),
                    ];
                }
            }

            return $tables;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Estimate the size of data to be transferred.
     *
     * @param  StandalonePostgresql  $database
     */
    public function estimateSize(mixed $database, Server $server, ?array $options = null): int
    {
        try {
            $containerName = $this->escapeContainerName($this->getContainerName($database));
            $user = $database->postgres_user;
            $dbName = $database->postgres_db;
            $password = $database->postgres_password;

            if ($options && ! empty($options['tables'])) {
                // Calculate size for specific tables — validate names to prevent SQL injection
                $tableList = array_map(function ($t) {
                    $this->validatePath($t, 'table name');

                    return "'".str_replace("'", "''", $t)."'";
                }, $options['tables']);
                $tableListStr = implode(',', $tableList);

                $query = "SELECT SUM(pg_total_relation_size(quote_ident(table_name))) FROM information_schema.tables WHERE table_schema = 'public' AND table_name IN ({$tableListStr});";
            } else {
                // Calculate total database size
                $escapedDbName = str_replace("'", "''", $dbName);
                $query = "SELECT pg_database_size('{$escapedDbName}');";
            }

            $command = "docker exec -e PGPASSWORD=\"{$password}\" {$containerName} psql -U {$user} -d {$dbName} -t -c \"{$query}\"";

            $output = $this->executeCommand([$command], $server, false);

            return (int) trim($output);
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
