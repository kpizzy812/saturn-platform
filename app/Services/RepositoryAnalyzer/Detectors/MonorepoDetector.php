<?php

namespace App\Services\RepositoryAnalyzer\Detectors;

use App\Services\RepositoryAnalyzer\DTOs\MonorepoInfo;
use JsonException;
use Symfony\Component\Yaml\Exception\ParseException as YamlException;
use Symfony\Component\Yaml\Yaml;

class MonorepoDetector
{
    /**
     * Monorepo markers and their types (order matters - more specific first)
     *
     * Note: turbo.json is checked first because Turborepo is often used
     * WITH pnpm-workspace.yaml or npm workspaces. If turbo.json exists,
     * we treat it as Turborepo and look for workspace config inside.
     */
    private const MARKERS = [
        'turbo.json' => 'turborepo',
        'nx.json' => 'nx',
        'lerna.json' => 'lerna',
        'pnpm-workspace.yaml' => 'pnpm',
        'rush.json' => 'rush',
    ];

    /**
     * Maximum file size to read (1MB) - prevents memory issues
     */
    private const MAX_FILE_SIZE = 1024 * 1024;

    public function detect(string $repoPath): MonorepoInfo
    {
        foreach (self::MARKERS as $file => $type) {
            $filePath = $repoPath.'/'.$file;
            if (file_exists($filePath)) {
                $result = $this->parseMonorepoConfig($filePath, $type, $repoPath);
                if ($result->isMonorepo) {
                    return $result;
                }
            }
        }

        // Check for workspaces in root package.json (npm/yarn workspaces)
        $packageJson = $repoPath.'/package.json';
        if (file_exists($packageJson)) {
            $content = $this->readJsonFile($packageJson);
            if ($content !== null && isset($content['workspaces'])) {
                return $this->parseWorkspaces($content['workspaces'], 'npm-workspaces', $repoPath);
            }
        }

        // Fallback: detect "simple" monorepos without standard markers
        // (repositories with multiple app directories in root)
        return $this->detectSimpleMonorepo($repoPath);
    }

