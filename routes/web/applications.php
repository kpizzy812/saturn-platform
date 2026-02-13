<?php

/**
 * Application routes for Saturn Platform
 *
 * These routes handle application management, deployments, previews, and settings.
 * All routes require authentication and email verification.
 */

use App\Actions\Application\StopApplication;
use App\Jobs\DeleteResourceJob;
use App\Services\ServerSelectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Visus\Cuid2\Cuid2;

// Applications (Saturn)
Route::get('/applications', function () {
    // Ensure user has a current team
    $team = currentTeam();
    if (! $team) {
        return redirect()->route('dashboard')->with('error', 'Please select a team first');
    }

    $applications = \App\Models\Application::ownedByCurrentTeam()
        ->with(['environment.project'])
        ->get()
        ->map(function ($app) {
            // Check for active deployments
            $hasActiveDeployment = \App\Models\ApplicationDeploymentQueue::where('application_id', $app->id)
                ->whereIn('status', ['in_progress', 'queued'])
                ->exists();

            // Parse status into state and health
            $statusParts = explode(':', $app->status);
            $containerStatus = $statusParts[0] ?: 'stopped';
            $health = $statusParts[1] ?? 'unknown';

            // Override state if actively deploying
            if ($hasActiveDeployment) {
                $containerStatus = 'building';
            } elseif (in_array($containerStatus, ['exited', ''])) {
                $containerStatus = 'stopped';
            }

            return [
                'id' => $app->id,
                'uuid' => $app->uuid,
                'name' => $app->name,
                'description' => $app->description,
                'fqdn' => $app->fqdn,
                'git_repository' => $app->git_repository,
                'git_branch' => $app->git_branch,
                'build_pack' => $app->build_pack,
                'status' => [
                    'state' => $containerStatus,
                    'health' => $health,
                ],
                'project_name' => $app->environment->project->name,
                'environment_name' => $app->environment->name,
                'environment_type' => $app->environment->type ?? 'development',
                'created_at' => $app->created_at,
                'updated_at' => $app->updated_at,
            ];
        });

    return Inertia::render('Applications/Index', [
        'applications' => $applications,
    ]);
})->name('applications.index');

Route::get('/applications/create', function () {
    // Ensure user has a current team
    $team = currentTeam();
    if (! $team) {
        return redirect()->route('dashboard')->with('error', 'Please select a team first');
    }

    $authService = app(\App\Services\Authorization\ProjectAuthorizationService::class);
    $currentUser = auth()->user();

    $projects = \App\Models\Project::ownedByCurrentTeam()
        ->with('environments')
        ->get()
        ->each(function ($project) use ($authService, $currentUser) {
            // Filter out environments the user cannot deploy to (e.g. production for developers)
            $project->setRelation(
                'environments',
                $authService->filterVisibleEnvironments($currentUser, $project, $project->environments)
            );
        });

    // Always get localhost (platform's master server) - used by default
    $localhost = \App\Models\Server::where('id', 0)->first();

    // Get user's additional servers (optional, for advanced users)
    $userServers = \App\Models\Server::ownedByCurrentTeam()
        ->where('id', '!=', 0)
        ->whereRelation('settings', 'is_usable', true)
        ->get();

    // Extract wildcard domain info for subdomain input UI
    $wildcardDomain = null;
    $masterServer = $localhost;
    if ($masterServer) {
        $wildcard = data_get($masterServer, 'settings.wildcard_domain');
        if ($wildcard) {
            $url = \Spatie\Url\Url::fromString($wildcard);
            $wildcardDomain = [
                'host' => $url->getHost(),
                'scheme' => $url->getScheme(),
            ];
        }
    }

    // Check if team has an active GitHub App for auto-deploy
    $hasGithubApp = \App\Models\GithubApp::where('team_id', $team->id)
        ->whereNotNull('app_id')
        ->whereNotNull('installation_id')
        ->where('is_public', false)
        ->exists();

    return Inertia::render('Applications/Create', [
        'projects' => $projects,
        'localhost' => $localhost,
        'userServers' => $userServers,
        'needsProject' => $projects->isEmpty(),
        'preselectedSource' => request()->query('source'),
        'wildcardDomain' => $wildcardDomain,
        'hasGithubApp' => $hasGithubApp,
    ]);
})->name('applications.create');

