<?php

namespace App\Jobs;

use App\Actions\Migration\ExecuteMigrationAction;
use App\Models\EnvironmentMigration;
use App\Notifications\Migration\MigrationCompleted;
use App\Notifications\Migration\MigrationFailed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Job for executing an approved environment migration.
 * Runs in the background to clone/update resources between environments.
 */
class ExecuteMigrationJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times to attempt the job.
     */
    public int $tries = 1;

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

        $this->migration->appendLog('Starting migration job...');

        try {
            // Execute the migration
            $result = ExecuteMigrationAction::run($this->migration);

            if ($result['success']) {
                $this->migration->appendLog('Migration completed successfully');
                $this->notifySuccess();
            } else {
                $this->migration->appendLog('Migration failed: '.($result['error'] ?? 'Unknown error'));
                $this->notifyFailure($result['error'] ?? 'Unknown error');
            }

        } catch (Throwable $e) {
            $this->migration->markAsFailed($e->getMessage());
            $this->migration->appendLog('Migration exception: '.$e->getMessage());
            $this->notifyFailure($e->getMessage());

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        $this->migration->markAsFailed($exception->getMessage());
        $this->migration->appendLog('Job failed: '.$exception->getMessage());
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
            ray('Failed to send success notification', $e->getMessage());
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
            ray('Failed to send failure notification', $e->getMessage());
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
