<?php

/**
 * Migration routes for Saturn Platform
 *
 * These routes handle environment migration pages.
 * All routes require authentication and email verification.
 */

use App\Models\EnvironmentMigration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/migrations', function (Request $request) {
    $teamId = currentTeam()->id;

    $query = EnvironmentMigration::where('team_id', $teamId)
        ->with([
            'source',
            'sourceEnvironment.project',
            'targetEnvironment',
            'targetServer',
            'requestedBy',
        ])
        ->orderByDesc('created_at');

    if ($request->has('status') && $request->input('status')) {
        $query->where('status', $request->input('status'));
    }

    $migrations = $query->paginate(25);

    return Inertia::render('Migrations/Index', [
        'migrations' => $migrations,
        'statusFilter' => $request->input('status'),
    ]);
})->name('migrations.index');

Route::get('/projects/{projectUuid}/migration-history', function (string $projectUuid) {
    $teamId = currentTeam()->id;

    $project = \App\Models\Project::where('uuid', $projectUuid)
        ->whereRelation('team', 'id', $teamId)
        ->with('environments')
        ->firstOrFail();

    $migrations = EnvironmentMigration::where('team_id', $teamId)
        ->where(function ($q) use ($project) {
            $envIds = $project->environments->pluck('id');
            $q->whereIn('source_environment_id', $envIds)
                ->orWhereIn('target_environment_id', $envIds);
        })
        ->with([
            'source',
            'sourceEnvironment',
            'targetEnvironment',
            'requestedBy',
        ])
        ->orderByDesc('created_at')
        ->limit(100)
        ->get();

    return Inertia::render('Migrations/Timeline', [
        'project' => [
            'id' => $project->id,
            'uuid' => $project->uuid,
            'name' => $project->name,
        ],
        'environments' => $project->environments->map(fn ($env) => [
            'id' => $env->id,
            'name' => $env->name,
            'type' => $env->type ?? 'development',
        ]),
        'migrations' => $migrations,
    ]);
})->name('migrations.timeline');

Route::get('/migrations/{uuid}', function (string $uuid) {
    $teamId = currentTeam()->id;

    $migration = EnvironmentMigration::where('uuid', $uuid)
        ->where('team_id', $teamId)
        ->with([
            'source',
            'target',
            'sourceEnvironment.project',
            'targetEnvironment',
            'targetServer',
            'requestedBy',
            'approvedBy',
            'history',
        ])
        ->firstOrFail();

    // Hide rollback snapshot from frontend
    $migration->makeHidden(['rollback_snapshot']);

    return Inertia::render('Migrations/Show', [
        'migration' => $migration,
        'canApprove' => Gate::allows('approve', $migration),
        'canReject' => Gate::allows('reject', $migration),
        'canRollback' => Gate::allows('rollback', $migration),
    ]);
})->name('migrations.show');
