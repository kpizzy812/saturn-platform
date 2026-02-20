<?php

use App\Models\Application;
use App\Models\InstanceSettings;
use App\Models\Service;
use App\Models\StatusPageResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Admin Status Page Routes
|--------------------------------------------------------------------------
| Manage public status page settings and monitored resources.
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
                'teamName' => $team?->name ?? 'Unknown',
                'status' => $app->status ?? 'unknown',
            ]);
        }

        $services = Service::with('environment.project.team')
            ->select('id', 'uuid', 'name', 'status', 'environment_id')
            ->get();
        foreach ($services as $svc) {
            $team = $svc->environment?->project?->team;
            $availableResources->push([
                'id' => $svc->id,
                'type' => 'App\\Models\\Service',
                'name' => $svc->name,
                'teamName' => $team?->name ?? 'Unknown',
                'status' => $svc->status ?? 'unknown',
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

        return Inertia::render('Admin/StatusPage/Index', [
            'settings' => [
                'is_status_page_enabled' => $settings->is_status_page_enabled ?? false,
                'status_page_title' => $settings->status_page_title ?? '',
                'status_page_description' => $settings->status_page_description ?? '',
            ],
            'availableResources' => $availableResources->values(),
            'configuredResources' => $configuredResources,
        ]);
    })->name('admin.status-page');

    // Update settings
    Route::post('/settings', function (Request $request) {
        $validator = Validator::make($request->all(), [
            'is_status_page_enabled' => 'required|boolean',
            'status_page_title' => 'nullable|string|max:255',
            'status_page_description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $settings = InstanceSettings::get();
        $settings->update($validator->validated());

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

        return back()->with('success', 'Resource added to status page.');
    })->name('admin.status-page.resources.store');

    // Remove resource from status page
    Route::delete('/resources/{id}', function (int $id) {
        $resource = StatusPageResource::findOrFail($id);
        $resource->delete();

        return back()->with('success', 'Resource removed from status page.');
    })->name('admin.status-page.resources.destroy');
});
