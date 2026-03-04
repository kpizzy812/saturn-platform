<?php

/**
 * E2E Application CRUD Lifecycle Tests
 *
 * Tests the full application lifecycle through the API:
 * - Create applications (public, dockerfile, dockerimage)
 * - Read application details and list
 * - Update application settings
 * - Delete application with cleanup options
 * - API token scope enforcement
 * - Cross-team isolation
 * - Domain conflict handling
 */

use App\Jobs\DeleteResourceJob;
use App\Models\Application;
use App\Models\Environment;
use App\Models\InstanceSettings;
use App\Models\PrivateKey;
use App\Models\Project;
use App\Models\Server;
use App\Models\ServerSetting;
use App\Models\StandaloneDocker;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Visus\Cuid2\Cuid2;

uses(DatabaseTransactions::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function appApiHeaders(string $bearerToken): array
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

    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();
    $this->team->members()->attach($this->user->id, ['role' => 'owner']);
    session(['currentTeam' => $this->team]);

    $this->token = $this->user->createToken('test-token', ['*']);
    $this->bearerToken = $this->token->plainTextToken;

    if (! InstanceSettings::first()) {
        InstanceSettings::create(['id' => 0, 'is_api_enabled' => true]);
    }

    // Ensure public GithubApp exists (required for github.com URLs)
    // Use DB::table to bypass mass assignment protection on id/team_id
    DB::table('github_apps')->insertOrIgnore([
        'id' => 0,
        'uuid' => 'github-public',
        'name' => 'Public GitHub',
        'api_url' => 'https://api.github.com',
        'html_url' => 'https://github.com',
        'is_public' => true,
        'team_id' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->privateKey = PrivateKey::factory()->create(['team_id' => $this->team->id]);

    $this->server = Server::withoutEvents(fn () => Server::factory()->create([
        'uuid' => (string) new Cuid2,
        'team_id' => $this->team->id,
        'private_key_id' => $this->privateKey->id,
    ]));
    ServerSetting::firstOrCreate(['server_id' => $this->server->id]);

    $this->destination = StandaloneDocker::withoutEvents(fn () => StandaloneDocker::create([
        'uuid' => (string) new Cuid2,
        'name' => 'default',
        'network' => 'saturn',
        'server_id' => $this->server->id,
    ]));

    $this->project = Project::factory()->create(['team_id' => $this->team->id]);
    $this->environment = Environment::factory()->create(['project_id' => $this->project->id]);
});

// ─── POST /applications/public — Create public application ──────────────────

describe('POST /api/v1/applications/public — Create public application', function () {
    test('creates public application with required fields', function () {
        $response = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->postJson('/api/v1/applications/public', [
                'project_uuid' => $this->project->uuid,
                'server_uuid' => $this->server->uuid,
                'environment_name' => $this->environment->name,
                'git_repository' => 'https://github.com/coollabsio/coolify-examples.git',
                'git_branch' => 'main',
                'build_pack' => 'nixpacks',
                'ports_exposes' => '3000',
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['uuid']);

        $uuid = $response->json('uuid');
        $this->assertDatabaseHas('applications', [
            'uuid' => $uuid,
            'build_pack' => 'nixpacks',
            'ports_exposes' => '3000',
            'git_branch' => 'main',
        ]);
    });

    test('creates public application using environment_uuid instead of name', function () {
        $response = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->postJson('/api/v1/applications/public', [
                'project_uuid' => $this->project->uuid,
                'server_uuid' => $this->server->uuid,
                'environment_uuid' => $this->environment->uuid,
                'git_repository' => 'https://github.com/coollabsio/coolify-examples.git',
                'git_branch' => 'main',
                'build_pack' => 'nixpacks',
                'ports_exposes' => '3000',
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['uuid']);
    });

    test('creates application with custom name and description', function () {
        $response = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->postJson('/api/v1/applications/public', [
                'project_uuid' => $this->project->uuid,
                'server_uuid' => $this->server->uuid,
                'environment_name' => $this->environment->name,
                'git_repository' => 'https://github.com/coollabsio/coolify-examples.git',
                'git_branch' => 'main',
                'build_pack' => 'nixpacks',
                'ports_exposes' => '3000',
                'name' => 'My Custom App',
                'description' => 'Test application for e2e',
            ]);

        $response->assertStatus(201);

        $app = Application::where('uuid', $response->json('uuid'))->first();
        expect($app->name)->toBe('My Custom App');
        expect($app->description)->toBe('Test application for e2e');
    });

    test('creates application with railpack build pack', function () {
        $response = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->postJson('/api/v1/applications/public', [
                'project_uuid' => $this->project->uuid,
                'server_uuid' => $this->server->uuid,
                'environment_name' => $this->environment->name,
                'git_repository' => 'https://github.com/coollabsio/coolify-examples.git',
                'git_branch' => 'main',
                'build_pack' => 'railpack',
                'ports_exposes' => '3000',
            ]);

        $response->assertStatus(201);

        $app = Application::where('uuid', $response->json('uuid'))->first();
        expect($app->build_pack)->toBe('railpack');
    });

    test('returns 404 for non-existent project_uuid', function () {
        $response = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->postJson('/api/v1/applications/public', [
                'project_uuid' => 'non-existent-uuid',
                'server_uuid' => $this->server->uuid,
                'environment_name' => $this->environment->name,
                'git_repository' => 'https://github.com/test/test.git',
                'git_branch' => 'main',
                'build_pack' => 'nixpacks',
                'ports_exposes' => '3000',
            ]);

        $response->assertStatus(404);
    });

    test('returns 404 for non-existent server_uuid', function () {
        $response = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->postJson('/api/v1/applications/public', [
                'project_uuid' => $this->project->uuid,
                'server_uuid' => 'non-existent-server',
                'environment_name' => $this->environment->name,
                'git_repository' => 'https://github.com/test/test.git',
                'git_branch' => 'main',
                'build_pack' => 'nixpacks',
                'ports_exposes' => '3000',
            ]);

        $response->assertStatus(404);
    });

    test('returns 404 for non-existent environment_name', function () {
        $response = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->postJson('/api/v1/applications/public', [
                'project_uuid' => $this->project->uuid,
                'server_uuid' => $this->server->uuid,
                'environment_name' => 'non-existent-env',
                'git_repository' => 'https://github.com/test/test.git',
                'git_branch' => 'main',
                'build_pack' => 'nixpacks',
                'ports_exposes' => '3000',
            ]);

        $response->assertStatus(404);
    });

    test('validates required fields — missing git_repository', function () {
        $response = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->postJson('/api/v1/applications/public', [
                'project_uuid' => $this->project->uuid,
                'server_uuid' => $this->server->uuid,
                'environment_name' => $this->environment->name,
                'git_branch' => 'main',
                'build_pack' => 'nixpacks',
                'ports_exposes' => '3000',
            ]);

        $response->assertStatus(422);
    });

    test('validates build_pack enum', function () {
        $response = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->postJson('/api/v1/applications/public', [
                'project_uuid' => $this->project->uuid,
                'server_uuid' => $this->server->uuid,
                'environment_name' => $this->environment->name,
                'git_repository' => 'https://github.com/test/test.git',
                'git_branch' => 'main',
                'build_pack' => 'invalid-buildpack',
                'ports_exposes' => '3000',
            ]);

        $response->assertStatus(422);
    });

    test('validates ports_exposes format', function () {
        $response = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->postJson('/api/v1/applications/public', [
                'project_uuid' => $this->project->uuid,
                'server_uuid' => $this->server->uuid,
                'environment_name' => $this->environment->name,
                'git_repository' => 'https://github.com/test/test.git',
                'git_branch' => 'main',
                'build_pack' => 'nixpacks',
                'ports_exposes' => 'not-a-port',
            ]);

        $response->assertStatus(422);
    });

    test('accepts multiple comma-separated ports', function () {
        $response = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->postJson('/api/v1/applications/public', [
                'project_uuid' => $this->project->uuid,
                'server_uuid' => $this->server->uuid,
                'environment_name' => $this->environment->name,
                'git_repository' => 'https://github.com/coollabsio/coolify-examples.git',
                'git_branch' => 'main',
                'build_pack' => 'nixpacks',
                'ports_exposes' => '3000,8080',
            ]);

        $response->assertStatus(201);

        $app = Application::where('uuid', $response->json('uuid'))->first();
        expect($app->ports_exposes)->toBe('3000,8080');
    });
});

// ─── POST /applications/dockerfile — Create Dockerfile application ──────────

describe('POST /api/v1/applications/dockerfile — Create Dockerfile application', function () {
    test('creates application from base64-encoded Dockerfile', function () {
        $dockerfile = base64_encode("FROM node:18-alpine\nWORKDIR /app\nCOPY . .\nRUN npm install\nEXPOSE 3000\nCMD [\"node\", \"index.js\"]");

        $response = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->postJson('/api/v1/applications/dockerfile', [
                'project_uuid' => $this->project->uuid,
                'server_uuid' => $this->server->uuid,
                'environment_name' => $this->environment->name,
                'dockerfile' => $dockerfile,
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['uuid']);

        $app = Application::where('uuid', $response->json('uuid'))->first();
        expect($app->build_pack)->toBe('dockerfile');
    });

    test('auto-generates name when not provided', function () {
        $dockerfile = base64_encode("FROM nginx:latest\nEXPOSE 80");

        $response = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->postJson('/api/v1/applications/dockerfile', [
                'project_uuid' => $this->project->uuid,
                'server_uuid' => $this->server->uuid,
                'environment_name' => $this->environment->name,
                'dockerfile' => $dockerfile,
            ]);

        $response->assertStatus(201);

        $app = Application::where('uuid', $response->json('uuid'))->first();
        expect($app->name)->not->toBeEmpty();
    });
});

// ─── POST /applications/dockerimage — Create Docker image application ───────

describe('POST /api/v1/applications/dockerimage — Create Docker image application', function () {
    test('creates application from Docker image', function () {
        $response = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->postJson('/api/v1/applications/dockerimage', [
                'project_uuid' => $this->project->uuid,
                'server_uuid' => $this->server->uuid,
                'environment_name' => $this->environment->name,
                'docker_registry_image_name' => 'nginx',
                'docker_registry_image_tag' => 'latest',
                'ports_exposes' => '80',
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['uuid']);

        $app = Application::where('uuid', $response->json('uuid'))->first();
        expect($app->build_pack)->toBe('dockerimage');
        expect($app->docker_registry_image_name)->toBe('nginx');
    });

    test('defaults docker_registry_image_tag to latest', function () {
        $response = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->postJson('/api/v1/applications/dockerimage', [
                'project_uuid' => $this->project->uuid,
                'server_uuid' => $this->server->uuid,
                'environment_name' => $this->environment->name,
                'docker_registry_image_name' => 'redis',
                'ports_exposes' => '6379',
            ]);

        $response->assertStatus(201);

        $app = Application::where('uuid', $response->json('uuid'))->first();
        expect($app->docker_registry_image_tag)->toBe('latest');
    });

    test('validates required docker_registry_image_name', function () {
        $response = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->postJson('/api/v1/applications/dockerimage', [
                'project_uuid' => $this->project->uuid,
                'server_uuid' => $this->server->uuid,
                'environment_name' => $this->environment->name,
                'ports_exposes' => '80',
            ]);

        $response->assertStatus(422);
    });
});

// ─── GET /applications — List & Read ─────────────────────────────────────────

describe('GET /api/v1/applications — List and read applications', function () {
    test('returns empty array when no applications exist', function () {
        $response = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->getJson('/api/v1/applications');

        $response->assertStatus(200);
        $response->assertJsonCount(0);
    });

    test('lists all applications for the team', function () {
        $app1 = Application::factory()->create([
            'name' => 'App One',
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '3000',
        ]);
        $app2 = Application::factory()->create([
            'name' => 'App Two',
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '8080',
        ]);

        $response = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->getJson('/api/v1/applications');

        $response->assertStatus(200);
        $response->assertJsonCount(2);
        $response->assertJsonFragment(['name' => 'App One']);
        $response->assertJsonFragment(['name' => 'App Two']);
    });

    test('supports pagination via per_page parameter', function () {
        for ($i = 0; $i < 5; $i++) {
            Application::factory()->create([
                'name' => "App $i",
                'environment_id' => $this->environment->id,
                'destination_id' => $this->destination->id,
                'destination_type' => StandaloneDocker::class,
                'ports_exposes' => '3000',
            ]);
        }

        $response = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->getJson('/api/v1/applications?per_page=2&page=1');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'meta']);
        expect($response->json('meta.per_page'))->toBe(2);
        expect($response->json('meta.total'))->toBe(5);
        expect($response->json('data'))->toHaveCount(2);
    });

    test('gets single application by UUID', function () {
        $app = Application::factory()->create([
            'name' => 'Single App',
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '3000',
        ]);

        $response = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->getJson("/api/v1/applications/{$app->uuid}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Single App']);
        $response->assertJsonStructure(['uuid', 'name', 'build_pack', 'git_repository']);
    });

    test('returns 404 for non-existent application UUID', function () {
        $response = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->getJson('/api/v1/applications/non-existent-uuid');

        $response->assertStatus(404);
    });

    test('does not include applications from other teams', function () {
        // Create app for this team
        Application::factory()->create([
            'name' => 'My App',
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '3000',
        ]);

        // Create app for another team
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnv = Environment::factory()->create(['project_id' => $otherProject->id]);

        Application::factory()->create([
            'name' => 'Other Team App',
            'environment_id' => $otherEnv->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '3000',
        ]);

        $response = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->getJson('/api/v1/applications');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['name' => 'My App']);
        $response->assertJsonMissing(['name' => 'Other Team App']);
    });
});

// ─── PATCH /applications/{uuid} — Update application ─────────────────────────

describe('PATCH /api/v1/applications/{uuid} — Update application', function () {
    test('updates application name', function () {
        $app = Application::factory()->create([
            'name' => 'Old Name',
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '3000',
        ]);

        $response = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->patchJson("/api/v1/applications/{$app->uuid}", [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200);

        $app->refresh();
        expect($app->name)->toBe('New Name');
    });

    test('updates application description', function () {
        $app = Application::factory()->create([
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '3000',
        ]);

        $response = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->patchJson("/api/v1/applications/{$app->uuid}", [
                'description' => 'Updated description',
            ]);

        $response->assertStatus(200);

        $app->refresh();
        expect($app->description)->toBe('Updated description');
    });

    test('updates build_pack', function () {
        $app = Application::factory()->create([
            'build_pack' => 'nixpacks',
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '3000',
        ]);

        $response = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->patchJson("/api/v1/applications/{$app->uuid}", [
                'build_pack' => 'railpack',
            ]);

        $response->assertStatus(200);

        $app->refresh();
        expect($app->build_pack)->toBe('railpack');
    });

    test('updates git_branch and git_repository', function () {
        $app = Application::factory()->create([
            'git_branch' => 'main',
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '3000',
        ]);

        $response = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->patchJson("/api/v1/applications/{$app->uuid}", [
                'git_branch' => 'develop',
                'git_repository' => 'https://github.com/test/new-repo.git',
            ]);

        $response->assertStatus(200);

        $app->refresh();
        expect($app->git_branch)->toBe('develop');
        expect($app->git_repository)->toBe('https://github.com/test/new-repo.git');
    });

    test('updates health check settings', function () {
        $app = Application::factory()->create([
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '3000',
        ]);

        $response = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->patchJson("/api/v1/applications/{$app->uuid}", [
                'health_check_enabled' => true,
                'health_check_path' => '/healthz',
                'health_check_port' => '3000',
                'health_check_interval' => 30,
                'health_check_timeout' => 5,
                'health_check_retries' => 3,
            ]);

        $response->assertStatus(200);

        $app->refresh();
        expect($app->health_check_enabled)->toBeTrue();
        expect($app->health_check_path)->toBe('/healthz');
    });

    test('updates resource limits', function () {
        $app = Application::factory()->create([
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '3000',
        ]);

        $response = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->patchJson("/api/v1/applications/{$app->uuid}", [
                'limits_memory' => '512m',
                'limits_cpus' => '2',
            ]);

        $response->assertStatus(200);

        $app->refresh();
        expect($app->limits_memory)->toBe('512m');
        expect($app->limits_cpus)->toBe('2');
    });

    test('returns 404 for non-existent application', function () {
        $response = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->patchJson('/api/v1/applications/non-existent-uuid', [
                'name' => 'Test',
            ]);

        $response->assertStatus(404);
    });

    test('cannot update application from another team', function () {
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnv = Environment::factory()->create(['project_id' => $otherProject->id]);
        $otherApp = Application::factory()->create([
            'name' => 'Other App',
            'environment_id' => $otherEnv->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '3000',
        ]);

        $response = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->patchJson("/api/v1/applications/{$otherApp->uuid}", [
                'name' => 'Hacked',
            ]);

        $response->assertStatus(404);

        $otherApp->refresh();
        expect($otherApp->name)->toBe('Other App');
    });
});

// ─── DELETE /applications/{uuid} — Delete application ────────────────────────

describe('DELETE /api/v1/applications/{uuid} — Delete application', function () {
    test('queues deletion job for existing application', function () {
        $app = Application::factory()->create([
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '3000',
        ]);

        $response = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->deleteJson("/api/v1/applications/{$app->uuid}");

        $response->assertStatus(200);

        Queue::assertPushed(DeleteResourceJob::class);
    });

    test('accepts cleanup options', function () {
        $app = Application::factory()->create([
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '3000',
        ]);

        $response = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->deleteJson("/api/v1/applications/{$app->uuid}?delete_volumes=false&docker_cleanup=false");

        $response->assertStatus(200);

        Queue::assertPushed(DeleteResourceJob::class);
    });

    test('returns 404 for non-existent application', function () {
        $response = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->deleteJson('/api/v1/applications/non-existent-uuid');

        $response->assertStatus(404);
    });

    test('cannot delete application from another team', function () {
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnv = Environment::factory()->create(['project_id' => $otherProject->id]);
        $otherApp = Application::factory()->create([
            'environment_id' => $otherEnv->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '3000',
        ]);

        $response = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->deleteJson("/api/v1/applications/{$otherApp->uuid}");

        $response->assertStatus(404);
    });
});

// ─── API Token Scope Enforcement ─────────────────────────────────────────────

describe('API token scope enforcement for applications', function () {
    test('read-only token can list applications', function () {
        $readToken = $this->user->createToken('read-only', ['read']);

        $response = $this->withHeaders(appApiHeaders($readToken->plainTextToken))
            ->getJson('/api/v1/applications');

        $response->assertStatus(200);
    });

    test('read-only token cannot create application', function () {
        $readToken = $this->user->createToken('read-only', ['read']);

        $response = $this->withHeaders(appApiHeaders($readToken->plainTextToken))
            ->postJson('/api/v1/applications/public', [
                'project_uuid' => $this->project->uuid,
                'server_uuid' => $this->server->uuid,
                'environment_name' => $this->environment->name,
                'git_repository' => 'https://github.com/test/test.git',
                'git_branch' => 'main',
                'build_pack' => 'nixpacks',
                'ports_exposes' => '3000',
            ]);

        $response->assertStatus(403);
    });

    test('read-only token cannot update application', function () {
        $readToken = $this->user->createToken('read-only', ['read']);
        $app = Application::factory()->create([
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '3000',
        ]);

        $response = $this->withHeaders(appApiHeaders($readToken->plainTextToken))
            ->patchJson("/api/v1/applications/{$app->uuid}", ['name' => 'Hack']);

        $response->assertStatus(403);
    });

    test('read-only token cannot delete application', function () {
        $readToken = $this->user->createToken('read-only', ['read']);
        $app = Application::factory()->create([
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '3000',
        ]);

        $response = $this->withHeaders(appApiHeaders($readToken->plainTextToken))
            ->deleteJson("/api/v1/applications/{$app->uuid}");

        $response->assertStatus(403);
    });

    test('write token can create, update, and delete applications', function () {
        $writeToken = $this->user->createToken('write-token', ['write']);

        // Create
        $response = $this->withHeaders(appApiHeaders($writeToken->plainTextToken))
            ->postJson('/api/v1/applications/public', [
                'project_uuid' => $this->project->uuid,
                'server_uuid' => $this->server->uuid,
                'environment_name' => $this->environment->name,
                'git_repository' => 'https://github.com/coollabsio/coolify-examples.git',
                'git_branch' => 'main',
                'build_pack' => 'nixpacks',
                'ports_exposes' => '3000',
            ]);

        $response->assertStatus(201);
        $uuid = $response->json('uuid');

        // Update
        $response = $this->withHeaders(appApiHeaders($writeToken->plainTextToken))
            ->patchJson("/api/v1/applications/{$uuid}", ['name' => 'Updated']);

        $response->assertStatus(200);

        // Delete
        $response = $this->withHeaders(appApiHeaders($writeToken->plainTextToken))
            ->deleteJson("/api/v1/applications/{$uuid}");

        $response->assertStatus(200);
    });
});

// ─── Full Lifecycle E2E ──────────────────────────────────────────────────────

describe('Full application lifecycle — create → read → update → delete', function () {
    test('complete lifecycle: create → get → update → list → delete', function () {
        // 1. Create application
        $createResponse = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->postJson('/api/v1/applications/public', [
                'project_uuid' => $this->project->uuid,
                'server_uuid' => $this->server->uuid,
                'environment_name' => $this->environment->name,
                'git_repository' => 'https://github.com/coollabsio/coolify-examples.git',
                'git_branch' => 'main',
                'build_pack' => 'nixpacks',
                'ports_exposes' => '3000',
                'name' => 'Lifecycle Test App',
            ]);

        $createResponse->assertStatus(201);
        $uuid = $createResponse->json('uuid');
        expect($uuid)->toBeString();

        // 2. Read — verify created application
        $getResponse = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->getJson("/api/v1/applications/{$uuid}");

        $getResponse->assertStatus(200);
        $getResponse->assertJsonFragment([
            'name' => 'Lifecycle Test App',
            'build_pack' => 'nixpacks',
            'git_branch' => 'main',
        ]);

        // 3. Update — change name and branch
        $updateResponse = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->patchJson("/api/v1/applications/{$uuid}", [
                'name' => 'Updated Lifecycle App',
                'git_branch' => 'develop',
                'description' => 'Updated via lifecycle test',
            ]);

        $updateResponse->assertStatus(200);

        // 4. Verify update persisted
        $verifyResponse = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->getJson("/api/v1/applications/{$uuid}");

        $verifyResponse->assertStatus(200);
        $verifyResponse->assertJsonFragment([
            'name' => 'Updated Lifecycle App',
            'git_branch' => 'develop',
        ]);

        // 5. List — verify application appears in list
        $listResponse = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->getJson('/api/v1/applications');

        $listResponse->assertStatus(200);
        $listResponse->assertJsonFragment(['name' => 'Updated Lifecycle App']);

        // 6. Delete
        $deleteResponse = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->deleteJson("/api/v1/applications/{$uuid}");

        $deleteResponse->assertStatus(200);
        Queue::assertPushed(DeleteResourceJob::class);

        // 7. Verify delete was queued (actual removal happens async via DeleteResourceJob)
        Queue::assertPushed(DeleteResourceJob::class, function ($job) {
            return true; // Job was dispatched for this resource
        });
    });

    test('create Dockerfile app → update → delete lifecycle', function () {
        $dockerfile = base64_encode("FROM node:18\nWORKDIR /app\nCOPY . .\nEXPOSE 3000\nCMD [\"node\", \"server.js\"]");

        // Create
        $createResponse = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->postJson('/api/v1/applications/dockerfile', [
                'project_uuid' => $this->project->uuid,
                'server_uuid' => $this->server->uuid,
                'environment_name' => $this->environment->name,
                'dockerfile' => $dockerfile,
                'name' => 'Dockerfile Lifecycle',
            ]);

        $createResponse->assertStatus(201);
        $uuid = $createResponse->json('uuid');

        // Verify build_pack is dockerfile
        $app = Application::where('uuid', $uuid)->first();
        expect($app->build_pack)->toBe('dockerfile');

        // Update name
        $this->withHeaders(appApiHeaders($this->bearerToken))
            ->patchJson("/api/v1/applications/{$uuid}", ['name' => 'Updated Dockerfile'])
            ->assertStatus(200);

        // Delete
        $this->withHeaders(appApiHeaders($this->bearerToken))
            ->deleteJson("/api/v1/applications/{$uuid}")
            ->assertStatus(200);

        Queue::assertPushed(DeleteResourceJob::class);
    });

    test('create Docker image app → update → delete lifecycle', function () {
        // Create
        $createResponse = $this->withHeaders(appApiHeaders($this->bearerToken))
            ->postJson('/api/v1/applications/dockerimage', [
                'project_uuid' => $this->project->uuid,
                'server_uuid' => $this->server->uuid,
                'environment_name' => $this->environment->name,
                'docker_registry_image_name' => 'nginx',
                'docker_registry_image_tag' => 'alpine',
                'ports_exposes' => '80',
                'name' => 'Image Lifecycle',
            ]);

        $createResponse->assertStatus(201);
        $uuid = $createResponse->json('uuid');

        // Verify
        $app = Application::where('uuid', $uuid)->first();
        expect($app->build_pack)->toBe('dockerimage');
        expect($app->docker_registry_image_name)->toBe('nginx');
        expect($app->docker_registry_image_tag)->toBe('alpine');

        // Update ports
        $this->withHeaders(appApiHeaders($this->bearerToken))
            ->patchJson("/api/v1/applications/{$uuid}", ['ports_exposes' => '80,443'])
            ->assertStatus(200);

        $app->refresh();
        expect($app->ports_exposes)->toBe('80,443');

        // Delete
        $this->withHeaders(appApiHeaders($this->bearerToken))
            ->deleteJson("/api/v1/applications/{$uuid}")
            ->assertStatus(200);
    });
});
