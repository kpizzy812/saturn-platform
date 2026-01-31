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
     * Collect MongoDB metrics via SSH.
     */
    public function collectMetrics(mixed $server, mixed $database): array
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
     * Get MongoDB collections with statistics.
     */
    public function getCollections(mixed $server, mixed $database): array
    {
        $containerName = $database->uuid;
        $dbName = $database->mongo_initdb_database ?? 'admin';

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
        $containerName = $database->uuid;
        $dbName = $database->mongo_initdb_database ?? 'admin';

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
        if (empty($collection) || ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $collection)) {
            return ['success' => false, 'error' => 'Invalid collection name'];
        }

        if (empty($indexSpec)) {
            return ['success' => false, 'error' => 'Invalid field specification'];
        }

        $indexSpecJson = json_encode($indexSpec);
        $options = $unique ? ', { unique: true }' : '';

        // Build mongosh command and escape for shell
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
        $containerName = $database->uuid;

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
        $containerName = $database->uuid;

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
        $containerName = $database->uuid;
        $dbName = $database->mongo_initdb_database ?? 'admin';

        $command = "docker exec {$containerName} mongosh --quiet --eval \"JSON.stringify(db.getUsers().users.map(u => ({user: u.user, roles: u.roles.map(r => r.role).join(', ')})))\" {$dbName} 2>/dev/null || echo '[]'";
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
        $containerName = $database->uuid;
        $dbName = $database->mongo_initdb_database ?? 'admin';

        $command = "docker exec {$containerName} mongosh --quiet --eval \"JSON.stringify(db.getCollectionInfos().map(c => { const stats = db.getCollection(c.name).stats(); return { name: c.name, count: stats.count || 0, size: stats.size || 0 }; }))\" {$dbName} 2>/dev/null || echo '[]'";
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
        $containerName = $database->uuid;
        $password = $database->mongo_initdb_root_password ?? '';
        $username = $database->mongo_initdb_root_username ?? 'root';
        $dbName = $database->mongo_initdb_database ?? 'admin';

        // Get unique fields from first 100 documents to infer schema
        $command = "docker exec {$containerName} mongosh -u {$username} -p '{$password}' --authenticationDatabase admin {$dbName} --quiet --eval \"db.{$collectionName}.findOne()\" 2>/dev/null || echo '{}'";
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
        $containerName = $database->uuid;
        $password = $database->mongo_initdb_root_password ?? '';
        $username = $database->mongo_initdb_root_username ?? 'root';
        $dbName = $database->mongo_initdb_database ?? 'admin';
        $skip = ($page - 1) * $perPage;

        // Get columns first
        $columns = $this->getColumns($server, $database, $collectionName);

        // Build search query (text search across all fields)
        $searchQuery = '{}';
        if ($search !== '') {
            $escapedSearch = str_replace("'", "\\'", $search);
            $searchQuery = '{$or: ['.implode(',', array_map(fn ($col) => "{{$col['name']}: /.*{$escapedSearch}.*/i}}", $columns)).']}';
        }

        // Build sort query
        $sortQuery = $orderBy !== '' ? "{{$orderBy}: ".($orderDir === 'asc' ? '1' : '-1').'}' : '{}';

        // Get total count
        $countCommand = "docker exec {$containerName} mongosh -u {$username} -p '{$password}' --authenticationDatabase admin {$dbName} --quiet --eval \"db.{$collectionName}.countDocuments({$searchQuery})\" 2>/dev/null || echo '0'";
        $total = (int) trim(instant_remote_process([$countCommand], $server, false) ?? '0');

        // Get data
        $dataCommand = "docker exec {$containerName} mongosh -u {$username} -p '{$password}' --authenticationDatabase admin {$dbName} --quiet --eval \"JSON.stringify(db.{$collectionName}.find({$searchQuery}).sort({$sortQuery}).skip({$skip}).limit({$perPage}).toArray())\" 2>/dev/null || echo '[]'";
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
        $containerName = $database->uuid;
        $password = $database->mongo_initdb_root_password ?? '';
        $username = $database->mongo_initdb_root_username ?? 'root';
        $dbName = $database->mongo_initdb_database ?? 'admin';

        // Build filter (usually by _id)
        $filterParts = [];
        foreach ($primaryKey as $key => $value) {
            if ($key === '_id') {
                $filterParts[] = "_id: ObjectId('{$value}')";
            } else {
                $escapedValue = str_replace("'", "\\'", (string) $value);
                $filterParts[] = "{$key}: '{$escapedValue}'";
            }
        }
        $filter = '{'.implode(', ', $filterParts).'}';

        // Build update
        $updateParts = [];
        foreach ($updates as $key => $value) {
            if ($key === '_id') {
                continue; // Skip _id updates
            }
            $escapedValue = str_replace("'", "\\'", (string) $value);
            $updateParts[] = "{$key}: '{$escapedValue}'";
        }
        $update = '{\$set: {'.implode(', ', $updateParts).'}}';

        $command = "docker exec {$containerName} mongosh -u {$username} -p '{$password}' --authenticationDatabase admin {$dbName} --quiet --eval \"db.{$collectionName}.updateOne({$filter}, {$update})\" 2>&1";
        $result = instant_remote_process([$command], $server, false);

        return str_contains($result ?? '', 'modifiedCount') && ! str_contains($result ?? '', 'error');
    }

    /**
     * Delete MongoDB document.
     */
    public function deleteRow(mixed $server, mixed $database, string $collectionName, array $primaryKey): bool
    {
        $containerName = $database->uuid;
        $password = $database->mongo_initdb_root_password ?? '';
        $username = $database->mongo_initdb_root_username ?? 'root';
        $dbName = $database->mongo_initdb_database ?? 'admin';

        // Build filter (usually by _id)
        $filterParts = [];
        foreach ($primaryKey as $key => $value) {
            if ($key === '_id') {
                $filterParts[] = "_id: ObjectId('{$value}')";
            } else {
                $escapedValue = str_replace("'", "\\'", (string) $value);
                $filterParts[] = "{$key}: '{$escapedValue}'";
            }
        }
        $filter = '{'.implode(', ', $filterParts).'}';

        $command = "docker exec {$containerName} mongosh -u {$username} -p '{$password}' --authenticationDatabase admin {$dbName} --quiet --eval \"db.{$collectionName}.deleteOne({$filter})\" 2>&1";
        $result = instant_remote_process([$command], $server, false);

        return str_contains($result ?? '', 'deletedCount') && ! str_contains($result ?? '', 'error');
    }

    /**
     * Create MongoDB document.
     */
    public function createRow(mixed $server, mixed $database, string $collectionName, array $data): bool
    {
        $containerName = $database->uuid;
        $password = $database->mongo_initdb_root_password ?? '';
        $username = $database->mongo_initdb_root_username ?? 'root';
        $dbName = $database->mongo_initdb_database ?? 'admin';

        // Build document
        $docParts = [];
        foreach ($data as $key => $value) {
            if ($key === '_id') {
                continue; // Let MongoDB generate _id
            }
            if ($value === null) {
                $docParts[] = "{$key}: null";
            } else {
                $escapedValue = str_replace("'", "\\'", (string) $value);
                $docParts[] = "{$key}: '{$escapedValue}'";
            }
        }
        $document = '{'.implode(', ', $docParts).'}';

        $command = "docker exec {$containerName} mongosh -u {$username} -p '{$password}' --authenticationDatabase admin {$dbName} --quiet --eval \"db.{$collectionName}.insertOne({$document})\" 2>&1";
        $result = instant_remote_process([$command], $server, false);

        return str_contains($result ?? '', 'insertedId') && ! str_contains($result ?? '', 'error');
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
