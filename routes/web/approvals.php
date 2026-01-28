<?php

/**
 * Deployment Approvals routes for Saturn Platform
 *
 * These routes handle viewing and managing pending deployment approvals.
 * All routes require authentication and email verification.
 */

use App\Actions\Deployment\ApproveDeploymentAction;
use App\Models\ApplicationDeploymentQueue;
use App\Models\DeploymentApproval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Approvals list page
Route::get('/approvals', function () {
    return Inertia::render('Approvals/Index');
})->name('approvals.index');

// JSON API endpoints for web frontend (session auth)

// Get pending approvals for current user
Route::get('/approvals/pending/json', function () {
    /** @var \App\Models\User $user */
    $user = auth()->user();

    $approvals = DeploymentApproval::pendingForApprover($user)->get();

    return response()->json($approvals->map(function (DeploymentApproval $approval) {
        return [
            'uuid' => $approval->uuid,
            'status' => $approval->status,
            'deployment_uuid' => $approval->deployment?->deployment_uuid,
            'application_name' => $approval->deployment?->application?->name,
            'environment_name' => $approval->deployment?->application?->environment?->name,
            'project_name' => $approval->deployment?->application?->environment?->project?->name,
            'requested_by' => $approval->requestedBy?->email,
            'requested_at' => $approval->created_at?->toIso8601String(),
        ];
    }));
})->name('approvals.pending.json');

// Get pending approvals for a project
Route::get('/projects/{uuid}/approvals/pending/json', function (string $uuid) {
    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $approvals = DeploymentApproval::pendingForProject($project)->get();

    return response()->json($approvals->map(function (DeploymentApproval $approval) {
        return [
            'uuid' => $approval->uuid,
            'status' => $approval->status,
            'deployment_uuid' => $approval->deployment?->deployment_uuid,
            'application_name' => $approval->deployment?->application?->name,
            'environment_name' => $approval->deployment?->application?->environment?->name,
            'requested_by' => $approval->requestedBy?->email,
            'requested_at' => $approval->created_at?->toIso8601String(),
        ];
    }));
})->name('projects.approvals.pending.json');

// Approve a deployment
Route::post('/deployments/{uuid}/approve/json', function (Request $request, string $uuid, ApproveDeploymentAction $approveAction) {
    $deployment = ApplicationDeploymentQueue::where('deployment_uuid', $uuid)->firstOrFail();

    // Check if deployment belongs to user's team
    $application = $deployment->application;
    if (! $application || $application->team()?->id !== currentTeam()->id) {
        return response()->json(['message' => 'Deployment not found.'], 404);
    }

    $approval = DeploymentApproval::where('application_deployment_queue_id', $deployment->id)
        ->where('status', 'pending')
        ->firstOrFail();

    /** @var \App\Models\User $user */
    $user = auth()->user();

    try {
        $approveAction->approve($approval, $user, $request->input('comment'));

        return response()->json([
            'message' => 'Deployment approved successfully.',
            'deployment_uuid' => $deployment->deployment_uuid,
            'status' => 'approved',
        ]);
    } catch (\Exception $e) {
        $statusCode = str_contains($e->getMessage(), 'permission') ? 403 : 400;

        return response()->json(['message' => $e->getMessage()], $statusCode);
    }
})->name('deployments.approve.json');

// Reject a deployment
Route::post('/deployments/{uuid}/reject/json', function (Request $request, string $uuid, ApproveDeploymentAction $approveAction) {
    $deployment = ApplicationDeploymentQueue::where('deployment_uuid', $uuid)->firstOrFail();

    // Check if deployment belongs to user's team
    $application = $deployment->application;
    if (! $application || $application->team()?->id !== currentTeam()->id) {
        return response()->json(['message' => 'Deployment not found.'], 404);
    }

    $approval = DeploymentApproval::where('application_deployment_queue_id', $deployment->id)
        ->where('status', 'pending')
        ->firstOrFail();

    /** @var \App\Models\User $user */
    $user = auth()->user();

    try {
        $approveAction->reject($approval, $user, $request->input('reason'));

        return response()->json([
            'message' => 'Deployment rejected successfully.',
            'deployment_uuid' => $deployment->deployment_uuid,
            'status' => 'rejected',
        ]);
    } catch (\Exception $e) {
        $statusCode = str_contains($e->getMessage(), 'permission') ? 403 : 400;

        return response()->json(['message' => $e->getMessage()], $statusCode);
    }
})->name('deployments.reject.json');

// Check if deployment requires approval (for application)
Route::get('/applications/{uuid}/check-approval/json', function (string $uuid) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->with('environment.project')
        ->firstOrFail();

    /** @var \App\Models\User $user */
    $user = auth()->user();
    $environment = $application->environment;

    $requiresApproval = $user->requiresApprovalForEnvironment($environment);
    $canDeploy = $user->canDeployToEnvironment($environment);
    $userRole = $user->roleInProject($environment->project);

    return response()->json([
        'requires_approval' => $requiresApproval,
        'can_deploy' => $canDeploy,
        'user_role' => $userRole,
        'environment' => [
            'uuid' => $environment->uuid,
            'name' => $environment->name,
            'type' => $environment->type ?? 'development',
            'requires_approval' => $environment->requires_approval,
        ],
    ]);
})->name('applications.check-approval.json');

// Deploy application (JSON response for AJAX)
Route::post('/applications/{uuid}/deploy/json', function (string $uuid) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $deployment_uuid = new \Visus\Cuid2\Cuid2;

    $result = queue_application_deployment(
        application: $application,
        deployment_uuid: $deployment_uuid,
        force_rebuild: false,
        is_api: false,
    );

    if ($result['status'] === 'skipped') {
        return response()->json(['message' => $result['message']], 400);
    }

    return response()->json([
        'message' => 'Deployment started',
        'deployment_uuid' => (string) $deployment_uuid,
    ]);
})->name('applications.deploy.json');
