<?php

use App\Models\CloudProviderToken;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Visus\Cuid2\Cuid2;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();
    $this->team->members()->attach($this->user->id, ['role' => 'owner']);

    session(['currentTeam' => $this->team]);
    $this->token = $this->user->createToken('test-token', ['*']);
    $this->bearerToken = $this->token->plainTextToken;

    if (! DB::table('instance_settings')->where('id', 0)->exists()) {
        DB::table('instance_settings')->insert([
            'id' => 0,
            'is_api_enabled' => true,
            'is_registration_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // Create a cloud provider token for this team
    $this->cloudToken = CloudProviderToken::create([
        'uuid' => (string) new Cuid2,
        'team_id' => $this->team->id,
        'provider' => 'hetzner',
        'token' => 'test-hetzner-token-xxx',
        'name' => 'My Hetzner Token',
    ]);
});

describe('Authentication', function () {
    test('rejects request without token', function () {
        $response = $this->getJson('/api/v1/cloud-tokens');
        $response->assertStatus(401);
    });
});

describe('GET /api/v1/cloud-tokens', function () {
    test('returns list of cloud tokens for team', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/cloud-tokens');

        $response->assertStatus(200);

        $data = $response->json();
        expect($data)->toBeArray();
        expect(count($data))->toBeGreaterThanOrEqual(1);
    });

    test('does not return tokens from other teams', function () {
        $otherTeam = Team::factory()->create();
        CloudProviderToken::create([
            'uuid' => (string) new Cuid2,
            'team_id' => $otherTeam->id,
            'provider' => 'hetzner',
            'token' => 'other-team-token-xxx',
            'name' => 'Other Team Token',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/cloud-tokens');

        $response->assertStatus(200);

        $data = $response->json();
        $uuids = array_column($data, 'uuid');
        expect($uuids)->toContain($this->cloudToken->uuid);
        // Should not contain other team's token uuid
        expect(count($data))->toBe(1);
    });

    test('does not expose sensitive token field', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/cloud-tokens');

        $response->assertStatus(200);
        $data = $response->json();

        foreach ($data as $item) {
            expect($item)->not->toHaveKey('token');
            expect($item)->not->toHaveKey('id');
        }
    });
});

describe('GET /api/v1/cloud-tokens/{uuid}', function () {
    test('returns token by uuid', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/cloud-tokens/'.$this->cloudToken->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('uuid', $this->cloudToken->uuid)
            ->assertJsonPath('name', 'My Hetzner Token')
            ->assertJsonPath('provider', 'hetzner');
    });

    test('returns 404 for non-existent token', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/cloud-tokens/non-existent-uuid');

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Cloud provider token not found.');
    });

    test('returns 404 for token belonging to another team', function () {
        $otherTeam = Team::factory()->create();
        $otherToken = CloudProviderToken::create([
            'uuid' => (string) new Cuid2,
            'team_id' => $otherTeam->id,
            'provider' => 'hetzner',
            'token' => 'other-team-token-xxx',
            'name' => 'Other Token',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/cloud-tokens/'.$otherToken->uuid);

        $response->assertStatus(404);
    });
});

describe('POST /api/v1/cloud-tokens', function () {
    test('creates a token when provider validates successfully', function () {
        Http::fake([
            'https://api.hetzner.cloud/*' => Http::response(['servers' => []], 200),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson('/api/v1/cloud-tokens', [
            'provider' => 'hetzner',
            'token' => 'valid-hetzner-api-token',
            'name' => 'New Token',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['uuid']);
    });

    test('returns 400 when provider token is invalid', function () {
        Http::fake([
            'https://api.hetzner.cloud/*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson('/api/v1/cloud-tokens', [
            'provider' => 'hetzner',
            'token' => 'invalid-token',
            'name' => 'Bad Token',
        ]);

        $response->assertStatus(400);
    });

    test('returns 422 when required fields are missing', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson('/api/v1/cloud-tokens', []);

        $response->assertStatus(422);
    });

    test('returns 422 for unsupported provider', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson('/api/v1/cloud-tokens', [
            'provider' => 'aws',
            'token' => 'some-token',
            'name' => 'AWS Token',
        ]);

        $response->assertStatus(422);
    });

    test('returns 422 for extra unknown fields', function () {
        Http::fake([
            'https://api.hetzner.cloud/*' => Http::response([], 200),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson('/api/v1/cloud-tokens', [
            'provider' => 'hetzner',
            'token' => 'valid-token',
            'name' => 'Token',
            'unknown_field' => 'not-allowed',
        ]);

        $response->assertStatus(422);
    });
});

describe('PATCH /api/v1/cloud-tokens/{uuid}', function () {
    test('updates token name', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->patchJson('/api/v1/cloud-tokens/'.$this->cloudToken->uuid, [
            'name' => 'Updated Token Name',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('uuid', $this->cloudToken->uuid);

        $this->assertDatabaseHas('cloud_provider_tokens', [
            'id' => $this->cloudToken->id,
            'name' => 'Updated Token Name',
        ]);
    });

    test('returns 404 for non-existent token', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->patchJson('/api/v1/cloud-tokens/non-existent-uuid', [
            'name' => 'Updated',
        ]);

        $response->assertStatus(404);
    });

    test('returns 422 when name is missing', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->patchJson('/api/v1/cloud-tokens/'.$this->cloudToken->uuid, []);

        $response->assertStatus(422);
    });
});

describe('DELETE /api/v1/cloud-tokens/{uuid}', function () {
    test('deletes the token', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->deleteJson('/api/v1/cloud-tokens/'.$this->cloudToken->uuid);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('cloud_provider_tokens', [
            'id' => $this->cloudToken->id,
        ]);
    });

    test('returns 404 for non-existent token', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->deleteJson('/api/v1/cloud-tokens/non-existent-uuid');

        $response->assertStatus(404);
    });
});
