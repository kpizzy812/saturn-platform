<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web API Routes
|--------------------------------------------------------------------------
|
| JSON API endpoints for session-authenticated web requests.
| These are used by the frontend for AJAX calls.
|
*/

// Team activities JSON endpoint
Route::get('/web-api/team/activities', function (Request $request) {
    $limit = min((int) $request->query('per_page', 10), 100);
    $activities = \App\Http\Controllers\Inertia\ActivityHelper::getTeamActivities($limit);

    return response()->json([
        'data' => $activities,
        'meta' => [
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => $limit,
            'total' => count($activities),
        ],
    ]);
})->name('web-api.team.activities');

// Application settings JSON endpoints
Route::get('/web-api/applications/{uuid}', function (string $uuid) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->first();

    if (! $application) {
        return response()->json(['message' => 'Application not found.'], 404);
    }

    return response()->json($application);
})->name('web-api.applications.show');

Route::patch('/web-api/applications/{uuid}', function (string $uuid, Request $request) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->first();

    if (! $application) {
        return response()->json(['message' => 'Application not found.'], 404);
    }

    $allowedFields = [
        'health_check_enabled', 'health_check_path', 'health_check_interval',
        'health_check_timeout', 'health_check_retries',
        'ports_exposes', 'ports_mappings',
        'base_directory', 'publish_directory',
        'install_command', 'build_command', 'start_command',
        'watch_paths',
    ];

    $data = $request->only($allowedFields);
    $application->update($data);

    return response()->json($application->fresh());
})->name('web-api.applications.update');

Route::delete('/web-api/applications/{uuid}', function (string $uuid) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->first();

    if (! $application) {
        return response()->json(['message' => 'Application not found.'], 404);
    }

    $application->delete();

    return response()->json(['message' => 'Application deleted.']);
})->name('web-api.applications.delete');

// GitHub App API routes
Route::get('/web-api/github-apps/{github_app_id}/repositories', function ($github_app_id) {
    $githubApp = \App\Models\GithubApp::where('id', $github_app_id)
        ->where(function ($query) {
            $query->where('team_id', currentTeam()->id)
                ->orWhere('is_system_wide', true);
        })
        ->firstOrFail();

    $token = generateGithubInstallationToken($githubApp);
    $repositories = collect();
    $page = 1;
    $maxPages = 100;

    while ($page <= $maxPages) {
        $response = Http::GitHub($githubApp->api_url, $token)
            ->timeout(20)
            ->retry(3, 200, throw: false)
            ->get('/installation/repositories', [
                'per_page' => 100,
                'page' => $page,
            ]);

        if ($response->status() !== 200) {
            return response()->json([
                'message' => $response->json()['message'] ?? 'Failed to load repositories',
            ], $response->status());
        }

        $json = $response->json();
        $repos = $json['repositories'] ?? [];

        if (empty($repos)) {
            break;
        }

        $repositories = $repositories->concat($repos);
        $page++;
    }

    return response()->json([
        'repositories' => $repositories->sortBy('name')->values(),
    ]);
})->name('web-api.github-apps.repositories');

Route::get('/web-api/github-apps/{github_app_id}/repositories/{owner}/{repo}/branches', function ($github_app_id, $owner, $repo) {
    $githubApp = \App\Models\GithubApp::where('id', $github_app_id)
        ->where(function ($query) {
            $query->where('team_id', currentTeam()->id)
                ->orWhere('is_system_wide', true);
        })
        ->firstOrFail();

    $token = generateGithubInstallationToken($githubApp);

    $response = Http::GitHub($githubApp->api_url, $token)
        ->timeout(20)
        ->retry(3, 200, throw: false)
        ->get("/repos/{$owner}/{$repo}/branches");

    if ($response->status() !== 200) {
        return response()->json([
            'message' => 'Error loading branches from GitHub.',
            'error' => $response->json('message'),
        ], $response->status());
    }

    return response()->json([
        'branches' => $response->json(),
    ]);
})->name('web-api.github-apps.branches');

