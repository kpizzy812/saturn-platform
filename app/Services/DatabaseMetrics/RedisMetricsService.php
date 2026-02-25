<?php

namespace App\Services\DatabaseMetrics;

use App\Traits\FormatHelpers;
use Illuminate\Support\Facades\Log;

/**
 * Service for Redis/KeyDB/Dragonfly database metrics and operations.
 */
class RedisMetricsService
{
    use FormatHelpers;

    /**
     * Collect Redis/KeyDB/Dragonfly metrics via SSH.
     */
    public function collectMetrics(mixed $server, mixed $database): array
    {
        $containerName = $database->uuid;
        $password = $database->redis_password ?? $database->keydb_password ?? $database->dragonfly_password ?? '';

        $metrics = [
            'totalKeys' => null,
            'memoryUsed' => 'N/A',
            'opsPerSec' => null,
            'hitRate' => null,
        ];

        try {
            $escapedContainerName = escapeshellarg($containerName);
            $authFlag = $password ? '-a '.escapeshellarg($password) : '';

            // Get Redis INFO
            $infoCommand = "docker exec {$escapedContainerName} redis-cli {$authFlag} INFO 2>/dev/null || echo ''";
            $info = instant_remote_process([$infoCommand], $server, false) ?? '';

            // Parse used memory
            if (preg_match('/used_memory_human:(\S+)/', $info, $matches)) {
                $metrics['memoryUsed'] = $matches[1];
            }

            // Parse total keys (db0)
            if (preg_match('/db0:keys=(\d+)/', $info, $matches)) {
                $metrics['totalKeys'] = (int) $matches[1];
            }

            // Parse ops per sec
            if (preg_match('/instantaneous_ops_per_sec:(\d+)/', $info, $matches)) {
                $metrics['opsPerSec'] = (int) $matches[1];
            }

            // Parse hit rate
            if (preg_match('/keyspace_hits:(\d+)/', $info, $hitsMatch) &&
                preg_match('/keyspace_misses:(\d+)/', $info, $missesMatch)) {
                $hits = (int) $hitsMatch[1];
                $misses = (int) $missesMatch[1];
                $total = $hits + $misses;
                if ($total > 0) {
                    $metrics['hitRate'] = round(($hits / $total) * 100, 1).'%';
                }
            }
        } catch (\Exception $e) {
            Log::debug('Failed to collect Redis metrics', [
                'database_uuid' => $database->uuid ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return $metrics;
    }

    /**
     * Get Redis keys with details.
     */
    public function getKeys(mixed $server, mixed $database, string $pattern = '*', int $limit = 100): array
    {
        $containerName = escapeshellarg($database->uuid);
        $password = $database->redis_password ?? $database->keydb_password ?? $database->dragonfly_password ?? '';
        $authFlag = $password ? '-a '.escapeshellarg($password) : '';

        // Validate pattern to prevent command injection — allow only safe Redis glob patterns
        if (! InputValidator::isValidRedisPattern($pattern)) {
            return [];
        }
        $escapedPattern = escapeshellarg($pattern);

        // Get keys matching pattern
        $command = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning KEYS {$escapedPattern} 2>/dev/null | head -n {$limit}";
        $result = trim(instant_remote_process([$command], $server, false) ?? '');

        $keys = [];
        if ($result) {
            $keyNames = array_filter(explode("\n", $result), fn ($k) => ! empty(trim($k)));

            foreach (array_slice($keyNames, 0, $limit) as $keyName) {
                $keyName = trim($keyName);
                if (empty($keyName)) {
                    continue;
                }

                $escapedKeyName = escapeshellarg($keyName);

                // Get key type
                $typeCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning TYPE {$escapedKeyName} 2>/dev/null";
                $keyType = trim(instant_remote_process([$typeCmd], $server, false) ?? 'unknown');

                // Get TTL
                $ttlCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning TTL {$escapedKeyName} 2>/dev/null";
                $ttl = (int) trim(instant_remote_process([$ttlCmd], $server, false) ?? '-1');
                $ttlDisplay = $ttl === -1 ? 'none' : ($ttl === -2 ? 'expired' : $this->formatSeconds($ttl));

                // Get memory usage
                $memCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning MEMORY USAGE {$escapedKeyName} 2>/dev/null";
                $memUsage = trim(instant_remote_process([$memCmd], $server, false) ?? '0');
                $size = is_numeric($memUsage) ? $this->formatBytes((int) $memUsage) : 'N/A';

                $keys[] = [
                    'name' => $keyName,
                    'type' => $keyType,
                    'ttl' => $ttlDisplay,
                    'size' => $size,
                ];
            }
        }

        return $keys;
    }

    /**
     * Delete Redis key.
     */
    public function deleteKey(mixed $server, mixed $database, string $keyName): array
    {
        $containerName = escapeshellarg($database->uuid);
        $password = $database->redis_password ?? $database->keydb_password ?? $database->dragonfly_password ?? '';
        $authFlag = $password ? '-a '.escapeshellarg($password) : '';

        // Escape key name for shell using escapeshellarg for safety
        $escapedKey = escapeshellarg($keyName);

        $command = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning DEL {$escapedKey} 2>&1";
        $result = trim(instant_remote_process([$command], $server, false) ?? '');

        if (is_numeric($result) && (int) $result > 0) {
            return ['success' => true, 'message' => "Key '{$keyName}' deleted"];
        }

        return ['success' => false, 'error' => "Key not found or could not be deleted: {$result}"];
    }

    /**
     * Get Redis key value.
     */
    public function getKeyValue(mixed $server, mixed $database, string $keyName): array
    {
        $containerName = escapeshellarg($database->uuid);
        $password = $database->redis_password ?? $database->keydb_password ?? $database->dragonfly_password ?? '';
        $authFlag = $password ? '-a '.escapeshellarg($password) : '';
        $escapedKey = escapeshellarg($keyName);

        // Get key type first
        $typeCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning TYPE {$escapedKey} 2>/dev/null";
        $keyType = trim(instant_remote_process([$typeCmd], $server, false) ?? 'none');

        if ($keyType === 'none') {
            return ['success' => false, 'error' => 'Key not found'];
        }

        // Get TTL
        $ttlCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning TTL {$escapedKey} 2>/dev/null";
        $ttl = (int) trim(instant_remote_process([$ttlCmd], $server, false) ?? '-1');

        // Get value based on type
        $value = null;
        $length = 0;

        switch ($keyType) {
            case 'string':
                $valueCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning GET {$escapedKey} 2>/dev/null";
                $value = instant_remote_process([$valueCmd], $server, false) ?? '';
                $length = strlen($value);
                break;

            case 'list':
                $lenCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning LLEN {$escapedKey} 2>/dev/null";
                $length = (int) trim(instant_remote_process([$lenCmd], $server, false) ?? '0');
                // Get first 100 items
                $limit = min($length, 100);
                $valueCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning LRANGE {$escapedKey} 0 {$limit} 2>/dev/null";
                $result = instant_remote_process([$valueCmd], $server, false) ?? '';
                $value = array_filter(explode("\n", trim($result)), fn ($v) => $v !== '');
                break;

            case 'set':
                $lenCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning SCARD {$escapedKey} 2>/dev/null";
                $length = (int) trim(instant_remote_process([$lenCmd], $server, false) ?? '0');
                // Get members (limited to 100)
                $valueCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning SSCAN {$escapedKey} 0 COUNT 100 2>/dev/null";
                $result = instant_remote_process([$valueCmd], $server, false) ?? '';
                $lines = array_filter(explode("\n", trim($result)), fn ($v) => $v !== '');
                // Skip first line (cursor) and take remaining as members
                $value = array_slice($lines, 1);
                break;

            case 'zset':
                $lenCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning ZCARD {$escapedKey} 2>/dev/null";
                $length = (int) trim(instant_remote_process([$lenCmd], $server, false) ?? '0');
                // Get members with scores (limited to 100)
                $valueCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning ZRANGE {$escapedKey} 0 99 WITHSCORES 2>/dev/null";
                $result = instant_remote_process([$valueCmd], $server, false) ?? '';
                $lines = array_filter(explode("\n", trim($result)), fn ($v) => $v !== '');
                // Parse member-score pairs
                $value = [];
                for ($i = 0; $i < count($lines) - 1; $i += 2) {
                    $value[] = ['member' => $lines[$i], 'score' => $lines[$i + 1] ?? '0'];
                }
                break;

            case 'hash':
                $lenCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning HLEN {$escapedKey} 2>/dev/null";
                $length = (int) trim(instant_remote_process([$lenCmd], $server, false) ?? '0');
                // Get all fields and values
                $valueCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning HGETALL {$escapedKey} 2>/dev/null";
                $result = instant_remote_process([$valueCmd], $server, false) ?? '';
                $lines = array_filter(explode("\n", trim($result)), fn ($v) => $v !== '');
                // Parse field-value pairs
                $value = [];
                for ($i = 0; $i < count($lines) - 1; $i += 2) {
                    $value[$lines[$i]] = $lines[$i + 1] ?? '';
                }
                break;

            case 'stream':
                $lenCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning XLEN {$escapedKey} 2>/dev/null";
                $length = (int) trim(instant_remote_process([$lenCmd], $server, false) ?? '0');
                $value = ['message' => 'Stream type viewing not fully supported'];
                break;

            default:
                $value = null;
        }

        return [
            'success' => true,
            'key' => $keyName,
            'type' => $keyType,
            'value' => $value,
            'length' => $length,
            'ttl' => $ttl,
        ];
    }

    /**
     * Set Redis key value.
     */
    public function setKeyValue(mixed $server, mixed $database, string $keyName, string $keyType, mixed $value, int $ttl = -1): array
    {
        $containerName = escapeshellarg($database->uuid);
        $password = $database->redis_password ?? $database->keydb_password ?? $database->dragonfly_password ?? '';
        $authFlag = $password ? '-a '.escapeshellarg($password) : '';
        $escapedKey = escapeshellarg($keyName);

        $command = '';

        switch ($keyType) {
            case 'string':
                $escapedValue = escapeshellarg($value);
                $command = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning SET {$escapedKey} {$escapedValue} 2>&1";
                break;

            case 'list':
                // Delete old list and create new one
                $deleteCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning DEL {$escapedKey} 2>/dev/null";
                instant_remote_process([$deleteCmd], $server, false);

                if (is_array($value) && count($value) > 0) {
                    $escapedValues = implode(' ', array_map('escapeshellarg', $value));
                    $command = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning RPUSH {$escapedKey} {$escapedValues} 2>&1";
                } else {
                    return ['success' => true, 'message' => 'Empty list created'];
                }
                break;

            case 'set':
                // Delete old set and create new one
                $deleteCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning DEL {$escapedKey} 2>/dev/null";
                instant_remote_process([$deleteCmd], $server, false);

                if (is_array($value) && count($value) > 0) {
                    $escapedValues = implode(' ', array_map('escapeshellarg', $value));
                    $command = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning SADD {$escapedKey} {$escapedValues} 2>&1";
                } else {
                    return ['success' => true, 'message' => 'Empty set created'];
                }
                break;

            case 'zset':
                // Delete old sorted set and create new one
                $deleteCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning DEL {$escapedKey} 2>/dev/null";
                instant_remote_process([$deleteCmd], $server, false);

                if (is_array($value) && count($value) > 0) {
                    // Value should be array of {member, score}
                    $args = [];
                    foreach ($value as $item) {
                        $args[] = escapeshellarg((string) ($item['score'] ?? 0));
                        $args[] = escapeshellarg($item['member'] ?? '');
                    }
                    $escapedValues = implode(' ', $args);
                    $command = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning ZADD {$escapedKey} {$escapedValues} 2>&1";
                } else {
                    return ['success' => true, 'message' => 'Empty sorted set created'];
                }
                break;

            case 'hash':
                // Delete old hash and create new one
                $deleteCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning DEL {$escapedKey} 2>/dev/null";
                instant_remote_process([$deleteCmd], $server, false);

                if (is_array($value) && count($value) > 0) {
                    $args = [];
                    foreach ($value as $field => $fieldValue) {
                        $args[] = escapeshellarg($field);
                        $args[] = escapeshellarg($fieldValue);
                    }
                    $escapedValues = implode(' ', $args);
                    $command = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning HSET {$escapedKey} {$escapedValues} 2>&1";
                } else {
                    return ['success' => true, 'message' => 'Empty hash created'];
                }
                break;

            default:
                return ['success' => false, 'error' => 'Unsupported key type'];
        }

        $result = trim(instant_remote_process([$command], $server, false) ?? '');

        // Check for errors
        if (stripos($result, 'ERR') !== false || stripos($result, 'error') !== false) {
            return ['success' => false, 'error' => $result];
        }

        // Set TTL if specified
        if ($ttl > 0) {
            $ttlCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning EXPIRE {$escapedKey} {$ttl} 2>/dev/null";
            instant_remote_process([$ttlCmd], $server, false);
        }

        return ['success' => true, 'message' => "Key '{$keyName}' updated successfully"];
    }

    /**
     * Get Redis extended memory info.
     */
    public function getMemoryInfo(mixed $server, mixed $database): array
    {
        $containerName = escapeshellarg($database->uuid);
        $password = $database->redis_password ?? $database->keydb_password ?? $database->dragonfly_password ?? '';
        $authFlag = $password ? '-a '.escapeshellarg($password) : '';

        // Get memory info
        $command = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning INFO memory 2>/dev/null";
        $result = instant_remote_process([$command], $server, false) ?? '';

        $memory = [
            'usedMemory' => 'N/A',
            'peakMemory' => 'N/A',
            'fragmentationRatio' => 'N/A',
            'maxMemory' => 'N/A',
            'evictionPolicy' => 'N/A',
        ];

        if (preg_match('/used_memory_human:(\S+)/', $result, $matches)) {
            $memory['usedMemory'] = $matches[1];
        }
        if (preg_match('/used_memory_peak_human:(\S+)/', $result, $matches)) {
            $memory['peakMemory'] = $matches[1];
        }
        if (preg_match('/mem_fragmentation_ratio:(\S+)/', $result, $matches)) {
            $memory['fragmentationRatio'] = $matches[1];
        }

        // Get max memory and eviction policy
        $configCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning CONFIG GET maxmemory maxmemory-policy 2>/dev/null";
        $configResult = instant_remote_process([$configCmd], $server, false) ?? '';

        if (preg_match('/maxmemory\n(\d+)/', $configResult, $matches)) {
            $maxMem = (int) $matches[1];
            $memory['maxMemory'] = $maxMem > 0 ? $this->formatBytes($maxMem) : 'No limit';
        }
        if (preg_match('/maxmemory-policy\n(\S+)/', $configResult, $matches)) {
            $memory['evictionPolicy'] = $matches[1];
        }

        return $memory;
    }

    /**
     * Get Redis persistence settings.
     */
    public function getPersistenceSettings(mixed $server, mixed $database): array
    {
        $containerName = escapeshellarg($database->uuid);
        $password = $database->redis_password ?? $database->keydb_password ?? $database->dragonfly_password ?? '';
        $authFlag = $password ? '-a '.escapeshellarg($password) : '';

        $persistence = [
            'rdbEnabled' => false,
            'rdbSaveRules' => [],
            'aofEnabled' => false,
            'aofFsync' => 'everysec',
            'rdbLastSaveTime' => null,
            'rdbLastBgsaveStatus' => 'N/A',
        ];

        // Get CONFIG for persistence settings
        $configCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning CONFIG GET save 2>/dev/null";
        $saveConfig = instant_remote_process([$configCmd], $server, false) ?? '';

        // Parse RDB save rules
        if (preg_match('/save\n(.+)$/m', $saveConfig, $matches)) {
            $saveValue = trim($matches[1]);
            if (! empty($saveValue) && $saveValue !== '""' && $saveValue !== "''") {
                $persistence['rdbEnabled'] = true;
                // Parse save rules (e.g., "900 1 300 10 60 10000")
                $rules = preg_split('/\s+/', $saveValue);
                $rdbRules = [];
                for ($i = 0; $i < count($rules) - 1; $i += 2) {
                    if (is_numeric($rules[$i]) && is_numeric($rules[$i + 1])) {
                        $rdbRules[] = [
                            'seconds' => (int) $rules[$i],
                            'changes' => (int) $rules[$i + 1],
                        ];
                    }
                }
                $persistence['rdbSaveRules'] = $rdbRules;
            }
        }

        // Get AOF settings
        $aofCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning CONFIG GET appendonly 2>/dev/null";
        $aofConfig = instant_remote_process([$aofCmd], $server, false) ?? '';
        if (preg_match('/appendonly\n(\w+)/m', $aofConfig, $matches)) {
            $persistence['aofEnabled'] = strtolower(trim($matches[1])) === 'yes';
        }

        // Get AOF fsync policy
        $fsyncCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning CONFIG GET appendfsync 2>/dev/null";
        $fsyncConfig = instant_remote_process([$fsyncCmd], $server, false) ?? '';
        if (preg_match('/appendfsync\n(\w+)/m', $fsyncConfig, $matches)) {
            $persistence['aofFsync'] = trim($matches[1]);
        }

        // Get last save time from INFO
        $infoCmd = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning INFO persistence 2>/dev/null";
        $info = instant_remote_process([$infoCmd], $server, false) ?? '';

        if (preg_match('/rdb_last_save_time:(\d+)/', $info, $matches)) {
            $timestamp = (int) $matches[1];
            if ($timestamp > 0) {
                $persistence['rdbLastSaveTime'] = date('Y-m-d H:i:s', $timestamp);
            }
        }

        if (preg_match('/rdb_last_bgsave_status:(\w+)/', $info, $matches)) {
            $persistence['rdbLastBgsaveStatus'] = $matches[1];
        }

        return $persistence;
    }

    /**
     * Execute Redis FLUSHDB or FLUSHALL command.
     */
    public function flush(mixed $server, mixed $database, string $flushType): array
    {
        $containerName = escapeshellarg($database->uuid);
        $password = $database->redis_password ?? $database->keydb_password ?? $database->dragonfly_password ?? '';
        $authFlag = $password ? '-a '.escapeshellarg($password) : '';

        $flushCommand = $flushType === 'all' ? 'FLUSHALL' : 'FLUSHDB';
        $command = "docker exec {$containerName} redis-cli {$authFlag} --no-auth-warning {$flushCommand} 2>&1";
        $result = trim(instant_remote_process([$command], $server, false) ?? '');

        if (stripos($result, 'OK') !== false) {
            return [
                'success' => true,
                'message' => $flushType === 'all' ? 'All databases flushed' : 'Current database flushed',
            ];
        }

        return ['success' => false, 'error' => $result ?: 'Flush command failed'];
    }
}
