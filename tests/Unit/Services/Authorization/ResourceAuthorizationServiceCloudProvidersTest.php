<?php

use App\Models\User;
use App\Services\Authorization\PermissionService;
use App\Services\Authorization\ResourceAuthorizationService;

beforeEach(function () {
    $this->permissionService = Mockery::mock(PermissionService::class);
    $this->service = new ResourceAuthorizationService($this->permissionService);
});

afterEach(function () {
    Mockery::close();
});

/*
|--------------------------------------------------------------------------
| canManageCloudProviders Tests
|--------------------------------------------------------------------------
*/

it('allows superadmin to manage cloud providers', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isSuperAdmin')->andReturn(true);

    expect($this->service->canManageCloudProviders($user))->toBeTrue();
});

it('allows superadmin to manage cloud providers with explicit team id', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isSuperAdmin')->andReturn(true);

    expect($this->service->canManageCloudProviders($user, 5))->toBeTrue();
});

it('allows user with cloud providers permission to manage cloud providers', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);

    $this->permissionService->shouldReceive('userHasPermission')
        ->with($user, 'settings.cloud_providers')
        ->once()
        ->andReturn(true);

    expect($this->service->canManageCloudProviders($user, 1))->toBeTrue();
});

it('denies user without cloud providers permission to manage cloud providers', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);

    $this->permissionService->shouldReceive('userHasPermission')
        ->with($user, 'settings.cloud_providers')
        ->once()
        ->andReturn(false);

    expect($this->service->canManageCloudProviders($user, 1))->toBeFalse();
});

it('denies regular member without permission set to manage cloud providers', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);

    $this->permissionService->shouldReceive('userHasPermission')
        ->with($user, 'settings.cloud_providers')
        ->once()
        ->andReturn(false);

    expect($this->service->canManageCloudProviders($user, 2))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| canCreateServer permission check (ensures no hardcoded role bypass)
|--------------------------------------------------------------------------
*/

it('allows user with servers create permission to create server', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);

    $this->permissionService->shouldReceive('userHasPermission')
        ->with($user, 'servers.create')
        ->once()
        ->andReturn(true);

    expect($this->service->canCreateServer($user, 1))->toBeTrue();
});

it('denies user without servers create permission to create server', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);

    $this->permissionService->shouldReceive('userHasPermission')
        ->with($user, 'servers.create')
        ->once()
        ->andReturn(false);

    expect($this->service->canCreateServer($user, 1))->toBeFalse();
});

it('allows platform admin to create server bypassing permission check', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(true);

    // permissionService should NOT be called for platform admins
    $this->permissionService->shouldNotReceive('userHasPermission');

    expect($this->service->canCreateServer($user, 1))->toBeTrue();
});
