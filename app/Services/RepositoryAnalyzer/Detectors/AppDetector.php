<?php

namespace App\Services\RepositoryAnalyzer\Detectors;

use App\Services\RepositoryAnalyzer\DTOs\DetectedApp;
use App\Services\RepositoryAnalyzer\DTOs\MonorepoInfo;
use JsonException;
use Yosymfony\Toml\Toml;

class AppDetector
{
    /**
     * Framework detection rules (order matters - more specific first)
     *
     * 'matchMode' determines how deps are checked:
     * - 'any' (default): Match if ANY dep is found
     * - 'all': Match only if ALL deps are found
     */
    private const FRAMEWORK_RULES = [
        // Node.js frameworks (order: meta-frameworks first, then low-level)
        'nestjs' => [
            'file' => 'package.json',
            'deps' => ['@nestjs/core'],
            'buildPack' => 'nixpacks',
            'defaultPort' => 3000,
            'type' => 'backend',
        ],
        'nextjs' => [
            'file' => 'package.json',
            'deps' => ['next'],
            'buildPack' => 'nixpacks',
            'defaultPort' => 3000,
            'type' => 'fullstack',
        ],
        'nuxt' => [
            'file' => 'package.json',
            'deps' => ['nuxt'],
            'buildPack' => 'nixpacks',
            'defaultPort' => 3000,
            'type' => 'fullstack',
        ],
        'remix' => [
            'file' => 'package.json',
            'deps' => ['@remix-run/node'],
            'buildPack' => 'nixpacks',
            'defaultPort' => 3000,
            'type' => 'fullstack',
        ],
        'astro' => [
            'file' => 'package.json',
            'deps' => ['astro'],
            'buildPack' => 'nixpacks',
            'defaultPort' => 4321,
            'type' => 'frontend',
        ],
        'sveltekit' => [
            'file' => 'package.json',
            'deps' => ['@sveltejs/kit'],
            'buildPack' => 'nixpacks',
            'defaultPort' => 3000,
            'type' => 'fullstack',
        ],
        'vite-react' => [
            'file' => 'package.json',
            'deps' => ['vite', 'react'],
            'matchMode' => 'all',  // Must have BOTH vite AND react
            'excludeDeps' => ['next', '@remix-run/react'],  // Exclude if meta-framework
            'buildPack' => 'static',
            'defaultPort' => 80,
            'buildCommand' => 'npm run build',
            'publishDirectory' => 'dist',
            'type' => 'frontend',
        ],
        'vite-vue' => [
            'file' => 'package.json',
            'deps' => ['vite', 'vue'],
            'matchMode' => 'all',
            'excludeDeps' => ['nuxt'],
            'buildPack' => 'static',
            'defaultPort' => 80,
            'buildCommand' => 'npm run build',
            'publishDirectory' => 'dist',
            'type' => 'frontend',
        ],
        'vite-svelte' => [
            'file' => 'package.json',
            'deps' => ['vite', 'svelte'],
            'matchMode' => 'all',
            'excludeDeps' => ['@sveltejs/kit'],
            'buildPack' => 'static',
            'defaultPort' => 80,
            'buildCommand' => 'npm run build',
            'publishDirectory' => 'dist',
            'type' => 'frontend',
        ],
        'create-react-app' => [
            'file' => 'package.json',
            'deps' => ['react-scripts'],
            'buildPack' => 'static',
            'defaultPort' => 80,
            'buildCommand' => 'npm run build',
            'publishDirectory' => 'build',
            'type' => 'frontend',
        ],
        'fastify' => [
            'file' => 'package.json',
            'deps' => ['fastify'],
            'buildPack' => 'nixpacks',
            'defaultPort' => 3000,
            'type' => 'backend',
        ],
        'hono' => [
            'file' => 'package.json',
            'deps' => ['hono'],
            'buildPack' => 'nixpacks',
            'defaultPort' => 3000,
            'type' => 'backend',
        ],
        'express' => [
            'file' => 'package.json',
            'deps' => ['express'],
            'excludeDeps' => ['@nestjs/core', 'next'],  // Express often used internally
            'buildPack' => 'nixpacks',
            'defaultPort' => 3000,
            'type' => 'backend',
        ],

        // Python frameworks
        'django' => [
            'file' => 'requirements.txt',
            'deps' => ['Django', 'django'],
            'altFile' => 'pyproject.toml',
            'buildPack' => 'nixpacks',
            'defaultPort' => 8000,
            'type' => 'backend',
        ],
        'fastapi' => [
            'file' => 'requirements.txt',
            'deps' => ['fastapi'],
            'altFile' => 'pyproject.toml',
            'buildPack' => 'nixpacks',
            'defaultPort' => 8000,
            'type' => 'backend',
        ],
        'flask' => [
            'file' => 'requirements.txt',
            'deps' => ['Flask', 'flask'],
            'altFile' => 'pyproject.toml',
            'buildPack' => 'nixpacks',
            'defaultPort' => 5000,
            'type' => 'backend',
        ],

        // Go frameworks
        'go-fiber' => [
            'file' => 'go.mod',
            'deps' => ['github.com/gofiber/fiber/v2', 'github.com/gofiber/fiber'],
            'buildPack' => 'nixpacks',
            'defaultPort' => 3000,
            'type' => 'backend',
        ],
        'go-gin' => [
            'file' => 'go.mod',
            'deps' => ['github.com/gin-gonic/gin'],
            'buildPack' => 'nixpacks',
            'defaultPort' => 8080,
            'type' => 'backend',
        ],
        'go-echo' => [
            'file' => 'go.mod',
            'deps' => ['github.com/labstack/echo/v4', 'github.com/labstack/echo'],
            'buildPack' => 'nixpacks',
            'defaultPort' => 8080,
            'type' => 'backend',
        ],
        'go' => [
            'file' => 'go.mod',
            'deps' => [],
            'buildPack' => 'nixpacks',
            'defaultPort' => 8080,
            'type' => 'backend',
        ],

        // Ruby frameworks
        'rails' => [
            'file' => 'Gemfile',
            'deps' => ['rails'],
            'gemPattern' => "/gem\\s+['\"]rails['\"]/",
            'buildPack' => 'nixpacks',
            'defaultPort' => 3000,
            'type' => 'backend',
        ],
        'sinatra' => [
            'file' => 'Gemfile',
            'deps' => ['sinatra'],
            'gemPattern' => "/gem\\s+['\"]sinatra['\"]/",
            'buildPack' => 'nixpacks',
            'defaultPort' => 4567,
            'type' => 'backend',
        ],

        // Rust frameworks
        'rust-axum' => [
            'file' => 'Cargo.toml',
            'deps' => ['axum'],
            'cargoPattern' => '/^axum\s*=/m',
            'buildPack' => 'nixpacks',
            'defaultPort' => 3000,
            'type' => 'backend',
        ],
        'rust-actix' => [
            'file' => 'Cargo.toml',
            'deps' => ['actix-web'],
            'cargoPattern' => '/^actix-web\s*=/m',
            'buildPack' => 'nixpacks',
            'defaultPort' => 8080,
            'type' => 'backend',
        ],
        'rust' => [
            'file' => 'Cargo.toml',
            'deps' => [],
            'buildPack' => 'nixpacks',
            'defaultPort' => 8080,
            'type' => 'backend',
        ],

        // PHP frameworks
        'laravel' => [
            'file' => 'composer.json',
            'deps' => ['laravel/framework'],
            'buildPack' => 'nixpacks',
            'defaultPort' => 8000,
            'type' => 'backend',
        ],
        'symfony' => [
            'file' => 'composer.json',
            'deps' => ['symfony/framework-bundle'],
            'buildPack' => 'nixpacks',
            'defaultPort' => 8000,
            'type' => 'backend',
        ],

        // Elixir frameworks
        'phoenix' => [
            'file' => 'mix.exs',
            'deps' => [':phoenix'],
            'mixPattern' => '/{:phoenix,/',
            'buildPack' => 'nixpacks',
            'defaultPort' => 4000,
            'type' => 'backend',
        ],

        // Java/Kotlin frameworks
        'spring-boot' => [
            'file' => 'pom.xml',
            'deps' => ['spring-boot-starter'],
            'mavenPattern' => '/<artifactId>spring-boot-starter/',
            'altFile' => 'build.gradle',
            'gradlePattern' => '/org\\.springframework\\.boot/',
            'buildPack' => 'nixpacks',
            'defaultPort' => 8080,
            'type' => 'backend',
        ],
    ];

