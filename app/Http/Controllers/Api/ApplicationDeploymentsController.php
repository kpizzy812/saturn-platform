<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\ApplicationRollbackEvent;
use Illuminate\Http\Request;
use Visus\Cuid2\Cuid2;

class ApplicationDeploymentsController extends Controller
{
    /**
     * Get deployments for an application
     */
    public function get_deployments(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $uuid = $request->route('uuid');
        $application = Application::ownedByCurrentTeamAPI($teamId)->where('uuid', $uuid)->first();

        if (is_null($application)) {
            return response()->json(['message' => 'Application not found'], 404);
        }

        // Check authorization
        $this->authorize('view', $application);

        $take = $request->get('take', 20);
        $skip = $request->get('skip', 0);

        $deployments = ApplicationDeploymentQueue::where('application_id', $application->id)
            ->where('pull_request_id', 0)
            ->orderBy('created_at', 'desc')
            ->skip($skip)
            ->take($take)
            ->get()
            ->map(function ($deployment) {
                return serializeApiResponse($deployment);
            });

        return response()->json($deployments);
    }

    /**
     * Get rollback events for an application
     */
    public function get_rollback_events(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $uuid = $request->route('uuid');
        $application = Application::ownedByCurrentTeamAPI($teamId)->where('uuid', $uuid)->first();

        if (is_null($application)) {
            return response()->json(['message' => 'Application not found'], 404);
        }

        // Check authorization
        $this->authorize('view', $application);

        $take = $request->get('take', 10);

        $rollbackEvents = ApplicationRollbackEvent::where('application_id', $application->id)
            ->with(['failedDeployment', 'rollbackDeployment', 'triggeredByUser'])
            ->orderBy('created_at', 'desc')
            ->take($take)
            ->get()
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'application_id' => $event->application_id,
                    'failed_deployment_id' => $event->failed_deployment_id,
                    'rollback_deployment_id' => $event->rollback_deployment_id,
                    'triggered_by_user_id' => $event->triggered_by_user_id,
                    'trigger_reason' => $event->trigger_reason,
                    'trigger_type' => $event->trigger_type,
                    'status' => $event->status,
                    'from_commit' => $event->from_commit,
                    'to_commit' => $event->to_commit,
                    'triggered_at' => $event->triggered_at?->toISOString(),
                    'completed_at' => $event->completed_at?->toISOString(),
                    'triggered_by_user' => $event->triggeredByUser ? [
                        'id' => $event->triggeredByUser->id,
                        'name' => $event->triggeredByUser->name,
                        'email' => $event->triggeredByUser->email,
                    ] : null,
                ];
            });

        return response()->json($rollbackEvents);
    }

    /**
     * Execute rollback to a specific deployment
     */
    public function execute_rollback(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $uuid = $request->route('uuid');
        $deploymentUuid = $request->route('deploymentUuid');

        $application = Application::ownedByCurrentTeamAPI($teamId)->where('uuid', $uuid)->first();

        if (is_null($application)) {
            return response()->json(['message' => 'Application not found'], 404);
        }

        // Check authorization
        $this->authorize('deploy', $application);

        // Find the deployment to rollback to
        $targetDeployment = ApplicationDeploymentQueue::where('deployment_uuid', $deploymentUuid)
            ->where('application_id', $application->id)
            ->first();

        if (! $targetDeployment) {
            return response()->json(['message' => 'Deployment not found'], 404);
        }

        if ($targetDeployment->status !== 'finished') {
            return response()->json([
                'message' => 'Can only rollback to successful deployments',
            ], 400);
        }

        // Create rollback event
        $event = ApplicationRollbackEvent::createEvent(
            application: $application,
            reason: ApplicationRollbackEvent::REASON_MANUAL,
            type: 'manual',
            failedDeployment: $application->deployments()->latest()->first(),
            user: auth()->user()
        );

        $event->update(['to_commit' => $targetDeployment->commit]);

        // Queue rollback deployment
        $rollback_deployment_uuid = new Cuid2;

        $result = queue_application_deployment(
            application: $application,
            deployment_uuid: $rollback_deployment_uuid,
            commit: $targetDeployment->commit,
            rollback: true,
            force_rebuild: false,
            is_api: true
        );

        if ($result['status'] === 'queue_full') {
            return response()->json([
                'message' => $result['message'],
            ], 429)->header('Retry-After', '60');
        }

        $rollbackDeployment = ApplicationDeploymentQueue::where('deployment_uuid', $rollback_deployment_uuid)->first();

        if ($rollbackDeployment) {
            $event->markInProgress($rollbackDeployment->id);
        }

        return response()->json([
            'message' => 'Rollback initiated successfully',
            'deployment_uuid' => $rollback_deployment_uuid->toString(),
            'rollback_event_id' => $event->id,
        ]);
    }
}
