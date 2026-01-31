<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Boarding Routes
|--------------------------------------------------------------------------
|
| Routes for the onboarding wizard that helps new users get started.
|
*/

Route::get('/boarding', function () {
    $servers = \App\Models\Server::ownedByCurrentTeam()->get(['id', 'name', 'ip']);
    $privateKeys = \App\Models\PrivateKey::ownedByCurrentTeam()->get(['id', 'name']);

    // Get GitHub Apps for current team
    $githubApps = \App\Models\GithubApp::where(function ($query) {
        $query->where('team_id', currentTeam()->id)
            ->orWhere('is_system_wide', true);
    })->whereNotNull('app_id')->get();

    return Inertia::render('Boarding/Index', [
        'userName' => auth()->user()->name,
        'existingServers' => $servers,
        'privateKeys' => $privateKeys,
        'githubApps' => $githubApps->map(fn ($app) => [
            'id' => $app->id,
            'uuid' => $app->uuid,
            'name' => $app->name,
            'installation_id' => $app->installation_id,
        ]),
    ]);
})->name('boarding.index');

Route::post('/boarding/skip', function () {
    $team = currentTeam();
    $team->show_boarding = false;
    $team->save();
    refreshSession($team);

    return redirect()->route('dashboard');
})->name('boarding.skip');

Route::post('/boarding/deploy', function (Request $request) {
    $team = currentTeam();
    if (! $team) {
        return redirect()->route('dashboard')->with('error', 'Please select a team first');
    }

    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'git_repository' => 'required|string',
        'git_branch' => 'nullable|string',
        'server_id' => 'required|integer',
        'github_app_id' => 'nullable|integer',
    ]);

    // Find server
    $server = \App\Models\Server::ownedByCurrentTeam()
        ->where('id', $validated['server_id'])
        ->first();

    if (! $server) {
        return redirect()->back()->withErrors(['server_id' => 'Server not found']);
    }

    $destination = $server->destinations()->first();
    if (! $destination) {
        return redirect()->back()->withErrors(['server_id' => 'Server has no destinations configured']);
    }

    // Find or create default project
    $project = \App\Models\Project::ownedByCurrentTeam()->first();
    if (! $project) {
        $project = \App\Models\Project::create([
            'name' => 'Default Project',
            'team_id' => $team->id,
        ]);
    }

    // Find or create default environment
    $environment = $project->environments()->first();
    if (! $environment) {
        $environment = \App\Models\Environment::create([
            'name' => 'production',
            'project_id' => $project->id,
        ]);
    }

    // Create application
    $application = new \App\Models\Application;
    $application->name = $validated['name'];
    $application->git_repository = $validated['git_repository'];
    $application->git_branch = $validated['git_branch'] ?? 'main';
    $application->build_pack = 'nixpacks';
    $application->environment_id = $environment->id;
    $application->destination_id = $destination->id;
    $application->destination_type = $destination->getMorphClass();
    $application->ports_exposes = '80'; // Will be auto-detected during deployment
    $application->auto_inject_database_url = true; // Enable auto-inject for Railway-like experience

    // Set source (GitHub App or public)
    $githubAppId = $validated['github_app_id'] ?? 0;
    $githubApp = \App\Models\GithubApp::find($githubAppId);
    if ($githubApp) {
        $application->source_type = \App\Models\GithubApp::class;
        $application->source_id = $githubApp->id;
    }

    $application->save();

    // Auto-generate domain
    if (empty($application->fqdn)) {
        $application->fqdn = generateUrl(server: $server, random: $application->uuid);
        $application->save();
    }

    // Auto-inject database URLs from linked databases in the same environment
    $application->autoInjectDatabaseUrl();

    // Queue deployment
    queue_application_deployment(
        application: $application,
        deployment_uuid: (string) \Illuminate\Support\Str::uuid(),
        force_rebuild: false,
    );

    // Mark onboarding as complete
    $team->show_boarding = false;
    $team->save();
    refreshSession($team);

    return redirect()->route('applications.show', $application->uuid)
        ->with('success', 'Application created and deployment started!');
})->name('boarding.deploy');
