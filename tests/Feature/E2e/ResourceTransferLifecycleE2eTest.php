<?php

/**
 * E2E Resource Transfer Lifecycle Tests
 *
 * Tests the complete resource transfer workflow:
 * - Create transfer -> list -> get -> cancel lifecycle
 * - Status filtering and pagination
 * - Cross-team isolation
 * - Token ability enforcement
 * - Cancel state machine
 * - Transfer modes (clone, data_only, partial)
 * - Multiple transfers management
 */

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

    // Source infrastructure
    $this->project = Project::factory()->create(['team_id' => $this->team->id]);
    $this->environment = Environment::factory()->create(['project_id' => $this->project->id]);
    $this->privateKey = PrivateKey::factory()->create(['team_id' => $this->team->id]);

    $this->server = Server::withoutEvents(function () {
        return Server::factory()->create([
            'uuid' => (string) new Cuid2,
            'team_id' => $this->team->id,
            'private_key_id' => $this->privateKey->id,
            'ip' => '10.0.0.1',
        ]);
    });
    $setting = ServerSetting::firstOrCreate(['server_id' => $this->server->id]);
    // Make server functional: is_reachable/is_usable are excluded from $fillable,
    // so we bypass mass-assignment protection with forceFill().
    $setting->forceFill(['is_reachable' => true, 'is_usable' => true, 'force_disabled' => false])->save();

    $this->destination = StandaloneDocker::withoutEvents(function () {
        return StandaloneDocker::create([
            'uuid' => (string) new Cuid2,
            'name' => 'default',
            'network' => 'saturn',
            'server_id' => $this->server->id,
        ]);
    });

    // Source database
    $this->sourceDb = StandalonePostgresql::create([
        'name' => 'source-db',
        'environment_id' => $this->environment->id,
        'destination_id' => $this->destination->id,
        'destination_type' => StandaloneDocker::class,
        'postgres_user' => 'postgres',
        'postgres_password' => 'secret',
        'postgres_db' => 'sourcedb',
        'image' => 'postgres:16-alpine',
    ]);

    // Target infrastructure (second environment on same project for simple transfers)
    $this->targetEnvironment = Environment::factory()->create([
        'project_id' => $this->project->id,
        'name' => 'staging',
    ]);
});

// ─── Helper: create a transfer directly in the database ─────────────────────

function createTransferRecord(
    $teamId,
    $userId,
    $sourceId,
    $envId,
    $serverId,
    string $status = 'pending',
    string $mode = 'clone'
): ResourceTransfer {
    return ResourceTransfer::create([
        'team_id' => $teamId,
        'user_id' => $userId,
        'source_type' => StandalonePostgresql::class,
        'source_id' => $sourceId,
        'target_environment_id' => $envId,
        'target_server_id' => $serverId,
        'transfer_mode' => $mode,
        'status' => $status,
        'progress' => $status === 'completed' ? 100 : ($status === 'transferring' ? 50 : 0),
        'started_at' => in_array($status, ['transferring', 'completed', 'failed']) ? now() : null,
        'completed_at' => in_array($status, ['completed', 'failed', 'cancelled']) ? now() : null,
        'error_message' => $status === 'failed' ? 'Test error' : null,
    ]);
}

// ─── Helper: create other-team infrastructure for isolation tests ────────────

