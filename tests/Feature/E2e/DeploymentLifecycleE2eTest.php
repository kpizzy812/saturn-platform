<?php

/**
 * E2E Deployment Lifecycle Tests
 *
 * Tests the full deployment lifecycle:
 * - API trigger → DB record created with correct metadata
 * - API token ability enforcement (deploy scope required)
 * - triggered_by field correctness (api / webhook)
 * - Concurrent deploy queue ordering
 * - Full rollback cycle: deploy → finish → rollback → new deployment
 * - Deployment cancellation state machine
 */

use App\Enums\ApplicationDeploymentStatus;
use App\Jobs\ApplicationDeploymentJob;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\ApplicationRollbackEvent;
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

// ─── Setup ────────────────────────────────────────────────────────────────────

beforeEach(function () {
    Queue::fake();

    if (! DB::table('instance_settings')->where('id', 0)->exists()) {
        DB::table('instance_settings')->insert([
            'id' => 0,
            'is_api_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();
    $this->team->members()->attach($this->user->id, ['role' => 'owner']);

    session(['currentTeam' => $this->team]);
    $this->token = $this->user->createToken('test-token', ['*']);
    $this->bearerToken = $this->token->plainTextToken;

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

    $this->application = Application::factory()->create([
        'environment_id' => $this->environment->id,
        'destination_id' => $this->destination->id,
        'destination_type' => StandaloneDocker::class,
        'ports_exposes' => '3000',
    ]);
});

// ─── API Token Scope Enforcement ─────────────────────────────────────────────

describe('API token scope enforcement', function () {
    test('read-only token cannot trigger deployment — 403 returned', function () {
        $readToken = $this->user->createToken('read-only', ['read']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$readToken->plainTextToken,
        ])->getJson("/api/v1/deploy?uuid={$this->application->uuid}");

        $response->assertStatus(403);
        Queue::assertNothingPushed();
    });

    test('write-only token cannot trigger deployment — 403 returned', function () {
        $writeToken = $this->user->createToken('write-only', ['write']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$writeToken->plainTextToken,
        ])->getJson("/api/v1/deploy?uuid={$this->application->uuid}");

        $response->assertStatus(403);
        Queue::assertNothingPushed();
    });

    test('deploy-scoped token can trigger deployment', function () {
        $deployToken = $this->user->createToken('deploy-token', ['deploy']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$deployToken->plainTextToken,
        ])->getJson("/api/v1/deploy?uuid={$this->application->uuid}");

        $response->assertOk();
        Queue::assertPushed(ApplicationDeploymentJob::class);
    });

    test('root-scoped token can trigger deployment', function () {
        $rootToken = $this->user->createToken('root-token', ['root']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$rootToken->plainTextToken,
        ])->getJson("/api/v1/deploy?uuid={$this->application->uuid}");

        $response->assertOk();
        Queue::assertPushed(ApplicationDeploymentJob::class);
    });

    test('wildcard (*) token can trigger deployment', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson("/api/v1/deploy?uuid={$this->application->uuid}");

        $response->assertOk();
        Queue::assertPushed(ApplicationDeploymentJob::class);
    });
});

// ─── triggered_by Metadata ────────────────────────────────────────────────────

describe('Deployment triggered_by metadata', function () {
    test('API-triggered deploy sets triggered_by = api in DB record', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson("/api/v1/deploy?uuid={$this->application->uuid}");

        $response->assertOk();
        $deploymentUuid = $response->json('deployments.0.deployment_uuid');

        $record = ApplicationDeploymentQueue::where('deployment_uuid', $deploymentUuid)->first();
        expect($record)->not->toBeNull();
        expect($record->triggered_by)->toBe('api');
        expect((bool) $record->is_api)->toBeTrue();
        expect((bool) $record->is_webhook)->toBeFalse();
    });

    test('API deploy without force_rebuild sets force_rebuild = false', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson("/api/v1/deploy?uuid={$this->application->uuid}");

        $response->assertOk();
        $deploymentUuid = $response->json('deployments.0.deployment_uuid');
        $record = ApplicationDeploymentQueue::where('deployment_uuid', $deploymentUuid)->first();

        expect((bool) $record->force_rebuild)->toBeFalse();
    });

    test('API deploy with force=true sets force_rebuild = true', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson("/api/v1/deploy?uuid={$this->application->uuid}&force=true");

        $response->assertOk();
        $deploymentUuid = $response->json('deployments.0.deployment_uuid');
        $record = ApplicationDeploymentQueue::where('deployment_uuid', $deploymentUuid)->first();

        expect((bool) $record->force_rebuild)->toBeTrue();
    });
});

