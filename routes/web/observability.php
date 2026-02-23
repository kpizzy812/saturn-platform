<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Observability Routes
|--------------------------------------------------------------------------
|
| Routes for monitoring, metrics, logs, traces, and alerts.
|
*/

Route::get('/observability', function () {
    $team = auth()->user()->currentTeam();
    $servers = $team->servers;
    $serverIds = $servers->pluck('id');
    $applications = \App\Models\Application::ownedByCurrentTeam()->get();
    $applicationIds = $applications->pluck('id');

    $now = now();
    $last24h = $now->copy()->subHours(24);
    $prev24h = $now->copy()->subHours(48);

    // --- Metrics Overview (4 cards with sparklines) ---
    // Only use checks that have actual metric data (not null from unreachable servers)
    $healthChecks24h = \App\Models\ServerHealthCheck::whereIn('server_id', $serverIds)
        ->where('created_at', '>=', $last24h)
        ->whereNotNull('cpu_usage_percent')
        ->select('cpu_usage_percent', 'memory_usage_percent', 'disk_usage_percent', 'created_at')
        ->get();

    $healthChecksPrev24h = \App\Models\ServerHealthCheck::whereIn('server_id', $serverIds)
        ->whereBetween('created_at', [$prev24h, $last24h])
        ->whereNotNull('cpu_usage_percent')
        ->select('cpu_usage_percent', 'memory_usage_percent', 'disk_usage_percent')
        ->get();

    // Build sparkline data (24 hourly points) — null when no data for that hour
    $buildSparkline = function (string $field) use ($healthChecks24h, $now) {
        $points = [];
        for ($i = 23; $i >= 0; $i--) {
            $hourStart = $now->copy()->subHours($i + 1);
            $hourEnd = $now->copy()->subHours($i);
            $hourData = $healthChecks24h->filter(
                fn ($c) => $c->created_at >= $hourStart && $c->created_at < $hourEnd
            )->whereNotNull($field);
            $points[] = $hourData->count() > 0 ? round($hourData->avg($field), 1) : null;
        }

        return $points;
    };

    $calcMetric = function (string $field) use ($healthChecks24h, $healthChecksPrev24h, $buildSparkline) {
        $currentData = $healthChecks24h->whereNotNull($field);
        $previousData = $healthChecksPrev24h->whereNotNull($field);

        $hasCurrentData = $currentData->count() > 0;
        $hasPreviousData = $previousData->count() > 0;

        $current = $hasCurrentData ? round($currentData->avg($field), 1) : null;
        $previous = $hasPreviousData ? round($previousData->avg($field), 1) : null;

        // Only show change if both periods have data
        $change = '';
        $trend = 'neutral';
        if ($current !== null && $previous !== null) {
            $diff = $current - $previous;
            $change = $diff != 0 ? ($diff > 0 ? '+' : '').round($diff, 1).'%' : '';
            $trend = $diff > 0.5 ? 'up' : ($diff < -0.5 ? 'down' : 'neutral');
        }

        return [
            'value' => $current !== null ? $current.'%' : 'N/A',
            'change' => $change,
            'trend' => $trend,
            'data' => $buildSparkline($field),
        ];
    };

    $cpuMetric = $calcMetric('cpu_usage_percent');
    $memMetric = $calcMetric('memory_usage_percent');
    $diskMetric = $calcMetric('disk_usage_percent');

    $activeDeployments = \App\Models\ApplicationDeploymentQueue::whereIn('application_id', $applicationIds)
        ->where('status', 'in_progress')
        ->count();

    $metricsOverview = [
        ['label' => 'Avg CPU', ...$cpuMetric],
        ['label' => 'Avg Memory', ...$memMetric],
        ['label' => 'Avg Disk', ...$diskMetric],
        ['label' => 'Active Deployments', 'value' => (string) $activeDeployments, 'change' => '', 'trend' => 'neutral', 'data' => []],
    ];

    // --- Service Health (real data from ServerHealthCheck) ---
    $latestCheckIds = \App\Models\ServerHealthCheck::whereIn('server_id', $serverIds)
        ->select(DB::raw('MAX(id) as id'))
        ->groupBy('server_id')
        ->pluck('id');

    $latestChecks = \App\Models\ServerHealthCheck::whereIn('id', $latestCheckIds)
        ->get()
        ->keyBy('server_id');

    $serviceHealth = $servers->map(function ($server) use ($latestChecks, $last24h) {
        $latestCheck = $latestChecks->get($server->id);

        // Calculate uptime % — count healthy + degraded as "up" (only truly down/unreachable are downtime)
        $totalChecks = \App\Models\ServerHealthCheck::where('server_id', $server->id)
            ->where('created_at', '>=', $last24h)
            ->count();
        $upChecks = \App\Models\ServerHealthCheck::where('server_id', $server->id)
            ->where('created_at', '>=', $last24h)
            ->whereIn('status', ['healthy', 'degraded'])
            ->count();

        $uptime = $totalChecks > 0 ? round(($upChecks / $totalChecks) * 100, 1) : 0;
        $errorRate = $totalChecks > 0 ? round((1 - $upChecks / $totalChecks) * 100, 1) : 0;

        // Map status — preserve "unreachable" as distinct from "down"
        $status = 'down';
        if ($latestCheck) {
            $status = match ($latestCheck->status) {
                'healthy' => 'healthy',
                'degraded' => 'degraded',
                'unreachable' => 'unreachable',
                default => 'down',
            };
        } elseif (data_get($server, 'settings.is_reachable')) {
            $status = data_get($server, 'settings.is_usable') ? 'healthy' : 'degraded';
        }

        return [
            'id' => (string) $server->id,
            'name' => $server->name,
            'status' => $status,
            'uptime' => $uptime,
            'responseTime' => $latestCheck ? $latestCheck->response_time_ms : 0,
            'errorRate' => $errorRate,
        ];
    })->values();

    // --- Deployment Stats ---
    $deploysToday = \App\Models\ApplicationDeploymentQueue::whereIn('application_id', $applicationIds)
        ->where('created_at', '>=', $now->copy()->startOfDay())
        ->count();
    $deploysWeek = \App\Models\ApplicationDeploymentQueue::whereIn('application_id', $applicationIds)
        ->where('created_at', '>=', $now->copy()->subDays(7))
        ->count();
    $deploysSuccessWeek = \App\Models\ApplicationDeploymentQueue::whereIn('application_id', $applicationIds)
        ->where('created_at', '>=', $now->copy()->subDays(7))
        ->where('status', 'finished')
        ->count();
    $deploysFailedWeek = \App\Models\ApplicationDeploymentQueue::whereIn('application_id', $applicationIds)
        ->where('created_at', '>=', $now->copy()->subDays(7))
        ->where('status', 'failed')
        ->count();
    $successRate = $deploysWeek > 0 ? round(($deploysSuccessWeek / $deploysWeek) * 100, 1) : 100;
    $avgDuration = \App\Models\ApplicationDeploymentQueue::whereIn('application_id', $applicationIds)
        ->where('created_at', '>=', $now->copy()->subDays(7))
        ->where('status', 'finished')
        ->whereNotNull('finished_at')
        ->whereNotNull('started_at')
        ->selectRaw('AVG(EXTRACT(EPOCH FROM (finished_at - started_at))) as avg_seconds')
        ->value('avg_seconds');

    $deploymentStats = [
        'today' => $deploysToday,
        'week' => $deploysWeek,
        'successRate' => $successRate,
        'avgDuration' => $avgDuration ? round((float) $avgDuration) : 0,
        'success' => $deploysSuccessWeek,
        'failed' => $deploysFailedWeek,
    ];

    // --- Recent Alerts (AlertHistory + failed deploys merged) ---
    $alertHistoryItems = \App\Models\AlertHistory::whereIn('alert_id',
        \App\Models\Alert::where('team_id', $team->id)->pluck('id')
    )->with('alert:id,name')
        ->orderByDesc('triggered_at')
        ->limit(5)
        ->get();

    $alertHistories = collect();
    foreach ($alertHistoryItems as $h) {
        /** @var \App\Models\Alert|null $alertModel */
        $alertModel = $h->alert;
        $alertName = $alertModel ? $alertModel->name : 'Unknown Alert';
        $alertHistories->push([
            'id' => 'alert-'.$h->id,
            'severity' => $h->status === 'triggered' ? 'warning' : 'info',
            'service' => $alertName,
            'message' => ($h->status === 'triggered' ? 'Alert triggered' : 'Alert resolved').': '.$alertName,
            'time' => $h->triggered_at->diffForHumans(),
            'timestamp' => $h->triggered_at->timestamp,
        ]);
    }

    /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $failedDeploys */
    $failedDeploys = \App\Models\ApplicationDeploymentQueue::whereIn('application_id', $applicationIds)
        ->where('status', 'failed')
        ->orderByDesc('created_at')
        ->limit(5)
        ->get()
        ->map(function ($d) {
            /** @var \App\Models\ApplicationDeploymentQueue $d */
            return [
                'id' => 'deploy-'.$d->id,
                'severity' => 'critical',
                'service' => $d->application_name ?? 'Unknown',
                'message' => 'Deployment failed for '.($d->application_name ?? 'app'),
                'time' => $d->created_at ? $d->created_at->diffForHumans() : '',
                'timestamp' => $d->created_at ? $d->created_at->timestamp : 0,
            ];
        });

    $recentAlerts = $alertHistories->merge($failedDeploys)
        ->sortByDesc(fn (array $a) => $a['timestamp'] ?? 0)
        ->take(5)
        ->values()
        ->map(fn (array $a) => collect($a)->except('timestamp')->all());

    return Inertia::render('Observability/Index', [
        'metricsOverview' => $metricsOverview,
        'services' => $serviceHealth,
        'recentAlerts' => $recentAlerts,
        'deploymentStats' => $deploymentStats,
    ]);
})->name('observability.index');

