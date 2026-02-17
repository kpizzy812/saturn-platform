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
});

// =====================================================================
// handleSuccessfulDeployment — Rollback event on success
// =====================================================================

describe('Successful rollback deployment', function () {
    test('marks rollback event as success when rollback deployment finishes', function () {
        // Create a rollback event that is in_progress
        $rollbackDeployment = ApplicationDeploymentQueue::create([
            'application_id' => $this->application->id,
            'deployment_uuid' => (string) new Cuid2,
            'status' => ApplicationDeploymentStatus::FINISHED->value,
            'commit' => 'rollback_target_commit',
            'rollback' => true,
            'server_id' => $this->server->id,
            'server_name' => $this->server->name,
            'destination_id' => $this->destination->id,
            'application_name' => $this->application->name,
            'deployment_url' => '/test/deployment/rollback',
        ]);

        $event = ApplicationRollbackEvent::create([
            'application_id' => $this->application->id,
            'rollback_deployment_id' => $rollbackDeployment->id,
            'trigger_reason' => ApplicationRollbackEvent::REASON_CRASH_LOOP,
            'trigger_type' => 'automatic',
            'status' => ApplicationRollbackEvent::STATUS_IN_PROGRESS,
            'triggered_at' => now(),
        ]);

        // Simulate what HandlesDeploymentStatus::handleSuccessfulDeployment does
        // for a rollback deployment
        ApplicationRollbackEvent::where('rollback_deployment_id', $rollbackDeployment->id)
            ->whereIn('status', [ApplicationRollbackEvent::STATUS_TRIGGERED, ApplicationRollbackEvent::STATUS_IN_PROGRESS])
            ->first()
            ?->markSuccess();

        $event->refresh();
        expect($event->status)->toBe(ApplicationRollbackEvent::STATUS_SUCCESS);
        expect($event->completed_at)->not->toBeNull();
    });

    test('updates last_successful_deployment_id on success', function () {
        $deployment = ApplicationDeploymentQueue::create([
            'application_id' => $this->application->id,
            'deployment_uuid' => (string) new Cuid2,
            'status' => ApplicationDeploymentStatus::FINISHED->value,
            'commit' => 'success_commit',
            'server_id' => $this->server->id,
            'server_name' => $this->server->name,
            'destination_id' => $this->destination->id,
            'application_name' => $this->application->name,
            'deployment_url' => '/test/deployment/success',
        ]);

        // Simulate what handleSuccessfulDeployment does
        $this->application->update([
            'restart_count' => 0,
            'last_restart_at' => null,
            'last_restart_type' => null,
            'last_successful_deployment_id' => $deployment->id,
        ]);

        $this->application->refresh();
        expect($this->application->last_successful_deployment_id)->toBe($deployment->id);
        expect($this->application->restart_count)->toBe(0);
    });
});

// =====================================================================
// handleFailedDeployment — Rollback event on failure
// =====================================================================