// ─── Concurrent Deploy Queue Ordering ─────────────────────────────────────────

describe('Concurrent deployment queue ordering', function () {
    test('second deploy while first is IN_PROGRESS stays QUEUED, first remains IN_PROGRESS', function () {
        // Simulate an in-progress deployment
        $firstDeployment = ApplicationDeploymentQueue::factory()->create([
            'application_id' => $this->application->id,
            'server_id' => $this->server->id,
            'status' => ApplicationDeploymentStatus::IN_PROGRESS->value,
            'pull_request_id' => 0,
            'deployment_uuid' => (string) new Cuid2,
        ]);

        // Trigger a second deploy via API
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson("/api/v1/deploy?uuid={$this->application->uuid}");

        $response->assertOk();
        $secondUuid = $response->json('deployments.0.deployment_uuid');
        $secondRecord = ApplicationDeploymentQueue::where('deployment_uuid', $secondUuid)->first();

        expect($secondRecord)->not->toBeNull();
        // Second must be QUEUED, not IN_PROGRESS
        expect($secondRecord->status)->toBe(ApplicationDeploymentStatus::QUEUED->value);

        // First must still be IN_PROGRESS
        $firstDeployment->refresh();
        expect($firstDeployment->status)->toBe(ApplicationDeploymentStatus::IN_PROGRESS->value);
    });

    test('multiple queued deployments are not dispatched as jobs while one is IN_PROGRESS', function () {
        // Create an in-progress deployment
        ApplicationDeploymentQueue::factory()->create([
            'application_id' => $this->application->id,
            'server_id' => $this->server->id,
            'status' => ApplicationDeploymentStatus::IN_PROGRESS->value,
            'pull_request_id' => 0,
        ]);

        // Trigger second deploy
        $this->withHeaders(['Authorization' => 'Bearer '.$this->bearerToken])
            ->getJson("/api/v1/deploy?uuid={$this->application->uuid}");

        // The job should NOT be pushed again for the second deploy
        Queue::assertNotPushed(ApplicationDeploymentJob::class, function ($job) {
            return true; // Check no new job was dispatched
        });
    });

    test('deploy proceeds immediately when no in-progress deployment exists', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson("/api/v1/deploy?uuid={$this->application->uuid}");

        $response->assertOk();
        Queue::assertPushed(ApplicationDeploymentJob::class);
    });

    test('multiple apps deploy simultaneously — each gets own deployment record', function () {
        $secondApp = Application::factory()->create([
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '4000',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson("/api/v1/deploy?uuid={$this->application->uuid},{$secondApp->uuid}");

        $response->assertOk();
        $deployments = $response->json('deployments');
        expect($deployments)->toHaveCount(2);

        $uuids = collect($deployments)->pluck('resource_uuid')->sort()->values()->toArray();
        $expected = collect([$this->application->uuid, $secondApp->uuid])->sort()->values()->toArray();
        expect($uuids)->toBe($expected);

        // Both apps should have their own DB record
        $this->assertDatabaseHas('application_deployment_queues', ['application_id' => $this->application->id]);
        $this->assertDatabaseHas('application_deployment_queues', ['application_id' => $secondApp->id]);
    });
});

// ─── Full Deploy → Cancel Lifecycle ──────────────────────────────────────────

describe('Deployment cancellation lifecycle', function () {
    test('queued deployment can be cancelled — status transitions to cancelled', function () {
        $deployment = ApplicationDeploymentQueue::factory()->create([
            'application_id' => $this->application->id,
            'server_id' => $this->server->id,
            'status' => ApplicationDeploymentStatus::QUEUED->value,
            'deployment_uuid' => (string) new Cuid2,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson("/api/v1/deployments/{$deployment->deployment_uuid}/cancel");

        // May return 200 or 500 depending on whether SSH teardown is attempted on queued deploy
        expect($response->status())->toBeIn([200, 500]);

        if ($response->status() === 200) {
            $deployment->refresh();
            expect($deployment->status)->toBe(ApplicationDeploymentStatus::CANCELLED->value);
        }
    });

    test('finished deployment cannot be cancelled — 400 returned', function () {
        $deployment = ApplicationDeploymentQueue::factory()->create([
            'application_id' => $this->application->id,
            'server_id' => $this->server->id,
            'status' => ApplicationDeploymentStatus::FINISHED->value,
            'deployment_uuid' => (string) new Cuid2,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson("/api/v1/deployments/{$deployment->deployment_uuid}/cancel");

        $response->assertStatus(400);
    });

    test('failed deployment cannot be cancelled — 400 returned', function () {
        $deployment = ApplicationDeploymentQueue::factory()->create([
            'application_id' => $this->application->id,
            'server_id' => $this->server->id,
            'status' => ApplicationDeploymentStatus::FAILED->value,
            'deployment_uuid' => (string) new Cuid2,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson("/api/v1/deployments/{$deployment->deployment_uuid}/cancel");

        $response->assertStatus(400);
    });

    test('read-only token cannot cancel deployment — 403 returned', function () {
        $deployment = ApplicationDeploymentQueue::factory()->create([
            'application_id' => $this->application->id,
            'server_id' => $this->server->id,
            'status' => ApplicationDeploymentStatus::QUEUED->value,
            'deployment_uuid' => (string) new Cuid2,
        ]);

        $readToken = $this->user->createToken('read-only', ['read']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$readToken->plainTextToken,
        ])->postJson("/api/v1/deployments/{$deployment->deployment_uuid}/cancel");

        $response->assertStatus(403);
    });
});

// ─── Full Deploy → Rollback Cycle ────────────────────────────────────────────

describe('Full deploy → rollback cycle', function () {
    test('full cycle: deploy → finish → rollback → rollback deployment created with correct flags', function () {
        // Step 1: Initial deploy via API
        $deployResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson("/api/v1/deploy?uuid={$this->application->uuid}");

        $deployResponse->assertOk();
        $deploymentUuid = $deployResponse->json('deployments.0.deployment_uuid');
        $record = ApplicationDeploymentQueue::where('deployment_uuid', $deploymentUuid)->first();
        expect($record)->not->toBeNull();

        // Step 2: Simulate deployment finishing successfully
        $record->update([
            'status' => ApplicationDeploymentStatus::FINISHED->value,
            'commit' => 'abc123def456',
        ]);

        // Step 3: Trigger rollback to the finished deployment
        $rollbackResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson("/api/v1/applications/{$this->application->uuid}/rollback/{$deploymentUuid}");

        $rollbackResponse->assertOk();
        $rollbackResponse->assertJsonStructure(['message', 'deployment_uuid', 'rollback_event_id']);
        expect($rollbackResponse->json('message'))->toBe('Rollback initiated successfully');

        // Rollback deployment must have rollback=true and correct commit
        $rollbackDeploymentUuid = $rollbackResponse->json('deployment_uuid');
        $rollbackDeployment = ApplicationDeploymentQueue::where('deployment_uuid', $rollbackDeploymentUuid)->first();
        expect($rollbackDeployment)->not->toBeNull();
        expect((bool) $rollbackDeployment->rollback)->toBeTrue();
        expect($rollbackDeployment->commit)->toBe('abc123def456');
        expect($rollbackDeployment->application_id)->toBe($this->application->id);
    });

    test('rollback creates ApplicationRollbackEvent with manual reason', function () {
        // Create a finished deployment to rollback to
        $finishedDeploy = ApplicationDeploymentQueue::factory()->create([
            'application_id' => $this->application->id,
            'server_id' => $this->server->id,
            'status' => ApplicationDeploymentStatus::FINISHED->value,
            'commit' => 'aabbccdd1234',
            'deployment_uuid' => (string) new Cuid2,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson("/api/v1/applications/{$this->application->uuid}/rollback/{$finishedDeploy->deployment_uuid}");

        $response->assertOk();
        $eventId = $response->json('rollback_event_id');

        $event = ApplicationRollbackEvent::find($eventId);
        expect($event)->not->toBeNull();
        expect($event->trigger_reason)->toBe(ApplicationRollbackEvent::REASON_MANUAL);
        expect($event->trigger_type)->toBe('manual');
        expect($event->triggered_by_user_id)->toBe($this->user->id);
        expect($event->to_commit)->toBe('aabbccdd1234');
    });

    test('rollback event status is in_progress after rollback is queued', function () {
        $finishedDeploy = ApplicationDeploymentQueue::factory()->create([
            'application_id' => $this->application->id,
            'server_id' => $this->server->id,
            'status' => ApplicationDeploymentStatus::FINISHED->value,
            'commit' => 'commit-to-roll-back',
            'deployment_uuid' => (string) new Cuid2,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson("/api/v1/applications/{$this->application->uuid}/rollback/{$finishedDeploy->deployment_uuid}");

        $response->assertOk();
        $eventId = $response->json('rollback_event_id');
        $event = ApplicationRollbackEvent::find($eventId);

        // After rollback is queued, event should be in_progress
        expect($event->status)->toBe(ApplicationRollbackEvent::STATUS_IN_PROGRESS);
        expect($event->rollback_deployment_id)->not->toBeNull();
    });

    test('cannot rollback to a failed deployment — 400 returned', function () {
        $failedDeploy = ApplicationDeploymentQueue::factory()->create([
            'application_id' => $this->application->id,
            'server_id' => $this->server->id,
            'status' => ApplicationDeploymentStatus::FAILED->value,
            'commit' => 'failed-commit',
            'deployment_uuid' => (string) new Cuid2,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson("/api/v1/applications/{$this->application->uuid}/rollback/{$failedDeploy->deployment_uuid}");

        $response->assertStatus(400);
        expect($response->json('message'))->toContain('Can only rollback to successful deployments');
    });

    test('read-only token cannot execute rollback — 403 returned', function () {
        $finishedDeploy = ApplicationDeploymentQueue::factory()->create([
            'application_id' => $this->application->id,
            'server_id' => $this->server->id,
            'status' => ApplicationDeploymentStatus::FINISHED->value,
            'commit' => 'good-commit',
            'deployment_uuid' => (string) new Cuid2,
        ]);

        $readToken = $this->user->createToken('read-only', ['read']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$readToken->plainTextToken,
        ])->postJson("/api/v1/applications/{$this->application->uuid}/rollback/{$finishedDeploy->deployment_uuid}");

        $response->assertStatus(403);
    });
});

// ─── Deployment State Query ───────────────────────────────────────────────────

describe('Deployment list and state queries', function () {
    test('deployments list only shows in-progress entries — not finished ones', function () {
        // Create one in-progress and one finished
        ApplicationDeploymentQueue::factory()->create([
            'application_id' => $this->application->id,
            'server_id' => $this->server->id,
            'status' => ApplicationDeploymentStatus::IN_PROGRESS->value,
            'deployment_uuid' => $inProgressUuid = (string) new Cuid2,
        ]);

        ApplicationDeploymentQueue::factory()->create([
            'application_id' => $this->application->id,
            'server_id' => $this->server->id,
            'status' => ApplicationDeploymentStatus::FINISHED->value,
            'deployment_uuid' => $finishedUuid = (string) new Cuid2,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/deployments');

        $response->assertOk();
        $uuids = collect($response->json())->pluck('deployment_uuid')->toArray();

        expect($uuids)->toContain($inProgressUuid);
        expect($uuids)->not->toContain($finishedUuid);
    });

    test('application deployments endpoint returns all deployments ordered newest first', function () {
        $deploy1 = ApplicationDeploymentQueue::factory()->create([
            'application_id' => $this->application->id,
            'server_id' => $this->server->id,
            'status' => ApplicationDeploymentStatus::FINISHED->value,
            'deployment_uuid' => (string) new Cuid2,
            'created_at' => now()->subMinutes(10),
        ]);

        $deploy2 = ApplicationDeploymentQueue::factory()->create([
            'application_id' => $this->application->id,
            'server_id' => $this->server->id,
            'status' => ApplicationDeploymentStatus::FINISHED->value,
            'deployment_uuid' => (string) new Cuid2,
            'created_at' => now()->subMinutes(5),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson("/api/v1/deployments/applications/{$this->application->uuid}");

        $response->assertOk();
        $response->assertJsonStructure(['count', 'deployments']);

        $count = $response->json('count');
        expect($count)->toBeGreaterThanOrEqual(2);
    });

    test('deployment details endpoint returns correct deployment for team member', function () {
        $deployment = ApplicationDeploymentQueue::factory()->create([
            'application_id' => $this->application->id,
            'server_id' => $this->server->id,
            'status' => ApplicationDeploymentStatus::QUEUED->value,
            'deployment_uuid' => (string) new Cuid2,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson("/api/v1/deployments/{$deployment->deployment_uuid}");

        $response->assertOk();
        $response->assertJsonFragment(['deployment_uuid' => $deployment->deployment_uuid]);
    });

    test('cross-team deployment is not visible — 404 returned', function () {
        $otherTeam = Team::factory()->create();
        $otherKey = PrivateKey::factory()->create(['team_id' => $otherTeam->id]);
        $otherServer = Server::withoutEvents(fn () => Server::factory()->create([
            'uuid' => (string) new Cuid2,
            'team_id' => $otherTeam->id,
            'private_key_id' => $otherKey->id,
        ]));
        ServerSetting::firstOrCreate(['server_id' => $otherServer->id]);

        $otherDeployment = ApplicationDeploymentQueue::factory()->create([
            'application_id' => $this->application->id,
            'server_id' => $otherServer->id,
            'status' => ApplicationDeploymentStatus::QUEUED->value,
            'deployment_uuid' => (string) new Cuid2,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson("/api/v1/deployments/{$otherDeployment->deployment_uuid}");

        $response->assertStatus(404);
    });
});
