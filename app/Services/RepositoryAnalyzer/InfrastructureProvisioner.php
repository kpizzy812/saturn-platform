<?php

namespace App\Services\RepositoryAnalyzer;

use App\Models\Application;
use App\Models\Environment;
use App\Models\GithubApp;
use App\Models\ResourceLink;
use App\Models\Server;
use App\Models\StandaloneClickhouse;
use App\Models\StandaloneDocker;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use App\Models\StandaloneRedis;
use App\Services\RepositoryAnalyzer\DTOs\AnalysisResult;
use App\Services\RepositoryAnalyzer\DTOs\AppDependency;
use App\Services\RepositoryAnalyzer\DTOs\DetectedApp;
use App\Services\RepositoryAnalyzer\DTOs\DetectedDatabase;
use App\Services\RepositoryAnalyzer\DTOs\DetectedPersistentVolume;
use App\Services\RepositoryAnalyzer\Exceptions\ProvisioningException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

class InfrastructureProvisioner
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    /**
     * Provision infrastructure based on analysis result
     *
     * Uses database transaction to ensure atomicity - if any step fails,
     * all created resources are rolled back.
     *
     * @throws ProvisioningException
     */
    public function provision(
        AnalysisResult $analysis,
        Environment $environment,
        string $serverUuid,
        array $gitConfig,
        ?string $monorepoGroupId = null,
        array $appOverrides = [],
    ): ProvisioningResult {
        // Generate group ID for monorepo apps
        $groupId = $monorepoGroupId ?? ($analysis->monorepo->isMonorepo ? (string) Str::uuid() : null);

        // Find server and its default StandaloneDocker destination
        if ($serverUuid === 'auto' || empty($serverUuid)) {
            $selectionService = app(\App\Services\ServerSelectionService::class);
            $server = $selectionService->selectOptimalServer($environment);
            if (! $server) {
                throw new ProvisioningException('No available server found.');
            }
        } else {
            $server = Server::where('uuid', $serverUuid)->firstOrFail();
        }
        $destination = $server->standaloneDockers()->firstOrFail();
        $destinationUuid = $destination->uuid;

        try {
            return DB::transaction(function () use ($analysis, $environment, $server, $destination, $destinationUuid, $gitConfig, $groupId, $appOverrides) {
                // 1. Create databases first
                $createdDatabases = $this->createDatabases(
                    $analysis->databases,
                    $environment,
                    $destinationUuid
                );

                // 2. Create applications (sorted by deploy order for monorepos)
                $sortedApps = $this->sortByDeployOrder($analysis->applications, $analysis->appDependencies);
                $createdApps = $this->createApplications(
                    $sortedApps,
                    $environment,
                    $server,
                    $destination,
                    $gitConfig,
                    $groupId,
                    $appOverrides
                );

                // 3. Link databases to applications (ResourceLink with auto_inject)
                $this->createResourceLinks($createdApps, $createdDatabases, $analysis->databases, $environment);

                // 4. Create internal URLs between apps (e.g., API_URL for frontend → backend)
                $this->createInternalAppLinks($createdApps, $analysis->appDependencies, $analysis->applications);

                // 5. Create environment variables from .env.example
                $this->createEnvVariables($createdApps, $analysis->envVariables);

                // 6. Apply user-provided env variable overrides
                $this->applyEnvVarOverrides($createdApps, $appOverrides);

                // 7. Create persistent volumes for file-based databases (SQLite)
                $this->createPersistentVolumes($createdApps, $analysis->persistentVolumes);

                return new ProvisioningResult(
                    applications: $createdApps,
                    databases: $createdDatabases,
                    monorepoGroupId: $groupId,
                );
            });
        } catch (\Throwable $e) {
            $this->logger->error('Infrastructure provisioning failed', [
                'repository' => $gitConfig['git_repository'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            throw new ProvisioningException(
                "Failed to provision infrastructure: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * @param  DetectedDatabase[]  $detectedDatabases
     */
    private function createDatabases(
        array $detectedDatabases,
        Environment $environment,
        string $destinationUuid,
    ): array {
        $created = [];

        foreach ($detectedDatabases as $db) {
            $created[$db->type] = match ($db->type) {
                'postgresql' => $this->createPostgresql($environment, $destinationUuid, $db),
                'mysql' => $this->createMysql($environment, $destinationUuid, $db),
                'mongodb' => $this->createMongodb($environment, $destinationUuid, $db),
                'redis' => $this->createRedis($environment, $destinationUuid, $db),
                'clickhouse' => $this->createClickhouse($environment, $destinationUuid, $db),
                default => null,
            };
        }

        return array_filter($created);
    }

    private function createPostgresql(
        Environment $environment,
        string $destinationUuid,
        DetectedDatabase $db
    ): StandalonePostgresql {
        return create_standalone_postgresql(
            environmentId: $environment->id,
            destinationUuid: $destinationUuid,
            otherData: [
                'name' => $db->name.'-db',
                'description' => 'Auto-provisioned for '.implode(', ', $db->consumers),
            ]
        );
    }

    private function createMysql(
        Environment $environment,
        string $destinationUuid,
        DetectedDatabase $db
    ): StandaloneMysql {
        return create_standalone_mysql(
            $environment->id,
            $destinationUuid,
            [
                'name' => $db->name.'-db',
                'description' => 'Auto-provisioned for '.implode(', ', $db->consumers),
            ]
        );
    }

    private function createMongodb(
        Environment $environment,
        string $destinationUuid,
        DetectedDatabase $db
    ): StandaloneMongodb {
        return create_standalone_mongodb(
            $environment->id,
            $destinationUuid,
            [
                'name' => $db->name.'-db',
                'description' => 'Auto-provisioned for '.implode(', ', $db->consumers),
            ]
        );
    }

    private function createRedis(
        Environment $environment,
        string $destinationUuid,
        DetectedDatabase $db
    ): StandaloneRedis {
        return create_standalone_redis(
            $environment->id,
            $destinationUuid,
            [
                'name' => $db->name.'-cache',
                'description' => 'Auto-provisioned for '.implode(', ', $db->consumers),
            ]
        );
    }

    private function createClickhouse(
        Environment $environment,
        string $destinationUuid,
        DetectedDatabase $db
    ): StandaloneClickhouse {
        return create_standalone_clickhouse(
            $environment->id,
            $destinationUuid,
            [
                'name' => $db->name.'-analytics',
                'description' => 'Auto-provisioned for '.implode(', ', $db->consumers),
            ]
        );
    }

    /**
     * @param  DetectedApp[]  $detectedApps
     */
    private function createApplications(
        array $detectedApps,
        Environment $environment,
        Server $server,
        StandaloneDocker $destination,
        array $gitConfig,
        ?string $groupId,
        array $appOverrides = [],
    ): array {
        $created = [];

        foreach ($detectedApps as $app) {
            $overrides = $appOverrides[$app->name] ?? [];
            $created[$app->name] = $this->createApplication(
                $app,
                $environment,
                $server,
                $destination,
                $gitConfig,
                $groupId,
                $overrides
            );
        }

        return $created;
    }

    private function createApplication(
        DetectedApp $app,
        Environment $environment,
        Server $server,
        StandaloneDocker $destination,
        array $gitConfig,
        ?string $groupId,
        array $overrides = [],
    ): Application {
        $application = new Application;
        $application->uuid = (string) Str::uuid();
        $application->name = $app->name;
        $application->environment_id = $environment->id;
        $application->destination_id = $destination->id;
        $application->destination_type = StandaloneDocker::class;

        // Git configuration
        $application->git_repository = $gitConfig['git_repository'];
        $application->git_branch = $gitConfig['git_branch'] ?? 'main';
        $application->private_key_id = $gitConfig['private_key_id'] ?? null;

        // Handle different git sources (GitHub, GitLab, Bitbucket)
        if (isset($gitConfig['source_id'])) {
            $application->source_id = $gitConfig['source_id'];
            $application->source_type = $gitConfig['source_type'] ?? GithubApp::class;
        }

        // Build configuration
        $application->build_pack = $app->buildPack;

        // Application type: user override takes priority, then detected mode
        $applicationType = $overrides['application_type'] ?? $app->applicationMode;
        $application->application_type = $applicationType;

        // Apply base_directory: user override takes priority
        if (! empty($overrides['base_directory'])) {
            $baseDir = $overrides['base_directory'];
            $application->base_directory = $baseDir === '/' ? '' : $baseDir;
        } else {
            // Always use app subdirectory as base_directory (build context)
            // Dockerfile is expected relative to this directory
            $application->base_directory = $app->path === '.' ? '' : '/'.ltrim($app->path, '/');
        }

        // Workers don't need ports; web/both apps use detected port or default to 80
        if ($applicationType === 'worker') {
            $application->ports_exposes = null;
        } else {
            $application->ports_exposes = $app->defaultPort > 0
                ? (string) $app->defaultPort
                : '80';
        }

        // Apply CI config commands (install, build, start)
        if ($app->installCommand) {
            $application->install_command = $app->installCommand;
        }
        if ($app->buildCommand) {
            $application->build_command = $app->buildCommand;
        }
        if ($app->startCommand) {
            $application->start_command = $app->startCommand;
        }

        // Static site configuration
        if ($app->buildPack === 'static') {
            $application->static_image = 'nginx:alpine';
            if ($app->buildCommand) {
                $application->install_command = $app->installCommand ?? 'npm ci';
                $application->build_command = $app->buildCommand;
            }
            if ($app->publishDirectory) {
                $application->publish_directory = $app->publishDirectory;
            }
        }

        // Health check configuration
        if ($applicationType === 'worker') {
            // Workers don't serve HTTP — disable health check, use container stability check
            $application->health_check_enabled = false;
        } else {
            // Universal defaults that work for any web app
            // Dockerfile apps need longer start_period (migrations, compilation, etc.)
            $isDockerfile = $app->buildPack === 'dockerfile' || $app->dockerfileInfo !== null;
            $application->health_check_interval = 10;
            $application->health_check_timeout = 5;
            $application->health_check_retries = 10;
            $application->health_check_start_period = $isDockerfile ? 30 : 15;

            if ($app->healthCheck) {
                $application->health_check_enabled = true;
                $application->health_check_path = $app->healthCheck->path;
                $application->health_check_method = $app->healthCheck->method ?? 'GET';

                // Use detected values if more generous than defaults
                if ($app->healthCheck->intervalSeconds > 0) {
                    $application->health_check_interval = $app->healthCheck->intervalSeconds;
                }
                if ($app->healthCheck->timeoutSeconds > 0) {
                    $application->health_check_timeout = $app->healthCheck->timeoutSeconds;
                }
                if ($app->healthCheck->retries > 0) {
                    $application->health_check_retries = max($app->healthCheck->retries, 10);
                }
                if ($app->healthCheck->startPeriodSeconds > 0) {
                    $application->health_check_start_period = max(
                        $app->healthCheck->startPeriodSeconds,
                        $application->health_check_start_period
                    );
                }
            }
        }

        // Monorepo group
        if ($groupId) {
            $application->monorepo_group_id = $groupId;
        }

        // Workers don't need a domain — no HTTP traffic to route
        if ($applicationType === 'worker') {
            $application->fqdn = null;
        } else {
            // Generate FQDN from project name + short ID (e.g. "pix11-a1b2c3.saturn.ac")
            $projectName = $environment->project?->name;
            $slug = generateSubdomainFromName($application->name, $server, $projectName);
            $application->fqdn = generateFqdn($server, $slug);
        }

        $application->save();

        return $application;
    }

    /**
     * @param  DetectedDatabase[]  $detectedDatabases
     */
    private function createResourceLinks(
        array $createdApps,
        array $createdDatabases,
        array $detectedDatabases,
        Environment $environment
    ): void {
        foreach ($detectedDatabases as $dbInfo) {
            $database = $createdDatabases[$dbInfo->type] ?? null;
            if (! $database) {
                continue;
            }

            foreach ($dbInfo->consumers as $appName) {
                $application = $createdApps[$appName] ?? null;
                if (! $application) {
                    continue;
                }

                ResourceLink::create([
                    'environment_id' => $environment->id,
                    'source_type' => Application::class,
                    'source_id' => $application->id,
                    'target_type' => get_class($database),
                    'target_id' => $database->id,
                    'auto_inject' => true,
                    'inject_as' => null, // Use default (DATABASE_URL, REDIS_URL, etc.)
                    'use_external_url' => false,
                ]);
            }
        }
    }

    /**
     * Env var prefixes that are exposed to client-side JavaScript (browser).
     * These MUST use public FQDN, not Docker internal URLs.
     */
    private const CLIENT_SIDE_ENV_PREFIXES = [
        'NEXT_PUBLIC_',
        'VITE_',
        'REACT_APP_',
        'NUXT_PUBLIC_',
    ];

    /**
     * Create internal URL environment variables between apps
     *
     * For frontend apps (browser-side): uses public FQDN of the target backend
     * For backend-to-backend: uses Docker internal DNS (container-name:port)
     *
     * @param  AppDependency[]  $appDependencies
     * @param  DetectedApp[]  $detectedApps
     */
    private function createInternalAppLinks(array $createdApps, array $appDependencies, array $detectedApps): void
    {
        // Build a type map from detected apps
        $appTypeMap = [];
        $appFrameworkMap = [];
        foreach ($detectedApps as $detected) {
            $appTypeMap[$detected->name] = $detected->type;
            $appFrameworkMap[$detected->name] = $detected->framework;
        }

        foreach ($appDependencies as $dependency) {
            $sourceApp = $createdApps[$dependency->appName] ?? null;
            if (! $sourceApp || empty($dependency->internalUrls)) {
                continue;
            }

            $sourceType = $appTypeMap[$dependency->appName] ?? 'unknown';
            $sourceFramework = $appFrameworkMap[$dependency->appName] ?? '';
            $isFrontend = $sourceType === 'frontend';

            foreach ($dependency->internalUrls as $envVarName => $targetAppName) {
                $targetApp = $createdApps[$targetAppName] ?? null;
                if (! $targetApp) {
                    continue;
                }

                // Determine if this env var is consumed by client-side JavaScript
                $isClientSideVar = $this->isClientSideEnvVar($envVarName);

                if ($isFrontend || $isClientSideVar) {
                    // Frontend apps or client-side env vars: use public FQDN
                    $url = $targetApp->fqdn;

                    // Adapt env var name for the framework's client-side prefix
                    $envVarName = $this->adaptEnvVarForFramework($envVarName, $sourceFramework);
                } else {
                    // Backend-to-backend: use Docker internal DNS
                    $containerName = $targetApp->uuid;
                    $port = $targetApp->ports_exposes ?? 3000;

                    if (str_contains((string) $port, ',')) {
                        $port = explode(',', (string) $port)[0];
                    }

                    $url = "http://{$containerName}:{$port}";
                }

                $sourceApp->environment_variables()->updateOrCreate(
                    ['key' => $envVarName],
                    [
                        'value' => $url,
                        'is_buildtime' => $isFrontend || $isClientSideVar,
                    ]
                );
            }
        }
    }

    /**
     * Check if env var name is a client-side variable (exposed to browser)
     */
    private function isClientSideEnvVar(string $varName): bool
    {
        foreach (self::CLIENT_SIDE_ENV_PREFIXES as $prefix) {
            if (str_starts_with($varName, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Adapt env var name to use the correct framework-specific client-side prefix
     *
     * For example, API_URL → NEXT_PUBLIC_API_URL for Next.js frontend
     */
    private function adaptEnvVarForFramework(string $envVarName, string $framework): string
    {
        // If already has a client-side prefix, keep it
        if ($this->isClientSideEnvVar($envVarName)) {
            return $envVarName;
        }

        // Add framework-specific prefix for client-side access
        return match (true) {
            str_contains($framework, 'next') => 'NEXT_PUBLIC_'.$envVarName,
            str_contains($framework, 'nuxt') => 'NUXT_PUBLIC_'.$envVarName,
            str_contains($framework, 'vite'),
            str_contains($framework, 'vue'),
            str_contains($framework, 'svelte') => 'VITE_'.$envVarName,
            str_contains($framework, 'react') => 'REACT_APP_'.$envVarName,
            default => $envVarName,
        };
    }

    private function createEnvVariables(array $createdApps, array $envVariables): void
    {
        foreach ($envVariables as $envVar) {
            $application = $createdApps[$envVar->forApp] ?? null;
            if (! $application) {
                continue;
            }

            // Skip database-related variables (will be auto-injected via ResourceLink)
            if ($envVar->category === 'database' || $envVar->category === 'cache') {
                continue;
            }

            // Create placeholder for required variables without value
            $value = $envVar->defaultValue ?? '';
            if ($envVar->isRequired && empty($value)) {
                $value = '# TODO: Set value for '.$envVar->key;
            }

            $application->environment_variables()->updateOrCreate(
                ['key' => $envVar->key],
                [
                    'value' => $value,
                    'is_buildtime' => false,
                ]
            );
        }
    }

    /**
     * Apply user-provided environment variable overrides
     */
    private function applyEnvVarOverrides(array $createdApps, array $appOverrides): void
    {
        foreach ($appOverrides as $appName => $overrides) {
            $application = $createdApps[$appName] ?? null;
            if (! $application || empty($overrides['env_vars'])) {
                continue;
            }

            foreach ($overrides['env_vars'] as $envVar) {
                if (empty($envVar['key']) || ! isset($envVar['value'])) {
                    continue;
                }

                $application->environment_variables()->updateOrCreate(
                    ['key' => $envVar['key']],
                    [
                        'value' => $envVar['value'],
                        'is_buildtime' => false,
                    ]
                );
            }
        }
    }

    /**
     * Create persistent volumes for file-based databases (e.g., SQLite)
     *
     * Unlike PostgreSQL/MySQL which run as separate containers, SQLite stores
     * data in a file inside the app container. Without a persistent volume,
     * this file is lost on every redeployment. This method auto-creates the
     * volume and sets the appropriate environment variable.
     *
     * @param  Application[]  $createdApps
     * @param  DetectedPersistentVolume[]  $persistentVolumes
     */
    private function createPersistentVolumes(array $createdApps, array $persistentVolumes): void
    {
        foreach ($persistentVolumes as $volume) {
            $application = $createdApps[$volume->forApp] ?? null;
            if (! $application) {
                continue;
            }

            // Create persistent volume mount
            $application->persistentStorages()->create([
                'name' => $volume->name,
                'mount_path' => $volume->mountPath,
            ]);

            // Set env var so the app knows where to store the database file
            if ($volume->envVarName && $volume->envVarValue) {
                $application->environment_variables()->updateOrCreate(
                    ['key' => $volume->envVarName],
                    [
                        'value' => $volume->envVarValue,
                        'is_buildtime' => false,
                    ]
                );
            }

            $this->logger->info('Auto-created persistent volume for SQLite', [
                'app' => $volume->forApp,
                'mount_path' => $volume->mountPath,
                'reason' => $volume->reason,
            ]);
        }
    }

    /**
     * Sort applications by deploy order based on dependencies
     *
     * @param  DetectedApp[]  $apps
     * @param  AppDependency[]  $dependencies
     * @return DetectedApp[]
     */
    private function sortByDeployOrder(array $apps, array $dependencies): array
    {
        if (empty($dependencies)) {
            return $apps;
        }

        // Build order map from dependencies
        $orderMap = [];
        foreach ($dependencies as $dep) {
            $orderMap[$dep->appName] = $dep->deployOrder;
        }

        // Sort apps by deploy order
        usort($apps, function (DetectedApp $a, DetectedApp $b) use ($orderMap) {
            $orderA = $orderMap[$a->name] ?? PHP_INT_MAX;
            $orderB = $orderMap[$b->name] ?? PHP_INT_MAX;

            return $orderA <=> $orderB;
        });

        return $apps;
    }
}
