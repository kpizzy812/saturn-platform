<?php

namespace App\Http\Controllers\Api;

use App\Actions\Database\StartDatabase;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\GitAnalyzer\AnalyzeGitRequest;
use App\Http\Requests\Api\GitAnalyzer\ProvisionGitRequest;
use App\Models\Environment;
use App\Models\GithubApp;
use App\Models\GitlabApp;
use App\Models\PrivateKey;
use App\Services\RepositoryAnalyzer\DTOs\AnalysisResult;
use App\Services\RepositoryAnalyzer\Exceptions\RepositoryAnalysisException;
use App\Services\RepositoryAnalyzer\InfrastructureProvisioner;
use App\Services\RepositoryAnalyzer\RepositoryAnalyzer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class GitAnalyzerController extends Controller
{
    /**
     * Maximum time for repository clone (seconds)
     */
    private const CLONE_TIMEOUT = 120;

    public function __construct(
        private RepositoryAnalyzer $analyzer,
        private InfrastructureProvisioner $provisioner,
    ) {}

    /**
     * POST /api/v1/git/analyze
     *
     * Analyze a git repository to detect applications and dependencies.
     */
    public function analyze(AnalyzeGitRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Validate repository URL format
        try {
            $this->validateRepositoryUrl($validated['git_repository']);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Validate private key access if provided
        if (isset($validated['private_key_id'])) {
            $privateKey = PrivateKey::findOrFail($validated['private_key_id']);
            Gate::authorize('view', $privateKey);
        }

        $tempPath = null;

        try {
            $tempPath = $this->cloneRepository($validated);
            $result = $this->analyzer->analyze($tempPath);

            // Fix temp-dir app names with actual repository name
            $repoName = $this->extractRepoName($validated['git_repository']);
            $result = $this->fixTempDirAppNames($result, $repoName);

            return response()->json([
                'success' => true,
                'data' => array_merge($result->toArray(), [
                    'repository_name' => $repoName,
                    'git_branch' => $validated['git_branch'] ?? 'main',
                ]),
            ]);
        } catch (RepositoryAnalysisException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } finally {
            if ($tempPath !== null) {
                $this->cleanupTempDirectory($tempPath);
            }
        }
    }

    /**
     * POST /api/v1/git/provision
     *
     * Create infrastructure based on analysis result.
     */
    public function provision(ProvisionGitRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Validate repository URL format
        try {
            $this->validateRepositoryUrl($validated['git_repository']);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Authorize: user must be able to deploy to this environment
        $environment = Environment::where('uuid', $validated['environment_uuid'])->firstOrFail();
        if (! auth()->user()->canDeployToEnvironment($environment)) {
            abort(403, 'You do not have permission to deploy to this environment.');
        }

        // Determine source type class
        $sourceType = match ($validated['source_type'] ?? null) {
            'github' => GithubApp::class,
            'gitlab' => GitlabApp::class,
            default => null,
        };

        // Resolve source_id from github_app_id when not explicitly provided
        $sourceId = $validated['source_id'] ?? null;
        if (! $sourceId && ! empty($validated['github_app_id'])) {
            $sourceId = $validated['github_app_id'];
            $sourceType = $sourceType ?? GithubApp::class;
        }

        $tempPath = null;

        try {
            $tempPath = $this->cloneRepository($validated);

            $analysis = $this->analyzer->analyze($tempPath);

            // Fix temp-dir names before filtering (frontend sends fixed names)
            $repoName = $this->extractRepoName($validated['git_repository']);
            $analysis = $this->fixTempDirAppNames($analysis, $repoName);

            $analysis = $this->filterAnalysis($analysis, $validated);

            // Build per-app overrides from user input
            $appOverrides = collect($validated['applications'])
                ->keyBy('name')
                ->map(fn ($a) => array_filter([
                    'base_directory' => $a['base_directory'] ?? null,
                    'application_type' => $a['application_type'] ?? null,
                    'env_vars' => $a['env_vars'] ?? null,
                ]))
                ->toArray();

            $result = $this->provisioner->provision(
                $analysis,
                $environment,
                $validated['destination_uuid'],
                [
                    'git_repository' => $validated['git_repository'],
                    'git_branch' => $validated['git_branch'] ?? 'main',
                    'private_key_id' => $validated['private_key_id'] ?? null,
                    'source_id' => $sourceId,
                    'source_type' => $sourceType,
                ],
                appOverrides: $appOverrides,
            );

            // Queue deployments
            foreach ($result->applications as $app) {
                queue_application_deployment(
                    application: $app,
                    deployment_uuid: (string) Str::uuid(),
                    force_rebuild: true
                );
            }

            // Start databases
            foreach ($result->databases as $database) {
                StartDatabase::dispatch($database);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'applications' => collect($result->applications)->map(fn ($a) => [
                        'uuid' => $a->uuid,
                        'name' => $a->name,
                        'fqdn' => $a->fqdn,
                    ])->values(),
                    'databases' => collect($result->databases)->map(fn ($d) => [
                        'uuid' => $d->uuid,
                        'name' => $d->name,
                        'type' => $d->database_type ?? $d->type ?? 'unknown',
                    ])->values(),
                    'persistent_volumes' => collect($analysis->persistentVolumes)->map(fn ($v) => [
                        'name' => $v->name,
                        'mount_path' => $v->mountPath,
                        'reason' => $v->reason,
                        'for_app' => $v->forApp,
                    ])->values(),
                    'monorepo_group_id' => $result->monorepoGroupId,
                ],
            ]);
        } catch (RepositoryAnalysisException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            // Log unexpected errors for debugging
            report($e);

            return response()->json([
                'success' => false,
                'error' => 'An unexpected error occurred: '.$e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        } finally {
            if ($tempPath !== null) {
                $this->cleanupTempDirectory($tempPath);
            }
        }
    }

    /**
     * Extract human-readable repository name from git URL
     *
     * https://github.com/owner/repo.git → repo
     * git@github.com:owner/repo.git → repo
     */
    private function extractRepoName(string $gitRepository): string
    {
        $repo = preg_replace('/\.git$/', '', $gitRepository);
        $repo = basename($repo);

        return $repo ?: 'app';
    }

    /**
     * Replace temp-directory app names with the actual repository name
     *
     * When cloning to /tmp/saturn-repo-UUID, AppDetector::inferAppName()
     * produces UUID-based names for root-level apps. This replaces them.
     */
    private function fixTempDirAppNames(AnalysisResult $result, string $repoName): AnalysisResult
    {
        $fixedApps = [];
        $nameMap = []; // old name → new name (for fixing consumers in databases)

        foreach ($result->applications as $app) {
            if (str_starts_with($app->name, 'saturn-repo-')) {
                $nameMap[$app->name] = $repoName;
                $fixedApps[] = $app->withName($repoName);
            } else {
                $fixedApps[] = $app;
            }
        }

        // Also fix database consumer names
        $fixedDatabases = [];
        foreach ($result->databases as $db) {
            $fixedConsumers = array_map(
                fn ($c) => $nameMap[$c] ?? $c,
                $db->consumers
            );
            if ($fixedConsumers !== $db->consumers) {
                $fixedDatabases[] = new \App\Services\RepositoryAnalyzer\DTOs\DetectedDatabase(
                    type: $db->type,
                    name: $db->name,
                    envVarName: $db->envVarName,
                    consumers: $fixedConsumers,
                    detectedVia: $db->detectedVia,
                    port: $db->port,
                );
            } else {
                $fixedDatabases[] = $db;
            }
        }

        // Fix env variable forApp references
        $fixedEnvVars = [];
        foreach ($result->envVariables as $env) {
            if (isset($nameMap[$env->forApp])) {
                $fixedEnvVars[] = new \App\Services\RepositoryAnalyzer\DTOs\DetectedEnvVariable(
                    key: $env->key,
                    defaultValue: $env->defaultValue,
                    isRequired: $env->isRequired,
                    category: $env->category,
                    forApp: $nameMap[$env->forApp],
                    comment: $env->comment,
                );
            } else {
                $fixedEnvVars[] = $env;
            }
        }

        return new AnalysisResult(
            monorepo: $result->monorepo,
            applications: $fixedApps,
            databases: $fixedDatabases,
            services: $result->services,
            envVariables: $fixedEnvVars,
            appDependencies: $result->appDependencies,
            dockerComposeServices: $result->dockerComposeServices,
            ciConfig: $result->ciConfig,
            persistentVolumes: $result->persistentVolumes,
        );
    }

    /**
     * Validate that the URL is a proper git repository URL
     *
     * Rejects URLs that are user profile pages, contain query parameters,
     * or don't have the proper owner/repo format.
     *
     * @throws \RuntimeException
     */
    private function validateRepositoryUrl(string $url): void
    {
        // Check for query parameters (e.g., ?tab=repositories)
        if (str_contains($url, '?')) {
            throw new \RuntimeException(
                'Invalid repository URL: URL contains query parameters. Please provide a direct repository URL like https://github.com/owner/repo'
            );
        }

        // Parse HTTPS URLs for GitHub/GitLab/Bitbucket
        if (preg_match('#^https?://([^/]+)/(.+)$#', $url, $matches)) {
            $host = $matches[1];
            $path = trim($matches[2], '/');

            // Remove .git suffix for validation
            $path = preg_replace('/\.git$/', '', $path);

            // Count path segments
            $segments = explode('/', $path);

            // GitHub, GitLab, Bitbucket require at least owner/repo
            $knownHosts = ['github.com', 'gitlab.com', 'bitbucket.org'];
            if (in_array($host, $knownHosts, true)) {
                if (count($segments) < 2 || empty($segments[1])) {
                    throw new \RuntimeException(
                        "Invalid repository URL: This appears to be a user profile page, not a repository. Please provide a URL in the format https://{$host}/owner/repository"
                    );
                }
            }
        }

        // Parse SSH URLs (git@host:owner/repo.git)
        if (preg_match('#^git@([^:]+):(.+)$#', $url, $matches)) {
            $path = trim($matches[2], '/');
            $path = preg_replace('/\.git$/', '', $path);

            $segments = explode('/', $path);
            if (count($segments) < 2 || empty($segments[1])) {
                throw new \RuntimeException(
                    'Invalid repository URL: SSH URL must be in format git@host:owner/repository'
                );
            }
        }
    }

    /**
     * Clone repository to temporary directory
     *
     * Uses Laravel Process for better timeout handling and security.
     * For private repos, authenticates via GitHub App installation token.
     *
     * @throws \RuntimeException
     */
    private function cloneRepository(array $config): string
    {
        $tempPath = sys_get_temp_dir().'/saturn-repo-'.Str::uuid();

        $branch = $config['git_branch'] ?? 'main';
        $repository = $config['git_repository'];

        // If github_app_id provided, get installation token for authenticated clone
        if (! empty($config['github_app_id'])) {
            $githubApp = GithubApp::where('id', $config['github_app_id'])
                ->where(function ($query) {
                    $query->where('team_id', currentTeam()->id)
                        ->orWhere('is_system_wide', true);
                })
                ->first();

            if ($githubApp && $githubApp->installation_id) {
                try {
                    $token = generateGithubInstallationToken($githubApp);
                    // Replace https://github.com/owner/repo with token-authenticated URL
                    $repository = preg_replace(
                        '#^https://github\.com/#',
                        "https://x-access-token:{$token}@github.com/",
                        $repository
                    );
                } catch (\Throwable $e) {
                    throw new \RuntimeException(
                        'Failed to authenticate with GitHub App: '.$e->getMessage()
                    );
                }
            }
        }

        // Use Laravel Process facade for timeout support
        $result = Process::timeout(self::CLONE_TIMEOUT)
            ->run([
                'git', 'clone',
                '--depth', '1',
                '--branch', $branch,
                '--single-branch',
                $repository,
                $tempPath,
            ]);

        if (! $result->successful()) {
            throw new \RuntimeException(
                'Failed to clone repository: '.$result->errorOutput()
            );
        }

        return $tempPath;
    }

    /**
     * Cleanup temporary directory
     */
    private function cleanupTempDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        // Validate path is in temp directory (security check)
        $realPath = realpath($path);
        $tempDir = realpath(sys_get_temp_dir());

        if ($realPath && $tempDir && str_starts_with($realPath, $tempDir)) {
            Process::run(['rm', '-rf', $realPath]);
        }
    }

    /**
     * Filter analysis result based on user selection
     *
     * Creates a new AnalysisResult with only enabled apps and databases.
     */
    private function filterAnalysis(AnalysisResult $analysis, array $validated): AnalysisResult
    {
        $enabledApps = collect($validated['applications'])
            ->filter(fn ($a) => $a['enabled'])
            ->pluck('name')
            ->toArray();

        $filteredApps = array_filter(
            $analysis->applications,
            fn ($app) => in_array($app->name, $enabledApps, true)
        );

        $filteredDbs = $analysis->databases;
        if (! empty($validated['databases'])) {
            $enabledDbs = collect($validated['databases'])
                ->filter(fn ($d) => $d['enabled'])
                ->pluck('type')
                ->toArray();

            $filteredDbs = array_filter(
                $analysis->databases,
                fn ($db) => in_array($db->type, $enabledDbs, true)
            );
        }

        // Filter app dependencies to only include enabled apps
        $filteredAppDeps = array_filter(
            $analysis->appDependencies,
            fn ($dep) => in_array($dep->appName, $enabledApps, true)
        );

        // Filter persistent volumes to only include enabled apps
        $filteredVolumes = array_filter(
            $analysis->persistentVolumes,
            fn ($vol) => in_array($vol->forApp, $enabledApps, true)
        );

        // Return new AnalysisResult with filtered data
        return new AnalysisResult(
            monorepo: $analysis->monorepo,
            applications: array_values($filteredApps),
            databases: array_values($filteredDbs),
            services: $analysis->services,
            envVariables: $analysis->envVariables,
            appDependencies: array_values($filteredAppDeps),
            dockerComposeServices: $analysis->dockerComposeServices,
            ciConfig: $analysis->ciConfig,
            persistentVolumes: array_values($filteredVolumes),
        );
    }
}
