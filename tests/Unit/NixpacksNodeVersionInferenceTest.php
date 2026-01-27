<?php

use App\Jobs\ApplicationDeploymentJob;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;

/**
 * Tests for Node.js version inference from framework dependencies.
 *
 * When .nvmrc and engines.node are absent, Saturn should detect the required
 * Node.js version from known framework dependencies (Next.js, Nuxt, Astro, etc.).
 */
function createJobWithReflection(): array
{
    $mockApplication = Mockery::mock(Application::class)->shouldIgnoreMissing();
    $mockQueue = Mockery::mock(ApplicationDeploymentQueue::class)->shouldIgnoreMissing();
    $mockQueue->shouldReceive('addLogEntry')->andReturnNull();

    $job = Mockery::mock(ApplicationDeploymentJob::class)->makePartial();
    $job->shouldAllowMockingProtectedMethods();

    $reflection = new ReflectionClass(ApplicationDeploymentJob::class);

    $appProp = $reflection->getProperty('application');
    $appProp->setAccessible(true);
    $appProp->setValue($job, $mockApplication);

    $queueProp = $reflection->getProperty('application_deployment_queue');
    $queueProp->setAccessible(true);
    $queueProp->setValue($job, $mockQueue);

    return [$job, $reflection];
}

// --- inferNodeVersionFromDependencies tests ---

it('infers Node 20 from next@16', function () {
    [$job, $reflection] = createJobWithReflection();
    $method = $reflection->getMethod('inferNodeVersionFromDependencies');
    $method->setAccessible(true);

    $packageJson = [
        'dependencies' => [
            'next' => '16.0.7',
            'react' => '^19.0.0',
        ],
    ];

    $result = $method->invoke($job, $packageJson);
    expect($result)->toBe('20');
});

it('infers Node 18 from next@15', function () {
    [$job, $reflection] = createJobWithReflection();
    $method = $reflection->getMethod('inferNodeVersionFromDependencies');
    $method->setAccessible(true);

    $packageJson = [
        'dependencies' => [
            'next' => '^15.0.0',
        ],
    ];

    $result = $method->invoke($job, $packageJson);
    expect($result)->toBe('18');
});

it('infers Node 20 from nuxt@4', function () {
    [$job, $reflection] = createJobWithReflection();
    $method = $reflection->getMethod('inferNodeVersionFromDependencies');
    $method->setAccessible(true);

    $packageJson = [
        'dependencies' => [
            'nuxt' => '^4.0.0',
        ],
    ];

    $result = $method->invoke($job, $packageJson);
    expect($result)->toBe('20');
});

it('infers Node 20 from angular@19', function () {
    [$job, $reflection] = createJobWithReflection();
    $method = $reflection->getMethod('inferNodeVersionFromDependencies');
    $method->setAccessible(true);

    $packageJson = [
        'dependencies' => [
            '@angular/core' => '^19.0.0',
        ],
    ];

    $result = $method->invoke($job, $packageJson);
    expect($result)->toBe('20');
});

it('infers Node 18 from vite@6', function () {
    [$job, $reflection] = createJobWithReflection();
    $method = $reflection->getMethod('inferNodeVersionFromDependencies');
    $method->setAccessible(true);

    $packageJson = [
        'devDependencies' => [
            'vite' => '^6.1.0',
        ],
    ];

    $result = $method->invoke($job, $packageJson);
    expect($result)->toBe('18');
});

it('infers Node 20 from vite@7', function () {
    [$job, $reflection] = createJobWithReflection();
    $method = $reflection->getMethod('inferNodeVersionFromDependencies');
    $method->setAccessible(true);

    $packageJson = [
        'devDependencies' => [
            'vite' => '^7.0.0',
        ],
    ];

    $result = $method->invoke($job, $packageJson);
    expect($result)->toBe('20');
});

it('infers Node 18 from astro@5', function () {
    [$job, $reflection] = createJobWithReflection();
    $method = $reflection->getMethod('inferNodeVersionFromDependencies');
    $method->setAccessible(true);

    $packageJson = [
        'dependencies' => [
            'astro' => '5.2.0',
        ],
    ];

    $result = $method->invoke($job, $packageJson);
    expect($result)->toBe('18');
});

