<?php

/**
 * Service routes for Saturn Platform
 *
 * These routes handle service management (docker-compose based services).
 * All routes require authentication and email verification.
 */

use App\Actions\Service\RestartService;
use App\Actions\Service\StopService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Services
Route::get('/services', function () {
    $services = \App\Models\Service::ownedByCurrentTeam()->get();

    return Inertia::render('Services/Index', ['services' => $services]);
})->name('services.index');

Route::get('/services/create', function () {
    $authService = app(\App\Services\Authorization\ProjectAuthorizationService::class);
    $currentUser = auth()->user();

    $projects = \App\Models\Project::ownedByCurrentTeam()
        ->with('environments')
        ->get()
        ->each(function ($project) use ($authService, $currentUser) {
            $project->setRelation(
                'environments',
                $authService->filterVisibleEnvironments($currentUser, $project, $project->environments)
            );
        });

    // Always get localhost (platform's master server) - used by default
    $localhost = \App\Models\Server::where('id', 0)->first();

    // Get user's additional servers (optional)
    $userServers = \App\Models\Server::ownedByCurrentTeam()
        ->where('id', '!=', 0)
        ->whereRelation('settings', 'is_usable', true)
        ->get();

    // Get service templates for quick deploy
    $templateService = app(\App\Services\TemplateService::class);
    $templates = $templateService->getTemplates();

    return Inertia::render('Services/Create', [
        'projects' => $projects,
        'localhost' => $localhost,
        'userServers' => $userServers,
        'needsProject' => $projects->isEmpty(),
        'templates' => $templates,
    ]);
})->name('services.create');

Route::post('/services', function (Request $request) {
    Gate::authorize('create', \App\Models\Service::class);

    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'description' => 'nullable|string',
        'docker_compose_raw' => 'required|string',
        'project_uuid' => 'required|string',
        'environment_uuid' => 'required|string',
        'server_uuid' => 'required|string',
    ]);

    // Find project and environment
    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $validated['project_uuid'])
        ->firstOrFail();

    $environment = $project->environments()
        ->where('uuid', $validated['environment_uuid'])
        ->firstOrFail();

    // Find server and destination
    // First check if it's localhost (platform's master server with id=0)
    $localhost = \App\Models\Server::where('id', 0)->first();
    if ($localhost && $localhost->uuid === $validated['server_uuid']) {
        $server = $localhost;
    } else {
        // Otherwise, look for user's own servers
        $server = \App\Models\Server::ownedByCurrentTeam()
            ->where('uuid', $validated['server_uuid'])
            ->firstOrFail();
    }

    $destination = $server->destinations()->first();
    if (! $destination) {
        return redirect()->back()->withErrors(['server_uuid' => 'Server has no destinations configured']);
    }

    // Create the service
    $service = new \App\Models\Service;
    $service->name = $validated['name'];
    $service->description = $validated['description'] ?? null;
    $service->docker_compose_raw = $validated['docker_compose_raw'];
    $service->environment_id = $environment->id;
    $service->destination_id = $destination->id;
    $service->destination_type = $destination->getMorphClass();
    $service->server_id = $server->id;
    $service->save();

    // Parse docker-compose and create service applications/databases
    try {
        $service->parse();
    } catch (\Exception $e) {
        // Log but don't fail - service is created
        \Illuminate\Support\Facades\Log::warning('Failed to parse docker-compose: '.$e->getMessage());
    }

    return redirect()->route('services.show', $service->uuid)
        ->with('success', 'Service created successfully');
})->name('services.store');

Route::get('/services/{uuid}', function (string $uuid) {
    $service = \App\Models\Service::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->with(['applications', 'databases'])
        ->firstOrFail();

    // Build container list for the Logs tab
    $containers = collect();
    foreach ($service->applications as $app) {
        $containers->push([
            'name' => $app->name.'-'.$service->uuid,
            'label' => $app->name,
            'type' => 'application',
        ]);
    }
    foreach ($service->databases as $db) {
        $containers->push([
            'name' => $db->name.'-'.$service->uuid,
            'label' => $db->name,
            'type' => 'database',
        ]);
    }

    return Inertia::render('Services/Show', [
        'service' => $service,
        'containers' => $containers,
    ]);
})->name('services.show');

Route::get('/services/{uuid}/metrics', function (string $uuid) {
    $service = \App\Models\Service::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    return Inertia::render('Services/Metrics', [
        'service' => $service,
    ]);
})->name('services.metrics');

