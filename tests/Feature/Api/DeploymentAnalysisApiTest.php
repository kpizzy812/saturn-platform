<?php

use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\DeploymentLogAnalysis;
use App\Models\Environment;
use App\Models\PrivateKey;
use App\Models\Project;
use App\Models\Server;
use App\Models\ServerSetting;
use App\Models\StandaloneDocker;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
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

    $this->deployment = ApplicationDeploymentQueue::factory()->create([
        'application_id' => $this->application->id,
        'application_name' => $this->application->name,
    ]);
});

describe('Authentication', function () {
    test('rejects request without token for analysis show', function () {
        $response = $this->getJson('/api/v1/deployments/'.$this->deployment->deployment_uuid.'/analysis');
        $response->assertStatus(401);
    });

    test('rejects request without token for ai status', function () {
        $response = $this->getJson('/api/v1/ai/status');
        $response->assertStatus(401);
    });
});

describe('GET /api/v1/ai/status', function () {
    test('returns AI service status', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/ai/status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'enabled',
                'available',
                'provider',
                'model',
            ]);
    });

    test('returns enabled false when AI is disabled', function () {
        Config::set('ai.enabled', false);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/ai/status');

        $response->assertStatus(200)
            ->assertJsonPath('enabled', false);
    });
});

describe('GET /api/v1/deployments/{uuid}/analysis', function () {
    test('returns 404 when deployment does not exist', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/deployments/non-existent-uuid/analysis');

        $response->assertStatus(404);
    });

    test('returns not_found when no analysis exists for deployment', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/deployments/'.$this->deployment->deployment_uuid.'/analysis');

        $response->assertStatus(404)
            ->assertJsonPath('status', 'not_found');
    });

    test('returns analysis when it exists', function () {
        $analysis = DeploymentLogAnalysis::create([
            'application_deployment_queue_id' => $this->deployment->id,
            'status' => 'completed',
            'root_cause' => 'Dependency not found',
            'solution' => 'Install missing dependency',
            'error_category' => 'dependency',
            'severity' => 'high',
            'confidence' => 0.9,
            'provider' => 'anthropic',
            'model' => 'claude-3-5-sonnet',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/deployments/'.$this->deployment->deployment_uuid.'/analysis');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'completed')
            ->assertJsonStructure([
                'status',
                'analysis' => [
                    'id',
                    'root_cause',
                    'solution',
                    'severity',
                    'confidence',
                    'provider',
                    'model',
                    'status',
                ],
            ]);
    });

    test('returns 403 for deployment belonging to another team', function () {
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnv = Environment::factory()->create(['project_id' => $otherProject->id]);

        $otherApp = Application::factory()->create([
            'environment_id' => $otherEnv->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
        ]);

        $otherDeployment = ApplicationDeploymentQueue::factory()->create([
            'application_id' => $otherApp->id,
            'application_name' => $otherApp->name,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/deployments/'.$otherDeployment->deployment_uuid.'/analysis');

        $response->assertStatus(403);
    });
});

describe('POST /api/v1/deployments/{uuid}/analyze', function () {
    test('returns 503 when AI is disabled', function () {
        Config::set('ai.enabled', false);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson('/api/v1/deployments/'.$this->deployment->deployment_uuid.'/analyze');

        $response->assertStatus(503)
            ->assertJsonPath('error', 'AI analysis is disabled');
    });

    test('returns 404 for non-existent deployment', function () {
        // Ensure AI is enabled for this test
        Config::set('ai.enabled', true);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson('/api/v1/deployments/non-existent-uuid/analyze');

        $response->assertStatus(404);
    });
});
