<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Volumes Routes
|--------------------------------------------------------------------------
|
| Routes for managing persistent volumes attached to applications and services.
|
*/

Route::get('/volumes', function () {
    // Collect resource IDs for team's applications, services, and databases
    $applicationIds = \App\Models\Application::ownedByCurrentTeam()->pluck('id');
    $serviceAppIds = \App\Models\ServiceApplication::whereIn('service_id',
        \App\Models\Service::ownedByCurrentTeam()->pluck('id')
    )->pluck('id');
    $serviceDbIds = \App\Models\ServiceDatabase::whereIn('service_id',
        \App\Models\Service::ownedByCurrentTeam()->pluck('id')
    )->pluck('id');

    $volumes = \App\Models\LocalPersistentVolume::where(function ($q) use ($applicationIds, $serviceAppIds, $serviceDbIds) {
        $q->where(function ($q) use ($applicationIds) {
            $q->where('resource_type', 'App\\Models\\Application')
                ->whereIn('resource_id', $applicationIds);
        })->orWhere(function ($q) use ($serviceAppIds) {
            $q->where('resource_type', 'App\\Models\\ServiceApplication')
                ->whereIn('resource_id', $serviceAppIds);
        })->orWhere(function ($q) use ($serviceDbIds) {
            $q->where('resource_type', 'App\\Models\\ServiceDatabase')
                ->whereIn('resource_id', $serviceDbIds);
        });
    })->get()->map(fn ($vol) => [
        'id' => $vol->id,
        'uuid' => $vol->id,
        'name' => $vol->name ?? $vol->mount_path,
        'mountPath' => $vol->mount_path,
        'hostPath' => $vol->host_path,
        'resourceType' => class_basename($vol->resource_type),
        'resourceId' => $vol->resource_id,
        'created_at' => $vol->created_at?->toISOString(),
    ]);

    return Inertia::render('Volumes/Index', [
        'volumes' => $volumes,
    ]);
})->name('volumes.index');

Route::get('/volumes/create', function () {
    $applications = \App\Models\Application::ownedByCurrentTeam()
        ->select('id', 'uuid', 'name')
        ->get()
        ->map(fn ($app) => ['uuid' => $app->uuid, 'name' => $app->name, 'type' => 'application']);
    $services = \App\Models\Service::ownedByCurrentTeam()
        ->select('id', 'uuid', 'name')
        ->get()
        ->map(fn ($svc) => ['uuid' => $svc->uuid, 'name' => $svc->name, 'type' => 'service']);

    return Inertia::render('Volumes/Create', [
        'services' => $applications->merge($services)->values(),
    ]);
})->name('volumes.create');

Route::post('/volumes', function (Request $request) {
    if (! in_array(auth()->user()->role(), ['owner', 'admin', 'developer'])) {
        abort(403, 'You do not have permission to create volumes.');
    }

    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'mount_path' => 'required|string',
        'host_path' => 'nullable|string',
        'resource_uuid' => 'required|string',
        'resource_type' => 'required|string|in:application,service',
    ]);

    $resource = null;
    if ($validated['resource_type'] === 'application') {
        $resource = \App\Models\Application::ownedByCurrentTeam()
            ->where('uuid', $validated['resource_uuid'])->firstOrFail();
        $resourceType = \App\Models\Application::class;
    } else {
        $resource = \App\Models\Service::ownedByCurrentTeam()
            ->where('uuid', $validated['resource_uuid'])->firstOrFail();
        $resourceType = \App\Models\Service::class;
    }

    \App\Models\LocalPersistentVolume::create([
        'name' => $validated['name'],
        'mount_path' => $validated['mount_path'],
        'host_path' => $validated['host_path'] ?? null,
        'resource_type' => $resourceType,
        'resource_id' => $resource->id,
    ]);

    return redirect()->route('volumes.index')->with('success', 'Volume created successfully');
})->name('volumes.store');

Route::get('/volumes/{id}', function (string $id) {
    // LocalPersistentVolume has no uuid field, use id
    $applicationIds = \App\Models\Application::ownedByCurrentTeam()->pluck('id');
    $serviceAppIds = \App\Models\ServiceApplication::whereIn('service_id',
        \App\Models\Service::ownedByCurrentTeam()->pluck('id')
    )->pluck('id');
    $serviceDbIds = \App\Models\ServiceDatabase::whereIn('service_id',
        \App\Models\Service::ownedByCurrentTeam()->pluck('id')
    )->pluck('id');

    $vol = \App\Models\LocalPersistentVolume::where('id', $id)
        ->where(function ($q) use ($applicationIds, $serviceAppIds, $serviceDbIds) {
            $q->where(function ($q) use ($applicationIds) {
                $q->where('resource_type', 'App\\Models\\Application')
                    ->whereIn('resource_id', $applicationIds);
            })->orWhere(function ($q) use ($serviceAppIds) {
                $q->where('resource_type', 'App\\Models\\ServiceApplication')
                    ->whereIn('resource_id', $serviceAppIds);
            })->orWhere(function ($q) use ($serviceDbIds) {
                $q->where('resource_type', 'App\\Models\\ServiceDatabase')
                    ->whereIn('resource_id', $serviceDbIds);
            });
        })->with('resource')->firstOrFail();

    $volume = [
        'id' => $vol->id,
        'uuid' => (string) $vol->id,
        'name' => $vol->name ?? $vol->mount_path,
        'description' => null,
        'size' => 0,
        'used' => 0,
        'status' => 'active',
        'storage_class' => 'standard',
        'mount_path' => $vol->mount_path,
        'host_path' => $vol->host_path,
        'attached_services' => $vol->resource ? [[
            'id' => $vol->resource->id ?? 0,
            'name' => $vol->resource->name ?? 'Unknown',
            'type' => class_basename($vol->resource_type),
        ]] : [],
        'created_at' => $vol->created_at?->toISOString(),
        'updated_at' => $vol->updated_at?->toISOString(),
    ];

    return Inertia::render('Volumes/Show', [
        'volume' => $volume,
        'snapshots' => [],
    ]);
})->name('volumes.show');