Route::get('/services/{uuid}/build-logs', function (string $uuid) {
    $service = \App\Models\Service::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    // Fetch activity logs related to this service (deploy/restart/stop actions)
    $activities = \Spatie\Activitylog\Models\Activity::where('subject_type', \App\Models\Service::class)
        ->where('subject_id', $service->id)
        ->orderByDesc('created_at')
        ->limit(20)
        ->get()
        ->map(function ($activity) {
            $properties = $activity->properties->toArray();

            return [
                'id' => $activity->id,
                'name' => $activity->description ?? $activity->event ?? 'Unknown',
                'status' => ($properties['status'] ?? null) === 'failed' ? 'failed' : 'success',
                'duration' => $properties['duration'] ?? '-',
                'logs' => isset($properties['stderr']) ? explode("\n", $properties['stderr']) : [],
                'startTime' => $activity->created_at?->toISOString(),
                'endTime' => $activity->updated_at?->toISOString(),
            ];
        });

    return Inertia::render('Services/BuildLogs', [
        'service' => $service,
        'buildSteps' => $activities,
    ]);
})->name('services.build-logs');

Route::get('/services/{uuid}/domains', function (string $uuid) {
    $service = \App\Models\Service::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->with('applications')
        ->firstOrFail();

    // Extract FQDNs from service applications
    $domains = $service->applications
        ->filter(fn ($app) => ! empty($app->fqdn))
        ->flatMap(function ($app) {
            return collect(explode(',', $app->fqdn))->map(function ($fqdn, $index) use ($app) {
                $fqdn = trim($fqdn);

                return [
                    'id' => $app->id * 100 + $index,
                    'domain' => preg_replace('#^https?://#', '', $fqdn),
                    'isPrimary' => $index === 0,
                    'sslStatus' => str_starts_with($fqdn, 'https://') ? 'active' : 'none',
                    'sslProvider' => str_starts_with($fqdn, 'https://') ? 'letsencrypt' : null,
                    'createdAt' => $app->created_at?->toISOString(),
                ];
            });
        })
        ->values();

    return Inertia::render('Services/Domains', [
        'service' => $service,
        'domains' => $domains,
    ]);
})->name('services.domains');

Route::get('/services/{uuid}/webhooks', function (string $uuid) {
    $service = \App\Models\Service::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $team = auth()->user()->currentTeam();
    $webhooks = $team->webhooks()
        ->with(['deliveries' => function ($query) {
            $query->limit(5);
        }])
        ->orderBy('created_at', 'desc')
        ->get();

    return Inertia::render('Services/Webhooks', [
        'service' => $service,
        'webhooks' => $webhooks,
        'availableEvents' => \App\Models\TeamWebhook::availableEvents(),
    ]);
})->name('services.webhooks');

Route::get('/services/{uuid}/deployments', function (string $uuid) {
    $service = \App\Models\Service::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    return Inertia::render('Services/Deployments', [
        'service' => $service,
    ]);
})->name('services.deployments');

Route::get('/services/{uuid}/logs', function (string $uuid) {
    $service = \App\Models\Service::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->with(['applications', 'databases'])
        ->firstOrFail();

    // Build container list for the frontend selector
    $containers = collect();
    foreach ($service->applications as $app) {
        $containers->push([
            'name' => $app->name.'-'.$service->uuid,
            'label' => $app->name,
            'type' => 'application',
        ]);
    }
    foreach ($service->databases as $db) {
        $containers->push([
            'name' => $db->name.'-'.$service->uuid,
            'label' => $db->name,
            'type' => 'database',
        ]);
    }

    return Inertia::render('Services/Logs', [
        'service' => $service,
        'containers' => $containers,
    ]);
})->name('services.logs');

// JSON endpoint for service container logs (for XHR polling)
// Supports per-container filtering via ?container=<name> and incremental fetching via ?since=<unix_timestamp>
Route::get('/services/{uuid}/logs/json', function (string $uuid, Request $request) {
    $service = \App\Models\Service::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->with(['applications', 'databases'])
        ->firstOrFail();

    $server = $service->server;
    if (! $server) {
        return response()->json(['message' => 'Server not found.'], 404);
    }

    $containerFilter = $request->query('container');
    $since = $request->query('since');
    $lines = (int) ($request->query('lines', 200) ?: 200);
    $logs = [];

    // Collect all service containers (applications + databases)
    $allContainers = collect();
    foreach ($service->applications as $app) {
        $allContainers->push([
            'containerName' => $app->name.'-'.$service->uuid,
            'label' => $app->name,
            'type' => 'application',
        ]);
    }
    foreach ($service->databases as $db) {
        $allContainers->push([
            'containerName' => $db->name.'-'.$service->uuid,
            'label' => $db->name,
            'type' => 'database',
        ]);
    }

    // Filter to specific container if requested
    if ($containerFilter) {
        $allContainers = $allContainers->where('containerName', $containerFilter);
    }

    foreach ($allContainers as $container) {
        $name = $container['containerName'];
        try {
            if ($since) {
                $containerLogs = instant_remote_process(
                    ["docker logs --since {$since} --timestamps {$name} 2>&1"],
                    $server
                );
            } else {
                $containerLogs = instant_remote_process(
                    ["docker logs -n {$lines} --timestamps {$name} 2>&1"],
                    $server
                );
            }
        } catch (\Exception $e) {
            $containerLogs = '';
        }

        $logs[] = [
            'name' => $name,
            'label' => $container['label'],
            'type' => $container['type'],
            'logs' => $containerLogs,
        ];
    }

    return response()->json([
        'containers' => $logs,
        'timestamp' => now()->timestamp,
    ]);
})->name('services.logs.json');

