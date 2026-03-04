<?php

/**
 * Unit tests for Notification DTO classes: DiscordMessage, SlackMessage, PushoverMessage.
 *
 * Tests cover:
 * - DiscordMessage: color factory methods, addField() fluent API, toPayload() structure, isCritical @here
 * - SlackMessage: color factory methods (hex strings)
 * - PushoverMessage: getLevelIcon() for all levels, toPayload() structure with token/user
 */

use App\Notifications\Dto\DiscordMessage;
use App\Notifications\Dto\PushoverMessage;
use App\Notifications\Dto\SlackMessage;

// ─── DiscordMessage colors ────────────────────────────────────────────────────

test('DiscordMessage::successColor returns correct hex decimal', function () {
    expect(DiscordMessage::successColor())->toBe(hexdec('a1ffa5'));
});

test('DiscordMessage::warningColor returns correct hex decimal', function () {
    expect(DiscordMessage::warningColor())->toBe(hexdec('ffa743'));
});

test('DiscordMessage::errorColor returns correct hex decimal', function () {
    expect(DiscordMessage::errorColor())->toBe(hexdec('ff705f'));
});

test('DiscordMessage::infoColor returns correct hex decimal', function () {
    expect(DiscordMessage::infoColor())->toBe(hexdec('4f545c'));
});

// ─── DiscordMessage addField ──────────────────────────────────────────────────

test('DiscordMessage addField is fluent and adds field to payload', function () {
    $msg = new DiscordMessage('Test', 'Description', DiscordMessage::infoColor());
    $result = $msg->addField('Server', 'prod-01', false);

    expect($result)->toBe($msg); // fluent
    $payload = $msg->toPayload();
    $fields = $payload['embeds'][0]['fields'];

    // Fields contain our added field + timestamp
    $names = array_column($fields, 'name');
    expect($names)->toContain('Server');
});

test('DiscordMessage addField inline=true sets inline in payload', function () {
    $msg = new DiscordMessage('Test', 'Desc', DiscordMessage::infoColor());
    $msg->addField('App', 'my-app', inline: true);

    $fields = $msg->toPayload()['embeds'][0]['fields'];
    $appField = collect($fields)->firstWhere('name', 'App');
    expect($appField['inline'])->toBeTrue();
});

test('DiscordMessage payload has embeds structure', function () {
    $msg = new DiscordMessage('Alert Title', 'Alert body', DiscordMessage::errorColor());
    $payload = $msg->toPayload();

    expect($payload)->toHaveKey('embeds');
    expect($payload['embeds'][0]['title'])->toBe('Alert Title');
    expect($payload['embeds'][0]['description'])->toBe('Alert body');
    expect($payload['embeds'][0]['color'])->toBe(DiscordMessage::errorColor());
});

test('DiscordMessage isCritical adds @here content', function () {
    $msg = new DiscordMessage('Critical!', 'Server down', DiscordMessage::errorColor(), isCritical: true);
    $payload = $msg->toPayload();
    expect($payload['content'])->toBe('@here');
});

test('DiscordMessage non-critical does not add @here content', function () {
    $msg = new DiscordMessage('Info', 'Just info', DiscordMessage::infoColor(), isCritical: false);
    $payload = $msg->toPayload();
    expect($payload)->not->toHaveKey('content');
});

test('DiscordMessage payload fields always include timestamp', function () {
    $msg = new DiscordMessage('Test', 'Test', DiscordMessage::infoColor());
    $fields = $msg->toPayload()['embeds'][0]['fields'];

    $names = array_column($fields, 'name');
    expect($names)->toContain('Time');
});

// ─── SlackMessage colors ──────────────────────────────────────────────────────

test('SlackMessage::infoColor returns hex string', function () {
    expect(SlackMessage::infoColor())->toBe('#0099ff');
});

test('SlackMessage::errorColor returns hex string', function () {
    expect(SlackMessage::errorColor())->toBe('#ff0000');
});

test('SlackMessage::successColor returns hex string', function () {
    expect(SlackMessage::successColor())->toBe('#00ff00');
});

test('SlackMessage::warningColor returns hex string', function () {
    expect(SlackMessage::warningColor())->toBe('#ffa500');
});

test('SlackMessage default color is info color', function () {
    $msg = new SlackMessage('Test', 'Desc');
    expect($msg->color)->toBe(SlackMessage::infoColor());
});

// ─── PushoverMessage getLevelIcon ─────────────────────────────────────────────

test('PushoverMessage getLevelIcon returns cross for error', function () {
    $msg = new PushoverMessage('Error', 'Something failed', level: 'error');
    expect($msg->getLevelIcon())->toBe("\xE2\x9D\x8C"); // ❌
});

test('PushoverMessage getLevelIcon returns checkmark for success', function () {
    $msg = new PushoverMessage('Done', 'Deployed', level: 'success');
    expect($msg->getLevelIcon())->toBe("\xE2\x9C\x85"); // ✅
});

test('PushoverMessage getLevelIcon returns warning for warning level', function () {
    $msg = new PushoverMessage('Warning', 'Disk space low', level: 'warning');
    expect($msg->getLevelIcon())->toBe("\xE2\x9A\xA0\xEF\xB8\x8F"); // ⚠️
});

test('PushoverMessage getLevelIcon returns info for default level', function () {
    $msg = new PushoverMessage('Info', 'Server running', level: 'info');
    expect($msg->getLevelIcon())->toBe("\xE2\x84\xB9\xEF\xB8\x8F"); // ℹ️
});

test('PushoverMessage getLevelIcon returns info for unknown level', function () {
    $msg = new PushoverMessage('Unknown', 'message', level: 'unknown');
    expect($msg->getLevelIcon())->toBe("\xE2\x84\xB9\xEF\xB8\x8F"); // ℹ️ (default)
});

// ─── PushoverMessage toPayload ────────────────────────────────────────────────

test('PushoverMessage toPayload contains token and user', function () {
    $msg = new PushoverMessage('Alert', 'Server down');
    $payload = $msg->toPayload('my-token', 'user-key');

    expect($payload['token'])->toBe('my-token');
    expect($payload['user'])->toBe('user-key');
});

test('PushoverMessage toPayload title includes level icon', function () {
    $msg = new PushoverMessage('Deploy Done', 'App deployed', level: 'success');
    $payload = $msg->toPayload('token', 'user');

    expect($payload['title'])->toContain('Deploy Done');
    expect($payload['title'])->toContain("\xE2\x9C\x85"); // ✅
});

test('PushoverMessage toPayload has html flag set', function () {
    $msg = new PushoverMessage('Test', 'body');
    $payload = $msg->toPayload('token', 'user');
    expect($payload['html'])->toBe(1);
});

test('PushoverMessage toPayload appends button link to message', function () {
    $msg = new PushoverMessage('Alert', 'Click the link', buttons: [
        ['url' => 'https://example.com/deploy/42', 'text' => 'View Deploy'],
    ]);
    $payload = $msg->toPayload('token', 'user');

    expect($payload['message'])->toContain('View Deploy');
    expect($payload['message'])->toContain('https://example.com/deploy/42');
});
