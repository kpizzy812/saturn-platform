<?php

use App\Models\Environment;
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
use Illuminate\Support\Facades\Queue;
use Visus\Cuid2\Cuid2;

uses(DatabaseTransactions::class);

beforeEach(function () {
    Queue::fake(); // Prevent actual job dispatching

    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();
    $this->team->members()->attach($this->user->id, ['role' => 'owner']);

    session(['currentTeam' => $this->team]);
    $this->token = $this->user->createToken('test-token', ['*']);
    $this->bearerToken = $this->token->plainTextToken;

    // Create InstanceSettings
    if (! InstanceSettings::first()) {
        InstanceSettings::create([
            'id' => 0,
            'is_api_enabled' => true,
        ]);
    }

    // Create project > environment chain
    $this->project = Project::factory()->create(['team_id' => $this->team->id]);
    $this->environment = Environment::factory()->create(['project_id' => $this->project->id]);

    // Create server + destination (without triggering SSH)
    $this->privateKey = PrivateKey::factory()->create(['team_id' => $this->team->id]);
    // withoutEvents() skips BaseModel::boot() which generates uuid, so set it explicitly
    $this->server = Server::withoutEvents(function () {
        return Server::factory()->create([
            'uuid' => (string) new Cuid2,
            'team_id' => $this->team->id,
            'private_key_id' => $this->privateKey->id,
        ]);
    });

    // withoutEvents() also skips the created event that creates ServerSetting
    ServerSetting::firstOrCreate(['server_id' => $this->server->id]);

    // Create StandaloneDocker destination without triggering docker network creation
    $this->destination = StandaloneDocker::withoutEvents(function () {
        return StandaloneDocker::create([
            'uuid' => (string) new Cuid2,
            'name' => 'default',
            'network' => 'saturn',
            'server_id' => $this->server->id,
        ]);
    });

    // Create a test service
    // services.docker_compose_raw is NOT NULL — include it
    $this->service = Service::factory()->create([
        'environment_id' => $this->environment->id,
        'server_id' => $this->server->id,
        'destination_id' => $this->destination->id,
        'destination_type' => StandaloneDocker::class,
        'docker_compose_raw' => "version: '3.8'\nservices:\n  app:\n    image: nginx:latest",
    ]);
});

describe('Authentication', function () {
    test('rejects request without authentication', function () {
        $response = $this->getJson('/api/v1/services');
        $response->assertStatus(401);
    });

    test('rejects request with invalid token', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token-here',
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/services');

        $response->assertStatus(401);
    });
});

describe('GET /api/v1/services - List services', function () {
    test('returns list of services for current team', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/services');

        $response->assertStatus(200);
        $response->assertJsonIsArray();
    });

    test('returns empty array when no services exist', function () {
        // Delete the default service
        $this->service->forceDelete();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/services');

        $response->assertStatus(200);
        $response->assertJson([]);
    });

    test('returns only services for current team', function () {
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnvironment = Environment::factory()->create(['project_id' => $otherProject->id]);
        $otherService = Service::factory()->create([
            'environment_id' => $otherEnvironment->id,
            'server_id' => $this->server->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'docker_compose_raw' => "version: '3.8'\nservices:\n  app:\n    image: nginx:latest",
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/services');

        $response->assertStatus(200);
        $response->assertJsonMissing(['uuid' => $otherService->uuid]);
    });

    test('hides sensitive fields by default', function () {
        $limitedToken = $this->user->createToken('read-only-token', ['read']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$limitedToken->plainTextToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/services');

        $response->assertStatus(200);
    });
});

describe('GET /api/v1/services/{uuid} - Get service by UUID', function () {
    test('returns service details with correct structure', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/services/{$this->service->uuid}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'uuid',
            'name',
        ]);
        $response->assertJsonFragment(['uuid' => $this->service->uuid]);
    });

    test('returns 404 for non-existent service', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/services/non-existent-uuid');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Service not found.']);
    });

    test('returns 404 for service from another team', function () {
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnvironment = Environment::factory()->create(['project_id' => $otherProject->id]);
        $otherService = Service::factory()->create([
            'environment_id' => $otherEnvironment->id,
            'server_id' => $this->server->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'docker_compose_raw' => "version: '3.8'\nservices:\n  app:\n    image: nginx:latest",
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/services/{$otherService->uuid}");

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Service not found.']);
    });
});

