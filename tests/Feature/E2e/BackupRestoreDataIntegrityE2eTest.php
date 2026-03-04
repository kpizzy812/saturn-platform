<?php

namespace Tests\Feature\E2e;

use App\Jobs\DatabaseRestoreJob;
use App\Models\Environment;
use App\Models\InstanceSettings;
use App\Models\PrivateKey;
use App\Models\Project;
use App\Models\ScheduledDatabaseBackup;
use App\Models\ScheduledDatabaseBackupExecution;
use App\Models\Server;
use App\Models\ServerSetting;
use App\Models\StandaloneDocker;
use App\Models\StandalonePostgresql;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Visus\Cuid2\Cuid2;

uses(DatabaseTransactions::class);

// ---------------------------------------------------------------------------
// Shared helpers
// ---------------------------------------------------------------------------

/**
 * Create a ScheduledDatabaseBackupExecution with sensible defaults.
 * All keys in $overrides are merged on top.
 */
function makeExecution(ScheduledDatabaseBackup $backup, array $overrides = []): ScheduledDatabaseBackupExecution
{
    return ScheduledDatabaseBackupExecution::create(array_merge([
        'uuid' => (string) new Cuid2,
        'scheduled_database_backup_id' => $backup->id,
        'status' => 'success',
        'filename' => '/backups/databases/testteam-1/testdb-abc123/testdb_2026-03-01_120000.dump',
        'size' => '4096',
        'database_name' => 'testdb',
        'finished_at' => Carbon::now()->subHour(),
        'restore_status' => null,
    ], $overrides));
}

// ---------------------------------------------------------------------------
// Test setup
// ---------------------------------------------------------------------------

