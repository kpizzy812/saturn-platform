<?php

use App\Actions\Database\RestartDatabase;
use App\Actions\Database\StartDatabase;
use App\Actions\Database\StopDatabase;
use App\Models\Environment;
use App\Models\InstanceSettings;
use App\Models\PrivateKey;
use App\Models\Project;
use App\Models\Server;
use App\Models\ServerSetting;
use App\Models\StandaloneDocker;
use App\Models\StandalonePostgresql;
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
    ServerSetting::firstOrCreate(['server_id' => $this->server->id]);

    $this->destination = StandaloneDocker::create([
        'uuid' => (string) new Cuid2,
        'name' => 'test-destination',
        'server_id' => $this->server->id,
        'network' => 'test-network',
    ]);

    // Create a PostgreSQL database for testing
    $this->database = StandalonePostgresql::create([
        'uuid' => (string) new Cuid2,
        'name' => 'test-db',
        'environment_id' => $this->environment->id,
        'destination_id' => $this->destination->id,
        'destination_type' => StandaloneDocker::class,
        'postgres_user' => 'test',
        'postgres_password' => 'test',
        'postgres_db' => 'test',
        'status' => 'stopped',
    ]);
});

// ---------------------------------------------------------------------------
// Authentication
// ---------------------------------------------------------------------------

describe('Authentication', function () {
    it('rejects start request without authentication token', function () {
        $response = $this->getJson("/api/v1/databases/{$this->database->uuid}/start");

        $response->assertStatus(401);
    });

    it('rejects stop request without authentication token', function () {
        $response = $this->getJson("/api/v1/databases/{$this->database->uuid}/stop");

        $response->assertStatus(401);
    });

    it('rejects restart request without authentication token', function () {
        $response = $this->getJson("/api/v1/databases/{$this->database->uuid}/restart");

        $response->assertStatus(401);
    });

    it('rejects request with an invalid bearer token', function () {
        $response = $this->withHeader('Authorization', 'Bearer totally-invalid-token')
            ->getJson("/api/v1/databases/{$this->database->uuid}/start");

        $response->assertStatus(401);
    });
});

// ---------------------------------------------------------------------------
// GET|POST /api/v1/databases/{uuid}/start
// ---------------------------------------------------------------------------

describe('GET /api/v1/databases/{uuid}/start - Start database', function () {
    it('queues start request for a stopped database and returns 200', function () {
        // Database is already 'stopped' from beforeEach — start must be allowed.
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson("/api/v1/databases/{$this->database->uuid}/start");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Database starting request queued.']);
    });

    it('dispatches StartDatabase action when database is stopped', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson("/api/v1/databases/{$this->database->uuid}/start");

        $response->assertStatus(200);
        // laravel-actions wraps actions in a JobDecorator, so use the action's own assertPushed()
        StartDatabase::assertPushed();
    });

    it('also accepts POST for start', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson("/api/v1/databases/{$this->database->uuid}/start");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Database starting request queued.']);
    });

    it('returns 400 when database is already running', function () {
        // Directly update status to bypass $fillable restriction — status is system-managed.
        \Illuminate\Support\Facades\DB::table('standalone_postgresqls')
            ->where('id', $this->database->id)
            ->update(['status' => 'running:healthy']);

        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson("/api/v1/databases/{$this->database->uuid}/start");

        $response->assertStatus(400);
        $response->assertJson(['message' => 'Database is already running.']);
    });

    it('returns 400 when database status contains running substring', function () {
        // Variant status string — controller checks str()->contains('running').
        \Illuminate\Support\Facades\DB::table('standalone_postgresqls')
            ->where('id', $this->database->id)
            ->update(['status' => 'running:unhealthy']);

        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson("/api/v1/databases/{$this->database->uuid}/start");

        $response->assertStatus(400);
        $response->assertJson(['message' => 'Database is already running.']);
    });

    it('returns 404 for a non-existent database UUID', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson('/api/v1/databases/non-existent-uuid/start');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Database not found.']);
    });
});

// ---------------------------------------------------------------------------
// GET|POST /api/v1/databases/{uuid}/stop
// ---------------------------------------------------------------------------

describe('GET /api/v1/databases/{uuid}/stop - Stop database', function () {
    it('queues stop request for a running database and returns 200', function () {
        \Illuminate\Support\Facades\DB::table('standalone_postgresqls')
            ->where('id', $this->database->id)
            ->update(['status' => 'running:healthy']);

        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson("/api/v1/databases/{$this->database->uuid}/stop");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Database stopping request queued.']);
    });

    it('dispatches StopDatabase action when database is running', function () {
        \Illuminate\Support\Facades\DB::table('standalone_postgresqls')
            ->where('id', $this->database->id)
            ->update(['status' => 'running:healthy']);

        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson("/api/v1/databases/{$this->database->uuid}/stop");

        $response->assertStatus(200);
        // laravel-actions wraps actions in a JobDecorator, so use the action's own assertPushed()
        StopDatabase::assertPushed();
    });

    it('also accepts POST for stop', function () {
        \Illuminate\Support\Facades\DB::table('standalone_postgresqls')
            ->where('id', $this->database->id)
            ->update(['status' => 'running:healthy']);

        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson("/api/v1/databases/{$this->database->uuid}/stop");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Database stopping request queued.']);
    });

    it('returns 400 when database is already stopped', function () {
        // Database is already 'stopped' from beforeEach — stop must be rejected.
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson("/api/v1/databases/{$this->database->uuid}/stop");

        $response->assertStatus(400);
        $response->assertJson(['message' => 'Database is already stopped.']);
    });

    it('returns 400 when database status is exited', function () {
        // Controller also treats 'exited' as already stopped.
        \Illuminate\Support\Facades\DB::table('standalone_postgresqls')
            ->where('id', $this->database->id)
            ->update(['status' => 'exited:unhealthy']);

        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson("/api/v1/databases/{$this->database->uuid}/stop");

        $response->assertStatus(400);
        $response->assertJson(['message' => 'Database is already stopped.']);
    });

    it('returns 404 for a non-existent database UUID', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson('/api/v1/databases/non-existent-uuid/stop');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Database not found.']);
    });
});

