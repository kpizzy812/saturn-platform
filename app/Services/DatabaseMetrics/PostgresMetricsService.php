<?php

namespace App\Services\DatabaseMetrics;

use App\Traits\FormatHelpers;
use Illuminate\Support\Facades\Log;

/**
 * Service for PostgreSQL database metrics and operations.
 */
class PostgresMetricsService
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
     * Quote a table name for safe use in SQL, handling schema-qualified names.
     * "public.system_config" → "public"."system_config"
     * "my_table" → "my_table"
     */
    private function quoteTableName(string $tableName): string
    {
        if (str_contains($tableName, '.')) {
            $parts = explode('.', $tableName, 2);

            return '"'.str_replace('"', '""', $parts[0]).'"."'.str_replace('"', '""', $parts[1]).'"';
        }

        return '"'.str_replace('"', '""', $tableName).'"';
    }

    /**
     * Collect PostgreSQL metrics via SSH.
     */
    public function collectMetrics(mixed $server, mixed $database): array
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
            $escapedDbNameSql = str_replace("'", "''", $dbNameRaw);
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
            Log::debug('Failed to collect PostgreSQL metrics', [
                'database_uuid' => $database->uuid ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return $metrics;
    }

    /**
     * Get PostgreSQL extensions.
     */
    public function getExtensions(mixed $server, mixed $database): array
    {
        $containerName = escapeshellarg($database->uuid);
        $user = escapeshellarg($database->postgres_user ?? 'postgres');
        $dbName = escapeshellarg($database->postgres_db ?? 'postgres');

        // Get installed extensions
        $sql = escapeshellarg("SELECT e.extname, e.extversion, 'installed' as status, c.comment FROM pg_extension e LEFT JOIN pg_available_extensions c ON e.extname = c.name ORDER BY e.extname;");
        $command = "docker exec {$containerName} psql -U {$user} -d {$dbName} -t -A -F '|' -c {$sql} 2>/dev/null || echo ''";
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
        $availableSql = escapeshellarg('SELECT name, default_version, comment FROM pg_available_extensions WHERE installed_version IS NULL ORDER BY name LIMIT 20;');
        $availableCommand = "docker exec {$containerName} psql -U {$user} -d {$dbName} -t -A -F '|' -c {$availableSql} 2>/dev/null || echo ''";
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

        return $extensions;
    }

    /**
     * Toggle PostgreSQL extension.
     */
    public function toggleExtension(mixed $server, mixed $database, string $extensionName, bool $enable): array
    {
        // Validate extension name to prevent SQL injection
        if (! InputValidator::isValidExtensionName($extensionName)) {
            return ['success' => false, 'error' => 'Invalid extension name format'];
        }

        $containerName = escapeshellarg($database->uuid);
        $user = escapeshellarg($database->postgres_user ?? 'postgres');
        $dbName = escapeshellarg($database->postgres_db ?? 'postgres');

        $sql = $enable ? "CREATE EXTENSION IF NOT EXISTS \"{$extensionName}\"" : "DROP EXTENSION IF EXISTS \"{$extensionName}\"";
        $escapedSql = escapeshellarg("{$sql};");
        $command = "docker exec {$containerName} psql -U {$user} -d {$dbName} -c {$escapedSql} 2>&1";
        $result = trim(instant_remote_process([$command], $server, false) ?? '');

        // Check for error in result
        if (stripos($result, 'ERROR') !== false) {
            return ['success' => false, 'error' => $result];
        }

        return [
            'success' => true,
            'message' => $enable ? "Extension {$extensionName} enabled" : "Extension {$extensionName} disabled",
        ];
    }

    /**
     * Get PostgreSQL users.
     */
    public function getUsers(mixed $server, mixed $database): array
    {
        $containerName = escapeshellarg($database->uuid);
        $user = escapeshellarg($database->postgres_user ?? 'postgres');
        $dbName = escapeshellarg($database->postgres_db ?? 'postgres');

        $sql = escapeshellarg("SELECT r.rolname, CASE WHEN r.rolsuper THEN 'Superuser' WHEN r.rolcreaterole THEN 'Admin' ELSE 'Standard' END as role_type, (SELECT count(*) FROM pg_stat_activity WHERE usename = r.rolname AND state = 'active') as connections FROM pg_roles r WHERE r.rolcanlogin = true ORDER BY r.rolname;");
        $command = "docker exec {$containerName} psql -U {$user} -d {$dbName} -t -A -F '|' -c {$sql} 2>/dev/null || echo ''";
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
     * Create PostgreSQL user.
     */
    public function createUser(mixed $server, mixed $database, string $username, string $password): array
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
     * Delete PostgreSQL user.
     */
    public function deleteUser(mixed $server, mixed $database, string $username): array
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
     * Run PostgreSQL maintenance (VACUUM or ANALYZE).
     */
    public function runMaintenance(mixed $server, mixed $database, string $operation): array
    {
        // Whitelist validation: only known maintenance operations are allowed
        try {
            $sql = InputValidator::validateMaintenanceOperation($operation);
        } catch (\InvalidArgumentException $e) {
            return ['success' => false, 'error' => 'Invalid maintenance operation. Allowed: '.implode(', ', InputValidator::POSTGRES_MAINTENANCE_OPS)];
        }

        $containerName = escapeshellarg($database->uuid);
        $user = escapeshellarg($database->postgres_user ?? 'postgres');
        $dbName = escapeshellarg($database->postgres_db ?? 'postgres');

        $escapedSql = escapeshellarg("{$sql};");
        $command = "docker exec {$containerName} psql -U {$user} -d {$dbName} -c {$escapedSql} 2>&1";
        $result = trim(instant_remote_process([$command], $server, false) ?? '');

        if (stripos($result, 'ERROR') !== false) {
            return ['success' => false, 'error' => $result];
        }

        return ['success' => true, 'message' => "{$sql} completed successfully"];
    }

    /**
     * Get PostgreSQL active connections.
     */
    public function getActiveConnections(mixed $server, mixed $database): array
    {
        $containerName = escapeshellarg($database->uuid);
        $user = escapeshellarg($database->postgres_user ?? 'postgres');
        $dbName = escapeshellarg($database->postgres_db ?? 'postgres');

        $sql = escapeshellarg("SELECT pid, usename, datname, state, COALESCE(query, '<IDLE>'), COALESCE(EXTRACT(EPOCH FROM (now() - query_start))::text, '0'), COALESCE(client_addr::text, 'local') FROM pg_stat_activity WHERE pid <> pg_backend_pid() ORDER BY query_start DESC NULLS LAST LIMIT 50;");
        $command = "docker exec {$containerName} psql -U {$user} -d {$dbName} -t -A -F '|' -c {$sql} 2>/dev/null || echo ''";
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
     * Kill PostgreSQL connection.
     */
    public function killConnection(mixed $server, mixed $database, int $pid): bool
    {
        $containerName = escapeshellarg($database->uuid);
        $user = escapeshellarg($database->postgres_user ?? 'postgres');
        $dbName = escapeshellarg($database->postgres_db ?? 'postgres');

        $sql = escapeshellarg("SELECT pg_terminate_backend({$pid});");
        $command = "docker exec {$containerName} psql -U {$user} -d {$dbName} -t -c {$sql} 2>&1";
        $result = trim(instant_remote_process([$command], $server, false) ?? '');

        return stripos($result, 't') !== false;
    }

    /**
     * Execute PostgreSQL query.
     */
    public function executeQuery(mixed $server, mixed $database, string $query): array
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
     * Get PostgreSQL tables with row count and size.
     */
    public function getTables(mixed $server, mixed $database): array
    {
        $containerName = escapeshellarg($database->uuid);
        $user = escapeshellarg($database->postgres_user ?? 'postgres');
        $dbName = escapeshellarg($database->postgres_db ?? 'postgres');

        $sql = escapeshellarg("SELECT schemaname || '.' || relname, n_live_tup, pg_size_pretty(pg_total_relation_size(schemaname || '.' || relname)) FROM pg_stat_user_tables ORDER BY n_live_tup DESC LIMIT 100;");
        $command = "docker exec {$containerName} psql -U {$user} -d {$dbName} -t -A -F '|' -c {$sql} 2>/dev/null || echo ''";
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
     * Get PostgreSQL column schema.
     */
    public function getColumns(mixed $server, mixed $database, string $tableName): array
    {
        $containerName = escapeshellarg($database->uuid);
        $user = escapeshellarg($database->postgres_user ?? 'postgres');
        $dbName = escapeshellarg($database->postgres_db ?? 'postgres');

        // Escape table name for schema.table format
        $escapedTable = str_contains($tableName, '.') ? $tableName : "public.{$tableName}";
        [$schema, $table] = str_contains($tableName, '.') ? explode('.', $tableName, 2) : ['public', $tableName];

        // SQL-escape schema and table names to prevent injection
        $safeSchema = str_replace("'", "''", $schema);
        $safeTable = str_replace("'", "''", $table);

        $query = "SELECT
            column_name,
            data_type,
            is_nullable,
            column_default,
            (SELECT count(*) FROM information_schema.key_column_usage kcu
             JOIN information_schema.table_constraints tc
             ON kcu.constraint_name = tc.constraint_name
             WHERE tc.constraint_type = 'PRIMARY KEY'
             AND kcu.table_schema = '{$safeSchema}'
             AND kcu.table_name = '{$safeTable}'
             AND kcu.column_name = c.column_name) as is_primary
        FROM information_schema.columns c
        WHERE table_schema = '{$safeSchema}' AND table_name = '{$safeTable}'
        ORDER BY ordinal_position";

        $escapedQuery = escapeshellarg($query);
        $command = "docker exec {$containerName} psql -U {$user} -d {$dbName} -t -A -F '|' -c {$escapedQuery} 2>/dev/null || echo ''";
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
    public function getData(mixed $server, mixed $database, string $tableName, int $page, int $perPage, string $search, string $orderBy, string $orderDir, string $filters = ''): array
    {
        $containerName = escapeshellarg($database->uuid);
        $user = escapeshellarg($database->postgres_user ?? 'postgres');
        $dbName = escapeshellarg($database->postgres_db ?? 'postgres');
        $offset = ($page - 1) * $perPage;

        // Get columns first
        $columns = $this->getColumns($server, $database, $tableName);
        $columnNames = array_map(fn ($c) => $c['name'], $columns);

        // Build WHERE clause
        $whereConditions = [];

        // Add search condition if provided (sanitized to prevent SQL injection)
        if ($search !== '') {
            $escapedSearch = InputValidator::sanitizeSearch($search);
            $safeColumns = array_filter($columnNames, fn ($col) => InputValidator::isValidColumnName($col));
            $searchConditions = array_map(fn ($col) => "CAST(\"{$col}\" AS TEXT) ILIKE '%{$escapedSearch}%'", $safeColumns);
            if (! empty($searchConditions)) {
                $whereConditions[] = '('.implode(' OR ', $searchConditions).')';
            }
        }

        // Raw $filters removed — was a SQL injection vector (V5 audit SEC-CRIT-1)

        $whereClause = count($whereConditions) > 0 ? 'WHERE '.implode(' AND ', $whereConditions) : '';

        // Build ORDER BY clause — validate direction against whitelist
        $orderClause = '';
        if ($orderBy !== '' && in_array($orderBy, $columnNames)) {
            $safeDir = $this->safeOrderDir($orderDir);
            $orderClause = "ORDER BY \"{$orderBy}\" {$safeDir}";
        }

        // Validate table name to prevent SQL injection
        if (! InputValidator::isValidTableName($tableName)) {
            return ['rows' => [], 'total' => 0, 'columns' => $columns];
        }

        $safeTableName = $this->quoteTableName($tableName);

        // Get total count — use escapeshellarg to prevent shell injection from WHERE clause
        $countQuery = "SELECT COUNT(*) FROM {$safeTableName} {$whereClause}";
        $countCommand = "docker exec {$containerName} psql -U {$user} -d {$dbName} -t -A -c ".escapeshellarg($countQuery)." 2>/dev/null || echo '0'";
        $total = (int) trim(instant_remote_process([$countCommand], $server, false) ?? '0');

        // Get data — use escapeshellarg to prevent shell injection
        $dataQuery = "SELECT * FROM {$safeTableName} {$whereClause} {$orderClause} LIMIT {$perPage} OFFSET {$offset}";
        $dataCommand = "docker exec {$containerName} psql -U {$user} -d {$dbName} -t -A -F '|' -c ".escapeshellarg($dataQuery)." 2>/dev/null || echo ''";
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
     * Validate column name against injection using centralized validator.
     */
    private function isValidColumnName(string $column): bool
    {
        return InputValidator::isValidColumnName($column);
    }

    /**
     * Update PostgreSQL row.
     */
    public function updateRow(mixed $server, mixed $database, string $tableName, array $primaryKey, array $updates): bool
    {
        $containerName = escapeshellarg($database->uuid);
        $user = escapeshellarg($database->postgres_user ?? 'postgres');
        $dbName = escapeshellarg($database->postgres_db ?? 'postgres');
        $safeTableName = $this->quoteTableName($tableName);

        // Build SET clause — validate column names to prevent SQL injection
        $setClauses = [];
        foreach ($updates as $column => $value) {
            if (! $this->isValidColumnName((string) $column)) {
                continue;
            }
            $escapedValue = str_replace("'", "''", (string) $value);
            $setClauses[] = "\"{$column}\" = '{$escapedValue}'";
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
            $whereClauses[] = "\"{$column}\" = '{$escapedValue}'";
        }
        if (empty($whereClauses)) {
            return false;
        }

        $query = "UPDATE {$safeTableName} SET ".implode(', ', $setClauses).' WHERE '.implode(' AND ', $whereClauses);
        $command = "docker exec {$containerName} psql -U {$user} -d {$dbName} -c ".escapeshellarg($query).' 2>&1';
        $result = instant_remote_process([$command], $server, false);

        return str_contains($result ?? '', 'UPDATE');
    }

    /**
     * Delete PostgreSQL row.
     */
    public function deleteRow(mixed $server, mixed $database, string $tableName, array $primaryKey): bool
    {
        $containerName = escapeshellarg($database->uuid);
        $user = escapeshellarg($database->postgres_user ?? 'postgres');
        $dbName = escapeshellarg($database->postgres_db ?? 'postgres');
        $safeTableName = $this->quoteTableName($tableName);

        // Build WHERE clause — validate column names to prevent SQL injection
        $whereClauses = [];
        foreach ($primaryKey as $column => $value) {
            if (! $this->isValidColumnName((string) $column)) {
                continue;
            }
            $escapedValue = str_replace("'", "''", (string) $value);
            $whereClauses[] = "\"{$column}\" = '{$escapedValue}'";
        }
        if (empty($whereClauses)) {
            return false;
        }

        $query = "DELETE FROM {$safeTableName} WHERE ".implode(' AND ', $whereClauses);
        $command = "docker exec {$containerName} psql -U {$user} -d {$dbName} -c ".escapeshellarg($query).' 2>&1';
        $result = instant_remote_process([$command], $server, false);

        return str_contains($result ?? '', 'DELETE');
    }

    /**
     * Create PostgreSQL row.
     */
    public function createRow(mixed $server, mixed $database, string $tableName, array $data): bool
    {
        $containerName = escapeshellarg($database->uuid);
        $user = escapeshellarg($database->postgres_user ?? 'postgres');
        $dbName = escapeshellarg($database->postgres_db ?? 'postgres');
        $safeTableName = $this->quoteTableName($tableName);

        // Build columns and values — validate column names to prevent SQL injection
        $safeColumns = [];
        $values = [];
        foreach ($data as $column => $value) {
            if (! $this->isValidColumnName((string) $column)) {
                continue;
            }
            $safeColumns[] = "\"{$column}\"";
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

        $query = "INSERT INTO {$safeTableName} (".implode(', ', $safeColumns).') VALUES ('.implode(', ', $values).')';
        $command = "docker exec {$containerName} psql -U {$user} -d {$dbName} -c ".escapeshellarg($query).' 2>&1';
        $result = instant_remote_process([$command], $server, false);

        return str_contains($result ?? '', 'INSERT');
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
