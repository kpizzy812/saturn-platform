<?php

/**
 * Unit tests for TeamResourceTransfer model.
 *
 * Tests cover:
 * - STATUS_* and TYPE_* constants
 * - getAllStatuses() / getAllTypes() — static catalogs
 * - isInProgress() — true for pending and in_progress
 * - isCompleted() / isFailed() — exact status checks
 * - getStatusLabelAttribute() — human-readable status
 * - getTypeLabelAttribute() — human-readable transfer type
 * - getResourceTypeNameAttribute() — class basename from transferable_type
 */

use App\Models\TeamResourceTransfer;

// ─── STATUS_ constants ────────────────────────────────────────────────────────

test('STATUS_PENDING constant is pending', function () {
    expect(TeamResourceTransfer::STATUS_PENDING)->toBe('pending');
});

test('STATUS_IN_PROGRESS constant is in_progress', function () {
    expect(TeamResourceTransfer::STATUS_IN_PROGRESS)->toBe('in_progress');
});

test('STATUS_COMPLETED constant is completed', function () {
    expect(TeamResourceTransfer::STATUS_COMPLETED)->toBe('completed');
});

test('STATUS_FAILED constant is failed', function () {
    expect(TeamResourceTransfer::STATUS_FAILED)->toBe('failed');
});

test('STATUS_ROLLED_BACK constant is rolled_back', function () {
    expect(TeamResourceTransfer::STATUS_ROLLED_BACK)->toBe('rolled_back');
});

// ─── TYPE_ constants ──────────────────────────────────────────────────────────

test('TYPE_PROJECT_TRANSFER constant is project_transfer', function () {
    expect(TeamResourceTransfer::TYPE_PROJECT_TRANSFER)->toBe('project_transfer');
});

test('TYPE_TEAM_OWNERSHIP constant is team_ownership', function () {
    expect(TeamResourceTransfer::TYPE_TEAM_OWNERSHIP)->toBe('team_ownership');
});

test('TYPE_TEAM_MERGE constant is team_merge', function () {
    expect(TeamResourceTransfer::TYPE_TEAM_MERGE)->toBe('team_merge');
});

test('TYPE_USER_DELETION constant is user_deletion', function () {
    expect(TeamResourceTransfer::TYPE_USER_DELETION)->toBe('user_deletion');
});

test('TYPE_ARCHIVE constant is archive', function () {
    expect(TeamResourceTransfer::TYPE_ARCHIVE)->toBe('archive');
});

// ─── getAllStatuses() ─────────────────────────────────────────────────────────

test('getAllStatuses returns array with 5 statuses', function () {
    expect(TeamResourceTransfer::getAllStatuses())->toHaveCount(5);
});

test('getAllStatuses includes all status values', function () {
    $statuses = TeamResourceTransfer::getAllStatuses();
    expect($statuses)->toContain('pending');
    expect($statuses)->toContain('in_progress');
    expect($statuses)->toContain('completed');
    expect($statuses)->toContain('failed');
    expect($statuses)->toContain('rolled_back');
});

// ─── getAllTypes() ────────────────────────────────────────────────────────────

test('getAllTypes returns array with 5 types', function () {
    expect(TeamResourceTransfer::getAllTypes())->toHaveCount(5);
});

test('getAllTypes includes all type values', function () {
    $types = TeamResourceTransfer::getAllTypes();
    expect($types)->toContain('project_transfer');
    expect($types)->toContain('team_ownership');
    expect($types)->toContain('team_merge');
    expect($types)->toContain('user_deletion');
    expect($types)->toContain('archive');
});

// ─── isInProgress() ───────────────────────────────────────────────────────────

test('isInProgress returns true for pending status', function () {
    $transfer = new TeamResourceTransfer;
    $transfer->setRawAttributes(['status' => 'pending']);
    expect($transfer->isInProgress())->toBeTrue();
});

test('isInProgress returns true for in_progress status', function () {
    $transfer = new TeamResourceTransfer;
    $transfer->setRawAttributes(['status' => 'in_progress']);
    expect($transfer->isInProgress())->toBeTrue();
});

test('isInProgress returns false for completed status', function () {
    $transfer = new TeamResourceTransfer;
    $transfer->setRawAttributes(['status' => 'completed']);
    expect($transfer->isInProgress())->toBeFalse();
});

test('isInProgress returns false for failed status', function () {
    $transfer = new TeamResourceTransfer;
    $transfer->setRawAttributes(['status' => 'failed']);
    expect($transfer->isInProgress())->toBeFalse();
});

test('isInProgress returns false for rolled_back status', function () {
    $transfer = new TeamResourceTransfer;
    $transfer->setRawAttributes(['status' => 'rolled_back']);
    expect($transfer->isInProgress())->toBeFalse();
});

// ─── isCompleted() ────────────────────────────────────────────────────────────

