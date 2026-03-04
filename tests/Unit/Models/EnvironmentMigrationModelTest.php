<?php

/**
 * Unit tests for EnvironmentMigration model.
 *
 * Tests cover:
 * - STATUS_* and MODE_* constants
 * - Status-check methods: isPending/isApproved/isRejected/isInProgress/
 *   isCompleted/isFailed/isRolledBack
 * - Composite logic: isAwaitingApproval, canBeExecuted, canBeCancelled
 */

use App\Models\EnvironmentMigration;

// ─── STATUS_ constants ────────────────────────────────────────────────────────

test('STATUS_PENDING constant is pending', function () {
    expect(EnvironmentMigration::STATUS_PENDING)->toBe('pending');
});

test('STATUS_APPROVED constant is approved', function () {
    expect(EnvironmentMigration::STATUS_APPROVED)->toBe('approved');
});

test('STATUS_REJECTED constant is rejected', function () {
    expect(EnvironmentMigration::STATUS_REJECTED)->toBe('rejected');
});

test('STATUS_IN_PROGRESS constant is in_progress', function () {
    expect(EnvironmentMigration::STATUS_IN_PROGRESS)->toBe('in_progress');
});

test('STATUS_COMPLETED constant is completed', function () {
    expect(EnvironmentMigration::STATUS_COMPLETED)->toBe('completed');
});

test('STATUS_FAILED constant is failed', function () {
    expect(EnvironmentMigration::STATUS_FAILED)->toBe('failed');
});

test('STATUS_ROLLED_BACK constant is rolled_back', function () {
    expect(EnvironmentMigration::STATUS_ROLLED_BACK)->toBe('rolled_back');
});

test('STATUS_CANCELLED constant is cancelled', function () {
    expect(EnvironmentMigration::STATUS_CANCELLED)->toBe('cancelled');
});

// ─── MODE_ constants ──────────────────────────────────────────────────────────

test('MODE_CLONE constant is clone', function () {
    expect(EnvironmentMigration::MODE_CLONE)->toBe('clone');
});

test('MODE_PROMOTE constant is promote', function () {
    expect(EnvironmentMigration::MODE_PROMOTE)->toBe('promote');
});

// ─── Simple status-check methods ─────────────────────────────────────────────

test('isPending returns true for pending status', function () {
    $m = new EnvironmentMigration;
    $m->setRawAttributes(['status' => 'pending']);
    expect($m->isPending())->toBeTrue();
});

test('isPending returns false for completed status', function () {
    $m = new EnvironmentMigration;
    $m->setRawAttributes(['status' => 'completed']);
    expect($m->isPending())->toBeFalse();
});

test('isApproved returns true for approved status', function () {
    $m = new EnvironmentMigration;
    $m->setRawAttributes(['status' => 'approved']);
    expect($m->isApproved())->toBeTrue();
});

test('isRejected returns true for rejected status', function () {
    $m = new EnvironmentMigration;
    $m->setRawAttributes(['status' => 'rejected']);
    expect($m->isRejected())->toBeTrue();
});

test('isInProgress returns true for in_progress status', function () {
    $m = new EnvironmentMigration;
    $m->setRawAttributes(['status' => 'in_progress']);
    expect($m->isInProgress())->toBeTrue();
});

test('isCompleted returns true for completed status', function () {
    $m = new EnvironmentMigration;
    $m->setRawAttributes(['status' => 'completed']);
    expect($m->isCompleted())->toBeTrue();
});

test('isFailed returns true for failed status', function () {
    $m = new EnvironmentMigration;
    $m->setRawAttributes(['status' => 'failed']);
    expect($m->isFailed())->toBeTrue();
});

test('isRolledBack returns true for rolled_back status', function () {
    $m = new EnvironmentMigration;
    $m->setRawAttributes(['status' => 'rolled_back']);
    expect($m->isRolledBack())->toBeTrue();
});

test('isRolledBack returns false for failed status', function () {
    $m = new EnvironmentMigration;
    $m->setRawAttributes(['status' => 'failed']);
    expect($m->isRolledBack())->toBeFalse();
});

// ─── isAwaitingApproval() ─────────────────────────────────────────────────────

test('isAwaitingApproval returns true when requires_approval and status is pending', function () {
    $m = new EnvironmentMigration;
    $m->setRawAttributes(['status' => 'pending', 'requires_approval' => true]);
    expect($m->isAwaitingApproval())->toBeTrue();
});

test('isAwaitingApproval returns false when requires_approval is false even if pending', function () {
    $m = new EnvironmentMigration;
    $m->setRawAttributes(['status' => 'pending', 'requires_approval' => false]);
    expect($m->isAwaitingApproval())->toBeFalse();
});

test('isAwaitingApproval returns false when requires_approval but status is not pending', function () {
    $m = new EnvironmentMigration;
    $m->setRawAttributes(['status' => 'approved', 'requires_approval' => true]);
    expect($m->isAwaitingApproval())->toBeFalse();
});

// ─── canBeExecuted() ─────────────────────────────────────────────────────────

test('canBeExecuted returns true when requires_approval and status is approved', function () {
    $m = new EnvironmentMigration;
    $m->setRawAttributes(['status' => 'approved', 'requires_approval' => true]);
    expect($m->canBeExecuted())->toBeTrue();
});

test('canBeExecuted returns false when requires_approval and status is pending', function () {
    $m = new EnvironmentMigration;
    $m->setRawAttributes(['status' => 'pending', 'requires_approval' => true]);
    expect($m->canBeExecuted())->toBeFalse();
});

test('canBeExecuted returns true when no approval required and status is pending', function () {
    $m = new EnvironmentMigration;
    $m->setRawAttributes(['status' => 'pending', 'requires_approval' => false]);
    expect($m->canBeExecuted())->toBeTrue();
});

test('canBeExecuted returns false when no approval required and status is completed', function () {
    $m = new EnvironmentMigration;
    $m->setRawAttributes(['status' => 'completed', 'requires_approval' => false]);
    expect($m->canBeExecuted())->toBeFalse();
});

// ─── canBeCancelled() ────────────────────────────────────────────────────────

test('canBeCancelled returns true for pending status', function () {
    $m = new EnvironmentMigration;
    $m->setRawAttributes(['status' => 'pending']);
    expect($m->canBeCancelled())->toBeTrue();
});

test('canBeCancelled returns true for approved status', function () {
    $m = new EnvironmentMigration;
    $m->setRawAttributes(['status' => 'approved']);
    expect($m->canBeCancelled())->toBeTrue();
});

test('canBeCancelled returns false for in_progress status', function () {
    $m = new EnvironmentMigration;
    $m->setRawAttributes(['status' => 'in_progress']);
    expect($m->canBeCancelled())->toBeFalse();
});

test('canBeCancelled returns false for completed status', function () {
    $m = new EnvironmentMigration;
    $m->setRawAttributes(['status' => 'completed']);
    expect($m->canBeCancelled())->toBeFalse();
});

test('canBeCancelled returns false for failed status', function () {
    $m = new EnvironmentMigration;
    $m->setRawAttributes(['status' => 'failed']);
    expect($m->canBeCancelled())->toBeFalse();
});
