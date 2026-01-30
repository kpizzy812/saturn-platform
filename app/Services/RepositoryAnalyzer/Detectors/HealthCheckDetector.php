<?php

namespace App\Services\RepositoryAnalyzer\Detectors;

use App\Services\RepositoryAnalyzer\DTOs\DetectedHealthCheck;

/**
 * Detects health check endpoints from source code and configuration
 */
class HealthCheckDetector
{
    private const MAX_FILE_SIZE = 512 * 1024; // 512KB

    /**
     * Common health check paths to look for
     */
    private const HEALTH_PATHS = [
        '/health',
        '/healthz',
        '/health-check',
        '/healthcheck',
        '/api/health',
        '/api/v1/health',
        '/api/healthz',
        '/_health',
        '/ready',
        '/readiness',
        '/live',
        '/liveness',
        '/ping',
        '/status',
    ];

    /**
     * Route patterns by framework
     */
    private const ROUTE_PATTERNS = [
        // Express/Node.js
        'node' => [
            '/(?:app|router)\.(?:get|all)\s*\(\s*[\'"]([\/\w-]+health[\/\w-]*)[\'"]/',
            '/(?:app|router)\.(?:get|all)\s*\(\s*[\'"]([\/\w-]+ready[\/\w-]*)[\'"]/',
            '/(?:app|router)\.(?:get|all)\s*\(\s*[\'"]([\/\w-]+live[\/\w-]*)[\'"]/',
            '/(?:app|router)\.(?:get|all)\s*\(\s*[\'"]([\/\w-]+ping)[\'"]/',
        ],
        // FastAPI/Python
        'python' => [
            '/@(?:app|router)\.(?:get|api_route)\s*\(\s*[\'"]([\/\w-]+health[\/\w-]*)[\'"]/',
            '/@(?:app|router)\.(?:get|api_route)\s*\(\s*[\'"]([\/\w-]+ready[\/\w-]*)[\'"]/',
            '/@(?:app|router)\.(?:get|api_route)\s*\(\s*[\'"]([\/\w-]+live[\/\w-]*)[\'"]/',
            '/path\s*=\s*[\'"]([\/\w-]+health[\/\w-]*)[\'"]/i',
        ],
        // Go
        'go' => [
            '/\.(?:GET|Handle|HandleFunc)\s*\(\s*[\'"]([\/\w-]+health[\/\w-]*)[\'"]/',
            '/\.(?:GET|Handle|HandleFunc)\s*\(\s*[\'"]([\/\w-]+ready[\/\w-]*)[\'"]/',
            '/\.(?:GET|Handle|HandleFunc)\s*\(\s*[\'"]([\/\w-]+live[\/\w-]*)[\'"]/',
        ],
        // Laravel/PHP
        'php' => [
            '/Route::get\s*\(\s*[\'"]([\/\w-]+health[\/\w-]*)[\'"]/',
            '/->get\s*\(\s*[\'"]([\/\w-]+health[\/\w-]*)[\'"]/',
        ],
        // Ruby/Rails
        'ruby' => [
            '/get\s+[\'"]([\/\w-]+health[\/\w-]*)[\'"]\s*(?:=>|,)/',
            '/match\s+[\'"]([\/\w-]+health[\/\w-]*)[\'"]\s*(?:=>|,)/',
        ],
    ];

    /**
     * Files to scan for health routes
     */
    private const ROUTE_FILES = [
        'node' => [
            'src/routes/*.js', 'src/routes/*.ts', 'routes/*.js', 'routes/*.ts',
            'src/app.js', 'src/app.ts', 'app.js', 'app.ts',
            'src/index.js', 'src/index.ts', 'index.js', 'index.ts',
            'src/server.js', 'src/server.ts', 'server.js', 'server.ts',
            'src/main.ts', 'main.ts',
        ],
        'python' => [
            'app/main.py', 'main.py', 'app.py',
            'app/routes/*.py', 'routes/*.py',
            'app/api/*.py', 'api/*.py',
            'src/main.py', 'src/app.py',
        ],
        'go' => [
            'main.go', 'cmd/main.go', 'cmd/*/main.go',
            'internal/routes/*.go', 'routes/*.go',
            'internal/handler/*.go', 'handler/*.go',
        ],
        'php' => [
            'routes/web.php', 'routes/api.php',
            'app/Http/routes.php',
        ],
        'ruby' => [
            'config/routes.rb',
            'app/controllers/*_controller.rb',
        ],
    ];

    /**
     * Detect health check endpoint
     */
    public function detect(string $appPath, ?string $framework = null): ?DetectedHealthCheck
    {
        // 1. Check Dockerfile HEALTHCHECK
        $dockerHealth = $this->detectFromDockerfile($appPath);
        if ($dockerHealth !== null) {
            return $dockerHealth;
        }

        // 2. Check docker-compose.yml healthcheck
        $composeHealth = $this->detectFromDockerCompose($appPath);
        if ($composeHealth !== null) {
            return $composeHealth;
        }

        // 3. Scan source code for health routes
        $language = $this->getLanguageForFramework($framework);
        $codeHealth = $this->detectFromSourceCode($appPath, $language);
        if ($codeHealth !== null) {
            return $codeHealth;
        }

        // 4. Check for common health endpoint files
        $fileHealth = $this->detectFromHealthFiles($appPath);
        if ($fileHealth !== null) {
            return $fileHealth;
        }

        return null;
    }

