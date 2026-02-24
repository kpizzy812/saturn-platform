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
        $this->detector = new AppDetector;
        $this->tempDir = sys_get_temp_dir().'/test-repo-'.uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->tempDir));
        parent::tearDown();
    }

    public function test_detects_nestjs(): void
    {
        file_put_contents($this->tempDir.'/package.json', json_encode([
            'dependencies' => ['@nestjs/core' => '^10.0.0'],
        ]));

        $apps = $this->detector->detectSingleApp($this->tempDir);

        $this->assertCount(1, $apps);
        $this->assertEquals('nestjs', $apps[0]->framework);
        $this->assertEquals('nixpacks', $apps[0]->buildPack);
        $this->assertEquals(3000, $apps[0]->defaultPort);
        $this->assertEquals('backend', $apps[0]->type);
    }

    public function test_detects_nextjs(): void
    {
        file_put_contents($this->tempDir.'/package.json', json_encode([
            'dependencies' => ['next' => '^14.0.0', 'react' => '^18.0.0'],
        ]));

        $apps = $this->detector->detectSingleApp($this->tempDir);

        $this->assertCount(1, $apps);
        $this->assertEquals('nextjs', $apps[0]->framework);
        $this->assertEquals('fullstack', $apps[0]->type);
    }

    public function test_detects_fastapi(): void
    {
        file_put_contents($this->tempDir.'/requirements.txt', "fastapi==0.100.0\nuvicorn==0.23.0");

        $apps = $this->detector->detectSingleApp($this->tempDir);

        $this->assertCount(1, $apps);
        $this->assertEquals('fastapi', $apps[0]->framework);
        $this->assertEquals(8000, $apps[0]->defaultPort);
    }

    public function test_detects_vite_react_as_static(): void
    {
        file_put_contents($this->tempDir.'/package.json', json_encode([
            'dependencies' => ['react' => '^18.0.0'],
            'devDependencies' => ['vite' => '^5.0.0'],
        ]));

        $apps = $this->detector->detectSingleApp($this->tempDir);

        $this->assertCount(1, $apps);
        $this->assertEquals('vite-react', $apps[0]->framework);
        $this->assertEquals('static', $apps[0]->buildPack);
        $this->assertEquals('dist', $apps[0]->publishDirectory);
        $this->assertEquals('frontend', $apps[0]->type);
    }

    public function test_detects_dockerfile(): void
    {
        file_put_contents($this->tempDir.'/Dockerfile', "FROM node:20\nEXPOSE 8080\nCMD [\"node\", \"index.js\"]");

        $apps = $this->detector->detectSingleApp($this->tempDir);

        $this->assertCount(1, $apps);
        $this->assertEquals('dockerfile', $apps[0]->framework);
        $this->assertEquals('dockerfile', $apps[0]->buildPack);
        $this->assertEquals(8080, $apps[0]->defaultPort);
    }

    public function test_detects_multiple_apps_in_monorepo(): void
    {
        // Create monorepo structure
        mkdir($this->tempDir.'/apps');
        mkdir($this->tempDir.'/apps/api');
        mkdir($this->tempDir.'/apps/web');

        file_put_contents($this->tempDir.'/apps/api/package.json', json_encode([
            'name' => 'api',
            'dependencies' => ['@nestjs/core' => '^10.0.0'],
        ]));

        file_put_contents($this->tempDir.'/apps/web/package.json', json_encode([
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

        $appNames = array_map(fn ($a) => $a->name, $apps);
        $this->assertContains('api', $appNames);
        $this->assertContains('web', $appNames);
    }

    public function test_empty_package_json_returns_no_apps(): void
    {
        file_put_contents($this->tempDir.'/package.json', '{}');

        $apps = $this->detector->detectSingleApp($this->tempDir);

        $this->assertEmpty($apps);
    }

    public function test_vite_react_requires_both_deps(): void
    {
        // Only vite, no react - should not detect as vite-react
        file_put_contents($this->tempDir.'/package.json', json_encode([
            'devDependencies' => ['vite' => '^5.0.0'],
        ]));

        $apps = $this->detector->detectSingleApp($this->tempDir);

        // Should not match vite-react (requires both vite AND react)
        $this->assertEmpty($apps);
    }

    public function test_excludes_express_when_nestjs_present(): void
    {
        file_put_contents($this->tempDir.'/package.json', json_encode([
            'dependencies' => [
                '@nestjs/core' => '^10.0.0',
                'express' => '^4.0.0',  // Used internally by NestJS
            ],
        ]));

        $apps = $this->detector->detectSingleApp($this->tempDir);

        $this->assertCount(1, $apps);
        $this->assertEquals('nestjs', $apps[0]->framework);
    }

    public function test_detects_laravel(): void
    {
        file_put_contents($this->tempDir.'/composer.json', json_encode([
            'require' => ['laravel/framework' => '^11.0'],
        ]));

        $apps = $this->detector->detectSingleApp($this->tempDir);

        $this->assertCount(1, $apps);
        $this->assertEquals('laravel', $apps[0]->framework);
        $this->assertEquals('backend', $apps[0]->type);
    }

    public function test_detects_go_fiber(): void
    {
        file_put_contents($this->tempDir.'/go.mod', "module myapp\n\ngo 1.21\n\nrequire github.com/gofiber/fiber/v2 v2.52.0");

        $apps = $this->detector->detectSingleApp($this->tempDir);

        $this->assertCount(1, $apps);
        $this->assertEquals('go-fiber', $apps[0]->framework);
        $this->assertEquals(3000, $apps[0]->defaultPort);
    }

    // ── Worker Mode Detection ──────────────────────────────────────

    public function test_dockerfile_without_expose_is_worker(): void
    {
        file_put_contents($this->tempDir.'/Dockerfile', "FROM python:3.12\nCOPY . .\nCMD [\"python\", \"bot.py\"]");

        $apps = $this->detector->detectSingleApp($this->tempDir);

        $this->assertCount(1, $apps);
        $this->assertEquals('dockerfile', $apps[0]->buildPack);
        $this->assertEquals(0, $apps[0]->defaultPort);
        $this->assertEquals('worker', $apps[0]->applicationMode);
    }

    public function test_dockerfile_with_expose_is_web(): void
    {
        file_put_contents($this->tempDir.'/Dockerfile', "FROM node:20\nEXPOSE 3000\nCMD [\"node\", \"server.js\"]");

        $apps = $this->detector->detectSingleApp($this->tempDir);

        $this->assertCount(1, $apps);
        $this->assertEquals(3000, $apps[0]->defaultPort);
        $this->assertEquals('web', $apps[0]->applicationMode);
    }

    public function test_docker_compose_without_ports_is_worker(): void
    {
        file_put_contents($this->tempDir.'/docker-compose.yml', <<<'YAML'
version: '3.8'
services:
  bot:
    build: .
    environment:
      - BOT_TOKEN=xxx
  redis:
    image: redis:7
YAML);

        $apps = $this->detector->detectSingleApp($this->tempDir);

        $this->assertCount(1, $apps);
        $this->assertEquals('docker-compose', $apps[0]->buildPack);
        $this->assertEquals(0, $apps[0]->defaultPort);
        $this->assertEquals('worker', $apps[0]->applicationMode);
    }

    public function test_docker_compose_with_ports_is_web(): void
    {
        file_put_contents($this->tempDir.'/docker-compose.yml', <<<'YAML'
version: '3.8'
services:
  web:
    build: .
    ports:
      - "8080:80"
  redis:
    image: redis:7
YAML);

        $apps = $this->detector->detectSingleApp($this->tempDir);

        $this->assertCount(1, $apps);
        $this->assertEquals('docker-compose', $apps[0]->buildPack);
        $this->assertEquals(80, $apps[0]->defaultPort);
        $this->assertEquals('web', $apps[0]->applicationMode);
    }

    public function test_framework_apps_default_to_web_mode(): void
    {
        file_put_contents($this->tempDir.'/package.json', json_encode([
            'dependencies' => ['@nestjs/core' => '^10.0.0'],
        ]));

        $apps = $this->detector->detectSingleApp($this->tempDir);

        $this->assertCount(1, $apps);
        $this->assertEquals('web', $apps[0]->applicationMode);
    }

    public function test_detected_app_to_array_includes_application_mode(): void
    {
        $app = new \App\Services\RepositoryAnalyzer\DTOs\DetectedApp(
            name: 'bot',
            path: '.',
            framework: 'dockerfile',
            buildPack: 'dockerfile',
            defaultPort: 0,
            applicationMode: 'worker',
        );

        $array = $app->toArray();

        $this->assertArrayHasKey('application_mode', $array);
        $this->assertEquals('worker', $array['application_mode']);
    }

    public function test_detected_app_with_application_mode(): void
    {
        $app = new \App\Services\RepositoryAnalyzer\DTOs\DetectedApp(
            name: 'api',
            path: '.',
            framework: 'nestjs',
            buildPack: 'nixpacks',
            defaultPort: 3000,
        );

        $this->assertEquals('web', $app->applicationMode);

        $worker = $app->withApplicationMode('worker');
        $this->assertEquals('worker', $worker->applicationMode);
        $this->assertEquals('api', $worker->name); // Other fields preserved
    }
}
