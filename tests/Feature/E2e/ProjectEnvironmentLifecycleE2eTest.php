<?php

/**
 * E2E Project → Environment → Resource Lifecycle Tests
 *
 * Tests multi-step integration scenarios that cover the full project lifecycle:
 * - Project creation → environment management → resource attachment → cleanup
 * - Cross-environment resource isolation within a project
 * - Multi-project management and ordering
 * - Cross-team complete project isolation (IDOR prevention)
 * - API token ability enforcement for project/environment operations
 * - Project update and rename flows preserving identity (UUID immutability)
 * - Cascading protection: cannot delete project/environment with active resources
 *
 * These tests go BEYOND the individual CRUD tests in ProjectApiTest.php by
 * chaining multiple API calls into realistic end-to-end workflows.
 */

use App\Models\Application;
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

// ─── Helper ──────────────────────────────────────────────────────────────────

function projEnvHeaders(string $bearerToken): array
{
    return [
        'Authorization' => 'Bearer '.$bearerToken,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ];
}

// ─── Setup ───────────────────────────────────────────────────────────────────

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

    // Infrastructure for creating resources
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

// ─── 1. Full Project → Environment → Resource Lifecycle ─────────────────────

describe('Full project → environment → resource lifecycle', function () {
    test('create project → add envs → attach resources → delete env → remove resources → delete project', function () {
        $headers = projEnvHeaders($this->bearerToken);

        // Step 1: Create project
        $projectResponse = $this->withHeaders($headers)
            ->postJson('/api/v1/projects', [
                'name' => 'Lifecycle Project',
                'description' => 'Full lifecycle E2E test',
            ]);
        $projectResponse->assertStatus(201);
        $projectUuid = $projectResponse->json('uuid');
        expect($projectUuid)->toBeString()->not->toBeEmpty();

        // Step 2: Verify project was created with default environments (development, uat, production)
        $getProjectResponse = $this->withHeaders($headers)
            ->getJson("/api/v1/projects/{$projectUuid}");
        $getProjectResponse->assertStatus(200);
        $defaultEnvs = $getProjectResponse->json('environments');
        expect($defaultEnvs)->toHaveCount(3);

        // Step 3: Add a custom staging environment
        $stagingResponse = $this->withHeaders($headers)
            ->postJson("/api/v1/projects/{$projectUuid}/environments", [
                'name' => 'staging',
            ]);
        $stagingResponse->assertStatus(201);
        $stagingUuid = $stagingResponse->json('uuid');

        // Step 4: Verify project now shows 4 environments
        $envListResponse = $this->withHeaders($headers)
            ->getJson("/api/v1/projects/{$projectUuid}/environments");
        $envListResponse->assertStatus(200);
        $envListResponse->assertJsonCount(4);

        // Step 5: Add application to the production environment
        $project = Project::where('uuid', $projectUuid)->first();
        $prodEnv = $project->environments()->where('name', 'production')->first();

        $app = Application::factory()->create([
            'name' => 'prod-app',
            'environment_id' => $prodEnv->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '3000',
        ]);

        // Step 6: Add PostgreSQL DB to staging environment
        $stagingEnv = Environment::where('uuid', $stagingUuid)->first();
        $db = StandalonePostgresql::factory()->create([
            'name' => 'staging-db',
            'environment_id' => $stagingEnv->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
        ]);

        // Step 7: Verify project shows both envs with resources
        $prodEnvResponse = $this->withHeaders($headers)
            ->getJson("/api/v1/projects/{$projectUuid}/production");
        $prodEnvResponse->assertStatus(200);
        expect($prodEnvResponse->json('applications'))->toHaveCount(1);
        expect($prodEnvResponse->json('applications.0.name'))->toBe('prod-app');

        $stagingEnvResponse = $this->withHeaders($headers)
            ->getJson("/api/v1/projects/{$projectUuid}/staging");
        $stagingEnvResponse->assertStatus(200);
        expect($stagingEnvResponse->json('postgresqls'))->toHaveCount(1);

        // Step 8: Delete staging env should fail (has resources)
        $deleteEnvResponse = $this->withHeaders($headers)
            ->deleteJson("/api/v1/projects/{$projectUuid}/environments/staging");
        $deleteEnvResponse->assertStatus(400);
        $deleteEnvResponse->assertJson(['message' => 'Environment has resources, so it cannot be deleted.']);

        // Step 9: Remove DB from staging
        $db->forceDelete();

        // Step 10: Delete staging env now succeeds
        $deleteEnvResponse = $this->withHeaders($headers)
            ->deleteJson("/api/v1/projects/{$projectUuid}/environments/staging");
        $deleteEnvResponse->assertStatus(200);

        // Step 11: Production is still intact
        $prodStillExists = $this->withHeaders($headers)
            ->getJson("/api/v1/projects/{$projectUuid}/production");
        $prodStillExists->assertStatus(200);
        expect($prodStillExists->json('applications'))->toHaveCount(1);

        // Step 12: Delete project should fail (production still has app)
        $deleteProjectResponse = $this->withHeaders($headers)
            ->deleteJson("/api/v1/projects/{$projectUuid}");
        $deleteProjectResponse->assertStatus(400);
        $deleteProjectResponse->assertJson(['message' => 'Project has resources, so it cannot be deleted.']);

        // Step 13: Remove the app from production
        $app->forceDelete();

        // Step 14: Delete project succeeds
        $deleteProjectResponse = $this->withHeaders($headers)
            ->deleteJson("/api/v1/projects/{$projectUuid}");
        $deleteProjectResponse->assertStatus(200);
        $deleteProjectResponse->assertJson(['message' => 'Project deleted.']);

        // Step 15: Verify project no longer exists
        $this->withHeaders($headers)
            ->getJson("/api/v1/projects/{$projectUuid}")
            ->assertStatus(404);
    });
});

