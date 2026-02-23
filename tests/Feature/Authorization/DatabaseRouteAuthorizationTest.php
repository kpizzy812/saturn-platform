<?php

use App\Models\Environment;
use App\Models\InstanceSettings;
use App\Models\PrivateKey;
use App\Models\Project;
use App\Models\Server;
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

    $this->database = StandalonePostgresql::factory()->create([
        'environment_id' => $environment->id,
        'destination_id' => $standaloneDocker->id,
        'destination_type' => StandaloneDocker::class,
    ]);
});

describe('POST /databases (create)', function () {
    test('viewer cannot create database', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson('/databases', [
            'type' => 'postgresql',
            'server_uuid' => 'test',
            'project_uuid' => 'test',
            'environment_name' => 'test',
            'destination_uuid' => 'test',
        ])->assertStatus(403);
    });

    test('owner can create database (not 403)', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        expect($this->postJson('/databases', [
            'type' => 'postgresql',
        ])->status())->not->toBe(403);
    });
});

describe('PATCH /databases/{uuid} (update)', function () {
    test('viewer cannot update database', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->patchJson("/databases/{$this->database->uuid}", [
            'name' => 'renamed',
        ])->assertStatus(403);
    });

    test('owner can update database (not 403)', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        expect($this->patchJson("/databases/{$this->database->uuid}", [
            'name' => 'renamed',
        ])->status())->not->toBe(403);
    });
});

describe('DELETE /databases/{uuid}', function () {
    test('viewer cannot delete database', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->deleteJson("/databases/{$this->database->uuid}")
            ->assertStatus(403);
    });

    test('owner can delete database (not 403)', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        expect($this->deleteJson("/databases/{$this->database->uuid}")->status())
            ->not->toBe(403);
    });
});

describe('backup management routes (manageBackups)', function () {
    test('viewer cannot create backup configuration', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson("/databases/{$this->database->uuid}/backups", [
            'frequency' => '0 * * * *',
        ])->assertStatus(403);
    });

    test('viewer cannot update backup schedule', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->patchJson("/databases/{$this->database->uuid}/backups/schedule", [
            'frequency' => '0 * * * *',
        ])->assertStatus(403);
    });

    test('viewer cannot update backup settings', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->patchJson("/databases/{$this->database->uuid}/settings/backups", [
            'backup_enabled' => true,
        ])->assertStatus(403);
    });
});

describe('database management routes (manage)', function () {
    test('viewer cannot export database', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson("/databases/{$this->database->uuid}/export")
            ->assertStatus(403);
    });

    test('viewer cannot import to database via remote', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson("/databases/{$this->database->uuid}/import/remote")
            ->assertStatus(403);
    });

    test('viewer cannot restart database', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson("/databases/{$this->database->uuid}/restart")
            ->assertStatus(403);
    });

    test('owner can export database (not 403)', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        expect($this->postJson("/databases/{$this->database->uuid}/export")->status())
            ->not->toBe(403);
    });
});
