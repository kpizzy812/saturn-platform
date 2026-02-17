<?php

use App\Models\Environment;
use App\Models\InstanceSettings;
use App\Models\PrivateKey;
use App\Models\Project;
use App\Models\ScheduledDatabaseBackup;
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

    // Create a PostgreSQL database for backup tests
    $this->database = StandalonePostgresql::create([
        'name' => 'test-postgres',
        'environment_id' => $this->environment->id,
        'destination_id' => $this->destination->id,
        'destination_type' => StandaloneDocker::class,
        'postgres_user' => 'postgres',
        'postgres_password' => 'secret123',
        'postgres_db' => 'testdb',
        'image' => 'postgres:16-alpine',
    ]);
});

describe('Authentication', function () {
    test('rejects list backups request without authentication', function () {
        $response = $this->getJson("/api/v1/databases/{$this->database->uuid}/backups");
        $response->assertStatus(401);
    });

    test('rejects create backup request without authentication', function () {
        $response = $this->postJson("/api/v1/databases/{$this->database->uuid}/backups", [
            'frequency' => 'daily',
        ]);
        $response->assertStatus(401);
    });
});

describe('GET /api/v1/databases/{uuid}/backups - List backups', function () {
    test('returns empty array when database has no backups', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/databases/{$this->database->uuid}/backups");

        $response->assertStatus(200);
        $response->assertJson([]);
    });

    test('returns list of backup configurations for the database', function () {
        ScheduledDatabaseBackup::create([
            'frequency' => 'daily',
            'enabled' => true,
            'save_s3' => false,
            'database_id' => $this->database->id,
            'database_type' => StandalonePostgresql::class,
            'team_id' => $this->team->id,
            'databases_to_backup' => 'testdb',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/databases/{$this->database->uuid}/backups");

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['frequency' => 'daily']);
    });

    test('returns 404 for non-existent database', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/databases/non-existent-uuid/backups');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Database not found.']);
    });

    test('cannot access backups from another teams database', function () {
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
        ])->getJson("/api/v1/databases/{$otherDatabase->uuid}/backups");

        $response->assertStatus(404);
    });
});

describe('POST /api/v1/databases/{uuid}/backups - Create backup schedule', function () {
    test('creates a backup configuration successfully', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson("/api/v1/databases/{$this->database->uuid}/backups", [
            'frequency' => 'daily',
            'enabled' => true,
            'save_s3' => false,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['uuid', 'message']);
        $response->assertJson(['message' => 'Backup configuration created successfully.']);

        $this->assertDatabaseHas('scheduled_database_backups', [
            'database_id' => $this->database->id,
            'frequency' => 'daily',
            'enabled' => true,
        ]);
    });

    test('creates backup with hourly frequency', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson("/api/v1/databases/{$this->database->uuid}/backups", [
            'frequency' => 'hourly',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('scheduled_database_backups', [
            'database_id' => $this->database->id,
            'frequency' => 'hourly',
        ]);
    });

    test('creates backup with cron expression', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson("/api/v1/databases/{$this->database->uuid}/backups", [
            'frequency' => '0 2 * * *',
        ]);

        $response->assertStatus(201);
    });

    test('returns 422 when frequency is missing', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson("/api/v1/databases/{$this->database->uuid}/backups", [
            'enabled' => true,
        ]);

        $response->assertStatus(422);
    });

    test('returns 422 when frequency is invalid cron expression', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson("/api/v1/databases/{$this->database->uuid}/backups", [
            'frequency' => 'not-a-valid-cron-or-preset',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'errors' => [
                'frequency' => ['Invalid cron expression or frequency format.'],
            ],
        ]);
    });

    test('returns 422 when save_s3 is true but s3_storage_uuid is missing', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson("/api/v1/databases/{$this->database->uuid}/backups", [
            'frequency' => 'daily',
            'save_s3' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['s3_storage_uuid' => ['The s3_storage_uuid field is required when save_s3 is true.']]);
    });

    test('returns 422 for unknown extra fields', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson("/api/v1/databases/{$this->database->uuid}/backups", [
            'frequency' => 'daily',
            'unknown_field' => 'some_value',
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['unknown_field']]);
    });

    test('returns 404 for non-existent database', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/databases/non-existent-uuid/backups', [
            'frequency' => 'daily',
        ]);

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Database not found.']);
    });

    test('creates backup with retention settings', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson("/api/v1/databases/{$this->database->uuid}/backups", [
            'frequency' => 'weekly',
            'database_backup_retention_amount_locally' => 5,
            'database_backup_retention_days_locally' => 30,
        ]);

        $response->assertStatus(201);
    });

    test('dispatches backup job when backup_now is true', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson("/api/v1/databases/{$this->database->uuid}/backups", [
            'frequency' => 'daily',
            'backup_now' => true,
        ]);

        $response->assertStatus(201);
        Queue::assertPushed(\App\Jobs\DatabaseBackupJob::class);
    });
});

