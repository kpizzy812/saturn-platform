<?php

/**
 * Admin Metrics routes
 *
 * System performance metrics, deployment statistics, resource usage, team performance, and cost analytics.
 */

use App\Models\AiUsageLog;
use App\Models\ApplicationDeploymentQueue;
use App\Models\ServerHealthCheck;
use App\Services\TeamQuotaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/metrics', function (Request $request) {
    $tab = $request->query('tab', 'overview');

    $totalApplications = \App\Models\Application::count();
    $totalServices = \App\Models\Service::count();
    $totalDatabases = \App\Models\StandalonePostgresql::count()
        + \App\Models\StandaloneMysql::count()
        + \App\Models\StandaloneMariadb::count()
        + \App\Models\StandaloneMongodb::count()
        + \App\Models\StandaloneRedis::count()
        + \App\Models\StandaloneKeydb::count()
        + \App\Models\StandaloneDragonfly::count()
        + \App\Models\StandaloneClickhouse::count();

    $totalResources = $totalApplications + $totalServices + $totalDatabases;

    // Count "active" resources (applications with recent deployments)
    $activeResources = \App\Models\Application::whereIn('id', function ($query) {
        $query->select('application_id')
            ->from('application_deployment_queues')
            ->where('created_at', '>=', now()->subDays(7));
    })->count();

    $totalDeployments = ApplicationDeploymentQueue::count();
    $successfulDeployments = ApplicationDeploymentQueue::where('status', 'finished')->count();
    $failedDeployments = ApplicationDeploymentQueue::where('status', 'failed')->count();

    $deploymentsLast24h = ApplicationDeploymentQueue::where('created_at', '>=', now()->subHours(24))->count();
    $deploymentsLast7d = ApplicationDeploymentQueue::where('created_at', '>=', now()->subDays(7))->count();

    $successRate = $totalDeployments > 0
        ? round(($successfulDeployments / $totalDeployments) * 100, 1)
        : 0;

    $avgDeployTime = ApplicationDeploymentQueue::where('status', 'finished')
        ->whereNotNull('created_at')
        ->whereNotNull('updated_at')
        ->selectRaw('AVG(EXTRACT(EPOCH FROM (updated_at - created_at))) as avg_seconds')
        ->value('avg_seconds');

    $data = [
        'metrics' => [
            'totalResources' => $totalResources,
            'activeResources' => $activeResources,
            'totalDeployments' => $totalDeployments,
            'successfulDeployments' => $successfulDeployments,
            'failedDeployments' => $failedDeployments,
            'averageDeploymentTime' => round((float) ($avgDeployTime ?? 0)),
            'deploymentsLast24h' => $deploymentsLast24h,
            'deploymentsLast7d' => $deploymentsLast7d,
            'successRate' => $successRate,
        ],
        'activeTab' => $tab,
    ];

    // Load tab-specific data
    if ($tab === 'resource-usage') {
        $data['resourceUsage'] = getResourceUsageData($request);
    } elseif ($tab === 'team-performance') {
        $data['teamPerformance'] = getTeamPerformanceData();
    } elseif ($tab === 'cost-analytics') {
        $data['costAnalytics'] = getCostAnalyticsData();
    }

    return Inertia::render('Admin/Metrics/Index', $data);
})->name('admin.metrics.index');

/**
 * Resource usage data: server health trends.
 */
if (! function_exists('getResourceUsageData')) {
    function getResourceUsageData(Request $request): array
    {
        $period = $request->query('period', '24h');
        $since = match ($period) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => now()->subHours(24),
        };

        $servers = \App\Models\Server::with(['settings'])->get();
        $serverUsage = [];

        foreach ($servers as $server) {
            $checks = ServerHealthCheck::where('server_id', $server->id)
                ->where('checked_at', '>=', $since)
                ->orderBy('checked_at')
                ->get(['cpu_usage_percent', 'memory_usage_percent', 'disk_usage_percent', 'checked_at']);

            $serverUsage[] = [
                'server_id' => $server->id,
                'server_name' => $server->name,
                'server_ip' => $server->ip,
                'checks' => $checks->map(fn ($c) => [
                    'cpu' => round((float) ($c->cpu_usage_percent ?? 0), 1),
                    'memory' => round((float) ($c->memory_usage_percent ?? 0), 1),
                    'disk' => round((float) ($c->disk_usage_percent ?? 0), 1),
                    'time' => $c->checked_at->toIso8601String(),
                ])->values()->toArray(),
                'latest' => [
                    'cpu' => round((float) ($checks->last()->cpu_usage_percent ?? 0), 1),
                    'memory' => round((float) ($checks->last()->memory_usage_percent ?? 0), 1),
                    'disk' => round((float) ($checks->last()->disk_usage_percent ?? 0), 1),
                ],
            ];
        }

        return [
            'servers' => $serverUsage,
            'period' => $period,
        ];
    }
}

