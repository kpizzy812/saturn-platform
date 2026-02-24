<?php

use App\Models\InstanceSettings;
use App\Models\PrivateKey;
use App\Models\Server;
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
});

describe('POST /servers (create)', function () {
    test('viewer cannot create server', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $response = $this->postJson('/servers', [
            'name' => 'test-server',
            'ip' => '10.0.0.1',
            'user' => 'root',
            'private_key_id' => 1,
        ]);

        $response->assertStatus(403);
    });

    test('owner can create server (not 403)', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        $response = $this->postJson('/servers', [
            'name' => 'test-server',
            'ip' => '10.0.0.1',
            'user' => 'root',
            'private_key_id' => 1,
        ]);

        expect($response->status())->not->toBe(403);
    });
});

describe('PATCH /servers/{uuid}/settings/general (update)', function () {
    test('viewer cannot update server settings', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $response = $this->patchJson("/servers/{$this->server->uuid}/settings/general", [
            'name' => 'renamed',
        ]);

        $response->assertStatus(403);
    });

    test('owner can update server settings (not 403)', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        $response = $this->patchJson("/servers/{$this->server->uuid}/settings/general", [
            'name' => 'renamed',
        ]);

        expect($response->status())->not->toBe(403);
    });
});

describe('proxy management routes (manageProxy)', function () {
    test('viewer cannot manage proxy configuration', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $uuid = $this->server->uuid;

        $this->postJson("/servers/{$uuid}/proxy/configuration")->assertStatus(403);
        $this->postJson("/servers/{$uuid}/proxy/configuration/reset")->assertStatus(403);
        $this->postJson("/servers/{$uuid}/proxy/settings")->assertStatus(403);
    });

    test('viewer cannot start/stop/restart proxy', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $uuid = $this->server->uuid;

        $this->postJson("/servers/{$uuid}/proxy/restart")->assertStatus(403);
        $this->postJson("/servers/{$uuid}/proxy/start")->assertStatus(403);
        $this->postJson("/servers/{$uuid}/proxy/stop")->assertStatus(403);
    });

    test('viewer cannot manage proxy domains', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $uuid = $this->server->uuid;

        $this->postJson("/servers/{$uuid}/proxy/domains")->assertStatus(403);
        $this->patchJson("/servers/{$uuid}/proxy/domains/1")->assertStatus(403);
        $this->deleteJson("/servers/{$uuid}/proxy/domains/1")->assertStatus(403);
    });

    test('viewer cannot renew certificate', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson("/servers/{$this->server->uuid}/domains/1/renew-certificate")
            ->assertStatus(403);
    });

    test('owner can access proxy routes (not 403)', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        $uuid = $this->server->uuid;

        expect($this->postJson("/servers/{$uuid}/proxy/configuration")->status())->not->toBe(403);
        expect($this->postJson("/servers/{$uuid}/proxy/settings")->status())->not->toBe(403);
    });
});

describe('POST /servers/{uuid}/validate', function () {
    test('viewer cannot validate server', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson("/servers/{$this->server->uuid}/validate")
            ->assertStatus(403);
    });

    test('owner can validate server (not 403)', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        expect($this->postJson("/servers/{$this->server->uuid}/validate")->status())
            ->not->toBe(403);
    });
});
