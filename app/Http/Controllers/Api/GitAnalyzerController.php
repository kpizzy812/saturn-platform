<?php

namespace App\Http\Controllers\Api;

use App\Actions\Database\StartDatabase;
use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\GithubApp;
use App\Models\GitlabApp;
use App\Models\PrivateKey;
use App\Services\RepositoryAnalyzer\DTOs\AnalysisResult;
use App\Services\RepositoryAnalyzer\Exceptions\RepositoryAnalysisException;
use App\Services\RepositoryAnalyzer\InfrastructureProvisioner;
use App\Services\RepositoryAnalyzer\RepositoryAnalyzer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    public function analyze(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'git_repository' => ['required', 'string', 'regex:/^(https?:\/\/|git@)/'],
            'git_branch' => ['nullable', 'string', 'max:255'],
            'private_key_id' => ['nullable', 'integer', 'exists:private_keys,id'],
            'source_id' => ['nullable', 'integer'],
            'source_type' => ['nullable', 'string', 'in:github,gitlab,bitbucket'],
        ]);

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

            return response()->json([
                'success' => true,
                'data' => $result->toArray(),
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
    public function provision(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'environment_uuid' => ['required', 'string', 'exists:environments,uuid'],
            'destination_uuid' => ['required', 'string'],
            'git_repository' => ['required', 'string', 'regex:/^(https?:\/\/|git@)/'],
            'git_branch' => ['nullable', 'string', 'max:255'],
            'private_key_id' => ['nullable', 'integer', 'exists:private_keys,id'],
            'source_id' => ['nullable', 'integer'],
            'source_type' => ['nullable', 'string', 'in:github,gitlab,bitbucket'],
            'applications' => ['required', 'array', 'min:1'],
            'applications.*.name' => ['required', 'string'],
            'applications.*.enabled' => ['required', 'boolean'],
            'applications.*.base_directory' => ['nullable', 'string', 'max:255'],
            'applications.*.env_vars' => ['nullable', 'array'],
            'applications.*.env_vars.*.key' => ['required', 'string', 'max:255'],
            'applications.*.env_vars.*.value' => ['required', 'string', 'max:10000'],
            'databases' => ['nullable', 'array'],
            'databases.*.type' => ['required', 'string', 'in:postgresql,mysql,mongodb,redis,clickhouse'],
            'databases.*.enabled' => ['required', 'boolean'],
        ]);

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

        $tempPath = null;

        try {
            $tempPath = $this->cloneRepository($validated);

            $analysis = $this->analyzer->analyze($tempPath);
            $analysis = $this->filterAnalysis($analysis, $validated);

            // Build per-app overrides from user input
            $appOverrides = collect($validated['applications'])
                ->keyBy('name')
                ->map(fn ($a) => array_filter([
                    'base_directory' => $a['base_directory'] ?? null,
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
                    'source_id' => $validated['source_id'] ?? null,
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
     *
     * @throws \RuntimeException
     */
    private function cloneRepository(array $config): string
    {
        $tempPath = sys_get_temp_dir().'/saturn-repo-'.Str::uuid();

        // Build clone command with proper escaping
        $branch = $config['git_branch'] ?? 'main';
        $repository = $config['git_repository'];

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
        );
    }
}
