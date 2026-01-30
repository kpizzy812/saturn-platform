<?php

namespace App\Services\RepositoryAnalyzer\Detectors;

/**
 * Detects application ports from source code
 */
class PortDetector
{
    private const MAX_FILE_SIZE = 256 * 1024; // 256KB

    /**
     * Common port patterns by language/framework
     */
    private const PORT_PATTERNS = [
        // Node.js / JavaScript
        'node' => [
            '/\.listen\s*\(\s*(\d+)/',                                    // app.listen(3000)
            '/\.listen\s*\(\s*process\.env\.PORT\s*\|\|\s*(\d+)/',       // app.listen(process.env.PORT || 3000)
            '/\.listen\s*\(\s*parseInt\s*\([^)]*\)\s*\|\|\s*(\d+)/',     // .listen(parseInt(...) || 3000)
            '/port\s*[:=]\s*(\d+)/',                                      // port: 3000 or port = 3000
            '/PORT\s*[:=]\s*(\d+)/',                                      // PORT: 3000
        ],
        // Python
        'python' => [
            '/uvicorn\.run\s*\([^)]*port\s*=\s*(\d+)/',                  // uvicorn.run(app, port=8000)
            '/\.run\s*\([^)]*port\s*=\s*(\d+)/',                         // app.run(port=5000)
            '/--port\s+(\d+)/',                                           // --port 8000
            '/PORT\s*=\s*(\d+)/',                                         // PORT = 8000
            '/:(\d+)["\']\s*$/',                                          // "0.0.0.0:8000"
        ],
        // Go
        'go' => [
            '/ListenAndServe\s*\(\s*["\']:(\d+)/',                       // ListenAndServe(":8080"
            '/ListenAndServe\s*\(\s*["\']\S+:(\d+)/',                    // ListenAndServe("0.0.0.0:8080"
            '/\.Start\s*\(\s*["\']:(\d+)/',                              // e.Start(":3000")
            '/Addr\s*[:=]\s*["\']:(\d+)/',                               // Addr: ":8080"
        ],
        // Rust
        'rust' => [
            '/bind\s*\(\s*["\']\S+:(\d+)/',                              // bind("0.0.0.0:8080")
            '/\.port\s*\(\s*(\d+)\s*\)/',                                // .port(8080)
        ],
        // PHP
        'php' => [
            '/--port\s*[=\s]+(\d+)/',                                    // --port=8000
            '/SERVER_PORT.*(\d{4,5})/',                                  // SERVER_PORT
        ],
        // Ruby
        'ruby' => [
            '/port\s+(\d+)/',                                            // port 3000
            '/-p\s+(\d+)/',                                              // -p 3000
        ],
    ];

    /**
     * Files to check for port definitions
     */
    private const PORT_FILES = [
        'node' => ['index.js', 'app.js', 'server.js', 'main.js', 'src/index.js', 'src/main.js', 'src/server.js', 'src/app.js'],
        'python' => ['main.py', 'app.py', 'run.py', 'server.py', 'src/main.py', 'wsgi.py', 'asgi.py'],
        'go' => ['main.go', 'cmd/main.go', 'cmd/server/main.go', 'server.go'],
        'rust' => ['src/main.rs', 'main.rs'],
        'php' => ['artisan', 'public/index.php', 'index.php'],
        'ruby' => ['config.ru', 'config/puma.rb', 'Procfile'],
    ];

    /**
     * Detect port from source code
     *
     * @return int|null Detected port or null if not found
     */
    public function detect(string $appPath, ?string $framework = null): ?int
    {
        // Try framework-specific detection first
        if ($framework !== null) {
            $language = $this->getLanguageForFramework($framework);
            if ($language !== null) {
                $port = $this->detectForLanguage($appPath, $language);
                if ($port !== null) {
                    return $port;
                }
            }
        }

        // Try all languages
        foreach (array_keys(self::PORT_FILES) as $language) {
            $port = $this->detectForLanguage($appPath, $language);
            if ($port !== null) {
                return $port;
            }
        }

        // Check package.json scripts
        $port = $this->detectFromPackageJsonScripts($appPath);
        if ($port !== null) {
            return $port;
        }

        // Check Procfile
        $port = $this->detectFromProcfile($appPath);
        if ($port !== null) {
            return $port;
        }

        return null;
    }

