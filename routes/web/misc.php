<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Miscellaneous Routes
|--------------------------------------------------------------------------
|
| Routes for destinations, shared variables, tags, CLI, support,
| demo pages, error pages, subscription, and onboarding.
|
*/

// Dashboard
Route::get('/dashboard', function () {
    $projects = \App\Models\Project::ownedByCurrentTeam()
        ->with([
            'environments.applications',
            'environments.postgresqls',
            'environments.redis',
            'environments.mongodbs',
            'environments.mysqls',
            'environments.mariadbs',
            'environments.keydbs',
            'environments.dragonflies',
            'environments.clickhouses',
            'environments.services',
        ])
        ->get();

    // Merge individual DB relationships into a single "databases" collection per environment
    $projects->each(function ($project) {
        $project->environments->each(function ($env) {
            $env->setAttribute('databases', $env->databases());
            // Remove individual DB type relations from serialization
            $env->unsetRelation('postgresqls');
            $env->unsetRelation('redis');
            $env->unsetRelation('mongodbs');
            $env->unsetRelation('mysqls');
            $env->unsetRelation('mariadbs');
            $env->unsetRelation('keydbs');
            $env->unsetRelation('dragonflies');
            $env->unsetRelation('clickhouses');
        });
    });

    return Inertia::render('Dashboard', [
        'projects' => $projects,
    ]);
})->name('dashboard');

// Subscription Routes
Route::get('/subscription', fn () => Inertia::render('Subscription/Index'))->name('subscription.index');
Route::get('/subscription/plans', fn () => Inertia::render('Subscription/Plans'))->name('subscription.plans');
Route::get('/subscription/checkout', fn () => Inertia::render('Subscription/Checkout'))->name('subscription.checkout');
Route::get('/subscription/success', fn () => Inertia::render('Subscription/Success'))->name('subscription.success');

// Destinations routes
Route::get('/destinations', function () {
    $team = auth()->user()->currentTeam();
    $servers = $team->servers()->with(['standaloneDockers', 'swarmDockers'])->get();

    $destinations = collect();
    foreach ($servers as $server) {
        foreach ($server->standaloneDockers as $docker) {
            $destinations->push([
                'id' => $docker->id,
                'uuid' => $docker->uuid,
                'name' => $docker->name,
                'network' => $docker->network,
                'serverName' => $server->name,
                'serverUuid' => $server->uuid,
                'type' => 'standalone',
                'created_at' => $docker->created_at?->toISOString(),
            ]);
        }
        foreach ($server->swarmDockers as $swarm) {
            $destinations->push([
                'id' => $swarm->id,
                'uuid' => $swarm->uuid,
                'name' => $swarm->name,
                'network' => $swarm->network,
                'serverName' => $server->name,
                'serverUuid' => $server->uuid,
                'type' => 'swarm',
                'created_at' => $swarm->created_at?->toISOString(),
            ]);
        }
    }

    return Inertia::render('Destinations/Index', [
        'destinations' => $destinations->values(),
    ]);
})->name('destinations.index');

Route::get('/destinations/create', function () {
    $servers = \App\Models\Server::ownedByCurrentTeam()
        ->select('id', 'uuid', 'name', 'ip')
        ->get()
        ->map(fn ($s) => [
            'id' => $s->id,
            'uuid' => $s->uuid,
            'name' => $s->name,
            'ip' => $s->ip,
        ]);

    return Inertia::render('Destinations/Create', [
        'servers' => $servers,
    ]);
})->name('destinations.create');

Route::post('/destinations', fn (Request $request) => redirect()->route('destinations.index')->with('success', 'Destination created successfully'))->name('destinations.store');

Route::get('/destinations/{uuid}', fn (string $uuid) => Inertia::render('Destinations/Show', ['uuid' => $uuid]))->name('destinations.show');

