<?php

use App\Jobs\CleanupOrphanedPreviewContainersJob;
use Illuminate\Queue\Middleware\WithoutOverlapping;

beforeEach(function () {
    Mockery::close();
});

afterEach(function () {
    Mockery::close();
});

test('job has correct configuration', function () {
    $job = new CleanupOrphanedPreviewContainersJob;

    expect($job->timeout)->toBe(600);

    $interfaces = class_implements($job);
    expect($interfaces)->toContain(\Illuminate\Contracts\Queue\ShouldQueue::class);
    expect($interfaces)->toContain(\Illuminate\Contracts\Queue\ShouldBeEncrypted::class);
    expect($interfaces)->toContain(\Illuminate\Contracts\Queue\ShouldBeUnique::class);
});

test('middleware includes WithoutOverlapping', function () {
    $job = new CleanupOrphanedPreviewContainersJob;
    $middleware = $job->middleware();

    expect($middleware)->toHaveCount(1);
    expect($middleware[0])->toBeInstanceOf(WithoutOverlapping::class);
});

test('extractPullRequestId extracts valid PR ID from labels', function () {
    $job = new CleanupOrphanedPreviewContainersJob;
    $method = new ReflectionMethod($job, 'extractPullRequestId');

    $container = ['Labels' => 'saturn.pullRequestId=42,saturn.applicationId=5'];
    expect($method->invoke($job, $container))->toBe(42);
});

test('extractPullRequestId returns null for missing label', function () {
    $job = new CleanupOrphanedPreviewContainersJob;
    $method = new ReflectionMethod($job, 'extractPullRequestId');

    $container = ['Labels' => 'saturn.applicationId=5'];
    expect($method->invoke($job, $container))->toBeNull();
});

test('extractPullRequestId returns null for empty labels', function () {
    $job = new CleanupOrphanedPreviewContainersJob;
    $method = new ReflectionMethod($job, 'extractPullRequestId');

    $container = ['Labels' => ''];
    expect($method->invoke($job, $container))->toBeNull();
});

test('extractPullRequestId handles numeric IDs correctly', function () {
    $job = new CleanupOrphanedPreviewContainersJob;
    $method = new ReflectionMethod($job, 'extractPullRequestId');

    $container = ['Labels' => 'saturn.pullRequestId=12345'];
    expect($method->invoke($job, $container))->toBe(12345);

    $container = ['Labels' => 'saturn.pullRequestId=1'];
    expect($method->invoke($job, $container))->toBe(1);
});

test('extractApplicationId extracts valid app ID from labels', function () {
    $job = new CleanupOrphanedPreviewContainersJob;
    $method = new ReflectionMethod($job, 'extractApplicationId');

    $container = ['Labels' => 'saturn.applicationId=99,saturn.pullRequestId=7'];
    expect($method->invoke($job, $container))->toBe(99);
});

test('extractApplicationId returns null for missing label', function () {
    $job = new CleanupOrphanedPreviewContainersJob;
    $method = new ReflectionMethod($job, 'extractApplicationId');

    $container = ['Labels' => 'saturn.pullRequestId=7'];
    expect($method->invoke($job, $container))->toBeNull();
});

test('isOrphanedContainer returns false when both IDs are null', function () {
    $job = new CleanupOrphanedPreviewContainersJob;
    $method = new ReflectionMethod($job, 'isOrphanedContainer');

    // When applicationId or pullRequestId is null, should return false
    $container = ['Labels' => ''];
    expect($method->invoke($job, $container))->toBeFalse();
});

test('isOrphanedContainer returns false when applicationId is null', function () {
    $job = new CleanupOrphanedPreviewContainersJob;
    $method = new ReflectionMethod($job, 'isOrphanedContainer');

    $container = ['Labels' => 'saturn.pullRequestId=7'];
    expect($method->invoke($job, $container))->toBeFalse();
});

test('isOrphanedContainer returns false when pullRequestId is null', function () {
    $job = new CleanupOrphanedPreviewContainersJob;
    $method = new ReflectionMethod($job, 'isOrphanedContainer');

    $container = ['Labels' => 'saturn.applicationId=5'];
    expect($method->invoke($job, $container))->toBeFalse();
});

test('source code uses escapeshellarg for container removal', function () {
    $source = file_get_contents((new ReflectionClass(CleanupOrphanedPreviewContainersJob::class))->getFileName());

    expect($source)->toContain('escapeshellarg($containerName)');
    expect($source)->toContain('docker rm -f');
});

test('source code filters out test IP 1.2.3.4', function () {
    $source = file_get_contents((new ReflectionClass(CleanupOrphanedPreviewContainersJob::class))->getFileName());

    expect($source)->toContain("'1.2.3.4'");
    expect($source)->toContain('is_usable');
    expect($source)->toContain('is_reachable');
});

test('source code checks cloud subscription status', function () {
    $source = file_get_contents((new ReflectionClass(CleanupOrphanedPreviewContainersJob::class))->getFileName());

    expect($source)->toContain('isCloud()');
    expect($source)->toContain('stripe_invoice_paid');
});

test('source code checks ApplicationPreview with trashed', function () {
    $source = file_get_contents((new ReflectionClass(CleanupOrphanedPreviewContainersJob::class))->getFileName());

    expect($source)->toContain('withTrashed()');
    expect($source)->toContain('application_id');
    expect($source)->toContain('pull_request_id');
});

test('source code sends internal notification on overall failure', function () {
    $source = file_get_contents((new ReflectionClass(CleanupOrphanedPreviewContainersJob::class))->getFileName());

    expect($source)->toContain('send_internal_notification');
    expect($source)->toContain('CleanupOrphanedPreviewContainersJob failed');
});

test('source code logs orphaned container count', function () {
    $source = file_get_contents((new ReflectionClass(CleanupOrphanedPreviewContainersJob::class))->getFileName());

    expect($source)->toContain('orphanedCount');
    expect($source)->toContain('Removed');
    expect($source)->toContain('orphaned PR containers');
});

test('source code handles empty docker output', function () {
    $source = file_get_contents((new ReflectionClass(CleanupOrphanedPreviewContainersJob::class))->getFileName());

    expect($source)->toContain('empty($output)');
    expect($source)->toContain('collect()');
});

test('source code skips containers with empty names', function () {
    $source = file_get_contents((new ReflectionClass(CleanupOrphanedPreviewContainersJob::class))->getFileName());

    expect($source)->toContain('empty($containerName)');
    expect($source)->toContain('Cannot remove container: missing container name');
});