function createOtherTeamInfra(): array
{
    $otherTeam = Team::factory()->create();
    $otherUser = User::factory()->create();
    $otherTeam->members()->attach($otherUser->id, ['role' => 'owner']);

    $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
    $otherEnv = Environment::factory()->create(['project_id' => $otherProject->id]);
    $otherKey = PrivateKey::factory()->create(['team_id' => $otherTeam->id]);

    $otherServer = Server::withoutEvents(function () use ($otherTeam, $otherKey) {
        return Server::factory()->create([
            'uuid' => (string) new Cuid2,
            'team_id' => $otherTeam->id,
            'private_key_id' => $otherKey->id,
            'ip' => '10.0.0.2',
        ]);
    });
    ServerSetting::firstOrCreate(['server_id' => $otherServer->id]);

    $otherDest = StandaloneDocker::withoutEvents(function () use ($otherServer) {
        return StandaloneDocker::create([
            'uuid' => (string) new Cuid2,
            'name' => 'default',
            'network' => 'saturn',
            'server_id' => $otherServer->id,
        ]);
    });

    $otherDb = StandalonePostgresql::create([
        'name' => 'other-db',
        'environment_id' => $otherEnv->id,
        'destination_id' => $otherDest->id,
        'destination_type' => StandaloneDocker::class,
        'postgres_user' => 'postgres',
        'postgres_password' => 'secret',
        'postgres_db' => 'otherdb',
        'image' => 'postgres:16-alpine',
    ]);

    return [
        'team' => $otherTeam,
        'user' => $otherUser,
        'project' => $otherProject,
        'environment' => $otherEnv,
        'server' => $otherServer,
        'destination' => $otherDest,
        'database' => $otherDb,
    ];
}

// =============================================================================
// DESCRIBE: Full Transfer Lifecycle
// =============================================================================

describe('full transfer lifecycle', function () {
    test('create transfer via API, verify in list, get by UUID, cancel, verify cancelled', function () {
        // Step 1: Create transfer via API
        $createResponse = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/transfers', [
                'source_uuid' => $this->sourceDb->uuid,
                'source_type' => 'standalone-postgresql',
                'target_environment_uuid' => $this->targetEnvironment->uuid,
                'target_server_uuid' => $this->server->uuid,
            ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('requires_approval', false)
            ->assertJsonStructure(['message', 'transfer', 'requires_approval']);

        $transferUuid = $createResponse->json('transfer.uuid');
        expect($transferUuid)->not->toBeNull();

        // Step 2: Verify transfer appears in list
        $listResponse = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson('/api/v1/transfers');

        $listResponse->assertOk();
        $listData = collect($listResponse->json('data'));
        expect($listData->pluck('uuid')->toArray())->toContain($transferUuid);

        // Step 3: Get transfer by UUID
        $showResponse = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson("/api/v1/transfers/{$transferUuid}");

        $showResponse->assertOk()
            ->assertJsonPath('uuid', $transferUuid)
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('transfer_mode', 'clone');

        // Step 4: Cancel the transfer
        $cancelResponse = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson("/api/v1/transfers/{$transferUuid}/cancel");

        $cancelResponse->assertOk()
            ->assertJsonPath('message', 'Transfer cancelled.');

        // Step 5: Verify status changed to cancelled
        $showAfterCancel = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson("/api/v1/transfers/{$transferUuid}");

        $showAfterCancel->assertOk()
            ->assertJsonPath('status', 'cancelled');
    });
});

// =============================================================================
// DESCRIBE: Transfer List with Filtering
// =============================================================================

describe('transfer list with filtering', function () {
    test('filter transfers by status returns correct subset', function () {
        // Create transfers in various statuses
        createTransferRecord(
            $this->team->id, $this->user->id, $this->sourceDb->id,
            $this->targetEnvironment->id, $this->server->id, 'pending'
        );
        createTransferRecord(
            $this->team->id, $this->user->id, $this->sourceDb->id,
            $this->targetEnvironment->id, $this->server->id, 'completed'
        );
        createTransferRecord(
            $this->team->id, $this->user->id, $this->sourceDb->id,
            $this->targetEnvironment->id, $this->server->id, 'failed'
        );
        createTransferRecord(
            $this->team->id, $this->user->id, $this->sourceDb->id,
            $this->targetEnvironment->id, $this->server->id, 'cancelled'
        );

        // Filter by pending
        $pendingResponse = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson('/api/v1/transfers?status=pending');

        $pendingResponse->assertOk();
        $pendingData = collect($pendingResponse->json('data'));
        expect($pendingData)->toHaveCount(1);
        expect($pendingData->first()['status'])->toBe('pending');

        // Filter by completed
        $completedResponse = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson('/api/v1/transfers?status=completed');

        $completedResponse->assertOk();
        $completedData = collect($completedResponse->json('data'));
        expect($completedData)->toHaveCount(1);
        expect($completedData->first()['status'])->toBe('completed');

        // Filter by failed
        $failedResponse = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson('/api/v1/transfers?status=failed');

        $failedResponse->assertOk();
        expect(collect($failedResponse->json('data')))->toHaveCount(1);

        // No filter returns all
        $allResponse = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson('/api/v1/transfers');

        $allResponse->assertOk();
        expect($allResponse->json('total'))->toBe(4);
    });

    test('empty list when no transfers exist', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson('/api/v1/transfers');

        $response->assertOk()
            ->assertJsonPath('total', 0);
    });
});