// ─── 2. Environment-Based Resource Isolation ─────────────────────────────────

describe('Environment-based resource isolation', function () {
    test('resources in env1 are not visible in env2 and vice versa', function () {
        $headers = projEnvHeaders($this->bearerToken);

        // Create project with two custom environments
        $projectResponse = $this->withHeaders($headers)
            ->postJson('/api/v1/projects', ['name' => 'Isolation Test']);
        $projectResponse->assertStatus(201);
        $projectUuid = $projectResponse->json('uuid');

        $project = Project::where('uuid', $projectUuid)->first();
        $devEnv = $project->environments()->where('name', 'development')->first();
        $uatEnv = $project->environments()->where('name', 'uat')->first();

        // Add application to development env
        $app = Application::factory()->create([
            'name' => 'dev-only-app',
            'environment_id' => $devEnv->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '3000',
        ]);

        // Add PostgreSQL DB to uat env
        $db = StandalonePostgresql::factory()->create([
            'name' => 'uat-only-db',
            'environment_id' => $uatEnv->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
        ]);

        // GET development env: should only show the application, no databases
        $devResponse = $this->withHeaders($headers)
            ->getJson("/api/v1/projects/{$projectUuid}/development");
        $devResponse->assertStatus(200);
        expect($devResponse->json('applications'))->toHaveCount(1);
        expect($devResponse->json('applications.0.name'))->toBe('dev-only-app');
        expect($devResponse->json('postgresqls'))->toHaveCount(0);

        // GET uat env: should only show the database, no applications
        $uatResponse = $this->withHeaders($headers)
            ->getJson("/api/v1/projects/{$projectUuid}/uat");
        $uatResponse->assertStatus(200);
        expect($uatResponse->json('applications'))->toHaveCount(0);
        expect($uatResponse->json('postgresqls'))->toHaveCount(1);
    });

    test('deleting one environment does not affect resources in another', function () {
        $headers = projEnvHeaders($this->bearerToken);

        $projectResponse = $this->withHeaders($headers)
            ->postJson('/api/v1/projects', ['name' => 'Env Delete Isolation']);
        $projectUuid = $projectResponse->json('uuid');
        $project = Project::where('uuid', $projectUuid)->first();

        // Add custom environment
        $this->withHeaders($headers)
            ->postJson("/api/v1/projects/{$projectUuid}/environments", ['name' => 'ephemeral']);

        $devEnv = $project->environments()->where('name', 'development')->first();

        // Add app to development
        Application::factory()->create([
            'name' => 'persistent-app',
            'environment_id' => $devEnv->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '8080',
        ]);

        // Delete the empty ephemeral env
        $this->withHeaders($headers)
            ->deleteJson("/api/v1/projects/{$projectUuid}/environments/ephemeral")
            ->assertStatus(200);

        // Development app is still accessible
        $devResponse = $this->withHeaders($headers)
            ->getJson("/api/v1/projects/{$projectUuid}/development");
        $devResponse->assertStatus(200);
        expect($devResponse->json('applications'))->toHaveCount(1);
        expect($devResponse->json('applications.0.name'))->toBe('persistent-app');
    });
});

