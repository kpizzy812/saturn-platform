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
use Symfony\Component\Process\Exception\ProcessTimedOutException;

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
     * @return array{success: bool, backup_id?: int, message?: string, error?: string, timed_out?: bool}
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

        $timeoutSeconds = (int) config('migration.pre_backup_timeout_seconds', 300);

        try {
            // Find or create a backup configuration for this database
            $backup = $this->getOrCreateBackupConfig($targetResource);

            // Set timeout in-memory so DatabaseBackupJob picks it up as its job timeout.
            // Note: this does not affect individual SSH command timeouts, but it signals
            // the intent and allows future SSH-level enforcement without further changes here.
            $backup->timeout = $timeoutSeconds;

            // Create execution record to track this specific backup
            $execution = ScheduledDatabaseBackupExecution::create([
                'scheduled_database_backup_id' => $backup->id,
                'status' => 'running',
                'message' => 'Pre-migration backup for migration '.$migration->uuid,
            ]);

            // Dispatch backup job synchronously to ensure backup completes before migration.
            // dispatchSync ignores Horizon's job timeout, so we rely on SSH process timeouts
            // and the orphan-execution check below.
            DatabaseBackupJob::dispatchSync($backup, $execution->id);

            // Refresh execution to check result
            $execution->refresh();

            // Guard: if execution is still 'running', the job silently abandoned it (orphan).
            // This can happen when SSH hangs past the command_timeout and the process is killed.
            if ($execution->status === 'running') {
                Log::warning('Pre-migration backup execution is still running after dispatchSync — treating as timed out', [
                    'migration_id' => $migration->id,
                    'execution_id' => $execution->id,
                    'timeout_seconds' => $timeoutSeconds,
                ]);

                $execution->update([
                    'status' => 'failed',
                    'message' => "Backup timed out after {$timeoutSeconds}s (orphan execution)",
                ]);

                return [
                    'success' => false,
                    'backup_id' => $execution->id,
                    'error' => "Backup timed out after {$timeoutSeconds}s",
                    'timed_out' => true,
                ];
            }

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

        } catch (ProcessTimedOutException $e) {
            Log::warning('Pre-migration backup SSH process timed out', [
                'migration_id' => $migration->id,
                'resource_type' => get_class($targetResource),
                'resource_id' => $targetResource->getAttribute('id'),
                'timeout_seconds' => $timeoutSeconds,
            ]);

            return [
                'success' => false,
                'error' => "Backup timed out after {$timeoutSeconds}s",
                'timed_out' => true,
            ];
        } catch (\Throwable $e) {
            $isTimeout = $this->isTimeoutException($e);

            Log::error('Pre-migration backup failed', [
                'migration_id' => $migration->id,
                'resource_type' => get_class($targetResource),
                'resource_id' => $targetResource->getAttribute('id'),
                'error' => $e->getMessage(),
                'timed_out' => $isTimeout,
            ]);

            if ($isTimeout) {
                return [
                    'success' => false,
                    'error' => "Backup timed out after {$timeoutSeconds}s",
                    'timed_out' => true,
                ];
            }

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

    /**
     * Detect whether an exception is timeout-related by inspecting its message.
     */
    private function isTimeoutException(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || $e instanceof ProcessTimedOutException;
    }
}
