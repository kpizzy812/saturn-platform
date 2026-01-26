<?php

/**
 * Project routes for Saturn Platform
 *
 * These routes handle project management, environments, and project settings.
 * All routes require authentication and email verification.
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Projects
Route::get('/projects', function () {
    $projects = \App\Models\Project::ownedByCurrentTeam()
        ->with(['environments.applications'])
        ->get();

    return Inertia::render('Projects/Index', [
        'projects' => $projects,
    ]);
})->name('projects.index');

Route::get('/projects/create', function () {
    return Inertia::render('Projects/Create');
})->name('projects.create');

// Sub-routes for project creation flow
Route::get('/projects/create/github', function () {
    // Redirect to applications create with github preset
    return redirect()->route('applications.create', ['source' => 'github']);
})->name('projects.create.github');

Route::get('/projects/create/database', function () {
    return redirect()->route('databases.create');
})->name('projects.create.database');

Route::get('/projects/create/docker', function () {
    // Redirect to applications create with docker preset
    return redirect()->route('applications.create', ['source' => 'docker']);
})->name('projects.create.docker');

Route::get('/projects/create/empty', function () {
    // Create empty project directly
    $project = \App\Models\Project::create([
        'name' => 'New Project',
        'description' => null,
        'team_id' => currentTeam()->id,
    ]);

    // Create default environment
    $project->environments()->create([
        'name' => 'production',
    ]);

    return redirect()->route('projects.show', $project->uuid)
        ->with('success', 'Empty project created');
})->name('projects.create.empty');

Route::get('/projects/create/function', function () {
    return redirect()->route('projects.create')
        ->with('info', 'Functions are coming soon!');
})->name('projects.create.function');

Route::post('/projects', function (Request $request) {
    $request->validate([
        'name' => 'required|string|max:255',
        'description' => 'nullable|string',
    ]);

    $project = \App\Models\Project::create([
        'name' => $request->name,
        'description' => $request->description,
        'team_id' => currentTeam()->id,
    ]);

    // Create default environment
    $project->environments()->create([
        'name' => 'production',
    ]);

    // Return JSON for XHR requests (used by Create Application flow)
    if ($request->wantsJson() || $request->ajax()) {
        $project->load('environments');

        return response()->json($project);
    }

    return redirect()->route('projects.show', $project->uuid);
})->name('projects.store');

Route::get('/projects/{uuid}', function (string $uuid) {
    $project = \App\Models\Project::ownedByCurrentTeam()
        ->with([
            'environments.applications',
            'environments.services',
            // Load all database types (databases() is not a relationship)
            'environments.postgresqls',
            'environments.redis',
            'environments.mongodbs',
            'environments.mysqls',
            'environments.mariadbs',
            'environments.keydbs',
            'environments.dragonflies',
            'environments.clickhouses',
        ])
        ->where('uuid', $uuid)
        ->firstOrFail();

    // Add computed databases to each environment
    $project->environments->each(function ($env) {
        $env->databases = $env->databases();
    });

    return Inertia::render('Projects/Show', [
        'project' => $project,
    ]);
})->name('projects.show');

Route::get('/projects/{uuid}/environments', function (string $uuid) {
    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    return Inertia::render('Projects/Environments', [
        'project' => [
            'id' => $project->id,
            'uuid' => $project->uuid,
            'name' => $project->name,
        ],
    ]);
})->name('projects.environments');

// Project settings page
Route::get('/projects/{uuid}/settings', function (string $uuid) {
    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    // Count resources for delete warning
    $resourcesCount = [
        'applications' => $project->applications()->count(),
        'services' => $project->services()->count(),
        'databases' => $project->postgresqls()->count() +
            $project->mysqls()->count() +
            $project->mariadbs()->count() +
            $project->mongodbs()->count() +
            $project->redis()->count() +
            $project->keydbs()->count() +
            $project->dragonflies()->count() +
            $project->clickhouses()->count(),
    ];
    $totalResources = array_sum($resourcesCount);

    return Inertia::render('Projects/Settings', [
        'project' => [
            'id' => $project->id,
            'uuid' => $project->uuid,
            'name' => $project->name,
            'description' => $project->description,
            'created_at' => $project->created_at,
            'updated_at' => $project->updated_at,
            'is_empty' => $project->isEmpty(),
            'resources_count' => $resourcesCount,
            'total_resources' => $totalResources,
        ],
    ]);
})->name('projects.settings');

// Update project
Route::patch('/projects/{uuid}', function (Request $request, string $uuid) {
    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $request->validate([
        'name' => 'sometimes|required|string|max:255',
        'description' => 'nullable|string',
    ]);

    $project->update($request->only(['name', 'description']));

    return redirect()->back()->with('success', 'Project updated successfully');
})->name('projects.update');

// Delete project
Route::delete('/projects/{uuid}', function (string $uuid) {
    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    if (! $project->isEmpty()) {
        return redirect()->back()->with('error', 'Cannot delete project with active resources. Please remove all applications and databases first.');
    }

    $project->delete();

    return redirect()->route('projects.index')->with('success', 'Project deleted successfully');
})->name('projects.destroy');

// Projects additional routes
Route::get('/projects/{uuid}/local-setup', function (string $uuid) {
    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    return Inertia::render('Projects/LocalSetup', [
        'project' => $project,
    ]);
})->name('projects.local-setup');

Route::get('/projects/{uuid}/variables', function (string $uuid) {
    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    return Inertia::render('Projects/Variables', [
        'project' => $project,
    ]);
})->name('projects.variables');
