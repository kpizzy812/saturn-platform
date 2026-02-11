<?php

namespace App\Services\Database;

use InvalidArgumentException;

/**
 * Parses and validates database connection strings.
 *
 * Supported schemes: postgresql://, postgres://, mysql://, mariadb://,
 * mongodb://, mongodb+srv://, redis://, rediss://
 */
class ConnectionStringParser
{
    /**
     * Map of connection string schemes to normalized database types.
     */
    private const SCHEME_TO_TYPE = [
        'postgresql' => 'postgresql',
        'postgres' => 'postgresql',
        'mysql' => 'mysql',
        'mariadb' => 'mariadb',
        'mongodb' => 'mongodb',
        'mongodb+srv' => 'mongodb',
        'redis' => 'redis',
        'rediss' => 'redis',
    ];

    /**
     * Default ports for each database type.
     */
    private const DEFAULT_PORTS = [
        'postgresql' => 5432,
        'mysql' => 3306,
        'mariadb' => 3306,
        'mongodb' => 27017,
        'redis' => 6379,
    ];

    /**
     * Compatible target types for each source type.
     */
    private const COMPATIBILITY_MAP = [
        'postgresql' => ['postgresql'],
        'mysql' => ['mysql', 'mariadb'],
        'mariadb' => ['mysql', 'mariadb'],
        'mongodb' => ['mongodb'],
        'redis' => ['redis', 'keydb', 'dragonfly'],
    ];

