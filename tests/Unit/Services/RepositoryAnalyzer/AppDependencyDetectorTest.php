<?php

namespace Tests\Unit\Services\RepositoryAnalyzer;

use App\Services\RepositoryAnalyzer\Detectors\AppDependencyDetector;
use App\Services\RepositoryAnalyzer\DTOs\DetectedApp;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/app-dependency-test-'.uniqid();
    mkdir($this->tempDir, 0755, true);
    $this->detector = new AppDependencyDetector;
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        exec('rm -rf '.escapeshellarg($this->tempDir));
    }
});

test('detects workspace dependencies from package json', function () {
    // Create apps/web with dependency on api
    mkdir($this->tempDir.'/apps/web', 0755, true);
    mkdir($this->tempDir.'/apps/api', 0755, true);

    file_put_contents($this->tempDir.'/apps/web/package.json', json_encode([
        'name' => '@monorepo/web',
        'dependencies' => [
            'react' => '^18.0.0',
            '@monorepo/api-client' => 'workspace:*',
        ],
    ]));

    file_put_contents($this->tempDir.'/apps/api/package.json', json_encode([
        'name' => '@monorepo/api',
        'dependencies' => [
            'express' => '^4.18.0',
        ],
    ]));

    $apps = [
        new DetectedApp(
            name: 'web',
            path: 'apps/web',
            framework: 'react',
            buildPack: 'static',
            defaultPort: 3000,
            type: 'frontend',
        ),
        new DetectedApp(
            name: 'api',
            path: 'apps/api',
            framework: 'express',
            buildPack: 'node',
            defaultPort: 4000,
            type: 'backend',
        ),
    ];

    $dependencies = $this->detector->analyze($this->tempDir, $apps);

    expect($dependencies)->toHaveCount(2);

    $webDeps = collect($dependencies)->first(fn ($d) => $d->appName === 'web');
    expect($webDeps->dependsOn)->toContain('api');
});

test('calculates deploy order with topological sort', function () {
    mkdir($this->tempDir.'/packages/shared', 0755, true);
    mkdir($this->tempDir.'/apps/api', 0755, true);
    mkdir($this->tempDir.'/apps/web', 0755, true);

    // shared has no dependencies
    file_put_contents($this->tempDir.'/packages/shared/package.json', json_encode([
        'name' => '@monorepo/shared',
    ]));

    // api depends on shared
    file_put_contents($this->tempDir.'/apps/api/package.json', json_encode([
        'name' => '@monorepo/api',
        'dependencies' => [
            '@monorepo/shared' => 'workspace:*',
        ],
    ]));

    // web depends on api (and implicitly shared)
    file_put_contents($this->tempDir.'/apps/web/package.json', json_encode([
        'name' => '@monorepo/web',
        'dependencies' => [
            '@monorepo/api' => 'workspace:*',
        ],
    ]));

    $apps = [
        new DetectedApp(
            name: 'web',
            path: 'apps/web',
            framework: 'nextjs',
            buildPack: 'node',
            defaultPort: 3000,
        ),
        new DetectedApp(
            name: 'api',
            path: 'apps/api',
            framework: 'express',
            buildPack: 'node',
            defaultPort: 4000,
        ),
        new DetectedApp(
            name: 'shared',
            path: 'packages/shared',
            framework: 'unknown',
            buildPack: 'node',
            defaultPort: 0,
        ),
    ];

    $dependencies = $this->detector->analyze($this->tempDir, $apps);

    // Check that dependencies are sorted by deploy order
    $sharedOrder = collect($dependencies)->first(fn ($d) => $d->appName === 'shared')->deployOrder;
    $apiOrder = collect($dependencies)->first(fn ($d) => $d->appName === 'api')->deployOrder;
    $webOrder = collect($dependencies)->first(fn ($d) => $d->appName === 'web')->deployOrder;

    expect($sharedOrder)->toBeLessThan($apiOrder);
    expect($apiOrder)->toBeLessThan($webOrder);
});

test('detects internal urls from env files', function () {
    mkdir($this->tempDir.'/apps/web', 0755, true);
    mkdir($this->tempDir.'/apps/api', 0755, true);

    file_put_contents($this->tempDir.'/apps/web/package.json', json_encode([
        'name' => 'web',
    ]));

    file_put_contents($this->tempDir.'/apps/web/.env.example', <<<'ENV'
# Backend API
API_URL=http://api:4000
NEXT_PUBLIC_API_URL=http://localhost:4000
ENV);

    file_put_contents($this->tempDir.'/apps/api/package.json', json_encode([
        'name' => 'api',
    ]));

    $apps = [
        new DetectedApp(
            name: 'web',
            path: 'apps/web',
            framework: 'nextjs',
            buildPack: 'node',
            defaultPort: 3000,
            type: 'frontend',
        ),
        new DetectedApp(
            name: 'api',
            path: 'apps/api',
            framework: 'express',
            buildPack: 'node',
            defaultPort: 4000,
            type: 'backend',
        ),
    ];

    $dependencies = $this->detector->analyze($this->tempDir, $apps);

    $webDeps = collect($dependencies)->first(fn ($d) => $d->appName === 'web');
    expect($webDeps->internalUrls)->toHaveKey('API_URL');
    expect($webDeps->internalUrls['API_URL'])->toBe('api');
});

