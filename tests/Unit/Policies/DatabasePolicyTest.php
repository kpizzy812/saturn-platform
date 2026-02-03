<?php

use App\Models\StandalonePostgresql;
use App\Models\User;
use App\Policies\DatabasePolicy;
use App\Services\Authorization\ResourceAuthorizationService;

beforeEach(function () {
    $this->authService = Mockery::mock(ResourceAuthorizationService::class);
    $this->policy = new DatabasePolicy($this->authService);
});

afterEach(function () {
    Mockery::close();
});

/*
|--------------------------------------------------------------------------
| View Tests
|--------------------------------------------------------------------------
*/

it('allows viewing database when team member', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $database = Mockery::mock(StandalonePostgresql::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canViewDatabase')
        ->with($user, $database)
        ->once()
        ->andReturn(true);

    expect($this->policy->view($user, $database))->toBeTrue();
});

it('denies viewing database when not team member', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $database = Mockery::mock(StandalonePostgresql::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canViewDatabase')
        ->with($user, $database)
        ->once()
        ->andReturn(false);

    expect($this->policy->view($user, $database))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| View Credentials Tests
|--------------------------------------------------------------------------
*/

it('allows viewing credentials when admin', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $database = Mockery::mock(StandalonePostgresql::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canViewDatabaseCredentials')
        ->with($user, $database)
        ->once()
        ->andReturn(true);

    expect($this->policy->viewCredentials($user, $database))->toBeTrue();
});

it('denies viewing credentials when developer', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $database = Mockery::mock(StandalonePostgresql::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canViewDatabaseCredentials')
        ->with($user, $database)
        ->once()
        ->andReturn(false);

    expect($this->policy->viewCredentials($user, $database))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Update Tests
|--------------------------------------------------------------------------
*/

it('allows updating database when admin', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $database = Mockery::mock(StandalonePostgresql::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canUpdateDatabase')
        ->with($user, $database)
        ->once()
        ->andReturn(true);

    $result = $this->policy->update($user, $database);
    expect($result->allowed())->toBeTrue();
});

it('denies updating database when developer', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $database = Mockery::mock(StandalonePostgresql::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canUpdateDatabase')
        ->with($user, $database)
        ->once()
        ->andReturn(false);

    $result = $this->policy->update($user, $database);
    expect($result->allowed())->toBeFalse();
    expect($result->message())->toContain('admin or owner');
});

/*
|--------------------------------------------------------------------------
| Delete Tests
|--------------------------------------------------------------------------
*/

it('allows deleting database when owner', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $database = Mockery::mock(StandalonePostgresql::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canDeleteDatabase')
        ->with($user, $database)
        ->once()
        ->andReturn(true);

    expect($this->policy->delete($user, $database))->toBeTrue();
});

it('denies deleting database when admin', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $database = Mockery::mock(StandalonePostgresql::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canDeleteDatabase')
        ->with($user, $database)
        ->once()
        ->andReturn(false);

    expect($this->policy->delete($user, $database))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Manage Tests
|--------------------------------------------------------------------------
*/

it('allows managing database when admin', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $database = Mockery::mock(StandalonePostgresql::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canManageDatabase')
        ->with($user, $database)
        ->once()
        ->andReturn(true);

    expect($this->policy->manage($user, $database))->toBeTrue();
});

it('denies managing database when developer', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $database = Mockery::mock(StandalonePostgresql::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canManageDatabase')
        ->with($user, $database)
        ->once()
        ->andReturn(false);

    expect($this->policy->manage($user, $database))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Manage Backups Tests
|--------------------------------------------------------------------------
*/

it('allows managing backups when admin', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $database = Mockery::mock(StandalonePostgresql::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canManageDatabaseBackups')
        ->with($user, $database)
        ->once()
        ->andReturn(true);

    expect($this->policy->manageBackups($user, $database))->toBeTrue();
});

it('denies managing backups when developer', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $database = Mockery::mock(StandalonePostgresql::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canManageDatabaseBackups')
        ->with($user, $database)
        ->once()
        ->andReturn(false);

    expect($this->policy->manageBackups($user, $database))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Manage Environment Tests
|--------------------------------------------------------------------------
*/

it('allows managing environment when admin', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $database = Mockery::mock(StandalonePostgresql::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canManageDatabaseEnvironment')
        ->with($user, $database)
        ->once()
        ->andReturn(true);

    expect($this->policy->manageEnvironment($user, $database))->toBeTrue();
});

it('denies managing environment when developer', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $database = Mockery::mock(StandalonePostgresql::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canManageDatabaseEnvironment')
        ->with($user, $database)
        ->once()
        ->andReturn(false);

    expect($this->policy->manageEnvironment($user, $database))->toBeFalse();
});
