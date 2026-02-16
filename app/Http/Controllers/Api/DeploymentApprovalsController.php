<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ApplicationDeploymentJob;
use App\Models\ApplicationDeploymentQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeploymentApprovalsController extends Controller
{
    /**
     * Get all pending deployment approvals for the current team
     */
    public function index(Request $request): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $deployments = ApplicationDeploymentQueue::with(['application.environment.project'])
            ->where('requires_approval', true)
            ->where('approval_status', 'pending')
            ->whereHas('application.environment.project', function ($query) use ($teamId) {
                $query->where('team_id', $teamId);
            })
            ->latest()
            ->paginate(50);

        return response()->json($deployments);
    }

    /**
     * Approve a deployment
     */
    public function approve(Request $request, string $deploymentUuid): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $deployment = ApplicationDeploymentQueue::with(['application.environment.project'])
            ->where('deployment_uuid', $deploymentUuid)
            ->whereHas('application.environment.project', function ($query) use ($teamId) {
                $query->where('team_id', $teamId);
            })
            ->firstOrFail();

        if (! $deployment->requires_approval || $deployment->approval_status !== 'pending') {
            return response()->json(['message' => 'Deployment does not require approval or has already been processed.'], 400);
        }

        $deployment->update([
            'approval_status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'approval_note' => $request->input('note'),
            'status' => 'queued', // Change status to queued so job can pick it up
        ]);

        // Send notification to user who created deployment
        try {
            if ($deployment->user_id) {
                $user = \App\Models\User::find($deployment->user_id);
                $approver = \App\Models\User::find(auth()->id());
                if ($user && $approver && $deployment->application) {
                    $user->notify(new \App\Notifications\Application\DeploymentApproved(
                        $deployment->application,
                        $deployment,
                        $approver
                    ));
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Failed to send deployment approved notification: '.$e->getMessage());
        }

        // Dispatch deployment job to process the approved deployment
        ApplicationDeploymentJob::dispatch($deployment->id);

        return response()->json([
            'message' => 'Deployment approved successfully and queued for processing.',
            'deployment' => $deployment,
        ]);
    }

    /**
     * Reject a deployment
     */
    public function reject(Request $request, string $deploymentUuid): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $deployment = ApplicationDeploymentQueue::with(['application.environment.project'])
            ->where('deployment_uuid', $deploymentUuid)
            ->whereHas('application.environment.project', function ($query) use ($teamId) {
                $query->where('team_id', $teamId);
            })
            ->firstOrFail();

        if (! $deployment->requires_approval || $deployment->approval_status !== 'pending') {
            return response()->json(['message' => 'Deployment does not require approval or has already been processed.'], 400);
        }

        $deployment->update([
            'approval_status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'approval_note' => $request->input('note', 'Deployment rejected'),
            'status' => 'cancelled',
        ]);

        // Send notification to user who created deployment
        try {
            if ($deployment->user_id) {
                $user = \App\Models\User::find($deployment->user_id);
                $rejecter = \App\Models\User::find(auth()->id());
                if ($user && $rejecter && $deployment->application) {
                    $user->notify(new \App\Notifications\Application\DeploymentRejected(
                        $deployment->application,
                        $deployment,
                        $rejecter
                    ));
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Failed to send deployment rejected notification: '.$e->getMessage());
        }

        return response()->json([
            'message' => 'Deployment rejected successfully.',
            'deployment' => $deployment,
        ]);
    }
}