    /**
     * Read and parse JSON file with size limit and error handling
     */
    private function readJsonFile(string $path): ?array
    {
        if (! file_exists($path) || filesize($path) > self::MAX_FILE_SIZE) {
            return null;
        }

        try {
            $content = file_get_contents($path);

            return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * Read and parse YAML file with size limit and error handling
     */
    private function readYamlFile(string $path): ?array
    {
        if (! file_exists($path) || filesize($path) > self::MAX_FILE_SIZE) {
            return null;
        }

        try {
            $content = file_get_contents($path);
            $parsed = Yaml::parse($content);

            return is_array($parsed) ? $parsed : null;
        } catch (YamlException) {
            return null;
        }
    }

    private function parseMonorepoConfig(string $filePath, string $type, string $repoPath): MonorepoInfo
    {
        return match ($type) {
            'turborepo' => $this->parseTurborepo($repoPath),
            'pnpm' => $this->parsePnpmWorkspace($filePath, $repoPath),
            'lerna' => $this->parseLerna($filePath, $repoPath),
            'nx' => $this->parseNx($filePath, $repoPath),
            'rush' => $this->parseRush($filePath, $repoPath),
            default => MonorepoInfo::notMonorepo(),
        };
    }

    private function parseTurborepo(string $repoPath): MonorepoInfo
    {
        // Turborepo uses package.json workspaces or pnpm-workspace.yaml
        // turbo.json only defines pipeline, not workspaces

        // Check for pnpm-workspace.yaml first (common with Turborepo)
        $pnpmWorkspace = $repoPath.'/pnpm-workspace.yaml';
        if (file_exists($pnpmWorkspace)) {
            return $this->parsePnpmWorkspace($pnpmWorkspace, $repoPath, 'turborepo');
        }

        // Fall back to package.json workspaces
        $packageJson = $repoPath.'/package.json';
        $pkg = $this->readJsonFile($packageJson);
        if ($pkg !== null && isset($pkg['workspaces'])) {
            return $this->parseWorkspaces($pkg['workspaces'], 'turborepo', $repoPath);
        }

        // No workspace config found - use common Turborepo defaults
        $paths = $this->detectCommonDirectories($repoPath, ['apps', 'packages']);

        return new MonorepoInfo(
            isMonorepo: ! empty($paths),
            type: 'turborepo',
            workspacePaths: $paths ?: ['apps/*', 'packages/*'],
        );
    }

    private function parsePnpmWorkspace(string $filePath, string $repoPath, string $type = 'pnpm'): MonorepoInfo
    {
        $config = $this->readYamlFile($filePath);

        if ($config === null) {
            return MonorepoInfo::notMonorepo();
        }

        $packages = $config['packages'] ?? [];

        if (empty($packages)) {
            return MonorepoInfo::notMonorepo();
        }

        return new MonorepoInfo(
            isMonorepo: true,
            type: $type,
            workspacePaths: $packages,
        );
    }

    private function parseLerna(string $filePath, string $repoPath): MonorepoInfo
    {
        $config = $this->readJsonFile($filePath);

        if ($config === null) {
            return MonorepoInfo::notMonorepo();
        }

        // Lerna 7+ can use workspaces from package.json
        $useWorkspaces = $config['useWorkspaces'] ?? false;
        if ($useWorkspaces) {
            $pkg = $this->readJsonFile($repoPath.'/package.json');
            if ($pkg !== null && isset($pkg['workspaces'])) {
                return $this->parseWorkspaces($pkg['workspaces'], 'lerna', $repoPath);
            }
        }

        $packages = $config['packages'] ?? ['packages/*'];

        return new MonorepoInfo(
            isMonorepo: true,
            type: 'lerna',
            workspacePaths: $packages,
        );
    }

    private function parseNx(string $filePath, string $repoPath): MonorepoInfo
    {
        $config = $this->readJsonFile($filePath);

        // Nx 15+ can define projects directly in nx.json
        if ($config !== null && isset($config['projects'])) {
            $projects = $config['projects'];

            // Projects can be array of paths or object with project configs
            if (is_array($projects) && ! empty($projects)) {
                if (array_is_list($projects)) {
                    // Array format: ["apps/api", "apps/web"]
                    return new MonorepoInfo(
                        isMonorepo: true,
                        type: 'nx',
                        workspacePaths: $projects,
                    );
                } else {
                    // Object format: {"api": "apps/api", "web": {...}}
                    $paths = [];
                    foreach ($projects as $name => $projectConfig) {
                        if (is_string($projectConfig)) {
                            $paths[] = $projectConfig;
                        } elseif (is_array($projectConfig) && isset($projectConfig['root'])) {
                            $paths[] = $projectConfig['root'];
                        }
                    }
                    if (! empty($paths)) {
                        return new MonorepoInfo(
                            isMonorepo: true,
                            type: 'nx',
                            workspacePaths: $paths,
                        );
                    }
                }
            }
        }

        // Check for workspace.json (older Nx versions)
        $workspaceJson = $repoPath.'/workspace.json';
        $workspace = $this->readJsonFile($workspaceJson);
        if ($workspace !== null && isset($workspace['projects'])) {
            $paths = [];
            foreach ($workspace['projects'] as $name => $projectPath) {
                $paths[] = is_string($projectPath) ? $projectPath : ($projectPath['root'] ?? $name);
            }
            if (! empty($paths)) {
                return new MonorepoInfo(
                    isMonorepo: true,
                    type: 'nx',
                    workspacePaths: $paths,
                );
            }
        }

        // Fall back to common Nx directory structure
        $paths = $this->detectCommonDirectories($repoPath, ['apps', 'libs', 'packages']);

        return new MonorepoInfo(
            isMonorepo: ! empty($paths),
            type: 'nx',
            workspacePaths: $paths ?: ['apps/*', 'libs/*'],
        );
    }

    private function parseRush(string $filePath, string $repoPath): MonorepoInfo
    {
        $config = $this->readJsonFile($filePath);

        if ($config === null) {
            return MonorepoInfo::notMonorepo();
        }

        $projects = $config['projects'] ?? [];

        if (empty($projects)) {
            return MonorepoInfo::notMonorepo();
        }

        $paths = [];
        foreach ($projects as $project) {
            if (isset($project['projectFolder'])) {
                $paths[] = $project['projectFolder'];
            }
        }

        if (empty($paths)) {
            return MonorepoInfo::notMonorepo();
        }

        return new MonorepoInfo(
            isMonorepo: true,
            type: 'rush',
            workspacePaths: $paths,
        );
    }

    private function parseWorkspaces(array|string $workspaces, string $type, string $repoPath): MonorepoInfo
    {
        $paths = [];

        if (is_string($workspaces)) {
            // Single workspace path as string
            $paths = [$workspaces];
        } elseif (is_array($workspaces)) {
            if (isset($workspaces['packages'])) {
                // Yarn 2+ format: { packages: [...], nohoist: [...] }
                $paths = $workspaces['packages'];
            } elseif (array_is_list($workspaces)) {
                // Standard format: ["apps/*", "packages/*"]
                $paths = $workspaces;
            }
        }

        if (empty($paths)) {
            return MonorepoInfo::notMonorepo();
        }

        return new MonorepoInfo(
            isMonorepo: true,
            type: $type,
            workspacePaths: $paths,
        );
    }

    /**
     * Detect common monorepo directories that exist
     *
     * @return string[] Glob patterns for existing directories
     */
    private function detectCommonDirectories(string $repoPath, array $candidates): array
    {
        $found = [];
        foreach ($candidates as $dir) {
            if (is_dir($repoPath.'/'.$dir)) {
                $found[] = $dir.'/*';
            }
        }

        return $found;
    }

    /**
     * Detect "simple" monorepos without standard markers.
     *
     * This handles repositories with multiple app directories at root level
     * that don't use standard monorepo tools (turbo, nx, pnpm-workspaces, etc.).
     *
     * Example: a repo with /backend, /frontend, /admin folders each containing
     * their own package.json or requirements.txt.
     */
    private function detectSimpleMonorepo(string $repoPath): MonorepoInfo
    {
        // App marker files that indicate a directory contains an application
        // Note: docker-compose.yml is NOT included because it's often an
        // infrastructure file in root, not an app marker in subdirectories
        $appMarkers = [
            'package.json',
            'requirements.txt',
            'pyproject.toml',
            'go.mod',
            'Cargo.toml',
            'composer.json',
            'Gemfile',
            'mix.exs',
            'pom.xml',
            'build.gradle',
            'Dockerfile',
            'nixpacks.toml',
            'nixpacks.json',
            'Procfile',
        ];

        // Directories to ignore (common non-app directories)
        $ignoreDirs = [
            '.git',
            '.github',
            '.gitlab',
            '.vscode',
            '.idea',
            'node_modules',
            'vendor',
            '__pycache__',
            '.cache',
            'dist',
            'build',
            'out',
            'target',
            'docs',
            'documentation',
            'assets',
            'public',
            'static',
            'scripts',
            'tools',
            'config',
            'configs',
            '.devcontainer',
        ];

        $appDirs = [];
        $entries = scandir($repoPath);

        if ($entries === false) {
            return MonorepoInfo::notMonorepo();
        }

        foreach ($entries as $entry) {
            // Skip hidden dirs (except special cases) and ignored dirs
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (str_starts_with($entry, '.') || in_array($entry, $ignoreDirs, true)) {
                continue;
            }

            $dirPath = $repoPath.'/'.$entry;

            if (! is_dir($dirPath)) {
                continue;
            }

            // Check if this directory contains any app markers
            foreach ($appMarkers as $marker) {
                if (file_exists($dirPath.'/'.$marker)) {
                    $appDirs[] = $entry;
                    break;
                }
            }
        }

        // Need at least 2 app directories to be considered a monorepo
        if (count($appDirs) < 2) {
            return MonorepoInfo::notMonorepo();
        }

        return new MonorepoInfo(
            isMonorepo: true,
            type: 'simple',
            workspacePaths: $appDirs,
        );
    }
}