test('infers api url by app type not by name', function () {
    // Test that frontend connects to backend based on TYPE, not name
    mkdir($this->tempDir.'/apps/client', 0755, true);
    mkdir($this->tempDir.'/apps/server', 0755, true);

    file_put_contents($this->tempDir.'/apps/client/package.json', json_encode([
        'name' => 'client',
    ]));

    file_put_contents($this->tempDir.'/apps/server/package.json', json_encode([
        'name' => 'server',
    ]));

    $apps = [
        new DetectedApp(
            name: 'client',  // Not named 'web' or 'frontend'
            path: 'apps/client',
            framework: 'react',
            buildPack: 'static',
            defaultPort: 3000,
            type: 'frontend',  // But TYPE is frontend
        ),
        new DetectedApp(
            name: 'server',  // Not named 'api' or 'backend'
            path: 'apps/server',
            framework: 'express',
            buildPack: 'node',
            defaultPort: 4000,
            type: 'backend',  // But TYPE is backend
        ),
    ];

    $dependencies = $this->detector->analyze($this->tempDir, $apps);

    $clientDeps = collect($dependencies)->first(fn ($d) => $d->appName === 'client');
    // Should infer API_URL because client is frontend and server is the only backend
    expect($clientDeps->internalUrls)->toHaveKey('API_URL');
    expect($clientDeps->internalUrls['API_URL'])->toBe('server');
});

test('detects api need from env variable names', function () {
    mkdir($this->tempDir.'/apps/web', 0755, true);
    mkdir($this->tempDir.'/apps/core', 0755, true);

    // Frontend with NEXT_PUBLIC_API_URL (even without value)
    file_put_contents($this->tempDir.'/apps/web/package.json', '{}');
    file_put_contents($this->tempDir.'/apps/web/.env.example', <<<'ENV'
NEXT_PUBLIC_API_URL=
DATABASE_URL=
ENV);

    file_put_contents($this->tempDir.'/apps/core/package.json', '{}');

    $apps = [
        new DetectedApp(
            name: 'web',
            path: 'apps/web',
            framework: 'nextjs',
            buildPack: 'node',
            defaultPort: 3000,
            type: 'unknown',  // Even if type is unknown
        ),
        new DetectedApp(
            name: 'core',
            path: 'apps/core',
            framework: 'fastapi',
            buildPack: 'python',
            defaultPort: 8000,
            type: 'backend',
        ),
    ];

    $dependencies = $this->detector->analyze($this->tempDir, $apps);

    $webDeps = collect($dependencies)->first(fn ($d) => $d->appName === 'web');
    // Should detect API need from NEXT_PUBLIC_API_URL variable
    expect($webDeps->internalUrls)->toHaveKey('API_URL');
    expect($webDeps->internalUrls['API_URL'])->toBe('core');
});

test('does not infer api url when multiple backends exist', function () {
    mkdir($this->tempDir.'/apps/web', 0755, true);
    mkdir($this->tempDir.'/apps/api1', 0755, true);
    mkdir($this->tempDir.'/apps/api2', 0755, true);

    file_put_contents($this->tempDir.'/apps/web/package.json', '{}');
    file_put_contents($this->tempDir.'/apps/api1/package.json', '{}');
    file_put_contents($this->tempDir.'/apps/api2/package.json', '{}');

    $apps = [
        new DetectedApp(
            name: 'web',
            path: 'apps/web',
            framework: 'react',
            buildPack: 'static',
            defaultPort: 3000,
            type: 'frontend',
        ),
        new DetectedApp(
            name: 'api1',
            path: 'apps/api1',
            framework: 'express',
            buildPack: 'node',
            defaultPort: 4000,
            type: 'backend',
        ),
        new DetectedApp(
            name: 'api2',
            path: 'apps/api2',
            framework: 'fastapi',
            buildPack: 'python',
            defaultPort: 8000,
            type: 'backend',
        ),
    ];

    $dependencies = $this->detector->analyze($this->tempDir, $apps);

    $webDeps = collect($dependencies)->first(fn ($d) => $d->appName === 'web');
    // Should NOT infer - ambiguous which backend to use
    expect($webDeps->internalUrls)->toBeEmpty();
});

test('handles apps with no dependencies', function () {
    mkdir($this->tempDir.'/app1', 0755, true);
    mkdir($this->tempDir.'/app2', 0755, true);

    file_put_contents($this->tempDir.'/app1/package.json', json_encode([
        'name' => 'app1',
    ]));

    file_put_contents($this->tempDir.'/app2/package.json', json_encode([
        'name' => 'app2',
    ]));

    $apps = [
        new DetectedApp(
            name: 'app1',
            path: 'app1',
            framework: 'express',
            buildPack: 'node',
            defaultPort: 3000,
        ),
        new DetectedApp(
            name: 'app2',
            path: 'app2',
            framework: 'fastify',
            buildPack: 'node',
            defaultPort: 4000,
        ),
    ];

    $dependencies = $this->detector->analyze($this->tempDir, $apps);

    expect($dependencies)->toHaveCount(2);

    foreach ($dependencies as $dep) {
        expect($dep->dependsOn)->toBeEmpty();
    }
});
