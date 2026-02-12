<?php

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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(DatabaseTransactions::class);

beforeEach(function () {
    Queue::fake();

    // Create team with owner
    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();
    $this->team->members()->attach($this->user->id, ['role' => 'owner']);

    session(['currentTeam' => $this->team]);

    // Create API token with all abilities (team_id is taken from session)
    $this->token = $this->user->createToken('test-token', ['*']);
    $this->bearerToken = $this->token->plainTextToken;

    // Create project with dev and uat environments
    $this->project = Project::factory()->create(['team_id' => $this->team->id]);

    $this->devEnv = Environment::factory()->create([
        'name' => 'test-dev-'.uniqid(),
        'project_id' => $this->project->id,
        'type' => 'development',
    ]);

    $this->uatEnv = Environment::factory()->create([
        'name' => 'test-uat-'.uniqid(),
        'project_id' => $this->project->id,
        'type' => 'uat',
    ]);

    // Create private key and server without boot events (avoids SSH calls and Docker network creation)
    $this->privateKey = PrivateKey::factory()->create(['team_id' => $this->team->id]);

    $serverId = DB::table('servers')->insertGetId([
        'uuid' => (string) new \Visus\Cuid2\Cuid2,
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

    // Create ServerSetting manually (normally done by Server::created boot)
    // Note: is_usable and is_reachable are NOT in $fillable, must use DB::table
    DB::table('server_settings')->insert([
        'server_id' => $this->server->id,
        'is_usable' => true,
        'is_reachable' => true,
        'force_disabled' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Create StandaloneDocker without triggering SSH (boot event runs instant_remote_process)
    $dockerId = DB::table('standalone_dockers')->insertGetId([
        'uuid' => (string) new \Visus\Cuid2\Cuid2,
        'name' => 'test-docker',
        'network' => 'saturn',
        'server_id' => $this->server->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $this->docker = StandaloneDocker::find($dockerId);

    // Create application via DB insert to bypass boot events (which need InstanceSettings, SSH, etc.)
    $appId = DB::table('applications')->insertGetId([
        'uuid' => (string) new \Visus\Cuid2\Cuid2,
        'name' => 'test-app-'.uniqid(),
        'environment_id' => $this->devEnv->id,
        'destination_id' => $this->docker->id,
        'destination_type' => StandaloneDocker::class,
        'git_repository' => 'https://github.com/test/test.git',
        'git_branch' => 'main',
        'build_pack' => 'nixpacks',
        'ports_exposes' => '3000',
        'status' => 'running',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $this->testApp = Application::find($appId);
});

/**
 * Helper to create EnvironmentMigration record directly via DB::table.
 * Bypasses $fillable (status is not fillable) and partial unique index
 * (which only allows one active migration per source_type+source_id).
 */
function createMigration(array $overrides = []): EnvironmentMigration
{
    $defaults = [
        'uuid' => (string) new \Visus\Cuid2\Cuid2,
        'source_type' => Application::class,
        'source_id' => test()->testApp->id,
        'source_environment_id' => test()->devEnv->id,
        'target_environment_id' => test()->uatEnv->id,
        'target_server_id' => test()->server->id,
        'options' => json_encode(['copy_env_vars' => true, 'copy_volumes' => true]),
        'requires_approval' => false,
        'requested_by' => test()->user->id,
        'team_id' => test()->team->id,
        'status' => EnvironmentMigration::STATUS_PENDING,
        'created_at' => now(),
        'updated_at' => now(),
    ];

    $data = array_merge($defaults, $overrides);

    // Ensure options is JSON string
    if (is_array($data['options'])) {
        $data['options'] = json_encode($data['options']);
    }

    $id = DB::table('environment_migrations')->insertGetId($data);

    return EnvironmentMigration::findOrFail($id);
}

/**
 * Helper for authenticated requests.
 */
function authHeaders(): array
{
    return [
        'Authorization' => 'Bearer '.test()->bearerToken,
        'Content-Type' => 'application/json',
    ];
}

// ─── Authentication ───────────────────────────────────────────────────────────

describe('Authentication', function () {
    test('returns 401 for all endpoints without token', function () {
        $this->getJson('/api/v1/migrations')->assertStatus(401);
        $this->getJson('/api/v1/migrations/fake-uuid')->assertStatus(401);
        $this->postJson('/api/v1/migrations')->assertStatus(401);
        $this->postJson('/api/v1/migrations/fake-uuid/approve')->assertStatus(401);
        $this->postJson('/api/v1/migrations/fake-uuid/reject')->assertStatus(401);
        $this->postJson('/api/v1/migrations/fake-uuid/cancel')->assertStatus(401);
        $this->postJson('/api/v1/migrations/fake-uuid/rollback')->assertStatus(401);
    });
});

// ─── GET /api/v1/migrations ───────────────────────────────────────────────────

describe('GET /api/v1/migrations', function () {
    test('returns empty list when no migrations exist', function () {
        $response = $this->withHeaders(authHeaders())
            ->getJson('/api/v1/migrations');

        $response->assertStatus(200);
        $response->assertJsonPath('data', []);
    });

    test('returns migrations for current team', function () {
        // First pending, second completed (unique constraint allows only one active per source)
        createMigration();
        createMigration(['status' => EnvironmentMigration::STATUS_COMPLETED]);

        $response = $this->withHeaders(authHeaders())
            ->getJson('/api/v1/migrations');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    });

    test('does not return migrations from other teams', function () {
        // Create migration for current team
        createMigration();

        // Create migration for another team (different source to avoid unique constraint)
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnv = Environment::factory()->create([
            'project_id' => $otherProject->id,
            'type' => 'development',
        ]);
        $otherUatEnv = Environment::factory()->create([
            'project_id' => $otherProject->id,
            'type' => 'uat',
        ]);

        // Create a different source app for the other team's migration
        $otherAppId = DB::table('applications')->insertGetId([
            'uuid' => (string) new \Visus\Cuid2\Cuid2,
            'name' => 'other-app-'.uniqid(),
            'environment_id' => $otherEnv->id,
            'destination_id' => $this->docker->id,
            'destination_type' => StandaloneDocker::class,
            'git_repository' => 'https://github.com/test/other.git',
            'git_branch' => 'main',
            'build_pack' => 'nixpacks',
            'ports_exposes' => '3000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        createMigration([
            'source_id' => $otherAppId,
            'source_environment_id' => $otherEnv->id,
            'target_environment_id' => $otherUatEnv->id,
            'team_id' => $otherTeam->id,
        ]);

        $response = $this->withHeaders(authHeaders())
            ->getJson('/api/v1/migrations');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    });

    test('filters by status', function () {
        createMigration(); // pending
        createMigration(['status' => EnvironmentMigration::STATUS_COMPLETED]);

        $response = $this->withHeaders(authHeaders())
            ->getJson('/api/v1/migrations?status=completed');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    });

    test('respects per_page parameter', function () {
        // Create 5 completed migrations (unique constraint only applies to active statuses)
        for ($i = 0; $i < 5; $i++) {
            createMigration(['status' => EnvironmentMigration::STATUS_COMPLETED]);
        }

        $response = $this->withHeaders(authHeaders())
            ->getJson('/api/v1/migrations?per_page=2');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('per_page', 2);
    });
});

// ─── GET /api/v1/migrations/{uuid} ───────────────────────────────────────────

describe('GET /api/v1/migrations/{uuid}', function () {
    test('returns migration details', function () {
        $migration = createMigration();

        $response = $this->withHeaders(authHeaders())
            ->getJson("/api/v1/migrations/{$migration->uuid}");

        $response->assertStatus(200);
        $response->assertJsonPath('uuid', $migration->uuid);
        $response->assertJsonPath('status', 'pending');
        // rollback_snapshot should be hidden
        $response->assertJsonMissingPath('rollback_snapshot');
    });

    test('returns 404 for non-existent uuid', function () {
        $response = $this->withHeaders(authHeaders())
            ->getJson('/api/v1/migrations/non-existent-uuid');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Migration not found.']);
    });

    test('returns 404 for migration from another team', function () {
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnv = Environment::factory()->create([
            'project_id' => $otherProject->id,
            'type' => 'development',
        ]);
        $otherUatEnv = Environment::factory()->create([
            'project_id' => $otherProject->id,
            'type' => 'uat',
        ]);

        // Create source in other env to avoid unique constraint
        $otherAppId = DB::table('applications')->insertGetId([
            'uuid' => (string) new \Visus\Cuid2\Cuid2,
            'name' => 'other-app-'.uniqid(),
            'environment_id' => $otherEnv->id,
            'destination_id' => $this->docker->id,
            'destination_type' => StandaloneDocker::class,
            'git_repository' => 'https://github.com/test/other.git',
            'git_branch' => 'main',
            'build_pack' => 'nixpacks',
            'ports_exposes' => '3000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = createMigration([
            'source_id' => $otherAppId,
            'source_environment_id' => $otherEnv->id,
            'target_environment_id' => $otherUatEnv->id,
            'team_id' => $otherTeam->id,
        ]);

        $response = $this->withHeaders(authHeaders())
            ->getJson("/api/v1/migrations/{$migration->uuid}");

        $response->assertStatus(404);
    });
});

// ─── POST /api/v1/migrations (store) ─────────────────────────────────────────

describe('POST /api/v1/migrations', function () {
    test('returns 422 for missing required fields', function () {
        $response = $this->withHeaders(authHeaders())
            ->postJson('/api/v1/migrations', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['source_type', 'source_uuid', 'target_environment_id', 'target_server_id']);
    });

    test('returns 422 for invalid source_type', function () {
        $response = $this->withHeaders(authHeaders())
            ->postJson('/api/v1/migrations', [
                'source_type' => 'invalid_type',
                'source_uuid' => 'some-uuid',
                'target_environment_id' => $this->uatEnv->id,
                'target_server_id' => $this->server->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['source_type']);
    });

    test('returns 404 for non-existent source resource', function () {
        $response = $this->withHeaders(authHeaders())
            ->postJson('/api/v1/migrations', [
                'source_type' => 'application',
                'source_uuid' => 'non-existent-uuid',
                'target_environment_id' => $this->uatEnv->id,
                'target_server_id' => $this->server->id,
            ]);

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Source resource not found.']);
    });

    test('returns 404 for target environment from another team', function () {
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnv = Environment::factory()->create([
            'project_id' => $otherProject->id,
            'type' => 'uat',
        ]);

        $response = $this->withHeaders(authHeaders())
            ->postJson('/api/v1/migrations', [
                'source_type' => 'application',
                'source_uuid' => $this->testApp->uuid,
                'target_environment_id' => $otherEnv->id,
                'target_server_id' => $this->server->id,
            ]);

        $response->assertStatus(404);
    });

    test('creates migration for valid dev to uat request', function () {
        $response = $this->withHeaders(authHeaders())
            ->postJson('/api/v1/migrations', [
                'source_type' => 'application',
                'source_uuid' => $this->testApp->uuid,
                'target_environment_id' => $this->uatEnv->id,
                'target_server_id' => $this->server->id,
                'options' => [
                    'copy_env_vars' => true,
                    'copy_volumes' => true,
                ],
            ]);

        // Owner doesn't need approval for dev->uat, so 201
        $response->assertStatus(201);
        $response->assertJsonPath('requires_approval', false);
        $response->assertJsonStructure([
            'message',
            'migration' => ['uuid', 'status'],
            'requires_approval',
        ]);

        // Verify migration created in DB
        expect(EnvironmentMigration::where('team_id', $this->team->id)->count())->toBe(1);
    });

    test('returns dry_run preview without creating migration', function () {
        $response = $this->withHeaders(authHeaders())
            ->postJson('/api/v1/migrations', [
                'source_type' => 'application',
                'source_uuid' => $this->testApp->uuid,
                'target_environment_id' => $this->uatEnv->id,
                'target_server_id' => $this->server->id,
                'dry_run' => true,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('dry_run', true);
        $response->assertJsonStructure(['dry_run', 'pre_checks', 'diff']);

        // No migration should be created
        expect(EnvironmentMigration::where('team_id', $this->team->id)->count())->toBe(0);
    });

    test('returns 400 for invalid migration chain (dev to dev)', function () {
        // Create another dev environment in same project
        $devEnv2 = Environment::factory()->create([
            'name' => 'Dev 2',
            'project_id' => $this->project->id,
            'type' => 'development',
        ]);

        $response = $this->withHeaders(authHeaders())
            ->postJson('/api/v1/migrations', [
                'source_type' => 'application',
                'source_uuid' => $this->testApp->uuid,
                'target_environment_id' => $devEnv2->id,
                'target_server_id' => $this->server->id,
            ]);

        $response->assertStatus(400);
    });
});

// ─── POST /api/v1/migrations/{uuid}/cancel ───────────────────────────────────

describe('POST /api/v1/migrations/{uuid}/cancel', function () {
    test('requester can cancel own pending migration', function () {
        $migration = createMigration();

        $response = $this->withHeaders(authHeaders())
            ->postJson("/api/v1/migrations/{$migration->uuid}/cancel");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Migration cancelled successfully.']);

        $migration->refresh();
        expect($migration->status)->toBe(EnvironmentMigration::STATUS_CANCELLED);
    });

    test('returns 400 when cancelling already completed migration', function () {
        $migration = createMigration(['status' => EnvironmentMigration::STATUS_COMPLETED]);

        $response = $this->withHeaders(authHeaders())
            ->postJson("/api/v1/migrations/{$migration->uuid}/cancel");

        $response->assertStatus(400);
        $response->assertJson(['message' => 'Migration cannot be cancelled. Current status: completed']);
    });

    test('returns 400 when cancelling already failed migration', function () {
        $migration = createMigration(['status' => EnvironmentMigration::STATUS_FAILED]);

        $response = $this->withHeaders(authHeaders())
            ->postJson("/api/v1/migrations/{$migration->uuid}/cancel");

        $response->assertStatus(400);
    });

    test('non-requester without approve permission gets 403', function () {
        // Create a member user (no approve permission)
        $developer = User::factory()->create();
        $this->team->members()->attach($developer->id, ['role' => 'member']);
        $devToken = $developer->createToken('dev-token', ['*']);

        $migration = createMigration();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$devToken->plainTextToken,
            'Content-Type' => 'application/json',
        ])->postJson("/api/v1/migrations/{$migration->uuid}/cancel");

        $response->assertStatus(403);
    });

    test('returns 404 for non-existent migration', function () {
        $response = $this->withHeaders(authHeaders())
            ->postJson('/api/v1/migrations/non-existent-uuid/cancel');

        $response->assertStatus(404);
    });
});

// ─── POST /api/v1/migrations/{uuid}/approve ──────────────────────────────────

describe('POST /api/v1/migrations/{uuid}/approve', function () {
    test('owner can approve pending migration', function () {
        $migration = createMigration(['requires_approval' => true]);

        $response = $this->withHeaders(authHeaders())
            ->postJson("/api/v1/migrations/{$migration->uuid}/approve");

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Migration approved and execution started.');

        $migration->refresh();
        expect($migration->status)->toBe(EnvironmentMigration::STATUS_APPROVED);
        expect((int) $migration->approved_by)->toBe($this->user->id);
    });

    test('returns 403 when approving already approved migration', function () {
        $migration = createMigration(['requires_approval' => true]);

        // First approval succeeds
        $this->withHeaders(authHeaders())
            ->postJson("/api/v1/migrations/{$migration->uuid}/approve")
            ->assertStatus(200);

        // Second attempt: policy denies because status is no longer pending
        $response = $this->withHeaders(authHeaders())
            ->postJson("/api/v1/migrations/{$migration->uuid}/approve");

        $response->assertStatus(403);
    });

    test('returns 403 when non-admin tries to approve', function () {
        // Create a member user (not admin/owner)
        $member = User::factory()->create();
        $this->team->members()->attach($member->id, ['role' => 'member']);
        $memberToken = $member->createToken('member-token', ['*']);

        $migration = createMigration(['requires_approval' => true]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$memberToken->plainTextToken,
            'Content-Type' => 'application/json',
        ])->postJson("/api/v1/migrations/{$migration->uuid}/approve");

        $response->assertStatus(403);
    });

    test('returns 403 for migration not requiring approval', function () {
        $migration = createMigration(['requires_approval' => false]);

        $response = $this->withHeaders(authHeaders())
            ->postJson("/api/v1/migrations/{$migration->uuid}/approve");

        // Policy checks isAwaitingApproval(): requires_approval=false → denies
        $response->assertStatus(403);
    });
});

// ─── POST /api/v1/migrations/{uuid}/reject ───────────────────────────────────

describe('POST /api/v1/migrations/{uuid}/reject', function () {
    test('owner can reject pending migration', function () {
        $migration = createMigration(['requires_approval' => true]);

        $response = $this->withHeaders(authHeaders())
            ->postJson("/api/v1/migrations/{$migration->uuid}/reject", [
                'reason' => 'Not ready for UAT yet.',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Migration rejected.');

        $migration->refresh();
        expect($migration->status)->toBe(EnvironmentMigration::STATUS_REJECTED);
        expect($migration->rejection_reason)->toBe('Not ready for UAT yet.');
    });

    test('returns 422 when reason is missing', function () {
        $migration = createMigration(['requires_approval' => true]);

        $response = $this->withHeaders(authHeaders())
            ->postJson("/api/v1/migrations/{$migration->uuid}/reject", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reason']);
    });

    test('returns 403 when rejecting non-pending migration', function () {
        $migration = createMigration([
            'requires_approval' => true,
            'status' => EnvironmentMigration::STATUS_COMPLETED,
        ]);

        $response = $this->withHeaders(authHeaders())
            ->postJson("/api/v1/migrations/{$migration->uuid}/reject", [
                'reason' => 'Too late.',
            ]);

        // The policy returns false for non-pending, so 403
        $response->assertStatus(403);
    });

    test('returns 403 when non-admin tries to reject', function () {
        $member = User::factory()->create();
        $this->team->members()->attach($member->id, ['role' => 'member']);
        $memberToken = $member->createToken('member-token', ['*']);

        $migration = createMigration(['requires_approval' => true]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$memberToken->plainTextToken,
            'Content-Type' => 'application/json',
        ])->postJson("/api/v1/migrations/{$migration->uuid}/reject", [
            'reason' => 'Not allowed.',
        ]);

        $response->assertStatus(403);
    });
});

// ─── POST /api/v1/migrations/{uuid}/rollback ─────────────────────────────────

describe('POST /api/v1/migrations/{uuid}/rollback', function () {
    test('returns 403 for non-completed migration', function () {
        $migration = createMigration([
            'status' => EnvironmentMigration::STATUS_IN_PROGRESS,
        ]);

        $response = $this->withHeaders(authHeaders())
            ->postJson("/api/v1/migrations/{$migration->uuid}/rollback");

        // Policy checks canBeRolledBack() first — in_progress can't be rolled back
        $response->assertStatus(403);
    });

    test('returns 403 for completed migration without rollback_snapshot', function () {
        $migration = createMigration(['status' => EnvironmentMigration::STATUS_COMPLETED]);

        // canBeRolledBack returns false when snapshot is null → policy returns false → 403
        $response = $this->withHeaders(authHeaders())
            ->postJson("/api/v1/migrations/{$migration->uuid}/rollback");

        $response->assertStatus(403);
    });

    test('returns 404 for non-existent migration', function () {
        $response = $this->withHeaders(authHeaders())
            ->postJson('/api/v1/migrations/non-existent-uuid/rollback');

        $response->assertStatus(404);
    });
});

// ─── GET /api/v1/migrations/pending ──────────────────────────────────────────

describe('GET /api/v1/migrations/pending', function () {
    test('returns pending migrations requiring approval', function () {
        createMigration(['requires_approval' => true]);

        // Second migration needs different source to avoid unique constraint
        $app2Id = DB::table('applications')->insertGetId([
            'uuid' => (string) new \Visus\Cuid2\Cuid2,
            'name' => 'test-app2-'.uniqid(),
            'environment_id' => $this->devEnv->id,
            'destination_id' => $this->docker->id,
            'destination_type' => StandaloneDocker::class,
            'git_repository' => 'https://github.com/test/test2.git',
            'git_branch' => 'main',
            'build_pack' => 'nixpacks',
            'ports_exposes' => '3000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        createMigration(['source_id' => $app2Id, 'requires_approval' => false]);

        $response = $this->withHeaders(authHeaders())
            ->getJson('/api/v1/migrations/pending');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    });
});

// ─── POST /api/v1/migrations/check ──────────────────────────────────────────

describe('POST /api/v1/migrations/check', function () {
    test('returns check result for valid migration', function () {
        $response = $this->withHeaders(authHeaders())
            ->postJson('/api/v1/migrations/check', [
                'source_type' => 'application',
                'source_uuid' => $this->testApp->uuid,
                'target_environment_id' => $this->uatEnv->id,
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'allowed',
            'requires_approval',
            'source' => ['name', 'type', 'environment'],
            'target' => ['environment', 'environment_type'],
        ]);
    });

    test('returns 404 for non-existent source', function () {
        $response = $this->withHeaders(authHeaders())
            ->postJson('/api/v1/migrations/check', [
                'source_type' => 'application',
                'source_uuid' => 'fake-uuid',
                'target_environment_id' => $this->uatEnv->id,
            ]);

        $response->assertStatus(404);
    });
});

// ─── GET /api/v1/migrations/targets/{source_type}/{source_uuid} ──────────────

describe('GET /api/v1/migrations/targets', function () {
    test('returns available target environments', function () {
        $response = $this->withHeaders(authHeaders())
            ->getJson("/api/v1/migrations/targets/application/{$this->testApp->uuid}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'source' => ['name', 'type', 'environment', 'environment_type'],
            'target_environments',
            'servers',
        ]);
    });

    test('returns 404 for non-existent resource', function () {
        $response = $this->withHeaders(authHeaders())
            ->getJson('/api/v1/migrations/targets/application/fake-uuid');

        $response->assertStatus(404);
    });
});

// ─── Cross-team isolation ────────────────────────────────────────────────────

describe('Cross-team isolation', function () {
    test('cannot approve migration from another team', function () {
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnv = Environment::factory()->create([
            'project_id' => $otherProject->id,
            'type' => 'development',
        ]);
        $otherUatEnv = Environment::factory()->create([
            'project_id' => $otherProject->id,
            'type' => 'uat',
        ]);

        $otherAppId = DB::table('applications')->insertGetId([
            'uuid' => (string) new \Visus\Cuid2\Cuid2,
            'name' => 'cross-team-app-'.uniqid(),
            'environment_id' => $otherEnv->id,
            'destination_id' => $this->docker->id,
            'destination_type' => StandaloneDocker::class,
            'git_repository' => 'https://github.com/test/cross.git',
            'git_branch' => 'main',
            'build_pack' => 'nixpacks',
            'ports_exposes' => '3000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = createMigration([
            'source_id' => $otherAppId,
            'source_environment_id' => $otherEnv->id,
            'target_environment_id' => $otherUatEnv->id,
            'requires_approval' => true,
            'team_id' => $otherTeam->id,
        ]);

        $response = $this->withHeaders(authHeaders())
            ->postJson("/api/v1/migrations/{$migration->uuid}/approve");

        $response->assertStatus(404);
    });

    test('cannot cancel migration from another team', function () {
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnv = Environment::factory()->create([
            'project_id' => $otherProject->id,
            'type' => 'development',
        ]);
        $otherUatEnv = Environment::factory()->create([
            'project_id' => $otherProject->id,
            'type' => 'uat',
        ]);

        $otherAppId = DB::table('applications')->insertGetId([
            'uuid' => (string) new \Visus\Cuid2\Cuid2,
            'name' => 'cross-team-app2-'.uniqid(),
            'environment_id' => $otherEnv->id,
            'destination_id' => $this->docker->id,
            'destination_type' => StandaloneDocker::class,
            'git_repository' => 'https://github.com/test/cross2.git',
            'git_branch' => 'main',
            'build_pack' => 'nixpacks',
            'ports_exposes' => '3000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = createMigration([
            'source_id' => $otherAppId,
            'source_environment_id' => $otherEnv->id,
            'target_environment_id' => $otherUatEnv->id,
            'team_id' => $otherTeam->id,
        ]);

        $response = $this->withHeaders(authHeaders())
            ->postJson("/api/v1/migrations/{$migration->uuid}/cancel");

        $response->assertStatus(404);
    });
});

// ─── Status state machine ────────────────────────────────────────────────────

describe('Status state machine', function () {
    test('cancel succeeds for approved status', function () {
        $migration = createMigration([
            'requires_approval' => true,
            'status' => EnvironmentMigration::STATUS_APPROVED,
            'approved_by' => $this->user->id,
            'approved_at' => now(),
        ]);

        $response = $this->withHeaders(authHeaders())
            ->postJson("/api/v1/migrations/{$migration->uuid}/cancel");

        $response->assertStatus(200);

        $migration->refresh();
        expect($migration->status)->toBe(EnvironmentMigration::STATUS_CANCELLED);
    });

    test('cancel fails for in_progress status', function () {
        $migration = createMigration([
            'status' => EnvironmentMigration::STATUS_IN_PROGRESS,
        ]);

        $response = $this->withHeaders(authHeaders())
            ->postJson("/api/v1/migrations/{$migration->uuid}/cancel");

        $response->assertStatus(400);
    });

    test('cancel fails for rejected status', function () {
        $migration = createMigration([
            'status' => EnvironmentMigration::STATUS_REJECTED,
        ]);

        $response = $this->withHeaders(authHeaders())
            ->postJson("/api/v1/migrations/{$migration->uuid}/cancel");

        $response->assertStatus(400);
    });
});
