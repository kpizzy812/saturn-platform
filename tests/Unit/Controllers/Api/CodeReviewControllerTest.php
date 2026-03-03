<?php

/**
 * Unit tests for CodeReviewController.
 *
 * Tests cover:
 * - Class structure (methods exist)
 * - trigger() early exit when code review is disabled (no DB access)
 * - formatReview() private method returns correct structure
 * - formatViolation() private method returns correct structure
 */

use App\Http\Controllers\Api\CodeReviewController;
use App\Models\CodeReview;
use App\Models\CodeReviewViolation;
use Carbon\Carbon;
use Illuminate\Http\Request;

// ─── Class structure ──────────────────────────────────────────────────────────

test('CodeReviewController class exists', function () {
    expect(class_exists(CodeReviewController::class))->toBeTrue();
});

test('CodeReviewController has show method', function () {
    expect(method_exists(CodeReviewController::class, 'show'))->toBeTrue();
});

test('CodeReviewController has trigger method', function () {
    expect(method_exists(CodeReviewController::class, 'trigger'))->toBeTrue();
});

test('CodeReviewController has violations method', function () {
    expect(method_exists(CodeReviewController::class, 'violations'))->toBeTrue();
});

test('CodeReviewController has status method', function () {
    expect(method_exists(CodeReviewController::class, 'status'))->toBeTrue();
});

// ─── trigger() early exit when code review is disabled ───────────────────────

test('trigger returns 503 when code review is disabled', function () {
    config(['ai.code_review.enabled' => false]);

    $controller = new CodeReviewController;
    $request = Request::create('/api/v1/code-review/test-uuid/trigger', 'POST');

    $response = $controller->trigger($request, 'test-uuid');

    expect($response->getStatusCode())->toBe(503);
    $data = json_decode($response->getContent(), true);
    expect($data['error'])->toBe('Code review is disabled');
});

test('trigger error response has hint field when code review disabled', function () {
    config(['ai.code_review.enabled' => false]);

    $controller = new CodeReviewController;
    $request = Request::create('/api/v1/code-review/any-uuid/trigger', 'POST');

    $response = $controller->trigger($request, 'any-uuid');
    $data = json_decode($response->getContent(), true);

    expect($data)->toHaveKey('hint');
    expect($data['hint'])->toContain('AI_CODE_REVIEW_ENABLED');
});

// ─── formatReview() private method via reflection ────────────────────────────

