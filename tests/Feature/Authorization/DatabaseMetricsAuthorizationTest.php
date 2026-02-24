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

describe('destructive manage operations', function () {
    test('viewer cannot execute query', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson("/_internal/databases/{$this->database->uuid}/query", [
            'query' => 'SELECT 1',
        ])->assertStatus(403);
    });

    test('viewer cannot flush redis', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson("/_internal/databases/{$this->database->uuid}/redis/flush")
            ->assertStatus(403);
    });

    test('viewer cannot run postgres maintenance', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson("/_internal/databases/{$this->database->uuid}/postgres/maintenance", [
            'operation' => 'vacuum',
        ])->assertStatus(403);
    });

    test('viewer cannot kill database connection', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson("/_internal/databases/{$this->database->uuid}/connections/kill", [
            'pid' => 123,
        ])->assertStatus(403);
    });

    test('viewer cannot create database user', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson("/_internal/databases/{$this->database->uuid}/users/create", [
            'username' => 'testuser',
            'password' => 'testpass',
        ])->assertStatus(403);
    });

    test('viewer cannot delete database user', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson("/_internal/databases/{$this->database->uuid}/users/delete", [
            'username' => 'testuser',
        ])->assertStatus(403);
    });

    test('viewer cannot delete redis key', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson("/_internal/databases/{$this->database->uuid}/redis/keys/delete", [
            'key' => 'test:key',
        ])->assertStatus(403);
    });

    test('viewer cannot set redis key value', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson("/_internal/databases/{$this->database->uuid}/redis/keys/value", [
            'key' => 'test:key',
            'value' => 'test-value',
        ])->assertStatus(403);
    });

    test('viewer cannot update table row', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->patchJson("/_internal/databases/{$this->database->uuid}/tables/users/rows", [
            'data' => [],
        ])->assertStatus(403);
    });

    test('viewer cannot delete table row', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->deleteJson("/_internal/databases/{$this->database->uuid}/tables/users/rows")
            ->assertStatus(403);
    });

    test('viewer cannot create table row', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson("/_internal/databases/{$this->database->uuid}/tables/users/rows", [
            'data' => [],
        ])->assertStatus(403);
    });

    test('viewer cannot regenerate password', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson("/_internal/databases/{$this->database->uuid}/regenerate-password")
            ->assertStatus(403);
    });
});

describe('update operations', function () {
    test('viewer cannot toggle extension', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson("/_internal/databases/{$this->database->uuid}/extensions/toggle", [
            'extension' => 'pg_trgm',
            'enabled' => true,
        ])->assertStatus(403);
    });

    test('viewer cannot create mongo index', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson("/_internal/databases/{$this->database->uuid}/mongodb/indexes/create", [
            'collection' => 'users',
            'keys' => [],
        ])->assertStatus(403);
    });
});

describe('owner access', function () {
    test('owner can access destructive operations (not 403)', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        $uuid = $this->database->uuid;

        expect($this->postJson("/_internal/databases/{$uuid}/query", [
            'query' => 'SELECT 1',
        ])->status())->not->toBe(403);
    });
});
