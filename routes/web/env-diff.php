<?php

/**
 * Env Diff routes — compare environment variables across environments.
 */

use App\Actions\EnvDiff\CompareEnvironmentsAction;
use App\Models\Environment;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/projects/{uuid}/env-diff', function (string $uuid) {
    $project = Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->with('environments')
        ->firstOrFail();

    return Inertia::render('Projects/EnvDiff', [
        'project' => [
            'id' => $project->id,
            'uuid' => $project->uuid,
            'name' => $project->name,
        ],
        'environments' => $project->environments->map(fn ($env) => [
            'id' => $env->id,
            'uuid' => $env->uuid,
            'name' => $env->name,
            'type' => $env->type ?? 'development',
        ]),
    ]);
})->name('projects.env-diff');

Route::post('/projects/{uuid}/env-diff/compare', function (Request $request, string $uuid) {
    $request->validate([
        'source_env_id' => 'required|integer',
        'target_env_id' => 'required|integer|different:source_env_id',
        'resource_type' => 'nullable|string|in:application,service,database',
    ]);

    $project = Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $source = Environment::where('id', $request->source_env_id)
        ->where('project_id', $project->id)
        ->firstOrFail();

    $target = Environment::where('id', $request->target_env_id)
        ->where('project_id', $project->id)
        ->firstOrFail();

    $action = new CompareEnvironmentsAction;
    $result = $action->handle($source, $target, $request->resource_type);

    return response()->json($result);
})->name('projects.env-diff.compare');
