<?php

/**
 * Unit tests for CodeReview model.
 *
 * Tests cover:
 * - Status constants and status-check methods (isCompleted, isPending, isFailed, isAnalyzing)
 * - Violation-count helpers (hasViolations, hasCriticalViolations)
 * - status_label computed attribute (all label variants)
 * - status_color computed attribute (all color variants)
 */

use App\Models\CodeReview;

// ─── Status constants ─────────────────────────────────────────────────────────

test('CodeReview STATUS_PENDING constant is pending', function () {
    expect(CodeReview::STATUS_PENDING)->toBe('pending');
});

test('CodeReview STATUS_ANALYZING constant is analyzing', function () {
    expect(CodeReview::STATUS_ANALYZING)->toBe('analyzing');
});

test('CodeReview STATUS_COMPLETED constant is completed', function () {
    expect(CodeReview::STATUS_COMPLETED)->toBe('completed');
});

test('CodeReview STATUS_FAILED constant is failed', function () {
    expect(CodeReview::STATUS_FAILED)->toBe('failed');
});

// ─── isCompleted / isPending / isFailed / isAnalyzing ────────────────────────

test('isPending returns true when status is pending', function () {
    $review = new CodeReview;
    $review->setRawAttributes(['status' => 'pending']);
    expect($review->isPending())->toBeTrue();
});

test('isPending returns false when status is not pending', function () {
    $review = new CodeReview;
    $review->setRawAttributes(['status' => 'completed']);
    expect($review->isPending())->toBeFalse();
});

test('isCompleted returns true when status is completed', function () {
    $review = new CodeReview;
    $review->setRawAttributes(['status' => 'completed']);
    expect($review->isCompleted())->toBeTrue();
});

test('isCompleted returns false when status is not completed', function () {
    $review = new CodeReview;
    $review->setRawAttributes(['status' => 'analyzing']);
    expect($review->isCompleted())->toBeFalse();
});

test('isFailed returns true when status is failed', function () {
    $review = new CodeReview;
    $review->setRawAttributes(['status' => 'failed']);
    expect($review->isFailed())->toBeTrue();
});

test('isFailed returns false when status is not failed', function () {
    $review = new CodeReview;
    $review->setRawAttributes(['status' => 'completed']);
    expect($review->isFailed())->toBeFalse();
});

test('isAnalyzing returns true when status is analyzing', function () {
    $review = new CodeReview;
    $review->setRawAttributes(['status' => 'analyzing']);
    expect($review->isAnalyzing())->toBeTrue();
});

test('isAnalyzing returns false when status is not analyzing', function () {
    $review = new CodeReview;
    $review->setRawAttributes(['status' => 'pending']);
    expect($review->isAnalyzing())->toBeFalse();
});

// ─── hasViolations / hasCriticalViolations ────────────────────────────────────

test('hasViolations returns false when violations_count is zero', function () {
    $review = new CodeReview;
    $review->setRawAttributes(['violations_count' => 0]);
    expect($review->hasViolations())->toBeFalse();
});

test('hasViolations returns true when violations_count is positive', function () {
    $review = new CodeReview;
    $review->setRawAttributes(['violations_count' => 3]);
    expect($review->hasViolations())->toBeTrue();
});

test('hasCriticalViolations returns false when critical_count is zero', function () {
    $review = new CodeReview;
    $review->setRawAttributes(['critical_count' => 0]);
    expect($review->hasCriticalViolations())->toBeFalse();
});

test('hasCriticalViolations returns true when critical_count is positive', function () {
    $review = new CodeReview;
    $review->setRawAttributes(['critical_count' => 2]);
    expect($review->hasCriticalViolations())->toBeTrue();
});

// ─── status_label computed attribute ─────────────────────────────────────────

test('status_label is Pending for pending status', function () {
    $review = new CodeReview;
    $review->setRawAttributes(['status' => 'pending', 'violations_count' => 0, 'critical_count' => 0]);
    expect($review->status_label)->toBe('Pending');
});

test('status_label is Analyzing for analyzing status', function () {
    $review = new CodeReview;
    $review->setRawAttributes(['status' => 'analyzing', 'violations_count' => 0, 'critical_count' => 0]);
    expect($review->status_label)->toBe('Analyzing');
});

test('status_label is Passed for completed review with no violations', function () {
    $review = new CodeReview;
    $review->setRawAttributes(['status' => 'completed', 'violations_count' => 0, 'critical_count' => 0]);
    expect($review->status_label)->toBe('Passed');
});

test('status_label is Issues Found for completed review with violations but no critical', function () {
    $review = new CodeReview;
    $review->setRawAttributes(['status' => 'completed', 'violations_count' => 5, 'critical_count' => 0]);
    expect($review->status_label)->toBe('Issues Found');
});

test('status_label is Critical Issues for completed review with critical violations', function () {
    $review = new CodeReview;
    $review->setRawAttributes(['status' => 'completed', 'violations_count' => 5, 'critical_count' => 2]);
    expect($review->status_label)->toBe('Critical Issues');
});

test('status_label is Failed for failed status', function () {
    $review = new CodeReview;
    $review->setRawAttributes(['status' => 'failed', 'violations_count' => 0, 'critical_count' => 0]);
    expect($review->status_label)->toBe('Failed');
});

// ─── status_color computed attribute ─────────────────────────────────────────

test('status_color is gray for pending status', function () {
    $review = new CodeReview;
    $review->setRawAttributes(['status' => 'pending', 'violations_count' => 0, 'critical_count' => 0]);
    expect($review->status_color)->toBe('gray');
});

test('status_color is blue for analyzing status', function () {
    $review = new CodeReview;
    $review->setRawAttributes(['status' => 'analyzing', 'violations_count' => 0, 'critical_count' => 0]);
    expect($review->status_color)->toBe('blue');
});

test('status_color is green for completed review with no violations', function () {
    $review = new CodeReview;
    $review->setRawAttributes(['status' => 'completed', 'violations_count' => 0, 'critical_count' => 0]);
    expect($review->status_color)->toBe('green');
});

test('status_color is yellow for completed review with violations but no critical', function () {
    $review = new CodeReview;
    $review->setRawAttributes(['status' => 'completed', 'violations_count' => 3, 'critical_count' => 0]);
    expect($review->status_color)->toBe('yellow');
});

test('status_color is red for completed review with critical violations', function () {
    $review = new CodeReview;
    $review->setRawAttributes(['status' => 'completed', 'violations_count' => 3, 'critical_count' => 1]);
    expect($review->status_color)->toBe('red');
});

test('status_color is gray for failed status', function () {
    $review = new CodeReview;
    $review->setRawAttributes(['status' => 'failed', 'violations_count' => 0, 'critical_count' => 0]);
    expect($review->status_color)->toBe('gray');
});
