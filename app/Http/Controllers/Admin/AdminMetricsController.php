<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiUsageLog;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\Server;
use App\Models\ServerHealthCheck;
use App\Models\Service;
use App\Models\StandaloneClickhouse;
use App\Models\StandaloneDragonfly;
use App\Models\StandaloneKeydb;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use App\Models\StandaloneRedis;
use App\Models\Team;
use App\Services\TeamQuotaService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminMetricsController extends Controller
{
    public function index(Request $request): Response
    {
        $tab = $request->query('tab', 'overview');

        $totalApplications = Application::count();
        $totalServices = Service::count();
        $totalDatabases = StandalonePostgresql::count()
            + StandaloneMysql::count()
            + StandaloneMariadb::count()
            + StandaloneMongodb::count()
            + StandaloneRedis::count()
            + StandaloneKeydb::count()
            + StandaloneDragonfly::count()
            + StandaloneClickhouse::count();

        $totalResources = $totalApplications + $totalServices + $totalDatabases;

        $activeResources = Application::whereIn('id', function ($query) {
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

        if ($tab === 'resource-usage') {
            $data['resourceUsage'] = $this->getResourceUsageData($request);
        } elseif ($tab === 'team-performance') {
            $data['teamPerformance'] = $this->getTeamPerformanceData();
        } elseif ($tab === 'cost-analytics') {
            $data['costAnalytics'] = $this->getCostAnalyticsData();
        }

        return Inertia::render('Admin/Metrics/Index', $data);
    }

    private function getResourceUsageData(Request $request): array
    {
        $period = $request->query('period', '24h');
        $since = match ($period) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => now()->subHours(24),
        };

        $servers = Server::with(['settings'])->get();
        $serverUsage = [];

        // Load all health checks in a single query instead of N queries per server
        $allChecks = ServerHealthCheck::whereIn('server_id', $servers->pluck('id'))
            ->where('checked_at', '>=', $since)
            ->orderBy('checked_at')
            ->get(['server_id', 'cpu_usage_percent', 'memory_usage_percent', 'disk_usage_percent', 'checked_at'])
            ->groupBy('server_id');

        foreach ($servers as $server) {
            $checks = $allChecks->get($server->id, collect());

            if ($checks->isEmpty()) {
                $serverUsage[] = [
                    'server_id' => $server->id,
                    'server_name' => $server->name,
                    'server_ip' => $server->ip,
                    'checks' => [],
                    'latest' => ['cpu' => 0, 'memory' => 0, 'disk' => 0],
                ];

                continue;
            }

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

    private function getTeamPerformanceData(): array
    {
        $teams = Team::withCount(['members', 'servers', 'projects'])->get();
        $quotaService = new TeamQuotaService;

        return $teams->map(function ($team) use ($quotaService) {
            $usage = $quotaService->getUsage($team);

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

    private function getCostAnalyticsData(): array
    {
        $globalStats = AiUsageLog::getGlobalStats('30d');

        $teamCosts = AiUsageLog::where('created_at', '>=', now()->subDays(30))
            ->selectRaw('team_id, SUM(cost_usd) as total_cost, COUNT(*) as request_count, SUM(input_tokens + output_tokens) as total_tokens')
            ->groupBy('team_id')
            ->get();

        // Load all teams at once to avoid N+1
        $teamsById = Team::whereIn('id', $teamCosts->pluck('team_id'))->pluck('name', 'id');

        $teamCosts = $teamCosts
            ->map(function ($row) use ($teamsById) {
                return [
                    'team_id' => $row->team_id,
                    'team_name' => $teamsById->get($row->team_id) ?? 'Unknown',
                    'total_cost' => round((float) $row->getAttribute('total_cost'), 4),
                    'request_count' => (int) $row->getAttribute('request_count'),
                    'total_tokens' => (int) $row->getAttribute('total_tokens'),
                ];
            })
            ->sortByDesc('total_cost')
            ->values()
            ->toArray();

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
