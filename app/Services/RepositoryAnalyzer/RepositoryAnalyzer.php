<?php

namespace App\Services\RepositoryAnalyzer;

use App\Services\RepositoryAnalyzer\Detectors\AppDetector;
use App\Services\RepositoryAnalyzer\Detectors\DependencyAnalyzer;
use App\Services\RepositoryAnalyzer\Detectors\MonorepoDetector;
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

            // Step 3: Analyze dependencies for each app
            $databases = [];
            $services = [];
            $envVariables = [];

            foreach ($apps as $app) {
                $deps = $this->dependencyAnalyzer->analyze($repoPath, $app);
                $databases = array_merge($databases, $deps->databases);
                $services = array_merge($services, $deps->services);
                $envVariables = array_merge($envVariables, $deps->envVariables);
            }

            // Deduplicate databases (e.g., if both apps need PostgreSQL)
            $databases = $this->deduplicateDatabases($databases);

            return new AnalysisResult(
                monorepo: $monorepoInfo,
                applications: $apps,
                databases: $databases,
                services: $services,
                envVariables: $envVariables,
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
     * @param  DetectedDatabase[]  $databases
     * @return DetectedDatabase[]
     */
    private function deduplicateDatabases(array $databases): array
    {
        $unique = [];
        foreach ($databases as $db) {
            $key = $db->type.'_'.($db->name ?? 'default');
            if (! isset($unique[$key])) {
                $unique[$key] = $db;
            } else {
                // Create new DTO with merged consumers (DTOs are immutable)
                $unique[$key] = $unique[$key]->withMergedConsumers($db->consumers);
            }
        }

        return array_values($unique);
    }
}
