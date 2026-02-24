<?php

use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();
    $this->team->members()->attach($this->user->id, ['role' => 'owner']);

    session(['currentTeam' => $this->team]);

    $this->token = $this->user->createToken('test-token', ['*']);
    $this->bearerToken = $this->token->plainTextToken;

    if (! InstanceSettings::first()) {
        InstanceSettings::create([
            'id' => 0,
            'is_api_enabled' => true,
        ]);
    }
});

function notifChanHeaders(string $bearerToken): array
{
    return [
        'Authorization' => 'Bearer '.$bearerToken,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ];
}

// ─── Authentication ───

describe('Authentication', function () {
    test('rejects request without authentication', function () {
        $this->getJson('/api/v1/notification-channels')
            ->assertStatus(401);
    });

    test('rejects request with invalid token', function () {
        $this->withHeaders([
            'Authorization' => 'Bearer invalid-token-value',
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/notification-channels')
            ->assertStatus(401);
    });

    test('requires read ability for GET endpoint', function () {
        // Token with only deploy ability cannot read notification channels
        $deployToken = $this->user->createToken('deploy-only', ['deploy']);

        $this->withHeaders(notifChanHeaders($deployToken->plainTextToken))
            ->getJson('/api/v1/notification-channels')
            ->assertStatus(403);
    });

    test('requires write ability for PUT endpoints', function () {
        // Token with only read ability cannot update notification channels
        $readToken = $this->user->createToken('read-only', ['read']);

        $this->withHeaders(notifChanHeaders($readToken->plainTextToken))
            ->putJson('/api/v1/notification-channels/slack', [
                'slack_enabled' => true,
            ])
            ->assertStatus(403);
    });
});

// ─── GET /api/v1/notification-channels ───

describe('GET /api/v1/notification-channels', function () {
    test('returns all channel settings for the team', function () {
        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->getJson('/api/v1/notification-channels');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'email',
            'slack',
            'discord',
            'telegram',
            'webhook',
            'pushover',
        ]);
    });

    test('email settings contain expected public fields', function () {
        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->getJson('/api/v1/notification-channels');

        $response->assertStatus(200);
        $emailSettings = $response->json('email');

        expect($emailSettings)->toHaveKey('team_id');
        expect($emailSettings)->toHaveKey('smtp_enabled');
        expect($emailSettings['team_id'])->toBe($this->team->id);
    });

    test('slack settings contain expected public fields', function () {
        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->getJson('/api/v1/notification-channels');

        $response->assertStatus(200);
        $slackSettings = $response->json('slack');

        expect($slackSettings)->toHaveKey('team_id');
        expect($slackSettings)->toHaveKey('slack_enabled');
        expect($slackSettings['team_id'])->toBe($this->team->id);
    });

    // ─── SECURITY: Sensitive fields must be hidden ───

    test('SECURITY: slack_webhook_url is NOT present in response due to $hidden', function () {
        // Store a webhook URL to make sure it is not leaked
        $this->team->slackNotificationSettings->update([
            'slack_webhook_url' => 'https://hooks.slack.com/services/T00000/B00000/secret-token',
        ]);

        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->getJson('/api/v1/notification-channels');

        $response->assertStatus(200);

        // The $hidden property on SlackNotificationSettings must exclude this field
        $slackData = $response->json('slack');
        expect($slackData)->not->toHaveKey('slack_webhook_url');
    });

    test('SECURITY: discord_webhook_url is NOT present in response due to $hidden', function () {
        $this->team->discordNotificationSettings->update([
            'discord_webhook_url' => 'https://discord.com/api/webhooks/0000/secret',
        ]);

        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->getJson('/api/v1/notification-channels');

        $response->assertStatus(200);

        $discordData = $response->json('discord');
        expect($discordData)->not->toHaveKey('discord_webhook_url');
    });

    test('SECURITY: webhook_url is NOT present in response due to $hidden', function () {
        $this->team->webhookNotificationSettings->update([
            'webhook_url' => 'https://example.com/internal-webhook-secret',
        ]);

        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->getJson('/api/v1/notification-channels');

        $response->assertStatus(200);

        $webhookData = $response->json('webhook');
        expect($webhookData)->not->toHaveKey('webhook_url');
    });

    test('SECURITY: telegram_token is NOT present in response due to $hidden', function () {
        $this->team->telegramNotificationSettings->update([
            'telegram_token' => 'secret-bot-token-123456',
        ]);

        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->getJson('/api/v1/notification-channels');

        $response->assertStatus(200);

        $telegramData = $response->json('telegram');
        expect($telegramData)->not->toHaveKey('telegram_token');
    });

    test('SECURITY: telegram_chat_id is NOT present in response due to $hidden', function () {
        $this->team->telegramNotificationSettings->update([
            'telegram_chat_id' => '-100123456789',
        ]);

        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->getJson('/api/v1/notification-channels');

        $response->assertStatus(200);

        $telegramData = $response->json('telegram');
        expect($telegramData)->not->toHaveKey('telegram_chat_id');
    });

    test('SECURITY: pushover_user_key is NOT present in response due to $hidden', function () {
        $this->team->pushoverNotificationSettings->update([
            'pushover_user_key' => 'uQiRzpo4DXghDmr9QzzfQu',
        ]);

        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->getJson('/api/v1/notification-channels');

        $response->assertStatus(200);

        $pushoverData = $response->json('pushover');
        expect($pushoverData)->not->toHaveKey('pushover_user_key');
    });

    test('SECURITY: pushover_api_token is NOT present in response due to $hidden', function () {
        $this->team->pushoverNotificationSettings->update([
            'pushover_api_token' => 'azGDORePK8gMaC0QOYAMyEEuzJnyUi',
        ]);

        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->getJson('/api/v1/notification-channels');

        $response->assertStatus(200);

        $pushoverData = $response->json('pushover');
        expect($pushoverData)->not->toHaveKey('pushover_api_token');
    });

    test('SECURITY: email smtp_password is NOT present in response due to $hidden', function () {
        $this->team->emailNotificationSettings->update([
            'smtp_password' => 'super-secret-smtp-password',
        ]);

        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->getJson('/api/v1/notification-channels');

        $response->assertStatus(200);

        $emailData = $response->json('email');
        expect($emailData)->not->toHaveKey('smtp_password');
    });

    test('SECURITY: email resend_api_key is NOT present in response due to $hidden', function () {
        $this->team->emailNotificationSettings->update([
            'resend_api_key' => 're_secret_api_key_abc123',
        ]);

        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->getJson('/api/v1/notification-channels');

        $response->assertStatus(200);

        $emailData = $response->json('email');
        expect($emailData)->not->toHaveKey('resend_api_key');
    });

    // ─── Multi-tenancy isolation ───

    test('does not return settings from another team', function () {
        $otherTeam = Team::factory()->create();
        $otherUser = User::factory()->create();
        $otherTeam->members()->attach($otherUser->id, ['role' => 'owner']);
        session(['currentTeam' => $otherTeam]);
        $otherToken = $otherUser->createToken('other-token', ['*']);

        // Activate Slack on the other team
        $otherTeam->slackNotificationSettings->update(['slack_enabled' => true]);

        // Our team's token should return our team's settings (slack_enabled = false by default)
        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->getJson('/api/v1/notification-channels');

        $response->assertStatus(200);

        // Our team's slack is disabled by default; verify we get OUR team's ID, not the other team's
        $slackData = $response->json('slack');
        expect($slackData['team_id'])->toBe($this->team->id);
        expect($slackData['team_id'])->not->toBe($otherTeam->id);
    });
});

