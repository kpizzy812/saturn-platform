<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
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

// Project activities JSON endpoint
Route::get('/web-api/projects/{uuid}/activities', function (Request $request, string $uuid) {
    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $limit = min((int) $request->query('limit', 20), 100);
    $offset = max((int) $request->query('offset', 0), 0);
    $actionFilter = $request->query('action');

    $activities = \App\Http\Controllers\Inertia\ActivityHelper::getProjectActivities(
        $project,
        $limit,
        $offset,
        $actionFilter ?: null
    );

    return response()->json([
        'data' => $activities,
        'meta' => [
            'limit' => $limit,
            'offset' => $offset,
            'count' => count($activities),
            'has_more' => count($activities) === $limit,
        ],
    ]);
})->name('web-api.projects.activities');

// Application settings JSON endpoints
Route::get('/web-api/applications/{uuid}', function (string $uuid) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->with(['settings', 'source'])
        ->where('uuid', $uuid)
        ->first();

    if (! $application) {
        return response()->json(['message' => 'Application not found.'], 404);
    }

    $appData = $application->toArray();

    // Add auto-deploy info
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

    $appData['is_auto_deploy_enabled'] = $settings?->is_auto_deploy_enabled ?? false;
    $appData['auto_deploy_status'] = $autoDeployStatus;
    $appData['has_webhook_secret'] = ! empty($application->manual_webhook_secret_github);
    $appData['manual_webhook_secret_github'] = $application->manual_webhook_secret_github;
    $appData['webhook_url'] = url('/webhooks/source/github/events/manual');
    $appData['repository_project_id'] = $application->repository_project_id;
    $appData['source_info'] = $hasRealGithubApp ? [
        'id' => $source->id,
        'name' => $source->name,
        'type' => 'github_app',
    ] : ($source ? [
        'id' => $source->id,
        'name' => $source->name ?? 'Public GitHub',
        'type' => 'public',
    ] : null);

    return response()->json($appData);
})->name('web-api.applications.show');

Route::patch('/web-api/applications/{uuid}', function (string $uuid, Request $request) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->first();

    if (! $application) {
        return response()->json(['message' => 'Application not found.'], 404);
    }

    Gate::authorize('update', $application);

    $allowedFields = [
        'health_check_enabled', 'health_check_path', 'health_check_interval',
        'health_check_timeout', 'health_check_retries',
        'ports_exposes', 'ports_mappings',
        'base_directory', 'publish_directory',
        'install_command', 'build_command', 'start_command',
        'watch_paths', 'git_branch',
    ];

    $data = $request->only($allowedFields);
    $application->update($data);

    // Handle is_auto_deploy_enabled (stored in application_settings)
    if ($request->has('is_auto_deploy_enabled')) {
        $settings = $application->settings;
        if ($settings) {
            $settings->update([
                'is_auto_deploy_enabled' => (bool) $request->input('is_auto_deploy_enabled'),
            ]);
        } else {
            $application->settings()->create([
                'is_auto_deploy_enabled' => (bool) $request->input('is_auto_deploy_enabled'),
            ]);
        }
    }

    // Handle GitHub App re-linking
    if ($request->has('github_app_id')) {
        $githubAppId = (int) $request->input('github_app_id');
        $team = currentTeam();

        if ($githubAppId === 0) {
            // Unlink: revert to public source
            $publicSource = \App\Models\GithubApp::find(0);
            if ($publicSource) {
                $application->source_id = $publicSource->id;
                $application->source_type = \App\Models\GithubApp::class;
                $application->repository_project_id = null;
                $application->save();
            }
        } else {
            // Link to specific GitHub App
            $githubApp = \App\Models\GithubApp::where('id', $githubAppId)
                ->where('team_id', $team->id)
                ->whereNotNull('app_id')
                ->whereNotNull('installation_id')
                ->first();

            if (! $githubApp) {
                return response()->json(['message' => 'GitHub App not found or not accessible.'], 404);
            }

            // Fetch repository_project_id BEFORE updating source — atomic operation
            $repositoryProjectId = null;
            if ($application->git_repository) {
                $repoFullName = str($application->git_repository)
                    ->replace('https://github.com/', '')
                    ->replace('http://github.com/', '')
                    ->replace('.git', '')
                    ->toString();

                if (! preg_match('#^[a-zA-Z0-9._-]+/[a-zA-Z0-9._-]+$#', $repoFullName)) {
                    return response()->json(['message' => 'Invalid repository URL format for GitHub API.'], 422);
                }

                try {
                    $token = generateGithubInstallationToken($githubApp);
                    $repoResponse = Http::GitHub($githubApp->api_url, $token)
                        ->timeout(10)
                        ->get("/repos/{$repoFullName}");
                    if ($repoResponse->successful()) {
                        $repositoryProjectId = $repoResponse->json('id');
                    } else {
                        return response()->json([
                            'message' => 'GitHub API returned '.$repoResponse->status().': could not verify repository access. Check that your GitHub App has access to this repo.',
                        ], 422);
                    }
                } catch (\Throwable $e) {
                    return response()->json([
                        'message' => 'Failed to connect to GitHub API: '.$e->getMessage(),
                    ], 422);
                }
            }

            // All verified — update atomically
            $application->source_id = $githubApp->id;
            $application->source_type = \App\Models\GithubApp::class;
            $application->repository_project_id = $repositoryProjectId;

            // Ensure webhook secret exists
            if (! $application->manual_webhook_secret_github) {
                $application->manual_webhook_secret_github = \Illuminate\Support\Str::random(32);
            }

            $application->save();
        }
    }

    return response()->json($application->fresh()->load(['settings', 'source']));
})->name('web-api.applications.update');

