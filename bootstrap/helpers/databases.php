<?php

use App\Models\EnvironmentVariable;
use App\Models\S3Storage;
use App\Models\Server;
use App\Models\StandaloneClickhouse;
use App\Models\StandaloneDocker;
use App\Models\StandaloneDragonfly;
use App\Models\StandaloneKeydb;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use App\Models\StandaloneRedis;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Visus\Cuid2\Cuid2;

function create_standalone_postgresql($environmentId, $destinationUuid, ?array $otherData = null, string $databaseImage = 'postgres:16-alpine'): StandalonePostgresql
{
    $destination = StandaloneDocker::where('uuid', $destinationUuid)->firstOrFail();
    $database = new StandalonePostgresql;
    $database->uuid = (new Cuid2);
    $database->name = 'postgresql-database-'.$database->uuid;
    $database->image = $databaseImage;
    $database->postgres_password = \Illuminate\Support\Str::password(length: 64, symbols: false);
    $database->environment_id = $environmentId;
    $database->destination_id = $destination->id;
    $database->destination_type = $destination->getMorphClass();
    $database->is_public = false;
    if ($otherData) {
        $database->fill($otherData);
    }
    $database->save();

    return $database;
}

function create_standalone_redis($environment_id, $destination_uuid, ?array $otherData = null): StandaloneRedis
{
    $destination = StandaloneDocker::where('uuid', $destination_uuid)->firstOrFail();
    $database = new StandaloneRedis;
    $database->uuid = (new Cuid2);
    $database->name = 'redis-database-'.$database->uuid;

    $redis_password = \Illuminate\Support\Str::password(length: 64, symbols: false);
    if ($otherData && isset($otherData['redis_password'])) {
        $redis_password = $otherData['redis_password'];
        unset($otherData['redis_password']);
    }

    $database->environment_id = $environment_id;
    $database->destination_id = $destination->id;
    $database->destination_type = $destination->getMorphClass();
    $database->is_public = false;
    if ($otherData) {
        $database->fill($otherData);
    }
    $database->save();

    EnvironmentVariable::create([
        'key' => 'REDIS_PASSWORD',
        'value' => $redis_password,
        'resourceable_type' => StandaloneRedis::class,
        'resourceable_id' => $database->id,
        'is_shared' => false,
    ]);

    EnvironmentVariable::create([
        'key' => 'REDIS_USERNAME',
        'value' => 'default',
        'resourceable_type' => StandaloneRedis::class,
        'resourceable_id' => $database->id,
        'is_shared' => false,
    ]);

    return $database;
}

function create_standalone_mongodb($environment_id, $destination_uuid, ?array $otherData = null): StandaloneMongodb
{
    $destination = StandaloneDocker::where('uuid', $destination_uuid)->firstOrFail();
    $database = new StandaloneMongodb;
    $database->uuid = (new Cuid2);
    $database->name = 'mongodb-database-'.$database->uuid;
    $database->mongo_initdb_root_password = \Illuminate\Support\Str::password(length: 64, symbols: false);
    $database->environment_id = $environment_id;
    $database->destination_id = $destination->id;
    $database->destination_type = $destination->getMorphClass();
    $database->is_public = false;
    if ($otherData) {
        $database->fill($otherData);
    }
    $database->save();

    return $database;
}

function create_standalone_mysql($environment_id, $destination_uuid, ?array $otherData = null): StandaloneMysql
{
    $destination = StandaloneDocker::where('uuid', $destination_uuid)->firstOrFail();
    $database = new StandaloneMysql;
    $database->uuid = (new Cuid2);
    $database->name = 'mysql-database-'.$database->uuid;
    $database->mysql_root_password = \Illuminate\Support\Str::password(length: 64, symbols: false);
    $database->mysql_password = \Illuminate\Support\Str::password(length: 64, symbols: false);
    $database->environment_id = $environment_id;
    $database->destination_id = $destination->id;
    $database->destination_type = $destination->getMorphClass();
    $database->is_public = false;
    if ($otherData) {
        $database->fill($otherData);
    }
    $database->save();

    return $database;
}

