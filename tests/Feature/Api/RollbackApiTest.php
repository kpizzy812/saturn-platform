<?php

use App\Enums\ApplicationDeploymentStatus;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\ApplicationRollbackEvent;
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
    Queue::fake();

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

    // Create some finished deployments for rollback targets
    $this->finishedDeployment = ApplicationDeploymentQueue::create([
        'application_id' => $this->application->id,
        'deployment_uuid' => (string) new Cuid2,
        'status' => ApplicationDeploymentStatus::FINISHED->value,
        'commit' => 'abc123def456',
        'server_id' => $this->server->id,
        'server_name' => $this->server->name,
        'destination_id' => $this->destination->id,
        'application_name' => $this->application->name,
        'deployment_url' => '/test/deployment/1',
    ]);

    $this->latestDeployment = ApplicationDeploymentQueue::create([
        'application_id' => $this->application->id,
        'deployment_uuid' => (string) new Cuid2,
        'status' => ApplicationDeploymentStatus::FINISHED->value,
        'commit' => 'def789ghi012',
        'server_id' => $this->server->id,
        'server_name' => $this->server->name,
        'destination_id' => $this->destination->id,
        'application_name' => $this->application->name,
        'deployment_url' => '/test/deployment/2',
    ]);
});

// =====================================================================
// GET /api/v1/applications/{uuid}/rollback-events
// =====================================================================