describe('PATCH /api/v1/databases/{uuid}/backups/{scheduled_backup_uuid} - Update backup', function () {
    test('updates an existing backup configuration', function () {
        $backup = ScheduledDatabaseBackup::create([
            'frequency' => 'daily',
            'enabled' => true,
            'save_s3' => false,
            'database_id' => $this->database->id,
            'database_type' => StandalonePostgresql::class,
            'team_id' => $this->team->id,
            'databases_to_backup' => 'testdb',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/databases/{$this->database->uuid}/backups/{$backup->uuid}", [
            'enabled' => false,
            'frequency' => 'weekly',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Database backup configuration updated']);

        $this->assertDatabaseHas('scheduled_database_backups', [
            'uuid' => $backup->uuid,
            'enabled' => false,
            'frequency' => 'weekly',
        ]);
    });

    test('returns 404 for non-existent backup configuration', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/databases/{$this->database->uuid}/backups/non-existent-backup-uuid", [
            'enabled' => false,
        ]);

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Backup config not found.']);
    });

    test('returns 404 for non-existent database', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson('/api/v1/databases/non-existent-uuid/backups/some-backup-uuid', [
            'enabled' => false,
        ]);

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Database not found.']);
    });

    test('returns 422 for unknown extra fields', function () {
        $backup = ScheduledDatabaseBackup::create([
            'frequency' => 'daily',
            'enabled' => true,
            'save_s3' => false,
            'database_id' => $this->database->id,
            'database_type' => StandalonePostgresql::class,
            'team_id' => $this->team->id,
            'databases_to_backup' => 'testdb',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/databases/{$this->database->uuid}/backups/{$backup->uuid}", [
            'enabled' => false,
            'unknown_field' => 'value',
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['unknown_field']]);
    });

    test('dispatches backup job on update when backup_now is true', function () {
        $backup = ScheduledDatabaseBackup::create([
            'frequency' => 'daily',
            'enabled' => true,
            'save_s3' => false,
            'database_id' => $this->database->id,
            'database_type' => StandalonePostgresql::class,
            'team_id' => $this->team->id,
            'databases_to_backup' => 'testdb',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->patchJson("/api/v1/databases/{$this->database->uuid}/backups/{$backup->uuid}", [
            'backup_now' => true,
        ]);

        $response->assertStatus(200);
        Queue::assertPushed(\App\Jobs\DatabaseBackupJob::class);
    });
});

describe('DELETE /api/v1/databases/{uuid}/backups/{scheduled_backup_uuid} - Delete backup', function () {
    test('deletes a backup configuration successfully', function () {
        $backup = ScheduledDatabaseBackup::create([
            'frequency' => 'daily',
            'enabled' => true,
            'save_s3' => false,
            'database_id' => $this->database->id,
            'database_type' => StandalonePostgresql::class,
            'team_id' => $this->team->id,
            'databases_to_backup' => 'testdb',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->deleteJson("/api/v1/databases/{$this->database->uuid}/backups/{$backup->uuid}");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Backup configuration and all executions deleted.']);

        $this->assertDatabaseMissing('scheduled_database_backups', [
            'uuid' => $backup->uuid,
        ]);
    });

    test('returns 404 for non-existent backup configuration', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->deleteJson("/api/v1/databases/{$this->database->uuid}/backups/non-existent-uuid");

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Backup configuration not found.']);
    });

    test('returns 404 for non-existent database', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->deleteJson('/api/v1/databases/non-existent-uuid/backups/some-backup-uuid');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Database not found.']);
    });

    test('cannot delete backup from another teams database', function () {
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

        $otherBackup = ScheduledDatabaseBackup::create([
            'frequency' => 'daily',
            'enabled' => true,
            'save_s3' => false,
            'database_id' => $otherDatabase->id,
            'database_type' => StandalonePostgresql::class,
            'team_id' => $otherTeam->id,
            'databases_to_backup' => 'testdb',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->deleteJson("/api/v1/databases/{$otherDatabase->uuid}/backups/{$otherBackup->uuid}");

        $response->assertStatus(404);
    });
});

describe('GET /api/v1/databases/{uuid}/backups/{scheduled_backup_uuid}/executions - List backup executions', function () {
    test('returns empty executions list for a backup with no runs', function () {
        $backup = ScheduledDatabaseBackup::create([
            'frequency' => 'daily',
            'enabled' => true,
            'save_s3' => false,
            'database_id' => $this->database->id,
            'database_type' => StandalonePostgresql::class,
            'team_id' => $this->team->id,
            'databases_to_backup' => 'testdb',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/databases/{$this->database->uuid}/backups/{$backup->uuid}/executions");

        $response->assertStatus(200);
        $response->assertJsonStructure(['executions']);
        expect($response->json('executions'))->toBeEmpty();
    });

    test('returns 404 when backup configuration does not exist', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/databases/{$this->database->uuid}/backups/non-existent-uuid/executions");

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Backup configuration not found.']);
    });

    test('returns 404 for non-existent database', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/databases/non-existent-uuid/backups/some-backup-uuid/executions');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Database not found.']);
    });

    test('returns 401 without authentication', function () {
        $backup = ScheduledDatabaseBackup::create([
            'frequency' => 'daily',
            'enabled' => true,
            'save_s3' => false,
            'database_id' => $this->database->id,
            'database_type' => StandalonePostgresql::class,
            'team_id' => $this->team->id,
            'databases_to_backup' => 'testdb',
        ]);

        $response = $this->getJson("/api/v1/databases/{$this->database->uuid}/backups/{$backup->uuid}/executions");
        $response->assertStatus(401);
    });
});