function create_standalone_mariadb($environment_id, $destination_uuid, ?array $otherData = null): StandaloneMariadb
{
    $destination = StandaloneDocker::where('uuid', $destination_uuid)->firstOrFail();
    $database = new StandaloneMariadb;
    $database->uuid = (new Cuid2);
    $database->name = 'mariadb-database-'.$database->uuid;
    $database->mariadb_root_password = \Illuminate\Support\Str::password(length: 64, symbols: false);
    $database->mariadb_password = \Illuminate\Support\Str::password(length: 64, symbols: false);
    $database->environment_id = $environment_id;
    $database->destination_id = $destination->id;
    $database->destination_type = $destination->getMorphClass();
    $database->is_public = false;
    if ($otherData) {
        $database->fill($otherData);
    }
    $database->save();

    return $database;
}

function create_standalone_keydb($environment_id, $destination_uuid, ?array $otherData = null): StandaloneKeydb
{
    $destination = StandaloneDocker::where('uuid', $destination_uuid)->firstOrFail();
    $database = new StandaloneKeydb;
    $database->uuid = (new Cuid2);
    $database->name = 'keydb-database-'.$database->uuid;
    $database->keydb_password = \Illuminate\Support\Str::password(length: 64, symbols: false);
    $database->environment_id = $environment_id;
    $database->destination_id = $destination->id;
    $database->destination_type = $destination->getMorphClass();
    $database->is_public = false;
    if ($otherData) {
        $database->fill($otherData);
    }
    $database->save();

    return $database;
}

function create_standalone_dragonfly($environment_id, $destination_uuid, ?array $otherData = null): StandaloneDragonfly
{
    $destination = StandaloneDocker::where('uuid', $destination_uuid)->firstOrFail();
    $database = new StandaloneDragonfly;
    $database->uuid = (new Cuid2);
    $database->name = 'dragonfly-database-'.$database->uuid;
    $database->dragonfly_password = \Illuminate\Support\Str::password(length: 64, symbols: false);
    $database->environment_id = $environment_id;
    $database->destination_id = $destination->id;
    $database->destination_type = $destination->getMorphClass();
    $database->is_public = false;
    if ($otherData) {
        $database->fill($otherData);
    }
    $database->save();

    return $database;
}

function create_standalone_clickhouse($environment_id, $destination_uuid, ?array $otherData = null): StandaloneClickhouse
{
    $destination = StandaloneDocker::where('uuid', $destination_uuid)->firstOrFail();
    $database = new StandaloneClickhouse;
    $database->uuid = (new Cuid2);
    $database->name = 'clickhouse-database-'.$database->uuid;
    $database->clickhouse_admin_password = \Illuminate\Support\Str::password(length: 64, symbols: false);
    $database->environment_id = $environment_id;
    $database->destination_id = $destination->id;
    $database->destination_type = $destination->getMorphClass();
    $database->is_public = false;
    if ($otherData) {
        $database->fill($otherData);
    }
    $database->save();

    return $database;
}

function deleteBackupsLocally(string|array|null $filenames, Server $server): void
{
    if (empty($filenames)) {
        return;
    }
    if (is_string($filenames)) {
        $filenames = [$filenames];
    }
    $quotedFiles = array_map(fn ($file) => "\"$file\"", $filenames);
    instant_remote_process(['rm -f '.implode(' ', $quotedFiles)], $server, throwError: false);

    $foldersToCheck = collect($filenames)->map(fn ($file) => dirname($file))->unique();
    $foldersToCheck->each(fn ($folder) => deleteEmptyBackupFolder($folder, $server));
}

function deleteBackupsS3(string|array|null $filenames, S3Storage $s3): void
{
    if (empty($filenames) || ! $s3) {
        return;
    }
    if (is_string($filenames)) {
        $filenames = [$filenames];
    }

    $disk = Storage::build([
        'driver' => 's3',
        'key' => $s3->key,
        'secret' => $s3->secret,
        'region' => $s3->region,
        'bucket' => $s3->bucket,
        'endpoint' => $s3->endpoint,
        'use_path_style_endpoint' => true,
        'aws_url' => $s3->awsUrl(),
    ]);

    $disk->delete($filenames);
}

