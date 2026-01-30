# Dependency Analyzer

**Файл:** `app/Services/RepositoryAnalyzer/Detectors/DependencyAnalyzer.php`

## Определение баз данных

### PostgreSQL

| Package Manager | Dependencies |
|-----------------|--------------|
| npm | `pg`, `@prisma/client`, `sequelize`, `typeorm`, `drizzle-orm`, `knex`, `postgres` |
| pip | `psycopg2`, `psycopg2-binary`, `asyncpg`, `sqlalchemy`, `databases[postgresql]` |
| composer | `doctrine/dbal`, `illuminate/database` |
| gem | `pg`, `activerecord-postgresql-adapter` |
| go | `github.com/lib/pq`, `github.com/jackc/pgx`, `gorm.io/driver/postgres` |
| cargo | `tokio-postgres`, `sqlx`, `diesel` |

**Env Variable:** `DATABASE_URL`

### MySQL / MariaDB

| Package Manager | Dependencies |
|-----------------|--------------|
| npm | `mysql`, `mysql2` |
| pip | `mysqlclient`, `pymysql`, `aiomysql` |
| composer | `doctrine/dbal`, `illuminate/database` |
| gem | `mysql2` |
| go | `github.com/go-sql-driver/mysql`, `gorm.io/driver/mysql` |
| cargo | `mysql`, `sqlx` |

**Env Variable:** `DATABASE_URL`

### MongoDB

| Package Manager | Dependencies |
|-----------------|--------------|
| npm | `mongodb`, `mongoose`, `@typegoose/typegoose` |
| pip | `pymongo`, `motor`, `mongoengine` |
| composer | `mongodb/mongodb`, `jenssegers/mongodb` |
| gem | `mongoid`, `mongo` |
| go | `go.mongodb.org/mongo-driver` |
| cargo | `mongodb` |

**Env Variable:** `MONGODB_URL`

### Redis

| Package Manager | Dependencies |
|-----------------|--------------|
| npm | `redis`, `ioredis`, `@upstash/redis`, `bullmq`, `bull` |
| pip | `redis`, `aioredis`, `celery[redis]` |
| composer | `predis/predis`, `phpredis/phpredis` |
| gem | `redis`, `sidekiq`, `resque` |
| go | `github.com/go-redis/redis`, `github.com/redis/go-redis` |
| cargo | `redis`, `deadpool-redis` |

**Env Variable:** `REDIS_URL`

### ClickHouse

| Package Manager | Dependencies |
|-----------------|--------------|
| npm | `@clickhouse/client`, `clickhouse` |
| pip | `clickhouse-driver`, `clickhouse-connect`, `asynch` |
| go | `github.com/ClickHouse/clickhouse-go` |

**Env Variable:** `CLICKHOUSE_URL`

---

## Определение внешних сервисов

### S3 / Object Storage

| Package Manager | Dependencies |
|-----------------|--------------|
| npm | `@aws-sdk/client-s3`, `aws-sdk`, `minio` |
| pip | `boto3`, `minio` |
| go | `github.com/aws/aws-sdk-go`, `github.com/minio/minio-go` |

**Required Env Vars:** `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `S3_BUCKET`, `S3_ENDPOINT`

### Elasticsearch

| Package Manager | Dependencies |
|-----------------|--------------|
| npm | `@elastic/elasticsearch`, `elasticsearch` |
| pip | `elasticsearch`, `elasticsearch-dsl` |
| go | `github.com/elastic/go-elasticsearch` |

**Required Env Vars:** `ELASTICSEARCH_URL`, `ELASTIC_URL`

### RabbitMQ

| Package Manager | Dependencies |
|-----------------|--------------|
| npm | `amqplib`, `amqp-connection-manager` |
| pip | `pika`, `aio-pika`, `celery[rabbitmq]` |
| go | `github.com/streadway/amqp`, `github.com/rabbitmq/amqp091-go` |

**Required Env Vars:** `RABBITMQ_URL`, `AMQP_URL`

### Kafka

| Package Manager | Dependencies |
|-----------------|--------------|
| npm | `kafkajs`, `node-rdkafka` |
| pip | `kafka-python`, `aiokafka`, `confluent-kafka` |
| go | `github.com/segmentio/kafka-go`, `github.com/confluentinc/confluent-kafka-go` |

**Required Env Vars:** `KAFKA_BROKERS`, `KAFKA_URL`

### Email (SMTP)

| Package Manager | Dependencies |
|-----------------|--------------|
| npm | `nodemailer`, `@sendgrid/mail`, `resend` |
| pip | `sendgrid`, `resend` |

**Required Env Vars:** `SMTP_HOST`, `SMTP_PORT`, `SENDGRID_API_KEY`, `RESEND_API_KEY`

---

## Парсинг .env.example

Анализатор ищет файлы:
- `.env.example`
- `.env.sample`
- `.env.template`
- `env.example`

### Определение обязательных переменных

Переменная считается **обязательной** если:
- Значение пустое (`KEY=`)
- Содержит placeholder (`your_`, `xxx`, `CHANGE_ME`, `<...>`, `TODO`)

### Категоризация переменных

| Паттерн ключа | Категория |
|---------------|-----------|
| `DATABASE`, `DB_` | database |
| `REDIS` | cache |
| `MONGODB`, `MONGO_` | database |
| `AWS`, `S3_` | storage |
| `SMTP`, `MAIL` | email |
| `SECRET`, `KEY`, `TOKEN` | secrets |
| `PORT`, `HOST`, `URL` | network |
| Остальное | general |

---

## Реализация

> **Важно:** Для парсинга .env.example файлов используем существующий сервис
> `App\Services\EnvExampleParser`, который уже реализован в Saturn.

```php
<?php

