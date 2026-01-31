<?php

/**
 * Admin Projects routes
 *
 * Project management including listing, viewing, and deletion.
 */

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/projects', function () {
    // Fetch all projects across all teams (admin view)
    $projects = \App\Models\Project::with(['team', 'environments'])
        ->withCount(['environments'])
        ->latest()
        ->paginate(50)
        ->through(function ($project) {
            $applicationsCount = 0;
            $servicesCount = 0;
            $databasesCount = 0;

            foreach ($project->environments as $env) {
                $applicationsCount += $env->applications()->count();
                $servicesCount += $env->services()->count();
                $databasesCount += $env->databases()->count();
            }

            return [
                'id' => $project->id,
                'uuid' => $project->uuid,
                'name' => $project->name,
                'description' => $project->description,
                'team_id' => $project->team_id,
                'team_name' => $project->team?->name ?? 'Unknown',
                'environments_count' => $project->environments_count,
                'applications_count' => $applicationsCount,
                'services_count' => $servicesCount,
                'databases_count' => $databasesCount,
                'created_at' => $project->created_at,
                'updated_at' => $project->updated_at,
            ];
        });

    return Inertia::render('Admin/Projects/Index', [
        'projects' => $projects,
    ]);
})->name('admin.projects.index');

Route::get('/projects/{id}', function (int $id) {
    // Fetch specific project with all resources
    $project = \App\Models\Project::with(['team', 'environments'])
        ->findOrFail($id);

    $environments = $project->environments->map(function ($env) {
        return [
            'id' => $env->id,
            'uuid' => $env->uuid,
            'name' => $env->name,
            'applications' => $env->applications->map(function ($app) {
                return [
                    'id' => $app->id,
                    'uuid' => $app->uuid,
                    'name' => $app->name,
                    'fqdn' => $app->fqdn,
                    'status' => $app->status ?? 'unknown',
                ];
            }),
            'services' => $env->services->map(function ($service) {
                return [
                    'id' => $service->id,
                    'uuid' => $service->uuid,
                    'name' => $service->name,
                    'status' => 'running',
                ];
            }),
            'databases' => $env->databases()->map(function ($db) {
                return [
                    'id' => $db->id,
                    'uuid' => $db->uuid,
                    'name' => $db->name,
                    'type' => class_basename($db),
                    'status' => method_exists($db, 'status') ? $db->status() : 'unknown',
                ];
            }),
        ];
    });

    return Inertia::render('Admin/Projects/Show', [
        'project' => [
            'id' => $project->id,
            'uuid' => $project->uuid,
            'name' => $project->name,
            'description' => $project->description,
            'team_id' => $project->team_id,
            'team_name' => $project->team?->name ?? 'Unknown',
            'created_at' => $project->created_at,
            'updated_at' => $project->updated_at,
            'environments' => $environments,
        ],
    ]);
})->name('admin.projects.show');

Route::delete('/projects/{id}', function (int $id) {
    $project = \App\Models\Project::findOrFail($id);
    $projectName = $project->name;
    $project->delete();

    return redirect()->route('admin.projects.index')->with('success', "Project '{$projectName}' deleted");
})->name('admin.projects.delete');