test('isCompleted returns true for completed status', function () {
    $transfer = new TeamResourceTransfer;
    $transfer->setRawAttributes(['status' => 'completed']);
    expect($transfer->isCompleted())->toBeTrue();
});

test('isCompleted returns false for failed status', function () {
    $transfer = new TeamResourceTransfer;
    $transfer->setRawAttributes(['status' => 'failed']);
    expect($transfer->isCompleted())->toBeFalse();
});

test('isCompleted returns false for pending status', function () {
    $transfer = new TeamResourceTransfer;
    $transfer->setRawAttributes(['status' => 'pending']);
    expect($transfer->isCompleted())->toBeFalse();
});

// ─── isFailed() ───────────────────────────────────────────────────────────────

test('isFailed returns true for failed status', function () {
    $transfer = new TeamResourceTransfer;
    $transfer->setRawAttributes(['status' => 'failed']);
    expect($transfer->isFailed())->toBeTrue();
});

test('isFailed returns false for completed status', function () {
    $transfer = new TeamResourceTransfer;
    $transfer->setRawAttributes(['status' => 'completed']);
    expect($transfer->isFailed())->toBeFalse();
});

// ─── getStatusLabelAttribute() ────────────────────────────────────────────────

test('status_label is Pending for pending', function () {
    $transfer = new TeamResourceTransfer;
    $transfer->setRawAttributes(['status' => 'pending']);
    expect($transfer->status_label)->toBe('Pending');
});

test('status_label is In Progress for in_progress', function () {
    $transfer = new TeamResourceTransfer;
    $transfer->setRawAttributes(['status' => 'in_progress']);
    expect($transfer->status_label)->toBe('In Progress');
});

test('status_label is Completed for completed', function () {
    $transfer = new TeamResourceTransfer;
    $transfer->setRawAttributes(['status' => 'completed']);
    expect($transfer->status_label)->toBe('Completed');
});

test('status_label is Failed for failed', function () {
    $transfer = new TeamResourceTransfer;
    $transfer->setRawAttributes(['status' => 'failed']);
    expect($transfer->status_label)->toBe('Failed');
});

test('status_label is Rolled Back for rolled_back', function () {
    $transfer = new TeamResourceTransfer;
    $transfer->setRawAttributes(['status' => 'rolled_back']);
    expect($transfer->status_label)->toBe('Rolled Back');
});

test('status_label falls back to raw status for unknown value', function () {
    $transfer = new TeamResourceTransfer;
    $transfer->setRawAttributes(['status' => 'custom_status']);
    expect($transfer->status_label)->toBe('custom_status');
});

// ─── getTypeLabelAttribute() ──────────────────────────────────────────────────

test('type_label is Project Transfer for project_transfer', function () {
    $transfer = new TeamResourceTransfer;
    $transfer->setRawAttributes(['transfer_type' => 'project_transfer']);
    expect($transfer->type_label)->toBe('Project Transfer');
});

test('type_label is Team Ownership Change for team_ownership', function () {
    $transfer = new TeamResourceTransfer;
    $transfer->setRawAttributes(['transfer_type' => 'team_ownership']);
    expect($transfer->type_label)->toBe('Team Ownership Change');
});

test('type_label is Team Merge for team_merge', function () {
    $transfer = new TeamResourceTransfer;
    $transfer->setRawAttributes(['transfer_type' => 'team_merge']);
    expect($transfer->type_label)->toBe('Team Merge');
});

test('type_label is User Deletion for user_deletion', function () {
    $transfer = new TeamResourceTransfer;
    $transfer->setRawAttributes(['transfer_type' => 'user_deletion']);
    expect($transfer->type_label)->toBe('User Deletion');
});

test('type_label is Archive for archive', function () {
    $transfer = new TeamResourceTransfer;
    $transfer->setRawAttributes(['transfer_type' => 'archive']);
    expect($transfer->type_label)->toBe('Archive');
});

test('type_label falls back to raw value for unknown type', function () {
    $transfer = new TeamResourceTransfer;
    $transfer->setRawAttributes(['transfer_type' => 'custom_type']);
    expect($transfer->type_label)->toBe('custom_type');
});

// ─── getResourceTypeNameAttribute() ──────────────────────────────────────────

test('resource_type_name returns class basename for full class name', function () {
    $transfer = new TeamResourceTransfer;
    $transfer->setRawAttributes(['transferable_type' => 'App\\Models\\Project']);
    expect($transfer->resource_type_name)->toBe('Project');
});

test('resource_type_name returns class basename for Server', function () {
    $transfer = new TeamResourceTransfer;
    $transfer->setRawAttributes(['transferable_type' => 'App\\Models\\Server']);
    expect($transfer->resource_type_name)->toBe('Server');
});

test('resource_type_name returns Unknown when transferable_type is null', function () {
    $transfer = new TeamResourceTransfer;
    $transfer->setRawAttributes(['transferable_type' => null]);
    expect($transfer->resource_type_name)->toBe('Unknown');
});