test('formatReview returns all required keys', function () {
    $now = Carbon::now()->toDateTimeString();

    $review = new CodeReview;
    $review->setRawAttributes([
        'id' => 42,
        'deployment_id' => 100,
        'application_id' => 200,
        'commit_sha' => 'abc123def456',
        'base_commit_sha' => '000000000000',
        'status' => 'completed',
        'files_analyzed' => json_encode(['app/Models/User.php']),
        'violations_count' => 0,
        'critical_count' => 0,
        'llm_provider' => 'claude',
        'llm_model' => 'claude-sonnet-4-6',
        'llm_failed' => 0,
        'duration_ms' => 1500,
        'error_message' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    // Load violations as empty so getViolationsBySeverity() works without DB
    $review->setRelation('violations', collect([]));

    $method = new ReflectionMethod(CodeReviewController::class, 'formatReview');
    $controller = new CodeReviewController;
    $result = $method->invoke($controller, $review);

    expect($result)->toHaveKeys([
        'id',
        'deployment_id',
        'application_id',
        'commit_sha',
        'base_commit_sha',
        'status',
        'status_label',
        'status_color',
        'files_analyzed',
        'files_count',
        'violations_count',
        'critical_count',
        'has_violations',
        'has_critical',
        'violations_by_severity',
        'llm_provider',
        'llm_model',
        'llm_failed',
        'duration_ms',
        'started_at',
        'finished_at',
        'error_message',
        'created_at',
        'violations',
    ]);
});

test('formatReview calculates files_count from files_analyzed array', function () {
    $now = Carbon::now()->toDateTimeString();

    $review = new CodeReview;
    $review->setRawAttributes([
        'id' => 1,
        'status' => 'completed',
        'files_analyzed' => json_encode(['app/Models/User.php', 'app/Models/Team.php', 'app/Models/Server.php']),
        'violations_count' => 0,
        'critical_count' => 0,
        'llm_failed' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $review->setRelation('violations', collect([]));

    $method = new ReflectionMethod(CodeReviewController::class, 'formatReview');
    $result = $method->invoke(new CodeReviewController, $review);

    expect($result['files_count'])->toBe(3);
    expect($result['files_analyzed'])->toBe(['app/Models/User.php', 'app/Models/Team.php', 'app/Models/Server.php']);
});

test('formatReview status_label is Passed for completed review with no violations', function () {
    $now = Carbon::now()->toDateTimeString();

    $review = new CodeReview;
    $review->setRawAttributes([
        'id' => 1,
        'status' => 'completed',
        'violations_count' => 0,
        'critical_count' => 0,
        'llm_failed' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $review->setRelation('violations', collect([]));

    $method = new ReflectionMethod(CodeReviewController::class, 'formatReview');
    $result = $method->invoke(new CodeReviewController, $review);

    expect($result['status_label'])->toBe('Passed');
    expect($result['status_color'])->toBe('green');
    expect($result['has_violations'])->toBeFalse();
    expect($result['has_critical'])->toBeFalse();
});

test('formatReview status_label is Issues Found for completed review with violations but no critical', function () {
    $now = Carbon::now()->toDateTimeString();

    $review = new CodeReview;
    $review->setRawAttributes([
        'id' => 2,
        'status' => 'completed',
        'violations_count' => 3,
        'critical_count' => 0,
        'llm_failed' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $review->setRelation('violations', collect([]));

    $method = new ReflectionMethod(CodeReviewController::class, 'formatReview');
    $result = $method->invoke(new CodeReviewController, $review);

    expect($result['status_label'])->toBe('Issues Found');
    expect($result['status_color'])->toBe('yellow');
    expect($result['has_violations'])->toBeTrue();
    expect($result['has_critical'])->toBeFalse();
});

test('formatReview status_label is Critical Issues when critical_count is set', function () {
    $now = Carbon::now()->toDateTimeString();

    $review = new CodeReview;
    $review->setRawAttributes([
        'id' => 3,
        'status' => 'completed',
        'violations_count' => 2,
        'critical_count' => 1,
        'llm_failed' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $review->setRelation('violations', collect([]));

    $method = new ReflectionMethod(CodeReviewController::class, 'formatReview');
    $result = $method->invoke(new CodeReviewController, $review);

    expect($result['status_label'])->toBe('Critical Issues');
    expect($result['status_color'])->toBe('red');
});

test('formatReview status_label is Analyzing for analyzing status', function () {
    $now = Carbon::now()->toDateTimeString();

    $review = new CodeReview;
    $review->setRawAttributes([
        'id' => 4,
        'status' => 'analyzing',
        'violations_count' => 0,
        'critical_count' => 0,
        'llm_failed' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $review->setRelation('violations', collect([]));

    $method = new ReflectionMethod(CodeReviewController::class, 'formatReview');
    $result = $method->invoke(new CodeReviewController, $review);

    expect($result['status_label'])->toBe('Analyzing');
    expect($result['status_color'])->toBe('blue');
});

test('formatReview violations is empty when no violations exist', function () {
    $now = Carbon::now()->toDateTimeString();

    $review = new CodeReview;
    $review->setRawAttributes([
        'id' => 5,
        'status' => 'pending',
        'violations_count' => 0,
        'critical_count' => 0,
        'llm_failed' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $review->setRelation('violations', collect([]));

    $method = new ReflectionMethod(CodeReviewController::class, 'formatReview');
    $result = $method->invoke(new CodeReviewController, $review);

    // With violations relation loaded (empty), violations key returns empty collection
    expect($result['violations'])->toBeEmpty();
});

// ─── formatViolation() private method via reflection ─────────────────────────

test('formatViolation returns all required keys', function () {
    $now = Carbon::now()->toDateTimeString();

    $violation = new CodeReviewViolation;
    $violation->setRawAttributes([
        'id' => 10,
        'rule_id' => 'SEC001',
        'source' => 'regex',
        'severity' => 'critical',
        'confidence' => 1.0,
        'file_path' => 'app/Models/User.php',
        'line_number' => 42,
        'message' => 'Hardcoded API key detected',
        'snippet' => '$apiKey = "sk-1234567890";',
        'suggestion' => 'Use environment variables instead',
        'contains_secret' => 1,
        'created_at' => $now,
    ]);

    $method = new ReflectionMethod(CodeReviewController::class, 'formatViolation');
    $result = $method->invoke(new CodeReviewController, $violation);

    expect($result)->toHaveKeys([
        'id',
        'rule_id',
        'rule_description',
        'rule_category',
        'source',
        'severity',
        'severity_color',
        'confidence',
        'file_path',
        'line_number',
        'location',
        'message',
        'snippet',
        'suggestion',
        'contains_secret',
        'is_deterministic',
        'created_at',
    ]);
});

test('formatViolation severity_color is red for critical severity', function () {
    $now = Carbon::now()->toDateTimeString();

    $violation = new CodeReviewViolation;
    $violation->setRawAttributes([
        'id' => 1,
        'rule_id' => 'SEC001',
        'source' => 'regex',
        'severity' => 'critical',
        'confidence' => 1.0,
        'file_path' => 'app/foo.php',
        'message' => 'test',
        'contains_secret' => 0,
        'created_at' => $now,
    ]);

    $method = new ReflectionMethod(CodeReviewController::class, 'formatViolation');
    $result = $method->invoke(new CodeReviewController, $violation);

    expect($result['severity_color'])->toBe('red');
});

test('formatViolation is_deterministic is true for regex source', function () {
    $now = Carbon::now()->toDateTimeString();

    $violation = new CodeReviewViolation;
    $violation->setRawAttributes([
        'id' => 2,
        'rule_id' => 'SEC002',
        'source' => 'regex',
        'severity' => 'high',
        'confidence' => 0.9,
        'file_path' => 'app/foo.php',
        'message' => 'test',
        'contains_secret' => 0,
        'created_at' => $now,
    ]);

    $method = new ReflectionMethod(CodeReviewController::class, 'formatViolation');
    $result = $method->invoke(new CodeReviewController, $violation);

    expect($result['is_deterministic'])->toBeTrue();
});

test('formatViolation is_deterministic is false for llm source', function () {
    $now = Carbon::now()->toDateTimeString();

    $violation = new CodeReviewViolation;
    $violation->setRawAttributes([
        'id' => 3,
        'rule_id' => 'QUAL001',
        'source' => 'llm',
        'severity' => 'low',
        'confidence' => 0.7,
        'file_path' => 'app/foo.php',
        'message' => 'code smell',
        'contains_secret' => 0,
        'created_at' => $now,
    ]);

    $method = new ReflectionMethod(CodeReviewController::class, 'formatViolation');
    $result = $method->invoke(new CodeReviewController, $violation);

    expect($result['is_deterministic'])->toBeFalse();
});

test('formatViolation rule_category is Security for SEC rule_id', function () {
    $now = Carbon::now()->toDateTimeString();

    $violation = new CodeReviewViolation;
    $violation->setRawAttributes([
        'id' => 4,
        'rule_id' => 'SEC003',
        'source' => 'ast',
        'severity' => 'medium',
        'confidence' => 0.85,
        'file_path' => 'app/foo.php',
        'message' => 'test',
        'contains_secret' => 0,
        'created_at' => $now,
    ]);

    $method = new ReflectionMethod(CodeReviewController::class, 'formatViolation');
    $result = $method->invoke(new CodeReviewController, $violation);

    expect($result['rule_category'])->toBe('Security');
});

test('formatViolation location includes line number when present', function () {
    $now = Carbon::now()->toDateTimeString();

    $violation = new CodeReviewViolation;
    $violation->setRawAttributes([
        'id' => 5,
        'rule_id' => 'SEC001',
        'source' => 'regex',
        'severity' => 'high',
        'confidence' => 1.0,
        'file_path' => 'app/Models/User.php',
        'line_number' => 99,
        'message' => 'test',
        'contains_secret' => 0,
        'created_at' => $now,
    ]);

    $method = new ReflectionMethod(CodeReviewController::class, 'formatViolation');
    $result = $method->invoke(new CodeReviewController, $violation);

    expect($result['location'])->toBe('app/Models/User.php:99');
});
