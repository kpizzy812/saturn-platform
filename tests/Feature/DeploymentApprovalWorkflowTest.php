<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\DeploymentApproval;
use App\Models\Environment;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test deployment approval workflow
 *
 * Tests the complete approval workflow for deployments to protected environments:
 * 1. Developer requests deployment to production with requires_approval enabled
 * 2. Deployment is created with pending_approval status
 * 3. Admin/Owner can approve or reject
 * 4. On approval, deployment proceeds
 * 5. On rejection, deployment is cancelled
 */
class DeploymentApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private Project $project;

    private Environment $environment;

    private Application $application;

    private User $owner;

    private User $developer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create team
        $this->team = Team::factory()->create();

        // Create owner user
        $this->owner = User::factory()->create();
        $this->owner->teams()->attach($this->team->id, ['role' => 'owner']);

        // Create developer user
        $this->developer = User::factory()->create();
        $this->developer->teams()->attach($this->team->id, ['role' => 'developer']);

        // Create project
        $this->project = Project::factory()->create([
            'team_id' => $this->team->id,
        ]);

        // Create production environment with approval required
        $this->environment = Environment::factory()->create([
            'project_id' => $this->project->id,
            'name' => 'production',
            'type' => 'production',
            'requires_approval' => true,
        ]);

        // Create application
        $this->application = Application::factory()->create([
            'environment_id' => $this->environment->id,
        ]);
    }

    /** @test */
    public function developer_can_deploy_to_production_environment()
    {
        // Developer should be able to deploy (authorization passes)
        $this->assertTrue(
            $this->developer->canDeployToEnvironment($this->environment)
        );
    }

    /** @test */
    public function developer_requires_approval_for_production_with_approval_enabled()
    {
        // Developer should require approval for production environment
        $this->assertTrue(
            $this->developer->requiresApprovalForEnvironment($this->environment)
        );
    }

    /** @test */
    public function owner_does_not_require_approval_for_production()
    {
        // Owner should NOT require approval
        $this->assertFalse(
            $this->owner->requiresApprovalForEnvironment($this->environment)
        );
    }

    /** @test */
    public function deployment_by_developer_creates_approval_request()
    {
        $this->actingAs($this->developer);

        $deployment_uuid = new \Visus\Cuid2\Cuid2;

        $result = queue_application_deployment(
            application: $this->application,
            deployment_uuid: $deployment_uuid,
            user_id: $this->developer->id,
        );

        // Should return approval_required status
        $this->assertEquals('approval_required', $result['status']);
        $this->assertArrayHasKey('approval_uuid', $result);

        // Deployment should exist with pending_approval status
        $deployment = ApplicationDeploymentQueue::where('deployment_uuid', $deployment_uuid)->first();
        $this->assertNotNull($deployment);
        $this->assertEquals('pending_approval', $deployment->status);

        // Approval request should exist
        $approval = DeploymentApproval::where('application_deployment_queue_id', $deployment->id)->first();
        $this->assertNotNull($approval);
        $this->assertEquals('pending', $approval->status);
        $this->assertEquals($this->developer->id, $approval->requested_by);
    }

    /** @test */
    public function deployment_by_owner_does_not_require_approval()
    {
        $this->actingAs($this->owner);

        $deployment_uuid = new \Visus\Cuid2\Cuid2;

        $result = queue_application_deployment(
            application: $this->application,
            deployment_uuid: $deployment_uuid,
            user_id: $this->owner->id,
        );

        // Should return queued status (no approval needed)
        $this->assertEquals('queued', $result['status']);

        // Deployment should exist with queued status (not pending_approval)
        $deployment = ApplicationDeploymentQueue::where('deployment_uuid', $deployment_uuid)->first();
        $this->assertNotNull($deployment);
        $this->assertNotEquals('pending_approval', $deployment->status);
    }

    /** @test */
    public function owner_can_approve_pending_deployment()
    {
        $this->actingAs($this->developer);

        // Developer creates deployment
        $deployment_uuid = new \Visus\Cuid2\Cuid2;
        queue_application_deployment(
            application: $this->application,
            deployment_uuid: $deployment_uuid,
            user_id: $this->developer->id,
        );

        $deployment = ApplicationDeploymentQueue::where('deployment_uuid', $deployment_uuid)->first();
        $approval = DeploymentApproval::where('application_deployment_queue_id', $deployment->id)->first();

        // Owner approves
        $this->actingAs($this->owner);
        $approveAction = new \App\Actions\Deployment\ApproveDeploymentAction;
        $approveAction->approve($approval, $this->owner, 'Looks good!');

        // Approval should be approved
        $approval->refresh();
        $this->assertEquals('approved', $approval->status);
        $this->assertEquals($this->owner->id, $approval->approved_by);

        // Deployment should be queued
        $deployment->refresh();
        $this->assertEquals('queued', $deployment->status);
    }

    /** @test */
    public function owner_can_reject_pending_deployment()
    {
        $this->actingAs($this->developer);

        // Developer creates deployment
        $deployment_uuid = new \Visus\Cuid2\Cuid2;
        queue_application_deployment(
            application: $this->application,
            deployment_uuid: $deployment_uuid,
            user_id: $this->developer->id,
        );

        $deployment = ApplicationDeploymentQueue::where('deployment_uuid', $deployment_uuid)->first();
        $approval = DeploymentApproval::where('application_deployment_queue_id', $deployment->id)->first();

        // Owner rejects
        $this->actingAs($this->owner);
        $approveAction = new \App\Actions\Deployment\ApproveDeploymentAction;
        $approveAction->reject($approval, $this->owner, 'Needs more testing');

        // Approval should be rejected
        $approval->refresh();
        $this->assertEquals('rejected', $approval->status);
        $this->assertEquals($this->owner->id, $approval->approved_by);

        // Deployment should be cancelled
        $deployment->refresh();
        $this->assertEquals('cancelled', $deployment->status);
    }

    /** @test */
    public function deployment_without_requires_approval_proceeds_immediately()
    {
        // Disable requires_approval
        $this->environment->update(['requires_approval' => false]);

        $this->actingAs($this->developer);

        $deployment_uuid = new \Visus\Cuid2\Cuid2;

        $result = queue_application_deployment(
            application: $this->application,
            deployment_uuid: $deployment_uuid,
            user_id: $this->developer->id,
        );

        // Should return queued status (no approval)
        $this->assertEquals('queued', $result['status']);

        // No approval request should be created
        $deployment = ApplicationDeploymentQueue::where('deployment_uuid', $deployment_uuid)->first();
        $approval = DeploymentApproval::where('application_deployment_queue_id', $deployment->id)->first();
        $this->assertNull($approval);
    }
}