describe('GET rollback-events', function () {
    test('returns empty array when no rollback events exist', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson("/api/v1/applications/{$this->application->uuid}/rollback-events");

        $response->assertStatus(200);
        $response->assertJson([]);
    });

    test('returns rollback events with correct structure', function () {
        $event = ApplicationRollbackEvent::create([
            'application_id' => $this->application->id,
            'failed_deployment_id' => $this->latestDeployment->id,
            'triggered_by_user_id' => $this->user->id,
            'trigger_reason' => ApplicationRollbackEvent::REASON_MANUAL,
            'trigger_type' => 'manual',
            'status' => ApplicationRollbackEvent::STATUS_SUCCESS,
            'from_commit' => 'def789ghi012',
            'to_commit' => 'abc123def456',
            'triggered_at' => now(),
            'completed_at' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson("/api/v1/applications/{$this->application->uuid}/rollback-events");

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment([
            'id' => $event->id,
            'trigger_reason' => 'manual',
            'trigger_type' => 'manual',
            'status' => 'success',
            'from_commit' => 'def789ghi012',
            'to_commit' => 'abc123def456',
        ]);
    });

    test('returns events ordered by newest first', function () {
        $event1 = ApplicationRollbackEvent::create([
            'application_id' => $this->application->id,
            'trigger_reason' => ApplicationRollbackEvent::REASON_MANUAL,
            'trigger_type' => 'manual',
            'status' => ApplicationRollbackEvent::STATUS_SUCCESS,
            'triggered_at' => now()->subHour(),
        ]);

        // Force created_at to be later so ordering is deterministic
        \Illuminate\Support\Facades\DB::table('application_rollback_events')
            ->where('id', $event1->id)
            ->update(['created_at' => now()->subHour()]);

        $event2 = ApplicationRollbackEvent::create([
            'application_id' => $this->application->id,
            'trigger_reason' => ApplicationRollbackEvent::REASON_CRASH_LOOP,
            'trigger_type' => 'automatic',
            'status' => ApplicationRollbackEvent::STATUS_SUCCESS,
            'triggered_at' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson("/api/v1/applications/{$this->application->uuid}/rollback-events");

        $response->assertStatus(200);
        $json = $response->json();
        expect(count($json))->toBe(2);
        // Newest (event2) should come first due to created_at ordering
        expect($json[0]['trigger_reason'])->toBe(ApplicationRollbackEvent::REASON_CRASH_LOOP);
        expect($json[1]['trigger_reason'])->toBe(ApplicationRollbackEvent::REASON_MANUAL);
    });

    test('includes triggered_by_user info', function () {
        ApplicationRollbackEvent::create([
            'application_id' => $this->application->id,
            'triggered_by_user_id' => $this->user->id,
            'trigger_reason' => ApplicationRollbackEvent::REASON_MANUAL,
            'trigger_type' => 'manual',
            'status' => ApplicationRollbackEvent::STATUS_TRIGGERED,
            'triggered_at' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson("/api/v1/applications/{$this->application->uuid}/rollback-events");

        $response->assertStatus(200);
        $json = $response->json();
        expect($json[0]['triggered_by_user'])->not->toBeNull();
        expect($json[0]['triggered_by_user']['id'])->toBe($this->user->id);
        expect($json[0]['triggered_by_user'])->toHaveKeys(['name', 'email']);
    });

    test('returns null for triggered_by_user on automatic rollbacks', function () {
        ApplicationRollbackEvent::create([
            'application_id' => $this->application->id,
            'trigger_reason' => ApplicationRollbackEvent::REASON_CRASH_LOOP,
            'trigger_type' => 'automatic',
            'status' => ApplicationRollbackEvent::STATUS_SUCCESS,
            'triggered_at' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson("/api/v1/applications/{$this->application->uuid}/rollback-events");

        $response->assertStatus(200);
        $json = $response->json();
        expect($json[0]['triggered_by_user'])->toBeNull();
    });

    test('rejects unauthenticated request', function () {
        $response = $this->getJson("/api/v1/applications/{$this->application->uuid}/rollback-events");

        $response->assertStatus(401);
    });

    test('returns 404 for non-existent application', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/applications/non-existent-uuid/rollback-events');

        $response->assertStatus(404);
    });

    test('cannot access rollback events from another team', function () {
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnv = Environment::factory()->create(['project_id' => $otherProject->id]);

        $otherApp = Application::factory()->create([
            'environment_id' => $otherEnv->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson("/api/v1/applications/{$otherApp->uuid}/rollback-events");

        $response->assertStatus(404);
    });
});

// =====================================================================
// POST /api/v1/applications/{uuid}/rollback/{deploymentUuid}
// =====================================================================

describe('POST execute_rollback', function () {
    test('successfully initiates rollback to a finished deployment', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson("/api/v1/applications/{$this->application->uuid}/rollback/{$this->finishedDeployment->deployment_uuid}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'deployment_uuid',
            'rollback_event_id',
        ]);
        $response->assertJsonFragment(['message' => 'Rollback initiated successfully']);

        // Verify rollback event was created
        $eventId = $response->json('rollback_event_id');
        $event = ApplicationRollbackEvent::find($eventId);
        expect($event)->not->toBeNull();
        expect($event->trigger_reason)->toBe(ApplicationRollbackEvent::REASON_MANUAL);
        expect($event->trigger_type)->toBe('manual');
        expect($event->to_commit)->toBe('abc123def456');
        expect($event->triggered_by_user_id)->toBe($this->user->id);
    });

    test('creates rollback deployment in queue with rollback=true', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson("/api/v1/applications/{$this->application->uuid}/rollback/{$this->finishedDeployment->deployment_uuid}");

        $response->assertStatus(200);
        $deploymentUuid = $response->json('deployment_uuid');

        $deployment = ApplicationDeploymentQueue::where('deployment_uuid', $deploymentUuid)->first();
        expect($deployment)->not->toBeNull();
        expect((bool) $deployment->rollback)->toBeTrue();
        expect($deployment->commit)->toBe('abc123def456');
        expect($deployment->application_id)->toBe($this->application->id);
    });

    test('rollback event transitions to in_progress when deployment is queued', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson("/api/v1/applications/{$this->application->uuid}/rollback/{$this->finishedDeployment->deployment_uuid}");

        $response->assertStatus(200);
        $eventId = $response->json('rollback_event_id');

        $event = ApplicationRollbackEvent::find($eventId);
        expect($event->status)->toBe(ApplicationRollbackEvent::STATUS_IN_PROGRESS);
        expect($event->rollback_deployment_id)->not->toBeNull();
    });

    test('rejects rollback to non-finished deployment', function () {
        $failedDeployment = ApplicationDeploymentQueue::create([
            'application_id' => $this->application->id,
            'deployment_uuid' => (string) new Cuid2,
            'status' => ApplicationDeploymentStatus::FAILED->value,
            'commit' => 'failed999',
            'server_id' => $this->server->id,
            'server_name' => $this->server->name,
            'destination_id' => $this->destination->id,
            'application_name' => $this->application->name,
            'deployment_url' => '/test/deployment/failed',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson("/api/v1/applications/{$this->application->uuid}/rollback/{$failedDeployment->deployment_uuid}");

        $response->assertStatus(400);
        $response->assertJsonFragment(['message' => 'Can only rollback to successful deployments']);
    });

    test('returns 404 for non-existent deployment uuid', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson("/api/v1/applications/{$this->application->uuid}/rollback/non-existent-deployment-uuid");

        $response->assertStatus(404);
        $response->assertJsonFragment(['message' => 'Deployment not found']);
    });

    test('returns 404 for non-existent application', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson("/api/v1/applications/non-existent-uuid/rollback/{$this->finishedDeployment->deployment_uuid}");

        $response->assertStatus(404);
    });

    test('rejects unauthenticated request', function () {
        $response = $this->postJson("/api/v1/applications/{$this->application->uuid}/rollback/{$this->finishedDeployment->deployment_uuid}");

        $response->assertStatus(401);
    });

    test('cannot rollback application from another team', function () {
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnv = Environment::factory()->create(['project_id' => $otherProject->id]);

        $otherApp = Application::factory()->create([
            'environment_id' => $otherEnv->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson("/api/v1/applications/{$otherApp->uuid}/rollback/{$this->finishedDeployment->deployment_uuid}");

        $response->assertStatus(404);
    });

    test('cannot rollback deployment that belongs to different application', function () {
        $otherApp = Application::factory()->create([
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
        ]);

        $otherDeployment = ApplicationDeploymentQueue::create([
            'application_id' => $otherApp->id,
            'deployment_uuid' => (string) new Cuid2,
            'status' => ApplicationDeploymentStatus::FINISHED->value,
            'commit' => 'other_commit',
            'server_id' => $this->server->id,
            'server_name' => $this->server->name,
            'destination_id' => $this->destination->id,
            'application_name' => $otherApp->name,
            'deployment_url' => '/test/deployment/other',
        ]);

        // Try to rollback to a deployment from a different application
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson("/api/v1/applications/{$this->application->uuid}/rollback/{$otherDeployment->deployment_uuid}");

        $response->assertStatus(404);
    });

    test('read-only token cannot execute rollback', function () {
        $readToken = $this->user->createToken('read-only', ['read']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$readToken->plainTextToken,
        ])->postJson("/api/v1/applications/{$this->application->uuid}/rollback/{$this->finishedDeployment->deployment_uuid}");

        // Should be rejected by api.ability:deploy middleware
        $response->assertStatus(403);
    });
});

// =====================================================================
// GET /api/v1/applications/{uuid}/deployments
// =====================================================================

describe('GET deployments', function () {
    test('returns deployments list', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson("/api/v1/applications/{$this->application->uuid}/deployments");

        $response->assertStatus(200);
        $json = $response->json();
        expect(count($json))->toBeGreaterThanOrEqual(2);
    });

    test('deployments include rollback flag', function () {
        // Create a rollback deployment
        ApplicationDeploymentQueue::create([
            'application_id' => $this->application->id,
            'deployment_uuid' => (string) new Cuid2,
            'status' => ApplicationDeploymentStatus::FINISHED->value,
            'commit' => 'rollback_commit',
            'rollback' => true,
            'server_id' => $this->server->id,
            'server_name' => $this->server->name,
            'destination_id' => $this->destination->id,
            'application_name' => $this->application->name,
            'deployment_url' => '/test/deployment/rollback',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson("/api/v1/applications/{$this->application->uuid}/deployments");

        $response->assertStatus(200);
        $json = $response->json();

        // Find the rollback deployment
        $rollbackDeploy = collect($json)->firstWhere('commit', 'rollback_commit');
        expect($rollbackDeploy)->not->toBeNull();
        expect((bool) $rollbackDeploy['rollback'])->toBeTrue();
    });
});

// =====================================================================
// Rollback Event Status Transitions
// =====================================================================

describe('Rollback event status transitions', function () {
    test('markInProgress sets status and rollback_deployment_id', function () {
        $event = ApplicationRollbackEvent::create([
            'application_id' => $this->application->id,
            'failed_deployment_id' => $this->latestDeployment->id,
            'trigger_reason' => ApplicationRollbackEvent::REASON_MANUAL,
            'trigger_type' => 'manual',
            'status' => ApplicationRollbackEvent::STATUS_TRIGGERED,
            'triggered_at' => now(),
        ]);

        $event->markInProgress($this->finishedDeployment->id);
        $event->refresh();

        expect($event->status)->toBe(ApplicationRollbackEvent::STATUS_IN_PROGRESS);
        expect($event->rollback_deployment_id)->toBe($this->finishedDeployment->id);
    });

    test('markSuccess sets status and completed_at', function () {
        $event = ApplicationRollbackEvent::create([
            'application_id' => $this->application->id,
            'trigger_reason' => ApplicationRollbackEvent::REASON_CRASH_LOOP,
            'trigger_type' => 'automatic',
            'status' => ApplicationRollbackEvent::STATUS_IN_PROGRESS,
            'triggered_at' => now(),
        ]);

        $event->markSuccess();
        $event->refresh();

        expect($event->status)->toBe(ApplicationRollbackEvent::STATUS_SUCCESS);
        expect($event->completed_at)->not->toBeNull();
    });

    test('markFailed sets status, error_message, and completed_at', function () {
        $event = ApplicationRollbackEvent::create([
            'application_id' => $this->application->id,
            'trigger_reason' => ApplicationRollbackEvent::REASON_HEALTH_CHECK_FAILED,
            'trigger_type' => 'automatic',
            'status' => ApplicationRollbackEvent::STATUS_IN_PROGRESS,
            'triggered_at' => now(),
        ]);

        $event->markFailed('Docker build failed');
        $event->refresh();

        expect($event->status)->toBe(ApplicationRollbackEvent::STATUS_FAILED);
        expect($event->error_message)->toBe('Docker build failed');
        expect($event->completed_at)->not->toBeNull();
    });

    test('full lifecycle: triggered → in_progress → success', function () {
        // Step 1: Create event (triggered)
        $event = ApplicationRollbackEvent::createEvent(
            application: $this->application,
            reason: ApplicationRollbackEvent::REASON_MANUAL,
            type: 'manual',
            failedDeployment: $this->latestDeployment,
            user: $this->user
        );

        expect($event->status)->toBe(ApplicationRollbackEvent::STATUS_TRIGGERED);
        expect($event->trigger_reason)->toBe(ApplicationRollbackEvent::REASON_MANUAL);
        expect($event->trigger_type)->toBe('manual');
        expect($event->failed_deployment_id)->toBe($this->latestDeployment->id);
        expect($event->from_commit)->toBe($this->latestDeployment->commit);

        // Step 2: Mark in progress
        $event->markInProgress($this->finishedDeployment->id);
        $event->refresh();
        expect($event->status)->toBe(ApplicationRollbackEvent::STATUS_IN_PROGRESS);

        // Step 3: Mark success
        $event->markSuccess();
        $event->refresh();
        expect($event->status)->toBe(ApplicationRollbackEvent::STATUS_SUCCESS);
        expect($event->completed_at)->not->toBeNull();
    });

    test('full lifecycle: triggered → in_progress → failed', function () {
        $event = ApplicationRollbackEvent::createEvent(
            application: $this->application,
            reason: ApplicationRollbackEvent::REASON_CRASH_LOOP,
            type: 'automatic',
            failedDeployment: $this->latestDeployment,
            metrics: ['restart_count' => 5, 'status' => 'exited:1']
        );

        expect($event->status)->toBe(ApplicationRollbackEvent::STATUS_TRIGGERED);
        expect($event->metrics_snapshot)->toBe(['restart_count' => 5, 'status' => 'exited:1']);

        $event->markInProgress($this->finishedDeployment->id);
        $event->refresh();
        expect($event->status)->toBe(ApplicationRollbackEvent::STATUS_IN_PROGRESS);

        $event->markFailed('Container failed to start');
        $event->refresh();
        expect($event->status)->toBe(ApplicationRollbackEvent::STATUS_FAILED);
        expect($event->error_message)->toBe('Container failed to start');
    });
});
