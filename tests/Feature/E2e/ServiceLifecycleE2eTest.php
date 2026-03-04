<?php

/**
 * E2E Service Lifecycle Tests
 *
 * Tests the full service lifecycle through the API:
 * - Create services (one-click and custom docker-compose)
 * - Read service details and list
 * - Update service settings
 * - Delete service with cleanup options
 * - API token scope enforcement
 * - Cross-team isolation
 */

use App\Jobs\DeleteResourceJob;
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

// ─── Helpers ─────────────────────────────────────────────────────────────────

function svcApiHeaders(string $bearerToken): array
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
});

// ─── GET /services — List services ──────────────────────────────────────────

describe('GET /api/v1/services — List services', function () {
    test('returns empty array when no services exist', function () {
        $response = $this->withHeaders(svcApiHeaders($this->bearerToken))
            ->getJson('/api/v1/services');

        $response->assertStatus(200);
        $response->assertJson([]);
    });

    test('lists services for the team', function () {
        Service::factory()->create([
            'name' => 'Service One',
            'environment_id' => $this->environment->id,
            'server_id' => $this->server->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
        ]);
        Service::factory()->create([
            'name' => 'Service Two',
            'environment_id' => $this->environment->id,
            'server_id' => $this->server->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
        ]);

        $response = $this->withHeaders(svcApiHeaders($this->bearerToken))
            ->getJson('/api/v1/services');

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Service One']);
        $response->assertJsonFragment(['name' => 'Service Two']);
    });

    test('does not include services from other teams', function () {
        Service::factory()->create([
            'name' => 'My Service',
            'environment_id' => $this->environment->id,
            'server_id' => $this->server->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
        ]);

        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnv = Environment::factory()->create(['project_id' => $otherProject->id]);

        Service::factory()->create([
            'name' => 'Other Team Service',
            'environment_id' => $otherEnv->id,
            'server_id' => $this->server->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
        ]);

        $response = $this->withHeaders(svcApiHeaders($this->bearerToken))
            ->getJson('/api/v1/services');

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'My Service']);
        $response->assertJsonMissing(['name' => 'Other Team Service']);
    });
});

// ─── GET /services/{uuid} — Get service by UUID ─────────────────────────────