Route::get('/observability/metrics', function () {
    $team = auth()->user()->currentTeam();
    $servers = $team->servers()->select('id', 'uuid', 'name')->get();

    return Inertia::render('Observability/Metrics', [
        'servers' => $servers->map(fn ($s) => [
            'uuid' => $s->uuid,
            'name' => $s->name,
        ])->values()->toArray(),
    ]);
})->name('observability.metrics');

Route::get('/observability/logs', function () {
    $team = auth()->user()->currentTeam();

    // Get all resources from the team
    $applications = \App\Models\Application::whereHas('environment.project.team', function ($query) use ($team) {
        $query->where('id', $team->id);
    })->select('uuid', 'name', 'status')->get()->map(fn ($app) => [
        'uuid' => $app->uuid,
        'name' => $app->name,
        'type' => 'application',
        'status' => $app->status,
    ]);

    $services = \App\Models\Service::whereHas('environment.project.team', function ($query) use ($team) {
        $query->where('id', $team->id);
    })->select('uuid', 'name')->get()->map(fn ($svc) => [
        'uuid' => $svc->uuid,
        'name' => $svc->name,
        'type' => 'service',
        'status' => 'running',
    ]);

    // Get all database types
    $databaseModels = [
        \App\Models\StandalonePostgresql::class,
        \App\Models\StandaloneMysql::class,
        \App\Models\StandaloneMongodb::class,
        \App\Models\StandaloneRedis::class,
        \App\Models\StandaloneMariadb::class,
        \App\Models\StandaloneKeydb::class,
        \App\Models\StandaloneDragonfly::class,
        \App\Models\StandaloneClickhouse::class,
    ];

    $databases = collect();
    foreach ($databaseModels as $model) {
        $dbs = $model::whereHas('environment.project.team', function ($query) use ($team) {
            $query->where('id', $team->id);
        })->select(['uuid', 'name', 'status'])->get()->map(fn ($db) => [
            'uuid' => $db->uuid,
            'name' => $db->name,
            'type' => 'database',
            'status' => $db->status,
        ]);
        $databases = $databases->merge($dbs);
    }

    $resources = $applications->merge($services)->merge($databases)->values();

    return Inertia::render('Observability/Logs', [
        'resources' => $resources,
    ]);
})->name('observability.logs');

