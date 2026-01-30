<?php

namespace App\Services\RepositoryAnalyzer;

use App\Models\Application;
use App\Models\Environment;
use App\Models\GithubApp;
use App\Models\ResourceLink;
use App\Models\StandaloneClickhouse;
use App\Models\StandaloneDocker;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use App\Models\StandaloneRedis;
use App\Services\RepositoryAnalyzer\DTOs\AnalysisResult;
use App\Services\RepositoryAnalyzer\DTOs\DetectedApp;
use App\Services\RepositoryAnalyzer\DTOs\DetectedDatabase;
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
        string $destinationUuid,
        array $gitConfig,
        ?string $monorepoGroupId = null,
    ): ProvisioningResult {
        // Generate group ID for monorepo apps
        $groupId = $monorepoGroupId ?? ($analysis->monorepo->isMonorepo ? (string) Str::uuid() : null);

        try {
            return DB::transaction(function () use ($analysis, $environment, $destinationUuid, $gitConfig, $groupId) {
                // 1. Create databases first
                $createdDatabases = $this->createDatabases(
                    $analysis->databases,
                    $environment,
                    $destinationUuid
                );

                // 2. Create applications
                $createdApps = $this->createApplications(
                    $analysis->applications,
                    $environment,
                    $destinationUuid,
                    $gitConfig,
                    $groupId
                );

                // 3. Link databases to applications (ResourceLink with auto_inject)
                $this->createResourceLinks($createdApps, $createdDatabases, $analysis->databases);

                // 4. Create environment variables from .env.example
                $this->createEnvVariables($createdApps, $analysis->envVariables);

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
        string $destinationUuid,
        array $gitConfig,
        ?string $groupId,
    ): array {
        $created = [];

        foreach ($detectedApps as $app) {
            $created[$app->name] = $this->createApplication(
                $app,
                $environment,
                $destinationUuid,
                $gitConfig,
                $groupId
            );
        }

        return $created;
    }

    private function createApplication(
        DetectedApp $app,
        Environment $environment,
        string $destinationUuid,
        array $gitConfig,
        ?string $groupId,
    ): Application {
        $destination = StandaloneDocker::where('uuid', $destinationUuid)->firstOrFail();
        $server = $destination->server;

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
        $application->base_directory = $app->path === '.' ? '' : '/'.ltrim($app->path, '/');
        $application->ports_exposes = (string) $app->defaultPort;

        // Static site configuration
        if ($app->buildPack === 'static') {
            $application->static_image = 'nginx:alpine';
            if ($app->buildCommand) {
                $application->install_command = 'npm ci';
                $application->build_command = $app->buildCommand;
            }
            if ($app->publishDirectory) {
                $application->publish_directory = $app->publishDirectory;
            }
        }

        // Monorepo group
        if ($groupId) {
            $application->monorepo_group_id = $groupId;
        }

        // Generate FQDN using Saturn's helper
        $application->fqdn = generateFqdn($server, $application->uuid);

        $application->save();

        return $application;
    }

    /**
     * @param  DetectedDatabase[]  $detectedDatabases
     */
    private function createResourceLinks(
        array $createdApps,
        array $createdDatabases,
        array $detectedDatabases
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
                    'is_build_time' => false,
                ]
            );
        }
    }
}
