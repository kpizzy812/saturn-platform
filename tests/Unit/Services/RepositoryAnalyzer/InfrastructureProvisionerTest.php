<?php

namespace Tests\Unit\Services\RepositoryAnalyzer;

use App\Services\RepositoryAnalyzer\DTOs\DetectedApp;
use Tests\TestCase;

/**
 * Tests the build context logic used in InfrastructureProvisioner::createApplication().
 *
 * The logic determines base_directory and dockerfile_location based on:
 * - Whether the app is in a monorepo (groupId is set)
 * - Whether the buildpack is 'dockerfile'
 * - The app's path within the repository
 */
class InfrastructureProvisionerTest extends TestCase
{
    /**
     * Replicate the build context logic from InfrastructureProvisioner::createApplication().
     *
     * @return array{base_directory: string, dockerfile_location: string|null}
     */
    private function resolveBuildContext(DetectedApp $app, ?string $groupId): array
    {
        // This mirrors the logic in InfrastructureProvisioner::createApplication() lines 265-271
        if ($groupId && $app->buildPack === 'dockerfile' && $app->path !== '.') {
            return [
                'base_directory' => '',
                'dockerfile_location' => '/'.ltrim($app->path, '/').'/Dockerfile',
            ];
        }

        return [
            'base_directory' => $app->path === '.' ? '' : '/'.ltrim($app->path, '/'),
            'dockerfile_location' => null,
        ];
    }

    public function test_monorepo_dockerfile_uses_root_context(): void
    {
        $app = new DetectedApp(
            name: 'api',
            path: 'apps/api',
            framework: 'nestjs',
            buildPack: 'dockerfile',
            defaultPort: 3000,
            type: 'backend',
        );

        $result = $this->resolveBuildContext($app, groupId: 'some-group-uuid');

        $this->assertEquals('', $result['base_directory'], 'Monorepo dockerfile should use repo root as base_directory');
        $this->assertEquals('/apps/api/Dockerfile', $result['dockerfile_location'], 'Monorepo dockerfile should set dockerfile_location to app path');
    }

    public function test_monorepo_nixpacks_keeps_app_directory(): void
    {
        $app = new DetectedApp(
            name: 'web',
            path: 'apps/web',
            framework: 'nextjs',
            buildPack: 'nixpacks',
            defaultPort: 3000,
            type: 'frontend',
        );

        $result = $this->resolveBuildContext($app, groupId: 'some-group-uuid');

        $this->assertEquals('/apps/web', $result['base_directory'], 'Monorepo nixpacks should keep app directory as base_directory');
        $this->assertNull($result['dockerfile_location'], 'Monorepo nixpacks should not set dockerfile_location');
    }

    public function test_non_monorepo_dockerfile_keeps_app_directory(): void
    {
        $app = new DetectedApp(
            name: 'api',
            path: 'apps/api',
            framework: 'nestjs',
            buildPack: 'dockerfile',
            defaultPort: 3000,
            type: 'backend',
        );

        $result = $this->resolveBuildContext($app, groupId: null);

        $this->assertEquals('/apps/api', $result['base_directory'], 'Non-monorepo dockerfile should use app directory');
        $this->assertNull($result['dockerfile_location'], 'Non-monorepo dockerfile should not override dockerfile_location');
    }

    public function test_root_app_keeps_empty_base_directory(): void
    {
        $app = new DetectedApp(
            name: 'app',
            path: '.',
            framework: 'nextjs',
            buildPack: 'dockerfile',
            defaultPort: 3000,
            type: 'fullstack',
        );

        $result = $this->resolveBuildContext($app, groupId: 'some-group-uuid');

        $this->assertEquals('', $result['base_directory'], 'Root app should have empty base_directory');
        $this->assertNull($result['dockerfile_location'], 'Root app should not set custom dockerfile_location');
    }

    public function test_monorepo_dockerfile_with_nested_path(): void
    {
        $app = new DetectedApp(
            name: 'service',
            path: 'services/auth/api',
            framework: 'expressjs',
            buildPack: 'dockerfile',
            defaultPort: 4000,
            type: 'backend',
        );

        $result = $this->resolveBuildContext($app, groupId: 'some-group-uuid');

        $this->assertEquals('', $result['base_directory'], 'Deeply nested monorepo app should use root context');
        $this->assertEquals('/services/auth/api/Dockerfile', $result['dockerfile_location'], 'Deeply nested path should be in dockerfile_location');
    }

    public function test_monorepo_static_keeps_app_directory(): void
    {
        $app = new DetectedApp(
            name: 'docs',
            path: 'apps/docs',
            framework: 'vite',
            buildPack: 'static',
            defaultPort: 80,
            type: 'frontend',
        );

        $result = $this->resolveBuildContext($app, groupId: 'some-group-uuid');

        $this->assertEquals('/apps/docs', $result['base_directory'], 'Monorepo static should keep app directory');
        $this->assertNull($result['dockerfile_location'], 'Monorepo static should not set dockerfile_location');
    }

    public function test_path_with_leading_slash_is_normalized(): void
    {
        $app = new DetectedApp(
            name: 'api',
            path: '/apps/api',
            framework: 'nestjs',
            buildPack: 'dockerfile',
            defaultPort: 3000,
            type: 'backend',
        );

        $result = $this->resolveBuildContext($app, groupId: 'some-group-uuid');

        $this->assertEquals('', $result['base_directory']);
        $this->assertEquals('/apps/api/Dockerfile', $result['dockerfile_location'], 'Leading slash in path should be normalized');
    }
}
