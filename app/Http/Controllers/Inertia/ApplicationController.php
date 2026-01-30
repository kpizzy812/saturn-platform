<?php

namespace App\Http\Controllers\Inertia;

use App\Actions\Application\StopApplication;
use App\Http\Controllers\Controller;
use App\Jobs\DeleteResourceJob;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\Environment;
use App\Models\Project;
use App\Models\Server;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Visus\Cuid2\Cuid2;

class ApplicationController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of applications.
     */
    public function index(): Response
    {
        $applications = Application::ownedByCurrentTeam()
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
    }

    /**
     * Show the form for creating a new application.
     */
    public function create(): Response
    {
        $projects = Project::ownedByCurrentTeam()
            ->with('environments')
            ->get();

        $servers = Server::ownedByCurrentTeam()
            ->where('is_usable', true)
            ->get();

        return Inertia::render('Applications/Create', [
            'projects' => $projects,
            'servers' => $servers,
        ]);
    }

    /**
     * Store a newly created application in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Application::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'git_repository' => 'required|string',
            'git_branch' => 'required|string',
            'build_pack' => 'required|string|in:nixpacks,static,dockerfile,dockercompose',
            'environment_id' => 'required|exists:environments,id',
            'server_id' => 'required|exists:servers,id',
            'destination_uuid' => 'nullable|string',
            'ports_exposes' => 'nullable|string',
            'fqdn' => 'nullable|string',
        ]);

        $environment = Environment::findOrFail($validated['environment_id']);
        $server = Server::findOrFail($validated['server_id']);

        // Get destination
        $destinations = $server->destinations();
        if ($destinations->count() == 0) {
            return redirect()->back()->withErrors(['error' => 'Server has no destinations.']);
        }

        $destination = $destinations->first();
        if (isset($validated['destination_uuid'])) {
            $destination = $destinations->where('uuid', $validated['destination_uuid'])->first();
            if (! $destination) {
                return redirect()->back()->withErrors(['error' => 'Destination not found.']);
            }
        }

        // Create application
        $application = new Application;
        $application->name = $validated['name'];
        $application->description = $validated['description'] ?? null;
        $application->git_repository = $validated['git_repository'];
        $application->git_branch = $validated['git_branch'];
        $application->build_pack = $validated['build_pack'];
        $application->fqdn = $validated['fqdn'] ?? null;
        $application->ports_exposes = $validated['ports_exposes'] ?? '80';
        $application->environment_id = $environment->id;
        $application->destination_id = $destination->id;
        $application->destination_type = $destination->getMorphClass();
        $application->save();

        return redirect()->route('applications.show', $application->uuid)
            ->with('success', 'Application created successfully');
    }

    /**
     * Display the specified application.
     */
    public function show(string $uuid): Response
    {
        $application = Application::ownedByCurrentTeam()
            ->with(['environment.project'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        $this->authorize('view', $application);

        // Get recent deployments
        $recentDeployments = ApplicationDeploymentQueue::where('application_id', $application->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($deployment) {
                return [
                    'id' => $deployment->id,
                    'deployment_uuid' => $deployment->deployment_uuid,
                    'status' => $deployment->status,
                    'commit' => $deployment->commit,
                    'created_at' => $deployment->created_at,
                    'updated_at' => $deployment->updated_at,
                ];
            });

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
                'environment_variables_count' => $application->environment_variables->count(),
            ],
        ]);
    }

    /**
     * Deploy the application.
     */
    public function deploy(Request $request, string $uuid): RedirectResponse
    {
        $application = Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $this->authorize('deploy', $application);

        $deployment_uuid = new Cuid2;
        $force_rebuild = $request->boolean('force_rebuild', false);

        $result = queue_application_deployment(
            application: $application,
            deployment_uuid: $deployment_uuid,
            force_rebuild: $force_rebuild,
            is_api: false,
        );

        if ($result['status'] === 'skipped') {
            return redirect()->back()->with('info', $result['message']);
        }

        if ($result['status'] === 'approval_required') {
            return redirect()->back()->with('warning', $result['message']);
        }

        $message = $force_rebuild ? 'Force rebuild started' : 'Deployment started';

        return redirect()->back()->with('success', $message);
    }

    /**
     * Start the application.
     */
    public function start(string $uuid): RedirectResponse
    {
        $application = Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $this->authorize('deploy', $application);

        $deployment_uuid = new Cuid2;

        $result = queue_application_deployment(
            application: $application,
            deployment_uuid: $deployment_uuid,
            force_rebuild: false,
            is_api: false,
        );

        if ($result['status'] === 'skipped') {
            return redirect()->back()->with('info', $result['message']);
        }

        return redirect()->back()->with('success', 'Application started');
    }

    /**
     * Stop the application.
     */
    public function stop(string $uuid): RedirectResponse
    {
        $application = Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $this->authorize('deploy', $application);

        StopApplication::dispatch($application);

        return redirect()->back()->with('success', 'Application stopping request queued');
    }

    /**
     * Restart the application.
     */
    public function restart(string $uuid): RedirectResponse
    {
        $application = Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $this->authorize('deploy', $application);

        $deployment_uuid = new Cuid2;

        $result = queue_application_deployment(
            application: $application,
            deployment_uuid: $deployment_uuid,
            restart_only: true,
            is_api: false,
        );

        if ($result['status'] === 'skipped') {
            return redirect()->back()->with('info', $result['message']);
        }

        return redirect()->back()->with('success', 'Restart request queued');
    }

    /**
     * Remove the specified application from storage.
     */
    public function destroy(string $uuid): RedirectResponse
    {
        $application = Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $this->authorize('delete', $application);

        DeleteResourceJob::dispatch(
            resource: $application,
            deleteVolumes: true,
            deleteConnectedNetworks: true,
            deleteConfigurations: true,
            dockerCleanup: true
        );

        return redirect()->route('applications.index')
            ->with('success', 'Application deletion request queued');
    }

    /**
     * Display application rollback history.
     */
    public function rollback(string $uuid): Response
    {
        $application = Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $project = $application->environment->project;
        $environment = $application->environment;

        return Inertia::render('Applications/Rollback/Index', [
            'application' => $application,
            'projectUuid' => $project->uuid,
            'environmentUuid' => $environment->uuid,
        ]);
    }

    /**
     * Display specific rollback deployment.
     */
    public function rollbackShow(string $uuid, string $deploymentUuid): Response
    {
        $application = Application::ownedByCurrentTeam()
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
    }

    /**
     * Display application preview deployments.
     */
    public function previews(string $uuid): Response
    {
        $application = Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $this->authorize('view', $application);

        $project = $application->environment->project;
        $environment = $application->environment;

        // Fetch actual preview deployments from database
        $previews = $application->previews()
            ->get()
            ->map(function ($preview) {
                return [
                    'id' => $preview->id,
                    'uuid' => $preview->uuid,
                    'pull_request_id' => $preview->pull_request_id,
                    'pull_request_html_url' => $preview->pull_request_html_url,
                    'fqdn' => $preview->fqdn,
                    'status' => $preview->status,
                    'created_at' => $preview->created_at,
                    'updated_at' => $preview->updated_at,
                ];
            });

        return Inertia::render('Applications/Previews/Index', [
            'application' => $application,
            'previews' => $previews,
            'projectUuid' => $project->uuid,
            'environmentUuid' => $environment->uuid,
        ]);
    }

    /**
     * Display preview deployment settings.
     */
    public function previewsSettings(string $uuid): Response
    {
        $application = Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $this->authorize('view', $application);

        $project = $application->environment->project;
        $environment = $application->environment;

        // Fetch actual preview settings from the application
        $settings = [
            'preview_url_template' => $application->preview_url_template,
            'manual_webhook_secret_github' => $application->manual_webhook_secret_github,
            'manual_webhook_secret_gitlab' => $application->manual_webhook_secret_gitlab,
            'manual_webhook_secret_bitbucket' => $application->manual_webhook_secret_bitbucket,
            'manual_webhook_secret_gitea' => $application->manual_webhook_secret_gitea,
        ];

        return Inertia::render('Applications/Previews/Settings', [
            'application' => $application,
            'settings' => $settings,
            'projectUuid' => $project->uuid,
            'environmentUuid' => $environment->uuid,
        ]);
    }

    /**
     * Display specific preview deployment.
     */
    public function previewsShow(string $uuid, string $previewUuid): Response
    {
        $application = Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $this->authorize('view', $application);

        $project = $application->environment->project;
        $environment = $application->environment;

        // Fetch actual preview deployment from database
        $preview = $application->previews()
            ->where('uuid', $previewUuid)
            ->firstOrFail();

        return Inertia::render('Applications/Previews/Show', [
            'application' => $application,
            'preview' => [
                'id' => $preview->id,
                'uuid' => $preview->uuid,
                'pull_request_id' => $preview->pull_request_id,
                'pull_request_html_url' => $preview->pull_request_html_url,
                'fqdn' => $preview->fqdn,
                'status' => $preview->status,
                'created_at' => $preview->created_at,
                'updated_at' => $preview->updated_at,
            ],
            'previewUuid' => $previewUuid,
            'projectUuid' => $project->uuid,
            'environmentUuid' => $environment->uuid,
        ]);
    }

    /**
     * Display application settings.
     */
    public function settings(string $uuid): Response
    {
        $application = Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $project = $application->environment->project;
        $environment = $application->environment;

        return Inertia::render('Applications/Settings/Index', [
            'application' => $application,
            'projectUuid' => $project->uuid,
            'environmentUuid' => $environment->uuid,
        ]);
    }

    /**
     * Display application domains settings.
     */
    public function settingsDomains(string $uuid): Response
    {
        $application = Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $this->authorize('view', $application);

        $project = $application->environment->project;
        $environment = $application->environment;

        // Fetch actual domains from the fqdn field (comma-separated)
        $domains = [];
        if (! empty($application->fqdn)) {
            $domains = array_map('trim', explode(',', $application->fqdn));
        }

        return Inertia::render('Applications/Settings/Domains', [
            'application' => $application,
            'domains' => $domains,
            'projectUuid' => $project->uuid,
            'environmentUuid' => $environment->uuid,
        ]);
    }

    /**
     * Display application environment variables.
     */
    public function settingsVariables(string $uuid): Response
    {
        $application = Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $this->authorize('view', $application);

        $project = $application->environment->project;
        $environment = $application->environment;

        // Fetch actual environment variables from database
        $variables = $application->environment_variables()
            ->get()
            ->map(function ($variable) {
                return [
                    'id' => $variable->id,
                    'uuid' => $variable->uuid,
                    'key' => $variable->key,
                    'value' => $variable->value,
                    'is_build_time' => $variable->is_build_time,
                    'is_literal' => $variable->is_literal,
                    'is_multiline' => $variable->is_multiline,
                    'is_required' => $variable->is_required,
                    'is_shown_once' => $variable->is_shown_once,
                    'created_at' => $variable->created_at,
                    'updated_at' => $variable->updated_at,
                ];
            });

        return Inertia::render('Applications/Settings/Variables', [
            'application' => $application,
            'variables' => $variables,
            'projectUuid' => $project->uuid,
            'environmentUuid' => $environment->uuid,
        ]);
    }

    /**
     * Display application logs.
     */
    public function logs(string $uuid): Response
    {
        $application = Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $project = $application->environment->project;
        $environment = $application->environment;

        return Inertia::render('Applications/Logs', [
            'application' => $application,
            'projectUuid' => $project->uuid,
            'environmentUuid' => $environment->uuid,
        ]);
    }

    /**
     * Display application deployments.
     */
    public function deployments(string $uuid): Response
    {
        $application = Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $this->authorize('view', $application);

        $project = $application->environment->project;
        $environment = $application->environment;

        // Fetch actual deployments from database
        $deployments = $application->deployment_queue()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($deployment) {
                return [
                    'id' => $deployment->id,
                    'deployment_uuid' => $deployment->deployment_uuid,
                    'application_name' => $deployment->application_name,
                    'server_name' => $deployment->server_name,
                    'status' => $deployment->status,
                    'commit' => $deployment->commit,
                    'pull_request_id' => $deployment->pull_request_id,
                    'force_rebuild' => $deployment->force_rebuild,
                    'restart_only' => $deployment->restart_only,
                    'is_webhook' => $deployment->is_webhook,
                    'is_api' => $deployment->is_api,
                    'rollback' => $deployment->rollback,
                    'created_at' => $deployment->created_at,
                    'updated_at' => $deployment->updated_at,
                ];
            });

        return Inertia::render('Applications/Deployments', [
            'application' => $application,
            'deployments' => $deployments,
            'projectUuid' => $project->uuid,
            'environmentUuid' => $environment->uuid,
        ]);
    }
}
