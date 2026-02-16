<?php

namespace App\Jobs;

use App\Models\ScheduledDatabaseBackup;
use App\Models\ScheduledDatabaseBackupExecution;
use App\Models\Server;
use App\Models\ServiceDatabase;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use App\Notifications\Database\BackupRestoreTestFailed;
use App\Notifications\Database\BackupRestoreTestSuccess;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class BackupRestoreTestJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 1800; // 30 minutes

    private ?Server $server = null;

    private string $testContainerName;

    private Carbon $startTime;

    public function __construct(
        public ScheduledDatabaseBackup $backup,
        public ?ScheduledDatabaseBackupExecution $execution = null
    ) {
        $this->onQueue('high');
        $this->testContainerName = 'backup-test-'.Str::random(8);
    }

    public function handle(): void
    {
        $this->startTime = now();

        try {
            // If no specific execution, get latest successful one
            if (! $this->execution) {
                /** @var ScheduledDatabaseBackupExecution|null $latestExecution */
                $latestExecution = $this->backup->executions()
                    ->where('status', 'success')
                    ->latest()
                    ->first();
                $this->execution = $latestExecution;
            }

            if (! $this->execution) {
                Log::info('No successful backup found for restore test', [
                    'backup_id' => $this->backup->id,
                ]);

                return;
            }

            $this->execution->update([
                'restore_test_status' => 'pending',
                'restore_test_at' => now(),
            ]);

            $database = $this->backup->database;
            if (! $database) {
                $this->markFailed('Database not found');

                return;
            }

            $destination = $database->getAttribute('destination');
            $this->server = $destination->server ?? $database->getAttribute('server') ?? null;
            if (! $this->server) {
                $this->markFailed('Server not found');

                return;
            }

            // Ensure backup file exists
            $backupFile = $this->ensureBackupFileAvailable();
            if (! $backupFile) {
                return;
            }

            // Run restore test based on database type
            $success = match (true) {
                $database instanceof StandalonePostgresql => $this->testPostgresRestore($database, $backupFile),
                $database instanceof StandaloneMysql => $this->testMysqlRestore($database, $backupFile),
                $database instanceof StandaloneMariadb => $this->testMariadbRestore($database, $backupFile),
                $database instanceof StandaloneMongodb => $this->testMongoRestore($database, $backupFile),
                $database instanceof ServiceDatabase => $this->testServiceDatabaseRestore($database, $backupFile),
                default => $this->markFailed('Unsupported database type'),
            };

            if ($success) {
                $duration = (int) now()->diffInSeconds($this->startTime);
                $this->execution->update([
                    'restore_test_status' => 'success',
                    'restore_test_message' => 'Restore test completed successfully',
                    'restore_test_duration_seconds' => $duration,
                ]);

                $this->backup->update([
                    'last_restore_test_at' => now(),
                ]);

                // Notify success
                $this->notifySuccess($duration);
            }
        } catch (Throwable $e) {
            Log::error('Backup restore test failed', [
                'backup_id' => $this->backup->id,
                'error' => $e->getMessage(),
            ]);
            $this->markFailed($e->getMessage());
        } finally {
            // Always cleanup test container
            $this->cleanupTestContainer();
        }
    }

    private function ensureBackupFileAvailable(): ?string
    {
        $filename = $this->execution->filename;

        // Check if local file exists
        if (! $this->execution->local_storage_deleted) {
            $checkExists = instant_remote_process(
                ["test -f {$filename} && echo 'exists' || echo 'not_found'"],
                $this->server
            );

            if (trim($checkExists) === 'exists') {
                return $filename;
            }
        }

        // Try to download from S3
        if ($this->execution->s3_uploaded && ! $this->execution->s3_storage_deleted) {
            $downloaded = $this->downloadFromS3();
            if ($downloaded) {
                return $filename;
            }
        }

        $this->markFailed('Backup file not available locally or in S3');

        return null;
    }

    private function downloadFromS3(): bool
    {
        $s3 = $this->backup->s3;
        if (! $s3) {
            return false;
        }

        try {
            $database = $this->backup->database;
            $team = $database->getAttribute('team');
            $teamSlug = Str::slug($team->name);
            $dbSlug = Str::slug($database->getAttribute('name'));
            $filename = basename($this->execution->filename);
            $s3Path = trim($s3->path, '/')
                ."/databases/{$teamSlug}-{$team->id}/{$dbSlug}-{$database->getAttribute('uuid')}/{$filename}";

            // Download using mc (MinIO client)
            $commands = [
                'mkdir -p '.dirname($this->execution->filename),
                'docker run --rm -v '.dirname($this->execution->filename).':'.dirname($this->execution->filename)
                    ." --entrypoint sh minio/mc -c 'mc alias set backup "
                    .escapeshellarg($s3->endpoint).' '
                    .escapeshellarg($s3->key).' '
                    .escapeshellarg($s3->secret)
                    ." && mc cp backup/{$s3->bucket}/{$s3Path} ".$this->execution->filename."'",
            ];

            instant_remote_process($commands, $this->server);

            // Verify download
            $checkExists = instant_remote_process(
                ["test -f {$this->execution->filename} && echo 'exists' || echo 'not_found'"],
                $this->server
            );

            return trim($checkExists) === 'exists';
        } catch (Throwable $e) {
            Log::error('Failed to download backup from S3', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function testPostgresRestore(StandalonePostgresql $database, string $backupFile): bool
    {
        $password = $database->postgres_password ?? 'postgres';
        $dbName = 'restore_test_'.Str::random(6);

        $commands = [
            // Start temporary PostgreSQL container
            "docker run -d --name {$this->testContainerName} "
                .'-e POSTGRES_PASSWORD='.escapeshellarg($password).' '
                .'-e POSTGRES_DB='.$dbName.' '
                .'postgres:'.($database->postgres_version ?? '15').'-alpine',

            // Wait for PostgreSQL to be ready
            'sleep 10',

            // Restore backup into test container
            $this->getRestoreCommand($backupFile, $password, $dbName),

            // Verify by counting tables
            "docker exec {$this->testContainerName} psql -U postgres -d {$dbName} "
                ."-c \"SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public';\"",
        ];

        try {
            foreach ($commands as $command) {
                $output = instant_remote_process([$command], $this->server);
                Log::debug('Restore test command output', ['command' => $command, 'output' => $output]);
            }

            return true;
        } catch (Throwable $e) {
            $this->markFailed('PostgreSQL restore failed: '.$e->getMessage());

            return false;
        }
    }

    private function getRestoreCommand(string $backupFile, string $password, string $dbName): string
    {
        if (str_ends_with($backupFile, '.gz')) {
            return "gunzip -c {$backupFile} | docker exec -i {$this->testContainerName} "
                ."psql -U postgres -d {$dbName}";
        } elseif (str_ends_with($backupFile, '.dmp')) {
            return "docker exec -i {$this->testContainerName} pg_restore -U postgres -d {$dbName} "
                ."--no-owner --no-privileges < {$backupFile}";
        }

        return "cat {$backupFile} | docker exec -i {$this->testContainerName} psql -U postgres -d {$dbName}";
    }

    private function testMysqlRestore(StandaloneMysql $database, string $backupFile): bool
    {
        $password = $database->mysql_root_password ?? 'root';
        $dbName = 'restore_test';

        $commands = [
            // Start temporary MySQL container
            "docker run -d --name {$this->testContainerName} "
                .'-e MYSQL_ROOT_PASSWORD='.escapeshellarg($password).' '
                ."-e MYSQL_DATABASE={$dbName} "
                .'mysql:'.($database->mysql_version ?? '8.0'),

            // Wait for MySQL to be ready
            'sleep 20',

            // Restore backup
            "gunzip -c {$backupFile} 2>/dev/null || cat {$backupFile} | "
                ."docker exec -i {$this->testContainerName} mysql -u root -p".escapeshellarg($password)." {$dbName}",

            // Verify
            "docker exec {$this->testContainerName} mysql -u root -p".escapeshellarg($password)
                ." -e 'SHOW TABLES;' {$dbName}",
        ];

        try {
            foreach ($commands as $command) {
                instant_remote_process([$command], $this->server);
            }

            return true;
        } catch (Throwable $e) {
            $this->markFailed('MySQL restore failed: '.$e->getMessage());

            return false;
        }
    }

    private function testMariadbRestore(StandaloneMariadb $database, string $backupFile): bool
    {
        $password = $database->mariadb_root_password ?? 'root';
        $dbName = 'restore_test';

        $commands = [
            // Start temporary MariaDB container
            "docker run -d --name {$this->testContainerName} "
                .'-e MARIADB_ROOT_PASSWORD='.escapeshellarg($password).' '
                ."-e MARIADB_DATABASE={$dbName} "
                .'mariadb:'.($database->mariadb_version ?? '10'),

            // Wait for MariaDB to be ready
            'sleep 15',

            // Restore backup
            "gunzip -c {$backupFile} 2>/dev/null || cat {$backupFile} | "
                ."docker exec -i {$this->testContainerName} mariadb -u root -p".escapeshellarg($password)." {$dbName}",

            // Verify
            "docker exec {$this->testContainerName} mariadb -u root -p".escapeshellarg($password)
                ." -e 'SHOW TABLES;' {$dbName}",
        ];

        try {
            foreach ($commands as $command) {
                instant_remote_process([$command], $this->server);
            }

            return true;
        } catch (Throwable $e) {
            $this->markFailed('MariaDB restore failed: '.$e->getMessage());

            return false;
        }
    }

    private function testMongoRestore(StandaloneMongodb $database, string $backupFile): bool
    {
        $commands = [
            // Start temporary MongoDB container
            "docker run -d --name {$this->testContainerName} mongo:".($database->mongodb_version ?? '6.0'),

            // Wait for MongoDB to be ready
            'sleep 10',

            // Extract and restore
            "mkdir -p /tmp/{$this->testContainerName}",
            "tar -xzf {$backupFile} -C /tmp/{$this->testContainerName}",
            "docker cp /tmp/{$this->testContainerName} {$this->testContainerName}:/dump",
            "docker exec {$this->testContainerName} mongorestore /dump",

            // Verify
            "docker exec {$this->testContainerName} mongosh --eval 'db.adminCommand({listDatabases: 1})'",

            // Cleanup temp
            "rm -rf /tmp/{$this->testContainerName}",
        ];

        try {
            foreach ($commands as $command) {
                instant_remote_process([$command], $this->server);
            }

            return true;
        } catch (Throwable $e) {
            $this->markFailed('MongoDB restore failed: '.$e->getMessage());

            return false;
        }
    }

    private function testServiceDatabaseRestore(ServiceDatabase $database, string $backupFile): bool
    {
        // For service databases, we need to detect the type from the image
        $image = $database->image ?? '';

        if (str_contains($image, 'postgres')) {
            return $this->testPostgresRestoreGeneric($backupFile);
        } elseif (str_contains($image, 'mysql')) {
            return $this->testMysqlRestoreGeneric($backupFile);
        } elseif (str_contains($image, 'mariadb')) {
            return $this->testMariadbRestoreGeneric($backupFile);
        } elseif (str_contains($image, 'mongo')) {
            return $this->testMongoRestoreGeneric($backupFile);
        }

        $this->markFailed('Cannot determine database type for service database');

        return false;
    }

    private function testPostgresRestoreGeneric(string $backupFile): bool
    {
        $commands = [
            "docker run -d --name {$this->testContainerName} "
                .'-e POSTGRES_PASSWORD=testpass '
                .'-e POSTGRES_DB=restore_test '
                .'postgres:15-alpine',
            'sleep 10',
            "gunzip -c {$backupFile} 2>/dev/null || cat {$backupFile} | "
                ."docker exec -i {$this->testContainerName} psql -U postgres -d restore_test",
        ];

        try {
            foreach ($commands as $command) {
                instant_remote_process([$command], $this->server);
            }

            return true;
        } catch (Throwable $e) {
            $this->markFailed('PostgreSQL restore failed: '.$e->getMessage());

            return false;
        }
    }

    private function testMysqlRestoreGeneric(string $backupFile): bool
    {
        $commands = [
            "docker run -d --name {$this->testContainerName} "
                .'-e MYSQL_ROOT_PASSWORD=testpass '
                .'-e MYSQL_DATABASE=restore_test '
                .'mysql:8.0',
            'sleep 20',
            "gunzip -c {$backupFile} 2>/dev/null || cat {$backupFile} | "
                ."docker exec -i {$this->testContainerName} mysql -u root -ptestpass restore_test",
        ];

        try {
            foreach ($commands as $command) {
                instant_remote_process([$command], $this->server);
            }

            return true;
        } catch (Throwable $e) {
            $this->markFailed('MySQL restore failed: '.$e->getMessage());

            return false;
        }
    }

    private function testMariadbRestoreGeneric(string $backupFile): bool
    {
        $commands = [
            "docker run -d --name {$this->testContainerName} "
                .'-e MARIADB_ROOT_PASSWORD=testpass '
                .'-e MARIADB_DATABASE=restore_test '
                .'mariadb:10',
            'sleep 15',
            "gunzip -c {$backupFile} 2>/dev/null || cat {$backupFile} | "
                ."docker exec -i {$this->testContainerName} mariadb -u root -ptestpass restore_test",
        ];

        try {
            foreach ($commands as $command) {
                instant_remote_process([$command], $this->server);
            }

            return true;
        } catch (Throwable $e) {
            $this->markFailed('MariaDB restore failed: '.$e->getMessage());

            return false;
        }
    }

    private function testMongoRestoreGeneric(string $backupFile): bool
    {
        $commands = [
            "docker run -d --name {$this->testContainerName} mongo:6.0",
            'sleep 10',
            "mkdir -p /tmp/{$this->testContainerName}",
            "tar -xzf {$backupFile} -C /tmp/{$this->testContainerName}",
            "docker cp /tmp/{$this->testContainerName} {$this->testContainerName}:/dump",
            "docker exec {$this->testContainerName} mongorestore /dump",
            "rm -rf /tmp/{$this->testContainerName}",
        ];

        try {
            foreach ($commands as $command) {
                instant_remote_process([$command], $this->server);
            }

            return true;
        } catch (Throwable $e) {
            $this->markFailed('MongoDB restore failed: '.$e->getMessage());

            return false;
        }
    }

    private function cleanupTestContainer(): void
    {
        if (! $this->server) {
            return;
        }

        try {
            instant_remote_process([
                "docker rm -f {$this->testContainerName} 2>/dev/null || true",
            ], $this->server);
        } catch (Throwable $e) {
            Log::warning('Failed to cleanup test container', [
                'container' => $this->testContainerName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function markFailed(string $message): bool
    {
        $duration = (int) now()->diffInSeconds($this->startTime);
        $this->execution->update([
            'restore_test_status' => 'failed',
            'restore_test_message' => $message,
            'restore_test_duration_seconds' => $duration,
        ]);

        // Notify failure
        $this->notifyFailure($message);

        return false;
    }

    private function notifySuccess(int $duration): void
    {
        try {
            $team = $this->backup->team;
            if ($team) {
                $team->notify(new BackupRestoreTestSuccess(
                    $this->backup->database,
                    $duration
                ));
            }
        } catch (Throwable $e) {
            Log::warning('Failed to send restore test success notification', ['error' => $e->getMessage()]);
        }
    }

    private function notifyFailure(string $message): void
    {
        try {
            $team = $this->backup->team;
            if ($team) {
                $team->notify(new BackupRestoreTestFailed(
                    $this->backup->database,
                    $message
                ));
            }
        } catch (Throwable $e) {
            Log::warning('Failed to send restore test failure notification', ['error' => $e->getMessage()]);
        }
    }
}
