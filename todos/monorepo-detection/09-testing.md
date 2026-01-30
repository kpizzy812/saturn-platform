# Testing

## Unit Tests

**Директория:** `tests/Unit/Services/RepositoryAnalyzer/`

---

## MonorepoDetectorTest

**Файл:** `tests/Unit/Services/RepositoryAnalyzer/MonorepoDetectorTest.php`

```php
<?php

namespace Tests\Unit\Services\RepositoryAnalyzer;

use App\Services\RepositoryAnalyzer\Detectors\MonorepoDetector;
use Tests\TestCase;

class MonorepoDetectorTest extends TestCase
{
    private MonorepoDetector $detector;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new MonorepoDetector();
        $this->tempDir = sys_get_temp_dir() . '/test-repo-' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tempDir));
        parent::tearDown();
    }

    public function test_detects_turborepo(): void
    {
        file_put_contents($this->tempDir . '/turbo.json', '{}');
        file_put_contents($this->tempDir . '/package.json', json_encode([
            'workspaces' => ['apps/*', 'packages/*'],
        ]));

        $result = $this->detector->detect($this->tempDir);

        $this->assertTrue($result->isMonorepo);
        $this->assertEquals('turborepo', $result->type);
        $this->assertContains('apps/*', $result->workspacePaths);
    }

    public function test_detects_pnpm_workspace(): void
    {
        file_put_contents($this->tempDir . '/pnpm-workspace.yaml', "packages:\n  - apps/*\n  - packages/*");

        $result = $this->detector->detect($this->tempDir);

        $this->assertTrue($result->isMonorepo);
        $this->assertEquals('pnpm', $result->type);
    }

    public function test_detects_lerna(): void
    {
        file_put_contents($this->tempDir . '/lerna.json', json_encode([
            'version' => 'independent',
            'packages' => ['packages/*'],
        ]));

        $result = $this->detector->detect($this->tempDir);

        $this->assertTrue($result->isMonorepo);
        $this->assertEquals('lerna', $result->type);
    }

    public function test_detects_nx(): void
    {
        file_put_contents($this->tempDir . '/nx.json', '{}');
        mkdir($this->tempDir . '/apps');
        mkdir($this->tempDir . '/libs');

        $result = $this->detector->detect($this->tempDir);

        $this->assertTrue($result->isMonorepo);
        $this->assertEquals('nx', $result->type);
        $this->assertContains('apps/*', $result->workspacePaths);
        $this->assertContains('libs/*', $result->workspacePaths);
    }

    public function test_detects_npm_workspaces(): void
    {
        file_put_contents($this->tempDir . '/package.json', json_encode([
            'name' => 'my-monorepo',
            'workspaces' => ['packages/*'],
        ]));

        $result = $this->detector->detect($this->tempDir);

        $this->assertTrue($result->isMonorepo);
        $this->assertEquals('npm-workspaces', $result->type);
    }

    public function test_detects_single_repo(): void
    {
        file_put_contents($this->tempDir . '/package.json', json_encode([
            'name' => 'my-app',
            'dependencies' => ['express' => '^4.0.0'],
        ]));

        $result = $this->detector->detect($this->tempDir);

        $this->assertFalse($result->isMonorepo);
        $this->assertNull($result->type);
    }
}
```

---

## AppDetectorTest

**Файл:** `tests/Unit/Services/RepositoryAnalyzer/AppDetectorTest.php`

