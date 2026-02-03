<?php

namespace App\Services\Transfer\Strategies;

use App\Models\Server;
use App\Models\StandaloneMysql;

/**
 * Transfer strategy for MySQL databases.
 *
 * Uses mysqldump for creating dumps and mysql for restoring.
 */
class MysqlTransferStrategy extends AbstractTransferStrategy
{
    public function getDatabaseType(): string
    {
        return 'mysql';
    }

    public function getContainerName(mixed $database): string
    {
        return $database->uuid;
    }

    public function getDumpExtension(): string
    {
        return '.sql';
    }

    /**
     * Create a dump file on the source server.
     *
     * @param  StandaloneMysql  $database
     */
    public function createDump(
        mixed $database,
        Server $server,
        string $dumpPath,
        ?array $options = null
    ): array {
        try {
            $containerName = $this->escapeContainerName($this->getContainerName($database));
            $dbName = $database->mysql_database;
            $password = $database->mysql_root_password;

            // Ensure dump directory exists
            $dumpDir = dirname($dumpPath);
            $this->ensureDirectory($server, $dumpDir);

            // Build mysqldump command
            $tableFlags = '';
            if ($options && ! empty($options['tables'])) {
                foreach ($options['tables'] as $table) {
                    $this->validatePath($table, 'table name');
                    $tableFlags .= ' '.escapeshellarg($table);
                }
            }

            $command = "docker exec {$containerName} mysqldump -u root -p\"{$password}\" --single-transaction --quick --lock-tables=false {$dbName}{$tableFlags} > {$dumpPath}";

            $this->executeCommand([$command], $server, true, 3600);

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
     * @param  StandaloneMysql  $database
     */
    public function restoreDump(
        mixed $database,
        Server $server,
        string $dumpPath,
        ?array $options = null
    ): array {
        try {
            $containerName = $this->escapeContainerName($this->getContainerName($database));
            $dbName = $database->mysql_database;
            $password = $database->mysql_root_password;

            // Check if dump file exists
            if (! $this->fileExists($server, $dumpPath)) {
                return [
                    'success' => false,
                    'error' => 'Dump file not found on target server',
                ];
            }

            $command = "docker exec -i {$containerName} mysql -u root -p\"{$password}\" {$dbName} < {$dumpPath}";

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
     * @param  StandaloneMysql  $database
     */
    public function getStructure(mixed $database, Server $server): array
    {
        try {
            $containerName = $this->escapeContainerName($this->getContainerName($database));
            $dbName = $database->mysql_database;
            $password = $database->mysql_root_password;

            // Query to get tables with their sizes
            $query = "SELECT table_name, CONCAT(ROUND(data_length / 1024 / 1024, 2), ' MB') as size, data_length as size_bytes FROM information_schema.tables WHERE table_schema = '{$dbName}' ORDER BY data_length DESC;";

            $command = "docker exec {$containerName} mysql -u root -p\"{$password}\" -N -e \"{$query}\"";

            $output = $this->executeCommand([$command], $server, false);

            $tables = [];
            $lines = explode("\n", trim($output));

            foreach ($lines as $line) {
                if (empty(trim($line))) {
                    continue;
                }

                $parts = preg_split('/\s+/', trim($line));
                if (count($parts) >= 3) {
                    $tables[] = [
                        'name' => trim($parts[0]),
                        'size_formatted' => trim($parts[1].' '.$parts[2]),
                        'size_bytes' => (int) trim($parts[count($parts) - 1]),
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
     * @param  StandaloneMysql  $database
     */
    public function estimateSize(mixed $database, Server $server, ?array $options = null): int
    {
        try {
            $containerName = $this->escapeContainerName($this->getContainerName($database));
            $dbName = $database->mysql_database;
            $password = $database->mysql_root_password;

            if ($options && ! empty($options['tables'])) {
                // Calculate size for specific tables
                $tableList = array_map(fn ($t) => "'{$t}'", $options['tables']);
                $tableListStr = implode(',', $tableList);

                $query = "SELECT SUM(data_length + index_length) FROM information_schema.tables WHERE table_schema = '{$dbName}' AND table_name IN ({$tableListStr});";
            } else {
                // Calculate total database size
                $query = "SELECT SUM(data_length + index_length) FROM information_schema.tables WHERE table_schema = '{$dbName}';";
            }

            $command = "docker exec {$containerName} mysql -u root -p\"{$password}\" -N -e \"{$query}\"";

            $output = $this->executeCommand([$command], $server, false);

            return (int) trim($output);
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
