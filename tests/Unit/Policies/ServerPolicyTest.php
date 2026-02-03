<?php

use App\Models\Server;
use App\Models\User;
use App\Policies\ServerPolicy;
use App\Services\Authorization\ResourceAuthorizationService;

beforeEach(function () {
    $this->authService = Mockery::mock(ResourceAuthorizationService::class);
    $this->policy = new ServerPolicy($this->authService);
});

afterEach(function () {
    Mockery::close();
});

/*
|--------------------------------------------------------------------------
| View Tests
|--------------------------------------------------------------------------
*/

it('allows viewing server when authorized', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $server = Mockery::mock(Server::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canViewServer')
        ->with($user, $server)
        ->once()
        ->andReturn(true);

    expect($this->policy->view($user, $server))->toBeTrue();
});

it('denies viewing server when not authorized', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $server = Mockery::mock(Server::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canViewServer')
        ->with($user, $server)
        ->once()
        ->andReturn(false);

    expect($this->policy->view($user, $server))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Update Tests
|--------------------------------------------------------------------------
*/

it('allows updating server when admin', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $server = Mockery::mock(Server::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canUpdateServer')
        ->with($user, $server)
        ->once()
        ->andReturn(true);

    expect($this->policy->update($user, $server))->toBeTrue();
});

it('denies updating server when developer', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $server = Mockery::mock(Server::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canUpdateServer')
        ->with($user, $server)
        ->once()
        ->andReturn(false);

    expect($this->policy->update($user, $server))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Delete Tests
|--------------------------------------------------------------------------
*/

it('allows deleting server when owner', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $server = Mockery::mock(Server::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canDeleteServer')
        ->with($user, $server)
        ->once()
        ->andReturn(true);

    expect($this->policy->delete($user, $server))->toBeTrue();
});

it('denies deleting server when admin', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $server = Mockery::mock(Server::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canDeleteServer')
        ->with($user, $server)
        ->once()
        ->andReturn(false);

    expect($this->policy->delete($user, $server))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Manage Proxy Tests
|--------------------------------------------------------------------------
*/

it('allows managing proxy when admin', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $server = Mockery::mock(Server::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canManageServerProxy')
        ->with($user, $server)
        ->once()
        ->andReturn(true);

    expect($this->policy->manageProxy($user, $server))->toBeTrue();
});

it('denies managing proxy when developer', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $server = Mockery::mock(Server::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canManageServerProxy')
        ->with($user, $server)
        ->once()
        ->andReturn(false);

    expect($this->policy->manageProxy($user, $server))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| View Security Tests
|--------------------------------------------------------------------------
*/

it('allows viewing security when admin', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $server = Mockery::mock(Server::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canViewServerSecurity')
        ->with($user, $server)
        ->once()
        ->andReturn(true);

    expect($this->policy->viewSecurity($user, $server))->toBeTrue();
});

it('denies viewing security when developer', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $server = Mockery::mock(Server::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canViewServerSecurity')
        ->with($user, $server)
        ->once()
        ->andReturn(false);

    expect($this->policy->viewSecurity($user, $server))->toBeFalse();
});