Route::post('/applications', function (Request $request) {
    // Ensure user has a current team
    $team = currentTeam();
    if (! $team) {
        return redirect()->route('dashboard')->with('error', 'Please select a team first');
    }

    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'source_type' => 'required|string|in:github,gitlab,bitbucket,docker',
        'git_repository' => 'required_unless:source_type,docker|nullable|string',
        'git_branch' => 'nullable|string',
        'build_pack' => 'required|string|in:nixpacks,dockerfile,dockercompose,dockerimage',
        'project_uuid' => 'required|string',
        'environment_uuid' => 'required|string',
        'server_uuid' => 'nullable|string',
        'fqdn' => 'nullable|string',
        'description' => 'nullable|string',
        'docker_image' => 'required_if:source_type,docker|nullable|string',
    ]);

    // Find project and environment
    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $validated['project_uuid'])
        ->firstOrFail();

    $environment = $project->environments()
        ->where('uuid', $validated['environment_uuid'])
        ->firstOrFail();

    // Find server and destination
    $serverUuid = $validated['server_uuid'] ?? '';

    if ($serverUuid === 'auto' || empty($serverUuid)) {
        // Smart server selection
        $selectionService = app(ServerSelectionService::class);
        $server = $selectionService->selectOptimalServer($environment);
        if (! $server) {
            return redirect()->back()->withErrors(['server_uuid' => 'No usable servers available for auto-selection']);
        }

        // Set project affinity if not already set
        if (! $project->settings?->default_server_id) {
            $project->settings()->updateOrCreate(
                ['project_id' => $project->id],
                ['default_server_id' => $server->id]
            );
        }
    } else {
        // First check if it's localhost (platform's master server with id=0)
        $localhost = \App\Models\Server::where('id', 0)->first();
        if ($localhost && $localhost->uuid === $serverUuid) {
            $server = $localhost;
        } else {
            // Otherwise, look for user's own servers
            $server = \App\Models\Server::ownedByCurrentTeam()
                ->where('uuid', $serverUuid)
                ->firstOrFail();
        }
    }

    $destination = $server->destinations()->first();
    if (! $destination) {
        return redirect()->back()->withErrors(['server_uuid' => 'Server has no destinations configured']);
    }

    // Create the application
    $application = new \App\Models\Application;
    $application->name = $validated['name'];
    $application->description = $validated['description'] ?? null;
    $application->fqdn = $validated['fqdn'] ?? null;
    $application->git_repository = $validated['git_repository'] ?? null;
    $application->git_branch = $validated['git_branch'] ?? 'main';
    $application->build_pack = $validated['build_pack'];
    $application->environment_id = $environment->id;
    $application->destination_id = $destination->id;
    $application->destination_type = $destination->getMorphClass();

    // Handle Docker image source
    if ($validated['source_type'] === 'docker') {
        $application->build_pack = 'dockerimage';
        $application->docker_registry_image_name = $validated['docker_image'];
    }

    // Set source type for git — prefer team's active GitHub App for auto-deploy
    if (in_array($validated['source_type'], ['github', 'gitlab', 'bitbucket'])) {
        $githubApp = null;

        if ($validated['source_type'] === 'github') {
            // Find team's active GitHub App (has app_id + installation_id = real credentials)
            $githubApp = \App\Models\GithubApp::where('team_id', $team->id)
                ->whereNotNull('app_id')
                ->whereNotNull('installation_id')
                ->where('is_public', false)
                ->first();
        }

        // Fallback to public source
        if (! $githubApp) {
            $githubApp = \App\Models\GithubApp::find(0);
        }

        if ($githubApp) {
            $application->source_type = \App\Models\GithubApp::class;
            $application->source_id = $githubApp->id;
        }

        // Auto-generate manual webhook secret for fallback manual webhooks
        $application->manual_webhook_secret_github = \Illuminate\Support\Str::random(32);
    }

    // Set default ports
    $application->ports_exposes = '80';

    $application->save();

    // Fetch repository_project_id via GitHub API for webhook matching
    if ($application->source_id && $application->source_id !== 0 && $application->git_repository) {
        try {
            $source = $application->source;
            if ($source && $source->app_id && $source->installation_id) {
                $repoFullName = str($application->git_repository)
                    ->replace('https://github.com/', '')
                    ->replace('http://github.com/', '')
                    ->replace('.git', '')
                    ->toString();
                $token = generateGithubInstallationToken($source);
                $repoResponse = \Illuminate\Support\Facades\Http::GitHub($source->api_url, $token)
                    ->timeout(10)
                    ->get("/repos/{$repoFullName}");
                if ($repoResponse->successful()) {
                    $application->repository_project_id = $repoResponse->json('id');
                    $application->save();
                }
            }
        } catch (\Throwable $e) {
            // Non-critical: auto-deploy won't work but app still created
            \Illuminate\Support\Facades\Log::warning("Failed to fetch repository_project_id for app {$application->uuid}: {$e->getMessage()}");
        }
    }

    // Handle domain: expand subdomain-only input or auto-generate if empty
    if (! empty($application->fqdn) && ! str_contains($application->fqdn, '.') && ! str_contains($application->fqdn, '://')) {
        // User entered just a subdomain (e.g. "uranus") — expand to full URL
        $application->fqdn = generateUrl(server: $server, random: $application->fqdn);
        $application->save();
    } elseif (empty($application->fqdn)) {
        // No domain provided — auto-generate from project name
        $slug = generateSubdomainFromName($application->name, $server, $project->name);
        $application->fqdn = generateUrl(server: $server, random: $slug);
        $application->save();
    }

    return redirect()->route('applications.show', $application->uuid)
        ->with('success', 'Application created successfully');
})->name('applications.store');

