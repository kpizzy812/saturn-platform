<?php

use App\Models\Application;
use App\Models\Environment;
use App\Models\PrivateKey;
use App\Models\Project;
use App\Models\ResourceLink;
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

    $this->application = Application::factory()->create([
        'environment_id' => $this->environment->id,
        'destination_id' => $this->destination->id,
        'destination_type' => StandaloneDocker::class,
    ]);
});

describe('Authentication', function () {
    test('rejects GET request without token', function () {
        $response = $this->getJson('/api/v1/environments/'.$this->environment->uuid.'/links');
        $response->assertStatus(401);
    });

    test('rejects POST request without token', function () {
        $response = $this->postJson('/api/v1/environments/'.$this->environment->uuid.'/links', []);
        $response->assertStatus(401);
    });
});

describe('GET /api/v1/environments/{uuid}/links', function () {
    test('returns empty array when no links exist', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/environments/'.$this->environment->uuid.'/links');

        $response->assertStatus(200);
        expect($response->json())->toBe([]);
    });

    test('returns 404 for non-existent environment', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/environments/non-existent-uuid/links');

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Environment not found.');
    });

    test('returns 404 for environment from another team', function () {
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnv = Environment::factory()->create(['project_id' => $otherProject->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/environments/'.$otherEnv->uuid.'/links');

        $response->assertStatus(404);
    });

    test('returns links for environment', function () {
        $anotherApp = Application::factory()->create([
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
        ]);

        ResourceLink::create([
            'environment_id' => $this->environment->id,
            'source_type' => Application::class,
            'source_id' => $this->application->id,
            'target_type' => Application::class,
            'target_id' => $anotherApp->id,
            'auto_inject' => true,
            'use_external_url' => false,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/environments/'.$this->environment->uuid.'/links');

        $response->assertStatus(200);
        expect(count($response->json()))->toBeGreaterThanOrEqual(1);
    });
});

describe('POST /api/v1/environments/{uuid}/links', function () {
    test('returns 422 when required fields are missing', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson('/api/v1/environments/'.$this->environment->uuid.'/links', []);

        $response->assertStatus(422);
    });

    test('returns 404 for non-existent environment', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson('/api/v1/environments/non-existent-uuid/links', [
            'source_id' => $this->application->id,
            'target_type' => 'application',
            'target_id' => 999,
        ]);

        $response->assertStatus(404);
    });

    test('returns 404 when source application not found in environment', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson('/api/v1/environments/'.$this->environment->uuid.'/links', [
            'source_id' => 99999,
            'target_type' => 'application',
            'target_id' => $this->application->id,
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Application not found in this environment.');
    });

    test('returns 400 when linking application to itself', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson('/api/v1/environments/'.$this->environment->uuid.'/links', [
            'source_id' => $this->application->id,
            'target_type' => 'application',
            'target_id' => $this->application->id,
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Cannot link an application to itself.');
    });
});

describe('DELETE /api/v1/environments/{uuid}/links/{id}', function () {
    test('deletes a resource link', function () {
        $anotherApp = Application::factory()->create([
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
        ]);

        $link = ResourceLink::create([
            'environment_id' => $this->environment->id,
            'source_type' => Application::class,
            'source_id' => $this->application->id,
            'target_type' => Application::class,
            'target_id' => $anotherApp->id,
            'auto_inject' => false,
            'use_external_url' => false,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->deleteJson('/api/v1/environments/'.$this->environment->uuid.'/links/'.$link->id);

        $response->assertStatus(204);

        $this->assertDatabaseMissing('resource_links', ['id' => $link->id]);
    });

    test('returns 404 for non-existent link', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->deleteJson('/api/v1/environments/'.$this->environment->uuid.'/links/99999');

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Link not found.');
    });
});

describe('PATCH /api/v1/environments/{uuid}/links/{id}', function () {
    test('updates a resource link', function () {
        $anotherApp = Application::factory()->create([
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
        ]);

        $link = ResourceLink::create([
            'environment_id' => $this->environment->id,
            'source_type' => Application::class,
            'source_id' => $this->application->id,
            'target_type' => Application::class,
            'target_id' => $anotherApp->id,
            'auto_inject' => true,
            'use_external_url' => false,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->patchJson('/api/v1/environments/'.$this->environment->uuid.'/links/'.$link->id, [
            'auto_inject' => false,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('resource_links', [
            'id' => $link->id,
            'auto_inject' => false,
        ]);
    });

    test('returns 404 for non-existent link', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->patchJson('/api/v1/environments/'.$this->environment->uuid.'/links/99999', [
            'auto_inject' => false,
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Link not found.');
    });
});
