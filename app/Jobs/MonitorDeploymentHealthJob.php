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
 *
 * Default monitoring window: 1800 seconds (30 minutes).
 * Rollback requires `rollback_consecutive_failures` (default: 2) consecutive
 * failed checks to avoid false positives from transient blips.
 */
class MonitorDeploymentHealthJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $queue = 'deployments';

    public int $tries = 1;

    public int $timeout = 90;

    public function __construct(
        public ApplicationDeploymentQueue $deployment,
        public int $checkIntervalSeconds = 30,
        public int $totalChecks = 60,
        public int $currentCheck = 0,
        public int $initialRestartCount = -1,
        public int $consecutiveFailures = 0,
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
            $windowMinutes = round(($this->totalChecks * $this->checkIntervalSeconds) / 60);
            Log::info("Starting health monitoring for deployment {$this->deployment->deployment_uuid} ({$this->totalChecks} checks over {$windowMinutes} minutes)");
        }

        // Refresh application to get latest status
        $application->refresh();

        // Capture metrics
        $metricsSnapshot = $this->captureMetrics($application);

        // Check if rollback conditions are met this cycle
        $failureReason = $this->checkForFailure($application);

        if ($failureReason !== null) {
            $requiredConsecutive = $application->settings->rollback_consecutive_failures ?? 2;
            $newConsecutiveCount = $this->consecutiveFailures + 1;

            if ($newConsecutiveCount >= $requiredConsecutive) {
                // Enough consecutive failures — trigger rollback
                $this->triggerAutoRollback($application, $failureReason, $metricsSnapshot);

                return;
            }

            Log::warning("Health check failure #{$newConsecutiveCount}/{$requiredConsecutive} for {$application->name}: {$failureReason} (waiting for consecutive threshold)");

            // Schedule next check with incremented consecutive counter
            $this->scheduleNextCheck($newConsecutiveCount);

            return;
        }

        // No failure — reset consecutive counter and schedule next check
        if ($this->consecutiveFailures > 0) {
            Log::info("Health check recovered for {$application->name} after {$this->consecutiveFailures} failures");
        }

        $this->currentCheck++;

        if ($this->currentCheck < $this->totalChecks) {
            $this->scheduleNextCheck(0);
        } else {
            $windowMinutes = round(($this->totalChecks * $this->checkIntervalSeconds) / 60);
            Log::info("Health monitoring complete for {$this->deployment->deployment_uuid} — all {$this->totalChecks} checks passed over {$windowMinutes} minutes");
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('MonitorDeploymentHealthJob permanently failed', [
            'deployment_uuid' => $this->deployment->deployment_uuid,
            'application_id' => $this->deployment->application_id,
            'error' => $exception->getMessage(),
        ]);
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
            'consecutive_failures' => $this->consecutiveFailures,
            'captured_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Check if any rollback condition is met this cycle.
     * Returns a reason string on failure, null if everything is healthy.
     */
    protected function checkForFailure(Application $application): ?string
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

        // Check 3: Docker health check failures
        if ($settings->rollback_on_health_check_fail && $application->health_check_enabled) {
            $healthStatus = str($status)->after(':')->value();

            if ($healthStatus === 'unhealthy') {
                Log::warning("Docker health check failed for {$application->name}");

                return ApplicationRollbackEvent::REASON_HEALTH_CHECK_FAILED;
            }
        }

        // Check 4: Error rate via docker logs (requires curl on server, optional)
        if ($settings->rollback_on_error_rate ?? false) {
            $errorRateReason = $this->checkErrorRateFromLogs($application);
            if ($errorRateReason !== null) {
                return $errorRateReason;
            }
        }

        return null;
    }

    /**
     * Check error rate by scanning recent container logs for HTTP 5xx patterns.
     *
     * Runs `docker logs --since=Xs` on the deployment server via SSH and counts
     * lines matching common error patterns. Returns null if check cannot be
     * performed (server unreachable, no container, etc.) to fail open.
     */
    protected function checkErrorRateFromLogs(Application $application): ?string
    {
        try {
            $server = $application->destination?->server;
            if (! $server || ! $server->isFunctional()) {
                return null;
            }

            $containerName = $application->uuid;
            $threshold = $application->settings->rollback_error_rate_threshold ?? 10;
            $since = $this->checkIntervalSeconds;

            // Count lines matching HTTP 5xx pattern or common error keywords in logs
            $output = instant_remote_process(
                ["docker logs --since={$since}s --no-log-prefix {$containerName} 2>&1 | grep -cEi 'HTTP/1\\.[01][[:space:]]+5[0-9]{2}|\" 5[0-9]{2} | 5[0-9]{2} [0-9]+ \"-\"' 2>/dev/null || echo 0"],
                $server,
                throwError: false
            );

            $errorCount = (int) trim($output ?? '0');

            if ($errorCount >= $threshold) {
                Log::warning("Error rate threshold exceeded for {$application->name}: {$errorCount} HTTP 5xx errors in last {$since}s (threshold: {$threshold})");

                return ApplicationRollbackEvent::REASON_ERROR_RATE;
            }
        } catch (\Throwable $e) {
            // Fail open: log but do NOT trigger rollback on monitoring errors
            Log::debug("Could not check error rate for {$application->name}: {$e->getMessage()}");
        }

        return null;
    }

    protected function triggerAutoRollback(Application $application, string $reason, array $metricsSnapshot): void
    {
        Log::warning("Triggering auto-rollback for {$application->name} due to: {$reason} (after {$this->consecutiveFailures} consecutive failures)");

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

    private function scheduleNextCheck(int $consecutiveFailures): void
    {
        self::dispatch(
            deployment: $this->deployment,
            checkIntervalSeconds: $this->checkIntervalSeconds,
            totalChecks: $this->totalChecks,
            currentCheck: $this->currentCheck,
            initialRestartCount: $this->initialRestartCount,
            consecutiveFailures: $consecutiveFailures,
        )->delay(now()->addSeconds($this->checkIntervalSeconds));
    }
}
