<?php

use App\Models\Project;
use App\Models\User;
use App\Policies\ProjectPolicy;
use App\Services\Authorization\ProjectAuthorizationService;

beforeEach(function () {
    $this->authService = Mockery::mock(ProjectAuthorizationService::class);
    $this->policy = new ProjectPolicy($this->authService);
});

afterEach(function () {
    Mockery::close();
});

/*
|--------------------------------------------------------------------------
| ViewAny Tests
|--------------------------------------------------------------------------
*/

it('allows viewing any projects', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();

    expect($this->policy->viewAny($user))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| View Tests
|--------------------------------------------------------------------------
*/

it('allows viewing project when authorized', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canViewProject')
        ->with($user, $project)
        ->once()
        ->andReturn(true);

    expect($this->policy->view($user, $project))->toBeTrue();
});

it('denies viewing project when not authorized', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canViewProject')
        ->with($user, $project)
        ->once()
        ->andReturn(false);

    expect($this->policy->view($user, $project))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Create Tests
|--------------------------------------------------------------------------
*/

it('allows creating projects', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();

    expect($this->policy->create($user))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Update Tests
|--------------------------------------------------------------------------
*/

it('allows updating project when authorized', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canManageProject')
        ->with($user, $project)
        ->once()
        ->andReturn(true);

    expect($this->policy->update($user, $project))->toBeTrue();
});

it('denies updating project when not authorized', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canManageProject')
        ->with($user, $project)
        ->once()
        ->andReturn(false);

    expect($this->policy->update($user, $project))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Delete Tests
|--------------------------------------------------------------------------
*/

it('allows deleting project when authorized', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canDeleteProject')
        ->with($user, $project)
        ->once()
        ->andReturn(true);

    expect($this->policy->delete($user, $project))->toBeTrue();
});

it('denies deleting project when not authorized', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canDeleteProject')
        ->with($user, $project)
        ->once()
        ->andReturn(false);

    expect($this->policy->delete($user, $project))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Restore Tests
|--------------------------------------------------------------------------
*/

it('allows restoring project when authorized', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canDeleteProject')
        ->with($user, $project)
        ->once()
        ->andReturn(true);

    expect($this->policy->restore($user, $project))->toBeTrue();
});

it('denies restoring project when not authorized', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canDeleteProject')
        ->with($user, $project)
        ->once()
        ->andReturn(false);

    expect($this->policy->restore($user, $project))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| ForceDelete Tests
|--------------------------------------------------------------------------
*/

it('allows force deleting project when authorized', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canDeleteProject')
        ->with($user, $project)
        ->once()
        ->andReturn(true);

    expect($this->policy->forceDelete($user, $project))->toBeTrue();
});

it('denies force deleting project when not authorized', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canDeleteProject')
        ->with($user, $project)
        ->once()
        ->andReturn(false);

    expect($this->policy->forceDelete($user, $project))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| ManageMembers Tests
|--------------------------------------------------------------------------
*/

it('allows managing members when authorized', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canManageMembers')
        ->with($user, $project)
        ->once()
        ->andReturn(true);

    expect($this->policy->manageMembers($user, $project))->toBeTrue();
});

it('denies managing members when not authorized', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canManageMembers')
        ->with($user, $project)
        ->once()
        ->andReturn(false);

    expect($this->policy->manageMembers($user, $project))->toBeFalse();
});