    /**
     * Maximum file size to read (512KB)
     */
    private const MAX_FILE_SIZE = 512 * 1024;

    /**
     * @return DetectedApp[]
     */
    public function detectFromMonorepo(string $repoPath, MonorepoInfo $monorepo): array
    {
        $apps = [];

        foreach ($monorepo->workspacePaths as $pattern) {
            $directories = $this->expandGlobPattern($repoPath, $pattern);

            foreach ($directories as $dir) {
                $app = $this->detectAppInDirectory($dir, $repoPath);
                if ($app !== null) {
                    $apps[] = $app;
                }
            }
        }

        return $apps;
    }

    /**
     * @return DetectedApp[]
     */
    public function detectSingleApp(string $repoPath): array
    {
        $app = $this->detectAppInDirectory($repoPath, $repoPath);

        return $app !== null ? [$app] : [];
    }

    private function detectAppInDirectory(string $appPath, string $repoPath): ?DetectedApp
    {
        foreach (self::FRAMEWORK_RULES as $framework => $rules) {
            if ($this->checkFramework($appPath, $rules)) {
                $relativePath = $this->getRelativePath($appPath, $repoPath);
                $name = $this->inferAppName($appPath, $relativePath);

                return new DetectedApp(
                    name: $name,
                    path: $relativePath,
                    framework: $framework,
                    buildPack: $rules['buildPack'],
                    defaultPort: $rules['defaultPort'],
                    buildCommand: $rules['buildCommand'] ?? null,
                    publishDirectory: $rules['publishDirectory'] ?? null,
                    type: $rules['type'] ?? 'backend',
                );
            }
        }

        // Check for Dockerfile as fallback
        if (file_exists($appPath.'/Dockerfile')) {
            $relativePath = $this->getRelativePath($appPath, $repoPath);

            return new DetectedApp(
                name: $this->inferAppName($appPath, $relativePath),
                path: $relativePath,
                framework: 'dockerfile',
                buildPack: 'dockerfile',
                defaultPort: $this->extractPortFromDockerfile($appPath.'/Dockerfile'),
                type: 'unknown',
            );
        }

        // Check for docker-compose.yml
        if (file_exists($appPath.'/docker-compose.yml') || file_exists($appPath.'/docker-compose.yaml')) {
            $relativePath = $this->getRelativePath($appPath, $repoPath);

            return new DetectedApp(
                name: $this->inferAppName($appPath, $relativePath),
                path: $relativePath,
                framework: 'docker-compose',
                buildPack: 'docker-compose',
                defaultPort: 80,
                type: 'unknown',
            );
        }

        return null;
    }

