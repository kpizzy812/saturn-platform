<?php

namespace App\Services\RepositoryAnalyzer;

use App\Services\RepositoryAnalyzer\Detectors\AppDependencyDetector;
use App\Services\RepositoryAnalyzer\Detectors\AppDetector;
use App\Services\RepositoryAnalyzer\Detectors\CIConfigDetector;
use App\Services\RepositoryAnalyzer\Detectors\DependencyAnalyzer;
use App\Services\RepositoryAnalyzer\Detectors\DockerComposeAnalyzer;
use App\Services\RepositoryAnalyzer\Detectors\DockerfileAnalyzer;
use App\Services\RepositoryAnalyzer\Detectors\HealthCheckDetector;
use App\Services\RepositoryAnalyzer\Detectors\MonorepoDetector;
use App\Services\RepositoryAnalyzer\Detectors\PortDetector;
use App\Services\RepositoryAnalyzer\DTOs\AnalysisResult;
use App\Services\RepositoryAnalyzer\DTOs\DetectedDatabase;
use App\Services\RepositoryAnalyzer\Exceptions\RepositoryAnalysisException;
use JsonException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Exception\ParseException as YamlException;

class RepositoryAnalyzer
{
    /**
     * Maximum repository size in MB (prevents cloning huge repos)
     */
    private const MAX_REPO_SIZE_MB = 500;

    public function __construct(
        private MonorepoDetector $monorepoDetector,
        private AppDetector $appDetector,
        private DependencyAnalyzer $dependencyAnalyzer,
        private DockerComposeAnalyzer $dockerComposeAnalyzer,
        private PortDetector $portDetector,
        private HealthCheckDetector $healthCheckDetector,
        private CIConfigDetector $ciConfigDetector,
        private AppDependencyDetector $appDependencyDetector,
        private DockerfileAnalyzer $dockerfileAnalyzer,
        private LoggerInterface $logger,
    ) {}