namespace App\Services\RepositoryAnalyzer\Detectors;

use App\Services\EnvExampleParser;
use App\Services\RepositoryAnalyzer\DTOs\DetectedApp;
use App\Services\RepositoryAnalyzer\DTOs\DependencyAnalysisResult;
use App\Services\RepositoryAnalyzer\DTOs\DetectedDatabase;
use App\Services\RepositoryAnalyzer\DTOs\DetectedService;
use App\Services\RepositoryAnalyzer\DTOs\DetectedEnvVariable;
use Yosymfony\Toml\Toml;
use JsonException;

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
            : $repoPath . '/' . $app->path;

        $databases = $this->detectDatabases($appPath, $app);
        $services = $this->detectServices($appPath, $app);
        $envVariables = $this->detectEnvVariables($appPath, $app);

        return new DependencyAnalysisResult(
            databases: $databases,
            services: $services,
            envVariables: $envVariables,
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
                if (!isset($rules[$packageManager])) {
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
                if (!isset($rules[$packageManager])) {
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
     * Detect environment variables from .env.example files
     *
     * Uses the existing EnvExampleParser service from Saturn.
     *
     * @return DetectedEnvVariable[]
     */
    private function detectEnvVariables(string $appPath, DetectedApp $app): array
    {
        $envFiles = ['.env.example', '.env.sample', '.env.template', 'env.example'];

        foreach ($envFiles as $envFile) {
            $filePath = $appPath . '/' . $envFile;
            if (file_exists($filePath) && filesize($filePath) <= self::MAX_FILE_SIZE) {
                return $this->parseEnvFile($filePath, $app);
            }
        }

        return [];
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
        $packageJson = $appPath . '/package.json';
        if ($this->isReadableFile($packageJson)) {
            $deps = $this->extractNodeDeps($packageJson);
            if ($deps !== null) {
                $dependencies['npm'] = $deps;
            }
        }

        // requirements.txt (pip)
        $requirements = $appPath . '/requirements.txt';
        if ($this->isReadableFile($requirements)) {
            $dependencies['pip'] = $this->extractPythonRequirementsDeps($requirements);
        }

        // pyproject.toml (poetry/pip)
        $pyproject = $appPath . '/pyproject.toml';
        if ($this->isReadableFile($pyproject)) {
            $pyDeps = $this->extractPyprojectDeps($pyproject);
            if ($pyDeps !== null) {
                $dependencies['pip'] = array_merge($dependencies['pip'] ?? [], $pyDeps);
            }
        }

        // composer.json (PHP)
        $composer = $appPath . '/composer.json';
        if ($this->isReadableFile($composer)) {
            $deps = $this->extractPhpDeps($composer);
            if ($deps !== null) {
                $dependencies['composer'] = $deps;
            }
        }

        // Gemfile (Ruby)
        $gemfile = $appPath . '/Gemfile';
        if ($this->isReadableFile($gemfile)) {
            $dependencies['gem'] = $this->extractRubyDeps($gemfile);
        }

        // go.mod (Go)
        $goMod = $appPath . '/go.mod';
        if ($this->isReadableFile($goMod)) {
            $dependencies['go'] = $this->extractGoDeps($goMod);
        }

        // Cargo.toml (Rust)
        $cargo = $appPath . '/Cargo.toml';
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
```
