<?php

use App\Models\Environment;
use App\Models\Project;
use App\Models\User;
use App\Policies\EnvironmentPolicy;
use App\Services\Authorization\ProjectAuthorizationService;

beforeEach(function () {
    $this->authService = Mockery::mock(ProjectAuthorizationService::class);
    $this->policy = new EnvironmentPolicy($this->authService);
});

afterEach(function () {
    Mockery::close();
});

/*
|--------------------------------------------------------------------------
| View Tests - Production Environment Visibility
|--------------------------------------------------------------------------
*/

it('allows admin to view production environment', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $environment->shouldReceive('getAttribute')->with('project')->andReturn($project);

    // Admin has project access and can view production
    $this->authService->shouldReceive('canViewProject')
        ->with($user, $project)
        ->once()
        ->andReturn(true);

    $this->authService->shouldReceive('canViewProductionEnvironment')
        ->with($user, $environment)
        ->once()
        ->andReturn(true);

    expect($this->policy->view($user, $environment))->toBeTrue();
});

it('denies developer access to view production environment', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $environment->shouldReceive('getAttribute')->with('project')->andReturn($project);

    // Developer has project access but cannot view production
    $this->authService->shouldReceive('canViewProject')
        ->with($user, $project)
        ->once()
        ->andReturn(true);

    $this->authService->shouldReceive('canViewProductionEnvironment')
        ->with($user, $environment)
        ->once()
        ->andReturn(false);

    expect($this->policy->view($user, $environment))->toBeFalse();
});

it('allows developer to view non-production environment', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $environment->shouldReceive('getAttribute')->with('project')->andReturn($project);

    // Developer has project access and can view non-production
    $this->authService->shouldReceive('canViewProject')
        ->with($user, $project)
        ->once()
        ->andReturn(true);

    $this->authService->shouldReceive('canViewProductionEnvironment')
        ->with($user, $environment)
        ->once()
        ->andReturn(true);

    expect($this->policy->view($user, $environment))->toBeTrue();
});

it('denies access when user has no project access', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $environment->shouldReceive('getAttribute')->with('project')->andReturn($project);

    // User has no project access
    $this->authService->shouldReceive('canViewProject')
        ->with($user, $project)
        ->once()
        ->andReturn(false);

    expect($this->policy->view($user, $environment))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Create Tests - Only Owner/Admin
|--------------------------------------------------------------------------
*/

it('allows admin to create environment', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canCreateEnvironment')
        ->with($user, $project)
        ->once()
        ->andReturn(true);

    expect($this->policy->create($user, $project))->toBeTrue();
});

it('denies developer from creating environment', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canCreateEnvironment')
        ->with($user, $project)
        ->once()
        ->andReturn(false);

    expect($this->policy->create($user, $project))->toBeFalse();
});

it('denies create when project is null', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();

    expect($this->policy->create($user, null))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Update Tests - Only Owner/Admin
|--------------------------------------------------------------------------
*/

it('allows admin to update environment', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canManageEnvironment')
        ->with($user, $environment)
        ->once()
        ->andReturn(true);

    expect($this->policy->update($user, $environment))->toBeTrue();
});

it('denies developer from updating environment', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canManageEnvironment')
        ->with($user, $environment)
        ->once()
        ->andReturn(false);

    expect($this->policy->update($user, $environment))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Delete Tests - Only Owner/Admin
|--------------------------------------------------------------------------
*/

it('allows admin to delete environment', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canManageEnvironment')
        ->with($user, $environment)
        ->once()
        ->andReturn(true);

    expect($this->policy->delete($user, $environment))->toBeTrue();
});

it('denies developer from deleting environment', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canManageEnvironment')
        ->with($user, $environment)
        ->once()
        ->andReturn(false);

    expect($this->policy->delete($user, $environment))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Deploy Tests
|--------------------------------------------------------------------------
*/

it('allows user to deploy when authorized', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canDeploy')
        ->with($user, $environment)
        ->once()
        ->andReturn(true);

    expect($this->policy->deploy($user, $environment))->toBeTrue();
});

it('denies deploy when not authorized', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();

    $this->authService->shouldReceive('canDeploy')
        ->with($user, $environment)
        ->once()
        ->andReturn(false);

    expect($this->policy->deploy($user, $environment))->toBeFalse();
});
