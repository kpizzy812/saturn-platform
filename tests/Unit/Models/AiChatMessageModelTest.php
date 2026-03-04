<?php

/**
 * Unit tests for AiChatMessage model.
 *
 * Tests cover:
 * - isFromUser() / isFromAssistant() / isSystem() — role checks
 * - Mutual exclusion of role checks
 * - hasCommand() — based on intent presence
 * - isCommandPending/Executing/Completed/Failed() — command status checks
 * - getIntentLabelAttribute() — human-readable intent labels
 * - getCommandStatusColorAttribute() — color per command status
 * - rate() — boundary validation (pure, no DB)
 */

use App\Models\AiChatMessage;

// ─── isFromUser() ─────────────────────────────────────────────────────────────

test('isFromUser returns true when role is user', function () {
    $msg = new AiChatMessage;
    $msg->setRawAttributes(['role' => 'user']);
    expect($msg->isFromUser())->toBeTrue();
});

test('isFromUser returns false when role is assistant', function () {
    $msg = new AiChatMessage;
    $msg->setRawAttributes(['role' => 'assistant']);
    expect($msg->isFromUser())->toBeFalse();
});

test('isFromUser returns false when role is system', function () {
    $msg = new AiChatMessage;
    $msg->setRawAttributes(['role' => 'system']);
    expect($msg->isFromUser())->toBeFalse();
});

// ─── isFromAssistant() ────────────────────────────────────────────────────────

test('isFromAssistant returns true when role is assistant', function () {
    $msg = new AiChatMessage;
    $msg->setRawAttributes(['role' => 'assistant']);
    expect($msg->isFromAssistant())->toBeTrue();
});

test('isFromAssistant returns false when role is user', function () {
    $msg = new AiChatMessage;
    $msg->setRawAttributes(['role' => 'user']);
    expect($msg->isFromAssistant())->toBeFalse();
});

// ─── isSystem() ───────────────────────────────────────────────────────────────

test('isSystem returns true when role is system', function () {
    $msg = new AiChatMessage;
    $msg->setRawAttributes(['role' => 'system']);
    expect($msg->isSystem())->toBeTrue();
});

test('isSystem returns false when role is user', function () {
    $msg = new AiChatMessage;
    $msg->setRawAttributes(['role' => 'user']);
    expect($msg->isSystem())->toBeFalse();
});

// ─── Mutual exclusion of roles ────────────────────────────────────────────────

test('only isFromUser is true for user role', function () {
    $msg = new AiChatMessage;
    $msg->setRawAttributes(['role' => 'user']);

    expect($msg->isFromUser())->toBeTrue();
    expect($msg->isFromAssistant())->toBeFalse();
    expect($msg->isSystem())->toBeFalse();
});

test('only isFromAssistant is true for assistant role', function () {
    $msg = new AiChatMessage;
    $msg->setRawAttributes(['role' => 'assistant']);

    expect($msg->isFromUser())->toBeFalse();
    expect($msg->isFromAssistant())->toBeTrue();
    expect($msg->isSystem())->toBeFalse();
});

test('only isSystem is true for system role', function () {
    $msg = new AiChatMessage;
    $msg->setRawAttributes(['role' => 'system']);

    expect($msg->isFromUser())->toBeFalse();
    expect($msg->isFromAssistant())->toBeFalse();
    expect($msg->isSystem())->toBeTrue();
});

// ─── hasCommand() ─────────────────────────────────────────────────────────────

test('hasCommand returns true when intent is set', function () {
    $msg = new AiChatMessage;
    $msg->setRawAttributes(['intent' => 'deploy']);
    expect($msg->hasCommand())->toBeTrue();
});

test('hasCommand returns false when intent is null', function () {
    $msg = new AiChatMessage;
    $msg->setRawAttributes(['intent' => null]);
    expect($msg->hasCommand())->toBeFalse();
});

test('hasCommand returns false when intent is empty string', function () {
    $msg = new AiChatMessage;
    $msg->setRawAttributes(['intent' => '']);
    expect($msg->hasCommand())->toBeFalse();
});

// ─── Command status checks ────────────────────────────────────────────────────

test('isCommandPending returns true when command_status is pending', function () {
    $msg = new AiChatMessage;
    $msg->setRawAttributes(['command_status' => 'pending']);
    expect($msg->isCommandPending())->toBeTrue();
});

test('isCommandPending returns false when command_status is executing', function () {
    $msg = new AiChatMessage;
    $msg->setRawAttributes(['command_status' => 'executing']);
    expect($msg->isCommandPending())->toBeFalse();
});

