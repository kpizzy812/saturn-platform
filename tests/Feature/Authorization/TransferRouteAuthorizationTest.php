<?php

use App\Models\Application;
use App\Models\Environment;
use App\Models\InstanceSettings;
use App\Models\PrivateKey;
use App\Models\Project;
use App\Models\Server;
use App\Models\Service;
use App\Models\StandaloneDocker;
use App\Models\StandalonePostgresql;
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
    $this->server = Server::factory()->create([
        'team_id' => $this->team->id,
        'private_key_id' => $privateKey->id,
    ]);

    $standaloneDocker = StandaloneDocker::create([
        'server_id' => $this->server->id,
        'network' => 'test-network',
    ]);

    $project = Project::factory()->create(['team_id' => $this->team->id]);
    $this->environment = Environment::factory()->create(['project_id' => $project->id]);

    $this->application = Application::factory()->create([
        'environment_id' => $this->environment->id,
        'destination_id' => $standaloneDocker->id,
        'destination_type' => StandaloneDocker::class,
    ]);

    $this->service = Service::factory()->create([
        'environment_id' => $this->environment->id,
        'server_id' => $this->server->id,
        'destination_id' => $standaloneDocker->id,
        'destination_type' => StandaloneDocker::class,
    ]);

    $this->database = StandalonePostgresql::factory()->create([
        'environment_id' => $this->environment->id,
        'destination_id' => $standaloneDocker->id,
        'destination_type' => StandaloneDocker::class,
    ]);
});

describe('POST /transfers (store) — application clone', function () {
    test('viewer cannot transfer/clone application', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson('/transfers', [
            'source_type' => 'application',
            'source_uuid' => $this->application->uuid,
            'target_environment_id' => $this->environment->id,
            'target_server_id' => $this->server->id,
            'transfer_mode' => 'clone',
        ])->assertStatus(403);
    });

    test('owner can transfer/clone application (not 403)', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        expect($this->postJson('/transfers', [
            'source_type' => 'application',
            'source_uuid' => $this->application->uuid,
            'target_environment_id' => $this->environment->id,
            'target_server_id' => $this->server->id,
            'transfer_mode' => 'clone',
        ])->status())->not->toBe(403);
    });
});

describe('POST /transfers (store) — service clone', function () {
    test('viewer cannot transfer/clone service', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson('/transfers', [
            'source_type' => 'service',
            'source_uuid' => $this->service->uuid,
            'target_environment_id' => $this->environment->id,
            'target_server_id' => $this->server->id,
            'transfer_mode' => 'clone',
        ])->assertStatus(403);
    });
});

describe('POST /transfers (store) — database transfer', function () {
    test('viewer cannot transfer database', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson('/transfers', [
            'source_type' => 'database',
            'source_uuid' => $this->database->uuid,
            'target_environment_id' => $this->environment->id,
            'target_server_id' => $this->server->id,
            'transfer_mode' => 'clone',
        ])->assertStatus(403);
    });
});

describe('GET /_internal/databases/{uuid}/structure (view)', function () {
    test('viewer cannot view database structure for transfer', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->getJson("/_internal/databases/{$this->database->uuid}/structure")
            ->assertStatus(403);
    });

    test('owner can view database structure (not 403)', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        expect($this->getJson("/_internal/databases/{$this->database->uuid}/structure")->status())
            ->not->toBe(403);
    });
});
