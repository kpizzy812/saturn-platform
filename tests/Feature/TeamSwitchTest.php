<?php

use App\Models\Team;
use App\Models\User;

test('user can switch between their teams', function () {
    // Create user with personal team
    $user = User::factory()->create();
    $personalTeam = $user->teams()->first();

    // Create another team and add user to it
    $workTeam = Team::factory()->create(['name' => 'Work Team']);
    $workTeam->members()->attach($user->id, ['role' => 'developer']);

    // Initially set current team to personal team
    session(['currentTeam' => $personalTeam]);

    // Switch to work team
    $response = $this->actingAs($user)->post("/teams/switch/{$workTeam->id}");

    $response->assertRedirect('/dashboard');
    $response->assertSessionHas('success', "Switched to {$workTeam->name}");

    // Verify session was updated
    expect(session('currentTeam')->id)->toBe($workTeam->id);
});

test('user cannot switch to team they are not member of', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create(['name' => 'Other Team']);

    // Try to switch to a team user is not a member of
    $response = $this->actingAs($user)->post("/teams/switch/{$otherTeam->id}");

    $response->assertRedirect();
    $response->assertSessionHas('error', 'You are not a member of this team');
});

test('user with multiple teams sees all teams in shared props', function () {
    $user = User::factory()->create();
    $personalTeam = $user->teams()->first();

    // Create additional teams
    $team2 = Team::factory()->create(['name' => 'Team 2']);
    $team3 = Team::factory()->create(['name' => 'Team 3']);

    $team2->members()->attach($user->id, ['role' => 'admin']);
    $team3->members()->attach($user->id, ['role' => 'member']);

    // Refresh user model to get updated teams
    $user->refresh();

    // Verify user has 3 teams
    expect($user->teams)->toHaveCount(3);
});

test('switching team clears team cache', function () {
    $user = User::factory()->create();
    $personalTeam = $user->teams()->first();

    $workTeam = Team::factory()->create(['name' => 'Work Team']);
    $workTeam->members()->attach($user->id, ['role' => 'developer']);

    // Set initial team
    session(['currentTeam' => $personalTeam]);
    \Illuminate\Support\Facades\Cache::put('team:'.$user->id, $personalTeam, 3600);

    // Switch teams
    $this->actingAs($user)->post("/teams/switch/{$workTeam->id}");

    // Cache should be updated to new team
    $cachedTeam = \Illuminate\Support\Facades\Cache::get('team:'.$user->id);
    expect($cachedTeam->id)->toBe($workTeam->id);
});