Route::delete('/web-api/applications/{uuid}', function (string $uuid) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->first();

    if (! $application) {
        return response()->json(['message' => 'Application not found.'], 404);
    }

    Gate::authorize('delete', $application);

    $application->delete();

    return response()->json(['message' => 'Application deleted.']);
})->name('web-api.applications.delete');

// Active GitHub Apps for team (for auto-deploy dropdown)
Route::get('/web-api/github-apps/active', function () {
    $team = currentTeam();
    $githubApps = \App\Models\GithubApp::where('team_id', $team->id)
        ->whereNotNull('app_id')
        ->whereNotNull('installation_id')
        ->where('is_public', false)
        ->get(['id', 'name', 'organization', 'app_id', 'installation_id']);

    return response()->json([
        'github_apps' => $githubApps->map(fn ($app) => [
            'id' => $app->id,
            'name' => $app->name,
            'organization' => $app->organization,
        ]),
    ]);
})->name('web-api.github-apps.active');

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

// Deployment AI Analysis routes (web-session authenticated)
Route::get('/web-api/deployments/{uuid}/analysis', function (string $uuid) {
    $deployment = \App\Models\ApplicationDeploymentQueue::where('deployment_uuid', $uuid)->first();

    if (! $deployment) {
        return response()->json([
            'status' => 'not_found',
            'message' => 'Deployment not found',
        ], 404);
    }

    // Check authorization via team ownership
    $application = $deployment->application;
    if (! $application || ! \App\Models\Application::ownedByCurrentTeam()->where('id', $application->id)->exists()) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    $analysis = $deployment->logAnalysis;

    if ($analysis === null) {
        // Return 200 with status instead of 404 to avoid console errors
        return response()->json([
            'status' => 'not_found',
            'analysis' => null,
            'message' => 'No analysis available for this deployment',
        ]);
    }

    return response()->json([
        'status' => $analysis->status,
        'analysis' => [
            'id' => $analysis->id,
            'root_cause' => $analysis->root_cause,
            'root_cause_details' => $analysis->root_cause_details,
            'solution' => $analysis->solution,
            'prevention' => $analysis->prevention,
            'error_category' => $analysis->error_category,
            'category_label' => $analysis->category_label,
            'severity' => $analysis->severity,
            'severity_color' => $analysis->severity_color,
            'confidence' => $analysis->confidence,
            'confidence_percent' => round($analysis->confidence * 100),
            'provider' => $analysis->provider,
            'model' => $analysis->model,
            'tokens_used' => $analysis->tokens_used,
            'status' => $analysis->status,
            'error_message' => $analysis->error_message,
            'created_at' => $analysis->created_at->toISOString(),
            'updated_at' => $analysis->updated_at->toISOString(),
        ],
    ]);
})->name('web-api.deployments.analysis');

Route::post('/web-api/deployments/{uuid}/analyze', function (string $uuid) {
    if (! config('ai.enabled', true)) {
        return response()->json([
            'error' => 'AI analysis is disabled',
            'hint' => 'Enable AI analysis by setting AI_ANALYSIS_ENABLED=true',
        ], 503);
    }

    $analyzer = app(\App\Services\AI\DeploymentLogAnalyzer::class);
    if (! $analyzer->isAvailable()) {
        return response()->json([
            'error' => 'No AI provider available',
            'hint' => 'Configure at least one AI provider (ANTHROPIC_API_KEY, OPENAI_API_KEY, or Ollama)',
        ], 503);
    }

    $deployment = \App\Models\ApplicationDeploymentQueue::where('deployment_uuid', $uuid)->first();

    if (! $deployment) {
        return response()->json(['error' => 'Deployment not found'], 404);
    }

    // Check authorization via team ownership
    $application = $deployment->application;
    if (! $application || ! \App\Models\Application::ownedByCurrentTeam()->where('id', $application->id)->exists()) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    // Check if already analyzing
    $existingAnalysis = $deployment->logAnalysis;
    if ($existingAnalysis?->isAnalyzing()) {
        return response()->json([
            'status' => 'analyzing',
            'message' => 'Analysis is already in progress',
        ]);
    }

    // Dispatch job
    \App\Jobs\AnalyzeDeploymentLogsJob::dispatch($deployment->id);

    return response()->json([
        'status' => 'queued',
        'message' => 'Analysis has been queued',
    ]);
})->name('web-api.deployments.analyze');

