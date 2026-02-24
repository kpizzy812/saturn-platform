<?php

namespace App\Services\RepositoryAnalyzer\Detectors;

use App\Services\RepositoryAnalyzer\DTOs\DetectedDatabase;
use App\Services\RepositoryAnalyzer\DTOs\DetectedService;
use App\Services\RepositoryAnalyzer\DTOs\DockerComposeService;
use Symfony\Component\Yaml\Yaml;

/**
 * Analyzes docker-compose.yml to extract services, databases, and configuration
 */
class DockerComposeAnalyzer
{
    private const MAX_FILE_SIZE = 512 * 1024; // 512KB

    private const COMPOSE_FILES = [
        'docker-compose.yml',
        'docker-compose.yaml',
        'compose.yml',
        'compose.yaml',
        'docker-compose.dev.yml',
        'docker-compose.dev.yaml',
    ];

    /**
     * Database image patterns and their types
     */
    private const DATABASE_IMAGES = [
        'postgres' => [
            'type' => 'postgresql',
            'envVarName' => 'DATABASE_URL',
            'defaultPort' => 5432,
        ],
        'mysql' => [
            'type' => 'mysql',
            'envVarName' => 'DATABASE_URL',
            'defaultPort' => 3306,
        ],
        'mariadb' => [
            'type' => 'mysql',
            'envVarName' => 'DATABASE_URL',
            'defaultPort' => 3306,
        ],
        'mongo' => [
            'type' => 'mongodb',
            'envVarName' => 'MONGODB_URL',
            'defaultPort' => 27017,
        ],
        'redis' => [
            'type' => 'redis',
            'envVarName' => 'REDIS_URL',
            'defaultPort' => 6379,
        ],
        'clickhouse' => [
            'type' => 'clickhouse',
            'envVarName' => 'CLICKHOUSE_URL',
            'defaultPort' => 8123,
        ],
    ];

    /**
     * External service image patterns
     */
    private const SERVICE_IMAGES = [
        'elasticsearch' => [
            'description' => 'Elasticsearch for full-text search',
            'envVars' => ['ELASTICSEARCH_URL'],
        ],
        'rabbitmq' => [
            'description' => 'RabbitMQ message broker',
            'envVars' => ['RABBITMQ_URL', 'AMQP_URL'],
        ],
        'kafka' => [
            'description' => 'Apache Kafka event streaming',
            'envVars' => ['KAFKA_BROKERS'],
        ],
        'minio' => [
            'description' => 'MinIO S3-compatible storage',
            'envVars' => ['S3_ENDPOINT', 'AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY'],
        ],
        'mailhog' => [
            'description' => 'MailHog email testing',
            'envVars' => ['SMTP_HOST', 'SMTP_PORT'],
        ],
        'mailpit' => [
            'description' => 'Mailpit email testing',
            'envVars' => ['SMTP_HOST', 'SMTP_PORT'],
        ],
    ];

    /**
     * Analyze docker-compose files in repository
     *
     * @return array{services: DockerComposeService[], databases: DetectedDatabase[], externalServices: DetectedService[]}
     */
    public function analyze(string $repoPath, ?string $appPath = null): array
    {
        $searchPaths = $appPath ? [$appPath, $repoPath] : [$repoPath];

        foreach ($searchPaths as $path) {
            foreach (self::COMPOSE_FILES as $file) {
                $filePath = $path.'/'.$file;
                if ($this->isReadableFile($filePath)) {
                    return $this->parseComposeFile($filePath);
                }
            }
        }

        return [
            'services' => [],
            'databases' => [],
            'externalServices' => [],
        ];
    }

