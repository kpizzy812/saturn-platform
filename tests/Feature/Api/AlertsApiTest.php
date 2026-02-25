<?php

use App\Models\Alert;
use App\Models\AlertHistory;
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

function alertHeaders(string $bearerToken): array
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
        $this->getJson('/api/v1/alerts')
            ->assertStatus(401);
    });

    test('rejects request with invalid token', function () {
        $this->withHeaders([
            'Authorization' => 'Bearer invalid-token-value',
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/alerts')
            ->assertStatus(401);
    });

    test('requires read ability for GET endpoints', function () {
        $deployToken = $this->user->createToken('deploy-only', ['deploy']);

        $this->withHeaders(alertHeaders($deployToken->plainTextToken))
            ->getJson('/api/v1/alerts')
            ->assertStatus(403);
    });

    test('requires write ability for POST endpoints', function () {
        $readToken = $this->user->createToken('read-only', ['read']);

        $this->withHeaders(alertHeaders($readToken->plainTextToken))
            ->postJson('/api/v1/alerts', [
                'name' => 'Test Alert',
                'metric' => 'cpu',
                'condition' => '>',
                'threshold' => 80,
                'duration' => 5,
            ])
            ->assertStatus(403);
    });

    test('requires write ability for PUT endpoints', function () {
        $readToken = $this->user->createToken('read-only', ['read']);

        $alert = Alert::create([
            'name' => 'Test',
            'metric' => 'cpu',
            'condition' => '>',
            'threshold' => 80,
            'duration' => 5,
            'team_id' => $this->team->id,
            'enabled' => true,
        ]);

        $this->withHeaders(alertHeaders($readToken->plainTextToken))
            ->putJson("/api/v1/alerts/{$alert->uuid}", ['name' => 'Updated'])
            ->assertStatus(403);
    });

    test('requires write ability for DELETE endpoints', function () {
        $readToken = $this->user->createToken('read-only', ['read']);

        $alert = Alert::create([
            'name' => 'Test',
            'metric' => 'cpu',
            'condition' => '>',
            'threshold' => 80,
            'duration' => 5,
            'team_id' => $this->team->id,
            'enabled' => true,
        ]);

        $this->withHeaders(alertHeaders($readToken->plainTextToken))
            ->deleteJson("/api/v1/alerts/{$alert->uuid}")
            ->assertStatus(403);
    });
});

// ─── GET /api/v1/alerts ───

describe('GET /api/v1/alerts', function () {
    test('returns empty array when no alerts exist', function () {
        $response = $this->withHeaders(alertHeaders($this->bearerToken))
            ->getJson('/api/v1/alerts');

        $response->assertStatus(200);
        $response->assertJson([]);
    });

    test('returns alerts for the current team', function () {
        Alert::create([
            'name' => 'High CPU',
            'metric' => 'cpu',
            'condition' => '>',
            'threshold' => 90,
            'duration' => 5,
            'team_id' => $this->team->id,
            'enabled' => true,
            'channels' => ['email', 'slack'],
        ]);

        $response = $this->withHeaders(alertHeaders($this->bearerToken))
            ->getJson('/api/v1/alerts');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment([
            'name' => 'High CPU',
            'metric' => 'cpu',
            'condition' => '>',
            'threshold' => 90.0,
            'duration' => 5,
            'enabled' => true,
        ]);
    });

    test('returns alerts ordered by created_at desc', function () {
        Alert::create([
            'name' => 'First Alert',
            'metric' => 'cpu',
            'condition' => '>',
            'threshold' => 80,
            'duration' => 5,
            'team_id' => $this->team->id,
            'enabled' => true,
        ]);

        Alert::create([
            'name' => 'Second Alert',
            'metric' => 'memory',
            'condition' => '>',
            'threshold' => 90,
            'duration' => 10,
            'team_id' => $this->team->id,
            'enabled' => true,
        ]);

        $response = $this->withHeaders(alertHeaders($this->bearerToken))
            ->getJson('/api/v1/alerts');

        $response->assertStatus(200);
        $response->assertJsonCount(2);

        $data = $response->json();
        expect($data[0]['name'])->toBe('Second Alert');
        expect($data[1]['name'])->toBe('First Alert');
    });

    test('SECURITY: does not return alerts from another team', function () {
        $otherTeam = Team::factory()->create();

        Alert::create([
            'name' => 'Other Team Alert',
            'metric' => 'cpu',
            'condition' => '>',
            'threshold' => 80,
            'duration' => 5,
            'team_id' => $otherTeam->id,
            'enabled' => true,
        ]);

        Alert::create([
            'name' => 'My Alert',
            'metric' => 'memory',
            'condition' => '>',
            'threshold' => 90,
            'duration' => 10,
            'team_id' => $this->team->id,
            'enabled' => true,
        ]);

        $response = $this->withHeaders(alertHeaders($this->bearerToken))
            ->getJson('/api/v1/alerts');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['name' => 'My Alert']);
        $response->assertJsonMissing(['name' => 'Other Team Alert']);
    });
});

