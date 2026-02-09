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
    $totalDeployments24h = \App\Models\ApplicationDeploymentQueue::where('created_at', '>=', now()->subHours(24))->count();
    $successDeployments24h = \App\Models\ApplicationDeploymentQueue::where('created_at', '>=', now()->subHours(24))
        ->where('status', 'finished')->count();
    $totalDeployments7d = \App\Models\ApplicationDeploymentQueue::where('created_at', '>=', now()->subDays(7))->count();
    $successDeployments7d = \App\Models\ApplicationDeploymentQueue::where('created_at', '>=', now()->subDays(7))
        ->where('status', 'finished')->count();

    // Trend calculations: current 30d vs previous 30d
    $totalUsers = \App\Models\User::count();
    $newUsersThisPeriod = \App\Models\User::where('created_at', '>=', now()->subDays(30))->count();
    $newUsersPrevPeriod = \App\Models\User::whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])->count();

    $totalServers = \App\Models\Server::count();
    $newServersThisPeriod = \App\Models\Server::where('created_at', '>=', now()->subDays(30))->count();
    $newServersPrevPeriod = \App\Models\Server::whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])->count();

    $deploymentsThisPeriod = \App\Models\ApplicationDeploymentQueue::where('created_at', '>=', now()->subDays(30))->count();
    $deploymentsPrevPeriod = \App\Models\ApplicationDeploymentQueue::whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])->count();
    $failedDeployments = \App\Models\ApplicationDeploymentQueue::where('status', 'failed')->count();

    $totalTeams = \App\Models\Team::count();
    $newTeamsThisPeriod = \App\Models\Team::where('created_at', '>=', now()->subDays(30))->count();
    $newTeamsPrevPeriod = \App\Models\Team::whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])->count();

    // Helper to calculate trend percentage
    $calcTrend = function (int $current, int $previous): ?int {
        if ($previous === 0) {
            return $current > 0 ? 100 : null;
        }

        return (int) round((($current - $previous) / $previous) * 100);
    };

    $stats = [
        'totalUsers' => $totalUsers,
        'activeUsers' => $newUsersThisPeriod,
        'totalServers' => $totalServers,
        'totalDeployments' => \App\Models\ApplicationDeploymentQueue::count(),
        'failedDeployments' => $failedDeployments,
        'totalTeams' => $totalTeams,
        'totalApplications' => \App\Models\Application::count(),
        'totalServices' => \App\Models\Service::count(),
        'totalDatabases' => \App\Models\StandalonePostgresql::count()
            + \App\Models\StandaloneMysql::count()
            + \App\Models\StandaloneMariadb::count()
            + \App\Models\StandaloneMongodb::count()
            + \App\Models\StandaloneRedis::count()
            + \App\Models\StandaloneKeydb::count()
            + \App\Models\StandaloneDragonfly::count()
            + \App\Models\StandaloneClickhouse::count(),
        'deploymentSuccessRate24h' => $totalDeployments24h > 0
            ? round(($successDeployments24h / $totalDeployments24h) * 100)
            : 100,
        'deploymentSuccessRate7d' => $totalDeployments7d > 0
            ? round(($successDeployments7d / $totalDeployments7d) * 100)
            : 100,
        'queuePending' => \App\Models\ApplicationDeploymentQueue::whereIn('status', ['queued', 'in_progress'])->count(),
        'queueFailed' => \Illuminate\Support\Facades\DB::table('failed_jobs')->count(),
        // Trends (current 30d vs previous 30d)
        'trends' => [
            'users' => $calcTrend($newUsersThisPeriod, $newUsersPrevPeriod),
            'servers' => $calcTrend($newServersThisPeriod, $newServersPrevPeriod),
            'deployments' => $calcTrend($deploymentsThisPeriod, $deploymentsPrevPeriod),
            'teams' => $calcTrend($newTeamsThisPeriod, $newTeamsPrevPeriod),
        ],
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
