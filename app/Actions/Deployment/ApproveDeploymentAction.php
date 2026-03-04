<?php

namespace App\Actions\Deployment;

use App\Events\DeploymentApprovalResolved;
use App\Jobs\ApplicationDeploymentJob;
use App\Models\DeploymentApproval;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

        // BUG #2 FIX: Wrap deployment status update in a transaction with pessimistic
        // locking to prevent race condition when two approvers click simultaneously.
        // Both would pass isPending() above, but only one will win the lock here.
        DB::transaction(function () use ($approval) {
            // Re-fetch deployment inside transaction with exclusive row lock
            $deployment = $approval->deployment()->lockForUpdate()->first();

            if (! $deployment || $deployment->status !== 'pending_approval') {
                // Already approved or dispatched by a concurrent request — skip
                return;
            }

            // BUG #11 FIX: Respect server concurrent_builds limit.
            // next_queuable() checks both per-application in-progress lock and the
            // server-level concurrent build cap, matching the pattern used in
            // queue_application_deployment() in bootstrap/helpers/applications.php.
            if (next_queuable(
                server_id: $deployment->server_id,
                application_id: $deployment->application_id,
                commit: $deployment->commit ?? 'HEAD',
                pull_request_id: $deployment->pull_request_id ?? 0,
            )) {
                // Server has capacity — mark in_progress and dispatch immediately
                $deployment->update(['status' => 'in_progress']);
                ApplicationDeploymentJob::dispatch(
                    application_deployment_queue_id: $deployment->id,
                );
            } else {
                // Server is at concurrent build limit — leave as queued so
                // queue_next_deployment() will pick it up when a slot opens
                $deployment->update(['status' => 'queued']);
            }
        });

        // Invalidate cached pending approvals count for the approver
        Cache::forget("pending_approvals_count_{$approver->id}");

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
        if ($deployment && $deployment->status === 'pending_approval') {
            $deployment->update([
                'status' => 'cancelled',
                'logs' => $deployment->logs."\n[Deployment rejected by {$approver->email}".($reason ? ": {$reason}" : '').']',
            ]);
        }

        // Invalidate cached pending approvals count for the approver
        Cache::forget("pending_approvals_count_{$approver->id}");

        // Notify the requester via WebSocket
        event(DeploymentApprovalResolved::fromApproval($approval));

        return true;
    }
}
