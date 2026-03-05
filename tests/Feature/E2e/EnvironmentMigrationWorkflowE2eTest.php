<?php

/**
 * E2E Environment Migration Workflow Tests
 *
 * Tests the full migration API workflow:
 * - Check migration feasibility
 * - Create migrations (with and without approval)
 * - List and filter migrations
 * - Approve, reject, cancel, and rollback migrations
 * - Cross-team isolation
 * - Token ability enforcement
 * - Batch migrations
 * - Available targets
 */

use App\Jobs\ExecuteMigrationJob;
use App\Models\Application;
use App\Models\Environment;
use App\Models\EnvironmentMigration;
use App\Models\PrivateKey;
use App\Models\Project;
use App\Models\Server;
use App\Models\StandaloneDocker;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Visus\Cuid2\Cuid2;

uses(DatabaseTransactions::class);

// -- Helpers ------------------------------------------------------------------

function migrationHeaders(string $bearerToken): array
{
    return [
        'Authorization' => 'Bearer '.$bearerToken,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ];
}

/**
 * Create an EnvironmentMigration directly via DB::table to bypass $fillable
 * restrictions (status is not mass-assignable) and partial unique index.
 */
function createMigrationRecord(array $overrides = []): EnvironmentMigration
{
    $defaults = [
        'uuid' => (string) new Cuid2,
        'source_type' => Application::class,
        'source_id' => test()->sourceApp->id,
        'source_environment_id' => test()->sourceEnv->id,
        'target_environment_id' => test()->targetEnv->id,
        'target_server_id' => test()->server->id,
        'options' => json_encode(['copy_env_vars' => true, 'copy_volumes' => true]),
        'requires_approval' => false,
        'requested_by' => test()->user->id,
        'team_id' => test()->team->id,
        'status' => EnvironmentMigration::STATUS_PENDING,
        'progress' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ];

    $data = array_merge($defaults, $overrides);

    // Ensure options is always JSON string for raw DB insert
    if (is_array($data['options'])) {
        $data['options'] = json_encode($data['options']);
    }
    if (isset($data['rollback_snapshot']) && is_array($data['rollback_snapshot'])) {
        $data['rollback_snapshot'] = json_encode($data['rollback_snapshot']);
    }

    $id = DB::table('environment_migrations')->insertGetId($data);

    return EnvironmentMigration::findOrFail($id);
}

/**
 * Create an additional application in the source environment to avoid
 * hitting the unique-active-migration-per-source constraint.
 */
