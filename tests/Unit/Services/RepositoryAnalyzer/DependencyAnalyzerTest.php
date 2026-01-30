<?php

namespace Tests\Unit\Services\RepositoryAnalyzer;

use App\Services\RepositoryAnalyzer\Detectors\DependencyAnalyzer;
use App\Services\RepositoryAnalyzer\DTOs\DetectedApp;
use Tests\TestCase;

class DependencyAnalyzerTest extends TestCase
{
    private DependencyAnalyzer $analyzer;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new DependencyAnalyzer;
        $this->tempDir = sys_get_temp_dir().'/test-repo-'.uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->tempDir));
        parent::tearDown();
    }

    private function createApp(string $name = 'api', string $type = 'backend'): DetectedApp
    {
        return new DetectedApp(
            name: $name,
            path: '.',
            framework: 'express',
            buildPack: 'nixpacks',
            defaultPort: 3000,
            type: $type,
        );
    }

    public function test_detects_postgresql_from_pg(): void
    {
        file_put_contents($this->tempDir.'/package.json', json_encode([
            'dependencies' => ['pg' => '^8.0.0', 'express' => '^4.0.0'],
        ]));

        $result = $this->analyzer->analyze($this->tempDir, $this->createApp());

        $this->assertCount(1, $result->databases);
        $this->assertEquals('postgresql', $result->databases[0]->type);
        $this->assertEquals('DATABASE_URL', $result->databases[0]->envVarName);
    }

    public function test_detects_postgresql_from_prisma(): void
    {
        file_put_contents($this->tempDir.'/package.json', json_encode([
            'dependencies' => ['@prisma/client' => '^5.0.0'],
        ]));

        $result = $this->analyzer->analyze($this->tempDir, $this->createApp());

        $this->assertCount(1, $result->databases);
        $this->assertEquals('postgresql', $result->databases[0]->type);
    }

    public function test_detects_redis_from_ioredis(): void
    {
        file_put_contents($this->tempDir.'/package.json', json_encode([
            'dependencies' => ['ioredis' => '^5.0.0'],
        ]));

        $result = $this->analyzer->analyze($this->tempDir, $this->createApp());

        $this->assertCount(1, $result->databases);
        $this->assertEquals('redis', $result->databases[0]->type);
        $this->assertEquals('REDIS_URL', $result->databases[0]->envVarName);
    }

    public function test_detects_mongodb_from_mongoose(): void
    {
        file_put_contents($this->tempDir.'/package.json', json_encode([
            'dependencies' => ['mongoose' => '^7.0.0'],
        ]));

        $result = $this->analyzer->analyze($this->tempDir, $this->createApp());

        $this->assertCount(1, $result->databases);
        $this->assertEquals('mongodb', $result->databases[0]->type);
        $this->assertEquals('MONGODB_URL', $result->databases[0]->envVarName);
    }

    public function test_detects_multiple_databases(): void
    {
        file_put_contents($this->tempDir.'/package.json', json_encode([
            'dependencies' => [
                'pg' => '^8.0.0',
                'ioredis' => '^5.0.0',
            ],
        ]));

        $result = $this->analyzer->analyze($this->tempDir, $this->createApp());

        $this->assertCount(2, $result->databases);

        $types = array_map(fn ($d) => $d->type, $result->databases);
        $this->assertContains('postgresql', $types);
        $this->assertContains('redis', $types);
    }

    public function test_detects_s3_service(): void
    {
        file_put_contents($this->tempDir.'/package.json', json_encode([
            'dependencies' => ['@aws-sdk/client-s3' => '^3.0.0'],
        ]));

        $result = $this->analyzer->analyze($this->tempDir, $this->createApp());

        $this->assertCount(1, $result->services);
        $this->assertEquals('s3', $result->services[0]->type);
        $this->assertContains('AWS_ACCESS_KEY_ID', $result->services[0]->requiredEnvVars);
    }

    public function test_parses_env_example(): void
    {
        file_put_contents($this->tempDir.'/package.json', '{}');
        file_put_contents($this->tempDir.'/.env.example', <<<'ENV'
# Database
DATABASE_URL=

# API Keys
STRIPE_API_KEY=your_stripe_key
SENDGRID_API_KEY=

# App Config
NODE_ENV=development
PORT=3000
ENV);

        $result = $this->analyzer->analyze($this->tempDir, $this->createApp());

        $envVars = collect($result->envVariables);

        // Required variables (empty or placeholder)
        $this->assertTrue($envVars->contains(fn ($v) => $v->key === 'DATABASE_URL' && $v->isRequired));
        $this->assertTrue($envVars->contains(fn ($v) => $v->key === 'STRIPE_API_KEY' && $v->isRequired));
        $this->assertTrue($envVars->contains(fn ($v) => $v->key === 'SENDGRID_API_KEY' && $v->isRequired));

        // Non-required (has value)
        $this->assertTrue($envVars->contains(fn ($v) => $v->key === 'NODE_ENV' && ! $v->isRequired));
        $this->assertTrue($envVars->contains(fn ($v) => $v->key === 'PORT' && ! $v->isRequired));
    }

    public function test_categorizes_env_variables(): void
    {
        file_put_contents($this->tempDir.'/package.json', '{}');
        file_put_contents($this->tempDir.'/.env.example', <<<'ENV'
DATABASE_URL=
REDIS_URL=
AWS_ACCESS_KEY_ID=
SMTP_HOST=
JWT_SECRET=
ENV);

        $result = $this->analyzer->analyze($this->tempDir, $this->createApp());

        $envVars = collect($result->envVariables);

        $this->assertTrue($envVars->contains(fn ($v) => $v->key === 'DATABASE_URL' && $v->category === 'database'));
        $this->assertTrue($envVars->contains(fn ($v) => $v->key === 'REDIS_URL' && $v->category === 'cache'));
        $this->assertTrue($envVars->contains(fn ($v) => $v->key === 'AWS_ACCESS_KEY_ID' && $v->category === 'storage'));
        $this->assertTrue($envVars->contains(fn ($v) => $v->key === 'SMTP_HOST' && $v->category === 'email'));
        $this->assertTrue($envVars->contains(fn ($v) => $v->key === 'JWT_SECRET' && $v->category === 'secrets'));
    }

    public function test_detects_python_dependencies(): void
    {
        file_put_contents($this->tempDir.'/requirements.txt', <<<'REQS'
fastapi==0.100.0
psycopg2-binary==2.9.0
redis==4.0.0
REQS);

        $app = new DetectedApp(
            name: 'api',
            path: '.',
            framework: 'fastapi',
            buildPack: 'nixpacks',
            defaultPort: 8000,
        );

        $result = $this->analyzer->analyze($this->tempDir, $app);

        $this->assertCount(2, $result->databases);

        $types = array_map(fn ($d) => $d->type, $result->databases);
        $this->assertContains('postgresql', $types);
        $this->assertContains('redis', $types);
    }

    public function test_detects_php_composer_dependencies(): void
    {
        file_put_contents($this->tempDir.'/composer.json', json_encode([
            'require' => [
                'laravel/framework' => '^11.0',
                'predis/predis' => '^2.0',
            ],
        ]));

        $app = new DetectedApp(
            name: 'api',
            path: '.',
            framework: 'laravel',
            buildPack: 'nixpacks',
            defaultPort: 8000,
        );

        $result = $this->analyzer->analyze($this->tempDir, $app);

        $this->assertCount(1, $result->databases);
        $this->assertEquals('redis', $result->databases[0]->type);
    }
}
