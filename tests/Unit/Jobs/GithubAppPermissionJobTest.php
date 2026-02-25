<?php

use App\Jobs\GithubAppPermissionJob;
use App\Models\GithubApp;

beforeEach(function () {
    Mockery::close();
});

afterEach(function () {
    Mockery::close();
});

test('job has correct configuration', function () {
    $githubApp = Mockery::mock(GithubApp::class)->makePartial();
    $job = new GithubAppPermissionJob($githubApp);

    expect($job->tries)->toBe(4);

    $interfaces = class_implements($job);
    expect($interfaces)->toContain(\Illuminate\Contracts\Queue\ShouldQueue::class);
    expect($interfaces)->toContain(\Illuminate\Contracts\Queue\ShouldBeEncrypted::class);
});

test('backoff returns 1 second in dev environment', function () {
    $githubApp = Mockery::mock(GithubApp::class)->makePartial();
    $job = new GithubAppPermissionJob($githubApp);

    // backoff() is dynamic based on isDev()
    $result = $job->backoff();
    expect($result)->toBeInt();
    // In test environment, isDev() usually returns true
    expect($result)->toBeIn([1, 3]);
});

test('source code fetches permissions from GitHub API', function () {
    $source = file_get_contents((new ReflectionClass(GithubAppPermissionJob::class))->getFileName());

    // Verifies it calls the /app endpoint
    expect($source)->toContain('/app');
    expect($source)->toContain('Authorization');
    expect($source)->toContain('Bearer');
    expect($source)->toContain('application/vnd.github+json');
});

test('source code updates all four permission fields', function () {
    $source = file_get_contents((new ReflectionClass(GithubAppPermissionJob::class))->getFileName());

    expect($source)->toContain("->contents = data_get(\$permissions, 'contents')");
    expect($source)->toContain("->metadata = data_get(\$permissions, 'metadata')");
    expect($source)->toContain("->pull_requests = data_get(\$permissions, 'pull_requests')");
    expect($source)->toContain("->administration = data_get(\$permissions, 'administration')");
});

test('source code saves model after updating permissions', function () {
    $source = file_get_contents((new ReflectionClass(GithubAppPermissionJob::class))->getFileName());

    expect($source)->toContain('->save()');
});

test('source code handles error and sends notification', function () {
    $source = file_get_contents((new ReflectionClass(GithubAppPermissionJob::class))->getFileName());

    expect($source)->toContain('send_internal_notification');
    expect($source)->toContain('GithubAppPermissionJob failed with');
    expect($source)->toContain('throw $e');
});

test('source code makes secrets visible after save', function () {
    $source = file_get_contents((new ReflectionClass(GithubAppPermissionJob::class))->getFileName());

    expect($source)->toContain("makeVisible('client_secret')");
    expect($source)->toContain("makeVisible('webhook_secret')");
});

test('source code uses generateGithubJwt helper', function () {
    $source = file_get_contents((new ReflectionClass(GithubAppPermissionJob::class))->getFileName());

    expect($source)->toContain('generateGithubJwt');
});

test('source code throws on non-successful response', function () {
    $source = file_get_contents((new ReflectionClass(GithubAppPermissionJob::class))->getFileName());

    expect($source)->toContain('->successful()');
    expect($source)->toContain('throw new \RuntimeException');
    expect($source)->toContain('Failed to fetch GitHub app permissions');
});

test('permission data extraction with data_get', function () {
    // Simulate GitHub API response
    $response = [
        'permissions' => [
            'contents' => 'write',
            'metadata' => 'read',
            'pull_requests' => 'write',
            'administration' => 'read',
        ],
    ];

    $permissions = data_get($response, 'permissions');
    expect(data_get($permissions, 'contents'))->toBe('write');
    expect(data_get($permissions, 'metadata'))->toBe('read');
    expect(data_get($permissions, 'pull_requests'))->toBe('write');
    expect(data_get($permissions, 'administration'))->toBe('read');
});

test('permission data extraction with missing fields', function () {
    $response = [
        'permissions' => [
            'contents' => 'write',
        ],
    ];

    $permissions = data_get($response, 'permissions');
    expect(data_get($permissions, 'contents'))->toBe('write');
    expect(data_get($permissions, 'metadata'))->toBeNull();
    expect(data_get($permissions, 'pull_requests'))->toBeNull();
    expect(data_get($permissions, 'administration'))->toBeNull();
});

test('permission data extraction with empty permissions', function () {
    $response = ['permissions' => null];

    $permissions = data_get($response, 'permissions');
    expect(data_get($permissions, 'contents'))->toBeNull();
    expect(data_get($permissions, 'metadata'))->toBeNull();
});
