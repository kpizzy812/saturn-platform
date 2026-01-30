<?php

namespace App\Services\RepositoryAnalyzer\Detectors;

use App\Services\RepositoryAnalyzer\DTOs\AppDependency;
use App\Services\RepositoryAnalyzer\DTOs\DetectedApp;

/**
 * Detects dependencies between apps in a monorepo
 */
class AppDependencyDetector
{
    private const MAX_FILE_SIZE = 256 * 1024; // 256KB

    /**
     * Analyze dependencies between apps
     *
     * @param  DetectedApp[]  $apps
     * @return AppDependency[]
     */
    public function analyze(string $repoPath, array $apps): array
    {
        $appNames = array_map(fn ($app) => $app->name, $apps);
        $dependencies = [];

        foreach ($apps as $app) {
            $appPath = $app->path === '.' ? $repoPath : $repoPath.'/'.$app->path;
            $deps = $this->detectDependencies($appPath, $app, $appNames, $apps);
            $dependencies[] = $deps;
        }

        // Calculate deploy order based on dependencies
        $dependencies = $this->calculateDeployOrder($dependencies);

        return $dependencies;
    }

    /**
     * Detect dependencies for a single app
     *
     * @param  DetectedApp[]  $allApps
     */
    private function detectDependencies(string $appPath, DetectedApp $app, array $allAppNames, array $allApps): AppDependency
    {
        $dependsOn = [];
        $internalUrls = [];

        // Check package.json for workspace dependencies
        $packageDeps = $this->detectFromPackageJson($appPath, $allAppNames);
        $dependsOn = array_merge($dependsOn, $packageDeps['dependsOn']);
        $internalUrls = array_merge($internalUrls, $packageDeps['internalUrls']);

        // Check imports in source code
        $codeDeps = $this->detectFromSourceCode($appPath, $allAppNames);
        $dependsOn = array_merge($dependsOn, $codeDeps);

        // Check environment variables for internal URLs (explicit references)
        $envDeps = $this->detectFromEnvFiles($appPath, $allAppNames);
        $internalUrls = array_merge($internalUrls, $envDeps);

        // Check if app needs API connection (based on env var names, even if empty)
        $needsApi = $this->detectApiNeed($appPath);

        // Infer internal URLs based on app types (smart fallback)
        // Only if no explicit URLs found and app is frontend or needs API
        if (empty($internalUrls) && ($app->type === 'frontend' || $needsApi)) {
            $inferredUrls = $this->inferInternalUrlsByType($allApps, $app->name);
            $internalUrls = array_merge($internalUrls, $inferredUrls);
        }

        return new AppDependency(
            appName: $app->name,
            dependsOn: array_unique($dependsOn),
            internalUrls: $internalUrls,
        );
    }

    /**
     * Detect dependencies from package.json
     */
    private function detectFromPackageJson(string $appPath, array $allAppNames): array
    {
        $packageJson = $appPath.'/package.json';
        if (! $this->isReadableFile($packageJson)) {
            return ['dependsOn' => [], 'internalUrls' => []];
        }

        try {
            $content = file_get_contents($packageJson);
            $json = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            $dependsOn = [];
            $internalUrls = [];

            // Check dependencies for workspace references
            $allDeps = array_merge(
                $json['dependencies'] ?? [],
                $json['devDependencies'] ?? [],
            );

            foreach ($allDeps as $dep => $version) {
                // Check for workspace: protocol
                if (str_starts_with($version, 'workspace:')) {
                    // Extract app name from package name
                    // e.g., @monorepo/api-client -> api
                    $possibleAppName = $this->extractAppNameFromPackage($dep);
                    if ($possibleAppName && in_array($possibleAppName, $allAppNames, true)) {
                        $dependsOn[] = $possibleAppName;
                    }
                }

                // Check if package name matches an app
                foreach ($allAppNames as $appName) {
                    if (str_contains($dep, $appName) || str_contains($dep, str_replace('-', '/', $appName))) {
                        if (! in_array($appName, $dependsOn, true)) {
                            $dependsOn[] = $appName;
                        }
                    }
                }
            }

            return ['dependsOn' => $dependsOn, 'internalUrls' => $internalUrls];
        } catch (\Exception) {
            return ['dependsOn' => [], 'internalUrls' => []];
        }
    }