// Public git repository branches
Route::get('/web-api/git/branches', function (Request $request) {
    $repositoryUrl = $request->query('repository_url');

    if (empty($repositoryUrl)) {
        return response()->json([
            'message' => 'Repository URL is required',
        ], 400);
    }

    // Parse repository URL
    $url = preg_replace('/\.git$/', '', $repositoryUrl);
    $parsed = null;

    // GitHub
    if (preg_match('#^https?://github\.com/([^/]+)/([^/]+)#', $url, $matches)) {
        $parsed = ['platform' => 'github', 'owner' => $matches[1], 'repo' => $matches[2]];
    }
    // GitLab
    elseif (preg_match('#^https?://gitlab\.com/([^/]+)/([^/]+)#', $url, $matches)) {
        $parsed = ['platform' => 'gitlab', 'owner' => $matches[1], 'repo' => $matches[2]];
    }
    // Bitbucket
    elseif (preg_match('#^https?://bitbucket\.org/([^/]+)/([^/]+)#', $url, $matches)) {
        $parsed = ['platform' => 'bitbucket', 'owner' => $matches[1], 'repo' => $matches[2]];
    }

    if (! $parsed) {
        return response()->json([
            'message' => 'Invalid repository URL. Supported platforms: GitHub, GitLab, Bitbucket',
        ], 400);
    }

    // Cache key
    $cacheKey = 'git_branches_'.md5($repositoryUrl);
    $cached = Cache::get($cacheKey);
    if ($cached) {
        return response()->json($cached);
    }

    $owner = $parsed['owner'];
    $repo = $parsed['repo'];
    $result = null;

    // Fetch branches based on platform
    if ($parsed['platform'] === 'github') {
        $response = Http::withHeaders([
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'Saturn-Platform',
        ])->timeout(15)->retry(2, 100, throw: false)
            ->get("https://api.github.com/repos/{$owner}/{$repo}/branches", ['per_page' => 100]);

        if ($response->failed()) {
            $status = $response->status();
            if ($status === 404) {
                return response()->json(['message' => 'Repository not found or is private'], 404);
            }

            return response()->json(['message' => $response->json('message', 'Failed to fetch branches')], $status);
        }

        $branches = collect($response->json())->map(fn ($b) => ['name' => $b['name'], 'is_default' => false])->toArray();

        // Get default branch
        $repoResponse = Http::withHeaders([
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'Saturn-Platform',
        ])->timeout(10)->get("https://api.github.com/repos/{$owner}/{$repo}");

        $defaultBranch = $repoResponse->json('default_branch', 'main');
        foreach ($branches as &$branch) {
            if ($branch['name'] === $defaultBranch) {
                $branch['is_default'] = true;
            }
        }

        $result = ['branches' => $branches, 'default_branch' => $defaultBranch];
    } elseif ($parsed['platform'] === 'gitlab') {
        $projectPath = urlencode("{$owner}/{$repo}");
        $response = Http::withHeaders(['User-Agent' => 'Saturn-Platform'])
            ->timeout(15)->retry(2, 100, throw: false)
            ->get("https://gitlab.com/api/v4/projects/{$projectPath}/repository/branches", ['per_page' => 100]);

        if ($response->failed()) {
            $status = $response->status();
            if ($status === 404) {
                return response()->json(['message' => 'Repository not found or is private'], 404);
            }

            return response()->json(['message' => $response->json('message', 'Failed to fetch branches')], $status);
        }

        $projectResponse = Http::withHeaders(['User-Agent' => 'Saturn-Platform'])
            ->timeout(10)->get("https://gitlab.com/api/v4/projects/{$projectPath}");
        $defaultBranch = $projectResponse->json('default_branch', 'main');

        $branches = collect($response->json())->map(fn ($b) => [
            'name' => $b['name'],
            'is_default' => $b['name'] === $defaultBranch,
        ])->toArray();

        $result = ['branches' => $branches, 'default_branch' => $defaultBranch];
    } elseif ($parsed['platform'] === 'bitbucket') {
        $response = Http::withHeaders(['User-Agent' => 'Saturn-Platform'])
            ->timeout(15)->retry(2, 100, throw: false)
            ->get("https://api.bitbucket.org/2.0/repositories/{$owner}/{$repo}/refs/branches", ['pagelen' => 100]);

        if ($response->failed()) {
            $status = $response->status();
            if ($status === 404) {
                return response()->json(['message' => 'Repository not found or is private'], 404);
            }

            return response()->json(['message' => $response->json('error.message', 'Failed to fetch branches')], $status);
        }

        $repoResponse = Http::withHeaders(['User-Agent' => 'Saturn-Platform'])
            ->timeout(10)->get("https://api.bitbucket.org/2.0/repositories/{$owner}/{$repo}");
        $defaultBranch = $repoResponse->json('mainbranch.name', 'main');

        $branches = collect($response->json('values', []))->map(fn ($b) => [
            'name' => $b['name'],
            'is_default' => $b['name'] === $defaultBranch,
        ])->toArray();

        $result = ['branches' => $branches, 'default_branch' => $defaultBranch];
    }

    if (! $result) {
        return response()->json(['message' => 'Unsupported platform'], 400);
    }

    // Sort: default first, then alphabetically
    usort($result['branches'], function ($a, $b) {
        if ($a['is_default'] && ! $b['is_default']) {
            return -1;
        }
        if (! $a['is_default'] && $b['is_default']) {
            return 1;
        }

        return strcasecmp($a['name'], $b['name']);
    });

    $responseData = [
        'branches' => $result['branches'],
        'default_branch' => $result['default_branch'],
        'platform' => $parsed['platform'],
    ];

    // Cache for 5 minutes
    Cache::put($cacheKey, $responseData, 300);

    return response()->json($responseData);
})->name('web-api.git.branches');