// ─── 3. Multi-Project Management ─────────────────────────────────────────────

describe('Multi-project management', function () {
    test('create 3 projects → list returns all → update each → delete in reverse → count decreases', function () {
        $headers = projEnvHeaders($this->bearerToken);

        // Create 3 projects
        $uuids = [];
        foreach (['Alpha', 'Beta', 'Gamma'] as $name) {
            $resp = $this->withHeaders($headers)
                ->postJson('/api/v1/projects', ['name' => "Project {$name}"]);
            $resp->assertStatus(201);
            $uuids[] = $resp->json('uuid');
        }

        // List returns all 3
        $listResp = $this->withHeaders($headers)->getJson('/api/v1/projects');
        $listResp->assertStatus(200);
        $listResp->assertJsonCount(3);

        // Update each project name
        foreach ($uuids as $i => $uuid) {
            $updateResp = $this->withHeaders($headers)
                ->patchJson("/api/v1/projects/{$uuid}", [
                    'name' => "Updated Project #{$i}",
                ]);
            $updateResp->assertStatus(201);
            expect($updateResp->json('name'))->toBe("Updated Project #{$i}");
        }

        // Delete in reverse order (Gamma, Beta, Alpha)
        for ($i = count($uuids) - 1; $i >= 0; $i--) {
            $this->withHeaders($headers)
                ->deleteJson("/api/v1/projects/{$uuids[$i]}")
                ->assertStatus(200);

            // Verify decreasing count
            $remaining = $this->withHeaders($headers)->getJson('/api/v1/projects');
            $remaining->assertJsonCount($i);
        }

        // Final: list is empty
        $this->withHeaders($headers)->getJson('/api/v1/projects')
            ->assertJsonCount(0);
    });
});

// ─── 4. Project with Multiple Environments Lifecycle ─────────────────────────

describe('Project with multiple environments lifecycle', function () {
    test('create project → add custom envs → list envs shows all → delete one → others remain', function () {
        $headers = projEnvHeaders($this->bearerToken);

        // Create project (auto-creates development, uat, production)
        $projectResp = $this->withHeaders($headers)
            ->postJson('/api/v1/projects', ['name' => 'Multi Env Project']);
        $projectResp->assertStatus(201);
        $projectUuid = $projectResp->json('uuid');

        // Add custom environments
        $this->withHeaders($headers)
            ->postJson("/api/v1/projects/{$projectUuid}/environments", ['name' => 'staging'])
            ->assertStatus(201);

        $this->withHeaders($headers)
            ->postJson("/api/v1/projects/{$projectUuid}/environments", ['name' => 'qa'])
            ->assertStatus(201);

        // List envs returns 5 (3 default + 2 custom)
        $envListResp = $this->withHeaders($headers)
            ->getJson("/api/v1/projects/{$projectUuid}/environments");
        $envListResp->assertStatus(200);
        $envListResp->assertJsonCount(5);

        $envNames = array_column($envListResp->json(), 'name');
        expect($envNames)->toContain('development');
        expect($envNames)->toContain('uat');
        expect($envNames)->toContain('production');
        expect($envNames)->toContain('staging');
        expect($envNames)->toContain('qa');

        // Delete qa environment
        $this->withHeaders($headers)
            ->deleteJson("/api/v1/projects/{$projectUuid}/environments/qa")
            ->assertStatus(200);

        // Remaining envs: 4
        $envListResp = $this->withHeaders($headers)
            ->getJson("/api/v1/projects/{$projectUuid}/environments");
        $envListResp->assertJsonCount(4);

        $remainingNames = array_column($envListResp->json(), 'name');
        expect($remainingNames)->not->toContain('qa');
        expect($remainingNames)->toContain('staging');
        expect($remainingNames)->toContain('development');
        expect($remainingNames)->toContain('production');
    });

    test('cannot create duplicate environment name in same project', function () {
        $headers = projEnvHeaders($this->bearerToken);

        $projectResp = $this->withHeaders($headers)
            ->postJson('/api/v1/projects', ['name' => 'Dup Env Project']);
        $projectUuid = $projectResp->json('uuid');

        // Create 'staging' env
        $this->withHeaders($headers)
            ->postJson("/api/v1/projects/{$projectUuid}/environments", ['name' => 'staging'])
            ->assertStatus(201);

        // Attempt duplicate
        $this->withHeaders($headers)
            ->postJson("/api/v1/projects/{$projectUuid}/environments", ['name' => 'staging'])
            ->assertStatus(409)
            ->assertJson(['message' => 'Environment with this name already exists.']);
    });

    test('environment names in different projects are independent', function () {
        $headers = projEnvHeaders($this->bearerToken);

        $project1Resp = $this->withHeaders($headers)
            ->postJson('/api/v1/projects', ['name' => 'Project One']);
        $project1Uuid = $project1Resp->json('uuid');

        $project2Resp = $this->withHeaders($headers)
            ->postJson('/api/v1/projects', ['name' => 'Project Two']);
        $project2Uuid = $project2Resp->json('uuid');

        // Both projects can have 'staging' environment
        $this->withHeaders($headers)
            ->postJson("/api/v1/projects/{$project1Uuid}/environments", ['name' => 'staging'])
            ->assertStatus(201);

        $this->withHeaders($headers)
            ->postJson("/api/v1/projects/{$project2Uuid}/environments", ['name' => 'staging'])
            ->assertStatus(201);
    });
});

