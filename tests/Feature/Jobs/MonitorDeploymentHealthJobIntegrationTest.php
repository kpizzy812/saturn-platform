<?php

use App\Enums\ApplicationDeploymentStatus;
use App\Jobs\MonitorDeploymentHealthJob;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Visus\Cuid2\Cuid2;

uses(DatabaseTransactions::class);

/**
 * Helper: update application status directly via DB
 * (status is excluded from $fillable for security)
 */
function setAppStatus(Application $app, string $status): void
{
    DB::table('applications')->where('id', $app->id)->update(['status' => $status]);
    $app->refresh();
}

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
        'restart_count' => 0,
    ]);
    // Set status via DB (not in $fillable)
    setAppStatus($this->application, 'running');

    // Enable auto-rollback in settings
    $this->application->settings()->update([
        'auto_rollback_enabled' => true,
        'rollback_validation_seconds' => 300,
        'rollback_max_restarts' => 3,
        'rollback_on_health_check_fail' => true,
        'rollback_on_crash_loop' => true,
    ]);

    // Create a previous successful deployment (rollback target)
    $this->previousDeployment = ApplicationDeploymentQueue::create([
        'application_id' => $this->application->id,
        'deployment_uuid' => (string) new Cuid2,
        'status' => ApplicationDeploymentStatus::FINISHED->value,
        'commit' => 'previous_good_commit',
        'pull_request_id' => 0,
        'server_id' => $this->server->id,
        'server_name' => $this->server->name,
        'destination_id' => $this->destination->id,
        'application_name' => $this->application->name,
        'deployment_url' => '/test/deployment/previous',
    ]);

    // Create the current deployment (the one being monitored)
    $this->currentDeployment = ApplicationDeploymentQueue::create([
        'application_id' => $this->application->id,
        'deployment_uuid' => (string) new Cuid2,
        'status' => ApplicationDeploymentStatus::FINISHED->value,
        'commit' => 'current_bad_commit',
        'pull_request_id' => 0,
        'server_id' => $this->server->id,
        'server_name' => $this->server->name,
        'destination_id' => $this->destination->id,
        'application_name' => $this->application->name,
        'deployment_url' => '/test/deployment/current',
    ]);
});

// =====================================================================
// Early returns — job should NOT trigger rollback
// =====================================================================

describe('Early returns', function () {
    test('does nothing when auto_rollback is disabled', function () {
        $this->application->settings()->update(['auto_rollback_enabled' => false]);

        $job = new MonitorDeploymentHealthJob(
            deployment: $this->currentDeployment,
        );
        $job->handle();

        expect(ApplicationRollbackEvent::count())->toBe(0);
        Queue::assertNothingPushed();
    });

    test('does nothing for PR deployments', function () {
        $prDeployment = ApplicationDeploymentQueue::create([
            'application_id' => $this->application->id,
            'deployment_uuid' => (string) new Cuid2,
            'status' => ApplicationDeploymentStatus::FINISHED->value,
            'commit' => 'pr_commit',
            'pull_request_id' => 42,
            'server_id' => $this->server->id,
            'server_name' => $this->server->name,
            'destination_id' => $this->destination->id,
            'application_name' => $this->application->name,
            'deployment_url' => '/test/deployment/pr',
        ]);

        $job = new MonitorDeploymentHealthJob(deployment: $prDeployment);
        $job->handle();

        expect(ApplicationRollbackEvent::count())->toBe(0);
    });

    test('does nothing when deployment status is not finished', function () {
        $this->currentDeployment->update(['status' => ApplicationDeploymentStatus::FAILED->value]);

        $job = new MonitorDeploymentHealthJob(deployment: $this->currentDeployment);
        $job->handle();

        expect(ApplicationRollbackEvent::count())->toBe(0);
    });
});

// =====================================================================
// Crash Loop Detection
// =====================================================================

