<?php

use App\Models\Environment;
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

beforeEach(function () {
    Queue::fake();

    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();
    $this->team->members()->attach($this->user->id, ['role' => 'owner']);

    session(['currentTeam' => $this->team]);
    $this->token = $this->user->createToken('test-token', ['*']);
    $this->bearerToken = $this->token->plainTextToken;

    if (! DB::table('instance_settings')->where('id', 0)->exists()) {
        DB::table('instance_settings')->insert([
            'id' => 0,
            'is_api_enabled' => true,
            'is_registration_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
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

describe('Authentication', function () {
    test('rejects request without token', function () {
        $response = $this->postJson('/api/v1/applications/public', []);
        $response->assertStatus(401);
    });

    test('rejects request with invalid token', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token',
        ])->postJson('/api/v1/applications/public', []);
        $response->assertStatus(401);
    });
});

describe('POST /api/v1/applications/public', function () {
    test('returns error when required fields are missing', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson('/api/v1/applications/public', []);

        $response->assertStatus(422);
    });

    test('returns error for non-existent project uuid', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson('/api/v1/applications/public', [
            'project_uuid' => 'non-existent-uuid',
            'server_uuid' => $this->server->uuid,
            'environment_name' => $this->environment->name,
            'environment_uuid' => $this->environment->uuid,
            'git_repository' => 'https://github.com/test/repo',
            'git_branch' => 'main',
            'build_pack' => 'nixpacks',
            'ports_exposes' => '3000',
        ]);

        $response->assertStatus(404);
    });

    test('returns error for non-existent server uuid', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson('/api/v1/applications/public', [
            'project_uuid' => $this->project->uuid,
            'server_uuid' => 'non-existent-server',
            'environment_name' => $this->environment->name,
            'environment_uuid' => $this->environment->uuid,
            'git_repository' => 'https://github.com/test/repo',
            'git_branch' => 'main',
            'build_pack' => 'nixpacks',
            'ports_exposes' => '3000',
        ]);

        $response->assertStatus(404);
    });

    test('returns error for invalid git repository url', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson('/api/v1/applications/public', [
            'project_uuid' => $this->project->uuid,
            'server_uuid' => $this->server->uuid,
            'environment_name' => $this->environment->name,
            'environment_uuid' => $this->environment->uuid,
            'git_repository' => 'not-a-valid-url',
            'git_branch' => 'main',
            'build_pack' => 'nixpacks',
            'ports_exposes' => '3000',
        ]);

        // Validation error or not found for server destination
        $response->assertStatus(422);
    });
});

describe('POST /api/v1/applications/dockerfile', function () {
    test('returns error when required fields are missing', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson('/api/v1/applications/dockerfile', []);

        $response->assertStatus(422);
    });

    test('returns error for non-existent project', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson('/api/v1/applications/dockerfile', [
            'project_uuid' => 'non-existent',
            'server_uuid' => $this->server->uuid,
            'environment_name' => $this->environment->name,
            'environment_uuid' => $this->environment->uuid,
            'dockerfile' => base64_encode('FROM nginx:latest'),
        ]);

        $response->assertStatus(404);
    });
});

describe('POST /api/v1/applications/dockerimage', function () {
    test('returns error when required fields are missing', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson('/api/v1/applications/dockerimage', []);

        $response->assertStatus(422);
    });

    test('returns error for non-existent project', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson('/api/v1/applications/dockerimage', [
            'project_uuid' => 'non-existent',
            'server_uuid' => $this->server->uuid,
            'environment_name' => $this->environment->name,
            'environment_uuid' => $this->environment->uuid,
            'docker_registry_image_name' => 'nginx',
            'ports_exposes' => '80',
        ]);

        $response->assertStatus(404);
    });
});

describe('POST /api/v1/applications/dockercompose', function () {
    test('returns error when required fields are missing', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson('/api/v1/applications/dockercompose', []);

        $response->assertStatus(422);
    });
});

describe('POST /api/v1/applications/private-deploy-key', function () {
    test('returns error when required fields are missing', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson('/api/v1/applications/private-deploy-key', []);

        $response->assertStatus(422);
    });

    test('returns error for non-existent private key', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson('/api/v1/applications/private-deploy-key', [
            'project_uuid' => $this->project->uuid,
            'server_uuid' => $this->server->uuid,
            'environment_name' => $this->environment->name,
            'environment_uuid' => $this->environment->uuid,
            'private_key_uuid' => 'non-existent-key',
            'git_repository' => 'git@github.com:test/repo.git',
            'git_branch' => 'main',
            'build_pack' => 'nixpacks',
            'ports_exposes' => '3000',
        ]);

        $response->assertStatus(404);
    });
});

describe('POST /api/v1/applications/private-github-app', function () {
    test('returns error when required fields are missing', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson('/api/v1/applications/private-github-app', []);

        $response->assertStatus(422);
    });
});