// ─── 5. Cross-Team Complete Project Isolation ────────────────────────────────

describe('Cross-team complete project isolation', function () {
    test('team B cannot access team A projects, environments, or create envs in team A project', function () {
        $headersA = projEnvHeaders($this->bearerToken);

        // Team A creates project with resources
        $projectResp = $this->withHeaders($headersA)
            ->postJson('/api/v1/projects', ['name' => 'Team A Secret']);
        $projectResp->assertStatus(201);
        $projectAUuid = $projectResp->json('uuid');

        $this->withHeaders($headersA)
            ->postJson("/api/v1/projects/{$projectAUuid}/environments", ['name' => 'staging'])
            ->assertStatus(201);

        // Set up Team B
        $teamB = Team::factory()->create();
        $userB = User::factory()->create();
        $teamB->members()->attach($userB->id, ['role' => 'owner']);
        $tokenB = $userB->createToken('team-b-token', ['*']);
        $headersB = projEnvHeaders($tokenB->plainTextToken);

        // Team B: cannot list Team A's project
        $teamBList = $this->withHeaders($headersB)->getJson('/api/v1/projects');
        $teamBList->assertStatus(200);
        $teamBList->assertJsonCount(0);

        // Team B: cannot get Team A's project by UUID
        $this->withHeaders($headersB)
            ->getJson("/api/v1/projects/{$projectAUuid}")
            ->assertStatus(404)
            ->assertJson(['message' => 'Project not found.']);

        // Team B: cannot list environments in Team A's project
        $this->withHeaders($headersB)
            ->getJson("/api/v1/projects/{$projectAUuid}/environments")
            ->assertStatus(404)
            ->assertJson(['message' => 'Project not found.']);

        // Team B: cannot get environment details in Team A's project
        $this->withHeaders($headersB)
            ->getJson("/api/v1/projects/{$projectAUuid}/development")
            ->assertStatus(404)
            ->assertJson(['message' => 'Project not found.']);

        // Team B: cannot create env in Team A's project
        $this->withHeaders($headersB)
            ->postJson("/api/v1/projects/{$projectAUuid}/environments", ['name' => 'injected'])
            ->assertStatus(404)
            ->assertJson(['message' => 'Project not found.']);

        // Team B: cannot delete env in Team A's project
        $this->withHeaders($headersB)
            ->deleteJson("/api/v1/projects/{$projectAUuid}/environments/staging")
            ->assertStatus(404)
            ->assertJson(['message' => 'Project not found.']);

        // Team B: cannot update Team A's project
        $this->withHeaders($headersB)
            ->patchJson("/api/v1/projects/{$projectAUuid}", ['name' => 'Hacked!'])
            ->assertStatus(404)
            ->assertJson(['message' => 'Project not found.']);

        // Team B: cannot delete Team A's project
        $this->withHeaders($headersB)
            ->deleteJson("/api/v1/projects/{$projectAUuid}")
            ->assertStatus(404)
            ->assertJson(['message' => 'Project not found.']);

        // Verify Team A's project is untouched
        $verifyResp = $this->withHeaders($headersA)
            ->getJson("/api/v1/projects/{$projectAUuid}");
        $verifyResp->assertStatus(200);
        expect($verifyResp->json('name'))->toBe('Team A Secret');
    });

    test('team A and team B can independently manage same-named projects', function () {
        $headersA = projEnvHeaders($this->bearerToken);

        // Team A creates 'Shared Name' project
        $projectARsp = $this->withHeaders($headersA)
            ->postJson('/api/v1/projects', ['name' => 'Shared Name']);
        $projectARsp->assertStatus(201);

        // Set up Team B
        $teamB = Team::factory()->create();
        $userB = User::factory()->create();
        $teamB->members()->attach($userB->id, ['role' => 'owner']);
        $tokenB = $userB->createToken('team-b-token', ['*']);
        $headersB = projEnvHeaders($tokenB->plainTextToken);

        // Team B creates 'Shared Name' project — no conflict
        $projectBRsp = $this->withHeaders($headersB)
            ->postJson('/api/v1/projects', ['name' => 'Shared Name']);
        $projectBRsp->assertStatus(201);

        // UUIDs are different
        expect($projectARsp->json('uuid'))->not->toBe($projectBRsp->json('uuid'));

        // Each team sees only their project
        $teamAList = $this->withHeaders($headersA)->getJson('/api/v1/projects');
        $teamAList->assertJsonCount(1);

        $teamBList = $this->withHeaders($headersB)->getJson('/api/v1/projects');
        $teamBList->assertJsonCount(1);
    });
});