function deleteEmptyBackupFolder($folderPath, Server $server): void
{
    $escapedPath = escapeshellarg($folderPath);
    $escapedParentPath = escapeshellarg(dirname($folderPath));

    $checkEmpty = instant_remote_process(["[ -d $escapedPath ] && [ -z \"$(ls -A $escapedPath)\" ] && echo 'empty' || echo 'not empty'"], $server, throwError: false);

    if (trim($checkEmpty) === 'empty') {
        instant_remote_process(["rmdir $escapedPath"], $server, throwError: false);
        $checkParentEmpty = instant_remote_process(["[ -d $escapedParentPath ] && [ -z \"$(ls -A $escapedParentPath)\" ] && echo 'empty' || echo 'not empty'"], $server, throwError: false);
        if (trim($checkParentEmpty) === 'empty') {
            instant_remote_process(["rmdir $escapedParentPath"], $server, throwError: false);
        }
    }
}

function removeOldBackups($backup): void
{
    try {
        if ($backup->executions) {
            // Delete old local backups (only if local backup is NOT disabled)
            // Note: When disable_local_backup is enabled, each execution already marks its own
            // local_storage_deleted status at the time of backup, so we don't need to retroactively
            // update old executions
            if (! $backup->disable_local_backup) {
                $localBackupsToDelete = deleteOldBackupsLocally($backup);
                if ($localBackupsToDelete->isNotEmpty()) {
                    $backup->executions()
                        ->whereIn('id', $localBackupsToDelete->pluck('id'))
                        ->update(['local_storage_deleted' => true]);
                }
            }
        }

        if ($backup->save_s3 && $backup->executions) {
            $s3BackupsToDelete = deleteOldBackupsFromS3($backup);
            if ($s3BackupsToDelete->isNotEmpty()) {
                $backup->executions()
                    ->whereIn('id', $s3BackupsToDelete->pluck('id'))
                    ->update(['s3_storage_deleted' => true]);
            }
        }

        // Delete execution records where all backup copies are gone
        // Case 1: Both local and S3 backups are deleted
        $backup->executions()
            ->where('local_storage_deleted', true)
            ->where('s3_storage_deleted', true)
            ->delete();

        // Case 2: Local backup is deleted and S3 was never used (s3_uploaded is null)
        $backup->executions()
            ->where('local_storage_deleted', true)
            ->whereNull('s3_uploaded')
            ->delete();

    } catch (\Exception $e) {
        throw $e;
    }
}