    private function checkFramework(string $appPath, array $rules): bool
    {
        $filePath = $appPath.'/'.$rules['file'];
        $altFilePath = isset($rules['altFile']) ? $appPath.'/'.$rules['altFile'] : null;

        // Determine which file to use
        $useAltFile = false;
        if (! file_exists($filePath)) {
            if ($altFilePath !== null && file_exists($altFilePath)) {
                $filePath = $altFilePath;
                $useAltFile = true;
            } else {
                return false;
            }
        }

        // If no deps to check, file existence is enough
        if (empty($rules['deps'])) {
            return true;
        }

        // Parse dependencies based on file type
        $dependencies = $this->extractDependencies($filePath, $rules['file'], $useAltFile ? $rules['altFile'] : null);

        if ($dependencies === null) {
            return false;
        }

        // Check exclude deps first
        if (isset($rules['excludeDeps'])) {
            foreach ($rules['excludeDeps'] as $excludeDep) {
                if (in_array($excludeDep, $dependencies, true)) {
                    return false;
                }
            }
        }

        // Check required deps
        $matchMode = $rules['matchMode'] ?? 'any';
        $requiredDeps = $rules['deps'];

        if ($matchMode === 'all') {
            // ALL deps must be present
            foreach ($requiredDeps as $dep) {
                if (! in_array($dep, $dependencies, true)) {
                    return false;
                }
            }

            return true;
        }

        // ANY dep must be present (default)
        foreach ($requiredDeps as $dep) {
            if (in_array($dep, $dependencies, true)) {
                return true;
            }
        }

        // For files with specific patterns (Gemfile, Cargo.toml, etc.)
        if (isset($rules['gemPattern']) || isset($rules['cargoPattern']) ||
            isset($rules['mixPattern']) || isset($rules['mavenPattern']) ||
            isset($rules['gradlePattern'])) {
            return $this->checkPatternMatch($filePath, $rules, $useAltFile);
        }

        return false;
    }