/**
 * Team performance data: resource counts and deployment stats per team.
 */
if (! function_exists('getTeamPerformanceData')) {
    function getTeamPerformanceData(): array
    {
        $teams = \App\Models\Team::withCount(['members', 'servers', 'projects'])->get();
        $quotaService = new TeamQuotaService;

        return $teams->map(function ($team) use ($quotaService) {
            $usage = $quotaService->getUsage($team);

            // Deployment stats (last 30 days)
            $deploymentsQuery = ApplicationDeploymentQueue::whereHas('application.environment.project', function ($q) use ($team) {
                $q->where('team_id', $team->id);
            })->where('created_at', '>=', now()->subDays(30));

            $totalDeploys = (clone $deploymentsQuery)->count();
            $successDeploys = (clone $deploymentsQuery)->where('status', 'finished')->count();

            return [
                'id' => $team->id,
                'name' => $team->name,
                'members_count' => $team->members_count,
                'servers_count' => $team->servers_count,
                'projects_count' => $team->projects_count,
                'applications' => $usage['applications']['current'],
                'databases' => $usage['databases']['current'],
                'deployments_30d' => $totalDeploys,
                'success_rate' => $totalDeploys > 0 ? round(($successDeploys / $totalDeploys) * 100, 1) : 0,
                'quotas' => [
                    'max_servers' => $team->max_servers,
                    'max_applications' => $team->max_applications,
                    'max_databases' => $team->max_databases,
                    'max_projects' => $team->max_projects,
                ],
            ];
        })->toArray();
    }
}

/**
 * Cost analytics data: AI usage costs and per-team breakdown.
 */
if (! function_exists('getCostAnalyticsData')) {
    function getCostAnalyticsData(): array
    {
        $globalStats = AiUsageLog::getGlobalStats('30d');

        // Per-team AI costs
        $teamCosts = AiUsageLog::where('created_at', '>=', now()->subDays(30))
            ->selectRaw('team_id, SUM(cost_usd) as total_cost, COUNT(*) as request_count, SUM(input_tokens + output_tokens) as total_tokens')
            ->groupBy('team_id')
            ->get()
            ->map(function ($row) {
                $team = \App\Models\Team::find($row->team_id);

                return [
                    'team_id' => $row->team_id,
                    'team_name' => $team->name ?? 'Unknown',
                    'total_cost' => round((float) $row->getAttribute('total_cost'), 4),
                    'request_count' => (int) $row->getAttribute('request_count'),
                    'total_tokens' => (int) $row->getAttribute('total_tokens'),
                ];
            })
            ->sortByDesc('total_cost')
            ->values()
            ->toArray();

        // Per-model breakdown
        $modelCosts = AiUsageLog::where('created_at', '>=', now()->subDays(30))
            ->selectRaw('model, SUM(cost_usd) as total_cost, COUNT(*) as request_count')
            ->groupBy('model')
            ->get()
            ->map(fn ($row) => [
                'model' => $row->model,
                'total_cost' => round((float) $row->getAttribute('total_cost'), 4),
                'request_count' => (int) $row->getAttribute('request_count'),
            ])
            ->sortByDesc('total_cost')
            ->values()
            ->toArray();

        // Daily cost trend (last 30 days)
        $dailyCosts = AiUsageLog::where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, SUM(cost_usd) as cost, COUNT(*) as requests')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->getAttribute('date'),
                'cost' => round((float) $row->getAttribute('cost'), 4),
                'requests' => (int) $row->getAttribute('requests'),
            ])
            ->toArray();

        return [
            'globalStats' => $globalStats,
            'teamCosts' => $teamCosts,
            'modelCosts' => $modelCosts,
            'dailyCosts' => $dailyCosts,
        ];
    }
}
