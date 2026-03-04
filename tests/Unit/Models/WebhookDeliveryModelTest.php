<?php

/**
 * Unit tests for WebhookDelivery model.
 *
 * Tests cover:
 * - isSuccess() / isFailed() / isPending() — status checks
 * - Mutual exclusion: exactly one status method is true at a time
 * - Fillable contract
 */

use App\Models\WebhookDelivery;

// ─── isSuccess() ──────────────────────────────────────────────────────────────

test('isSuccess returns true when status is success', function () {
    $delivery = new WebhookDelivery;
    $delivery->setRawAttributes(['status' => 'success']);
    expect($delivery->isSuccess())->toBeTrue();
});

test('isSuccess returns false when status is failed', function () {
    $delivery = new WebhookDelivery;
    $delivery->setRawAttributes(['status' => 'failed']);
    expect($delivery->isSuccess())->toBeFalse();
});

test('isSuccess returns false when status is pending', function () {
    $delivery = new WebhookDelivery;
    $delivery->setRawAttributes(['status' => 'pending']);
    expect($delivery->isSuccess())->toBeFalse();
});

// ─── isFailed() ───────────────────────────────────────────────────────────────

test('isFailed returns true when status is failed', function () {
    $delivery = new WebhookDelivery;
    $delivery->setRawAttributes(['status' => 'failed']);
    expect($delivery->isFailed())->toBeTrue();
});

test('isFailed returns false when status is success', function () {
    $delivery = new WebhookDelivery;
    $delivery->setRawAttributes(['status' => 'success']);
    expect($delivery->isFailed())->toBeFalse();
});

test('isFailed returns false when status is pending', function () {
    $delivery = new WebhookDelivery;
    $delivery->setRawAttributes(['status' => 'pending']);
    expect($delivery->isFailed())->toBeFalse();
});

// ─── isPending() ──────────────────────────────────────────────────────────────

test('isPending returns true when status is pending', function () {
    $delivery = new WebhookDelivery;
    $delivery->setRawAttributes(['status' => 'pending']);
    expect($delivery->isPending())->toBeTrue();
});

test('isPending returns false when status is success', function () {
    $delivery = new WebhookDelivery;
    $delivery->setRawAttributes(['status' => 'success']);
    expect($delivery->isPending())->toBeFalse();
});

test('isPending returns false when status is failed', function () {
    $delivery = new WebhookDelivery;
    $delivery->setRawAttributes(['status' => 'failed']);
    expect($delivery->isPending())->toBeFalse();
});

// ─── Mutual exclusion: exactly one status is true ────────────────────────────

test('only isSuccess is true for success status', function () {
    $delivery = new WebhookDelivery;
    $delivery->setRawAttributes(['status' => 'success']);

    expect($delivery->isSuccess())->toBeTrue();
    expect($delivery->isFailed())->toBeFalse();
    expect($delivery->isPending())->toBeFalse();
});

test('only isFailed is true for failed status', function () {
    $delivery = new WebhookDelivery;
    $delivery->setRawAttributes(['status' => 'failed']);

    expect($delivery->isSuccess())->toBeFalse();
    expect($delivery->isFailed())->toBeTrue();
    expect($delivery->isPending())->toBeFalse();
});

test('only isPending is true for pending status', function () {
    $delivery = new WebhookDelivery;
    $delivery->setRawAttributes(['status' => 'pending']);

    expect($delivery->isSuccess())->toBeFalse();
    expect($delivery->isFailed())->toBeFalse();
    expect($delivery->isPending())->toBeTrue();
});

// ─── Fillable contract ────────────────────────────────────────────────────────

test('fillable includes uuid', function () {
    expect(in_array('uuid', (new WebhookDelivery)->getFillable()))->toBeTrue();
});

test('fillable includes status', function () {
    expect(in_array('status', (new WebhookDelivery)->getFillable()))->toBeTrue();
});

test('fillable includes event', function () {
    expect(in_array('event', (new WebhookDelivery)->getFillable()))->toBeTrue();
});

test('fillable includes attempts', function () {
    expect(in_array('attempts', (new WebhookDelivery)->getFillable()))->toBeTrue();
});

test('fillable includes status_code', function () {
    expect(in_array('status_code', (new WebhookDelivery)->getFillable()))->toBeTrue();
});

test('fillable includes response_time_ms', function () {
    expect(in_array('response_time_ms', (new WebhookDelivery)->getFillable()))->toBeTrue();
});