function deleteOldBackupsLocally($backup): Collection
{
    if (! $backup || ! $backup->executions) {
        return collect();
    }

    $successfulBackups = $backup->executions()
        ->where('status', 'success')
        ->where('local_storage_deleted', false)
        ->orderBy('created_at', 'desc')
        ->get();

    if ($successfulBackups->isEmpty()) {
        return collect();
    }

    $retentionAmount = $backup->database_backup_retention_amount_locally;
    $retentionDays = $backup->database_backup_retention_days_locally;
    $maxStorageGB = $backup->database_backup_retention_max_storage_locally;

    if ($retentionAmount === 0 && $retentionDays === 0 && $maxStorageGB === 0) {
        return collect();
    }

    $backupsToDelete = collect();

    if ($retentionAmount > 0) {
        $byAmount = $successfulBackups->skip($retentionAmount);
        $backupsToDelete = $backupsToDelete->merge($byAmount);
    }

    if ($retentionDays > 0) {
        $oldestAllowedDate = $successfulBackups->first()->created_at->clone()->utc()->subDays($retentionDays);
        $oldBackups = $successfulBackups->filter(fn ($execution) => $execution->created_at->utc() < $oldestAllowedDate);
        $backupsToDelete = $backupsToDelete->merge($oldBackups);
    }

    if ($maxStorageGB > 0) {
        $maxStorageBytes = $maxStorageGB * pow(1024, 3);
        $totalSize = 0;
        $backupsOverLimit = collect();

        $backupsToCheck = $successfulBackups->skip(1);

        foreach ($backupsToCheck as $backupExecution) {
            $totalSize += (int) $backupExecution->size;
            if ($totalSize > $maxStorageBytes) {
                $backupsOverLimit = $successfulBackups->filter(
                    fn ($b) => $b->created_at->utc() <= $backupExecution->created_at->utc()
                )->skip(1);
                break;
            }
        }

        $backupsToDelete = $backupsToDelete->merge($backupsOverLimit);
    }

    $backupsToDelete = $backupsToDelete->unique('id');
    $processedBackups = collect();

    $server = null;
    if ($backup->database_type === \App\Models\ServiceDatabase::class) {
        $server = $backup->database->service->server;
    } else {
        $server = $backup->database->destination->server;
    }

    if (! $server) {
        return collect();
    }

    $filesToDelete = $backupsToDelete
        ->filter(fn ($execution) => ! empty($execution->filename))
        ->pluck('filename')
        ->all();

    if (! empty($filesToDelete)) {
        deleteBackupsLocally($filesToDelete, $server);
        $processedBackups = $backupsToDelete;
    }

    return $processedBackups;
}

function deleteOldBackupsFromS3($backup): Collection
{
    if (! $backup || ! $backup->executions || ! $backup->s3) {
        return collect();
    }

    $successfulBackups = $backup->executions()
        ->where('status', 'success')
        ->where('s3_storage_deleted', false)
        ->orderBy('created_at', 'desc')
        ->get();

    if ($successfulBackups->isEmpty()) {
        return collect();
    }

    $retentionAmount = $backup->database_backup_retention_amount_s3;
    $retentionDays = $backup->database_backup_retention_days_s3;
    $maxStorageGB = $backup->database_backup_retention_max_storage_s3;

    if ($retentionAmount === 0 && $retentionDays === 0 && $maxStorageGB === 0) {
        return collect();
    }

    $backupsToDelete = collect();

    if ($retentionAmount > 0) {
        $byAmount = $successfulBackups->skip($retentionAmount);
        $backupsToDelete = $backupsToDelete->merge($byAmount);
    }

    if ($retentionDays > 0) {
        $oldestAllowedDate = $successfulBackups->first()->created_at->clone()->utc()->subDays($retentionDays);
        $oldBackups = $successfulBackups->filter(fn ($execution) => $execution->created_at->utc() < $oldestAllowedDate);
        $backupsToDelete = $backupsToDelete->merge($oldBackups);
    }

    if ($maxStorageGB > 0) {
        $maxStorageBytes = $maxStorageGB * pow(1024, 3);
        $totalSize = 0;
        $backupsOverLimit = collect();

        $backupsToCheck = $successfulBackups->skip(1);

        foreach ($backupsToCheck as $backupExecution) {
            $totalSize += (int) $backupExecution->size;
            if ($totalSize > $maxStorageBytes) {
                $backupsOverLimit = $successfulBackups->filter(
                    fn ($b) => $b->created_at->utc() <= $backupExecution->created_at->utc()
                )->skip(1);
                break;
            }
        }

        $backupsToDelete = $backupsToDelete->merge($backupsOverLimit);
    }

    $backupsToDelete = $backupsToDelete->unique('id');
    $processedBackups = collect();

    $filesToDelete = $backupsToDelete
        ->filter(fn ($execution) => ! empty($execution->filename))
        ->pluck('filename')
        ->all();

    if (! empty($filesToDelete)) {
        deleteBackupsS3($filesToDelete, $backup->s3);
        $processedBackups = $backupsToDelete;
    }

    return $processedBackups;
}

