<?php

/**
 * E2E Environment Variable Management Tests
 *
 * Tests the full environment variable lifecycle for applications:
 * - Create env vars (single and bulk)
 * - List env vars
 * - Update env vars
 * - Delete env vars
 * - Duplicate key handling (409 conflict)
 * - Key format validation
 * - API token scope enforcement (read:sensitive)
 * - Cross-team isolation
 */

use App\Models\Application;
use App\Models\Environment;
use App\Models\EnvironmentVariable;
use App\Models\InstanceSettings;
use App\Models\PrivateKey;
use App\Models\Project;
use App\Models\Server;
use App\Models\ServerSetting;
use App\Models\StandaloneDocker;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Visus\Cuid2\Cuid2;

uses(DatabaseTransactions::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function envApiHeaders(string $bearerToken): array
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

    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();
    $this->team->members()->attach($this->user->id, ['role' => 'owner']);
    session(['currentTeam' => $this->team]);

    $this->token = $this->user->createToken('test-token', ['*']);
    $this->bearerToken = $this->token->plainTextToken;

    if (! InstanceSettings::first()) {
        InstanceSettings::create(['id' => 0, 'is_api_enabled' => true]);
    }

    $this->privateKey = PrivateKey::factory()->create(['team_id' => $this->team->id]);

    $this->server = Server::withoutEvents(fn () => Server::factory()->create([
        'uuid' => (string) new Cuid2,
        'team_id' => $this->team->id,
        'private_key_id' => $this->privateKey->id,
    ]));
    ServerSetting::firstOrCreate(['server_id' => $this->server->id]);

    $this->destination = StandaloneDocker::withoutEvents(fn () => StandaloneDocker::create([
        'uuid' => (string) new Cuid2,
        'name' => 'default',
        'network' => 'saturn',
        'server_id' => $this->server->id,
    ]));

    $this->project = Project::factory()->create(['team_id' => $this->team->id]);
    $this->environment = Environment::factory()->create(['project_id' => $this->project->id]);

    $this->application = Application::factory()->create([
        'name' => 'Env Var Test App',
        'environment_id' => $this->environment->id,
        'destination_id' => $this->destination->id,
        'destination_type' => StandaloneDocker::class,
        'ports_exposes' => '3000',
    ]);
});

// ─── POST /applications/{uuid}/envs — Create environment variable ───────────

