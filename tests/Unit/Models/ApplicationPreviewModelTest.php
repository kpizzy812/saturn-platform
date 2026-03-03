<?php

/**
 * Unit tests for ApplicationPreview model.
 *
 * Tests cover:
 * - isRunning() — status starts with 'running' (including health suffix)
 * - Fillable contract
 */

use App\Models\ApplicationPreview;

// ─── isRunning() ──────────────────────────────────────────────────────────────

test('isRunning returns true when status is running', function () {
    $preview = new ApplicationPreview;
    $preview->setRawAttributes(['status' => 'running']);
    expect($preview->isRunning())->toBeTrue();
});

test('isRunning returns true when status is running (healthy)', function () {
    $preview = new ApplicationPreview;
    $preview->setRawAttributes(['status' => 'running (healthy)']);
    expect($preview->isRunning())->toBeTrue();
});

test('isRunning returns true when status is running (unhealthy)', function () {
    $preview = new ApplicationPreview;
    $preview->setRawAttributes(['status' => 'running (unhealthy)']);
    expect($preview->isRunning())->toBeTrue();
});

test('isRunning returns false when status is stopped', function () {
    $preview = new ApplicationPreview;
    $preview->setRawAttributes(['status' => 'stopped']);
    expect($preview->isRunning())->toBeFalse();
});

test('isRunning returns false when status is exited', function () {
    $preview = new ApplicationPreview;
    $preview->setRawAttributes(['status' => 'exited']);
    expect($preview->isRunning())->toBeFalse();
});

test('isRunning returns false when status is restarting', function () {
    $preview = new ApplicationPreview;
    $preview->setRawAttributes(['status' => 'restarting']);
    expect($preview->isRunning())->toBeFalse();
});

test('isRunning returns false when status is null', function () {
    $preview = new ApplicationPreview;
    $preview->setRawAttributes(['status' => null]);
    expect($preview->isRunning())->toBeFalse();
});

// ─── Fillable contract ────────────────────────────────────────────────────────

test('fillable includes pull_request_id', function () {
    expect(in_array('pull_request_id', (new ApplicationPreview)->getFillable()))->toBeTrue();
});

test('fillable includes fqdn', function () {
    expect(in_array('fqdn', (new ApplicationPreview)->getFillable()))->toBeTrue();
});

test('fillable includes status', function () {
    expect(in_array('status', (new ApplicationPreview)->getFillable()))->toBeTrue();
});

test('fillable includes application_id', function () {
    expect(in_array('application_id', (new ApplicationPreview)->getFillable()))->toBeTrue();
});