Route::get('/web-api/ai/status', function () {
    $isEnabled = config('ai.enabled', true);
    $analyzer = app(\App\Services\AI\DeploymentLogAnalyzer::class);
    $isAvailable = $analyzer->isAvailable();
    $provider = $analyzer->getAvailableProvider();

    return response()->json([
        'enabled' => $isEnabled,
        'available' => $isAvailable,
        'provider' => $provider?->getName(),
        'model' => $provider?->getModel(),
    ]);
})->name('web-api.ai.status');

// Git Repository Analyzer (web-session authenticated)
Route::post('/git/analyze', [\App\Http\Controllers\Api\GitAnalyzerController::class, 'analyze'])
    ->name('web-api.git.analyze');

Route::post('/git/provision', [\App\Http\Controllers\Api\GitAnalyzerController::class, 'provision'])
    ->name('web-api.git.provision');

// Code Review routes (web-session authenticated)
Route::get('/web-api/deployments/{uuid}/code-review', function (string $uuid) {
    $deployment = \App\Models\ApplicationDeploymentQueue::where('deployment_uuid', $uuid)->first();

    if (! $deployment) {
        return response()->json([
            'status' => 'not_found',
            'message' => 'Deployment not found',
        ], 404);
    }

    // Check authorization via team ownership
    $application = $deployment->application;
    if (! $application || ! \App\Models\Application::ownedByCurrentTeam()->where('id', $application->id)->exists()) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    $review = \App\Models\CodeReview::where('deployment_id', $deployment->id)
        ->with('violations')
        ->first();

    if ($review === null) {
        // Try to find by commit SHA (different deployment, same commit)
        $review = \App\Models\CodeReview::where('application_id', $deployment->application_id)
            ->where('commit_sha', $deployment->commit)
            ->with('violations')
            ->first();
    }

    if ($review === null) {
        // Return 200 with status instead of 404 to avoid console errors
        return response()->json([
            'status' => 'not_found',
            'review' => null,
            'message' => 'No code review available for this deployment',
        ]);
    }

    return response()->json([
        'status' => $review->status,
        'review' => [
            'id' => $review->id,
            'deployment_id' => $review->deployment_id,
            'application_id' => $review->application_id,
            'commit_sha' => $review->commit_sha,
            'base_commit_sha' => $review->base_commit_sha,
            'status' => $review->status,
            'status_label' => $review->status_label,
            'status_color' => $review->status_color,
            'summary' => $review->summary,
            'files_analyzed' => $review->files_analyzed ?? [],
            'files_count' => count($review->files_analyzed ?? []),
            'violations_count' => $review->violations_count,
            'critical_count' => $review->critical_count,
            'has_violations' => $review->hasViolations(),
            'has_critical' => $review->hasCriticalViolations(),
            'violations_by_severity' => $review->getViolationsBySeverity(),
            'llm_provider' => $review->llm_provider,
            'llm_model' => $review->llm_model,
            'llm_failed' => $review->llm_failed,
            'duration_ms' => $review->duration_ms,
            'started_at' => $review->started_at?->toISOString(),
            'finished_at' => $review->finished_at?->toISOString(),
            'error_message' => $review->error_message,
            'created_at' => $review->created_at->toISOString(),
            'violations' => $review->violations->map(fn ($v) => [
                'id' => $v->id,
                'rule_id' => $v->rule_id,
                'source' => $v->source,
                'severity' => $v->severity,
                'severity_color' => $v->severity_color,
                'confidence' => $v->confidence,
                'file_path' => $v->file_path,
                'line_number' => $v->line_number,
                'location' => $v->location,
                'message' => $v->message,
                'snippet' => $v->snippet,
                'suggestion' => $v->suggestion,
                'contains_secret' => $v->contains_secret,
                'is_deterministic' => $v->isDeterministic(),
                'created_at' => $v->created_at->toISOString(),
            ]),
        ],
    ]);
})->name('web-api.deployments.code-review');

