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
        $this->detector = new MonorepoDetector;
        $this->tempDir = sys_get_temp_dir().'/test-repo-'.uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->tempDir));
        parent::tearDown();
    }

    public function test_detects_turborepo(): void
    {
        file_put_contents($this->tempDir.'/turbo.json', '{}');
        file_put_contents($this->tempDir.'/package.json', json_encode([
            'workspaces' => ['apps/*', 'packages/*'],
        ]));

        $result = $this->detector->detect($this->tempDir);

        $this->assertTrue($result->isMonorepo);
        $this->assertEquals('turborepo', $result->type);
        $this->assertContains('apps/*', $result->workspacePaths);
    }

    public function test_detects_pnpm_workspace(): void
    {
        file_put_contents($this->tempDir.'/pnpm-workspace.yaml', "packages:\n  - apps/*\n  - packages/*");

        $result = $this->detector->detect($this->tempDir);

        $this->assertTrue($result->isMonorepo);
        $this->assertEquals('pnpm', $result->type);
    }

    public function test_detects_lerna(): void
    {
        file_put_contents($this->tempDir.'/lerna.json', json_encode([
            'version' => 'independent',
            'packages' => ['packages/*'],
        ]));

        $result = $this->detector->detect($this->tempDir);

        $this->assertTrue($result->isMonorepo);
        $this->assertEquals('lerna', $result->type);
    }

    public function test_detects_nx(): void
    {
        file_put_contents($this->tempDir.'/nx.json', '{}');
        mkdir($this->tempDir.'/apps');
        mkdir($this->tempDir.'/libs');

        $result = $this->detector->detect($this->tempDir);

        $this->assertTrue($result->isMonorepo);
        $this->assertEquals('nx', $result->type);
        $this->assertContains('apps/*', $result->workspacePaths);
        $this->assertContains('libs/*', $result->workspacePaths);
    }

    public function test_detects_npm_workspaces(): void
    {
        file_put_contents($this->tempDir.'/package.json', json_encode([
            'name' => 'my-monorepo',
            'workspaces' => ['packages/*'],
        ]));

        $result = $this->detector->detect($this->tempDir);

        $this->assertTrue($result->isMonorepo);
        $this->assertEquals('npm-workspaces', $result->type);
    }

    public function test_detects_single_repo(): void
    {
        file_put_contents($this->tempDir.'/package.json', json_encode([
            'name' => 'my-app',
            'dependencies' => ['express' => '^4.0.0'],
        ]));

        $result = $this->detector->detect($this->tempDir);

        $this->assertFalse($result->isMonorepo);
        $this->assertNull($result->type);
    }

    public function test_handles_empty_package_json(): void
    {
        file_put_contents($this->tempDir.'/package.json', '{}');

        $result = $this->detector->detect($this->tempDir);

        $this->assertFalse($result->isMonorepo);
    }

    public function test_handles_invalid_json(): void
    {
        file_put_contents($this->tempDir.'/package.json', 'not valid json');

        $result = $this->detector->detect($this->tempDir);

        $this->assertFalse($result->isMonorepo);
    }

    public function test_detects_nx_with_project_json(): void
    {
        file_put_contents($this->tempDir.'/nx.json', json_encode([
            'projects' => [
                'api' => 'apps/api',
                'web' => 'apps/web',
            ],
        ]));

        $result = $this->detector->detect($this->tempDir);

        $this->assertTrue($result->isMonorepo);
        $this->assertEquals('nx', $result->type);
        $this->assertContains('apps/api', $result->workspacePaths);
        $this->assertContains('apps/web', $result->workspacePaths);
    }

    public function test_detects_yarn_berry_workspaces(): void
    {
        file_put_contents($this->tempDir.'/package.json', json_encode([
            'name' => 'my-monorepo',
            'workspaces' => [
                'packages' => ['apps/*', 'packages/*'],
                'nohoist' => ['**/react-native'],
            ],
        ]));

        $result = $this->detector->detect($this->tempDir);

        $this->assertTrue($result->isMonorepo);
        $this->assertContains('apps/*', $result->workspacePaths);
    }

    public function test_detects_simple_monorepo_without_markers(): void
    {
        // Create structure like pixelpets: /backend, /frontend, /admin-panel
        mkdir($this->tempDir.'/backend');
        file_put_contents($this->tempDir.'/backend/requirements.txt', 'fastapi==0.100.0');

        mkdir($this->tempDir.'/frontend');
        file_put_contents($this->tempDir.'/frontend/package.json', json_encode([
            'name' => 'frontend',
            'dependencies' => ['next' => '14.0.0'],
        ]));

        mkdir($this->tempDir.'/admin-panel');
        file_put_contents($this->tempDir.'/admin-panel/package.json', json_encode([
            'name' => 'admin',
            'dependencies' => ['next' => '14.0.0'],
        ]));

        // Add some ignored directories that should NOT be detected as apps
        mkdir($this->tempDir.'/assets');
        mkdir($this->tempDir.'/docs');

        $result = $this->detector->detect($this->tempDir);

        $this->assertTrue($result->isMonorepo);
        $this->assertEquals('simple', $result->type);
        $this->assertCount(3, $result->workspacePaths);
        $this->assertContains('backend', $result->workspacePaths);
        $this->assertContains('frontend', $result->workspacePaths);
        $this->assertContains('admin-panel', $result->workspacePaths);
    }

    public function test_simple_monorepo_requires_two_apps(): void
    {
        // Only one app directory - should not be detected as monorepo
        mkdir($this->tempDir.'/app');
        file_put_contents($this->tempDir.'/app/package.json', json_encode([
            'name' => 'app',
        ]));

        $result = $this->detector->detect($this->tempDir);

        $this->assertFalse($result->isMonorepo);
    }

    public function test_simple_monorepo_ignores_hidden_directories(): void
    {
        // Create hidden directories with markers - should be ignored
        mkdir($this->tempDir.'/.hidden');
        file_put_contents($this->tempDir.'/.hidden/package.json', '{}');

        mkdir($this->tempDir.'/app1');
        file_put_contents($this->tempDir.'/app1/package.json', '{}');

        mkdir($this->tempDir.'/app2');
        file_put_contents($this->tempDir.'/app2/package.json', '{}');

        $result = $this->detector->detect($this->tempDir);

        $this->assertTrue($result->isMonorepo);
        $this->assertNotContains('.hidden', $result->workspacePaths);
    }

    public function test_simple_monorepo_detects_dockerfile_apps(): void
    {
        mkdir($this->tempDir.'/api');
        file_put_contents($this->tempDir.'/api/Dockerfile', 'FROM node:18');

        mkdir($this->tempDir.'/worker');
        file_put_contents($this->tempDir.'/worker/Dockerfile', 'FROM python:3.11');

        $result = $this->detector->detect($this->tempDir);

        $this->assertTrue($result->isMonorepo);
        $this->assertEquals('simple', $result->type);
        $this->assertContains('api', $result->workspacePaths);
        $this->assertContains('worker', $result->workspacePaths);
    }

    public function test_simple_monorepo_detects_nixpacks_config(): void
    {
        mkdir($this->tempDir.'/service1');
        file_put_contents($this->tempDir.'/service1/nixpacks.toml', '[phases.build]');

        mkdir($this->tempDir.'/service2');
        file_put_contents($this->tempDir.'/service2/Procfile', 'web: node server.js');

        $result = $this->detector->detect($this->tempDir);

        $this->assertTrue($result->isMonorepo);
        $this->assertEquals('simple', $result->type);
        $this->assertContains('service1', $result->workspacePaths);
        $this->assertContains('service2', $result->workspacePaths);
    }
}
