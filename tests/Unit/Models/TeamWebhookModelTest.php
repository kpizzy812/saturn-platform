<?php

/**
 * Unit tests for TeamWebhook model.
 *
 * Tests cover:
 * - isEnabled() — reflects enabled boolean
 * - hasEvent() — checks if an event is subscribed
 * - availableEvents() — static catalog of all supported event types
 */

use App\Models\TeamWebhook;

// ─── isEnabled() ──────────────────────────────────────────────────────────────

test('isEnabled returns true when enabled is true', function () {
    $webhook = new TeamWebhook;
    $webhook->setRawAttributes(['enabled' => true]);
    expect($webhook->isEnabled())->toBeTrue();
});

test('isEnabled returns false when enabled is false', function () {
    $webhook = new TeamWebhook;
    $webhook->setRawAttributes(['enabled' => false]);
    expect($webhook->isEnabled())->toBeFalse();
});

// ─── hasEvent() ───────────────────────────────────────────────────────────────

test('hasEvent returns true when event is in the list', function () {
    $webhook = new TeamWebhook;
    $webhook->setRawAttributes(['events' => json_encode(['deploy.started', 'deploy.failed'])]);
    expect($webhook->hasEvent('deploy.started'))->toBeTrue();
});

test('hasEvent returns true for second event in the list', function () {
    $webhook = new TeamWebhook;
    $webhook->setRawAttributes(['events' => json_encode(['deploy.started', 'deploy.failed'])]);
    expect($webhook->hasEvent('deploy.failed'))->toBeTrue();
});

test('hasEvent returns false when event is not in the list', function () {
    $webhook = new TeamWebhook;
    $webhook->setRawAttributes(['events' => json_encode(['deploy.started'])]);
    expect($webhook->hasEvent('deploy.failed'))->toBeFalse();
});

test('hasEvent returns false when events is null', function () {
    $webhook = new TeamWebhook;
    $webhook->setRawAttributes(['events' => null]);
    expect($webhook->hasEvent('deploy.started'))->toBeFalse();
});

test('hasEvent returns false when events is empty array', function () {
    $webhook = new TeamWebhook;
    $webhook->setRawAttributes(['events' => json_encode([])]);
    expect($webhook->hasEvent('deploy.started'))->toBeFalse();
});

// ─── availableEvents() ────────────────────────────────────────────────────────

test('availableEvents returns an array', function () {
    expect(TeamWebhook::availableEvents())->toBeArray();
});

test('availableEvents returns 8 events', function () {
    expect(TeamWebhook::availableEvents())->toHaveCount(8);
});

test('each available event has value, label, and description keys', function () {
    foreach (TeamWebhook::availableEvents() as $event) {
        expect($event)->toHaveKeys(['value', 'label', 'description']);
    }
});

test('availableEvents includes deploy.started', function () {
    $values = array_column(TeamWebhook::availableEvents(), 'value');
    expect(in_array('deploy.started', $values))->toBeTrue();
});

test('availableEvents includes deploy.finished', function () {
    $values = array_column(TeamWebhook::availableEvents(), 'value');
    expect(in_array('deploy.finished', $values))->toBeTrue();
});

test('availableEvents includes deploy.failed', function () {
    $values = array_column(TeamWebhook::availableEvents(), 'value');
    expect(in_array('deploy.failed', $values))->toBeTrue();
});

test('availableEvents includes server.unreachable', function () {
    $values = array_column(TeamWebhook::availableEvents(), 'value');
    expect(in_array('server.unreachable', $values))->toBeTrue();
});

test('availableEvents includes database.backup', function () {
    $values = array_column(TeamWebhook::availableEvents(), 'value');
    expect(in_array('database.backup', $values))->toBeTrue();
});
