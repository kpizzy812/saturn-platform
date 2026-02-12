<?php

namespace App\Services\Transfer\Strategies;

use App\Models\Server;
use App\Models\StandaloneDragonfly;
use App\Models\StandaloneKeydb;
use App\Models\StandaloneRedis;

/**
 * Transfer strategy for Redis-compatible databases.
 *
 * Supports Redis, KeyDB, and Dragonfly using DUMP/RESTORE or RDB backup.
 */
class RedisTransferStrategy extends AbstractTransferStrategy
{
    public function getDatabaseType(): string
    {
        return 'redis';
    }

    public function getContainerName(mixed $database): string
    {
        return $database->uuid;
    }

    public function getDumpExtension(): string
    {
        return '.rdb';
    }

    /**
     * Get the password field based on database type.
     */
    private function getPassword(mixed $database): ?string
    {
        if ($database instanceof StandaloneRedis) {
            return $database->redis_password;
        } elseif ($database instanceof StandaloneKeydb) {
            return $database->keydb_password;
        } elseif ($database instanceof StandaloneDragonfly) {
            return $database->dragonfly_password;
        }

        return null;
    }

    /**
     * Build redis-cli auth argument.
     */
    private function buildAuthArg(mixed $database): string
    {
        $password = $this->getPassword($database);

        return $password ? "-a \"{$password}\"" : '';
    }

