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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Visus\Cuid2\Cuid2;

uses(DatabaseTransactions::class);

beforeEach(function () {
    Queue::fake();

    Cache::flush();
    try {
        Cache::store('redis')->flush();
    } catch (\Throwable $e) {
    }

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

    $this->destination = StandaloneDocker::create([
        'uuid' => (string) new Cuid2,
        'name' => 'test-destination',
        'server_id' => $this->server->id,
        'network' => 'test-network',
    ]);

    $this->application = Application::factory()->create([
        'uuid' => (string) new Cuid2,
        'environment_id' => $this->environment->id,
        'destination_id' => $this->destination->id,
        'destination_type' => StandaloneDocker::class,
        'ports_exposes' => '80',
    ]);
});

// Helper to create a deployment record for the test application
function makeDeployment(array $overrides = []): ApplicationDeploymentQueue
{
    return ApplicationDeploymentQueue::create(array_merge([
        'application_id' => test()->application->id,
        'deployment_uuid' => (string) new Cuid2,
        'status' => ApplicationDeploymentStatus::FINISHED->value,
        'pull_request_id' => 0,
        'commit' => fake()->sha1(),
        'server_id' => test()->server->id,
        'server_name' => test()->server->name,
        'destination_id' => test()->destination->id,
        'application_name' => test()->application->name,
        'deployment_url' => '/test/deployment/1',
    ], $overrides));
}

// =====================================================================
// GET /api/v1/applications/{uuid}/deployments
// =====================================================================

