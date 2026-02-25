<?php

namespace App\Jobs;

use App\Actions\Migration\ExecuteMigrationAction;
use App\Models\EnvironmentMigration;
use App\Notifications\Migration\MigrationCompleted;
use App\Notifications\Migration\MigrationFailed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job for executing an approved environment migration.
 * Runs in the background to clone/update resources between environments.
 */
class ExecuteMigrationJob implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times to attempt the job.
     * Allows retry on transient failures (network timeout, docker restart).
     */
    public int $tries = 3;

    /**
     * Maximum number of unhandled exceptions before marking as permanently failed.
     * Prevents infinite retry loops on non-transient errors (e.g., missing model, logic error).
     */
    public int $maxExceptions = 3;

    /**
     * Backoff intervals in seconds between retries (1 min, 5 min, 15 min).
     *
     * @var array<int>
     */
    public array $backoff = [60, 300, 900];

    /**
     * Maximum job execution time in seconds (30 minutes).
     */
    public int $timeout = 1800;

    /**
     * The migration to execute.
     */
    protected EnvironmentMigration $migration;

    /**
     * Create a new job instance.
     */
    public function __construct(EnvironmentMigration $migration)
    {
        $this->migration = $migration;
    }

    /**
     * Unique ID to prevent duplicate job dispatches for the same migration.
     */
    public function uniqueId(): string
    {
        return 'migration-'.$this->migration->id;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Refresh the migration to get latest state
        $this->migration->refresh();

        // Check if migration is still valid to execute
        if (! $this->migration->canBeExecuted()) {
            $this->migration->appendLog('Migration cannot be executed. Status: '.$this->migration->status);

            return;
        }

        // Check target server exists and is healthy before proceeding
        $targetServer = $this->migration->targetServer;
        if (! $targetServer) {
            $this->migration->markAsFailed('Target server was deleted.');
            $this->notifyFailure('Target server was deleted.');

            return;
        }

        if (! $targetServer->isFunctional()) {
            $this->migration->markAsFailed('Target server is not reachable.');
            $this->notifyFailure('Target server is not reachable.');

            return;
        }

        $this->migration->appendLog('Starting migration job...');

        try {
            // Execute the migration
            $result = ExecuteMigrationAction::run($this->migration);

            if ($result['success']) {
                $this->migration->appendLog('Migration completed successfully');
                $this->notifySuccess();
            } else {
                $error = $result['error'] ?? 'Unknown error';
                $this->migration->markAsFailed($error);
                $this->migration->appendLog('Migration failed: '.$error);
                $this->notifyFailure($error);
            }

        } catch (Throwable $e) {
            // Only log here; markAsFailed is handled by the failed() hook
            // to avoid double status update
            $this->migration->appendLog('Migration exception: '.$e->getMessage());
            $this->notifyFailure($e->getMessage());

            throw $e;
        }
    }

    /**
     * Handle a job failure (called by Laravel after all retries exhausted or exception thrown).
     * This is the Dead Letter Queue handler — the job is written to the failed_jobs table
     * and will not be retried unless manually dispatched via queue:retry.
     */
    public function failed(Throwable $exception): void
    {
        // Log at critical level for monitoring/alerting (DLQ entry)
        Log::critical('Migration job permanently failed — written to failed_jobs (DLQ)', [
            'migration_uuid' => $this->migration->uuid,
            'migration_id' => $this->migration->id,
            'team_id' => $this->migration->team_id,
            'source_type' => $this->migration->source_type,
            'source_id' => $this->migration->source_id,
            'exception' => $exception->getMessage(),
            'exception_class' => get_class($exception),
        ]);

        try {
            $this->migration->markAsFailed($exception->getMessage());
            $this->migration->appendLog('Job failed permanently: '.$exception->getMessage());
        } catch (\LogicException $e) {
            // Migration already in terminal state — just log
            $this->migration->appendLog('Job failed but migration already terminal: '.$e->getMessage());
        }

        $this->notifyFailure($exception->getMessage());
    }

    /**
     * Notify about successful migration.
     */
    protected function notifySuccess(): void
    {
        try {
            $requester = $this->migration->requestedBy;
            if ($requester) {
                $requester->notify(new MigrationCompleted($this->migration));
            }

            // Also notify approver if different from requester
            if ($this->migration->requires_approval && $this->migration->approved_by) {
                $approver = $this->migration->approvedBy;
                if ($approver && $approver->id !== $requester?->id) {
                    $approver->notify(new MigrationCompleted($this->migration));
                }
            }
        } catch (Throwable $e) {
            Log::warning('Failed to send notification', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Notify about failed migration.
     */
    protected function notifyFailure(string $error): void
    {
        try {
            $requester = $this->migration->requestedBy;
            if ($requester) {
                $requester->notify(new MigrationFailed($this->migration, $error));
            }
        } catch (Throwable $e) {
            Log::warning('Failed to send notification', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get the tags for the job.
     */
    public function tags(): array
    {
        return [
            'migration',
            'migration:'.$this->migration->uuid,
            'team:'.$this->migration->team_id,
        ];
    }
}
