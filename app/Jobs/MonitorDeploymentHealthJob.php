<?php

namespace App\Jobs;

use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\ApplicationRollbackEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Monitors deployment health after a successful deploy.
 *
 * Uses self-dispatching pattern: each invocation performs ONE health check,
 * then dispatches itself again with delay for the next check. This avoids
 * blocking a queue worker with sleep() for the entire validation period.
 */
class MonitorDeploymentHealthJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        public ApplicationDeploymentQueue $deployment,
        public int $checkIntervalSeconds = 30,
        public int $totalChecks = 10,
        public int $currentCheck = 0,
        public int $initialRestartCount = -1
    ) {}

    public function handle(): void
    {
        $application = $this->deployment->application;

        if (! $application?->settings?->auto_rollback_enabled) {
            return;
        }

        if ($this->deployment->pull_request_id > 0) {
            return;
        }

        if ($this->deployment->status !== 'finished') {
            return;
        }

        // Capture initial restart count on first check
        if ($this->initialRestartCount < 0) {
            $this->initialRestartCount = $application->restart_count ?? 0;
        }

        if ($this->currentCheck === 0) {
            Log::info("Starting health monitoring for deployment {$this->deployment->deployment_uuid} ({$this->totalChecks} checks)");
        }

        // Refresh application to get latest status
        $application->refresh();

        // Capture metrics
        $metricsSnapshot = $this->captureMetrics($application);

        // Check if rollback is needed
        $rollbackReason = $this->checkForRollback($application);

        if ($rollbackReason !== null) {
            $this->triggerAutoRollback($application, $rollbackReason, $metricsSnapshot);

            return;
        }

        $this->currentCheck++;

        // Schedule next check or complete monitoring
        if ($this->currentCheck < $this->totalChecks) {
            self::dispatch(
                deployment: $this->deployment,
                checkIntervalSeconds: $this->checkIntervalSeconds,
                totalChecks: $this->totalChecks,
                currentCheck: $this->currentCheck,
                initialRestartCount: $this->initialRestartCount
            )->delay(now()->addSeconds($this->checkIntervalSeconds));
        } else {
            Log::info("Health monitoring complete for {$this->deployment->deployment_uuid} - all checks passed");
        }
    }

    protected function captureMetrics(Application $application): array
    {
        return [
            'status' => $application->status,
            'restart_count' => $application->restart_count ?? 0,
            'initial_restart_count' => $this->initialRestartCount,
            'restart_delta' => ($application->restart_count ?? 0) - $this->initialRestartCount,
            'last_restart_at' => $application->last_restart_at?->toIso8601String(),
            'check_number' => $this->currentCheck + 1,
            'total_checks' => $this->totalChecks,
            'captured_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Check if rollback should be triggered. Returns reason string or null.
     */
    protected function checkForRollback(Application $application): ?string
    {
        $settings = $application->settings;

        // Check 1: Crash loop detection
        if ($settings->rollback_on_crash_loop) {
            $maxRestarts = $settings->rollback_max_restarts ?? 3;
            $restartDelta = ($application->restart_count ?? 0) - $this->initialRestartCount;

            if ($restartDelta >= $maxRestarts) {
                Log::warning("Crash loop detected for {$application->name}: {$restartDelta} restarts since deployment");

                return ApplicationRollbackEvent::REASON_CRASH_LOOP;
            }
        }

        // Check 2: Container exited/degraded
        $status = $application->status ?? '';
        if (str_starts_with($status, 'exited') || str_starts_with($status, 'degraded')) {
            Log::warning("Container unhealthy for {$application->name}: status={$status}");

            return ApplicationRollbackEvent::REASON_CONTAINER_EXITED;
        }

        // Check 3: Health check failures
        if ($settings->rollback_on_health_check_fail && $application->health_check_enabled) {
            $healthStatus = str($status)->after(':')->value();

            if ($healthStatus === 'unhealthy') {
                Log::warning("Health check failed for {$application->name}");

                return ApplicationRollbackEvent::REASON_HEALTH_CHECK_FAILED;
            }
        }

        return null;
    }

    protected function triggerAutoRollback(Application $application, string $reason, array $metricsSnapshot): void
    {
        Log::warning("Triggering auto-rollback for {$application->name} due to: {$reason}");

        // Find last successful deployment (before the current one)
        $lastSuccessful = ApplicationDeploymentQueue::where('application_id', $application->id)
            ->where('status', 'finished')
            ->where('pull_request_id', 0)
            ->where('id', '<', $this->deployment->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $lastSuccessful) {
            Log::warning('No previous successful deployment found for rollback');

            ApplicationRollbackEvent::create([
                'application_id' => $application->id,
                'failed_deployment_id' => $this->deployment->id,
                'trigger_reason' => $reason,
                'trigger_type' => 'automatic',
                'metrics_snapshot' => $metricsSnapshot,
                'status' => ApplicationRollbackEvent::STATUS_SKIPPED,
                'error_message' => 'No previous successful deployment found',
                'from_commit' => $this->deployment->commit,
                'triggered_at' => now(),
                'completed_at' => now(),
            ]);

            return;
        }

        // Create rollback event
        $rollbackEvent = ApplicationRollbackEvent::createEvent(
            application: $application,
            reason: $reason,
            type: 'automatic',
            failedDeployment: $this->deployment,
            metrics: $metricsSnapshot
        );

        $rollbackEvent->update(['to_commit' => $lastSuccessful->commit]);

        // Queue rollback deployment
        $deployment_uuid = new \Visus\Cuid2\Cuid2;

        queue_application_deployment(
            application: $application,
            deployment_uuid: $deployment_uuid,
            commit: $lastSuccessful->commit,
            rollback: true,
            force_rebuild: false,
            no_questions_asked: true
        );

        $rollbackDeployment = ApplicationDeploymentQueue::where('deployment_uuid', $deployment_uuid)->first();

        if ($rollbackDeployment) {
            $rollbackEvent->markInProgress($rollbackDeployment->id);
        }

        $this->sendRollbackNotification($application, $rollbackEvent);
    }

    protected function sendRollbackNotification(Application $application, ApplicationRollbackEvent $event): void
    {
        $team = $application->environment?->project?->team;

        if ($team) {
            $team->notify(
                new \App\Notifications\Application\DeploymentFailed(
                    $application,
                    $this->deployment->deployment_uuid
                )
            );
        }

        Log::warning("Auto-rollback triggered for {$application->name}: {$event->getReasonLabel()}");
    }
}
