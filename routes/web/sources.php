<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Sources Routes
|--------------------------------------------------------------------------
|
| Routes for managing Git sources (GitHub Apps, GitLab, Bitbucket).
|
*/

Route::prefix('sources')->group(function () {
    Route::get('/', function () {
        $githubApps = \App\Models\GithubApp::ownedByCurrentTeam()->get();
        $gitlabApps = \App\Models\GitlabApp::ownedByCurrentTeam()->get();

        // Combine all sources with type indicator
        // Only include sources that are actually connected
        $sources = collect()
            ->concat($githubApps
                ->filter(fn ($app) => ! is_null($app->app_id) && ! is_null($app->installation_id))
                ->map(fn ($app) => [
                    'id' => $app->id,
                    'uuid' => $app->uuid ?? null,
                    'name' => $app->name,
                    'type' => 'github',
                    'html_url' => $app->html_url,
                    'is_public' => $app->is_public,
                    'status' => 'connected',
                    'repos_count' => $app->applications()->count(),
                    'created_at' => $app->created_at,
                ]))
            ->concat($gitlabApps
                ->filter(fn ($app) => ! is_null($app->app_id) || ! is_null($app->deploy_key_id))
                ->map(fn ($app) => [
                    'id' => $app->id,
                    'uuid' => $app->uuid ?? null,
                    'name' => $app->name,
                    'type' => 'gitlab',
                    'html_url' => $app->html_url ?? null,
                    'is_public' => $app->public_key ? false : true,
                    'status' => 'connected',
                    'repos_count' => $app->applications()->count(),
                    'created_at' => $app->created_at,
                ]));

        return Inertia::render('Sources/Index', [
            'sources' => $sources,
        ]);
    })->name('sources.index');

    // GitHub routes
    Route::prefix('github')->group(function () {
        Route::get('/', function () {
            $apps = \App\Models\GithubApp::ownedByCurrentTeam()->get()->map(fn ($app) => [
                'id' => $app->id,
                'uuid' => $app->uuid ?? null,
                'name' => $app->name,
                'html_url' => $app->html_url,
                'api_url' => $app->api_url,
                'app_id' => $app->app_id,
                'installation_id' => $app->installation_id,
                'is_public' => $app->is_public,
                'is_system_wide' => $app->is_system_wide,
                'organization' => $app->organization,
                'status' => (! is_null($app->app_id) && ! is_null($app->installation_id)) ? 'active' : 'pending',
                'repos_count' => $app->applications()->count(),
                'created_at' => $app->created_at?->toISOString(),
                'last_synced_at' => $app->updated_at?->toISOString(),
            ]);

            return Inertia::render('Sources/GitHub/Index', [
                'apps' => $apps,
            ]);
        })->name('sources.github.index');

        Route::get('/create', function () {
            $fqdn = config('app.url');

            return Inertia::render('Sources/GitHub/Create', [
                'webhookUrl' => $fqdn.'/source/github/events',
                'callbackUrl' => $fqdn.'/source/github/redirect',
            ]);
        })->name('sources.github.create');

        // Create a placeholder GithubApp for the manifest flow
        Route::post('/', function (Request $request) {
            $validated = $request->validate([
                'is_public' => 'sometimes|boolean',
            ]);

            $team = auth()->user()->currentTeam();

            $githubApp = new \App\Models\GithubApp;
            $githubApp->uuid = (string) new \Visus\Cuid2\Cuid2;
            $githubApp->name = 'github-app-pending';
            $githubApp->api_url = 'https://api.github.com';
            $githubApp->html_url = 'https://github.com';
            $githubApp->team_id = $team->id;
            $githubApp->is_public = $validated['is_public'] ?? false;
            $githubApp->save();

            return response()->json([
                'uuid' => $githubApp->uuid,
                'id' => $githubApp->id,
            ]);
        })->name('sources.github.store');

        Route::get('/{id}', function (string $id) {
            // Support both numeric ID and UUID lookup
            $query = \App\Models\GithubApp::ownedByCurrentTeam();
            $source = is_numeric($id)
                ? $query->findOrFail($id)
                : $query->where('uuid', $id)->firstOrFail();

            return Inertia::render('Sources/GitHub/Show', [
                'source' => [
                    'id' => $source->id,
                    'uuid' => $source->uuid,
                    'name' => $source->name,
                    'app_id' => $source->app_id,
                    'client_id' => $source->client_id,
                    'installation_id' => $source->installation_id,
                    'html_url' => $source->html_url,
                    'api_url' => $source->api_url,
                    'organization' => $source->organization,
                    'is_public' => $source->is_public,
                    'is_system_wide' => $source->is_system_wide,
                    'connected' => ! is_null($source->app_id) && ! is_null($source->installation_id),
                    'created_at' => $source->created_at?->toISOString(),
                    'updated_at' => $source->updated_at?->toISOString(),
                ],
                'applicationsCount' => $source->applications()->count(),
            ]);
        })->name('sources.github.show');

        Route::put('/{id}', function (string $id, Request $request) {
            $query = \App\Models\GithubApp::ownedByCurrentTeam();
            $source = is_numeric($id)
                ? $query->findOrFail($id)
                : $query->where('uuid', $id)->firstOrFail();
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'organization' => 'nullable|string|max:255',
                'is_public' => 'sometimes|boolean',
            ]);
            $source->update($validated);

            return back()->with('success', 'GitHub App updated');
        })->name('sources.github.update');

        Route::delete('/{id}', function (string $id) {
            $query = \App\Models\GithubApp::ownedByCurrentTeam();
            $source = is_numeric($id)
                ? $query->findOrFail($id)
                : $query->where('uuid', $id)->firstOrFail();
            $source->delete();

            return redirect()->route('sources.github.index')->with('success', 'GitHub App deleted successfully');
        })->name('sources.github.destroy');
    });

    // GitLab routes
    Route::prefix('gitlab')->group(function () {
        Route::get('/', function () {
            $connections = \App\Models\GitlabApp::ownedByCurrentTeam()->get()->map(fn ($app) => [
                'id' => $app->id,
                'uuid' => $app->uuid ?? null,
                'name' => $app->name,
                'instance_url' => $app->html_url ?? $app->api_url,
                'api_url' => $app->api_url,
                'app_id' => $app->app_id,
                'is_system_wide' => $app->is_system_wide,
                'group' => $app->group_name,
                'status' => (! is_null($app->app_id) || ! is_null($app->deploy_key_id)) ? 'active' : 'pending',
                'repos_count' => $app->applications()->count(),
                'created_at' => $app->created_at?->toISOString(),
                'last_synced_at' => $app->updated_at?->toISOString(),
            ]);

            return Inertia::render('Sources/GitLab/Index', [
                'connections' => $connections,
            ]);
        })->name('sources.gitlab.index');

        Route::get('/create', fn () => Inertia::render('Sources/GitLab/Create'))->name('sources.gitlab.create');

        Route::post('/', function (Request $request) {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'api_url' => 'required|url',
                'html_url' => 'required|url',
                'app_id' => 'nullable|integer',
                'app_secret' => 'nullable|string',
                'group_name' => 'nullable|string|max:255',
            ]);

            $team = auth()->user()->currentTeam();

            $gitlabApp = new \App\Models\GitlabApp;
            $gitlabApp->uuid = (string) new \Visus\Cuid2\Cuid2;
            $gitlabApp->name = $validated['name'];
            $gitlabApp->api_url = $validated['api_url'];
            $gitlabApp->html_url = $validated['html_url'];
            $gitlabApp->app_id = $validated['app_id'] ?? null;
            $gitlabApp->app_secret = $validated['app_secret'] ?? null;
            $gitlabApp->group_name = $validated['group_name'] ?? null;
            $gitlabApp->team_id = $team->id;
            $gitlabApp->save();

            return redirect()->route('sources.gitlab.show', ['id' => $gitlabApp->id])
                ->with('success', 'GitLab connection created successfully');
        })->name('sources.gitlab.store');

        Route::get('/{id}', function (string $id) {
            // Support both numeric ID and UUID lookup
            $query = \App\Models\GitlabApp::ownedByCurrentTeam();
            $source = is_numeric($id)
                ? $query->findOrFail($id)
                : $query->where('uuid', $id)->firstOrFail();

            return Inertia::render('Sources/GitLab/Show', [
                'source' => [
                    'id' => $source->id,
                    'uuid' => $source->uuid,
                    'name' => $source->name,
                    'api_url' => $source->api_url,
                    'html_url' => $source->html_url,
                    'app_id' => $source->app_id,
                    'group_name' => $source->group_name,
                    'deploy_key_id' => $source->deploy_key_id,
                    'is_public' => $source->is_public,
                    'is_system_wide' => $source->is_system_wide,
                    'connected' => ! is_null($source->app_id) || ! is_null($source->deploy_key_id),
                    'created_at' => $source->created_at?->toISOString(),
                    'updated_at' => $source->updated_at?->toISOString(),
                ],
                'applicationsCount' => $source->applications()->count(),
            ]);
        })->name('sources.gitlab.show');

        Route::put('/{id}', function (string $id, Request $request) {
            $query = \App\Models\GitlabApp::ownedByCurrentTeam();
            $source = is_numeric($id)
                ? $query->findOrFail($id)
                : $query->where('uuid', $id)->firstOrFail();
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'api_url' => 'sometimes|url',
                'html_url' => 'sometimes|url',
                'group_name' => 'nullable|string|max:255',
            ]);
            $source->update($validated);

            return back()->with('success', 'GitLab connection updated');
        })->name('sources.gitlab.update');

        Route::delete('/{id}', function (string $id) {
            $query = \App\Models\GitlabApp::ownedByCurrentTeam();
            $source = is_numeric($id)
                ? $query->findOrFail($id)
                : $query->where('uuid', $id)->firstOrFail();
            $source->delete();

            return redirect()->route('sources.gitlab.index')->with('success', 'GitLab App deleted successfully');
        })->name('sources.gitlab.destroy');
    });

    // Bitbucket routes (coming soon)
    Route::get('/bitbucket', function () {
        return Inertia::render('Sources/Bitbucket/Index', [
            'sources' => [],
            'message' => 'Bitbucket integration coming soon',
        ]);
    })->name('sources.bitbucket.index');
});