function findDatabaseByUuid(string $uuid): array
{
    $database = StandalonePostgresql::ownedByCurrentTeam()->where('uuid', $uuid)->first();
    if ($database) {
        return [$database, 'postgresql'];
    }

    $database = StandaloneMysql::ownedByCurrentTeam()->where('uuid', $uuid)->first();
    if ($database) {
        return [$database, 'mysql'];
    }

    $database = StandaloneMariadb::ownedByCurrentTeam()->where('uuid', $uuid)->first();
    if ($database) {
        return [$database, 'mariadb'];
    }

    $database = StandaloneMongodb::ownedByCurrentTeam()->where('uuid', $uuid)->first();
    if ($database) {
        return [$database, 'mongodb'];
    }

    $database = StandaloneRedis::ownedByCurrentTeam()->where('uuid', $uuid)->first();
    if ($database) {
        return [$database, 'redis'];
    }

    $database = StandaloneKeydb::ownedByCurrentTeam()->where('uuid', $uuid)->first();
    if ($database) {
        return [$database, 'keydb'];
    }

    $database = StandaloneDragonfly::ownedByCurrentTeam()->where('uuid', $uuid)->first();
    if ($database) {
        return [$database, 'dragonfly'];
    }

    $database = StandaloneClickhouse::ownedByCurrentTeam()->where('uuid', $uuid)->first();
    if ($database) {
        return [$database, 'clickhouse'];
    }

    abort(404);
}

function formatDatabaseForView($databaseWithType): array
{
    [$database, $type] = is_array($databaseWithType) ? $databaseWithType : [$databaseWithType, 'postgresql'];

    $image = $database->image ?? '';
    $version = str_contains($image, ':') ? explode(':', $image, 2)[1] : $image;

    $connection = [];
    switch ($type) {
        case 'postgresql':
            $connection = [
                'internal_host' => $database->uuid,
                'port' => '5432',
                'database' => $database->postgres_db ?? 'postgres',
                'username' => $database->postgres_user ?? 'postgres',
                'password' => $database->postgres_password ?? '',
            ];
            break;
        case 'mysql':
        case 'mariadb':
            $connection = [
                'internal_host' => $database->uuid,
                'port' => '3306',
                'database' => $database->mysql_database ?? ($database->mariadb_database ?? ''),
                'username' => $database->mysql_user ?? ($database->mariadb_user ?? 'root'),
                'password' => $database->mysql_password ?? ($database->mariadb_password ?? $database->mysql_root_password ?? $database->mariadb_root_password ?? ''),
            ];
            break;
        case 'mongodb':
            $connection = [
                'internal_host' => $database->uuid,
                'port' => '27017',
                'database' => $database->mongo_initdb_database ?? 'admin',
                'username' => $database->mongo_initdb_root_username ?? '',
                'password' => $database->mongo_initdb_root_password ?? '',
            ];
            break;
        case 'redis':
        case 'keydb':
        case 'dragonfly':
            $connection = [
                'internal_host' => $database->uuid,
                'port' => '6379',
                'database' => '0',
                'username' => '',
                'password' => $database->redis_password ?? '',
            ];
            break;
        case 'clickhouse':
            $connection = [
                'internal_host' => $database->uuid,
                'port' => '8123',
                'database' => $database->clickhouse_db ?? 'default',
                'username' => $database->clickhouse_user ?? 'default',
                'password' => $database->clickhouse_password ?? '',
            ];
            break;
    }

    return [
        'id' => $database->id,
        'uuid' => $database->uuid,
        'name' => $database->name,
        'description' => $database->description,
        'database_type' => $type,
        'status' => $database->status,
        'image' => $image,
        'version' => $version,
        'is_public' => $database->is_public ?? false,
        'public_port' => $database->public_port,
        'limits_memory' => $database->limits_memory ?? '0',
        'limits_memory_swap' => $database->limits_memory_swap ?? '0',
        'limits_memory_swappiness' => $database->limits_memory_swappiness ?? 60,
        'limits_memory_reservation' => $database->limits_memory_reservation ?? '0',
        'limits_cpus' => $database->limits_cpus ?? '0',
        'limits_cpuset' => $database->limits_cpuset ?? '0',
        'limits_cpu_shares' => $database->limits_cpu_shares ?? 1024,
        'enable_ssl' => $database->enable_ssl ?? false,
        'ssl_mode' => $database->ssl_mode ?? null,
        'allowed_ips' => $database->allowed_ips ?? null,
        'storage_limit' => $database->storage_limit ?? 0,
        'auto_scaling_enabled' => $database->auto_scaling_enabled ?? false,
        'connection_pool_enabled' => $database->connection_pool_enabled ?? false,
        'connection_pool_size' => $database->connection_pool_size ?? 20,
        'connection_pool_max' => $database->connection_pool_max ?? 100,
        'postgres_conf' => $database->postgres_conf ?? null,
        'custom_docker_run_options' => $database->custom_docker_run_options ?? null,
        'internal_db_url' => $database->internal_db_url ?? '',
        'external_db_url' => $database->external_db_url ?? '',
        'connection' => $connection,
        'postgres_user' => $database->postgres_user ?? null,
        'postgres_password' => $database->postgres_password ?? null,
        'postgres_db' => $database->postgres_db ?? null,
        'environment_id' => $database->environment_id,
        'created_at' => $database->created_at,
        'updated_at' => $database->updated_at,
    ];
}