Route::get('/applications/{uuid}', function (string $uuid) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->with(['environment.project'])
        ->where('uuid', $uuid)
        ->firstOrFail();

    // Get recent deployments
    $recentDeployments = \App\Models\ApplicationDeploymentQueue::where('application_id', $application->id)
        ->where('pull_request_id', 0)
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get()
        ->map(function ($deployment) {
            return [
                'id' => $deployment->id,
                'deployment_uuid' => $deployment->deployment_uuid,
                'status' => $deployment->status,
                'commit' => $deployment->commit,
                'commit_message' => $deployment->commitMessage(),
                'created_at' => $deployment->created_at,
            ];
        });

    // Count environment variables
    $envVarsCount = \App\Models\EnvironmentVariable::where('resourceable_type', \App\Models\Application::class)
        ->where('resourceable_id', $application->id)
        ->where('is_preview', false)
        ->count();

    // Determine display status based on container status and active deployments
    $containerStatus = str($application->status)->before(':')->toString();
    $hasActiveDeployment = $recentDeployments->contains(fn ($d) => in_array($d['status'], ['in_progress', 'queued']));

    if ($hasActiveDeployment) {
        $displayStatus = 'building';
    } elseif ($containerStatus === 'running') {
        $displayStatus = 'running';
    } elseif (in_array($containerStatus, ['exited', 'stopped', ''])) {
        $displayStatus = 'stopped';
    } else {
        $displayStatus = $containerStatus ?: 'stopped';
    }

    // Auto-deploy status
    $source = $application->source;
    $settings = $application->settings;
    $hasRealGithubApp = $source
        && $source instanceof \App\Models\GithubApp
        && $source->id !== 0
        && ! $source->is_public
        && $source->app_id
        && $source->installation_id;

    $autoDeployStatus = 'not_configured';
    if ($hasRealGithubApp && $application->repository_project_id) {
        $autoDeployStatus = 'automatic';
    } elseif ($application->manual_webhook_secret_github) {
        $autoDeployStatus = 'manual_webhook';
    }

    return Inertia::render('Applications/Show', [
        'application' => [
            'id' => $application->id,
            'uuid' => $application->uuid,
            'name' => $application->name,
            'description' => $application->description,
            'fqdn' => $application->fqdn,
            'git_repository' => $application->git_repository,
            'git_branch' => $application->git_branch,
            'build_pack' => $application->build_pack,
            'status' => $displayStatus,
            'created_at' => $application->created_at,
            'updated_at' => $application->updated_at,
            'project' => $application->environment->project,
            'environment' => $application->environment,
            'recent_deployments' => $recentDeployments,
            'environment_variables_count' => $envVarsCount,
            'auto_deploy_status' => $autoDeployStatus,
            'is_auto_deploy_enabled' => $settings?->is_auto_deploy_enabled ?? false,
            'source_name' => $hasRealGithubApp ? $source->name : null,
            'webhook_url' => url('/webhooks/source/github/events/manual'),
            'has_webhook_secret' => ! empty($application->manual_webhook_secret_github),
        ],
    ]);
})->name('applications.show');

// Application action routes
Route::post('/applications/{uuid}/deploy', function (string $uuid, \Illuminate\Http\Request $request) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $deployment_uuid = new Cuid2;

    $result = queue_application_deployment(
        application: $application,
        deployment_uuid: $deployment_uuid,
        force_rebuild: (bool) $request->input('force_rebuild', false),
        is_api: false,
        requires_approval: (bool) $request->input('requires_approval', false),
    );

    if ($result['status'] === 'skipped') {
        return redirect()->back()->with('error', $result['message']);
    }

    return redirect()->back()->with('success', 'Deployment started');
})->name('applications.deploy');

Route::post('/applications/{uuid}/start', function (string $uuid) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $deployment_uuid = new Cuid2;

    $result = queue_application_deployment(
        application: $application,
        deployment_uuid: $deployment_uuid,
        force_rebuild: false,
        is_api: false,
    );

    if ($result['status'] === 'skipped') {
        return redirect()->back()->with('error', $result['message']);
    }

    return redirect()->back()->with('success', 'Application started');
})->name('applications.start');

Route::post('/applications/{uuid}/stop', function (string $uuid) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    StopApplication::dispatch($application);

    return redirect()->back()->with('success', 'Application stopped');
})->name('applications.stop');

Route::post('/applications/{uuid}/restart', function (string $uuid) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $deployment_uuid = new Cuid2;

    $result = queue_application_deployment(
        application: $application,
        deployment_uuid: $deployment_uuid,
        restart_only: true,
        is_api: false,
    );

    if ($result['status'] === 'skipped') {
        return redirect()->back()->with('error', $result['message']);
    }

    return redirect()->back()->with('success', 'Application restarted');
})->name('applications.restart');

Route::delete('/applications/{uuid}', function (string $uuid) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    DeleteResourceJob::dispatch(
        resource: $application,
        deleteVolumes: true,
        deleteConnectedNetworks: true,
        deleteConfigurations: true,
        dockerCleanup: true
    );

    return redirect()->route('applications.index')->with('success', 'Application deletion queued');
})->name('applications.destroy');

