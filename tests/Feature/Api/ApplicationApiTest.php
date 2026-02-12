<?php

use App\Models\Application;
use App\Models\Environment;
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
    // Create server without triggering boot events that do SSH.
    // withoutEvents() also skips BaseModel::boot() which generates uuid,
    // so we must set uuid explicitly.
    $this->server = Server::withoutEvents(function () {
        return Server::factory()->create([
            'uuid' => (string) new Cuid2,
            'team_id' => $this->team->id,
            'private_key_id' => $this->privateKey->id,
        ]);
    });

    // withoutEvents() also skips the created event that creates ServerSetting,
    // so we must create it manually. ServerSetting is needed by server methods
    // like isBuildServer() and isProxyShouldRun().
    ServerSetting::firstOrCreate(['server_id' => $this->server->id]);

    // Create StandaloneDocker destination without triggering docker network creation.
    // Must set uuid explicitly since withoutEvents() skips BaseModel::boot().
    $this->destination = StandaloneDocker::withoutEvents(function () {
        return StandaloneDocker::create([
            'uuid' => (string) new Cuid2,
            'name' => 'default',
            'network' => 'saturn',
            'server_id' => $this->server->id,
        ]);
    });

    // Create a test application
    $this->application = Application::factory()->create([
        'environment_id' => $this->environment->id,
        'destination_id' => $this->destination->id,
        'destination_type' => StandaloneDocker::class,
    ]);
});

describe('Authentication', function () {
    test('rejects request without authentication', function () {
        $response = $this->getJson('/api/v1/applications');
        $response->assertStatus(401);
    });

    test('rejects request with invalid token', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token-here',
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/applications');

        $response->assertStatus(401);
    });
});

describe('GET /api/v1/applications - List applications', function () {
    test('returns empty array when no applications exist', function () {
        // Delete the default application
        $this->application->forceDelete();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/applications');

        $response->assertStatus(200);
        $response->assertJson([]);
    });

    test('returns list of applications with correct structure', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/applications');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonStructure([
            '*' => [
                'uuid',
                'name',
                'git_repository',
                'git_branch',
                'build_pack',
                'ports_exposes',
                'environment_id',
                'destination_id',
                'destination_type',
            ],
        ]);
    });

    test('returns only applications for current team', function () {
        // Create another team with application
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnvironment = Environment::factory()->create(['project_id' => $otherProject->id]);
        $otherApplication = Application::factory()->create([
            'environment_id' => $otherEnvironment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/applications');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['uuid' => $this->application->uuid]);
        $response->assertJsonMissing(['uuid' => $otherApplication->uuid]);
    });

    test('hides sensitive fields by default', function () {
        // Create a limited token without root or read:sensitive abilities,
        // so the API middleware will not grant can_read_sensitive access.
        $limitedToken = $this->user->createToken('read-only-token', ['read']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$limitedToken->plainTextToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/applications');

        $response->assertStatus(200);
        $json = $response->json();
        $firstApp = $json[0];

        expect($firstApp)->not->toHaveKey('custom_labels');
        expect($firstApp)->not->toHaveKey('dockerfile');
        expect($firstApp)->not->toHaveKey('docker_compose_raw');
        expect($firstApp)->not->toHaveKey('manual_webhook_secret_github');
        expect($firstApp)->not->toHaveKey('manual_webhook_secret_gitlab');
        expect($firstApp)->not->toHaveKey('manual_webhook_secret_bitbucket');
        expect($firstApp)->not->toHaveKey('manual_webhook_secret_gitea');
        expect($firstApp)->not->toHaveKey('private_key_id');
    });
});

describe('GET /api/v1/applications/{uuid} - Get application by UUID', function () {
    test('returns application details with correct structure', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/applications/{$this->application->uuid}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'uuid',
            'name',
            'git_repository',
            'git_branch',
            'build_pack',
            'ports_exposes',
            'environment_id',
            'destination_id',
            'destination_type',
        ]);
        $response->assertJsonFragment(['uuid' => $this->application->uuid]);
    });

    test('returns 404 for non-existent application', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/applications/non-existent-uuid');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Application not found.']);
    });

    test('returns 404 for application from another team', function () {
        // Create another team with application
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnvironment = Environment::factory()->create(['project_id' => $otherProject->id]);
        $otherApplication = Application::factory()->create([
            'environment_id' => $otherEnvironment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/applications/{$otherApplication->uuid}");

        $response->assertStatus(404);
    });
});