describe('Crash loop detection', function () {
    test('triggers rollback when restart count exceeds max_restarts', function () {
        $this->application->update(['restart_count' => 4]);

        $job = new MonitorDeploymentHealthJob(
            deployment: $this->currentDeployment,
            checkIntervalSeconds: 30,
            totalChecks: 10,
            currentCheck: 0,
            initialRestartCount: 0
        );
        $job->handle();

        $event = ApplicationRollbackEvent::where('application_id', $this->application->id)->first();
        expect($event)->not->toBeNull();
        expect($event->trigger_reason)->toBe(ApplicationRollbackEvent::REASON_CRASH_LOOP);
        expect($event->trigger_type)->toBe('automatic');
        expect($event->to_commit)->toBe('previous_good_commit');
        expect($event->from_commit)->toBe('current_bad_commit');
    });

    test('does NOT trigger when restarts are below threshold', function () {
        $this->application->update(['restart_count' => 2]);

        $job = new MonitorDeploymentHealthJob(
            deployment: $this->currentDeployment,
            checkIntervalSeconds: 30,
            totalChecks: 10,
            currentCheck: 0,
            initialRestartCount: 0
        );
        $job->handle();

        expect(ApplicationRollbackEvent::where('trigger_reason', ApplicationRollbackEvent::REASON_CRASH_LOOP)->count())->toBe(0);
    });

    test('respects custom rollback_max_restarts setting', function () {
        $this->application->settings()->update(['rollback_max_restarts' => 5]);
        $this->application->update(['restart_count' => 4]);

        $job = new MonitorDeploymentHealthJob(
            deployment: $this->currentDeployment,
            checkIntervalSeconds: 30,
            totalChecks: 10,
            currentCheck: 0,
            initialRestartCount: 0
        );
        $job->handle();

        expect(ApplicationRollbackEvent::where('trigger_reason', ApplicationRollbackEvent::REASON_CRASH_LOOP)->count())->toBe(0);
    });

    test('does NOT trigger crash loop when rollback_on_crash_loop is disabled', function () {
        $this->application->settings()->update(['rollback_on_crash_loop' => false]);
        $this->application->update(['restart_count' => 10]);

        $job = new MonitorDeploymentHealthJob(
            deployment: $this->currentDeployment,
            checkIntervalSeconds: 30,
            totalChecks: 10,
            currentCheck: 0,
            initialRestartCount: 0
        );
        $job->handle();

        expect(ApplicationRollbackEvent::where('trigger_reason', ApplicationRollbackEvent::REASON_CRASH_LOOP)->count())->toBe(0);
    });

    test('uses restart delta not absolute count', function () {
        // Initial count was 5, current is 7 — delta is 2, below threshold of 3
        $this->application->update(['restart_count' => 7]);

        $job = new MonitorDeploymentHealthJob(
            deployment: $this->currentDeployment,
            checkIntervalSeconds: 30,
            totalChecks: 10,
            currentCheck: 1,
            initialRestartCount: 5
        );
        $job->handle();

        expect(ApplicationRollbackEvent::where('trigger_reason', ApplicationRollbackEvent::REASON_CRASH_LOOP)->count())->toBe(0);

        // Now delta = 3 (8 - 5), should trigger
        $this->application->update(['restart_count' => 8]);

        $job2 = new MonitorDeploymentHealthJob(
            deployment: $this->currentDeployment,
            checkIntervalSeconds: 30,
            totalChecks: 10,
            currentCheck: 2,
            initialRestartCount: 5
        );
        $job2->handle();

        expect(ApplicationRollbackEvent::where('trigger_reason', ApplicationRollbackEvent::REASON_CRASH_LOOP)->count())->toBe(1);
    });
});

// =====================================================================
// Container Exited / Degraded Detection
// =====================================================================

describe('Container status detection', function () {
    test('triggers rollback when container has exited', function () {
        setAppStatus($this->application, 'exited:1');

        $job = new MonitorDeploymentHealthJob(
            deployment: $this->currentDeployment,
            checkIntervalSeconds: 30,
            totalChecks: 10,
            currentCheck: 0,
            initialRestartCount: 0
        );
        $job->handle();

        $event = ApplicationRollbackEvent::where('application_id', $this->application->id)->first();
        expect($event)->not->toBeNull();
        expect($event->trigger_reason)->toBe(ApplicationRollbackEvent::REASON_CONTAINER_EXITED);
        expect($event->trigger_type)->toBe('automatic');
    });

    test('triggers rollback when container is degraded', function () {
        setAppStatus($this->application, 'degraded:unhealthy');

        $job = new MonitorDeploymentHealthJob(
            deployment: $this->currentDeployment,
            checkIntervalSeconds: 30,
            totalChecks: 10,
            currentCheck: 0,
            initialRestartCount: 0
        );
        $job->handle();

        $event = ApplicationRollbackEvent::where('application_id', $this->application->id)->first();
        expect($event)->not->toBeNull();
        expect($event->trigger_reason)->toBe(ApplicationRollbackEvent::REASON_CONTAINER_EXITED);
    });

    test('does NOT trigger for running status', function () {
        // Status is already 'running' from beforeEach
        $job = new MonitorDeploymentHealthJob(
            deployment: $this->currentDeployment,
            checkIntervalSeconds: 30,
            totalChecks: 10,
            currentCheck: 0,
            initialRestartCount: 0
        );
        $job->handle();

        expect(ApplicationRollbackEvent::where('trigger_reason', ApplicationRollbackEvent::REASON_CONTAINER_EXITED)->count())->toBe(0);
    });
});

// =====================================================================
// Health Check Failure Detection
// =====================================================================