describe('PATCH /api/v1/services/{uuid} - Update service', function () {
    test('updates service name successfully', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/services/{$this->service->uuid}", [
            'name' => 'Updated Service Name',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['uuid', 'domains']);
        $response->assertJsonFragment(['uuid' => $this->service->uuid]);

        $this->assertDatabaseHas('services', [
            'uuid' => $this->service->uuid,
            'name' => 'Updated Service Name',
        ]);
    });

    test('updates service description successfully', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/services/{$this->service->uuid}", [
            'description' => 'Updated description text',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('services', [
            'uuid' => $this->service->uuid,
            'description' => 'Updated description text',
        ]);
    });

    test('updates docker_compose_raw with base64 encoded value', function () {
        $compose = "version: '3.8'\nservices:\n  web:\n    image: nginx:latest";
        $encoded = base64_encode($compose);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/services/{$this->service->uuid}", [
            'docker_compose_raw' => $encoded,
        ]);

        $response->assertStatus(200);
    });

    test('rejects docker_compose_raw that is not base64 encoded', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/services/{$this->service->uuid}", [
            'docker_compose_raw' => 'not-base64-encoded-string!!!',
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['docker_compose_raw']]);
    });

    test('rejects extra fields not in allowed list', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/services/{$this->service->uuid}", [
            'name' => 'Valid Name',
            'invalid_field' => 'some value',
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['invalid_field']]);
    });

    test('validates limits_memory_swappiness is between 0 and 100', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/services/{$this->service->uuid}", [
            'limits_memory_swappiness' => 150,
        ]);

        $response->assertStatus(422);
    });

    test('updates resource limits successfully', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/services/{$this->service->uuid}", [
            'limits_memory' => '512m',
            'limits_cpus' => '0.5',
        ]);

        $response->assertStatus(200);
    });

    test('returns 404 for non-existent service', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson('/api/v1/services/non-existent-uuid', [
            'name' => 'Test Name',
        ]);

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Service not found.']);
    });

    test('cannot update service from another team', function () {
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnvironment = Environment::factory()->create(['project_id' => $otherProject->id]);
        $otherService = Service::factory()->create([
            'environment_id' => $otherEnvironment->id,
            'server_id' => $this->server->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'docker_compose_raw' => "version: '3.8'\nservices:\n  app:\n    image: nginx:latest",
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/services/{$otherService->uuid}", [
            'name' => 'Hacked Name',
        ]);

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Service not found.']);
    });

    test('rejects empty request body', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/services/{$this->service->uuid}", []);

        $response->assertStatus(400);
    });
});

describe('DELETE /api/v1/services/{uuid} - Delete service', function () {
    test('deletes service successfully', function () {
        $uuid = $this->service->uuid;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->deleteJson("/api/v1/services/{$uuid}");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Service deletion request queued.']);

        Queue::assertPushed(\App\Jobs\DeleteResourceJob::class);
    });

    test('returns 404 for non-existent service', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->deleteJson('/api/v1/services/non-existent-uuid');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Service not found.']);
    });

    test('cannot delete service from another team', function () {
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnvironment = Environment::factory()->create(['project_id' => $otherProject->id]);
        $otherService = Service::factory()->create([
            'environment_id' => $otherEnvironment->id,
            'server_id' => $this->server->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'docker_compose_raw' => "version: '3.8'\nservices:\n  app:\n    image: nginx:latest",
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->deleteJson("/api/v1/services/{$otherService->uuid}");

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Service not found.']);
    });

    test('accepts query parameters for delete options', function () {
        $uuid = $this->service->uuid;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->deleteJson("/api/v1/services/{$uuid}?delete_volumes=true&delete_configurations=false&docker_cleanup=false");

        $response->assertStatus(200);
        Queue::assertPushed(\App\Jobs\DeleteResourceJob::class);
    });
});

describe('POST /api/v1/services - Create service', function () {
    test('returns 400 when neither type nor docker_compose_raw is provided', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/services', [
            'project_uuid' => $this->project->uuid,
            'environment_name' => $this->environment->name,
            'server_uuid' => $this->server->uuid,
        ]);

        $response->assertStatus(400);
        $response->assertJson(['message' => 'No service type or docker_compose_raw provided.']);
    });

    test('returns 400 for empty request body', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/services', []);

        $response->assertStatus(400);
    });

    test('returns 422 when environment_name and environment_uuid are both missing', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/services', [
            'type' => 'minio',
            'project_uuid' => $this->project->uuid,
            'server_uuid' => $this->server->uuid,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'You need to provide at least one of environment_name or environment_uuid.']);
    });

    test('returns 404 when project_uuid does not exist', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/services', [
            'type' => 'minio',
            'project_uuid' => 'non-existent-uuid',
            'environment_name' => $this->environment->name,
            'server_uuid' => $this->server->uuid,
        ]);

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Project not found.']);
    });

    test('returns 404 when environment does not exist', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/services', [
            'type' => 'minio',
            'project_uuid' => $this->project->uuid,
            'environment_name' => 'non-existent-environment',
            'server_uuid' => $this->server->uuid,
        ]);

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Environment not found.']);
    });

    test('returns 404 when server_uuid does not exist', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/services', [
            'type' => 'minio',
            'project_uuid' => $this->project->uuid,
            'environment_name' => $this->environment->name,
            'server_uuid' => 'non-existent-uuid',
        ]);

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Server not found.']);
    });

    test('returns 422 when docker_compose_raw is not base64 encoded for custom service', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/services', [
            'docker_compose_raw' => 'this is not base64!!!',
            'project_uuid' => $this->project->uuid,
            'environment_name' => $this->environment->name,
            'server_uuid' => $this->server->uuid,
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['docker_compose_raw']]);
    });

    test('rejects extra fields not in allowed list', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/services', [
            'type' => 'minio',
            'project_uuid' => $this->project->uuid,
            'environment_name' => $this->environment->name,
            'server_uuid' => $this->server->uuid,
            'invalid_field' => 'invalid_value',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['invalid_field' => ['This field is not allowed.']]);
    });
});
