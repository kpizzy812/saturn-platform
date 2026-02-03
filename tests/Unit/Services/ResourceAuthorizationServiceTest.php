<?php

use App\Models\Server;
use App\Models\StandalonePostgresql;
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
| Server Authorization Tests
|--------------------------------------------------------------------------
*/

it('allows platform admin to view any server', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(true);

    $server = Mockery::mock(Server::class)->makePartial()->shouldIgnoreMissing();

    expect($this->service->canViewServer($user, $server))->toBeTrue();
});

it('allows team member to view server when has permission', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->teams = collect([(object) ['id' => 1]]);

    $server = Mockery::mock(Server::class)->makePartial()->shouldIgnoreMissing();
    $server->team_id = 1;

    $this->permissionService->shouldReceive('userHasPermission')
        ->with($user, 'servers.view')
        ->once()
        ->andReturn(true);

    expect($this->service->canViewServer($user, $server))->toBeTrue();
});

it('denies user from viewing server in another team', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->teams = collect([(object) ['id' => 1]]);

    $server = Mockery::mock(Server::class)->makePartial()->shouldIgnoreMissing();
    $server->team_id = 2;

    expect($this->service->canViewServer($user, $server))->toBeFalse();
});

it('allows user to create server when has permission', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);

    $this->permissionService->shouldReceive('userHasPermission')
        ->with($user, 'servers.create')
        ->once()
        ->andReturn(true);

    expect($this->service->canCreateServer($user, 1))->toBeTrue();
});

it('denies user from creating server without permission', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);

    $this->permissionService->shouldReceive('userHasPermission')
        ->with($user, 'servers.create')
        ->once()
        ->andReturn(false);

    expect($this->service->canCreateServer($user, 1))->toBeFalse();
});

it('allows user to update server when has permission', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->teams = collect([(object) ['id' => 1]]);

    $server = Mockery::mock(Server::class)->makePartial()->shouldIgnoreMissing();
    $server->team_id = 1;

    $this->permissionService->shouldReceive('userHasPermission')
        ->with($user, 'servers.update')
        ->once()
        ->andReturn(true);

    expect($this->service->canUpdateServer($user, $server))->toBeTrue();
});

it('denies user from updating server without permission', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->teams = collect([(object) ['id' => 1]]);

    $server = Mockery::mock(Server::class)->makePartial()->shouldIgnoreMissing();
    $server->team_id = 1;

    $this->permissionService->shouldReceive('userHasPermission')
        ->with($user, 'servers.update')
        ->once()
        ->andReturn(false);

    expect($this->service->canUpdateServer($user, $server))->toBeFalse();
});

it('allows user to delete server when has permission', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->teams = collect([(object) ['id' => 1]]);

    $server = Mockery::mock(Server::class)->makePartial()->shouldIgnoreMissing();
    $server->team_id = 1;

    $this->permissionService->shouldReceive('userHasPermission')
        ->with($user, 'servers.delete')
        ->once()
        ->andReturn(true);

    expect($this->service->canDeleteServer($user, $server))->toBeTrue();
});

it('denies user from deleting server without permission', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->teams = collect([(object) ['id' => 1]]);

    $server = Mockery::mock(Server::class)->makePartial()->shouldIgnoreMissing();
    $server->team_id = 1;

    $this->permissionService->shouldReceive('userHasPermission')
        ->with($user, 'servers.delete')
        ->once()
        ->andReturn(false);

    expect($this->service->canDeleteServer($user, $server))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Database Authorization Tests
|--------------------------------------------------------------------------
*/

it('allows team member to view database when has permission', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->teams = collect([(object) ['id' => 1]]);

    $database = Mockery::mock(StandalonePostgresql::class)->makePartial()->shouldIgnoreMissing();
    $database->team_id = 1;

    $this->permissionService->shouldReceive('userHasPermission')
        ->with($user, 'databases.view')
        ->once()
        ->andReturn(true);

    expect($this->service->canViewDatabase($user, $database))->toBeTrue();
});

it('allows user to view database credentials when has permission', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->teams = collect([(object) ['id' => 1]]);

    $database = Mockery::mock(StandalonePostgresql::class)->makePartial()->shouldIgnoreMissing();
    $database->team_id = 1;

    $this->permissionService->shouldReceive('userHasPermission')
        ->with($user, 'databases.credentials')
        ->once()
        ->andReturn(true);

    expect($this->service->canViewDatabaseCredentials($user, $database))->toBeTrue();
});

it('denies user from viewing database credentials without permission', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->teams = collect([(object) ['id' => 1]]);

    $database = Mockery::mock(StandalonePostgresql::class)->makePartial()->shouldIgnoreMissing();
    $database->team_id = 1;

    $this->permissionService->shouldReceive('userHasPermission')
        ->with($user, 'databases.credentials')
        ->once()
        ->andReturn(false);

    expect($this->service->canViewDatabaseCredentials($user, $database))->toBeFalse();
});

it('allows user to delete database when has permission', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->teams = collect([(object) ['id' => 1]]);

    $database = Mockery::mock(StandalonePostgresql::class)->makePartial()->shouldIgnoreMissing();
    $database->team_id = 1;

    $this->permissionService->shouldReceive('userHasPermission')
        ->with($user, 'databases.delete')
        ->once()
        ->andReturn(true);

    expect($this->service->canDeleteDatabase($user, $database))->toBeTrue();
});

it('denies user from deleting database without permission', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->teams = collect([(object) ['id' => 1]]);

    $database = Mockery::mock(StandalonePostgresql::class)->makePartial()->shouldIgnoreMissing();
    $database->team_id = 1;

    $this->permissionService->shouldReceive('userHasPermission')
        ->with($user, 'databases.delete')
        ->once()
        ->andReturn(false);

    expect($this->service->canDeleteDatabase($user, $database))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Sensitive Data Access Tests
|--------------------------------------------------------------------------
*/

it('allows user to access sensitive data when has permission', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);

    $this->permissionService->shouldReceive('userHasPermission')
        ->with($user, 'applications.env_vars_sensitive')
        ->once()
        ->andReturn(true);

    expect($this->service->canAccessSensitiveData($user, 1))->toBeTrue();
});

it('denies user from accessing sensitive data without permission', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);

    $this->permissionService->shouldReceive('userHasPermission')
        ->with($user, 'applications.env_vars_sensitive')
        ->once()
        ->andReturn(false);

    expect($this->service->canAccessSensitiveData($user, 1))->toBeFalse();
});

it('allows platform admin to access sensitive data', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(true);

    expect($this->service->canAccessSensitiveData($user, 1))->toBeTrue();
});