// Shared Variables routes
Route::get('/shared-variables', function () {
    $team = auth()->user()->currentTeam();
    $variables = \App\Models\SharedEnvironmentVariable::where('team_id', $team->id)
        ->with(['project', 'environment'])
        ->get()
        ->map(fn ($var) => [
            'id' => $var->id,
            'uuid' => $var->uuid ?? $var->id,
            'key' => $var->key,
            'value' => $var->value,
            'scope' => $var->environment_id ? 'environment' : ($var->project_id ? 'project' : 'team'),
            'project_name' => $var->project?->name,
            'environment_name' => $var->environment?->name,
            'created_at' => $var->created_at?->toISOString(),
        ]);

    return Inertia::render('SharedVariables/Index', [
        'variables' => $variables,
        'team' => ['id' => $team->id, 'name' => $team->name],
    ]);
})->name('shared-variables.index');

Route::get('/shared-variables/create', function () {
    $team = auth()->user()->currentTeam();
    $currentUser = auth()->user();
    $authService = app(\App\Services\Authorization\ProjectAuthorizationService::class);

    $projects = $team->projects()->with('environments')->get()
        ->each(function ($project) use ($authService, $currentUser) {
            $project->setRelation(
                'environments',
                $authService->filterVisibleEnvironments($currentUser, $project, $project->environments)
            );
        });

    return Inertia::render('SharedVariables/Create', [
        'teams' => [['id' => $team->id, 'name' => $team->name]],
        'projects' => $projects->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'team_id' => $team->id]),
        'environments' => $projects->flatMap(fn ($p) => $p->environments->map(fn ($e) => [
            'id' => $e->id,
            'name' => $e->name,
            'project_id' => $p->id,
        ])),
    ]);
})->name('shared-variables.create');

Route::post('/shared-variables', fn (Request $request) => redirect()->route('shared-variables.index')->with('success', 'Shared variable created successfully'))->name('shared-variables.store');

Route::get('/shared-variables/{uuid}', fn (string $uuid) => Inertia::render('SharedVariables/Show', ['uuid' => $uuid]))->name('shared-variables.show');

// Tags routes
Route::get('/tags', function () {
    $tags = \App\Models\Tag::ownedByCurrentTeam()
        ->withCount(['applications', 'services'])
        ->get()
        ->map(fn ($tag) => [
            'id' => $tag->id,
            'name' => $tag->name,
            'applicationsCount' => $tag->applications_count,
            'servicesCount' => $tag->services_count,
            'created_at' => $tag->created_at?->toISOString(),
        ]);

    return Inertia::render('Tags/Index', [
        'tags' => $tags,
    ]);
})->name('tags.index');

Route::get('/tags/{tagName}', fn (string $tagName) => Inertia::render('Tags/Show', ['tagName' => $tagName]))->name('tags.show');

// Environments additional routes
Route::get('/environments/{environmentUuid}/secrets', fn (string $environmentUuid) => Inertia::render('Environments/Secrets', ['environmentUuid' => $environmentUuid]))->name('environments.secrets');

Route::get('/environments/{environmentUuid}/variables', fn (string $environmentUuid) => Inertia::render('Environments/Variables', ['environmentUuid' => $environmentUuid]))->name('environments.variables');

// CLI routes
Route::get('/cli/setup', fn () => Inertia::render('CLI/Setup'))->name('cli.setup');
Route::get('/cli/commands', fn () => Inertia::render('CLI/Commands'))->name('cli.commands');

// Support routes
Route::get('/support', fn () => Inertia::render('Support/Index'))->name('support.index');

// Onboarding routes
Route::get('/onboarding/welcome', fn () => Inertia::render('Onboarding/Welcome'))->name('onboarding.welcome');

Route::get('/onboarding/connect-repo', function () {
    // Get GitHub Apps for current team
    $githubApps = \App\Models\GithubApp::where(function ($query) {
        $query->where('team_id', currentTeam()->id)
            ->orWhere('is_system_wide', true);
    })->whereNotNull('app_id')->get();

    return Inertia::render('Onboarding/ConnectRepo', [
        'githubApps' => $githubApps->map(fn ($app) => [
            'id' => $app->id,
            'uuid' => $app->uuid,
            'name' => $app->name,
            'installation_id' => $app->installation_id,
        ]),
    ]);
})->name('onboarding.connect-repo');

// Demo routes
Route::get('/demo', fn () => Inertia::render('Demo/Index'))->name('demo.index');

