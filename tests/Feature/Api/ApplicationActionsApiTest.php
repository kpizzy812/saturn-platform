<?php

use App\Actions\Application\StopApplication;
use App\Jobs\ApplicationDeploymentJob;
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
    // MUST be set before createToken() so getTeamIdFromToken() resolves correctly.
    session(['currentTeam' => $this->team]);
    $this->token = $this->user->createToken('test-token', ['*']);
    $this->bearerToken = $this->token->plainTextToken;

    // InstanceSettings id=0 MUST exist for the API to respond (checked in middleware)
    if (! InstanceSettings::first()) {
        InstanceSettings::create([
            'id' => 0,
            'is_api_enabled' => true,
        ]);
    }

    // Build infrastructure chain: project → environment
    $this->project = Project::factory()->create(['team_id' => $this->team->id]);
    $this->environment = Environment::factory()->create(['project_id' => $this->project->id]);

    // Create server without triggering boot events that perform SSH connections.
    // withoutEvents() skips BaseModel::boot() which generates uuid, so set it explicitly.
    $this->privateKey = PrivateKey::factory()->create(['team_id' => $this->team->id]);
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

    // Create a test application.
    // applications.ports_exposes is NOT NULL — must be provided.
    $this->application = Application::factory()->create([
        'uuid' => (string) new Cuid2,
        'name' => 'test-app',
        'environment_id' => $this->environment->id,
        'destination_id' => $this->destination->id,
        'destination_type' => StandaloneDocker::class,
        'ports_exposes' => '3000',
    ]);
});

// ---------------------------------------------------------------------------
// Authentication
// ---------------------------------------------------------------------------

describe('Authentication', function () {
    test('rejects start request without authentication token', function () {
        $response = $this->getJson("/api/v1/applications/{$this->application->uuid}/start");

        $response->assertStatus(401);
    });

    test('rejects start request with invalid token', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token-here',
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/applications/{$this->application->uuid}/start");

        $response->assertStatus(401);
    });
});

// ---------------------------------------------------------------------------
// GET|POST /api/v1/applications/{uuid}/start
// ---------------------------------------------------------------------------

describe('GET /api/v1/applications/{uuid}/start - Start application', function () {
    test('queues deployment for application and returns 200', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/applications/{$this->application->uuid}/start");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Deployment request queued.']);
    });

    test('response contains deployment_uuid key', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/applications/{$this->application->uuid}/start");

        $response->assertStatus(200);
        $response->assertJsonStructure(['message', 'deployment_uuid']);
    });

    test('response message is Deployment request queued.', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/applications/{$this->application->uuid}/start");

        expect($response->json('message'))->toBe('Deployment request queued.');
    });

    test('starts application with force=true and returns 200', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/applications/{$this->application->uuid}/start?force=true");

        $response->assertStatus(200);
        $response->assertJsonStructure(['message', 'deployment_uuid']);
    });

    test('starts application with instant_deploy parameter', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/applications/{$this->application->uuid}/start?instant_deploy=true");

        $response->assertStatus(200);
        // With instant_deploy=true, no_questions_asked is set and ApplicationDeploymentJob is dispatched
        Queue::assertPushed(ApplicationDeploymentJob::class);
    });

    test('also accepts POST for start', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson("/api/v1/applications/{$this->application->uuid}/start");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Deployment request queued.']);
    });

    test('returns 404 for unknown UUID', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/applications/non-existent-uuid/start');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Application not found.']);
    });
});

// ---------------------------------------------------------------------------
// GET|POST /api/v1/applications/{uuid}/stop
// ---------------------------------------------------------------------------

describe('GET /api/v1/applications/{uuid}/stop - Stop application', function () {
    test('queues stop request and returns 200', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/applications/{$this->application->uuid}/stop");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Application stopping request queued.']);
    });

    test('response message is Application stopping request queued.', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/applications/{$this->application->uuid}/stop");

        expect($response->json('message'))->toBe('Application stopping request queued.');
    });

    test('dispatches StopApplication job', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/applications/{$this->application->uuid}/stop");

        $response->assertStatus(200);
        // laravel-actions wraps actions in a JobDecorator, so use the action's own assertPushed()
        StopApplication::assertPushed();
    });

    test('also accepts POST for stop', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson("/api/v1/applications/{$this->application->uuid}/stop");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Application stopping request queued.']);
    });

    test('returns 404 for unknown UUID', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/applications/non-existent-uuid/stop');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Application not found.']);
    });
});

// ---------------------------------------------------------------------------
// GET|POST /api/v1/applications/{uuid}/restart
// ---------------------------------------------------------------------------

