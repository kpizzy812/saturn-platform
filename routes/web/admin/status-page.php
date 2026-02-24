<?php

use App\Models\Application;
use App\Models\InstanceSettings;
use App\Models\Service;
use App\Models\StatusPageIncident;
use App\Models\StatusPageIncidentUpdate;
use App\Models\StatusPageResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Admin Status Page Routes
|--------------------------------------------------------------------------
| Manage public status page settings, monitored resources, and incidents.
*/

Route::prefix('status-page')->group(function () {
    // Status Page settings & resource management
    Route::get('/', function () {
        $settings = InstanceSettings::get();

        // Get all available resources for selection
        $availableResources = collect();

        $apps = Application::with('environment.project.team')
            ->select('id', 'uuid', 'name', 'status', 'environment_id')
            ->get();
        foreach ($apps as $app) {
            $team = $app->environment?->project?->team;
            $availableResources->push([
                'id' => $app->id,
                'type' => 'App\\Models\\Application',
                'name' => $app->name,
                'teamName' => $team->name ?? 'Unknown',
            ]);
        }

        // Service.status is computed via accessor (no DB column), so don't select it
        $services = Service::with('environment.project.team')
            ->select('id', 'uuid', 'name', 'environment_id')
            ->get();
        foreach ($services as $svc) {
            $team = $svc->environment?->project?->team;
            $availableResources->push([
                'id' => $svc->id,
                'type' => 'App\\Models\\Service',
                'name' => $svc->name,
                'teamName' => $team->name ?? 'Unknown',
            ]);
        }

        $configuredResources = StatusPageResource::orderBy('display_order')->get()->map(fn ($r) => [
            'id' => $r->id,
            'resource_type' => $r->resource_type,
            'resource_id' => $r->resource_id,
            'display_name' => $r->display_name,
            'display_order' => $r->display_order,
            'is_visible' => $r->is_visible,
            'group_name' => $r->group_name,
        ]);

        $incidents = StatusPageIncident::with('updates')
            ->orderByDesc('started_at')
            ->limit(20)
            ->get()
            ->map(function (StatusPageIncident $i) {
                return [
                    'id' => $i->id,
                    'title' => $i->title,
                    'severity' => $i->severity,
                    'status' => $i->status,
                    'started_at' => $i->started_at->toIso8601String(),
                    'resolved_at' => $i->resolved_at?->toIso8601String(),
                    'is_visible' => $i->is_visible,
                    'updates' => $i->updates->map(function (StatusPageIncidentUpdate $u) {
                        return [
                            'id' => $u->id,
                            'status' => $u->status,
                            'message' => $u->message,
                            'posted_at' => $u->posted_at->toIso8601String(),
                        ];
                    }),
                ];
            });

        return Inertia::render('Admin/StatusPage/Index', [
            'settings' => [
                'is_status_page_enabled' => $settings->is_status_page_enabled ?? false,
                'status_page_title' => $settings->status_page_title ?? '',
                'status_page_description' => $settings->status_page_description ?? '',
                'status_page_mode' => $settings->status_page_mode ?? 'auto',
            ],
            'availableResources' => $availableResources->values(),
            'configuredResources' => $configuredResources,
            'incidents' => $incidents,
        ]);
    })->name('admin.status-page');

    // Update settings
    Route::post('/settings', function (Request $request) {
        $validator = Validator::make($request->all(), [
            'is_status_page_enabled' => 'required|boolean',
            'status_page_title' => 'nullable|string|max:255',
            'status_page_description' => 'nullable|string|max:1000',
            'status_page_mode' => 'required|string|in:auto,manual',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $settings = InstanceSettings::get();
        $settings->update($validator->validated());

        // Clear status page cache when settings change
        Cache::forget('status_page_data');

        return back()->with('success', 'Status page settings updated.');
    })->name('admin.status-page.settings.update');

    // Add resource to status page
    Route::post('/resources', function (Request $request) {
        $validator = Validator::make($request->all(), [
            'resource_type' => 'required|string',
            'resource_id' => 'required|integer',
            'display_name' => 'required|string|max:255',
            'group_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $maxOrder = StatusPageResource::max('display_order') ?? 0;

        StatusPageResource::create([
            'team_id' => auth()->user()->currentTeam()->id,
            'resource_type' => $request->resource_type,
            'resource_id' => $request->resource_id,
            'display_name' => $request->display_name,
            'display_order' => $maxOrder + 1,
            'is_visible' => true,
            'group_name' => $request->group_name,
        ]);

        Cache::forget('status_page_data');

        return back()->with('success', 'Resource added to status page.');
    })->name('admin.status-page.resources.store');

    // Remove resource from status page
    Route::delete('/resources/{id}', function (int $id) {
        $resource = StatusPageResource::findOrFail($id);
        $resource->delete();

        Cache::forget('status_page_data');

        return back()->with('success', 'Resource removed from status page.');
    })->name('admin.status-page.resources.destroy');

    // Create incident
    Route::post('/incidents', function (Request $request) {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'severity' => 'required|string|in:minor,major,critical,maintenance',
            'message' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $incident = StatusPageIncident::create([
            'title' => $request->title,
            'severity' => $request->severity,
            'status' => 'investigating',
            'started_at' => now(),
            'is_visible' => true,
        ]);

        StatusPageIncidentUpdate::create([
            'incident_id' => $incident->id,
            'status' => 'investigating',
            'message' => $request->message,
            'posted_at' => now(),
        ]);

        Cache::forget('status_page_data');

        return back()->with('success', 'Incident created.');
    })->name('admin.status-page.incidents.store');

    // Update incident status
    Route::put('/incidents/{id}', function (Request $request, int $id) {
        $incident = StatusPageIncident::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:investigating,identified,monitoring,resolved',
            'message' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $incident->update([
            'status' => $request->status,
            'resolved_at' => $request->status === 'resolved' ? now() : null,
        ]);

        StatusPageIncidentUpdate::create([
            'incident_id' => $incident->id,
            'status' => $request->status,
            'message' => $request->message,
            'posted_at' => now(),
        ]);

        Cache::forget('status_page_data');

        return back()->with('success', 'Incident updated.');
    })->name('admin.status-page.incidents.update');

    // Delete incident
    Route::delete('/incidents/{id}', function (int $id) {
        $incident = StatusPageIncident::findOrFail($id);
        $incident->delete();

        Cache::forget('status_page_data');

        return back()->with('success', 'Incident deleted.');
    })->name('admin.status-page.incidents.destroy');
});