describe('PATCH /api/v1/applications/{uuid} - Update application', function () {
    test('updates application name successfully', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/applications/{$this->application->uuid}", [
            'name' => 'Updated Application Name',
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['uuid' => $this->application->uuid]);

        $this->assertDatabaseHas('applications', [
            'uuid' => $this->application->uuid,
            'name' => 'Updated Application Name',
        ]);
    });

    test('updates application description successfully', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/applications/{$this->application->uuid}", [
            'description' => 'Updated description text',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('applications', [
            'uuid' => $this->application->uuid,
            'description' => 'Updated description text',
        ]);
    });

    test('updates git_repository and git_branch successfully', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/applications/{$this->application->uuid}", [
            'git_repository' => 'https://github.com/user/new-repo',
            'git_branch' => 'develop',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('applications', [
            'uuid' => $this->application->uuid,
            'git_repository' => 'https://github.com/user/new-repo',
            'git_branch' => 'develop',
        ]);
    });

    test('updates build_pack successfully', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/applications/{$this->application->uuid}", [
            'build_pack' => 'dockerfile',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('applications', [
            'uuid' => $this->application->uuid,
            'build_pack' => 'dockerfile',
        ]);
    });

    test('updates ports_exposes with valid format', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/applications/{$this->application->uuid}", [
            'ports_exposes' => '3000,8080,9000',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('applications', [
            'uuid' => $this->application->uuid,
            'ports_exposes' => '3000,8080,9000',
        ]);
    });

    test('rejects ports_exposes with invalid format', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/applications/{$this->application->uuid}", [
            'ports_exposes' => 'invalid,port,format',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ports_exposes']);
    });

    test('rejects extra fields not in allowed list', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/applications/{$this->application->uuid}", [
            'name' => 'Valid Name',
            'invalid_field' => 'some value',
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['invalid_field']]);
    });

    test('updates custom_labels with base64 encoded value', function () {
        $labels = 'traefik.enable=true';
        $encoded = base64_encode($labels);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/applications/{$this->application->uuid}", [
            'custom_labels' => $encoded,
        ]);

        $response->assertStatus(200);

        $this->application->refresh();
        // The controller stores the base64 encoded value directly.
        // Verify it was stored by decoding the stored value.
        $storedLabels = base64_decode($this->application->custom_labels);
        expect($storedLabels)->toContain('traefik.enable');
    });

    test('rejects custom_labels that are not base64 encoded', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/applications/{$this->application->uuid}", [
            'custom_labels' => 'not-base64-encoded-string!!!',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['custom_labels']);
    });

    test('updates docker_compose_raw with base64 encoded value', function () {
        $compose = "version: '3.8'\nservices:\n  web:\n    image: nginx:latest";
        $encoded = base64_encode($compose);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/applications/{$this->application->uuid}", [
            'docker_compose_raw' => $encoded,
        ]);

        $response->assertStatus(200);
    });

    test('rejects docker_compose_raw that is not base64 encoded when docker_compose_domains present', function () {
        // The controller only validates docker_compose_raw as base64 when
        // docker_compose_domains is also present in the request.
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/applications/{$this->application->uuid}", [
            'docker_compose_raw' => 'not-base64-string!!!',
            'docker_compose_domains' => [['name' => 'web', 'domain' => 'https://example.com']],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['docker_compose_raw']);
    });

    test('returns 404 for non-existent application', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson('/api/v1/applications/non-existent-uuid', [
            'name' => 'Test Name',
        ]);

        $response->assertStatus(404);
    });

    test('cannot update application from another team', function () {
        // Create another team with application
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnvironment = Environment::factory()->create(['project_id' => $otherProject->id]);
        $otherApplication = Application::factory()->create([
            'environment_id' => $otherEnvironment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/applications/{$otherApplication->uuid}", [
            'name' => 'Hacked Name',
        ]);

        $response->assertStatus(404);
    });

    test('updates multiple fields in single request', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/applications/{$this->application->uuid}", [
            'name' => 'Multi-Update Name',
            'description' => 'Multi-Update Description',
            'git_branch' => 'staging',
            'build_pack' => 'static',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('applications', [
            'uuid' => $this->application->uuid,
            'name' => 'Multi-Update Name',
            'description' => 'Multi-Update Description',
            'git_branch' => 'staging',
            'build_pack' => 'static',
        ]);
    });

    test('validates ports_mappings format', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/applications/{$this->application->uuid}", [
            'ports_mappings' => '8080:80,9090:90',
        ]);

        $response->assertStatus(200);
    });

    test('rejects duplicate port in ports_mappings', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/applications/{$this->application->uuid}", [
            'ports_mappings' => '8080:80,8080:90',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ports_mappings']);
    });
});

