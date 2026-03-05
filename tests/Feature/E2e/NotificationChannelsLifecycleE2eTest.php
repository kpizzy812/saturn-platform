<?php

/**
 * E2E Notification Channels Lifecycle Tests
 *
 * Tests the full notification channel configuration lifecycle:
 * - Multi-channel configuration and retrieval
 * - Individual channel update and re-update persistence
 * - Cross-team isolation for notification settings
 * - Token ability enforcement (read, write, deploy, root, empty)
 * - Multi-channel enable/disable toggling
 * - Validation of channel-specific fields (email, URL formats)
 * - All 6 channel types configurable in a single flow
 * - Notification event flags and alternative providers (Resend)
 * - Response structure: hidden sensitive fields, consistent JSON shape
 *
 * All tests use DatabaseTransactions to ensure isolation.
 */

use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

uses(DatabaseTransactions::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function notifHeaders(string $bearerToken): array
{
    return [
        'Authorization' => 'Bearer '.$bearerToken,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ];
}

// ─── Setup ───────────────────────────────────────────────────────────────────

beforeEach(function () {
    Queue::fake();
    Cache::flush();
    try {
        Cache::store('redis')->flush();
    } catch (\Throwable $e) {
    }

    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();
    $this->team->members()->attach($this->user->id, ['role' => 'owner']);

    session(['currentTeam' => $this->team]);
    $this->token = $this->user->createToken('test-token', ['*']);
    $this->bearerToken = $this->token->plainTextToken;

    if (! InstanceSettings::first()) {
        InstanceSettings::create(['id' => 0, 'is_api_enabled' => true]);
    }
});

// ─── 1. Full multi-channel configuration lifecycle ───────────────────────────

describe('Full multi-channel configuration lifecycle', function () {
    test('GET defaults then configure email + slack + discord and verify all persisted', function () {
        $headers = notifHeaders($this->bearerToken);

        // Step 1: GET all channels — should return all 6 keys with defaults
        $response = $this->withHeaders($headers)->getJson('/api/v1/notification-channels');
        $response->assertOk();
        $json = $response->json();
        expect($json)->toHaveKeys(['email', 'slack', 'discord', 'telegram', 'webhook', 'pushover']);

        // All channels should be disabled by default
        expect($json['slack']['slack_enabled'])->toBeFalse();
        expect($json['discord']['discord_enabled'])->toBeFalse();
        expect($json['telegram']['telegram_enabled'])->toBeFalse();
        expect($json['webhook']['webhook_enabled'])->toBeFalse();
        expect($json['pushover']['pushover_enabled'])->toBeFalse();

        // Step 2: Configure email with SMTP settings
        $emailResponse = $this->withHeaders($headers)->putJson('/api/v1/notification-channels/email', [
            'smtp_enabled' => true,
            'smtp_from_address' => 'noreply@saturn.ac',
            'smtp_from_name' => 'Saturn Platform',
            'smtp_host' => 'smtp.saturn.ac',
            'smtp_port' => 587,
        ]);
        $emailResponse->assertOk();
        $emailResponse->assertJsonFragment(['message' => 'Email notification settings updated successfully.']);
        expect($emailResponse->json('settings.smtp_enabled'))->toBeTrue();

        // Step 3: Configure Slack
        $slackResponse = $this->withHeaders($headers)->putJson('/api/v1/notification-channels/slack', [
            'slack_enabled' => true,
            'slack_webhook_url' => 'https://hooks.slack.com/services/T000/B000/xxxx',
        ]);
        $slackResponse->assertOk();
        $slackResponse->assertJsonFragment(['message' => 'Slack notification settings updated successfully.']);
        expect($slackResponse->json('settings.slack_enabled'))->toBeTrue();

        // Step 4: Configure Discord
        $discordResponse = $this->withHeaders($headers)->putJson('/api/v1/notification-channels/discord', [
            'discord_enabled' => true,
            'discord_webhook_url' => 'https://discord.com/api/webhooks/123456/abcdef',
        ]);
        $discordResponse->assertOk();
        $discordResponse->assertJsonFragment(['message' => 'Discord notification settings updated successfully.']);
        expect($discordResponse->json('settings.discord_enabled'))->toBeTrue();

        // Step 5: GET all channels again — verify all 3 are now configured
        $finalResponse = $this->withHeaders($headers)->getJson('/api/v1/notification-channels');
        $finalResponse->assertOk();
        $final = $finalResponse->json();

        expect($final['email']['smtp_enabled'])->toBeTrue();
        expect($final['slack']['slack_enabled'])->toBeTrue();
        expect($final['discord']['discord_enabled'])->toBeTrue();
        // Telegram, webhook, pushover should still be disabled
        expect($final['telegram']['telegram_enabled'])->toBeFalse();
        expect($final['webhook']['webhook_enabled'])->toBeFalse();
        expect($final['pushover']['pushover_enabled'])->toBeFalse();
    });
});

// ─── 2. Individual channel update flow ───────────────────────────────────────

describe('Individual channel update flow', function () {
    test('configure slack webhook URL then update to new URL and verify persistence', function () {
        $headers = notifHeaders($this->bearerToken);

        // Step 1: Set initial Slack webhook URL
        $this->withHeaders($headers)->putJson('/api/v1/notification-channels/slack', [
            'slack_enabled' => true,
            'slack_webhook_url' => 'https://hooks.slack.com/services/T111/B111/first',
        ])->assertOk();

        // Step 2: Verify via GET (webhook_url is hidden, but slack_enabled should be true)
        $getResponse = $this->withHeaders($headers)->getJson('/api/v1/notification-channels');
        $getResponse->assertOk();
        expect($getResponse->json('slack.slack_enabled'))->toBeTrue();

        // Step 3: Update webhook URL to a new one (partial update)
        $updateResponse = $this->withHeaders($headers)->putJson('/api/v1/notification-channels/slack', [
            'slack_webhook_url' => 'https://hooks.slack.com/services/T222/B222/second',
        ]);
        $updateResponse->assertOk();
        $updateResponse->assertJsonFragment(['message' => 'Slack notification settings updated successfully.']);

        // Step 4: Verify slack_enabled still true after partial update
        $finalGet = $this->withHeaders($headers)->getJson('/api/v1/notification-channels');
        expect($finalGet->json('slack.slack_enabled'))->toBeTrue();
    });

    test('telegram update flow with token and chat_id', function () {
        $headers = notifHeaders($this->bearerToken);

        // Configure telegram
        $response = $this->withHeaders($headers)->putJson('/api/v1/notification-channels/telegram', [
            'telegram_enabled' => true,
            'telegram_token' => 'bot123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
            'telegram_chat_id' => '-1001234567890',
        ]);
        $response->assertOk();
        $response->assertJsonFragment(['message' => 'Telegram notification settings updated successfully.']);
        expect($response->json('settings.telegram_enabled'))->toBeTrue();

        // Verify persisted via GET (token and chat_id are hidden)
        $getResponse = $this->withHeaders($headers)->getJson('/api/v1/notification-channels');
        expect($getResponse->json('telegram.telegram_enabled'))->toBeTrue();
    });
});

// ─── 3. Cross-team notification isolation ────────────────────────────────────

describe('Cross-team notification isolation', function () {
    test('Team A configures slack but Team B GET returns Team B defaults', function () {
        $headers = notifHeaders($this->bearerToken);

        // Team A: Configure Slack
        $this->withHeaders($headers)->putJson('/api/v1/notification-channels/slack', [
            'slack_enabled' => true,
            'slack_webhook_url' => 'https://hooks.slack.com/services/TEAM-A/HOOK/url',
        ])->assertOk();

        // Verify Team A has slack enabled
        $teamAGet = $this->withHeaders($headers)->getJson('/api/v1/notification-channels');
        expect($teamAGet->json('slack.slack_enabled'))->toBeTrue();

        // Create Team B with its own user and token
        $teamB = Team::factory()->create();
        $userB = User::factory()->create();
        $teamB->members()->attach($userB->id, ['role' => 'owner']);

        session(['currentTeam' => $teamB]);
        $tokenB = $userB->createToken('team-b-token', ['*']);
        $bearerB = $tokenB->plainTextToken;
        session(['currentTeam' => $this->team]);

        // Team B: GET channels — should see Team B defaults, NOT Team A config
        $headersB = notifHeaders($bearerB);
        $teamBGet = $this->withHeaders($headersB)->getJson('/api/v1/notification-channels');
        $teamBGet->assertOk();
        expect($teamBGet->json('slack.slack_enabled'))->toBeFalse();
        expect($teamBGet->json('discord.discord_enabled'))->toBeFalse();
        expect($teamBGet->json('telegram.telegram_enabled'))->toBeFalse();
    });

    test('Team B configuring discord does not affect Team A settings', function () {
        $headers = notifHeaders($this->bearerToken);

        // Create Team B
        $teamB = Team::factory()->create();
        $userB = User::factory()->create();
        $teamB->members()->attach($userB->id, ['role' => 'owner']);

        session(['currentTeam' => $teamB]);
        $tokenB = $userB->createToken('team-b-token', ['*']);
        $bearerB = $tokenB->plainTextToken;
        session(['currentTeam' => $this->team]);

        // Team B: Configure Discord
        $headersB = notifHeaders($bearerB);
        $this->withHeaders($headersB)->putJson('/api/v1/notification-channels/discord', [
            'discord_enabled' => true,
            'discord_webhook_url' => 'https://discord.com/api/webhooks/999/teamb-hook',
        ])->assertOk();

        // Team A: GET should still show Discord disabled
        $teamAGet = $this->withHeaders($headers)->getJson('/api/v1/notification-channels');
        expect($teamAGet->json('discord.discord_enabled'))->toBeFalse();

        // Team B: GET should show Discord enabled
        $teamBGet = $this->withHeaders($headersB)->getJson('/api/v1/notification-channels');
        expect($teamBGet->json('discord.discord_enabled'))->toBeTrue();
    });
});

// ─── 4. Token ability enforcement ────────────────────────────────────────────

describe('Token ability enforcement', function () {
    test('read token can GET but cannot PUT notification channels', function () {
        $readToken = $this->user->createToken('read-only', ['read']);
        $headers = notifHeaders($readToken->plainTextToken);

        // Can read
        $getResponse = $this->withHeaders($headers)->getJson('/api/v1/notification-channels');
        $getResponse->assertOk();
        $getResponse->assertJsonStructure(['email', 'slack', 'discord', 'telegram', 'webhook', 'pushover']);

        // Cannot write
        $putResponse = $this->withHeaders($headers)->putJson('/api/v1/notification-channels/slack', [
            'slack_enabled' => true,
        ]);
        $putResponse->assertStatus(403);
    });

    test('write token can PUT notification channels', function () {
        $writeToken = $this->user->createToken('write-only', ['write']);
        $headers = notifHeaders($writeToken->plainTextToken);

        $response = $this->withHeaders($headers)->putJson('/api/v1/notification-channels/email', [
            'smtp_enabled' => true,
            'smtp_from_address' => 'admin@saturn.ac',
        ]);
        $response->assertOk();
        $response->assertJsonFragment(['message' => 'Email notification settings updated successfully.']);
    });

    test('deploy token cannot GET or PUT notification channels', function () {
        $deployToken = $this->user->createToken('deploy-only', ['deploy']);
        $headers = notifHeaders($deployToken->plainTextToken);

        // Deploy token lacks 'read' ability
        $this->withHeaders($headers)->getJson('/api/v1/notification-channels')
            ->assertStatus(403);

        // Deploy token lacks 'write' ability
        $this->withHeaders($headers)->putJson('/api/v1/notification-channels/slack', [
            'slack_enabled' => true,
        ])->assertStatus(403);
    });

    test('root token has full access to notification channels', function () {
        $rootToken = $this->user->createToken('root-token', ['root']);
        $headers = notifHeaders($rootToken->plainTextToken);

        // Root can GET
        $this->withHeaders($headers)->getJson('/api/v1/notification-channels')
            ->assertOk();

        // Root can PUT
        $this->withHeaders($headers)->putJson('/api/v1/notification-channels/discord', [
            'discord_enabled' => true,
            'discord_webhook_url' => 'https://discord.com/api/webhooks/root/access',
        ])->assertOk();
    });

    test('empty abilities token cannot access notification channels at all', function () {
        $emptyToken = $this->user->createToken('no-abilities', []);
        $headers = notifHeaders($emptyToken->plainTextToken);

        $this->withHeaders($headers)->getJson('/api/v1/notification-channels')
            ->assertStatus(403);

        $this->withHeaders($headers)->putJson('/api/v1/notification-channels/email', [
            'smtp_enabled' => true,
        ])->assertStatus(403);
    });
});

// ─── 5. Multi-channel enable/disable toggling ────────────────────────────────

describe('Multi-channel enable/disable toggling', function () {
    test('enable email + slack + telegram then disable slack and verify state', function () {
        $headers = notifHeaders($this->bearerToken);

        // Enable email
        $this->withHeaders($headers)->putJson('/api/v1/notification-channels/email', [
            'smtp_enabled' => true,
            'smtp_from_address' => 'alerts@saturn.ac',
        ])->assertOk();

        // Enable slack
        $this->withHeaders($headers)->putJson('/api/v1/notification-channels/slack', [
            'slack_enabled' => true,
            'slack_webhook_url' => 'https://hooks.slack.com/services/T/B/x',
        ])->assertOk();

        // Enable telegram
        $this->withHeaders($headers)->putJson('/api/v1/notification-channels/telegram', [
            'telegram_enabled' => true,
            'telegram_token' => 'bot999:token',
            'telegram_chat_id' => '-100999',
        ])->assertOk();

        // Verify all 3 enabled
        $allEnabled = $this->withHeaders($headers)->getJson('/api/v1/notification-channels');
        expect($allEnabled->json('email.smtp_enabled'))->toBeTrue();
        expect($allEnabled->json('slack.slack_enabled'))->toBeTrue();
        expect($allEnabled->json('telegram.telegram_enabled'))->toBeTrue();

        // Disable slack only
        $this->withHeaders($headers)->putJson('/api/v1/notification-channels/slack', [
            'slack_enabled' => false,
        ])->assertOk();

        // Verify email + telegram still enabled, slack disabled
        $afterDisable = $this->withHeaders($headers)->getJson('/api/v1/notification-channels');
        expect($afterDisable->json('email.smtp_enabled'))->toBeTrue();
        expect($afterDisable->json('slack.slack_enabled'))->toBeFalse();
        expect($afterDisable->json('telegram.telegram_enabled'))->toBeTrue();
    });

    test('disable all channels then re-enable one selectively', function () {
        $headers = notifHeaders($this->bearerToken);

        // Enable discord and webhook
        $this->withHeaders($headers)->putJson('/api/v1/notification-channels/discord', [
            'discord_enabled' => true,
            'discord_webhook_url' => 'https://discord.com/api/webhooks/1/abc',
        ])->assertOk();

        $this->withHeaders($headers)->putJson('/api/v1/notification-channels/webhook', [
            'webhook_enabled' => true,
            'webhook_url' => 'https://example.com/webhook',
        ])->assertOk();

        // Disable both
        $this->withHeaders($headers)->putJson('/api/v1/notification-channels/discord', [
            'discord_enabled' => false,
        ])->assertOk();

        $this->withHeaders($headers)->putJson('/api/v1/notification-channels/webhook', [
            'webhook_enabled' => false,
        ])->assertOk();

        // Verify both disabled
        $bothDisabled = $this->withHeaders($headers)->getJson('/api/v1/notification-channels');
        expect($bothDisabled->json('discord.discord_enabled'))->toBeFalse();
        expect($bothDisabled->json('webhook.webhook_enabled'))->toBeFalse();

        // Re-enable discord only
        $this->withHeaders($headers)->putJson('/api/v1/notification-channels/discord', [
            'discord_enabled' => true,
        ])->assertOk();

        $finalState = $this->withHeaders($headers)->getJson('/api/v1/notification-channels');
        expect($finalState->json('discord.discord_enabled'))->toBeTrue();
        expect($finalState->json('webhook.webhook_enabled'))->toBeFalse();
    });
});

// ─── 6. Validation errors ────────────────────────────────────────────────────

describe('Validation errors', function () {
    test('invalid email format returns 422', function () {
        $headers = notifHeaders($this->bearerToken);

        $response = $this->withHeaders($headers)->putJson('/api/v1/notification-channels/email', [
            'smtp_from_address' => 'not-an-email',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('smtp_from_address');
    });

    test('invalid webhook URL formats return 422 for slack, discord, and webhook channels', function () {
        $headers = notifHeaders($this->bearerToken);

        // Slack: invalid URL
        $this->withHeaders($headers)->putJson('/api/v1/notification-channels/slack', [
            'slack_webhook_url' => 'not-a-valid-url',
        ])->assertStatus(422)->assertJsonValidationErrors('slack_webhook_url');

        // Discord: invalid URL
        $this->withHeaders($headers)->putJson('/api/v1/notification-channels/discord', [
            'discord_webhook_url' => 'ftp://invalid-scheme',
        ])->assertStatus(422)->assertJsonValidationErrors('discord_webhook_url');

        // Webhook: invalid URL
        $this->withHeaders($headers)->putJson('/api/v1/notification-channels/webhook', [
            'webhook_url' => 'totally-not-a-url',
        ])->assertStatus(422)->assertJsonValidationErrors('webhook_url');
    });

    test('invalid smtp_port out of range returns 422', function () {
        $headers = notifHeaders($this->bearerToken);

        $response = $this->withHeaders($headers)->putJson('/api/v1/notification-channels/email', [
            'smtp_port' => 99999,
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('smtp_port');
    });

    test('non-boolean enabled flag returns 422', function () {
        $headers = notifHeaders($this->bearerToken);

        $response = $this->withHeaders($headers)->putJson('/api/v1/notification-channels/slack', [
            'slack_enabled' => 'yes-please',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('slack_enabled');
    });
});

// ─── 7. All 6 channel types configurable ─────────────────────────────────────

describe('All 6 channel types configurable', function () {
    test('configure all 6 channels and GET returns all with correct enabled state', function () {
        $headers = notifHeaders($this->bearerToken);

        // Email
        $this->withHeaders($headers)->putJson('/api/v1/notification-channels/email', [
            'smtp_enabled' => true,
            'smtp_from_address' => 'ops@saturn.ac',
            'smtp_host' => 'mail.saturn.ac',
            'smtp_port' => 465,
        ])->assertOk();

        // Slack
        $this->withHeaders($headers)->putJson('/api/v1/notification-channels/slack', [
            'slack_enabled' => true,
            'slack_webhook_url' => 'https://hooks.slack.com/services/ALL/SIX/test',
        ])->assertOk();

        // Discord
        $this->withHeaders($headers)->putJson('/api/v1/notification-channels/discord', [
            'discord_enabled' => true,
            'discord_webhook_url' => 'https://discord.com/api/webhooks/all/six',
        ])->assertOk();

        // Telegram
        $this->withHeaders($headers)->putJson('/api/v1/notification-channels/telegram', [
            'telegram_enabled' => true,
            'telegram_token' => 'bot555:all-six-test',
            'telegram_chat_id' => '-100555',
        ])->assertOk();

        // Webhook
        $this->withHeaders($headers)->putJson('/api/v1/notification-channels/webhook', [
            'webhook_enabled' => true,
            'webhook_url' => 'https://example.com/saturn-webhook',
        ])->assertOk();

        // Pushover
        $this->withHeaders($headers)->putJson('/api/v1/notification-channels/pushover', [
            'pushover_enabled' => true,
            'pushover_user_key' => 'user-key-abc123',
            'pushover_api_token' => 'api-token-xyz789',
        ])->assertOk();

        // GET all — verify all 6 enabled
        $all = $this->withHeaders($headers)->getJson('/api/v1/notification-channels');
        $all->assertOk();
        $json = $all->json();

        expect($json['email']['smtp_enabled'])->toBeTrue();
        expect($json['slack']['slack_enabled'])->toBeTrue();
        expect($json['discord']['discord_enabled'])->toBeTrue();
        expect($json['telegram']['telegram_enabled'])->toBeTrue();
        expect($json['webhook']['webhook_enabled'])->toBeTrue();
        expect($json['pushover']['pushover_enabled'])->toBeTrue();
    });
});

// ─── 8. Notification event flags and alternative providers ───────────────────

describe('Notification event flags and alternative providers', function () {
    test('default event flags are false and channel config persists alongside them', function () {
        $headers = notifHeaders($this->bearerToken);

        // Verify default event flags for slack
        $defaults = $this->withHeaders($headers)->getJson('/api/v1/notification-channels');
        expect($defaults->json('slack.deployment_success_slack_notifications'))->toBeFalse();
        expect($defaults->json('slack.deployment_failure_slack_notifications'))->toBeFalse();
        expect($defaults->json('slack.backup_success_slack_notifications'))->toBeFalse();
        expect($defaults->json('slack.backup_failure_slack_notifications'))->toBeFalse();

        // Configure slack channel — event flags are fillable on the model but
        // the FormRequest only validates slack_enabled and slack_webhook_url.
        // The update should succeed and persist validated fields.
        $response = $this->withHeaders($headers)->putJson('/api/v1/notification-channels/slack', [
            'slack_enabled' => true,
            'slack_webhook_url' => 'https://hooks.slack.com/services/T/B/events',
        ]);
        $response->assertOk();
        expect($response->json('settings.slack_enabled'))->toBeTrue();
    });

    test('configure email with Resend API key instead of SMTP', function () {
        $headers = notifHeaders($this->bearerToken);

        $response = $this->withHeaders($headers)->putJson('/api/v1/notification-channels/email', [
            'resend_enabled' => true,
            'resend_api_key' => 're_test_123456789',
        ]);
        $response->assertOk();
        $response->assertJsonFragment(['message' => 'Email notification settings updated successfully.']);
        expect($response->json('settings.resend_enabled'))->toBeTrue();

        // Verify persisted via GET
        $getResponse = $this->withHeaders($headers)->getJson('/api/v1/notification-channels');
        expect($getResponse->json('email.resend_enabled'))->toBeTrue();
        // resend_api_key should be hidden
        expect($getResponse->json('email'))->not->toHaveKey('resend_api_key');
    });

    test('pushover and webhook configuration persist correctly', function () {
        $headers = notifHeaders($this->bearerToken);

        // Pushover
        $pushoverResponse = $this->withHeaders($headers)->putJson('/api/v1/notification-channels/pushover', [
            'pushover_enabled' => true,
            'pushover_user_key' => 'user-key-events',
            'pushover_api_token' => 'api-token-events',
        ]);
        $pushoverResponse->assertOk();
        $pushoverResponse->assertJsonFragment(['message' => 'Pushover notification settings updated successfully.']);
        expect($pushoverResponse->json('settings.pushover_enabled'))->toBeTrue();

        // Webhook
        $webhookResponse = $this->withHeaders($headers)->putJson('/api/v1/notification-channels/webhook', [
            'webhook_enabled' => true,
            'webhook_url' => 'https://example.com/saturn-events',
        ]);
        $webhookResponse->assertOk();
        $webhookResponse->assertJsonFragment(['message' => 'Webhook notification settings updated successfully.']);
        expect($webhookResponse->json('settings.webhook_enabled'))->toBeTrue();

        // Verify both via GET (keys/URLs hidden, enabled flags persist)
        $getResponse = $this->withHeaders($headers)->getJson('/api/v1/notification-channels');
        expect($getResponse->json('pushover.pushover_enabled'))->toBeTrue();
        expect($getResponse->json('webhook.webhook_enabled'))->toBeTrue();
    });
});

// ─── 9. Response structure and hidden fields ─────────────────────────────────

describe('Response structure and hidden fields', function () {
    test('GET response hides all sensitive fields across all 6 channels', function () {
        $headers = notifHeaders($this->bearerToken);

        // Configure channels with sensitive data
        $this->withHeaders($headers)->putJson('/api/v1/notification-channels/slack', [
            'slack_enabled' => true,
            'slack_webhook_url' => 'https://hooks.slack.com/services/SECRET/URL/here',
        ])->assertOk();

        $this->withHeaders($headers)->putJson('/api/v1/notification-channels/telegram', [
            'telegram_enabled' => true,
            'telegram_token' => 'bot-secret-token-123',
            'telegram_chat_id' => '-100secret',
        ])->assertOk();

        // GET all channels
        $response = $this->withHeaders($headers)->getJson('/api/v1/notification-channels');
        $response->assertOk();
        $json = $response->json();

        // Slack webhook_url should be hidden
        expect($json['slack'])->not->toHaveKey('slack_webhook_url');

        // Discord webhook_url should be hidden
        expect($json['discord'])->not->toHaveKey('discord_webhook_url');

        // Telegram token and chat_id should be hidden
        expect($json['telegram'])->not->toHaveKey('telegram_token');
        expect($json['telegram'])->not->toHaveKey('telegram_chat_id');

        // Email smtp_password and resend_api_key should be hidden
        expect($json['email'])->not->toHaveKey('smtp_password');
        expect($json['email'])->not->toHaveKey('resend_api_key');

        // Webhook URL should be hidden
        expect($json['webhook'])->not->toHaveKey('webhook_url');

        // Pushover keys should be hidden
        expect($json['pushover'])->not->toHaveKey('pushover_user_key');
        expect($json['pushover'])->not->toHaveKey('pushover_api_token');
    });

    test('PUT response has consistent structure with message and settings object', function () {
        $headers = notifHeaders($this->bearerToken);

        // Test multiple channels for consistent response shape
        $slackResponse = $this->withHeaders($headers)->putJson('/api/v1/notification-channels/slack', [
            'slack_enabled' => true,
            'slack_webhook_url' => 'https://hooks.slack.com/services/T/B/struct',
        ]);
        $slackResponse->assertOk();
        $slackResponse->assertJsonStructure(['message', 'settings']);
        expect($slackResponse->json('settings'))->toHaveKey('slack_enabled');

        $discordResponse = $this->withHeaders($headers)->putJson('/api/v1/notification-channels/discord', [
            'discord_enabled' => true,
            'discord_webhook_url' => 'https://discord.com/api/webhooks/struct/test',
        ]);
        $discordResponse->assertOk();
        $discordResponse->assertJsonStructure(['message', 'settings']);
        expect($discordResponse->json('settings'))->toHaveKey('discord_enabled');
    });
});
