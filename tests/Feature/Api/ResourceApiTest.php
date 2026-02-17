<?php

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
use Illuminate\Support\Facades\Queue;
use Visus\Cuid2\Cuid2;

uses(DatabaseTransactions::class);

beforeEach(function () {
    Queue::fake(); // Prevent actual job dispatching

    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();
    $this->team->members()->attach($this->user->id, ['role' => 'owner']);

    session(['currentTeam' => $this->team]);
    $this->token = $this->user->createToken('test-token', ['*']);
    $this->bearerToken = $this->token->plainTextToken;

    // Create InstanceSettings
    if (! InstanceSettings::first()) {
        InstanceSettings::create([
            'id' => 0,
            'is_api_enabled' => true,
        ]);
    }

    // Create project > environment chain
    $this->project = Project::factory()->create(['team_id' => $this->team->id]);
    $this->environment = Environment::factory()->create(['project_id' => $this->project->id]);

    // Create server + destination (without triggering SSH)
    $this->privateKey = PrivateKey::factory()->create(['team_id' => $this->team->id]);
    // withoutEvents() skips BaseModel::boot() which generates uuid, so set it explicitly
    $this->server = Server::withoutEvents(function () {
        return Server::factory()->create([
            'uuid' => (string) new Cuid2,
            'team_id' => $this->team->id,
            'private_key_id' => $this->privateKey->id,
        ]);
    });

    // withoutEvents() also skips the created event that creates ServerSetting
    ServerSetting::firstOrCreate(['server_id' => $this->server->id]);

    // Create StandaloneDocker destination without triggering docker network creation
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
    test('rejects request without authentication', function () {
        $response = $this->getJson('/api/v1/resources');
        $response->assertStatus(401);
    });

    test('rejects request with invalid token', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token-here',
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/resources');

        $response->assertStatus(401);
    });
});

describe('GET /api/v1/resources - List all resources', function () {
    test('returns empty array when no resources exist', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/resources');

        $response->assertStatus(200);
        $response->assertJsonIsArray();
        $response->assertJson([]);
    });

    test('returns list of resources including applications', function () {
        // applications.ports_exposes is NOT NULL — include it
        $application = Application::factory()->create([
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '3000',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/resources');

        $response->assertStatus(200);
        $response->assertJsonIsArray();
        $json = $response->json();
        expect($json)->not->toBeEmpty();
    });

    test('includes type field in each resource', function () {
        Application::factory()->create([
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '3000',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/resources');

        $response->assertStatus(200);
        $json = $response->json();
        $firstResource = $json[0];
        expect($firstResource)->toHaveKey('type');
        expect($firstResource)->toHaveKey('status');
    });

    test('returns only resources for current team', function () {
        // Create application for this team
        Application::factory()->create([
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '3000',
        ]);

        // Create resources for another team (via different project)
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnvironment = Environment::factory()->create(['project_id' => $otherProject->id]);
        $otherApplication = Application::factory()->create([
            'environment_id' => $otherEnvironment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '4000',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/resources');

        $response->assertStatus(200);
        $json = $response->json();
        $uuids = collect($json)->pluck('uuid')->all();
        expect($uuids)->not->toContain($otherApplication->uuid);
    });

    test('returns resources from multiple projects under the same team', function () {
        // Create second project for the same team
        $secondProject = Project::factory()->create(['team_id' => $this->team->id]);
        $secondEnvironment = Environment::factory()->create(['project_id' => $secondProject->id]);

        $app1 = Application::factory()->create([
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '3000',
        ]);

        $app2 = Application::factory()->create([
            'environment_id' => $secondEnvironment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '4000',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/resources');

        $response->assertStatus(200);
        $json = $response->json();
        $uuids = collect($json)->pluck('uuid')->all();
        expect($uuids)->toContain($app1->uuid);
        expect($uuids)->toContain($app2->uuid);
    });

    test('returns response with status field for each resource', function () {
        Application::factory()->create([
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '3000',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/resources');

        $response->assertStatus(200);
        $json = $response->json();
        foreach ($json as $resource) {
            expect($resource)->toHaveKey('status');
        }
    });
});
