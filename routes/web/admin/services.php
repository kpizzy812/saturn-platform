<?php

/**
 * Admin Services routes
 *
 * Service management including listing, viewing, restart, stop, start, and deletion.
 */

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/services', function (\Illuminate\Http\Request $request) {
    $search = $request->get('search');
    $status = $request->get('status');
    $page = max(1, (int) $request->get('page', 1));
    $perPage = 50;

    $query = \App\Models\Service::with(['environment.project.team', 'server', 'applications'])
        ->latest();

    if ($search) {
        $query->where(function ($q) use ($search) {
            $q->where('name', 'ilike', "%{$search}%")
                ->orWhereHas('environment.project.team', function ($tq) use ($search) {
                    $tq->where('name', 'ilike', "%{$search}%");
                })
                ->orWhereHas('environment.project', function ($pq) use ($search) {
                    $pq->where('name', 'ilike', "%{$search}%");
                });
        });
    }

    // Services don't have a status column - status is computed from applications
    // So we need to load, map, then filter by status in PHP
    $allFiltered = $query->get();

    $stats = ['runningCount' => 0, 'stoppedCount' => 0, 'errorCount' => 0];
    $mapped = $allFiltered->map(function ($service) use (&$stats) {
        $serviceStatus = $service->status();

        // Compute stats before status filter
        if (str_starts_with($serviceStatus, 'running')) {
            $stats['runningCount']++;
        } elseif (str_starts_with($serviceStatus, 'stopped') || str_starts_with($serviceStatus, 'exited')) {
            $stats['stoppedCount']++;
        } elseif (str_starts_with($serviceStatus, 'error') || str_starts_with($serviceStatus, 'degraded')) {
            $stats['errorCount']++;
        }

        return [
            'id' => $service->id,
            'uuid' => $service->uuid,
            'name' => $service->name,
            'description' => $service->description,
            'status' => $serviceStatus,
            'service_type' => $service->service_type ?? null,
            'team_name' => $service->environment?->project?->team?->name ?? 'Unknown',
            'team_id' => $service->environment?->project?->team?->id,
            'project_name' => $service->environment?->project?->name,
            'environment_name' => $service->environment?->name,
            'server_name' => $service->server?->name,
            'created_at' => $service->created_at,
            'updated_at' => $service->updated_at,
        ];
    });

    // Apply status filter after computing stats
    if ($status && $status !== 'all') {
        $mapped = $mapped->filter(function ($service) use ($status) {
            return str_starts_with($service['status'], $status);
        });
    }

    $sorted = $mapped->values();
    $total = $sorted->count();
    $lastPage = max(1, (int) ceil($total / $perPage));
    $paginatedItems = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

    return Inertia::render('Admin/Services/Index', [
        'services' => [
            'data' => $paginatedItems,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $lastPage,
        ],
        'stats' => $stats,
        'filters' => [
            'search' => $search,
            'status' => $status ?? 'all',
        ],
    ]);
})->name('admin.services.index');

Route::get('/services/{uuid}', function (string $uuid) {
    // Fetch specific service with all relationships
    $service = \App\Models\Service::with([
        'environment.project.team',
        'server',
        'applications',
        'databases',
    ])->where('uuid', $uuid)->firstOrFail();

    return Inertia::render('Admin/Services/Show', [
        'service' => [
            'id' => $service->id,
            'uuid' => $service->uuid,
            'name' => $service->name,
            'description' => $service->description,
            'status' => $service->status() ?? 'unknown',
            'service_type' => $service->service_type ?? null,
            'team_id' => $service->environment?->project?->team?->id,
            'team_name' => $service->environment?->project?->team?->name ?? 'Unknown',
            'project_id' => $service->environment?->project?->id,
            'project_name' => $service->environment?->project?->name ?? 'Unknown',
            'environment_id' => $service->environment?->id,
            'environment_name' => $service->environment?->name ?? 'Unknown',
            'server_id' => $service->server?->id,
            'server_name' => $service->server?->name,
            'server_uuid' => $service->server?->uuid,
            'applications' => $service->applications->map(function ($app) {
                return [
                    'id' => $app->id,
                    'uuid' => $app->uuid ?? '',
                    'name' => $app->name,
                    'fqdn' => $app->fqdn ?? null,
                    'status' => $app->status ?? 'unknown',
                ];
            }),
            'databases' => $service->databases->map(function ($db) {
                return [
                    'id' => $db->id,
                    'uuid' => $db->uuid ?? '',
                    'name' => $db->name,
                    'type' => class_basename($db),
                    'status' => method_exists($db, 'status') ? $db->status() : 'unknown',
                ];
            }),
            'created_at' => $service->created_at,
            'updated_at' => $service->updated_at,
        ],
    ]);
})->name('admin.services.show');

Route::post('/services/{uuid}/restart', function (string $uuid) {
    $service = \App\Models\Service::where('uuid', $uuid)->firstOrFail();

    try {
        $service->parse();
        $activity = \App\Actions\Service\RestartService::run($service);

        return back()->with('success', 'Service restart initiated');
    } catch (\Exception $e) {
        return back()->with('error', 'Failed to restart service: '.$e->getMessage());
    }
})->name('admin.services.restart');

Route::post('/services/{uuid}/stop', function (string $uuid) {
    $service = \App\Models\Service::where('uuid', $uuid)->firstOrFail();

    try {
        $service->parse();
        \App\Actions\Service\StopService::run($service);

        return back()->with('success', 'Service stopped');
    } catch (\Exception $e) {
        return back()->with('error', 'Failed to stop service: '.$e->getMessage());
    }
})->name('admin.services.stop');

Route::post('/services/{uuid}/start', function (string $uuid) {
    $service = \App\Models\Service::where('uuid', $uuid)->firstOrFail();

    try {
        $service->parse();
        $activity = \App\Actions\Service\StartService::run($service);

        return back()->with('success', 'Service started');
    } catch (\Exception $e) {
        return back()->with('error', 'Failed to start service: '.$e->getMessage());
    }
})->name('admin.services.start');

Route::delete('/services/{id}', function (int $id) {
    $service = \App\Models\Service::findOrFail($id);
    $serviceName = $service->name;
    $service->delete();

    return back()->with('success', "Service '{$serviceName}' deleted");
})->name('admin.services.delete');
