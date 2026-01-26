<?php

/**
 * Application routes for Saturn Platform
 *
 * These routes handle application management, deployments, previews, and settings.
 * All routes require authentication and email verification.
 */

use App\Actions\Application\StopApplication;
use App\Jobs\DeleteResourceJob;
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
            return [
                'id' => $app->id,
                'uuid' => $app->uuid,
                'name' => $app->name,
                'description' => $app->description,
                'fqdn' => $app->fqdn,
                'git_repository' => $app->git_repository,
                'git_branch' => $app->git_branch,
                'build_pack' => $app->build_pack,
                'status' => $app->status,
                'project_name' => $app->environment->project->name,
                'environment_name' => $app->environment->name,
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

    $projects = \App\Models\Project::ownedByCurrentTeam()
        ->with('environments')
        ->get();

    // Always get localhost (platform's master server) - used by default
    $localhost = \App\Models\Server::where('id', 0)->first();

    // Get user's additional servers (optional, for advanced users)
    $userServers = \App\Models\Server::ownedByCurrentTeam()
        ->where('id', '!=', 0)
        ->whereRelation('settings', 'is_usable', true)
        ->get();

    return Inertia::render('Applications/Create', [
        'projects' => $projects,
        'localhost' => $localhost,
        'userServers' => $userServers,
        'needsProject' => $projects->isEmpty(),
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
        'server_uuid' => 'required|string',
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
    // First check if it's localhost (platform's master server with id=0)
    $localhost = \App\Models\Server::where('id', 0)->first();
    if ($localhost && $localhost->uuid === $validated['server_uuid']) {
        $server = $localhost;
    } else {
        // Otherwise, look for user's own servers
        $server = \App\Models\Server::ownedByCurrentTeam()
            ->where('uuid', $validated['server_uuid'])
            ->firstOrFail();
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

    // Set source type for git
    if (in_array($validated['source_type'], ['github', 'gitlab', 'bitbucket'])) {
        $githubApp = \App\Models\GithubApp::find(0); // Default public source
        if ($githubApp) {
            $application->source_type = \App\Models\GithubApp::class;
            $application->source_id = $githubApp->id;
        }
    }

    // Set default ports
    $application->ports_exposes = '80';

    $application->save();

    // Auto-generate domain if not provided
    if (empty($application->fqdn)) {
        $application->fqdn = generateUrl(server: $server, random: $application->uuid);
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
            'status' => $application->status,
            'created_at' => $application->created_at,
            'updated_at' => $application->updated_at,
            'project' => $application->environment->project,
            'environment' => $application->environment,
            'recent_deployments' => $recentDeployments,
            'environment_variables_count' => $envVarsCount,
        ],
    ]);
})->name('applications.show');

// Application action routes
Route::post('/applications/{uuid}/deploy', function (string $uuid) {
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

    return Inertia::render('Applications/Rollback/Index', [
        'application' => $application,
        'projectUuid' => $project->uuid,
        'environmentUuid' => $environment->uuid,
    ]);
})->name('applications.rollback');

Route::get('/applications/{uuid}/rollback/{deploymentUuid}', function (string $uuid, string $deploymentUuid) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $project = $application->environment->project;
    $environment = $application->environment;

    return Inertia::render('Applications/Rollback/Show', [
        'application' => $application,
        'deploymentUuid' => $deploymentUuid,
        'projectUuid' => $project->uuid,
        'environmentUuid' => $environment->uuid,
    ]);
})->name('applications.rollback.show');

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

    return Inertia::render('Applications/Settings/Index', [
        'application' => $application,
        'projectUuid' => $project->uuid,
        'environmentUuid' => $environment->uuid,
    ]);
})->name('applications.settings');

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
        ->paginate(20)
        ->through(function ($deployment) {
            return [
                'id' => $deployment->id,
                'deployment_uuid' => $deployment->deployment_uuid,
                'status' => $deployment->status,
                'commit' => $deployment->commit,
                'commit_message' => $deployment->commitMessage(),
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