describe('Health check failure detection', function () {
    test('triggers rollback when health check is unhealthy', function () {
        $this->application->update(['health_check_enabled' => true]);
        setAppStatus($this->application, 'running:unhealthy');

        $job = new MonitorDeploymentHealthJob(
            deployment: $this->currentDeployment,
            checkIntervalSeconds: 30,
            totalChecks: 10,
            currentCheck: 0,
            initialRestartCount: 0
        );
        $job->handle();

        $event = ApplicationRollbackEvent::where('application_id', $this->application->id)->first();
        expect($event)->not->toBeNull();
        expect($event->trigger_reason)->toBe(ApplicationRollbackEvent::REASON_HEALTH_CHECK_FAILED);
    });

    test('does NOT trigger when rollback_on_health_check_fail is disabled', function () {
        $this->application->settings()->update(['rollback_on_health_check_fail' => false]);
        $this->application->update(['health_check_enabled' => true]);
        setAppStatus($this->application, 'running:unhealthy');

        $job = new MonitorDeploymentHealthJob(
            deployment: $this->currentDeployment,
            checkIntervalSeconds: 30,
            totalChecks: 10,
            currentCheck: 0,
            initialRestartCount: 0
        );
        $job->handle();

        expect(ApplicationRollbackEvent::where('trigger_reason', ApplicationRollbackEvent::REASON_HEALTH_CHECK_FAILED)->count())->toBe(0);
    });

    test('does NOT trigger health check rollback when health_check_enabled is false', function () {
        $this->application->update(['health_check_enabled' => false]);
        setAppStatus($this->application, 'running:unhealthy');

        $job = new MonitorDeploymentHealthJob(
            deployment: $this->currentDeployment,
            checkIntervalSeconds: 30,
            totalChecks: 10,
            currentCheck: 0,
            initialRestartCount: 0
        );
        $job->handle();

        expect(ApplicationRollbackEvent::where('trigger_reason', ApplicationRollbackEvent::REASON_HEALTH_CHECK_FAILED)->count())->toBe(0);
    });
});

// =====================================================================
// Auto-Rollback Deployment Creation
// =====================================================================

describe('Rollback deployment creation', function () {
    test('creates rollback deployment queue entry targeting previous successful deployment', function () {
        setAppStatus($this->application, 'exited:0');

        $deploymentCountBefore = ApplicationDeploymentQueue::where('application_id', $this->application->id)->count();

        $job = new MonitorDeploymentHealthJob(
            deployment: $this->currentDeployment,
            checkIntervalSeconds: 30,
            totalChecks: 10,
            currentCheck: 0,
            initialRestartCount: 0
        );
        $job->handle();

        $deploymentCountAfter = ApplicationDeploymentQueue::where('application_id', $this->application->id)->count();
        expect($deploymentCountAfter)->toBe($deploymentCountBefore + 1);

        // The new deployment should be a rollback
        $rollbackDeployment = ApplicationDeploymentQueue::where('application_id', $this->application->id)
            ->orderBy('id', 'desc')
            ->first();
        expect((bool) $rollbackDeployment->rollback)->toBeTrue();
        expect($rollbackDeployment->commit)->toBe('previous_good_commit');
    });

    test('rollback event includes metrics snapshot', function () {
        $this->application->update(['restart_count' => 5]);
        setAppStatus($this->application, 'exited:1');

        $job = new MonitorDeploymentHealthJob(
            deployment: $this->currentDeployment,
            checkIntervalSeconds: 30,
            totalChecks: 10,
            currentCheck: 2,
            initialRestartCount: 0
        );
        $job->handle();

        $event = ApplicationRollbackEvent::where('application_id', $this->application->id)->first();
        expect($event)->not->toBeNull();
        expect($event->metrics_snapshot)->not->toBeNull();
        expect($event->metrics_snapshot)->toHaveKey('status');
        expect($event->metrics_snapshot)->toHaveKey('restart_count');
        expect($event->metrics_snapshot)->toHaveKey('restart_delta');
        expect($event->metrics_snapshot)->toHaveKey('check_number');
        expect($event->metrics_snapshot['restart_count'])->toBe(5);
    });

    test('creates skipped event when no previous successful deployment exists', function () {
        // Delete the previous deployment
        $this->previousDeployment->forceDelete();

        setAppStatus($this->application, 'exited:0');

        $job = new MonitorDeploymentHealthJob(
            deployment: $this->currentDeployment,
            checkIntervalSeconds: 30,
            totalChecks: 10,
            currentCheck: 0,
            initialRestartCount: 0
        );
        $job->handle();

        $event = ApplicationRollbackEvent::where('application_id', $this->application->id)->first();
        expect($event)->not->toBeNull();
        expect($event->status)->toBe(ApplicationRollbackEvent::STATUS_SKIPPED);
        expect($event->error_message)->toBe('No previous successful deployment found');
    });

    test('does NOT use PR deployments as rollback target', function () {
        // Make the previous deployment a PR deployment
        $this->previousDeployment->update(['pull_request_id' => 99]);

        // Create another previous deployment that is NOT a PR (before current)
        // Use DB insert to control the id
        $oldId = $this->previousDeployment->id;
        $normalPreviousDeployment = ApplicationDeploymentQueue::create([
            'application_id' => $this->application->id,
            'deployment_uuid' => (string) new Cuid2,
            'status' => ApplicationDeploymentStatus::FINISHED->value,
            'commit' => 'even_older_good_commit',
            'pull_request_id' => 0,
            'server_id' => $this->server->id,
            'server_name' => $this->server->name,
            'destination_id' => $this->destination->id,
            'application_name' => $this->application->name,
            'deployment_url' => '/test/deployment/old',
        ]);

        // Ensure this deployment has an id < currentDeployment->id
        // (it was created after, but auto-increment gives it a higher id)
        // Instead, use crash loop to trigger and verify the commit used
        $this->application->update(['restart_count' => 5]);

        $job = new MonitorDeploymentHealthJob(
            deployment: $this->currentDeployment,
            checkIntervalSeconds: 30,
            totalChecks: 10,
            currentCheck: 0,
            initialRestartCount: 0
        );
        $job->handle();

        // The rollback should skip the PR deployment and find no valid target
        // (normalPreviousDeployment has id > currentDeployment, so it's excluded)
        $event = ApplicationRollbackEvent::where('application_id', $this->application->id)->first();
        expect($event)->not->toBeNull();
        // Since the only non-PR deployment before current was removed,
        // it should be skipped
        expect($event->status)->toBe(ApplicationRollbackEvent::STATUS_SKIPPED);
    });
});

