<?php

use App\Models\Team;
use App\Models\User;
use App\Policies\TeamPolicy;
use App\Services\Authorization\ResourceAuthorizationService;
use Illuminate\Support\Collection;

beforeEach(function () {
    $this->authService = Mockery::mock(ResourceAuthorizationService::class);
    $this->policy = new TeamPolicy($this->authService);
});

afterEach(function () {
    Mockery::close();
});

/*
|--------------------------------------------------------------------------
| ViewAny Tests
|--------------------------------------------------------------------------
*/

it('allows viewing any teams', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();

    expect($this->policy->viewAny($user))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| View Tests
|--------------------------------------------------------------------------
*/

it('allows viewing team when user is member', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $team = Mockery::mock(Team::class)->makePartial()->shouldIgnoreMissing();
    $team->id = 1;

    $teams = Mockery::mock(Collection::class);
    $teams->shouldReceive('contains')
        ->with('id', 1)
        ->once()
        ->andReturn(true);

    $user->shouldReceive('getAttribute')
        ->with('teams')
        ->andReturn($teams);

    expect($this->policy->view($user, $team))->toBeTrue();
});

it('denies viewing team when user is not member', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $team = Mockery::mock(Team::class)->makePartial()->shouldIgnoreMissing();
    $team->id = 1;

    $teams = Mockery::mock(Collection::class);
    $teams->shouldReceive('contains')
        ->with('id', 1)
        ->once()
        ->andReturn(false);

    $user->shouldReceive('getAttribute')
        ->with('teams')
        ->andReturn($teams);

    expect($this->policy->view($user, $team))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Create Tests
|--------------------------------------------------------------------------
*/

it('allows creating teams', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();

    expect($this->policy->create($user))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Update Tests
|--------------------------------------------------------------------------
*/

it('denies updating team when user is not member', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $team = Mockery::mock(Team::class)->makePartial()->shouldIgnoreMissing();
    $team->id = 1;

    $teams = Mockery::mock(Collection::class);
    $teams->shouldReceive('contains')
        ->with('id', 1)
        ->once()
        ->andReturn(false);

    $user->shouldReceive('getAttribute')
        ->with('teams')
        ->andReturn($teams);

    expect($this->policy->update($user, $team))->toBeFalse();
});

it('allows updating team when user has settings.update permission', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $team = Mockery::mock(Team::class)->makePartial()->shouldIgnoreMissing();
    $team->id = 1;

    $teams = Mockery::mock(Collection::class);
    $teams->shouldReceive('contains')
        ->with('id', 1)
        ->once()
        ->andReturn(true);

    $user->shouldReceive('getAttribute')
        ->with('teams')
        ->andReturn($teams);

    $this->authService->shouldReceive('hasPermission')
        ->with($user, 'settings.update', 1)
        ->once()
        ->andReturn(true);

    expect($this->policy->update($user, $team))->toBeTrue();
});

it('denies updating team when user lacks settings.update permission', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $team = Mockery::mock(Team::class)->makePartial()->shouldIgnoreMissing();
    $team->id = 1;

    $teams = Mockery::mock(Collection::class);
    $teams->shouldReceive('contains')
        ->with('id', 1)
        ->once()
        ->andReturn(true);

    $user->shouldReceive('getAttribute')
        ->with('teams')
        ->andReturn($teams);

    $this->authService->shouldReceive('hasPermission')
        ->with($user, 'settings.update', 1)
        ->once()
        ->andReturn(false);

    expect($this->policy->update($user, $team))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Delete Tests
|--------------------------------------------------------------------------
*/

it('denies deleting team when user is not member', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $team = Mockery::mock(Team::class)->makePartial()->shouldIgnoreMissing();
    $team->id = 1;

    $teams = Mockery::mock(Collection::class);
    $teams->shouldReceive('contains')
        ->with('id', 1)
        ->once()
        ->andReturn(false);

    $user->shouldReceive('getAttribute')
        ->with('teams')
        ->andReturn($teams);

    expect($this->policy->delete($user, $team))->toBeFalse();
});

