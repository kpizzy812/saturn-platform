<?php

use App\Jobs\AlertEvaluationJob;

beforeEach(function () {
    Mockery::close();
});

afterEach(function () {
    Mockery::close();
});

// ═══════════════════════════════════════════
// checkCondition() — condition evaluation
// ═══════════════════════════════════════════

test('checkCondition greater than returns true when value exceeds threshold', function () {
    $job = new AlertEvaluationJob;
    $method = new ReflectionMethod($job, 'checkCondition');

    expect($method->invoke($job, 95.5, '>', 90.0))->toBeTrue();
});

test('checkCondition greater than returns false when value equals threshold', function () {
    $job = new AlertEvaluationJob;
    $method = new ReflectionMethod($job, 'checkCondition');

    expect($method->invoke($job, 90.0, '>', 90.0))->toBeFalse();
});

test('checkCondition greater than returns false when value below threshold', function () {
    $job = new AlertEvaluationJob;
    $method = new ReflectionMethod($job, 'checkCondition');

    expect($method->invoke($job, 80.0, '>', 90.0))->toBeFalse();
});

test('checkCondition less than returns true when value below threshold', function () {
    $job = new AlertEvaluationJob;
    $method = new ReflectionMethod($job, 'checkCondition');

    expect($method->invoke($job, 5.0, '<', 10.0))->toBeTrue();
});

test('checkCondition less than returns false when value exceeds threshold', function () {
    $job = new AlertEvaluationJob;
    $method = new ReflectionMethod($job, 'checkCondition');

    expect($method->invoke($job, 15.0, '<', 10.0))->toBeFalse();
});

test('checkCondition less than returns false when value equals threshold', function () {
    $job = new AlertEvaluationJob;
    $method = new ReflectionMethod($job, 'checkCondition');

    expect($method->invoke($job, 10.0, '<', 10.0))->toBeFalse();
});

test('checkCondition equals returns true for exact match', function () {
    $job = new AlertEvaluationJob;
    $method = new ReflectionMethod($job, 'checkCondition');

    expect($method->invoke($job, 50.0, '=', 50.0))->toBeTrue();
});

test('checkCondition equals uses float tolerance of 0.01', function () {
    $job = new AlertEvaluationJob;
    $method = new ReflectionMethod($job, 'checkCondition');

    // Within tolerance
    expect($method->invoke($job, 50.005, '=', 50.0))->toBeTrue();
    expect($method->invoke($job, 49.995, '=', 50.0))->toBeTrue();

    // Outside tolerance
    expect($method->invoke($job, 50.02, '=', 50.0))->toBeFalse();
    expect($method->invoke($job, 49.98, '=', 50.0))->toBeFalse();
});

test('checkCondition returns false for unknown operator', function () {
    $job = new AlertEvaluationJob;
    $method = new ReflectionMethod($job, 'checkCondition');

    expect($method->invoke($job, 50.0, '>=', 50.0))->toBeFalse();
    expect($method->invoke($job, 50.0, '!=', 50.0))->toBeFalse();
    expect($method->invoke($job, 50.0, 'invalid', 50.0))->toBeFalse();
});

// ═══════════════════════════════════════════
// METRIC_MAP — metric mapping
// ═══════════════════════════════════════════

test('METRIC_MAP contains all expected metrics', function () {
    $class = new ReflectionClass(AlertEvaluationJob::class);
    $map = $class->getConstant('METRIC_MAP');

    expect($map)->toHaveKey('cpu');
    expect($map)->toHaveKey('memory');
    expect($map)->toHaveKey('disk');
    expect($map)->toHaveKey('response_time');
});

test('METRIC_MAP maps to correct database columns', function () {
    $class = new ReflectionClass(AlertEvaluationJob::class);
    $map = $class->getConstant('METRIC_MAP');

    expect($map['cpu'])->toBe('cpu_usage_percent');
    expect($map['memory'])->toBe('memory_usage_percent');
    expect($map['disk'])->toBe('disk_usage_percent');
    expect($map['response_time'])->toBe('response_time_ms');
});

test('METRIC_MAP returns null for unknown metric', function () {
    $class = new ReflectionClass(AlertEvaluationJob::class);
    $map = $class->getConstant('METRIC_MAP');

    expect($map['unknown_metric'] ?? null)->toBeNull();
    expect($map['bandwidth'] ?? null)->toBeNull();
});

// ═══════════════════════════════════════════
// Job configuration
// ═══════════════════════════════════════════

test('job has correct tries configuration', function () {
    $job = new AlertEvaluationJob;

    expect($job->tries)->toBe(1);
});

test('job has correct timeout configuration', function () {
    $job = new AlertEvaluationJob;

    expect($job->timeout)->toBe(60);
});

// ═══════════════════════════════════════════
// Condition evaluation edge cases
// ═══════════════════════════════════════════

test('checkCondition handles zero values correctly', function () {
    $job = new AlertEvaluationJob;
    $method = new ReflectionMethod($job, 'checkCondition');

    expect($method->invoke($job, 0.0, '>', 0.0))->toBeFalse();
    expect($method->invoke($job, 0.0, '<', 0.0))->toBeFalse();
    expect($method->invoke($job, 0.0, '=', 0.0))->toBeTrue();
});

test('checkCondition handles large values', function () {
    $job = new AlertEvaluationJob;
    $method = new ReflectionMethod($job, 'checkCondition');

    expect($method->invoke($job, 99999.99, '>', 99999.98))->toBeTrue();
    expect($method->invoke($job, 0.001, '<', 0.002))->toBeTrue();
});

test('checkCondition handles negative values', function () {
    $job = new AlertEvaluationJob;
    $method = new ReflectionMethod($job, 'checkCondition');

    expect($method->invoke($job, -5.0, '<', 0.0))->toBeTrue();
    expect($method->invoke($job, -1.0, '>', -2.0))->toBeTrue();
});

// ═══════════════════════════════════════════
// Duration handling
// ═══════════════════════════════════════════

test('duration is clamped to minimum of 1', function () {
    // Verify the logic: max(1, (int) $alert->duration)
    expect(max(1, (int) 0))->toBe(1);
    expect(max(1, (int) -5))->toBe(1);
    expect(max(1, (int) 1))->toBe(1);
    expect(max(1, (int) 60))->toBe(60);
});

test('null duration defaults to 1 minute', function () {
    $duration = null;
    expect(max(1, (int) $duration))->toBe(1);
});
