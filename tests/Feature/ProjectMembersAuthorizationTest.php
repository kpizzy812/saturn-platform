<?php

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->team = Team::factory()->create();
    $this->teamOwner = User::factory()->create();
    $this->teamAdmin = User::factory()->create();
    $this->teamMember = User::factory()->create();
    $this->targetUser = User::factory()->create();

    // Set up team memberships
    $this->team->members()->attach($this->teamOwner->id, ['role' => 'owner']);
    $this->team->members()->attach($this->teamAdmin->id, ['role' => 'admin']);
    $this->team->members()->attach($this->teamMember->id, ['role' => 'member']);
    $this->team->members()->attach($this->targetUser->id, ['role' => 'member']);

    // Create a project without explicit project members
    $this->project = Project::factory()->create([
        'team_id' => $this->team->id,
    ]);
});

describe('POST /projects/{uuid}/members authorization', function () {
    test('team owner can add project members even without explicit project membership', function () {
        $this->actingAs($this->teamOwner);
        session(['currentTeam' => $this->team]);

        $response = $this->postJson("/projects/{$this->project->uuid}/members", [
            'user_id' => $this->targetUser->id,
            'role' => 'developer',
        ]);

        $response->assertSuccessful();
        $response->assertJsonStructure(['id', 'name', 'email', 'role']);
        expect($response->json('role'))->toBe('developer');
    });

    test('team admin can add project members even without explicit project membership', function () {
        $this->actingAs($this->teamAdmin);
        session(['currentTeam' => $this->team]);

        $response = $this->postJson("/projects/{$this->project->uuid}/members", [
            'user_id' => $this->targetUser->id,
            'role' => 'developer',
        ]);

        $response->assertSuccessful();
    });

    test('team member cannot add project members', function () {
        $this->actingAs($this->teamMember);
        session(['currentTeam' => $this->team]);

        $response = $this->postJson("/projects/{$this->project->uuid}/members", [
            'user_id' => $this->targetUser->id,
            'role' => 'developer',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['message' => 'You do not have permission to manage members']);
    });

    test('only owner can assign owner role', function () {
        $this->actingAs($this->teamAdmin);
        session(['currentTeam' => $this->team]);

        $response = $this->postJson("/projects/{$this->project->uuid}/members", [
            'user_id' => $this->targetUser->id,
            'role' => 'owner',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Only project owners can assign the owner role']);
    });

    test('team owner can assign owner role', function () {
        $this->actingAs($this->teamOwner);
        session(['currentTeam' => $this->team]);

        $response = $this->postJson("/projects/{$this->project->uuid}/members", [
            'user_id' => $this->targetUser->id,
            'role' => 'owner',
        ]);

        $response->assertSuccessful();
        expect($response->json('role'))->toBe('owner');
    });
});

describe('PATCH /projects/{uuid}/members/{memberId} authorization', function () {
    beforeEach(function () {
        // Add target user as a project member first
        $this->project->addMember($this->targetUser, 'developer');
    });

    test('team owner can update member roles even without explicit project membership', function () {
        $this->actingAs($this->teamOwner);
        session(['currentTeam' => $this->team]);

        $response = $this->patchJson("/projects/{$this->project->uuid}/members/{$this->targetUser->id}", [
            'role' => 'admin',
        ]);

        $response->assertSuccessful();
        $response->assertJson(['message' => 'Role updated successfully']);
    });

    test('team admin can update member roles even without explicit project membership', function () {
        $this->actingAs($this->teamAdmin);
        session(['currentTeam' => $this->team]);

        $response = $this->patchJson("/projects/{$this->project->uuid}/members/{$this->targetUser->id}", [
            'role' => 'admin',
        ]);

        $response->assertSuccessful();
    });

    test('team member cannot update member roles', function () {
        $this->actingAs($this->teamMember);
        session(['currentTeam' => $this->team]);

        $response = $this->patchJson("/projects/{$this->project->uuid}/members/{$this->targetUser->id}", [
            'role' => 'admin',
        ]);

        $response->assertStatus(403);
    });
});

describe('DELETE /projects/{uuid}/members/{memberId} authorization', function () {
    beforeEach(function () {
        // Add target user as a project member first
        $this->project->addMember($this->targetUser, 'developer');
    });

    test('team owner can remove project members even without explicit project membership', function () {
        $this->actingAs($this->teamOwner);
        session(['currentTeam' => $this->team]);

        $response = $this->deleteJson("/projects/{$this->project->uuid}/members/{$this->targetUser->id}");

        $response->assertSuccessful();
        $response->assertJson(['message' => 'Member removed successfully']);
    });

    test('team admin can remove project members even without explicit project membership', function () {
        $this->actingAs($this->teamAdmin);
        session(['currentTeam' => $this->team]);

        $response = $this->deleteJson("/projects/{$this->project->uuid}/members/{$this->targetUser->id}");

        $response->assertSuccessful();
    });

    test('team member cannot remove project members', function () {
        $this->actingAs($this->teamMember);
        session(['currentTeam' => $this->team]);

        $response = $this->deleteJson("/projects/{$this->project->uuid}/members/{$this->targetUser->id}");

        $response->assertStatus(403);
    });
});
