<?php

use App\Models\Environment;
use App\Models\Project;
use App\Models\User;
use App\Services\Authorization\ProjectAuthorizationService;

beforeEach(function () {
    $this->service = new ProjectAuthorizationService;
});

afterEach(function () {
    Mockery::close();
});

/*
|--------------------------------------------------------------------------
| canViewProductionEnvironment Tests
|--------------------------------------------------------------------------
*/

it('allows platform admin to view production environment', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(true);

    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();

    expect($this->service->canViewProductionEnvironment($user, $environment))->toBeTrue();
});

it('allows super admin to view production environment', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(true);

    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();

    expect($this->service->canViewProductionEnvironment($user, $environment))->toBeTrue();
});

it('allows anyone with project access to view non-production environment', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);

    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $environment->shouldReceive('isProduction')->andReturn(false);

    expect($this->service->canViewProductionEnvironment($user, $environment))->toBeTrue();
});

it('allows admin to view production environment', function () {
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->shouldReceive('roleInProject')->with($project)->andReturn('admin');
    $user->shouldReceive('teams')->andReturn(
        Mockery::mock()->shouldReceive('where')->andReturnSelf()->shouldReceive('first')->andReturnNull()->getMock()
    );

    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $environment->shouldReceive('isProduction')->andReturn(true);
    $environment->shouldReceive('getAttribute')->with('project')->andReturn($project);

    expect($this->service->canViewProductionEnvironment($user, $environment))->toBeTrue();
});

it('denies developer from viewing production environment', function () {
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->shouldReceive('roleInProject')->with($project)->andReturn('developer');
    $user->shouldReceive('teams')->andReturn(
        Mockery::mock()->shouldReceive('where')->andReturnSelf()->shouldReceive('first')->andReturnNull()->getMock()
    );

    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $environment->shouldReceive('isProduction')->andReturn(true);
    $environment->shouldReceive('getAttribute')->with('project')->andReturn($project);

    expect($this->service->canViewProductionEnvironment($user, $environment))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| canCreateEnvironment Tests
|--------------------------------------------------------------------------
*/

it('allows platform admin to create environment', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(true);

    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    expect($this->service->canCreateEnvironment($user, $project))->toBeTrue();
});

it('allows project admin to create environment', function () {
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->shouldReceive('roleInProject')->with($project)->andReturn('admin');
    $user->shouldReceive('teams')->andReturn(
        Mockery::mock()->shouldReceive('where')->andReturnSelf()->shouldReceive('first')->andReturnNull()->getMock()
    );

    expect($this->service->canCreateEnvironment($user, $project))->toBeTrue();
});

it('allows project owner to create environment', function () {
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->shouldReceive('roleInProject')->with($project)->andReturn('owner');
    $user->shouldReceive('teams')->andReturn(
        Mockery::mock()->shouldReceive('where')->andReturnSelf()->shouldReceive('first')->andReturnNull()->getMock()
    );

    expect($this->service->canCreateEnvironment($user, $project))->toBeTrue();
});

it('denies developer from creating environment', function () {
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->shouldReceive('roleInProject')->with($project)->andReturn('developer');
    $user->shouldReceive('teams')->andReturn(
        Mockery::mock()->shouldReceive('where')->andReturnSelf()->shouldReceive('first')->andReturnNull()->getMock()
    );

    expect($this->service->canCreateEnvironment($user, $project))->toBeFalse();
});

it('denies member from creating environment', function () {
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->shouldReceive('roleInProject')->with($project)->andReturn('member');
    $user->shouldReceive('teams')->andReturn(
        Mockery::mock()->shouldReceive('where')->andReturnSelf()->shouldReceive('first')->andReturnNull()->getMock()
    );

    expect($this->service->canCreateEnvironment($user, $project))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| filterVisibleEnvironments Tests
|--------------------------------------------------------------------------
*/

it('returns all environments for platform admin', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(true);

    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $prodEnv = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $prodEnv->shouldReceive('isProduction')->andReturn(true);
    $prodEnv->name = 'production';

    $devEnv = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $devEnv->shouldReceive('isProduction')->andReturn(false);
    $devEnv->name = 'development';

    $environments = collect([$prodEnv, $devEnv]);

    $result = $this->service->filterVisibleEnvironments($user, $project, $environments);

    expect($result)->toHaveCount(2);
});

it('returns all environments for project admin', function () {
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->shouldReceive('roleInProject')->with($project)->andReturn('admin');
    $user->shouldReceive('teams')->andReturn(
        Mockery::mock()->shouldReceive('where')->andReturnSelf()->shouldReceive('first')->andReturnNull()->getMock()
    );

    $prodEnv = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $prodEnv->shouldReceive('isProduction')->andReturn(true);
    $prodEnv->name = 'production';

    $devEnv = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $devEnv->shouldReceive('isProduction')->andReturn(false);
    $devEnv->name = 'development';

    $environments = collect([$prodEnv, $devEnv]);

    $result = $this->service->filterVisibleEnvironments($user, $project, $environments);

    expect($result)->toHaveCount(2);
});

it('filters out production environments for developer', function () {
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->shouldReceive('roleInProject')->with($project)->andReturn('developer');
    $user->shouldReceive('teams')->andReturn(
        Mockery::mock()->shouldReceive('where')->andReturnSelf()->shouldReceive('first')->andReturnNull()->getMock()
    );

    $prodEnv = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $prodEnv->shouldReceive('isProduction')->andReturn(true);
    $prodEnv->name = 'production';

    $devEnv = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $devEnv->shouldReceive('isProduction')->andReturn(false);
    $devEnv->name = 'development';

    $environments = collect([$prodEnv, $devEnv]);

    $result = $this->service->filterVisibleEnvironments($user, $project, $environments);

    expect($result)->toHaveCount(1);
    expect($result->first()->name)->toBe('development');
});

it('returns only non-production environments for member', function () {
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->shouldReceive('roleInProject')->with($project)->andReturn('member');
    $user->shouldReceive('teams')->andReturn(
        Mockery::mock()->shouldReceive('where')->andReturnSelf()->shouldReceive('first')->andReturnNull()->getMock()
    );

    $prodEnv = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $prodEnv->shouldReceive('isProduction')->andReturn(true);
    $prodEnv->name = 'production';

    $stagingEnv = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $stagingEnv->shouldReceive('isProduction')->andReturn(false);
    $stagingEnv->name = 'staging';

    $devEnv = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $devEnv->shouldReceive('isProduction')->andReturn(false);
    $devEnv->name = 'development';

    $environments = collect([$prodEnv, $stagingEnv, $devEnv]);

    $result = $this->service->filterVisibleEnvironments($user, $project, $environments);

    expect($result)->toHaveCount(2);
    expect($result->pluck('name')->toArray())->toContain('staging', 'development');
    expect($result->pluck('name')->toArray())->not->toContain('production');
});