it('infers Node 20 from nuxt@3', function () {
    [$job, $reflection] = createJobWithReflection();
    $method = $reflection->getMethod('inferNodeVersionFromDependencies');
    $method->setAccessible(true);

    $packageJson = [
        'dependencies' => [
            'nuxt' => '^3.14.0',
        ],
    ];

    $result = $method->invoke($job, $packageJson);
    expect($result)->toBe('20');
});

it('infers Node 20 from svelte@5', function () {
    [$job, $reflection] = createJobWithReflection();
    $method = $reflection->getMethod('inferNodeVersionFromDependencies');
    $method->setAccessible(true);

    $packageJson = [
        'dependencies' => [
            'svelte' => '^5.0.0',
        ],
    ];

    $result = $method->invoke($job, $packageJson);
    expect($result)->toBe('20');
});

it('returns highest required Node version when multiple frameworks present', function () {
    [$job, $reflection] = createJobWithReflection();
    $method = $reflection->getMethod('inferNodeVersionFromDependencies');
    $method->setAccessible(true);

    $packageJson = [
        'dependencies' => [
            'next' => '^15.0.0',  // requires Node 18
        ],
        'devDependencies' => [
            'vite' => '^7.0.0',  // requires Node 20
        ],
    ];

    $result = $method->invoke($job, $packageJson);
    expect($result)->toBe('20');
});

it('returns null when no known frameworks found', function () {
    [$job, $reflection] = createJobWithReflection();
    $method = $reflection->getMethod('inferNodeVersionFromDependencies');
    $method->setAccessible(true);

    $packageJson = [
        'dependencies' => [
            'express' => '^4.18.0',
            'lodash' => '^4.17.21',
        ],
    ];

    $result = $method->invoke($job, $packageJson);
    expect($result)->toBeNull();
});

it('returns null for empty package.json', function () {
    [$job, $reflection] = createJobWithReflection();
    $method = $reflection->getMethod('inferNodeVersionFromDependencies');
    $method->setAccessible(true);

    $result = $method->invoke($job, []);
    expect($result)->toBeNull();
});

it('handles latest and wildcard version specifiers gracefully', function () {
    [$job, $reflection] = createJobWithReflection();
    $method = $reflection->getMethod('inferNodeVersionFromDependencies');
    $method->setAccessible(true);

    $packageJson = [
        'dependencies' => [
            'next' => 'latest',
        ],
    ];

    // 'latest' cannot be parsed to a major version, so should return null
    $result = $method->invoke($job, $packageJson);
    expect($result)->toBeNull();
});

// --- extractMajorVersion tests ---

it('extracts major version from semver string', function () {
    [$job, $reflection] = createJobWithReflection();
    $method = $reflection->getMethod('extractMajorVersion');
    $method->setAccessible(true);

    expect($method->invoke($job, '16.0.7'))->toBe(16);
    expect($method->invoke($job, '15.2.3'))->toBe(15);
    expect($method->invoke($job, '4.0.0-rc.1'))->toBe(4);
});

it('extracts major version from caret range', function () {
    [$job, $reflection] = createJobWithReflection();
    $method = $reflection->getMethod('extractMajorVersion');
    $method->setAccessible(true);

    expect($method->invoke($job, '^16.0.0'))->toBe(16);
    expect($method->invoke($job, '^19.0.0'))->toBe(19);
});

it('extracts major version from tilde range', function () {
    [$job, $reflection] = createJobWithReflection();
    $method = $reflection->getMethod('extractMajorVersion');
    $method->setAccessible(true);

    expect($method->invoke($job, '~16.0.0'))->toBe(16);
});

it('extracts major version from gte range', function () {
    [$job, $reflection] = createJobWithReflection();
    $method = $reflection->getMethod('extractMajorVersion');
    $method->setAccessible(true);

    expect($method->invoke($job, '>=16'))->toBe(16);
    expect($method->invoke($job, '>=20.9.0'))->toBe(20);
});

it('returns null for non-numeric specifiers', function () {
    [$job, $reflection] = createJobWithReflection();
    $method = $reflection->getMethod('extractMajorVersion');
    $method->setAccessible(true);

    expect($method->invoke($job, 'latest'))->toBeNull();
    expect($method->invoke($job, '*'))->toBeNull();
    expect($method->invoke($job, ''))->toBeNull();
});
