<?php

/**
 * Unit tests for DatabaseImport model.
 *
 * Tests cover:
 * - isPending() / isInProgress() / isCompleted() / isFailed() — status-check methods
 * - Mutual exclusion: exactly one status method is true at a time
 * - Fillable contract (key fields)
 */

use App\Models\DatabaseImport;

// ─── isPending() ──────────────────────────────────────────────────────────────

test('isPending returns true when status is pending', function () {
    $import = new DatabaseImport;
    $import->setRawAttributes(['status' => 'pending']);
    expect($import->isPending())->toBeTrue();
});

test('isPending returns false when status is in_progress', function () {
    $import = new DatabaseImport;
    $import->setRawAttributes(['status' => 'in_progress']);
    expect($import->isPending())->toBeFalse();
});

test('isPending returns false when status is completed', function () {
    $import = new DatabaseImport;
    $import->setRawAttributes(['status' => 'completed']);
    expect($import->isPending())->toBeFalse();
});

// ─── isInProgress() ───────────────────────────────────────────────────────────

test('isInProgress returns true when status is in_progress', function () {
    $import = new DatabaseImport;
    $import->setRawAttributes(['status' => 'in_progress']);
    expect($import->isInProgress())->toBeTrue();
});

test('isInProgress returns false when status is pending', function () {
    $import = new DatabaseImport;
    $import->setRawAttributes(['status' => 'pending']);
    expect($import->isInProgress())->toBeFalse();
});

test('isInProgress returns false when status is failed', function () {
    $import = new DatabaseImport;
    $import->setRawAttributes(['status' => 'failed']);
    expect($import->isInProgress())->toBeFalse();
});

// ─── isCompleted() ────────────────────────────────────────────────────────────

test('isCompleted returns true when status is completed', function () {
    $import = new DatabaseImport;
    $import->setRawAttributes(['status' => 'completed']);
    expect($import->isCompleted())->toBeTrue();
});

test('isCompleted returns false when status is in_progress', function () {
    $import = new DatabaseImport;
    $import->setRawAttributes(['status' => 'in_progress']);
    expect($import->isCompleted())->toBeFalse();
});

// ─── isFailed() ───────────────────────────────────────────────────────────────

test('isFailed returns true when status is failed', function () {
    $import = new DatabaseImport;
    $import->setRawAttributes(['status' => 'failed']);
    expect($import->isFailed())->toBeTrue();
});

test('isFailed returns false when status is completed', function () {
    $import = new DatabaseImport;
    $import->setRawAttributes(['status' => 'completed']);
    expect($import->isFailed())->toBeFalse();
});

// ─── Mutual exclusion: exactly one status is true ────────────────────────────

test('only isPending is true for pending status', function () {
    $import = new DatabaseImport;
    $import->setRawAttributes(['status' => 'pending']);

    expect($import->isPending())->toBeTrue();
    expect($import->isInProgress())->toBeFalse();
    expect($import->isCompleted())->toBeFalse();
    expect($import->isFailed())->toBeFalse();
});

test('only isInProgress is true for in_progress status', function () {
    $import = new DatabaseImport;
    $import->setRawAttributes(['status' => 'in_progress']);

    expect($import->isPending())->toBeFalse();
    expect($import->isInProgress())->toBeTrue();
    expect($import->isCompleted())->toBeFalse();
    expect($import->isFailed())->toBeFalse();
});

test('only isCompleted is true for completed status', function () {
    $import = new DatabaseImport;
    $import->setRawAttributes(['status' => 'completed']);

    expect($import->isPending())->toBeFalse();
    expect($import->isInProgress())->toBeFalse();
    expect($import->isCompleted())->toBeTrue();
    expect($import->isFailed())->toBeFalse();
});

test('only isFailed is true for failed status', function () {
    $import = new DatabaseImport;
    $import->setRawAttributes(['status' => 'failed']);

    expect($import->isPending())->toBeFalse();
    expect($import->isInProgress())->toBeFalse();
    expect($import->isCompleted())->toBeFalse();
    expect($import->isFailed())->toBeTrue();
});

// ─── Fillable contract ────────────────────────────────────────────────────────

test('fillable includes mode', function () {
    $import = new DatabaseImport;
    expect(in_array('mode', $import->getFillable()))->toBeTrue();
});

test('fillable includes status', function () {
    $import = new DatabaseImport;
    expect(in_array('status', $import->getFillable()))->toBeTrue();
});

test('fillable includes progress', function () {
    $import = new DatabaseImport;
    expect(in_array('progress', $import->getFillable()))->toBeTrue();
});

test('fillable includes connection_string', function () {
    $import = new DatabaseImport;
    expect(in_array('connection_string', $import->getFillable()))->toBeTrue();
});