Route::post('/web-api/deployments/{uuid}/code-review', function (string $uuid) {
    if (! config('ai.code_review.enabled', false)) {
        return response()->json([
            'error' => 'Code review is disabled',
            'hint' => 'Enable code review by setting AI_CODE_REVIEW_ENABLED=true',
        ], 503);
    }

    $deployment = \App\Models\ApplicationDeploymentQueue::where('deployment_uuid', $uuid)->first();

    if (! $deployment) {
        return response()->json(['error' => 'Deployment not found'], 404);
    }

    // Check authorization via team ownership
    $application = $deployment->application;
    if (! $application || ! \App\Models\Application::ownedByCurrentTeam()->where('id', $application->id)->exists()) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    // Check if commit exists
    if (empty($deployment->commit)) {
        return response()->json([
            'error' => 'No commit SHA available',
            'hint' => 'This deployment does not have a commit SHA associated with it',
        ], 400);
    }

    // Check if already analyzing
    $existingReview = \App\Models\CodeReview::where('application_id', $deployment->application_id)
        ->where('commit_sha', $deployment->commit)
        ->first();

    if ($existingReview?->isAnalyzing()) {
        return response()->json([
            'status' => 'analyzing',
            'message' => 'Code review is already in progress',
        ]);
    }

    // Dispatch job
    \App\Jobs\AnalyzeCodeReviewJob::dispatch($deployment->id);

    return response()->json([
        'status' => 'queued',
        'message' => 'Code review has been queued',
    ]);
})->name('web-api.deployments.code-review.trigger');

Route::get('/web-api/code-review/status', function () {
    $settings = \App\Models\InstanceSettings::get();
    $isEnabled = $settings->is_ai_code_review_enabled ?? false;
    $mode = config('ai.code_review.mode', 'report_only');
    $enricher = app(\App\Services\AI\CodeReview\LLMEnricher::class);
    $llmAvailable = $enricher->isAvailable();
    $llmInfo = $enricher->getProviderInfo();

    return response()->json([
        'enabled' => $isEnabled,
        'mode' => $mode,
        'detectors' => [
            'secrets' => config('ai.code_review.detectors.secrets', true),
            'dangerous_functions' => config('ai.code_review.detectors.dangerous_functions', true),
        ],
        'llm' => [
            'enabled' => config('ai.code_review.llm_enrichment', true),
            'available' => $llmAvailable,
            'provider' => $llmInfo['provider'],
            'model' => $llmInfo['model'],
        ],
    ]);
})->name('web-api.code-review.status');

