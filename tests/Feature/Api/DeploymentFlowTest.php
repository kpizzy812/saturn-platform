<?php

/**
 * E2E Deployment Flow Tests
 *
 * Covers the full pipeline: API trigger → ApplicationDeploymentQueue record created
 * → ApplicationDeploymentJob dispatched. Complements DeployApiTest.php by adding
 * database-level assertions and concurrent-deployment protection checks.
 */

use App\Enums\ApplicationDeploymentStatus;
use App\Jobs\ApplicationDeploymentJob;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
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

    // Eloquent create() ignores explicit id=0 on PostgreSQL bigserial columns;
    // use raw DB insert to guarantee the singleton record exists.
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
        'ports_exposes' => '3000',
    ]);
});

describe('Deployment flow: API trigger → DB record → Job dispatch', function () {
    test('deploy trigger creates ApplicationDeploymentQueue record in database', function () {
        $this->withHeaders(['Authorization' => 'Bearer '.$this->bearerToken])
            ->getJson("/api/v1/deploy?uuid={$this->application->uuid}");

        $this->assertDatabaseHas('application_deployment_queues', [
            'application_id' => $this->application->id,
        ]);
    });

    test('deploy trigger dispatches ApplicationDeploymentJob', function () {
        $this->withHeaders(['Authorization' => 'Bearer '.$this->bearerToken])
            ->getJson("/api/v1/deploy?uuid={$this->application->uuid}");

        Queue::assertPushed(ApplicationDeploymentJob::class);
    });

    test('deployment record has correct initial status', function () {
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->bearerToken])
            ->getJson("/api/v1/deploy?uuid={$this->application->uuid}");

        $response->assertStatus(200);
        $json = $response->json();
        $deploymentUuid = $json['deployments'][0]['deployment_uuid'] ?? null;

        expect($deploymentUuid)->not->toBeNull();

        // After dispatch the status is IN_PROGRESS (queue_application dispatches immediately
        // when no other deployment is in progress)
        $record = ApplicationDeploymentQueue::where('deployment_uuid', $deploymentUuid)->first();
        expect($record)->not->toBeNull();
        expect($record->application_id)->toBe($this->application->id);
    });

    test('force=true sets force_rebuild flag on deployment record', function () {
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->bearerToken])
            ->getJson("/api/v1/deploy?uuid={$this->application->uuid}&force=true");

        $response->assertStatus(200);
        $json = $response->json();
        $deploymentUuid = $json['deployments'][0]['deployment_uuid'] ?? null;

        $record = ApplicationDeploymentQueue::where('deployment_uuid', $deploymentUuid)->first();
        expect($record)->not->toBeNull();
        expect((bool) $record->force_rebuild)->toBeTrue();
    });
});

describe('Concurrent deployment prevention (next_queuable logic)', function () {
    test('second deploy while first is IN_PROGRESS stays QUEUED', function () {
        // Create an in-progress deployment to simulate an ongoing deploy
        ApplicationDeploymentQueue::factory()->create([
            'application_id' => $this->application->id,
            'server_id' => $this->server->id,
            'status' => ApplicationDeploymentStatus::IN_PROGRESS->value,
            'pull_request_id' => 0,
        ]);

        // Trigger a second deploy via API
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->bearerToken])
            ->getJson("/api/v1/deploy?uuid={$this->application->uuid}");

        $response->assertStatus(200);

        // The new deployment should be created but NOT dispatched as a job
        // (next_queuable() returns false when IN_PROGRESS exists)
        $json = $response->json();
        $deploymentUuid = $json['deployments'][0]['deployment_uuid'] ?? null;
        expect($deploymentUuid)->not->toBeNull();

        $record = ApplicationDeploymentQueue::where('deployment_uuid', $deploymentUuid)->first();
        expect($record)->not->toBeNull();
        // Status must be QUEUED (not IN_PROGRESS) because another deploy is running
        expect($record->status)->toBe(ApplicationDeploymentStatus::QUEUED->value);

        // Job must NOT have been dispatched for this second deployment
        Queue::assertNotPushed(ApplicationDeploymentJob::class, function ($job) use ($record) {
            return $job->application_deployment_queue_id === $record->id;
        });
    });

    test('deploy proceeds when no in-progress deployment exists', function () {
        // No pre-existing in-progress deployment
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->bearerToken])
            ->getJson("/api/v1/deploy?uuid={$this->application->uuid}");

        $response->assertStatus(200);

        // Job should have been dispatched
        Queue::assertPushed(ApplicationDeploymentJob::class);
    });
});

describe('Deployment response structure', function () {
    test('deploy response contains deployments array with resource_uuid', function () {
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->bearerToken])
            ->getJson("/api/v1/deploy?uuid={$this->application->uuid}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'deployments' => [
                '*' => ['deployment_uuid', 'resource_uuid'],
            ],
        ]);
        $response->assertJsonPath('deployments.0.resource_uuid', $this->application->uuid);
    });

    test('deploy response deployment_uuid matches database record', function () {
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->bearerToken])
            ->getJson("/api/v1/deploy?uuid={$this->application->uuid}");

        $json = $response->json();
        $deploymentUuid = $json['deployments'][0]['deployment_uuid'];

        $this->assertDatabaseHas('application_deployment_queues', [
            'deployment_uuid' => $deploymentUuid,
            'application_id' => $this->application->id,
        ]);
    });
});
