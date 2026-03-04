<?php

/**
 * Unit tests for Notification API FormRequest validation rules.
 *
 * Tests cover:
 * - UpdateDiscordNotificationRequest: url validation, boolean enabled
 * - UpdateSlackNotificationRequest: url validation, boolean enabled
 * - UpdatePushoverNotificationRequest: string fields, boolean enabled
 * - UpdateTelegramNotificationRequest: string fields, boolean enabled
 * - UpdateWebhookNotificationRequest: url validation, boolean enabled
 * - All requests: empty data passes (all fields are 'sometimes')
 */

use App\Http\Requests\Api\Notification\UpdateDiscordNotificationRequest;
use App\Http\Requests\Api\Notification\UpdatePushoverNotificationRequest;
use App\Http\Requests\Api\Notification\UpdateSlackNotificationRequest;
use App\Http\Requests\Api\Notification\UpdateTelegramNotificationRequest;
use App\Http\Requests\Api\Notification\UpdateWebhookNotificationRequest;
use Illuminate\Support\Facades\Validator;

// ─── UpdateDiscordNotificationRequest ────────────────────────────────────────

test('UpdateDiscordNotificationRequest empty data passes', function () {
    $validator = Validator::make([], (new UpdateDiscordNotificationRequest)->rules());
    expect($validator->passes())->toBeTrue();
});

test('UpdateDiscordNotificationRequest valid webhook URL passes', function () {
    $validator = Validator::make(
        ['discord_webhook_url' => 'https://discord.com/api/webhooks/123/abc'],
        (new UpdateDiscordNotificationRequest)->rules()
    );
    expect($validator->passes())->toBeTrue();
});

test('UpdateDiscordNotificationRequest invalid URL fails', function () {
    $validator = Validator::make(
        ['discord_webhook_url' => 'not-a-url'],
        (new UpdateDiscordNotificationRequest)->rules()
    );
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('discord_webhook_url'))->toBeTrue();
});

test('UpdateDiscordNotificationRequest null URL passes', function () {
    $validator = Validator::make(
        ['discord_webhook_url' => null],
        (new UpdateDiscordNotificationRequest)->rules()
    );
    expect($validator->passes())->toBeTrue();
});

test('UpdateDiscordNotificationRequest enabled accepts boolean values', function () {
    $validator = Validator::make(
        ['discord_enabled' => true],
        (new UpdateDiscordNotificationRequest)->rules()
    );
    expect($validator->passes())->toBeTrue();

    $validator = Validator::make(
        ['discord_enabled' => false],
        (new UpdateDiscordNotificationRequest)->rules()
    );
    expect($validator->passes())->toBeTrue();
});

// ─── UpdateSlackNotificationRequest ──────────────────────────────────────────

test('UpdateSlackNotificationRequest empty data passes', function () {
    $validator = Validator::make([], (new UpdateSlackNotificationRequest)->rules());
    expect($validator->passes())->toBeTrue();
});

test('UpdateSlackNotificationRequest valid webhook URL passes', function () {
    $validator = Validator::make(
        ['slack_webhook_url' => 'https://example.com/slack/webhook/notify'],
        (new UpdateSlackNotificationRequest)->rules()
    );
    expect($validator->passes())->toBeTrue();
});

test('UpdateSlackNotificationRequest invalid URL fails', function () {
    $validator = Validator::make(
        ['slack_webhook_url' => 'not-a-url'],
        (new UpdateSlackNotificationRequest)->rules()
    );
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('slack_webhook_url'))->toBeTrue();
});

test('UpdateSlackNotificationRequest null URL passes', function () {
    $validator = Validator::make(
        ['slack_webhook_url' => null],
        (new UpdateSlackNotificationRequest)->rules()
    );
    expect($validator->passes())->toBeTrue();
});

// ─── UpdatePushoverNotificationRequest ───────────────────────────────────────

test('UpdatePushoverNotificationRequest empty data passes', function () {
    $validator = Validator::make([], (new UpdatePushoverNotificationRequest)->rules());
    expect($validator->passes())->toBeTrue();
});

test('UpdatePushoverNotificationRequest valid user key and token passes', function () {
    $validator = Validator::make(
        [
            'pushover_enabled' => true,
            'pushover_user_key' => 'uQiRzpo4DXghDmr9QzzfQu',
            'pushover_api_token' => 'azGDORePK8gMaC0QOYAMyEEuzJnyUi',
        ],
        (new UpdatePushoverNotificationRequest)->rules()
    );
    expect($validator->passes())->toBeTrue();
});

test('UpdatePushoverNotificationRequest null keys pass', function () {
    $validator = Validator::make(
        ['pushover_user_key' => null, 'pushover_api_token' => null],
        (new UpdatePushoverNotificationRequest)->rules()
    );
    expect($validator->passes())->toBeTrue();
});

// ─── UpdateTelegramNotificationRequest ───────────────────────────────────────

test('UpdateTelegramNotificationRequest empty data passes', function () {
    $validator = Validator::make([], (new UpdateTelegramNotificationRequest)->rules());
    expect($validator->passes())->toBeTrue();
});

test('UpdateTelegramNotificationRequest valid token and chat_id passes', function () {
    $validator = Validator::make(
        [
            'telegram_enabled' => true,
            'telegram_token' => '123456789:ABCdef-ghIjkLMnopQrStUVwxyz',
            'telegram_chat_id' => '-100123456789',
        ],
        (new UpdateTelegramNotificationRequest)->rules()
    );
    expect($validator->passes())->toBeTrue();
});

test('UpdateTelegramNotificationRequest null fields pass', function () {
    $validator = Validator::make(
        ['telegram_token' => null, 'telegram_chat_id' => null],
        (new UpdateTelegramNotificationRequest)->rules()
    );
    expect($validator->passes())->toBeTrue();
});

// ─── UpdateWebhookNotificationRequest ────────────────────────────────────────

test('UpdateWebhookNotificationRequest empty data passes', function () {
    $validator = Validator::make([], (new UpdateWebhookNotificationRequest)->rules());
    expect($validator->passes())->toBeTrue();
});

test('UpdateWebhookNotificationRequest valid URL passes', function () {
    $validator = Validator::make(
        ['webhook_url' => 'https://my-server.example.com/webhook'],
        (new UpdateWebhookNotificationRequest)->rules()
    );
    expect($validator->passes())->toBeTrue();
});

test('UpdateWebhookNotificationRequest invalid URL fails', function () {
    $validator = Validator::make(
        ['webhook_url' => 'not-a-url'],
        (new UpdateWebhookNotificationRequest)->rules()
    );
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('webhook_url'))->toBeTrue();
});

test('UpdateWebhookNotificationRequest null URL passes', function () {
    $validator = Validator::make(
        ['webhook_url' => null],
        (new UpdateWebhookNotificationRequest)->rules()
    );
    expect($validator->passes())->toBeTrue();
});

test('UpdateWebhookNotificationRequest enabled accepts boolean', function () {
    $validator = Validator::make(
        ['webhook_enabled' => false],
        (new UpdateWebhookNotificationRequest)->rules()
    );
    expect($validator->passes())->toBeTrue();
});