// Application Rollback Routes (Saturn)
Route::get('/applications/{uuid}/rollback', function (string $uuid) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $project = $application->environment->project;
    $environment = $application->environment;

    // Fetch deployments server-side
    $deployments = \App\Models\ApplicationDeploymentQueue::where('application_id', $application->id)
        ->where('pull_request_id', 0)
        ->orderBy('created_at', 'desc')
        ->limit(50)
        ->get()
        ->map(function ($deployment) {
            $duration = null;
            if ($deployment->started_at && $deployment->finished_at) {
                $duration = \Carbon\Carbon::parse($deployment->finished_at)
                    ->diffInSeconds(\Carbon\Carbon::parse($deployment->started_at));
            }

            return [
                'id' => $deployment->id,
                'deployment_uuid' => $deployment->deployment_uuid,
                'status' => $deployment->status,
                'commit' => $deployment->commit,
                'commit_message' => $deployment->commitMessage(),
                'trigger' => $deployment->is_webhook ? 'push' : ($deployment->rollback ? 'rollback' : 'manual'),
                'rollback' => (bool) $deployment->rollback,
                'is_webhook' => (bool) $deployment->is_webhook,
                'is_api' => (bool) $deployment->is_api,
                'duration' => $duration,
                'created_at' => $deployment->created_at?->toISOString(),
                'updated_at' => $deployment->updated_at?->toISOString(),
            ];
        });

    // Fetch rollback events server-side
    $rollbackEvents = \App\Models\ApplicationRollbackEvent::where('application_id', $application->id)
        ->with(['triggeredByUser:id,name,email'])
        ->orderBy('created_at', 'desc')
        ->limit(20)
        ->get()
        ->map(function ($event) {
            return [
                'id' => $event->id,
                'trigger_reason' => $event->trigger_reason,
                'trigger_type' => $event->trigger_type,
                'status' => $event->status,
                'from_commit' => $event->from_commit,
                'to_commit' => $event->to_commit,
                'error_message' => $event->error_message,
                'triggered_at' => $event->triggered_at?->toISOString(),
                'completed_at' => $event->completed_at?->toISOString(),
                'triggered_by_user' => $event->triggeredByUser ? [
                    'id' => $event->triggeredByUser->id,
                    'name' => $event->triggeredByUser->name,
                ] : null,
            ];
        });

    // Auto-rollback settings
    $settings = $application->settings;

    return Inertia::render('Applications/Rollback/Index', [
        'application' => $application,
        'projectUuid' => $project->uuid,
        'environmentUuid' => $environment->uuid,
        'deployments' => $deployments,
        'rollbackEvents' => $rollbackEvents,
        'rollbackSettings' => [
            'auto_rollback_enabled' => $settings->auto_rollback_enabled ?? false,
            'rollback_validation_seconds' => $settings->rollback_validation_seconds ?? 300,
            'rollback_max_restarts' => $settings->rollback_max_restarts ?? 3,
        ],
    ]);
})->name('applications.rollback');

Route::get('/applications/{uuid}/rollback/{deploymentUuid}', function (string $uuid, string $deploymentUuid) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $project = $application->environment->project;
    $environment = $application->environment;

    // Fetch the specific deployment
    $deployment = \App\Models\ApplicationDeploymentQueue::where('application_id', $application->id)
        ->where('deployment_uuid', $deploymentUuid)
        ->firstOrFail();

    $duration = null;
    if ($deployment->started_at && $deployment->finished_at) {
        $duration = \Carbon\Carbon::parse($deployment->finished_at)
            ->diffInSeconds(\Carbon\Carbon::parse($deployment->started_at));
    }

    $deploymentData = [
        'id' => $deployment->id,
        'deployment_uuid' => $deployment->deployment_uuid,
        'commit' => $deployment->commit,
        'commit_message' => $deployment->commitMessage(),
        'status' => $deployment->status,
        'created_at' => $deployment->created_at?->toISOString(),
        'updated_at' => $deployment->updated_at?->toISOString(),
        'rollback' => (bool) $deployment->rollback,
        'force_rebuild' => (bool) $deployment->force_rebuild,
        'is_webhook' => (bool) $deployment->is_webhook,
        'is_api' => (bool) $deployment->is_api,
        'server_id' => $deployment->server_id,
        'server_name' => $deployment->server?->name,
        'duration' => $duration,
    ];

    // Fetch current (latest finished) deployment
    $currentDeployment = \App\Models\ApplicationDeploymentQueue::where('application_id', $application->id)
        ->where('pull_request_id', 0)
        ->orderBy('created_at', 'desc')
        ->first();

    $currentDeploymentData = null;
    if ($currentDeployment) {
        $currentDeploymentData = [
            'id' => $currentDeployment->id,
            'deployment_uuid' => $currentDeployment->deployment_uuid,
            'commit' => $currentDeployment->commit,
            'commit_message' => $currentDeployment->commitMessage(),
            'status' => $currentDeployment->status,
            'created_at' => $currentDeployment->created_at?->toISOString(),
        ];
    }

    return Inertia::render('Applications/Rollback/Show', [
        'application' => $application,
        'deployment' => $deploymentData,
        'currentDeployment' => $currentDeploymentData,
        'projectUuid' => $project->uuid,
        'environmentUuid' => $environment->uuid,
    ]);
})->name('applications.rollback.show');

Route::post('/applications/{uuid}/rollback/{deploymentUuid}', function (string $uuid, string $deploymentUuid) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    // Find the deployment to rollback to
    $targetDeployment = \App\Models\ApplicationDeploymentQueue::where('deployment_uuid', $deploymentUuid)
        ->where('application_id', $application->id)
        ->firstOrFail();

    if ($targetDeployment->status !== 'finished') {
        return response()->json(['message' => 'Can only rollback to successful deployments'], 400);
    }

    // Create rollback event
    $currentDeployment = \App\Models\ApplicationDeploymentQueue::where('application_id', $application->id)
        ->orderBy('created_at', 'desc')
        ->first();

    $event = \App\Models\ApplicationRollbackEvent::createEvent(
        application: $application,
        reason: \App\Models\ApplicationRollbackEvent::REASON_MANUAL,
        type: 'manual',
        failedDeployment: $currentDeployment,
        user: auth()->user()
    );

    $event->update(['to_commit' => $targetDeployment->commit]);

    // Queue rollback deployment
    $rollback_deployment_uuid = new Cuid2;

    $result = queue_application_deployment(
        application: $application,
        deployment_uuid: $rollback_deployment_uuid,
        commit: $targetDeployment->commit,
        rollback: true,
        force_rebuild: false,
    );

    if ($result['status'] === 'queue_full') {
        return response()->json(['message' => $result['message']], 429);
    }

    $rollbackDeployment = \App\Models\ApplicationDeploymentQueue::where('deployment_uuid', $rollback_deployment_uuid)->first();

    if ($rollbackDeployment) {
        $event->markInProgress($rollbackDeployment->id);
    }

    return response()->json([
        'message' => 'Rollback initiated successfully',
        'deployment_uuid' => $rollback_deployment_uuid->toString(),
    ]);
})->name('applications.rollback.execute');

