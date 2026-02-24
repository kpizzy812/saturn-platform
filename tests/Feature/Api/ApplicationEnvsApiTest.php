<?php

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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Visus\Cuid2\Cuid2;

uses(DatabaseTransactions::class);

beforeEach(function () {
    Queue::fake();

    // Flush both default and Redis cache stores to prevent stale permission/team data.
    // The PermissionService caches authorization results; stale entries cause 403 errors.
    Cache::flush();
    try {
        Cache::store('redis')->flush();
    } catch (\Throwable $e) {
        // Redis may not be available in some test environments
    }

    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();
    $this->team->members()->attach($this->user->id, ['role' => 'owner']);

    // Set session-based team context (used by currentTeam() helper).
    // IMPORTANT: session must be set before createToken so the token is
    // associated with the correct team context.
    session(['currentTeam' => $this->team]);
    $this->token = $this->user->createToken('test-token', ['*']);
    $this->bearerToken = $this->token->plainTextToken;

    // InstanceSettings id=0 must exist for the API to function
    if (! InstanceSettings::first()) {
        InstanceSettings::create([
            'id' => 0,
            'is_api_enabled' => true,
        ]);
    }

    // Create project > environment chain
    $this->project = Project::factory()->create(['team_id' => $this->team->id]);
    $this->environment = Environment::factory()->create(['project_id' => $this->project->id]);

    // Create server + destination without triggering SSH boot events.
    // withoutEvents() also skips BaseModel::boot() which generates uuid,
    // so we must set uuid explicitly.
    $this->privateKey = PrivateKey::factory()->create(['team_id' => $this->team->id]);

    $this->server = Server::withoutEvents(function () {
        return Server::factory()->create([
            'uuid' => (string) new Cuid2,
            'team_id' => $this->team->id,
            'private_key_id' => $this->privateKey->id,
        ]);
    });

    // withoutEvents() skips Server::created that creates ServerSetting,
    // so create it manually. Required by server methods like isBuildServer().
    ServerSetting::firstOrCreate(['server_id' => $this->server->id]);

    // Create StandaloneDocker destination without triggering docker network creation.
    $this->destination = StandaloneDocker::withoutEvents(function () {
        return StandaloneDocker::create([
            'uuid' => (string) new Cuid2,
            'name' => 'default',
            'network' => 'saturn',
            'server_id' => $this->server->id,
        ]);
    });

    // Create a test application.
    // IMPORTANT: ports_exposes is NOT NULL in the applications table.
    $this->application = Application::factory()->create([
        'uuid' => (string) new Cuid2,
        'name' => 'test-app',
        'environment_id' => $this->environment->id,
        'destination_id' => $this->destination->id,
        'destination_type' => StandaloneDocker::class,
        'ports_exposes' => '3000',
    ]);
});

describe('Authentication', function () {
    test('rejects list envs request without authentication', function () {
        $response = $this->getJson("/api/v1/applications/{$this->application->uuid}/envs");
        $response->assertStatus(401);
    });

    test('rejects request with invalid token', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token-here',
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/applications/{$this->application->uuid}/envs");

        $response->assertStatus(401);
    });
});

describe('GET /api/v1/applications/{uuid}/envs - List environment variables', function () {
    test('returns empty array when application has no envs', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/applications/{$this->application->uuid}/envs");

        $response->assertStatus(200);
        $response->assertJson([]);
    });

    test('returns list of environment variables for the application', function () {
        EnvironmentVariable::create([
            'uuid' => (string) new Cuid2,
            'key' => 'APP_ENV',
            'value' => 'production',
            'is_preview' => false,
            'is_literal' => false,
            'is_multiline' => false,
            'resourceable_type' => Application::class,
            'resourceable_id' => $this->application->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/applications/{$this->application->uuid}/envs");

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['key' => 'APP_ENV']);
    });

    test('returns 404 for non-existent application UUID', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/applications/non-existent-uuid/envs');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Application not found']);
    });

    test('hides sensitive value field without read:sensitive ability', function () {
        EnvironmentVariable::create([
            'uuid' => (string) new Cuid2,
            'key' => 'SECRET_KEY',
            'value' => 'super-secret-value',
            'is_preview' => false,
            'is_literal' => false,
            'is_multiline' => false,
            'resourceable_type' => Application::class,
            'resourceable_id' => $this->application->id,
        ]);

        // Token with only 'read' ability (no 'read:sensitive')
        $limitedToken = $this->user->createToken('read-only-token', ['read']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$limitedToken->plainTextToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/applications/{$this->application->uuid}/envs");

        $response->assertStatus(200);
        $json = $response->json();
        $firstEnv = $json[0];

        expect($firstEnv)->not->toHaveKey('value');
        expect($firstEnv)->not->toHaveKey('real_value');
    });
});