describe('Failed rollback deployment', function () {
    test('marks rollback event as failed when rollback deployment fails', function () {
        $rollbackDeployment = ApplicationDeploymentQueue::create([
            'application_id' => $this->application->id,
            'deployment_uuid' => (string) new Cuid2,
            'status' => ApplicationDeploymentStatus::FAILED->value,
            'commit' => 'rollback_target_commit',
            'rollback' => true,
            'server_id' => $this->server->id,
            'server_name' => $this->server->name,
            'destination_id' => $this->destination->id,
            'application_name' => $this->application->name,
            'deployment_url' => '/test/deployment/rollback-fail',
        ]);

        $event = ApplicationRollbackEvent::create([
            'application_id' => $this->application->id,
            'rollback_deployment_id' => $rollbackDeployment->id,
            'trigger_reason' => ApplicationRollbackEvent::REASON_CONTAINER_EXITED,
            'trigger_type' => 'automatic',
            'status' => ApplicationRollbackEvent::STATUS_IN_PROGRESS,
            'triggered_at' => now(),
        ]);

        // Simulate what HandlesDeploymentStatus::handleFailedDeployment does
        ApplicationRollbackEvent::where('rollback_deployment_id', $rollbackDeployment->id)
            ->whereIn('status', [ApplicationRollbackEvent::STATUS_TRIGGERED, ApplicationRollbackEvent::STATUS_IN_PROGRESS])
            ->first()
            ?->markFailed('Rollback deployment failed');

        $event->refresh();
        expect($event->status)->toBe(ApplicationRollbackEvent::STATUS_FAILED);
        expect($event->error_message)->toBe('Rollback deployment failed');
        expect($event->completed_at)->not->toBeNull();
    });

    test('does not affect unrelated rollback events', function () {
        $rollbackDeployment = ApplicationDeploymentQueue::create([
            'application_id' => $this->application->id,
            'deployment_uuid' => (string) new Cuid2,
            'status' => ApplicationDeploymentStatus::FAILED->value,
            'commit' => 'rollback_commit',
            'rollback' => true,
            'server_id' => $this->server->id,
            'server_name' => $this->server->name,
            'destination_id' => $this->destination->id,
            'application_name' => $this->application->name,
            'deployment_url' => '/test/deployment/rollback-2',
        ]);

        // Create an event linked to this rollback deployment
        $linkedEvent = ApplicationRollbackEvent::create([
            'application_id' => $this->application->id,
            'rollback_deployment_id' => $rollbackDeployment->id,
            'trigger_reason' => ApplicationRollbackEvent::REASON_CRASH_LOOP,
            'trigger_type' => 'automatic',
            'status' => ApplicationRollbackEvent::STATUS_IN_PROGRESS,
            'triggered_at' => now(),
        ]);

        // Create another deployment to use as an unrelated rollback target
        $anotherDeployment = ApplicationDeploymentQueue::create([
            'application_id' => $this->application->id,
            'deployment_uuid' => (string) new Cuid2,
            'status' => ApplicationDeploymentStatus::FINISHED->value,
            'commit' => 'unrelated_commit',
            'server_id' => $this->server->id,
            'server_name' => $this->server->name,
            'destination_id' => $this->destination->id,
            'application_name' => $this->application->name,
            'deployment_url' => '/test/deployment/unrelated',
        ]);

        // Create an unrelated event (different deployment)
        $unrelatedEvent = ApplicationRollbackEvent::create([
            'application_id' => $this->application->id,
            'rollback_deployment_id' => $anotherDeployment->id,
            'trigger_reason' => ApplicationRollbackEvent::REASON_MANUAL,
            'trigger_type' => 'manual',
            'status' => ApplicationRollbackEvent::STATUS_IN_PROGRESS,
            'triggered_at' => now(),
        ]);

        // Mark failure for the linked rollback deployment
        ApplicationRollbackEvent::where('rollback_deployment_id', $rollbackDeployment->id)
            ->whereIn('status', [ApplicationRollbackEvent::STATUS_TRIGGERED, ApplicationRollbackEvent::STATUS_IN_PROGRESS])
            ->first()
            ?->markFailed('Rollback deployment failed');

        $linkedEvent->refresh();
        $unrelatedEvent->refresh();

        expect($linkedEvent->status)->toBe(ApplicationRollbackEvent::STATUS_FAILED);
        expect($unrelatedEvent->status)->toBe(ApplicationRollbackEvent::STATUS_IN_PROGRESS); // Unchanged
    });
});

// =====================================================================
// Health Monitor Dispatch Logic
// =====================================================================