// AI Chat routes
Route::prefix('web-api/ai-chat')->group(function () {
    // Get chat status
    Route::get('/status', function () {
        $chatService = app(\App\Services\AI\Chat\AiChatService::class);
        $providerInfo = $chatService->getProviderInfo();

        return response()->json([
            'enabled' => $chatService->isEnabled(),
            'available' => $chatService->isAvailable(),
            'provider' => $providerInfo['provider'],
            'model' => $providerInfo['model'],
        ]);
    })->name('web-api.ai-chat.status');

    // List sessions
    Route::get('/sessions', function () {
        $sessions = \App\Models\AiChatSession::where('user_id', auth()->id())
            ->where('team_id', currentTeam()->id)
            ->active()
            ->with(['messages' => fn ($q) => $q->latest()->take(1)])
            ->latest()
            ->take(20)
            ->get();

        return response()->json([
            'sessions' => $sessions->map(fn ($s) => [
                'uuid' => $s->uuid,
                'title' => $s->title ?? 'New conversation',
                'context_type' => $s->context_type,
                'context_name' => $s->context_name,
                'last_message' => $s->messages->first()?->content,
                'created_at' => $s->created_at->toIso8601String(),
                'updated_at' => $s->updated_at->toIso8601String(),
            ]),
        ]);
    })->name('web-api.ai-chat.sessions.index');

    // Create new session
    Route::post('/sessions', function (Request $request) {
        $chatService = app(\App\Services\AI\Chat\AiChatService::class);

        if (! $chatService->isEnabledAndAvailable()) {
            return response()->json([
                'error' => 'AI Chat is not available',
            ], 503);
        }

        $session = $chatService->getOrCreateSession(
            user: auth()->user(),
            teamId: currentTeam()->id,
            contextType: $request->input('context_type'),
            contextId: $request->input('context_id'),
            contextName: $request->input('context_name'),
        );

        return response()->json([
            'session' => [
                'uuid' => $session->uuid,
                'title' => $session->title,
                'context_type' => $session->context_type,
                'context_id' => $session->context_id,
                'context_name' => $session->context_name,
            ],
        ]);
    })->name('web-api.ai-chat.sessions.store');

    // Get session messages
    Route::get('/sessions/{uuid}/messages', function (string $uuid) {
        $session = \App\Models\AiChatSession::where('uuid', $uuid)
            ->where('user_id', auth()->id())
            ->where('team_id', currentTeam()->id)
            ->first();

        if (! $session) {
            return response()->json(['error' => 'Session not found'], 404);
        }

        $messages = $session->messages()
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'session' => [
                'uuid' => $session->uuid,
                'title' => $session->title,
                'context_type' => $session->context_type,
                'context_name' => $session->context_name,
            ],
            'messages' => $messages->map(fn ($m) => [
                'uuid' => $m->uuid,
                'role' => $m->role,
                'content' => $m->content,
                'intent' => $m->intent,
                'intent_label' => $m->intent_label,
                'command_status' => $m->command_status,
                'command_result' => $m->command_result,
                'rating' => $m->rating,
                'created_at' => $m->created_at->toIso8601String(),
            ]),
        ]);
    })->name('web-api.ai-chat.sessions.messages');

    // Send message to session
    Route::post('/sessions/{uuid}/messages', function (string $uuid, Request $request) {
        $session = \App\Models\AiChatSession::where('uuid', $uuid)
            ->where('user_id', auth()->id())
            ->where('team_id', currentTeam()->id)
            ->first();

        if (! $session) {
            return response()->json(['error' => 'Session not found'], 404);
        }

        $chatService = app(\App\Services\AI\Chat\AiChatService::class);

        if (! $chatService->isEnabledAndAvailable()) {
            return response()->json([
                'error' => 'AI Chat is not available',
            ], 503);
        }

        $content = $request->input('content');
        if (empty($content)) {
            return response()->json(['error' => 'Message content is required'], 422);
        }

        // Check rate limiting
        $recentMessages = \App\Models\AiChatMessage::where('session_id', $session->id)
            ->where('role', 'user')
            ->where('created_at', '>=', now()->subMinute())
            ->count();

        $rateLimit = config('ai.chat.rate_limit.messages_per_minute', 20);
        if ($recentMessages >= $rateLimit) {
            return response()->json([
                'error' => 'Rate limit exceeded. Please wait a moment.',
            ], 429);
        }

        try {
            $message = $chatService->sendMessage(
                session: $session,
                content: $content,
                executeCommands: $request->boolean('execute_commands', true),
            );

            return response()->json([
                'message' => [
                    'uuid' => $message->uuid,
                    'role' => $message->role,
                    'content' => $message->content,
                    'intent' => $message->intent,
                    'intent_label' => $message->intent_label,
                    'command_status' => $message->command_status,
                    'command_result' => $message->command_result,
                    'created_at' => $message->created_at->toIso8601String(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to process message: '.$e->getMessage(),
            ], 500);
        }
    })->name('web-api.ai-chat.sessions.messages.store');

    // Rate a message
    Route::post('/messages/{uuid}/rate', function (string $uuid, Request $request) {
        $message = \App\Models\AiChatMessage::where('uuid', $uuid)
            ->whereHas('session', fn ($q) => $q
                ->where('user_id', auth()->id())
                ->where('team_id', currentTeam()->id))
            ->first();

        if (! $message) {
            return response()->json(['error' => 'Message not found'], 404);
        }

        $rating = $request->input('rating');
        if (! is_numeric($rating) || $rating < 1 || $rating > 5) {
            return response()->json(['error' => 'Rating must be between 1 and 5'], 422);
        }

        $message->rate((int) $rating);

        return response()->json(['success' => true, 'rating' => (int) $rating]);
    })->name('web-api.ai-chat.messages.rate');

    // Archive (delete) session
    Route::delete('/sessions/{uuid}', function (string $uuid) {
        $session = \App\Models\AiChatSession::where('uuid', $uuid)
            ->where('user_id', auth()->id())
            ->where('team_id', currentTeam()->id)
            ->first();

        if (! $session) {
            return response()->json(['error' => 'Session not found'], 404);
        }

        $session->archive();

        return response()->json(['success' => true]);
    })->name('web-api.ai-chat.sessions.delete');

    // Confirm and execute command
    Route::post('/sessions/{uuid}/confirm', function (string $uuid, Request $request) {
        // Command execution requires admin+ role
        if (! in_array(auth()->user()->role(), ['owner', 'admin'])) {
            return response()->json(['error' => 'You do not have permission to execute AI commands.'], 403);
        }

        $session = \App\Models\AiChatSession::where('uuid', $uuid)
            ->where('user_id', auth()->id())
            ->where('team_id', currentTeam()->id)
            ->first();

        if (! $session) {
            return response()->json(['error' => 'Session not found'], 404);
        }

        $intent = $request->input('intent');
        $params = $request->input('params', []);

        if (empty($intent)) {
            return response()->json(['error' => 'Intent is required'], 422);
        }

        // Dispatch command execution job
        $message = $session->messages()->create([
            'role' => 'user',
            'content' => "Confirmed: {$intent}",
            'intent' => $intent,
            'intent_params' => $params,
            'command_status' => 'pending',
        ]);

        \App\Jobs\ExecuteAiCommandJob::dispatch(
            session: $session,
            message: $message,
            intent: $intent,
            params: $params,
        );

        return response()->json([
            'message' => [
                'uuid' => $message->uuid,
                'role' => $message->role,
                'content' => $message->content,
                'intent' => $message->intent,
                'intent_label' => $message->intent_label,
                'intent_params' => $message->intent_params,
                'command_status' => $message->command_status,
                'command_result' => $message->command_result,
                'created_at' => $message->created_at->toIso8601String(),
            ],
        ]);
    })->name('web-api.ai-chat.sessions.confirm');
});