describe('GET /api/v1/applications/{uuid}/restart - Restart application', function () {
    test('queues restart deployment and returns 200', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/applications/{$this->application->uuid}/restart");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Restart request queued.']);
    });

    test('response message is Restart request queued.', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/applications/{$this->application->uuid}/restart");

        expect($response->json('message'))->toBe('Restart request queued.');
    });

    test('response contains deployment_uuid key', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/applications/{$this->application->uuid}/restart");

        $response->assertStatus(200);
        $response->assertJsonStructure(['message', 'deployment_uuid']);
    });

    test('also accepts POST for restart', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson("/api/v1/applications/{$this->application->uuid}/restart");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Restart request queued.']);
    });

    test('returns 404 for unknown UUID', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/applications/non-existent-uuid/restart');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Application not found.']);
    });
});

// ---------------------------------------------------------------------------
// Multi-tenancy isolation
// ---------------------------------------------------------------------------

describe('Multi-tenancy isolation', function () {
    test('Team A cannot start Team B application', function () {
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnvironment = Environment::factory()->create(['project_id' => $otherProject->id]);

        $otherPrivateKey = PrivateKey::factory()->create(['team_id' => $otherTeam->id]);
        $otherServer = Server::withoutEvents(function () use ($otherTeam, $otherPrivateKey) {
            return Server::factory()->create([
                'uuid' => (string) new Cuid2,
                'team_id' => $otherTeam->id,
                'private_key_id' => $otherPrivateKey->id,
            ]);
        });
        ServerSetting::firstOrCreate(['server_id' => $otherServer->id]);

        $otherDestination = StandaloneDocker::withoutEvents(function () use ($otherServer) {
            return StandaloneDocker::create([
                'uuid' => (string) new Cuid2,
                'name' => 'other-docker',
                'network' => 'other-network',
                'server_id' => $otherServer->id,
            ]);
        });

        $otherApplication = Application::factory()->create([
            'uuid' => (string) new Cuid2,
            'name' => 'other-app',
            'environment_id' => $otherEnvironment->id,
            'destination_id' => $otherDestination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '4000',
        ]);

        // Authenticated as Team A — should not be able to start Team B's application
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/applications/{$otherApplication->uuid}/start");

        $response->assertStatus(404);
    });

    test('Team A cannot stop Team B application', function () {
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnvironment = Environment::factory()->create(['project_id' => $otherProject->id]);

        $otherPrivateKey = PrivateKey::factory()->create(['team_id' => $otherTeam->id]);
        $otherServer = Server::withoutEvents(function () use ($otherTeam, $otherPrivateKey) {
            return Server::factory()->create([
                'uuid' => (string) new Cuid2,
                'team_id' => $otherTeam->id,
                'private_key_id' => $otherPrivateKey->id,
            ]);
        });
        ServerSetting::firstOrCreate(['server_id' => $otherServer->id]);

        $otherDestination = StandaloneDocker::withoutEvents(function () use ($otherServer) {
            return StandaloneDocker::create([
                'uuid' => (string) new Cuid2,
                'name' => 'other-docker',
                'network' => 'other-network',
                'server_id' => $otherServer->id,
            ]);
        });

        $otherApplication = Application::factory()->create([
            'uuid' => (string) new Cuid2,
            'name' => 'other-app',
            'environment_id' => $otherEnvironment->id,
            'destination_id' => $otherDestination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '4000',
        ]);

        // Authenticated as Team A — should not be able to stop Team B's application
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/applications/{$otherApplication->uuid}/stop");

        $response->assertStatus(404);
    });

    test('Team A cannot restart Team B application', function () {
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnvironment = Environment::factory()->create(['project_id' => $otherProject->id]);

        $otherPrivateKey = PrivateKey::factory()->create(['team_id' => $otherTeam->id]);
        $otherServer = Server::withoutEvents(function () use ($otherTeam, $otherPrivateKey) {
            return Server::factory()->create([
                'uuid' => (string) new Cuid2,
                'team_id' => $otherTeam->id,
                'private_key_id' => $otherPrivateKey->id,
            ]);
        });
        ServerSetting::firstOrCreate(['server_id' => $otherServer->id]);

        $otherDestination = StandaloneDocker::withoutEvents(function () use ($otherServer) {
            return StandaloneDocker::create([
                'uuid' => (string) new Cuid2,
                'name' => 'other-docker',
                'network' => 'other-network',
                'server_id' => $otherServer->id,
            ]);
        });

        $otherApplication = Application::factory()->create([
            'uuid' => (string) new Cuid2,
            'name' => 'other-app',
            'environment_id' => $otherEnvironment->id,
            'destination_id' => $otherDestination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '4000',
        ]);

        // Authenticated as Team A — should not be able to restart Team B's application
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/applications/{$otherApplication->uuid}/restart");

        $response->assertStatus(404);
    });
});
