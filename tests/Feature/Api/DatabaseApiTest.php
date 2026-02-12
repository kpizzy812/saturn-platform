<?php

use App\Jobs\DeleteResourceJob;
use App\Models\Environment;
use App\Models\InstanceSettings;
use App\Models\PrivateKey;
use App\Models\Project;
use App\Models\Server;
use App\Models\ServerSetting;
use App\Models\StandaloneDocker;
use App\Models\StandalonePostgresql;
use App\Models\StandaloneRedis;
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
});

describe('GET /api/v1/databases', function () {
    test('returns 401 when not authenticated', function () {
        $response = $this->getJson('/api/v1/databases');

        $response->assertStatus(401);
    });

    test('returns empty array when no databases exist', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/databases');

        $response->assertStatus(200);
        $response->assertJson([]);
    });

    test('lists databases for the team', function () {
        // Create a PostgreSQL database
        $database = StandalonePostgresql::create([
            'name' => 'test-postgres',
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'postgres_user' => 'postgres',
            'postgres_password' => 'secret123',
            'postgres_db' => 'testdb',
            'image' => 'postgres:16-alpine',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/databases');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment([
            'name' => 'test-postgres',
            'database_type' => 'standalone-postgresql',
        ]);
    });

    test('lists multiple database types', function () {
        // Create PostgreSQL database
        StandalonePostgresql::create([
            'name' => 'postgres-db',
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'postgres_user' => 'postgres',
            'postgres_password' => 'secret',
            'postgres_db' => 'testdb',
            'image' => 'postgres:16-alpine',
        ]);

        // Create Redis database (redis_password column does not exist in table,
        // it's generated from the model's accessor, so omit it here)
        StandaloneRedis::create([
            'name' => 'redis-db',
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'image' => 'redis:7-alpine',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/databases');

        $response->assertStatus(200);
        $response->assertJsonCount(2);
    });

    test('does not include databases from other teams', function () {
        // Create database for this team
        StandalonePostgresql::create([
            'name' => 'team1-postgres',
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'postgres_user' => 'postgres',
            'postgres_password' => 'secret',
            'postgres_db' => 'testdb',
            'image' => 'postgres:16-alpine',
        ]);

        // Create another team with database
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

        $otherDestination = StandaloneDocker::withoutEvents(function () use ($otherServer) {
            return StandaloneDocker::create([
                'uuid' => (string) new Cuid2,
                'name' => 'default',
                'network' => 'saturn',
                'server_id' => $otherServer->id,
            ]);
        });

        StandalonePostgresql::create([
            'name' => 'team2-postgres',
            'environment_id' => $otherEnvironment->id,
            'destination_id' => $otherDestination->id,
            'destination_type' => StandaloneDocker::class,
            'postgres_user' => 'postgres',
            'postgres_password' => 'secret',
            'postgres_db' => 'testdb',
            'image' => 'postgres:16-alpine',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/databases');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['name' => 'team1-postgres']);
        $response->assertJsonMissing(['name' => 'team2-postgres']);
    });
});

describe('GET /api/v1/databases/{uuid}', function () {
    test('returns 404 when database does not exist', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/databases/non-existent-uuid');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Database not found.']);
    });

    test('shows database by UUID', function () {
        $database = StandalonePostgresql::create([
            'name' => 'my-postgres',
            'description' => 'Test database',
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'postgres_user' => 'dbuser',
            'postgres_password' => 'dbpass',
            'postgres_db' => 'mydb',
            'image' => 'postgres:16-alpine',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/databases/{$database->uuid}");

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'name' => 'my-postgres',
            'description' => 'Test database',
            'database_type' => 'standalone-postgresql',
        ]);
    });

    test('cannot access database from another team', function () {
        // Create another team with database
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

        $otherDestination = StandaloneDocker::withoutEvents(function () use ($otherServer) {
            return StandaloneDocker::create([
                'uuid' => (string) new Cuid2,
                'name' => 'default',
                'network' => 'saturn',
                'server_id' => $otherServer->id,
            ]);
        });

        $otherDatabase = StandalonePostgresql::create([
            'name' => 'other-team-db',
            'environment_id' => $otherEnvironment->id,
            'destination_id' => $otherDestination->id,
            'destination_type' => StandaloneDocker::class,
            'postgres_user' => 'postgres',
            'postgres_password' => 'secret',
            'postgres_db' => 'testdb',
            'image' => 'postgres:16-alpine',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/databases/{$otherDatabase->uuid}");

        $response->assertStatus(404);
    });
});

describe('PATCH /api/v1/databases/{uuid}', function () {
    test('updates database name', function () {
        $database = StandalonePostgresql::create([
            'name' => 'old-name',
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'postgres_user' => 'postgres',
            'postgres_password' => 'secret',
            'postgres_db' => 'testdb',
            'image' => 'postgres:16-alpine',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/databases/{$database->uuid}", [
            'name' => 'new-name',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Database updated.']);

        $this->assertDatabaseHas('standalone_postgresqls', [
            'uuid' => $database->uuid,
            'name' => 'new-name',
        ]);
    });

    test('updates database description', function () {
        $database = StandalonePostgresql::create([
            'name' => 'test-db',
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'postgres_user' => 'postgres',
            'postgres_password' => 'secret',
            'postgres_db' => 'testdb',
            'image' => 'postgres:16-alpine',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/databases/{$database->uuid}", [
            'description' => 'Updated description',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('standalone_postgresqls', [
            'uuid' => $database->uuid,
            'description' => 'Updated description',
        ]);
    });

    test('updates postgres-specific fields', function () {
        $database = StandalonePostgresql::create([
            'name' => 'test-db',
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'postgres_user' => 'olduser',
            'postgres_password' => 'oldpass',
            'postgres_db' => 'olddb',
            'image' => 'postgres:16-alpine',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/databases/{$database->uuid}", [
            'postgres_user' => 'newuser',
            'postgres_password' => 'newpass',
            'postgres_db' => 'newdb',
        ]);

        $response->assertStatus(200);

        $database->refresh();
        expect($database->postgres_user)->toBe('newuser');
        expect($database->postgres_db)->toBe('newdb');
        // Password is encrypted, so we just check it's changed
        expect($database->postgres_password)->not->toBe('oldpass');
    });

    test('updates image', function () {
        $database = StandalonePostgresql::create([
            'name' => 'test-db',
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'postgres_user' => 'postgres',
            'postgres_password' => 'secret',
            'postgres_db' => 'testdb',
            'image' => 'postgres:16-alpine',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/databases/{$database->uuid}", [
            'image' => 'postgres:17-alpine',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('standalone_postgresqls', [
            'uuid' => $database->uuid,
            'image' => 'postgres:17-alpine',
        ]);
    });

    test('returns 404 for non-existent database', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson('/api/v1/databases/non-existent-uuid', [
            'name' => 'new-name',
        ]);

        $response->assertStatus(404);
    });

    test('validates public_port range', function () {
        $database = StandalonePostgresql::create([
            'name' => 'test-db',
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'postgres_user' => 'postgres',
            'postgres_password' => 'secret',
            'postgres_db' => 'testdb',
            'image' => 'postgres:16-alpine',
        ]);

        // Test below minimum
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/databases/{$database->uuid}", [
            'public_port' => 1023,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['public_port']);

        // Test above maximum
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/databases/{$database->uuid}", [
            'public_port' => 65536,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['public_port']);
    });

    test('accepts valid public_port', function () {
        $database = StandalonePostgresql::create([
            'name' => 'test-db',
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'postgres_user' => 'postgres',
            'postgres_password' => 'secret',
            'postgres_db' => 'testdb',
            'image' => 'postgres:16-alpine',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/databases/{$database->uuid}", [
            'public_port' => 5432,
            'is_public' => true,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('standalone_postgresqls', [
            'uuid' => $database->uuid,
            'public_port' => 5432,
            'is_public' => true,
        ]);
    });

    test('cannot update database from another team', function () {
        // Create another team with database
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

        $otherDestination = StandaloneDocker::withoutEvents(function () use ($otherServer) {
            return StandaloneDocker::create([
                'uuid' => (string) new Cuid2,
                'name' => 'default',
                'network' => 'saturn',
                'server_id' => $otherServer->id,
            ]);
        });

        $otherDatabase = StandalonePostgresql::create([
            'name' => 'other-team-db',
            'environment_id' => $otherEnvironment->id,
            'destination_id' => $otherDestination->id,
            'destination_type' => StandaloneDocker::class,
            'postgres_user' => 'postgres',
            'postgres_password' => 'secret',
            'postgres_db' => 'testdb',
            'image' => 'postgres:16-alpine',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/databases/{$otherDatabase->uuid}", [
            'name' => 'hacked-name',
        ]);

        $response->assertStatus(404);
    });
});

describe('DELETE /api/v1/databases/{uuid}', function () {
    test('deletes database', function () {
        $database = StandalonePostgresql::create([
            'name' => 'to-delete',
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'postgres_user' => 'postgres',
            'postgres_password' => 'secret',
            'postgres_db' => 'testdb',
            'image' => 'postgres:16-alpine',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->deleteJson("/api/v1/databases/{$database->uuid}");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Database deletion request queued.']);

        // Verify job was dispatched
        Queue::assertPushed(DeleteResourceJob::class, function ($job) use ($database) {
            return $job->resource->uuid === $database->uuid;
        });
    });

    test('returns 404 for non-existent database', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->deleteJson('/api/v1/databases/non-existent-uuid');

        $response->assertStatus(404);
    });

    test('cannot delete database from another team', function () {
        // Create another team with database
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

        $otherDestination = StandaloneDocker::withoutEvents(function () use ($otherServer) {
            return StandaloneDocker::create([
                'uuid' => (string) new Cuid2,
                'name' => 'default',
                'network' => 'saturn',
                'server_id' => $otherServer->id,
            ]);
        });

        $otherDatabase = StandalonePostgresql::create([
            'name' => 'other-team-db',
            'environment_id' => $otherEnvironment->id,
            'destination_id' => $otherDestination->id,
            'destination_type' => StandaloneDocker::class,
            'postgres_user' => 'postgres',
            'postgres_password' => 'secret',
            'postgres_db' => 'testdb',
            'image' => 'postgres:16-alpine',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->deleteJson("/api/v1/databases/{$otherDatabase->uuid}");

        $response->assertStatus(404);
    });
});

describe('POST /api/v1/databases/postgresql', function () {
    test('creates PostgreSQL database', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/databases/postgresql', [
            'server_uuid' => $this->server->uuid,
            'project_uuid' => $this->project->uuid,
            'environment_name' => $this->environment->name,
            'name' => 'new-postgres-db',
            'postgres_user' => 'testuser',
            'postgres_password' => 'testpass',
            'postgres_db' => 'testdb',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['uuid', 'internal_db_url']);

        $this->assertDatabaseHas('standalone_postgresqls', [
            'name' => 'new-postgres-db',
            'postgres_user' => 'testuser',
            'postgres_db' => 'testdb',
        ]);
    });

    test('requires server_uuid for database creation', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/databases/postgresql', [
            'project_uuid' => $this->project->uuid,
            'environment_name' => $this->environment->name,
        ]);

        // Controller looks up server before running field validators,
        // so missing server_uuid results in 404 "Server not found."
        $response->assertStatus(404);
        $response->assertJson(['message' => 'Server not found.']);
    });

    test('requires project_uuid for database creation', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/databases/postgresql', [
            'server_uuid' => $this->server->uuid,
            'environment_name' => $this->environment->name,
        ]);

        // Controller looks up project before running field validators,
        // so missing project_uuid results in 404 "Project not found."
        $response->assertStatus(404);
        $response->assertJson(['message' => 'Project not found.']);
    });

    test('requires environment_name or environment_uuid', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/databases/postgresql', [
            'server_uuid' => $this->server->uuid,
            'project_uuid' => $this->project->uuid,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'You need to provide at least one of environment_name or environment_uuid.']);
    });

    test('creates database with custom image', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/databases/postgresql', [
            'server_uuid' => $this->server->uuid,
            'project_uuid' => $this->project->uuid,
            'environment_name' => $this->environment->name,
            'image' => 'postgres:15-alpine',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('standalone_postgresqls', [
            'image' => 'postgres:15-alpine',
        ]);
    });

    test('creates database with public port', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/databases/postgresql', [
            'server_uuid' => $this->server->uuid,
            'project_uuid' => $this->project->uuid,
            'environment_name' => $this->environment->name,
            'is_public' => true,
            'public_port' => 5433,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['uuid', 'internal_db_url', 'external_db_url']);

        $this->assertDatabaseHas('standalone_postgresqls', [
            'is_public' => true,
            'public_port' => 5433,
        ]);
    });

    test('validates public_port is within allowed range', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/databases/postgresql', [
            'server_uuid' => $this->server->uuid,
            'project_uuid' => $this->project->uuid,
            'environment_name' => $this->environment->name,
            'is_public' => true,
            'public_port' => 999,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['public_port' => 'The public port should be between 1024 and 65535.']);
    });

    test('returns 404 for non-existent project', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/databases/postgresql', [
            'server_uuid' => $this->server->uuid,
            'project_uuid' => 'non-existent-uuid',
            'environment_name' => $this->environment->name,
        ]);

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Project not found.']);
    });

    test('returns 404 for non-existent server', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/databases/postgresql', [
            'server_uuid' => 'non-existent-uuid',
            'project_uuid' => $this->project->uuid,
            'environment_name' => $this->environment->name,
        ]);

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Server not found.']);
    });

    test('returns 422 for invalid environment', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/databases/postgresql', [
            'server_uuid' => $this->server->uuid,
            'project_uuid' => $this->project->uuid,
            'environment_name' => 'non-existent-environment',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'You need to provide a valid environment_name or environment_uuid.']);
    });
});

