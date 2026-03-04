<?php

/**
 * Unit tests for DeploymentApproval model.
 *
 * Tests cover:
 * - isPending() / isApproved() / isRejected() — pure status-check logic
 * - Default attributes and fillable contract
 */

use App\Models\DeploymentApproval;

// ─── isPending() ──────────────────────────────────────────────────────────────

test('isPending returns true when status is pending', function () {
    $approval = new DeploymentApproval;
    $approval->setRawAttributes(['status' => 'pending']);
    expect($approval->isPending())->toBeTrue();
});

test('isPending returns false when status is approved', function () {
    $approval = new DeploymentApproval;
    $approval->setRawAttributes(['status' => 'approved']);
    expect($approval->isPending())->toBeFalse();
});

test('isPending returns false when status is rejected', function () {
    $approval = new DeploymentApproval;
    $approval->setRawAttributes(['status' => 'rejected']);
    expect($approval->isPending())->toBeFalse();
});

// ─── isApproved() ────────────────────────────────────────────────────────────

test('isApproved returns true when status is approved', function () {
    $approval = new DeploymentApproval;
    $approval->setRawAttributes(['status' => 'approved']);
    expect($approval->isApproved())->toBeTrue();
});

test('isApproved returns false when status is pending', function () {
    $approval = new DeploymentApproval;
    $approval->setRawAttributes(['status' => 'pending']);
    expect($approval->isApproved())->toBeFalse();
});

test('isApproved returns false when status is rejected', function () {
    $approval = new DeploymentApproval;
    $approval->setRawAttributes(['status' => 'rejected']);
    expect($approval->isApproved())->toBeFalse();
});

// ─── isRejected() ────────────────────────────────────────────────────────────

test('isRejected returns true when status is rejected', function () {
    $approval = new DeploymentApproval;
    $approval->setRawAttributes(['status' => 'rejected']);
    expect($approval->isRejected())->toBeTrue();
});

test('isRejected returns false when status is pending', function () {
    $approval = new DeploymentApproval;
    $approval->setRawAttributes(['status' => 'pending']);
    expect($approval->isRejected())->toBeFalse();
});

test('isRejected returns false when status is approved', function () {
    $approval = new DeploymentApproval;
    $approval->setRawAttributes(['status' => 'approved']);
    expect($approval->isRejected())->toBeFalse();
});

// ─── Exactly one status method is true at a time ─────────────────────────────

test('only isPending is true for pending status', function () {
    $approval = new DeploymentApproval;
    $approval->setRawAttributes(['status' => 'pending']);

    expect($approval->isPending())->toBeTrue();
    expect($approval->isApproved())->toBeFalse();
    expect($approval->isRejected())->toBeFalse();
});

test('only isApproved is true for approved status', function () {
    $approval = new DeploymentApproval;
    $approval->setRawAttributes(['status' => 'approved']);

    expect($approval->isPending())->toBeFalse();
    expect($approval->isApproved())->toBeTrue();
    expect($approval->isRejected())->toBeFalse();
});

test('only isRejected is true for rejected status', function () {
    $approval = new DeploymentApproval;
    $approval->setRawAttributes(['status' => 'rejected']);

    expect($approval->isPending())->toBeFalse();
    expect($approval->isApproved())->toBeFalse();
    expect($approval->isRejected())->toBeTrue();
});

// ─── Fillable contract ────────────────────────────────────────────────────────

test('DeploymentApproval fillable includes application_deployment_queue_id', function () {
    $approval = new DeploymentApproval;
    expect(in_array('application_deployment_queue_id', $approval->getFillable()))->toBeTrue();
});

test('DeploymentApproval fillable includes requested_by', function () {
    $approval = new DeploymentApproval;
    expect(in_array('requested_by', $approval->getFillable()))->toBeTrue();
});

test('DeploymentApproval fillable does not include status', function () {
    // status is system-managed, not mass-assignable
    $approval = new DeploymentApproval;
    expect(in_array('status', $approval->getFillable()))->toBeFalse();
});

test('DeploymentApproval fillable does not include approved_by', function () {
    // approved_by is system-managed, not mass-assignable
    $approval = new DeploymentApproval;
    expect(in_array('approved_by', $approval->getFillable()))->toBeFalse();
});
