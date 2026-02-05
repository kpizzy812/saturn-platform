<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Templates Routes
|--------------------------------------------------------------------------
|
| Routes for browsing and deploying one-click service templates.
|
*/

Route::get('/templates', function () {
    $templateService = app(\App\Services\TemplateService::class);

    return Inertia::render('Templates/Index', [
        'templates' => $templateService->getTemplates()->toArray(),
    ]);
})->name('templates.index');

Route::get('/templates/categories', fn () => Inertia::render('Templates/Categories'))->name('templates.categories');

Route::get('/templates/submit', fn () => Inertia::render('Templates/Submit'))->name('templates.submit');

Route::post('/templates/submit', fn (Request $request) => redirect()->route('templates.index')->with('success', 'Template submitted successfully'))->name('templates.submit.store');

Route::get('/templates/{id}', function (string $id) {
    $templateService = app(\App\Services\TemplateService::class);
    $template = $templateService->getTemplate($id);

    if (! $template) {
        abort(404);
    }

    return Inertia::render('Templates/Show', [
        'template' => $template,
    ]);
})->name('templates.show');

Route::get('/templates/{id}/deploy', function (string $id) {
    $templateService = app(\App\Services\TemplateService::class);
    $template = $templateService->getTemplate($id);

    if (! $template) {
        abort(404);
    }

    // Get projects with environments (filter production for non-admins)
    $authService = app(\App\Services\Authorization\ProjectAuthorizationService::class);
    $currentUser = auth()->user();

    $projects = \App\Models\Project::ownedByCurrentTeam()
        ->with('environments')
        ->get()
        ->each(function ($project) use ($authService, $currentUser) {
            $project->setRelation(
                'environments',
                $authService->filterVisibleEnvironments($currentUser, $project, $project->environments)
            );
        });

    // Get localhost (platform's master server)
    $localhost = \App\Models\Server::where('id', 0)->first();

    // Get user's additional servers
    $userServers = \App\Models\Server::ownedByCurrentTeam()
        ->where('id', '!=', 0)
        ->whereRelation('settings', 'is_usable', true)
        ->get();

    return Inertia::render('Templates/Deploy', [
        'template' => $template,
        'projects' => $projects,
        'localhost' => $localhost,
        'userServers' => $userServers,
        'needsProject' => $projects->isEmpty(),
    ]);
})->name('templates.deploy');

// Template deploy action (POST)
Route::post('/templates/{id}/deploy', function (string $id, Request $request) {
    $templateService = app(\App\Services\TemplateService::class);
    $template = $templateService->getTemplate($id);

    if (! $template) {
        abort(404);
    }

    $validated = $request->validate([
        'project_uuid' => 'required|string',
        'environment_uuid' => 'required|string',
        'server_uuid' => 'required|string',
        'instant_deploy' => 'boolean',
    ]);

    // Find project and environment
    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $validated['project_uuid'])
        ->firstOrFail();

    $environment = $project->environments()
        ->where('uuid', $validated['environment_uuid'])
        ->firstOrFail();

    // Find server
    $localhost = \App\Models\Server::where('id', 0)->first();
    if ($localhost && $localhost->uuid === $validated['server_uuid']) {
        $server = $localhost;
    } else {
        $server = \App\Models\Server::ownedByCurrentTeam()
            ->where('uuid', $validated['server_uuid'])
            ->firstOrFail();
    }

    $destination = $server->destinations()->first();
    if (! $destination) {
        return redirect()->back()->withErrors(['server_uuid' => 'Server has no destinations configured']);
    }

    // Create one-click service using the template
    $result = \App\Actions\Service\CreateOneClickServiceAction::run(
        type: $id,
        server: $server,
        environment: $environment,
        destination: $destination,
        instantDeploy: $validated['instant_deploy'] ?? false
    );

    if (isset($result['error'])) {
        return redirect()->back()->withErrors(['template' => $result['error']]);
    }

    $service = $result['service'];

    return redirect()->route('services.show', $service->uuid)
        ->with('success', 'Service "'.$template['name'].'" deployed successfully!');
})->name('templates.deploy.store');