    /**
     * Detect health check from Dockerfile HEALTHCHECK instruction
     */
    private function detectFromDockerfile(string $appPath): ?DetectedHealthCheck
    {
        $dockerfile = $appPath.'/Dockerfile';
        if (! $this->isReadableFile($dockerfile)) {
            return null;
        }

        $content = file_get_contents($dockerfile);

        // Match HEALTHCHECK instruction
        // HEALTHCHECK --interval=30s --timeout=5s CMD curl -f http://localhost:3000/health || exit 1
        if (preg_match('/HEALTHCHECK\s+(?:--\w+[=\s]+\S+\s+)*CMD\s+(.+?)(?:\|\||$)/im', $content, $matches)) {
            $cmd = trim($matches[1]);

            // Extract path from curl command
            if (preg_match('/curl\s+(?:-[fsSL]+\s+)?(?:http[s]?:\/\/)?(?:localhost|127\.0\.0\.1)(?::\d+)?([\/\w-]+)/', $cmd, $pathMatch)) {
                $path = $pathMatch[1];

                // Extract interval and timeout
                $interval = 30;
                $timeout = 5;

                if (preg_match('/--interval[=\s]+(\d+)s?/', $content, $intervalMatch)) {
                    $interval = (int) $intervalMatch[1];
                }
                if (preg_match('/--timeout[=\s]+(\d+)s?/', $content, $timeoutMatch)) {
                    $timeout = (int) $timeoutMatch[1];
                }

                return new DetectedHealthCheck(
                    path: $path,
                    method: 'GET',
                    intervalSeconds: $interval,
                    timeoutSeconds: $timeout,
                    detectedVia: 'Dockerfile HEALTHCHECK',
                );
            }
        }

        return null;
    }

    /**
     * Detect health check from docker-compose.yml
     */
    private function detectFromDockerCompose(string $appPath): ?DetectedHealthCheck
    {
        $composeFiles = ['docker-compose.yml', 'docker-compose.yaml', 'compose.yml', 'compose.yaml'];

        foreach ($composeFiles as $file) {
            $filePath = $appPath.'/'.$file;
            if (! $this->isReadableFile($filePath)) {
                continue;
            }

            $content = file_get_contents($filePath);

            // Look for healthcheck in any service
            if (preg_match('/healthcheck:\s*\n\s+test:\s*\[?"?CMD"?,?\s*"?curl"?,?\s*"?-f"?,?\s*"?([^"\]]+)"?\]?/i', $content, $matches)) {
                $url = trim($matches[1]);

                // Extract path from URL
                if (preg_match('/(?:http[s]?:\/\/)?(?:localhost|127\.0\.0\.1)(?::\d+)?([\/\w-]+)/', $url, $pathMatch)) {
                    return new DetectedHealthCheck(
                        path: $pathMatch[1],
                        method: 'GET',
                        detectedVia: 'docker-compose healthcheck',
                    );
                }
            }
        }

        return null;
    }

    /**
     * Detect health check from source code
     */
    private function detectFromSourceCode(string $appPath, ?string $language): ?DetectedHealthCheck
    {
        $languages = $language ? [$language] : array_keys(self::ROUTE_PATTERNS);

        foreach ($languages as $lang) {
            if (! isset(self::ROUTE_PATTERNS[$lang]) || ! isset(self::ROUTE_FILES[$lang])) {
                continue;
            }

            foreach (self::ROUTE_FILES[$lang] as $filePattern) {
                $files = $this->expandGlob($appPath.'/'.$filePattern);

                foreach ($files as $file) {
                    if (! $this->isReadableFile($file)) {
                        continue;
                    }

                    $content = file_get_contents($file);

                    foreach (self::ROUTE_PATTERNS[$lang] as $pattern) {
                        if (preg_match($pattern, $content, $matches)) {
                            $path = $matches[1];
                            if (! str_starts_with($path, '/')) {
                                $path = '/'.$path;
                            }

                            return new DetectedHealthCheck(
                                path: $path,
                                method: 'GET',
                                detectedVia: 'source code: '.basename($file),
                            );
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Detect health check from common health endpoint files
     */
    private function detectFromHealthFiles(string $appPath): ?DetectedHealthCheck
    {
        // Check for dedicated health files
        $healthFiles = [
            'src/health.js' => '/health',
            'src/health.ts' => '/health',
            'app/health.py' => '/health',
            'internal/health/health.go' => '/health',
            'src/routes/health.js' => '/health',
            'src/routes/health.ts' => '/health',
            'api/health.ts' => '/api/health',
            'pages/api/health.ts' => '/api/health',
            'pages/api/health.js' => '/api/health',
            'app/api/health/route.ts' => '/api/health',
        ];

        foreach ($healthFiles as $file => $path) {
            if (file_exists($appPath.'/'.$file)) {
                return new DetectedHealthCheck(
                    path: $path,
                    method: 'GET',
                    detectedVia: 'health file: '.$file,
                );
            }
        }

        return null;
    }

    /**
     * Get language for a framework
     */
    private function getLanguageForFramework(?string $framework): ?string
    {
        if ($framework === null) {
            return null;
        }

        $frameworkLanguages = [
            'nestjs' => 'node',
            'nextjs' => 'node',
            'nuxt' => 'node',
            'express' => 'node',
            'fastify' => 'node',
            'hono' => 'node',
            'django' => 'python',
            'fastapi' => 'python',
            'flask' => 'python',
            'go-fiber' => 'go',
            'go-gin' => 'go',
            'go-echo' => 'go',
            'go' => 'go',
            'laravel' => 'php',
            'symfony' => 'php',
            'rails' => 'ruby',
            'sinatra' => 'ruby',
        ];

        return $frameworkLanguages[$framework] ?? null;
    }

    /**
     * Expand glob pattern to file paths
     */
    private function expandGlob(string $pattern): array
    {
        if (str_contains($pattern, '*')) {
            return glob($pattern) ?: [];
        }

        return file_exists($pattern) ? [$pattern] : [];
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