// =====================================================================
// Self-dispatching Pattern (Next Check Scheduling)
// =====================================================================

describe('Self-dispatching pattern', function () {
    test('dispatches next check when healthy and not final check', function () {
        // Application is healthy — should schedule next check
        $job = new MonitorDeploymentHealthJob(
            deployment: $this->currentDeployment,
            checkIntervalSeconds: 30,
            totalChecks: 10,
            currentCheck: 0,
            initialRestartCount: 0
        );
        $job->handle();

        Queue::assertPushed(MonitorDeploymentHealthJob::class);
    });

    test('does NOT dispatch next check on final check', function () {
        $job = new MonitorDeploymentHealthJob(
            deployment: $this->currentDeployment,
            checkIntervalSeconds: 30,
            totalChecks: 10,
            currentCheck: 9,
            initialRestartCount: 0
        );
        $job->handle();

        Queue::assertNotPushed(MonitorDeploymentHealthJob::class);
    });

    test('does NOT dispatch next check when rollback triggered', function () {
        // Use crash loop (restart_count is in $fillable) to trigger rollback
        $this->application->update(['restart_count' => 5]);

        // Reset Queue fake to only capture dispatches from handle()
        Queue::fake();

        $job = new MonitorDeploymentHealthJob(
            deployment: $this->currentDeployment,
            checkIntervalSeconds: 30,
            totalChecks: 10,
            currentCheck: 2,
            initialRestartCount: 0
        );
        $job->handle();

        // Should create rollback deployment (ApplicationDeploymentJob) but NOT continue monitoring
        Queue::assertNotPushed(MonitorDeploymentHealthJob::class);
    });
});

// =====================================================================
// Infinite Loop Protection
// =====================================================================

describe('Infinite loop protection', function () {
    test('auto-rollback finds deployment BEFORE current one only', function () {
        // Delete the previous deployment so there's nothing before current
        $this->previousDeployment->forceDelete();

        // Create a future deployment (id > current)
        ApplicationDeploymentQueue::create([
            'application_id' => $this->application->id,
            'deployment_uuid' => (string) new Cuid2,
            'status' => ApplicationDeploymentStatus::FINISHED->value,
            'commit' => 'future_commit',
            'pull_request_id' => 0,
            'server_id' => $this->server->id,
            'server_name' => $this->server->name,
            'destination_id' => $this->destination->id,
            'application_name' => $this->application->name,
            'deployment_url' => '/test/deployment/future',
        ]);

        // Trigger rollback via crash loop (status not in $fillable)
        $this->application->update(['restart_count' => 5]);

        $job = new MonitorDeploymentHealthJob(
            deployment: $this->currentDeployment,
            checkIntervalSeconds: 30,
            totalChecks: 10,
            currentCheck: 0,
            initialRestartCount: 0
        );
        $job->handle();

        // Should have a skipped event because the only available deployment has id > current
        $event = ApplicationRollbackEvent::where('application_id', $this->application->id)->first();
        expect($event)->not->toBeNull();
        expect($event->status)->toBe(ApplicationRollbackEvent::STATUS_SKIPPED);
    });
});