    /**
     * Create a dump file on the source server.
     *
     * @param  StandaloneRedis|StandaloneKeydb|StandaloneDragonfly  $database
     */
    public function createDump(
        mixed $database,
        Server $server,
        string $dumpPath,
        ?array $options = null
    ): array {
        try {
            $containerName = $this->escapeContainerName($this->getContainerName($database));
            $authArg = $this->buildAuthArg($database);

            // Ensure dump directory exists
            $dumpDir = dirname($dumpPath);
            $this->ensureDirectory($server, $dumpDir);

            if ($options && ! empty($options['key_patterns'])) {
                // Partial transfer using DUMP/RESTORE for specific keys
                return $this->createPartialDump($database, $server, $dumpPath, $options['key_patterns']);
            }

            // Full backup using BGSAVE + copy RDB file
            // Save initial LASTSAVE timestamp, trigger BGSAVE, then wait until LASTSAVE changes
            $commands = [
                // Save initial timestamp and trigger BGSAVE, then poll until LASTSAVE changes
                'INITIAL_LASTSAVE=$(docker exec '.$containerName.' redis-cli '.$authArg.' LASTSAVE) && docker exec '.$containerName.' redis-cli '.$authArg.' BGSAVE && for i in $(seq 1 120); do sleep 1; CURRENT=$(docker exec '.$containerName.' redis-cli '.$authArg.' LASTSAVE); if [ "$CURRENT" != "$INITIAL_LASTSAVE" ]; then break; fi; done',
                // Copy RDB file out
                "docker cp {$containerName}:/data/dump.rdb {$dumpPath}",
            ];

            foreach ($commands as $command) {
                $this->executeCommand([$command], $server, false, 300);
            }

            // Get dump file size
            $size = $this->getFileSize($server, $dumpPath);

            if ($size === 0) {
                return [
                    'success' => false,
                    'size' => 0,
                    'error' => 'Dump file is empty or not created',
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
     * Create a partial dump with specific key patterns.
     */
    private function createPartialDump(
        mixed $database,
        Server $server,
        string $dumpPath,
        array $keyPatterns
    ): array {
        try {
            $containerName = $this->escapeContainerName($this->getContainerName($database));
            $authArg = $this->buildAuthArg($database);

            // For each pattern, scan keys and dump them
            // We'll create a file with KEY:SERIALIZED_DATA format
            $commands = [];

            foreach ($keyPatterns as $pattern) {
                $this->validatePath($pattern, 'key pattern');
                $escapedPattern = escapeshellarg($pattern);

                // Use SCAN to find keys matching pattern and dump them
                $commands[] = "docker exec {$containerName} redis-cli {$authArg} --scan --pattern {$escapedPattern} | while read key; do echo \"KEY:\$key\"; docker exec {$containerName} redis-cli {$authArg} DUMP \"\$key\" | base64; done >> {$dumpPath}.tmp";
            }

            foreach ($commands as $command) {
                $this->executeCommand([$command], $server, false, 1800);
            }

            // Move temp file to final location
            $this->executeCommand(["mv {$dumpPath}.tmp {$dumpPath}"], $server, false);

            $size = $this->getFileSize($server, $dumpPath);

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
     * @param  StandaloneRedis|StandaloneKeydb|StandaloneDragonfly  $database
     */
    public function restoreDump(
        mixed $database,
        Server $server,
        string $dumpPath,
        ?array $options = null
    ): array {
        try {
            $containerName = $this->escapeContainerName($this->getContainerName($database));
            $authArg = $this->buildAuthArg($database);

            // Check if dump file exists
            if (! $this->fileExists($server, $dumpPath)) {
                return [
                    'success' => false,
                    'error' => 'Dump file not found on target server',
                ];
            }

            // Check if it's a partial dump (text file) or full RDB
            $isPartial = $options && ! empty($options['key_patterns']);

            if ($isPartial) {
                return $this->restorePartialDump($database, $server, $dumpPath);
            }

            // Full restore: stop Redis, copy RDB file, restart
            $commands = [
                // Stop Redis gracefully
                "docker exec {$containerName} redis-cli {$authArg} SHUTDOWN NOSAVE || true",
                // Wait for container to stop
                'sleep 2',
                // Copy RDB file into container's data directory
                "docker cp {$dumpPath} {$containerName}:/data/dump.rdb",
                // Start container
                "docker start {$containerName}",
                // Wait for Redis to be ready
                'sleep 3',
            ];

            foreach ($commands as $command) {
                $this->executeCommand([$command], $server, false, 120);
            }

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
     * Restore a partial dump.
     */
    private function restorePartialDump(
        mixed $database,
        Server $server,
        string $dumpPath
    ): array {
        try {
            $containerName = $this->escapeContainerName($this->getContainerName($database));
            $authArg = $this->buildAuthArg($database);

            // Read dump file and restore each key using RESTORE command
            // Format: KEY:keyname\nbase64_data
            $script = <<<'BASH'
while IFS= read -r line; do
    if [[ $line == KEY:* ]]; then
        keyname="${line#KEY:}"
        read -r data
        if [ -n "$data" ]; then
            echo "$data" | base64 -d | docker exec -i CONTAINER redis-cli AUTH_ARG RESTORE "$keyname" 0 - REPLACE 2>/dev/null || true
        fi
    fi
done < DUMPPATH
BASH;

            $script = str_replace('CONTAINER', trim($containerName, "'"), $script);
            $script = str_replace('AUTH_ARG', $authArg, $script);
            $script = str_replace('DUMPPATH', $dumpPath, $script);

            $this->executeCommand([$script], $server, false, 1800);

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
     * Get database structure (key patterns with counts).
     *
     * @param  StandaloneRedis|StandaloneKeydb|StandaloneDragonfly  $database
     */
    public function getStructure(mixed $database, Server $server): array
    {
        try {
            $containerName = $this->escapeContainerName($this->getContainerName($database));
            $authArg = $this->buildAuthArg($database);

            // Get key count by common prefixes
            // First get all keys (limited sample for large databases)
            $command = "docker exec {$containerName} redis-cli {$authArg} --scan --count 1000 | head -10000 | cut -d: -f1 | sort | uniq -c | sort -rn | head -50";

            $output = $this->executeCommand([$command], $server, false, 60);

            $patterns = [];
            $lines = explode("\n", trim($output));

            foreach ($lines as $line) {
                if (empty(trim($line))) {
                    continue;
                }

                preg_match('/\s*(\d+)\s+(.+)/', trim($line), $matches);
                if (count($matches) >= 3) {
                    $count = (int) $matches[1];
                    $prefix = $matches[2];
                    $patterns[] = [
                        'name' => $prefix.':*',
                        'count' => $count,
                        'size_formatted' => "{$count} keys",
                        'size_bytes' => 0, // Redis doesn't easily provide per-pattern size
                    ];
                }
            }

            return $patterns;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Estimate the size of data to be transferred.
     *
     * @param  StandaloneRedis|StandaloneKeydb|StandaloneDragonfly  $database
     */
    public function estimateSize(mixed $database, Server $server, ?array $options = null): int
    {
        try {
            $containerName = $this->escapeContainerName($this->getContainerName($database));
            $authArg = $this->buildAuthArg($database);

            // Get total memory usage
            $command = "docker exec {$containerName} redis-cli {$authArg} INFO memory | grep used_memory: | cut -d: -f2 | tr -d '\\r'";

            $output = $this->executeCommand([$command], $server, false);

            return (int) trim($output);
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