// Application Preview Deployment Routes (Saturn)
Route::get('/applications/{uuid}/previews', function (string $uuid) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $project = $application->environment->project;
    $environment = $application->environment;

    // Fetch actual preview deployments from database
    $previews = \App\Models\ApplicationDeploymentQueue::where('application_id', $application->id)
        ->where('pull_request_id', '!=', 0)
        ->orderBy('created_at', 'desc')
        ->get()
        ->groupBy('pull_request_id')
        ->map(function ($deployments, $prId) {
            $latest = $deployments->first();

            return [
                'pull_request_id' => $prId,
                'status' => $latest->status,
                'commit' => $latest->commit,
                'commit_message' => $latest->commitMessage(),
                'created_at' => $latest->created_at,
                'updated_at' => $latest->updated_at,
                'deployments_count' => $deployments->count(),
            ];
        })
        ->values();

    return Inertia::render('Applications/Previews/Index', [
        'application' => $application,
        'previews' => $previews,
        'projectUuid' => $project->uuid,
        'environmentUuid' => $environment->uuid,
    ]);
})->name('applications.previews');

Route::get('/applications/{uuid}/previews/settings', function (string $uuid) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $project = $application->environment->project;
    $environment = $application->environment;

    // Get preview settings from application
    $settings = [
        'preview_url_template' => $application->preview_url_template,
        'instant_deploy_preview' => $application->instant_deploy_preview ?? false,
    ];

    return Inertia::render('Applications/Previews/Settings', [
        'application' => $application,
        'settings' => $settings,
        'projectUuid' => $project->uuid,
        'environmentUuid' => $environment->uuid,
    ]);
})->name('applications.previews.settings');

Route::get('/applications/{uuid}/previews/{previewUuid}', function (string $uuid, string $previewUuid) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $project = $application->environment->project;
    $environment = $application->environment;

    // Fetch actual preview deployment from database
    $preview = \App\Models\ApplicationDeploymentQueue::where('application_id', $application->id)
        ->where('deployment_uuid', $previewUuid)
        ->firstOrFail();

    $previewData = [
        'deployment_uuid' => $preview->deployment_uuid,
        'pull_request_id' => $preview->pull_request_id,
        'status' => $preview->status,
        'commit' => $preview->commit,
        'commit_message' => $preview->commitMessage(),
        'created_at' => $preview->created_at,
        'updated_at' => $preview->updated_at,
        'started_at' => $preview->started_at,
        'finished_at' => $preview->finished_at,
    ];

    return Inertia::render('Applications/Previews/Show', [
        'application' => $application,
        'preview' => $previewData,
        'previewUuid' => $previewUuid,
        'projectUuid' => $project->uuid,
        'environmentUuid' => $environment->uuid,
    ]);
})->name('applications.previews.show');

// Application Settings Routes (Saturn)
Route::get('/applications/{uuid}/settings', function (string $uuid) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $project = $application->environment->project;
    $environment = $application->environment;

    // Load settings for rollback configuration
    $settings = $application->settings;

    return Inertia::render('Applications/Settings/Index', [
        'application' => $application,
        'applicationSettings' => [
            'auto_rollback_enabled' => $settings->auto_rollback_enabled ?? false,
            'rollback_validation_seconds' => $settings->rollback_validation_seconds ?? 300,
            'rollback_max_restarts' => $settings->rollback_max_restarts ?? 3,
            'rollback_on_health_check_fail' => $settings->rollback_on_health_check_fail ?? true,
            'rollback_on_crash_loop' => $settings->rollback_on_crash_loop ?? true,
            'docker_images_to_keep' => $settings->docker_images_to_keep ?? 2,
        ],
        'projectUuid' => $project->uuid,
        'environmentUuid' => $environment->uuid,
    ]);
})->name('applications.settings');

