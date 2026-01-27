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

    // Note: default 'production' environment is auto-created in Project::booted()

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
        ->with('environments')
        ->firstOrFail();

    $environments = $project->environments->map(function ($env) {
        return [
            'id' => $env->id,
            'uuid' => $env->uuid,
            'name' => $env->name,
            'created_at' => $env->created_at,
            'updated_at' => $env->updated_at,
            'applications_count' => $env->applications()->count(),
            'services_count' => $env->services()->count(),
            'databases_count' => $env->databases()->count(),
        ];
    });

    return Inertia::render('Projects/Environments', [
        'project' => [
            'id' => $project->id,
            'uuid' => $project->uuid,
            'name' => $project->name,
        ],
        'environments' => $environments,
    ]);
})->name('projects.environments');

// Project settings page
Route::get('/projects/{uuid}/settings', function (string $uuid) {
    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->with(['environments', 'settings.defaultServer'])
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

    // Environments with isEmpty check
    $environments = $project->environments->map(fn ($env) => [
        'id' => $env->id,
        'uuid' => $env->uuid,
        'name' => $env->name,
        'created_at' => $env->created_at,
        'is_empty' => $env->isEmpty(),
    ]);

    // Project-scoped shared variables
    $sharedVariables = $project->environment_variables()
        ->whereNull('environment_id')
        ->get()
        ->map(fn ($var) => [
            'id' => $var->id,
            'key' => $var->key,
            'value' => $var->value,
            'is_shown_once' => $var->is_shown_once,
        ]);

    // Available servers for default server selection
    $servers = \App\Models\Server::ownedByCurrentTeam()->get()
        ->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'ip' => $s->ip,
        ]);

    // Team notification channels status
    $team = currentTeam();
    $notificationChannels = [
        'discord' => [
            'enabled' => $team->discordNotificationSettings->discord_enabled ?? false,
            'configured' => ! empty($team->discordNotificationSettings->discord_webhook_url),
        ],
        'slack' => [
            'enabled' => $team->slackNotificationSettings->slack_enabled ?? false,
            'configured' => ! empty($team->slackNotificationSettings->slack_webhook_url),
        ],
        'telegram' => [
            'enabled' => $team->telegramNotificationSettings->telegram_enabled ?? false,
            'configured' => ! empty($team->telegramNotificationSettings->telegram_token),
        ],
        'email' => [
            'enabled' => $team->emailNotificationSettings->isEnabled() ?? false,
            'configured' => $team->emailNotificationSettings->smtp_enabled
                || $team->emailNotificationSettings->resend_enabled
                || $team->emailNotificationSettings->use_instance_email_settings,
        ],
        'webhook' => [
            'enabled' => $team->webhookNotificationSettings->webhook_enabled ?? false,
            'configured' => ! empty($team->webhookNotificationSettings->webhook_url),
        ],
    ];

    // Team members
    $teamMembers = $team->members->map(fn ($m) => [
        'id' => $m->id,
        'name' => $m->name,
        'email' => $m->email,
        'role' => $m->pivot->role ?? 'member',
    ]);

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
            'default_server_id' => $project->settings?->default_server_id,
        ],
        'environments' => $environments,
        'sharedVariables' => $sharedVariables,
        'servers' => $servers,
        'notificationChannels' => $notificationChannels,
        'teamMembers' => $teamMembers,
        'teamName' => $team->name,
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

Route::post('/projects/{uuid}/environments', function (Request $request, string $uuid) {
    $request->validate([
        'name' => 'required|string|max:255',
    ]);

    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    // Check if environment with this name already exists
    if ($project->environments()->where('name', $request->name)->exists()) {
        return response()->json([
            'message' => 'Environment with this name already exists',
        ], 422);
    }

    $environment = $project->environments()->create([
        'name' => $request->name,
    ]);

    return response()->json($environment);
})->name('projects.environments.store');

