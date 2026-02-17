<?php

use App\Models\Environment;
use App\Models\EnvironmentVariable;
use App\Models\InstanceSettings;
use App\Models\PrivateKey;
use App\Models\Project;
use App\Models\Server;
use App\Models\ServerSetting;
use App\Models\Service;
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

    // Set session-based team context (used by currentTeam() helper)
    session(['currentTeam' => $this->team]);
    $this->token = $this->user->createToken('test-token', ['*']);
    $this->bearerToken = $this->token->plainTextToken;

    // InstanceSettings
    if (! InstanceSettings::first()) {
        InstanceSettings::create([
            'id' => 0,
            'is_api_enabled' => true,
        ]);
    }

    // Create infrastructure
    $this->project = Project::factory()->create(['team_id' => $this->team->id]);
    $this->environment = Environment::factory()->create(['project_id' => $this->project->id]);
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

    $this->destination = StandaloneDocker::withoutEvents(function () {
        return StandaloneDocker::create([
            'uuid' => (string) new Cuid2,
            'name' => 'default',
            'network' => 'saturn',
            'server_id' => $this->server->id,
        ]);
    });

    // Create a test service.
    // docker_compose_raw is NOT NULL in the services table so we must provide it.
    $this->service = Service::withoutEvents(function () {
        return Service::factory()->create([
            'uuid' => (string) new Cuid2,
            'environment_id' => $this->environment->id,
            'server_id' => $this->server->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'docker_compose_raw' => "services:\n  app:\n    image: nginx:latest\n",
        ]);
    });
});

describe('Authentication', function () {
    test('rejects list envs request without authentication', function () {
        $response = $this->getJson("/api/v1/services/{$this->service->uuid}/envs");
        $response->assertStatus(401);
    });

    test('rejects create env request without authentication', function () {
        $response = $this->postJson("/api/v1/services/{$this->service->uuid}/envs", [
            'key' => 'MY_VAR',
            'value' => 'my_value',
        ]);
        $response->assertStatus(401);
    });

    test('rejects update env request without authentication', function () {
        $response = $this->patchJson("/api/v1/services/{$this->service->uuid}/envs", [
            'key' => 'MY_VAR',
            'value' => 'new_value',
        ]);
        $response->assertStatus(401);
    });

    test('rejects delete env request without authentication', function () {
        $response = $this->deleteJson("/api/v1/services/{$this->service->uuid}/envs/some-uuid");
        $response->assertStatus(401);
    });
});

describe('GET /api/v1/services/{uuid}/envs - List environment variables', function () {
    test('returns empty array when service has no envs', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/services/{$this->service->uuid}/envs");

        $response->assertStatus(200);
        $response->assertJson([]);
    });

    test('returns list of environment variables', function () {
        // Create an env variable linked to the service via polymorphic relationship
        EnvironmentVariable::create([
            'uuid' => (string) new Cuid2,
            'key' => 'APP_ENV',
            'value' => 'production',
            'is_literal' => false,
            'is_multiline' => false,
            'resourceable_type' => Service::class,
            'resourceable_id' => $this->service->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/services/{$this->service->uuid}/envs");

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['key' => 'APP_ENV']);
    });

    test('returns 404 for non-existent service', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/services/non-existent-uuid/envs');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Service not found.']);
    });

    test('returns 404 for service from another team', function () {
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnvironment = Environment::factory()->create(['project_id' => $otherProject->id]);

        $otherService = Service::withoutEvents(function () use ($otherEnvironment) {
            return Service::factory()->create([
                'uuid' => (string) new Cuid2,
                'environment_id' => $otherEnvironment->id,
                'server_id' => $this->server->id,
                'destination_id' => $this->destination->id,
                'destination_type' => StandaloneDocker::class,
                'docker_compose_raw' => "services:\n  app:\n    image: nginx:latest\n",
            ]);
        });

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/services/{$otherService->uuid}/envs");

        $response->assertStatus(404);
    });

    test('hides sensitive value field by default with limited token', function () {
        EnvironmentVariable::create([
            'uuid' => (string) new Cuid2,
            'key' => 'SECRET_KEY',
            'value' => 'super-secret-value',
            'is_literal' => false,
            'is_multiline' => false,
            'resourceable_type' => Service::class,
            'resourceable_id' => $this->service->id,
        ]);

        $limitedToken = $this->user->createToken('read-only-token', ['read']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$limitedToken->plainTextToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/services/{$this->service->uuid}/envs");

        $response->assertStatus(200);
        $json = $response->json();
        $firstEnv = $json[0];

        expect($firstEnv)->not->toHaveKey('value');
        expect($firstEnv)->not->toHaveKey('real_value');
    });
});

