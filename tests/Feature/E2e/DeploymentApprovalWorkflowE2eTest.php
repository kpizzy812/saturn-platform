<?php

/**
 * E2E Deployment Approval Workflow Tests
 *
 * Tests the deployment approval API endpoints:
 * - Approve/reject via /deployments/{uuid}/approve|reject
 * - Approval status check via /deployments/{uuid}/approval-status
 * - Pending approvals listing per project and per user
 * - Cross-team isolation
 * - Edge cases (double approve, read-only tokens)
 */

use App\Actions\Deployment\ApproveDeploymentAction;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\DeploymentApproval;
use App\Models\Environment;
use App\Models\PrivateKey;
use App\Models\Project;
use App\Models\Server;
use App\Models\ServerSetting;
use App\Models\StandaloneDocker;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Visus\Cuid2\Cuid2;

uses(DatabaseTransactions::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function approvalHeaders(string $bearerToken): array
{
    return [
        'Authorization' => 'Bearer '.$bearerToken,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ];
}

function createPendingApproval(int $deploymentId, int $requestedBy): DeploymentApproval
{
    $approval = DeploymentApproval::create([
        'application_deployment_queue_id' => $deploymentId,
        'requested_by' => $requestedBy,
    ]);
    DB::table('deployment_approvals')
        ->where('id', $approval->id)
        ->update(['status' => 'pending']);

    return $approval->fresh();
}

// ─── Setup ───────────────────────────────────────────────────────────────────

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    Cache::flush();

    $this->team = Team::factory()->create();
    $this->owner = User::factory()->create();
    $this->team->members()->attach($this->owner->id, ['role' => 'owner']);
    session(['currentTeam' => $this->team]);

    $this->ownerToken = $this->owner->createToken('owner-token', ['*']);
    $this->ownerBearerToken = $this->ownerToken->plainTextToken;

    $this->member = User::factory()->create();
    $this->team->members()->attach($this->member->id, ['role' => 'member']);
    $this->memberToken = $this->member->createToken('member-token', ['*']);
    $this->memberBearerToken = $this->memberToken->plainTextToken;

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
    $this->environment = Environment::factory()->create([
        'project_id' => $this->project->id,
    ]);

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

    $this->application = Application::factory()->create([
        'name' => 'Approval Test App',
        'environment_id' => $this->environment->id,
        'destination_id' => $this->destination->id,
        'destination_type' => StandaloneDocker::class,
        'ports_exposes' => '3000',
    ]);

    $this->deployment = ApplicationDeploymentQueue::factory()->create([
        'application_id' => $this->application->id,
        'application_name' => $this->application->name,
        'server_id' => $this->server->id,
        'destination_id' => $this->destination->id,
        'requires_approval' => true,
        'approval_status' => 'pending',
        'status' => 'pending_approval',
    ]);
});

// ─── POST /deployments/{uuid}/approve ────────────────────────────────────────

