<?php

namespace Tests\Unit\Services\RepositoryAnalyzer;

use App\Services\RepositoryAnalyzer\DTOs\DetectedDatabase;
use App\Services\RepositoryAnalyzer\DTOs\DetectedEnvVariable;
use App\Services\RepositoryAnalyzer\RepositoryAnalyzer;
use ReflectionMethod;
use Tests\TestCase;

class RepositoryAnalyzerTest extends TestCase
{
    private function callEnrichDatabaseConsumers(array $databases, array $envVariables): array
    {
        $analyzer = $this->app->make(RepositoryAnalyzer::class);
        $method = new ReflectionMethod($analyzer, 'enrichDatabaseConsumers');

        return $method->invoke($analyzer, $databases, $envVariables);
    }

    public function test_enriches_redis_consumers_from_env_variables(): void
    {
        $databases = [
            new DetectedDatabase(
                type: 'redis',
                name: 'redis',
                envVarName: 'REDIS_URL',
                consumers: [],
                detectedVia: 'docker-compose:redis',
                port: 6379,
            ),
        ];

        $envVariables = [
            new DetectedEnvVariable(
                key: 'REDIS_URL',
                defaultValue: 'redis://:changeme@localhost:6379/0',
                isRequired: true,
                category: 'cache',
                forApp: 'fundingbot',
            ),
            new DetectedEnvVariable(
                key: 'REDIS_URL',
                defaultValue: 'redis://:changeme@localhost:6379/0',
                isRequired: true,
                category: 'cache',
                forApp: 'hummingbot',
            ),
        ];

        $result = $this->callEnrichDatabaseConsumers($databases, $envVariables);

        $this->assertCount(1, $result);
        $this->assertEquals('redis', $result[0]->type);
        $this->assertContains('fundingbot', $result[0]->consumers);
        $this->assertContains('hummingbot', $result[0]->consumers);
    }

    public function test_enriches_postgresql_consumers_from_database_url(): void
    {
        $databases = [
            new DetectedDatabase(
                type: 'postgresql',
                name: 'postgresql',
                envVarName: 'DATABASE_URL',
                consumers: [],
                detectedVia: 'docker-compose:postgres',
            ),
        ];

        $envVariables = [
            new DetectedEnvVariable(
                key: 'DATABASE_URL',
                defaultValue: 'postgresql://user:pass@localhost:5432/db',
                isRequired: true,
                category: 'database',
                forApp: 'api',
            ),
        ];

        $result = $this->callEnrichDatabaseConsumers($databases, $envVariables);

        $this->assertContains('api', $result[0]->consumers);
    }

    public function test_does_not_duplicate_existing_consumers(): void
    {
        $databases = [
            new DetectedDatabase(
                type: 'redis',
                name: 'redis',
                envVarName: 'REDIS_URL',
                consumers: ['fundingbot'],
                detectedVia: 'npm:ioredis',
            ),
        ];

        $envVariables = [
            new DetectedEnvVariable(
                key: 'REDIS_URL',
                defaultValue: 'redis://localhost',
                isRequired: true,
                category: 'cache',
                forApp: 'fundingbot',
            ),
        ];

        $result = $this->callEnrichDatabaseConsumers($databases, $envVariables);

        $this->assertCount(1, $result[0]->consumers);
        $this->assertEquals(['fundingbot'], $result[0]->consumers);
    }

    public function test_enriches_multiple_database_types(): void
    {
        $databases = [
            new DetectedDatabase(
                type: 'postgresql',
                name: 'db',
                envVarName: 'DATABASE_URL',
                consumers: [],
                detectedVia: 'docker-compose:postgres',
            ),
            new DetectedDatabase(
                type: 'redis',
                name: 'redis',
                envVarName: 'REDIS_URL',
                consumers: [],
                detectedVia: 'docker-compose:redis',
            ),
        ];

        $envVariables = [
            new DetectedEnvVariable(
                key: 'POSTGRES_HOST',
                defaultValue: 'localhost',
                isRequired: true,
                category: 'database',
                forApp: 'api',
            ),
            new DetectedEnvVariable(
                key: 'REDIS_HOST',
                defaultValue: 'localhost',
                isRequired: true,
                category: 'cache',
                forApp: 'api',
            ),
            new DetectedEnvVariable(
                key: 'REDIS_PASSWORD',
                defaultValue: 'secret',
                isRequired: true,
                category: 'cache',
                forApp: 'worker',
            ),
        ];

        $result = $this->callEnrichDatabaseConsumers($databases, $envVariables);

        $this->assertContains('api', $result[0]->consumers);    // PostgreSQL
        $this->assertContains('api', $result[1]->consumers);    // Redis
        $this->assertContains('worker', $result[1]->consumers); // Redis
    }

    public function test_unrelated_env_vars_dont_create_consumers(): void
    {
        $databases = [
            new DetectedDatabase(
                type: 'redis',
                name: 'redis',
                envVarName: 'REDIS_URL',
                consumers: [],
                detectedVia: 'docker-compose:redis',
            ),
        ];

        $envVariables = [
            new DetectedEnvVariable(
                key: 'API_KEY',
                defaultValue: null,
                isRequired: true,
                category: 'secrets',
                forApp: 'fundingbot',
            ),
            new DetectedEnvVariable(
                key: 'PORT',
                defaultValue: '3000',
                isRequired: false,
                category: 'network',
                forApp: 'fundingbot',
            ),
        ];

        $result = $this->callEnrichDatabaseConsumers($databases, $envVariables);

        $this->assertEmpty($result[0]->consumers);
    }

    public function test_database_url_matches_mysql_when_mysql_detected(): void
    {
        $databases = [
            new DetectedDatabase(
                type: 'mysql',
                name: 'mysql',
                envVarName: 'DATABASE_URL',
                consumers: [],
                detectedVia: 'docker-compose:mysql',
            ),
        ];

        $envVariables = [
            new DetectedEnvVariable(
                key: 'DATABASE_URL',
                defaultValue: 'mysql://user:pass@localhost:3306/db',
                isRequired: true,
                category: 'database',
                forApp: 'api',
            ),
        ];

        $result = $this->callEnrichDatabaseConsumers($databases, $envVariables);

        $this->assertContains('api', $result[0]->consumers);
    }
}
