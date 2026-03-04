<?php

use App\Models\Environment;
use App\Models\PrivateKey;
use App\Models\Project;
use App\Models\Server;
use App\Models\ServerSetting;
use App\Models\Service;
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

    $this->service = Service::withoutEvents(function () {
        return Service::factory()->create([
            'environment_id' => $this->environment->id,
            'server_id' => $this->server->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
        ]);
    });
});

describe('Authentication', function () {
    test('rejects GET request without token', function () {
        $response = $this->getJson('/api/v1/services/'.$this->service->uuid.'/healthcheck');
        $response->assertStatus(401);
    });

    test('rejects PATCH request without token', function () {
        $response = $this->patchJson('/api/v1/services/'.$this->service->uuid.'/healthcheck', []);
        $response->assertStatus(401);
    });
});

describe('GET /api/v1/services/{uuid}/healthcheck', function () {
    test('returns healthcheck config for a service', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/services/'.$this->service->uuid.'/healthcheck');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'enabled',
                'status',
            ]);
    });

    test('returns 404 for non-existent service', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/services/non-existent-uuid/healthcheck');

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Service not found.');
    });

    test('returns 404 for service belonging to another team', function () {
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnv = Environment::factory()->create(['project_id' => $otherProject->id]);

        $otherService = Service::withoutEvents(function () use ($otherEnv) {
            return Service::factory()->create([
                'environment_id' => $otherEnv->id,
                'server_id' => $this->server->id,
                'destination_id' => $this->destination->id,
                'destination_type' => StandaloneDocker::class,
            ]);
        });

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/services/'.$otherService->uuid.'/healthcheck');

        $response->assertStatus(404);
    });
});

describe('PATCH /api/v1/services/{uuid}/healthcheck', function () {
    test('updates healthcheck configuration', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->patchJson('/api/v1/services/'.$this->service->uuid.'/healthcheck', [
            'enabled' => true,
            'type' => 'http',
            'interval' => 30,
            'timeout' => 10,
            'retries' => 3,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Healthcheck configuration updated.');
    });

    test('returns 422 for invalid healthcheck type', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->patchJson('/api/v1/services/'.$this->service->uuid.'/healthcheck', [
            'type' => 'invalid-type',
        ]);

        $response->assertStatus(422);
    });

    test('returns 422 for negative interval', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->patchJson('/api/v1/services/'.$this->service->uuid.'/healthcheck', [
            'interval' => 0,
        ]);

        $response->assertStatus(422);
    });

    test('returns 404 for non-existent service', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->patchJson('/api/v1/services/non-existent-uuid/healthcheck', [
            'enabled' => true,
        ]);

        $response->assertStatus(404);
    });
});
