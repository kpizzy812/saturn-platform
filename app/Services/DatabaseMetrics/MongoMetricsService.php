<?php

namespace App\Services\DatabaseMetrics;

use App\Traits\FormatHelpers;

/**
 * Service for MongoDB database metrics and operations.
 */
class MongoMetricsService
{
    use FormatHelpers;

    /**
     * Validate field/key name against NoSQL injection using centralized validator.
     */
    private function isValidFieldName(string $name): bool
    {
        return InputValidator::isValidFieldName($name);
    }

    /**
     * Collect MongoDB metrics via SSH.
     */
    public function collectMetrics(mixed $server, mixed $database): array
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
            // Get database stats
            $statsCommand = "docker exec {$containerName} mongosh --quiet --eval ".escapeshellarg('JSON.stringify(db.stats())')." {$dbName} 2>/dev/null || echo '{}'";
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
     * Get MongoDB collections with statistics.
     */
    public function getCollections(mixed $server, mixed $database): array
    {
        $containerName = escapeshellarg($database->uuid);
        $dbName = escapeshellarg($database->mongo_initdb_database ?? 'admin');

        // Get collections with stats
        $command = "docker exec {$containerName} mongosh --quiet --eval \"JSON.stringify(db.getCollectionInfos().map(c => { const stats = db.getCollection(c.name).stats(); return { name: c.name, documentCount: stats.count || 0, size: stats.size || 0, avgObjSize: stats.avgObjSize || 0 }; }))\" {$dbName} 2>/dev/null || echo '[]'";
        $result = trim(instant_remote_process([$command], $server, false) ?? '[]');

        $collections = [];
        $parsed = json_decode($result, true);

        if (is_array($parsed)) {
            foreach ($parsed as $c) {
                $collections[] = [
                    'name' => $c['name'] ?? 'unknown',
                    'documentCount' => $c['documentCount'] ?? 0,
                    'size' => $this->formatBytes($c['size'] ?? 0),
                    'avgDocSize' => $this->formatBytes($c['avgObjSize'] ?? 0),
                ];
            }
        }

        return $collections;
    }

    /**
     * Get MongoDB indexes.
     */
    public function getIndexes(mixed $server, mixed $database): array
    {
        $containerName = escapeshellarg($database->uuid);
        $dbName = escapeshellarg($database->mongo_initdb_database ?? 'admin');

        // Get indexes for all collections
        $command = "docker exec {$containerName} mongosh --quiet --eval \"
            const indexes = [];
            db.getCollectionNames().forEach(collName => {
                db.getCollection(collName).getIndexes().forEach(idx => {
                    indexes.push({
                        collection: collName,
                        name: idx.name,
                        fields: Object.keys(idx.key),
                        unique: idx.unique || false,
                        size: 0
                    });
                });
            });
            JSON.stringify(indexes);
        \" {$dbName} 2>/dev/null || echo '[]'";
        $result = trim(instant_remote_process([$command], $server, false) ?? '[]');

        $indexes = [];
        $parsed = json_decode($result, true);

        if (is_array($parsed)) {
            foreach ($parsed as $idx) {
                $indexes[] = [
                    'collection' => $idx['collection'] ?? 'unknown',
                    'name' => $idx['name'] ?? '',
                    'fields' => $idx['fields'] ?? [],
                    'unique' => $idx['unique'] ?? false,
                    'size' => 'N/A',
                ];
            }
        }

        return $indexes;
    }

