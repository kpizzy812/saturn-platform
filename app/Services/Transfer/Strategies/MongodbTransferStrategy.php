<?php

namespace App\Services\Transfer\Strategies;

use App\Models\Server;
use App\Models\StandaloneMongodb;

/**
 * Transfer strategy for MongoDB databases.
 *
 * Uses mongodump for creating dumps and mongorestore for restoring.
 */
class MongodbTransferStrategy extends AbstractTransferStrategy
{
    public function getDatabaseType(): string
    {
        return 'mongodb';
    }

    public function getContainerName(mixed $database): string
    {
        return $database->uuid;
    }

    public function getDumpExtension(): string
    {
        return '.archive.gz';
    }

    /**
     * Create a dump file on the source server.
     *
     * @param  StandaloneMongodb  $database
     */
    public function createDump(
        mixed $database,
        Server $server,
        string $dumpPath,
        ?array $options = null
    ): array {
        try {
            $containerName = $this->escapeContainerName($this->getContainerName($database));

            // Ensure dump directory exists
            $dumpDir = dirname($dumpPath);
            $this->ensureDirectory($server, $dumpDir);

            // Build mongodump command
            $uri = $database->internal_db_url;
            $isMongoFour = str($database->image)->startsWith('mongo:4');

            // Determine authentication flag based on version
            $authFlag = $isMongoFour ? '' : '--authenticationDatabase=admin';

            // Build collection flags for partial transfer
            $collectionFlags = '';
            if ($options && ! empty($options['collections'])) {
                $dbName = $database->mongo_initdb_database ?? 'admin';
                foreach ($options['collections'] as $collection) {
                    $this->validatePath($collection, 'collection name');
                    $escapedCollection = escapeshellarg($collection);
                    // For partial dumps, we need to specify db and collection
                    $collectionFlags .= " --collection={$escapedCollection}";
                }
                // Add database flag when using collection filter
                $collectionFlags = " --db={$dbName}".$collectionFlags;
            }

            $command = "docker exec {$containerName} mongodump {$authFlag} --uri=\"{$uri}\"{$collectionFlags} --gzip --archive > {$dumpPath}";

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
     * @param  StandaloneMongodb  $database
     */
    public function restoreDump(
        mixed $database,
        Server $server,
        string $dumpPath,
        ?array $options = null
    ): array {
        try {
            $containerName = $this->escapeContainerName($this->getContainerName($database));

            // Check if dump file exists
            if (! $this->fileExists($server, $dumpPath)) {
                return [
                    'success' => false,
                    'error' => 'Dump file not found on target server',
                ];
            }

            $uri = $database->internal_db_url;
            $isMongoFour = str($database->image)->startsWith('mongo:4');

            // Determine authentication flag based on version
            $authFlag = $isMongoFour ? '' : '--authenticationDatabase=admin';

            $command = "docker exec -i {$containerName} mongorestore {$authFlag} --uri=\"{$uri}\" --drop --gzip --archive < {$dumpPath}";

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
     * Get database structure (collections with sizes).
     *
     * @param  StandaloneMongodb  $database
     */
    public function getStructure(mixed $database, Server $server): array
    {
        try {
            $containerName = $this->escapeContainerName($this->getContainerName($database));
            $uri = $database->internal_db_url;
            $dbName = $database->mongo_initdb_database ?? 'admin';
            $isMongoFour = str($database->image)->startsWith('mongo:4');

            // Get list of collections with their stats
            $script = "db.getCollectionNames().forEach(function(c) { var stats = db[c].stats(); print(c + '|' + stats.size + '|' + stats.storageSize); });";

            $authFlag = $isMongoFour ? '' : '--authenticationDatabase admin';

            $command = "docker exec {$containerName} mongosh {$authFlag} --quiet --eval \"{$script}\" \"{$uri}/{$dbName}\"";

            $output = $this->executeCommand([$command], $server, false);

            $collections = [];
            $lines = explode("\n", trim($output));

            foreach ($lines as $line) {
                if (empty(trim($line))) {
                    continue;
                }

                $parts = explode('|', $line);
                if (count($parts) >= 3) {
                    $sizeBytes = (int) trim($parts[1]);
                    $collections[] = [
                        'name' => trim($parts[0]),
                        'size_formatted' => $this->formatBytes($sizeBytes),
                        'size_bytes' => $sizeBytes,
                    ];
                }
            }

            // Sort by size descending
            usort($collections, fn ($a, $b) => $b['size_bytes'] <=> $a['size_bytes']);

            return $collections;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Estimate the size of data to be transferred.
     *
     * @param  StandaloneMongodb  $database
     */
    public function estimateSize(mixed $database, Server $server, ?array $options = null): int
    {
        try {
            $containerName = $this->escapeContainerName($this->getContainerName($database));
            $uri = $database->internal_db_url;
            $dbName = $database->mongo_initdb_database ?? 'admin';
            $isMongoFour = str($database->image)->startsWith('mongo:4');

            $authFlag = $isMongoFour ? '' : '--authenticationDatabase admin';

            if ($options && ! empty($options['collections'])) {
                // Calculate size for specific collections — validate names to prevent JS injection
                $collectionList = array_map(function ($c) {
                    $this->validatePath($c, 'collection name');

                    // Escape single quotes for JS string literals
                    return "'".str_replace(["'", '\\'], ["\\'", '\\\\'], $c)."'";
                }, $options['collections']);
                $collectionListStr = implode(',', $collectionList);

                $script = "var total = 0; [{$collectionListStr}].forEach(function(c) { total += db[c].stats().size; }); print(total);";
            } else {
                // Calculate total database size
                $script = 'print(db.stats().dataSize);';
            }

            $command = "docker exec {$containerName} mongosh {$authFlag} --quiet --eval \"{$script}\" \"{$uri}/{$dbName}\"";

            $output = $this->executeCommand([$command], $server, false);

            return (int) trim($output);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Format bytes to human-readable string.
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);

        return number_format($bytes / pow(1024, $power), 2).' '.$units[$power];
    }
}
