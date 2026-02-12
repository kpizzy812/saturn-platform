<?php

namespace App\Actions\Migration;

use App\Jobs\DatabaseBackupJob;
use App\Models\EnvironmentMigration;
use App\Models\ScheduledDatabaseBackup;
use App\Models\ScheduledDatabaseBackupExecution;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Create an automatic backup of the target resource before production promotion.
 *
 * For databases: creates a one-time backup via DatabaseBackupJob.
 * For applications: config is already snapshotted via MigrationHistory (no action needed).
 */
class CreatePreMigrationBackupAction
{
    use AsAction;

    /**
     * @return array{success: bool, backup_id?: int, message?: string, error?: string}
     */
    public function handle(Model $targetResource, EnvironmentMigration $migration): array
    {
        // Only databases need physical backup; apps are snapshot-based via MigrationHistory
        if (! self::isBackupable($targetResource)) {
            return [
                'success' => true,
                'message' => 'Backup not required for '.class_basename($targetResource),
            ];
        }

        try {
            // Find or create a backup configuration for this database
            $backup = $this->getOrCreateBackupConfig($targetResource);

            // Create execution record to track this specific backup
            $execution = ScheduledDatabaseBackupExecution::create([
                'scheduled_database_backup_id' => $backup->id,
                'status' => 'running',
                'message' => 'Pre-migration backup for migration '.$migration->uuid,
            ]);

            // Dispatch backup job synchronously to ensure backup completes before migration
            DatabaseBackupJob::dispatchSync($backup);

            // Refresh execution to check result
            $execution->refresh();

            if ($execution->status === 'failed') {
                return [
                    'success' => false,
                    'backup_id' => $execution->id,
                    'error' => 'Backup failed: '.($execution->message ?? 'Unknown error'),
                ];
            }

            return [
                'success' => true,
                'backup_id' => $execution->id,
                'message' => 'Backup completed successfully',
            ];

        } catch (\Throwable $e) {
            Log::error('Pre-migration backup failed', [
                'migration_id' => $migration->id,
                'resource_type' => get_class($targetResource),
                'resource_id' => $targetResource->getAttribute('id'),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Backup failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Get existing backup config or create a temporary one for the database.
     */
    protected function getOrCreateBackupConfig(Model $database): ScheduledDatabaseBackup
    {
        $existing = ScheduledDatabaseBackup::where('database_id', $database->getAttribute('id'))
            ->where('database_type', get_class($database))
            ->where('enabled', true)
            ->first();

        if ($existing) {
            return $existing;
        }

        return ScheduledDatabaseBackup::create([
            'database_id' => $database->getAttribute('id'),
            'database_type' => get_class($database),
            'enabled' => false,
            'frequency' => '0 0 * * *',
            'save_s3' => false,
            'databases_to_backup' => $this->getDefaultDatabaseName($database),
            'name' => 'Pre-migration backup',
        ]);
    }

    /**
     * Get the default database name to back up based on the database type.
     */
    protected function getDefaultDatabaseName(Model $database): string
    {
        if ($database instanceof StandalonePostgresql) {
            return $database->postgres_db ?? 'postgres';
        }

        if ($database instanceof StandaloneMysql) {
            return $database->mysql_database ?? 'mysql';
        }

        if ($database instanceof StandaloneMariadb) {
            return $database->mariadb_database ?? 'mariadb';
        }

        return '*';
    }

    /**
     * Check if a resource type supports backups.
     */
    public static function isBackupable(Model $resource): bool
    {
        return $resource instanceof StandalonePostgresql
            || $resource instanceof StandaloneMysql
            || $resource instanceof StandaloneMariadb
            || $resource instanceof StandaloneMongodb;
    }
}
