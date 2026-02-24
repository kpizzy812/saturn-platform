<?php

namespace App\Services\RepositoryAnalyzer\Detectors;

use App\Services\EnvExampleParser;
use App\Services\RepositoryAnalyzer\DTOs\DependencyAnalysisResult;
use App\Services\RepositoryAnalyzer\DTOs\DetectedApp;
use App\Services\RepositoryAnalyzer\DTOs\DetectedDatabase;
use App\Services\RepositoryAnalyzer\DTOs\DetectedEnvVariable;
use App\Services\RepositoryAnalyzer\DTOs\DetectedPersistentVolume;
use App\Services\RepositoryAnalyzer\DTOs\DetectedService;
use JsonException;
use Yosymfony\Toml\Toml;

class DependencyAnalyzer
{
    /**
     * Maximum file size to read (512KB)
     */
    private const MAX_FILE_SIZE = 512 * 1024;

    /**
     * Database detection rules
     *
     * Note: Order matters for ambiguous packages (e.g., sqlx supports multiple DBs).
     * More specific packages should be listed first.
     */
    private const DATABASE_RULES = [
        'postgresql' => [
            'npm' => ['pg', 'postgres', '@prisma/client', 'sequelize', 'typeorm', 'drizzle-orm', 'knex'],
            'pip' => ['psycopg2', 'psycopg2-binary', 'asyncpg', 'databases[postgresql]'],
            'composer' => ['doctrine/dbal', 'illuminate/database'],
            'gem' => ['pg', 'activerecord-postgresql-adapter'],
            'go' => ['github.com/lib/pq', 'github.com/jackc/pgx', 'gorm.io/driver/postgres'],
            'cargo' => ['tokio-postgres', 'diesel'],
            'envVarName' => 'DATABASE_URL',
            'saturnType' => 'postgresql',
        ],
        'mysql' => [
            'npm' => ['mysql', 'mysql2'],
            'pip' => ['mysqlclient', 'pymysql', 'aiomysql'],
            'gem' => ['mysql2'],
            'go' => ['github.com/go-sql-driver/mysql', 'gorm.io/driver/mysql'],
            'cargo' => ['mysql'],
            'envVarName' => 'DATABASE_URL',
            'saturnType' => 'mysql',
        ],
        'mongodb' => [
            'npm' => ['mongodb', 'mongoose', '@typegoose/typegoose'],
            'pip' => ['pymongo', 'motor', 'mongoengine'],
            'composer' => ['mongodb/mongodb', 'jenssegers/mongodb'],
            'gem' => ['mongoid', 'mongo'],
            'go' => ['go.mongodb.org/mongo-driver'],
            'cargo' => ['mongodb'],
            'envVarName' => 'MONGODB_URL',
            'saturnType' => 'mongodb',
        ],
        'redis' => [
            'npm' => ['redis', 'ioredis', '@upstash/redis', 'bullmq', 'bull'],
            'pip' => ['redis', 'aioredis'],
            'composer' => ['predis/predis'],
            'gem' => ['redis', 'sidekiq', 'resque'],
            'go' => ['github.com/go-redis/redis', 'github.com/redis/go-redis'],
            'cargo' => ['redis', 'deadpool-redis'],
            'envVarName' => 'REDIS_URL',
            'saturnType' => 'redis',
        ],
        'clickhouse' => [
            'npm' => ['@clickhouse/client', 'clickhouse'],
            'pip' => ['clickhouse-driver', 'clickhouse-connect', 'asynch'],
            'go' => ['github.com/ClickHouse/clickhouse-go'],
            'envVarName' => 'CLICKHOUSE_URL',
            'saturnType' => 'clickhouse',
        ],
    ];

    /**
     * SQLite detection rules — file-based DB, needs persistent volume instead of standalone database
     */
    private const SQLITE_RULES = [
        'npm' => ['better-sqlite3', 'sql.js', 'sqlite3'],
        'pip' => ['aiosqlite', 'databases[sqlite]'],
        'composer' => ['ext-sqlite3'],
        'gem' => ['sqlite3'],
        'go' => ['github.com/mattn/go-sqlite3', 'modernc.org/sqlite', 'gorm.io/driver/sqlite'],
        'cargo' => ['rusqlite', 'diesel'],
    ];