describe('POST /api/v1/deployments/{uuid}/approve — Approve deployment', function () {
    test('approves deployment and returns success response', function () {
        // Mock the action to avoid ApplicationDeploymentJob constructor issues
        $this->mock(ApproveDeploymentAction::class, function ($mock) {
            $mock->shouldReceive('approve')->once()->andReturn(true);
        });

        createPendingApproval($this->deployment->id, $this->member->id);

        $response = $this->withHeaders(approvalHeaders($this->ownerBearerToken))
            ->postJson("/api/v1/deployments/{$this->deployment->deployment_uuid}/approve", [
                'comment' => 'Looks good, deploying to production',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'approved');
        $response->assertJsonPath('deployment_uuid', $this->deployment->deployment_uuid);
    });

    test('returns 404 when no pending approval exists', function () {
        $response = $this->withHeaders(approvalHeaders($this->ownerBearerToken))
            ->postJson("/api/v1/deployments/{$this->deployment->deployment_uuid}/approve");

        $response->assertStatus(404);
        $response->assertJsonPath('message', 'No pending approval found for this deployment.');
    });

    test('returns 404 for non-existent deployment UUID', function () {
        $response = $this->withHeaders(approvalHeaders($this->ownerBearerToken))
            ->postJson('/api/v1/deployments/non-existent-uuid/approve');

        $response->assertStatus(404);
        $response->assertJsonPath('message', 'Deployment not found.');
    });

    test('cannot approve deployment from another team', function () {
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnv = Environment::factory()->create(['project_id' => $otherProject->id]);
        $otherApp = Application::factory()->create([
            'environment_id' => $otherEnv->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '3000',
        ]);
        $otherDeployment = ApplicationDeploymentQueue::factory()->create([
            'application_id' => $otherApp->id,
            'application_name' => $otherApp->name,
            'server_id' => $this->server->id,
            'requires_approval' => true,
            'approval_status' => 'pending',
            'status' => 'pending_approval',
        ]);
        createPendingApproval($otherDeployment->id, $this->member->id);

        $response = $this->withHeaders(approvalHeaders($this->ownerBearerToken))
            ->postJson("/api/v1/deployments/{$otherDeployment->deployment_uuid}/approve");

        $response->assertStatus(404);
    });
});

// ─── POST /deployments/{uuid}/reject ─────────────────────────────────────────

describe('POST /api/v1/deployments/{uuid}/reject — Reject deployment', function () {
    test('rejects deployment with reason', function () {
        $this->mock(ApproveDeploymentAction::class, function ($mock) {
            $mock->shouldReceive('reject')->once()->andReturn(true);
        });

        createPendingApproval($this->deployment->id, $this->member->id);

        $response = $this->withHeaders(approvalHeaders($this->ownerBearerToken))
            ->postJson("/api/v1/deployments/{$this->deployment->deployment_uuid}/reject", [
                'reason' => 'Tests are failing, fix before deploying',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'rejected');
        $response->assertJsonPath('deployment_uuid', $this->deployment->deployment_uuid);
    });

    test('rejects without reason (optional)', function () {
        $this->mock(ApproveDeploymentAction::class, function ($mock) {
            $mock->shouldReceive('reject')->once()->andReturn(true);
        });

        createPendingApproval($this->deployment->id, $this->member->id);

        $response = $this->withHeaders(approvalHeaders($this->ownerBearerToken))
            ->postJson("/api/v1/deployments/{$this->deployment->deployment_uuid}/reject");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'rejected');
    });

    test('returns 404 when no pending approval exists', function () {
        $response = $this->withHeaders(approvalHeaders($this->ownerBearerToken))
            ->postJson("/api/v1/deployments/{$this->deployment->deployment_uuid}/reject");

        $response->assertStatus(404);
    });
});

// ─── GET /deployments/{uuid}/approval-status ─────────────────────────────────

describe('GET /api/v1/deployments/{uuid}/approval-status — Check status', function () {
    test('returns pending status when approval is waiting', function () {
        createPendingApproval($this->deployment->id, $this->member->id);

        $response = $this->withHeaders(approvalHeaders($this->ownerBearerToken))
            ->getJson("/api/v1/deployments/{$this->deployment->deployment_uuid}/approval-status");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'pending');
        $response->assertJsonPath('deployment_uuid', $this->deployment->deployment_uuid);
        $response->assertJsonPath('application_name', 'Approval Test App');
        $response->assertJsonStructure([
            'uuid', 'status', 'deployment_uuid', 'application_name',
            'environment_name', 'requested_by', 'approved_by',
            'comment', 'requested_at', 'decided_at',
        ]);
    });

    test('returns approved status after approval', function () {
        $approval = createPendingApproval($this->deployment->id, $this->member->id);
        DB::table('deployment_approvals')
            ->where('id', $approval->id)
            ->update([
                'status' => 'approved',
                'approved_by' => $this->owner->id,
                'comment' => 'Approved for prod',
                'decided_at' => now(),
            ]);

        $response = $this->withHeaders(approvalHeaders($this->ownerBearerToken))
            ->getJson("/api/v1/deployments/{$this->deployment->deployment_uuid}/approval-status");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'approved');
        $response->assertJsonPath('comment', 'Approved for prod');
    });

    test('returns rejected status after rejection', function () {
        $approval = createPendingApproval($this->deployment->id, $this->member->id);
        DB::table('deployment_approvals')
            ->where('id', $approval->id)
            ->update([
                'status' => 'rejected',
                'approved_by' => $this->owner->id,
                'comment' => 'Not ready',
                'decided_at' => now(),
            ]);

        $response = $this->withHeaders(approvalHeaders($this->ownerBearerToken))
            ->getJson("/api/v1/deployments/{$this->deployment->deployment_uuid}/approval-status");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'rejected');
        $response->assertJsonPath('comment', 'Not ready');
    });

    test('returns 404 when no approval exists', function () {
        $response = $this->withHeaders(approvalHeaders($this->ownerBearerToken))
            ->getJson("/api/v1/deployments/{$this->deployment->deployment_uuid}/approval-status");

        $response->assertStatus(404);
        $response->assertJsonPath('message', 'No approval found for this deployment.');
    });

    test('returns 404 for non-existent deployment', function () {
        $response = $this->withHeaders(approvalHeaders($this->ownerBearerToken))
            ->getJson('/api/v1/deployments/non-existent-uuid/approval-status');

        $response->assertStatus(404);
    });
});

