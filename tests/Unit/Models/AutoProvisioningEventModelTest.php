<?php

/**
 * Unit tests for AutoProvisioningEvent model.
 *
 * Tests cover:
 * - STATUS_* and TRIGGER_* constants
 * - getTriggerReasonLabelAttribute() — human-readable trigger label
 * - getStatusLabelAttribute() — human-readable status label
 */

use App\Models\AutoProvisioningEvent;

// ─── STATUS_ constants ────────────────────────────────────────────────────────

test('STATUS_PENDING constant is pending', function () {
    expect(AutoProvisioningEvent::STATUS_PENDING)->toBe('pending');
});

test('STATUS_PROVISIONING constant is provisioning', function () {
    expect(AutoProvisioningEvent::STATUS_PROVISIONING)->toBe('provisioning');
});

test('STATUS_INSTALLING constant is installing', function () {
    expect(AutoProvisioningEvent::STATUS_INSTALLING)->toBe('installing');
});

test('STATUS_READY constant is ready', function () {
    expect(AutoProvisioningEvent::STATUS_READY)->toBe('ready');
});

test('STATUS_FAILED constant is failed', function () {
    expect(AutoProvisioningEvent::STATUS_FAILED)->toBe('failed');
});

// ─── TRIGGER_ constants ───────────────────────────────────────────────────────

test('TRIGGER_CPU_CRITICAL constant is cpu_critical', function () {
    expect(AutoProvisioningEvent::TRIGGER_CPU_CRITICAL)->toBe('cpu_critical');
});

test('TRIGGER_MEMORY_CRITICAL constant is memory_critical', function () {
    expect(AutoProvisioningEvent::TRIGGER_MEMORY_CRITICAL)->toBe('memory_critical');
});

test('TRIGGER_MANUAL constant is manual', function () {
    expect(AutoProvisioningEvent::TRIGGER_MANUAL)->toBe('manual');
});

// ─── getTriggerReasonLabelAttribute() ────────────────────────────────────────

test('trigger_reason_label is CPU Overload for cpu_critical', function () {
    $event = new AutoProvisioningEvent;
    $event->setRawAttributes(['trigger_reason' => 'cpu_critical']);
    expect($event->trigger_reason_label)->toBe('CPU Overload');
});

test('trigger_reason_label is Memory Overload for memory_critical', function () {
    $event = new AutoProvisioningEvent;
    $event->setRawAttributes(['trigger_reason' => 'memory_critical']);
    expect($event->trigger_reason_label)->toBe('Memory Overload');
});

test('trigger_reason_label is Manual Request for manual', function () {
    $event = new AutoProvisioningEvent;
    $event->setRawAttributes(['trigger_reason' => 'manual']);
    expect($event->trigger_reason_label)->toBe('Manual Request');
});

test('trigger_reason_label falls back to raw value for unknown trigger', function () {
    $event = new AutoProvisioningEvent;
    $event->setRawAttributes(['trigger_reason' => 'custom_trigger']);
    expect($event->trigger_reason_label)->toBe('custom_trigger');
});

// ─── getStatusLabelAttribute() ────────────────────────────────────────────────

test('status_label is Pending for pending status', function () {
    $event = new AutoProvisioningEvent;
    $event->setRawAttributes(['status' => 'pending']);
    expect($event->status_label)->toBe('Pending');
});

test('status_label is Creating VPS for provisioning status', function () {
    $event = new AutoProvisioningEvent;
    $event->setRawAttributes(['status' => 'provisioning']);
    expect($event->status_label)->toBe('Creating VPS');
});

test('status_label is Installing Docker for installing status', function () {
    $event = new AutoProvisioningEvent;
    $event->setRawAttributes(['status' => 'installing']);
    expect($event->status_label)->toBe('Installing Docker');
});

test('status_label is Ready for ready status', function () {
    $event = new AutoProvisioningEvent;
    $event->setRawAttributes(['status' => 'ready']);
    expect($event->status_label)->toBe('Ready');
});

test('status_label is Failed for failed status', function () {
    $event = new AutoProvisioningEvent;
    $event->setRawAttributes(['status' => 'failed']);
    expect($event->status_label)->toBe('Failed');
});

test('status_label falls back to raw status for unknown status', function () {
    $event = new AutoProvisioningEvent;
    $event->setRawAttributes(['status' => 'unknown_state']);
    expect($event->status_label)->toBe('unknown_state');
});