Route::get('/observability/traces', function () {
    $team = auth()->user()->currentTeam();
    $operations = collect();

    // 1. Deployments — team-scoped via Application::ownedByCurrentTeam()
    $teamApplicationIds = \App\Models\Application::ownedByCurrentTeam()->pluck('id');

    if ($teamApplicationIds->isNotEmpty()) {
        $deployments = \App\Models\ApplicationDeploymentQueue::whereIn('application_id', $teamApplicationIds)
            ->with(['application', 'logEntries'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        foreach ($deployments as $deployment) {
            $app = $deployment->application;
            $appName = $app->name ?? 'Unknown';

            // Real duration from started_at/finished_at
            $duration = null;
            if ($deployment->started_at && $deployment->finished_at) {
                $duration = (int) $deployment->started_at->diffInSeconds($deployment->finished_at);
            }

            // Map status
            $status = match ($deployment->status) {
                'finished' => 'success',
                'failed' => 'error',
                'in_progress' => 'in_progress',
                default => 'queued',
            };

            // Determine trigger source
            $triggeredBy = 'manual';
            if ($deployment->is_webhook) {
                $triggeredBy = 'webhook';
            } elseif ($deployment->is_api) {
                $triggeredBy = 'api';
            }

            // Get user who triggered
            $deploymentUser = $deployment->user;
            $user = $deploymentUser ? [
                'name' => $deploymentUser->getAttribute('name') ?? 'System',
                'email' => $deploymentUser->getAttribute('email') ?? 'system@saturn.local',
            ] : ['name' => 'System', 'email' => 'system@saturn.local'];

            // Build stages from log entries grouped by stage
            $stages = [];
            $logEntries = $deployment->logEntries;
            if ($logEntries->isNotEmpty()) {
                $grouped = $logEntries->groupBy('stage');
                $stageOrder = ['prepare', 'clone', 'build', 'push', 'deploy', 'healthcheck'];
                $stageNames = [
                    'prepare' => 'Prepare',
                    'clone' => 'Clone',
                    'build' => 'Build',
                    'push' => 'Push',
                    'deploy' => 'Deploy',
                    'healthcheck' => 'Health Check',
                ];

                foreach ($stageOrder as $stageName) {
                    $entries = $grouped->get($stageName);
                    if (! $entries) {
                        continue;
                    }

                    $first = $entries->first();
                    $last = $entries->last();
                    $stageDuration = null;
                    if ($first && $last) {
                        $stageDuration = (int) $first->created_at->diffInSeconds($last->created_at);
                    }

                    $hasError = $entries->contains(fn ($e) => $e->type === 'stderr');
                    $stageStatus = $hasError ? 'failed' : 'completed';

                    // If deployment is still running and this is the last stage with entries
                    if ($deployment->status === 'in_progress') {
                        $lastStageWithEntries = $grouped->keys()->intersect($stageOrder)->last();
                        if ($stageName === $lastStageWithEntries) {
                            $stageStatus = 'running';
                        }
                    }

                    $stages[] = [
                        'id' => $stageName,
                        'name' => $stageNames[$stageName],
                        'status' => $stageStatus,
                        'duration' => $stageDuration,
                    ];
                }
            }

            $operations->push([
                'id' => 'deploy-'.$deployment->id,
                'type' => 'deployment',
                'name' => 'Deployed '.$appName,
                'status' => $status,
                'duration' => $duration,
                'timestamp' => $deployment->created_at->toIso8601String(),
                'resource' => [
                    'type' => 'application',
                    'name' => $appName,
                    'id' => (string) ($app->uuid ?? ''),
                ],
                'user' => $user,
                'commit' => $deployment->commit ? substr($deployment->commit, 0, 7) : null,
                'triggeredBy' => $triggeredBy,
                'stages' => $stages,
                'changes' => null,
            ]);
        }
    }

    // 2. Config changes — team-scoped via causer_id in team members
    $memberIds = $team->members()->pluck('users.id')->toArray();

    if (! empty($memberIds)) {
        $spatieActivities = \Spatie\Activitylog\Models\Activity::query()
            ->where('causer_type', 'App\\Models\\User')
            ->whereIn('causer_id', $memberIds)
            ->with(['causer', 'subject'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        foreach ($spatieActivities as $activity) {
            $causer = $activity->causer;
            $subject = $activity->subject;
            $properties = $activity->properties->toArray();

            $resourceType = 'unknown';
            $resourceName = 'Unknown';
            $resourceId = '';

            if ($subject) {
                $className = class_basename($subject);
                $resourceType = match (true) {
                    str_contains($className, 'Application') => 'application',
                    str_contains($className, 'Service') => 'service',
                    str_contains($className, 'Standalone') || str_contains($className, 'Database') => 'database',
                    str_contains($className, 'Server') => 'server',
                    str_contains($className, 'Project') => 'project',
                    default => strtolower($className),
                };
                $resourceName = $subject->getAttribute('name') ?? $subject->getAttribute('uuid') ?? class_basename($subject);
                $resourceId = (string) ($subject->getAttribute('uuid') ?? $subject->getAttribute('id') ?? '');
            }

            $event = $activity->event ?? 'updated';
            $name = ucfirst($event).' '.$resourceName;

            $operations->push([
                'id' => 'activity-'.$activity->id,
                'type' => 'config_change',
                'name' => $name,
                'status' => 'success',
                'duration' => null,
                'timestamp' => $activity->created_at->toIso8601String(),
                'resource' => [
                    'type' => $resourceType,
                    'name' => $resourceName,
                    'id' => $resourceId,
                ],
                'user' => [
                    'name' => $causer?->getAttribute('name') ?? 'System',
                    'email' => $causer?->getAttribute('email') ?? 'system@saturn.local',
                ],
                'commit' => null,
                'triggeredBy' => null,
                'stages' => null,
                'changes' => [
                    'old' => $properties['old'] ?? null,
                    'attributes' => $properties['attributes'] ?? null,
                ],
            ]);
        }
    }

    // Sort by timestamp descending, limit to 50
    $operations = $operations
        ->sortByDesc('timestamp')
        ->take(50)
        ->values();

    return Inertia::render('Observability/Traces', [
        'operations' => $operations,
    ]);
})->name('observability.traces');

Route::get('/observability/alerts', function () {
    $alerts = \App\Models\Alert::ownedByCurrentTeam()->get()->map(fn ($alert) => [
        'id' => $alert->id,
        'uuid' => $alert->uuid,
        'name' => $alert->name,
        'metric' => $alert->metric,
        'condition' => $alert->condition,
        'threshold' => $alert->threshold,
        'duration' => $alert->duration,
        'enabled' => $alert->enabled,
        'channels' => $alert->channels ?? [],
        'triggered_count' => $alert->triggered_count,
        'last_triggered' => $alert->last_triggered_at?->toISOString(),
        'created_at' => $alert->created_at?->toISOString(),
    ]);

    $historyItems = \App\Models\AlertHistory::whereIn('alert_id',
        \App\Models\Alert::ownedByCurrentTeam()->pluck('id')
    )->with('alert:id,name')
        ->orderByDesc('triggered_at')
        ->limit(50)
        ->get();

    $history = collect();
    foreach ($historyItems as $h) {
        /** @var \App\Models\Alert|null $alertModel */
        $alertModel = $h->alert;
        $history->push([
            'id' => $h->id,
            'alert_id' => $h->alert_id,
            'alert_name' => $alertModel ? $alertModel->name : 'Unknown',
            'triggered_at' => $h->triggered_at->toISOString(),
            'resolved_at' => $h->resolved_at?->toISOString(),
            'value' => $h->value,
            'status' => $h->status,
        ]);
    }

    return Inertia::render('Observability/Alerts', [
        'alerts' => $alerts,
        'history' => $history,
    ]);
})->name('observability.alerts');

// Alert CRUD routes
Route::post('/observability/alerts', function (Request $request) {
    // Only admins+ can create alerts
    if (! in_array(auth()->user()->role(), ['owner', 'admin'])) {
        abort(403, 'You do not have permission to create alerts.');
    }

    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'metric' => 'required|string|in:cpu,memory,disk,error_rate,response_time',
        'condition' => 'required|string|in:>,<,=',
        'threshold' => 'required|numeric',
        'duration' => 'required|integer|min:1',
        'channels' => 'nullable|array',
    ]);

    $alert = new \App\Models\Alert($validated);
    $alert->team_id = currentTeam()->id;
    $alert->enabled = true;
    $alert->save();

    return redirect()->back()->with('success', 'Alert created successfully');
})->name('observability.alerts.store');

Route::put('/observability/alerts/{id}', function (Request $request, int $id) {
    if (! in_array(auth()->user()->role(), ['owner', 'admin'])) {
        abort(403, 'You do not have permission to update alerts.');
    }

    $alert = \App\Models\Alert::ownedByCurrentTeam()->where('id', $id)->firstOrFail();

    $validated = $request->validate([
        'name' => 'sometimes|string|max:255',
        'metric' => 'sometimes|string|in:cpu,memory,disk,error_rate,response_time',
        'condition' => 'sometimes|string|in:>,<,=',
        'threshold' => 'sometimes|numeric',
        'duration' => 'sometimes|integer|min:1',
        'enabled' => 'sometimes|boolean',
        'channels' => 'nullable|array',
    ]);

    $alert->update($validated);

    return redirect()->back()->with('success', 'Alert updated successfully');
})->name('observability.alerts.update');

Route::delete('/observability/alerts/{id}', function (string $id) {
    if (! in_array(auth()->user()->role(), ['owner', 'admin'])) {
        abort(403, 'You do not have permission to delete alerts.');
    }

    $alert = \App\Models\Alert::ownedByCurrentTeam()->where('id', $id)->firstOrFail();
    $alert->delete();

    return redirect()->back()->with('success', 'Alert deleted successfully');
})->name('observability.alerts.destroy');
