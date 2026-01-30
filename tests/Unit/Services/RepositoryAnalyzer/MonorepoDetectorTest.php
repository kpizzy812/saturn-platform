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
}