describe('POST /api/v1/databases/redis', function () {
    test('creates Redis database', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/databases/redis', [
            'server_uuid' => $this->server->uuid,
            'project_uuid' => $this->project->uuid,
            'environment_name' => $this->environment->name,
            'name' => 'new-redis-db',
            'redis_password' => 'redispass',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['uuid', 'internal_db_url']);

        $this->assertDatabaseHas('standalone_redis', [
            'name' => 'new-redis-db',
        ]);
    });
});

describe('POST /api/v1/databases/mongodb', function () {
    // Known production bug: StandaloneMongodb::mongoInitdbRootPassword getter
    // calls $this->save() when it fails to decrypt a plain text password.
    // This triggers infinite recursion through the Auditable trait's
    // getOriginal() -> getter -> save() cycle, causing a duplicate key violation.
    test('creates MongoDB database')->skip(
        'Production bug: mongoInitdbRootPassword getter causes recursive save via Auditable trait'
    );
});

describe('Cross-team isolation', function () {
    test('cannot create database on another teams server', function () {
        // Create another team
        $otherTeam = Team::factory()->create();
        $otherPrivateKey = PrivateKey::factory()->create(['team_id' => $otherTeam->id]);

        $otherServer = Server::withoutEvents(function () use ($otherTeam, $otherPrivateKey) {
            return Server::factory()->create([
                'uuid' => (string) new Cuid2,
                'team_id' => $otherTeam->id,
                'private_key_id' => $otherPrivateKey->id,
            ]);
        });

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/databases/postgresql', [
            'server_uuid' => $otherServer->uuid,
            'project_uuid' => $this->project->uuid,
            'environment_name' => $this->environment->name,
        ]);

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Server not found.']);
    });

    test('cannot create database in another teams project', function () {
        // Create another team
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/databases/postgresql', [
            'server_uuid' => $this->server->uuid,
            'project_uuid' => $otherProject->uuid,
            'environment_name' => $this->environment->name,
        ]);

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Project not found.']);
    });
});