describe('POST /api/v1/applications/{uuid}/envs - Create environment variable', function () {
    test('creates a new environment variable successfully', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson("/api/v1/applications/{$this->application->uuid}/envs", [
            'key' => 'NEW_VAR',
            'value' => 'new_value',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['uuid']);
    });

    test('returns 409 when environment variable with same key already exists', function () {
        EnvironmentVariable::create([
            'uuid' => (string) new Cuid2,
            'key' => 'EXISTING_VAR',
            'value' => 'existing_value',
            'is_preview' => false,
            'is_literal' => false,
            'is_multiline' => false,
            'resourceable_type' => Application::class,
            'resourceable_id' => $this->application->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson("/api/v1/applications/{$this->application->uuid}/envs", [
            'key' => 'EXISTING_VAR',
            'value' => 'new_value',
        ]);

        $response->assertStatus(409);
        $response->assertJson(['message' => 'Environment variable already exists. Use PATCH request to update it.']);
    });

    test('returns 422 when key is missing', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson("/api/v1/applications/{$this->application->uuid}/envs", [
            'value' => 'some_value',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Validation failed.']);
    });

    test('creates a preview environment variable with is_preview=true', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson("/api/v1/applications/{$this->application->uuid}/envs", [
            'key' => 'PREVIEW_VAR',
            'value' => 'preview_value',
            'is_preview' => true,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['uuid']);

        // Verify it was stored as a preview env
        $uuid = $response->json('uuid');
        $env = EnvironmentVariable::where('uuid', $uuid)->first();
        expect($env)->not->toBeNull();
        expect($env->is_preview)->toBeTrue();
    });

    test('returns 404 for non-existent application UUID', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/applications/non-existent-uuid/envs', [
            'key' => 'MY_VAR',
            'value' => 'my_value',
        ]);

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Application not found']);
    });
});

describe('PATCH /api/v1/applications/{uuid}/envs - Update environment variable', function () {
    test('updates an existing environment variable', function () {
        EnvironmentVariable::create([
            'uuid' => (string) new Cuid2,
            'key' => 'UPDATE_VAR',
            'value' => 'old_value',
            'is_preview' => false,
            'is_literal' => false,
            'is_multiline' => false,
            'resourceable_type' => Application::class,
            'resourceable_id' => $this->application->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/applications/{$this->application->uuid}/envs", [
            'key' => 'UPDATE_VAR',
            'value' => 'new_value',
        ]);

        $response->assertStatus(201);
    });

    test('returns 404 when environment variable key is not found', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/applications/{$this->application->uuid}/envs", [
            'key' => 'NON_EXISTENT_VAR',
            'value' => 'some_value',
        ]);

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Environment variable not found.']);
    });

    test('updates is_runtime and is_buildtime flags', function () {
        EnvironmentVariable::create([
            'uuid' => (string) new Cuid2,
            'key' => 'FLAGS_VAR',
            'value' => 'some_value',
            'is_preview' => false,
            'is_literal' => false,
            'is_multiline' => false,
            'is_runtime' => true,
            'is_buildtime' => false,
            'resourceable_type' => Application::class,
            'resourceable_id' => $this->application->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/applications/{$this->application->uuid}/envs", [
            'key' => 'FLAGS_VAR',
            'value' => 'updated_value',
            'is_runtime' => false,
            'is_buildtime' => true,
        ]);

        $response->assertStatus(201);
    });

    test('returns 422 when key is missing', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/applications/{$this->application->uuid}/envs", [
            'value' => 'some_value',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Validation failed.']);
    });
});

describe('PATCH /api/v1/applications/{uuid}/envs/bulk - Bulk update environment variables', function () {
    test('creates or updates multiple environment variables at once', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/applications/{$this->application->uuid}/envs/bulk", [
            'data' => [
                ['key' => 'BULK_VAR_1', 'value' => 'value1'],
                ['key' => 'BULK_VAR_2', 'value' => 'value2'],
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonCount(2);
    });

    test('returns 400 when data field is missing', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/applications/{$this->application->uuid}/envs/bulk", []);

        $response->assertStatus(400);
        $response->assertJson(['message' => 'Bulk data is required.']);
    });

    test('returns 422 when a bulk item has invalid data (missing key)', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/applications/{$this->application->uuid}/envs/bulk", [
            'data' => [
                ['value' => 'value_without_key'],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Validation failed.']);
    });
});

describe('DELETE /api/v1/applications/{uuid}/envs/{env_uuid} - Delete environment variable', function () {
    test('deletes an environment variable successfully', function () {
        $envUuid = (string) new Cuid2;
        EnvironmentVariable::create([
            'uuid' => $envUuid,
            'key' => 'DELETE_VAR',
            'value' => 'delete_value',
            'is_preview' => false,
            'is_literal' => false,
            'is_multiline' => false,
            'resourceable_type' => Application::class,
            'resourceable_id' => $this->application->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->deleteJson("/api/v1/applications/{$this->application->uuid}/envs/{$envUuid}");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Environment variable deleted.']);
    });

    test('returns 404 for unknown env UUID', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->deleteJson("/api/v1/applications/{$this->application->uuid}/envs/non-existent-env-uuid");

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Environment variable not found.']);
    });

    test('returns 404 when env belongs to another application (IDOR protection)', function () {
        // Create a second application owned by the same team
        $secondApp = Application::factory()->create([
            'uuid' => (string) new Cuid2,
            'name' => 'second-app',
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '4000',
        ]);

        $envUuid = (string) new Cuid2;
        EnvironmentVariable::create([
            'uuid' => $envUuid,
            'key' => 'OTHER_APP_VAR',
            'value' => 'some_value',
            'is_preview' => false,
            'is_literal' => false,
            'is_multiline' => false,
            'resourceable_type' => Application::class,
            'resourceable_id' => $secondApp->id,
        ]);

        // Attempt to delete the second app's env via the first app's UUID - must return 404
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->deleteJson("/api/v1/applications/{$this->application->uuid}/envs/{$envUuid}");

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Environment variable not found.']);
    });
});

describe('Multi-tenancy', function () {
    test('team A cannot access team B application envs', function () {
        // Create a completely separate team with its own application
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnvironment = Environment::factory()->create(['project_id' => $otherProject->id]);

        $otherApp = Application::factory()->create([
            'uuid' => (string) new Cuid2,
            'name' => 'other-team-app',
            'environment_id' => $otherEnvironment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '5000',
        ]);

        // Our team A token attempts to list envs of team B's application
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/applications/{$otherApp->uuid}/envs");

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Application not found']);
    });
});