describe('POST /api/v1/applications/{uuid}/envs — Create env var', function () {
    test('creates env var with key and value', function () {
        $response = $this->withHeaders(envApiHeaders($this->bearerToken))
            ->postJson("/api/v1/applications/{$this->application->uuid}/envs", [
                'key' => 'DATABASE_URL',
                'value' => 'postgres://user:pass@host:5432/db',
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['uuid']);

        // Verify in database
        $this->assertDatabaseHas('environment_variables', [
            'key' => 'DATABASE_URL',
            'resourceable_type' => Application::class,
            'resourceable_id' => $this->application->id,
        ]);
    });

    test('creates env var with all optional flags', function () {
        $response = $this->withHeaders(envApiHeaders($this->bearerToken))
            ->postJson("/api/v1/applications/{$this->application->uuid}/envs", [
                'key' => 'SECRET_KEY',
                'value' => 'my-secret-value',
                'is_literal' => true,
                'is_multiline' => false,
                'is_shown_once' => true,
                'is_runtime' => true,
                'is_buildtime' => false,
            ]);

        $response->assertStatus(201);

        $envUuid = $response->json('uuid');
        $env = EnvironmentVariable::where('uuid', $envUuid)->first();
        expect($env->key)->toBe('SECRET_KEY');
        expect($env->is_literal)->toBeTrue();
        expect($env->is_shown_once)->toBeTrue();
        expect($env->is_buildtime)->toBeFalse();
    });

    test('creates preview env var', function () {
        $response = $this->withHeaders(envApiHeaders($this->bearerToken))
            ->postJson("/api/v1/applications/{$this->application->uuid}/envs", [
                'key' => 'PREVIEW_VAR',
                'value' => 'preview-value',
                'is_preview' => true,
            ]);

        $response->assertStatus(201);

        $envUuid = $response->json('uuid');
        $env = EnvironmentVariable::where('uuid', $envUuid)->first();
        expect($env->is_preview)->toBeTrue();
    });

    test('creates env var with null value', function () {
        $response = $this->withHeaders(envApiHeaders($this->bearerToken))
            ->postJson("/api/v1/applications/{$this->application->uuid}/envs", [
                'key' => 'EMPTY_VAR',
                'value' => null,
            ]);

        $response->assertStatus(201);
    });

    test('returns 409 when key already exists', function () {
        // Create first env var
        $this->withHeaders(envApiHeaders($this->bearerToken))
            ->postJson("/api/v1/applications/{$this->application->uuid}/envs", [
                'key' => 'DUPLICATE_KEY',
                'value' => 'first-value',
            ])
            ->assertStatus(201);

        // Try to create duplicate
        $response = $this->withHeaders(envApiHeaders($this->bearerToken))
            ->postJson("/api/v1/applications/{$this->application->uuid}/envs", [
                'key' => 'DUPLICATE_KEY',
                'value' => 'second-value',
            ]);

        $response->assertStatus(409);
        $response->assertJsonFragment(['message' => 'Environment variable already exists. Use PATCH request to update it.']);
    });

    test('validates key format — rejects invalid characters', function () {
        $response = $this->withHeaders(envApiHeaders($this->bearerToken))
            ->postJson("/api/v1/applications/{$this->application->uuid}/envs", [
                'key' => 'invalid-key-with-dashes',
                'value' => 'value',
            ]);

        $response->assertStatus(422);
    });

    test('validates key format — rejects key starting with number', function () {
        $response = $this->withHeaders(envApiHeaders($this->bearerToken))
            ->postJson("/api/v1/applications/{$this->application->uuid}/envs", [
                'key' => '1INVALID',
                'value' => 'value',
            ]);

        $response->assertStatus(422);
    });

    test('accepts key starting with underscore', function () {
        $response = $this->withHeaders(envApiHeaders($this->bearerToken))
            ->postJson("/api/v1/applications/{$this->application->uuid}/envs", [
                'key' => '_PRIVATE_VAR',
                'value' => 'private-value',
            ]);

        $response->assertStatus(201);
    });

    test('validates key max length', function () {
        $response = $this->withHeaders(envApiHeaders($this->bearerToken))
            ->postJson("/api/v1/applications/{$this->application->uuid}/envs", [
                'key' => str_repeat('A', 256),
                'value' => 'value',
            ]);

        $response->assertStatus(422);
    });

    test('requires key field', function () {
        $response = $this->withHeaders(envApiHeaders($this->bearerToken))
            ->postJson("/api/v1/applications/{$this->application->uuid}/envs", [
                'value' => 'value-without-key',
            ]);

        $response->assertStatus(422);
    });

    test('rejects extra fields', function () {
        $response = $this->withHeaders(envApiHeaders($this->bearerToken))
            ->postJson("/api/v1/applications/{$this->application->uuid}/envs", [
                'key' => 'VALID_KEY',
                'value' => 'valid',
                'unauthorized_field' => 'bad',
            ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['unauthorized_field' => ['This field is not allowed.']]);
    });

    test('returns 404 for non-existent application', function () {
        $response = $this->withHeaders(envApiHeaders($this->bearerToken))
            ->postJson('/api/v1/applications/non-existent-uuid/envs', [
                'key' => 'TEST',
                'value' => 'test',
            ]);

        $response->assertStatus(404);
    });
});

// ─── GET /applications/{uuid}/envs — List environment variables ──────────────

describe('GET /api/v1/applications/{uuid}/envs — List env vars', function () {
    test('returns empty array when no env vars exist', function () {
        $response = $this->withHeaders(envApiHeaders($this->bearerToken))
            ->getJson("/api/v1/applications/{$this->application->uuid}/envs");

        $response->assertStatus(200);
        $response->assertJson([]);
    });

    test('lists all env vars for application', function () {
        // Create two env vars
        $this->application->environment_variables()->create([
            'key' => 'VAR_ONE',
            'value' => 'value-one',
            'resourceable_type' => Application::class,
            'resourceable_id' => $this->application->id,
        ]);
        $this->application->environment_variables()->create([
            'key' => 'VAR_TWO',
            'value' => 'value-two',
            'resourceable_type' => Application::class,
            'resourceable_id' => $this->application->id,
        ]);

        $response = $this->withHeaders(envApiHeaders($this->bearerToken))
            ->getJson("/api/v1/applications/{$this->application->uuid}/envs");

        $response->assertStatus(200);
        $response->assertJsonFragment(['key' => 'VAR_ONE']);
        $response->assertJsonFragment(['key' => 'VAR_TWO']);
    });

    test('hides value field without read:sensitive permission', function () {
        $this->application->environment_variables()->create([
            'key' => 'SECRET',
            'value' => 'super-secret',
            'resourceable_type' => Application::class,
            'resourceable_id' => $this->application->id,
        ]);

        // Create read-only token (no read:sensitive)
        $readToken = $this->user->createToken('read-only', ['read']);

        $response = $this->withHeaders(envApiHeaders($readToken->plainTextToken))
            ->getJson("/api/v1/applications/{$this->application->uuid}/envs");

        $response->assertStatus(200);

        // Value should be hidden
        $envs = $response->json();
        $secret = collect($envs)->firstWhere('key', 'SECRET');
        expect($secret)->not->toHaveKey('value');
    });

    test('shows value field with read:sensitive permission', function () {
        $this->application->environment_variables()->create([
            'key' => 'VISIBLE_SECRET',
            'value' => 'visible-value',
            'resourceable_type' => Application::class,
            'resourceable_id' => $this->application->id,
        ]);

        // Create token with sensitive read access
        $sensitiveToken = $this->user->createToken('sensitive-token', ['read', 'read:sensitive']);

        $response = $this->withHeaders(envApiHeaders($sensitiveToken->plainTextToken))
            ->getJson("/api/v1/applications/{$this->application->uuid}/envs");

        $response->assertStatus(200);

        $envs = $response->json();
        $secret = collect($envs)->firstWhere('key', 'VISIBLE_SECRET');
        expect($secret)->toHaveKey('value');
        expect($secret['value'])->toBe('visible-value');
    });

    test('returns 404 for non-existent application', function () {
        $response = $this->withHeaders(envApiHeaders($this->bearerToken))
            ->getJson('/api/v1/applications/non-existent-uuid/envs');

        $response->assertStatus(404);
    });

    test('cannot list env vars from another team app', function () {
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnv = Environment::factory()->create(['project_id' => $otherProject->id]);
        $otherApp = Application::factory()->create([
            'environment_id' => $otherEnv->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '3000',
        ]);

        $response = $this->withHeaders(envApiHeaders($this->bearerToken))
            ->getJson("/api/v1/applications/{$otherApp->uuid}/envs");

        $response->assertStatus(404);
    });
});

// ─── PATCH /applications/{uuid}/envs — Update environment variable ───────────

describe('PATCH /api/v1/applications/{uuid}/envs — Update env var', function () {
    test('updates existing env var value', function () {
        $this->application->environment_variables()->create([
            'key' => 'UPDATE_ME',
            'value' => 'old-value',
            'resourceable_type' => Application::class,
            'resourceable_id' => $this->application->id,
        ]);

        $response = $this->withHeaders(envApiHeaders($this->bearerToken))
            ->patchJson("/api/v1/applications/{$this->application->uuid}/envs", [
                'key' => 'UPDATE_ME',
                'value' => 'new-value',
            ]);

        $response->assertStatus(201);

        $env = $this->application->environment_variables()->where('key', 'UPDATE_ME')->first();
        expect($env->value)->toBe('new-value');
    });

    test('updates env var flags', function () {
        $this->application->environment_variables()->create([
            'key' => 'FLAG_VAR',
            'value' => 'value',
            'is_literal' => false,
            'is_multiline' => false,
            'resourceable_type' => Application::class,
            'resourceable_id' => $this->application->id,
        ]);

        $response = $this->withHeaders(envApiHeaders($this->bearerToken))
            ->patchJson("/api/v1/applications/{$this->application->uuid}/envs", [
                'key' => 'FLAG_VAR',
                'value' => 'value',
                'is_literal' => true,
                'is_multiline' => true,
            ]);

        $response->assertStatus(201);

        $env = $this->application->environment_variables()->where('key', 'FLAG_VAR')->first();
        expect($env->is_literal)->toBeTrue();
        expect($env->is_multiline)->toBeTrue();
    });

    test('returns 404 for non-existent key', function () {
        $response = $this->withHeaders(envApiHeaders($this->bearerToken))
            ->patchJson("/api/v1/applications/{$this->application->uuid}/envs", [
                'key' => 'NON_EXISTENT_KEY',
                'value' => 'value',
            ]);

        $response->assertStatus(404);
    });
});

// ─── PATCH /applications/{uuid}/envs/bulk — Bulk create/update ───────────────

describe('PATCH /api/v1/applications/{uuid}/envs/bulk — Bulk operations', function () {
    test('creates multiple env vars in bulk', function () {
        $response = $this->withHeaders(envApiHeaders($this->bearerToken))
            ->patchJson("/api/v1/applications/{$this->application->uuid}/envs/bulk", [
                'data' => [
                    ['key' => 'BULK_ONE', 'value' => 'value-one'],
                    ['key' => 'BULK_TWO', 'value' => 'value-two'],
                    ['key' => 'BULK_THREE', 'value' => 'value-three'],
                ],
            ]);

        $response->assertStatus(201);

        // Verify all three were created
        $envs = $this->application->environment_variables()->whereIn('key', ['BULK_ONE', 'BULK_TWO', 'BULK_THREE'])->get();
        expect($envs)->toHaveCount(3);
    });

    test('updates existing and creates new vars in bulk', function () {
        // Create existing var
        $this->application->environment_variables()->create([
            'key' => 'EXISTING',
            'value' => 'old-value',
            'resourceable_type' => Application::class,
            'resourceable_id' => $this->application->id,
        ]);

        $response = $this->withHeaders(envApiHeaders($this->bearerToken))
            ->patchJson("/api/v1/applications/{$this->application->uuid}/envs/bulk", [
                'data' => [
                    ['key' => 'EXISTING', 'value' => 'updated-value'],
                    ['key' => 'NEW_VAR', 'value' => 'new-value'],
                ],
            ]);

        $response->assertStatus(201);

        // Verify update
        $existing = $this->application->environment_variables()->where('key', 'EXISTING')->first();
        expect($existing->value)->toBe('updated-value');

        // Verify creation
        $newVar = $this->application->environment_variables()->where('key', 'NEW_VAR')->first();
        expect($newVar)->not->toBeNull();
        expect($newVar->value)->toBe('new-value');
    });

    test('returns 400 when data array is missing', function () {
        $response = $this->withHeaders(envApiHeaders($this->bearerToken))
            ->patchJson("/api/v1/applications/{$this->application->uuid}/envs/bulk", []);

        $response->assertStatus(400);
    });

    test('validates keys in bulk data', function () {
        $response = $this->withHeaders(envApiHeaders($this->bearerToken))
            ->patchJson("/api/v1/applications/{$this->application->uuid}/envs/bulk", [
                'data' => [
                    ['key' => 'VALID_KEY', 'value' => 'ok'],
                    ['key' => 'invalid-key', 'value' => 'not ok'],
                ],
            ]);

        $response->assertStatus(422);
    });
});

// ─── DELETE /applications/{uuid}/envs/{env_uuid} — Delete env var ────────────

describe('DELETE /api/v1/applications/{uuid}/envs/{env_uuid} — Delete env var', function () {
    test('deletes existing env var', function () {
        $env = $this->application->environment_variables()->create([
            'key' => 'DELETE_ME',
            'value' => 'to-be-deleted',
            'resourceable_type' => Application::class,
            'resourceable_id' => $this->application->id,
        ]);

        $response = $this->withHeaders(envApiHeaders($this->bearerToken))
            ->deleteJson("/api/v1/applications/{$this->application->uuid}/envs/{$env->uuid}");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Environment variable deleted.']);

        // Verify deletion
        expect(EnvironmentVariable::where('uuid', $env->uuid)->first())->toBeNull();
    });

    test('returns 404 for non-existent env var UUID', function () {
        $response = $this->withHeaders(envApiHeaders($this->bearerToken))
            ->deleteJson("/api/v1/applications/{$this->application->uuid}/envs/non-existent-uuid");

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Environment variable not found.']);
    });

    test('returns 404 for non-existent application', function () {
        $response = $this->withHeaders(envApiHeaders($this->bearerToken))
            ->deleteJson('/api/v1/applications/non-existent-app/envs/some-uuid');

        $response->assertStatus(404);
    });

    test('cannot delete env var from another team application', function () {
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnv = Environment::factory()->create(['project_id' => $otherProject->id]);
        $otherApp = Application::factory()->create([
            'environment_id' => $otherEnv->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '3000',
        ]);
        $envVar = $otherApp->environment_variables()->create([
            'key' => 'OTHER_TEAM_VAR',
            'value' => 'other-value',
            'resourceable_type' => Application::class,
            'resourceable_id' => $otherApp->id,
        ]);

        $response = $this->withHeaders(envApiHeaders($this->bearerToken))
            ->deleteJson("/api/v1/applications/{$otherApp->uuid}/envs/{$envVar->uuid}");

        $response->assertStatus(404);

        // Verify env var still exists
        expect(EnvironmentVariable::where('uuid', $envVar->uuid)->first())->not->toBeNull();
    });
});

// ─── API Token Scope Enforcement ─────────────────────────────────────────────

describe('API token scope enforcement for env vars', function () {
    test('read-only token can list env vars', function () {
        $readToken = $this->user->createToken('read-only', ['read']);

        $response = $this->withHeaders(envApiHeaders($readToken->plainTextToken))
            ->getJson("/api/v1/applications/{$this->application->uuid}/envs");

        $response->assertStatus(200);
    });

    test('read-only token cannot create env vars', function () {
        $readToken = $this->user->createToken('read-only', ['read']);

        $response = $this->withHeaders(envApiHeaders($readToken->plainTextToken))
            ->postJson("/api/v1/applications/{$this->application->uuid}/envs", [
                'key' => 'FORBIDDEN',
                'value' => 'nope',
            ]);

        $response->assertStatus(403);
    });

    test('read-only token cannot delete env vars', function () {
        $readToken = $this->user->createToken('read-only', ['read']);
        $env = $this->application->environment_variables()->create([
            'key' => 'PROTECT_ME',
            'value' => 'protected',
            'resourceable_type' => Application::class,
            'resourceable_id' => $this->application->id,
        ]);

        $response = $this->withHeaders(envApiHeaders($readToken->plainTextToken))
            ->deleteJson("/api/v1/applications/{$this->application->uuid}/envs/{$env->uuid}");

        $response->assertStatus(403);
    });
});

// ─── Full Lifecycle E2E ──────────────────────────────────────────────────────

describe('Full env var lifecycle — create → list → update → delete', function () {
    test('complete lifecycle: create → list → update → verify → delete → verify', function () {
        // 1. Create env var
        $createResponse = $this->withHeaders(envApiHeaders($this->bearerToken))
            ->postJson("/api/v1/applications/{$this->application->uuid}/envs", [
                'key' => 'LIFECYCLE_VAR',
                'value' => 'initial-value',
            ]);

        $createResponse->assertStatus(201);
        $envUuid = $createResponse->json('uuid');

        // 2. List and verify it exists
        $listResponse = $this->withHeaders(envApiHeaders($this->bearerToken))
            ->getJson("/api/v1/applications/{$this->application->uuid}/envs");

        $listResponse->assertStatus(200);
        $listResponse->assertJsonFragment(['key' => 'LIFECYCLE_VAR']);

        // 3. Update the value
        $updateResponse = $this->withHeaders(envApiHeaders($this->bearerToken))
            ->patchJson("/api/v1/applications/{$this->application->uuid}/envs", [
                'key' => 'LIFECYCLE_VAR',
                'value' => 'updated-value',
                'is_literal' => true,
            ]);

        $updateResponse->assertStatus(201);

        // 4. Verify update in database
        $env = EnvironmentVariable::where('uuid', $envUuid)->first();
        expect($env->value)->toBe('updated-value');
        expect($env->is_literal)->toBeTrue();

        // 5. Delete the env var by UUID
        $deleteResponse = $this->withHeaders(envApiHeaders($this->bearerToken))
            ->deleteJson("/api/v1/applications/{$this->application->uuid}/envs/{$envUuid}");

        $deleteResponse->assertStatus(200);

        // 6. Verify the specific record was deleted
        expect(EnvironmentVariable::where('uuid', $envUuid)->first())->toBeNull();
    });

    test('bulk create → individual update → bulk verify', function () {
        // 1. Bulk create
        $bulkResponse = $this->withHeaders(envApiHeaders($this->bearerToken))
            ->patchJson("/api/v1/applications/{$this->application->uuid}/envs/bulk", [
                'data' => [
                    ['key' => 'APP_NAME', 'value' => 'saturn'],
                    ['key' => 'APP_ENV', 'value' => 'production'],
                    ['key' => 'APP_DEBUG', 'value' => 'false'],
                ],
            ]);

        $bulkResponse->assertStatus(201);

        // 2. Update one via PATCH
        $this->withHeaders(envApiHeaders($this->bearerToken))
            ->patchJson("/api/v1/applications/{$this->application->uuid}/envs", [
                'key' => 'APP_ENV',
                'value' => 'staging',
            ])
            ->assertStatus(201);

        // 3. List and verify all exist with correct values
        $listResponse = $this->withHeaders(envApiHeaders($this->bearerToken))
            ->getJson("/api/v1/applications/{$this->application->uuid}/envs");

        $listResponse->assertStatus(200);
        $listResponse->assertJsonFragment(['key' => 'APP_NAME']);
        $listResponse->assertJsonFragment(['key' => 'APP_ENV']);
        $listResponse->assertJsonFragment(['key' => 'APP_DEBUG']);

        // Verify updated value in DB
        $appEnv = $this->application->environment_variables()->where('key', 'APP_ENV')->first();
        expect($appEnv->value)->toBe('staging');
    });
});