beforeEach(function () {
    Queue::fake();

    Cache::flush();
    try {
        Cache::store('redis')->flush();
    } catch (\Throwable) {
        // Redis may not be available
    }

    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();
    $this->team->members()->attach($this->user->id, ['role' => 'owner']);
    session(['currentTeam' => $this->team]);

    $this->token = $this->user->createToken('test-token', ['*']);
    $this->bearerToken = $this->token->plainTextToken;

    if (! InstanceSettings::first()) {
        InstanceSettings::create(['id' => 0, 'is_api_enabled' => true]);
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

    $this->backup = ScheduledDatabaseBackup::create([
        'uuid' => (string) new Cuid2,
        'frequency' => 'daily',
        'enabled' => true,
        'save_s3' => false,
        'database_id' => $this->database->id,
        'database_type' => StandalonePostgresql::class,
        'team_id' => $this->team->id,
        'databases_to_backup' => 'testdb',
    ]);
});

// ---------------------------------------------------------------------------
// Authentication & authorisation
// ---------------------------------------------------------------------------

describe('Authentication & Authorisation', function () {
    test('rejects restore request without authentication', function () {
        $execution = makeExecution($this->backup);

        $response = $this->postJson(
            "/api/v1/databases/{$this->database->uuid}/backups/{$this->backup->uuid}/restore"
        );

        $response->assertStatus(401);
        Queue::assertNothingPushed();
    });

    test('rejects restore with read-only token scope', function () {
        $token = $this->user->createToken('read-token', ['read'])->plainTextToken;
        $execution = makeExecution($this->backup);

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson(
                "/api/v1/databases/{$this->database->uuid}/backups/{$this->backup->uuid}/restore"
            );

        $response->assertStatus(403);
        Queue::assertNothingPushed();
    });

    test('rejects restore with deploy-only token scope', function () {
        $token = $this->user->createToken('deploy-token', ['deploy'])->plainTextToken;
        $execution = makeExecution($this->backup);

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson(
                "/api/v1/databases/{$this->database->uuid}/backups/{$this->backup->uuid}/restore"
            );

        $response->assertStatus(403);
        Queue::assertNothingPushed();
    });

    test('allows restore with write token scope', function () {
        $token = $this->user->createToken('write-token', ['write'])->plainTextToken;
        $execution = makeExecution($this->backup);

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson(
                "/api/v1/databases/{$this->database->uuid}/backups/{$this->backup->uuid}/restore"
            );

        $response->assertStatus(200);
        Queue::assertPushed(DatabaseRestoreJob::class);
    });

    test('allows restore with root token scope', function () {
        $token = $this->user->createToken('root-token', ['root'])->plainTextToken;
        $execution = makeExecution($this->backup);

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson(
                "/api/v1/databases/{$this->database->uuid}/backups/{$this->backup->uuid}/restore"
            );

        $response->assertStatus(200);
        Queue::assertPushed(DatabaseRestoreJob::class);
    });
});

// ---------------------------------------------------------------------------
// Core restore functionality
// ---------------------------------------------------------------------------

describe('POST /api/v1/databases/{uuid}/backups/{backup_uuid}/restore — core', function () {
    test('dispatches DatabaseRestoreJob for latest successful execution', function () {
        $execution = makeExecution($this->backup);

        $response = $this->withHeaders(['Authorization' => "Bearer {$this->bearerToken}"])
            ->postJson(
                "/api/v1/databases/{$this->database->uuid}/backups/{$this->backup->uuid}/restore"
            );

        $response->assertStatus(200);
        $response->assertJsonFragment(['message' => 'Database restore initiated.']);
        Queue::assertPushed(DatabaseRestoreJob::class);
    });

    test('response includes backup execution metadata for data verification', function () {
        $execution = makeExecution($this->backup, [
            'filename' => '/backups/databases/myteam-1/mydb-xyz/mydb_2026-03-01_120000.dump',
            'size' => '8192',
        ]);

        $response = $this->withHeaders(['Authorization' => "Bearer {$this->bearerToken}"])
            ->postJson(
                "/api/v1/databases/{$this->database->uuid}/backups/{$this->backup->uuid}/restore"
            );

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'backup_execution' => ['uuid', 'created_at', 'filename', 'size'],
        ]);
        $response->assertJsonPath('backup_execution.uuid', $execution->uuid);
        $response->assertJsonPath('backup_execution.filename', $execution->filename);
        $response->assertJsonPath('backup_execution.size', '8192');
    });

    test('restores from latest successful execution when no execution_uuid given', function () {
        // Create two successful executions: an older and a newer one
        $older = makeExecution($this->backup, [
            'database_name' => 'testdb',
            'filename' => '/backups/old_backup.dump',
            'finished_at' => Carbon::now()->subHours(5),
        ]);
        $newer = makeExecution($this->backup, [
            'database_name' => 'testdb',
            'filename' => '/backups/new_backup.dump',
            'finished_at' => Carbon::now()->subHour(),
        ]);

        $response = $this->withHeaders(['Authorization' => "Bearer {$this->bearerToken}"])
            ->postJson(
                "/api/v1/databases/{$this->database->uuid}/backups/{$this->backup->uuid}/restore"
            );

        $response->assertStatus(200);
        // Should use the MOST RECENT successful execution
        $response->assertJsonPath('backup_execution.uuid', $newer->uuid);
        $response->assertJsonPath('backup_execution.filename', '/backups/new_backup.dump');
    });

    test('restores from specific execution when execution_uuid is provided', function () {
        $first = makeExecution($this->backup, [
            'filename' => '/backups/first_backup.dump',
            'database_name' => 'testdb',
            'finished_at' => Carbon::now()->subDays(3),
        ]);
        $second = makeExecution($this->backup, [
            'filename' => '/backups/second_backup.dump',
            'database_name' => 'testdb',
            'finished_at' => Carbon::now()->subDay(),
        ]);

        // Explicitly request the OLDER first backup (data integrity: restore from known good point)
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->bearerToken}"])
            ->postJson(
                "/api/v1/databases/{$this->database->uuid}/backups/{$this->backup->uuid}/restore",
                ['execution_uuid' => $first->uuid]
            );

        $response->assertStatus(200);
        // Must use the specific execution we asked for, not the latest
        $response->assertJsonPath('backup_execution.uuid', $first->uuid);
        $response->assertJsonPath('backup_execution.filename', '/backups/first_backup.dump');
    });

    test('skips failed executions when selecting latest for restore', function () {
        // One failed and one successful execution
        makeExecution($this->backup, [
            'status' => 'failed',
            'filename' => '/backups/failed_backup.dump',
            'finished_at' => Carbon::now()->subMinutes(30), // More recent, but failed
        ]);
        $successfulExecution = makeExecution($this->backup, [
            'status' => 'success',
            'filename' => '/backups/successful_backup.dump',
            'finished_at' => Carbon::now()->subHours(2),
        ]);

        $response = $this->withHeaders(['Authorization' => "Bearer {$this->bearerToken}"])
            ->postJson(
                "/api/v1/databases/{$this->database->uuid}/backups/{$this->backup->uuid}/restore"
            );

        $response->assertStatus(200);
        // Must NOT use the failed execution — only the successful one
        $response->assertJsonPath('backup_execution.uuid', $successfulExecution->uuid);
        $response->assertJsonPath('backup_execution.filename', '/backups/successful_backup.dump');
    });
});