// AI Analytics routes
Route::prefix('web-api/ai-analytics')->group(function () {
    // Get usage statistics
    Route::get('/usage', function (Request $request) {
        $period = $request->query('period', '30d');
        $teamId = currentTeam()->id;

        $stats = \App\Models\AiUsageLog::getTeamStats($teamId, $period);

        return response()->json($stats);
    })->name('web-api.ai-analytics.usage');

    // Get popular commands
    Route::get('/commands', function () {
        $teamId = currentTeam()->id;

        $commands = \App\Models\AiChatMessage::whereHas('session', fn ($q) => $q->where('team_id', $teamId))
            ->whereNotNull('intent')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('intent, COUNT(*) as count')
            ->groupBy('intent')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return response()->json([
            'commands' => $commands->map(fn ($c) => [
                'intent' => $c->intent,
                'count' => $c->count,
            ]),
        ]);
    })->name('web-api.ai-analytics.commands');

    // Get rating distribution
    Route::get('/ratings', function () {
        $teamId = currentTeam()->id;

        $ratings = \App\Models\AiChatMessage::whereHas('session', fn ($q) => $q->where('team_id', $teamId))
            ->whereNotNull('rating')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->orderBy('rating')
            ->get();

        $total = $ratings->sum('count');
        $average = $total > 0 ? $ratings->sum(fn ($r) => $r->rating * $r->count) / $total : 0;

        return response()->json([
            'ratings' => $ratings->pluck('count', 'rating'),
            'total' => $total,
            'average' => round($average, 2),
        ]);
    })->name('web-api.ai-analytics.ratings');

    // Get daily usage for chart
    Route::get('/daily', function (Request $request) {
        $days = min((int) $request->query('days', 30), 90);
        $teamId = currentTeam()->id;

        $daily = \App\Models\AiUsageLog::where('team_id', $teamId)
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as requests, SUM(input_tokens + output_tokens) as tokens, SUM(cost_usd) as cost')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'daily' => $daily->map(fn ($d) => [
                'date' => $d->date,
                'requests' => $d->requests,
                'tokens' => (int) $d->tokens,
                'cost' => round((float) $d->cost, 4),
            ]),
        ]);
    })->name('web-api.ai-analytics.daily');
});

// Environment resource statuses (lightweight polling endpoint for canvas real-time updates)
Route::get('/web-api/environments/{uuid}/statuses', function (string $uuid) {
    $environment = \App\Models\Environment::where('uuid', $uuid)->firstOrFail();

    // Verify team ownership
    $project = $environment->project;
    if (! $project || ! \App\Models\Project::ownedByCurrentTeam()->where('id', $project->id)->exists()) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    $applications = $environment->applications()->select('id', 'status')->get();
    $databases = $environment->databases(); // Returns collection (concat of all DB types)
    // Services: 'status' is an accessor, not a real column - must load full models
    $services = $environment->services()->with(['applications', 'databases'])->get();

    // Active deployments (queued or in_progress) for all apps in this environment
    $activeDeployments = \App\Models\ApplicationDeploymentQueue::whereIn('application_id', $applications->pluck('id'))
        ->whereIn('status', [\App\Enums\ApplicationDeploymentStatus::QUEUED->value, \App\Enums\ApplicationDeploymentStatus::IN_PROGRESS->value])
        ->select('application_id', 'status')
        ->get()
        ->pluck('status', 'application_id');

    return response()->json([
        'applications' => $applications->pluck('status', 'id'),
        'databases' => $databases->mapWithKeys(fn ($db) => [$db->id => $db->status]),
        'services' => $services->mapWithKeys(fn ($svc) => [$svc->id => $svc->status]),
        'deploying' => $activeDeployments,
    ]);
})->name('web-api.environment.statuses');