    /**
     * Create MongoDB index.
     */
    public function createIndex(mixed $server, mixed $database, string $collection, array $indexSpec, bool $unique = false): array
    {
        $containerName = escapeshellarg($database->uuid);
        $dbName = $database->mongo_initdb_database ?? 'admin';
        $escapedDbName = escapeshellarg($dbName);

        // Validate collection name
        if (empty($collection) || ! InputValidator::isValidCollectionName($collection)) {
            return ['success' => false, 'error' => 'Invalid collection name'];
        }

        if (empty($indexSpec)) {
            return ['success' => false, 'error' => 'Invalid field specification'];
        }

        $indexSpecJson = json_encode($indexSpec);
        $options = $unique ? ', { unique: true }' : '';

        // Build mongosh command and escape for shell — getCollection() for safe collection access
        $mongoCommand = "db.getCollection('{$collection}').createIndex({$indexSpecJson}{$options})";
        $escapedMongoCommand = escapeshellarg($mongoCommand);

        $command = "docker exec {$containerName} mongosh --quiet --eval {$escapedMongoCommand} {$escapedDbName} 2>&1";
        $result = trim(instant_remote_process([$command], $server, false) ?? '');

        if (stripos($result, 'error') !== false || stripos($result, 'exception') !== false) {
            return ['success' => false, 'error' => $result];
        }

        return ['success' => true, 'message' => "Index created on {$collection}"];
    }

    /**
     * Get MongoDB replica set status.
     */
    public function getReplicaSetStatus(mixed $server, mixed $database): array
    {
        $containerName = escapeshellarg($database->uuid);

        // Check replica set status
        $command = "docker exec {$containerName} mongosh --quiet --eval \"
            try {
                const status = rs.status();
                JSON.stringify({
                    enabled: true,
                    name: status.set,
                    members: status.members.map(m => ({
                        host: m.name,
                        state: m.stateStr,
                        health: m.health
                    }))
                });
            } catch (e) {
                JSON.stringify({ enabled: false, name: null, members: [] });
            }
        \" 2>/dev/null || echo '{\"enabled\": false}'";
        $result = trim(instant_remote_process([$command], $server, false) ?? '{"enabled": false}');

        return json_decode($result, true) ?? ['enabled' => false, 'name' => null, 'members' => []];
    }

    /**
     * Get MongoDB storage settings.
     */
    public function getStorageSettings(mixed $server, mixed $database): array
    {
        $containerName = escapeshellarg($database->uuid);

        $settings = [
            'storageEngine' => 'WiredTiger',
            'cacheSize' => null,
            'journalEnabled' => true,
            'directoryPerDb' => false,
        ];

        // Get server status including storage engine info
        $command = "docker exec {$containerName} mongosh --quiet --eval \"
            const status = db.serverStatus();
            const params = db.adminCommand({getParameter: '*'});
            JSON.stringify({
                storageEngine: status.storageEngine?.name || 'WiredTiger',
                cacheSize: status.wiredTiger?.cache ? status.wiredTiger.cache['maximum bytes configured'] : null,
                journalEnabled: status.dur?.journalEnabled ?? true,
                directoryPerDb: params.directoryperdb ?? false
            });
        \" 2>/dev/null || echo '{}'";

        $result = trim(instant_remote_process([$command], $server, false) ?? '{}');
        $parsed = json_decode($result, true);

        if ($parsed && is_array($parsed)) {
            $settings['storageEngine'] = $parsed['storageEngine'] ?? 'WiredTiger';

            if (isset($parsed['cacheSize']) && is_numeric($parsed['cacheSize'])) {
                $settings['cacheSize'] = $this->formatBytes((int) $parsed['cacheSize']);
            } else {
                $settings['cacheSize'] = 'Default (50% RAM)';
            }

            $settings['journalEnabled'] = $parsed['journalEnabled'] ?? true;
            $settings['directoryPerDb'] = $parsed['directoryPerDb'] ?? false;
        }

        return $settings;
    }