// =============================================================================
// DESCRIBE: Cross-Team Isolation
// =============================================================================

describe('cross-team isolation', function () {
    test('team A transfers are invisible to team B across all operations', function () {
        $otherInfra = createOtherTeamInfra();

        // Create a transfer owned by the OTHER team
        $otherTransfer = ResourceTransfer::create([
            'team_id' => $otherInfra['team']->id,
            'user_id' => $otherInfra['user']->id,
            'source_type' => StandalonePostgresql::class,
            'source_id' => $otherInfra['database']->id,
            'target_environment_id' => $otherInfra['environment']->id,
            'target_server_id' => $otherInfra['server']->id,
            'transfer_mode' => ResourceTransfer::MODE_CLONE,
            'status' => ResourceTransfer::STATUS_PENDING,
        ]);

        // Team A: list should NOT see team B's transfer
        $listResponse = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson('/api/v1/transfers');

        $listResponse->assertOk()
            ->assertJsonPath('total', 0);

        // Team A: get by UUID should return 404
        $showResponse = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson("/api/v1/transfers/{$otherTransfer->uuid}");

        $showResponse->assertNotFound()
            ->assertJsonPath('message', 'Transfer not found.');

        // Team A: cancel should return 404
        $cancelResponse = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson("/api/v1/transfers/{$otherTransfer->uuid}/cancel");

        $cancelResponse->assertNotFound()
            ->assertJsonPath('message', 'Transfer not found.');
    });

    test('each team sees only their own transfers in list', function () {
        $otherInfra = createOtherTeamInfra();

        // Create transfer for current team
        createTransferRecord(
            $this->team->id, $this->user->id, $this->sourceDb->id,
            $this->targetEnvironment->id, $this->server->id, 'pending'
        );

        // Create transfer for other team
        ResourceTransfer::create([
            'team_id' => $otherInfra['team']->id,
            'user_id' => $otherInfra['user']->id,
            'source_type' => StandalonePostgresql::class,
            'source_id' => $otherInfra['database']->id,
            'target_environment_id' => $otherInfra['environment']->id,
            'target_server_id' => $otherInfra['server']->id,
            'transfer_mode' => ResourceTransfer::MODE_CLONE,
            'status' => ResourceTransfer::STATUS_PENDING,
        ]);

        // Current team sees exactly 1
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson('/api/v1/transfers');

        $response->assertOk()
            ->assertJsonPath('total', 1);
    });
});

// =============================================================================
// DESCRIBE: Transfer Validation
// =============================================================================

describe('transfer validation', function () {
    test('missing all required fields returns 422 with validation errors', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/transfers', []);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure(['message', 'errors']);
    });

    test('invalid source, environment, or server UUID returns 404', function () {
        // Invalid source_uuid
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/transfers', [
                'source_uuid' => 'nonexistent-uuid',
                'source_type' => 'standalone-postgresql',
                'target_environment_uuid' => $this->targetEnvironment->uuid,
                'target_server_uuid' => $this->server->uuid,
            ]);

        $response->assertNotFound()
            ->assertJsonPath('message', 'Source database not found.');

        // Invalid target environment UUID
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/transfers', [
                'source_uuid' => $this->sourceDb->uuid,
                'source_type' => 'standalone-postgresql',
                'target_environment_uuid' => 'nonexistent-env-uuid',
                'target_server_uuid' => $this->server->uuid,
            ]);

        $response->assertNotFound()
            ->assertJsonPath('message', 'Target environment not found.');

        // Invalid target server UUID
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/transfers', [
                'source_uuid' => $this->sourceDb->uuid,
                'source_type' => 'standalone-postgresql',
                'target_environment_uuid' => $this->targetEnvironment->uuid,
                'target_server_uuid' => 'nonexistent-server-uuid',
            ]);

        $response->assertNotFound()
            ->assertJsonPath('message', 'Target server not found.');
    });

    test('show nonexistent transfer returns 404', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson('/api/v1/transfers/nonexistent-uuid');

        $response->assertNotFound()
            ->assertJsonPath('message', 'Transfer not found.');
    });
});