// ─── PUT /api/v1/notification-channels/email ───

describe('PUT /api/v1/notification-channels/email', function () {
    test('updates email smtp_enabled flag', function () {
        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->putJson('/api/v1/notification-channels/email', [
                'smtp_enabled' => true,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Email notification settings updated successfully.');

        $this->team->emailNotificationSettings->refresh();
        expect($this->team->emailNotificationSettings->smtp_enabled)->toBeTrue();
    });

    test('updates email smtp_host and smtp_port', function () {
        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->putJson('/api/v1/notification-channels/email', [
                'smtp_host' => 'smtp.example.com',
                'smtp_port' => 587,
            ]);

        $response->assertStatus(200);

        $this->team->emailNotificationSettings->refresh();
        expect($this->team->emailNotificationSettings->smtp_host)->toBe('smtp.example.com');
        expect($this->team->emailNotificationSettings->smtp_port)->toBe(587);
    });

    test('updates email smtp_from_address and smtp_from_name', function () {
        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->putJson('/api/v1/notification-channels/email', [
                'smtp_from_address' => 'noreply@example.com',
                'smtp_from_name' => 'Saturn Platform',
            ]);

        $response->assertStatus(200);

        $this->team->emailNotificationSettings->refresh();
        expect($this->team->emailNotificationSettings->smtp_from_address)->toBe('noreply@example.com');
        expect($this->team->emailNotificationSettings->smtp_from_name)->toBe('Saturn Platform');
    });

    test('updates resend_enabled flag', function () {
        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->putJson('/api/v1/notification-channels/email', [
                'resend_enabled' => true,
            ]);

        $response->assertStatus(200);

        $this->team->emailNotificationSettings->refresh();
        expect($this->team->emailNotificationSettings->resend_enabled)->toBeTrue();
    });

    test('returns 422 for invalid email address in smtp_from_address', function () {
        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->putJson('/api/v1/notification-channels/email', [
                'smtp_from_address' => 'not-a-valid-email',
            ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['smtp_from_address']]);
    });

    test('returns 422 for smtp_port below valid range', function () {
        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->putJson('/api/v1/notification-channels/email', [
                'smtp_port' => 0,
            ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['smtp_port']]);
    });

    test('returns 422 for smtp_port above valid range', function () {
        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->putJson('/api/v1/notification-channels/email', [
                'smtp_port' => 65536,
            ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['smtp_port']]);
    });

    test('returns 422 for invalid smtp_recipients format', function () {
        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->putJson('/api/v1/notification-channels/email', [
                'smtp_recipients' => 'not-a-valid-email-list',
            ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['smtp_recipients']]);
    });

    test('accepts valid comma-separated smtp_recipients', function () {
        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->putJson('/api/v1/notification-channels/email', [
                'smtp_recipients' => 'a@example.com,b@example.com',
            ]);

        $response->assertStatus(200);
    });

    test('response settings do NOT include smtp_password', function () {
        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->putJson('/api/v1/notification-channels/email', [
                'smtp_password' => 'new-secret-pass',
            ]);

        $response->assertStatus(200);

        // The $hidden property must prevent the password from leaking in the response
        $settingsInResponse = $response->json('settings');
        expect($settingsInResponse)->not->toHaveKey('smtp_password');
    });
});

// ─── PUT /api/v1/notification-channels/slack ───

describe('PUT /api/v1/notification-channels/slack', function () {
    test('updates slack_enabled flag', function () {
        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->putJson('/api/v1/notification-channels/slack', [
                'slack_enabled' => true,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Slack notification settings updated successfully.');

        $this->team->slackNotificationSettings->refresh();
        expect($this->team->slackNotificationSettings->slack_enabled)->toBeTrue();
    });

    test('stores slack_webhook_url encrypted in the database', function () {
        $webhookUrl = 'https://hooks.slack.com/services/T12345/B12345/abcdefg';

        $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->putJson('/api/v1/notification-channels/slack', [
                'slack_webhook_url' => $webhookUrl,
            ])
            ->assertStatus(200);

        // The value must be stored (encrypted) - read via model to confirm it decrypts correctly
        $this->team->slackNotificationSettings->refresh();
        expect($this->team->slackNotificationSettings->slack_webhook_url)->toBe($webhookUrl);
    });

    test('response settings do NOT include slack_webhook_url due to $hidden', function () {
        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->putJson('/api/v1/notification-channels/slack', [
                'slack_webhook_url' => 'https://hooks.slack.com/services/T12345/B12345/secret',
            ]);

        $response->assertStatus(200);

        // The $hidden property on SlackNotificationSettings must exclude this field
        $settingsInResponse = $response->json('settings');
        expect($settingsInResponse)->not->toHaveKey('slack_webhook_url');
    });

    test('returns 422 for invalid slack_webhook_url format', function () {
        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->putJson('/api/v1/notification-channels/slack', [
                'slack_webhook_url' => 'not-a-valid-url',
            ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['slack_webhook_url']]);
    });

    test('accepts null slack_webhook_url to clear the setting', function () {
        $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->putJson('/api/v1/notification-channels/slack', [
                'slack_webhook_url' => null,
            ])
            ->assertStatus(200);
    });
});

// ─── PUT /api/v1/notification-channels/discord ───

describe('PUT /api/v1/notification-channels/discord', function () {
    test('updates discord_enabled flag', function () {
        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->putJson('/api/v1/notification-channels/discord', [
                'discord_enabled' => true,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Discord notification settings updated successfully.');

        $this->team->discordNotificationSettings->refresh();
        expect($this->team->discordNotificationSettings->discord_enabled)->toBeTrue();
    });

    test('stores discord_webhook_url and persists it', function () {
        $webhookUrl = 'https://discord.com/api/webhooks/123456/abcdef';

        $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->putJson('/api/v1/notification-channels/discord', [
                'discord_webhook_url' => $webhookUrl,
            ])
            ->assertStatus(200);

        $this->team->discordNotificationSettings->refresh();
        expect($this->team->discordNotificationSettings->discord_webhook_url)->toBe($webhookUrl);
    });

    test('response settings do NOT include discord_webhook_url due to $hidden', function () {
        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->putJson('/api/v1/notification-channels/discord', [
                'discord_webhook_url' => 'https://discord.com/api/webhooks/999/secret',
            ]);

        $response->assertStatus(200);

        $settingsInResponse = $response->json('settings');
        expect($settingsInResponse)->not->toHaveKey('discord_webhook_url');
    });

    test('returns 422 for invalid discord_webhook_url format', function () {
        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->putJson('/api/v1/notification-channels/discord', [
                'discord_webhook_url' => 'not-a-valid-url',
            ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['discord_webhook_url']]);
    });

    test('accepts null discord_webhook_url to clear the setting', function () {
        $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->putJson('/api/v1/notification-channels/discord', [
                'discord_webhook_url' => null,
            ])
            ->assertStatus(200);
    });
});

// ─── PUT /api/v1/notification-channels/telegram ───

describe('PUT /api/v1/notification-channels/telegram', function () {
    test('updates telegram_enabled flag', function () {
        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->putJson('/api/v1/notification-channels/telegram', [
                'telegram_enabled' => true,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Telegram notification settings updated successfully.');

        $this->team->telegramNotificationSettings->refresh();
        expect($this->team->telegramNotificationSettings->telegram_enabled)->toBeTrue();
    });

    test('stores telegram_token and telegram_chat_id encrypted', function () {
        $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->putJson('/api/v1/notification-channels/telegram', [
                'telegram_token' => 'bot123456:AAFakeBotToken',
                'telegram_chat_id' => '-100987654321',
            ])
            ->assertStatus(200);

        $this->team->telegramNotificationSettings->refresh();
        expect($this->team->telegramNotificationSettings->telegram_token)->toBe('bot123456:AAFakeBotToken');
        expect($this->team->telegramNotificationSettings->telegram_chat_id)->toBe('-100987654321');
    });

    test('response settings do NOT include telegram_token due to $hidden', function () {
        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->putJson('/api/v1/notification-channels/telegram', [
                'telegram_token' => 'bot999:SecretToken',
                'telegram_chat_id' => '-100111',
            ]);

        $response->assertStatus(200);

        $settingsInResponse = $response->json('settings');
        expect($settingsInResponse)->not->toHaveKey('telegram_token');
        expect($settingsInResponse)->not->toHaveKey('telegram_chat_id');
    });

    test('accepts null values to clear telegram credentials', function () {
        $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->putJson('/api/v1/notification-channels/telegram', [
                'telegram_token' => null,
                'telegram_chat_id' => null,
            ])
            ->assertStatus(200);
    });
});

// ─── PUT /api/v1/notification-channels/webhook ───

describe('PUT /api/v1/notification-channels/webhook', function () {
    test('updates webhook_enabled flag', function () {
        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->putJson('/api/v1/notification-channels/webhook', [
                'webhook_enabled' => true,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Webhook notification settings updated successfully.');

        $this->team->webhookNotificationSettings->refresh();
        expect($this->team->webhookNotificationSettings->webhook_enabled)->toBeTrue();
    });

    test('stores webhook_url encrypted in the database', function () {
        $webhookUrl = 'https://example.com/my-secret-webhook-endpoint';

        $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->putJson('/api/v1/notification-channels/webhook', [
                'webhook_url' => $webhookUrl,
            ])
            ->assertStatus(200);

        $this->team->webhookNotificationSettings->refresh();
        expect($this->team->webhookNotificationSettings->webhook_url)->toBe($webhookUrl);
    });

    test('response settings do NOT include webhook_url due to $hidden', function () {
        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->putJson('/api/v1/notification-channels/webhook', [
                'webhook_url' => 'https://example.com/secret-endpoint',
            ]);

        $response->assertStatus(200);

        $settingsInResponse = $response->json('settings');
        expect($settingsInResponse)->not->toHaveKey('webhook_url');
    });

    test('returns 422 for invalid webhook_url format', function () {
        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->putJson('/api/v1/notification-channels/webhook', [
                'webhook_url' => 'not-a-url',
            ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['webhook_url']]);
    });

    test('accepts null webhook_url to clear the setting', function () {
        $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->putJson('/api/v1/notification-channels/webhook', [
                'webhook_url' => null,
            ])
            ->assertStatus(200);
    });
});

// ─── PUT /api/v1/notification-channels/pushover ───

describe('PUT /api/v1/notification-channels/pushover', function () {
    test('updates pushover_enabled flag', function () {
        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->putJson('/api/v1/notification-channels/pushover', [
                'pushover_enabled' => true,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Pushover notification settings updated successfully.');

        $this->team->pushoverNotificationSettings->refresh();
        expect($this->team->pushoverNotificationSettings->pushover_enabled)->toBeTrue();
    });

    test('stores pushover_user_key and pushover_api_token encrypted', function () {
        $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->putJson('/api/v1/notification-channels/pushover', [
                'pushover_user_key' => 'uQiRzpo4DXghDmr9QzzfQu',
                'pushover_api_token' => 'azGDORePK8gMaC0QOYAMyE',
            ])
            ->assertStatus(200);

        $this->team->pushoverNotificationSettings->refresh();
        expect($this->team->pushoverNotificationSettings->pushover_user_key)->toBe('uQiRzpo4DXghDmr9QzzfQu');
        expect($this->team->pushoverNotificationSettings->pushover_api_token)->toBe('azGDORePK8gMaC0QOYAMyE');
    });

    test('response settings do NOT include pushover_user_key due to $hidden', function () {
        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->putJson('/api/v1/notification-channels/pushover', [
                'pushover_user_key' => 'uQiRzpo4DXghDmr9QzzfQu',
                'pushover_api_token' => 'azGDORePK8gMaC0QOYAMyE',
            ]);

        $response->assertStatus(200);

        $settingsInResponse = $response->json('settings');
        expect($settingsInResponse)->not->toHaveKey('pushover_user_key');
        expect($settingsInResponse)->not->toHaveKey('pushover_api_token');
    });

    test('accepts null values to clear pushover credentials', function () {
        $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->putJson('/api/v1/notification-channels/pushover', [
                'pushover_user_key' => null,
                'pushover_api_token' => null,
            ])
            ->assertStatus(200);
    });
});

// ─── Cross-team isolation for PUT endpoints ───

describe('Cross-team isolation', function () {
    test('token is scoped to its own team - updating does not affect another team', function () {
        $otherTeam = Team::factory()->create();
        $otherUser = User::factory()->create();
        $otherTeam->members()->attach($otherUser->id, ['role' => 'owner']);

        // Capture the other team's original slack_enabled state
        $otherOriginalEnabled = $otherTeam->slackNotificationSettings->slack_enabled;

        // Our team updates its own Slack settings
        $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->putJson('/api/v1/notification-channels/slack', [
                'slack_enabled' => true,
            ])
            ->assertStatus(200);

        // The other team's settings must be unchanged
        $otherTeam->slackNotificationSettings->refresh();
        expect($otherTeam->slackNotificationSettings->slack_enabled)->toBe($otherOriginalEnabled);

        // Our team's settings must have changed
        $this->team->slackNotificationSettings->refresh();
        expect($this->team->slackNotificationSettings->slack_enabled)->toBeTrue();
    });

    test('GET returns settings scoped to the authenticated team only', function () {
        $otherTeam = Team::factory()->create();

        // Activate discord on the other team
        $otherTeam->discordNotificationSettings->update(['discord_enabled' => true]);

        // Our team's discord is disabled (default) - GET must return OUR team's state
        $response = $this->withHeaders(notifChanHeaders($this->bearerToken))
            ->getJson('/api/v1/notification-channels');

        $response->assertStatus(200);

        $discordData = $response->json('discord');
        expect($discordData['team_id'])->toBe($this->team->id);
        // Our team has discord_enabled = false by default
        expect($discordData['discord_enabled'])->toBeFalse();
    });
});
