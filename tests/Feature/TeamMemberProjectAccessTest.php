<?php

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->team = Team::factory()->create();
    $this->owner = User::factory()->create();
    $this->admin = User::factory()->create();
    $this->member = User::factory()->create();

    $this->team->members()->attach($this->owner->id, ['role' => 'owner']);
    $this->team->members()->attach($this->admin->id, ['role' => 'admin']);
    $this->team->members()->attach($this->member->id, ['role' => 'member']);

    // Create some projects
    $this->projects = Project::factory()->count(3)->create([
        'team_id' => $this->team->id,
    ]);
});

describe('POST /settings/team/members/{id}/projects validation', function () {
    test('accepts integer project IDs from JSON payload', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        $response = $this->postJson("/settings/team/members/{$this->member->id}/projects", [
            'grant_all' => false,
            'allowed_projects' => [1, 2, 3], // integers from JSON
        ]);

        $response->assertSuccessful();
    });

    test('accepts string asterisk for full access', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        $response = $this->postJson("/settings/team/members/{$this->member->id}/projects", [
            'grant_all' => true,
            'allowed_projects' => ['*'],
        ]);

        $response->assertSuccessful();
    });

    test('accepts empty array for no access', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        $response = $this->postJson("/settings/team/members/{$this->member->id}/projects", [
            'grant_all' => false,
            'allowed_projects' => [],
        ]);

        $response->assertSuccessful();
    });

    test('rejects invalid string values', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        $response = $this->postJson("/settings/team/members/{$this->member->id}/projects", [
            'grant_all' => false,
            'allowed_projects' => ['invalid', 'strings'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['allowed_projects.0', 'allowed_projects.1']);
    });

    test('rejects mixed valid and invalid values', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        $response = $this->postJson("/settings/team/members/{$this->member->id}/projects", [
            'grant_all' => false,
            'allowed_projects' => [1, 'invalid', 3],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['allowed_projects.1']);
    });

    test('accepts numeric string project IDs', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        $response = $this->postJson("/settings/team/members/{$this->member->id}/projects", [
            'grant_all' => false,
            'allowed_projects' => ['1', '2', '3'], // numeric strings
        ]);

        $response->assertSuccessful();
    });
});

describe('POST /settings/team/members/{id}/projects authorization', function () {
    test('owner can update member project access', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        $response = $this->postJson("/settings/team/members/{$this->member->id}/projects", [
            'grant_all' => false,
            'allowed_projects' => [$this->projects[0]->id],
        ]);

        $response->assertSuccessful();
    });

    test('admin can update member project access', function () {
        $this->actingAs($this->admin);
        session(['currentTeam' => $this->team]);

        $response = $this->postJson("/settings/team/members/{$this->member->id}/projects", [
            'grant_all' => false,
            'allowed_projects' => [$this->projects[0]->id],
        ]);

        $response->assertSuccessful();
    });

    test('member cannot update project access', function () {
        $this->actingAs($this->member);
        session(['currentTeam' => $this->team]);

        $anotherMember = User::factory()->create();
        $this->team->members()->attach($anotherMember->id, ['role' => 'member']);

        $response = $this->postJson("/settings/team/members/{$anotherMember->id}/projects", [
            'grant_all' => false,
            'allowed_projects' => [1],
        ]);

        $response->assertStatus(403);
    });

    test('cannot restrict owner project access', function () {
        $this->actingAs($this->admin);
        session(['currentTeam' => $this->team]);

        $response = $this->postJson("/settings/team/members/{$this->owner->id}/projects", [
            'grant_all' => false,
            'allowed_projects' => [1],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Owner always has full access and cannot be restricted']);
    });

    test('admin cannot manage other admin project access', function () {
        $anotherAdmin = User::factory()->create();
        $this->team->members()->attach($anotherAdmin->id, ['role' => 'admin']);

        $this->actingAs($this->admin);
        session(['currentTeam' => $this->team]);

        $response = $this->postJson("/settings/team/members/{$anotherAdmin->id}/projects", [
            'grant_all' => false,
            'allowed_projects' => [1],
        ]);

        $response->assertStatus(403);
    });
});
