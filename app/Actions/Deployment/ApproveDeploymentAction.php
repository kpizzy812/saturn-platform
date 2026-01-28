<?php

namespace App\Actions\Deployment;

use App\Events\DeploymentApprovalResolved;
use App\Jobs\ApplicationDeploymentJob;
use App\Models\DeploymentApproval;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Action to approve or reject a deployment request.
 */
class ApproveDeploymentAction
{
    /**
     * Approve a deployment request and trigger the deployment.
     */
    public function approve(
        DeploymentApproval $approval,
        User $approver,
        ?string $comment = null
    ): bool {
        // Check authorization
        if (Gate::forUser($approver)->denies('approve', $approval)) {
            throw new \Exception('You do not have permission to approve this deployment.');
        }

        if (! $approval->isPending()) {
            throw new \Exception('This approval request is no longer pending.');
        }

        // Approve the request
        $approval->approve($approver, $comment);

        // Get the deployment and trigger it
        $deployment = $approval->deployment;
        if ($deployment && $deployment->status === 'queued') {
            // Update deployment status to indicate it's approved and ready
            $deployment->update(['status' => 'queued']);

            // Dispatch the deployment job
            dispatch(new ApplicationDeploymentJob($deployment->id));
        }

        // Notify the requester via WebSocket
        event(DeploymentApprovalResolved::fromApproval($approval));

        return true;
    }

    /**
     * Reject a deployment request.
     */
    public function reject(
        DeploymentApproval $approval,
        User $approver,
        ?string $reason = null
    ): bool {
        // Check authorization
        if (Gate::forUser($approver)->denies('reject', $approval)) {
            throw new \Exception('You do not have permission to reject this deployment.');
        }

        if (! $approval->isPending()) {
            throw new \Exception('This approval request is no longer pending.');
        }

        // Reject the request
        $approval->reject($approver, $reason);

        // Cancel the deployment
        $deployment = $approval->deployment;
        if ($deployment && $deployment->status === 'queued') {
            $deployment->update([
                'status' => 'cancelled',
                'logs' => $deployment->logs."\n[Deployment rejected by {$approver->email}".($reason ? ": {$reason}" : '').']',
            ]);
        }

        // Notify the requester via WebSocket
        event(DeploymentApprovalResolved::fromApproval($approval));

        return true;
    }
}