    /**
     * Extract dependency names from various package manager files
     *
     * @return string[]|null Dependency names or null if parsing failed
     */
    private function extractDependencies(string $filePath, string $fileType, ?string $altFileType = null): ?array
    {
        if (filesize($filePath) > self::MAX_FILE_SIZE) {
            return null;
        }

        $actualType = $altFileType ?? $fileType;

        return match ($actualType) {
            'package.json' => $this->extractNodeDeps($filePath),
            'requirements.txt' => $this->extractPythonRequirementsDeps($filePath),
            'pyproject.toml' => $this->extractPyprojectDeps($filePath),
            'go.mod' => $this->extractGoDeps($filePath),
            'Gemfile' => $this->extractRubyDeps($filePath),
            'Cargo.toml' => $this->extractRustDeps($filePath),
            'composer.json' => $this->extractPhpDeps($filePath),
            'mix.exs' => $this->extractElixirDeps($filePath),
            'pom.xml' => $this->extractMavenDeps($filePath),
            'build.gradle' => $this->extractGradleDeps($filePath),
            default => null,
        };
    }

    private function extractNodeDeps(string $filePath): ?array
    {
        try {
            $content = file_get_contents($filePath);
            $json = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            $deps = array_merge(
                array_keys($json['dependencies'] ?? []),
                array_keys($json['devDependencies'] ?? []),
                array_keys($json['peerDependencies'] ?? []),
            );

            return $deps;
        } catch (JsonException) {
            return null;
        }
    }

    private function extractPythonRequirementsDeps(string $filePath): ?array
    {
        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);
        $deps = [];

        foreach ($lines as $line) {
            $line = trim($line);
            // Skip comments and empty lines
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            // Extract package name (before ==, >=, <=, ~=, etc.)
            if (preg_match('/^([a-zA-Z0-9_-]+)/', $line, $matches)) {
                $deps[] = $matches[1];
            }
        }

