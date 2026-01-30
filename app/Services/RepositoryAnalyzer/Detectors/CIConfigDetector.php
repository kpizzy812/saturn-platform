<?php

namespace App\Services\RepositoryAnalyzer\Detectors;

use App\Services\RepositoryAnalyzer\DTOs\CIConfig;
use Symfony\Component\Yaml\Yaml;

/**
 * Detects CI/CD configuration from workflow files
 */
class CIConfigDetector
{
    private const MAX_FILE_SIZE = 256 * 1024; // 256KB

    /**
     * Detect CI configuration from repository
     */
    public function detect(string $repoPath, ?string $appPath = null): ?CIConfig
    {
        $searchPath = $appPath ?? $repoPath;

        // Try different CI systems in order of priority
        $config = $this->detectGitHubActions($repoPath, $searchPath);
        if ($config !== null) {
            return $config;
        }

        $config = $this->detectGitLabCI($repoPath);
        if ($config !== null) {
            return $config;
        }

        $config = $this->detectCircleCI($repoPath);
        if ($config !== null) {
            return $config;
        }

        // Check package.json scripts as fallback
        $config = $this->detectFromPackageJson($searchPath);
        if ($config !== null) {
            return $config;
        }

        return null;
    }

    /**
     * Detect from GitHub Actions workflows
     */
    private function detectGitHubActions(string $repoPath, string $appPath): ?CIConfig
    {
        $workflowsDir = $repoPath.'/.github/workflows';
        if (! is_dir($workflowsDir)) {
            return null;
        }

        $files = glob($workflowsDir.'/*.{yml,yaml}', GLOB_BRACE) ?: [];

        $installCommand = null;
        $buildCommand = null;
        $testCommand = null;
        $startCommand = null;
        $nodeVersion = null;
        $pythonVersion = null;
        $goVersion = null;

        foreach ($files as $file) {
            if (! $this->isReadableFile($file)) {
                continue;
            }

            try {
                $content = file_get_contents($file);
                $yaml = Yaml::parse($content);

                if (! isset($yaml['jobs']) || ! is_array($yaml['jobs'])) {
                    continue;
                }

                foreach ($yaml['jobs'] as $jobName => $job) {
                    // Extract Node version from setup-node
                    if (isset($job['steps']) && is_array($job['steps'])) {
                        foreach ($job['steps'] as $step) {
                            // Node version
                            if (isset($step['uses']) && str_contains($step['uses'], 'setup-node')) {
                                $nodeVersion = $step['with']['node-version'] ?? $nodeVersion;
                            }
                            // Python version
                            if (isset($step['uses']) && str_contains($step['uses'], 'setup-python')) {
                                $pythonVersion = $step['with']['python-version'] ?? $pythonVersion;
                            }
                            // Go version
                            if (isset($step['uses']) && str_contains($step['uses'], 'setup-go')) {
                                $goVersion = $step['with']['go-version'] ?? $goVersion;
                            }

                            // Extract commands from run steps
                            if (isset($step['run'])) {
                                $run = $step['run'];
                                $commands = is_array($run) ? $run : explode("\n", $run);

                                foreach ($commands as $cmd) {
                                    $cmd = trim($cmd);
                                    if (empty($cmd)) {
                                        continue;
                                    }

                                    // Install commands
                                    if ($this->isInstallCommand($cmd)) {
                                        $installCommand = $installCommand ?? $cmd;
                                    }
                                    // Build commands
                                    if ($this->isBuildCommand($cmd)) {
                                        $buildCommand = $buildCommand ?? $cmd;
                                    }
                                    // Test commands
                                    if ($this->isTestCommand($cmd)) {
                                        $testCommand = $testCommand ?? $cmd;
                                    }
                                    // Start commands
                                    if ($this->isStartCommand($cmd)) {
                                        $startCommand = $startCommand ?? $cmd;
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (\Exception) {
                continue;
            }
        }

        if ($installCommand || $buildCommand || $testCommand || $nodeVersion || $pythonVersion) {
            return new CIConfig(
                installCommand: $installCommand,
                buildCommand: $buildCommand,
                testCommand: $testCommand,
                startCommand: $startCommand,
                nodeVersion: $this->normalizeVersion($nodeVersion),
                pythonVersion: $this->normalizeVersion($pythonVersion),
                goVersion: $this->normalizeVersion($goVersion),
                detectedFrom: 'GitHub Actions',
            );
        }

        return null;
    }

    /**
     * Detect from GitLab CI
     */
    private function detectGitLabCI(string $repoPath): ?CIConfig
    {
        $ciFile = $repoPath.'/.gitlab-ci.yml';
        if (! $this->isReadableFile($ciFile)) {
            return null;
        }

        try {
            $content = file_get_contents($ciFile);
            $yaml = Yaml::parse($content);

            $installCommand = null;
            $buildCommand = null;
            $testCommand = null;
            $nodeVersion = null;

            // Check default image for version
            if (isset($yaml['image'])) {
                $nodeVersion = $this->extractVersionFromImage($yaml['image'], 'node');
            }

            // Parse jobs
            foreach ($yaml as $key => $value) {
                if (! is_array($value) || ! isset($value['script'])) {
                    continue;
                }

                $scripts = is_array($value['script']) ? $value['script'] : [$value['script']];

                foreach ($scripts as $cmd) {
                    $cmd = trim($cmd);
                    if ($this->isInstallCommand($cmd)) {
                        $installCommand = $installCommand ?? $cmd;
                    }
                    if ($this->isBuildCommand($cmd)) {
                        $buildCommand = $buildCommand ?? $cmd;
                    }
                    if ($this->isTestCommand($cmd)) {
                        $testCommand = $testCommand ?? $cmd;
                    }
                }
            }

            if ($installCommand || $buildCommand || $testCommand) {
                return new CIConfig(
                    installCommand: $installCommand,
                    buildCommand: $buildCommand,
                    testCommand: $testCommand,
                    nodeVersion: $nodeVersion,
                    detectedFrom: 'GitLab CI',
                );
            }
        } catch (\Exception) {
            return null;
        }

        return null;
    }

    /**
     * Detect from CircleCI
     */
    private function detectCircleCI(string $repoPath): ?CIConfig
    {
        $ciFile = $repoPath.'/.circleci/config.yml';
        if (! $this->isReadableFile($ciFile)) {
            return null;
        }

        try {
            $content = file_get_contents($ciFile);
            $yaml = Yaml::parse($content);

            $installCommand = null;
            $buildCommand = null;
            $testCommand = null;

            // Parse jobs
            if (isset($yaml['jobs']) && is_array($yaml['jobs'])) {
                foreach ($yaml['jobs'] as $job) {
                    if (! isset($job['steps']) || ! is_array($job['steps'])) {
                        continue;
                    }

                    foreach ($job['steps'] as $step) {
                        if (isset($step['run'])) {
                            $run = $step['run'];
                            $cmd = is_array($run) ? ($run['command'] ?? '') : $run;
                            $cmd = trim($cmd);

                            if ($this->isInstallCommand($cmd)) {
                                $installCommand = $installCommand ?? $cmd;
                            }
                            if ($this->isBuildCommand($cmd)) {
                                $buildCommand = $buildCommand ?? $cmd;
                            }
                            if ($this->isTestCommand($cmd)) {
                                $testCommand = $testCommand ?? $cmd;
                            }
                        }
                    }
                }
            }

            if ($installCommand || $buildCommand || $testCommand) {
                return new CIConfig(
                    installCommand: $installCommand,
                    buildCommand: $buildCommand,
                    testCommand: $testCommand,
                    detectedFrom: 'CircleCI',
                );
            }
        } catch (\Exception) {
            return null;
        }

        return null;
    }

    /**
     * Detect from package.json scripts
     */
    private function detectFromPackageJson(string $appPath): ?CIConfig
    {
        $packageJson = $appPath.'/package.json';
        if (! $this->isReadableFile($packageJson)) {
            return null;
        }

        try {
            $content = file_get_contents($packageJson);
            $json = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            $scripts = $json['scripts'] ?? [];
            $engines = $json['engines'] ?? [];

            $installCommand = null;
            $buildCommand = null;
            $testCommand = null;
            $startCommand = null;
            $nodeVersion = $engines['node'] ?? null;

            // Detect install command
            if (file_exists($appPath.'/pnpm-lock.yaml')) {
                $installCommand = 'pnpm install';
            } elseif (file_exists($appPath.'/yarn.lock')) {
                $installCommand = 'yarn install';
            } elseif (file_exists($appPath.'/bun.lockb')) {
                $installCommand = 'bun install';
            } else {
                $installCommand = 'npm ci';
            }

            // Build command
            if (isset($scripts['build'])) {
                $buildCommand = 'npm run build';
                if (file_exists($appPath.'/pnpm-lock.yaml')) {
                    $buildCommand = 'pnpm run build';
                } elseif (file_exists($appPath.'/yarn.lock')) {
                    $buildCommand = 'yarn build';
                }
            }

            // Test command
            if (isset($scripts['test'])) {
                $testCommand = 'npm test';
                if (file_exists($appPath.'/pnpm-lock.yaml')) {
                    $testCommand = 'pnpm test';
                } elseif (file_exists($appPath.'/yarn.lock')) {
                    $testCommand = 'yarn test';
                }
            }

            // Start command
            if (isset($scripts['start'])) {
                $startCommand = 'npm start';
                if (file_exists($appPath.'/pnpm-lock.yaml')) {
                    $startCommand = 'pnpm start';
                } elseif (file_exists($appPath.'/yarn.lock')) {
                    $startCommand = 'yarn start';
                }
            }

            return new CIConfig(
                installCommand: $installCommand,
                buildCommand: $buildCommand,
                testCommand: $testCommand,
                startCommand: $startCommand,
                nodeVersion: $this->normalizeVersion($nodeVersion),
                detectedFrom: 'package.json',
            );
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Check if command is an install command
     */
    private function isInstallCommand(string $cmd): bool
    {
        $patterns = [
            'npm ci',
            'npm install',
            'yarn install',
            'yarn --frozen-lockfile',
            'pnpm install',
            'bun install',
            'pip install',
            'poetry install',
            'composer install',
            'bundle install',
            'cargo build',
            'go mod download',
        ];

        foreach ($patterns as $pattern) {
            if (str_starts_with($cmd, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if command is a build command
     */
    private function isBuildCommand(string $cmd): bool
    {
        $patterns = [
            'npm run build',
            'yarn build',
            'pnpm build',
            'pnpm run build',
            'bun run build',
            'next build',
            'nuxt build',
            'vite build',
            'tsc',
            'go build',
            'cargo build --release',
            'mix compile',
            'mvn package',
            'gradle build',
        ];

        foreach ($patterns as $pattern) {
            if (str_starts_with($cmd, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if command is a test command
     */
    private function isTestCommand(string $cmd): bool
    {
        $patterns = [
            'npm test',
            'npm run test',
            'yarn test',
            'pnpm test',
            'jest',
            'vitest',
            'pytest',
            'python -m pytest',
            'go test',
            'cargo test',
            'phpunit',
            'pest',
            'rspec',
            'bundle exec rspec',
            'mix test',
        ];

        foreach ($patterns as $pattern) {
            if (str_starts_with($cmd, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if command is a start command
     */
    private function isStartCommand(string $cmd): bool
    {
        $patterns = [
            'npm start',
            'npm run start',
            'yarn start',
            'node ',
            'python ',
            'uvicorn',
            'gunicorn',
            './main',
            'go run',
        ];

        foreach ($patterns as $pattern) {
            if (str_starts_with($cmd, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract version from Docker image name
     */
    private function extractVersionFromImage(string $image, string $runtime): ?string
    {
        if (! str_contains(strtolower($image), $runtime)) {
            return null;
        }

        // node:18, node:18-alpine, node:lts
        if (preg_match('/:(\d+(?:\.\d+)?)/', $image, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Normalize version string
     */
    private function normalizeVersion(?string $version): ?string
    {
        if ($version === null) {
            return null;
        }

        // Handle version ranges like ">=18", "^18.0.0", "18.x"
        if (preg_match('/(\d+)(?:\.\d+)*/', (string) $version, $matches)) {
            return $matches[0];
        }

        return null;
    }

    /**
     * Check if file is readable and within size limit
     */
    private function isReadableFile(string $path): bool
    {
        return file_exists($path)
            && is_file($path)
            && is_readable($path)
            && filesize($path) <= self::MAX_FILE_SIZE;
    }
}
