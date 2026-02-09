<?php

/**
 * Admin Deployments routes
 *
 * Deployment management including listing and deployment approvals.
 */

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/deployments', function (\Illuminate\Http\Request $request) {
    $query = \App\Models\ApplicationDeploymentQueue::with(['application.environment.project.team', 'user']);

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
            // Determine trigger type
            $trigger = 'manual';
            if ($deployment->is_webhook) {
                $trigger = 'webhook';
            } elseif ($deployment->is_api) {
                $trigger = 'api';
            } elseif ($deployment->rollback) {
                $trigger = 'rollback';
            }

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
                'trigger' => $trigger,
                'triggered_by' => $deployment->user ? [
                    'name' => $deployment->user->name,
                    'email' => $deployment->user->email,
                ] : null,
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

Route::get('/deployments/{uuid}', function (string $uuid) {
    $deployment = \App\Models\ApplicationDeploymentQueue::with(['application', 'user'])
        ->where('deployment_uuid', $uuid)
        ->first();

    if (! $deployment) {
        return redirect()->route('admin.deployments.index');
    }

    $logs = $deployment->logs ? json_decode($deployment->logs, true) : [];
    $buildLogs = [];
    $deployLogs = [];

    foreach ($logs as $log) {
        if (! empty($log['hidden'])) {
            continue;
        }

        $timestamp = $log['timestamp'] ?? '';
        $output = $log['output'] ?? $log['message'] ?? '';

        $formattedLine = $output;
        if ($timestamp) {
            try {
                $time = \Carbon\Carbon::parse($timestamp)->format('H:i:s');
                $formattedLine = "[$time] ".$output;
            } catch (\Exception $e) {
                $formattedLine = $output;
            }
        }

        $buildLogs[] = trim($formattedLine);
    }

    // Calculate duration
    $duration = null;
    $startTime = $deployment->started_at ?? $deployment->created_at;
    if ($startTime && $deployment->updated_at && $deployment->status !== 'in_progress') {
        $diff = $startTime->diff($deployment->updated_at);
        if ($diff->i > 0) {
            $duration = $diff->i.'m '.$diff->s.'s';
        } else {
            $duration = $diff->s.'s';
        }
    }

    $application = $deployment->application;

    // Get user who triggered the deployment
    $author = null;
    if ($deployment->user) {
        $author = [
            'name' => $deployment->user->name,
            'email' => $deployment->user->email,
            'avatar' => $deployment->user->avatar ? '/storage/'.$deployment->user->avatar : null,
        ];
    }

    // Determine trigger type
    $trigger = 'manual';
    if ($deployment->is_webhook) {
        $trigger = 'push';
    } elseif ($deployment->is_api) {
        $trigger = 'api';
    } elseif ($deployment->rollback) {
        $trigger = 'rollback';
    }

    $data = [
        'id' => $deployment->id,
        'uuid' => $deployment->deployment_uuid,
        'application_id' => $deployment->application_id,
        'application_uuid' => $application?->uuid,
        'status' => $deployment->status,
        'commit' => $deployment->commit,
        'commit_message' => $deployment->commit_message ?? 'Deployment',
        'created_at' => $deployment->created_at?->toISOString(),
        'updated_at' => $deployment->updated_at?->toISOString(),
        'service_name' => $deployment->application_name ?? $application?->name,
        'trigger' => $trigger,
        'duration' => $duration,
        'build_logs' => $buildLogs,
        'deploy_logs' => $deployLogs,
        'author' => $author,
    ];

    return Inertia::render('Admin/Deployments/Show', [
        'deployment' => $data,
    ]);
})->name('admin.deployments.show');

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