test('isCommandExecuting returns true when command_status is executing', function () {
    $msg = new AiChatMessage;
    $msg->setRawAttributes(['command_status' => 'executing']);
    expect($msg->isCommandExecuting())->toBeTrue();
});

test('isCommandCompleted returns true when command_status is completed', function () {
    $msg = new AiChatMessage;
    $msg->setRawAttributes(['command_status' => 'completed']);
    expect($msg->isCommandCompleted())->toBeTrue();
});

test('isCommandCompleted returns false when command_status is failed', function () {
    $msg = new AiChatMessage;
    $msg->setRawAttributes(['command_status' => 'failed']);
    expect($msg->isCommandCompleted())->toBeFalse();
});

test('isCommandFailed returns true when command_status is failed', function () {
    $msg = new AiChatMessage;
    $msg->setRawAttributes(['command_status' => 'failed']);
    expect($msg->isCommandFailed())->toBeTrue();
});

// ─── getIntentLabelAttribute() ────────────────────────────────────────────────

test('intent_label is Deploy for deploy intent', function () {
    $msg = new AiChatMessage;
    $msg->setRawAttributes(['intent' => 'deploy']);
    expect($msg->intent_label)->toBe('Deploy');
});

test('intent_label is Restart for restart intent', function () {
    $msg = new AiChatMessage;
    $msg->setRawAttributes(['intent' => 'restart']);
    expect($msg->intent_label)->toBe('Restart');
});

test('intent_label is Stop for stop intent', function () {
    $msg = new AiChatMessage;
    $msg->setRawAttributes(['intent' => 'stop']);
    expect($msg->intent_label)->toBe('Stop');
});

test('intent_label is Start for start intent', function () {
    $msg = new AiChatMessage;
    $msg->setRawAttributes(['intent' => 'start']);
    expect($msg->intent_label)->toBe('Start');
});

test('intent_label is View Logs for logs intent', function () {
    $msg = new AiChatMessage;
    $msg->setRawAttributes(['intent' => 'logs']);
    expect($msg->intent_label)->toBe('View Logs');
});

test('intent_label is Check Status for status intent', function () {
    $msg = new AiChatMessage;
    $msg->setRawAttributes(['intent' => 'status']);
    expect($msg->intent_label)->toBe('Check Status');
});

test('intent_label is Help for help intent', function () {
    $msg = new AiChatMessage;
    $msg->setRawAttributes(['intent' => 'help']);
    expect($msg->intent_label)->toBe('Help');
});

test('intent_label is ucfirst of raw value for unknown intent', function () {
    $msg = new AiChatMessage;
    $msg->setRawAttributes(['intent' => 'custom_action']);
    expect($msg->intent_label)->toBe('Custom_action');
});

test('intent_label is null when intent is null', function () {
    $msg = new AiChatMessage;
    $msg->setRawAttributes(['intent' => null]);
    expect($msg->intent_label)->toBeNull();
});

// ─── getCommandStatusColorAttribute() ────────────────────────────────────────

test('command_status_color is yellow for pending', function () {
    $msg = new AiChatMessage;
    $msg->setRawAttributes(['command_status' => 'pending']);
    expect($msg->command_status_color)->toBe('yellow');
});

test('command_status_color is blue for executing', function () {
    $msg = new AiChatMessage;
    $msg->setRawAttributes(['command_status' => 'executing']);
    expect($msg->command_status_color)->toBe('blue');
});

test('command_status_color is green for completed', function () {
    $msg = new AiChatMessage;
    $msg->setRawAttributes(['command_status' => 'completed']);
    expect($msg->command_status_color)->toBe('green');
});

test('command_status_color is red for failed', function () {
    $msg = new AiChatMessage;
    $msg->setRawAttributes(['command_status' => 'failed']);
    expect($msg->command_status_color)->toBe('red');
});

test('command_status_color is gray for cancelled', function () {
    $msg = new AiChatMessage;
    $msg->setRawAttributes(['command_status' => 'cancelled']);
    expect($msg->command_status_color)->toBe('gray');
});

test('command_status_color is gray for unknown status', function () {
    $msg = new AiChatMessage;
    $msg->setRawAttributes(['command_status' => 'unknown']);
    expect($msg->command_status_color)->toBe('gray');
});

// ─── rate() — boundary validation (no DB) ────────────────────────────────────

test('rate returns false when rating is 0', function () {
    $msg = new AiChatMessage;
    expect($msg->rate(0))->toBeFalse();
});

test('rate returns false when rating is negative', function () {
    $msg = new AiChatMessage;
    expect($msg->rate(-1))->toBeFalse();
});

test('rate returns false when rating is 6', function () {
    $msg = new AiChatMessage;
    expect($msg->rate(6))->toBeFalse();
});