function createExtraApp(): Application
{
    $appId = DB::table('applications')->insertGetId([
        'uuid' => (string) new Cuid2,
        'name' => 'extra-app-'.uniqid(),
        'environment_id' => test()->sourceEnv->id,
        'destination_id' => test()->destination->id,
        'destination_type' => StandaloneDocker::class,
        'git_repository' => 'https://github.com/test/extra.git',
        'git_branch' => 'main',
        'build_pack' => 'nixpacks',
        'ports_exposes' => '3000',
        'status' => 'running',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return Application::find($appId);
}

// -- Setup --------------------------------------------------------------------

beforeEach(function () {
    Queue::fake();
    Cache::flush();
    try {
        Cache::store('redis')->flush();
    } catch (\Throwable $e) {
    }

    // Team + owner
    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();
    $this->team->members()->attach($this->user->id, ['role' => 'owner']);

    session(['currentTeam' => $this->team]);
    $this->token = $this->user->createToken('test-token', ['*']);
    $this->bearerToken = $this->token->plainTextToken;

    // Ensure API is enabled
    if (! DB::table('instance_settings')->where('id', 0)->exists()) {
        DB::table('instance_settings')->insert([
            'id' => 0,
            'is_api_enabled' => true,
            'is_registration_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // Project with dev (source) and uat (target) environments
    $this->project = Project::factory()->create(['team_id' => $this->team->id]);
    $this->sourceEnv = Environment::factory()->create([
        'project_id' => $this->project->id,
        'name' => 'development',
        'type' => 'development',
    ]);
    $this->targetEnv = Environment::factory()->create([
        'project_id' => $this->project->id,
        'name' => 'staging',
        'type' => 'uat',
    ]);

    // Server with functional settings (is_reachable + is_usable + force_disabled=false)
    $this->privateKey = PrivateKey::factory()->create(['team_id' => $this->team->id]);

    $serverId = DB::table('servers')->insertGetId([
        'uuid' => (string) new Cuid2,
        'name' => 'test-server-'.uniqid(),
        'ip' => '10.0.0.'.rand(2, 254),
        'port' => 22,
        'user' => 'root',
        'private_key_id' => $this->privateKey->id,
        'team_id' => $this->team->id,
        'proxy' => json_encode(['type' => 'TRAEFIK', 'redirect_enabled' => true]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $this->server = Server::find($serverId);

    DB::table('server_settings')->insert([
        'server_id' => $this->server->id,
        'is_usable' => true,
        'is_reachable' => true,
        'force_disabled' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Docker destination (bypass boot events that trigger SSH)
    $dockerId = DB::table('standalone_dockers')->insertGetId([
        'uuid' => (string) new Cuid2,
        'name' => 'test-docker',
        'network' => 'saturn',
        'server_id' => $this->server->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $this->destination = StandaloneDocker::find($dockerId);

    // Source application (via DB insert to bypass boot events)
    $appId = DB::table('applications')->insertGetId([
        'uuid' => (string) new Cuid2,
        'name' => 'migration-test-app-'.uniqid(),
        'environment_id' => $this->sourceEnv->id,
        'destination_id' => $this->destination->id,
        'destination_type' => StandaloneDocker::class,
        'git_repository' => 'https://github.com/test/migration-app.git',
        'git_branch' => 'main',
        'build_pack' => 'nixpacks',
        'ports_exposes' => '3000',
        'status' => 'running',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $this->sourceApp = Application::find($appId);
});

// =============================================================================
// 1. Full migration lifecycle
// =============================================================================

describe('Full migration lifecycle', function () {
    test('check feasibility -> create -> list -> get details -> verify job dispatched', function () {
        // Step 1: Check feasibility
        $checkResponse = $this->withHeaders(migrationHeaders($this->bearerToken))
            ->postJson('/api/v1/migrations/check', [
                'source_type' => 'application',
                'source_uuid' => $this->sourceApp->uuid,
                'target_environment_id' => $this->targetEnv->id,
            ]);

        $checkResponse->assertStatus(200);
        $checkResponse->assertJsonPath('allowed', true);
        $checkResponse->assertJsonStructure([
            'allowed', 'requires_approval', 'reason',
            'source' => ['name', 'type', 'environment', 'environment_type'],
            'target' => ['environment', 'environment_type'],
            'target_servers',
        ]);

        // Step 2: Create migration (owner dev->uat = no approval needed)
        $createResponse = $this->withHeaders(migrationHeaders($this->bearerToken))
            ->postJson('/api/v1/migrations', [
                'source_type' => 'application',
                'source_uuid' => $this->sourceApp->uuid,
                'target_environment_id' => $this->targetEnv->id,
                'target_server_id' => $this->server->id,
                'options' => ['copy_env_vars' => true, 'copy_volumes' => true],
            ]);

        $createResponse->assertStatus(201);
        $createResponse->assertJsonPath('requires_approval', false);
        $createResponse->assertJsonPath('message', 'Migration started.');
        $migrationUuid = $createResponse->json('migration.uuid');
        expect($migrationUuid)->not->toBeNull();

        // Step 3: Verify in list
        $listResponse = $this->withHeaders(migrationHeaders($this->bearerToken))
            ->getJson('/api/v1/migrations');

        $listResponse->assertStatus(200);
        $uuids = collect($listResponse->json('data'))->pluck('uuid')->toArray();
        expect($uuids)->toContain($migrationUuid);

        // Step 4: Get details (rollback_snapshot hidden)
        $detailResponse = $this->withHeaders(migrationHeaders($this->bearerToken))
            ->getJson("/api/v1/migrations/{$migrationUuid}");

        $detailResponse->assertStatus(200);
        $detailResponse->assertJsonPath('uuid', $migrationUuid);
        $detailResponse->assertJsonPath('status', 'pending');
        $detailResponse->assertJsonMissingPath('rollback_snapshot');

        // Step 5: ExecuteMigrationJob was dispatched (no approval needed)
        Queue::assertPushed(ExecuteMigrationJob::class);
    });
});

// =============================================================================
// 2. Migration with approval workflow
// =============================================================================

describe('Migration with approval workflow', function () {
    test('pending migration appears in pending list -> approve -> dispatches job -> removed from pending', function () {
        $migration = createMigrationRecord([
            'requires_approval' => true,
            'status' => EnvironmentMigration::STATUS_PENDING,
        ]);

        // Appears in pending list
        $pendingResponse = $this->withHeaders(migrationHeaders($this->bearerToken))
            ->getJson('/api/v1/migrations/pending');

        $pendingResponse->assertStatus(200);
        $pendingUuids = collect($pendingResponse->json('data'))->pluck('uuid')->toArray();
        expect($pendingUuids)->toContain($migration->uuid);

        // Approve
        $approveResponse = $this->withHeaders(migrationHeaders($this->bearerToken))
            ->postJson("/api/v1/migrations/{$migration->uuid}/approve");

        $approveResponse->assertStatus(200);
        $approveResponse->assertJsonPath('message', 'Migration approved and execution started.');

        // Verify DB state
        $migration->refresh();
        expect($migration->status)->toBe(EnvironmentMigration::STATUS_APPROVED);
        expect((int) $migration->approved_by)->toBe($this->user->id);
        expect($migration->approved_at)->not->toBeNull();

        // Job dispatched
        Queue::assertPushed(ExecuteMigrationJob::class);

        // No longer in pending list
        $pendingAfter = $this->withHeaders(migrationHeaders($this->bearerToken))
            ->getJson('/api/v1/migrations/pending');

        $pendingAfterUuids = collect($pendingAfter->json('data'))->pluck('uuid')->toArray();
        expect($pendingAfterUuids)->not->toContain($migration->uuid);
    });

    test('approve race condition — second attempt returns 403 (no longer pending)', function () {
        $migration = createMigrationRecord([
            'requires_approval' => true,
            'status' => EnvironmentMigration::STATUS_PENDING,
        ]);

        // First succeeds
        $this->withHeaders(migrationHeaders($this->bearerToken))
            ->postJson("/api/v1/migrations/{$migration->uuid}/approve")
            ->assertStatus(200);

        // Second fails (policy denies: isAwaitingApproval is false)
        $this->withHeaders(migrationHeaders($this->bearerToken))
            ->postJson("/api/v1/migrations/{$migration->uuid}/approve")
            ->assertStatus(403);
    });
});

// =============================================================================
// 3. Migration rejection flow
// =============================================================================

describe('Migration rejection flow', function () {
    test('reject with reason -> status rejected, reason stored, removed from pending', function () {
        $migration = createMigrationRecord([
            'requires_approval' => true,
            'status' => EnvironmentMigration::STATUS_PENDING,
        ]);

        $rejectResponse = $this->withHeaders(migrationHeaders($this->bearerToken))
            ->postJson("/api/v1/migrations/{$migration->uuid}/reject", [
                'reason' => 'Not ready for staging deployment.',
            ]);

        $rejectResponse->assertStatus(200);
        $rejectResponse->assertJsonPath('message', 'Migration rejected.');

        $migration->refresh();
        expect($migration->status)->toBe(EnvironmentMigration::STATUS_REJECTED);
        expect($migration->rejection_reason)->toBe('Not ready for staging deployment.');
        expect((int) $migration->approved_by)->toBe($this->user->id);

        // Not in pending list
        $pendingUuids = collect(
            $this->withHeaders(migrationHeaders($this->bearerToken))
                ->getJson('/api/v1/migrations/pending')
                ->json('data')
        )->pluck('uuid')->toArray();

        expect($pendingUuids)->not->toContain($migration->uuid);
    });

    test('reject requires reason field and rejects non-pending migration', function () {
        // Missing reason -> 422
        $migration = createMigrationRecord([
            'requires_approval' => true,
            'status' => EnvironmentMigration::STATUS_PENDING,
        ]);

        $this->withHeaders(migrationHeaders($this->bearerToken))
            ->postJson("/api/v1/migrations/{$migration->uuid}/reject", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);

        // Completed migration -> 403
        $completed = createMigrationRecord([
            'source_id' => createExtraApp()->id,
            'requires_approval' => true,
            'status' => EnvironmentMigration::STATUS_COMPLETED,
        ]);

        $this->withHeaders(migrationHeaders($this->bearerToken))
            ->postJson("/api/v1/migrations/{$completed->uuid}/reject", ['reason' => 'Too late.'])
            ->assertStatus(403);
    });
});

// =============================================================================
// 4. Cancel migration
// =============================================================================

describe('Cancel migration', function () {
    test('cancel pending -> verified cancelled -> re-cancel returns 400', function () {
        $migration = createMigrationRecord(['status' => EnvironmentMigration::STATUS_PENDING]);

        $this->withHeaders(migrationHeaders($this->bearerToken))
            ->postJson("/api/v1/migrations/{$migration->uuid}/cancel")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Migration cancelled successfully.');

        $migration->refresh();
        expect($migration->status)->toBe(EnvironmentMigration::STATUS_CANCELLED);

        // Re-cancel -> 400
        $this->withHeaders(migrationHeaders($this->bearerToken))
            ->postJson("/api/v1/migrations/{$migration->uuid}/cancel")
            ->assertStatus(400)
            ->assertJsonFragment(['message' => 'Migration cannot be cancelled. Current status: cancelled']);
    });

    test('cancel succeeds for approved but fails for in_progress and completed', function () {
        // Approved -> cancellable
        $approved = createMigrationRecord([
            'requires_approval' => true,
            'status' => EnvironmentMigration::STATUS_APPROVED,
            'approved_by' => $this->user->id,
            'approved_at' => now(),
        ]);

        $this->withHeaders(migrationHeaders($this->bearerToken))
            ->postJson("/api/v1/migrations/{$approved->uuid}/cancel")
            ->assertStatus(200);

        $approved->refresh();
        expect($approved->status)->toBe(EnvironmentMigration::STATUS_CANCELLED);

        // In-progress -> 400
        $inProgress = createMigrationRecord([
            'source_id' => createExtraApp()->id,
            'status' => EnvironmentMigration::STATUS_IN_PROGRESS,
        ]);

        $this->withHeaders(migrationHeaders($this->bearerToken))
            ->postJson("/api/v1/migrations/{$inProgress->uuid}/cancel")
            ->assertStatus(400);

        // Completed -> 400
        $completed = createMigrationRecord([
            'source_id' => createExtraApp()->id,
            'status' => EnvironmentMigration::STATUS_COMPLETED,
        ]);

        $this->withHeaders(migrationHeaders($this->bearerToken))
            ->postJson("/api/v1/migrations/{$completed->uuid}/cancel")
            ->assertStatus(400)
            ->assertJsonFragment(['message' => 'Migration cannot be cancelled. Current status: completed']);
    });
});

// =============================================================================
// 5. Dry run mode
// =============================================================================

describe('Dry run mode', function () {
    test('dry_run returns pre_checks and diff without creating a migration record', function () {
        $response = $this->withHeaders(migrationHeaders($this->bearerToken))
            ->postJson('/api/v1/migrations', [
                'source_type' => 'application',
                'source_uuid' => $this->sourceApp->uuid,
                'target_environment_id' => $this->targetEnv->id,
                'target_server_id' => $this->server->id,
                'dry_run' => true,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('dry_run', true);
        $response->assertJsonStructure(['dry_run', 'pre_checks', 'diff']);

        // No migration should be created
        expect(EnvironmentMigration::where('team_id', $this->team->id)->count())->toBe(0);
    });
});

// =============================================================================
// 6. Cross-team isolation
// =============================================================================

describe('Cross-team isolation', function () {
    beforeEach(function () {
        $this->otherTeam = Team::factory()->create();
        $this->otherUser = User::factory()->create();
        $this->otherTeam->members()->attach($this->otherUser->id, ['role' => 'owner']);

        $this->otherProject = Project::factory()->create(['team_id' => $this->otherTeam->id]);
        $this->otherDevEnv = Environment::factory()->create([
            'project_id' => $this->otherProject->id,
            'name' => 'other-dev',
            'type' => 'development',
        ]);
        $this->otherUatEnv = Environment::factory()->create([
            'project_id' => $this->otherProject->id,
            'name' => 'other-uat',
            'type' => 'uat',
        ]);

        $otherAppId = DB::table('applications')->insertGetId([
            'uuid' => (string) new Cuid2,
            'name' => 'other-team-app-'.uniqid(),
            'environment_id' => $this->otherDevEnv->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'git_repository' => 'https://github.com/other/app.git',
            'git_branch' => 'main',
            'build_pack' => 'nixpacks',
            'ports_exposes' => '3000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->otherApp = Application::find($otherAppId);

        $this->otherMigration = createMigrationRecord([
            'source_id' => $this->otherApp->id,
            'source_environment_id' => $this->otherDevEnv->id,
            'target_environment_id' => $this->otherUatEnv->id,
            'team_id' => $this->otherTeam->id,
            'requires_approval' => true,
        ]);
    });

    test('team A cannot list, get, approve, reject, or cancel migrations from team B', function () {
        $headers = migrationHeaders($this->bearerToken);
        $uuid = $this->otherMigration->uuid;

        // List excludes other team
        $listResponse = $this->withHeaders($headers)->getJson('/api/v1/migrations');
        $listResponse->assertStatus(200);
        $uuids = collect($listResponse->json('data'))->pluck('uuid')->toArray();
        expect($uuids)->not->toContain($uuid);

        // Get -> 404
        $this->withHeaders($headers)->getJson("/api/v1/migrations/{$uuid}")
            ->assertStatus(404)
            ->assertJsonPath('message', 'Migration not found.');

        // Approve -> 404
        $this->withHeaders($headers)->postJson("/api/v1/migrations/{$uuid}/approve")
            ->assertStatus(404);

        // Reject -> 404
        $this->withHeaders($headers)->postJson("/api/v1/migrations/{$uuid}/reject", ['reason' => 'Cross-team attempt.'])
            ->assertStatus(404);

        // Cancel -> 404
        $this->withHeaders($headers)->postJson("/api/v1/migrations/{$uuid}/cancel")
            ->assertStatus(404);
    });
});

// =============================================================================
// 7. Token ability enforcement
// =============================================================================

describe('Token ability enforcement', function () {
    test('read-only token can list, get, and check migrations', function () {
        $readToken = $this->user->createToken('read-only', ['read']);
        $headers = migrationHeaders($readToken->plainTextToken);

        $migration = createMigrationRecord();

        // List
        $this->withHeaders($headers)->getJson('/api/v1/migrations')->assertStatus(200);

        // Get details
        $this->withHeaders($headers)->getJson("/api/v1/migrations/{$migration->uuid}")->assertStatus(200);

        // Check feasibility (POST but uses read ability)
        $this->withHeaders($headers)->postJson('/api/v1/migrations/check', [
            'source_type' => 'application',
            'source_uuid' => $this->sourceApp->uuid,
            'target_environment_id' => $this->targetEnv->id,
        ])->assertStatus(200);
    });

    test('read-only token cannot create, approve, reject, or cancel migrations', function () {
        $readToken = $this->user->createToken('read-only', ['read']);
        $headers = migrationHeaders($readToken->plainTextToken);

        $migration = createMigrationRecord([
            'requires_approval' => true,
            'status' => EnvironmentMigration::STATUS_PENDING,
        ]);

        // Create -> blocked by api.ability:write
        $createStatus = $this->withHeaders($headers)->postJson('/api/v1/migrations', [
            'source_type' => 'application',
            'source_uuid' => $this->sourceApp->uuid,
            'target_environment_id' => $this->targetEnv->id,
            'target_server_id' => $this->server->id,
        ])->status();
        expect($createStatus)->toBeIn([401, 403]);

        // Approve -> blocked
        $approveStatus = $this->withHeaders($headers)
            ->postJson("/api/v1/migrations/{$migration->uuid}/approve")
            ->status();
        expect($approveStatus)->toBeIn([401, 403]);

        // Reject -> blocked
        $rejectStatus = $this->withHeaders($headers)
            ->postJson("/api/v1/migrations/{$migration->uuid}/reject", ['reason' => 'Nope.'])
            ->status();
        expect($rejectStatus)->toBeIn([401, 403]);

        // Cancel -> blocked
        $cancelStatus = $this->withHeaders($headers)
            ->postJson("/api/v1/migrations/{$migration->uuid}/cancel")
            ->status();
        expect($cancelStatus)->toBeIn([401, 403]);
    });
});

// =============================================================================
// 8. Migration status filtering
// =============================================================================

describe('Migration status filtering', function () {
    test('filters list by status parameter and returns all when unfiltered', function () {
        createMigrationRecord(['status' => EnvironmentMigration::STATUS_PENDING]);

        $secondApp = createExtraApp();
        createMigrationRecord([
            'source_id' => $secondApp->id,
            'status' => EnvironmentMigration::STATUS_COMPLETED,
        ]);

        // Filtered: only completed
        $filtered = $this->withHeaders(migrationHeaders($this->bearerToken))
            ->getJson('/api/v1/migrations?status=completed');

        $filtered->assertStatus(200);
        $statuses = collect($filtered->json('data'))->pluck('status')->unique()->toArray();
        expect($statuses)->toBe(['completed']);

        // Unfiltered: both
        $unfiltered = $this->withHeaders(migrationHeaders($this->bearerToken))
            ->getJson('/api/v1/migrations');

        $unfiltered->assertStatus(200);
        $unfiltered->assertJsonCount(2, 'data');
    });
});

// =============================================================================
// 9. Batch migration
// =============================================================================

describe('Batch migration', function () {
    test('batch creates migrations for multiple resources', function () {
        $secondApp = createExtraApp();

        $response = $this->withHeaders(migrationHeaders($this->bearerToken))
            ->postJson('/api/v1/migrations/batch', [
                'target_environment_id' => $this->targetEnv->id,
                'target_server_id' => $this->server->id,
                'resources' => [
                    ['type' => 'application', 'uuid' => $this->sourceApp->uuid],
                    ['type' => 'application', 'uuid' => $secondApp->uuid],
                ],
            ]);

        // 201 if all succeed, 207 if partial
        expect($response->status())->toBeIn([201, 207]);
        $response->assertJsonStructure(['message', 'migrations', 'errors']);
    });

    test('batch returns 404 for missing resource and 422 for missing fields', function () {
        // Not found
        $this->withHeaders(migrationHeaders($this->bearerToken))
            ->postJson('/api/v1/migrations/batch', [
                'target_environment_id' => $this->targetEnv->id,
                'target_server_id' => $this->server->id,
                'resources' => [
                    ['type' => 'application', 'uuid' => 'non-existent-uuid'],
                ],
            ])
            ->assertStatus(404)
            ->assertJsonPath('message', 'Some resources were not found.');

        // Validation error
        $this->withHeaders(migrationHeaders($this->bearerToken))
            ->postJson('/api/v1/migrations/batch', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['target_environment_id', 'target_server_id', 'resources']);
    });
});

// =============================================================================
// 10. Available targets
// =============================================================================

describe('Available targets', function () {
    test('returns target environments and servers for a resource', function () {
        $response = $this->withHeaders(migrationHeaders($this->bearerToken))
            ->getJson("/api/v1/migrations/targets/application/{$this->sourceApp->uuid}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'source' => ['name', 'type', 'environment', 'environment_type'],
            'target_environments',
            'servers',
        ]);

        $targetEnvs = $response->json('target_environments');
        expect($targetEnvs)->toBeArray();
    });

    test('returns 404 for non-existent resource and non-existent environment', function () {
        // Resource not found
        $this->withHeaders(migrationHeaders($this->bearerToken))
            ->getJson('/api/v1/migrations/targets/application/non-existent-uuid')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Source resource not found.');

        // Environment not found
        $this->withHeaders(migrationHeaders($this->bearerToken))
            ->getJson('/api/v1/migrations/environment-targets/non-existent-uuid')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Environment not found.');
    });

    test('returns environment targets for bulk migration', function () {
        $response = $this->withHeaders(migrationHeaders($this->bearerToken))
            ->getJson("/api/v1/migrations/environment-targets/{$this->sourceEnv->uuid}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'source' => ['name', 'type', 'environment', 'environment_type'],
            'target_environments',
            'servers',
        ]);
    });
});

// =============================================================================
// 11. Rollback
// =============================================================================

describe('Rollback migration', function () {
    test('returns 403 for non-completed or snapshot-less migration, 404 for non-existent', function () {
        // In-progress -> 403
        $inProgress = createMigrationRecord(['status' => EnvironmentMigration::STATUS_IN_PROGRESS]);
        $this->withHeaders(migrationHeaders($this->bearerToken))
            ->postJson("/api/v1/migrations/{$inProgress->uuid}/rollback")
            ->assertStatus(403);

        // Completed without snapshot -> 403
        $noSnapshot = createMigrationRecord([
            'source_id' => createExtraApp()->id,
            'status' => EnvironmentMigration::STATUS_COMPLETED,
        ]);
        $this->withHeaders(migrationHeaders($this->bearerToken))
            ->postJson("/api/v1/migrations/{$noSnapshot->uuid}/rollback")
            ->assertStatus(403);

        // Non-existent -> 404
        $this->withHeaders(migrationHeaders($this->bearerToken))
            ->postJson('/api/v1/migrations/non-existent-uuid/rollback')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Migration not found.');
    });
});

// =============================================================================
// 12. Validation and edge cases
// =============================================================================

describe('Validation and edge cases', function () {
    test('create returns 422 for missing required fields and invalid source_type', function () {
        // Missing fields
        $this->withHeaders(migrationHeaders($this->bearerToken))
            ->postJson('/api/v1/migrations', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['source_type', 'source_uuid', 'target_environment_id', 'target_server_id']);

        // Invalid source_type
        $this->withHeaders(migrationHeaders($this->bearerToken))
            ->postJson('/api/v1/migrations', [
                'source_type' => 'invalid_type',
                'source_uuid' => $this->sourceApp->uuid,
                'target_environment_id' => $this->targetEnv->id,
                'target_server_id' => $this->server->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['source_type']);
    });

    test('create returns 404 for non-existent source or cross-team target', function () {
        // Non-existent source
        $this->withHeaders(migrationHeaders($this->bearerToken))
            ->postJson('/api/v1/migrations', [
                'source_type' => 'application',
                'source_uuid' => 'does-not-exist',
                'target_environment_id' => $this->targetEnv->id,
                'target_server_id' => $this->server->id,
            ])
            ->assertStatus(404)
            ->assertJsonPath('message', 'Source resource not found.');

        // Target environment from another team
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnv = Environment::factory()->create([
            'project_id' => $otherProject->id,
            'type' => 'uat',
        ]);

        $this->withHeaders(migrationHeaders($this->bearerToken))
            ->postJson('/api/v1/migrations', [
                'source_type' => 'application',
                'source_uuid' => $this->sourceApp->uuid,
                'target_environment_id' => $otherEnv->id,
                'target_server_id' => $this->server->id,
            ])
            ->assertStatus(404);
    });

    test('returns 401 for all endpoints without token', function () {
        $this->getJson('/api/v1/migrations')->assertStatus(401);
        $this->getJson('/api/v1/migrations/fake-uuid')->assertStatus(401);
        $this->postJson('/api/v1/migrations')->assertStatus(401);
        $this->postJson('/api/v1/migrations/check')->assertStatus(401);
        $this->postJson('/api/v1/migrations/fake-uuid/approve')->assertStatus(401);
        $this->postJson('/api/v1/migrations/fake-uuid/reject')->assertStatus(401);
        $this->postJson('/api/v1/migrations/fake-uuid/cancel')->assertStatus(401);
        $this->postJson('/api/v1/migrations/fake-uuid/rollback')->assertStatus(401);
    });

    test('non-admin member cannot approve or cancel another users migration', function () {
        $member = User::factory()->create();
        $this->team->members()->attach($member->id, ['role' => 'member']);
        $memberToken = $member->createToken('member-token', ['*']);
        $headers = migrationHeaders($memberToken->plainTextToken);

        // Cannot approve
        $pendingMigration = createMigrationRecord([
            'requires_approval' => true,
            'status' => EnvironmentMigration::STATUS_PENDING,
        ]);

        $this->withHeaders($headers)
            ->postJson("/api/v1/migrations/{$pendingMigration->uuid}/approve")
            ->assertStatus(403);

        // Cannot cancel (not requester, not admin/owner)
        $ownerMigration = createMigrationRecord([
            'source_id' => createExtraApp()->id,
        ]);

        $this->withHeaders($headers)
            ->postJson("/api/v1/migrations/{$ownerMigration->uuid}/cancel")
            ->assertStatus(403);
    });
});