Route::get('/demo/project', function () {
    $mockProject = [
        'id' => 1,
        'uuid' => 'demo-project-uuid',
        'name' => 'Saturn Demo Project',
        'description' => 'Demo project to preview the Railway-style UI',
        'environments' => [
            [
                'id' => 1,
                'uuid' => 'env-production',
                'name' => 'production',
                'applications' => [
                    [
                        'id' => 1,
                        'uuid' => 'app-api-001',
                        'name' => 'API Server',
                        'description' => 'Main backend API',
                        'status' => 'running',
                        'fqdn' => 'api.saturn.app',
                        'git_repository' => 'github.com/saturn/api',
                        'git_branch' => 'main',
                        'created_at' => now()->subDays(30)->toISOString(),
                        'updated_at' => now()->subHours(2)->toISOString(),
                    ],
                    [
                        'id' => 2,
                        'uuid' => 'app-web-002',
                        'name' => 'Web Frontend',
                        'description' => 'React frontend application',
                        'status' => 'running',
                        'fqdn' => 'app.saturn.app',
                        'git_repository' => 'github.com/saturn/web',
                        'git_branch' => 'main',
                        'created_at' => now()->subDays(25)->toISOString(),
                        'updated_at' => now()->subMinutes(30)->toISOString(),
                    ],
                    [
                        'id' => 3,
                        'uuid' => 'app-worker-003',
                        'name' => 'Background Worker',
                        'description' => 'Job processing worker',
                        'status' => 'running',
                        'fqdn' => null,
                        'git_repository' => 'github.com/saturn/worker',
                        'git_branch' => 'main',
                        'created_at' => now()->subDays(20)->toISOString(),
                        'updated_at' => now()->subHours(1)->toISOString(),
                    ],
                ],
                'databases' => [
                    [
                        'id' => 1,
                        'uuid' => 'db-postgres-001',
                        'name' => 'PostgreSQL Main',
                        'database_type' => 'postgresql',
                        'status' => 'running',
                        'created_at' => now()->subDays(30)->toISOString(),
                        'updated_at' => now()->subHours(5)->toISOString(),
                    ],
                    [
                        'id' => 2,
                        'uuid' => 'db-redis-002',
                        'name' => 'Redis Cache',
                        'database_type' => 'redis',
                        'status' => 'running',
                        'created_at' => now()->subDays(28)->toISOString(),
                        'updated_at' => now()->subHours(3)->toISOString(),
                    ],
                ],
                'services' => [
                    [
                        'id' => 1,
                        'uuid' => 'svc-minio-001',
                        'name' => 'MinIO Storage',
                        'description' => 'S3-compatible object storage',
                        'status' => 'running',
                        'created_at' => now()->subDays(15)->toISOString(),
                        'updated_at' => now()->subHours(8)->toISOString(),
                    ],
                ],
            ],
            [
                'id' => 2,
                'uuid' => 'env-staging',
                'name' => 'staging',
                'applications' => [
                    [
                        'id' => 4,
                        'uuid' => 'app-api-staging',
                        'name' => 'API Server (Staging)',
                        'description' => 'Staging API',
                        'status' => 'stopped',
                        'fqdn' => 'api-staging.saturn.app',
                        'git_repository' => 'github.com/saturn/api',
                        'git_branch' => 'develop',
                        'created_at' => now()->subDays(10)->toISOString(),
                        'updated_at' => now()->subDays(1)->toISOString(),
                    ],
                ],
                'databases' => [],
                'services' => [],
            ],
        ],
        'created_at' => now()->subDays(30)->toISOString(),
        'updated_at' => now()->toISOString(),
    ];

    return Inertia::render('Projects/Show', [
        'project' => $mockProject,
    ]);
})->name('demo.project');

// Error pages (for preview)
Route::get('/errors/404', fn () => Inertia::render('Errors/404'))->name('errors.404');
Route::get('/errors/500', fn () => Inertia::render('Errors/500'))->name('errors.500');
Route::get('/errors/403', fn () => Inertia::render('Errors/403'))->name('errors.403');
Route::get('/errors/maintenance', fn () => Inertia::render('Errors/Maintenance'))->name('errors.maintenance');
