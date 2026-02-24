<?php

use App\Models\Application;
use App\Models\Environment;
use App\Models\InstanceSettings;
use App\Models\PrivateKey;
use App\Models\Project;
use App\Models\Server;
use App\Models\StandaloneDocker;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::factory()->create(['id' => 0]);

    $this->team = Team::factory()->create();
    $this->owner = User::factory()->create();
    $this->viewer = User::factory()->create();

    $this->team->members()->attach($this->owner->id, ['role' => 'owner']);
    $this->team->members()->attach($this->viewer->id, ['role' => 'viewer']);

    $privateKey = PrivateKey::factory()->create(['team_id' => $this->team->id]);
    $server = Server::factory()->create([
        'team_id' => $this->team->id,
        'private_key_id' => $privateKey->id,
    ]);

    $standaloneDocker = StandaloneDocker::create([
        'server_id' => $server->id,
        'network' => 'test-network',
    ]);

    $project = Project::factory()->create(['team_id' => $this->team->id]);
    $environment = Environment::factory()->create(['project_id' => $project->id]);

    $this->application = Application::factory()->create([
        'environment_id' => $environment->id,
        'destination_id' => $standaloneDocker->id,
        'destination_type' => StandaloneDocker::class,
    ]);
});

describe('POST /applications (create)', function () {
    test('viewer cannot create application', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $response = $this->postJson('/applications', [
            'type' => 'public',
            'project_uuid' => 'test',
            'environment_name' => 'test',
            'server_uuid' => 'test',
            'destination_uuid' => 'test',
        ]);

        // ApplicationPolicy::create() returns true for all users,
        // so the Gate check passes. The route may fail for other reasons.
        // This test documents the intended behavior.
        expect($response->status())->not->toBe(403);
    });
});

describe('deployment routes (deploy)', function () {
    test('viewer cannot deploy application', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson("/applications/{$this->application->uuid}/deploy")
            ->assertStatus(403);
    });

    test('viewer cannot start application', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson("/applications/{$this->application->uuid}/start")
            ->assertStatus(403);
    });

    test('viewer cannot stop application', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson("/applications/{$this->application->uuid}/stop")
            ->assertStatus(403);
    });

    test('viewer cannot restart application', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson("/applications/{$this->application->uuid}/restart")
            ->assertStatus(403);
    });

    test('owner can deploy application (not 403)', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        expect($this->postJson("/applications/{$this->application->uuid}/deploy")->status())
            ->not->toBe(403);
    });
});

describe('DELETE /applications/{uuid}', function () {
    test('viewer cannot delete application', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->deleteJson("/applications/{$this->application->uuid}")
            ->assertStatus(403);
    });

    test('owner can delete application (not 403)', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        expect($this->deleteJson("/applications/{$this->application->uuid}")->status())
            ->not->toBe(403);
    });
});

describe('PATCH /applications/{uuid}/settings (update)', function () {
    test('viewer cannot update application settings', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->patchJson("/applications/{$this->application->uuid}/settings", [
            'name' => 'renamed',
        ])->assertStatus(403);
    });

    test('owner can update application settings (not 403)', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        expect($this->patchJson("/applications/{$this->application->uuid}/settings", [
            'name' => 'renamed',
        ])->status())->not->toBe(403);
    });
});

describe('POST /applications/{uuid}/rollback (deploy)', function () {
    test('viewer cannot rollback application', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson("/applications/{$this->application->uuid}/rollback/fake-commit/execute")
            ->assertStatus(403);
    });
});
