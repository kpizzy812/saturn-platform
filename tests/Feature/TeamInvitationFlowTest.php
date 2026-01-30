<?php

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user can see received invitations on team settings page', function () {
    // Create two teams
    $teamA = Team::factory()->create(['name' => 'Team A']);
    $teamB = Team::factory()->create(['name' => 'Team B']);

    // Create a user who is member of Team A
    $user = User::factory()->create(['email' => 'user@example.com']);
    $teamA->members()->attach($user->id, ['role' => 'member']);

    // Create invitation for user to join Team B
    $invitation = TeamInvitation::create([
        'team_id' => $teamB->id,
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'email' => $user->email,
        'role' => 'developer',
        'link' => url('/invitations/test-uuid'),
        'via' => 'link',
    ]);

    // Act as the user and visit team settings
    $response = $this->actingAs($user)->get('/settings/team');

    // Assert the response contains received invitation data
    $response->assertInertia(fn ($page) => $page
        ->component('Settings/Team')
        ->has('receivedInvitations', 1)
        ->where('receivedInvitations.0.teamName', 'Team B')
        ->where('receivedInvitations.0.role', 'developer')
    );
});

test('user does not see invitation for teams they are already member of', function () {
    // Create a team
    $team = Team::factory()->create(['name' => 'Test Team']);

    // Create a user who is already member of the team
    $user = User::factory()->create(['email' => 'user@example.com']);
    $team->members()->attach($user->id, ['role' => 'member']);

    // Create invitation for same team (should be filtered out)
    TeamInvitation::create([
        'team_id' => $team->id,
        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'email' => $user->email,
        'role' => 'admin',
        'link' => url('/invitations/test-uuid'),
        'via' => 'link',
    ]);

    // Visit team settings
    $response = $this->actingAs($user)->get('/settings/team');

    // Assert no received invitations shown (user is already member)
    $response->assertInertia(fn ($page) => $page
        ->component('Settings/Team')
        ->has('receivedInvitations', 0)
    );
});

test('user can accept invitation and join new team', function () {
    // Create two teams
    $personalTeam = Team::factory()->create(['name' => 'Personal Team', 'personal_team' => true]);
    $newTeam = Team::factory()->create(['name' => 'New Team']);

    // Create a user
    $user = User::factory()->create(['email' => 'user@example.com', 'current_team_id' => $personalTeam->id]);
    $personalTeam->members()->attach($user->id, ['role' => 'owner']);

    // Create invitation
    $uuid = \Illuminate\Support\Str::uuid()->toString();
    $invitation = TeamInvitation::create([
        'team_id' => $newTeam->id,
        'uuid' => $uuid,
        'email' => $user->email,
        'role' => 'developer',
        'link' => url("/invitations/{$uuid}"),
        'via' => 'link',
    ]);

    // Accept invitation
    $response = $this->actingAs($user)->post("/invitations/{$uuid}/accept");

    // Assert user was added to new team
    expect($newTeam->members()->where('user_id', $user->id)->exists())->toBeTrue();

    // Assert invitation was deleted
    expect(TeamInvitation::where('uuid', $uuid)->exists())->toBeFalse();

    // Assert user was switched to new team
    expect($user->fresh()->current_team_id)->toBe($newTeam->id);

    // Assert redirect to dashboard
    $response->assertRedirect('/dashboard');
});

test('user can decline invitation', function () {
    // Create team
    $team = Team::factory()->create(['name' => 'Test Team']);

    // Create user
    $user = User::factory()->create(['email' => 'user@example.com']);

    // Create invitation
    $uuid = \Illuminate\Support\Str::uuid()->toString();
    $invitation = TeamInvitation::create([
        'team_id' => $team->id,
        'uuid' => $uuid,
        'email' => $user->email,
        'role' => 'member',
        'link' => url("/invitations/{$uuid}"),
        'via' => 'link',
    ]);

    // Decline invitation
    $response = $this->actingAs($user)->post("/invitations/{$uuid}/decline");

    // Assert user was NOT added to team
    expect($team->members()->where('user_id', $user->id)->exists())->toBeFalse();

    // Assert invitation was deleted
    expect(TeamInvitation::where('uuid', $uuid)->exists())->toBeFalse();

    // Assert redirect to dashboard
    $response->assertRedirect('/dashboard');
});

test('user cannot accept invitation sent to different email', function () {
    // Create team
    $team = Team::factory()->create(['name' => 'Test Team']);

    // Create user with different email
    $user = User::factory()->create(['email' => 'user@example.com']);

    // Create invitation for different email
    $uuid = \Illuminate\Support\Str::uuid()->toString();
    $invitation = TeamInvitation::create([
        'team_id' => $team->id,
        'uuid' => $uuid,
        'email' => 'different@example.com',
        'role' => 'member',
        'link' => url("/invitations/{$uuid}"),
        'via' => 'link',
    ]);

    // Try to accept invitation
    $response = $this->actingAs($user)->post("/invitations/{$uuid}/accept");

    // Assert user was NOT added to team
    expect($team->members()->where('user_id', $user->id)->exists())->toBeFalse();

    // Assert invitation still exists
    expect(TeamInvitation::where('uuid', $uuid)->exists())->toBeTrue();

    // Assert error message
    $response->assertRedirect();
    $response->assertSessionHas('error');
});

test('user cannot accept already used invitation', function () {
    // Create team
    $team = Team::factory()->create(['name' => 'Test Team']);

    // Create user
    $user = User::factory()->create(['email' => 'user@example.com']);

    // Add user to team (simulate already accepted)
    $team->members()->attach($user->id, ['role' => 'member']);

    // Create invitation (simulating orphaned invitation)
    $uuid = \Illuminate\Support\Str::uuid()->toString();
    $invitation = TeamInvitation::create([
        'team_id' => $team->id,
        'uuid' => $uuid,
        'email' => $user->email,
        'role' => 'member',
        'link' => url("/invitations/{$uuid}"),
        'via' => 'link',
    ]);

    // Try to accept invitation again
    $response = $this->actingAs($user)->post("/invitations/{$uuid}/accept");

    // Assert invitation was deleted
    expect(TeamInvitation::where('uuid', $uuid)->exists())->toBeFalse();

    // Assert info message
    $response->assertRedirect('/dashboard');
    $response->assertSessionHas('info', 'You are already a member of this team.');
});

test('expired invitation shows error page', function () {
    // Create team
    $team = Team::factory()->create(['name' => 'Test Team']);

    // Create user
    $user = User::factory()->create(['email' => 'user@example.com']);

    // Create old invitation (created 8 days ago, beyond 7 day expiration)
    $uuid = \Illuminate\Support\Str::uuid()->toString();
    $invitation = TeamInvitation::create([
        'team_id' => $team->id,
        'uuid' => $uuid,
        'email' => $user->email,
        'role' => 'member',
        'link' => url("/invitations/{$uuid}"),
        'via' => 'link',
        'created_at' => now()->subDays(8),
    ]);

    // Visit invitation page
    $response = $this->actingAs($user)->get("/invitations/{$uuid}");

    // Assert error page is shown
    $response->assertInertia(fn ($page) => $page
        ->component('Auth/AcceptInvite')
        ->where('invitation', null)
        ->has('error')
    );
});