```php
<?php

namespace Tests\Unit\Services\RepositoryAnalyzer;

use App\Services\RepositoryAnalyzer\Detectors\AppDetector;
use App\Services\RepositoryAnalyzer\DTOs\MonorepoInfo;
use Tests\TestCase;

class AppDetectorTest extends TestCase
{
    private AppDetector $detector;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new AppDetector();
        $this->tempDir = sys_get_temp_dir() . '/test-repo-' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tempDir));
        parent::tearDown();
    }

    public function test_detects_nestjs(): void
    {
        file_put_contents($this->tempDir . '/package.json', json_encode([
            'dependencies' => ['@nestjs/core' => '^10.0.0'],
        ]));

        $apps = $this->detector->detectSingleApp($this->tempDir);

        $this->assertCount(1, $apps);
        $this->assertEquals('nestjs', $apps[0]->framework);
        $this->assertEquals('nixpacks', $apps[0]->buildPack);
        $this->assertEquals(3000, $apps[0]->defaultPort);
    }

    public function test_detects_nextjs(): void
    {
        file_put_contents($this->tempDir . '/package.json', json_encode([
            'dependencies' => ['next' => '^14.0.0', 'react' => '^18.0.0'],
        ]));

        $apps = $this->detector->detectSingleApp($this->tempDir);

        $this->assertCount(1, $apps);
        $this->assertEquals('nextjs', $apps[0]->framework);
    }

    public function test_detects_fastapi(): void
    {
        file_put_contents($this->tempDir . '/requirements.txt', "fastapi==0.100.0\nuvicorn==0.23.0");

        $apps = $this->detector->detectSingleApp($this->tempDir);

        $this->assertCount(1, $apps);
        $this->assertEquals('fastapi', $apps[0]->framework);
        $this->assertEquals(8000, $apps[0]->defaultPort);
    }

    public function test_detects_vite_react_as_static(): void
    {
        file_put_contents($this->tempDir . '/package.json', json_encode([
            'dependencies' => ['react' => '^18.0.0'],
            'devDependencies' => ['vite' => '^5.0.0'],
        ]));

        $apps = $this->detector->detectSingleApp($this->tempDir);

        $this->assertCount(1, $apps);
        $this->assertEquals('vite-react', $apps[0]->framework);
        $this->assertEquals('static', $apps[0]->buildPack);
        $this->assertEquals('dist', $apps[0]->publishDirectory);
    }

    public function test_detects_dockerfile(): void
    {
        file_put_contents($this->tempDir . '/Dockerfile', "FROM node:20\nEXPOSE 8080\nCMD [\"node\", \"index.js\"]");

        $apps = $this->detector->detectSingleApp($this->tempDir);

        $this->assertCount(1, $apps);
        $this->assertEquals('dockerfile', $apps[0]->framework);
        $this->assertEquals('dockerfile', $apps[0]->buildPack);
        $this->assertEquals(8080, $apps[0]->defaultPort);
    }

    public function test_detects_multiple_apps_in_monorepo(): void
    {
        // Create monorepo structure
        mkdir($this->tempDir . '/apps');
        mkdir($this->tempDir . '/apps/api');
        mkdir($this->tempDir . '/apps/web');

        file_put_contents($this->tempDir . '/apps/api/package.json', json_encode([
            'name' => 'api',
            'dependencies' => ['@nestjs/core' => '^10.0.0'],
        ]));

        file_put_contents($this->tempDir . '/apps/web/package.json', json_encode([
            'name' => 'web',
            'dependencies' => ['next' => '^14.0.0'],
        ]));

        $monorepo = new MonorepoInfo(
            isMonorepo: true,
            type: 'turborepo',
            workspacePaths: ['apps/*'],
        );

        $apps = $this->detector->detectFromMonorepo($this->tempDir, $monorepo);

        $this->assertCount(2, $apps);

        $appNames = array_map(fn($a) => $a->name, $apps);
        $this->assertContains('api', $appNames);
        $this->assertContains('web', $appNames);
    }
}
```

---

## DependencyAnalyzerTest

**Файл:** `tests/Unit/Services/RepositoryAnalyzer/DependencyAnalyzerTest.php`

