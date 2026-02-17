<?php

namespace App\Jobs;

use App\Events\BackupCreated;
use App\Models\S3Storage;
use App\Models\ScheduledDatabaseBackup;
use App\Models\ScheduledDatabaseBackupExecution;
use App\Models\Server;
use App\Models\ServiceDatabase;
use App\Models\StandaloneClickhouse;
use App\Models\StandaloneDragonfly;
use App\Models\StandaloneKeydb;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use App\Models\StandaloneRedis;
use App\Models\Team;
use App\Notifications\Database\BackupFailed;
use App\Notifications\Database\BackupSuccess;
use App\Notifications\Database\BackupSuccessWithS3Warning;
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
use Visus\Cuid2\Cuid2;

class DatabaseBackupJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;

    public $maxExceptions = 1;

    public ?Team $team = null;

    public Server $server;

    public StandalonePostgresql|StandaloneMongodb|StandaloneMysql|StandaloneMariadb|StandaloneRedis|StandaloneKeydb|StandaloneDragonfly|StandaloneClickhouse|ServiceDatabase $database;

    public ?string $container_name = null;

    public ?string $directory_name = null;

    public ?ScheduledDatabaseBackupExecution $backup_log = null;

    public string $backup_status = 'failed';

    public ?string $backup_location = null;

    public string $backup_dir;

    public string $backup_file;

    public int $size = 0;

    public ?string $backup_output = null;

    public ?string $error_output = null;

    public bool $s3_uploaded = false;

    public ?string $postgres_password = null;

    public ?string $mongo_root_username = null;

    public ?string $mongo_root_password = null;

    public ?S3Storage $s3 = null;

    public $timeout = 3600;

    public ?string $backup_log_uuid = null;

    public ?int $preCreatedExecutionId = null;

    public function __construct(public ScheduledDatabaseBackup $backup, ?int $preCreatedExecutionId = null)
    {
        $this->preCreatedExecutionId = $preCreatedExecutionId;
        $this->onQueue('high');
        $this->timeout = max(60, $backup->timeout ?? 3600);
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [60, 120];
    }

    public function handle(): void
    {
        try {
            $databasesToBackup = null;

            $this->team = Team::find($this->backup->team_id);
            if (! $this->team) {
                $this->backup->delete();

                return;
            }
            if (data_get($this->backup, 'database_type') === \App\Models\ServiceDatabase::class) {
                $this->database = data_get($this->backup, 'database');
                $this->server = $this->database->service->server;
                $this->s3 = $this->backup->s3;
            } else {
                $this->database = data_get($this->backup, 'database');
                $this->server = $this->database->destination->server;
                $this->s3 = $this->backup->s3;
            }
            BackupCreated::dispatch($this->team->id);

            $status = str(data_get($this->database, 'status'));
            if (! $status->startsWith('running') && $this->database->id !== 0) {
                return;
            }
            if (data_get($this->backup, 'database_type') === \App\Models\ServiceDatabase::class) {
                $databaseType = $this->database->database_type;
                $serviceUuid = $this->database->service->uuid;
                $serviceName = str($this->database->service->name)->slug();
                if (str($databaseType)->contains('postgres')) {
                    $this->container_name = "{$this->database->name}-$serviceUuid";
                    $this->directory_name = $serviceName.'-'.$this->container_name;
                    $escapedContainerName = escapeshellarg($this->container_name);
                    $commands[] = "docker exec {$escapedContainerName} env | grep POSTGRES_";
                    $envs = instant_remote_process($commands, $this->server, true, false, null, disableMultiplexing: true);
                    $envs = str($envs)->explode("\n");

                    $user = $envs->filter(function ($env) {
                        return str($env)->startsWith('POSTGRES_USER=');
                    })->first();
                    if ($user) {
                        $this->database->postgres_user = str($user)->after('POSTGRES_USER=')->value();
                    } else {
                        $this->database->postgres_user = 'postgres';
                    }

                    $db = $envs->filter(function ($env) {
                        return str($env)->startsWith('POSTGRES_DB=');
                    })->first();

                    if ($db) {
                        $databasesToBackup = str($db)->after('POSTGRES_DB=')->value();
                    } else {
                        $databasesToBackup = $this->database->postgres_user;
                    }
                    $this->postgres_password = $envs->filter(function ($env) {
                        return str($env)->startsWith('POSTGRES_PASSWORD=');
                    })->first();
                    if ($this->postgres_password) {
                        $this->postgres_password = str($this->postgres_password)->after('POSTGRES_PASSWORD=')->value();
                    }
                } elseif (str($databaseType)->contains('mysql')) {
                    $this->container_name = "{$this->database->name}-$serviceUuid";
                    $this->directory_name = $serviceName.'-'.$this->container_name;
                    $escapedContainerName = escapeshellarg($this->container_name);
                    $commands[] = "docker exec {$escapedContainerName} env | grep MYSQL_";
                    $envs = instant_remote_process($commands, $this->server, true, false, null, disableMultiplexing: true);
                    $envs = str($envs)->explode("\n");

                    $rootPassword = $envs->filter(function ($env) {
                        return str($env)->startsWith('MYSQL_ROOT_PASSWORD=');
                    })->first();
                    if ($rootPassword) {
                        $this->database->mysql_root_password = str($rootPassword)->after('MYSQL_ROOT_PASSWORD=')->value();
                    }

                    $db = $envs->filter(function ($env) {
                        return str($env)->startsWith('MYSQL_DATABASE=');
                    })->first();

                    if ($db) {
                        $databasesToBackup = str($db)->after('MYSQL_DATABASE=')->value();
                    } else {
                        throw new \Exception('MYSQL_DATABASE not found');
                    }
                } elseif (str($databaseType)->contains('mariadb')) {
                    $this->container_name = "{$this->database->name}-$serviceUuid";
                    $this->directory_name = $serviceName.'-'.$this->container_name;
                    $escapedContainerName = escapeshellarg($this->container_name);
                    $commands[] = "docker exec {$escapedContainerName} env";
                    $envs = instant_remote_process($commands, $this->server, true, false, null, disableMultiplexing: true);
                    $envs = str($envs)->explode("\n");
                    $rootPassword = $envs->filter(function ($env) {
                        return str($env)->startsWith('MARIADB_ROOT_PASSWORD=');
                    })->first();
                    if ($rootPassword) {
                        $this->database->mariadb_root_password = str($rootPassword)->after('MARIADB_ROOT_PASSWORD=')->value();
                    } else {
                        $rootPassword = $envs->filter(function ($env) {
                            return str($env)->startsWith('MYSQL_ROOT_PASSWORD=');
                        })->first();
                        if ($rootPassword) {
                            $this->database->mariadb_root_password = str($rootPassword)->after('MYSQL_ROOT_PASSWORD=')->value();
                        }
                    }

                    $db = $envs->filter(function ($env) {
                        return str($env)->startsWith('MARIADB_DATABASE=');
                    })->first();

                    if ($db) {
                        $databasesToBackup = str($db)->after('MARIADB_DATABASE=')->value();
                    } else {
                        $db = $envs->filter(function ($env) {
                            return str($env)->startsWith('MYSQL_DATABASE=');
                        })->first();

                        if ($db) {
                            $databasesToBackup = str($db)->after('MYSQL_DATABASE=')->value();
                        } else {
                            throw new \Exception('MARIADB_DATABASE or MYSQL_DATABASE not found');
                        }
                    }
                } elseif (str($databaseType)->contains('mongo')) {
                    $databasesToBackup = ['*'];
                    $this->container_name = "{$this->database->name}-$serviceUuid";
                    $this->directory_name = $serviceName.'-'.$this->container_name;

                    // Try to extract MongoDB credentials from environment variables
                    try {
                        $commands = [];
                        $escapedContainerName = escapeshellarg($this->container_name);
                        $commands[] = "docker exec {$escapedContainerName} env | grep MONGO_INITDB_";
                        $envs = instant_remote_process($commands, $this->server, true, false, null, disableMultiplexing: true);

                        if (filled($envs)) {
                            $envs = str($envs)->explode("\n");
                            $rootPassword = $envs->filter(function ($env) {
                                return str($env)->startsWith('MONGO_INITDB_ROOT_PASSWORD=');
                            })->first();
                            if ($rootPassword) {
                                $this->mongo_root_password = str($rootPassword)->after('MONGO_INITDB_ROOT_PASSWORD=')->value();
                            }
                            $rootUsername = $envs->filter(function ($env) {
                                return str($env)->startsWith('MONGO_INITDB_ROOT_USERNAME=');
                            })->first();
                            if ($rootUsername) {
                                $this->mongo_root_username = str($rootUsername)->after('MONGO_INITDB_ROOT_USERNAME=')->value();
                            }
                        }

                    } catch (\Throwable $e) {
                        // Continue without env vars - will be handled in backup_standalone_mongodb method
                    }
                }
            } else {
                $databaseName = str($this->database->name)->slug()->value();
                $this->container_name = $this->database->uuid;
                $this->directory_name = $databaseName.'-'.$this->container_name;
                $databaseType = $this->database->type();
                $databasesToBackup = data_get($this->backup, 'databases_to_backup');
            }
            if (blank($databasesToBackup)) {
                if (str($databaseType)->contains('postgres')) {
                    $databasesToBackup = [$this->database->postgres_db];
                } elseif (str($databaseType)->contains('mongo')) {
                    $databasesToBackup = ['*'];
                } elseif (str($databaseType)->contains('mysql')) {
                    $databasesToBackup = [$this->database->mysql_database];
                } elseif (str($databaseType)->contains('mariadb')) {
                    $databasesToBackup = [$this->database->mariadb_database];
                } elseif (str($databaseType)->contains('redis') || str($databaseType)->contains('keydb') || str($databaseType)->contains('dragonfly')) {
                    $databasesToBackup = ['all'];
                } elseif (str($databaseType)->contains('clickhouse')) {
                    $databasesToBackup = [$this->database->clickhouse_db ?? 'default'];
                } else {
                    return;
                }
            } else {
                if (str($databaseType)->contains('postgres')) {
                    // Format: db1,db2,db3
                    $databasesToBackup = explode(',', $databasesToBackup);
                    $databasesToBackup = array_map('trim', $databasesToBackup);
                } elseif (str($databaseType)->contains('mongo')) {
                    // Format: db1:collection1,collection2|db2:collection3,collection4
                    // Only explode if it's a string, not if it's already an array
                    if (is_string($databasesToBackup)) {
                        $databasesToBackup = explode('|', $databasesToBackup);
                        $databasesToBackup = array_map('trim', $databasesToBackup);
                    }
                } elseif (str($databaseType)->contains('mysql')) {
                    // Format: db1,db2,db3
                    $databasesToBackup = explode(',', $databasesToBackup);
                    $databasesToBackup = array_map('trim', $databasesToBackup);
                } elseif (str($databaseType)->contains('mariadb')) {
                    // Format: db1,db2,db3
                    $databasesToBackup = explode(',', $databasesToBackup);
                    $databasesToBackup = array_map('trim', $databasesToBackup);
                } elseif (str($databaseType)->contains('redis') || str($databaseType)->contains('keydb') || str($databaseType)->contains('dragonfly')) {
                    $databasesToBackup = ['all'];
                } elseif (str($databaseType)->contains('clickhouse')) {
                    if (is_string($databasesToBackup)) {
                        $databasesToBackup = explode(',', $databasesToBackup);
                        $databasesToBackup = array_map('trim', $databasesToBackup);
                    }
                } else {
                    return;
                }
            }
            $this->backup_dir = backup_dir().'/databases/'.str($this->team->name)->slug().'-'.$this->team->id.'/'.$this->directory_name;
            if ($this->database->name === 'saturn-db') {
                $databasesToBackup = ['saturn'];
                $this->directory_name = $this->container_name = 'saturn-db';
                $ip = Str::slug($this->server->ip);
                $this->backup_dir = backup_dir().'/saturn'."/saturn-db-$ip";
            }
            foreach ($databasesToBackup as $database) {
                $size = 0;
                $localBackupSucceeded = false;
                $s3UploadError = null;

                // Step 1: Create local backup
                try {
                    if (str($databaseType)->contains('postgres')) {
                        $this->backup_file = "/pg-dump-$database-".Carbon::now()->timestamp.'.dmp';
                        if ($this->backup->dump_all) {
                            $this->backup_file = '/pg-dump-all-'.Carbon::now()->timestamp.'.gz';
                        }
                        $this->backup_location = $this->backup_dir.$this->backup_file;
                        $this->backup_log = $this->createOrReuseExecution($database, $this->backup_location);
                        $this->backup_standalone_postgresql($database);
                    } elseif (str($databaseType)->contains('mongo')) {
                        if ($database === '*') {
                            $database = 'all';
                            $databaseName = 'all';
                        } else {
                            if (str($database)->contains(':')) {
                                $databaseName = str($database)->before(':');
                            } else {
                                $databaseName = $database;
                            }
                        }
                        $this->backup_file = "/mongo-dump-$databaseName-".Carbon::now()->timestamp.'.tar.gz';
                        $this->backup_location = $this->backup_dir.$this->backup_file;
                        $this->backup_log = $this->createOrReuseExecution($databaseName, $this->backup_location);
                        $this->backup_standalone_mongodb($database);
                    } elseif (str($databaseType)->contains('mysql')) {
                        $this->backup_file = "/mysql-dump-$database-".Carbon::now()->timestamp.'.dmp';
                        if ($this->backup->dump_all) {
                            $this->backup_file = '/mysql-dump-all-'.Carbon::now()->timestamp.'.gz';
                        }
                        $this->backup_location = $this->backup_dir.$this->backup_file;
                        $this->backup_log = $this->createOrReuseExecution($database, $this->backup_location);
                        $this->backup_standalone_mysql($database);
                    } elseif (str($databaseType)->contains('mariadb')) {
                        $this->backup_file = "/mariadb-dump-$database-".Carbon::now()->timestamp.'.dmp';
                        if ($this->backup->dump_all) {
                            $this->backup_file = '/mariadb-dump-all-'.Carbon::now()->timestamp.'.gz';
                        }
                        $this->backup_location = $this->backup_dir.$this->backup_file;
                        $this->backup_log = $this->createOrReuseExecution($database, $this->backup_location);
                        $this->backup_standalone_mariadb($database);
                    } elseif (str($databaseType)->contains('redis') || str($databaseType)->contains('keydb') || str($databaseType)->contains('dragonfly')) {
                        $this->backup_file = "/redis-dump-$database-".Carbon::now()->timestamp.'.rdb';
                        $this->backup_location = $this->backup_dir.$this->backup_file;
                        $this->backup_log = $this->createOrReuseExecution($database, $this->backup_location);
                        $this->backup_standalone_redis($databaseType);
                    } elseif (str($databaseType)->contains('clickhouse')) {
                        $this->backup_file = "/clickhouse-dump-$database-".Carbon::now()->timestamp.'.tar.gz';
                        $this->backup_location = $this->backup_dir.$this->backup_file;
                        $this->backup_log = $this->createOrReuseExecution($database, $this->backup_location);
                        $this->backup_standalone_clickhouse($database);
                    } else {
                        throw new \Exception('Unsupported database type');
                    }

                    $size = $this->calculate_size();

                    // Verify local backup succeeded
                    if ($size > 0) {
                        $localBackupSucceeded = true;
                    } else {
                        throw new \Exception('Local backup file is empty or was not created');
                    }
                } catch (\Throwable $e) {
                    // Local backup failed
                    if ($this->backup_log) {
                        $this->backup_log->update([
                            'status' => 'failed',
                            'message' => $this->error_output ?? $this->backup_output ?? $e->getMessage(),
                            'size' => $size,
                            'filename' => null,
                            's3_uploaded' => null,
                        ]);
                    }
                    $this->team?->notify(new BackupFailed($this->backup, $this->database, $this->error_output ?? $this->backup_output ?? $e->getMessage(), $database));

                    continue;
                }

                // Step 2: Upload to S3 if enabled (independent of local backup)
                $localStorageDeleted = false;
                if ($this->backup->save_s3) {
                    try {
                        $this->upload_to_s3();

                        // If local backup is disabled, delete the local file immediately after S3 upload
                        if ($this->backup->disable_local_backup) {
                            deleteBackupsLocally($this->backup_location, $this->server);
                            $localStorageDeleted = true;
                        }
                    } catch (\Throwable $e) {
                        // S3 upload failed but local backup succeeded
                        $s3UploadError = $e->getMessage();
                    }
                }

                // Step 3: Update status and send notifications based on results
                $message = $this->backup_output;

                if ($s3UploadError) {
                    $message = $message
                        ? $message."\n\nWarning: S3 upload failed: ".$s3UploadError
                        : 'Warning: S3 upload failed: '.$s3UploadError;
                }

                $this->backup_log->update([
                    'status' => 'success',
                    'message' => $message,
                    'size' => $size,
                    's3_uploaded' => $this->backup->save_s3 ? $this->s3_uploaded : null,
                    'local_storage_deleted' => $localStorageDeleted,
                ]);

                // Send appropriate notification
                if ($s3UploadError) {
                    $this->team->notify(new BackupSuccessWithS3Warning($this->backup, $this->database, $database, $s3UploadError));
                } else {
                    $this->team->notify(new BackupSuccess($this->backup, $this->database, $database));
                }
            }
            if ($this->backup_log && $this->backup_log->status === 'success') {
                removeOldBackups($this->backup);

                // Dispatch verification job if enabled
                if ($this->backup->verify_after_backup ?? true) {
                    BackupVerificationJob::dispatch($this->backup_log);
                }
            }
        } catch (\Throwable $e) {
            throw $e;
        } finally {
            if ($this->team) {
                BackupCreated::dispatch($this->team->id);
            }
            if ($this->backup_log) {
                $this->backup_log->update([
                    'finished_at' => Carbon::now()->toImmutable(),
                ]);
            }
        }
    }

    /**
     * Create a new execution record or reuse a pre-created one (from manual export).
     */
    private function createOrReuseExecution(string $databaseName, string $filename): ScheduledDatabaseBackupExecution
    {
        // If a pre-created execution exists (from manual export), reuse it on first iteration
        if ($this->preCreatedExecutionId) {
            $execution = ScheduledDatabaseBackupExecution::find($this->preCreatedExecutionId);
            if ($execution) {
                $execution->update([
                    'database_name' => $databaseName,
                    'filename' => $filename,
                    'status' => 'running',
                ]);
                $this->backup_log_uuid = $execution->uuid;
                $this->preCreatedExecutionId = null; // Only reuse once

                return $execution;
            }
        }

        // Generate unique UUID for new execution
        $attempts = 0;
        do {
            $this->backup_log_uuid = (string) new Cuid2;
            $exists = ScheduledDatabaseBackupExecution::where('uuid', $this->backup_log_uuid)->exists();
            $attempts++;
            if ($attempts >= 3 && $exists) {
                throw new \Exception('Unable to generate unique UUID for backup execution after 3 attempts');
            }
        } while ($exists);

        return ScheduledDatabaseBackupExecution::create([
            'uuid' => $this->backup_log_uuid,
            'database_name' => $databaseName,
            'filename' => $filename,
            'scheduled_database_backup_id' => $this->backup->id,
            'local_storage_deleted' => false,
        ]);
    }

    private function backup_standalone_mongodb(string $databaseWithCollections): void
    {
        try {
            $url = $this->database->internal_db_url;
            if (blank($url)) {
                // For service-based MongoDB, try to build URL from environment variables
                if (filled($this->mongo_root_username) && filled($this->mongo_root_password)) {
                    // Use container name instead of server IP for service-based MongoDB
                    $url = "mongodb://{$this->mongo_root_username}:{$this->mongo_root_password}@{$this->container_name}:27017";
                } else {
                    // If no environment variables are available, throw an exception
                    throw new \Exception('MongoDB credentials not found. Ensure MONGO_INITDB_ROOT_USERNAME and MONGO_INITDB_ROOT_PASSWORD environment variables are available in the container.');
                }
            }
            Log::info('MongoDB backup URL configured', ['has_url' => filled($url), 'using_env_vars' => blank($this->database->internal_db_url)]);
            $escapedContainerName = escapeshellarg($this->container_name);
            if ($databaseWithCollections === 'all') {
                $commands[] = 'mkdir -p '.$this->backup_dir;
                if (str($this->database->image)->startsWith('mongo:4')) {
                    $commands[] = "docker exec {$escapedContainerName} mongodump --uri=\"$url\" --gzip --archive > $this->backup_location";
                } else {
                    $commands[] = "docker exec {$escapedContainerName} mongodump --authenticationDatabase=admin --uri=\"$url\" --gzip --archive > $this->backup_location";
                }
            } else {
                if (str($databaseWithCollections)->contains(':')) {
                    $databaseName = str($databaseWithCollections)->before(':');
                    $collectionsToExclude = str($databaseWithCollections)->after(':')->explode(',');
                } else {
                    $databaseName = $databaseWithCollections;
                    $collectionsToExclude = collect();
                }
                $commands[] = 'mkdir -p '.$this->backup_dir;

                // Validate and escape database name to prevent command injection
                validateShellSafePath($databaseName, 'database name');
                $escapedDatabaseName = escapeshellarg($databaseName);

                if ($collectionsToExclude->count() === 0) {
                    if (str($this->database->image)->startsWith('mongo:4')) {
                        $commands[] = "docker exec {$escapedContainerName} mongodump --uri=\"$url\" --gzip --archive > $this->backup_location";
                    } else {
                        $commands[] = "docker exec {$escapedContainerName} mongodump --authenticationDatabase=admin --uri=\"$url\" --db $escapedDatabaseName --gzip --archive > $this->backup_location";
                    }
                } else {
                    if (str($this->database->image)->startsWith('mongo:4')) {
                        $commands[] = "docker exec {$escapedContainerName} mongodump --uri=$url --gzip --excludeCollection ".$collectionsToExclude->implode(' --excludeCollection ')." --archive > $this->backup_location";
                    } else {
                        $commands[] = "docker exec {$escapedContainerName} mongodump --authenticationDatabase=admin --uri=\"$url\" --db $escapedDatabaseName --gzip --excludeCollection ".$collectionsToExclude->implode(' --excludeCollection ')." --archive > $this->backup_location";
                    }
                }
            }
            $this->backup_output = instant_remote_process($commands, $this->server, true, false, $this->timeout, disableMultiplexing: true);
            $this->backup_output = trim($this->backup_output);
            if ($this->backup_output === '') {
                $this->backup_output = null;
            }
        } catch (\Throwable $e) {
            $this->add_to_error_output($e->getMessage());
            throw $e;
        }
    }

    private function backup_standalone_postgresql(string $database): void
    {
        try {
            $commands[] = 'mkdir -p '.$this->backup_dir;
            $escapedContainerName = escapeshellarg($this->container_name);
            $backupCommand = 'docker exec';
            if ($this->postgres_password) {
                $escapedPassword = escapeshellarg($this->postgres_password);
                $backupCommand .= " -e PGPASSWORD={$escapedPassword}";
            }
            if ($this->backup->dump_all) {
                $backupCommand .= " {$escapedContainerName} pg_dumpall --username {$this->database->postgres_user} | gzip > $this->backup_location";
            } else {
                // Validate and escape database name to prevent command injection
                validateShellSafePath($database, 'database name');
                $escapedDatabase = escapeshellarg($database);
                $backupCommand .= " {$escapedContainerName} pg_dump --format=custom --no-acl --no-owner --username {$this->database->postgres_user} $escapedDatabase > $this->backup_location";
            }

            $commands[] = $backupCommand;
            $this->backup_output = instant_remote_process($commands, $this->server, true, false, $this->timeout, disableMultiplexing: true);
            $this->backup_output = trim($this->backup_output);
            if ($this->backup_output === '') {
                $this->backup_output = null;
            }
        } catch (\Throwable $e) {
            $this->add_to_error_output($e->getMessage());
            throw $e;
        }
    }

    private function backup_standalone_mysql(string $database): void
    {
        try {
            $commands[] = 'mkdir -p '.$this->backup_dir;
            $escapedContainerName = escapeshellarg($this->container_name);
            $escapedMysqlPassword = escapeshellarg($this->database->mysql_root_password);
            if ($this->backup->dump_all) {
                $commands[] = "docker exec {$escapedContainerName} mysqldump -u root -p{$escapedMysqlPassword} --all-databases --single-transaction --quick --lock-tables=false --compress | gzip > $this->backup_location";
            } else {
                // Validate and escape database name to prevent command injection
                validateShellSafePath($database, 'database name');
                $escapedDatabase = escapeshellarg($database);
                $commands[] = "docker exec {$escapedContainerName} mysqldump -u root -p{$escapedMysqlPassword} --single-transaction --quick --routines --events $escapedDatabase > $this->backup_location";
            }
            $this->backup_output = instant_remote_process($commands, $this->server, true, false, $this->timeout, disableMultiplexing: true);
            $this->backup_output = trim($this->backup_output);
            if ($this->backup_output === '') {
                $this->backup_output = null;
            }
        } catch (\Throwable $e) {
            $this->add_to_error_output($e->getMessage());
            throw $e;
        }
    }

    private function backup_standalone_mariadb(string $database): void
    {
        try {
            $commands[] = 'mkdir -p '.$this->backup_dir;
            $escapedContainerName = escapeshellarg($this->container_name);
            $escapedMariaPassword = escapeshellarg($this->database->mariadb_root_password);
            if ($this->backup->dump_all) {
                $commands[] = "docker exec {$escapedContainerName} mariadb-dump -u root -p{$escapedMariaPassword} --all-databases --single-transaction --quick --lock-tables=false --compress > $this->backup_location";
            } else {
                // Validate and escape database name to prevent command injection
                validateShellSafePath($database, 'database name');
                $escapedDatabase = escapeshellarg($database);
                $commands[] = "docker exec {$escapedContainerName} mariadb-dump -u root -p{$escapedMariaPassword} --single-transaction --quick --routines --events $escapedDatabase > $this->backup_location";
            }
            $this->backup_output = instant_remote_process($commands, $this->server, true, false, $this->timeout, disableMultiplexing: true);
            $this->backup_output = trim($this->backup_output);
            if ($this->backup_output === '') {
                $this->backup_output = null;
            }
        } catch (\Throwable $e) {
            $this->add_to_error_output($e->getMessage());
            throw $e;
        }
    }

    private function backup_standalone_redis(string $databaseType): void
    {
        try {
            $commands[] = 'mkdir -p '.$this->backup_dir;
            $escapedContainerName = escapeshellarg($this->container_name);

            // Get the password based on the database type
            $password = match (true) {
                str($databaseType)->contains('keydb') => $this->database->keydb_password ?? '',
                str($databaseType)->contains('dragonfly') => $this->database->dragonfly_password ?? '',
                default => $this->database->redis_password ?? '',
            };

            // Build auth flag
            $authFlag = '';
            if (filled($password)) {
                $escapedPassword = escapeshellarg($password);
                $authFlag = "-a {$escapedPassword} --no-auth-warning";
            }

            // Trigger synchronous SAVE to ensure data is flushed to disk
            $commands[] = "docker exec {$escapedContainerName} redis-cli {$authFlag} SAVE";

            // Copy the RDB file out of the container
            $commands[] = "docker exec {$escapedContainerName} cat /data/dump.rdb > {$this->backup_location}";

            $this->backup_output = instant_remote_process($commands, $this->server, true, false, $this->timeout, disableMultiplexing: true);
            $this->backup_output = trim($this->backup_output);
            if ($this->backup_output === '') {
                $this->backup_output = null;
            }
        } catch (\Throwable $e) {
            $this->add_to_error_output($e->getMessage());
            throw $e;
        }
    }

    private function backup_standalone_clickhouse(string $database): void
    {
        try {
            $commands[] = 'mkdir -p '.$this->backup_dir;
            $escapedContainerName = escapeshellarg($this->container_name);

            $user = $this->database->clickhouse_user ?? 'default';
            $password = $this->database->clickhouse_password ?? '';

            validateShellSafePath($database, 'database name');
            $escapedDatabase = escapeshellarg($database);
            $escapedUser = escapeshellarg($user);
            $escapedPassword = escapeshellarg($password);

            $tmpDir = '/tmp/ch_backup_'.Carbon::now()->timestamp;

            // Build a shell script that runs inside the container:
            // 1. Get list of tables
            // 2. For each table: dump CREATE TABLE DDL + data in Native format
            // 3. Package everything into tar.gz
            $script = implode(' && ', [
                "mkdir -p {$tmpDir}",
                // Dump DDL for all tables
                "clickhouse-client --user {$escapedUser} --password {$escapedPassword} -d {$escapedDatabase} --query 'SHOW TABLES' | while read -r tbl; do "
                    ."clickhouse-client --user {$escapedUser} --password {$escapedPassword} -d {$escapedDatabase} --query \"SHOW CREATE TABLE \\\"\${tbl}\\\"\" --format TSVRaw > {$tmpDir}/\${tbl}.sql; "
                    ."clickhouse-client --user {$escapedUser} --password {$escapedPassword} -d {$escapedDatabase} --query \"SELECT * FROM \\\"\${tbl}\\\" FORMAT Native\" > {$tmpDir}/\${tbl}.native; "
                    .'done',
                "cd /tmp && tar czf ch_backup.tar.gz -C {$tmpDir} .",
                "rm -rf {$tmpDir}",
            ]);

            $commands[] = "docker exec {$escapedContainerName} bash -c ".escapeshellarg($script);
            $commands[] = "docker exec {$escapedContainerName} cat /tmp/ch_backup.tar.gz > {$this->backup_location}";
            $commands[] = "docker exec {$escapedContainerName} rm -f /tmp/ch_backup.tar.gz";

            $this->backup_output = instant_remote_process($commands, $this->server, true, false, $this->timeout, disableMultiplexing: true);
            $this->backup_output = trim($this->backup_output);
            if ($this->backup_output === '') {
                $this->backup_output = null;
            }
        } catch (\Throwable $e) {
            $this->add_to_error_output($e->getMessage());
            throw $e;
        }
    }

    private function add_to_error_output($output): void
    {
        if ($this->error_output) {
            $this->error_output = $this->error_output."\n".$output;
        } else {
            $this->error_output = $output;
        }
    }

    private function calculate_size()
    {
        return instant_remote_process(["du -b $this->backup_location | cut -f1"], $this->server, false, false, null, disableMultiplexing: true);
    }

    private function upload_to_s3(): void
    {
        $escapedContainer = escapeshellarg("backup-of-{$this->backup_log_uuid}");
        try {
            if (is_null($this->s3)) {
                return;
            }
            $key = $this->s3->key;
            $secret = $this->s3->secret;
            // $region = $this->s3->region;
            $bucket = $this->s3->bucket;
            $endpoint = $this->s3->endpoint;
            $this->s3->testConnection(shouldSave: true);
            if (data_get($this->backup, 'database_type') === \App\Models\ServiceDatabase::class) {
                $network = $this->database->service->destination->network;
            } else {
                $network = $this->database->destination->network;
            }

            $fullImageName = $this->getFullImageName();
            $escapedNetwork = escapeshellarg($network);

            $containerExists = instant_remote_process(["docker ps -a -q -f name={$escapedContainer}"], $this->server, false, false, null, disableMultiplexing: true);
            if (filled($containerExists)) {
                instant_remote_process(["docker rm -f {$escapedContainer}"], $this->server, false, false, null, disableMultiplexing: true);
            }

            if (isDev()) {
                if ($this->database->name === 'saturn-db') {
                    $backup_location_from = '/var/lib/docker/volumes/saturn_dev_backups_data/_data/saturn/saturn-db-'.$this->server->ip.$this->backup_file;
                    $commands[] = "docker run -d --network {$escapedNetwork} --name {$escapedContainer} --rm -v ".escapeshellarg($backup_location_from.':'.$this->backup_location.':ro')." {$fullImageName}";
                } else {
                    $backup_location_from = '/var/lib/docker/volumes/saturn_dev_backups_data/_data/databases/'.str($this->team->name)->slug().'-'.$this->team->id.'/'.$this->directory_name.$this->backup_file;
                    $commands[] = "docker run -d --network {$escapedNetwork} --name {$escapedContainer} --rm -v ".escapeshellarg($backup_location_from.':'.$this->backup_location.':ro')." {$fullImageName}";
                }
            } else {
                $commands[] = "docker run -d --network {$escapedNetwork} --name {$escapedContainer} --rm -v ".escapeshellarg($this->backup_location.':'.$this->backup_location.':ro')." {$fullImageName}";
            }

            // Escape S3 credentials to prevent command injection
            $escapedEndpoint = escapeshellarg($endpoint);
            $escapedKey = escapeshellarg($key);
            $escapedSecret = escapeshellarg($secret);

            $commands[] = "docker exec {$escapedContainer} mc alias set temporary {$escapedEndpoint} {$escapedKey} {$escapedSecret}";
            $commands[] = "docker exec {$escapedContainer} mc cp ".escapeshellarg($this->backup_location).' '.escapeshellarg("temporary/{$bucket}{$this->backup_dir}/");
            instant_remote_process($commands, $this->server, true, false, null, disableMultiplexing: true);

            $this->s3_uploaded = true;
        } catch (\Throwable $e) {
            $this->s3_uploaded = false;
            $this->add_to_error_output($e->getMessage());
            throw $e;
        } finally {
            instant_remote_process(["docker rm -f {$escapedContainer}"], $this->server, true, false, null, disableMultiplexing: true);
        }
    }

    private function getFullImageName(): string
    {
        $helperImage = config('constants.saturn.helper_image');
        $latestVersion = getHelperVersion();

        return "{$helperImage}:{$latestVersion}";
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('scheduled-errors')->error('DatabaseBackup permanently failed', [
            'job' => 'DatabaseBackupJob',
            'backup_id' => $this->backup->uuid,
            'database' => $this->database->name ?? 'unknown',
            'database_type' => get_class($this->database),
            'server' => $this->server->name,
            'total_attempts' => $this->attempts(),
            'error' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);

        $log = ScheduledDatabaseBackupExecution::where('uuid', $this->backup_log_uuid)->first();

        if ($log) {
            $log->update([
                'status' => 'failed',
                'message' => 'Job permanently failed after '.$this->attempts().' attempts: '.($exception?->getMessage() ?? 'Unknown error'),
                'size' => 0,
                'filename' => null,
                'finished_at' => Carbon::now(),
            ]);
        }

        // Notify team about permanent failure
        if ($this->team) {
            $databaseName = $log->database_name ?? 'unknown';
            $output = $this->backup_output ?? $exception?->getMessage() ?? 'Unknown error';
            $this->team->notify(new BackupFailed($this->backup, $this->database, $output, $databaseName));
        }
    }
}
