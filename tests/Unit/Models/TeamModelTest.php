<?php

use App\Models\Team;

// routeNotificationForDiscord Tests
test('routeNotificationForDiscord returns webhook url', function () {
    $team = new Team;
    $team->discord_webhook_url = 'https://discord.com/api/webhooks/123/abc';

    expect($team->routeNotificationForDiscord())->toBe('https://discord.com/api/webhooks/123/abc');
});

test('routeNotificationForDiscord returns null when not set', function () {
    $team = new Team;
    $team->discord_webhook_url = null;

    expect($team->routeNotificationForDiscord())->toBeNull();
});

// routeNotificationForTelegram Tests
test('routeNotificationForTelegram returns token and chat_id', function () {
    $team = new Team;
    $team->telegram_token = 'bot123:ABC';
    $team->telegram_chat_id = '-100123456';

    $result = $team->routeNotificationForTelegram();

    expect($result)->toBe([
        'token' => 'bot123:ABC',
        'chat_id' => '-100123456',
    ]);
});

test('routeNotificationForTelegram returns nulls when not configured', function () {
    $team = new Team;
    $team->telegram_token = null;
    $team->telegram_chat_id = null;

    $result = $team->routeNotificationForTelegram();

    expect($result['token'])->toBeNull();
    expect($result['chat_id'])->toBeNull();
});

// routeNotificationForSlack Tests
test('routeNotificationForSlack returns webhook url', function () {
    $team = new Team;
    $team->slack_webhook_url = 'https://hooks.slack.com/services/T00/B00/xxx';

    expect($team->routeNotificationForSlack())->toBe('https://hooks.slack.com/services/T00/B00/xxx');
});

test('routeNotificationForSlack returns null when not set', function () {
    $team = new Team;
    $team->slack_webhook_url = null;

    expect($team->routeNotificationForSlack())->toBeNull();
});

// routeNotificationForPushover Tests
test('routeNotificationForPushover returns user and token', function () {
    $team = new Team;
    $team->pushover_user_key = 'user123';
    $team->pushover_api_token = 'token456';

    $result = $team->routeNotificationForPushover();

    expect($result)->toBe([
        'user' => 'user123',
        'token' => 'token456',
    ]);
});

test('routeNotificationForPushover returns nulls when not configured', function () {
    $team = new Team;
    $team->pushover_user_key = null;
    $team->pushover_api_token = null;

    $result = $team->routeNotificationForPushover();

    expect($result['user'])->toBeNull();
    expect($result['token'])->toBeNull();
});

// subscriptionPastOverDue Tests
test('subscriptionPastOverDue returns false when not cloud', function () {
    $team = new Team;
    // isCloud() returns false in testing environment
    expect($team->subscriptionPastOverDue())->toBeFalse();
});
