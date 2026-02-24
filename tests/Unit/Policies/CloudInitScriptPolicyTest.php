<?php

use App\Models\CloudInitScript;
use App\Models\User;
use App\Policies\CloudInitScriptPolicy;
use App\Services\Authorization\ResourceAuthorizationService;

beforeEach(function () {
    $this->authService = Mockery::mock(ResourceAuthorizationService::class);
    $this->policy = new CloudInitScriptPolicy($this->authService);
});

afterEach(function () {
    Mockery::close();
});

/*
|--------------------------------------------------------------------------
| View Tests - Team Membership Check (IDOR prevention)
|--------------------------------------------------------------------------
*/

it('allows team member to view cloud init script from their team', function () {
    $teams = collect([
        (object) ['id' => 1, 'pivot' => (object) ['role' => 'member']],
    ]);

    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);

    $script = Mockery::mock(CloudInitScript::class)->makePartial()->shouldIgnoreMissing();
    $script->shouldReceive('getAttribute')->with('team_id')->andReturn(1);
    $script->team_id = 1;

    expect($this->policy->view($user, $script))->toBeTrue();
});

it('denies team member from viewing cloud init script from another team', function () {
    $teams = collect([
        (object) ['id' => 1, 'pivot' => (object) ['role' => 'admin']],
    ]);

    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);

    $script = Mockery::mock(CloudInitScript::class)->makePartial()->shouldIgnoreMissing();
    $script->shouldReceive('getAttribute')->with('team_id')->andReturn(2);
    $script->team_id = 2;

    expect($this->policy->view($user, $script))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Update Tests - Team Check + Permission (IDOR prevention)
|--------------------------------------------------------------------------
*/

it('allows user with permission to update cloud init script from their team', function () {
    $teams = collect([
        (object) ['id' => 1, 'pivot' => (object) ['role' => 'admin']],
    ]);

    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);

    $script = Mockery::mock(CloudInitScript::class)->makePartial()->shouldIgnoreMissing();
    $script->shouldReceive('getAttribute')->with('team_id')->andReturn(1);
    $script->team_id = 1;

    $this->authService->shouldReceive('canManageIntegrations')
        ->with($user, 1)
        ->once()
        ->andReturn(true);

    expect($this->policy->update($user, $script))->toBeTrue();
});

it('denies user without permission from updating cloud init script', function () {
    $teams = collect([
        (object) ['id' => 1, 'pivot' => (object) ['role' => 'member']],
    ]);

    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);

    $script = Mockery::mock(CloudInitScript::class)->makePartial()->shouldIgnoreMissing();
    $script->shouldReceive('getAttribute')->with('team_id')->andReturn(1);
    $script->team_id = 1;

    $this->authService->shouldReceive('canManageIntegrations')
        ->with($user, 1)
        ->once()
        ->andReturn(false);

    expect($this->policy->update($user, $script))->toBeFalse();
});

it('denies updating cloud init script from another team even with permission', function () {
    $teams = collect([
        (object) ['id' => 1, 'pivot' => (object) ['role' => 'admin']],
    ]);

    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);

    $script = Mockery::mock(CloudInitScript::class)->makePartial()->shouldIgnoreMissing();
    $script->shouldReceive('getAttribute')->with('team_id')->andReturn(2);
    $script->team_id = 2;

    // Permission check should NOT be called - team check fails first
    $this->authService->shouldNotReceive('canManageIntegrations');

    expect($this->policy->update($user, $script))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Delete Tests - Team Check + Permission (IDOR prevention)
|--------------------------------------------------------------------------
*/

it('allows user with permission to delete cloud init script from their team', function () {
    $teams = collect([
        (object) ['id' => 1, 'pivot' => (object) ['role' => 'admin']],
    ]);

    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);

    $script = Mockery::mock(CloudInitScript::class)->makePartial()->shouldIgnoreMissing();
    $script->shouldReceive('getAttribute')->with('team_id')->andReturn(1);
    $script->team_id = 1;

    $this->authService->shouldReceive('canManageIntegrations')
        ->with($user, 1)
        ->once()
        ->andReturn(true);

    expect($this->policy->delete($user, $script))->toBeTrue();
});

it('denies deleting cloud init script from another team', function () {
    $teams = collect([
        (object) ['id' => 1, 'pivot' => (object) ['role' => 'admin']],
    ]);

    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);

    $script = Mockery::mock(CloudInitScript::class)->makePartial()->shouldIgnoreMissing();
    $script->shouldReceive('getAttribute')->with('team_id')->andReturn(2);
    $script->team_id = 2;

    expect($this->policy->delete($user, $script))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Edge Cases
|--------------------------------------------------------------------------
*/

it('denies viewing cloud init script with null team_id', function () {
    $teams = collect([
        (object) ['id' => 1, 'pivot' => (object) ['role' => 'admin']],
    ]);

    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);

    $script = Mockery::mock(CloudInitScript::class)->makePartial()->shouldIgnoreMissing();
    $script->shouldReceive('getAttribute')->with('team_id')->andReturn(null);
    $script->team_id = null;

    expect($this->policy->view($user, $script))->toBeFalse();
});

it('restore and forceDelete always return false', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $script = Mockery::mock(CloudInitScript::class)->makePartial()->shouldIgnoreMissing();

    expect($this->policy->restore($user, $script))->toBeFalse();
    expect($this->policy->forceDelete($user, $script))->toBeFalse();
});
