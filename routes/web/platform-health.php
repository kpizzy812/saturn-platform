<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Platform Health Routes
|--------------------------------------------------------------------------
| Unified platform health dashboard showing servers, resources, alerts,
| and deployments in a single view.
*/

Route::get('/platform-health', function () {
    $team = auth()->user()->currentTeam();
    $servers = $team->servers()->with('settings')->get();
    $serverIds = $servers->pluck('id');
    $applications = \App\Models\Application::ownedByCurrentTeam()->get();
    $applicationIds = $applications->pluck('id');

    $now = now();

    // --- Summary Cards ---
    $totalServers = $servers->count();
    $totalApps = $applications->count();

    // Latest health check per server (single query)
    $latestCheckIds = \App\Models\ServerHealthCheck::whereIn('server_id', $serverIds)
        ->select(DB::raw('MAX(id) as id'))
        ->groupBy('server_id')
        ->pluck('id');

    $latestChecks = \App\Models\ServerHealthCheck::whereIn('id', $latestCheckIds)
        ->get()
        ->keyBy('server_id');

    $healthyServers = 0;
    $degradedServers = 0;
    $downServers = 0;
    foreach ($servers as $server) {
        $check = $latestChecks->get($server->id);
        if ($check && $check->status === 'healthy') {
            $healthyServers++;
        } elseif ($check && $check->status === 'degraded') {
            $degradedServers++;
        } else {
            $downServers++;
        }
    }

    $summary = [
        'totalServers' => $totalServers,
        'healthyServers' => $healthyServers,
        'degradedServers' => $degradedServers,
        'downServers' => $downServers,
        'totalApps' => $totalApps,
        'activeDeployments' => \App\Models\ApplicationDeploymentQueue::whereIn('application_id', $applicationIds)
            ->where('status', 'in_progress')
            ->count(),
    ];

    // --- Servers Grid with CPU/RAM/Disk ---
    $serversData = $servers->map(function ($server) use ($latestChecks) {
        $check = $latestChecks->get($server->id);

        $status = 'unknown';
        if ($check) {
            $status = $check->status;
        } elseif (data_get($server, 'settings.is_reachable')) {
            $status = data_get($server, 'settings.is_usable') ? 'healthy' : 'degraded';
        }

        return [
            'id' => $server->id,
            'name' => $server->name,
            'ip' => $server->ip,
            'status' => $status,
            'cpu' => $check?->cpu_usage_percent,
            'memory' => $check?->memory_usage_percent,
            'disk' => $check?->disk_usage_percent,
            'uptime' => $check?->uptime_seconds,
            'checkedAt' => $check?->checked_at?->diffForHumans(),
        ];
    })->values();

    // --- Resources Table (apps + services + databases with statuses) ---
    $services = \App\Models\Service::ownedByCurrentTeam()->get();
    $databases = collect();
    $dbTypes = [
        \App\Models\StandalonePostgresql::class,
        \App\Models\StandaloneMysql::class,
        \App\Models\StandaloneMariadb::class,
        \App\Models\StandaloneRedis::class,
        \App\Models\StandaloneKeydb::class,
        \App\Models\StandaloneDragonfly::class,
        \App\Models\StandaloneMongodb::class,
        \App\Models\StandaloneClickhouse::class,
    ];
    foreach ($dbTypes as $dbType) {
        if (method_exists($dbType, 'ownedByCurrentTeam')) {
            $databases = $databases->merge($dbType::ownedByCurrentTeam()->get());
        }
    }

    $resources = collect();

    foreach ($applications as $app) {
        $resources->push([
            'id' => $app->id,
            'uuid' => $app->uuid,
            'name' => $app->name,
            'type' => 'Application',
            'status' => $app->status ?? 'unknown',
        ]);
    }

    foreach ($services as $svc) {
        $resources->push([
            'id' => $svc->id,
            'uuid' => $svc->uuid,
            'name' => $svc->name,
            'type' => 'Service',
            'status' => $svc->status ?? 'unknown',
        ]);
    }

    foreach ($databases as $db) {
        $resources->push([
            'id' => $db->id,
            'uuid' => $db->uuid,
            'name' => $db->name,
            'type' => 'Database',
            'status' => $db->status ?? 'unknown',
        ]);
    }

    // --- Active Alerts ---
    $activeAlerts = \App\Models\AlertHistory::whereIn('alert_id',
        \App\Models\Alert::where('team_id', $team->id)->pluck('id')
    )->where('status', 'triggered')
        ->with('alert:id,name')
        ->orderByDesc('triggered_at')
        ->limit(10)
        ->get()
        ->map(function ($h) {
            return [
                'id' => $h->id,
                'alertName' => $h->alert?->name ?? 'Unknown',
                'value' => $h->value,
                'triggeredAt' => $h->triggered_at->diffForHumans(),
            ];
        });

    // --- Recent Deployments ---
    $recentDeployments = \App\Models\ApplicationDeploymentQueue::whereIn('application_id', $applicationIds)
        ->orderByDesc('created_at')
        ->limit(10)
        ->get()
        ->map(function ($d) {
            return [
                'id' => $d->id,
                'uuid' => $d->deployment_uuid,
                'appName' => $d->application_name ?? 'Unknown',
                'serverName' => $d->server_name ?? 'Unknown',
                'status' => $d->status,
                'commit' => $d->commit ? substr($d->commit, 0, 7) : null,
                'createdAt' => $d->created_at?->diffForHumans(),
            ];
        });

    return Inertia::render('PlatformHealth/Index', [
        'summary' => $summary,
        'servers' => $serversData,
        'resources' => $resources->values(),
        'activeAlerts' => $activeAlerts,
        'recentDeployments' => $recentDeployments,
    ]);
})->name('platform-health');