    /**
     * Get MongoDB users.
     */
    public function getUsers(mixed $server, mixed $database): array
    {
        $containerName = escapeshellarg($database->uuid);
        $dbName = escapeshellarg($database->mongo_initdb_database ?? 'admin');

        $eval = escapeshellarg("JSON.stringify(db.getUsers().users.map(u => ({user: u.user, roles: u.roles.map(r => r.role).join(', ')})))");
        $command = "docker exec {$containerName} mongosh --quiet --eval {$eval} {$dbName} 2>/dev/null || echo '[]'";
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
     * Get MongoDB collections as "tables".
     */
    public function getTables(mixed $server, mixed $database): array
    {
        $containerName = escapeshellarg($database->uuid);
        $dbName = escapeshellarg($database->mongo_initdb_database ?? 'admin');

        $eval = escapeshellarg('JSON.stringify(db.getCollectionInfos().map(c => { const stats = db.getCollection(c.name).stats(); return { name: c.name, count: stats.count || 0, size: stats.size || 0 }; }))');
        $command = "docker exec {$containerName} mongosh --quiet --eval {$eval} {$dbName} 2>/dev/null || echo '[]'";
        $result = trim(instant_remote_process([$command], $server, false) ?? '[]');

        $tables = [];
        $parsed = json_decode($result, true);
        if (is_array($parsed)) {
            foreach ($parsed as $c) {
                $tables[] = [
                    'name' => $c['name'] ?? 'unknown',
                    'rows' => $c['count'] ?? 0,
                    'size' => $this->formatBytes($c['size'] ?? 0),
                ];
            }
        }

        return $tables;
    }

    /**
     * Get MongoDB collection schema (dynamic - inferred from documents).
     */
    public function getColumns(mixed $server, mixed $database, string $collectionName): array
    {
        $containerName = escapeshellarg($database->uuid);
        $password = escapeshellarg($database->mongo_initdb_root_password ?? '');
        $username = escapeshellarg($database->mongo_initdb_root_username ?? 'root');
        $dbName = escapeshellarg($database->mongo_initdb_database ?? 'admin');

        // Validate collection name to prevent NoSQL injection
        if (! InputValidator::isValidCollectionName($collectionName)) {
            return [['name' => '_id', 'type' => 'ObjectId', 'nullable' => false, 'default' => null, 'is_primary' => true]];
        }

        // Get unique fields from first 100 documents to infer schema
        // Safe: $collectionName validated above, then entire JS expression escaped for shell
        $eval = escapeshellarg("db.getCollection('{$collectionName}').findOne()");
        $command = "docker exec {$containerName} mongosh -u {$username} -p {$password} --authenticationDatabase admin {$dbName} --quiet --eval {$eval} 2>/dev/null || echo '{}'";
        $result = trim(instant_remote_process([$command], $server, false) ?? '{}');

        // Parse JSON to extract field names
        $columns = [];
        try {
            $doc = json_decode($result, true);
            if ($doc && is_array($doc)) {
                foreach ($doc as $key => $value) {
                    $columns[] = [
                        'name' => $key,
                        'type' => $this->inferMongoType($value),
                        'nullable' => true, // MongoDB fields are always optional
                        'default' => null,
                        'is_primary' => $key === '_id',
                    ];
                }
            }
        } catch (\Exception $e) {
            // Fallback: basic _id field
            $columns = [
                ['name' => '_id', 'type' => 'ObjectId', 'nullable' => false, 'default' => null, 'is_primary' => true],
            ];
        }

        return $columns;
    }

    /**
     * Get MongoDB collection data with pagination.
     */
    public function getData(mixed $server, mixed $database, string $collectionName, int $page, int $perPage, string $search, string $orderBy, string $orderDir, string $filters = ''): array
    {
        $containerName = escapeshellarg($database->uuid);
        $password = escapeshellarg($database->mongo_initdb_root_password ?? '');
        $username = escapeshellarg($database->mongo_initdb_root_username ?? 'root');
        $dbName = escapeshellarg($database->mongo_initdb_database ?? 'admin');
        $skip = ($page - 1) * $perPage;

        // Get columns first
        $columns = $this->getColumns($server, $database, $collectionName);

        // Validate collection name to prevent NoSQL injection
        if (! InputValidator::isValidCollectionName($collectionName)) {
            return ['rows' => [], 'total' => 0, 'columns' => $columns];
        }

        // Build search query (text search across all fields, sanitized)
        $searchQuery = '{}';
        if ($search !== '') {
            // Strip chars that could break out of regex context or inject JS
            $escapedSearch = InputValidator::sanitizeMongoSearch($search);
            if ($escapedSearch !== '') {
                $safeColumns = array_filter($columns, fn ($col) => preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $col['name']));
                $searchQuery = '{$or: ['.implode(',', array_map(fn ($col) => "{{$col['name']}: /.*{$escapedSearch}.*/i}}", $safeColumns)).']}';
            }
        }

        // Build sort query — validate $orderBy against known column names to prevent NoSQL injection
        $columnNames = array_column($columns, 'name');
        $safeOrderBy = ($orderBy !== '' && in_array($orderBy, $columnNames, true)) ? $orderBy : '';
        $sortQuery = $safeOrderBy !== '' ? "{{$safeOrderBy}: ".($orderDir === 'asc' ? '1' : '-1').'}' : '{}';

        // Get total count — use getCollection() for safe collection access
        $countEval = escapeshellarg("db.getCollection('{$collectionName}').countDocuments({$searchQuery})");
        $countCommand = "docker exec {$containerName} mongosh -u {$username} -p {$password} --authenticationDatabase admin {$dbName} --quiet --eval {$countEval} 2>/dev/null || echo '0'";
        $total = (int) trim(instant_remote_process([$countCommand], $server, false) ?? '0');

        // Get data — use getCollection() for safe collection access
        $dataEval = escapeshellarg("JSON.stringify(db.getCollection('{$collectionName}').find({$searchQuery}).sort({$sortQuery}).skip({$skip}).limit({$perPage}).toArray())");
        $dataCommand = "docker exec {$containerName} mongosh -u {$username} -p {$password} --authenticationDatabase admin {$dbName} --quiet --eval {$dataEval} 2>/dev/null || echo '[]'";
        $result = trim(instant_remote_process([$dataCommand], $server, false) ?? '[]');

        $rows = [];
        try {
            $docs = json_decode($result, true);
            if ($docs && is_array($docs)) {
                foreach ($docs as $doc) {
                    $row = [];
                    foreach ($columns as $col) {
                        $key = $col['name'];
                        if (isset($doc[$key])) {
                            // Handle MongoDB ObjectId and other special types
                            if (is_array($doc[$key]) && isset($doc[$key]['$oid'])) {
                                $row[$key] = $doc[$key]['$oid'];
                            } else {
                                $row[$key] = is_array($doc[$key]) ? json_encode($doc[$key]) : $doc[$key];
                            }
                        } else {
                            $row[$key] = null;
                        }
                    }
                    $rows[] = $row;
                }
            }
        } catch (\Exception $e) {
            // Return empty result on error
        }

        return [
            'rows' => $rows,
            'total' => $total,
            'columns' => $columns,
        ];
    }