    /**
     * Parse a connection string into its components.
     *
     * @return array{type: string, host: string, port: int, username: string, password: string, database: string, options: array<string, string>}
     *
     * @throws InvalidArgumentException
     */
    public function parse(string $connectionString): array
    {
        $connectionString = trim($connectionString);

        if ($connectionString === '') {
            throw new InvalidArgumentException('Connection string cannot be empty.');
        }

        // Handle mongodb+srv:// by temporarily replacing it for parse_url
        $normalizedForParsing = $connectionString;
        $isSrv = false;
        if (str_starts_with($connectionString, 'mongodb+srv://')) {
            $normalizedForParsing = 'mongodb://'.substr($connectionString, strlen('mongodb+srv://'));
            $isSrv = true;
        }

        $parts = parse_url($normalizedForParsing);

        if ($parts === false || ! isset($parts['scheme'])) {
            throw new InvalidArgumentException('Invalid connection string format. Expected scheme://user:password@host:port/database');
        }

        $scheme = $isSrv ? 'mongodb+srv' : strtolower($parts['scheme']);

        if (! isset(self::SCHEME_TO_TYPE[$scheme])) {
            throw new InvalidArgumentException(
                "Unsupported scheme: {$scheme}://. Supported: ".implode(', ', array_map(fn ($s) => "{$s}://", array_keys(self::SCHEME_TO_TYPE)))
            );
        }

        $type = self::SCHEME_TO_TYPE[$scheme];
        $host = $parts['host'] ?? '';
        $port = $parts['port'] ?? self::DEFAULT_PORTS[$type];
        $username = isset($parts['user']) ? urldecode($parts['user']) : '';
        $password = isset($parts['pass']) ? urldecode($parts['pass']) : '';
        $database = isset($parts['path']) ? ltrim($parts['path'], '/') : '';

        // Parse query string options
        $options = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $options);
        }

        if ($host === '') {
            throw new InvalidArgumentException('Connection string must include a host.');
        }

        return [
            'type' => $type,
            'host' => $host,
            'port' => (int) $port,
            'username' => $username,
            'password' => $password,
            'database' => $database,
            'options' => $options,
        ];
    }

    /**
     * Check if the parsed source type is compatible with the target database type.
     */
    public function validateCompatibility(string $sourceType, string $targetDbType): bool
    {
        $compatibleTargets = self::COMPATIBILITY_MAP[$sourceType] ?? [];

        return in_array($targetDbType, $compatibleTargets, true);
    }

    /**
     * Build a dump command for the given parsed connection.
     *
     * All user-provided values are escaped with escapeshellarg().
     *
     * @param  array{type: string, host: string, port: int, username: string, password: string, database: string, options: array<string, string>}  $parsed
     * @return string The dump command to execute
     *
     * @throws InvalidArgumentException
     */
    public function buildDumpCommand(array $parsed, string $outputPath): string
    {
        $escapedOutput = escapeshellarg($outputPath);

        return match ($parsed['type']) {
            'postgresql' => $this->buildPgDumpCommand($parsed, $escapedOutput),
            'mysql', 'mariadb' => $this->buildMysqldumpCommand($parsed, $escapedOutput),
            'mongodb' => $this->buildMongodumpCommand($parsed, $escapedOutput),
            'redis' => throw new InvalidArgumentException('Redis does not support remote dump via connection string. Use RDB file upload instead.'),
            default => throw new InvalidArgumentException("Unsupported database type for dump: {$parsed['type']}"),
        };
    }

    /**
     * Get the Docker image to use for dumping a given database type.
     */
    public function getDumpDockerImage(string $type): string
    {
        return match ($type) {
            'postgresql' => 'postgres:16-alpine',
            'mysql' => 'mysql:8.0',
            'mariadb' => 'mariadb:11',
            'mongodb' => 'mongo:7',
            default => throw new InvalidArgumentException("No dump image for type: {$type}"),
        };
    }

    /**
     * Get the dump file extension for a given database type.
     */
    public function getDumpExtension(string $type): string
    {
        return match ($type) {
            'postgresql' => 'sql',
            'mysql', 'mariadb' => 'sql',
            'mongodb' => 'archive',
            default => 'dump',
        };
    }

    /**
     * Reconstruct a connection string from parsed components (without password for logging).
     */
    public function toSafeString(array $parsed): string
    {
        $scheme = $parsed['type'] === 'postgresql' ? 'postgresql' : $parsed['type'];
        $userPart = $parsed['username'] ? "{$parsed['username']}:***@" : '';

        return "{$scheme}://{$userPart}{$parsed['host']}:{$parsed['port']}/{$parsed['database']}";
    }

    private function buildPgDumpCommand(array $parsed, string $escapedOutput): string
    {
        $host = escapeshellarg($parsed['host']);
        $port = escapeshellarg((string) $parsed['port']);
        $username = escapeshellarg($parsed['username']);
        $database = escapeshellarg($parsed['database'] ?: 'postgres');

        // Use PGPASSWORD env var instead of passing password in command
        $password = escapeshellarg($parsed['password']);

        $sslMode = '';
        if (! empty($parsed['options']['sslmode'])) {
            $sslMode = ' --no-password';
        }

        return "PGPASSWORD={$password} pg_dump -h {$host} -p {$port} -U {$username}{$sslMode} -Fc {$database} > {$escapedOutput}";
    }

    private function buildMysqldumpCommand(array $parsed, string $escapedOutput): string
    {
        $host = escapeshellarg($parsed['host']);
        $port = escapeshellarg((string) $parsed['port']);
        $username = escapeshellarg($parsed['username']);
        $password = escapeshellarg($parsed['password']);
        $database = escapeshellarg($parsed['database'] ?: 'mysql');

        $ssl = '';
        if (! empty($parsed['options']['ssl']) || ! empty($parsed['options']['useSSL'])) {
            $ssl = ' --ssl';
        }

        return "mysqldump -h {$host} -P {$port} -u {$username} -p{$password}{$ssl} --single-transaction --routines --triggers {$database} > {$escapedOutput}";
    }

    private function buildMongodumpCommand(array $parsed, string $escapedOutput): string
    {
        $host = escapeshellarg($parsed['host']);
        $port = escapeshellarg((string) $parsed['port']);
        $database = $parsed['database'] ?: 'admin';

        $auth = '';
        if ($parsed['username'] !== '') {
            $username = escapeshellarg($parsed['username']);
            $password = escapeshellarg($parsed['password']);
            $auth = " --username={$username} --password={$password} --authenticationDatabase=admin";
        }

        $escapedDb = escapeshellarg($database);

        $ssl = '';
        if (! empty($parsed['options']['ssl']) || ! empty($parsed['options']['tls'])) {
            $ssl = ' --ssl';
        }

        return "mongodump --host={$host} --port={$port}{$auth}{$ssl} --db={$escapedDb} --archive={$escapedOutput}";
    }
}