// Global resource search for Command Palette
Route::get('/web-api/search', function (Request $request) {
    $query = trim((string) $request->query('q', ''));

    if (mb_strlen($query) < 2) {
        return response()->json(['results' => []]);
    }

    $results = collect();
    $pattern = '%'.str_replace(['%', '_'], ['\%', '\_'], $query).'%';

    // Projects
    $results = $results->concat(
        \App\Models\Project::ownedByCurrentTeam()
            ->where(fn ($q) => $q->where('name', 'ILIKE', $pattern)->orWhere('description', 'ILIKE', $pattern))
            ->limit(5)
            ->get()
            ->map(fn ($p) => ['type' => 'project', 'uuid' => $p->uuid, 'name' => $p->name, 'description' => $p->description, 'href' => "/projects/{$p->uuid}"])
    );

    // Servers
    $results = $results->concat(
        \App\Models\Server::ownedByCurrentTeam()
            ->where(fn ($q) => $q->where('name', 'ILIKE', $pattern)->orWhere('description', 'ILIKE', $pattern)->orWhere('ip', 'ILIKE', $pattern))
            ->limit(5)
            ->get()
            ->map(fn ($s) => ['type' => 'server', 'uuid' => $s->uuid, 'name' => $s->name, 'description' => $s->description ?? $s->ip, 'href' => "/servers/{$s->uuid}"])
    );

    // Applications
    $results = $results->concat(
        \App\Models\Application::ownedByCurrentTeam()
            ->with('environment:id,name,project_id', 'environment.project:id,name')
            ->where(fn ($q) => $q->where('name', 'ILIKE', $pattern)->orWhere('description', 'ILIKE', $pattern)->orWhere('fqdn', 'ILIKE', $pattern))
            ->limit(5)
            ->get()
            ->map(fn ($a) => [
                'type' => 'application', 'uuid' => $a->uuid, 'name' => $a->name,
                'description' => $a->description ?? $a->fqdn, 'href' => "/applications/{$a->uuid}",
                'project_name' => $a->environment?->project?->name,
                'environment_name' => $a->environment?->name,
            ])
    );

    // Services
    $results = $results->concat(
        \App\Models\Service::ownedByCurrentTeam()
            ->with('environment:id,name,project_id', 'environment.project:id,name')
            ->where(fn ($q) => $q->where('name', 'ILIKE', $pattern)->orWhere('description', 'ILIKE', $pattern))
            ->limit(5)
            ->get()
            ->map(fn ($s) => [
                'type' => 'service', 'uuid' => $s->uuid, 'name' => $s->name,
                'description' => $s->description, 'href' => "/services/{$s->uuid}",
                'project_name' => $s->environment?->project?->name,
                'environment_name' => $s->environment?->name,
            ])
    );

    // Databases (all types)
    $databaseModels = [
        \App\Models\StandalonePostgresql::class,
        \App\Models\StandaloneMysql::class,
        \App\Models\StandaloneMongodb::class,
        \App\Models\StandaloneRedis::class,
        \App\Models\StandaloneMariadb::class,
        \App\Models\StandaloneKeydb::class,
        \App\Models\StandaloneDragonfly::class,
        \App\Models\StandaloneClickhouse::class,
    ];

    foreach ($databaseModels as $model) {
        $results = $results->concat(
            $model::ownedByCurrentTeam()
                ->with('environment:id,name,project_id', 'environment.project:id,name')
                ->where(fn ($q) => $q->where('name', 'ILIKE', $pattern)->orWhere('description', 'ILIKE', $pattern))
                ->limit(3)
                ->get()
                ->map(fn ($db) => [
                    'type' => 'database', 'uuid' => $db->uuid, 'name' => $db->name,
                    'description' => $db->description, 'href' => "/databases/{$db->uuid}",
                    'project_name' => $db->environment?->project?->name,
                    'environment_name' => $db->environment?->name,
                ])
        );
    }

    // Sort: exact match first, then prefix, then substring
    $lowerQuery = mb_strtolower($query);
    $sorted = $results->sortBy(function ($item) use ($lowerQuery) {
        $name = mb_strtolower($item['name']);
        if ($name === $lowerQuery) {
            return 0;
        }
        if (str_starts_with($name, $lowerQuery)) {
            return 1;
        }

        return 2;
    })->take(15)->values();

    return response()->json(['results' => $sorted]);
})->middleware('throttle:60,1')->name('web-api.search');

