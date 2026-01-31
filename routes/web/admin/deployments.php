<?php

/**
 * Admin Deployments routes
 *
 * Deployment management including listing and deployment approvals.
 */

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/deployments', function () {
    // Fetch all deployments across all teams (admin view)
    $deployments = \App\Models\ApplicationDeploymentQueue::with(['application.environment.project.team'])
        ->latest()
        ->paginate(50)
        ->through(function ($deployment) {
            return [
                'id' => $deployment->id,
                'deployment_uuid' => $deployment->deployment_uuid,
                'application_name' => $deployment->application?->name ?? 'Unknown',
                'application_uuid' => $deployment->application?->uuid,
                'status' => $deployment->status,
                'team_name' => $deployment->application?->environment?->project?->team?->name ?? 'Unknown',
                'team_id' => $deployment->application?->environment?->project?->team?->id,
                'created_at' => $deployment->created_at,
                'updated_at' => $deployment->updated_at,
            ];
        });

    return Inertia::render('Admin/Deployments/Index', [
        'deployments' => $deployments,
    ]);
})->name('admin.deployments.index');

Route::get('/deployment-approvals', function () {
    // Fetch pending deployment approvals across all teams (admin view)
    $deployments = \App\Models\ApplicationDeploymentQueue::with(['application.environment.project.team'])
        ->where('requires_approval', true)
        ->where('approval_status', 'pending')
        ->latest()
        ->paginate(50)
        ->through(function ($deployment) {
            return [
                'id' => $deployment->id,
                'deployment_uuid' => $deployment->deployment_uuid,
                'application_name' => $deployment->application?->name ?? 'Unknown',
                'application_uuid' => $deployment->application?->uuid,
                'status' => $deployment->status,
                'approval_status' => $deployment->approval_status,
                'commit' => $deployment->commit,
                'commit_message' => $deployment->commit_message,
                'team_name' => $deployment->application?->environment?->project?->team?->name ?? 'Unknown',
                'team_id' => $deployment->application?->environment?->project?->team?->id,
                'created_at' => $deployment->created_at,
            ];
        });

    return Inertia::render('Admin/Deployments/Approvals', [
        'deployments' => $deployments,
    ]);
})->name('admin.deployment-approvals.index');