describe('DELETE /api/v1/applications/{uuid} - Delete application', function () {
    test('deletes application successfully', function () {
        $uuid = $this->application->uuid;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->deleteJson("/api/v1/applications/{$uuid}");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Application deletion request queued.']);

        // Verify DeleteResourceJob was dispatched
        Queue::assertPushed(\App\Jobs\DeleteResourceJob::class);
    });

    test('returns 404 for non-existent application', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->deleteJson('/api/v1/applications/non-existent-uuid');

        $response->assertStatus(404);
    });

    test('cannot delete application from another team', function () {
        // Create another team with application
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnvironment = Environment::factory()->create(['project_id' => $otherProject->id]);
        $otherApplication = Application::factory()->create([
            'environment_id' => $otherEnvironment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->deleteJson("/api/v1/applications/{$otherApplication->uuid}");

        $response->assertStatus(404);
    });

    test('accepts query parameters for delete options', function () {
        $uuid = $this->application->uuid;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->deleteJson("/api/v1/applications/{$uuid}?delete_volumes=true&delete_configurations=false");

        $response->assertStatus(200);
        Queue::assertPushed(\App\Jobs\DeleteResourceJob::class);
    });
});

describe('Domain validation', function () {
    beforeEach(function () {
        // Set server proxy to traefik so isProxyShouldRun() returns true.
        // proxy is a SchemalessAttributes column, so set attributes directly.
        $this->server->proxy->type = 'traefik';
        $this->server->proxy->status = 'running';
        $this->server->save();
    });

    test('rejects invalid domain format', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/applications/{$this->application->uuid}", [
            'domains' => 'invalid-domain-without-protocol',
        ]);

        $response->assertStatus(422);
    });

    test('accepts valid domain format', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/applications/{$this->application->uuid}", [
            'domains' => 'https://example.com',
        ]);

        // May return 200 or 409 depending on domain availability
        expect($response->status())->toBeIn([200, 409]);
    });

    test('accepts multiple domains', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/applications/{$this->application->uuid}", [
            'domains' => 'https://example1.com,https://example2.com',
        ]);

        expect($response->status())->toBeIn([200, 409]);
    });

    test('handles domain conflict detection', function () {
        // Create another application with a domain
        $app2 = Application::factory()->create([
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'fqdn' => 'https://conflict.com',
        ]);

        // Try to use the same domain on first application
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/applications/{$this->application->uuid}", [
            'domains' => 'https://conflict.com',
        ]);

        // Should return 409 conflict
        expect($response->status())->toBe(409);
        $response->assertJsonStructure(['message', 'conflicts']);
    });

    test('allows domain override with force flag', function () {
        // Create another application with a domain
        $app2 = Application::factory()->create([
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'fqdn' => 'https://conflict.com',
        ]);

        // Try to use the same domain with force flag
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/applications/{$this->application->uuid}", [
            'domains' => 'https://conflict.com',
            'force_domain_override' => true,
        ]);

        $response->assertStatus(200);
    });
});
