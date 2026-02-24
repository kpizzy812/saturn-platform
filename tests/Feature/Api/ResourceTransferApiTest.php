<?php

use App\Models\Environment;
use App\Models\InstanceSettings;
use App\Models\PrivateKey;
use App\Models\Project;
use App\Models\ResourceTransfer;
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
    Cache::flush();
    try {
        Cache::store('redis')->flush();
    } catch (\Throwable $e) {
    }

    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();
    $this->team->members()->attach($this->user->id, ['role' => 'owner']);

    session(['currentTeam' => $this->team]);
    $this->token = $this->user->createToken('test-token', ['*']);
    $this->bearerToken = $this->token->plainTextToken;

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

    $this->destination = StandaloneDocker::withoutEvents(function () {
        return StandaloneDocker::create([
            'uuid' => (string) new Cuid2,
            'name' => 'default',
            'network' => 'saturn',
            'server_id' => $this->server->id,
        ]);
    });
});

// ─── INDEX ───────────────────────────────────────────────────────────

test('list transfers returns empty when none exist', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->getJson('/api/v1/transfers');

    $response->assertOk()
        ->assertJsonPath('total', 0);
});

test('list transfers returns team transfers', function () {
    // Create a source database for the transfer
    $database = StandalonePostgresql::withoutEvents(function () {
        return StandalonePostgresql::create([
            'uuid' => (string) new Cuid2,
            'name' => 'test-pg',
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'postgres_user' => 'test',
            'postgres_password' => 'test',
            'postgres_db' => 'testdb',
        ]);
    });

    ResourceTransfer::create([
        'team_id' => $this->team->id,
        'user_id' => $this->user->id,
        'source_type' => StandalonePostgresql::class,
        'source_id' => $database->id,
        'target_environment_id' => $this->environment->id,
        'target_server_id' => $this->server->id,
        'transfer_mode' => ResourceTransfer::MODE_CLONE,
        'status' => ResourceTransfer::STATUS_PENDING,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->getJson('/api/v1/transfers');

    $response->assertOk()
        ->assertJsonPath('total', 1);
});

test('list transfers does not return other team transfers', function () {
    $otherTeam = Team::factory()->create();
    $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
    $otherEnv = Environment::factory()->create(['project_id' => $otherProject->id]);

    $otherKey = PrivateKey::factory()->create(['team_id' => $otherTeam->id]);
    $otherServer = Server::withoutEvents(function () use ($otherTeam, $otherKey) {
        return Server::factory()->create([
            'uuid' => (string) new Cuid2,
            'team_id' => $otherTeam->id,
            'private_key_id' => $otherKey->id,
        ]);
    });
    ServerSetting::firstOrCreate(['server_id' => $otherServer->id]);

    ResourceTransfer::create([
        'team_id' => $otherTeam->id,
        'user_id' => $this->user->id,
        'source_type' => StandalonePostgresql::class,
        'source_id' => 1,
        'target_environment_id' => $otherEnv->id,
        'target_server_id' => $otherServer->id,
        'transfer_mode' => ResourceTransfer::MODE_CLONE,
        'status' => ResourceTransfer::STATUS_PENDING,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->getJson('/api/v1/transfers');

    $response->assertOk()
        ->assertJsonPath('total', 0);
});

test('list transfers filters by status', function () {
    $database = StandalonePostgresql::withoutEvents(function () {
        return StandalonePostgresql::create([
            'uuid' => (string) new Cuid2,
            'name' => 'test-pg',
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'postgres_user' => 'test',
            'postgres_password' => 'test',
            'postgres_db' => 'testdb',
        ]);
    });

    ResourceTransfer::create([
        'team_id' => $this->team->id,
        'user_id' => $this->user->id,
        'source_type' => StandalonePostgresql::class,
        'source_id' => $database->id,
        'target_environment_id' => $this->environment->id,
        'target_server_id' => $this->server->id,
        'transfer_mode' => ResourceTransfer::MODE_CLONE,
        'status' => ResourceTransfer::STATUS_PENDING,
    ]);

    ResourceTransfer::create([
        'team_id' => $this->team->id,
        'user_id' => $this->user->id,
        'source_type' => StandalonePostgresql::class,
        'source_id' => $database->id,
        'target_environment_id' => $this->environment->id,
        'target_server_id' => $this->server->id,
        'transfer_mode' => ResourceTransfer::MODE_CLONE,
        'status' => ResourceTransfer::STATUS_COMPLETED,
        'completed_at' => now(),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->getJson('/api/v1/transfers?status=pending');

    $response->assertOk()
        ->assertJsonPath('total', 1);
});

// ─── SHOW ────────────────────────────────────────────────────────────

test('show transfer returns details', function () {
    $database = StandalonePostgresql::withoutEvents(function () {
        return StandalonePostgresql::create([
            'uuid' => (string) new Cuid2,
            'name' => 'test-pg',
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'postgres_user' => 'test',
            'postgres_password' => 'test',
            'postgres_db' => 'testdb',
        ]);
    });

    $transfer = ResourceTransfer::create([
        'team_id' => $this->team->id,
        'user_id' => $this->user->id,
        'source_type' => StandalonePostgresql::class,
        'source_id' => $database->id,
        'target_environment_id' => $this->environment->id,
        'target_server_id' => $this->server->id,
        'transfer_mode' => ResourceTransfer::MODE_CLONE,
        'status' => ResourceTransfer::STATUS_PENDING,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->getJson("/api/v1/transfers/{$transfer->uuid}");

    $response->assertOk()
        ->assertJsonPath('uuid', $transfer->uuid)
        ->assertJsonPath('status', 'pending')
        ->assertJsonPath('transfer_mode', 'clone');
});

test('show transfer returns 404 for other team', function () {
    $otherTeam = Team::factory()->create();
    $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
    $otherEnv = Environment::factory()->create(['project_id' => $otherProject->id]);

    $otherKey = PrivateKey::factory()->create(['team_id' => $otherTeam->id]);
    $otherServer = Server::withoutEvents(function () use ($otherTeam, $otherKey) {
        return Server::factory()->create([
            'uuid' => (string) new Cuid2,
            'team_id' => $otherTeam->id,
            'private_key_id' => $otherKey->id,
        ]);
    });
    ServerSetting::firstOrCreate(['server_id' => $otherServer->id]);

    $transfer = ResourceTransfer::create([
        'team_id' => $otherTeam->id,
        'user_id' => $this->user->id,
        'source_type' => StandalonePostgresql::class,
        'source_id' => 1,
        'target_environment_id' => $otherEnv->id,
        'target_server_id' => $otherServer->id,
        'transfer_mode' => ResourceTransfer::MODE_CLONE,
        'status' => ResourceTransfer::STATUS_PENDING,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->getJson("/api/v1/transfers/{$transfer->uuid}");

    $response->assertNotFound();
});

test('show transfer returns 404 for nonexistent uuid', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->getJson('/api/v1/transfers/nonexistent-uuid');

    $response->assertNotFound();
});

// ─── CANCEL ──────────────────────────────────────────────────────────

test('cancel pending transfer', function () {
    $database = StandalonePostgresql::withoutEvents(function () {
        return StandalonePostgresql::create([
            'uuid' => (string) new Cuid2,
            'name' => 'test-pg',
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'postgres_user' => 'test',
            'postgres_password' => 'test',
            'postgres_db' => 'testdb',
        ]);
    });

    $transfer = ResourceTransfer::create([
        'team_id' => $this->team->id,
        'user_id' => $this->user->id,
        'source_type' => StandalonePostgresql::class,
        'source_id' => $database->id,
        'target_environment_id' => $this->environment->id,
        'target_server_id' => $this->server->id,
        'transfer_mode' => ResourceTransfer::MODE_CLONE,
        'status' => ResourceTransfer::STATUS_PENDING,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->postJson("/api/v1/transfers/{$transfer->uuid}/cancel");

    $response->assertOk()
        ->assertJsonPath('message', 'Transfer cancelled.');

    $transfer->refresh();
    expect($transfer->status)->toBe(ResourceTransfer::STATUS_CANCELLED);
});

test('cannot cancel completed transfer', function () {
    $database = StandalonePostgresql::withoutEvents(function () {
        return StandalonePostgresql::create([
            'uuid' => (string) new Cuid2,
            'name' => 'test-pg',
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'postgres_user' => 'test',
            'postgres_password' => 'test',
            'postgres_db' => 'testdb',
        ]);
    });

    $transfer = ResourceTransfer::create([
        'team_id' => $this->team->id,
        'user_id' => $this->user->id,
        'source_type' => StandalonePostgresql::class,
        'source_id' => $database->id,
        'target_environment_id' => $this->environment->id,
        'target_server_id' => $this->server->id,
        'transfer_mode' => ResourceTransfer::MODE_CLONE,
        'status' => ResourceTransfer::STATUS_COMPLETED,
        'completed_at' => now(),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->postJson("/api/v1/transfers/{$transfer->uuid}/cancel");

    $response->assertStatus(400);
});

test('cancel transfer returns 404 for other team', function () {
    $otherTeam = Team::factory()->create();
    $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
    $otherEnv = Environment::factory()->create(['project_id' => $otherProject->id]);

    $otherKey = PrivateKey::factory()->create(['team_id' => $otherTeam->id]);
    $otherServer = Server::withoutEvents(function () use ($otherTeam, $otherKey) {
        return Server::factory()->create([
            'uuid' => (string) new Cuid2,
            'team_id' => $otherTeam->id,
            'private_key_id' => $otherKey->id,
        ]);
    });
    ServerSetting::firstOrCreate(['server_id' => $otherServer->id]);

    $transfer = ResourceTransfer::create([
        'team_id' => $otherTeam->id,
        'user_id' => $this->user->id,
        'source_type' => StandalonePostgresql::class,
        'source_id' => 1,
        'target_environment_id' => $otherEnv->id,
        'target_server_id' => $otherServer->id,
        'transfer_mode' => ResourceTransfer::MODE_CLONE,
        'status' => ResourceTransfer::STATUS_PENDING,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->postJson("/api/v1/transfers/{$transfer->uuid}/cancel");

    $response->assertNotFound();
});

// ─── STORE VALIDATION ────────────────────────────────────────────────

test('create transfer validates required fields', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->postJson('/api/v1/transfers', []);

    $response->assertStatus(422)
        ->assertJsonStructure(['errors']);
});

test('create transfer returns 404 for nonexistent source database', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
        ->postJson('/api/v1/transfers', [
            'source_uuid' => 'nonexistent-uuid',
            'source_type' => 'standalone-postgresql',
            'target_environment_uuid' => $this->environment->uuid,
            'target_server_uuid' => $this->server->uuid,
        ]);

    $response->assertNotFound();
});

// ─── AUTH ────────────────────────────────────────────────────────────

test('unauthenticated request returns 401', function () {
    $response = $this->getJson('/api/v1/transfers');
    $response->assertUnauthorized();
});

test('read-only token can list transfers', function () {
    $readToken = $this->user->createToken('read-token', ['read']);

    $response = $this->withHeader('Authorization', "Bearer {$readToken->plainTextToken}")
        ->getJson('/api/v1/transfers');

    $response->assertOk();
});

test('read-only token cannot cancel transfer', function () {
    $readToken = $this->user->createToken('read-token', ['read']);

    $database = StandalonePostgresql::withoutEvents(function () {
        return StandalonePostgresql::create([
            'uuid' => (string) new Cuid2,
            'name' => 'test-pg',
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'postgres_user' => 'test',
            'postgres_password' => 'test',
            'postgres_db' => 'testdb',
        ]);
    });

    $transfer = ResourceTransfer::create([
        'team_id' => $this->team->id,
        'user_id' => $this->user->id,
        'source_type' => StandalonePostgresql::class,
        'source_id' => $database->id,
        'target_environment_id' => $this->environment->id,
        'target_server_id' => $this->server->id,
        'transfer_mode' => ResourceTransfer::MODE_CLONE,
        'status' => ResourceTransfer::STATUS_PENDING,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$readToken->plainTextToken}")
        ->postJson("/api/v1/transfers/{$transfer->uuid}/cancel");

    $response->assertStatus(403);
});