// Update application settings (web route for session auth)
Route::patch('/applications/{uuid}/settings', function (string $uuid, \Illuminate\Http\Request $request) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->first();

    if (! $application) {
        return back()->with('error', 'Application not found.');
    }

    $validated = $request->validate([
        'name' => 'sometimes|string|max:255',
        'description' => 'sometimes|nullable|string',
        'base_directory' => 'sometimes|nullable|string|max:255',
        'build_command' => 'sometimes|nullable|string',
        'install_command' => 'sometimes|nullable|string',
        'start_command' => 'sometimes|nullable|string',
        'health_check_path' => 'sometimes|nullable|string|max:255',
        'health_check_interval' => 'sometimes|integer|min:1|max:300',
        'build_pack' => 'sometimes|string|in:nixpacks,dockerfile,dockercompose,dockerimage',
        'deploy_on_push' => 'sometimes|boolean',
        'cpu_limit' => 'sometimes|nullable|string',
        'memory_limit' => 'sometimes|nullable|string',
        // Rollback settings
        'auto_rollback_enabled' => 'sometimes|boolean',
        'rollback_validation_seconds' => 'sometimes|integer|min:60|max:1800',
        'rollback_max_restarts' => 'sometimes|integer|min:1|max:10',
        'rollback_on_health_check_fail' => 'sometimes|boolean',
        'rollback_on_crash_loop' => 'sometimes|boolean',
        'docker_images_to_keep' => 'sometimes|integer|min:1|max:20',
    ]);

    // Map frontend field names to application model field names
    $appMappings = [
        'cpu_limit' => 'limits_cpus',
        'memory_limit' => 'limits_memory',
    ];

    foreach ($appMappings as $frontendKey => $modelKey) {
        if (isset($validated[$frontendKey])) {
            $validated[$modelKey] = $validated[$frontendKey];
            unset($validated[$frontendKey]);
        }
    }

    // Handle settings fields (stored in application_settings table)
    $settingsFields = [
        'deploy_on_push' => 'is_auto_deploy_enabled',
        'auto_rollback_enabled' => 'auto_rollback_enabled',
        'rollback_validation_seconds' => 'rollback_validation_seconds',
        'rollback_max_restarts' => 'rollback_max_restarts',
        'rollback_on_health_check_fail' => 'rollback_on_health_check_fail',
        'rollback_on_crash_loop' => 'rollback_on_crash_loop',
        'docker_images_to_keep' => 'docker_images_to_keep',
    ];

    $settingsUpdate = [];
    foreach ($settingsFields as $frontendKey => $settingsKey) {
        if (isset($validated[$frontendKey])) {
            $settingsUpdate[$settingsKey] = $validated[$frontendKey];
            unset($validated[$frontendKey]);
        }
    }

    if (! empty($settingsUpdate)) {
        $application->settings->update($settingsUpdate);
    }

    // Mark build_pack as explicitly set when user changes it via settings
    if (isset($validated['build_pack'])) {
        $validated['build_pack_explicitly_set'] = true;
    }

    $application->update($validated);

    return back()->with('success', 'Settings saved successfully.');
})->name('applications.settings.update');

Route::get('/applications/{uuid}/settings/domains', function (string $uuid) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $project = $application->environment->project;
    $environment = $application->environment;

    // Parse domains from fqdn field (comma-separated)
    $domains = [];
    if ($application->fqdn) {
        $fqdns = explode(',', $application->fqdn);
        foreach ($fqdns as $index => $fqdn) {
            $fqdn = trim($fqdn);
            if ($fqdn) {
                $domains[] = [
                    'id' => $index,
                    'domain' => $fqdn,
                    'is_primary' => $index === 0,
                ];
            }
        }
    }

    return Inertia::render('Applications/Settings/Domains', [
        'application' => $application,
        'domains' => $domains,
        'projectUuid' => $project->uuid,
        'environmentUuid' => $environment->uuid,
    ]);
})->name('applications.settings.domains');

Route::get('/applications/{uuid}/settings/variables', function (string $uuid) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $project = $application->environment->project;
    $environment = $application->environment;

    // Fetch actual environment variables from database
    $variables = \App\Models\EnvironmentVariable::where('resourceable_type', \App\Models\Application::class)
        ->where('resourceable_id', $application->id)
        ->where('is_preview', false)
        ->orderBy('key')
        ->get()
        ->map(function ($var) {
            return [
                'id' => $var->id,
                'key' => $var->key,
                'value' => $var->value,
                'is_multiline' => $var->is_multiline,
                'is_literal' => $var->is_literal,
                'is_runtime' => $var->is_runtime,
                'is_buildtime' => $var->is_buildtime,
                'created_at' => $var->created_at,
            ];
        });

    return Inertia::render('Applications/Settings/Variables', [
        'application' => $application,
        'variables' => $variables,
        'projectUuid' => $project->uuid,
        'environmentUuid' => $environment->uuid,
    ]);
})->name('applications.settings.variables');

// Application Environment Variables JSON endpoint (for Show page eye toggle)
Route::get('/applications/{uuid}/envs/json', function (string $uuid) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $variables = \App\Models\EnvironmentVariable::where('resourceable_type', \App\Models\Application::class)
        ->where('resourceable_id', $application->id)
        ->where('is_preview', false)
        ->orderBy('key')
        ->get()
        ->map(function ($var) {
            return [
                'id' => $var->id,
                'uuid' => $var->uuid,
                'key' => $var->key,
                'value' => $var->value,
                'real_value' => $var->real_value ?? $var->value,
                'is_preview' => $var->is_preview,
                'is_buildtime' => $var->is_buildtime,
            ];
        });

    return response()->json($variables);
})->name('applications.envs.json');

// Application Logs Route (Saturn)
Route::get('/applications/{uuid}/logs', function (string $uuid) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $project = $application->environment->project;
    $environment = $application->environment;

    return Inertia::render('Applications/Logs', [
        'application' => $application,
        'projectUuid' => $project->uuid,
        'environmentUuid' => $environment->uuid,
    ]);
})->name('applications.logs');