    /**
     * Detect port for a specific language
     */
    private function detectForLanguage(string $appPath, string $language): ?int
    {
        if (! isset(self::PORT_FILES[$language]) || ! isset(self::PORT_PATTERNS[$language])) {
            return null;
        }

        foreach (self::PORT_FILES[$language] as $file) {
            $filePath = $appPath.'/'.$file;
            if (! $this->isReadableFile($filePath)) {
                continue;
            }

            $content = file_get_contents($filePath);
            foreach (self::PORT_PATTERNS[$language] as $pattern) {
                if (preg_match($pattern, $content, $matches)) {
                    $port = (int) $matches[1];
                    if ($this->isValidPort($port)) {
                        return $port;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Detect port from package.json scripts
     */
    private function detectFromPackageJsonScripts(string $appPath): ?int
    {
        $packageJson = $appPath.'/package.json';
        if (! $this->isReadableFile($packageJson)) {
            return null;
        }

        try {
            $content = file_get_contents($packageJson);
            $json = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            $scripts = $json['scripts'] ?? [];
            $relevantScripts = ['start', 'dev', 'serve', 'server'];

            foreach ($relevantScripts as $scriptName) {
                if (isset($scripts[$scriptName])) {
                    $script = $scripts[$scriptName];

                    // Look for --port or -p flags
                    if (preg_match('/(?:--port|-p)\s*[=\s]*(\d+)/', $script, $matches)) {
                        $port = (int) $matches[1];
                        if ($this->isValidPort($port)) {
                            return $port;
                        }
                    }

                    // Look for PORT= assignment
                    if (preg_match('/PORT=(\d+)/', $script, $matches)) {
                        $port = (int) $matches[1];
                        if ($this->isValidPort($port)) {
                            return $port;
                        }
                    }
                }
            }
        } catch (\JsonException) {
            return null;
        }

        return null;
    }

    /**
     * Detect port from Procfile
     */
    private function detectFromProcfile(string $appPath): ?int
    {
        $procfile = $appPath.'/Procfile';
        if (! $this->isReadableFile($procfile)) {
            return null;
        }

        $content = file_get_contents($procfile);
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            // Look for web: process definition
            if (preg_match('/^web:\s*(.+)$/i', $line, $matches)) {
                $command = $matches[1];

                // Check for port flags
                if (preg_match('/(?:--port|-p)\s*[=\s]*(\d+)/', $command, $portMatch)) {
                    $port = (int) $portMatch[1];
                    if ($this->isValidPort($port)) {
                        return $port;
                    }
                }

                // Check for $PORT or ${PORT}
                if (preg_match('/\$\{?PORT\}?/', $command)) {
                    // Uses PORT env var, default to 3000 for Node, 8000 for Python
                    if (str_contains($command, 'node') || str_contains($command, 'npm')) {
                        return 3000;
                    }
                    if (str_contains($command, 'python') || str_contains($command, 'uvicorn') || str_contains($command, 'gunicorn')) {
                        return 8000;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Get language for a framework
     */
    private function getLanguageForFramework(string $framework): ?string
    {
        $frameworkLanguages = [
            // Node.js
            'nestjs' => 'node',
            'nextjs' => 'node',
            'nuxt' => 'node',
            'express' => 'node',
            'fastify' => 'node',
            'hono' => 'node',
            'remix' => 'node',
            'astro' => 'node',
            'sveltekit' => 'node',
            'vite-react' => 'node',
            'vite-vue' => 'node',
            // Python
            'django' => 'python',
            'fastapi' => 'python',
            'flask' => 'python',
            // Go
            'go-fiber' => 'go',
            'go-gin' => 'go',
            'go-echo' => 'go',
            'go' => 'go',
            // Rust
            'rust-axum' => 'rust',
            'rust-actix' => 'rust',
            'rust' => 'rust',
            // PHP
            'laravel' => 'php',
            'symfony' => 'php',
            // Ruby
            'rails' => 'ruby',
            'sinatra' => 'ruby',
        ];

        return $frameworkLanguages[$framework] ?? null;
    }

    /**
     * Validate port number
     */
    private function isValidPort(int $port): bool
    {
        return $port > 0 && $port <= 65535;
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
