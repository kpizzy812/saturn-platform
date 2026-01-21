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

class MonitorDeploymentHealthJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    protected string $rollbackReason = '';

    protected array $metricsSnapshot = [];

    public function __construct(
        public ApplicationDeploymentQueue $deployment,
        public int $checkIntervalSeconds = 30,
        public int $totalChecks = 10
    ) {}

    public function handle(): void
    {
        $application = $this->deployment->application;

        // Skip if auto-rollback is not enabled
        if (! $application->settings->auto_rollback_enabled) {
            return;
        }

        // Skip PR deployments
        if ($this->deployment->pull_request_id > 0) {
            return;
        }

        // Skip if deployment failed (nothing to monitor)
        if ($this->deployment->status !== 'finished') {
            return;
        }

        Log::info("Starting health monitoring for deployment {$this->deployment->deployment_uuid}");

        $startedAt = now();
        $validationSeconds = $application->settings->rollback_validation_seconds ?? 300;
        $initialRestartCount = $application->restart_count ?? 0;

        // Perform health checks over the validation period
        for ($i = 0; $i < $this->totalChecks; $i++) {
            sleep($this->checkIntervalSeconds);

            // Refresh application status
            $application->refresh();

            // Capture metrics
            $this->captureMetrics($application, $initialRestartCount);

            // Check if rollback is needed
            if ($this->shouldRollback($application, $initialRestartCount)) {
                $this->triggerAutoRollback($application);

                return;
            }

            // Check if validation period is complete
            if (now()->diffInSeconds($startedAt) >= $validationSeconds) {
                Log::info("Health monitoring complete for {$this->deployment->deployment_uuid} - all checks passed");

                // Update last successful deployment
                $application->update(['last_successful_deployment_id' => $this->deployment->id]);

                return;
            }
        }

        Log::info("Health monitoring complete for {$this->deployment->deployment_uuid}");
    }

    protected function captureMetrics(Application $application, int $initialRestartCount): void
    {
        $this->metricsSnapshot = [
            'status' => $application->status,
            'restart_count' => $application->restart_count ?? 0,
            'initial_restart_count' => $initialRestartCount,
            'restart_delta' => ($application->restart_count ?? 0) - $initialRestartCount,
            'last_restart_at' => $application->last_restart_at?->toIso8601String(),
            'captured_at' => now()->toIso8601String(),
        ];
    }

    protected function shouldRollback(Application $application, int $initialRestartCount): bool
    {
        $settings = $application->settings;

        // Check 1: Crash loop detection
        if ($settings->rollback_on_crash_loop) {
            $maxRestarts = $settings->rollback_max_restarts ?? 3;
            $restartDelta = ($application->restart_count ?? 0) - $initialRestartCount;

            if ($restartDelta >= $maxRestarts) {
                $this->rollbackReason = ApplicationRollbackEvent::REASON_CRASH_LOOP;
                Log::warning("Crash loop detected for {$application->name}: {$restartDelta} restarts since deployment");

                return true;
            }
        }

        // Check 2: Container exited/degraded
        $status = $application->status ?? '';
        if (str_starts_with($status, 'exited') || str_starts_with($status, 'degraded')) {
            $this->rollbackReason = ApplicationRollbackEvent::REASON_CONTAINER_EXITED;
            Log::warning("Container unhealthy for {$application->name}: status={$status}");

            return true;
        }

        // Check 3: Health check failures
        if ($settings->rollback_on_health_check_fail && $application->health_check_enabled) {
            $healthStatus = str($status)->after(':')->value();

            if ($healthStatus === 'unhealthy') {
                $this->rollbackReason = ApplicationRollbackEvent::REASON_HEALTH_CHECK_FAILED;
                Log::warning("Health check failed for {$application->name}");

                return true;
            }
        }

        return false;
    }

    protected function triggerAutoRollback(Application $application): void
    {
        Log::warning("Triggering auto-rollback for {$application->name} due to: {$this->rollbackReason}");

        // Find last successful deployment
        $lastSuccessful = $application->lastSuccessfulDeployment;

        if (! $lastSuccessful) {
            // Try to find from history
            $lastSuccessful = ApplicationDeploymentQueue::where('application_id', $application->id)
                ->where('status', 'finished')
                ->where('pull_request_id', 0)
                ->where('id', '<', $this->deployment->id)
                ->orderBy('created_at', 'desc')
                ->first();
        }

        if (! $lastSuccessful) {
            Log::warning("No previous successful deployment found for rollback");

            // Create event but mark as skipped
            ApplicationRollbackEvent::create([
                'application_id' => $application->id,
                'failed_deployment_id' => $this->deployment->id,
                'trigger_reason' => $this->rollbackReason,
                'trigger_type' => 'automatic',
                'metrics_snapshot' => $this->metricsSnapshot,
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
            reason: $this->rollbackReason,
            type: 'automatic',
            failedDeployment: $this->deployment,
            metrics: $this->metricsSnapshot
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

        // Find the queued deployment and update event
        $rollbackDeployment = ApplicationDeploymentQueue::where('deployment_uuid', $deployment_uuid)->first();

        if ($rollbackDeployment) {
            $rollbackEvent->markInProgress($rollbackDeployment->id);
        }

        // Send notification
        $this->sendRollbackNotification($application, $rollbackEvent);
    }

    protected function sendRollbackNotification(Application $application, ApplicationRollbackEvent $event): void
    {
        // Dispatch notification to team
        $team = $application->environment?->project?->team;

        if ($team) {
            // Use existing notification system
            $application->environment->project->team->notify(
                new \App\Notifications\Application\DeploymentFailed(
                    $application,
                    "Auto-rollback triggered: {$event->getReasonLabel()}"
                )
            );
        }
    }
}