        return $deps;
    }

    private function extractPyprojectDeps(string $filePath): ?array
    {
        try {
            $toml = Toml::parseFile($filePath);

            $deps = [];

            // PEP 621 format (project.dependencies)
            if (isset($toml['project']['dependencies'])) {
                foreach ($toml['project']['dependencies'] as $dep) {
                    if (preg_match('/^([a-zA-Z0-9_-]+)/', $dep, $matches)) {
                        $deps[] = strtolower($matches[1]);
                    }
                }
            }

            // Poetry format (tool.poetry.dependencies)
            if (isset($toml['tool']['poetry']['dependencies'])) {
                $deps = array_merge($deps, array_keys($toml['tool']['poetry']['dependencies']));
            }

            return $deps;
        } catch (\Exception) {
            return null;
        }
    }

    private function extractGoDeps(string $filePath): ?array
    {
        $content = file_get_contents($filePath);
        $deps = [];

        // Match require blocks and single requires
        if (preg_match_all('/require\s+([^\s]+)\s+v/', $content, $matches)) {
            $deps = array_merge($deps, $matches[1]);
        }

        // Match require block format
        if (preg_match('/require\s*\((.*?)\)/s', $content, $block)) {
            if (preg_match_all('/^\s*([^\s]+)\s+v/m', $block[1], $matches)) {
                $deps = array_merge($deps, $matches[1]);
            }
        }

        return array_unique($deps);
    }

    private function extractRubyDeps(string $filePath): ?array
    {
        $content = file_get_contents($filePath);
        $deps = [];

        // Match gem 'name' or gem "name"
        if (preg_match_all("/gem\\s+['\"]([^'\"]+)['\"]/", $content, $matches)) {
            $deps = $matches[1];
        }

        return $deps;
    }

    private function extractRustDeps(string $filePath): ?array
    {
        try {
            $toml = Toml::parseFile($filePath);

            $deps = [];
            if (isset($toml['dependencies'])) {
                $deps = array_merge($deps, array_keys($toml['dependencies']));
            }
            if (isset($toml['dev-dependencies'])) {
                $deps = array_merge($deps, array_keys($toml['dev-dependencies']));
            }

            return $deps;
        } catch (\Exception) {
            return null;
        }
    }

    private function extractPhpDeps(string $filePath): ?array
    {
        try {
            $content = file_get_contents($filePath);
            $json = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            return array_merge(
                array_keys($json['require'] ?? []),
                array_keys($json['require-dev'] ?? []),
            );
        } catch (JsonException) {
            return null;
        }
    }

    private function extractElixirDeps(string $filePath): ?array
    {
        $content = file_get_contents($filePath);
        $deps = [];

        // Match {:dep_name, "~> x.x"}
        if (preg_match_all('/{:([a-z_]+),/', $content, $matches)) {
            $deps = array_map(fn ($d) => ":$d", $matches[1]);
        }

        return $deps;
    }

    private function extractMavenDeps(string $filePath): ?array
    {
        $content = file_get_contents($filePath);
        $deps = [];

        // Extract artifactIds
        if (preg_match_all('/<artifactId>([^<]+)<\/artifactId>/', $content, $matches)) {
            $deps = $matches[1];
        }

        return $deps;
    }

    private function extractGradleDeps(string $filePath): ?array
    {
        $content = file_get_contents($filePath);
        $deps = [];

        // Match implementation/compile 'group:artifact:version'
        if (preg_match_all("/(?:implementation|compile|api)\\s*['\"]([^:]+):([^:]+)/", $content, $matches)) {
            foreach ($matches[1] as $i => $group) {
                $deps[] = $group.':'.$matches[2][$i];
                $deps[] = $group;  // Also add just the group
            }
        }

        // Match plugin blocks
        if (preg_match_all("/id\\s*['\"]([^'\"]+)['\"]/", $content, $matches)) {
            $deps = array_merge($deps, $matches[1]);
        }

        return array_unique($deps);
    }

    private function checkPatternMatch(string $filePath, array $rules, bool $isAltFile): bool
    {
        $content = file_get_contents($filePath);

        $patternKey = match (true) {
            $isAltFile && isset($rules['gradlePattern']) => 'gradlePattern',
            isset($rules['gemPattern']) => 'gemPattern',
            isset($rules['cargoPattern']) => 'cargoPattern',
            isset($rules['mixPattern']) => 'mixPattern',
            isset($rules['mavenPattern']) => 'mavenPattern',
            isset($rules['gradlePattern']) => 'gradlePattern',
            default => null,
        };

        if ($patternKey === null) {
            return false;
        }

        return preg_match($rules[$patternKey], $content) === 1;
    }

    private function expandGlobPattern(string $basePath, string $pattern): array
    {
        // Handle patterns like "apps/*" or "packages/*"
        $pattern = rtrim($pattern, '/');
        $fullPath = $basePath.'/'.$pattern;

        if (str_contains($pattern, '*')) {
            return glob($fullPath, GLOB_ONLYDIR) ?: [];
        }

        // Single directory path (e.g., "apps/api")
        return is_dir($fullPath) ? [$fullPath] : [];
    }

    private function getRelativePath(string $appPath, string $repoPath): string
    {
        if ($appPath === $repoPath) {
            return '.';
        }

        return ltrim(str_replace($repoPath, '', $appPath), '/');
    }

    private function inferAppName(string $appPath, string $relativePath): string
    {
        if ($relativePath === '.') {
            return basename($appPath);
        }

        // Use last directory name (e.g., "apps/api" -> "api")
        return basename($appPath);
    }

    private function extractPortFromDockerfile(string $dockerfilePath): int
    {
        $content = file_get_contents($dockerfilePath);

        // Handle multiple EXPOSE formats:
        // EXPOSE 3000
        // EXPOSE 3000/tcp
        // EXPOSE 3000 8080
        // ARG PORT=3000 + EXPOSE ${PORT} (simplified - take first numeric)
        if (preg_match('/EXPOSE\s+(\d+)/', $content, $matches)) {
            $port = (int) $matches[1];
            // Sanity check port range
            if ($port > 0 && $port <= 65535) {
                return $port;
            }
        }

        return 3000; // Default fallback
    }
}