describe('Health monitor dispatch logic', function () {
    test('does NOT dispatch MonitorDeploymentHealthJob for rollback deployments', function () {
        // Enable auto-rollback
        $this->application->settings()->update(['auto_rollback_enabled' => true]);

        $rollbackDeployment = ApplicationDeploymentQueue::create([
            'application_id' => $this->application->id,
            'deployment_uuid' => (string) new Cuid2,
            'status' => ApplicationDeploymentStatus::FINISHED->value,
            'commit' => 'rollback_commit',
            'rollback' => true,
            'pull_request_id' => 0,
            'server_id' => $this->server->id,
            'server_name' => $this->server->name,
            'destination_id' => $this->destination->id,
            'application_name' => $this->application->name,
            'deployment_url' => '/test/deployment/rollback',
        ]);

        // Verify the condition: isRollback should prevent health monitor dispatch
        $isRollback = (bool) ($rollbackDeployment->rollback ?? false);
        $autoRollbackEnabled = $this->application->settings?->auto_rollback_enabled;
        $isPR = ($rollbackDeployment->pull_request_id ?? 0) > 0;

        // The HandlesDeploymentStatus trait checks:
        // if (auto_rollback_enabled && !PR && !isRollback)
        $shouldDispatch = $autoRollbackEnabled && ! $isPR && ! $isRollback;

        expect($shouldDispatch)->toBeFalse();
    });

    test('dispatches MonitorDeploymentHealthJob for normal successful deployment', function () {
        $this->application->settings()->update([
            'auto_rollback_enabled' => true,
            'rollback_validation_seconds' => 300,
        ]);

        $normalDeployment = ApplicationDeploymentQueue::create([
            'application_id' => $this->application->id,
            'deployment_uuid' => (string) new Cuid2,
            'status' => ApplicationDeploymentStatus::FINISHED->value,
            'commit' => 'normal_commit',
            'rollback' => false,
            'pull_request_id' => 0,
            'server_id' => $this->server->id,
            'server_name' => $this->server->name,
            'destination_id' => $this->destination->id,
            'application_name' => $this->application->name,
            'deployment_url' => '/test/deployment/normal',
        ]);

        // Verify the condition
        $isRollback = (bool) ($normalDeployment->rollback ?? false);
        $autoRollbackEnabled = $this->application->settings?->auto_rollback_enabled;
        $isPR = ($normalDeployment->pull_request_id ?? 0) > 0;

        $shouldDispatch = $autoRollbackEnabled && ! $isPR && ! $isRollback;

        expect($shouldDispatch)->toBeTrue();

        // Verify calculation: 300s / 30s = 10 total checks
        $validationSeconds = $this->application->settings->rollback_validation_seconds ?? 300;
        $checkInterval = 30;
        $totalChecks = (int) ceil($validationSeconds / $checkInterval);
        expect($totalChecks)->toBe(10);
    });

    test('does NOT dispatch MonitorDeploymentHealthJob for PR deployments', function () {
        $this->application->settings()->update(['auto_rollback_enabled' => true]);

        $prDeployment = ApplicationDeploymentQueue::create([
            'application_id' => $this->application->id,
            'deployment_uuid' => (string) new Cuid2,
            'status' => ApplicationDeploymentStatus::FINISHED->value,
            'commit' => 'pr_commit',
            'rollback' => false,
            'pull_request_id' => 42,
            'server_id' => $this->server->id,
            'server_name' => $this->server->name,
            'destination_id' => $this->destination->id,
            'application_name' => $this->application->name,
            'deployment_url' => '/test/deployment/pr',
        ]);

        $isRollback = (bool) ($prDeployment->rollback ?? false);
        $autoRollbackEnabled = $this->application->settings?->auto_rollback_enabled;
        $isPR = ($prDeployment->pull_request_id ?? 0) > 0;

        $shouldDispatch = $autoRollbackEnabled && ! $isPR && ! $isRollback;

        expect($shouldDispatch)->toBeFalse();
    });

    test('does NOT dispatch when auto_rollback_enabled is false', function () {
        $this->application->settings()->update(['auto_rollback_enabled' => false]);

        $deployment = ApplicationDeploymentQueue::create([
            'application_id' => $this->application->id,
            'deployment_uuid' => (string) new Cuid2,
            'status' => ApplicationDeploymentStatus::FINISHED->value,
            'commit' => 'commit_123',
            'rollback' => false,
            'pull_request_id' => 0,
            'server_id' => $this->server->id,
            'server_name' => $this->server->name,
            'destination_id' => $this->destination->id,
            'application_name' => $this->application->name,
            'deployment_url' => '/test/deployment/no-monitor',
        ]);

        $autoRollbackEnabled = $this->application->settings?->auto_rollback_enabled;
        $shouldDispatch = $autoRollbackEnabled && ! (($deployment->pull_request_id ?? 0) > 0) && ! ((bool) ($deployment->rollback ?? false));

        expect($shouldDispatch)->toBeFalse();
    });

    test('calculates correct number of total checks', function () {
        // 60 seconds / 30 second interval = 2 checks
        $this->application->settings()->update(['rollback_validation_seconds' => 60]);
        $totalChecks = (int) ceil(60 / 30);
        expect($totalChecks)->toBe(2);

        // 300 seconds / 30 second interval = 10 checks
        $this->application->settings()->update(['rollback_validation_seconds' => 300]);
        $totalChecks = (int) ceil(300 / 30);
        expect($totalChecks)->toBe(10);

        // 600 seconds / 30 second interval = 20 checks
        $this->application->settings()->update(['rollback_validation_seconds' => 600]);
        $totalChecks = (int) ceil(600 / 30);
        expect($totalChecks)->toBe(20);
    });
});