```php
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
        $this->analyzer = new DependencyAnalyzer();
        $this->tempDir = sys_get_temp_dir() . '/test-repo-' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tempDir));
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
        file_put_contents($this->tempDir . '/package.json', json_encode([
            'dependencies' => ['pg' => '^8.0.0', 'express' => '^4.0.0'],
        ]));

        $result = $this->analyzer->analyze($this->tempDir, $this->createApp());

        $this->assertCount(1, $result->databases);
        $this->assertEquals('postgresql', $result->databases[0]->type);
        $this->assertEquals('DATABASE_URL', $result->databases[0]->envVarName);
    }

    public function test_detects_postgresql_from_prisma(): void
    {
        file_put_contents($this->tempDir . '/package.json', json_encode([
            'dependencies' => ['@prisma/client' => '^5.0.0'],
        ]));

        $result = $this->analyzer->analyze($this->tempDir, $this->createApp());

        $this->assertCount(1, $result->databases);
        $this->assertEquals('postgresql', $result->databases[0]->type);
    }

    public function test_detects_redis_from_ioredis(): void
    {
        file_put_contents($this->tempDir . '/package.json', json_encode([
            'dependencies' => ['ioredis' => '^5.0.0'],
        ]));

        $result = $this->analyzer->analyze($this->tempDir, $this->createApp());

        $this->assertCount(1, $result->databases);
        $this->assertEquals('redis', $result->databases[0]->type);
        $this->assertEquals('REDIS_URL', $result->databases[0]->envVarName);
    }

    public function test_detects_mongodb_from_mongoose(): void
    {
        file_put_contents($this->tempDir . '/package.json', json_encode([
            'dependencies' => ['mongoose' => '^7.0.0'],
        ]));

        $result = $this->analyzer->analyze($this->tempDir, $this->createApp());

        $this->assertCount(1, $result->databases);
        $this->assertEquals('mongodb', $result->databases[0]->type);
        $this->assertEquals('MONGODB_URL', $result->databases[0]->envVarName);
    }

    public function test_detects_multiple_databases(): void
    {
        file_put_contents($this->tempDir . '/package.json', json_encode([
            'dependencies' => [
                'pg' => '^8.0.0',
                'ioredis' => '^5.0.0',
            ],
        ]));

        $result = $this->analyzer->analyze($this->tempDir, $this->createApp());

        $this->assertCount(2, $result->databases);

        $types = array_map(fn($d) => $d->type, $result->databases);
        $this->assertContains('postgresql', $types);
        $this->assertContains('redis', $types);
    }

    public function test_detects_s3_service(): void
    {
        file_put_contents($this->tempDir . '/package.json', json_encode([
            'dependencies' => ['@aws-sdk/client-s3' => '^3.0.0'],
        ]));

        $result = $this->analyzer->analyze($this->tempDir, $this->createApp());

        $this->assertCount(1, $result->services);
        $this->assertEquals('s3', $result->services[0]->type);
        $this->assertContains('AWS_ACCESS_KEY_ID', $result->services[0]->requiredEnvVars);
    }

    public function test_parses_env_example(): void
    {
        file_put_contents($this->tempDir . '/package.json', '{}');
        file_put_contents($this->tempDir . '/.env.example', <<<ENV
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
        $this->assertTrue($envVars->contains(fn($v) => $v->key === 'DATABASE_URL' && $v->isRequired));
        $this->assertTrue($envVars->contains(fn($v) => $v->key === 'STRIPE_API_KEY' && $v->isRequired));
        $this->assertTrue($envVars->contains(fn($v) => $v->key === 'SENDGRID_API_KEY' && $v->isRequired));

        // Non-required (has value)
        $this->assertTrue($envVars->contains(fn($v) => $v->key === 'NODE_ENV' && !$v->isRequired));
        $this->assertTrue($envVars->contains(fn($v) => $v->key === 'PORT' && !$v->isRequired));
    }

    public function test_categorizes_env_variables(): void
    {
        file_put_contents($this->tempDir . '/package.json', '{}');
        file_put_contents($this->tempDir . '/.env.example', <<<ENV
DATABASE_URL=
REDIS_URL=
AWS_ACCESS_KEY_ID=
SMTP_HOST=
JWT_SECRET=
ENV);

        $result = $this->analyzer->analyze($this->tempDir, $this->createApp());

        $envVars = collect($result->envVariables);

        $this->assertTrue($envVars->contains(fn($v) => $v->key === 'DATABASE_URL' && $v->category === 'database'));
        $this->assertTrue($envVars->contains(fn($v) => $v->key === 'REDIS_URL' && $v->category === 'cache'));
        $this->assertTrue($envVars->contains(fn($v) => $v->key === 'AWS_ACCESS_KEY_ID' && $v->category === 'storage'));
        $this->assertTrue($envVars->contains(fn($v) => $v->key === 'SMTP_HOST' && $v->category === 'email'));
        $this->assertTrue($envVars->contains(fn($v) => $v->key === 'JWT_SECRET' && $v->category === 'secrets'));
    }

    public function test_detects_python_dependencies(): void
    {
        file_put_contents($this->tempDir . '/requirements.txt', <<<REQS
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

        $types = array_map(fn($d) => $d->type, $result->databases);
        $this->assertContains('postgresql', $types);
        $this->assertContains('redis', $types);
    }
}
```

---

---

## Дополнительные тесты для edge cases

