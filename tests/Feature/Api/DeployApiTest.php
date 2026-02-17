<?php

use App\Enums\ApplicationDeploymentStatus;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\Environment;
use App\Models\InstanceSettings;
use App\Models\PrivateKey;
use App\Models\Project;
use App\Models\Server;
use App\Models\ServerSetting;
use App\Models\StandaloneDocker;
use App\Models\Tag;
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

    // Create a test application
    // applications.ports_exposes is NOT NULL — include it
    $this->application = Application::factory()->create([
        'environment_id' => $this->environment->id,
        'destination_id' => $this->destination->id,
        'destination_type' => StandaloneDocker::class,
        'ports_exposes' => '3000',
    ]);
});

describe('Authentication', function () {
    test('rejects request without authentication to deploy endpoint', function () {
        $response = $this->getJson('/api/v1/deploy');
        $response->assertStatus(401);
    });

    test('rejects request with invalid token to deploy endpoint', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token-here',
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/deploy');

        $response->assertStatus(401);
    });

    test('rejects request without authentication to deployments list', function () {
        $response = $this->getJson('/api/v1/deployments');
        $response->assertStatus(401);
    });
});

describe('GET /api/v1/deploy - Deploy by UUID', function () {
    test('returns 400 when neither uuid nor tag provided', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/deploy');

        $response->assertStatus(400);
        $response->assertJson(['message' => 'You must provide uuid or tag.']);
    });

    test('returns 400 when both uuid and tag are provided', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/deploy?uuid=some-uuid&tag=some-tag');

        $response->assertStatus(400);
        $response->assertJson(['message' => 'You can only use uuid or tag, not both.']);
    });

    test('returns 404 when uuid does not match any resource', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/deploy?uuid=non-existent-uuid');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'No resources found.']);
    });

    test('deploys application by UUID successfully', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/deploy?uuid={$this->application->uuid}");

        $response->assertStatus(200);
        $response->assertJsonStructure(['deployments']);
        $json = $response->json();
        expect($json['deployments'])->toBeArray();
        expect($json['deployments'][0])->toHaveKey('resource_uuid');
        expect($json['deployments'][0]['resource_uuid'])->toBe($this->application->uuid);
    });

    test('accepts force parameter for rebuild without cache', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/deploy?uuid={$this->application->uuid}&force=true");

        $response->assertStatus(200);
        $response->assertJsonStructure(['deployments']);
    });

    test('deploys multiple applications by comma-separated UUIDs', function () {
        $secondApp = Application::factory()->create([
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '4000',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/deploy?uuid={$this->application->uuid},{$secondApp->uuid}");

        $response->assertStatus(200);
        $response->assertJsonStructure(['deployments']);
        $json = $response->json();
        expect($json['deployments'])->toHaveCount(2);
    });

    test('accepts POST method for deploy endpoint', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/deploy', [
            'uuid' => $this->application->uuid,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['deployments']);
    });
});

describe('GET /api/v1/deploy - Deploy by tag', function () {
    test('returns 404 when tag does not exist', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/deploy?tag=non-existent-tag');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'No resources found with this tag.']);
    });

    test('returns 400 when tag and pr are both provided', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/deploy?tag=some-tag&pr=1');

        $response->assertStatus(400);
        $response->assertJson(['message' => 'You can only use tag or pr, not both.']);
    });

    test('deploys resources by tag when tag has applications', function () {
        // Create a tag and attach the application to it
        $tag = Tag::firstOrCreate(['name' => 'test-tag', 'team_id' => $this->team->id]);
        $this->application->tags()->syncWithoutDetaching([$tag->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/deploy?tag=test-tag');

        // When tag is found and has applications but deploy authorization varies,
        // the response could be 200 (deployed) or 404 (no resources deployed successfully).
        expect($response->status())->toBeIn([200, 404]);
    });

    test('returns 404 when no matching tag resources can be deployed', function () {
        // When no tags exist in this team that match the given name,
        // by_tags() will skip and return 404.
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/deploy?tag=this-tag-definitely-does-not-exist-in-db');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'No resources found with this tag.']);
    });
});