    /**
     * Update MongoDB document.
     */
    public function updateRow(mixed $server, mixed $database, string $collectionName, array $primaryKey, array $updates): bool
    {
        // Validate collection name to prevent NoSQL injection
        if (! InputValidator::isValidCollectionName($collectionName)) {
            return false;
        }

        $containerName = escapeshellarg($database->uuid);
        $password = escapeshellarg($database->mongo_initdb_root_password ?? '');
        $username = escapeshellarg($database->mongo_initdb_root_username ?? 'root');
        $dbName = escapeshellarg($database->mongo_initdb_database ?? 'admin');

        // Build filter — validate key names to prevent NoSQL injection
        $filterParts = [];
        foreach ($primaryKey as $key => $value) {
            if ($key === '_id') {
                // ObjectId must be a 24-char hex string
                if (preg_match('/^[a-f0-9]{24}$/i', (string) $value)) {
                    $filterParts[] = "_id: ObjectId('{$value}')";
                }
            } elseif ($this->isValidFieldName((string) $key)) {
                $escapedValue = json_encode((string) $value);
                $filterParts[] = "{$key}: {$escapedValue}";
            }
        }
        if (empty($filterParts)) {
            return false;
        }

        // Build update — validate key names to prevent NoSQL injection
        $updateParts = [];
        foreach ($updates as $key => $value) {
            if ($key === '_id' || ! $this->isValidFieldName((string) $key)) {
                continue;
            }
            $escapedValue = json_encode((string) $value);
            $updateParts[] = "{$key}: {$escapedValue}";
        }
        if (empty($updateParts)) {
            return false;
        }

        $eval = escapeshellarg("db.getCollection('{$collectionName}').updateOne({".implode(', ', $filterParts).'}, {$set: {'.implode(', ', $updateParts).'}})');
        $command = "docker exec {$containerName} mongosh -u {$username} -p {$password} --authenticationDatabase admin {$dbName} --quiet --eval {$eval} 2>&1";
        $result = instant_remote_process([$command], $server, false);

        return str_contains((string) $result, 'modifiedCount') && ! str_contains((string) $result, 'error');
    }