    /**
     * Framework-specific SQLite mount paths and env vars
     *
     * When we detect SQLite for a known framework, we use the framework's
     * conventional database directory so it works with minimal user config.
     */
    private const SQLITE_FRAMEWORK_DEFAULTS = [
        'laravel' => [
            'mount_path' => '/var/www/html/database',
            'env_var_name' => 'DB_DATABASE',
            'env_var_value' => '/var/www/html/database/database.sqlite',
        ],
        'django' => [
            'mount_path' => '/app/data',
            'env_var_name' => 'DATABASE_PATH',
            'env_var_value' => '/app/data/db.sqlite3',
        ],
    ];

    private const SQLITE_DEFAULT = [
        'mount_path' => '/data',
        'env_var_name' => 'DATABASE_PATH',
        'env_var_value' => '/data/db.sqlite',
    ];

    private const SERVICE_RULES = [
        's3' => [
            'npm' => ['@aws-sdk/client-s3', 'aws-sdk', 'minio'],
            'pip' => ['boto3', 'minio'],
            'go' => ['github.com/aws/aws-sdk-go', 'github.com/minio/minio-go'],
            'envVars' => ['AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'S3_BUCKET', 'S3_ENDPOINT'],
            'description' => 'S3-compatible object storage (AWS S3, MinIO, etc.)',
        ],
        'elasticsearch' => [
            'npm' => ['@elastic/elasticsearch'],
            'pip' => ['elasticsearch', 'elasticsearch-dsl'],
            'go' => ['github.com/elastic/go-elasticsearch'],
            'envVars' => ['ELASTICSEARCH_URL', 'ELASTIC_URL'],
            'description' => 'Elasticsearch for full-text search',
        ],
        'rabbitmq' => [
            'npm' => ['amqplib', 'amqp-connection-manager'],
            'pip' => ['pika', 'aio-pika'],
            'go' => ['github.com/streadway/amqp', 'github.com/rabbitmq/amqp091-go'],
            'envVars' => ['RABBITMQ_URL', 'AMQP_URL'],
            'description' => 'RabbitMQ message broker',
        ],
        'kafka' => [
            'npm' => ['kafkajs', 'node-rdkafka'],
            'pip' => ['kafka-python', 'aiokafka', 'confluent-kafka'],
            'go' => ['github.com/segmentio/kafka-go', 'github.com/confluentinc/confluent-kafka-go'],
            'envVars' => ['KAFKA_BROKERS', 'KAFKA_URL'],
            'description' => 'Apache Kafka for event streaming',
        ],
        'email' => [
            'npm' => ['nodemailer', '@sendgrid/mail', 'resend'],
            'pip' => ['sendgrid', 'resend'],
            'envVars' => ['SMTP_HOST', 'SMTP_PORT', 'SENDGRID_API_KEY', 'RESEND_API_KEY'],
            'description' => 'Email service (SMTP, SendGrid, Resend)',
        ],
    ];

    /**
     * Env variable key patterns for categorization
     */
    private const ENV_CATEGORIES = [
        'database' => ['DATABASE', 'DB_', 'POSTGRES', 'MYSQL', 'MONGODB', 'MONGO_'],
        'cache' => ['REDIS', 'CACHE_', 'MEMCACHE'],
        'storage' => ['AWS', 'S3_', 'MINIO', 'STORAGE_'],
        'email' => ['SMTP', 'MAIL', 'SENDGRID', 'RESEND'],
        'secrets' => ['SECRET', '_KEY', '_TOKEN', 'PASSWORD', 'PRIVATE'],
        'network' => ['PORT', 'HOST', '_URL', 'DOMAIN'],
    ];

    public function analyze(string $repoPath, DetectedApp $app): DependencyAnalysisResult
    {
        $appPath = $app->path === '.'
            ? $repoPath
            : $repoPath.'/'.$app->path;

        $databases = $this->detectDatabases($appPath, $app);
        $services = $this->detectServices($appPath, $app);
        $envVariables = $this->detectEnvVariables($appPath, $app);
        $persistentVolumes = $this->detectSqliteVolumes($appPath, $app);

        return new DependencyAnalysisResult(
            databases: $databases,
            services: $services,
            envVariables: $envVariables,
            persistentVolumes: $persistentVolumes,
        );
    }

    /**
     * Detect database dependencies from package manager files
     *
     * @return DetectedDatabase[]
     */
    private function detectDatabases(string $appPath, DetectedApp $app): array
    {
        $detected = [];
        $dependencies = $this->extractDependencies($appPath);
        $foundTypes = []; // Track found DB types to prevent duplicates

        foreach (self::DATABASE_RULES as $dbType => $rules) {
            if (isset($foundTypes[$dbType])) {
                continue;
            }

            foreach ($dependencies as $packageManager => $deps) {
                if (! isset($rules[$packageManager])) {
                    continue;
                }

                $matchedDep = $this->findMatchingDependency($deps, $rules[$packageManager]);
                if ($matchedDep !== null) {
                    $detected[] = new DetectedDatabase(
                        type: $rules['saturnType'],
                        name: $dbType,
                        envVarName: $rules['envVarName'],
                        consumers: [$app->name],
                        detectedVia: "{$packageManager}:{$matchedDep}",
                    );
                    $foundTypes[$dbType] = true;
                    break; // Found this DB type, move to next
                }
            }
        }

        return $detected;
    }

    /**
     * Detect external service dependencies
     *
     * @return DetectedService[]
     */
    private function detectServices(string $appPath, DetectedApp $app): array
    {
        $detected = [];
        $dependencies = $this->extractDependencies($appPath);
        $foundTypes = [];

        foreach (self::SERVICE_RULES as $serviceType => $rules) {
            if (isset($foundTypes[$serviceType])) {
                continue;
            }

            foreach ($dependencies as $packageManager => $deps) {
                if (! isset($rules[$packageManager])) {
                    continue;
                }

                $matchedDep = $this->findMatchingDependency($deps, $rules[$packageManager]);
                if ($matchedDep !== null) {
                    $detected[] = new DetectedService(
                        type: $serviceType,
                        description: $rules['description'],
                        requiredEnvVars: $rules['envVars'],
                        consumers: [$app->name],
                    );
                    $foundTypes[$serviceType] = true;
                    break;
                }
            }
        }

        return $detected;
    }

    /**
     * Detect environment variables from .env.example files,
     * falling back to source code scanning.
     *
     * @return DetectedEnvVariable[]
     */
    private function detectEnvVariables(string $appPath, DetectedApp $app): array
    {
        // Priority 1: .env.example / .env.sample / .env.template
        $envFiles = ['.env.example', '.env.sample', '.env.template', 'env.example'];

        foreach ($envFiles as $envFile) {
            $filePath = $appPath.'/'.$envFile;
            if (file_exists($filePath) && filesize($filePath) <= self::MAX_FILE_SIZE) {
                return $this->parseEnvFile($filePath, $app);
            }
        }

        // Priority 2: Scan source code for env var references
        $fromSource = $this->detectEnvVariablesFromSource($appPath, $app);
        if (! empty($fromSource)) {
            return $fromSource;
        }

        // Priority 3: Extract from Dockerfile ENV directives
        return $this->detectEnvVariablesFromDockerfile($appPath, $app);
    }

    /**
     * Scan source code files for environment variable references
     *
     * Detects patterns like:
     * - Python: os.getenv("KEY"), os.environ["KEY"], os.environ.get("KEY")
     * - JS/TS: process.env.KEY
     * - Go: os.Getenv("KEY")
     * - Ruby: ENV["KEY"], ENV.fetch("KEY")
     *
     * @return DetectedEnvVariable[]
     */
    private function detectEnvVariablesFromSource(string $appPath, DetectedApp $app): array
    {
        $envVars = [];

        // Scan Python files
        $pythonFiles = array_merge(
            glob($appPath.'/*.py') ?: [],
            glob($appPath.'/src/*.py') ?: [],
            glob($appPath.'/app/*.py') ?: [],
            glob($appPath.'/bot/*.py') ?: [],
            glob($appPath.'/config/*.py') ?: [],
        );
        foreach ($pythonFiles as $file) {
            if (! $this->isReadableFile($file)) {
                continue;
            }
            $content = file_get_contents($file);
            // os.getenv("KEY"), os.environ.get("KEY")
            if (preg_match_all('/os\.(?:getenv|environ\.get)\s*\(\s*["\']([A-Z_][A-Z0-9_]*)["\']/', $content, $matches)) {
                foreach ($matches[1] as $key) {
                    $envVars[$key] = true;
                }
            }
            // os.environ["KEY"]
            if (preg_match_all('/os\.environ\s*\[\s*["\']([A-Z_][A-Z0-9_]*)["\']/', $content, $matches)) {
                foreach ($matches[1] as $key) {
                    $envVars[$key] = true;
                }
            }
        }

        // Scan JS/TS files
        $jsFiles = array_merge(
            glob($appPath.'/*.{js,ts,mjs,mts}', GLOB_BRACE) ?: [],
            glob($appPath.'/src/*.{js,ts,mjs,mts}', GLOB_BRACE) ?: [],
            glob($appPath.'/config/*.{js,ts}', GLOB_BRACE) ?: [],
        );
        foreach ($jsFiles as $file) {
            if (! $this->isReadableFile($file)) {
                continue;
            }
            $content = file_get_contents($file);
            // process.env.KEY
            if (preg_match_all('/process\.env\.([A-Z_][A-Z0-9_]*)/', $content, $matches)) {
                foreach ($matches[1] as $key) {
                    $envVars[$key] = true;
                }
            }
        }

        // Scan Go files
        $goFiles = array_merge(
            glob($appPath.'/*.go') ?: [],
            glob($appPath.'/cmd/*.go') ?: [],
            glob($appPath.'/internal/config/*.go') ?: [],
        );
        foreach ($goFiles as $file) {
            if (! $this->isReadableFile($file)) {
                continue;
            }
            $content = file_get_contents($file);
            // os.Getenv("KEY")
            if (preg_match_all('/os\.Getenv\s*\(\s*"([A-Z_][A-Z0-9_]*)"/', $content, $matches)) {
                foreach ($matches[1] as $key) {
                    $envVars[$key] = true;
                }
            }
        }

        // Filter out common non-configurable vars
        $ignored = ['PATH', 'HOME', 'USER', 'SHELL', 'PWD', 'TERM', 'LANG', 'LC_ALL', 'NODE_ENV', 'PYTHONPATH'];
        $envVars = array_diff_key($envVars, array_flip($ignored));

        return array_map(fn ($key) => new DetectedEnvVariable(
            key: $key,
            defaultValue: null,
            isRequired: true,
            category: $this->categorizeEnvVar($key),
            forApp: $app->name,
        ), array_keys($envVars));
    }

    /**
     * Extract env variables from Dockerfile ENV directives
     *
     * @return DetectedEnvVariable[]
     */
    private function detectEnvVariablesFromDockerfile(string $appPath, DetectedApp $app): array
    {
        $dockerfile = $appPath.'/Dockerfile';
        if (! $this->isReadableFile($dockerfile)) {
            return [];
        }

        $content = file_get_contents($dockerfile);
        $envVars = [];

        // Match ENV KEY=value and ENV KEY value
        if (preg_match_all('/^ENV\s+([A-Z_][A-Z0-9_]*)[\s=]/m', $content, $matches)) {
            foreach ($matches[1] as $key) {
                $envVars[$key] = true;
            }
        }

        // Match ARG KEY (build args that might need configuration)
        if (preg_match_all('/^ARG\s+([A-Z_][A-Z0-9_]*)(?:\s*=)?/m', $content, $matches)) {
            foreach ($matches[1] as $key) {
                $envVars[$key] = true;
            }
        }

        // Filter out standard/non-configurable vars
        $ignored = ['PATH', 'HOME', 'PYTHONUNBUFFERED', 'PYTHONDONTWRITEBYTECODE', 'DEBIAN_FRONTEND', 'TZ', 'LANG', 'LC_ALL', 'NODE_ENV'];
        $envVars = array_diff_key($envVars, array_flip($ignored));

        return array_map(fn ($key) => new DetectedEnvVariable(
            key: $key,
            defaultValue: null,
            isRequired: true,
            category: $this->categorizeEnvVar($key),
            forApp: $app->name,
        ), array_keys($envVars));
    }

    /**
     * Detect SQLite usage and create persistent volume recommendation
     *
     * SQLite is a file-based database — unlike PostgreSQL/MySQL, it doesn't need
     * a standalone container. Instead, it needs a persistent volume so the
     * database file survives container redeployments.
     *
     * @return DetectedPersistentVolume[]
     */
    private function detectSqliteVolumes(string $appPath, DetectedApp $app): array
    {
        $dependencies = $this->extractDependencies($appPath);

        foreach ($dependencies as $packageManager => $deps) {
            if (! isset(self::SQLITE_RULES[$packageManager])) {
                continue;
            }

            $matchedDep = $this->findMatchingDependency($deps, self::SQLITE_RULES[$packageManager]);
            if ($matchedDep === null) {
                continue;
            }

            // Also check for Prisma with sqlite provider
            if ($matchedDep === 'better-sqlite3' || $packageManager === 'npm') {
                // Accept the match as-is for npm sqlite packages
            }

            // Determine mount path based on framework
            $defaults = self::SQLITE_FRAMEWORK_DEFAULTS[$app->framework] ?? self::SQLITE_DEFAULT;

            return [
                new DetectedPersistentVolume(
                    name: 'sqlite-data',
                    mountPath: $defaults['mount_path'],
                    reason: "SQLite database detected ({$matchedDep})",
                    forApp: $app->name,
                    envVarName: $defaults['env_var_name'],
                    envVarValue: $defaults['env_var_value'],
                ),
            ];
        }

        // Also check for Prisma with sqlite datasource in schema.prisma
        if ($this->detectPrismaSqlite($appPath)) {
            $defaults = self::SQLITE_DEFAULT;

            return [
                new DetectedPersistentVolume(
                    name: 'sqlite-data',
                    mountPath: $defaults['mount_path'],
                    reason: 'SQLite database detected (prisma:sqlite)',
                    forApp: $app->name,
                    envVarName: $defaults['env_var_name'],
                    envVarValue: $defaults['env_var_value'],
                ),
            ];
        }

        return [];
    }

    /**
     * Check if Prisma schema uses sqlite as datasource provider
     */
    private function detectPrismaSqlite(string $appPath): bool
    {
        $schemaLocations = [
            $appPath.'/prisma/schema.prisma',
            $appPath.'/schema.prisma',
        ];

        foreach ($schemaLocations as $schemaPath) {
            if (! $this->isReadableFile($schemaPath)) {
                continue;
            }

            $content = file_get_contents($schemaPath);
            // Match: provider = "sqlite"
            if (preg_match('/provider\s*=\s*"sqlite"/i', $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract dependencies from all supported package manager files
     *
     * @return array<string, string[]> Map of package manager -> dependency names
     */
    private function extractDependencies(string $appPath): array
    {
        $dependencies = [];

        // package.json (npm/yarn/pnpm)
        $packageJson = $appPath.'/package.json';
        if ($this->isReadableFile($packageJson)) {
            $deps = $this->extractNodeDeps($packageJson);
            if ($deps !== null) {
                $dependencies['npm'] = $deps;
            }
        }

        // requirements.txt (pip)
        $requirements = $appPath.'/requirements.txt';
        if ($this->isReadableFile($requirements)) {
            $dependencies['pip'] = $this->extractPythonRequirementsDeps($requirements);
        }

        // pyproject.toml (poetry/pip)
        $pyproject = $appPath.'/pyproject.toml';
        if ($this->isReadableFile($pyproject)) {
            $pyDeps = $this->extractPyprojectDeps($pyproject);
            if ($pyDeps !== null) {
                $dependencies['pip'] = array_merge($dependencies['pip'] ?? [], $pyDeps);
            }
        }

        // composer.json (PHP)
        $composer = $appPath.'/composer.json';
        if ($this->isReadableFile($composer)) {
            $deps = $this->extractPhpDeps($composer);
            if ($deps !== null) {
                $dependencies['composer'] = $deps;
            }
        }

        // Gemfile (Ruby)
        $gemfile = $appPath.'/Gemfile';
        if ($this->isReadableFile($gemfile)) {
            $dependencies['gem'] = $this->extractRubyDeps($gemfile);
        }

        // go.mod (Go)
        $goMod = $appPath.'/go.mod';
        if ($this->isReadableFile($goMod)) {
            $dependencies['go'] = $this->extractGoDeps($goMod);
        }

        // Cargo.toml (Rust)
        $cargo = $appPath.'/Cargo.toml';
        if ($this->isReadableFile($cargo)) {
            $deps = $this->extractRustDeps($cargo);
            if ($deps !== null) {
                $dependencies['cargo'] = $deps;
            }
        }

        return $dependencies;
    }

    private function isReadableFile(string $path): bool
    {
        return file_exists($path) && filesize($path) <= self::MAX_FILE_SIZE;
    }

    private function extractNodeDeps(string $filePath): ?array
    {
        try {
            $content = file_get_contents($filePath);
            $json = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            return array_merge(
                array_keys($json['dependencies'] ?? []),
                array_keys($json['devDependencies'] ?? []),
            );
        } catch (JsonException) {
            return null;
        }
    }

    private function extractPythonRequirementsDeps(string $filePath): array
    {
        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);
        $deps = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '-')) {
                continue;
            }
            // Extract package name (before ==, >=, <=, ~=, [extras], etc.)
            if (preg_match('/^([a-zA-Z0-9_-]+)/', $line, $matches)) {
                $deps[] = strtolower($matches[1]);
            }
        }

        return $deps;
    }

    private function extractPyprojectDeps(string $filePath): ?array
    {
        try {
            $toml = Toml::parseFile($filePath);
            $deps = [];

            // PEP 621 format
            if (isset($toml['project']['dependencies'])) {
                foreach ($toml['project']['dependencies'] as $dep) {
                    if (preg_match('/^([a-zA-Z0-9_-]+)/', $dep, $matches)) {
                        $deps[] = strtolower($matches[1]);
                    }
                }
            }

            // Poetry format
            if (isset($toml['tool']['poetry']['dependencies'])) {
                $deps = array_merge($deps, array_keys($toml['tool']['poetry']['dependencies']));
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

    private function extractRubyDeps(string $filePath): array
    {
        $content = file_get_contents($filePath);
        $deps = [];

        if (preg_match_all("/gem\\s+['\"]([^'\"]+)['\"]/", $content, $matches)) {
            $deps = $matches[1];
        }

        return $deps;
    }

    private function extractGoDeps(string $filePath): array
    {
        $content = file_get_contents($filePath);
        $deps = [];

        // Match require blocks
        if (preg_match('/require\s*\((.*?)\)/s', $content, $block)) {
            if (preg_match_all('/^\s*([^\s]+)\s+v/m', $block[1], $matches)) {
                $deps = array_merge($deps, $matches[1]);
            }
        }

        // Match single-line requires
        if (preg_match_all('/require\s+([^\s]+)\s+v/', $content, $matches)) {
            $deps = array_merge($deps, $matches[1]);
        }

        return array_unique($deps);
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

    /**
     * Find first matching dependency (exact match only)
     */
    private function findMatchingDependency(array $deps, array $required): ?string
    {
        foreach ($required as $req) {
            if (in_array($req, $deps, true)) {
                return $req;
            }
        }

        return null;
    }

    /**
     * Parse .env.example file using Saturn's EnvExampleParser
     *
     * @return DetectedEnvVariable[]
     */
    private function parseEnvFile(string $filePath, DetectedApp $app): array
    {
        $content = file_get_contents($filePath);

        // Use existing Saturn service
        $parsed = EnvExampleParser::parse($content);

        $variables = [];
        foreach ($parsed as $var) {
            $variables[] = new DetectedEnvVariable(
                key: $var['key'],
                defaultValue: $var['value'],
                isRequired: $var['is_required'],
                category: $this->categorizeEnvVar($var['key']),
                forApp: $app->name,
            );
        }

        return $variables;
    }

    /**
     * Categorize environment variable by key pattern
     */
    private function categorizeEnvVar(string $key): string
    {
        $upperKey = strtoupper($key);

        foreach (self::ENV_CATEGORIES as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($upperKey, $pattern)) {
                    return $category;
                }
            }
        }

        return 'general';
    }
}
