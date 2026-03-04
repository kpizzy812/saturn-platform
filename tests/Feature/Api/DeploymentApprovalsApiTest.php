<?php

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
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Visus\Cuid2\Cuid2;

uses(DatabaseTransactions::class);

beforeEach(function () {
    Queue::fake();
    Notification::fake();

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
        'requires_approval' => true,
        'approval_status' => 'pending',
        'status' => 'waiting',
    ]);
});

describe('Authentication', function () {
    test('rejects request without token', function () {
        $response = $this->getJson('/api/v1/deployment-approvals');
        $response->assertStatus(401);
    });
});

describe('GET /api/v1/deployment-approvals', function () {
    test('returns list of pending approvals for team', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/deployment-approvals');

        $response->assertStatus(200);

        $data = $response->json();
        // Response is paginated
        expect($data)->toHaveKey('data');
    });

    test('returns only pending approvals', function () {
        // Create an approved deployment - should not appear
        ApplicationDeploymentQueue::factory()->create([
            'application_id' => $this->application->id,
            'application_name' => $this->application->name,
            'requires_approval' => true,
            'approval_status' => 'approved',
            'status' => 'finished',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/deployment-approvals');

        $response->assertStatus(200);

        $data = $response->json('data');
        foreach ($data as $item) {
            expect($item['approval_status'])->toBe('pending');
        }
    });

    test('does not return approvals from other teams', function () {
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnvironment = Environment::factory()->create(['project_id' => $otherProject->id]);

        $otherApp = Application::factory()->create([
            'environment_id' => $otherEnvironment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
        ]);

        ApplicationDeploymentQueue::factory()->create([
            'application_id' => $otherApp->id,
            'application_name' => $otherApp->name,
            'requires_approval' => true,
            'approval_status' => 'pending',
            'status' => 'waiting',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/deployment-approvals');

        $response->assertStatus(200);

        // Should only have our team's deployment
        $data = $response->json('data');
        foreach ($data as $item) {
            expect($item['application_id'])->toBe($this->application->id);
        }
    });
});

describe('POST /api/v1/deployment-approvals/{uuid}/approve', function () {
    test('approves a pending deployment', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson('/api/v1/deployment-approvals/'.$this->deployment->deployment_uuid.'/approve');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Deployment approved successfully and queued for processing.');

        $this->assertDatabaseHas('application_deployment_queue', [
            'id' => $this->deployment->id,
            'approval_status' => 'approved',
        ]);
    });

    test('returns 404 for non-existent deployment', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson('/api/v1/deployment-approvals/non-existent-uuid/approve');

        $response->assertStatus(404);
    });

    test('returns 400 when deployment does not require approval', function () {
        $deployment = ApplicationDeploymentQueue::factory()->create([
            'application_id' => $this->application->id,
            'application_name' => $this->application->name,
            'requires_approval' => false,
            'approval_status' => 'not_required',
            'status' => 'finished',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson('/api/v1/deployment-approvals/'.$deployment->deployment_uuid.'/approve');

        $response->assertStatus(400);
    });

    test('returns 400 when deployment already approved', function () {
        $this->deployment->update(['approval_status' => 'approved', 'status' => 'finished']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson('/api/v1/deployment-approvals/'.$this->deployment->deployment_uuid.'/approve');

        $response->assertStatus(400);
    });

    test('rejects member without admin role', function () {
        $member = User::factory()->create();
        $this->team->members()->attach($member->id, ['role' => 'member']);
        $memberToken = $member->createToken('member-token', ['*'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$memberToken,
        ])->postJson('/api/v1/deployment-approvals/'.$this->deployment->deployment_uuid.'/approve');

        $response->assertStatus(403);
    });
});

describe('POST /api/v1/deployment-approvals/{uuid}/reject', function () {
    test('rejects a pending deployment', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson('/api/v1/deployment-approvals/'.$this->deployment->deployment_uuid.'/reject', [
            'note' => 'Not ready for production',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Deployment rejected successfully.');

        $this->assertDatabaseHas('application_deployment_queue', [
            'id' => $this->deployment->id,
            'approval_status' => 'rejected',
            'status' => 'cancelled',
        ]);
    });

    test('returns 404 for non-existent deployment', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson('/api/v1/deployment-approvals/non-existent-uuid/reject');

        $response->assertStatus(404);
    });

    test('rejects member without admin role', function () {
        $member = User::factory()->create();
        $this->team->members()->attach($member->id, ['role' => 'member']);
        $memberToken = $member->createToken('member-token', ['*'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$memberToken,
        ])->postJson('/api/v1/deployment-approvals/'.$this->deployment->deployment_uuid.'/reject');

        $response->assertStatus(403);
    });
});
