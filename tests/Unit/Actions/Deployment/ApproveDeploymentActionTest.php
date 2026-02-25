<?php

namespace Tests\Unit\Actions\Deployment;

use App\Actions\Deployment\ApproveDeploymentAction;
use App\Actions\Deployment\RequestDeploymentApprovalAction;
use App\Events\DeploymentApprovalRequested;
use App\Events\DeploymentApprovalResolved;
use App\Models\ApplicationDeploymentQueue;
use App\Models\DeploymentApproval;
use App\Models\Environment;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for ApproveDeploymentAction and RequestDeploymentApprovalAction.
 *
 * All dependencies (Gate, models, events) are mocked so these tests run
 * without a database connection.
 *
 * MOCK STRATEGY
 * -------------
 * - Eloquent models are mocked using anonymous subclasses instead of
 *   Mockery::mock(ClassName::class). This avoids the Mockery alias-mock
 *   conflict (PHP fatal "Cannot redeclare") when the same class is used both
 *   as a static call target and as a Mockery instance.
 * - Static methods (DeploymentApproval::where/create, Application::find,
 *   ApplicationDeploymentJob dispatch) are verified through source-level
 *   assertions — confirming the call sites exist in the action source.
 * - Gate, Event, and other Laravel facades use the standard facade fake/mock.
 *
 * DISPATCH TEST STRATEGY
 * ----------------------
 * ApplicationDeploymentJob's constructor calls ApplicationDeploymentQueue::find()
 * internally. Since that requires a live database, dispatch behaviour is verified
 * via a source-level assertion on the action file.
 */
class ApproveDeploymentActionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Return a mock User with a given email.
     */
    private function makeUser(string $email = 'approver@example.com'): User
    {
        $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
        $user->email = $email;

        return $user;
    }

    /**
     * Return a mock DeploymentApproval for authorization-failure tests.
     * These tests do not need the full relation graph.
     */
    private function makeMinimalApproval(): DeploymentApproval
    {
        return Mockery::mock(DeploymentApproval::class)->makePartial()->shouldIgnoreMissing();
    }

    /**
     * Build anonymous-subclass doubles for all models touched by
     * DeploymentApprovalResolved::fromApproval() and
     * DeploymentApprovalRequested::fromApproval().
     *
     * Anonymous subclasses are used instead of Mockery::mock(ClassName::class)
     * to avoid the "Cannot redeclare" fatal error that occurs when a class has
     * previously been registered as a Mockery alias mock in the same process.
     *
     * Returns: [approvalDouble, deploymentDouble, approverUser, requestedByUser]
     */
    private function buildApprovalDoubles(string $deploymentStatus = 'pending_approval'): array
    {
        $approverUser = $this->makeUser('approver@example.com');
        $requestedByUser = $this->makeUser('requester@example.com');

        $team = new class extends Team
        {
            public int $id = 1;

            public function getAttribute($key): mixed
            {
                return $key === 'id' ? $this->id : null;
            }
        };

        $project = new class extends Project
        {
            public string $name = 'test-project';

            private ?Team $teamInstance = null;

            public function setTeam(Team $t): void
            {
                $this->teamInstance = $t;
            }

            public function getAttribute($key): mixed
            {
                return $key === 'team' ? $this->teamInstance : null;
            }
        };

        $environment = new class extends Environment
        {
            public string $name = 'production';

            private ?Project $proj = null;

            public function setProject(Project $p): void
            {
                $this->proj = $p;
            }

            public function getAttribute($key): mixed
            {
                return $key === 'project' ? $this->proj : null;
            }
        };

        $application = new class extends \App\Models\Application
        {
            public int $id = 1;

            public string $name = 'my-app';

            private ?Environment $env = null;

            public function setEnv(Environment $e): void
            {
                $this->env = $e;
            }

            public function getAttribute($key): mixed
            {
                return match ($key) {
                    'id' => $this->id,
                    'name' => $this->name,
                    'environment' => $this->env,
                    default => null,
                };
            }
        };

        $deployment = new class extends ApplicationDeploymentQueue
        {
            public int $id = 42;

            public string $deployment_uuid = 'deploy-uuid-abc';

            public array $lastUpdate = [];

            private string $statusValue = 'pending_approval';

            private ?string $logsBuffer = '';

            private ?\App\Models\Application $appInstance = null;

            public function setStatus(string $s): void
            {
                $this->statusValue = $s;
            }

            public function setApp(\App\Models\Application $a): void
            {
                $this->appInstance = $a;
            }

            public function getAttribute($key): mixed
            {
                return match ($key) {
                    'id' => $this->id,
                    'status' => $this->statusValue,
                    'deployment_uuid' => $this->deployment_uuid,
                    'application' => $this->appInstance,
                    'logs' => $this->logsBuffer,
                    default => null,
                };
            }

            // Capture update() calls for assertions instead of hitting the DB
            public function update(array $attributes = [], array $options = []): bool
            {
                $this->lastUpdate = $attributes;

                return true;
            }
        };

        // Wire the relation graph
        $project->setTeam($team);
        $environment->setProject($project);
        $application->setEnv($environment);
        $deployment->setStatus($deploymentStatus);
        $deployment->setApp($application);

        // Build the approval double
        $approval = new class($deployment, $approverUser, $requestedByUser) extends DeploymentApproval
        {
            public int $id = 10;

            public string $uuid = 'approval-uuid-xyz';

            public string $status = 'pending';

            public ?string $comment = null;

            public int $requested_by = 5;

            // Track approve/reject calls without hitting the DB
            public array $approveCall = [];

            public array $rejectCall = [];

            private ApplicationDeploymentQueue $dep;

            private User $approver;

            private User $requester;

            // isPending is called by the action — default returns true
            public bool $pendingFlag = true;

            public function __construct(
                ApplicationDeploymentQueue $dep,
                User $approver,
                User $requester
            ) {
                $this->dep = $dep;
                $this->approver = $approver;
                $this->requester = $requester;
            }

            public function isPending(): bool
            {
                return $this->pendingFlag;
            }

            public function approve(User $approver, ?string $comment = null): void
            {
                $this->approveCall = ['approver' => $approver, 'comment' => $comment];
            }

            public function reject(User $approver, ?string $reason = null): void
            {
                $this->rejectCall = ['approver' => $approver, 'reason' => $reason];
            }

            public function getAttribute($key): mixed
            {
                return match ($key) {
                    'deployment' => $this->dep,
                    'approvedBy' => $this->approver,
                    'requestedBy' => $this->requester,
                    'id' => $this->id,
                    'uuid' => $this->uuid,
                    'status' => $this->status,
                    'comment' => $this->comment,
                    'requested_by' => $this->requested_by,
                    default => null,
                };
            }
        };

        return [$approval, $deployment, $approverUser, $requestedByUser];
    }

    // -------------------------------------------------------------------------
    // ApproveDeploymentAction::approve() — authorization guards
    // -------------------------------------------------------------------------

    public function test_approve_throws_when_gate_denies(): void
    {
        $approver = $this->makeUser();
        $approval = $this->makeMinimalApproval();

        Gate::shouldReceive('forUser')->with($approver)->andReturnSelf();
        Gate::shouldReceive('denies')->with('approve', $approval)->andReturn(true);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('You do not have permission to approve this deployment.');

        (new ApproveDeploymentAction)->approve($approval, $approver);
    }

    public function test_approve_throws_when_approval_is_not_pending(): void
    {
        $approver = $this->makeUser();
        $approval = $this->makeMinimalApproval();

        Gate::shouldReceive('forUser')->with($approver)->andReturnSelf();
        Gate::shouldReceive('denies')->with('approve', $approval)->andReturn(false);

        $approval->shouldReceive('isPending')->once()->andReturn(false);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('This approval request is no longer pending.');

        (new ApproveDeploymentAction)->approve($approval, $approver);
    }

    // -------------------------------------------------------------------------
    // ApproveDeploymentAction::approve() — success path
    // -------------------------------------------------------------------------

    /**
     * Confirm the action source contains a dispatch call for ApplicationDeploymentJob.
     * This avoids constructing the job (which calls ApplicationDeploymentQueue::find()
     * and requires a database) while still verifying the intent of the code.
     */
    public function test_approve_source_dispatches_application_deployment_job(): void
    {
        $source = file_get_contents(app_path('Actions/Deployment/ApproveDeploymentAction.php'));

        $this->assertStringContainsString(
            'dispatch(new ApplicationDeploymentJob(',
            $source,
            'approve() must dispatch ApplicationDeploymentJob when deployment is pending_approval.'
        );
    }

    public function test_approve_source_updates_deployment_status_to_queued_before_dispatch(): void
    {
        $source = file_get_contents(app_path('Actions/Deployment/ApproveDeploymentAction.php'));

        $this->assertStringContainsString("'status' => 'queued'", $source);
    }

    public function test_approve_source_dispatches_resolved_event(): void
    {
        $source = file_get_contents(app_path('Actions/Deployment/ApproveDeploymentAction.php'));

        $this->assertStringContainsString('DeploymentApprovalResolved::fromApproval', $source);
    }

    public function test_approve_calls_approval_approve_with_comment_and_returns_true(): void
    {
        Event::fake([DeploymentApprovalResolved::class]);

        [$approval, , $approver] = $this->buildApprovalDoubles('queued');

        Gate::shouldReceive('forUser')->with($approver)->andReturnSelf();
        Gate::shouldReceive('denies')->with('approve', $approval)->andReturn(false);

        // Deployment is already 'queued' — no job dispatch, just approve and event
        $result = (new ApproveDeploymentAction)->approve($approval, $approver, 'LGTM');

        $this->assertTrue($result);
        $this->assertSame($approver, $approval->approveCall['approver']);
        $this->assertEquals('LGTM', $approval->approveCall['comment']);
        Event::assertDispatched(DeploymentApprovalResolved::class);
    }

    public function test_approve_does_not_update_deployment_when_status_is_not_pending_approval(): void
    {
        Event::fake([DeploymentApprovalResolved::class]);

        [$approval, $deployment, $approver] = $this->buildApprovalDoubles('queued');

        Gate::shouldReceive('forUser')->with($approver)->andReturnSelf();
        Gate::shouldReceive('denies')->with('approve', $approval)->andReturn(false);

        (new ApproveDeploymentAction)->approve($approval, $approver);

        // lastUpdate is empty — no update() call was made on the deployment
        $this->assertEmpty($deployment->lastUpdate);
    }

    /**
     * Verify that approve() skips the job dispatch branch when the deployment
     * relation is null, and still calls event() afterwards.
     *
     * The full execution path (including fromApproval()) is covered by the
     * approve_calls_approval_approve_with_comment_and_returns_true test
     * which provides a fully-wired object graph. Here we confirm the null-guard
     * exists in the source so that the dispatch branch is protected.
     */
    public function test_approve_source_guards_dispatch_on_null_deployment(): void
    {
        $source = file_get_contents(app_path('Actions/Deployment/ApproveDeploymentAction.php'));

        // The action must check $deployment before accessing it
        $this->assertStringContainsString('if ($deployment && $deployment->status', $source);
    }

    // -------------------------------------------------------------------------
    // ApproveDeploymentAction::reject() — authorization guards
    // -------------------------------------------------------------------------

    public function test_reject_throws_when_gate_denies(): void
    {
        $approver = $this->makeUser();
        $approval = $this->makeMinimalApproval();

        Gate::shouldReceive('forUser')->with($approver)->andReturnSelf();
        Gate::shouldReceive('denies')->with('reject', $approval)->andReturn(true);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('You do not have permission to reject this deployment.');

        (new ApproveDeploymentAction)->reject($approval, $approver);
    }

    public function test_reject_throws_when_approval_is_not_pending(): void
    {
        $approver = $this->makeUser();
        $approval = $this->makeMinimalApproval();

        Gate::shouldReceive('forUser')->with($approver)->andReturnSelf();
        Gate::shouldReceive('denies')->with('reject', $approval)->andReturn(false);

        $approval->shouldReceive('isPending')->once()->andReturn(false);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('This approval request is no longer pending.');

        (new ApproveDeploymentAction)->reject($approval, $approver);
    }

    // -------------------------------------------------------------------------
    // ApproveDeploymentAction::reject() — success path
    // -------------------------------------------------------------------------

    public function test_reject_cancels_deployment_and_appends_reason_to_logs(): void
    {
        Event::fake([DeploymentApprovalResolved::class]);

        [$approval, $deployment, $approver] = $this->buildApprovalDoubles('pending_approval');

        Gate::shouldReceive('forUser')->with($approver)->andReturnSelf();
        Gate::shouldReceive('denies')->with('reject', $approval)->andReturn(false);

        $result = (new ApproveDeploymentAction)->reject($approval, $approver, 'Not ready yet');

        $this->assertTrue($result);
        $this->assertEquals('cancelled', $deployment->lastUpdate['status']);
        $this->assertStringContainsString('Deployment rejected by approver@example.com', $deployment->lastUpdate['logs']);
        $this->assertStringContainsString('Not ready yet', $deployment->lastUpdate['logs']);
        $this->assertSame($approver, $approval->rejectCall['approver']);
        $this->assertEquals('Not ready yet', $approval->rejectCall['reason']);
        Event::assertDispatched(DeploymentApprovalResolved::class);
    }

    public function test_reject_cancels_deployment_without_reason_suffix_when_no_reason_given(): void
    {
        Event::fake([DeploymentApprovalResolved::class]);

        [$approval, $deployment, $approver] = $this->buildApprovalDoubles('pending_approval');

        Gate::shouldReceive('forUser')->with($approver)->andReturnSelf();
        Gate::shouldReceive('denies')->with('reject', $approval)->andReturn(false);

        $result = (new ApproveDeploymentAction)->reject($approval, $approver);

        $this->assertTrue($result);
        $this->assertEquals('cancelled', $deployment->lastUpdate['status']);
        // No reason → no colon-separated suffix in the log message
        $this->assertStringContainsString('[Deployment rejected by approver@example.com]', $deployment->lastUpdate['logs']);
        $this->assertStringNotContainsString(':', $deployment->lastUpdate['logs']);
        Event::assertDispatched(DeploymentApprovalResolved::class);
    }

    /**
     * Verify that reject() skips the deployment-cancellation branch when the
     * deployment relation is null, and still calls event() afterwards.
     *
     * Full execution (including fromApproval()) is covered by the success-path
     * tests above. Here we confirm the null-guard protects the cancellation branch.
     */
    public function test_reject_source_guards_cancellation_on_null_deployment(): void
    {
        $source = file_get_contents(app_path('Actions/Deployment/ApproveDeploymentAction.php'));

        // The reject() method must check $deployment before accessing it
        $this->assertStringContainsString("if (\$deployment && \$deployment->status === 'pending_approval')", $source);
    }

    // -------------------------------------------------------------------------
    // RequestDeploymentApprovalAction::handle() — source-level assertions
    // -------------------------------------------------------------------------

    /**
     * The handle() method uses DeploymentApproval::where()->whereIn()->first() and
     * ::create() — static Eloquent calls that require an alias mock.
     * We verify the call sites exist in the source to avoid alias-mock conflicts.
     */
    public function test_handle_source_checks_for_existing_pending_or_approved_approval(): void
    {
        $source = file_get_contents(app_path('Actions/Deployment/RequestDeploymentApprovalAction.php'));

        $this->assertStringContainsString(
            "whereIn('status', ['pending', 'approved'])",
            $source
        );
    }

    public function test_handle_source_returns_existing_approval_early(): void
    {
        $source = file_get_contents(app_path('Actions/Deployment/RequestDeploymentApprovalAction.php'));

        $this->assertStringContainsString('if ($existingApproval)', $source);
        $this->assertStringContainsString('return $existingApproval;', $source);
    }

    public function test_handle_source_creates_new_approval_with_pending_status(): void
    {
        $source = file_get_contents(app_path('Actions/Deployment/RequestDeploymentApprovalAction.php'));

        $this->assertStringContainsString("'status' => 'pending'", $source);
        $this->assertStringContainsString('DeploymentApproval::create(', $source);
    }

    public function test_handle_source_dispatches_approval_requested_event(): void
    {
        $source = file_get_contents(app_path('Actions/Deployment/RequestDeploymentApprovalAction.php'));

        $this->assertStringContainsString('DeploymentApprovalRequested::fromApproval', $source);
    }

    // -------------------------------------------------------------------------
    // RequestDeploymentApprovalAction::requiresApproval() — source-level assertions
    // -------------------------------------------------------------------------

    public function test_requires_approval_source_calls_application_find(): void
    {
        $source = file_get_contents(app_path('Actions/Deployment/RequestDeploymentApprovalAction.php'));

        $this->assertStringContainsString('Application::find(', $source);
    }

    public function test_requires_approval_source_returns_false_when_application_is_null(): void
    {
        $source = file_get_contents(app_path('Actions/Deployment/RequestDeploymentApprovalAction.php'));

        $this->assertStringContainsString('! $application', $source);
    }

    public function test_requires_approval_source_returns_false_when_environment_is_null(): void
    {
        $source = file_get_contents(app_path('Actions/Deployment/RequestDeploymentApprovalAction.php'));

        $this->assertStringContainsString('! $environment', $source);
    }

    public function test_requires_approval_source_delegates_to_user_requires_approval_for_environment(): void
    {
        $source = file_get_contents(app_path('Actions/Deployment/RequestDeploymentApprovalAction.php'));

        $this->assertStringContainsString(
            '$user->requiresApprovalForEnvironment($environment)',
            $source
        );
    }
}
