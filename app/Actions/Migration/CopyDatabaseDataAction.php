<?php

namespace App\Actions\Migration;

use App\Models\Environment;
use App\Models\EnvironmentMigration;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Copy database data from source to target using dump/restore.
 * SECURITY: Blocked for production targets. Only for dev/uat.
 *
 * Uses Docker exec to run dump on source server and restore on target.
 * Supports PostgreSQL, MySQL, MariaDB, MongoDB.
 */
class CopyDatabaseDataAction
{
    use AsAction;

    /**
     * @return array{success: bool, message?: string, error?: string}
     */
    public function handle(
        Model $source,
        Model $target,
        Environment $targetEnvironment,
        ?EnvironmentMigration $migration = null
    ): array {
        // SECURITY: Never copy data to production
        if ($targetEnvironment->isProduction()) {
            return [
                'success' => false,
                'error' => 'Data copy to production environments is forbidden.',
            ];
        }

        if (! $this->isSupportedDatabase($source)) {
            return [
                'success' => false,
                'error' => 'Unsupported database type for data copy: '.class_basename($source),
            ];
        }

        $sourceServer = $this->getServer($source);
        $targetServer = $this->getServer($target);

        if (! $sourceServer || ! $targetServer) {
            return [
                'success' => false,
                'error' => 'Could not determine source or target server.',
            ];
        }

        try {
            $migration?->updateProgress(86, 'Dumping source database...');

            $dumpFile = $this->dumpDatabase($source, $sourceServer);

            if (! $dumpFile) {
                return [
                    'success' => false,
                    'error' => 'Database dump failed.',
                ];
            }

            // If same server, skip transfer
            if ($sourceServer->id !== $targetServer->id) {
                $migration?->updateProgress(88, 'Transferring dump to target server...');
                $this->transferDump($dumpFile, $sourceServer, $targetServer);
            }

            $migration?->updateProgress(90, 'Restoring database on target...');
            $this->restoreDatabase($target, $targetServer, $dumpFile);

            // Cleanup dump file
            $this->cleanupDump($dumpFile, $sourceServer);
            if ($sourceServer->id !== $targetServer->id) {
                $this->cleanupDump($dumpFile, $targetServer);
            }

            return [
                'success' => true,
                'message' => 'Database data copied successfully.',
            ];
        } catch (\Throwable $e) {
            Log::error('Database data copy failed', [
                'source' => class_basename($source).':'.$source->getKey(),
                'target' => class_basename($target).':'.$target->getKey(),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Data copy failed: '.$e->getMessage(),
            ];
        }
    }

    protected function isSupportedDatabase(Model $resource): bool
    {
        return $resource instanceof StandalonePostgresql
            || $resource instanceof StandaloneMysql
            || $resource instanceof StandaloneMariadb
            || $resource instanceof StandaloneMongodb;
    }

    protected function getServer(Model $resource): ?\App\Models\Server
    {
        $dest = $resource->getAttribute('destination');
        if ($dest instanceof Model) {
            $server = $dest->getAttribute('server');

            return $server instanceof \App\Models\Server ? $server : null;
        }

        return null;
    }

    /**
     * Dump database to a temporary file on the source server.
     */
    protected function dumpDatabase(Model $source, \App\Models\Server $server): ?string
    {
        $containerName = escapeshellarg((string) $source->getAttribute('uuid'));
        $timestamp = now()->format('Ymd_His');
        $dumpFile = "/tmp/saturn_migration_dump_{$timestamp}";
        $escapedDumpFile = escapeshellarg($dumpFile);

        if ($source instanceof StandalonePostgresql) {
            $database = escapeshellarg($source->postgres_db ?? 'postgres');
            $user = escapeshellarg($source->postgres_user ?? 'postgres');
            $cmd = "docker exec {$containerName} pg_dump --format=custom --no-acl --no-owner --username {$user} {$database} > {$escapedDumpFile}";
        } elseif ($source instanceof StandaloneMysql) {
            $database = escapeshellarg($source->mysql_database ?? 'mysql');
            $password = $source->mysql_root_password ?? '';
            $cmd = "docker exec {$containerName} mysqldump -u root -p".escapeshellarg($password)." {$database} --single-transaction --quick > {$escapedDumpFile}";
        } elseif ($source instanceof StandaloneMariadb) {
            $database = escapeshellarg($source->mariadb_database ?? 'mariadb');
            $password = $source->mariadb_root_password ?? '';
            $cmd = "docker exec {$containerName} mariadb-dump -u root -p".escapeshellarg($password)." {$database} --single-transaction --quick > {$escapedDumpFile}";
        } elseif ($source instanceof StandaloneMongodb) {
            $url = $source->internal_db_url ?? 'mongodb://localhost:27017';
            $cmd = "docker exec {$containerName} mongodump --authenticationDatabase=admin --uri=".escapeshellarg($url)." --gzip --archive > {$escapedDumpFile}";
        } else {
            return null;
        }

        instant_remote_process([$cmd], $server);

        // Verify dump was created
        $check = instant_remote_process(
            ["test -f {$escapedDumpFile} && du -b {$escapedDumpFile} | cut -f1 || echo '0'"],
            $server,
            false
        );

        $size = (int) trim($check ?? '0');
        if ($size === 0) {
            return null;
        }

        return $dumpFile;
    }

    /**
     * Transfer dump file between servers using SSH pipe.
     */
    protected function transferDump(string $dumpFile, \App\Models\Server $source, \App\Models\Server $target): void
    {
        $escapedDumpFile = escapeshellarg($dumpFile);

        // Use scp-like approach via the target server pulling from source
        // This avoids needing direct server-to-server SSH keys
        $content = instant_remote_process(
            ["cat {$escapedDumpFile} | base64"],
            $source
        );

        if ($content) {
            instant_remote_process(
                ['echo '.escapeshellarg($content)." | base64 -d > {$escapedDumpFile}"],
                $target
            );
        }
    }

    /**
     * Restore database from dump file on the target server.
     */
    protected function restoreDatabase(Model $target, \App\Models\Server $server, string $dumpFile): void
    {
        $containerName = escapeshellarg((string) $target->getAttribute('uuid'));
        $escapedDumpFile = escapeshellarg($dumpFile);

        if ($target instanceof StandalonePostgresql) {
            $database = escapeshellarg($target->postgres_db ?? 'postgres');
            $user = escapeshellarg($target->postgres_user ?? 'postgres');
            $password = $target->postgres_password ?? '';
            $cmd = 'docker exec -e PGPASSWORD='.escapeshellarg($password)." {$containerName} pg_restore --username {$user} --dbname {$database} --clean --if-exists --no-owner --no-acl < {$escapedDumpFile} 2>/dev/null || true";
        } elseif ($target instanceof StandaloneMysql) {
            $database = escapeshellarg($target->mysql_database ?? 'mysql');
            $password = $target->mysql_root_password ?? '';
            $cmd = "docker exec -i {$containerName} mysql -u root -p".escapeshellarg($password)." {$database} < {$escapedDumpFile}";
        } elseif ($target instanceof StandaloneMariadb) {
            $database = escapeshellarg($target->mariadb_database ?? 'mariadb');
            $password = $target->mariadb_root_password ?? '';
            $cmd = "docker exec -i {$containerName} mariadb -u root -p".escapeshellarg($password)." {$database} < {$escapedDumpFile}";
        } elseif ($target instanceof StandaloneMongodb) {
            $url = $target->internal_db_url ?? 'mongodb://localhost:27017';
            $cmd = "docker exec {$containerName} mongorestore --authenticationDatabase=admin --uri=".escapeshellarg($url)." --gzip --archive={$escapedDumpFile} --drop 2>/dev/null || true";
        } else {
            return;
        }

        instant_remote_process([$cmd], $server);
    }

    /**
     * Remove temporary dump file.
     */
    protected function cleanupDump(string $dumpFile, \App\Models\Server $server): void
    {
        try {
            $escapedDumpFile = escapeshellarg($dumpFile);
            instant_remote_process(["rm -f {$escapedDumpFile}"], $server, false);
        } catch (\Throwable $e) {
            Log::debug('Failed to clean up dump file after database migration', [
                'dump_file' => $dumpFile,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
