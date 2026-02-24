<?php

use App\Models\CloudProviderToken;
use App\Models\User;
use App\Policies\CloudProviderTokenPolicy;
use App\Services\Authorization\ResourceAuthorizationService;

beforeEach(function () {
    $this->authService = Mockery::mock(ResourceAuthorizationService::class);
    $this->policy = new CloudProviderTokenPolicy($this->authService);
});

afterEach(function () {
    Mockery::close();
});

/*
|--------------------------------------------------------------------------
| View Tests - Team Membership Check (IDOR prevention)
|--------------------------------------------------------------------------
*/

it('allows team member to view cloud provider token from their team', function () {
    $teams = collect([
        (object) ['id' => 1, 'pivot' => (object) ['role' => 'member']],
    ]);

    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);

    $token = Mockery::mock(CloudProviderToken::class)->makePartial()->shouldIgnoreMissing();
    $token->shouldReceive('getAttribute')->with('team_id')->andReturn(1);
    $token->team_id = 1;

    expect($this->policy->view($user, $token))->toBeTrue();
});

it('denies team member from viewing cloud provider token from another team', function () {
    $teams = collect([
        (object) ['id' => 1, 'pivot' => (object) ['role' => 'admin']],
    ]);

    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);

    $token = Mockery::mock(CloudProviderToken::class)->makePartial()->shouldIgnoreMissing();
    $token->shouldReceive('getAttribute')->with('team_id')->andReturn(2);
    $token->team_id = 2;

    expect($this->policy->view($user, $token))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Update Tests - Team Check + Permission (IDOR prevention)
|--------------------------------------------------------------------------
*/

it('allows user with permission to update cloud provider token from their team', function () {
    $teams = collect([
        (object) ['id' => 1, 'pivot' => (object) ['role' => 'admin']],
    ]);

    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);

    $token = Mockery::mock(CloudProviderToken::class)->makePartial()->shouldIgnoreMissing();
    $token->shouldReceive('getAttribute')->with('team_id')->andReturn(1);
    $token->team_id = 1;

    $this->authService->shouldReceive('canManageIntegrations')
        ->with($user, 1)
        ->once()
        ->andReturn(true);

    expect($this->policy->update($user, $token))->toBeTrue();
});

it('denies user without permission from updating cloud provider token', function () {
    $teams = collect([
        (object) ['id' => 1, 'pivot' => (object) ['role' => 'member']],
    ]);

    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);

    $token = Mockery::mock(CloudProviderToken::class)->makePartial()->shouldIgnoreMissing();
    $token->shouldReceive('getAttribute')->with('team_id')->andReturn(1);
    $token->team_id = 1;

    $this->authService->shouldReceive('canManageIntegrations')
        ->with($user, 1)
        ->once()
        ->andReturn(false);

    expect($this->policy->update($user, $token))->toBeFalse();
});

it('denies updating cloud provider token from another team even with permission', function () {
    $teams = collect([
        (object) ['id' => 1, 'pivot' => (object) ['role' => 'admin']],
    ]);

    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);

    $token = Mockery::mock(CloudProviderToken::class)->makePartial()->shouldIgnoreMissing();
    $token->shouldReceive('getAttribute')->with('team_id')->andReturn(2);
    $token->team_id = 2;

    // Permission check should NOT be called - team check fails first
    $this->authService->shouldNotReceive('canManageIntegrations');

    expect($this->policy->update($user, $token))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Delete Tests - Team Check + Permission (IDOR prevention)
|--------------------------------------------------------------------------
*/

it('allows user with permission to delete cloud provider token from their team', function () {
    $teams = collect([
        (object) ['id' => 1, 'pivot' => (object) ['role' => 'admin']],
    ]);

    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);

    $token = Mockery::mock(CloudProviderToken::class)->makePartial()->shouldIgnoreMissing();
    $token->shouldReceive('getAttribute')->with('team_id')->andReturn(1);
    $token->team_id = 1;

    $this->authService->shouldReceive('canManageIntegrations')
        ->with($user, 1)
        ->once()
        ->andReturn(true);

    expect($this->policy->delete($user, $token))->toBeTrue();
});

it('denies deleting cloud provider token from another team', function () {
    $teams = collect([
        (object) ['id' => 1, 'pivot' => (object) ['role' => 'admin']],
    ]);

    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);

    $token = Mockery::mock(CloudProviderToken::class)->makePartial()->shouldIgnoreMissing();
    $token->shouldReceive('getAttribute')->with('team_id')->andReturn(2);
    $token->team_id = 2;

    expect($this->policy->delete($user, $token))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Edge Cases
|--------------------------------------------------------------------------
*/

it('denies viewing cloud provider token with null team_id', function () {
    $teams = collect([
        (object) ['id' => 1, 'pivot' => (object) ['role' => 'admin']],
    ]);

    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);

    $token = Mockery::mock(CloudProviderToken::class)->makePartial()->shouldIgnoreMissing();
    $token->shouldReceive('getAttribute')->with('team_id')->andReturn(null);
    $token->team_id = null;

    expect($this->policy->view($user, $token))->toBeFalse();
});

it('restore and forceDelete always return false', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $token = Mockery::mock(CloudProviderToken::class)->makePartial()->shouldIgnoreMissing();

    expect($this->policy->restore($user, $token))->toBeFalse();
    expect($this->policy->forceDelete($user, $token))->toBeFalse();
});
