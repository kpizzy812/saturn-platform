<?php

namespace App\Traits\Deployment;

use App\Enums\ApplicationDeploymentStatus;
use App\Events\ApplicationConfigurationChanged;
use App\Exceptions\DeploymentException;
use App\Jobs\MonitorDeploymentHealthJob;
use App\Models\ApplicationRollbackEvent;
use App\Notifications\Application\DeploymentFailed;
use App\Notifications\Application\DeploymentSuccess;
use App\Services\MasterProxyConfigService;
use Illuminate\Support\Facades\Log;

/**
 * Trait for deployment status management operations.
 *
 * Required properties from parent class:
 * - $application, $application_deployment_queue, $deployment_uuid
 * - $only_this_server, $preview
 *
 * Required methods from parent class:
 * - deploy_to_additional_destinations()
 */
trait HandlesDeploymentStatus
{
    /**
     * Transition deployment to a new status with proper validation and side effects.
     * This is the single source of truth for status transitions.
     */
    private function transitionToStatus(ApplicationDeploymentStatus $status): void
    {
        if ($this->isInTerminalState()) {
            return;
        }

        $this->updateDeploymentStatus($status);
        $this->handleStatusTransition($status);
        queue_next_deployment($this->application);
    }

    /**
     * Check if deployment is in a terminal state (FINISHED, FAILED or CANCELLED).
     * Terminal states cannot be changed.
     */
    private function isInTerminalState(): bool
    {
        $this->application_deployment_queue->refresh();

        if ($this->application_deployment_queue->status === ApplicationDeploymentStatus::FINISHED->value) {
            return true;
        }

        if ($this->application_deployment_queue->status === ApplicationDeploymentStatus::FAILED->value) {
            return true;
        }

        if ($this->application_deployment_queue->status === ApplicationDeploymentStatus::CANCELLED_BY_USER->value) {
            $this->application_deployment_queue->addLogEntry('Deployment cancelled by user, stopping execution.');
            throw new DeploymentException('Deployment cancelled by user', 69420);
        }

        return false;
    }

    /**
     * Update the deployment status in the database.
     */
    private function updateDeploymentStatus(ApplicationDeploymentStatus $status): void
    {
        $this->application_deployment_queue->update([
            'status' => $status->value,
        ]);
    }

    /**
     * Execute status-specific side effects (events, notifications, additional deployments).
     */
    private function handleStatusTransition(ApplicationDeploymentStatus $status): void
    {
        match ($status) {
            ApplicationDeploymentStatus::FINISHED => $this->handleSuccessfulDeployment(),
            ApplicationDeploymentStatus::FAILED => $this->handleFailedDeployment(),
            default => null,
        };
    }

    /**
     * Handle side effects when deployment succeeds.
     */
    private function handleSuccessfulDeployment(): void
    {
        $isRollback = (bool) ($this->application_deployment_queue->rollback ?? false);

        // Reset restart count and track last successful deployment
        $this->application->update([
            'restart_count' => 0,
            'last_restart_at' => null,
            'last_restart_type' => null,
            'last_successful_deployment_id' => $this->application_deployment_queue->id,
        ]);

        // Update rollback event status if this is a rollback deployment
        if ($isRollback) {
            ApplicationRollbackEvent::where('rollback_deployment_id', $this->application_deployment_queue->id)
                ->whereIn('status', [ApplicationRollbackEvent::STATUS_TRIGGERED, ApplicationRollbackEvent::STATUS_IN_PROGRESS])
                ->first()
                ?->markSuccess();
        }

        event(new ApplicationConfigurationChanged($this->application->team()->id));

        if (! $this->only_this_server) {
            $this->deploy_to_additional_destinations();
        }

        // Sync master proxy route for remote server apps
        try {
            $appServer = $this->application->destination?->server;
            if ($appServer && ! $appServer->isMasterServer()) {
                app(MasterProxyConfigService::class)->syncRemoteRoute($this->application);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to sync master proxy route', ['error' => $e->getMessage()]);
        }

        $this->sendDeploymentNotification(DeploymentSuccess::class);

        // Start health monitoring for auto-rollback (skip PR and rollback deployments)
        if ($this->application->settings?->auto_rollback_enabled
            && ($this->application_deployment_queue->pull_request_id ?? 0) === 0
            && ! $isRollback
        ) {
            $validationSeconds = $this->application->settings->rollback_validation_seconds ?? 300;
            $checkInterval = 30;
            $totalChecks = (int) ceil($validationSeconds / $checkInterval);

            MonitorDeploymentHealthJob::dispatch(
                deployment: $this->application_deployment_queue,
                checkIntervalSeconds: $checkInterval,
                totalChecks: $totalChecks
            )->delay(now()->addSeconds(30));
        }
    }

    /**
     * Handle side effects when deployment fails.
     */
    private function handleFailedDeployment(): void
    {
        $this->sendDeploymentNotification(DeploymentFailed::class);

        // Update rollback event status if this is a rollback deployment
        if ($this->application_deployment_queue->rollback ?? false) {
            ApplicationRollbackEvent::where('rollback_deployment_id', $this->application_deployment_queue->id)
                ->whereIn('status', [ApplicationRollbackEvent::STATUS_TRIGGERED, ApplicationRollbackEvent::STATUS_IN_PROGRESS])
                ->first()
                ?->markFailed('Rollback deployment failed');
        }
    }

    /**
     * Send deployment status notification to the team.
     */
    private function sendDeploymentNotification(string $notificationClass): void
    {
        $this->application->environment?->project?->team?->notify(
            new $notificationClass($this->application, $this->deployment_uuid, $this->preview)
        );
    }

    /**
     * Complete deployment successfully.
     * Sends success notification and triggers additional deployments if needed.
     */
    private function completeDeployment(): void
    {
        $this->transitionToStatus(ApplicationDeploymentStatus::FINISHED);
    }

    /**
     * Fail the deployment.
     * Sends failure notification and queues next deployment.
     */
    protected function failDeployment(): void
    {
        $this->transitionToStatus(ApplicationDeploymentStatus::FAILED);
    }
}