// ─── GET /api/v1/alerts/{uuid} ───

describe('GET /api/v1/alerts/{uuid}', function () {
    test('returns alert details with history', function () {
        $alert = Alert::create([
            'name' => 'CPU Alert',
            'metric' => 'cpu',
            'condition' => '>',
            'threshold' => 80,
            'duration' => 5,
            'team_id' => $this->team->id,
            'enabled' => true,
            'channels' => ['email'],
        ]);

        $history = new AlertHistory([
            'triggered_at' => now()->subMinutes(30),
            'value' => 95.5,
            'status' => 'triggered',
        ]);
        $history->alert_id = $alert->id;
        $history->save();

        $resolved = new AlertHistory([
            'triggered_at' => now()->subHours(2),
            'resolved_at' => now()->subHour(),
            'value' => 85.0,
            'status' => 'resolved',
        ]);
        $resolved->alert_id = $alert->id;
        $resolved->save();

        $response = $this->withHeaders(alertHeaders($this->bearerToken))
            ->getJson("/api/v1/alerts/{$alert->uuid}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'CPU Alert']);
        $response->assertJsonStructure([
            'id', 'uuid', 'name', 'metric', 'condition', 'threshold',
            'duration', 'enabled', 'channels', 'triggered_count',
            'last_triggered_at', 'created_at', 'updated_at',
            'history' => [
                '*' => ['id', 'triggered_at', 'resolved_at', 'value', 'status'],
            ],
        ]);

        $historyData = $response->json('history');
        expect(count($historyData))->toBe(2);
    });

    test('returns 404 for non-existent alert', function () {
        $response = $this->withHeaders(alertHeaders($this->bearerToken))
            ->getJson('/api/v1/alerts/non-existent-uuid');

        $response->assertStatus(404);
    });

    test('SECURITY: cannot access alert from another team', function () {
        $otherTeam = Team::factory()->create();

        $alert = Alert::create([
            'name' => 'Other Team Alert',
            'metric' => 'cpu',
            'condition' => '>',
            'threshold' => 80,
            'duration' => 5,
            'team_id' => $otherTeam->id,
            'enabled' => true,
        ]);

        $response = $this->withHeaders(alertHeaders($this->bearerToken))
            ->getJson("/api/v1/alerts/{$alert->uuid}");

        $response->assertStatus(404);
    });
});

// ─── POST /api/v1/alerts ───

describe('POST /api/v1/alerts', function () {
    test('creates a new alert with valid data', function () {
        $response = $this->withHeaders(alertHeaders($this->bearerToken))
            ->postJson('/api/v1/alerts', [
                'name' => 'High Memory Usage',
                'metric' => 'memory',
                'condition' => '>',
                'threshold' => 85,
                'duration' => 10,
                'channels' => ['email', 'slack'],
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('message', 'Alert created successfully.');
        $response->assertJsonPath('alert.name', 'High Memory Usage');
        $response->assertJsonPath('alert.metric', 'memory');
        $response->assertJsonPath('alert.enabled', true);
        $response->assertJsonPath('alert.channels', ['email', 'slack']);

        $this->assertDatabaseHas('alerts', [
            'name' => 'High Memory Usage',
            'team_id' => $this->team->id,
        ]);
    });

    test('creates alert with enabled=false', function () {
        $response = $this->withHeaders(alertHeaders($this->bearerToken))
            ->postJson('/api/v1/alerts', [
                'name' => 'Disabled Alert',
                'metric' => 'cpu',
                'condition' => '>',
                'threshold' => 90,
                'duration' => 5,
                'enabled' => false,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('alert.enabled', false);
    });

    test('defaults enabled to true when not specified', function () {
        $response = $this->withHeaders(alertHeaders($this->bearerToken))
            ->postJson('/api/v1/alerts', [
                'name' => 'Default Alert',
                'metric' => 'disk',
                'condition' => '>',
                'threshold' => 80,
                'duration' => 5,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('alert.enabled', true);
    });

    test('returns 422 when name is missing', function () {
        $response = $this->withHeaders(alertHeaders($this->bearerToken))
            ->postJson('/api/v1/alerts', [
                'metric' => 'cpu',
                'condition' => '>',
                'threshold' => 80,
                'duration' => 5,
            ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['name']]);
    });

    test('returns 422 for invalid metric', function () {
        $response = $this->withHeaders(alertHeaders($this->bearerToken))
            ->postJson('/api/v1/alerts', [
                'name' => 'Test',
                'metric' => 'invalid_metric',
                'condition' => '>',
                'threshold' => 80,
                'duration' => 5,
            ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['metric']]);
    });

    test('returns 422 for invalid condition', function () {
        $response = $this->withHeaders(alertHeaders($this->bearerToken))
            ->postJson('/api/v1/alerts', [
                'name' => 'Test',
                'metric' => 'cpu',
                'condition' => '>=',
                'threshold' => 80,
                'duration' => 5,
            ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['condition']]);
    });

    test('returns 422 for negative threshold', function () {
        $response = $this->withHeaders(alertHeaders($this->bearerToken))
            ->postJson('/api/v1/alerts', [
                'name' => 'Test',
                'metric' => 'cpu',
                'condition' => '>',
                'threshold' => -5,
                'duration' => 5,
            ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['threshold']]);
    });

    test('returns 422 for duration less than 1', function () {
        $response = $this->withHeaders(alertHeaders($this->bearerToken))
            ->postJson('/api/v1/alerts', [
                'name' => 'Test',
                'metric' => 'cpu',
                'condition' => '>',
                'threshold' => 80,
                'duration' => 0,
            ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['duration']]);
    });

    test('returns 422 for duration exceeding 1440', function () {
        $response = $this->withHeaders(alertHeaders($this->bearerToken))
            ->postJson('/api/v1/alerts', [
                'name' => 'Test',
                'metric' => 'cpu',
                'condition' => '>',
                'threshold' => 80,
                'duration' => 1441,
            ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['duration']]);
    });

    test('returns 422 for invalid channel name', function () {
        $response = $this->withHeaders(alertHeaders($this->bearerToken))
            ->postJson('/api/v1/alerts', [
                'name' => 'Test',
                'metric' => 'cpu',
                'condition' => '>',
                'threshold' => 80,
                'duration' => 5,
                'channels' => ['email', 'invalid_channel'],
            ]);

        $response->assertStatus(422);
    });

    test('accepts all valid metrics', function () {
        $validMetrics = ['cpu', 'memory', 'disk', 'error_rate', 'response_time'];

        foreach ($validMetrics as $metric) {
            $response = $this->withHeaders(alertHeaders($this->bearerToken))
                ->postJson('/api/v1/alerts', [
                    'name' => "Test {$metric}",
                    'metric' => $metric,
                    'condition' => '>',
                    'threshold' => 80,
                    'duration' => 5,
                ]);

            $response->assertStatus(201);
        }
    });

    test('accepts all valid conditions', function () {
        foreach (['>', '<', '='] as $condition) {
            $response = $this->withHeaders(alertHeaders($this->bearerToken))
                ->postJson('/api/v1/alerts', [
                    'name' => "Test {$condition}",
                    'metric' => 'cpu',
                    'condition' => $condition,
                    'threshold' => 80,
                    'duration' => 5,
                ]);

            $response->assertStatus(201);
        }
    });
});

// ─── PUT /api/v1/alerts/{uuid} ───

describe('PUT /api/v1/alerts/{uuid}', function () {
    test('updates alert name', function () {
        $alert = Alert::create([
            'name' => 'Original Name',
            'metric' => 'cpu',
            'condition' => '>',
            'threshold' => 80,
            'duration' => 5,
            'team_id' => $this->team->id,
            'enabled' => true,
        ]);

        $response = $this->withHeaders(alertHeaders($this->bearerToken))
            ->putJson("/api/v1/alerts/{$alert->uuid}", [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Alert updated successfully.');
        $response->assertJsonPath('alert.name', 'Updated Name');

        $alert->refresh();
        expect($alert->name)->toBe('Updated Name');
    });

    test('updates alert threshold and duration', function () {
        $alert = Alert::create([
            'name' => 'Test',
            'metric' => 'cpu',
            'condition' => '>',
            'threshold' => 80,
            'duration' => 5,
            'team_id' => $this->team->id,
            'enabled' => true,
        ]);

        $response = $this->withHeaders(alertHeaders($this->bearerToken))
            ->putJson("/api/v1/alerts/{$alert->uuid}", [
                'threshold' => 95,
                'duration' => 15,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('alert.threshold', 95.0);
        $response->assertJsonPath('alert.duration', 15);
    });

    test('toggles enabled flag', function () {
        $alert = Alert::create([
            'name' => 'Test',
            'metric' => 'cpu',
            'condition' => '>',
            'threshold' => 80,
            'duration' => 5,
            'team_id' => $this->team->id,
            'enabled' => true,
        ]);

        $response = $this->withHeaders(alertHeaders($this->bearerToken))
            ->putJson("/api/v1/alerts/{$alert->uuid}", [
                'enabled' => false,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('alert.enabled', false);

        $alert->refresh();
        expect($alert->enabled)->toBeFalse();
    });

    test('updates channels', function () {
        $alert = Alert::create([
            'name' => 'Test',
            'metric' => 'cpu',
            'condition' => '>',
            'threshold' => 80,
            'duration' => 5,
            'team_id' => $this->team->id,
            'enabled' => true,
            'channels' => ['email'],
        ]);

        $response = $this->withHeaders(alertHeaders($this->bearerToken))
            ->putJson("/api/v1/alerts/{$alert->uuid}", [
                'channels' => ['email', 'slack', 'discord'],
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('alert.channels', ['email', 'slack', 'discord']);
    });

    test('returns 404 for non-existent alert', function () {
        $response = $this->withHeaders(alertHeaders($this->bearerToken))
            ->putJson('/api/v1/alerts/non-existent-uuid', [
                'name' => 'Updated',
            ]);

        $response->assertStatus(404);
    });

    test('SECURITY: cannot update alert from another team', function () {
        $otherTeam = Team::factory()->create();

        $alert = Alert::create([
            'name' => 'Other Team Alert',
            'metric' => 'cpu',
            'condition' => '>',
            'threshold' => 80,
            'duration' => 5,
            'team_id' => $otherTeam->id,
            'enabled' => true,
        ]);

        $response = $this->withHeaders(alertHeaders($this->bearerToken))
            ->putJson("/api/v1/alerts/{$alert->uuid}", [
                'name' => 'Hacked Name',
            ]);

        $response->assertStatus(404);

        $alert->refresh();
        expect($alert->name)->toBe('Other Team Alert');
    });

    test('returns 422 for invalid update data', function () {
        $alert = Alert::create([
            'name' => 'Test',
            'metric' => 'cpu',
            'condition' => '>',
            'threshold' => 80,
            'duration' => 5,
            'team_id' => $this->team->id,
            'enabled' => true,
        ]);

        $response = $this->withHeaders(alertHeaders($this->bearerToken))
            ->putJson("/api/v1/alerts/{$alert->uuid}", [
                'metric' => 'invalid_metric',
            ]);

        $response->assertStatus(422);
    });
});

// ─── DELETE /api/v1/alerts/{uuid} ───

describe('DELETE /api/v1/alerts/{uuid}', function () {
    test('deletes an alert', function () {
        $alert = Alert::create([
            'name' => 'To Delete',
            'metric' => 'cpu',
            'condition' => '>',
            'threshold' => 80,
            'duration' => 5,
            'team_id' => $this->team->id,
            'enabled' => true,
        ]);

        $response = $this->withHeaders(alertHeaders($this->bearerToken))
            ->deleteJson("/api/v1/alerts/{$alert->uuid}");

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Alert deleted successfully.');

        $this->assertDatabaseMissing('alerts', ['id' => $alert->id]);
    });

    test('returns 404 for non-existent alert', function () {
        $response = $this->withHeaders(alertHeaders($this->bearerToken))
            ->deleteJson('/api/v1/alerts/non-existent-uuid');

        $response->assertStatus(404);
    });

    test('SECURITY: cannot delete alert from another team', function () {
        $otherTeam = Team::factory()->create();

        $alert = Alert::create([
            'name' => 'Other Team Alert',
            'metric' => 'cpu',
            'condition' => '>',
            'threshold' => 80,
            'duration' => 5,
            'team_id' => $otherTeam->id,
            'enabled' => true,
        ]);

        $response = $this->withHeaders(alertHeaders($this->bearerToken))
            ->deleteJson("/api/v1/alerts/{$alert->uuid}");

        $response->assertStatus(404);

        $this->assertDatabaseHas('alerts', ['id' => $alert->id]);
    });
});
