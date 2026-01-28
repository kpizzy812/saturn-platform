<?php

namespace App\Actions\Deployment;

use App\Events\DeploymentApprovalRequested;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\DeploymentApproval;
use App\Models\User;

/**
 * Action to request approval for a deployment to a protected environment.
 */
class RequestDeploymentApprovalAction
{
    /**
     * Create an approval request for a deployment.
     */
    public function handle(
        ApplicationDeploymentQueue $deployment,
        User $requestedBy
    ): DeploymentApproval {
        // Check if an approval already exists for this deployment
        $existingApproval = DeploymentApproval::where('application_deployment_queue_id', $deployment->id)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existingApproval) {
            return $existingApproval;
        }

        // Create new approval request
        $approval = DeploymentApproval::create([
            'application_deployment_queue_id' => $deployment->id,
            'requested_by' => $requestedBy->id,
            'status' => 'pending',
        ]);

        // Dispatch event to notify approvers via WebSocket
        event(DeploymentApprovalRequested::fromApproval($approval));

        return $approval;
    }

    /**
     * Check if a deployment requires approval.
     */
    public function requiresApproval(ApplicationDeploymentQueue $deployment, User $user): bool
    {
        $application = Application::find($deployment->application_id);
        if (! $application) {
            return false;
        }

        $environment = $application->environment;
        if (! $environment) {
            return false;
        }

        return $user->requiresApprovalForEnvironment($environment);
    }
}
