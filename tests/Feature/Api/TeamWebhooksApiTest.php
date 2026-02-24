<?php

use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\TeamWebhook;
use App\Models\User;
use App\Models\WebhookDelivery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

uses(DatabaseTransactions::class);

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
        InstanceSettings::create([
            'id' => 0,
            'is_api_enabled' => true,
        ]);
    }
});

// ─── INDEX ───────────────────────────────────────────────────────────

test('list webhooks returns empty array when none exist', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->getJson('/api/v1/webhooks');

    $response->assertOk()
        ->assertJsonStructure(['data', 'available_events'])
        ->assertJsonCount(0, 'data');
});

test('list webhooks returns team webhooks with deliveries', function () {
    $webhook = TeamWebhook::factory()->create(['team_id' => $this->team->id]);
    WebhookDelivery::factory()->count(3)->create([
        'team_webhook_id' => $webhook->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->getJson('/api/v1/webhooks');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $webhook->uuid)
        ->assertJsonPath('data.0.name', $webhook->name);
});

test('list webhooks does not return other team webhooks', function () {
    $otherTeam = Team::factory()->create();
    TeamWebhook::factory()->create(['team_id' => $otherTeam->id]);

    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->getJson('/api/v1/webhooks');

    $response->assertOk()
        ->assertJsonCount(0, 'data');
});

// ─── STORE ───────────────────────────────────────────────────────────

test('create webhook with valid data', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->postJson('/api/v1/webhooks', [
            'name' => 'My Webhook',
            'url' => 'https://example.com/webhook',
            'events' => ['deploy.started', 'deploy.finished'],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'My Webhook')
        ->assertJsonPath('data.events', ['deploy.started', 'deploy.finished'])
        ->assertJsonPath('data.enabled', true);

    // Secret should be returned on creation
    expect($response->json('data.secret'))->toStartWith('whsec_');
});

test('create webhook validates required fields', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->postJson('/api/v1/webhooks', []);

    $response->assertStatus(422)
        ->assertJsonStructure(['errors' => ['name', 'url', 'events']]);
});

test('create webhook validates url format', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->postJson('/api/v1/webhooks', [
            'name' => 'Bad URL',
            'url' => 'not-a-url',
            'events' => ['deploy.started'],
        ]);

    $response->assertStatus(422)
        ->assertJsonStructure(['errors' => ['url']]);
});

test('create webhook validates event values', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->postJson('/api/v1/webhooks', [
            'name' => 'Bad Events',
            'url' => 'https://example.com/webhook',
            'events' => ['invalid.event'],
        ]);

    $response->assertStatus(422);
});

// ─── SHOW ────────────────────────────────────────────────────────────

test('show webhook returns details', function () {
    $webhook = TeamWebhook::factory()->create(['team_id' => $this->team->id]);

    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->getJson("/api/v1/webhooks/{$webhook->uuid}");

    $response->assertOk()
        ->assertJsonPath('uuid', $webhook->uuid)
        ->assertJsonPath('name', $webhook->name)
        ->assertJsonStructure(['id', 'uuid', 'name', 'url', 'secret', 'events', 'enabled', 'created_at']);
});

test('show webhook returns 404 for other team webhook', function () {
    $otherTeam = Team::factory()->create();
    $webhook = TeamWebhook::factory()->create(['team_id' => $otherTeam->id]);

    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->getJson("/api/v1/webhooks/{$webhook->uuid}");

    $response->assertNotFound();
});

test('show webhook returns 404 for nonexistent uuid', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->getJson('/api/v1/webhooks/nonexistent-uuid');

    $response->assertNotFound();
});

// ─── UPDATE ──────────────────────────────────────────────────────────