// ---------------------------------------------------------------------------
// Data integrity: job carries correct execution data
// ---------------------------------------------------------------------------

describe('Data integrity — dispatched job carries correct backup data', function () {
    test('dispatched job carries execution with correct database name', function () {
        $execution = makeExecution($this->backup, [
            'database_name' => 'production_db',
            'filename' => '/backups/production_db_2026.dump',
        ]);

        $this->withHeaders(['Authorization' => "Bearer {$this->bearerToken}"])
            ->postJson(
                "/api/v1/databases/{$this->database->uuid}/backups/{$this->backup->uuid}/restore"
            );

        Queue::assertPushed(DatabaseRestoreJob::class, function (DatabaseRestoreJob $job) use ($execution) {
            // Verify the job is dispatched with the exact execution we expect
            return $job->execution->id === $execution->id
                && $job->execution->database_name === 'production_db';
        });
    });

    test('dispatched job carries execution with correct backup filename', function () {
        $filename = '/backups/databases/myteam-5/postgres-abc/mydb_2026-03-01_150000.dump';
        $execution = makeExecution($this->backup, ['filename' => $filename]);

        $this->withHeaders(['Authorization' => "Bearer {$this->bearerToken}"])
            ->postJson(
                "/api/v1/databases/{$this->database->uuid}/backups/{$this->backup->uuid}/restore"
            );

        Queue::assertPushed(DatabaseRestoreJob::class, function (DatabaseRestoreJob $job) use ($filename) {
            return $job->execution->filename === $filename;
        });
    });

    test('dispatched job carries the correct backup configuration', function () {
        $execution = makeExecution($this->backup);

        $this->withHeaders(['Authorization' => "Bearer {$this->bearerToken}"])
            ->postJson(
                "/api/v1/databases/{$this->database->uuid}/backups/{$this->backup->uuid}/restore"
            );

        Queue::assertPushed(DatabaseRestoreJob::class, function (DatabaseRestoreJob $job) {
            return $job->backup->id === $this->backup->id
                && $job->backup->uuid === $this->backup->uuid;
        });
    });

    test('dispatched job carries execution with correct file size for integrity check', function () {
        $execution = makeExecution($this->backup, ['size' => '102400']);

        $this->withHeaders(['Authorization' => "Bearer {$this->bearerToken}"])
            ->postJson(
                "/api/v1/databases/{$this->database->uuid}/backups/{$this->backup->uuid}/restore"
            );

        Queue::assertPushed(DatabaseRestoreJob::class, function (DatabaseRestoreJob $job) use ($execution) {
            return $job->execution->size === '102400'
                && $job->execution->id === $execution->id;
        });
    });

    test('when restoring specific execution, job carries that exact execution data', function () {
        $target = makeExecution($this->backup, [
            'database_name' => 'snapshot_jan',
            'filename' => '/backups/snapshot_jan.dump',
            'size' => '20480',
            'finished_at' => Carbon::now()->subDays(30),
        ]);
        // Create a newer execution that should NOT be used
        makeExecution($this->backup, [
            'database_name' => 'snapshot_mar',
            'filename' => '/backups/snapshot_mar.dump',
            'finished_at' => Carbon::now()->subDay(),
        ]);

        $this->withHeaders(['Authorization' => "Bearer {$this->bearerToken}"])
            ->postJson(
                "/api/v1/databases/{$this->database->uuid}/backups/{$this->backup->uuid}/restore",
                ['execution_uuid' => $target->uuid]
            );

        Queue::assertPushed(DatabaseRestoreJob::class, function (DatabaseRestoreJob $job) use ($target) {
            return $job->execution->id === $target->id
                && $job->execution->database_name === 'snapshot_jan'
                && $job->execution->filename === '/backups/snapshot_jan.dump'
                && $job->execution->size === '20480';
        });
    });
});

