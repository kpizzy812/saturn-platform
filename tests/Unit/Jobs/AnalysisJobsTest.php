<?php

/**
 * Unit tests for AI Analysis Jobs.
 *
 * Tests cover:
 * - AnalyzeDeploymentLogsJob: ShouldQueue, tries, timeout, constructor
 * - AnalyzeCodeReviewJob: ShouldQueue, tries, timeout, backoff, constructor
 */

use App\Jobs\AnalyzeCodeReviewJob;
use App\Jobs\AnalyzeDeploymentLogsJob;
use Illuminate\Contracts\Queue\ShouldQueue;

// ─── AnalyzeDeploymentLogsJob ─────────────────────────────────────────────────

test('AnalyzeDeploymentLogsJob class exists', function () {
    expect(class_exists(AnalyzeDeploymentLogsJob::class))->toBeTrue();
});

test('AnalyzeDeploymentLogsJob implements ShouldQueue', function () {
    expect(is_a(AnalyzeDeploymentLogsJob::class, ShouldQueue::class, allow_string: true))->toBeTrue();
});

test('AnalyzeDeploymentLogsJob has handle method', function () {
    expect(method_exists(AnalyzeDeploymentLogsJob::class, 'handle'))->toBeTrue();
});

test('AnalyzeDeploymentLogsJob tries is 1', function () {
    $job = new AnalyzeDeploymentLogsJob(999);
    expect($job->tries)->toBe(1);
});

test('AnalyzeDeploymentLogsJob timeout is 120 seconds', function () {
    $job = new AnalyzeDeploymentLogsJob(999);
    expect($job->timeout)->toBe(120);
});

test('AnalyzeDeploymentLogsJob stores deploymentId in constructor', function () {
    $job = new AnalyzeDeploymentLogsJob(42);
    expect($job->deploymentId)->toBe(42);
});

// ─── AnalyzeCodeReviewJob ─────────────────────────────────────────────────────

test('AnalyzeCodeReviewJob class exists', function () {
    expect(class_exists(AnalyzeCodeReviewJob::class))->toBeTrue();
});

test('AnalyzeCodeReviewJob implements ShouldQueue', function () {
    expect(is_a(AnalyzeCodeReviewJob::class, ShouldQueue::class, allow_string: true))->toBeTrue();
});

test('AnalyzeCodeReviewJob has handle method', function () {
    expect(method_exists(AnalyzeCodeReviewJob::class, 'handle'))->toBeTrue();
});

test('AnalyzeCodeReviewJob tries is 2', function () {
    $job = new AnalyzeCodeReviewJob(999);
    expect($job->tries)->toBe(2);
});

test('AnalyzeCodeReviewJob timeout is 180 seconds', function () {
    $job = new AnalyzeCodeReviewJob(999);
    expect($job->timeout)->toBe(180);
});

test('AnalyzeCodeReviewJob backoff is 30 and 60 seconds', function () {
    $job = new AnalyzeCodeReviewJob(999);
    expect($job->backoff)->toBe([30, 60]);
});

test('AnalyzeCodeReviewJob stores deploymentId in constructor', function () {
    $job = new AnalyzeCodeReviewJob(123);
    expect($job->deploymentId)->toBe(123);
});