**Файл:** `tests/Unit/Services/RepositoryAnalyzer/EdgeCasesTest.php`

```php
<?php

namespace Tests\Unit\Services\RepositoryAnalyzer;

use App\Services\RepositoryAnalyzer\Detectors\MonorepoDetector;
use App\Services\RepositoryAnalyzer\Detectors\AppDetector;
use Tests\TestCase;

class EdgeCasesTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/test-repo-' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tempDir));
        parent::tearDown();
    }

    public function test_handles_empty_package_json(): void
    {
        file_put_contents($this->tempDir . '/package.json', '{}');

        $detector = new AppDetector();
        $apps = $detector->detectSingleApp($this->tempDir);

        $this->assertEmpty($apps);
    }

    public function test_handles_invalid_json(): void
    {
        file_put_contents($this->tempDir . '/package.json', 'not valid json');

        $detector = new AppDetector();
        $apps = $detector->detectSingleApp($this->tempDir);

        $this->assertEmpty($apps);
    }

    public function test_handles_invalid_yaml(): void
    {
        file_put_contents($this->tempDir . '/pnpm-workspace.yaml', "invalid:\n  - yaml\n    content");

        $detector = new MonorepoDetector();
        $result = $detector->detect($this->tempDir);

        // Should gracefully handle and return not monorepo
        $this->assertFalse($result->isMonorepo);
    }

    public function test_detects_nx_with_project_json(): void
    {
        file_put_contents($this->tempDir . '/nx.json', json_encode([
            'projects' => [
                'api' => 'apps/api',
                'web' => 'apps/web',
            ],
        ]));

        $detector = new MonorepoDetector();
        $result = $detector->detect($this->tempDir);

        $this->assertTrue($result->isMonorepo);
        $this->assertEquals('nx', $result->type);
        $this->assertContains('apps/api', $result->workspacePaths);
        $this->assertContains('apps/web', $result->workspacePaths);
    }

    public function test_detects_yarn_berry_workspaces(): void
    {
        file_put_contents($this->tempDir . '/package.json', json_encode([
            'name' => 'my-monorepo',
            'workspaces' => [
                'packages' => ['apps/*', 'packages/*'],
                'nohoist' => ['**/react-native'],
            ],
        ]));

        $detector = new MonorepoDetector();
        $result = $detector->detect($this->tempDir);

        $this->assertTrue($result->isMonorepo);
        $this->assertContains('apps/*', $result->workspacePaths);
    }

    public function test_vite_react_requires_both_deps(): void
    {
        // Only vite, no react - should not detect as vite-react
        file_put_contents($this->tempDir . '/package.json', json_encode([
            'devDependencies' => ['vite' => '^5.0.0'],
        ]));

        $detector = new AppDetector();
        $apps = $detector->detectSingleApp($this->tempDir);

        // Should not match vite-react (requires both vite AND react)
        $this->assertEmpty($apps);
    }

    public function test_excludes_express_when_nestjs_present(): void
    {
        file_put_contents($this->tempDir . '/package.json', json_encode([
            'dependencies' => [
                '@nestjs/core' => '^10.0.0',
                'express' => '^4.0.0',  // Used internally by NestJS
            ],
        ]));

        $detector = new AppDetector();
        $apps = $detector->detectSingleApp($this->tempDir);

        $this->assertCount(1, $apps);
        $this->assertEquals('nestjs', $apps[0]->framework);
    }

    public function test_detects_app_type_correctly(): void
    {
        file_put_contents($this->tempDir . '/package.json', json_encode([
            'dependencies' => ['next' => '^14.0.0'],
        ]));

        $detector = new AppDetector();
        $apps = $detector->detectSingleApp($this->tempDir);

        $this->assertCount(1, $apps);
        $this->assertEquals('fullstack', $apps[0]->type);
    }
}
```

---

## Запуск тестов

```bash
# Все unit тесты для RepositoryAnalyzer
./vendor/bin/pest tests/Unit/Services/RepositoryAnalyzer

# Конкретный тест
./vendor/bin/pest tests/Unit/Services/RepositoryAnalyzer/MonorepoDetectorTest.php

# С coverage
./vendor/bin/pest tests/Unit/Services/RepositoryAnalyzer --coverage

# Edge cases
./vendor/bin/pest tests/Unit/Services/RepositoryAnalyzer/EdgeCasesTest.php
```