// ─── 6. Token Ability Enforcement for Projects ──────────────────────────────

describe('Token ability enforcement for projects', function () {
    test('read token can list projects and get project details', function () {
        $readToken = $this->user->createToken('read-only', ['read']);
        $headers = projEnvHeaders($readToken->plainTextToken);

        // Create a project first (using full-access token)
        $fullHeaders = projEnvHeaders($this->bearerToken);
        $projectResp = $this->withHeaders($fullHeaders)
            ->postJson('/api/v1/projects', ['name' => 'Readable Project']);
        $projectUuid = $projectResp->json('uuid');

        // Read token: can list projects
        $this->withHeaders($headers)->getJson('/api/v1/projects')
            ->assertStatus(200)
            ->assertJsonCount(1);

        // Read token: can get project details
        $this->withHeaders($headers)->getJson("/api/v1/projects/{$projectUuid}")
            ->assertStatus(200)
            ->assertJsonPath('name', 'Readable Project');

        // Read token: can list environments
        $this->withHeaders($headers)
            ->getJson("/api/v1/projects/{$projectUuid}/environments")
            ->assertStatus(200);

        // Read token: can get environment details
        $this->withHeaders($headers)
            ->getJson("/api/v1/projects/{$projectUuid}/development")
            ->assertStatus(200);
    });

    test('read token cannot create, update, or delete projects', function () {
        $readToken = $this->user->createToken('read-only', ['read']);
        $headers = projEnvHeaders($readToken->plainTextToken);

        // Read token: cannot create project
        $this->withHeaders($headers)
            ->postJson('/api/v1/projects', ['name' => 'Unauthorized'])
            ->assertStatus(403);

        // Create a project with full-access token for update/delete tests
        $fullHeaders = projEnvHeaders($this->bearerToken);
        $projectResp = $this->withHeaders($fullHeaders)
            ->postJson('/api/v1/projects', ['name' => 'Target']);
        $projectUuid = $projectResp->json('uuid');

        // Read token: cannot update project
        $this->withHeaders($headers)
            ->patchJson("/api/v1/projects/{$projectUuid}", ['name' => 'Hacked'])
            ->assertStatus(403);

        // Read token: cannot delete project
        $this->withHeaders($headers)
            ->deleteJson("/api/v1/projects/{$projectUuid}")
            ->assertStatus(403);

        // Read token: cannot create environment
        $this->withHeaders($headers)
            ->postJson("/api/v1/projects/{$projectUuid}/environments", ['name' => 'nope'])
            ->assertStatus(403);

        // Read token: cannot delete environment
        $this->withHeaders($headers)
            ->deleteJson("/api/v1/projects/{$projectUuid}/environments/development")
            ->assertStatus(403);
    });

    test('write token can create, update, and delete projects and environments', function () {
        $writeToken = $this->user->createToken('write-token', ['write']);
        $headers = projEnvHeaders($writeToken->plainTextToken);

        // Write token: can create project
        $projectResp = $this->withHeaders($headers)
            ->postJson('/api/v1/projects', ['name' => 'Write Project']);
        $projectResp->assertStatus(201);
        $projectUuid = $projectResp->json('uuid');

        // Write token: can update project
        $this->withHeaders($headers)
            ->patchJson("/api/v1/projects/{$projectUuid}", ['name' => 'Updated Write'])
            ->assertStatus(201);

        // Write token: can create environment
        $this->withHeaders($headers)
            ->postJson("/api/v1/projects/{$projectUuid}/environments", ['name' => 'staging'])
            ->assertStatus(201);

        // Write token: can delete environment
        $this->withHeaders($headers)
            ->deleteJson("/api/v1/projects/{$projectUuid}/environments/staging")
            ->assertStatus(200);

        // Write token: can delete project
        $this->withHeaders($headers)
            ->deleteJson("/api/v1/projects/{$projectUuid}")
            ->assertStatus(200);
    });

    test('deploy token cannot manage projects or environments', function () {
        $deployToken = $this->user->createToken('deploy-token', ['deploy']);
        $headers = projEnvHeaders($deployToken->plainTextToken);

        // Deploy token: cannot list projects (requires read ability)
        $this->withHeaders($headers)->getJson('/api/v1/projects')
            ->assertStatus(403);

        // Deploy token: cannot create project
        $this->withHeaders($headers)
            ->postJson('/api/v1/projects', ['name' => 'Deploy Attempt'])
            ->assertStatus(403);
    });
});