    /**
     * Delete MongoDB document.
     */
    public function deleteRow(mixed $server, mixed $database, string $collectionName, array $primaryKey): bool
    {
        // Validate collection name to prevent NoSQL injection
        if (! InputValidator::isValidCollectionName($collectionName)) {
            return false;
        }

        $containerName = escapeshellarg($database->uuid);
        $password = escapeshellarg($database->mongo_initdb_root_password ?? '');
        $username = escapeshellarg($database->mongo_initdb_root_username ?? 'root');
        $dbName = escapeshellarg($database->mongo_initdb_database ?? 'admin');

        // Build filter — validate key names to prevent NoSQL injection
        $filterParts = [];
        foreach ($primaryKey as $key => $value) {
            if ($key === '_id') {
                if (preg_match('/^[a-f0-9]{24}$/i', (string) $value)) {
                    $filterParts[] = "_id: ObjectId('{$value}')";
                }
            } elseif ($this->isValidFieldName((string) $key)) {
                $escapedValue = json_encode((string) $value);
                $filterParts[] = "{$key}: {$escapedValue}";
            }
        }
        if (empty($filterParts)) {
            return false;
        }

        $eval = escapeshellarg("db.getCollection('{$collectionName}').deleteOne({".implode(', ', $filterParts).'})');
        $command = "docker exec {$containerName} mongosh -u {$username} -p {$password} --authenticationDatabase admin {$dbName} --quiet --eval {$eval} 2>&1";
        $result = instant_remote_process([$command], $server, false);

        return str_contains((string) $result, 'deletedCount') && ! str_contains((string) $result, 'error');
    }

    /**
     * Create MongoDB document.
     */
    public function createRow(mixed $server, mixed $database, string $collectionName, array $data): bool
    {
        // Validate collection name to prevent NoSQL injection
        if (! InputValidator::isValidCollectionName($collectionName)) {
            return false;
        }

        $containerName = escapeshellarg($database->uuid);
        $password = escapeshellarg($database->mongo_initdb_root_password ?? '');
        $username = escapeshellarg($database->mongo_initdb_root_username ?? 'root');
        $dbName = escapeshellarg($database->mongo_initdb_database ?? 'admin');

        // Build document — validate key names to prevent NoSQL injection
        $docParts = [];
        foreach ($data as $key => $value) {
            if ($key === '_id' || ! $this->isValidFieldName((string) $key)) {
                continue;
            }
            $escapedValue = $value === null ? 'null' : json_encode((string) $value);
            $docParts[] = "{$key}: {$escapedValue}";
        }
        if (empty($docParts)) {
            return false;
        }

        $eval = escapeshellarg("db.getCollection('{$collectionName}').insertOne({".implode(', ', $docParts).'})');
        $command = "docker exec {$containerName} mongosh -u {$username} -p {$password} --authenticationDatabase admin {$dbName} --quiet --eval {$eval} 2>&1";
        $result = instant_remote_process([$command], $server, false);

        return str_contains((string) $result, 'insertedId') && ! str_contains((string) $result, 'error');
    }

    /**
     * Infer MongoDB field type from value.
     */
    private function inferMongoType(mixed $value): string
    {
        if (is_array($value)) {
            return isset($value['$oid']) ? 'ObjectId' : 'Array';
        }
        if (is_string($value)) {
            return 'String';
        }
        if (is_int($value)) {
            return 'Int';
        }
        if (is_float($value)) {
            return 'Double';
        }
        if (is_bool($value)) {
            return 'Boolean';
        }
        if (is_null($value)) {
            return 'Null';
        }

        return 'Mixed';
    }
}
