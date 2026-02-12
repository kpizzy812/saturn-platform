<?php

namespace App\Services\Transfer\Strategies;

use App\Models\Server;
use App\Models\StandaloneClickhouse;

/**
 * Transfer strategy for ClickHouse databases.
 *
 * Uses clickhouse-client for creating and restoring dumps.
 */
class ClickhouseTransferStrategy extends AbstractTransferStrategy
{
    public function getDatabaseType(): string
    {
        return 'clickhouse';
    }

    public function getContainerName(mixed $database): string
    {
        return $database->uuid;
    }

    public function getDumpExtension(): string
    {
        return '.sql.gz';
    }

    /**
     * Create a dump file on the source server.
     *
     * @param  StandaloneClickhouse  $database
     */
    public function createDump(
        mixed $database,
        Server $server,
        string $dumpPath,
        ?array $options = null
    ): array {
        try {
            $containerName = $this->escapeContainerName($this->getContainerName($database));
            $user = $database->clickhouse_admin_user;
            $password = $database->clickhouse_admin_password;
            $dbName = 'default'; // ClickHouse default database

            // Ensure dump directory exists
            $dumpDir = dirname($dumpPath);
            $this->ensureDirectory($server, $dumpDir);

            // Build clickhouse-client auth
            $auth = "--user {$user} --password \"{$password}\"";

            // Get tables to dump
            $tables = [];
            if ($options && ! empty($options['tables'])) {
                $tables = $options['tables'];
            } else {
                // Get all tables from default database
                $tablesOutput = $this->executeCommand(
                    ["docker exec {$containerName} clickhouse-client {$auth} --query \"SHOW TABLES FROM {$dbName}\""],
                    $server,
                    false
                );
                $tables = array_filter(explode("\n", trim($tablesOutput)));
            }

            if (empty($tables)) {
                return [
                    'success' => false,
                    'size' => 0,
                    'error' => 'No tables found to dump',
                ];
            }

            // For each table, dump schema and data
            $commands = [];

            // Remove existing dump file
            $commands[] = "rm -f {$dumpPath}";

            foreach ($tables as $table) {
                $this->validatePath($table, 'table name');
                $escapedTable = escapeshellarg($table);

                // Dump table schema (CREATE TABLE)
                $commands[] = "echo '-- Table: {$table}' >> {$dumpPath}.tmp";
                $commands[] = "docker exec {$containerName} clickhouse-client {$auth} --query \"SHOW CREATE TABLE {$dbName}.{$table}\" >> {$dumpPath}.tmp";
                $commands[] = "echo ';' >> {$dumpPath}.tmp";
                $commands[] = "echo '' >> {$dumpPath}.tmp";

                // Dump table data using TabSeparated format for smaller files
                $commands[] = "echo '-- Data for table: {$table}' >> {$dumpPath}.tmp";
                $commands[] = "docker exec {$containerName} clickhouse-client {$auth} --query \"SELECT * FROM {$dbName}.{$table} FORMAT TabSeparatedWithNames\" >> {$dumpPath}.tmp";
                $commands[] = "echo '' >> {$dumpPath}.tmp";
            }

            // Compress the dump
            $commands[] = "gzip -c {$dumpPath}.tmp > {$dumpPath}";
            $commands[] = "rm -f {$dumpPath}.tmp";

            foreach ($commands as $command) {
                $this->executeCommand([$command], $server, false, 3600);
            }

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
     * @param  StandaloneClickhouse  $database
     */
    public function restoreDump(
        mixed $database,
        Server $server,
        string $dumpPath,
        ?array $options = null
    ): array {
        try {
            $containerName = $this->escapeContainerName($this->getContainerName($database));
            $user = $database->clickhouse_admin_user;
            $password = $database->clickhouse_admin_password;
            $dbName = 'default';

            // Check if dump file exists
            if (! $this->fileExists($server, $dumpPath)) {
                return [
                    'success' => false,
                    'error' => 'Dump file not found on target server',
                ];
            }

            // Build clickhouse-client auth
            $auth = "--user {$user} --password \"{$password}\"";

            // Decompress and restore
            // Note: ClickHouse restore is complex due to TabSeparated format
            // We need to parse the dump and execute appropriate commands
            $commands = [
                // Decompress
                "gunzip -c {$dumpPath} > {$dumpPath}.sql",
            ];

            $this->executeCommand($commands, $server, false, 300);

            // Parse the dump file: extract CREATE TABLE statements and data blocks,
            // then restore schema first and pipe data via INSERT FORMAT TabSeparatedWithNames
            $escapedContainer = trim($containerName, "'");
            $restoreScript = <<<BASH
current_table=""
in_data=false
schema_line=""
data_file=""
while IFS= read -r line; do
    if [[ \$line == "-- Table: "* ]]; then
        current_table="\${line#-- Table: }"
        in_data=false
    elif [[ \$line == "CREATE"* ]]; then
        # Collect full CREATE TABLE statement (may be single line)
        schema_line="\$line"
        # Execute CREATE TABLE (drop existing first)
        docker exec {$escapedContainer} clickhouse-client {$auth} --query "DROP TABLE IF EXISTS {$dbName}.\$current_table" 2>/dev/null || true
        docker exec {$escapedContainer} clickhouse-client {$auth} --query "\$schema_line" 2>/dev/null || true
    elif [[ \$line == "-- Data for table: "* ]]; then
        current_table="\${line#-- Data for table: }"
        in_data=true
        data_file="{$dumpPath}.\${current_table}.tsv"
        rm -f "\$data_file"
    elif [[ \$in_data == true ]] && [[ -n "\$line" ]] && [[ "\$line" != "--"* ]]; then
        echo "\$line" >> "\$data_file"
    elif [[ \$in_data == true ]] && [[ -z "\$line" ]]; then
        # Empty line marks end of data block — import collected data
        if [[ -f "\$data_file" ]] && [[ -s "\$data_file" ]]; then
            docker exec -i {$escapedContainer} clickhouse-client {$auth} --query "INSERT INTO {$dbName}.\$current_table FORMAT TabSeparatedWithNames" < "\$data_file" 2>/dev/null || true
            rm -f "\$data_file"
        fi
        in_data=false
    fi
done < {$dumpPath}.sql
# Import last table if file didn't end with empty line
if [[ \$in_data == true ]] && [[ -f "\$data_file" ]] && [[ -s "\$data_file" ]]; then
    docker exec -i {$escapedContainer} clickhouse-client {$auth} --query "INSERT INTO {$dbName}.\$current_table FORMAT TabSeparatedWithNames" < "\$data_file" 2>/dev/null || true
    rm -f "\$data_file"
fi
BASH;

            $this->executeCommand([$restoreScript], $server, false, 3600);

            // Cleanup temp files
            $this->executeCommand(["rm -f {$dumpPath}.sql {$dumpPath}.*.tsv"], $server, false);

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
     * @param  StandaloneClickhouse  $database
     */
    public function getStructure(mixed $database, Server $server): array
    {
        try {
            $containerName = $this->escapeContainerName($this->getContainerName($database));
            $user = $database->clickhouse_admin_user;
            $password = $database->clickhouse_admin_password;
            $auth = "--user {$user} --password \"{$password}\"";

            // Query to get tables with their sizes
            $query = "SELECT name, formatReadableSize(total_bytes) as size, total_bytes FROM system.tables WHERE database = 'default' AND total_bytes > 0 ORDER BY total_bytes DESC FORMAT TabSeparated";

            $command = "docker exec {$containerName} clickhouse-client {$auth} --query \"{$query}\"";

            $output = $this->executeCommand([$command], $server, false);

            $tables = [];
            $lines = explode("\n", trim($output));

            foreach ($lines as $line) {
                if (empty(trim($line))) {
                    continue;
                }

                $parts = explode("\t", $line);
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
     * @param  StandaloneClickhouse  $database
     */
    public function estimateSize(mixed $database, Server $server, ?array $options = null): int
    {
        try {
            $containerName = $this->escapeContainerName($this->getContainerName($database));
            $user = $database->clickhouse_admin_user;
            $password = $database->clickhouse_admin_password;
            $auth = "--user {$user} --password \"{$password}\"";

            if ($options && ! empty($options['tables'])) {
                // Calculate size for specific tables — validate names to prevent SQL injection
                $tableList = array_map(function ($t) {
                    $this->validatePath($t, 'table name');

                    return "'".str_replace("'", "\\'", $t)."'";
                }, $options['tables']);
                $tableListStr = implode(',', $tableList);

                $query = "SELECT SUM(total_bytes) FROM system.tables WHERE database = 'default' AND name IN ({$tableListStr})";
            } else {
                // Calculate total database size
                $query = "SELECT SUM(total_bytes) FROM system.tables WHERE database = 'default'";
            }

            $command = "docker exec {$containerName} clickhouse-client {$auth} --query \"{$query}\"";

            $output = $this->executeCommand([$command], $server, false);

            return (int) trim($output);
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