// ─── 7. Environment with Database and Application ────────────────────────────

describe('Environment with database and application', function () {
    test('add PG DB and app to same env → GET shows both → delete DB → app remains → delete app → env empty', function () {
        $headers = projEnvHeaders($this->bearerToken);

        // Create project
        $projectResp = $this->withHeaders($headers)
            ->postJson('/api/v1/projects', ['name' => 'Mixed Resources']);
        $projectUuid = $projectResp->json('uuid');
        $project = Project::where('uuid', $projectUuid)->first();
        $devEnv = $project->environments()->where('name', 'development')->first();

        // Add PostgreSQL database to development
        $db = StandalonePostgresql::factory()->create([
            'name' => 'dev-postgres',
            'environment_id' => $devEnv->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
        ]);

        // Add application to same environment
        $app = Application::factory()->create([
            'name' => 'dev-app',
            'environment_id' => $devEnv->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '3000',
        ]);

        // GET env: shows both
        $envResp = $this->withHeaders($headers)
            ->getJson("/api/v1/projects/{$projectUuid}/development");
        $envResp->assertStatus(200);
        expect($envResp->json('applications'))->toHaveCount(1);
        expect($envResp->json('postgresqls'))->toHaveCount(1);

        // Environment is not empty, cannot delete
        $this->withHeaders($headers)
            ->deleteJson("/api/v1/projects/{$projectUuid}/environments/development")
            ->assertStatus(400);

        // Delete the database
        $db->forceDelete();

        // GET env: only app remains
        $envResp = $this->withHeaders($headers)
            ->getJson("/api/v1/projects/{$projectUuid}/development");
        expect($envResp->json('applications'))->toHaveCount(1);
        expect($envResp->json('postgresqls'))->toHaveCount(0);

        // Still cannot delete env (app exists)
        $this->withHeaders($headers)
            ->deleteJson("/api/v1/projects/{$projectUuid}/environments/development")
            ->assertStatus(400);

        // Delete the application
        $app->forceDelete();

        // GET env: empty now
        $envResp = $this->withHeaders($headers)
            ->getJson("/api/v1/projects/{$projectUuid}/development");
        expect($envResp->json('applications'))->toHaveCount(0);
        expect($envResp->json('postgresqls'))->toHaveCount(0);

        // Can delete env now
        $this->withHeaders($headers)
            ->deleteJson("/api/v1/projects/{$projectUuid}/environments/development")
            ->assertStatus(200);
    });

    test('multiple databases in one environment are all visible', function () {
        $headers = projEnvHeaders($this->bearerToken);

        $projectResp = $this->withHeaders($headers)
            ->postJson('/api/v1/projects', ['name' => 'Multi DB']);
        $projectUuid = $projectResp->json('uuid');
        $project = Project::where('uuid', $projectUuid)->first();
        $devEnv = $project->environments()->where('name', 'development')->first();

        // Add 3 PostgreSQL databases
        for ($i = 1; $i <= 3; $i++) {
            StandalonePostgresql::factory()->create([
                'name' => "db-{$i}",
                'environment_id' => $devEnv->id,
                'destination_id' => $this->destination->id,
                'destination_type' => StandaloneDocker::class,
            ]);
        }

        $envResp = $this->withHeaders($headers)
            ->getJson("/api/v1/projects/{$projectUuid}/development");
        $envResp->assertStatus(200);
        expect($envResp->json('postgresqls'))->toHaveCount(3);
    });
});

// ─── 8. Project Update and Rename Flow ───────────────────────────────────────

