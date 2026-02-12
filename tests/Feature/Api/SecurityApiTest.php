<?php

use App\Models\PrivateKey;
use App\Models\Server;
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

    if (! \App\Models\InstanceSettings::first()) {
        \App\Models\InstanceSettings::create([
            'id' => 0,
            'is_api_enabled' => true,
        ]);
    }
});

function securityHeaders($bearerToken): array
{
    return [
        'Authorization' => 'Bearer '.$bearerToken,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ];
}

// ─── GET /api/v1/security/keys ───

describe('GET /api/v1/security/keys', function () {
    test('returns 401 without authentication', function () {
        $this->getJson('/api/v1/security/keys')
            ->assertStatus(401);
    });

    test('returns empty array when no keys exist', function () {
        $this->withHeaders(securityHeaders($this->bearerToken))
            ->getJson('/api/v1/security/keys')
            ->assertStatus(200)
            ->assertJson([]);
    });

    test('returns list of private keys for the team', function () {
        PrivateKey::factory()->count(3)->create(['team_id' => $this->team->id]);

        $response = $this->withHeaders(securityHeaders($this->bearerToken))
            ->getJson('/api/v1/security/keys');

        $response->assertStatus(200);
        expect(count($response->json()))->toBe(3);
    });

    test('does not return keys from other teams', function () {
        $otherTeam = Team::factory()->create();
        PrivateKey::factory()->create(['team_id' => $this->team->id]);
        PrivateKey::factory()->create(['team_id' => $otherTeam->id]);

        $response = $this->withHeaders(securityHeaders($this->bearerToken))
            ->getJson('/api/v1/security/keys');

        $response->assertStatus(200);
        expect(count($response->json()))->toBe(1);
    });

    test('hides private_key field when token lacks sensitive permission', function () {
        PrivateKey::factory()->create(['team_id' => $this->team->id]);

        // Create a token with only 'read' ability (no 'root' or 'read:sensitive')
        $limitedToken = $this->user->createToken('limited-token', ['read']);
        $limitedBearer = $limitedToken->plainTextToken;

        $response = $this->withHeaders(securityHeaders($limitedBearer))
            ->getJson('/api/v1/security/keys');

        $response->assertStatus(200);
        $firstKey = $response->json()[0];
        expect($firstKey)->not->toHaveKey('private_key');
    });
});

// ─── GET /api/v1/security/keys/{uuid} ───

describe('GET /api/v1/security/keys/{uuid}', function () {
    test('returns key details by UUID', function () {
        $key = PrivateKey::factory()->create(['team_id' => $this->team->id]);

        $response = $this->withHeaders(securityHeaders($this->bearerToken))
            ->getJson("/api/v1/security/keys/{$key->uuid}");

        $response->assertStatus(200);
        expect($response->json('uuid'))->toBe($key->uuid);
    });

    test('returns 404 for non-existent key', function () {
        $this->withHeaders(securityHeaders($this->bearerToken))
            ->getJson('/api/v1/security/keys/non-existent-uuid')
            ->assertStatus(404)
            ->assertJson(['message' => 'Private Key not found.']);
    });

    test('returns 404 for key belonging to another team', function () {
        $otherTeam = Team::factory()->create();
        $key = PrivateKey::factory()->create(['team_id' => $otherTeam->id]);

        $this->withHeaders(securityHeaders($this->bearerToken))
            ->getJson("/api/v1/security/keys/{$key->uuid}")
            ->assertStatus(404);
    });
});

// ─── POST /api/v1/security/keys ───

