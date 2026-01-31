<?php

/**
 * Admin Dashboard routes
 *
 * Main admin panel dashboard with system stats, recent activity, and health checks.
 */

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    // Fetch actual system stats
    $stats = [
        'totalUsers' => \App\Models\User::count(),
        'activeUsers' => \App\Models\User::where('created_at', '>=', now()->subDays(30))->count(),
        'totalServers' => \App\Models\Server::count(),
        'totalDeployments' => \App\Models\ApplicationDeploymentQueue::count(),
        'failedDeployments' => \App\Models\ApplicationDeploymentQueue::where('status', 'failed')->count(),
        'totalTeams' => \App\Models\Team::count(),
        'totalApplications' => \App\Models\Application::count(),
        'totalServices' => \App\Models\Service::count(),
    ];

    // Recent activity from deployments (primary source of activity)
    $recentActivity = \App\Models\ApplicationDeploymentQueue::with(['application.environment.project.team', 'user'])
        ->latest()
        ->limit(10)
        ->get()
        ->map(function ($deployment) {
            $statusAction = match ($deployment->status) {
                'finished' => 'deployment_completed',
                'failed', 'cancelled' => 'deployment_failed',
                'in_progress', 'queued' => 'deployment_started',
                default => 'deployment_updated',
            };

            // Get user name - from user relationship or fallback to team name
            $userName = $deployment->user?->name;
            if (! $userName) {
                // Determine trigger source if no user
                if ($deployment->is_webhook) {
                    $userName = 'Webhook';
                } elseif ($deployment->is_api) {
                    $userName = 'API';
                } elseif ($deployment->triggered_by) {
                    $userName = ucfirst($deployment->triggered_by);
                } else {
                    $userName = $deployment->application?->environment?->project?->team?->name ?? 'System';
                }
            }

            return [
                'id' => $deployment->id,
                'deployment_uuid' => $deployment->deployment_uuid,
                'action' => $statusAction,
                'status' => $deployment->status,
                'description' => $deployment->commit_message ?: "Deployment {$deployment->status}",
                'commit' => $deployment->commit ? substr($deployment->commit, 0, 7) : null,
                'user_name' => $userName,
                'user_email' => $deployment->user?->email,
                'team_name' => $deployment->application?->environment?->project?->team?->name,
                'resource_type' => 'Application',
                'resource_name' => $deployment->application?->name,
                'application_uuid' => $deployment->application?->uuid,
                'triggered_by' => $deployment->triggered_by ?? ($deployment->is_webhook ? 'webhook' : ($deployment->is_api ? 'api' : 'manual')),
                'is_webhook' => $deployment->is_webhook,
                'is_api' => $deployment->is_api,
                'created_at' => $deployment->created_at,
            ];
        });

    // Health checks
    $healthChecks = [];
    try {
        // Check PostgreSQL
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $healthChecks[] = [
            'service' => 'PostgreSQL',
            'status' => 'healthy',
            'lastCheck' => now()->toISOString(),
        ];
    } catch (\Exception $e) {
        $healthChecks[] = [
            'service' => 'PostgreSQL',
            'status' => 'down',
            'lastCheck' => now()->toISOString(),
        ];
    }

    try {
        \Illuminate\Support\Facades\Redis::ping();
        $healthChecks[] = [
            'service' => 'Redis',
            'status' => 'healthy',
            'lastCheck' => now()->toISOString(),
        ];
    } catch (\Exception $e) {
        $healthChecks[] = [
            'service' => 'Redis',
            'status' => 'down',
            'lastCheck' => now()->toISOString(),
        ];
    }

    return Inertia::render('Admin/Index', [
        'stats' => $stats,
        'recentActivity' => $recentActivity,
        'healthChecks' => $healthChecks,
    ]);
})->name('admin.index');