test('update webhook name', function () {
    $webhook = TeamWebhook::factory()->create(['team_id' => $this->team->id]);

    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->putJson("/api/v1/webhooks/{$webhook->uuid}", [
            'name' => 'Updated Name',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Updated Name');
});

test('update webhook url and events', function () {
    $webhook = TeamWebhook::factory()->create(['team_id' => $this->team->id]);

    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->putJson("/api/v1/webhooks/{$webhook->uuid}", [
            'url' => 'https://new-endpoint.com/hook',
            'events' => ['deploy.failed'],
        ]);

    $response->assertOk()
        ->assertJsonPath('data.events', ['deploy.failed']);
});

test('update webhook returns 404 for other team', function () {
    $otherTeam = Team::factory()->create();
    $webhook = TeamWebhook::factory()->create(['team_id' => $otherTeam->id]);

    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->putJson("/api/v1/webhooks/{$webhook->uuid}", ['name' => 'Hack']);

    $response->assertNotFound();
});

test('update webhook validates fields', function () {
    $webhook = TeamWebhook::factory()->create(['team_id' => $this->team->id]);

    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->putJson("/api/v1/webhooks/{$webhook->uuid}", [
            'url' => 'not-a-url',
        ]);

    $response->assertStatus(422);
});

// ─── DELETE ──────────────────────────────────────────────────────────

test('delete webhook', function () {
    $webhook = TeamWebhook::factory()->create(['team_id' => $this->team->id]);

    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->deleteJson("/api/v1/webhooks/{$webhook->uuid}");

    $response->assertOk()
        ->assertJsonPath('message', 'Webhook deleted successfully.');

    expect(TeamWebhook::find($webhook->id))->toBeNull();
});

test('delete webhook returns 404 for other team', function () {
    $otherTeam = Team::factory()->create();
    $webhook = TeamWebhook::factory()->create(['team_id' => $otherTeam->id]);

    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->deleteJson("/api/v1/webhooks/{$webhook->uuid}");

    $response->assertNotFound();
    expect(TeamWebhook::find($webhook->id))->not->toBeNull();
});

// ─── TOGGLE ──────────────────────────────────────────────────────────

test('toggle webhook enables disabled webhook', function () {
    $webhook = TeamWebhook::factory()->disabled()->create(['team_id' => $this->team->id]);
    expect($webhook->enabled)->toBeFalse();

    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->postJson("/api/v1/webhooks/{$webhook->uuid}/toggle");

    $response->assertOk()
        ->assertJsonPath('enabled', true);
});

test('toggle webhook disables enabled webhook', function () {
    $webhook = TeamWebhook::factory()->create(['team_id' => $this->team->id, 'enabled' => true]);

    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->postJson("/api/v1/webhooks/{$webhook->uuid}/toggle");

    $response->assertOk()
        ->assertJsonPath('enabled', false);
});

// ─── TEST WEBHOOK ────────────────────────────────────────────────────

test('test webhook creates delivery and dispatches job', function () {
    $webhook = TeamWebhook::factory()->create(['team_id' => $this->team->id]);

    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->postJson("/api/v1/webhooks/{$webhook->uuid}/test");

    $response->assertOk()
        ->assertJsonStructure(['message', 'delivery_uuid']);

    // Verify delivery was created
    $deliveryUuid = $response->json('delivery_uuid');
    $delivery = WebhookDelivery::where('uuid', $deliveryUuid)->first();
    expect($delivery)->not->toBeNull();
    expect($delivery->event)->toBe('test.event');
    expect($delivery->status)->toBe('pending');
});

test('test webhook returns 404 for other team', function () {
    $otherTeam = Team::factory()->create();
    $webhook = TeamWebhook::factory()->create(['team_id' => $otherTeam->id]);

    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->postJson("/api/v1/webhooks/{$webhook->uuid}/test");

    $response->assertNotFound();
});

// ─── DELIVERIES ──────────────────────────────────────────────────────

test('list webhook deliveries', function () {
    $webhook = TeamWebhook::factory()->create(['team_id' => $this->team->id]);
    WebhookDelivery::factory()->count(5)->create(['team_webhook_id' => $webhook->id]);

    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->getJson("/api/v1/webhooks/{$webhook->uuid}/deliveries");

    $response->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']])
        ->assertJsonPath('meta.total', 5);
});

test('list webhook deliveries supports pagination', function () {
    $webhook = TeamWebhook::factory()->create(['team_id' => $this->team->id]);
    WebhookDelivery::factory()->count(25)->create(['team_webhook_id' => $webhook->id]);

    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->getJson("/api/v1/webhooks/{$webhook->uuid}/deliveries?per_page=10");

    $response->assertOk()
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonPath('meta.total', 25)
        ->assertJsonCount(10, 'data');
});

test('list webhook deliveries returns 404 for other team', function () {
    $otherTeam = Team::factory()->create();
    $webhook = TeamWebhook::factory()->create(['team_id' => $otherTeam->id]);

    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->getJson("/api/v1/webhooks/{$webhook->uuid}/deliveries");

    $response->assertNotFound();
});

// ─── RETRY DELIVERY ──────────────────────────────────────────────────

test('retry failed delivery', function () {
    $webhook = TeamWebhook::factory()->create(['team_id' => $this->team->id]);
    $delivery = WebhookDelivery::factory()->failed()->create([
        'team_webhook_id' => $webhook->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->postJson("/api/v1/webhooks/{$webhook->uuid}/deliveries/{$delivery->uuid}/retry");

    $response->assertOk()
        ->assertJsonPath('message', 'Retry queued for delivery.');

    // Verify status reset
    $delivery->refresh();
    expect($delivery->status)->toBe('pending');
});

test('retry delivery returns 404 for nonexistent delivery', function () {
    $webhook = TeamWebhook::factory()->create(['team_id' => $this->team->id]);

    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->postJson("/api/v1/webhooks/{$webhook->uuid}/deliveries/nonexistent-uuid/retry");

    $response->assertNotFound();
});

test('retry delivery returns 404 for other team webhook', function () {
    $otherTeam = Team::factory()->create();
    $webhook = TeamWebhook::factory()->create(['team_id' => $otherTeam->id]);
    $delivery = WebhookDelivery::factory()->failed()->create([
        'team_webhook_id' => $webhook->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->postJson("/api/v1/webhooks/{$webhook->uuid}/deliveries/{$delivery->uuid}/retry");

    $response->assertNotFound();
});

// ─── AUTH ────────────────────────────────────────────────────────────

test('unauthenticated request returns 401', function () {
    $response = $this->getJson('/api/v1/webhooks');
    $response->assertUnauthorized();
});

test('read-only token can list webhooks', function () {
    $readToken = $this->user->createToken('read-token', ['read']);

    $response = $this->withHeader('Authorization', "Bearer {$readToken->plainTextToken}")
        ->getJson('/api/v1/webhooks');

    $response->assertOk();
});

test('read-only token cannot create webhook', function () {
    $readToken = $this->user->createToken('read-token', ['read']);

    $response = $this->withHeader('Authorization', "Bearer {$readToken->plainTextToken}")
        ->postJson('/api/v1/webhooks', [
            'name' => 'Test',
            'url' => 'https://example.com',
            'events' => ['deploy.started'],
        ]);

    $response->assertStatus(403);
});
