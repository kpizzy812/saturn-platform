<?php

use App\Models\DiscordNotificationSettings;
use App\Models\EmailNotificationSettings;
use App\Models\PushoverNotificationSettings;
use App\Models\SlackNotificationSettings;
use App\Models\TelegramNotificationSettings;
use App\Models\WebhookNotificationSettings;

// ── Model method existence ──────────────────────────────────────────

it('discord notification settings has isEnabled and team methods', function () {
    expect(method_exists(DiscordNotificationSettings::class, 'isEnabled'))->toBeTrue();
    expect(method_exists(DiscordNotificationSettings::class, 'team'))->toBeTrue();
});

it('slack notification settings has isEnabled and team methods', function () {
    expect(method_exists(SlackNotificationSettings::class, 'isEnabled'))->toBeTrue();
    expect(method_exists(SlackNotificationSettings::class, 'team'))->toBeTrue();
});

it('telegram notification settings has isEnabled and team methods', function () {
    expect(method_exists(TelegramNotificationSettings::class, 'isEnabled'))->toBeTrue();
    expect(method_exists(TelegramNotificationSettings::class, 'team'))->toBeTrue();
});

it('email notification settings has isEnabled and team methods', function () {
    expect(method_exists(EmailNotificationSettings::class, 'isEnabled'))->toBeTrue();
    expect(method_exists(EmailNotificationSettings::class, 'team'))->toBeTrue();
});

it('pushover notification settings has isEnabled and team methods', function () {
    expect(method_exists(PushoverNotificationSettings::class, 'isEnabled'))->toBeTrue();
    expect(method_exists(PushoverNotificationSettings::class, 'team'))->toBeTrue();
});

it('webhook notification settings has isEnabled and team methods', function () {
    expect(method_exists(WebhookNotificationSettings::class, 'isEnabled'))->toBeTrue();
    expect(method_exists(WebhookNotificationSettings::class, 'team'))->toBeTrue();
});

// ── Event flag counts ───────────────────────────────────────────────

it('discord has 14 event flags', function () {
    $model = new DiscordNotificationSettings;
    $flags = array_filter($model->getFillable(), fn ($f) => str_ends_with($f, '_discord_notifications'));
    expect(count($flags))->toBe(14);
});

it('slack has 14 event flags', function () {
    $model = new SlackNotificationSettings;
    $flags = array_filter($model->getFillable(), fn ($f) => str_ends_with($f, '_slack_notifications'));
    expect(count($flags))->toBe(14);
});

it('telegram has 14 event flags', function () {
    $model = new TelegramNotificationSettings;
    $flags = array_filter($model->getFillable(), fn ($f) => str_ends_with($f, '_telegram_notifications'));
    expect(count($flags))->toBe(14);
});

it('email has 15 event flags', function () {
    $model = new EmailNotificationSettings;
    $flags = array_filter($model->getFillable(), fn ($f) => str_ends_with($f, '_email_notifications'));
    expect(count($flags))->toBe(15);
});

it('pushover has 14 event flags', function () {
    $model = new PushoverNotificationSettings;
    $flags = array_filter($model->getFillable(), fn ($f) => str_ends_with($f, '_pushover_notifications'));
    expect(count($flags))->toBe(14);
});

it('webhook has 15 event flags', function () {
    $model = new WebhookNotificationSettings;
    $flags = array_filter($model->getFillable(), fn ($f) => str_ends_with($f, '_webhook_notifications'));
    expect(count($flags))->toBe(15);
});

// ── Encrypted sensitive fields ──────────────────────────────────────

it('discord encrypts webhook url', function () {
    $model = new DiscordNotificationSettings;
    $casts = $model->getCasts();
    expect($casts['discord_webhook_url'])->toBe('encrypted');
});

it('slack encrypts webhook url', function () {
    $model = new SlackNotificationSettings;
    $casts = $model->getCasts();
    expect($casts['slack_webhook_url'])->toBe('encrypted');
});

