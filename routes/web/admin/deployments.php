<?php

/**
 * Admin Deployments routes
 *
 * Deployment management including listing and deployment approvals.
 */

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/deployments', function (\Illuminate\Http\Request $request) {
    $query = \App\Models\ApplicationDeploymentQueue::with(['application.environment.project.team']);

    // Search filter
    if ($search = $request->get('search')) {
        $query->where(function ($q) use ($search) {
            $q->where('commit_message', 'like', "%{$search}%")
                ->orWhere('commit', 'like', "%{$search}%")
                ->orWhereHas('application', function ($aq) use ($search) {
                    $aq->where('name', 'like', "%{$search}%");
                })
                ->orWhereHas('application.environment.project.team', function ($tq) use ($search) {
                    $tq->where('name', 'like', "%{$search}%");
                });
        });
    }

    // Status filter
    if ($status = $request->get('status')) {
        if ($status === 'in_progress') {
            $query->whereIn('status', ['in_progress', 'queued']);
        } else {
            $query->where('status', $status);
        }
    }

    $deployments = $query->latest()
        ->paginate(50)
        ->through(function ($deployment) {
            return [
                'id' => $deployment->id,
                'deployment_uuid' => $deployment->deployment_uuid,
                'application_name' => $deployment->application?->name ?? 'Unknown',
                'application_uuid' => $deployment->application?->uuid,
                'application_id' => $deployment->application_id,
                'status' => $deployment->status,
                'commit' => $deployment->commit,
                'commit_message' => $deployment->commit_message,
                'is_webhook' => (bool) $deployment->is_webhook,
                'is_api' => (bool) $deployment->is_api,
                'team_name' => $deployment->application?->environment?->project?->team?->name ?? 'Unknown',
                'team_id' => $deployment->application?->environment?->project?->team?->id,
                'created_at' => $deployment->created_at,
                'updated_at' => $deployment->updated_at,
            ];
        });

    // Stats for the status cards
    $stats = [
        'successCount' => \App\Models\ApplicationDeploymentQueue::where('status', 'finished')->count(),
        'failedCount' => \App\Models\ApplicationDeploymentQueue::where('status', 'failed')->count(),
        'inProgressCount' => \App\Models\ApplicationDeploymentQueue::whereIn('status', ['in_progress', 'queued'])->count(),
    ];

    return Inertia::render('Admin/Deployments/Index', [
        'deployments' => $deployments,
        'stats' => $stats,
        'filters' => [
            'search' => $request->get('search'),
            'status' => $request->get('status'),
        ],
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
