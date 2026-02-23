<?php

use App\Models\PrivateKey;
use App\Models\User;
use App\Policies\PrivateKeyPolicy;
use App\Services\Authorization\ResourceAuthorizationService;

beforeEach(function () {
    $this->authService = Mockery::mock(ResourceAuthorizationService::class);
    $this->policy = new PrivateKeyPolicy($this->authService);
});

afterEach(function () {
    Mockery::close();
});

/*
|--------------------------------------------------------------------------
| View Tests - System Resources (team_id=0)
|--------------------------------------------------------------------------
*/

it('allows root team admin to view system private key', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('canAccessSystemResources')->andReturn(true);

    $privateKey = Mockery::mock(PrivateKey::class)->makePartial()->shouldIgnoreMissing();
    $privateKey->shouldReceive('getAttribute')->with('team_id')->andReturn(0);
    $privateKey->team_id = 0;

    expect($this->policy->view($user, $privateKey))->toBeTrue();
});

it('denies regular member of root team to view system private key', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('canAccessSystemResources')->andReturn(false);

    $privateKey = Mockery::mock(PrivateKey::class)->makePartial()->shouldIgnoreMissing();
    $privateKey->shouldReceive('getAttribute')->with('team_id')->andReturn(0);
    $privateKey->team_id = 0;

    expect($this->policy->view($user, $privateKey))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| View Tests - Regular Resources
|--------------------------------------------------------------------------
*/

it('allows team member to view their own team private key', function () {
    $teams = collect([
        (object) ['id' => 1, 'pivot' => (object) ['role' => 'member']],
    ]);

    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);

    $privateKey = Mockery::mock(PrivateKey::class)->makePartial()->shouldIgnoreMissing();
    $privateKey->shouldReceive('getAttribute')->with('team_id')->andReturn(1);
    $privateKey->team_id = 1;

    expect($this->policy->view($user, $privateKey))->toBeTrue();
});

it('denies team member to view another team private key', function () {
    $teams = collect([
        (object) ['id' => 1, 'pivot' => (object) ['role' => 'owner']],
    ]);

    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);

    $privateKey = Mockery::mock(PrivateKey::class)->makePartial()->shouldIgnoreMissing();
    $privateKey->shouldReceive('getAttribute')->with('team_id')->andReturn(2);
    $privateKey->team_id = 2;

    expect($this->policy->view($user, $privateKey))->toBeFalse();
});

it('denies viewing private key with null team_id', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();

    $privateKey = Mockery::mock(PrivateKey::class)->makePartial()->shouldIgnoreMissing();
    $privateKey->shouldReceive('getAttribute')->with('team_id')->andReturn(null);
    $privateKey->team_id = null;

    expect($this->policy->view($user, $privateKey))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Update Tests - System Resources
|--------------------------------------------------------------------------
*/

it('allows root team admin to update system private key', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('canAccessSystemResources')->andReturn(true);

    $privateKey = Mockery::mock(PrivateKey::class)->makePartial()->shouldIgnoreMissing();
    $privateKey->shouldReceive('getAttribute')->with('team_id')->andReturn(0);
    $privateKey->team_id = 0;

    expect($this->policy->update($user, $privateKey))->toBeTrue();
});

it('denies root team member to update system private key', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('canAccessSystemResources')->andReturn(false);

    $privateKey = Mockery::mock(PrivateKey::class)->makePartial()->shouldIgnoreMissing();
    $privateKey->shouldReceive('getAttribute')->with('team_id')->andReturn(0);
    $privateKey->team_id = 0;

    expect($this->policy->update($user, $privateKey))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Update Tests - Regular Resources (uses PermissionService)
|--------------------------------------------------------------------------
*/

it('allows team member with servers.security permission to update private key', function () {
    $teams = collect([
        (object) ['id' => 1, 'pivot' => (object) ['role' => 'admin']],
    ]);

    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);

    $privateKey = Mockery::mock(PrivateKey::class)->makePartial()->shouldIgnoreMissing();
    $privateKey->shouldReceive('getAttribute')->with('team_id')->andReturn(1);
    $privateKey->team_id = 1;

    $this->authService->shouldReceive('hasPermission')
        ->with($user, 'servers.security', 1)
        ->once()
        ->andReturn(true);

    expect($this->policy->update($user, $privateKey))->toBeTrue();
});

it('denies team member without servers.security permission from updating private key', function () {
    $teams = collect([
        (object) ['id' => 1, 'pivot' => (object) ['role' => 'member']],
    ]);

    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);

    $privateKey = Mockery::mock(PrivateKey::class)->makePartial()->shouldIgnoreMissing();
    $privateKey->shouldReceive('getAttribute')->with('team_id')->andReturn(1);
    $privateKey->team_id = 1;

    $this->authService->shouldReceive('hasPermission')
        ->with($user, 'servers.security', 1)
        ->once()
        ->andReturn(false);

    expect($this->policy->update($user, $privateKey))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Delete Tests - System Resources
|--------------------------------------------------------------------------
*/

it('allows root team admin to delete system private key', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('canAccessSystemResources')->andReturn(true);

    $privateKey = Mockery::mock(PrivateKey::class)->makePartial()->shouldIgnoreMissing();
    $privateKey->shouldReceive('getAttribute')->with('team_id')->andReturn(0);
    $privateKey->team_id = 0;

    expect($this->policy->delete($user, $privateKey))->toBeTrue();
});

it('denies root team member to delete system private key', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('canAccessSystemResources')->andReturn(false);

    $privateKey = Mockery::mock(PrivateKey::class)->makePartial()->shouldIgnoreMissing();
    $privateKey->shouldReceive('getAttribute')->with('team_id')->andReturn(0);
    $privateKey->team_id = 0;

    expect($this->policy->delete($user, $privateKey))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Delete Tests - Regular Resources
|--------------------------------------------------------------------------
*/

it('allows team member with servers.security permission to delete private key', function () {
    $teams = collect([
        (object) ['id' => 1, 'pivot' => (object) ['role' => 'admin']],
    ]);

    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);

    $privateKey = Mockery::mock(PrivateKey::class)->makePartial()->shouldIgnoreMissing();
    $privateKey->shouldReceive('getAttribute')->with('team_id')->andReturn(1);
    $privateKey->team_id = 1;

    $this->authService->shouldReceive('hasPermission')
        ->with($user, 'servers.security', 1)
        ->once()
        ->andReturn(true);

    expect($this->policy->delete($user, $privateKey))->toBeTrue();
});

it('denies non-team member from deleting private key', function () {
    $teams = collect([
        (object) ['id' => 2, 'pivot' => (object) ['role' => 'admin']],
    ]);

    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);

    $privateKey = Mockery::mock(PrivateKey::class)->makePartial()->shouldIgnoreMissing();
    $privateKey->shouldReceive('getAttribute')->with('team_id')->andReturn(1);
    $privateKey->team_id = 1;

    expect($this->policy->delete($user, $privateKey))->toBeFalse();
});
