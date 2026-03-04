<?php

/**
 * Unit tests for AiUsageLog model.
 *
 * Tests cover:
 * - Default attributes (tokens, cost, success flag)
 * - getTotalTokensAttribute() — sums input + output tokens
 * - isSuccessful() — reflects the success flag
 * - Fillable contract
 */

use App\Models\AiUsageLog;

// ─── Default attributes ───────────────────────────────────────────────────────

test('default input_tokens is 0', function () {
    $log = new AiUsageLog;
    expect($log->input_tokens)->toBe(0);
});

test('default output_tokens is 0', function () {
    $log = new AiUsageLog;
    expect($log->output_tokens)->toBe(0);
});

test('default success is true', function () {
    $log = new AiUsageLog;
    expect($log->success)->toBeTrue();
});

// ─── getTotalTokensAttribute() ────────────────────────────────────────────────

test('total_tokens is zero when both input and output are zero', function () {
    $log = new AiUsageLog;
    $log->setRawAttributes(['input_tokens' => 0, 'output_tokens' => 0]);
    expect($log->total_tokens)->toBe(0);
});

test('total_tokens sums input and output tokens', function () {
    $log = new AiUsageLog;
    $log->setRawAttributes(['input_tokens' => 1000, 'output_tokens' => 500]);
    expect($log->total_tokens)->toBe(1500);
});

test('total_tokens works when only input tokens are set', function () {
    $log = new AiUsageLog;
    $log->setRawAttributes(['input_tokens' => 800, 'output_tokens' => 0]);
    expect($log->total_tokens)->toBe(800);
});

test('total_tokens works when only output tokens are set', function () {
    $log = new AiUsageLog;
    $log->setRawAttributes(['input_tokens' => 0, 'output_tokens' => 200]);
    expect($log->total_tokens)->toBe(200);
});

// ─── isSuccessful() ───────────────────────────────────────────────────────────

test('isSuccessful returns true when success is true', function () {
    $log = new AiUsageLog;
    $log->setRawAttributes(['success' => 1]);
    expect($log->isSuccessful())->toBeTrue();
});

test('isSuccessful returns false when success is false', function () {
    $log = new AiUsageLog;
    $log->setRawAttributes(['success' => 0]);
    expect($log->isSuccessful())->toBeFalse();
});

// ─── Fillable contract ────────────────────────────────────────────────────────

test('fillable includes provider', function () {
    $log = new AiUsageLog;
    expect(in_array('provider', $log->getFillable()))->toBeTrue();
});

test('fillable includes model', function () {
    $log = new AiUsageLog;
    expect(in_array('model', $log->getFillable()))->toBeTrue();
});

test('fillable includes input_tokens', function () {
    $log = new AiUsageLog;
    expect(in_array('input_tokens', $log->getFillable()))->toBeTrue();
});

test('fillable includes output_tokens', function () {
    $log = new AiUsageLog;
    expect(in_array('output_tokens', $log->getFillable()))->toBeTrue();
});

test('fillable includes cost_usd', function () {
    $log = new AiUsageLog;
    expect(in_array('cost_usd', $log->getFillable()))->toBeTrue();
});