describe('POST /api/v1/security/keys', function () {
    test('creates a new private key', function () {
        $privateKeyContent = PrivateKey::factory()->make()->private_key;

        $response = $this->withHeaders(securityHeaders($this->bearerToken))
            ->postJson('/api/v1/security/keys', [
                'name' => 'Test Key',
                'description' => 'A test key',
                'private_key' => $privateKeyContent,
            ]);

        $response->assertStatus(201);
        expect($response->json('uuid'))->not->toBeNull();

        $this->assertDatabaseHas('private_keys', [
            'name' => 'Test Key',
            'team_id' => $this->team->id,
        ]);
    });

    test('creates key with auto-generated name when name is not provided', function () {
        $privateKeyContent = PrivateKey::factory()->make()->private_key;

        $response = $this->withHeaders(securityHeaders($this->bearerToken))
            ->postJson('/api/v1/security/keys', [
                'private_key' => $privateKeyContent,
            ]);

        $response->assertStatus(201);
    });

    test('returns 422 when private_key is missing', function () {
        $this->withHeaders(securityHeaders($this->bearerToken))
            ->postJson('/api/v1/security/keys', [
                'name' => 'Test Key',
            ])
            ->assertStatus(422);
    });

    test('returns 422 for invalid private key', function () {
        $this->withHeaders(securityHeaders($this->bearerToken))
            ->postJson('/api/v1/security/keys', [
                'private_key' => 'not-a-valid-key',
            ])
            ->assertStatus(422)
            ->assertJson(['message' => 'Invalid private key.']);
    });

    test('returns 422 for duplicate private key fingerprint', function () {
        $key = PrivateKey::factory()->create(['team_id' => $this->team->id]);

        $this->withHeaders(securityHeaders($this->bearerToken))
            ->postJson('/api/v1/security/keys', [
                'private_key' => $key->private_key,
            ])
            ->assertStatus(422)
            ->assertJson(['message' => 'Private key already exists.']);
    });

    test('accepts base64-encoded private key', function () {
        $privateKeyContent = PrivateKey::factory()->make()->private_key;
        $base64Key = base64_encode($privateKeyContent);

        $response = $this->withHeaders(securityHeaders($this->bearerToken))
            ->postJson('/api/v1/security/keys', [
                'name' => 'Base64 Key',
                'private_key' => $base64Key,
            ]);

        $response->assertStatus(201);
    });
});

// ─── PATCH /api/v1/security/keys/{uuid} ───

describe('PATCH /api/v1/security/keys/{uuid}', function () {
    test('updates a private key', function () {
        $key = PrivateKey::factory()->create(['team_id' => $this->team->id]);
        $newPrivateKey = PrivateKey::factory()->make()->private_key;

        $response = $this->withHeaders(securityHeaders($this->bearerToken))
            ->patchJson("/api/v1/security/keys/{$key->uuid}", [
                'name' => 'Updated Key',
                'private_key' => $newPrivateKey,
            ]);

        $response->assertStatus(201);
        expect($response->json('uuid'))->toBe($key->uuid);
    });

    test('returns 404 for non-existent key', function () {
        $newPrivateKey = PrivateKey::factory()->make()->private_key;

        $this->withHeaders(securityHeaders($this->bearerToken))
            ->patchJson('/api/v1/security/keys/non-existent-uuid', [
                'private_key' => $newPrivateKey,
            ])
            ->assertStatus(404);
    });

    test('returns 422 for extra fields', function () {
        $key = PrivateKey::factory()->create(['team_id' => $this->team->id]);
        $newPrivateKey = PrivateKey::factory()->make()->private_key;

        $this->withHeaders(securityHeaders($this->bearerToken))
            ->patchJson("/api/v1/security/keys/{$key->uuid}", [
                'private_key' => $newPrivateKey,
                'unknown_field' => 'value',
            ])
            ->assertStatus(422);
    });
});

// ─── DELETE /api/v1/security/keys/{uuid} ───

describe('DELETE /api/v1/security/keys/{uuid}', function () {
    test('deletes a private key', function () {
        $key = PrivateKey::factory()->create(['team_id' => $this->team->id]);

        $this->withHeaders(securityHeaders($this->bearerToken))
            ->deleteJson("/api/v1/security/keys/{$key->uuid}")
            ->assertStatus(200)
            ->assertJson(['message' => 'Private Key deleted.']);

        $this->assertDatabaseMissing('private_keys', ['id' => $key->id]);
    });

    test('returns 404 for non-existent key', function () {
        $this->withHeaders(securityHeaders($this->bearerToken))
            ->deleteJson('/api/v1/security/keys/non-existent-uuid')
            ->assertStatus(404);
    });

    test('returns 422 when key is in use by a server', function () {
        $key = PrivateKey::factory()->create(['team_id' => $this->team->id]);

        // Create a server using this key (without triggering SSH boot events)
        // Must include uuid explicitly since withoutEvents() skips boot events that generate it
        Server::withoutEvents(function () use ($key) {
            return Server::factory()->create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'team_id' => $this->team->id,
                'private_key_id' => $key->id,
            ]);
        });

        $this->withHeaders(securityHeaders($this->bearerToken))
            ->deleteJson("/api/v1/security/keys/{$key->uuid}")
            ->assertStatus(422)
            ->assertJson(['message' => 'Private Key is in use and cannot be deleted.']);
    });

    test('cannot delete key from another team', function () {
        $otherTeam = Team::factory()->create();
        $key = PrivateKey::factory()->create(['team_id' => $otherTeam->id]);

        $this->withHeaders(securityHeaders($this->bearerToken))
            ->deleteJson("/api/v1/security/keys/{$key->uuid}")
            ->assertStatus(404);
    });
});