describe('Project update and rename flow', function () {
    test('update name preserves UUID → update description → list shows updated values', function () {
        $headers = projEnvHeaders($this->bearerToken);

        // Create project
        $createResp = $this->withHeaders($headers)
            ->postJson('/api/v1/projects', [
                'name' => 'Original Name',
                'description' => 'Original Description',
            ]);
        $createResp->assertStatus(201);
        $originalUuid = $createResp->json('uuid');

        // Update name
        $updateResp = $this->withHeaders($headers)
            ->patchJson("/api/v1/projects/{$originalUuid}", [
                'name' => 'Renamed Project',
            ]);
        $updateResp->assertStatus(201);
        expect($updateResp->json('uuid'))->toBe($originalUuid); // UUID unchanged
        expect($updateResp->json('name'))->toBe('Renamed Project');

        // Update description only, name should remain
        $updateResp2 = $this->withHeaders($headers)
            ->patchJson("/api/v1/projects/{$originalUuid}", [
                'description' => 'Updated Description',
            ]);
        $updateResp2->assertStatus(201);
        expect($updateResp2->json('uuid'))->toBe($originalUuid);
        expect($updateResp2->json('name'))->toBe('Renamed Project'); // Name unchanged
        expect($updateResp2->json('description'))->toBe('Updated Description');

        // List projects: shows updated values
        $listResp = $this->withHeaders($headers)->getJson('/api/v1/projects');
        $listResp->assertStatus(200);
        $listResp->assertJsonFragment(['name' => 'Renamed Project']);
        $listResp->assertJsonFragment(['description' => 'Updated Description']);
        $listResp->assertJsonMissing(['name' => 'Original Name']);
    });

    test('multiple sequential updates all preserve the same UUID', function () {
        $headers = projEnvHeaders($this->bearerToken);

        $createResp = $this->withHeaders($headers)
            ->postJson('/api/v1/projects', ['name' => 'UUID Stability']);
        $uuid = $createResp->json('uuid');

        // Multiple updates
        $names = ['First Update', 'Second Update', 'Third Update', 'Final Update'];
        foreach ($names as $name) {
            $resp = $this->withHeaders($headers)
                ->patchJson("/api/v1/projects/{$uuid}", ['name' => $name]);
            $resp->assertStatus(201);
            expect($resp->json('uuid'))->toBe($uuid);
        }

        // Final verification
        $getResp = $this->withHeaders($headers)->getJson("/api/v1/projects/{$uuid}");
        $getResp->assertStatus(200);
        expect($getResp->json('name'))->toBe('Final Update');
        expect($getResp->json('uuid'))->toBe($uuid);
    });
});

// ─── 9. Database Creation via API in Environment ─────────────────────────────

describe('Database creation via API in project environment', function () {
    test('create PostgreSQL via API → verify in environment details', function () {
        $headers = projEnvHeaders($this->bearerToken);

        // Create project
        $projectResp = $this->withHeaders($headers)
            ->postJson('/api/v1/projects', ['name' => 'DB API Test']);
        $projectUuid = $projectResp->json('uuid');

        // Create PostgreSQL via API endpoint
        $dbResp = $this->withHeaders($headers)
            ->postJson('/api/v1/databases/postgresql', [
                'server_uuid' => $this->server->uuid,
                'project_uuid' => $projectUuid,
                'environment_name' => 'development',
                'name' => 'api-created-pg',
                'postgres_user' => 'testuser',
                'postgres_password' => 'testpassword123',
                'postgres_db' => 'testdb',
                'instant_deploy' => false,
            ]);
        $dbResp->assertStatus(200);
        $dbUuid = $dbResp->json('uuid');

        // Verify DB appears in environment details
        $envResp = $this->withHeaders($headers)
            ->getJson("/api/v1/projects/{$projectUuid}/development");
        $envResp->assertStatus(200);
        expect($envResp->json('postgresqls'))->toHaveCount(1);

        // Project cannot be deleted with DB present
        $this->withHeaders($headers)
            ->deleteJson("/api/v1/projects/{$projectUuid}")
            ->assertStatus(400);
    });
});

// ─── 10. Environment Access via UUID and Name ────────────────────────────────