    /**
     * Detect dependencies from source code imports
     */
    private function detectFromSourceCode(string $appPath, array $allAppNames): array
    {
        $dependsOn = [];

        // Check common import patterns
        $sourceFiles = $this->findSourceFiles($appPath);

        foreach ($sourceFiles as $file) {
            if (! $this->isReadableFile($file)) {
                continue;
            }

            $content = file_get_contents($file);

            foreach ($allAppNames as $appName) {
                // Check for imports like:
                // import { x } from '@monorepo/api'
                // import { x } from '../api'
                // require('@workspace/api')
                $patterns = [
                    '/@\w+\/'.$appName.'[\'"]/',
                    '/from\s+[\'"]\.\.\/\w*'.$appName.'/',
                    '/require\s*\(\s*[\'"]@\w+\/'.$appName.'[\'"]/',
                ];

                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $content)) {
                        if (! in_array($appName, $dependsOn, true)) {
                            $dependsOn[] = $appName;
                        }
                        break;
                    }
                }
            }
        }

        return $dependsOn;
    }

    /**
     * Detect internal URL references from env files
     */
    private function detectFromEnvFiles(string $appPath, array $allAppNames): array
    {
        $internalUrls = [];
        $envFiles = ['.env.example', '.env.sample', '.env.template'];

        foreach ($envFiles as $envFile) {
            $filePath = $appPath.'/'.$envFile;
            if (! $this->isReadableFile($filePath)) {
                continue;
            }

            $content = file_get_contents($filePath);
            $lines = explode("\n", $content);

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || str_starts_with($line, '#')) {
                    continue;
                }

                // Look for URL variables that might reference other apps
                // e.g., API_URL=http://api:3000
                if (preg_match('/^(\w+_URL)\s*=\s*(.*)$/', $line, $matches)) {
                    $varName = $matches[1];
                    $value = $matches[2];

                    foreach ($allAppNames as $appName) {
                        if (str_contains(strtolower($value), $appName)) {
                            $internalUrls[$varName] = $appName;
                            break;
                        }
                    }
                }
            }
        }

        return $internalUrls;
    }

    /**
     * Detect if app needs API connection based on env variable names
     *
     * Looks for API-related env variables even if they have no value
     */
    private function detectApiNeed(string $appPath): bool
    {
        $envFiles = ['.env.example', '.env.sample', '.env.template'];
        $apiPatterns = [
            'API_URL',
            'BACKEND_URL',
            'NEXT_PUBLIC_API',
            'VITE_API',
            'REACT_APP_API',
            'NUXT_PUBLIC_API',
        ];

        foreach ($envFiles as $envFile) {
            $filePath = $appPath.'/'.$envFile;
            if (! $this->isReadableFile($filePath)) {
                continue;
            }

            $content = file_get_contents($filePath);
            foreach ($apiPatterns as $pattern) {
                if (str_contains($content, $pattern)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Infer internal URLs based on app types (smart fallback)
     *
     * Instead of matching hardcoded names, this looks at actual app types.
     * If there's exactly one backend in the monorepo, we can reasonably
     * assume it should be connected.
     *
     * @param  DetectedApp[]  $allApps
     */
    private function inferInternalUrlsByType(array $allApps, string $excludeAppName): array
    {
        $internalUrls = [];

        // Find all backend apps in the monorepo (excluding current app)
        $backends = array_filter($allApps, fn ($a) => $a->type === 'backend' && $a->name !== $excludeAppName);

        // If exactly one backend exists, it's safe to assume the app needs it
        // (caller already verified that app is frontend or needs API)
        if (count($backends) === 1) {
            $backend = reset($backends);
            $internalUrls['API_URL'] = $backend->name;
        }

        return $internalUrls;
    }

    /**
     * Calculate deploy order based on dependencies
     *
     * @param  AppDependency[]  $dependencies
     * @return AppDependency[]
     */
    private function calculateDeployOrder(array $dependencies): array
    {
        // Build dependency graph
        $graph = [];
        foreach ($dependencies as $dep) {
            $graph[$dep->appName] = $dep->dependsOn;
        }

        // Topological sort to determine order
        $order = $this->topologicalSort($graph);

        // Assign deploy order
        $result = [];
        foreach ($dependencies as $dep) {
            $deployOrder = array_search($dep->appName, $order, true);
            $result[] = new AppDependency(
                appName: $dep->appName,
                dependsOn: $dep->dependsOn,
                internalUrls: $dep->internalUrls,
                deployOrder: $deployOrder !== false ? $deployOrder : 0,
            );
        }

        // Sort by deploy order
        usort($result, fn ($a, $b) => $a->deployOrder <=> $b->deployOrder);

        return $result;
    }

    /**
     * Topological sort for dependency ordering
     */
    private function topologicalSort(array $graph): array
    {
        $visited = [];
        $result = [];

        $visit = function (string $node) use (&$visit, &$visited, &$result, $graph) {
            if (isset($visited[$node])) {
                return;
            }
            $visited[$node] = true;

            foreach ($graph[$node] ?? [] as $dep) {
                if (isset($graph[$dep])) {
                    $visit($dep);
                }
            }

            $result[] = $node;
        };

        foreach (array_keys($graph) as $node) {
            $visit($node);
        }

        return $result;
    }

    /**
     * Extract app name from package name
     */
    private function extractAppNameFromPackage(string $packageName): ?string
    {
        // @monorepo/api-client -> api-client -> api
        // @workspace/shared -> shared
        if (preg_match('/@[\w-]+\/(.+)/', $packageName, $matches)) {
            $name = $matches[1];
            // Remove common suffixes
            $name = preg_replace('/-(client|sdk|types|shared)$/', '', $name);

            return $name;
        }

        return null;
    }

    /**
     * Find source files in app directory
     */
    private function findSourceFiles(string $appPath): array
    {
        // Define patterns with explicit extensions (no GLOB_BRACE - not available in Alpine/musl)
        $baseDirs = [
            $appPath.'/src/*/',
            $appPath.'/app/*/',
            $appPath.'/lib/*/',
            $appPath.'/',
        ];

        $extensions = ['ts', 'js', 'tsx', 'jsx'];

        $files = [];
        foreach ($baseDirs as $baseDir) {
            foreach ($extensions as $ext) {
                $pattern = rtrim($baseDir, '/').'/*.'.$ext;
                $found = glob($pattern) ?: [];
                $files = array_merge($files, $found);
            }
        }

        // Limit to first 20 files to avoid scanning too much
        return array_slice(array_unique($files), 0, 20);
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