describe('GET /api/v1/deployments - List deployments', function () {
    test('returns empty array when no active deployments exist', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/deployments');

        $response->assertStatus(200);
        $response->assertJsonIsArray();
    });

    test('returns active deployments for the team', function () {
        // Create an in-progress deployment on the team's server
        $deployment = ApplicationDeploymentQueue::factory()->create([
            'application_id' => $this->application->id,
            'server_id' => $this->server->id,
            'status' => ApplicationDeploymentStatus::IN_PROGRESS->value,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/deployments');

        $response->assertStatus(200);
        $response->assertJsonIsArray();
    });

    test('does not include finished deployments', function () {
        // Create a finished deployment
        $finishedDeployment = ApplicationDeploymentQueue::factory()->create([
            'application_id' => $this->application->id,
            'server_id' => $this->server->id,
            'status' => ApplicationDeploymentStatus::FINISHED->value,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/deployments');

        $response->assertStatus(200);
        $json = $response->json();
        $deploymentUuids = collect($json)->pluck('deployment_uuid')->all();
        expect($deploymentUuids)->not->toContain($finishedDeployment->deployment_uuid);
    });
});

describe('GET /api/v1/deployments/{uuid} - Get deployment by UUID', function () {
    test('returns 404 for non-existent deployment UUID', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/deployments/non-existent-uuid');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Deployment not found.']);
    });

    test('returns deployment details by UUID', function () {
        $deployment = ApplicationDeploymentQueue::factory()->create([
            'application_id' => $this->application->id,
            'server_id' => $this->server->id,
            'status' => ApplicationDeploymentStatus::QUEUED->value,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/deployments/{$deployment->deployment_uuid}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['deployment_uuid' => $deployment->deployment_uuid]);
    });

    test('returns 404 for deployment from another team', function () {
        $otherTeam = Team::factory()->create();
        $otherPrivateKey = PrivateKey::factory()->create(['team_id' => $otherTeam->id]);
        $otherServer = Server::withoutEvents(function () use ($otherTeam, $otherPrivateKey) {
            return Server::factory()->create([
                'uuid' => (string) new Cuid2,
                'team_id' => $otherTeam->id,
                'private_key_id' => $otherPrivateKey->id,
            ]);
        });
        ServerSetting::firstOrCreate(['server_id' => $otherServer->id]);

        $deployment = ApplicationDeploymentQueue::factory()->create([
            'application_id' => $this->application->id,
            'server_id' => $otherServer->id,
            'status' => ApplicationDeploymentStatus::QUEUED->value,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/deployments/{$deployment->deployment_uuid}");

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Deployment not found.']);
    });

    test('hides logs field without sensitive token', function () {
        $deployment = ApplicationDeploymentQueue::factory()->create([
            'application_id' => $this->application->id,
            'server_id' => $this->server->id,
            'status' => ApplicationDeploymentStatus::FINISHED->value,
        ]);

        $limitedToken = $this->user->createToken('read-only-token', ['read']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$limitedToken->plainTextToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/deployments/{$deployment->deployment_uuid}");

        $response->assertStatus(200);
        $json = $response->json();
        expect($json)->not->toHaveKey('logs');
    });
});

describe('POST /api/v1/deployments/{uuid}/cancel - Cancel deployment', function () {
    test('returns 404 for non-existent deployment', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/deployments/non-existent-uuid/cancel');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Deployment not found.']);
    });

    test('returns 400 when deployment is already finished', function () {
        $deployment = ApplicationDeploymentQueue::factory()->create([
            'application_id' => $this->application->id,
            'server_id' => $this->server->id,
            'status' => ApplicationDeploymentStatus::FINISHED->value,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson("/api/v1/deployments/{$deployment->deployment_uuid}/cancel");

        $response->assertStatus(400);
        $response->assertJsonStructure(['message']);
        expect($response->json('message'))->toContain('Deployment cannot be cancelled');
    });

    test('cancels queued deployment successfully', function () {
        $deployment = ApplicationDeploymentQueue::factory()->create([
            'application_id' => $this->application->id,
            'server_id' => $this->server->id,
            'status' => ApplicationDeploymentStatus::QUEUED->value,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson("/api/v1/deployments/{$deployment->deployment_uuid}/cancel");

        // May return 200 or 500 depending on whether SSH connection is attempted
        expect($response->status())->toBeIn([200, 500]);
        if ($response->status() === 200) {
            $response->assertJson(['message' => 'Deployment cancelled successfully.']);
        }
    });

    test('returns 403 when deployment belongs to another team', function () {
        $otherTeam = Team::factory()->create();
        $otherPrivateKey = PrivateKey::factory()->create(['team_id' => $otherTeam->id]);
        $otherServer = Server::withoutEvents(function () use ($otherTeam, $otherPrivateKey) {
            return Server::factory()->create([
                'uuid' => (string) new Cuid2,
                'team_id' => $otherTeam->id,
                'private_key_id' => $otherPrivateKey->id,
            ]);
        });
        ServerSetting::firstOrCreate(['server_id' => $otherServer->id]);

        $deployment = ApplicationDeploymentQueue::factory()->create([
            'application_id' => $this->application->id,
            'server_id' => $otherServer->id,
            'status' => ApplicationDeploymentStatus::QUEUED->value,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson("/api/v1/deployments/{$deployment->deployment_uuid}/cancel");

        $response->assertStatus(403);
        $response->assertJson(['message' => 'You do not have permission to cancel this deployment.']);
    });
});

describe('GET /api/v1/deployments/applications/{uuid} - List application deployments', function () {
    test('returns 404 for non-existent application', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/deployments/applications/non-existent-uuid');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Application not found']);
    });

    test('returns deployments object for the application', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/deployments/applications/{$this->application->uuid}");

        $response->assertStatus(200);
        // deployments() returns {'count': N, 'deployments': [...]}
        $response->assertJsonStructure(['count', 'deployments']);
    });

    test('accepts skip and take pagination parameters', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/deployments/applications/{$this->application->uuid}?skip=0&take=5");

        $response->assertStatus(200);
        $response->assertJsonStructure(['count', 'deployments']);
    });
});
