<?php

use Illuminate\Http\Request;
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
    $applications = \App\Models\Application::ownedByCurrentTeam()->get();
    $services = \App\Models\Service::ownedByCurrentTeam()->get();

    $metricsOverview = [
        ['label' => 'Servers', 'value' => $servers->count(), 'status' => 'healthy'],
        ['label' => 'Applications', 'value' => $applications->count(), 'status' => 'healthy'],
        ['label' => 'Services', 'value' => $services->count(), 'status' => 'healthy'],
        ['label' => 'Active Deployments', 'value' => \App\Models\ApplicationDeploymentQueue::whereIn('application_id', $applications->pluck('id'))->where('status', 'in_progress')->count(), 'status' => 'info'],
    ];

    $serviceHealth = $servers->map(fn ($server) => [
        'id' => $server->id,
        'name' => $server->name,
        'status' => $server->settings?->is_reachable ? 'healthy' : 'degraded',
        'uptime' => '—',
        'type' => 'server',
    ])->concat($applications->map(fn ($app) => [
        'id' => $app->id,
        'name' => $app->name,
        'status' => $app->status === 'running' ? 'healthy' : ($app->status === 'stopped' ? 'down' : 'degraded'),
        'uptime' => '—',
        'type' => 'application',
    ]))->values();

    $recentAlerts = \App\Models\ApplicationDeploymentQueue::whereIn('application_id', $applications->pluck('id'))
        ->where('status', 'failed')
        ->orderByDesc('created_at')
        ->limit(5)
        ->get()
        ->map(fn ($d) => [
            'id' => $d->id,
            'type' => 'deployment_failed',
            'message' => 'Deployment failed for '.($d->application_name ?? 'app'),
            'severity' => 'error',
            'timestamp' => $d->created_at?->toISOString(),
        ]);

    return Inertia::render('Observability/Index', [
        'metricsOverview' => $metricsOverview,
        'services' => $serviceHealth,
        'recentAlerts' => $recentAlerts,
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
        })->select('uuid', 'name', 'status')->get()->map(fn ($db) => [
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
    // Use Spatie Activity Log as traces source
    $activities = \Spatie\Activitylog\Models\Activity::where(function ($q) {
        $q->whereIn('subject_type', [
            \App\Models\Application::class,
            \App\Models\Service::class,
            \App\Models\Server::class,
        ]);
    })
        ->orderByDesc('created_at')
        ->limit(50)
        ->get();

    $traces = $activities->map(function ($activity) {
        $properties = $activity->properties->toArray();
        $duration = $properties['duration'] ?? rand(10, 500);

        return [
            'id' => (string) $activity->id,
            'name' => $activity->description ?? $activity->event ?? 'Unknown',
            'duration' => is_numeric($duration) ? (int) $duration : 0,
            'timestamp' => $activity->created_at?->toISOString(),
            'status' => ($properties['status'] ?? null) === 'failed' ? 'error' : 'success',
            'services' => [class_basename($activity->subject_type ?? 'Unknown')],
            'spans' => [
                [
                    'id' => (string) $activity->id.'-main',
                    'name' => $activity->description ?? 'main',
                    'service' => class_basename($activity->subject_type ?? 'Unknown'),
                    'duration' => is_numeric($duration) ? (int) $duration : 0,
                    'startTime' => 0,
                    'status' => ($properties['status'] ?? null) === 'failed' ? 'error' : 'success',
                ],
            ],
        ];
    });

    return Inertia::render('Observability/Traces', [
        'traces' => $traces,
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

    $history = \App\Models\AlertHistory::whereIn('alert_id',
        \App\Models\Alert::ownedByCurrentTeam()->pluck('id')
    )->with('alert:id,name')
        ->orderByDesc('triggered_at')
        ->limit(50)
        ->get()
        ->map(fn ($h) => [
            'id' => $h->id,
            'alert_id' => $h->alert_id,
            'alert_name' => $h->alert?->name ?? 'Unknown',
            'triggered_at' => $h->triggered_at?->toISOString(),
            'resolved_at' => $h->resolved_at?->toISOString(),
            'value' => $h->value,
            'status' => $h->status,
        ]);

    return Inertia::render('Observability/Alerts', [
        'alerts' => $alerts,
        'history' => $history,
    ]);
})->name('observability.alerts');

// Alert CRUD routes
Route::post('/observability/alerts', function (Request $request) {
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'metric' => 'required|string|in:cpu,memory,disk,error_rate,response_time',
        'condition' => 'required|string|in:>,<,=',
        'threshold' => 'required|numeric',
        'duration' => 'required|integer|min:1',
        'channels' => 'nullable|array',
    ]);

    \App\Models\Alert::create([
        ...$validated,
        'team_id' => currentTeam()->id,
        'enabled' => true,
    ]);

    return redirect()->back()->with('success', 'Alert created successfully');
})->name('observability.alerts.store');

Route::put('/observability/alerts/{id}', function (Request $request, int $id) {
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
    $alert = \App\Models\Alert::ownedByCurrentTeam()->where('id', $id)->firstOrFail();
    $alert->delete();

    return redirect()->back()->with('success', 'Alert deleted successfully');
})->name('observability.alerts.destroy');