    /**
     * Parse a docker-compose file
     */
    private function parseComposeFile(string $filePath): array
    {
        try {
            $content = file_get_contents($filePath);
            $compose = Yaml::parse($content);

            if (! isset($compose['services']) || ! is_array($compose['services'])) {
                return ['services' => [], 'databases' => [], 'externalServices' => []];
            }

            $services = [];
            $databases = [];
            $externalServices = [];

            foreach ($compose['services'] as $name => $config) {
                $service = $this->parseService($name, $config);
                $services[] = $service;

                // Check if it's a database
                $dbType = $this->detectDatabaseType($service->image);
                if ($dbType !== null) {
                    $databases[] = new DetectedDatabase(
                        type: self::DATABASE_IMAGES[$dbType]['type'],
                        name: $name,
                        envVarName: self::DATABASE_IMAGES[$dbType]['envVarName'],
                        consumers: [],
                        detectedVia: 'docker-compose:'.$service->image,
                    );
                }

                // Check if it's an external service
                $extService = $this->detectExternalService($name, $service->image);
                if ($extService !== null) {
                    $externalServices[] = $extService;
                }
            }

            return [
                'services' => $services,
                'databases' => $databases,
                'externalServices' => $externalServices,
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('[DockerComposeAnalyzer] Failed to parse compose file', [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);

            return ['services' => [], 'databases' => [], 'externalServices' => []];
        }
    }

    /**
     * Parse a single service definition
     */
    private function parseService(string $name, array $config): DockerComposeService
    {
        return new DockerComposeService(
            name: $name,
            image: $this->parseImage($config),
            ports: $this->parsePorts($config['ports'] ?? []),
            environment: $this->parseEnvironment($config['environment'] ?? []),
            healthcheck: $this->parseHealthcheck($config['healthcheck'] ?? null),
            dependsOn: $this->parseDependsOn($config['depends_on'] ?? []),
            volumes: $config['volumes'] ?? [],
        );
    }

    /**
     * Parse image from service config (handles both string and array formats)
     */
    private function parseImage(array $config): string
    {
        // Handle image field
        if (isset($config['image'])) {
            if (is_string($config['image'])) {
                return $config['image'];
            }
            // Array format like {name: "image", tag: "latest"}
            if (is_array($config['image'])) {
                return $config['image']['name'] ?? 'unknown';
            }
        }

        // Handle build field
        if (isset($config['build'])) {
            if (is_string($config['build'])) {
                return 'build:'.$config['build'];
            }
            // Array format like {context: ".", dockerfile: "Dockerfile"}
            if (is_array($config['build'])) {
                $context = $config['build']['context'] ?? '.';
                $dockerfile = $config['build']['dockerfile'] ?? 'Dockerfile';

                return 'build:'.$context.'/'.$dockerfile;
            }
        }

        return 'unknown';
    }

    /**
     * Parse ports configuration
     */
    private function parsePorts(array $ports): array
    {
        $result = [];
        foreach ($ports as $port) {
            if (is_string($port)) {
                $result[] = $port;
            } elseif (is_array($port) && isset($port['target'])) {
                $result[] = ($port['published'] ?? $port['target']).':'.$port['target'];
            }
        }

        return $result;
    }

    /**
     * Parse environment variables
     */
    private function parseEnvironment(?array $env): array
    {
        if ($env === null) {
            return [];
        }

        $result = [];

        // Handle array format: ['KEY=value', 'KEY2=value2']
        if (array_is_list($env)) {
            foreach ($env as $item) {
                if (is_string($item) && str_contains($item, '=')) {
                    [$key, $value] = explode('=', $item, 2);
                    $result[$key] = $value;
                }
            }
        } else {
            // Handle map format: {KEY: value, KEY2: value2}
            $result = $env;
        }

        return $result;
    }

    /**
     * Parse healthcheck configuration
     */
    private function parseHealthcheck(?array $healthcheck): ?string
    {
        if ($healthcheck === null || ! isset($healthcheck['test'])) {
            return null;
        }

        $test = $healthcheck['test'];
        if (is_array($test)) {
            // Format: ["CMD", "curl", "-f", "http://localhost/health"]
            return implode(' ', array_slice($test, 1));
        }

        return $test;
    }

    /**
     * Parse depends_on configuration
     */
    private function parseDependsOn(array $dependsOn): array
    {
        $result = [];
        foreach ($dependsOn as $key => $value) {
            if (is_string($value)) {
                $result[] = $value;
            } elseif (is_string($key)) {
                $result[] = $key;
            }
        }

        return $result;
    }

    /**
     * Detect database type from image name
     */
    private function detectDatabaseType(string $image): ?string
    {
        $imageLower = strtolower($image);

        foreach (array_keys(self::DATABASE_IMAGES) as $pattern) {
            if (str_contains($imageLower, $pattern)) {
                return $pattern;
            }
        }

        return null;
    }

    /**
     * Detect external service from image name
     */
    private function detectExternalService(string $name, string $image): ?DetectedService
    {
        $imageLower = strtolower($image);

        foreach (self::SERVICE_IMAGES as $pattern => $config) {
            if (str_contains($imageLower, $pattern) || str_contains(strtolower($name), $pattern)) {
                return new DetectedService(
                    type: $pattern,
                    description: $config['description'],
                    requiredEnvVars: $config['envVars'],
                );
            }
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
