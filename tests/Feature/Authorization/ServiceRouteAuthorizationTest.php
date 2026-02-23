<?php

use App\Models\Environment;
use App\Models\InstanceSettings;
use App\Models\PrivateKey;
use App\Models\Project;
use App\Models\Server;
use App\Models\Service;
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

    $this->service = Service::factory()->create([
        'environment_id' => $environment->id,
        'server_id' => $server->id,
        'destination_id' => $standaloneDocker->id,
        'destination_type' => StandaloneDocker::class,
    ]);
});

describe('POST /services (create)', function () {
    test('viewer cannot create service', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson('/services', [
            'type' => 'plausible',
            'server_uuid' => 'test',
            'project_uuid' => 'test',
            'environment_name' => 'test',
            'destination_uuid' => 'test',
        ])->assertStatus(403);
    });

    test('owner can create service (not 403)', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        expect($this->postJson('/services', [
            'type' => 'plausible',
        ])->status())->not->toBe(403);
    });
});

describe('deployment routes (deploy)', function () {
    test('viewer cannot redeploy service', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson("/services/{$this->service->uuid}/redeploy")
            ->assertStatus(403);
    });

    test('viewer cannot restart service', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson("/services/{$this->service->uuid}/restart")
            ->assertStatus(403);
    });

    test('owner can redeploy service (not 403)', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        expect($this->postJson("/services/{$this->service->uuid}/redeploy")->status())
            ->not->toBe(403);
    });
});

describe('POST /services/{uuid}/stop (stop)', function () {
    test('viewer cannot stop service', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson("/services/{$this->service->uuid}/stop")
            ->assertStatus(403);
    });

    test('owner can stop service (not 403)', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        expect($this->postJson("/services/{$this->service->uuid}/stop")->status())
            ->not->toBe(403);
    });
});

describe('DELETE /services/{uuid}', function () {
    test('viewer cannot delete service', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->deleteJson("/services/{$this->service->uuid}")
            ->assertStatus(403);
    });

    test('owner can delete service (not 403)', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        expect($this->deleteJson("/services/{$this->service->uuid}")->status())
            ->not->toBe(403);
    });
});