describe('GET /api/v1/services/{uuid} — Get service by UUID', function () {
    test('returns service details', function () {
        $service = Service::factory()->create([
            'name' => 'Detailed Service',
            'description' => 'A test service',
            'environment_id' => $this->environment->id,
            'server_id' => $this->server->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
        ]);

        $response = $this->withHeaders(svcApiHeaders($this->bearerToken))
            ->getJson("/api/v1/services/{$service->uuid}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Detailed Service']);
        $response->assertJsonStructure(['uuid', 'name']);
    });

    test('returns 404 for non-existent service', function () {
        $response = $this->withHeaders(svcApiHeaders($this->bearerToken))
            ->getJson('/api/v1/services/non-existent-uuid');

        $response->assertStatus(404);
    });

    test('cannot access service from another team', function () {
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnv = Environment::factory()->create(['project_id' => $otherProject->id]);

        $otherService = Service::factory()->create([
            'name' => 'Other Service',
            'environment_id' => $otherEnv->id,
            'server_id' => $this->server->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
        ]);

        $response = $this->withHeaders(svcApiHeaders($this->bearerToken))
            ->getJson("/api/v1/services/{$otherService->uuid}");

        $response->assertStatus(404);
    });
});

// ─── POST /services — Create service ─────────────────────────────────────────

describe('POST /api/v1/services — Create service', function () {
    test('creates custom service from docker-compose', function () {
        $compose = base64_encode("version: '3.8'\nservices:\n  web:\n    image: nginx:latest\n    ports:\n      - '80:80'\n");

        $response = $this->withHeaders(svcApiHeaders($this->bearerToken))
            ->postJson('/api/v1/services', [
                'project_uuid' => $this->project->uuid,
                'server_uuid' => $this->server->uuid,
                'environment_name' => $this->environment->name,
                'docker_compose_raw' => $compose,
                'name' => 'Custom Nginx Service',
                'description' => 'Custom service for testing',
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['uuid']);
    });

    test('creates service using environment_uuid', function () {
        $compose = base64_encode("version: '3.8'\nservices:\n  app:\n    image: redis:7\n");

        $response = $this->withHeaders(svcApiHeaders($this->bearerToken))
            ->postJson('/api/v1/services', [
                'project_uuid' => $this->project->uuid,
                'server_uuid' => $this->server->uuid,
                'environment_uuid' => $this->environment->uuid,
                'docker_compose_raw' => $compose,
                'name' => 'Redis Service',
            ]);

        $response->assertStatus(201);
    });

    test('validates required project_uuid', function () {
        $compose = base64_encode("version: '3.8'\nservices:\n  app:\n    image: nginx\n");

        $response = $this->withHeaders(svcApiHeaders($this->bearerToken))
            ->postJson('/api/v1/services', [
                'server_uuid' => $this->server->uuid,
                'environment_name' => $this->environment->name,
                'docker_compose_raw' => $compose,
            ]);

        $response->assertStatus(422);
    });

    test('validates required server_uuid', function () {
        $compose = base64_encode("version: '3.8'\nservices:\n  app:\n    image: nginx\n");

        $response = $this->withHeaders(svcApiHeaders($this->bearerToken))
            ->postJson('/api/v1/services', [
                'project_uuid' => $this->project->uuid,
                'environment_name' => $this->environment->name,
                'docker_compose_raw' => $compose,
            ]);

        $response->assertStatus(422);
    });

    test('requires either type or docker_compose_raw', function () {
        $response = $this->withHeaders(svcApiHeaders($this->bearerToken))
            ->postJson('/api/v1/services', [
                'project_uuid' => $this->project->uuid,
                'server_uuid' => $this->server->uuid,
                'environment_name' => $this->environment->name,
            ]);

        // Controller returns 400 when neither type nor docker_compose_raw is provided
        $response->assertStatus(400);
    });

    test('returns 404 for non-existent project', function () {
        $compose = base64_encode("version: '3.8'\nservices:\n  app:\n    image: nginx\n");

        $response = $this->withHeaders(svcApiHeaders($this->bearerToken))
            ->postJson('/api/v1/services', [
                'project_uuid' => 'fake-project-uuid',
                'server_uuid' => $this->server->uuid,
                'environment_name' => $this->environment->name,
                'docker_compose_raw' => $compose,
            ]);

        $response->assertStatus(404);
    });

    test('returns 404 for non-existent server', function () {
        $compose = base64_encode("version: '3.8'\nservices:\n  app:\n    image: nginx\n");

        $response = $this->withHeaders(svcApiHeaders($this->bearerToken))
            ->postJson('/api/v1/services', [
                'project_uuid' => $this->project->uuid,
                'server_uuid' => 'fake-server-uuid',
                'environment_name' => $this->environment->name,
                'docker_compose_raw' => $compose,
            ]);

        $response->assertStatus(404);
    });

    test('returns 404 for non-existent environment', function () {
        $compose = base64_encode("version: '3.8'\nservices:\n  app:\n    image: nginx\n");

        $response = $this->withHeaders(svcApiHeaders($this->bearerToken))
            ->postJson('/api/v1/services', [
                'project_uuid' => $this->project->uuid,
                'server_uuid' => $this->server->uuid,
                'environment_name' => 'non-existent-env',
                'docker_compose_raw' => $compose,
            ]);

        $response->assertStatus(404);
    });
});

// ─── PATCH /services/{uuid} — Update service ─────────────────────────────────

describe('PATCH /api/v1/services/{uuid} — Update service', function () {
    test('updates service name', function () {
        $service = Service::factory()->create([
            'name' => 'Old Service Name',
            'environment_id' => $this->environment->id,
            'server_id' => $this->server->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
        ]);

        $response = $this->withHeaders(svcApiHeaders($this->bearerToken))
            ->patchJson("/api/v1/services/{$service->uuid}", [
                'name' => 'New Service Name',
            ]);

        $response->assertStatus(200);

        $service->refresh();
        expect($service->name)->toBe('New Service Name');
    });

    test('updates service description', function () {
        $service = Service::factory()->create([
            'environment_id' => $this->environment->id,
            'server_id' => $this->server->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
        ]);

        $response = $this->withHeaders(svcApiHeaders($this->bearerToken))
            ->patchJson("/api/v1/services/{$service->uuid}", [
                'description' => 'Updated description for service',
            ]);

        $response->assertStatus(200);

        $service->refresh();
        expect($service->description)->toBe('Updated description for service');
    });

    test('updates resource limits', function () {
        $service = Service::factory()->create([
            'environment_id' => $this->environment->id,
            'server_id' => $this->server->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
        ]);

        $response = $this->withHeaders(svcApiHeaders($this->bearerToken))
            ->patchJson("/api/v1/services/{$service->uuid}", [
                'limits_memory' => '1g',
                'limits_cpus' => '2',
            ]);

        $response->assertStatus(200);

        $service->refresh();
        expect($service->limits_memory)->toBe('1g');
        expect($service->limits_cpus)->toBe('2');
    });

    test('returns 404 for non-existent service', function () {
        $response = $this->withHeaders(svcApiHeaders($this->bearerToken))
            ->patchJson('/api/v1/services/non-existent-uuid', [
                'name' => 'Test',
            ]);

        $response->assertStatus(404);
    });

    test('cannot update service from another team', function () {
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnv = Environment::factory()->create(['project_id' => $otherProject->id]);
        $otherService = Service::factory()->create([
            'name' => 'Other Service',
            'environment_id' => $otherEnv->id,
            'server_id' => $this->server->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
        ]);

        $response = $this->withHeaders(svcApiHeaders($this->bearerToken))
            ->patchJson("/api/v1/services/{$otherService->uuid}", [
                'name' => 'Hacked',
            ]);

        $response->assertStatus(404);

        $otherService->refresh();
        expect($otherService->name)->toBe('Other Service');
    });
});

// ─── DELETE /services/{uuid} — Delete service ────────────────────────────────

describe('DELETE /api/v1/services/{uuid} — Delete service', function () {
    test('queues deletion job for existing service', function () {
        $service = Service::factory()->create([
            'environment_id' => $this->environment->id,
            'server_id' => $this->server->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
        ]);

        $response = $this->withHeaders(svcApiHeaders($this->bearerToken))
            ->deleteJson("/api/v1/services/{$service->uuid}");

        $response->assertStatus(200);

        Queue::assertPushed(DeleteResourceJob::class);
    });

    test('accepts cleanup options', function () {
        $service = Service::factory()->create([
            'environment_id' => $this->environment->id,
            'server_id' => $this->server->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
        ]);

        $response = $this->withHeaders(svcApiHeaders($this->bearerToken))
            ->deleteJson("/api/v1/services/{$service->uuid}?delete_volumes=false&docker_cleanup=false");

        $response->assertStatus(200);

        Queue::assertPushed(DeleteResourceJob::class);
    });

    test('returns 404 for non-existent service', function () {
        $response = $this->withHeaders(svcApiHeaders($this->bearerToken))
            ->deleteJson('/api/v1/services/non-existent-uuid');

        $response->assertStatus(404);
    });

    test('cannot delete service from another team', function () {
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnv = Environment::factory()->create(['project_id' => $otherProject->id]);
        $otherService = Service::factory()->create([
            'environment_id' => $otherEnv->id,
            'server_id' => $this->server->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
        ]);

        $response = $this->withHeaders(svcApiHeaders($this->bearerToken))
            ->deleteJson("/api/v1/services/{$otherService->uuid}");

        $response->assertStatus(404);
    });
});

// ─── API Token Scope Enforcement ─────────────────────────────────────────────

describe('API token scope enforcement for services', function () {
    test('read-only token can list services', function () {
        $readToken = $this->user->createToken('read-only', ['read']);

        $response = $this->withHeaders(svcApiHeaders($readToken->plainTextToken))
            ->getJson('/api/v1/services');

        $response->assertStatus(200);
    });

    test('read-only token can get service by UUID', function () {
        $readToken = $this->user->createToken('read-only', ['read']);
        $service = Service::factory()->create([
            'environment_id' => $this->environment->id,
            'server_id' => $this->server->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
        ]);

        $response = $this->withHeaders(svcApiHeaders($readToken->plainTextToken))
            ->getJson("/api/v1/services/{$service->uuid}");

        $response->assertStatus(200);
    });

    test('read-only token cannot create service', function () {
        $readToken = $this->user->createToken('read-only', ['read']);
        $compose = base64_encode("version: '3.8'\nservices:\n  app:\n    image: nginx\n");

        $response = $this->withHeaders(svcApiHeaders($readToken->plainTextToken))
            ->postJson('/api/v1/services', [
                'project_uuid' => $this->project->uuid,
                'server_uuid' => $this->server->uuid,
                'environment_name' => $this->environment->name,
                'docker_compose_raw' => $compose,
            ]);

        $response->assertStatus(403);
    });

    test('read-only token cannot update service', function () {
        $readToken = $this->user->createToken('read-only', ['read']);
        $service = Service::factory()->create([
            'environment_id' => $this->environment->id,
            'server_id' => $this->server->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
        ]);

        $response = $this->withHeaders(svcApiHeaders($readToken->plainTextToken))
            ->patchJson("/api/v1/services/{$service->uuid}", ['name' => 'Hack']);

        $response->assertStatus(403);
    });

    test('read-only token cannot delete service', function () {
        $readToken = $this->user->createToken('read-only', ['read']);
        $service = Service::factory()->create([
            'environment_id' => $this->environment->id,
            'server_id' => $this->server->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
        ]);

        $response = $this->withHeaders(svcApiHeaders($readToken->plainTextToken))
            ->deleteJson("/api/v1/services/{$service->uuid}");

        $response->assertStatus(403);
    });
});

// ─── Full Lifecycle E2E ──────────────────────────────────────────────────────

describe('Full service lifecycle — create → read → update → delete', function () {
    test('complete lifecycle: create → get → update → list → delete', function () {
        $compose = base64_encode("version: '3.8'\nservices:\n  web:\n    image: nginx:alpine\n    ports:\n      - '80:80'\n");

        // 1. Create service
        $createResponse = $this->withHeaders(svcApiHeaders($this->bearerToken))
            ->postJson('/api/v1/services', [
                'project_uuid' => $this->project->uuid,
                'server_uuid' => $this->server->uuid,
                'environment_name' => $this->environment->name,
                'docker_compose_raw' => $compose,
                'name' => 'Lifecycle Test Service',
                'description' => 'Testing full lifecycle',
            ]);

        $createResponse->assertStatus(201);
        $uuid = $createResponse->json('uuid');
        expect($uuid)->toBeString();

        // 2. Get service details
        $getResponse = $this->withHeaders(svcApiHeaders($this->bearerToken))
            ->getJson("/api/v1/services/{$uuid}");

        $getResponse->assertStatus(200);
        $getResponse->assertJsonFragment(['name' => 'Lifecycle Test Service']);

        // 3. Update service
        $updateResponse = $this->withHeaders(svcApiHeaders($this->bearerToken))
            ->patchJson("/api/v1/services/{$uuid}", [
                'name' => 'Updated Lifecycle Service',
                'description' => 'Updated description',
                'limits_memory' => '512m',
            ]);

        $updateResponse->assertStatus(200);

        // 4. Verify update
        $service = Service::where('uuid', $uuid)->first();
        expect($service->name)->toBe('Updated Lifecycle Service');
        expect($service->description)->toBe('Updated description');
        expect($service->limits_memory)->toBe('512m');

        // 5. Verify in list
        $listResponse = $this->withHeaders(svcApiHeaders($this->bearerToken))
            ->getJson('/api/v1/services');

        $listResponse->assertStatus(200);
        $listResponse->assertJsonFragment(['name' => 'Updated Lifecycle Service']);

        // 6. Delete service
        $deleteResponse = $this->withHeaders(svcApiHeaders($this->bearerToken))
            ->deleteJson("/api/v1/services/{$uuid}");

        $deleteResponse->assertStatus(200);
        Queue::assertPushed(DeleteResourceJob::class);
    });
});
