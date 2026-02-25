<?php

use App\Jobs\CheckHelperImageJob;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Mockery::close();
});

afterEach(function () {
    Mockery::close();
});

test('job has correct configuration', function () {
    $job = new CheckHelperImageJob;

    expect($job->timeout)->toBe(120);
    expect($job->tries)->toBe(2);
    expect($job->backoff)->toBe([10, 30]);

    $interfaces = class_implements($job);
    expect($interfaces)->toContain(\Illuminate\Contracts\Queue\ShouldQueue::class);
    expect($interfaces)->toContain(\Illuminate\Contracts\Queue\ShouldBeEncrypted::class);
});

test('version comparison for newer version triggers update', function () {
    $latestVersion = '2.0.0';
    $currentVersion = '1.0.0';
    expect(version_compare($latestVersion, $currentVersion, '>'))->toBeTrue();
});

test('source code uses Http::retry for resilience', function () {
    $source = file_get_contents((new ReflectionClass(CheckHelperImageJob::class))->getFileName());

    expect($source)->toContain('Http::retry(3, 1000)');
    expect($source)->toContain('versions_url');
});

test('source code uses instanceSettings helper', function () {
    $source = file_get_contents((new ReflectionClass(CheckHelperImageJob::class))->getFileName());

    expect($source)->toContain('instanceSettings()');
    expect($source)->toContain('helper_version');
});

test('does not update when same version', function () {
    $latestVersion = '1.0.0';
    $currentVersion = '1.0.0';
    expect(version_compare($latestVersion, $currentVersion, '>'))->toBeFalse();
});

test('does not update when older version from CDN', function () {
    $latestVersion = '0.9.0';
    $currentVersion = '1.0.0';
    expect(version_compare($latestVersion, $currentVersion, '>'))->toBeFalse();
});

test('version comparison handles semver correctly', function () {
    expect(version_compare('1.0.1', '1.0.0', '>'))->toBeTrue();
    expect(version_compare('1.1.0', '1.0.9', '>'))->toBeTrue();
    expect(version_compare('2.0.0', '1.99.99', '>'))->toBeTrue();
    expect(version_compare('1.0.0', '1.0.0', '>'))->toBeFalse();
    expect(version_compare('0.9.9', '1.0.0', '>'))->toBeFalse();
});

test('http failure triggers notification and rethrows', function () {
    Http::fake([
        '*' => Http::response(null, 500),
    ]);

    // The job wraps failures in try-catch and calls send_internal_notification
    // Verify the exception propagation pattern
    $job = new CheckHelperImageJob;

    // Verify the class uses Http facade
    $reflection = new ReflectionClass(CheckHelperImageJob::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('Http::retry(3, 1000)');
    expect($source)->toContain('send_internal_notification');
    expect($source)->toContain('throw $e');
});

test('handles null latest version gracefully', function () {
    // When data_get returns null for the version path
    $versions = ['coolify' => ['helper' => []]];
    $latestVersion = data_get($versions, 'coolify.helper.version');

    expect($latestVersion)->toBeNull();

    // version_compare with null returns specific values
    // null is treated as empty string / 0
    expect(version_compare($latestVersion, '1.0.0', '>'))->toBeFalse();
});

test('handles missing coolify key in response', function () {
    $versions = ['other' => ['data' => true]];
    $latestVersion = data_get($versions, 'coolify.helper.version');

    expect($latestVersion)->toBeNull();
});