describe('GET /api/v1/applications/{uuid}/deployments', function () {
    it('returns an empty list when no deployments exist', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson("/api/v1/applications/{$this->application->uuid}/deployments");

        $response->assertStatus(200);
        $response->assertJson([]);
        expect($response->json())->toBeArray()->toBeEmpty();
    });

    it('returns deployments ordered by created_at descending', function () {
        $older = makeDeployment(['commit' => 'aaa111', 'created_at' => now()->subHour()]);
        $newer = makeDeployment(['commit' => 'bbb222', 'created_at' => now()]);

        // Force timestamps so ordering is deterministic
        \Illuminate\Support\Facades\DB::table('application_deployment_queues')
            ->where('id', $older->id)
            ->update(['created_at' => now()->subHour()]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson("/api/v1/applications/{$this->application->uuid}/deployments");

        $response->assertStatus(200);
        $json = $response->json();
        expect(count($json))->toBeGreaterThanOrEqual(2);
        // The newest deployment must appear first
        expect($json[0]['commit'])->toBe('bbb222');
        expect($json[1]['commit'])->toBe('aaa111');
    });

    it('respects the take parameter for pagination', function () {
        // Create 5 deployments
        foreach (range(1, 5) as $i) {
            makeDeployment(['commit' => "commit{$i}"]);
        }

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson("/api/v1/applications/{$this->application->uuid}/deployments?take=3");

        $response->assertStatus(200);
        expect($response->json())->toHaveCount(3);
    });

    it('respects the skip parameter for pagination', function () {
        foreach (range(1, 5) as $i) {
            $d = makeDeployment(['commit' => "commit{$i}"]);
            \Illuminate\Support\Facades\DB::table('application_deployment_queues')
                ->where('id', $d->id)
                ->update(['created_at' => now()->subMinutes(6 - $i)]);
        }

        // Skip the 2 newest, get the rest (default take=20)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson("/api/v1/applications/{$this->application->uuid}/deployments?skip=2");

        $response->assertStatus(200);
        expect($response->json())->toHaveCount(3);
    });

    it('combines take and skip correctly for paginated results', function () {
        foreach (range(1, 10) as $i) {
            $d = makeDeployment(['commit' => "commit{$i}"]);
            \Illuminate\Support\Facades\DB::table('application_deployment_queues')
                ->where('id', $d->id)
                ->update(['created_at' => now()->subMinutes(11 - $i)]);
        }

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson("/api/v1/applications/{$this->application->uuid}/deployments?take=4&skip=3");

        $response->assertStatus(200);
        expect($response->json())->toHaveCount(4);
    });

    it('filters out pull request deployments (pull_request_id != 0)', function () {
        // Regular deployment — should be included
        makeDeployment(['pull_request_id' => 0, 'commit' => 'regular_commit']);

        // PR deployment — must be excluded
        makeDeployment(['pull_request_id' => 42, 'commit' => 'pr_commit']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson("/api/v1/applications/{$this->application->uuid}/deployments");

        $response->assertStatus(200);
        $commits = collect($response->json())->pluck('commit');
        expect($commits)->toContain('regular_commit');
        expect($commits)->not->toContain('pr_commit');
    });

    it('returns 404 for a non-existent application UUID', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/applications/this-uuid-does-not-exist/deployments');

        $response->assertStatus(404);
        $response->assertJsonFragment(['message' => 'Application not found']);
    });

    it('returns 404 when the application belongs to another team', function () {
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnv = Environment::factory()->create(['project_id' => $otherProject->id]);

        $otherApp = Application::factory()->create([
            'uuid' => (string) new Cuid2,
            'environment_id' => $otherEnv->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '80',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson("/api/v1/applications/{$otherApp->uuid}/deployments");

        $response->assertStatus(404);
    });

    it('uses 20 as the default take value', function () {
        // Create 25 deployments — default take must cap at 20
        foreach (range(1, 25) as $i) {
            makeDeployment();
        }

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson("/api/v1/applications/{$this->application->uuid}/deployments");

        $response->assertStatus(200);
        expect(count($response->json()))->toBe(20);
    });

    it('returns 401 for unauthenticated requests', function () {
        $response = $this->getJson("/api/v1/applications/{$this->application->uuid}/deployments");

        $response->assertStatus(401);
    });
});

// =====================================================================
// GET /api/v1/applications/{uuid}/rollback-events
// =====================================================================

describe('GET /api/v1/applications/{uuid}/rollback-events', function () {
    it('returns an empty list when no rollback events exist', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson("/api/v1/applications/{$this->application->uuid}/rollback-events");

        $response->assertStatus(200);
        expect($response->json())->toBeArray()->toBeEmpty();
    });

    it('returns rollback events with the expected response structure', function () {
        $deployment = makeDeployment(['commit' => 'abc111']);

        $event = ApplicationRollbackEvent::create([
            'application_id' => $this->application->id,
            'failed_deployment_id' => $deployment->id,
            'triggered_by_user_id' => $this->user->id,
            'trigger_reason' => ApplicationRollbackEvent::REASON_MANUAL,
            'trigger_type' => 'manual',
            'status' => ApplicationRollbackEvent::STATUS_SUCCESS,
            'from_commit' => 'abc111',
            'to_commit' => 'def222',
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
            'application_id' => $this->application->id,
            'trigger_reason' => ApplicationRollbackEvent::REASON_MANUAL,
            'trigger_type' => 'manual',
            'status' => ApplicationRollbackEvent::STATUS_SUCCESS,
            'from_commit' => 'abc111',
            'to_commit' => 'def222',
        ]);
    });

    it('includes triggered_by_user with id, name, and email for manual rollbacks', function () {
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
        expect($json[0]['triggered_by_user'])->toHaveKeys(['id', 'name', 'email']);
    });

    it('sets triggered_by_user to null for automatic rollbacks with no user', function () {
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
        expect($response->json()[0]['triggered_by_user'])->toBeNull();
    });

    it('returns 404 for a non-existent application UUID', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/applications/non-existent-app-uuid/rollback-events');

        $response->assertStatus(404);
        $response->assertJsonFragment(['message' => 'Application not found']);
    });

    it('returns 404 when the application belongs to another team', function () {
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnv = Environment::factory()->create(['project_id' => $otherProject->id]);

        $otherApp = Application::factory()->create([
            'uuid' => (string) new Cuid2,
            'environment_id' => $otherEnv->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '80',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson("/api/v1/applications/{$otherApp->uuid}/rollback-events");

        $response->assertStatus(404);
    });

    it('returns 401 for unauthenticated requests', function () {
        $response = $this->getJson("/api/v1/applications/{$this->application->uuid}/rollback-events");

        $response->assertStatus(401);
    });
});

// =====================================================================
// POST /api/v1/applications/{uuid}/rollback/{deploymentUuid}
// =====================================================================

describe('POST /api/v1/applications/{uuid}/rollback/{deploymentUuid}', function () {
    it('initiates rollback to a finished deployment and returns the expected payload', function () {
        $target = makeDeployment([
            'status' => ApplicationDeploymentStatus::FINISHED->value,
            'commit' => 'target_commit_abc',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson("/api/v1/applications/{$this->application->uuid}/rollback/{$target->deployment_uuid}");

        $response->assertStatus(200);
        $response->assertJsonStructure(['message', 'deployment_uuid', 'rollback_event_id']);
        $response->assertJsonFragment(['message' => 'Rollback initiated successfully']);
    });

    it('creates an ApplicationRollbackEvent with reason=manual and the correct to_commit', function () {
        $target = makeDeployment([
            'status' => ApplicationDeploymentStatus::FINISHED->value,
            'commit' => 'target_hash_xyz',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson("/api/v1/applications/{$this->application->uuid}/rollback/{$target->deployment_uuid}");

        $response->assertStatus(200);

        $event = ApplicationRollbackEvent::find($response->json('rollback_event_id'));
        expect($event)->not->toBeNull();
        expect($event->trigger_reason)->toBe(ApplicationRollbackEvent::REASON_MANUAL);
        expect($event->trigger_type)->toBe('manual');
        expect($event->to_commit)->toBe('target_hash_xyz');
        expect($event->triggered_by_user_id)->toBe($this->user->id);
    });

    it('returns 404 when the deployment UUID does not exist', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson("/api/v1/applications/{$this->application->uuid}/rollback/uuid-that-does-not-exist");

        $response->assertStatus(404);
        $response->assertJsonFragment(['message' => 'Deployment not found']);
    });

    it('returns 400 when rolling back to a failed deployment', function () {
        $failedDeployment = makeDeployment([
            'status' => ApplicationDeploymentStatus::FAILED->value,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson("/api/v1/applications/{$this->application->uuid}/rollback/{$failedDeployment->deployment_uuid}");

        $response->assertStatus(400);
        $response->assertJsonFragment(['message' => 'Can only rollback to successful deployments']);
    });

    it('returns 400 when rolling back to an in-progress deployment', function () {
        $inProgressDeployment = makeDeployment([
            'status' => ApplicationDeploymentStatus::IN_PROGRESS->value,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson("/api/v1/applications/{$this->application->uuid}/rollback/{$inProgressDeployment->deployment_uuid}");

        $response->assertStatus(400);
        $response->assertJsonFragment(['message' => 'Can only rollback to successful deployments']);
    });

    it('returns 400 when rolling back to a queued deployment', function () {
        $queued = makeDeployment([
            'status' => ApplicationDeploymentStatus::QUEUED->value,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson("/api/v1/applications/{$this->application->uuid}/rollback/{$queued->deployment_uuid}");

        $response->assertStatus(400);
    });

    it('returns 404 for a non-existent application UUID', function () {
        $target = makeDeployment();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson("/api/v1/applications/non-existent-app-uuid/rollback/{$target->deployment_uuid}");

        $response->assertStatus(404);
    });

    it('returns 404 when the application belongs to another team', function () {
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnv = Environment::factory()->create(['project_id' => $otherProject->id]);

        $otherApp = Application::factory()->create([
            'uuid' => (string) new Cuid2,
            'environment_id' => $otherEnv->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '80',
        ]);

        $target = makeDeployment();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson("/api/v1/applications/{$otherApp->uuid}/rollback/{$target->deployment_uuid}");

        $response->assertStatus(404);
    });

    it('returns 404 when the deployment belongs to a different application', function () {
        $sibling = Application::factory()->create([
            'uuid' => (string) new Cuid2,
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '8080',
        ]);

        // Deployment belongs to $sibling, not $this->application
        $siblingDeployment = ApplicationDeploymentQueue::create([
            'application_id' => $sibling->id,
            'deployment_uuid' => (string) new Cuid2,
            'status' => ApplicationDeploymentStatus::FINISHED->value,
            'pull_request_id' => 0,
            'commit' => 'sibling_commit',
            'server_id' => $this->server->id,
            'server_name' => $this->server->name,
            'destination_id' => $this->destination->id,
            'application_name' => $sibling->name,
            'deployment_url' => '/test/sibling/1',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson("/api/v1/applications/{$this->application->uuid}/rollback/{$siblingDeployment->deployment_uuid}");

        $response->assertStatus(404);
    });

    it('returns 401 for unauthenticated requests', function () {
        $target = makeDeployment();

        $response = $this->postJson("/api/v1/applications/{$this->application->uuid}/rollback/{$target->deployment_uuid}");

        $response->assertStatus(401);
    });

    it('returns 403 when the token lacks the deploy ability', function () {
        $readOnlyToken = $this->user->createToken('read-only-token', ['read']);
        $target = makeDeployment();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$readOnlyToken->plainTextToken,
        ])->postJson("/api/v1/applications/{$this->application->uuid}/rollback/{$target->deployment_uuid}");

        $response->assertStatus(403);
    });
});
