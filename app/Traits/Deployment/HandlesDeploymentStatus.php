<?php

namespace App\Traits\Deployment;

use App\Enums\ApplicationDeploymentStatus;
use App\Events\ApplicationConfigurationChanged;
use App\Exceptions\DeploymentException;
use App\Notifications\Application\DeploymentFailed;
use App\Notifications\Application\DeploymentSuccess;

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
        // Reset restart count after successful deployment
        // This is done here (not in Livewire) to avoid race conditions
        // with GetContainersStatus reading old container restart counts
        $this->application->update([
            'restart_count' => 0,
            'last_restart_at' => null,
            'last_restart_type' => null,
        ]);

        event(new ApplicationConfigurationChanged($this->application->team()->id));

        if (! $this->only_this_server) {
            $this->deploy_to_additional_destinations();
        }

        $this->sendDeploymentNotification(DeploymentSuccess::class);
    }

    /**
     * Handle side effects when deployment fails.
     */
    private function handleFailedDeployment(): void
    {
        $this->sendDeploymentNotification(DeploymentFailed::class);

        // AI analysis is triggered manually from UI via AIAnalysisCard component
        // No automatic dispatch - user clicks "Analyze" button when needed
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