it('allows deleting team when user has settings.update permission', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $team = Mockery::mock(Team::class)->makePartial()->shouldIgnoreMissing();
    $team->id = 1;

    $teams = Mockery::mock(Collection::class);
    $teams->shouldReceive('contains')
        ->with('id', 1)
        ->once()
        ->andReturn(true);

    $user->shouldReceive('getAttribute')
        ->with('teams')
        ->andReturn($teams);

    $this->authService->shouldReceive('hasPermission')
        ->with($user, 'settings.update', 1)
        ->once()
        ->andReturn(true);

    expect($this->policy->delete($user, $team))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| ManageMembers Tests
|--------------------------------------------------------------------------
*/

it('allows managing members when user has permission', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $team = Mockery::mock(Team::class)->makePartial()->shouldIgnoreMissing();
    $team->id = 1;

    $teams = Mockery::mock(Collection::class);
    $teams->shouldReceive('contains')
        ->with('id', 1)
        ->once()
        ->andReturn(true);

    $user->shouldReceive('getAttribute')
        ->with('teams')
        ->andReturn($teams);

    $this->authService->shouldReceive('canManageTeamMembers')
        ->with($user, 1)
        ->once()
        ->andReturn(true);

    expect($this->policy->manageMembers($user, $team))->toBeTrue();
});

it('denies managing members when user lacks permission', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $team = Mockery::mock(Team::class)->makePartial()->shouldIgnoreMissing();
    $team->id = 1;

    $teams = Mockery::mock(Collection::class);
    $teams->shouldReceive('contains')
        ->with('id', 1)
        ->once()
        ->andReturn(true);

    $user->shouldReceive('getAttribute')
        ->with('teams')
        ->andReturn($teams);

    $this->authService->shouldReceive('canManageTeamMembers')
        ->with($user, 1)
        ->once()
        ->andReturn(false);

    expect($this->policy->manageMembers($user, $team))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| ViewAdmin Tests
|--------------------------------------------------------------------------
*/

it('allows viewing admin panel when user has settings.view permission', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $team = Mockery::mock(Team::class)->makePartial()->shouldIgnoreMissing();
    $team->id = 1;

    $teams = Mockery::mock(Collection::class);
    $teams->shouldReceive('contains')
        ->with('id', 1)
        ->once()
        ->andReturn(true);

    $user->shouldReceive('getAttribute')
        ->with('teams')
        ->andReturn($teams);

    $this->authService->shouldReceive('hasPermission')
        ->with($user, 'settings.view', 1)
        ->once()
        ->andReturn(true);

    expect($this->policy->viewAdmin($user, $team))->toBeTrue();
});

it('denies viewing admin panel when user lacks permission', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $team = Mockery::mock(Team::class)->makePartial()->shouldIgnoreMissing();
    $team->id = 1;

    $teams = Mockery::mock(Collection::class);
    $teams->shouldReceive('contains')
        ->with('id', 1)
        ->once()
        ->andReturn(true);

    $user->shouldReceive('getAttribute')
        ->with('teams')
        ->andReturn($teams);

    $this->authService->shouldReceive('hasPermission')
        ->with($user, 'settings.view', 1)
        ->once()
        ->andReturn(false);

    expect($this->policy->viewAdmin($user, $team))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| ManageInvitations Tests
|--------------------------------------------------------------------------
*/

it('allows managing invitations when user has permission', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $team = Mockery::mock(Team::class)->makePartial()->shouldIgnoreMissing();
    $team->id = 1;

    $teams = Mockery::mock(Collection::class);
    $teams->shouldReceive('contains')
        ->with('id', 1)
        ->once()
        ->andReturn(true);

    $user->shouldReceive('getAttribute')
        ->with('teams')
        ->andReturn($teams);

    $this->authService->shouldReceive('canInviteMembers')
        ->with($user, 1)
        ->once()
        ->andReturn(true);

    expect($this->policy->manageInvitations($user, $team))->toBeTrue();
});

it('denies managing invitations when user lacks permission', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $team = Mockery::mock(Team::class)->makePartial()->shouldIgnoreMissing();
    $team->id = 1;

    $teams = Mockery::mock(Collection::class);
    $teams->shouldReceive('contains')
        ->with('id', 1)
        ->once()
        ->andReturn(true);

    $user->shouldReceive('getAttribute')
        ->with('teams')
        ->andReturn($teams);

    $this->authService->shouldReceive('canInviteMembers')
        ->with($user, 1)
        ->once()
        ->andReturn(false);

    expect($this->policy->manageInvitations($user, $team))->toBeFalse();
});
