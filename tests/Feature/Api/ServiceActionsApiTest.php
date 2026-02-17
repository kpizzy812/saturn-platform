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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

    // Create a test service. The 'status' attribute on the Service model is computed
    // from child ServiceApplication/ServiceDatabase records (not a DB column).
    // A service with no children returns 'unknown:unknown:excluded' which contains
    // neither 'running', 'stopped', nor 'exited', so all actions are allowed.
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
    test('rejects start request without authentication', function () {
        $response = $this->getJson("/api/v1/services/{$this->service->uuid}/start");
        $response->assertStatus(401);
    });

    test('rejects stop request without authentication', function () {
        $response = $this->getJson("/api/v1/services/{$this->service->uuid}/stop");
        $response->assertStatus(401);
    });

    test('rejects restart request without authentication', function () {
        $response = $this->getJson("/api/v1/services/{$this->service->uuid}/restart");
        $response->assertStatus(401);
    });
});

describe('GET /api/v1/services/{uuid}/start - Start service', function () {
    test('queues start request for service', function () {
        // A service with no child apps has 'unknown:unknown:excluded' status.
        // This does not contain 'running', so start is allowed.
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/services/{$this->service->uuid}/start");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Service starting request queued.']);

        // laravel-actions wraps actions in a JobDecorator, so use the action's own assertPushed()
        \App\Actions\Service\StartService::assertPushed();
    });

    test('also accepts POST for start', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson("/api/v1/services/{$this->service->uuid}/start");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Service starting request queued.']);
    });

    test('returns 400 when service is already running', function () {
        // Create a running ServiceApplication child to make the service compute as running.
        // Use DB::table to bypass the $fillable restriction on the 'status' field
        // (status is system-managed and excluded from $fillable for security reasons).
        DB::table('service_applications')->insert([
            'uuid' => (string) new Cuid2,
            'name' => 'app-'.uniqid(),
            'service_id' => $this->service->id,
            'status' => 'running:healthy',
            'image' => 'nginx:latest',
            'last_online_at' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/services/{$this->service->uuid}/start");

        $response->assertStatus(400);
        $response->assertJson(['message' => 'Service is already running.']);
    });

    test('returns 404 for non-existent service', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/services/non-existent-uuid/start');

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
        ])->getJson("/api/v1/services/{$otherService->uuid}/start");

        $response->assertStatus(404);
    });
});

describe('GET /api/v1/services/{uuid}/stop - Stop service', function () {
    test('queues stop request for service', function () {
        // A service with no child apps has 'unknown:unknown:excluded' status which does
        // not contain 'stopped' or 'exited', so stop is allowed to proceed.
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/services/{$this->service->uuid}/stop");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Service stopping request queued.']);

        // laravel-actions wraps actions in a JobDecorator, so use the action's own assertPushed()
        \App\Actions\Service\StopService::assertPushed();
    });

    test('also accepts POST for stop', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson("/api/v1/services/{$this->service->uuid}/stop");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Service stopping request queued.']);
    });

    test('returns 400 when service is already stopped', function () {
        // Create a stopped ServiceApplication child to make the service compute as stopped.
        // Use DB::table to bypass the $fillable restriction on the 'status' field.
        DB::table('service_applications')->insert([
            'uuid' => (string) new Cuid2,
            'name' => 'app-'.uniqid(),
            'service_id' => $this->service->id,
            'status' => 'stopped:unhealthy',
            'image' => 'nginx:latest',
            'last_online_at' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/services/{$this->service->uuid}/stop");

        $response->assertStatus(400);
        $response->assertJson(['message' => 'Service is already stopped.']);
    });

    test('returns 400 when service status is exited', function () {
        // Create a exited ServiceApplication child to make the service compute as exited.
        // Use DB::table to bypass the $fillable restriction on the 'status' field.
        DB::table('service_applications')->insert([
            'uuid' => (string) new Cuid2,
            'name' => 'app-'.uniqid(),
            'service_id' => $this->service->id,
            'status' => 'exited:unhealthy',
            'image' => 'nginx:latest',
            'last_online_at' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/services/{$this->service->uuid}/stop");

        $response->assertStatus(400);
        $response->assertJson(['message' => 'Service is already stopped.']);
    });

    test('returns 404 for non-existent service', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/services/non-existent-uuid/stop');

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
        ])->getJson("/api/v1/services/{$otherService->uuid}/stop");

        $response->assertStatus(404);
    });
});

describe('GET /api/v1/services/{uuid}/restart - Restart service', function () {
    test('queues restart request for service', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/services/{$this->service->uuid}/restart");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Service restarting request queued.']);

        // laravel-actions wraps actions in a JobDecorator, so use the action's own assertPushed()
        \App\Actions\Service\RestartService::assertPushed();
    });

    test('also accepts POST for restart', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson("/api/v1/services/{$this->service->uuid}/restart");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Service restarting request queued.']);
    });

    test('restart dispatches with latest=true when query param is set', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/services/{$this->service->uuid}/restart?latest=true");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Service restarting request queued.']);

        // laravel-actions wraps actions in a JobDecorator, so use the action's own assertPushed()
        \App\Actions\Service\RestartService::assertPushed();
    });

    test('restart works regardless of service status', function () {
        // Create a running child to set the service status to running.
        // Use DB::table to bypass the $fillable restriction on the 'status' field.
        DB::table('service_applications')->insert([
            'uuid' => (string) new Cuid2,
            'name' => 'app-'.uniqid(),
            'service_id' => $this->service->id,
            'status' => 'running:healthy',
            'image' => 'nginx:latest',
            'last_online_at' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/services/{$this->service->uuid}/restart");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Service restarting request queued.']);
    });

    test('returns 404 for non-existent service', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/services/non-existent-uuid/restart');

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
        ])->getJson("/api/v1/services/{$otherService->uuid}/restart");

        $response->assertStatus(404);
    });
});
