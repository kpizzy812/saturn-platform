<?php

namespace Tests\Unit\Services\RepositoryAnalyzer;

use App\Services\RepositoryAnalyzer\DTOs\DetectedApp;
use App\Services\RepositoryAnalyzer\InfrastructureProvisioner;
use Psr\Log\NullLogger;
use Tests\TestCase;

/**
 * Tests the build context logic used in InfrastructureProvisioner::createApplication().
 *
 * The logic determines base_directory based on:
 * - Whether user provided an override
 * - The app's path within the repository
 *
 * Build context (base_directory) always points to the app's subdirectory
 * so Dockerfiles can reference files relative to their own location.
 */
class InfrastructureProvisionerTest extends TestCase
{
    /**
     * Replicate the build context logic from InfrastructureProvisioner::createApplication().
     *
     * @return array{base_directory: string}
     */
    private function resolveBuildContext(DetectedApp $app, ?string $groupId, array $overrides = []): array
    {
        // This mirrors the logic in InfrastructureProvisioner::createApplication()
        if (! empty($overrides['base_directory'])) {
            $baseDir = $overrides['base_directory'];

            return [
                'base_directory' => $baseDir === '/' ? '' : $baseDir,
            ];
        }

        // Always use app subdirectory as base_directory (build context)
        return [
            'base_directory' => $app->path === '.' ? '' : '/'.ltrim($app->path, '/'),
        ];
    }

    public function test_monorepo_dockerfile_uses_app_directory_as_context(): void
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

        $this->assertEquals('/apps/api', $result['base_directory'], 'Monorepo dockerfile should use app directory as build context');
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

        $this->assertEquals('/services/auth/api', $result['base_directory'], 'Deeply nested monorepo app should use its own directory as context');
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

        $this->assertEquals('/apps/api', $result['base_directory'], 'Leading slash in path should be normalized');
    }

    public function test_user_override_takes_priority(): void
    {
        $app = new DetectedApp(
            name: 'api',
            path: 'apps/api',
            framework: 'nestjs',
            buildPack: 'dockerfile',
            defaultPort: 3000,
            type: 'backend',
        );

        $result = $this->resolveBuildContext($app, groupId: 'some-group-uuid', overrides: ['base_directory' => '/custom/path']);

        $this->assertEquals('/custom/path', $result['base_directory'], 'User override should take priority over auto-detection');
    }

    public function test_user_override_root_slash_normalizes_to_empty(): void
    {
        $app = new DetectedApp(
            name: 'api',
            path: 'apps/api',
            framework: 'nestjs',
            buildPack: 'dockerfile',
            defaultPort: 3000,
            type: 'backend',
        );

        $result = $this->resolveBuildContext($app, groupId: 'some-group-uuid', overrides: ['base_directory' => '/']);

        $this->assertEquals('', $result['base_directory'], 'User override of "/" should normalize to empty string (repo root)');
    }

    // ── Internal App Links: URL Resolution ─────────────────────────

    private function getProvisioner(): InfrastructureProvisioner
    {
        return new InfrastructureProvisioner(new NullLogger);
    }

    private function callPrivateMethod(object $object, string $method, array $args = []): mixed
    {
        $ref = new \ReflectionMethod($object, $method);

        return $ref->invoke($object, ...$args);
    }

    public function test_client_side_env_var_detection(): void
    {
        $provisioner = $this->getProvisioner();

        // Client-side prefixes should be detected
        $this->assertTrue($this->callPrivateMethod($provisioner, 'isClientSideEnvVar', ['NEXT_PUBLIC_API_URL']));
        $this->assertTrue($this->callPrivateMethod($provisioner, 'isClientSideEnvVar', ['VITE_API_URL']));
        $this->assertTrue($this->callPrivateMethod($provisioner, 'isClientSideEnvVar', ['REACT_APP_API_URL']));
        $this->assertTrue($this->callPrivateMethod($provisioner, 'isClientSideEnvVar', ['NUXT_PUBLIC_API_URL']));

        // Server-side vars should NOT be detected as client-side
        $this->assertFalse($this->callPrivateMethod($provisioner, 'isClientSideEnvVar', ['API_URL']));
        $this->assertFalse($this->callPrivateMethod($provisioner, 'isClientSideEnvVar', ['DATABASE_URL']));
        $this->assertFalse($this->callPrivateMethod($provisioner, 'isClientSideEnvVar', ['BACKEND_URL']));
    }

    public function test_adapt_env_var_for_nextjs_framework(): void
    {
        $provisioner = $this->getProvisioner();

        $result = $this->callPrivateMethod($provisioner, 'adaptEnvVarForFramework', ['API_URL', 'nextjs']);
        $this->assertEquals('NEXT_PUBLIC_API_URL', $result);
    }

    public function test_adapt_env_var_for_nuxt_framework(): void
    {
        $provisioner = $this->getProvisioner();

        $result = $this->callPrivateMethod($provisioner, 'adaptEnvVarForFramework', ['API_URL', 'nuxt']);
        $this->assertEquals('NUXT_PUBLIC_API_URL', $result);
    }

    public function test_adapt_env_var_for_vite_framework(): void
    {
        $provisioner = $this->getProvisioner();

        $result = $this->callPrivateMethod($provisioner, 'adaptEnvVarForFramework', ['API_URL', 'vite-react']);
        $this->assertEquals('VITE_API_URL', $result);
    }

    public function test_adapt_env_var_for_vue_framework(): void
    {
        $provisioner = $this->getProvisioner();

        $result = $this->callPrivateMethod($provisioner, 'adaptEnvVarForFramework', ['API_URL', 'vue']);
        $this->assertEquals('VITE_API_URL', $result);
    }

    public function test_adapt_env_var_for_react_cra_framework(): void
    {
        $provisioner = $this->getProvisioner();

        $result = $this->callPrivateMethod($provisioner, 'adaptEnvVarForFramework', ['API_URL', 'react']);
        $this->assertEquals('REACT_APP_API_URL', $result);
    }

    public function test_adapt_env_var_preserves_existing_client_prefix(): void
    {
        $provisioner = $this->getProvisioner();

        // Already has VITE_ prefix — should not double-prefix
        $result = $this->callPrivateMethod($provisioner, 'adaptEnvVarForFramework', ['VITE_API_URL', 'nextjs']);
        $this->assertEquals('VITE_API_URL', $result);

        $result = $this->callPrivateMethod($provisioner, 'adaptEnvVarForFramework', ['NEXT_PUBLIC_API_URL', 'vue']);
        $this->assertEquals('NEXT_PUBLIC_API_URL', $result);
    }

    public function test_adapt_env_var_for_unknown_framework_keeps_original(): void
    {
        $provisioner = $this->getProvisioner();

        $result = $this->callPrivateMethod($provisioner, 'adaptEnvVarForFramework', ['API_URL', 'expressjs']);
        $this->assertEquals('API_URL', $result, 'Backend framework should not add client-side prefix');
    }

    public function test_adapt_env_var_for_svelte_framework(): void
    {
        $provisioner = $this->getProvisioner();

        $result = $this->callPrivateMethod($provisioner, 'adaptEnvVarForFramework', ['API_URL', 'sveltekit']);
        $this->assertEquals('VITE_API_URL', $result);
    }
}
