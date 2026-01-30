<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
            'status' => 'queued', // Change status from waiting_approval to queued
        ]);

        // TODO: Dispatch deployment job here
        // queue_application_deployment($deployment->application, $deployment->deployment_uuid);

        return response()->json([
            'message' => 'Deployment approved successfully.',
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

        return response()->json([
            'message' => 'Deployment rejected successfully.',
            'deployment' => $deployment,
        ]);
    }
}
