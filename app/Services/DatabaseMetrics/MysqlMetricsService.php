<?php

namespace App\Services\DatabaseMetrics;

use App\Traits\FormatHelpers;

/**
 * Service for MySQL/MariaDB database metrics and operations.
 */
class MysqlMetricsService
{
    use FormatHelpers;

    /**
     * Collect MySQL/MariaDB metrics via SSH.
     */
    public function collectMetrics(mixed $server, mixed $database): array
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
     * Get MySQL/MariaDB settings.
     */
    public function getSettings(mixed $server, mixed $database): array
    {
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

        return $settings;
    }

    /**
     * Get MySQL/MariaDB users.
     */
    public function getUsers(mixed $server, mixed $database): array
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
     * Create MySQL user.
     */
    public function createUser(mixed $server, mixed $database, string $username, string $password): array
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
     * Delete MySQL user.
     */
    public function deleteUser(mixed $server, mixed $database, string $username): array
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
     * Get MySQL/MariaDB active connections.
     */
    public function getActiveConnections(mixed $server, mixed $database): array
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
                        'clientAddr' => explode(':', $parts[6] ?? '')[0],
                    ];
                }
            }
        }

        return $connections;
    }

    /**
     * Kill MySQL connection.
     */
    public function killConnection(mixed $server, mixed $database, int $pid): bool
    {
        $containerName = $database->uuid;
        $password = $database->mysql_root_password ?? $database->mysql_password ?? '';

        $command = "docker exec {$containerName} mysql -u root -p'{$password}' -e \"KILL {$pid};\" 2>&1";
        $result = instant_remote_process([$command], $server, false) ?? '';

        return stripos($result, 'ERROR') === false;
    }

    /**
     * Execute MySQL/MariaDB query.
     */
    public function executeQuery(mixed $server, mixed $database, string $query): array
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
     * Get MySQL/MariaDB tables with row count and size.
     */
    public function getTables(mixed $server, mixed $database): array
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
     * Get MySQL column schema.
     */
    public function getColumns(mixed $server, mixed $database, string $tableName): array
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
     * Get MySQL table data with pagination and filters.
     */
    public function getData(mixed $server, mixed $database, string $tableName, int $page, int $perPage, string $search, string $orderBy, string $orderDir, string $filters = ''): array
    {
        $containerName = $database->uuid;
        $password = $database->mysql_root_password ?? $database->mysql_password ?? '';
        $dbName = $database->mysql_database ?? 'mysql';
        $offset = ($page - 1) * $perPage;

        // Get columns first
        $columns = $this->getColumns($server, $database, $tableName);
        $columnNames = array_map(fn ($c) => $c['name'], $columns);

        // Escape table name to prevent SQL injection
        $escapedTableName = str_replace('`', '``', $tableName);

        // Build WHERE clause
        $whereConditions = [];

        // Add search condition if provided
        if ($search !== '') {
            $escapedSearch = str_replace("'", "''", $search);
            $searchConditions = array_map(fn ($col) => "LOWER(CAST(`{$col}` AS CHAR)) LIKE LOWER('%{$escapedSearch}%')", $columnNames);
            $whereConditions[] = '('.implode(' OR ', $searchConditions).')';
        }

        // Add filter conditions if provided (convert PostgreSQL syntax to MySQL)
        if ($filters !== '') {
            // Convert PostgreSQL ILIKE to MySQL LIKE
            $mysqlFilters = str_replace('ILIKE', 'LIKE', $filters);
            // Convert double quotes to backticks for column names
            $mysqlFilters = preg_replace('/"([^"]+)"/', '`$1`', $mysqlFilters);
            $whereConditions[] = "({$mysqlFilters})";
        }

        $whereClause = count($whereConditions) > 0 ? 'WHERE '.implode(' AND ', $whereConditions) : '';

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
     * Update MySQL row.
     */
    public function updateRow(mixed $server, mixed $database, string $tableName, array $primaryKey, array $updates): bool
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
     * Delete MySQL row.
     */
    public function deleteRow(mixed $server, mixed $database, string $tableName, array $primaryKey): bool
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
     * Create MySQL row.
     */
    public function createRow(mixed $server, mixed $database, string $tableName, array $data): bool
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