// Command Palette drill-down browse
Route::get('/web-api/command-palette/browse', function (Request $request) {
    $type = $request->query('type', '');
    $parentUuid = $request->query('parent_uuid', '');

    $allowed = ['projects', 'servers', 'applications', 'databases', 'services', 'environments', 'env_resources'];
    if (! in_array($type, $allowed, true)) {
        return response()->json(['items' => []]);
    }

    $items = collect();

    switch ($type) {
        case 'projects':
            $items = \App\Models\Project::ownedByCurrentTeam()
                ->select('id', 'uuid', 'name', 'description')
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->uuid,
                    'name' => $p->name,
                    'description' => $p->description,
                    'href' => "/projects/{$p->uuid}",
                    'has_children' => true,
                    'child_type' => 'environments',
                ]);
            break;

        case 'environments':
            if (! $parentUuid) {
                break;
            }
            $project = \App\Models\Project::ownedByCurrentTeam()
                ->where('uuid', $parentUuid)
                ->first();
            if (! $project) {
                break;
            }
            $items = $project->environments()
                ->select('id', 'uuid', 'name', 'project_id')
                ->get()
                ->map(fn ($e) => [
                    'id' => $e->uuid,
                    'name' => $e->name,
                    'description' => $project->name,
                    'href' => "/projects/{$project->uuid}/{$e->uuid}",
                    'has_children' => true,
                    'child_type' => 'env_resources',
                ]);
            break;

        case 'env_resources':
            if (! $parentUuid) {
                break;
            }
            $env = \App\Models\Environment::ownedByCurrentTeam()
                ->where('uuid', $parentUuid)
                ->with('project:id,uuid,name')
                ->first();
            if (! $env) {
                break;
            }
            $projectName = $env->project->name ?? '';

            // Applications
            $items = $items->concat(
                $env->applications()->select('id', 'uuid', 'name', 'fqdn')->get()
                    ->map(fn ($a) => [
                        'id' => $a->uuid,
                        'name' => $a->name,
                        'description' => $a->fqdn,
                        'href' => "/applications/{$a->uuid}",
                        'has_children' => false,
                        'child_type' => null,
                        'meta' => ['type' => 'application', 'project' => $projectName, 'environment' => $env->name],
                    ])
            );

            // Services
            $items = $items->concat(
                $env->services()->select('id', 'uuid', 'name', 'description')->get()
                    ->map(fn ($s) => [
                        'id' => $s->uuid,
                        'name' => $s->name,
                        'description' => $s->description,
                        'href' => "/services/{$s->uuid}",
                        'has_children' => false,
                        'child_type' => null,
                        'meta' => ['type' => 'service', 'project' => $projectName, 'environment' => $env->name],
                    ])
            );

            // Databases (all types)
            foreach ($env->databases() as $db) {
                $items->push([
                    'id' => $db->uuid,
                    'name' => $db->name,
                    'description' => $db->description,
                    'href' => "/databases/{$db->uuid}",
                    'has_children' => false,
                    'child_type' => null,
                    'meta' => ['type' => 'database', 'project' => $projectName, 'environment' => $env->name],
                ]);
            }
            break;

        case 'servers':
            $items = \App\Models\Server::ownedByCurrentTeam(['id', 'uuid', 'name', 'ip', 'description'])
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->uuid,
                    'name' => $s->name,
                    'description' => $s->description ?? $s->ip,
                    'href' => "/servers/{$s->uuid}",
                    'has_children' => false,
                    'child_type' => null,
                ]);
            break;

        case 'applications':
            $items = \App\Models\Application::ownedByCurrentTeam()
                ->with('environment:id,name,project_id', 'environment.project:id,name')
                ->select('id', 'uuid', 'name', 'fqdn', 'description', 'environment_id')
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->uuid,
                    'name' => $a->name,
                    'description' => $a->description ?? $a->fqdn,
                    'href' => "/applications/{$a->uuid}",
                    'has_children' => false,
                    'child_type' => null,
                    'meta' => [
                        'type' => 'application',
                        'project' => $a->environment?->project?->name,
                        'environment' => $a->environment?->name,
                    ],
                ]);
            break;

        case 'databases':
            $databaseModels = [
                \App\Models\StandalonePostgresql::class,
                \App\Models\StandaloneMysql::class,
                \App\Models\StandaloneMongodb::class,
                \App\Models\StandaloneRedis::class,
                \App\Models\StandaloneMariadb::class,
                \App\Models\StandaloneKeydb::class,
                \App\Models\StandaloneDragonfly::class,
                \App\Models\StandaloneClickhouse::class,
            ];
            foreach ($databaseModels as $model) {
                $items = $items->concat(
                    $model::ownedByCurrentTeam()
                        ->with('environment:id,name,project_id', 'environment.project:id,name')
                        ->select('id', 'uuid', 'name', 'description', 'environment_id')
                        ->get()
                        ->map(fn ($db) => [
                            'id' => $db->uuid,
                            'name' => $db->name,
                            'description' => $db->description,
                            'href' => "/databases/{$db->uuid}",
                            'has_children' => false,
                            'child_type' => null,
                            'meta' => [
                                'type' => 'database',
                                'project' => $db->environment?->project?->name,
                                'environment' => $db->environment?->name,
                            ],
                        ])
                );
            }
            break;

        case 'services':
            $items = \App\Models\Service::ownedByCurrentTeam()
                ->with('environment:id,name,project_id', 'environment.project:id,name')
                ->select('id', 'uuid', 'name', 'description', 'environment_id')
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->uuid,
                    'name' => $s->name,
                    'description' => $s->description,
                    'href' => "/services/{$s->uuid}",
                    'has_children' => false,
                    'child_type' => null,
                    'meta' => [
                        'type' => 'service',
                        'project' => $s->environment?->project?->name,
                        'environment' => $s->environment?->name,
                    ],
                ]);
            break;
    }

    return response()->json(['items' => $items->values()]);
})->middleware('throttle:60,1')->name('web-api.command-palette.browse');
