<?php

/**
 * Admin Applications routes
 *
 * Application management including listing, viewing, restart, stop, start, redeploy, and delete.
 */

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/applications', function () {
    // Fetch all applications across all teams (admin view)
    $applications = \App\Models\Application::with(['environment.project.team', 'destination'])
        ->latest()
        ->paginate(50)
        ->through(function ($app) {
            return [
                'id' => $app->id,
                'uuid' => $app->uuid,
                'name' => $app->name,
                'description' => $app->description,
                'fqdn' => $app->fqdn,
                'status' => $app->status,
                'team_name' => $app->environment?->project?->team?->name ?? 'Unknown',
                'team_id' => $app->environment?->project?->team?->id,
                'created_at' => $app->created_at,
                'updated_at' => $app->updated_at,
            ];
        });

    return Inertia::render('Admin/Applications/Index', [
        'applications' => $applications,
    ]);
})->name('admin.applications.index');

Route::get('/applications/{uuid}', function (string $uuid) {
    // Fetch specific application with all relationships
    $application = \App\Models\Application::with([
        'environment.project.team',
        'destination.server',
    ])->where('uuid', $uuid)->firstOrFail();

    // Get recent deployments
    $recentDeployments = \App\Models\ApplicationDeploymentQueue::where('application_id', $application->id)
        ->orderByDesc('created_at')
        ->limit(10)
        ->get()
        ->map(function ($deployment) {
            return [
                'id' => $deployment->id,
                'deployment_uuid' => $deployment->deployment_uuid,
                'status' => $deployment->status,
                'commit' => $deployment->commit,
                'commit_message' => $deployment->commit_message,
                'triggered_by' => $deployment->triggered_by ?? ($deployment->is_webhook ? 'webhook' : ($deployment->is_api ? 'api' : 'manual')),
                'created_at' => $deployment->created_at,
                'finished_at' => $deployment->updated_at,
            ];
        });

    $server = $application->destination?->server;

    return Inertia::render('Admin/Applications/Show', [
        'application' => [
            'id' => $application->id,
            'uuid' => $application->uuid,
            'name' => $application->name,
            'description' => $application->description,
            'fqdn' => $application->fqdn,
            'status' => $application->status ?? 'unknown',
            'git_repository' => $application->git_repository,
            'git_branch' => $application->git_branch,
            'git_commit_sha' => $application->git_commit_sha,
            'build_pack' => $application->build_pack,
            'dockerfile_location' => $application->dockerfile_location,
            'team_id' => $application->environment?->project?->team?->id,
            'team_name' => $application->environment?->project?->team?->name ?? 'Unknown',
            'project_id' => $application->environment?->project?->id,
            'project_name' => $application->environment?->project?->name ?? 'Unknown',
            'environment_id' => $application->environment?->id,
            'environment_name' => $application->environment?->name ?? 'Unknown',
            'server_id' => $server?->id,
            'server_name' => $server?->name,
            'server_uuid' => $server?->uuid,
            'recent_deployments' => $recentDeployments,
            'created_at' => $application->created_at,
            'updated_at' => $application->updated_at,
        ],
    ]);
})->name('admin.applications.show');

Route::post('/applications/{uuid}/restart', function (string $uuid) {
    $application = \App\Models\Application::where('uuid', $uuid)->firstOrFail();

    try {
        $application->restart();

        return back()->with('success', 'Application restart initiated');
    } catch (\Exception $e) {
        return back()->with('error', 'Failed to restart application: '.$e->getMessage());
    }
})->name('admin.applications.restart');

Route::post('/applications/{uuid}/stop', function (string $uuid) {
    $application = \App\Models\Application::where('uuid', $uuid)->firstOrFail();

    try {
        $application->stop();

        return back()->with('success', 'Application stopped');
    } catch (\Exception $e) {
        return back()->with('error', 'Failed to stop application: '.$e->getMessage());
    }
})->name('admin.applications.stop');

Route::post('/applications/{uuid}/start', function (string $uuid) {
    $application = \App\Models\Application::where('uuid', $uuid)->firstOrFail();

    try {
        $application->restart();

        return back()->with('success', 'Application started');
    } catch (\Exception $e) {
        return back()->with('error', 'Failed to start application: '.$e->getMessage());
    }
})->name('admin.applications.start');

Route::post('/applications/{uuid}/redeploy', function (string $uuid) {
    $application = \App\Models\Application::where('uuid', $uuid)->firstOrFail();

    try {
        queue_application_deployment(
            application: $application,
            deployment_uuid: (string) new \Illuminate\Support\Str,
            force_rebuild: false,
        );

        return back()->with('success', 'Redeploy initiated');
    } catch (\Exception $e) {
        return back()->with('error', 'Failed to redeploy: '.$e->getMessage());
    }
})->name('admin.applications.redeploy');

Route::delete('/applications/{id}', function (int $id) {
    $app = \App\Models\Application::findOrFail($id);
    $appName = $app->name;
    $app->delete();

    return back()->with('success', "Application '{$appName}' deleted");
})->name('admin.applications.delete');