// =============================================================================
// DESCRIBE: Cancel State Machine
// =============================================================================

describe('cancel state machine', function () {
    test('can cancel pending transfer', function () {
        $transfer = createTransferRecord(
            $this->team->id, $this->user->id, $this->sourceDb->id,
            $this->targetEnvironment->id, $this->server->id, 'pending'
        );

        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson("/api/v1/transfers/{$transfer->uuid}/cancel");

        $response->assertOk()
            ->assertJsonPath('message', 'Transfer cancelled.');

        $transfer->refresh();
        expect($transfer->status)->toBe(ResourceTransfer::STATUS_CANCELLED);
        expect($transfer->completed_at)->not->toBeNull();
    });

    test('can cancel preparing transfer', function () {
        $transfer = createTransferRecord(
            $this->team->id, $this->user->id, $this->sourceDb->id,
            $this->targetEnvironment->id, $this->server->id, 'preparing'
        );

        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson("/api/v1/transfers/{$transfer->uuid}/cancel");

        $response->assertOk()
            ->assertJsonPath('message', 'Transfer cancelled.');

        $transfer->refresh();
        expect($transfer->status)->toBe(ResourceTransfer::STATUS_CANCELLED);
    });

    test('can cancel transferring transfer', function () {
        $transfer = createTransferRecord(
            $this->team->id, $this->user->id, $this->sourceDb->id,
            $this->targetEnvironment->id, $this->server->id, 'transferring'
        );

        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson("/api/v1/transfers/{$transfer->uuid}/cancel");

        $response->assertOk()
            ->assertJsonPath('message', 'Transfer cancelled.');

        $transfer->refresh();
        expect($transfer->status)->toBe(ResourceTransfer::STATUS_CANCELLED);
    });

    test('cannot cancel completed transfer', function () {
        $transfer = createTransferRecord(
            $this->team->id, $this->user->id, $this->sourceDb->id,
            $this->targetEnvironment->id, $this->server->id, 'completed'
        );

        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson("/api/v1/transfers/{$transfer->uuid}/cancel");

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Transfer cannot be cancelled in its current state.');
    });

    test('cannot cancel failed transfer', function () {
        $transfer = createTransferRecord(
            $this->team->id, $this->user->id, $this->sourceDb->id,
            $this->targetEnvironment->id, $this->server->id, 'failed'
        );

        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson("/api/v1/transfers/{$transfer->uuid}/cancel");

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Transfer cannot be cancelled in its current state.');
    });

    test('cannot cancel already cancelled transfer', function () {
        $transfer = createTransferRecord(
            $this->team->id, $this->user->id, $this->sourceDb->id,
            $this->targetEnvironment->id, $this->server->id, 'cancelled'
        );

        $response = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson("/api/v1/transfers/{$transfer->uuid}/cancel");

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Transfer cannot be cancelled in its current state.');
    });
});

// =============================================================================
// DESCRIBE: Multiple Transfers Management
// =============================================================================