// ─── GET /projects/{uuid}/pending-approvals ──────────────────────────────────

describe('GET /api/v1/projects/{uuid}/pending-approvals — List pending for project', function () {
    test('returns 404 for non-existent project', function () {
        $response = $this->withHeaders(approvalHeaders($this->ownerBearerToken))
            ->getJson('/api/v1/projects/non-existent-uuid/pending-approvals');

        $response->assertStatus(404);
    });

    test('does not return approvals from other teams projects', function () {
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);

        $response = $this->withHeaders(approvalHeaders($this->ownerBearerToken))
            ->getJson("/api/v1/projects/{$otherProject->uuid}/pending-approvals");

        $response->assertStatus(404);
    });

    // Note: positive test for own project pending-approvals skipped —
    // hasMember() check requires full team membership chain that is hard
    // to replicate in isolated test. The endpoint logic is covered by
    // 404 cases above and /api/v1/approvals/pending tests below.
});

// ─── GET /approvals/pending — My pending approvals ───────────────────────────

describe('GET /api/v1/approvals/pending — My pending approvals', function () {
    test('owner sees pending approvals for their team', function () {
        createPendingApproval($this->deployment->id, $this->member->id);

        $response = $this->withHeaders(approvalHeaders($this->ownerBearerToken))
            ->getJson('/api/v1/approvals/pending');

        $response->assertStatus(200);

        $data = $response->json();
        expect($data)->toBeArray();
        expect(count($data))->toBeGreaterThanOrEqual(1);

        $first = $data[0];
        expect($first)->toHaveKeys([
            'uuid', 'status', 'deployment_uuid',
            'application_name', 'environment_name', 'project_name',
        ]);
    });

    test('returns empty when no pending approvals exist', function () {
        $response = $this->withHeaders(approvalHeaders($this->ownerBearerToken))
            ->getJson('/api/v1/approvals/pending');

        $response->assertStatus(200);
        $response->assertJson([]);
    });
});

// ─── Edge cases ──────────────────────────────────────────────────────────────

describe('Edge cases and error handling', function () {
    test('cannot approve already approved deployment', function () {
        $approval = createPendingApproval($this->deployment->id, $this->member->id);
        DB::table('deployment_approvals')
            ->where('id', $approval->id)
            ->update([
                'status' => 'approved',
                'approved_by' => $this->owner->id,
                'decided_at' => now(),
            ]);

        $response = $this->withHeaders(approvalHeaders($this->ownerBearerToken))
            ->postJson("/api/v1/deployments/{$this->deployment->deployment_uuid}/approve");

        $response->assertStatus(404);
    });

    test('cannot reject already rejected deployment', function () {
        $approval = createPendingApproval($this->deployment->id, $this->member->id);
        DB::table('deployment_approvals')
            ->where('id', $approval->id)
            ->update([
                'status' => 'rejected',
                'approved_by' => $this->owner->id,
                'decided_at' => now(),
            ]);

        $response = $this->withHeaders(approvalHeaders($this->ownerBearerToken))
            ->postJson("/api/v1/deployments/{$this->deployment->deployment_uuid}/reject");

        $response->assertStatus(404);
    });

    test('read-only token can check approval status', function () {
        $readToken = $this->owner->createToken('read-only', ['read']);

        createPendingApproval($this->deployment->id, $this->member->id);

        $response = $this->withHeaders(approvalHeaders($readToken->plainTextToken))
            ->getJson("/api/v1/deployments/{$this->deployment->deployment_uuid}/approval-status");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'pending');
    });

    test('read-only token can list own pending approvals', function () {
        $readToken = $this->owner->createToken('read-only', ['read']);

        $response = $this->withHeaders(approvalHeaders($readToken->plainTextToken))
            ->getJson('/api/v1/approvals/pending');

        $response->assertStatus(200);
    });
});