// ---------------------------------------------------------------------------
// GET|POST /api/v1/databases/{uuid}/restart
// ---------------------------------------------------------------------------

describe('GET /api/v1/databases/{uuid}/restart - Restart database', function () {
    it('queues restart request and returns 200', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson("/api/v1/databases/{$this->database->uuid}/restart");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Database restarting request queued.']);
    });

    it('dispatches RestartDatabase action', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson("/api/v1/databases/{$this->database->uuid}/restart");

        $response->assertStatus(200);
        // laravel-actions wraps actions in a JobDecorator, so use the action's own assertPushed()
        RestartDatabase::assertPushed();
    });

    it('also accepts POST for restart', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson("/api/v1/databases/{$this->database->uuid}/restart");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Database restarting request queued.']);
    });

    it('restarts database regardless of running status', function () {
        \Illuminate\Support\Facades\DB::table('standalone_postgresqls')
            ->where('id', $this->database->id)
            ->update(['status' => 'running:healthy']);

        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson("/api/v1/databases/{$this->database->uuid}/restart");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Database restarting request queued.']);
    });

    it('restarts database regardless of stopped status', function () {
        // Database is already 'stopped' from beforeEach — restart must still be allowed.
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson("/api/v1/databases/{$this->database->uuid}/restart");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Database restarting request queued.']);
    });

    it('returns 404 for a non-existent database UUID', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson('/api/v1/databases/non-existent-uuid/restart');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Database not found.']);
    });
});

// ---------------------------------------------------------------------------
// Multi-tenancy isolation
// ---------------------------------------------------------------------------

describe('Multi-tenancy isolation', function () {
    it('returns 404 when trying to start another teams database', function () {
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

        $otherDestination = StandaloneDocker::create([
            'uuid' => (string) new Cuid2,
            'name' => 'other-destination',
            'server_id' => $otherServer->id,
            'network' => 'other-network',
        ]);

        $otherDatabase = StandalonePostgresql::create([
            'uuid' => (string) new Cuid2,
            'name' => 'other-db',
            'environment_id' => $otherEnvironment->id,
            'destination_id' => $otherDestination->id,
            'destination_type' => StandaloneDocker::class,
            'postgres_user' => 'other',
            'postgres_password' => 'other',
            'postgres_db' => 'other',
            'status' => 'stopped',
        ]);

        // Authenticated as Team A — queryDatabaseByUuidWithinTeam must not return Team B's database.
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson("/api/v1/databases/{$otherDatabase->uuid}/start");

        $response->assertStatus(404);
    });

    it('returns 404 when trying to stop another teams database', function () {
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

        $otherDestination = StandaloneDocker::create([
            'uuid' => (string) new Cuid2,
            'name' => 'other-destination',
            'server_id' => $otherServer->id,
            'network' => 'other-network',
        ]);

        $otherDatabase = StandalonePostgresql::create([
            'uuid' => (string) new Cuid2,
            'name' => 'other-db',
            'environment_id' => $otherEnvironment->id,
            'destination_id' => $otherDestination->id,
            'destination_type' => StandaloneDocker::class,
            'postgres_user' => 'other',
            'postgres_password' => 'other',
            'postgres_db' => 'other',
            'status' => 'running:healthy',
        ]);

        // Authenticated as Team A — queryDatabaseByUuidWithinTeam must not return Team B's database.
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson("/api/v1/databases/{$otherDatabase->uuid}/stop");

        $response->assertStatus(404);
    });

    it('returns 404 when trying to restart another teams database', function () {
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

        $otherDestination = StandaloneDocker::create([
            'uuid' => (string) new Cuid2,
            'name' => 'other-destination',
            'server_id' => $otherServer->id,
            'network' => 'other-network',
        ]);

        $otherDatabase = StandalonePostgresql::create([
            'uuid' => (string) new Cuid2,
            'name' => 'other-db',
            'environment_id' => $otherEnvironment->id,
            'destination_id' => $otherDestination->id,
            'destination_type' => StandaloneDocker::class,
            'postgres_user' => 'other',
            'postgres_password' => 'other',
            'postgres_db' => 'other',
            'status' => 'stopped',
        ]);

        // Authenticated as Team A — queryDatabaseByUuidWithinTeam must not return Team B's database.
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson("/api/v1/databases/{$otherDatabase->uuid}/restart");

        $response->assertStatus(404);
    });
});