// ---------------------------------------------------------------------------
// Restore status model transitions
// ---------------------------------------------------------------------------

describe('Restore status transitions — execution model', function () {
    test('execution restore_status is null before restore is triggered', function () {
        $execution = makeExecution($this->backup);

        expect($execution->restore_status)->toBeNull();
        expect($execution->restore_started_at)->toBeNull();
        expect($execution->restore_finished_at)->toBeNull();
        expect($execution->restore_message)->toBeNull();
    });

    test('execution restore_status can be set to in_progress with timestamp', function () {
        $execution = makeExecution($this->backup);

        $startedAt = Carbon::now();
        $execution->update([
            'restore_status' => 'in_progress',
            'restore_started_at' => $startedAt,
        ]);

        $execution->refresh();
        expect($execution->restore_status)->toBe('in_progress');
        expect($execution->restore_started_at)->not->toBeNull();
        expect($execution->restore_finished_at)->toBeNull();
    });

    test('execution restore_status transitions to success with completion timestamps', function () {
        $execution = makeExecution($this->backup);
        $started = Carbon::now()->subMinutes(2);
        $finished = Carbon::now();

        $execution->update([
            'restore_status' => 'in_progress',
            'restore_started_at' => $started,
        ]);
        $execution->update([
            'restore_status' => 'success',
            'restore_finished_at' => $finished,
            'restore_message' => 'Restore completed successfully',
        ]);

        $execution->refresh();
        expect($execution->restore_status)->toBe('success');
        expect($execution->restore_started_at)->not->toBeNull();
        expect($execution->restore_finished_at)->not->toBeNull();
        expect($execution->restore_message)->toBe('Restore completed successfully');
    });

    test('execution restore_status transitions to failed with error message', function () {
        $execution = makeExecution($this->backup);
        $errorMessage = 'pg_restore: error: could not execute query: ERROR: relation "users" already exists';

        $execution->update([
            'restore_status' => 'in_progress',
            'restore_started_at' => Carbon::now()->subMinutes(1),
        ]);
        $execution->update([
            'restore_status' => 'failed',
            'restore_finished_at' => Carbon::now(),
            'restore_message' => $errorMessage,
        ]);

        $execution->refresh();
        expect($execution->restore_status)->toBe('failed');
        expect($execution->restore_message)->toBe($errorMessage);
        expect($execution->restore_finished_at)->not->toBeNull();
    });

    test('restore duration can be calculated from timestamps', function () {
        $execution = makeExecution($this->backup);

        $started = Carbon::now()->subSeconds(45);
        $finished = Carbon::now();

        $execution->update([
            'restore_status' => 'success',
            'restore_started_at' => $started,
            'restore_finished_at' => $finished,
        ]);

        $execution->refresh();
        $duration = $execution->restore_finished_at->diffInSeconds($execution->restore_started_at);
        expect($duration)->toBeGreaterThanOrEqual(44);
        expect($duration)->toBeLessThanOrEqual(50);
    });

    test('failed backup can have subsequent restore attempts with fresh status', function () {
        // Simulates: first restore failed, then a new restore is initiated
        $execution = makeExecution($this->backup);

        // First attempt fails
        $execution->update([
            'restore_status' => 'failed',
            'restore_started_at' => Carbon::now()->subMinutes(10),
            'restore_finished_at' => Carbon::now()->subMinutes(9),
            'restore_message' => 'Connection timeout',
        ]);

        // Second attempt: status reset to in_progress
        $execution->update([
            'restore_status' => 'in_progress',
            'restore_started_at' => Carbon::now(),
            'restore_finished_at' => null,
            'restore_message' => null,
        ]);

        $execution->refresh();
        expect($execution->restore_status)->toBe('in_progress');
        expect($execution->restore_message)->toBeNull();
        expect($execution->restore_finished_at)->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// Business rules and error handling
// ---------------------------------------------------------------------------

describe('Business rules — error cases', function () {
    test('returns 400 when restoring from a failed backup execution', function () {
        $failedExecution = makeExecution($this->backup, ['status' => 'failed']);

        $response = $this->withHeaders(['Authorization' => "Bearer {$this->bearerToken}"])
            ->postJson(
                "/api/v1/databases/{$this->database->uuid}/backups/{$this->backup->uuid}/restore",
                ['execution_uuid' => $failedExecution->uuid]
            );

        $response->assertStatus(400);
        $response->assertJsonFragment(['message' => 'Cannot restore from a failed backup.']);
        Queue::assertNothingPushed();
    });

    test('returns 404 when no successful executions exist', function () {
        // Only failed executions exist
        makeExecution($this->backup, ['status' => 'failed']);
        makeExecution($this->backup, ['status' => 'failed']);

        $response = $this->withHeaders(['Authorization' => "Bearer {$this->bearerToken}"])
            ->postJson(
                "/api/v1/databases/{$this->database->uuid}/backups/{$this->backup->uuid}/restore"
            );

        $response->assertStatus(404);
        $response->assertJsonFragment(['message' => 'No backup execution found to restore from.']);
        Queue::assertNothingPushed();
    });

    test('returns 404 when backup has zero executions', function () {
        // No executions at all
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->bearerToken}"])
            ->postJson(
                "/api/v1/databases/{$this->database->uuid}/backups/{$this->backup->uuid}/restore"
            );

        $response->assertStatus(404);
        $response->assertJsonFragment(['message' => 'No backup execution found to restore from.']);
        Queue::assertNothingPushed();
    });

    test('returns 404 for non-existent execution_uuid', function () {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->bearerToken}"])
            ->postJson(
                "/api/v1/databases/{$this->database->uuid}/backups/{$this->backup->uuid}/restore",
                ['execution_uuid' => 'non-existent-uuid-12345']
            );

        $response->assertStatus(404);
        $response->assertJsonFragment(['message' => 'No backup execution found to restore from.']);
        Queue::assertNothingPushed();
    });

    test('returns 409 when restore is already in progress', function () {
        $execution = makeExecution($this->backup, ['restore_status' => 'in_progress']);

        $response = $this->withHeaders(['Authorization' => "Bearer {$this->bearerToken}"])
            ->postJson(
                "/api/v1/databases/{$this->database->uuid}/backups/{$this->backup->uuid}/restore",
                ['execution_uuid' => $execution->uuid]
            );

        $response->assertStatus(409);
        $response->assertJsonFragment(['message' => 'Restore is already in progress for this backup.']);
        Queue::assertNothingPushed();
    });

    test('allows new restore after previous restore succeeded', function () {
        $execution = makeExecution($this->backup, [
            'restore_status' => 'success',
            'restore_finished_at' => Carbon::now()->subHour(),
        ]);

        $response = $this->withHeaders(['Authorization' => "Bearer {$this->bearerToken}"])
            ->postJson(
                "/api/v1/databases/{$this->database->uuid}/backups/{$this->backup->uuid}/restore",
                ['execution_uuid' => $execution->uuid]
            );

        $response->assertStatus(200);
        Queue::assertPushed(DatabaseRestoreJob::class);
    });

    test('allows new restore after previous restore failed', function () {
        $execution = makeExecution($this->backup, [
            'restore_status' => 'failed',
            // Override status to 'success' so the backup itself is restorable
            // but the restore_status shows the previous attempt failed
        ]);

        $response = $this->withHeaders(['Authorization' => "Bearer {$this->bearerToken}"])
            ->postJson(
                "/api/v1/databases/{$this->database->uuid}/backups/{$this->backup->uuid}/restore",
                ['execution_uuid' => $execution->uuid]
            );

        $response->assertStatus(200);
        Queue::assertPushed(DatabaseRestoreJob::class);
    });
});

// ---------------------------------------------------------------------------
// Cross-team isolation
// ---------------------------------------------------------------------------

describe('Cross-team isolation', function () {
    test('returns 404 for database belonging to another team', function () {
        // Create another team's infrastructure
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnvironment = Environment::factory()->create(['project_id' => $otherProject->id]);
        $otherPrivateKey = PrivateKey::factory()->create(['team_id' => $otherTeam->id]);

        $otherServer = Server::withoutEvents(fn () => Server::factory()->create([
            'uuid' => (string) new Cuid2,
            'team_id' => $otherTeam->id,
            'private_key_id' => $otherPrivateKey->id,
        ]));
        ServerSetting::firstOrCreate(['server_id' => $otherServer->id]);

        $otherDestination = StandaloneDocker::withoutEvents(fn () => StandaloneDocker::create([
            'uuid' => (string) new Cuid2,
            'name' => 'default',
            'network' => 'saturn',
            'server_id' => $otherServer->id,
        ]));

        $otherDb = StandalonePostgresql::create([
            'name' => 'other-team-db',
            'environment_id' => $otherEnvironment->id,
            'destination_id' => $otherDestination->id,
            'destination_type' => StandaloneDocker::class,
            'postgres_user' => 'postgres',
            'postgres_password' => 'secret',
            'postgres_db' => 'otherdb',
            'image' => 'postgres:16-alpine',
        ]);

        $otherBackup = ScheduledDatabaseBackup::create([
            'uuid' => (string) new Cuid2,
            'frequency' => 'daily',
            'enabled' => true,
            'save_s3' => false,
            'database_id' => $otherDb->id,
            'database_type' => StandalonePostgresql::class,
            'team_id' => $otherTeam->id,
            'databases_to_backup' => 'otherdb',
        ]);
        makeExecution($otherBackup, ['database_name' => 'otherdb']);

        // Current team's token should NOT access other team's restore
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->bearerToken}"])
            ->postJson(
                "/api/v1/databases/{$otherDb->uuid}/backups/{$otherBackup->uuid}/restore"
            );

        $response->assertStatus(404);
        Queue::assertNothingPushed();
    });

    test('returns 404 for non-existent database UUID', function () {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->bearerToken}"])
            ->postJson(
                '/api/v1/databases/non-existent-db-uuid/backups/some-backup-uuid/restore'
            );

        $response->assertStatus(404);
        $response->assertJsonFragment(['message' => 'Database not found.']);
        Queue::assertNothingPushed();
    });

    test('returns 404 for non-existent backup configuration UUID', function () {
        $execution = makeExecution($this->backup);

        $response = $this->withHeaders(['Authorization' => "Bearer {$this->bearerToken}"])
            ->postJson(
                "/api/v1/databases/{$this->database->uuid}/backups/non-existent-backup-uuid/restore"
            );

        $response->assertStatus(404);
        $response->assertJsonFragment(['message' => 'Backup configuration not found.']);
        Queue::assertNothingPushed();
    });

    test('cannot restore using backup UUID from a different database of same team', function () {
        // Same team, different database — backup UUID does not belong to this database
        $secondDb = StandalonePostgresql::create([
            'name' => 'second-postgres',
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'postgres_user' => 'postgres',
            'postgres_password' => 'secret456',
            'postgres_db' => 'seconddb',
            'image' => 'postgres:16-alpine',
        ]);

        $secondBackup = ScheduledDatabaseBackup::create([
            'uuid' => (string) new Cuid2,
            'frequency' => 'daily',
            'enabled' => true,
            'save_s3' => false,
            'database_id' => $secondDb->id,
            'database_type' => StandalonePostgresql::class,
            'team_id' => $this->team->id,
            'databases_to_backup' => 'seconddb',
        ]);
        makeExecution($secondBackup, ['database_name' => 'seconddb']);

        // Try to restore secondBackup but via the FIRST database's UUID
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->bearerToken}"])
            ->postJson(
                "/api/v1/databases/{$this->database->uuid}/backups/{$secondBackup->uuid}/restore"
            );

        $response->assertStatus(404);
        $response->assertJsonFragment(['message' => 'Backup configuration not found.']);
        Queue::assertNothingPushed();
    });
});