it('telegram encrypts token and chat id', function () {
    $model = new TelegramNotificationSettings;
    $casts = $model->getCasts();
    expect($casts['telegram_token'])->toBe('encrypted');
    expect($casts['telegram_chat_id'])->toBe('encrypted');
});

it('email encrypts smtp credentials', function () {
    $model = new EmailNotificationSettings;
    $casts = $model->getCasts();
    expect($casts['smtp_password'])->toBe('encrypted');
    expect($casts['smtp_host'])->toBe('encrypted');
    expect($casts['resend_api_key'])->toBe('encrypted');
});

it('pushover encrypts user key and api token', function () {
    $model = new PushoverNotificationSettings;
    $casts = $model->getCasts();
    expect($casts['pushover_user_key'])->toBe('encrypted');
    expect($casts['pushover_api_token'])->toBe('encrypted');
});

it('webhook encrypts webhook url', function () {
    $model = new WebhookNotificationSettings;
    $casts = $model->getCasts();
    expect($casts['webhook_url'])->toBe('encrypted');
});

// ── isEnabled logic ─────────────────────────────────────────────────

it('email isEnabled checks smtp, resend, and instance settings', function () {
    $model = new EmailNotificationSettings;

    $model->smtp_enabled = false;
    $model->resend_enabled = false;
    $model->use_instance_email_settings = false;
    expect($model->isEnabled())->toBeFalse();

    $model->smtp_enabled = true;
    expect($model->isEnabled())->toBeTrue();

    $model->smtp_enabled = false;
    $model->resend_enabled = true;
    expect($model->isEnabled())->toBeTrue();

    $model->resend_enabled = false;
    $model->use_instance_email_settings = true;
    expect($model->isEnabled())->toBeTrue();
});

it('discord isEnabled checks discord_enabled flag', function () {
    $model = new DiscordNotificationSettings;
    $model->discord_enabled = false;
    expect($model->isEnabled())->toBeFalse();

    $model->discord_enabled = true;
    expect($model->isEnabled())->toBeTrue();
});

// ── Overview data structure validation ──────────────────────────────

it('all notification models use fillable not guarded', function () {
    $models = [
        new DiscordNotificationSettings,
        new SlackNotificationSettings,
        new TelegramNotificationSettings,
        new EmailNotificationSettings,
        new PushoverNotificationSettings,
        new WebhookNotificationSettings,
    ];

    foreach ($models as $model) {
        expect($model->getFillable())->not->toBeEmpty();
        expect($model->getGuarded())->toBe(['*']);
    }
});

it('all notification models have team_id in fillable', function () {
    $models = [
        new DiscordNotificationSettings,
        new SlackNotificationSettings,
        new TelegramNotificationSettings,
        new EmailNotificationSettings,
        new PushoverNotificationSettings,
        new WebhookNotificationSettings,
    ];

    foreach ($models as $model) {
        expect($model->getFillable())->toContain('team_id');
    }
});

it('event flag counting logic works correctly for all enabled', function () {
    $model = new DiscordNotificationSettings;
    $suffix = '_discord_notifications';
    $eventFields = array_filter($model->getFillable(), fn ($f) => str_ends_with($f, $suffix));

    // Set all event flags to true
    foreach ($eventFields as $field) {
        $model->{$field} = true;
    }

    $enabledCount = 0;
    foreach ($eventFields as $field) {
        if ($model->{$field}) {
            $enabledCount++;
        }
    }

    expect($enabledCount)->toBe(14);
});

it('event flag counting logic works correctly for none enabled', function () {
    $model = new DiscordNotificationSettings;
    $suffix = '_discord_notifications';
    $eventFields = array_filter($model->getFillable(), fn ($f) => str_ends_with($f, $suffix));

    // All default to false/null
    $enabledCount = 0;
    foreach ($eventFields as $field) {
        if ($model->{$field}) {
            $enabledCount++;
        }
    }

    expect($enabledCount)->toBe(0);
});