Route::get('/services/{uuid}/health-checks', function (string $uuid) {
    $service = \App\Models\Service::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    return Inertia::render('Services/HealthChecks', [
        'service' => $service,
    ]);
})->name('services.health-checks');

Route::get('/services/{uuid}/networking', function (string $uuid) {
    $service = \App\Models\Service::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    return Inertia::render('Services/Networking', [
        'service' => $service,
    ]);
})->name('services.networking');

Route::get('/services/{uuid}/scaling', function (string $uuid) {
    $service = \App\Models\Service::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    return Inertia::render('Services/Scaling', [
        'service' => $service,
    ]);
})->name('services.scaling');

Route::get('/services/{uuid}/rollbacks', function (string $uuid) {
    $service = \App\Models\Service::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->with(['applications', 'databases'])
        ->firstOrFail();

    // Get container info for deployments history
    $server = $service->server;
    $containers = [];

    if ($server && $server->isFunctional()) {
        try {
            // Get docker container info for this service
            $containersJson = instant_remote_process(
                ["docker ps -a --filter 'label=coolify.serviceId={$service->id}' --format '{{json .}}'"],
                $server,
                false
            );

            if ($containersJson) {
                $lines = array_filter(explode("\n", trim($containersJson)));
                foreach ($lines as $line) {
                    $container = json_decode($line, true);
                    if ($container) {
                        $containers[] = [
                            'id' => $container['ID'] ?? substr($container['Names'] ?? '', 0, 12),
                            'name' => $container['Names'] ?? 'Unknown',
                            'image' => $container['Image'] ?? 'Unknown',
                            'status' => $container['Status'] ?? 'Unknown',
                            'state' => $container['State'] ?? 'Unknown',
                            'created' => $container['CreatedAt'] ?? now()->toISOString(),
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently fail - containers info is optional
        }
    }

    return Inertia::render('Services/Rollbacks', [
        'service' => $service,
        'containers' => $containers,
    ]);
})->name('services.rollbacks');

// API endpoint for service redeploy (restart with pull latest)
Route::post('/_internal/services/{uuid}/redeploy', function (string $uuid, Request $request) {
    $service = \App\Models\Service::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    Gate::authorize('deploy', $service);

    $pullLatest = $request->boolean('pull_latest', true);

    RestartService::dispatch($service, $pullLatest);

    return response()->json([
        'success' => true,
        'message' => $pullLatest ? 'Service redeploy with latest images initiated' : 'Service restart initiated',
    ]);
})->name('services.redeploy.api');

// API endpoint for service container metrics (docker stats)
Route::get('/_internal/services/{uuid}/container-stats', function (string $uuid) {
    $service = \App\Models\Service::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $server = $service->server;

    if (! $server || ! $server->isFunctional()) {
        return response()->json([
            'error' => 'Server is not functional',
            'containers' => [],
        ]);
    }

    try {
        // Get all containers for this service by label
        $command = "docker stats --no-stream --format '{{json .}}' $(docker ps -q --filter 'label=coolify.serviceId={$service->id}' 2>/dev/null) 2>/dev/null || echo ''";
        $output = trim(instant_remote_process([$command], $server, false) ?? '');

        if (empty($output)) {
            return response()->json([
                'error' => 'No running containers found',
                'containers' => [],
            ]);
        }

        // Helper to parse memory values
        $parseMemory = function (string $val): int {
            $val = trim($val);
            if (preg_match('/^([\d.]+)\s*(B|KB|KiB|MB|MiB|GB|GiB|TB|TiB)$/i', $val, $m)) {
                $num = (float) $m[1];
                $unit = strtoupper($m[2]);

                return (int) match ($unit) {
                    'B' => $num,
                    'KB' => $num * 1000,
                    'KIB' => $num * 1024,
                    'MB' => $num * 1000 * 1000,
                    'MIB' => $num * 1024 * 1024,
                    'GB' => $num * 1000 * 1000 * 1000,
                    'GIB' => $num * 1024 * 1024 * 1024,
                    'TB' => $num * 1000 * 1000 * 1000 * 1000,
                    'TIB' => $num * 1024 * 1024 * 1024 * 1024,
                    default => $num,
                };
            }

            return 0;
        };

        $containers = [];
        $lines = array_filter(explode("\n", $output));

        foreach ($lines as $line) {
            $stats = json_decode(trim($line), true);
            if (! $stats) {
                continue;
            }

            $cpuPercent = (float) str_replace('%', '', $stats['CPUPerc'] ?? '0%');

            $memUsage = $stats['MemUsage'] ?? '0B / 0B';
            $memParts = explode('/', $memUsage);
            $memUsed = trim($memParts[0] ?? '0B');
            $memLimit = trim($memParts[1] ?? '0B');
            $memUsedBytes = $parseMemory($memUsed);
            $memLimitBytes = $parseMemory($memLimit);
            $memPercent = $memLimitBytes > 0 ? round(($memUsedBytes / $memLimitBytes) * 100, 1) : 0;

            $netIO = $stats['NetIO'] ?? '0B / 0B';
            $netParts = explode('/', $netIO);

            $blockIO = $stats['BlockIO'] ?? '0B / 0B';
            $blockParts = explode('/', $blockIO);

            $containers[] = [
                'name' => $stats['Name'] ?? 'unknown',
                'container_id' => $stats['ID'] ?? '',
                'cpu' => ['percent' => $cpuPercent, 'formatted' => $stats['CPUPerc'] ?? '0%'],
                'memory' => [
                    'used' => $memUsed,
                    'limit' => $memLimit,
                    'percent' => $memPercent,
                    'used_bytes' => $memUsedBytes,
                    'limit_bytes' => $memLimitBytes,
                ],
                'network' => ['rx' => trim($netParts[0] ?? '0B'), 'tx' => trim($netParts[1] ?? '0B')],
                'disk' => ['read' => trim($blockParts[0] ?? '0B'), 'write' => trim($blockParts[1] ?? '0B')],
                'pids' => $stats['PIDs'] ?? '0',
            ];
        }

        // Compute aggregated totals
        $totalCpu = array_sum(array_column(array_column($containers, 'cpu'), 'percent'));
        $totalMemUsed = array_sum(array_column(array_column($containers, 'memory'), 'used_bytes'));
        $totalMemLimit = array_sum(array_column(array_column($containers, 'memory'), 'limit_bytes'));

        return response()->json([
            'containers' => $containers,
            'summary' => [
                'cpu_percent' => round($totalCpu, 1),
                'memory_used_bytes' => $totalMemUsed,
                'memory_limit_bytes' => $totalMemLimit,
                'memory_percent' => $totalMemLimit > 0 ? round(($totalMemUsed / $totalMemLimit) * 100, 1) : 0,
                'container_count' => count($containers),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'containers' => [],
        ]);
    }
})->name('services.container-stats.api');

Route::get('/services/{uuid}/settings', function (string $uuid) {
    $service = \App\Models\Service::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    return Inertia::render('Services/Settings', [
        'service' => $service,
    ]);
})->name('services.settings');

Route::get('/services/{uuid}/variables', function (string $uuid) {
    $service = \App\Models\Service::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $variables = $service->environment_variables()->get()->map(fn ($var) => [
        'id' => $var->id,
        'key' => $var->key,
        'value' => $var->value,
        'isSecret' => ! (bool) ($var->is_literal ?? true),
    ]);

    return Inertia::render('Services/Variables', [
        'service' => $service,
        'variables' => $variables,
    ]);
})->name('services.variables');

// Service action routes
Route::post('/services/{uuid}/restart', function (string $uuid) {
    $service = \App\Models\Service::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    Gate::authorize('deploy', $service);

    RestartService::dispatch($service, false);

    return redirect()->back()->with('success', 'Service restart initiated');
})->name('services.restart');

Route::post('/services/{uuid}/stop', function (string $uuid) {
    $service = \App\Models\Service::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    Gate::authorize('stop', $service);

    StopService::dispatch($service);

    return redirect()->back()->with('success', 'Service stopped');
})->name('services.stop');

Route::delete('/services/{uuid}', function (string $uuid, Request $request) {
    $service = \App\Models\Service::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    Gate::authorize('delete', $service);

    $teamId = $service->environment->project->team->id;

    // Stop and delete the service
    StopService::run(service: $service, deleteConnectedNetworks: true, dockerCleanup: true);
    $service->delete();

    // Dispatch event for realtime updates
    \App\Events\ServiceStatusChanged::dispatch($teamId);

    // For all requests - redirect back to services index
    return redirect()->route('services.index')->with('success', 'Service deleted successfully');
})->name('services.destroy');
