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

    // ── SQLite Auto-Detection ─────────────────────────────────────

    public function test_detects_sqlite_from_better_sqlite3(): void
    {
        file_put_contents($this->tempDir.'/package.json', json_encode([
            'dependencies' => ['better-sqlite3' => '^9.0.0', 'express' => '^4.0.0'],
        ]));

        $result = $this->analyzer->analyze($this->tempDir, $this->createApp());

        $this->assertEmpty($result->databases, 'SQLite should NOT create a standalone database');
        $this->assertCount(1, $result->persistentVolumes);
        $this->assertEquals('sqlite-data', $result->persistentVolumes[0]->name);
        $this->assertEquals('/data', $result->persistentVolumes[0]->mountPath);
        $this->assertEquals('DATABASE_PATH', $result->persistentVolumes[0]->envVarName);
        $this->assertEquals('/data/db.sqlite', $result->persistentVolumes[0]->envVarValue);
        $this->assertStringContains('better-sqlite3', $result->persistentVolumes[0]->reason);
    }

    public function test_detects_sqlite_from_sqlite3_npm(): void
    {
        file_put_contents($this->tempDir.'/package.json', json_encode([
            'dependencies' => ['sqlite3' => '^5.0.0'],
        ]));

        $result = $this->analyzer->analyze($this->tempDir, $this->createApp());

        $this->assertCount(1, $result->persistentVolumes);
        $this->assertEquals('/data', $result->persistentVolumes[0]->mountPath);
    }

    public function test_detects_sqlite_from_python_aiosqlite(): void
    {
        file_put_contents($this->tempDir.'/requirements.txt', <<<'REQS'
fastapi==0.100.0
aiosqlite==0.19.0
REQS);

        $app = new DetectedApp(
            name: 'bot',
            path: '.',
            framework: 'fastapi',
            buildPack: 'nixpacks',
            defaultPort: 8000,
        );

        $result = $this->analyzer->analyze($this->tempDir, $app);

        $this->assertCount(1, $result->persistentVolumes);
        $this->assertEquals('sqlite-data', $result->persistentVolumes[0]->name);
        $this->assertStringContains('aiosqlite', $result->persistentVolumes[0]->reason);
    }

    public function test_detects_sqlite_from_go_driver(): void
    {
        file_put_contents($this->tempDir.'/go.mod', <<<'GO'
module github.com/user/bot

go 1.21

require (
    github.com/mattn/go-sqlite3 v1.14.0
)
GO);

        $app = new DetectedApp(
            name: 'bot',
            path: '.',
            framework: 'go',
            buildPack: 'nixpacks',
            defaultPort: 8080,
        );

        $result = $this->analyzer->analyze($this->tempDir, $app);

        $this->assertCount(1, $result->persistentVolumes);
        $this->assertStringContains('go-sqlite3', $result->persistentVolumes[0]->reason);
    }

    public function test_detects_sqlite_from_prisma_schema(): void
    {
        file_put_contents($this->tempDir.'/package.json', json_encode([
            'dependencies' => ['@prisma/client' => '^5.0.0'],
            'devDependencies' => ['prisma' => '^5.0.0'],
        ]));

        mkdir($this->tempDir.'/prisma');
        file_put_contents($this->tempDir.'/prisma/schema.prisma', <<<'PRISMA'
datasource db {
  provider = "sqlite"
  url      = env("DATABASE_URL")
}

generator client {
  provider = "prisma-client-js"
}

model User {
  id    Int    @id @default(autoincrement())
  name  String
}
PRISMA);

        $result = $this->analyzer->analyze($this->tempDir, $this->createApp());

        // Prisma with sqlite should create persistent volume, not standalone postgres
        $this->assertCount(1, $result->persistentVolumes);
        $this->assertStringContains('prisma:sqlite', $result->persistentVolumes[0]->reason);
    }

    public function test_sqlite_uses_laravel_specific_paths(): void
    {
        file_put_contents($this->tempDir.'/composer.json', json_encode([
            'require' => [
                'laravel/framework' => '^11.0',
                'ext-sqlite3' => '*',
            ],
        ]));

        $app = new DetectedApp(
            name: 'app',
            path: '.',
            framework: 'laravel',
            buildPack: 'nixpacks',
            defaultPort: 8000,
        );

        $result = $this->analyzer->analyze($this->tempDir, $app);

        $this->assertCount(1, $result->persistentVolumes);
        $this->assertEquals('/var/www/html/database', $result->persistentVolumes[0]->mountPath);
        $this->assertEquals('DB_DATABASE', $result->persistentVolumes[0]->envVarName);
        $this->assertEquals('/var/www/html/database/database.sqlite', $result->persistentVolumes[0]->envVarValue);
    }

    public function test_sqlite_uses_django_specific_paths(): void
    {
        file_put_contents($this->tempDir.'/requirements.txt', <<<'REQS'
Django==4.2.0
aiosqlite==0.19.0
REQS);

        $app = new DetectedApp(
            name: 'web',
            path: '.',
            framework: 'django',
            buildPack: 'nixpacks',
            defaultPort: 8000,
        );

        $result = $this->analyzer->analyze($this->tempDir, $app);

        $this->assertCount(1, $result->persistentVolumes);
        $this->assertEquals('/app/data', $result->persistentVolumes[0]->mountPath);
        $this->assertEquals('DATABASE_PATH', $result->persistentVolumes[0]->envVarName);
        $this->assertEquals('/app/data/db.sqlite3', $result->persistentVolumes[0]->envVarValue);
    }

    public function test_sqlite_and_redis_detected_together(): void
    {
        file_put_contents($this->tempDir.'/package.json', json_encode([
            'dependencies' => [
                'better-sqlite3' => '^9.0.0',
                'ioredis' => '^5.0.0',
            ],
        ]));

        $result = $this->analyzer->analyze($this->tempDir, $this->createApp());

        // Redis goes to databases (standalone container), SQLite goes to persistent volumes
        $this->assertCount(1, $result->databases);
        $this->assertEquals('redis', $result->databases[0]->type);

        $this->assertCount(1, $result->persistentVolumes);
        $this->assertEquals('sqlite-data', $result->persistentVolumes[0]->name);
    }

    public function test_no_sqlite_when_no_sqlite_deps(): void
    {
        file_put_contents($this->tempDir.'/package.json', json_encode([
            'dependencies' => ['express' => '^4.0.0', 'pg' => '^8.0.0'],
        ]));

        $result = $this->analyzer->analyze($this->tempDir, $this->createApp());

        $this->assertEmpty($result->persistentVolumes);
    }

    public function test_detects_sqlite_from_rust_rusqlite(): void
    {
        file_put_contents($this->tempDir.'/Cargo.toml', <<<'TOML'
[package]
name = "bot"
version = "0.1.0"
edition = "2021"

[dependencies]
rusqlite = { version = "0.31", features = ["bundled"] }
tokio = { version = "1", features = ["full"] }
TOML);

        $app = new DetectedApp(
            name: 'bot',
            path: '.',
            framework: 'rust',
            buildPack: 'nixpacks',
            defaultPort: 8080,
        );

        $result = $this->analyzer->analyze($this->tempDir, $app);

        $this->assertCount(1, $result->persistentVolumes);
        $this->assertStringContains('rusqlite', $result->persistentVolumes[0]->reason);
    }

    public function test_detects_sqlite_from_ruby_gem(): void
    {
        file_put_contents($this->tempDir.'/Gemfile', <<<'GEM'
source 'https://rubygems.org'

gem 'sinatra'
gem 'sqlite3'
gem 'sequel'
GEM);

        $app = new DetectedApp(
            name: 'bot',
            path: '.',
            framework: 'sinatra',
            buildPack: 'nixpacks',
            defaultPort: 4567,
        );

        $result = $this->analyzer->analyze($this->tempDir, $app);

        $this->assertCount(1, $result->persistentVolumes);
        $this->assertStringContains('sqlite3', $result->persistentVolumes[0]->reason);
    }

    /**
     * Helper: assert string contains substring
     */
    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertStringContainsString($needle, $haystack);
    }
}