// Rename environment
Route::patch('/projects/{uuid}/environments/{env_uuid}', function (Request $request, string $uuid, string $env_uuid) {
    $request->validate([
        'name' => 'required|string|max:255',
    ]);

    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $environment = $project->environments()
        ->where('uuid', $env_uuid)
        ->firstOrFail();

    // Check uniqueness within the project
    if ($project->environments()
        ->where('name', $request->name)
        ->where('id', '!=', $environment->id)
        ->exists()) {
        return response()->json(['message' => 'Environment with this name already exists'], 422);
    }

    $environment->update(['name' => $request->name]);

    return response()->json($environment);
})->name('projects.environments.update');

// Delete environment
Route::delete('/projects/{uuid}/environments/{env_uuid}', function (string $uuid, string $env_uuid) {
    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $environment = $project->environments()
        ->where('uuid', $env_uuid)
        ->firstOrFail();

    if (! $environment->isEmpty()) {
        return response()->json(['message' => 'Environment has resources and cannot be deleted.'], 400);
    }

    $environment->delete();

    return response()->json(['message' => 'Environment deleted.']);
})->name('projects.environments.destroy');

// Project-scoped shared variables CRUD
Route::post('/projects/{uuid}/shared-variables', function (Request $request, string $uuid) {
    $request->validate([
        'key' => 'required|string|max:255',
        'value' => 'required|string',
        'is_shown_once' => 'sometimes|boolean',
    ]);

    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $team = currentTeam();

    if (\App\Models\SharedEnvironmentVariable::where('key', $request->key)
        ->where('project_id', $project->id)
        ->where('team_id', $team->id)
        ->whereNull('environment_id')
        ->exists()) {
        return response()->json(['message' => 'Variable with this key already exists in this project'], 422);
    }

    $variable = \App\Models\SharedEnvironmentVariable::create([
        'key' => $request->key,
        'value' => $request->value,
        'is_shown_once' => $request->is_shown_once ?? false,
        'type' => 'project',
        'team_id' => $team->id,
        'project_id' => $project->id,
    ]);

    return response()->json([
        'id' => $variable->id,
        'key' => $variable->key,
        'value' => $variable->value,
        'is_shown_once' => $variable->is_shown_once,
    ]);
})->name('projects.shared-variables.store');

Route::patch('/projects/{uuid}/shared-variables/{variable_id}', function (Request $request, string $uuid, int $variable_id) {
    $request->validate([
        'key' => 'sometimes|required|string|max:255',
        'value' => 'sometimes|required|string',
    ]);

    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $variable = \App\Models\SharedEnvironmentVariable::where('id', $variable_id)
        ->where('project_id', $project->id)
        ->firstOrFail();

    $variable->update($request->only(['key', 'value']));

    return response()->json([
        'id' => $variable->id,
        'key' => $variable->key,
        'value' => $variable->value,
        'is_shown_once' => $variable->is_shown_once,
    ]);
})->name('projects.shared-variables.update');

Route::delete('/projects/{uuid}/shared-variables/{variable_id}', function (string $uuid, int $variable_id) {
    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $variable = \App\Models\SharedEnvironmentVariable::where('id', $variable_id)
        ->where('project_id', $project->id)
        ->firstOrFail();

    $variable->delete();

    return response()->json(['message' => 'Variable deleted.']);
})->name('projects.shared-variables.destroy');

// Update default server
Route::patch('/projects/{uuid}/settings/default-server', function (Request $request, string $uuid) {
    $request->validate([
        'default_server_id' => 'nullable|integer|exists:servers,id',
    ]);

    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    // Verify server belongs to the team
    if ($request->default_server_id) {
        \App\Models\Server::ownedByCurrentTeam()
            ->where('id', $request->default_server_id)
            ->firstOrFail();
    }

    $project->settings()->update([
        'default_server_id' => $request->default_server_id,
    ]);

    return redirect()->back()->with('success', 'Default server updated.');
})->name('projects.settings.default-server');