describe('POST /api/v1/services/{uuid}/envs - Create environment variable', function () {
    test('creates an environment variable successfully', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson("/api/v1/services/{$this->service->uuid}/envs", [
            'key' => 'NEW_VAR',
            'value' => 'new_value',
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['key' => 'NEW_VAR']);
    });

    test('normalizes key by replacing spaces with underscores', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson("/api/v1/services/{$this->service->uuid}/envs", [
            'key' => 'MY VAR WITH SPACES',
            'value' => 'some_value',
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['key' => 'MY_VAR_WITH_SPACES']);
    });

    test('creates env variable with boolean flags', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson("/api/v1/services/{$this->service->uuid}/envs", [
            'key' => 'MULTILINE_VAR',
            'value' => "line1\nline2",
            'is_multiline' => true,
            'is_shown_once' => true,
        ]);

        $response->assertStatus(201);
    });

    test('returns 409 when environment variable already exists', function () {
        EnvironmentVariable::create([
            'uuid' => (string) new Cuid2,
            'key' => 'EXISTING_VAR',
            'value' => 'existing_value',
            'is_literal' => false,
            'is_multiline' => false,
            'resourceable_type' => Service::class,
            'resourceable_id' => $this->service->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson("/api/v1/services/{$this->service->uuid}/envs", [
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
        ])->postJson("/api/v1/services/{$this->service->uuid}/envs", [
            'value' => 'some_value',
        ]);

        $response->assertStatus(422);
    });

    test('returns 404 for non-existent service', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/services/non-existent-uuid/envs', [
            'key' => 'MY_VAR',
            'value' => 'my_value',
        ]);

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Service not found.']);
    });
});

describe('PATCH /api/v1/services/{uuid}/envs - Update environment variable', function () {
    test('updates an existing environment variable', function () {
        EnvironmentVariable::create([
            'uuid' => (string) new Cuid2,
            'key' => 'UPDATE_VAR',
            'value' => 'old_value',
            'is_literal' => false,
            'is_multiline' => false,
            'resourceable_type' => Service::class,
            'resourceable_id' => $this->service->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/services/{$this->service->uuid}/envs", [
            'key' => 'UPDATE_VAR',
            'value' => 'new_value',
        ]);

        $response->assertStatus(201);
    });

    test('returns 404 when environment variable does not exist', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/services/{$this->service->uuid}/envs", [
            'key' => 'NON_EXISTENT_VAR',
            'value' => 'some_value',
        ]);

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Environment variable not found.']);
    });

    test('returns 422 when key is missing', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/services/{$this->service->uuid}/envs", [
            'value' => 'some_value',
        ]);

        $response->assertStatus(422);
    });

    test('returns 404 for non-existent service', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson('/api/v1/services/non-existent-uuid/envs', [
            'key' => 'MY_VAR',
            'value' => 'value',
        ]);

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Service not found.']);
    });
});

describe('PATCH /api/v1/services/{uuid}/envs/bulk - Bulk update environment variables', function () {
    test('creates or updates multiple environment variables', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/services/{$this->service->uuid}/envs/bulk", [
            'data' => [
                ['key' => 'BULK_VAR_1', 'value' => 'value1'],
                ['key' => 'BULK_VAR_2', 'value' => 'value2'],
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonCount(2);
    });

    test('returns 400 when data is missing', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/services/{$this->service->uuid}/envs/bulk", []);

        $response->assertStatus(400);
        $response->assertJson(['message' => 'Bulk data is required.']);
    });

    test('returns 422 when a bulk item has invalid data', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/services/{$this->service->uuid}/envs/bulk", [
            'data' => [
                ['value' => 'value_without_key'],
            ],
        ]);

        $response->assertStatus(422);
    });

    test('returns 404 for non-existent service', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson('/api/v1/services/non-existent-uuid/envs/bulk', [
            'data' => [
                ['key' => 'SOME_VAR', 'value' => 'value'],
            ],
        ]);

        $response->assertStatus(404);
    });
});

describe('DELETE /api/v1/services/{uuid}/envs/{env_uuid} - Delete environment variable', function () {
    test('deletes an environment variable successfully', function () {
        $envUuid = (string) new Cuid2;
        EnvironmentVariable::create([
            'uuid' => $envUuid,
            'key' => 'DELETE_VAR',
            'value' => 'delete_value',
            'is_literal' => false,
            'is_multiline' => false,
            'resourceable_type' => Service::class,
            'resourceable_id' => $this->service->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->deleteJson("/api/v1/services/{$this->service->uuid}/envs/{$envUuid}");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Environment variable deleted.']);
    });

    test('returns 404 when environment variable does not exist', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->deleteJson("/api/v1/services/{$this->service->uuid}/envs/non-existent-env-uuid");

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Environment variable not found.']);
    });

    test('cannot delete env variable belonging to another service', function () {
        // Create a second service in the same team
        $secondService = Service::withoutEvents(function () {
            return Service::factory()->create([
                'uuid' => (string) new Cuid2,
                'environment_id' => $this->environment->id,
                'server_id' => $this->server->id,
                'destination_id' => $this->destination->id,
                'destination_type' => StandaloneDocker::class,
                'docker_compose_raw' => "services:\n  app:\n    image: nginx:latest\n",
            ]);
        });

        $envUuid = (string) new Cuid2;
        EnvironmentVariable::create([
            'uuid' => $envUuid,
            'key' => 'OTHER_SERVICE_VAR',
            'value' => 'some_value',
            'is_literal' => false,
            'is_multiline' => false,
            'resourceable_type' => Service::class,
            'resourceable_id' => $secondService->id,
        ]);

        // Try to delete the env via the first service's UUID - should return 404
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->deleteJson("/api/v1/services/{$this->service->uuid}/envs/{$envUuid}");

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Environment variable not found.']);
    });

    test('returns 404 for non-existent service', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->deleteJson('/api/v1/services/non-existent-uuid/envs/some-env-uuid');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Service not found.']);
    });
});