describe('multiple transfers management', function () {
    test('create 3 transfers, list returns all, cancel one, filtered list shows 2 pending', function () {
        // Create 3 pending transfers
        $transfer1 = createTransferRecord(
            $this->team->id, $this->user->id, $this->sourceDb->id,
            $this->targetEnvironment->id, $this->server->id, 'pending'
        );
        $transfer2 = createTransferRecord(
            $this->team->id, $this->user->id, $this->sourceDb->id,
            $this->targetEnvironment->id, $this->server->id, 'pending'
        );
        $transfer3 = createTransferRecord(
            $this->team->id, $this->user->id, $this->sourceDb->id,
            $this->targetEnvironment->id, $this->server->id, 'pending'
        );

        // List returns all 3
        $listResponse = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson('/api/v1/transfers');

        $listResponse->assertOk();
        expect($listResponse->json('total'))->toBe(3);

        // Cancel transfer1
        $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson("/api/v1/transfers/{$transfer1->uuid}/cancel")
            ->assertOk();

        // List filtered by pending now returns 2
        $filteredResponse = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson('/api/v1/transfers?status=pending');

        $filteredResponse->assertOk();
        $pendingUuids = collect($filteredResponse->json('data'))->pluck('uuid')->toArray();
        expect($pendingUuids)->toHaveCount(2);
        expect($pendingUuids)->toContain($transfer2->uuid);
        expect($pendingUuids)->toContain($transfer3->uuid);
        expect($pendingUuids)->not->toContain($transfer1->uuid);

        // List filtered by cancelled returns 1
        $cancelledResponse = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson('/api/v1/transfers?status=cancelled');

        $cancelledResponse->assertOk();
        $cancelledUuids = collect($cancelledResponse->json('data'))->pluck('uuid')->toArray();
        expect($cancelledUuids)->toHaveCount(1);
        expect($cancelledUuids)->toContain($transfer1->uuid);
    });
});

// =============================================================================
// DESCRIBE: Transfer Modes
// =============================================================================

describe('transfer modes', function () {
    test('create transfer defaults to clone mode and explicit clone mode also works', function () {
        // Default mode (no transfer_mode specified) should be clone
        $defaultResponse = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/transfers', [
                'source_uuid' => $this->sourceDb->uuid,
                'source_type' => 'standalone-postgresql',
                'target_environment_uuid' => $this->targetEnvironment->uuid,
                'target_server_uuid' => $this->server->uuid,
            ]);

        $defaultResponse->assertStatus(201);
        expect($defaultResponse->json('transfer.transfer_mode'))->toBe('clone');

        // Cancel the first transfer so we can create another (same source)
        $firstUuid = $defaultResponse->json('transfer.uuid');
        $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson("/api/v1/transfers/{$firstUuid}/cancel");

        // Explicit clone mode
        $explicitResponse = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->postJson('/api/v1/transfers', [
                'source_uuid' => $this->sourceDb->uuid,
                'source_type' => 'standalone-postgresql',
                'target_environment_uuid' => $this->targetEnvironment->uuid,
                'target_server_uuid' => $this->server->uuid,
                'transfer_mode' => 'clone',
            ]);

        $explicitResponse->assertStatus(201);
        expect($explicitResponse->json('transfer.transfer_mode'))->toBe('clone');
    });

    test('all transfer modes (clone, data_only, partial) are preserved when queried', function () {
        // Create transfers with each mode via factory/direct insert
        $cloneTransfer = createTransferRecord(
            $this->team->id, $this->user->id, $this->sourceDb->id,
            $this->targetEnvironment->id, $this->server->id, 'pending', 'clone'
        );
        $dataOnlyTransfer = createTransferRecord(
            $this->team->id, $this->user->id, $this->sourceDb->id,
            $this->targetEnvironment->id, $this->server->id, 'pending', 'data_only'
        );
        $partialTransfer = createTransferRecord(
            $this->team->id, $this->user->id, $this->sourceDb->id,
            $this->targetEnvironment->id, $this->server->id, 'pending', 'partial'
        );

        // Verify each via GET
        $cloneShow = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson("/api/v1/transfers/{$cloneTransfer->uuid}");
        $cloneShow->assertOk()
            ->assertJsonPath('transfer_mode', 'clone');

        $dataOnlyShow = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson("/api/v1/transfers/{$dataOnlyTransfer->uuid}");
        $dataOnlyShow->assertOk()
            ->assertJsonPath('transfer_mode', 'data_only');

        $partialShow = $this->withHeader('Authorization', "Bearer {$this->bearerToken}")
            ->getJson("/api/v1/transfers/{$partialTransfer->uuid}");
        $partialShow->assertOk()
            ->assertJsonPath('transfer_mode', 'partial');
    });
});