describe('Environment access via UUID and name', function () {
    test('environment is accessible by both name and UUID and returns consistent data', function () {
        $headers = projEnvHeaders($this->bearerToken);

        $projectResp = $this->withHeaders($headers)
            ->postJson('/api/v1/projects', ['name' => 'Env Access Test']);
        $projectUuid = $projectResp->json('uuid');

        // Create custom environment
        $envResp = $this->withHeaders($headers)
            ->postJson("/api/v1/projects/{$projectUuid}/environments", ['name' => 'staging']);
        $envUuid = $envResp->json('uuid');

        // Access by name
        $byNameResp = $this->withHeaders($headers)
            ->getJson("/api/v1/projects/{$projectUuid}/staging");
        $byNameResp->assertStatus(200);

        // Access by UUID
        $byUuidResp = $this->withHeaders($headers)
            ->getJson("/api/v1/projects/{$projectUuid}/{$envUuid}");
        $byUuidResp->assertStatus(200);

        // Both return the same data
        expect($byNameResp->json('uuid'))->toBe($byUuidResp->json('uuid'));
        expect($byNameResp->json('name'))->toBe($byUuidResp->json('name'));
        expect($byNameResp->json('name'))->toBe('staging');
    });

    test('environment delete works by both name and UUID', function () {
        $headers = projEnvHeaders($this->bearerToken);

        $projectResp = $this->withHeaders($headers)
            ->postJson('/api/v1/projects', ['name' => 'Delete Methods']);
        $projectUuid = $projectResp->json('uuid');

        // Create two environments
        $this->withHeaders($headers)
            ->postJson("/api/v1/projects/{$projectUuid}/environments", ['name' => 'env-by-name']);
        $env2Resp = $this->withHeaders($headers)
            ->postJson("/api/v1/projects/{$projectUuid}/environments", ['name' => 'env-by-uuid']);
        $env2Uuid = $env2Resp->json('uuid');

        // Delete by name
        $this->withHeaders($headers)
            ->deleteJson("/api/v1/projects/{$projectUuid}/environments/env-by-name")
            ->assertStatus(200);

        // Delete by UUID
        $this->withHeaders($headers)
            ->deleteJson("/api/v1/projects/{$projectUuid}/environments/{$env2Uuid}")
            ->assertStatus(200);

        // Both are gone
        $this->withHeaders($headers)
            ->getJson("/api/v1/projects/{$projectUuid}/env-by-name")
            ->assertStatus(404);
        $this->withHeaders($headers)
            ->getJson("/api/v1/projects/{$projectUuid}/{$env2Uuid}")
            ->assertStatus(404);
    });
});

// ─── 11. Project Cascade Protection Edge Cases ───────────────────────────────

describe('Project cascade protection edge cases', function () {
    test('project with resources in multiple environments cannot be deleted', function () {
        $headers = projEnvHeaders($this->bearerToken);

        $projectResp = $this->withHeaders($headers)
            ->postJson('/api/v1/projects', ['name' => 'Cascade Protection']);
        $projectUuid = $projectResp->json('uuid');
        $project = Project::where('uuid', $projectUuid)->first();

        $devEnv = $project->environments()->where('name', 'development')->first();
        $prodEnv = $project->environments()->where('name', 'production')->first();

        // App in development
        $app = Application::factory()->create([
            'name' => 'dev-app',
            'environment_id' => $devEnv->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '3000',
        ]);

        // DB in production
        $db = StandalonePostgresql::factory()->create([
            'name' => 'prod-db',
            'environment_id' => $prodEnv->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
        ]);

        // Cannot delete project
        $this->withHeaders($headers)
            ->deleteJson("/api/v1/projects/{$projectUuid}")
            ->assertStatus(400);

        // Remove app only — project still has DB
        $app->forceDelete();
        $this->withHeaders($headers)
            ->deleteJson("/api/v1/projects/{$projectUuid}")
            ->assertStatus(400);

        // Remove DB — project is now empty
        $db->forceDelete();
        $this->withHeaders($headers)
            ->deleteJson("/api/v1/projects/{$projectUuid}")
            ->assertStatus(200)
            ->assertJson(['message' => 'Project deleted.']);
    });

    test('project isEmpty reflects resources across all environments', function () {
        $project = Project::factory()->create(['team_id' => $this->team->id]);
        expect($project->isEmpty())->toBeTrue();

        $devEnv = $project->environments()->where('name', 'development')->first();

        // Add an application
        $app = Application::factory()->create([
            'name' => 'test-isEmpty',
            'environment_id' => $devEnv->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '3000',
        ]);

        // Force refresh to clear any cached state
        $project = Project::find($project->id);
        expect($project->isEmpty())->toBeFalse();

        // Remove app
        $app->forceDelete();
        $project = Project::find($project->id);
        expect($project->isEmpty())->toBeTrue();
    });
});
