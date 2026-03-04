<?php

namespace App\Traits\Deployment;

use App\Enums\ApplicationDeploymentStatus;
use App\Models\ApplicationDeploymentQueue;
use Illuminate\Support\Facades\Log;
use Visus\Cuid2\Cuid2;

/**
 * Trait for handling automatic rollback logic during deployment failures.
 *
 * Required properties from parent class:
 * - $application, $application_deployment_queue, $server, $destination
 * - $pull_request_id, $rollback
 *
 * Required methods from parent class:
 * - queue_application_deployment()
 */
trait HandlesAutoRollback
{
    /**
     * Attempt to automatically rollback to the last successful deployment
     * when the current deployment fails.
     *
     * Skipped if:
     * - auto_rollback_on_failure setting is disabled
     * - This is already a rollback deployment (to prevent infinite loops)
     * - This is a pull request deployment
     * - No previous successful deployment exists
     */
    private function attemptAutoRollback(\Exception $e): void
    {
        // Skip if auto-rollback on build failure is disabled
        if (! data_get($this->application, 'settings.auto_rollback_enabled', true)) {
            return;
        }

        // Skip rollback deployments to prevent infinite rollback loops
        if ($this->rollback) {
            return;
        }

        // Skip pull request deployments — they have no stable "previous" version
        if ($this->pull_request_id !== 0) {
            return;
        }

        // Find last successful deployment for this application (excluding current)
        $lastSuccessful = ApplicationDeploymentQueue::where('application_id', $this->application->id)
            ->where('status', ApplicationDeploymentStatus::FINISHED->value)
            ->where('pull_request_id', 0)
            ->where('id', '!=', $this->application_deployment_queue->id)
            ->orderBy('id', 'desc')
            ->first();

        if (! $lastSuccessful || ! $lastSuccessful->commit) {
            $this->application_deployment_queue->addLogEntry(
                'Auto-rollback skipped: no previous successful deployment found.',
                'stderr'
            );

            return;
        }

        $this->application_deployment_queue->addLogEntry(
            "Build failed: {$e->getMessage()}. Initiating auto-rollback to commit: {$lastSuccessful->commit}",
            'stderr'
        );

        try {
            queue_application_deployment(
                application: $this->application,
                deployment_uuid: new Cuid2,
                server: $this->server,
                destination: $this->destination,
                commit: $lastSuccessful->commit,
                rollback: true,
                no_questions_asked: true,
            );

            $this->application_deployment_queue->addLogEntry(
                "Auto-rollback queued successfully to commit: {$lastSuccessful->commit}",
                'stderr'
            );
        } catch (\Throwable $rollbackError) {
            Log::error('Auto-rollback failed for application '.$this->application->uuid.': '.$rollbackError->getMessage());

            $this->application_deployment_queue->addLogEntry(
                'Auto-rollback failed to queue: '.$rollbackError->getMessage(),
                'stderr'
            );
        }
    }
}