// =============================================================================
// DESCRIBE: Token Ability Enforcement
// =============================================================================

describe('token ability enforcement', function () {
    test('read token can list and get transfers but cannot create or cancel', function () {
        $readToken = $this->user->createToken('read-token', ['read']);

        // Can list
        $listResponse = $this->withHeader('Authorization', "Bearer {$readToken->plainTextToken}")
            ->getJson('/api/v1/transfers');
        $listResponse->assertOk();

        // Can get by UUID
        $transfer = createTransferRecord(
            $this->team->id, $this->user->id, $this->sourceDb->id,
            $this->targetEnvironment->id, $this->server->id, 'pending'
        );

        $showResponse = $this->withHeader('Authorization', "Bearer {$readToken->plainTextToken}")
            ->getJson("/api/v1/transfers/{$transfer->uuid}");
        $showResponse->assertOk()
            ->assertJsonPath('uuid', $transfer->uuid);

        // Cannot create
        $createResponse = $this->withHeader('Authorization', "Bearer {$readToken->plainTextToken}")
            ->postJson('/api/v1/transfers', [
                'source_uuid' => $this->sourceDb->uuid,
                'source_type' => 'standalone-postgresql',
                'target_environment_uuid' => $this->targetEnvironment->uuid,
                'target_server_uuid' => $this->server->uuid,
            ]);
        $createResponse->assertStatus(403);

        // Cannot cancel
        $cancelResponse = $this->withHeader('Authorization', "Bearer {$readToken->plainTextToken}")
            ->postJson("/api/v1/transfers/{$transfer->uuid}/cancel");
        $cancelResponse->assertStatus(403);
    });

    test('write token can create and cancel transfers', function () {
        $writeToken = $this->user->createToken('write-token', ['read', 'write']);

        // Create
        $createResponse = $this->withHeader('Authorization', "Bearer {$writeToken->plainTextToken}")
            ->postJson('/api/v1/transfers', [
                'source_uuid' => $this->sourceDb->uuid,
                'source_type' => 'standalone-postgresql',
                'target_environment_uuid' => $this->targetEnvironment->uuid,
                'target_server_uuid' => $this->server->uuid,
            ]);

        $createResponse->assertStatus(201);
        $transferUuid = $createResponse->json('transfer.uuid');

        // Cancel
        $cancelResponse = $this->withHeader('Authorization', "Bearer {$writeToken->plainTextToken}")
            ->postJson("/api/v1/transfers/{$transferUuid}/cancel");

        $cancelResponse->assertOk()
            ->assertJsonPath('message', 'Transfer cancelled.');
    });

    test('deploy token cannot list or create transfers', function () {
        $deployToken = $this->user->createToken('deploy-token', ['deploy']);

        // Cannot list (requires read)
        $listResponse = $this->withHeader('Authorization', "Bearer {$deployToken->plainTextToken}")
            ->getJson('/api/v1/transfers');

        $listResponse->assertStatus(403);

        // Cannot create (requires write)
        $createResponse = $this->withHeader('Authorization', "Bearer {$deployToken->plainTextToken}")
            ->postJson('/api/v1/transfers', [
                'source_uuid' => $this->sourceDb->uuid,
                'source_type' => 'standalone-postgresql',
                'target_environment_uuid' => $this->targetEnvironment->uuid,
                'target_server_uuid' => $this->server->uuid,
            ]);

        $createResponse->assertStatus(403);
    });

    test('unauthenticated request returns 401', function () {
        $response = $this->getJson('/api/v1/transfers');

        $response->assertUnauthorized();
    });
});