    /**
     * Analyze a git repository and return infrastructure proposal
     *
     * @throws RepositoryAnalysisException
     */
    public function analyze(string $repoPath): AnalysisResult
    {
        // Validate path is within allowed directory (prevent path traversal)
        $this->validateRepoPath($repoPath);

        try {
            // Step 1: Detect if monorepo
            $monorepoInfo = $this->monorepoDetector->detect($repoPath);

            // Step 2: Find all applications
            $apps = $monorepoInfo->isMonorepo
                ? $this->appDetector->detectFromMonorepo($repoPath, $monorepoInfo)
                : $this->appDetector->detectSingleApp($repoPath);

            // Step 3: Analyze docker-compose if present
            $dockerComposeResult = $this->dockerComposeAnalyzer->analyze($repoPath);
            $dockerComposeServices = $dockerComposeResult['services'];

            // Step 4: Detect CI/CD configuration (repo-level)
            $ciConfig = $this->ciConfigDetector->detect($repoPath);

            // Step 5: Analyze dependencies for each app and enrich with additional info
            $databases = [];
            $services = [];
            $envVariables = [];
            $persistentVolumes = [];
            $enrichedApps = [];

            foreach ($apps as $app) {
                $appPath = $app->path === '.' ? $repoPath : $repoPath.'/'.$app->path;

                // Analyze dependencies (databases, services, env vars)
                $deps = $this->dependencyAnalyzer->analyze($repoPath, $app);
                $databases = array_merge($databases, $deps->databases);
                $services = array_merge($services, $deps->services);
                $envVariables = array_merge($envVariables, $deps->envVariables);
                $persistentVolumes = array_merge($persistentVolumes, $deps->persistentVolumes);

                // Analyze Dockerfile if present
                $dockerfileInfo = $this->dockerfileAnalyzer->analyze($appPath);
                if ($dockerfileInfo !== null) {
                    $app = $app->withDockerfileInfo($dockerfileInfo);

                    // Use Dockerfile's EXPOSE port if no port detected yet
                    $dockerfilePort = $dockerfileInfo->getPrimaryPort();
                    if ($dockerfilePort !== null && $dockerfilePort !== $app->defaultPort) {
                        $app = $app->withPort($dockerfilePort);
                    }
                }

                // Detect port from source code if not default
                $detectedPort = $this->portDetector->detect($appPath, $app->framework);
                if ($detectedPort !== null && $detectedPort !== $app->defaultPort) {
                    $app = $app->withPort($detectedPort);
                }

                // Detect health check endpoints
                $healthCheck = $this->healthCheckDetector->detect($appPath, $app->framework);
                if ($healthCheck !== null) {
                    $app = $app->withHealthCheck($healthCheck);
                }

                // Apply CI config to app (app-level CI config takes precedence)
                $appCiConfig = $this->ciConfigDetector->detect($repoPath, $appPath);
                if ($appCiConfig !== null) {
                    $app = $app->withCIConfig($appCiConfig);
                } elseif ($ciConfig !== null) {
                    $app = $app->withCIConfig($ciConfig);
                }

                $enrichedApps[] = $app;
            }

            // Step 6: Detect app dependencies and deploy order (for monorepos)
            $appDependencies = [];
            if ($monorepoInfo->isMonorepo && count($enrichedApps) > 1) {
                $appDependencies = $this->appDependencyDetector->analyze($repoPath, $enrichedApps);
            }

            // Merge databases from docker-compose
            $dockerComposeDatabases = $dockerComposeResult['databases'];
            $databases = array_merge($databases, $dockerComposeDatabases);

            // Merge external services from docker-compose
            $dockerComposeExternalServices = $dockerComposeResult['externalServices'];
            $services = array_merge($services, $dockerComposeExternalServices);

            // Deduplicate databases (e.g., if both apps need PostgreSQL)
            $databases = $this->deduplicateDatabases($databases);

            return new AnalysisResult(
                monorepo: $monorepoInfo,
                applications: $enrichedApps,
                databases: $databases,
                services: $services,
                envVariables: $envVariables,
                appDependencies: $appDependencies,
                dockerComposeServices: $dockerComposeServices,
                ciConfig: $ciConfig,
                persistentVolumes: $persistentVolumes,
            );
        } catch (JsonException|YamlException $e) {
            $this->logger->warning('Failed to parse config file', [
                'path' => $repoPath,
                'error' => $e->getMessage(),
            ]);
            throw new RepositoryAnalysisException(
                "Failed to parse repository configuration: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * Validate repository path is safe and within allowed directory
     *
     * @throws RepositoryAnalysisException
     */
    private function validateRepoPath(string $repoPath): void
    {
        $realPath = realpath($repoPath);
        $tempDir = realpath(sys_get_temp_dir());

        if ($realPath === false) {
            throw new RepositoryAnalysisException("Repository path does not exist: {$repoPath}");
        }

        if (! str_starts_with($realPath, $tempDir)) {
            throw new RepositoryAnalysisException('Repository path must be within temp directory');
        }

        // Check directory size
        $sizeMb = $this->getDirectorySizeMb($realPath);
        if ($sizeMb > self::MAX_REPO_SIZE_MB) {
            throw new RepositoryAnalysisException(
                "Repository too large: {$sizeMb}MB (max: ".self::MAX_REPO_SIZE_MB.'MB)'
            );
        }
    }

    private function getDirectorySizeMb(string $path): float
    {
        $output = shell_exec('du -sm '.escapeshellarg($path).' 2>/dev/null | cut -f1');

        return (float) trim($output ?: '0');
    }

    /**
     * Deduplicate databases, merging consumers from duplicates
     *
     * For most use cases, one database per type is enough.
     * Deduplicates by type only, keeping the one with more info (name, port).
     *
     * @param  DetectedDatabase[]  $databases
     * @return DetectedDatabase[]
     */
    private function deduplicateDatabases(array $databases): array
    {
        $unique = [];
        foreach ($databases as $db) {
            // Deduplicate by type only (one PostgreSQL, one Redis, etc.)
            $key = $db->type;

            if (! isset($unique[$key])) {
                $unique[$key] = $db;
            } else {
                // Merge: keep better name, merge consumers
                $existing = $unique[$key];

                // Prefer the entry with a specific name over generic ones
                $betterName = $this->pickBetterDbName($existing->name, $db->name);
                $betterPort = $existing->port ?? $db->port;
                $mergedConsumers = array_unique(array_merge($existing->consumers, $db->consumers));

                $unique[$key] = new DetectedDatabase(
                    type: $db->type,
                    name: $betterName,
                    envVarName: $existing->envVarName,
                    consumers: $mergedConsumers,
                    detectedVia: $existing->detectedVia ?? $db->detectedVia,
                    port: $betterPort,
                );
            }
        }

        return array_values($unique);
    }

    /**
     * Pick the better database name (prefer specific names over defaults)
     */
    private function pickBetterDbName(string $name1, string $name2): string
    {
        $defaults = ['default', 'db', 'database'];

        $isDefault1 = in_array(strtolower($name1), $defaults, true);
        $isDefault2 = in_array(strtolower($name2), $defaults, true);

        if ($isDefault1 && ! $isDefault2) {
            return $name2;
        }

        return $name1;
    }
}
