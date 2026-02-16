<?php

namespace App\Jobs;

use App\Models\S3Storage;
use App\Models\ScheduledDatabaseBackup;
use App\Models\ScheduledDatabaseBackupExecution;
use App\Models\Server;
use App\Models\ServiceDatabase;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class DatabaseRestoreJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $maxExceptions = 1;

    public $timeout = 3600;

    public ?Team $team = null;

    public Server $server;

    public StandalonePostgresql|StandaloneMongodb|StandaloneMysql|StandaloneMariadb|ServiceDatabase $database;

    public ?string $container_name = null;

    public string $restore_status = 'pending';

    public ?string $restore_output = null;

    public ?string $error_output = null;

    public ?string $postgres_password = null;

    public ?string $mongo_root_username = null;

    public ?string $mongo_root_password = null;

    public ?S3Storage $s3 = null;

    public function __construct(
        public ScheduledDatabaseBackup $backup,
        public ScheduledDatabaseBackupExecution $execution
    ) {
        $this->onQueue('high');
        $this->timeout = max(60, $backup->timeout ?? 3600);
    }

    public function handle(): void
    {
        try {
            $this->team = Team::find($this->backup->team_id);
            if (! $this->team) {
                throw new \Exception('Team not found');
            }

            $this->database = data_get($this->backup, 'database');
            if ($this->database instanceof ServiceDatabase) {
                $this->server = $this->database->service->server;
                $this->s3 = $this->backup->s3;
            } else {
                $this->server = $this->database->destination->server;
                $this->s3 = $this->backup->s3;
            }

            // Update execution status
            $this->execution->update([
                'restore_status' => 'in_progress',
                'restore_started_at' => Carbon::now(),
            ]);

            $databaseType = $this->getDatabaseType();
            $this->container_name = $this->getContainerName();

            // Get backup file location
            $backupLocation = $this->execution->filename;

            // Download from S3 if needed
            if ($this->execution->s3_uploaded && $this->execution->local_storage_deleted) {
                $backupLocation = $this->downloadFromS3();
            }

            // Verify backup file exists
            $fileExists = instant_remote_process(
                ["test -f {$backupLocation} && echo 'exists' || echo 'not found'"],
                $this->server,
                false,
                false,
                null,
                disableMultiplexing: true
            );

            if (trim($fileExists) !== 'exists') {
                throw new \Exception("Backup file not found at {$backupLocation}");
            }

            // Perform restore based on database type
            if (str($databaseType)->contains('postgres')) {
                $this->restorePostgresql($backupLocation);
            } elseif (str($databaseType)->contains('mysql')) {
                $this->restoreMysql($backupLocation);
            } elseif (str($databaseType)->contains('mariadb')) {
                $this->restoreMariadb($backupLocation);
            } elseif (str($databaseType)->contains('mongo')) {
                $this->restoreMongodb($backupLocation);
            } else {
                throw new \Exception('Unsupported database type for restore: '.$databaseType);
            }

            // Update execution status
            $this->execution->update([
                'restore_status' => 'success',
                'restore_finished_at' => Carbon::now(),
                'restore_message' => $this->restore_output,
            ]);

            Log::info('Database restore completed successfully', [
                'database' => $this->database->name,
                'execution_uuid' => $this->execution->uuid,
            ]);

        } catch (Throwable $e) {
            $this->execution->update([
                'restore_status' => 'failed',
                'restore_finished_at' => Carbon::now(),
                'restore_message' => $this->error_output ?? $e->getMessage(),
            ]);

            Log::error('Database restore failed', [
                'database' => $this->database->name ?? 'unknown',
                'execution_uuid' => $this->execution->uuid,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function getDatabaseType(): string
    {
        if ($this->database instanceof ServiceDatabase) {
            return $this->database->databaseType();
        }

        return $this->database->type();
    }

    private function getContainerName(): string
    {
        if ($this->database instanceof ServiceDatabase) {
            $serviceUuid = $this->database->service->uuid;

            return "{$this->database->name}-{$serviceUuid}";
        }

        return $this->database->uuid;
    }

    private function downloadFromS3(): string
    {
        if (is_null($this->s3)) {
            throw new \Exception('S3 storage not configured');
        }

        $key = $this->s3->key;
        $secret = $this->s3->secret;
        $bucket = $this->s3->bucket;
        $endpoint = $this->s3->endpoint;

        $this->s3->testConnection(shouldSave: true);

        if ($this->database instanceof ServiceDatabase) {
            $network = $this->database->service->destination->network;
        } else {
            $network = $this->database->destination->network;
        }

        $fullImageName = $this->getFullImageName();
        $tempContainerName = "restore-download-{$this->execution->uuid}";
        $localPath = $this->execution->filename;
        $escapedContainer = escapeshellarg($tempContainerName);
        $escapedNetwork = escapeshellarg($network);

        // Ensure directory exists
        $commands[] = 'mkdir -p '.escapeshellarg(dirname($localPath));

        // Remove existing container if any
        $containerExists = instant_remote_process(
            ["docker ps -a -q -f name={$escapedContainer}"],
            $this->server,
            false,
            false,
            null,
            disableMultiplexing: true
        );

        if (filled($containerExists)) {
            instant_remote_process(
                ["docker rm -f {$escapedContainer}"],
                $this->server,
                false,
                false,
                null,
                disableMultiplexing: true
            );
        }

        // Escape S3 credentials
        $escapedEndpoint = escapeshellarg($endpoint);
        $escapedKey = escapeshellarg($key);
        $escapedSecret = escapeshellarg($secret);
        $escapedBucket = escapeshellarg("temporary/{$bucket}{$localPath}");
        $escapedLocalPath = escapeshellarg($localPath);

        $commands[] = "docker run -d --network {$escapedNetwork} --name {$escapedContainer} -v ".escapeshellarg(dirname($localPath).':'.dirname($localPath))." {$fullImageName}";
        $commands[] = "docker exec {$escapedContainer} mc alias set temporary {$escapedEndpoint} {$escapedKey} {$escapedSecret}";
        $commands[] = "docker exec {$escapedContainer} mc cp {$escapedBucket} {$escapedLocalPath}";

        try {
            instant_remote_process($commands, $this->server, true, false, null, disableMultiplexing: true);

            return $localPath;
        } finally {
            instant_remote_process(
                ["docker rm -f {$escapedContainer}"],
                $this->server,
                true,
                false,
                null,
                disableMultiplexing: true
            );
        }
    }

    private function restorePostgresql(string $backupLocation): void
    {
        try {
            $this->getPostgresPassword();

            $databaseName = $this->execution->database_name;
            $commands = [];
            $escapedContainerName = escapeshellarg($this->container_name);

            // Check if it's a dump_all backup (gzipped)
            if (str($backupLocation)->endsWith('.gz')) {
                // Full dump restore
                $restoreCommand = 'docker exec';
                if ($this->postgres_password) {
                    $restoreCommand .= " -e PGPASSWORD=\"{$this->postgres_password}\"";
                }
                $restoreCommand .= " {$escapedContainerName} psql --username {$this->database->postgres_user} -d postgres";
                $commands[] = "gunzip -c {$backupLocation} | {$restoreCommand}";
            } else {
                // Custom format restore
                $restoreCommand = 'docker exec';
                if ($this->postgres_password) {
                    $restoreCommand .= " -e PGPASSWORD=\"{$this->postgres_password}\"";
                }
                // Validate database name
                validateShellSafePath($databaseName, 'database name');
                $escapedDatabase = escapeshellarg($databaseName);

                $restoreCommand .= " {$escapedContainerName} pg_restore --username {$this->database->postgres_user} --dbname {$escapedDatabase} --clean --if-exists --no-owner --no-acl {$backupLocation}";
                $commands[] = $restoreCommand;
            }

            $this->restore_output = instant_remote_process($commands, $this->server, true, false, $this->timeout, disableMultiplexing: true);
            $this->restore_output = trim($this->restore_output);
        } catch (Throwable $e) {
            $this->addToErrorOutput($e->getMessage());
            throw $e;
        }
    }

    private function restoreMysql(string $backupLocation): void
    {
        try {
            $databaseName = $this->execution->database_name;
            $commands = [];
            $escapedContainerName = escapeshellarg($this->container_name);

            // Check if it's a dump_all backup (gzipped)
            if (str($backupLocation)->endsWith('.gz')) {
                $commands[] = "gunzip -c {$backupLocation} | docker exec -i {$escapedContainerName} mysql -u root -p\"{$this->database->mysql_root_password}\"";
            } else {
                validateShellSafePath($databaseName, 'database name');
                $escapedDatabase = escapeshellarg($databaseName);
                $commands[] = "docker exec -i {$escapedContainerName} mysql -u root -p\"{$this->database->mysql_root_password}\" {$escapedDatabase} < {$backupLocation}";
            }

            $this->restore_output = instant_remote_process($commands, $this->server, true, false, $this->timeout, disableMultiplexing: true);
            $this->restore_output = trim($this->restore_output);
        } catch (Throwable $e) {
            $this->addToErrorOutput($e->getMessage());
            throw $e;
        }
    }

    private function restoreMariadb(string $backupLocation): void
    {
        try {
            $databaseName = $this->execution->database_name;
            $commands = [];
            $escapedContainerName = escapeshellarg($this->container_name);

            // Check if it's a dump_all backup
            if (str($backupLocation)->endsWith('.gz')) {
                $commands[] = "gunzip -c {$backupLocation} | docker exec -i {$escapedContainerName} mariadb -u root -p\"{$this->database->mariadb_root_password}\"";
            } else {
                validateShellSafePath($databaseName, 'database name');
                $escapedDatabase = escapeshellarg($databaseName);
                $commands[] = "docker exec -i {$escapedContainerName} mariadb -u root -p\"{$this->database->mariadb_root_password}\" {$escapedDatabase} < {$backupLocation}";
            }

            $this->restore_output = instant_remote_process($commands, $this->server, true, false, $this->timeout, disableMultiplexing: true);
            $this->restore_output = trim($this->restore_output);
        } catch (Throwable $e) {
            $this->addToErrorOutput($e->getMessage());
            throw $e;
        }
    }

    private function restoreMongodb(string $backupLocation): void
    {
        try {
            $this->getMongoCredentials();

            $url = $this->database instanceof StandaloneMongodb ? $this->database->internal_db_url : null;
            if (blank($url)) {
                if (filled($this->mongo_root_username) && filled($this->mongo_root_password)) {
                    $url = "mongodb://{$this->mongo_root_username}:{$this->mongo_root_password}@{$this->container_name}:27017";
                } else {
                    throw new \Exception('MongoDB credentials not found');
                }
            }

            $commands = [];
            $escapedContainerName = escapeshellarg($this->container_name);

            if (str($this->database->image)->startsWith('mongo:4')) {
                $commands[] = "docker exec {$escapedContainerName} mongorestore --uri=\"{$url}\" --gzip --archive={$backupLocation} --drop";
            } else {
                $commands[] = "docker exec {$escapedContainerName} mongorestore --authenticationDatabase=admin --uri=\"{$url}\" --gzip --archive={$backupLocation} --drop";
            }

            $this->restore_output = instant_remote_process($commands, $this->server, true, false, $this->timeout, disableMultiplexing: true);
            $this->restore_output = trim($this->restore_output);
        } catch (Throwable $e) {
            $this->addToErrorOutput($e->getMessage());
            throw $e;
        }
    }

    private function getPostgresPassword(): void
    {
        if ($this->database instanceof ServiceDatabase) {
            $commands = [];
            $escapedContainerName = escapeshellarg($this->container_name);
            $commands[] = "docker exec {$escapedContainerName} env | grep POSTGRES_PASSWORD=";
            $envs = instant_remote_process($commands, $this->server, true, false, null, disableMultiplexing: true);
            $envs = str($envs)->explode("\n");

            $password = $envs->filter(function ($env) {
                return str($env)->startsWith('POSTGRES_PASSWORD=');
            })->first();

            if ($password) {
                $this->postgres_password = str($password)->after('POSTGRES_PASSWORD=')->value();
            }
        } else {
            $this->postgres_password = $this->database->postgres_password;
        }
    }

    private function getMongoCredentials(): void
    {
        if ($this->database instanceof ServiceDatabase) {
            $commands = [];
            $escapedContainerName = escapeshellarg($this->container_name);
            $commands[] = "docker exec {$escapedContainerName} env | grep MONGO_INITDB_";
            $envs = instant_remote_process($commands, $this->server, true, false, null, disableMultiplexing: true);

            if (filled($envs)) {
                $envs = str($envs)->explode("\n");
                $rootPassword = $envs->filter(fn ($env) => str($env)->startsWith('MONGO_INITDB_ROOT_PASSWORD='))->first();
                if ($rootPassword) {
                    $this->mongo_root_password = str($rootPassword)->after('MONGO_INITDB_ROOT_PASSWORD=')->value();
                }
                $rootUsername = $envs->filter(fn ($env) => str($env)->startsWith('MONGO_INITDB_ROOT_USERNAME='))->first();
                if ($rootUsername) {
                    $this->mongo_root_username = str($rootUsername)->after('MONGO_INITDB_ROOT_USERNAME=')->value();
                }
            }
        } else {
            $this->mongo_root_username = $this->database->mongo_initdb_root_username;
            $this->mongo_root_password = $this->database->mongo_initdb_root_password;
        }
    }

    private function getFullImageName(): string
    {
        $helperImage = config('constants.saturn.helper_image');
        $latestVersion = getHelperVersion();

        return "{$helperImage}:{$latestVersion}";
    }

    private function addToErrorOutput(string $output): void
    {
        if ($this->error_output) {
            $this->error_output = $this->error_output."\n".$output;
        } else {
            $this->error_output = $output;
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('scheduled-errors')->error('DatabaseRestore permanently failed', [
            'job' => 'DatabaseRestoreJob',
            'backup_id' => $this->backup->uuid,
            'execution_uuid' => $this->execution->uuid,
            'database' => $this->database->name ?? 'unknown',
            'error' => $exception?->getMessage(),
        ]);

        $this->execution->update([
            'restore_status' => 'failed',
            'restore_finished_at' => Carbon::now(),
            'restore_message' => $exception?->getMessage() ?? 'Unknown error',
        ]);
    }
}
