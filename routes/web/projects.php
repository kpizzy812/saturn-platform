<?php

/**
 * Project routes for Saturn Platform
 *
 * These routes handle project management, environments, and project settings.
 * All routes require authentication and email verification.
 */

use App\Models\Environment;
use App\Services\Authorization\ProjectAuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Projects
Route::get('/projects', function () {
    $projects = \App\Models\Project::ownedByCurrentTeam()
        ->with([
            'environments.applications',
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
        ->get();

    $currentUser = auth()->user();
    $authService = app(ProjectAuthorizationService::class);

    // Filter environments visible to user (hide production from developers)
    $projects->each(function ($project) use ($currentUser, $authService) {
        $project->setRelation(
            'environments',
            $authService->filterVisibleEnvironments($currentUser, $project, $project->environments)
        );
        // Add computed databases to each environment
        $project->environments->each(function ($env) {
            $env->databases = $env->databases();
        });
    });

    return Inertia::render('Projects/Index', [
        'projects' => $projects,
    ]);
})->name('projects.index');

Route::get('/projects/create', function () {
    return Inertia::render('Projects/Create');
})->name('projects.create');

// Sub-routes for project creation flow
Route::get('/projects/create/git', function () {
    // Redirect to applications create - Step 1 will show Git provider selection
    return redirect()->route('applications.create');
})->name('projects.create.git');

Route::get('/projects/create/database', function () {
    return redirect()->route('databases.create');
})->name('projects.create.database');

Route::get('/projects/create/docker', function () {
    // Redirect to applications create with docker preset
    return redirect()->route('applications.create', ['source' => 'docker']);
})->name('projects.create.docker');

Route::get('/projects/create/empty', function () {
    // Create empty project directly
    // Note: Default environments (development, uat, production) are created
    // automatically in Project::booted() - no need to create them here
    $project = \App\Models\Project::create([
        'name' => 'New Project',
        'description' => null,
        'team_id' => currentTeam()->id,
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
            'environments.services.applications',
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

    // Get user's role in this project
    $currentUser = auth()->user();
    $userRole = $currentUser->roleInProject($project);
    $canManageEnvironments = in_array($userRole, ['owner', 'admin']);

    // Filter environments visible to user (hide production from developers)
    $authService = app(ProjectAuthorizationService::class);
    $project->setRelation(
        'environments',
        $authService->filterVisibleEnvironments($currentUser, $project, $project->environments)
    );

    // Add computed databases to each environment
    $project->environments->each(function ($env) {
        $env->databases = $env->databases();
    });

    return Inertia::render('Projects/Show', [
        'project' => $project,
        'userRole' => $userRole ?? 'member',
        'canManageEnvironments' => $canManageEnvironments,
    ]);
})->name('projects.show');

Route::get('/projects/{uuid}/environments', function (string $uuid) {
    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->with('environments')
        ->firstOrFail();

    // Filter environments visible to user (hide production from developers)
    $currentUser = auth()->user();
    $authService = app(ProjectAuthorizationService::class);
    $visibleEnvironments = $authService->filterVisibleEnvironments($currentUser, $project, $project->environments);

    $environments = $visibleEnvironments->map(function ($env) {
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
        ->with(['environments', 'settings.defaultServer', 'tags', 'notificationOverrides'])
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

    // Filter environments visible to user (hide production from developers)
    $currentUser = auth()->user();
    $authService = app(ProjectAuthorizationService::class);
    $visibleEnvironments = $authService->filterVisibleEnvironments($currentUser, $project, $project->environments);

    // Environments with isEmpty check
    $environments = $visibleEnvironments->map(fn ($env) => [
        'id' => $env->id,
        'uuid' => $env->uuid,
        'name' => $env->name,
        'created_at' => $env->created_at,
        'is_empty' => $env->isEmpty(),
        'default_git_branch' => $env->default_git_branch,
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

    // Quotas
    $quotaService = new \App\Services\ProjectQuotaService;
    $quotas = $quotaService->getUsage($project);

    // Deployment defaults (git branch is per-environment, not project-level)
    $deploymentDefaults = [
        'default_build_pack' => $project->settings?->default_build_pack,
        'default_auto_deploy' => $project->settings?->default_auto_deploy,
        'default_force_https' => $project->settings?->default_force_https,
        'default_preview_deployments' => $project->settings?->default_preview_deployments,
        'default_auto_rollback' => $project->settings?->default_auto_rollback,
    ];

    // Tags
    $projectTags = $project->tags->map(fn ($tag) => [
        'id' => $tag->id,
        'name' => $tag->name,
    ]);

    $availableTags = \App\Models\Tag::ownedByCurrentTeam()->get()
        ->map(fn ($tag) => [
            'id' => $tag->id,
            'name' => $tag->name,
        ]);

    // User's teams for transfer feature
    $userTeams = $currentUser->teams()
        ->where('team_id', '!=', $team->id)
        ->get()
        ->map(fn ($t) => [
            'id' => $t->id,
            'name' => $t->name,
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
            'is_archived' => $project->is_archived,
            'archived_at' => $project->archived_at,
        ],
        'environments' => $environments,
        'sharedVariables' => $sharedVariables,
        'servers' => $servers,
        'notificationChannels' => $notificationChannels,
        'teamMembers' => $teamMembers,
        'teamName' => $team->name,
        'projectTags' => $projectTags,
        'availableTags' => $availableTags,
        'userTeams' => $userTeams,
        'quotas' => $quotas,
        'deploymentDefaults' => $deploymentDefaults,
        'notificationOverrides' => [
            'deployment_success' => $project->notificationOverrides?->deployment_success,
            'deployment_failure' => $project->notificationOverrides?->deployment_failure,
            'backup_success' => $project->notificationOverrides?->backup_success,
            'backup_failure' => $project->notificationOverrides?->backup_failure,
            'status_change' => $project->notificationOverrides?->status_change,
            'custom_discord_webhook' => $project->notificationOverrides?->custom_discord_webhook ? '***' : null,
            'custom_slack_webhook' => $project->notificationOverrides?->custom_slack_webhook ? '***' : null,
            'custom_webhook_url' => $project->notificationOverrides?->custom_webhook_url ? '***' : null,
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

// Delete project (with cascade deletion of all resources)
Route::delete('/projects/{uuid}', function (string $uuid) {
    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->with([
            'environments.applications',
            'environments.services',
            'environments.postgresqls',
            'environments.redis',
            'environments.mongodbs',
            'environments.mysqls',
            'environments.mariadbs',
            'environments.keydbs',
            'environments.dragonflies',
            'environments.clickhouses',
        ])
        ->firstOrFail();

    // Delete all resources in all environments
    foreach ($project->environments as $environment) {
        // Delete applications
        foreach ($environment->applications as $application) {
            \App\Jobs\DeleteResourceJob::dispatch(
                resource: $application,
                deleteVolumes: true,
                deleteConnectedNetworks: true,
                deleteConfigurations: true,
                dockerCleanup: true
            );
        }

        // Delete all database types
        $databases = collect()
            ->merge($environment->postgresqls)
            ->merge($environment->redis)
            ->merge($environment->mongodbs)
            ->merge($environment->mysqls)
            ->merge($environment->mariadbs)
            ->merge($environment->keydbs)
            ->merge($environment->dragonflies)
            ->merge($environment->clickhouses);

        foreach ($databases as $database) {
            \App\Jobs\DeleteResourceJob::dispatch(
                resource: $database,
                deleteVolumes: true,
                deleteConnectedNetworks: true,
                deleteConfigurations: true,
                dockerCleanup: true
            );
        }

        // Delete services
        foreach ($environment->services as $service) {
            \App\Jobs\DeleteResourceJob::dispatch(
                resource: $service,
                deleteVolumes: true,
                deleteConnectedNetworks: true,
                deleteConfigurations: true,
                dockerCleanup: true
            );
        }
    }

    $project->delete();

    return redirect()->route('projects.index')->with('success', 'Project and all resources scheduled for deletion');
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

    // Check authorization: only owner/admin can create environments
    if (auth()->user()->cannot('create', [Environment::class, $project])) {
        return response()->json([
            'message' => 'You do not have permission to create environments',
        ], 403);
    }

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

    // Check authorization: only owner/admin can update environments
    if (auth()->user()->cannot('update', $environment)) {
        return response()->json(['message' => 'You do not have permission to update environments'], 403);
    }

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

    // Check authorization: only owner/admin can delete environments
    if (auth()->user()->cannot('delete', $environment)) {
        return response()->json(['message' => 'You do not have permission to delete environments'], 403);
    }

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

// Export project configuration
Route::get('/projects/{uuid}/export', function (string $uuid, Request $request) {
    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $includeSecrets = $request->boolean('include_secrets') && auth()->user()->isSuperAdmin();

    $exporter = new \App\Actions\Project\ExportProjectAction;
    $data = $exporter->execute($project, $includeSecrets);

    $filename = 'project-'.str($project->name)->slug().'-'.now()->format('Y-m-d').'.json';

    return response()->json($data)
        ->header('Content-Disposition', "attachment; filename=\"{$filename}\"")
        ->header('Content-Type', 'application/json');
})->name('projects.export');

// Clone project
Route::post('/projects/{uuid}/clone', function (Request $request, string $uuid) {
    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $request->validate([
        'name' => 'required|string|max:255',
        'clone_shared_vars' => 'boolean',
        'clone_tags' => 'boolean',
        'clone_settings' => 'boolean',
    ]);

    $cloner = new \App\Actions\Project\CloneProjectAction;
    $newProject = $cloner->execute(
        $project,
        $request->name,
        $request->boolean('clone_shared_vars', false),
        $request->boolean('clone_tags', false),
        $request->boolean('clone_settings', false),
    );

    return redirect()->route('projects.settings', $newProject->uuid)
        ->with('success', "Project cloned as '{$newProject->name}'");
})->name('projects.clone');

// Transfer project to another team
Route::post('/projects/{uuid}/transfer', function (Request $request, string $uuid) {
    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $request->validate([
        'target_team_id' => 'required|integer|exists:teams,id',
    ]);

    $user = auth()->user();
    $targetTeamId = $request->target_team_id;

    // Verify user is member of target team
    if (! $user->teams()->where('team_id', $targetTeamId)->exists()) {
        return response()->json(['message' => 'You must be a member of the target team'], 403);
    }

    // Verify user is owner/admin in current project
    $userRole = $user->roleInProject($project);
    if (! $userRole) {
        $teamMembership = $user->teamMembership(currentTeam()->id);
        $userRole = $teamMembership?->role;
    }
    if (! in_array($userRole, ['owner', 'admin'])) {
        return response()->json(['message' => 'Only project owners or admins can transfer projects'], 403);
    }

    // Transfer
    $project->update(['team_id' => $targetTeamId]);

    // Clear project-level members (they may not be in the new team)
    $project->members()->detach();

    // Audit log
    $project->audit('transfer', "Project transferred to team #{$targetTeamId}");

    return redirect()->route('projects.index')
        ->with('success', 'Project transferred successfully');
})->name('projects.transfer');

// Archive project
Route::post('/projects/{uuid}/archive', function (string $uuid) {
    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    if ($project->is_archived) {
        return response()->json(['message' => 'Project is already archived'], 422);
    }

    $project->update([
        'is_archived' => true,
        'archived_at' => now(),
        'archived_by' => auth()->id(),
    ]);

    return redirect()->back()->with('success', 'Project archived successfully');
})->name('projects.archive');

// Unarchive project
Route::post('/projects/{uuid}/unarchive', function (string $uuid) {
    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    if (! $project->is_archived) {
        return response()->json(['message' => 'Project is not archived'], 422);
    }

    $project->update([
        'is_archived' => false,
        'archived_at' => null,
        'archived_by' => null,
    ]);

    return redirect()->back()->with('success', 'Project unarchived successfully');
})->name('projects.unarchive');

// Attach tag to project
Route::post('/projects/{uuid}/tags', function (Request $request, string $uuid) {
    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $request->validate([
        'tag_id' => 'nullable|integer|exists:tags,id',
        'name' => 'nullable|string|max:255',
    ]);

    $team = currentTeam();

    if ($request->tag_id) {
        // Attach existing tag (verify it belongs to this team)
        $tag = \App\Models\Tag::where('id', $request->tag_id)
            ->where('team_id', $team->id)
            ->firstOrFail();
    } elseif ($request->name) {
        // Create or find tag by name for this team
        $tag = \App\Models\Tag::firstOrCreate(
            ['name' => strtolower(trim($request->name)), 'team_id' => $team->id],
        );
    } else {
        return response()->json(['message' => 'Either tag_id or name is required'], 422);
    }

    // Avoid duplicate attachment
    if (! $project->tags()->where('tag_id', $tag->id)->exists()) {
        $project->tags()->attach($tag->id);
    }

    return response()->json([
        'id' => $tag->id,
        'name' => $tag->name,
    ]);
})->name('projects.tags.store');

// Detach tag from project
Route::delete('/projects/{uuid}/tags/{tagId}', function (string $uuid, int $tagId) {
    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $project->tags()->detach($tagId);

    return response()->json(['message' => 'Tag removed']);
})->name('projects.tags.destroy');

// Update project notification overrides
Route::patch('/projects/{uuid}/notification-overrides', function (Request $request, string $uuid) {
    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $request->validate([
        'deployment_success' => 'nullable|boolean',
        'deployment_failure' => 'nullable|boolean',
        'backup_success' => 'nullable|boolean',
        'backup_failure' => 'nullable|boolean',
        'status_change' => 'nullable|boolean',
        'custom_discord_webhook' => 'nullable|url|max:500',
        'custom_slack_webhook' => 'nullable|url|max:500',
        'custom_webhook_url' => 'nullable|url|max:500',
    ]);

    \App\Models\ProjectNotificationOverride::updateOrCreate(
        ['project_id' => $project->id],
        $request->only([
            'deployment_success', 'deployment_failure',
            'backup_success', 'backup_failure', 'status_change',
            'custom_discord_webhook', 'custom_slack_webhook', 'custom_webhook_url',
        ])
    );

    return redirect()->back()->with('success', 'Notification overrides updated.');
})->name('projects.notification-overrides');

// Update project quotas
Route::patch('/projects/{uuid}/settings/quotas', function (Request $request, string $uuid) {
    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $request->validate([
        'max_applications' => 'nullable|integer|min:0',
        'max_services' => 'nullable|integer|min:0',
        'max_databases' => 'nullable|integer|min:0',
        'max_environments' => 'nullable|integer|min:0',
    ]);

    $project->settings()->update($request->only([
        'max_applications', 'max_services', 'max_databases', 'max_environments',
    ]));

    return redirect()->back()->with('success', 'Resource limits updated.');
})->name('projects.settings.quotas');

// Update deployment defaults
Route::patch('/projects/{uuid}/settings/deployment-defaults', function (Request $request, string $uuid) {
    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $request->validate([
        'default_build_pack' => 'nullable|string|in:nixpacks,dockerfile,dockerimage,dockercompose,static',
        'default_auto_deploy' => 'nullable|boolean',
        'default_force_https' => 'nullable|boolean',
        'default_preview_deployments' => 'nullable|boolean',
        'default_auto_rollback' => 'nullable|boolean',
    ]);

    $project->settings()->update($request->only([
        'default_build_pack', 'default_auto_deploy',
        'default_force_https', 'default_preview_deployments', 'default_auto_rollback',
    ]));

    return redirect()->back()->with('success', 'Deployment defaults updated.');
})->name('projects.settings.deployment-defaults');

// Update per-environment git branches
Route::patch('/projects/{uuid}/settings/environment-branches', function (Request $request, string $uuid) {
    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $request->validate([
        'branches' => 'required|array',
        'branches.*.environment_id' => 'required|integer',
        'branches.*.branch' => 'nullable|string|max:255',
    ]);

    foreach ($request->input('branches', []) as $item) {
        $env = $project->environments()->where('id', $item['environment_id'])->first();
        if ($env) {
            $env->update(['default_git_branch' => $item['branch'] ?: null]);
        }
    }

    return redirect()->back()->with('success', 'Environment branches updated.');
})->name('projects.settings.environment-branches');

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

// Environment settings page
Route::get('/environments/{uuid}/settings', function (string $uuid) {
    $environment = \App\Models\Environment::where('uuid', $uuid)
        ->whereHas('project', function ($query) {
            $query->whereRelation('team', 'id', currentTeam()->id);
        })
        ->with('project')
        ->firstOrFail();

    $currentUser = auth()->user();
    $userRole = $currentUser->roleInProject($environment->project);

    // Check if user can manage environment settings (owner/admin only)
    $canManage = in_array($userRole, ['owner', 'admin']);

    return Inertia::render('Environments/Settings', [
        'environment' => [
            'id' => $environment->id,
            'uuid' => $environment->uuid,
            'name' => $environment->name,
            'type' => $environment->type ?? 'development',
            'requires_approval' => $environment->requires_approval ?? false,
        ],
        'project' => [
            'id' => $environment->project->id,
            'uuid' => $environment->project->uuid,
            'name' => $environment->project->name,
        ],
        'canManage' => $canManage,
        'userRole' => $userRole ?? 'member',
    ]);
})->name('environments.settings');

// Project members management
Route::get('/projects/{uuid}/members', function (string $uuid) {
    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $currentUser = auth()->user();
    $team = currentTeam();

    // Get project-specific members (explicitly added to project)
    $projectMembers = $project->members()
        ->get()
        ->map(fn ($m) => [
            'id' => $m->id,
            'name' => $m->name,
            'email' => $m->email,
            'role' => $m->pivot->role,
            'access_type' => 'project', // Direct project membership
            'has_team_access' => false,
        ]);

    $projectMemberIds = $projectMembers->pluck('id')->toArray();

    // Get all team members
    $allTeamMembers = $team->members()
        ->get()
        ->map(function ($member) use ($team, $project, $projectMemberIds) {
            // Skip if already a project member
            if (in_array($member->id, $projectMemberIds)) {
                return null;
            }

            $teamUser = $member->teamMembership($team->id);
            $teamRole = $teamUser?->role ?? 'member';

            // Check team-level access to this project
            $hasTeamAccess = false;
            if ($teamRole === 'owner') {
                // Owners always have full access
                $hasTeamAccess = true;
            } elseif ($teamUser) {
                // Check allowed_projects for other roles
                $hasTeamAccess = $teamUser->canAccessProject($project->id);
            }

            return [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $teamRole,
                'access_type' => 'team', // Team-level access
                'has_team_access' => $hasTeamAccess,
            ];
        })
        ->filter()
        ->values();

    // Combine project members and team members
    $allMembers = $projectMembers->concat($allTeamMembers)
        ->sortBy('name')
        ->values();

    // Available team members to add (those not already in project)
    $availableTeamMembers = $team->members()
        ->whereNotIn('users.id', $projectMemberIds)
        ->get()
        ->map(fn ($m) => [
            'id' => $m->id,
            'name' => $m->name,
            'email' => $m->email,
        ]);

    // Get current user's role in the project
    // First check project-level role, then fall back to team role for owners/admins
    $currentUserRole = $currentUser->roleInProject($project);

    // If no project role, check team role - team owners/admins can manage project members
    if (! $currentUserRole) {
        $teamMembership = $currentUser->teamMembership($team->id);
        $teamRole = $teamMembership?->role;

        // Team owners and admins get equivalent project permissions
        if ($teamRole === 'owner' || $teamRole === 'admin') {
            $currentUserRole = $teamRole;
        }
    }

    return Inertia::render('Projects/Members', [
        'project' => [
            'id' => $project->id,
            'uuid' => $project->uuid,
            'name' => $project->name,
        ],
        'members' => $allMembers,
        'availableTeamMembers' => $availableTeamMembers,
        'currentUserRole' => $currentUserRole,
        'currentUserId' => $currentUser->id,
    ]);
})->name('projects.members');

// Add member to project
Route::post('/projects/{uuid}/members', function (Request $request, string $uuid) {
    $request->validate([
        'user_id' => 'required|integer|exists:users,id',
        'role' => 'required|in:owner,admin,developer,member,viewer',
    ]);

    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $currentUser = auth()->user();
    $team = currentTeam();
    $currentUserRole = $currentUser->roleInProject($project);

    // Fallback to team role - team owners/admins can manage project members
    if (! $currentUserRole) {
        $teamMembership = $currentUser->teamMembership($team->id);
        $teamRole = $teamMembership?->role;

        if ($teamRole === 'owner' || $teamRole === 'admin') {
            $currentUserRole = $teamRole;
        }
    }

    // Check if current user can manage members
    if (! in_array($currentUserRole, ['owner', 'admin'])) {
        return response()->json(['message' => 'You do not have permission to manage members'], 403);
    }

    // Only owners can assign owner role
    if ($request->role === 'owner' && $currentUserRole !== 'owner') {
        return response()->json(['message' => 'Only project owners can assign the owner role'], 403);
    }

    $user = \App\Models\User::findOrFail($request->user_id);

    // Check if user is in the same team
    $team = currentTeam();
    if (! $team->members()->where('user_id', $user->id)->exists()) {
        return response()->json(['message' => 'User is not a member of this team'], 422);
    }

    // Check if already a member
    if ($project->members()->where('user_id', $user->id)->exists()) {
        return response()->json(['message' => 'User is already a member of this project'], 422);
    }

    $project->addMember($user, $request->role);

    return response()->json([
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'role' => $request->role,
        'is_team_fallback' => false,
    ]);
})->name('projects.members.store');

// Update member role
Route::patch('/projects/{uuid}/members/{memberId}', function (Request $request, string $uuid, int $memberId) {
    $request->validate([
        'role' => 'required|in:owner,admin,developer,member,viewer',
    ]);

    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $currentUser = auth()->user();
    $team = currentTeam();
    $currentUserRole = $currentUser->roleInProject($project);

    // Fallback to team role - team owners/admins can manage project members
    if (! $currentUserRole) {
        $teamMembership = $currentUser->teamMembership($team->id);
        $teamRole = $teamMembership?->role;

        if ($teamRole === 'owner' || $teamRole === 'admin') {
            $currentUserRole = $teamRole;
        }
    }

    // Check if current user can manage members
    if (! in_array($currentUserRole, ['owner', 'admin'])) {
        return response()->json(['message' => 'You do not have permission to manage members'], 403);
    }

    // Only owners can assign owner role
    if ($request->role === 'owner' && $currentUserRole !== 'owner') {
        return response()->json(['message' => 'Only project owners can assign the owner role'], 403);
    }

    $user = \App\Models\User::findOrFail($memberId);

    // Check if the user is a project member
    if (! $project->members()->where('user_id', $user->id)->exists()) {
        return response()->json(['message' => 'User is not a project member'], 404);
    }

    // Cannot change own role
    if ($user->id === $currentUser->id) {
        return response()->json(['message' => 'You cannot change your own role'], 422);
    }

    $project->updateMemberRole($user, $request->role);

    return response()->json(['message' => 'Role updated successfully']);
})->name('projects.members.update');

// Remove member from project
Route::delete('/projects/{uuid}/members/{memberId}', function (string $uuid, int $memberId) {
    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $currentUser = auth()->user();
    $team = currentTeam();
    $currentUserRole = $currentUser->roleInProject($project);

    // Fallback to team role - team owners/admins can manage project members
    if (! $currentUserRole) {
        $teamMembership = $currentUser->teamMembership($team->id);
        $teamRole = $teamMembership?->role;

        if ($teamRole === 'owner' || $teamRole === 'admin') {
            $currentUserRole = $teamRole;
        }
    }

    // Check if current user can manage members
    if (! in_array($currentUserRole, ['owner', 'admin'])) {
        return response()->json(['message' => 'You do not have permission to manage members'], 403);
    }

    $user = \App\Models\User::findOrFail($memberId);

    // Cannot remove yourself
    if ($user->id === $currentUser->id) {
        return response()->json(['message' => 'You cannot remove yourself from the project'], 422);
    }

    // Check if user is a project member
    if (! $project->members()->where('user_id', $user->id)->exists()) {
        return response()->json(['message' => 'User is not a project member'], 404);
    }

    // Admins cannot remove owners
    $memberRole = $user->roleInProject($project);
    if ($memberRole === 'owner' && $currentUserRole !== 'owner') {
        return response()->json(['message' => 'Only project owners can remove other owners'], 403);
    }

    $project->removeMember($user);

    return response()->json(['message' => 'Member removed successfully']);
})->name('projects.members.destroy');