// ---------------------------------------------------------------------------
// Job dispatch integrity
// ---------------------------------------------------------------------------

describe('DatabaseRestoreJob — dispatch integrity', function () {
    test('job is dispatched on high priority queue', function () {
        $execution = makeExecution($this->backup);

        $this->withHeaders(['Authorization' => "Bearer {$this->bearerToken}"])
            ->postJson(
                "/api/v1/databases/{$this->database->uuid}/backups/{$this->backup->uuid}/restore"
            );

        Queue::assertPushedOn('high', DatabaseRestoreJob::class);
    });

    test('only one job is dispatched per restore request', function () {
        $execution = makeExecution($this->backup);

        $this->withHeaders(['Authorization' => "Bearer {$this->bearerToken}"])
            ->postJson(
                "/api/v1/databases/{$this->database->uuid}/backups/{$this->backup->uuid}/restore"
            );

        Queue::assertPushed(DatabaseRestoreJob::class, 1);
    });

    test('multiple databases can queue independent restore jobs', function () {
        // Setup second database with its own backup
        $secondDb = StandalonePostgresql::create([
            'name' => 'second-postgres',
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'postgres_user' => 'postgres',
            'postgres_password' => 'secret456',
            'postgres_db' => 'seconddb',
            'image' => 'postgres:16-alpine',
        ]);

        $secondBackup = ScheduledDatabaseBackup::create([
            'uuid' => (string) new Cuid2,
            'frequency' => 'daily',
            'enabled' => true,
            'save_s3' => false,
            'database_id' => $secondDb->id,
            'database_type' => StandalonePostgresql::class,
            'team_id' => $this->team->id,
            'databases_to_backup' => 'seconddb',
        ]);
        $exec1 = makeExecution($this->backup, ['database_name' => 'testdb']);
        $exec2 = makeExecution($secondBackup, ['database_name' => 'seconddb']);

        // Trigger restore for first database
        $this->withHeaders(['Authorization' => "Bearer {$this->bearerToken}"])
            ->postJson(
                "/api/v1/databases/{$this->database->uuid}/backups/{$this->backup->uuid}/restore"
            );

        // Trigger restore for second database
        $this->withHeaders(['Authorization' => "Bearer {$this->bearerToken}"])
            ->postJson(
                "/api/v1/databases/{$secondDb->uuid}/backups/{$secondBackup->uuid}/restore"
            );

        // Both jobs should be dispatched
        Queue::assertPushed(DatabaseRestoreJob::class, 2);

        // Each job carries the correct execution for its respective database
        Queue::assertPushed(DatabaseRestoreJob::class, function (DatabaseRestoreJob $job) use ($exec1) {
            return $job->execution->database_name === 'testdb'
                && $job->execution->id === $exec1->id;
        });
        Queue::assertPushed(DatabaseRestoreJob::class, function (DatabaseRestoreJob $job) use ($exec2) {
            return $job->execution->database_name === 'seconddb'
                && $job->execution->id === $exec2->id;
        });
    });
});