// JSON endpoint for application deployments (for XHR requests from canvas panel)
Route::get('/applications/{uuid}/deployments/json', function (string $uuid) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $deployments = \App\Models\ApplicationDeploymentQueue::where('application_id', $application->id)
        ->where('pull_request_id', 0)
        ->orderBy('created_at', 'desc')
        ->limit(50)
        ->get()
        ->map(function ($deployment) {
            $duration = null;
            if ($deployment->started_at && $deployment->finished_at) {
                $duration = \Carbon\Carbon::parse($deployment->finished_at)
                    ->diffInSeconds(\Carbon\Carbon::parse($deployment->started_at));
            }

            return [
                'id' => $deployment->id,
                'uuid' => $deployment->deployment_uuid,
                'deployment_uuid' => $deployment->deployment_uuid,
                'status' => $deployment->status,
                'commit' => $deployment->commit,
                'commit_message' => $deployment->commitMessage(),
                'trigger' => $deployment->is_webhook ? 'push' : ($deployment->rollback ? 'rollback' : 'manual'),
                'duration' => $duration,
                'created_at' => $deployment->created_at?->toISOString(),
                'updated_at' => $deployment->updated_at?->toISOString(),
                'started_at' => $deployment->started_at,
                'finished_at' => $deployment->finished_at,
            ];
        });

    return response()->json($deployments);
})->name('applications.deployments.json');

// Application Deployments Route (Saturn)
Route::get('/applications/{uuid}/deployments', function (string $uuid) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $project = $application->environment->project;
    $environment = $application->environment;

    // Fetch actual deployments from database
    $deployments = \App\Models\ApplicationDeploymentQueue::where('application_id', $application->id)
        ->where('pull_request_id', 0)
        ->orderBy('created_at', 'desc')
        ->limit(50)
        ->get()
        ->map(function ($deployment) {
            // Calculate duration if deployment finished
            $duration = null;
            if ($deployment->started_at && $deployment->finished_at) {
                $duration = \Carbon\Carbon::parse($deployment->finished_at)
                    ->diffInSeconds(\Carbon\Carbon::parse($deployment->started_at));
            }

            return [
                'id' => $deployment->id,
                'deployment_uuid' => $deployment->deployment_uuid,
                'status' => $deployment->status,
                'commit' => $deployment->commit,
                'commit_message' => $deployment->commitMessage(),
                'trigger' => $deployment->is_webhook ? 'push' : ($deployment->rollback ? 'rollback' : 'manual'),
                'duration' => $duration,
                'created_at' => $deployment->created_at,
                'updated_at' => $deployment->updated_at,
                'started_at' => $deployment->started_at,
                'finished_at' => $deployment->finished_at,
            ];
        });

    return Inertia::render('Applications/Deployments', [
        'application' => $application,
        'deployments' => $deployments,
        'projectUuid' => $project->uuid,
        'environmentUuid' => $environment->uuid,
    ]);
})->name('applications.deployments');

// Application Deployment Details Route (Saturn)
Route::get('/applications/{uuid}/deployments/{deploymentUuid}', function (string $uuid, string $deploymentUuid) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $deployment = \App\Models\ApplicationDeploymentQueue::where('application_id', $application->id)
        ->where('deployment_uuid', $deploymentUuid)
        ->firstOrFail();

    $project = $application->environment->project;
    $environment = $application->environment;

    // Parse logs from JSON
    $logs = [];
    if ($deployment->logs) {
        $rawLogs = json_decode($deployment->logs, true);
        if (is_array($rawLogs)) {
            $logs = collect($rawLogs)
                ->filter(fn ($log) => ! ($log['hidden'] ?? false))
                ->map(fn ($log) => [
                    'output' => $log['output'] ?? '',
                    'type' => $log['type'] ?? 'stdout',
                    'timestamp' => $log['timestamp'] ?? null,
                    'order' => $log['order'] ?? 0,
                ])
                ->sortBy('order')
                ->values()
                ->all();
        }
    }

    // Calculate duration
    $duration = null;
    if ($deployment->created_at && $deployment->updated_at && in_array($deployment->status, ['finished', 'failed', 'cancelled'])) {
        $duration = $deployment->updated_at->diffInSeconds($deployment->created_at);
    }

    // Get server info
    $server = $deployment->server;

    return Inertia::render('Applications/DeploymentDetails', [
        'application' => [
            'id' => $application->id,
            'uuid' => $application->uuid,
            'name' => $application->name,
            'git_repository' => $application->git_repository,
            'git_branch' => $application->git_branch,
        ],
        'deployment' => [
            'id' => $deployment->id,
            'deployment_uuid' => $deployment->deployment_uuid,
            'status' => $deployment->status,
            'commit' => $deployment->commit,
            'commit_message' => $deployment->commitMessage(),
            'is_webhook' => $deployment->is_webhook,
            'is_api' => $deployment->is_api,
            'force_rebuild' => $deployment->force_rebuild,
            'rollback' => $deployment->rollback,
            'only_this_server' => $deployment->only_this_server,
            'created_at' => $deployment->created_at,
            'updated_at' => $deployment->updated_at,
            'duration' => $duration,
            'server_name' => $server?->name ?? 'Unknown',
            'server_id' => $deployment->server_id,
        ],
        'logs' => $logs,
        'projectUuid' => $project->uuid,
        'environmentUuid' => $environment->uuid,
    ]);
})->name('applications.deployment.show');

