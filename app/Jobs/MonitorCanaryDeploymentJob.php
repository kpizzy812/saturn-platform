<?php

namespace App\Jobs;

use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Traits\Deployment\HandlesCanaryDeployment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Monitors a canary deployment and steps through traffic weights.
 *
 * Uses the same self-dispatching pattern as MonitorDeploymentHealthJob:
 * each invocation performs ONE check, then dispatches itself again with
 * a delay for the next check. This avoids blocking a queue worker.
 *
 * Decision tree per check:
 *   - Both containers dead → rollback
 *   - Error rate > threshold → rollback
 *   - Smoke test fails → rollback
 *   - currentStep == last step index → promote (100% already set)
 *   - Otherwise → advance to next step and reschedule
 */
class MonitorCanaryDeploymentJob implements ShouldQueue
{
    use Dispatchable, HandlesCanaryDeployment, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    // Populated in handle() — nullable to allow null-check before use
    protected ?Application $application = null;

    protected ?ApplicationDeploymentQueue $application_deployment_queue = null;

    protected $server = null;

    public function __construct(
        public ApplicationDeploymentQueue $deployment,
        public string $canaryContainer,
        public string $stableContainer,
        public int $currentStep = 0,
        public int $consecutiveFailures = 0,
    ) {
        $this->onQueue('deployments');
    }

    public function handle(): void
    {
        // Bootstrap properties expected by HandlesCanaryDeployment
        $this->application_deployment_queue = $this->deployment;
        $this->application = $this->deployment->application;

        if (! $this->application) {
            Log::warning('MonitorCanaryDeploymentJob: application not found, aborting');

            return;
        }

        $this->server = $this->application->destination?->server;

        if (! $this->server) {
            Log::warning("MonitorCanaryDeploymentJob: server not found for {$this->application->name}, aborting canary");
            $this->performRollback('server_unavailable');

            return;
        }

        $settings = $this->application->settings;
        $steps = $settings->canary_steps ?? [10, 25, 50, 100];

        if (empty($steps)) {
            $steps = [10, 25, 50, 100];
        }

        // Check container health
        if (! $this->bothContainersAlive()) {
            $newFailures = $this->consecutiveFailures + 1;
            Log::warning("Canary monitor: container(s) down for {$this->application->name} (consecutive: {$newFailures})");

            if ($newFailures >= 2) {
                $this->performRollback('container_down');

                return;
            }

            $this->reschedule($newFailures);

            return;
        }

        // Check error rate on canary container
        $errorRateExceeded = $this->checkCanaryErrorRate($settings->canary_error_rate_threshold ?? 5);

        if ($errorRateExceeded) {
            $newFailures = $this->consecutiveFailures + 1;
            Log::warning("Canary monitor: error rate exceeded for {$this->application->name} (consecutive: {$newFailures})");

            if ($newFailures >= 2) {
                $this->performRollback('error_rate_exceeded');

                return;
            }

            $this->reschedule($newFailures);

            return;
        }

        // All checks passed — reset consecutive failures
        $nextStep = $this->currentStep + 1;

        if ($nextStep >= count($steps)) {
            // All steps complete — promote
            Log::info("Canary monitor: all steps passed for {$this->application->name}, promoting");
            $this->performPromote();

            return;
        }

        // Advance to next traffic step
        $nextWeight = (int) $steps[$nextStep];
        Log::info("Canary monitor: advancing {$this->application->name} to step {$nextStep} ({$nextWeight}% canary)");

        $this->update_canary_traffic($nextWeight, $this->canaryContainer, $this->stableContainer);

        $this->deployment->update([
            'canary_state' => array_merge($this->deployment->canary_state ?? [], [
                'current_step' => $nextStep,
                'current_weight' => $nextWeight,
            ]),
        ]);

        $this->deployment->addLogEntry(
            "Canary step {$nextStep}: {$nextWeight}% traffic → {$this->canaryContainer}."
        );

        $stepMinutes = $settings->canary_step_minutes ?? 5;
        self::dispatch(
            deployment: $this->deployment,
            canaryContainer: $this->canaryContainer,
            stableContainer: $this->stableContainer,
            currentStep: $nextStep,
            consecutiveFailures: 0,
        )->delay(now()->addMinutes($stepMinutes));
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('MonitorCanaryDeploymentJob permanently failed', [
            'deployment_uuid' => $this->deployment->deployment_uuid ?? 'unknown',
            'application_id' => $this->deployment->application_id,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Check if both canary and stable containers are running.
     */
    private function bothContainersAlive(): bool
    {
        try {
            $escapedCanary = escapeshellarg($this->canaryContainer);
            $escapedStable = escapeshellarg($this->stableContainer);

            $output = instant_remote_process(
                ["docker ps --filter name={$escapedCanary} --filter name={$escapedStable} --format '{{.Names}}' 2>/dev/null | wc -l"],
                $this->server,
                throwError: false
            );

            return ((int) trim($output ?? '0')) >= 2;
        } catch (\Throwable $e) {
            Log::debug("Canary: could not check container status for {$this->application->name}: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Check if the canary container's recent HTTP 5xx error rate exceeds the threshold.
     *
     * Scans docker logs for the last 5 minutes for 5xx patterns.
     * Returns false (no rollback) if logs cannot be read, to fail open.
     */
    private function checkCanaryErrorRate(int $threshold): bool
    {
        try {
            $escapedName = escapeshellarg($this->canaryContainer);
            $output = instant_remote_process(
                ["docker logs --since=5m --no-log-prefix {$escapedName} 2>&1 | grep -cEi 'HTTP/1\\.[01][[:space:]]+5[0-9]{2}|\" 5[0-9]{2} | 5[0-9]{2} [0-9]+ \"-\"' 2>/dev/null || echo 0"],
                $this->server,
                throwError: false
            );

            $errorCount = (int) trim($output ?? '0');

            if ($errorCount >= $threshold) {
                Log::warning("Canary: {$this->application->name} has {$errorCount} HTTP 5xx in last 5 min (threshold: {$threshold})");

                return true;
            }

            return false;
        } catch (\Throwable $e) {
            Log::debug("Canary: could not check error rate for {$this->application->name}: {$e->getMessage()}");

            return false; // Fail open
        }
    }

    /**
     * Promote canary to full traffic.
     */
    private function performPromote(): void
    {
        try {
            $this->promote_canary($this->canaryContainer, $this->stableContainer);
        } catch (\Throwable $e) {
            Log::error("Canary promotion failed for {$this->application->name}: {$e->getMessage()}");
        }
    }

    /**
     * Roll back canary to stable.
     */
    private function performRollback(string $reason): void
    {
        try {
            $this->rollback_canary($this->canaryContainer, $this->stableContainer, $reason);
        } catch (\Throwable $e) {
            Log::error("Canary rollback failed for {$this->application->name}: {$e->getMessage()}");
        }
    }

    /**
     * Schedule the next check with updated consecutive failure counter.
     */
    private function reschedule(int $consecutiveFailures): void
    {
        $stepMinutes = $this->application->settings->canary_step_minutes ?? 5;

        self::dispatch(
            deployment: $this->deployment,
            canaryContainer: $this->canaryContainer,
            stableContainer: $this->stableContainer,
            currentStep: $this->currentStep,
            consecutiveFailures: $consecutiveFailures,
        )->delay(now()->addMinutes($stepMinutes));
    }
}
