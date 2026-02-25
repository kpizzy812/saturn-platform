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
     * Validate and return safe ORDER BY direction (ASC or DESC only).
     */
    private function safeOrderDir(string $dir): string
    {
        return InputValidator::safeOrderDirection($dir);
    }

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
        $containerName = escapeshellarg($database->uuid);
        $password = escapeshellarg($database->mysql_root_password ?? $database->mysql_password ?? '');

        $settings = [
            'slowQueryLog' => false,
            'binaryLogging' => false,
            'maxConnections' => null,
            'innodbBufferPoolSize' => null,
            'queryCacheSize' => null,
            'queryTimeout' => null,
        ];

        // Get slow_query_log status
        $slowQueryCmd = "docker exec {$containerName} mysql -u root -p{$password} -N -e \"SHOW VARIABLES LIKE 'slow_query_log';\" 2>/dev/null | awk '{print \$2}'";
        $slowQueryLog = trim(instant_remote_process([$slowQueryCmd], $server, false) ?? '');
        $settings['slowQueryLog'] = strtoupper($slowQueryLog) === 'ON';

        // Get log_bin status (binary logging)
        $logBinCmd = "docker exec {$containerName} mysql -u root -p{$password} -N -e \"SHOW VARIABLES LIKE 'log_bin';\" 2>/dev/null | awk '{print \$2}'";
        $logBin = trim(instant_remote_process([$logBinCmd], $server, false) ?? '');
        $settings['binaryLogging'] = strtoupper($logBin) === 'ON';

        // Get max_connections
        $maxConnCmd = "docker exec {$containerName} mysql -u root -p{$password} -N -e \"SHOW VARIABLES LIKE 'max_connections';\" 2>/dev/null | awk '{print \$2}'";
        $maxConnections = trim(instant_remote_process([$maxConnCmd], $server, false) ?? '');
        if (is_numeric($maxConnections)) {
            $settings['maxConnections'] = (int) $maxConnections;
        }

        // Get innodb_buffer_pool_size
        $bufferPoolCmd = "docker exec {$containerName} mysql -u root -p{$password} -N -e \"SHOW VARIABLES LIKE 'innodb_buffer_pool_size';\" 2>/dev/null | awk '{print \$2}'";
        $bufferPoolSize = trim(instant_remote_process([$bufferPoolCmd], $server, false) ?? '');
        if (is_numeric($bufferPoolSize)) {
            $settings['innodbBufferPoolSize'] = $this->formatBytes((int) $bufferPoolSize);
        }

        // Get query_cache_size (deprecated in MySQL 8.0 but still available in MariaDB)
        $queryCacheCmd = "docker exec {$containerName} mysql -u root -p{$password} -N -e \"SHOW VARIABLES LIKE 'query_cache_size';\" 2>/dev/null | awk '{print \$2}'";
        $queryCacheSize = trim(instant_remote_process([$queryCacheCmd], $server, false) ?? '');
        if (is_numeric($queryCacheSize)) {
            $settings['queryCacheSize'] = $this->formatBytes((int) $queryCacheSize);
        }

        // Get wait_timeout (query timeout)
        $timeoutCmd = "docker exec {$containerName} mysql -u root -p{$password} -N -e \"SHOW VARIABLES LIKE 'wait_timeout';\" 2>/dev/null | awk '{print \$2}'";
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
        $containerName = escapeshellarg($database->uuid);
        $password = escapeshellarg($database->mysql_root_password ?? $database->mysql_password ?? '');

        $sql = escapeshellarg("SELECT user, 'Standard' as role, 0 as connections FROM mysql.user WHERE user NOT IN ('root', 'mysql.sys', 'mysql.session', 'mysql.infoschema');");
        $command = "docker exec {$containerName} mysql -u root -p{$password} -N -e {$sql} 2>/dev/null || echo ''";
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
        $containerName = escapeshellarg($database->uuid);
        $password = escapeshellarg($database->mysql_root_password ?? $database->mysql_password ?? '');

        $sql = escapeshellarg("SELECT ID, USER, DB, COMMAND, TIME, INFO, HOST FROM INFORMATION_SCHEMA.PROCESSLIST WHERE COMMAND != 'Daemon' ORDER BY TIME DESC LIMIT 50;");
        $command = "docker exec {$containerName} mysql -u root -p{$password} -N -e {$sql} 2>/dev/null || echo ''";
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
        $containerName = escapeshellarg($database->uuid);
        $password = escapeshellarg($database->mysql_root_password ?? $database->mysql_password ?? '');

        $sql = escapeshellarg("KILL {$pid};");
        $command = "docker exec {$containerName} mysql -u root -p{$password} -e {$sql} 2>&1";
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
        $containerName = escapeshellarg($database->uuid);
        $password = escapeshellarg($database->mysql_root_password ?? $database->mysql_password ?? '');
        $dbName = $database->mysql_database ?? 'mysql';

        $escapedDbName = str_replace("'", "''", $dbName);
        $sql = escapeshellarg("SELECT TABLE_NAME, TABLE_ROWS, CONCAT(ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024, 1), ' KB') AS size FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '{$escapedDbName}' ORDER BY TABLE_ROWS DESC LIMIT 100;");
        $command = "docker exec {$containerName} mysql -u root -p{$password} -N -e {$sql} 2>/dev/null || echo ''";
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
        $containerName = escapeshellarg($database->uuid);
        $password = escapeshellarg($database->mysql_root_password ?? $database->mysql_password ?? '');
        $dbName = $database->mysql_database ?? 'mysql';
        $escapedDbName = escapeshellarg($dbName);

        // Escape table and database names to prevent SQL injection
        $escapedDbNameSql = str_replace("'", "''", $dbName);
        $escapedTableNameSql = str_replace("'", "''", $tableName);

        $query = "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, (CASE WHEN COLUMN_KEY = 'PRI' THEN 1 ELSE 0 END) as is_primary FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '{$escapedDbNameSql}' AND TABLE_NAME = '{$escapedTableNameSql}' ORDER BY ORDINAL_POSITION";
        $escapedQuery = escapeshellarg($query);

        $command = "docker exec {$containerName} mysql -u root -p{$password} -D {$escapedDbName} -N -e {$escapedQuery} 2>/dev/null | awk -F'\\t' '{print \$1\"|\"\$2\"|\"\$3\"|\"\$4\"|\"\$5}' || echo ''";
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
        $containerName = escapeshellarg($database->uuid);
        $password = escapeshellarg($database->mysql_root_password ?? $database->mysql_password ?? '');
        $dbName = escapeshellarg($database->mysql_database ?? 'mysql');
        $offset = ($page - 1) * $perPage;

        // Get columns first
        $columns = $this->getColumns($server, $database, $tableName);
        $columnNames = array_map(fn ($c) => $c['name'], $columns);

        // Validate table name to prevent SQL injection
        if (! InputValidator::isValidTableName($tableName)) {
            return ['rows' => [], 'total' => 0, 'columns' => $columns];
        }
        $escapedTableName = str_replace('`', '``', $tableName);

        // Build WHERE clause
        $whereConditions = [];

        // Add search condition if provided (sanitized to prevent SQL injection)
        if ($search !== '') {
            $escapedSearch = InputValidator::sanitizeSearch($search);
            $safeColumns = array_filter($columnNames, fn ($col) => InputValidator::isValidColumnName($col));
            $searchConditions = array_map(fn ($col) => "LOWER(CAST(`{$col}` AS CHAR)) LIKE LOWER('%{$escapedSearch}%')", $safeColumns);
            if (! empty($searchConditions)) {
                $whereConditions[] = '('.implode(' OR ', $searchConditions).')';
            }
        }

        // Raw $filters removed — was a SQL injection vector (V5 audit SEC-CRIT-2)

        $whereClause = count($whereConditions) > 0 ? 'WHERE '.implode(' AND ', $whereConditions) : '';

        // Build ORDER BY clause — validate direction against whitelist
        $orderClause = '';
        if ($orderBy !== '' && in_array($orderBy, $columnNames)) {
            $safeDir = $this->safeOrderDir($orderDir);
            $orderClause = "ORDER BY `{$orderBy}` {$safeDir}";
        }

        // Get total count — use escapeshellarg to prevent shell injection
        $countQuery = "SELECT COUNT(*) FROM `{$escapedTableName}` {$whereClause}";
        $countCommand = "docker exec {$containerName} mysql -u root -p{$password} -D {$dbName} -N -e ".escapeshellarg($countQuery)." 2>/dev/null || echo '0'";
        $total = (int) trim(instant_remote_process([$countCommand], $server, false) ?? '0');

        // Get data — use escapeshellarg to prevent shell injection
        $dataQuery = "SELECT * FROM `{$escapedTableName}` {$whereClause} {$orderClause} LIMIT {$perPage} OFFSET {$offset}";
        $dataCommand = "docker exec {$containerName} mysql -u root -p{$password} -D {$dbName} -N -e ".escapeshellarg($dataQuery)." 2>/dev/null | awk -F'\\t' 'BEGIN{OFS=\"|\"} {for(i=1;i<=NF;i++) printf \"%s%s\", \$i, (i==NF?\"\\n\":OFS)}' || echo ''";
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
     * Validate column name against injection using centralized validator.
     */
    private function isValidColumnName(string $column): bool
    {
        return InputValidator::isValidColumnName($column);
    }

    /**
     * Update MySQL row.
     */
    public function updateRow(mixed $server, mixed $database, string $tableName, array $primaryKey, array $updates): bool
    {
        $containerName = escapeshellarg($database->uuid);
        $password = escapeshellarg($database->mysql_root_password ?? $database->mysql_password ?? '');
        $dbName = $database->mysql_database ?? 'mysql';
        $escapedDbName = escapeshellarg($dbName);
        $escapedTableName = str_replace('`', '``', $tableName);

        // Build SET clause — validate column names to prevent SQL injection
        $setClauses = [];
        foreach ($updates as $column => $value) {
            if (! $this->isValidColumnName((string) $column)) {
                continue;
            }
            $escapedValue = str_replace("'", "''", (string) $value);
            $setClauses[] = "`{$column}` = '{$escapedValue}'";
        }
        if (empty($setClauses)) {
            return false;
        }

        // Build WHERE clause — validate column names to prevent SQL injection
        $whereClauses = [];
        foreach ($primaryKey as $column => $value) {
            if (! $this->isValidColumnName((string) $column)) {
                continue;
            }
            $escapedValue = str_replace("'", "''", (string) $value);
            $whereClauses[] = "`{$column}` = '{$escapedValue}'";
        }
        if (empty($whereClauses)) {
            return false;
        }

        $query = "UPDATE `{$escapedTableName}` SET ".implode(', ', $setClauses).' WHERE '.implode(' AND ', $whereClauses);
        $command = "docker exec {$containerName} mysql -u root -p{$password} -D {$escapedDbName} -e ".escapeshellarg($query).' 2>&1';
        $result = instant_remote_process([$command], $server, false);

        return ! str_contains($result ?? '', 'ERROR');
    }

    /**
     * Delete MySQL row.
     */
    public function deleteRow(mixed $server, mixed $database, string $tableName, array $primaryKey): bool
    {
        $containerName = escapeshellarg($database->uuid);
        $password = escapeshellarg($database->mysql_root_password ?? $database->mysql_password ?? '');
        $dbName = $database->mysql_database ?? 'mysql';
        $escapedDbName = escapeshellarg($dbName);
        $escapedTableName = str_replace('`', '``', $tableName);

        // Build WHERE clause — validate column names to prevent SQL injection
        $whereClauses = [];
        foreach ($primaryKey as $column => $value) {
            if (! $this->isValidColumnName((string) $column)) {
                continue;
            }
            $escapedValue = str_replace("'", "''", (string) $value);
            $whereClauses[] = "`{$column}` = '{$escapedValue}'";
        }
        if (empty($whereClauses)) {
            return false;
        }

        $query = "DELETE FROM `{$escapedTableName}` WHERE ".implode(' AND ', $whereClauses);
        $command = "docker exec {$containerName} mysql -u root -p{$password} -D {$escapedDbName} -e ".escapeshellarg($query).' 2>&1';
        $result = instant_remote_process([$command], $server, false);

        return ! str_contains($result ?? '', 'ERROR');
    }

    /**
     * Create MySQL row.
     */
    public function createRow(mixed $server, mixed $database, string $tableName, array $data): bool
    {
        $containerName = escapeshellarg($database->uuid);
        $password = escapeshellarg($database->mysql_root_password ?? $database->mysql_password ?? '');
        $dbName = $database->mysql_database ?? 'mysql';
        $escapedDbName = escapeshellarg($dbName);
        $escapedTableName = str_replace('`', '``', $tableName);

        // Build columns and values — validate column names to prevent SQL injection
        $safeColumns = [];
        $values = [];
        foreach ($data as $column => $value) {
            if (! $this->isValidColumnName((string) $column)) {
                continue;
            }
            $safeColumns[] = "`{$column}`";
            if ($value === null) {
                $values[] = 'NULL';
            } else {
                $escapedValue = str_replace("'", "''", (string) $value);
                $values[] = "'{$escapedValue}'";
            }
        }
        if (empty($safeColumns)) {
            return false;
        }

        $query = "INSERT INTO `{$escapedTableName}` (".implode(', ', $safeColumns).') VALUES ('.implode(', ', $values).')';
        $command = "docker exec {$containerName} mysql -u root -p{$password} -D {$escapedDbName} -e ".escapeshellarg($query).' 2>&1';
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