// Application Metrics API Route (Saturn)
Route::get('/applications/{uuid}/metrics', function (string $uuid) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $server = $application->destination->server;

    if (! $server->isFunctional()) {
        return response()->json([
            'error' => 'Server is not functional',
            'metrics' => null,
        ]);
    }

    try {
        // Find the running container by UUID prefix (container name may have a timestamp suffix)
        $containerUuid = $application->uuid;
        $findCommand = "docker ps -q --filter name=^{$containerUuid} 2>/dev/null | head -1";
        $containerId = trim(instant_remote_process([$findCommand], $server, false) ?? '');

        if (empty($containerId)) {
            return response()->json([
                'error' => 'Container not running',
                'metrics' => null,
            ]);
        }

        // Get container stats using the found container ID
        $command = "docker stats {$containerId} --no-stream --format '{{json .}}' 2>/dev/null || echo '{}'";
        $output = trim(instant_remote_process([$command], $server, false) ?? '{}');

        if (empty($output) || $output === '{}') {
            return response()->json([
                'error' => 'Container not running',
                'metrics' => null,
            ]);
        }

        $stats = json_decode($output, true);
        if (! $stats) {
            return response()->json([
                'error' => 'Failed to parse stats',
                'metrics' => null,
            ]);
        }

        // Helper to parse memory values like "512MiB", "2GiB", "100MB"
        $parseMemory = function (string $val): int {
            $val = trim($val);
            if (preg_match('/^([\d.]+)\s*(B|KB|KiB|MB|MiB|GB|GiB|TB|TiB)$/i', $val, $m)) {
                $num = (float) $m[1];
                $unit = strtoupper($m[2]);

                return (int) match ($unit) {
                    'B' => $num,
                    'KB' => $num * 1000,
                    'KIB' => $num * 1024,
                    'MB' => $num * 1000 * 1000,
                    'MIB' => $num * 1024 * 1024,
                    'GB' => $num * 1000 * 1000 * 1000,
                    'GIB' => $num * 1024 * 1024 * 1024,
                    'TB' => $num * 1000 * 1000 * 1000 * 1000,
                    'TIB' => $num * 1024 * 1024 * 1024 * 1024,
                    default => $num,
                };
            }

            return 0;
        };

        // Parse CPU percentage
        $cpuPercent = (float) str_replace('%', '', $stats['CPUPerc'] ?? '0%');

        // Parse memory usage (format: "512MiB / 2GiB")
        $memUsage = $stats['MemUsage'] ?? '0B / 0B';
        $memParts = explode('/', $memUsage);
        $memUsed = trim($memParts[0] ?? '0B');
        $memLimit = trim($memParts[1] ?? '0B');

        // Convert memory to bytes for calculations
        $memUsedBytes = $parseMemory($memUsed);
        $memLimitBytes = $parseMemory($memLimit);
        $memPercent = $memLimitBytes > 0 ? round(($memUsedBytes / $memLimitBytes) * 100, 1) : 0;

        // Parse network IO (format: "1.2MB / 500KB")
        $netIO = $stats['NetIO'] ?? '0B / 0B';
        $netParts = explode('/', $netIO);
        $netRx = trim($netParts[0] ?? '0B');
        $netTx = trim($netParts[1] ?? '0B');

        // Parse block IO (format: "100MB / 50MB")
        $blockIO = $stats['BlockIO'] ?? '0B / 0B';
        $blockParts = explode('/', $blockIO);
        $blockRead = trim($blockParts[0] ?? '0B');
        $blockWrite = trim($blockParts[1] ?? '0B');

        return response()->json([
            'metrics' => [
                'cpu' => [
                    'percent' => $cpuPercent,
                    'formatted' => $stats['CPUPerc'] ?? '0%',
                ],
                'memory' => [
                    'used' => $memUsed,
                    'limit' => $memLimit,
                    'percent' => $memPercent,
                    'used_bytes' => $memUsedBytes,
                    'limit_bytes' => $memLimitBytes,
                ],
                'network' => [
                    'rx' => $netRx,
                    'tx' => $netTx,
                ],
                'disk' => [
                    'read' => $blockRead,
                    'write' => $blockWrite,
                ],
                'pids' => $stats['PIDs'] ?? '0',
                'container_id' => $stats['ID'] ?? '',
                'container_name' => $stats['Name'] ?? $containerUuid,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'metrics' => null,
        ]);
    }
})->name('applications.metrics');

// API endpoint for application request stats (parsed from container logs)
Route::get('/_internal/applications/{uuid}/request-stats', [\App\Http\Controllers\Inertia\ApplicationMetricsController::class, 'getRequestStats'])
    ->name('applications.request-stats.api');

// Incident Timeline API endpoint
Route::get('/applications/{uuid}/incidents', function (string $uuid, \Illuminate\Http\Request $request) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $service = new \App\Services\IncidentTimelineService;

    // Parse time range from request
    $hours = (int) $request->input('hours', 24);
    $from = now()->subHours($hours);
    $to = now();

    $timeline = $service->getApplicationTimeline(
        application: $application,
        from: $from,
        to: $to,
        limit: (int) $request->input('limit', 100)
    );

    return response()->json($timeline);
})->name('applications.incidents');

// Incident Timeline Page
Route::get('/applications/{uuid}/incidents/view', function (string $uuid) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $project = $application->environment->project;
    $environment = $application->environment;

    return Inertia::render('Applications/Incidents', [
        'application' => [
            'id' => $application->id,
            'uuid' => $application->uuid,
            'name' => $application->name,
            'status' => $application->status,
        ],
        'projectUuid' => $project->uuid,
        'environmentUuid' => $environment->uuid,
    ]);
})->name('applications.incidents.view');