function getAllDatabases(): Collection
{
    $databases = StandalonePostgresql::ownedByCurrentTeamCached()
        ->map(fn ($db) => formatDatabaseForView([$db, 'postgresql']));

    $mysqlDatabases = StandaloneMysql::ownedByCurrentTeamCached()
        ->map(fn ($db) => formatDatabaseForView([$db, 'mysql']));

    $mariadbDatabases = StandaloneMariadb::ownedByCurrentTeamCached()
        ->map(fn ($db) => formatDatabaseForView([$db, 'mariadb']));

    $mongodbDatabases = StandaloneMongodb::ownedByCurrentTeamCached()
        ->map(fn ($db) => formatDatabaseForView([$db, 'mongodb']));

    $redisDatabases = StandaloneRedis::ownedByCurrentTeamCached()
        ->map(fn ($db) => formatDatabaseForView([$db, 'redis']));

    $keydbDatabases = StandaloneKeydb::ownedByCurrentTeamCached()
        ->map(fn ($db) => formatDatabaseForView([$db, 'keydb']));

    $dragonflyDatabases = StandaloneDragonfly::ownedByCurrentTeamCached()
        ->map(fn ($db) => formatDatabaseForView([$db, 'dragonfly']));

    $clickhouseDatabases = StandaloneClickhouse::ownedByCurrentTeamCached()
        ->map(fn ($db) => formatDatabaseForView([$db, 'clickhouse']));

    return $databases
        ->concat($mysqlDatabases)
        ->concat($mariadbDatabases)
        ->concat($mongodbDatabases)
        ->concat($redisDatabases)
        ->concat($keydbDatabases)
        ->concat($dragonflyDatabases)
        ->concat($clickhouseDatabases)
        ->sortByDesc('updated_at')
        ->values();
}

function getRandomPublicPort(StandaloneDocker $destination): int
{
    $server = $destination->server;
    $maxAttempts = 50;
    for ($i = 0; $i < $maxAttempts; $i++) {
        $port = random_int(10000, 65535);
        if (! isPublicPortAlreadyUsed($server, $port)) {
            return $port;
        }
    }

    // Fallback: return a random port even if collision check fails
    return random_int(10000, 65535);
}

function isPublicPortAlreadyUsed(Server $server, int $port, ?string $excludeUuid = null): bool
{
    $databases = $server->databases()
        ->where('public_port', $port)
        ->where('is_public', true);

    if ($excludeUuid) {
        // Use uuid (not id) because different DB types are in separate tables
        // and can share the same auto-increment id
        $databases = $databases->where('uuid', '!=', $excludeUuid);
    }

    return $databases->isNotEmpty();
}
