<?php

use App\Models\Environment;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use App\Policies\ServicePolicy;
use App\Services\Authorization\ProjectAuthorizationService;

beforeEach(function () {
    $this->authService = Mockery::mock(ProjectAuthorizationService::class);
    $this->policy = new ServicePolicy($this->authService);
});

afterEach(function () {
    Mockery::close();
});

/*
|--------------------------------------------------------------------------
| ViewAny Tests
|--------------------------------------------------------------------------
*/

it('allows viewing any services', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();

    expect($this->policy->viewAny($user))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| View Tests
|--------------------------------------------------------------------------
*/

it('denies viewing service when environment is null', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $service = Mockery::mock(Service::class)->makePartial()->shouldIgnoreMissing();

    $service->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn(null);

    expect($this->policy->view($user, $service))->toBeFalse();
});

it('allows viewing service when authorized', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $service = Mockery::mock(Service::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();

    $service->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $this->authService->shouldReceive('canViewProductionEnvironment')
        ->with($user, $environment)
        ->once()
        ->andReturn(true);

    expect($this->policy->view($user, $service))->toBeTrue();
});

it('denies viewing service when not authorized', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $service = Mockery::mock(Service::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();

    $service->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $this->authService->shouldReceive('canViewProductionEnvironment')
        ->with($user, $environment)
        ->once()
        ->andReturn(false);

    expect($this->policy->view($user, $service))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Create Tests
|--------------------------------------------------------------------------
| NOTE: create() tests are skipped because they rely on currentTeam() global
| helper which is difficult to mock in unit tests. These should be tested in
| feature tests instead.
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Update Tests
|--------------------------------------------------------------------------
*/

it('denies updating service when environment is null', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $service = Mockery::mock(Service::class)->makePartial()->shouldIgnoreMissing();

    $service->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn(null);

    expect($this->policy->update($user, $service))->toBeFalse();
});

it('denies updating service when project is null', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $service = Mockery::mock(Service::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();

    $service->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $environment->shouldReceive('getAttribute')
        ->with('project')
        ->andReturn(null);

    expect($this->policy->update($user, $service))->toBeFalse();
});

it('allows updating service when user has developer role', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $service = Mockery::mock(Service::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $service->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $environment->shouldReceive('getAttribute')
        ->with('project')
        ->andReturn($project);

    $this->authService->shouldReceive('hasMinimumRole')
        ->with($user, $project, 'developer')
        ->once()
        ->andReturn(true);

    expect($this->policy->update($user, $service))->toBeTrue();
});

it('denies updating service when user lacks developer role', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $service = Mockery::mock(Service::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $service->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $environment->shouldReceive('getAttribute')
        ->with('project')
        ->andReturn($project);

    $this->authService->shouldReceive('hasMinimumRole')
        ->with($user, $project, 'developer')
        ->once()
        ->andReturn(false);

    expect($this->policy->update($user, $service))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Delete Tests
|--------------------------------------------------------------------------
*/

it('denies deleting service when environment is null', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $service = Mockery::mock(Service::class)->makePartial()->shouldIgnoreMissing();

    $service->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn(null);

    expect($this->policy->delete($user, $service))->toBeFalse();
});

it('allows deleting service when user has admin role', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $service = Mockery::mock(Service::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $service->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $environment->shouldReceive('getAttribute')
        ->with('project')
        ->andReturn($project);

    $this->authService->shouldReceive('hasMinimumRole')
        ->with($user, $project, 'admin')
        ->once()
        ->andReturn(true);

    expect($this->policy->delete($user, $service))->toBeTrue();
});

it('denies deleting service when user lacks admin role', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $service = Mockery::mock(Service::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $service->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $environment->shouldReceive('getAttribute')
        ->with('project')
        ->andReturn($project);

    $this->authService->shouldReceive('hasMinimumRole')
        ->with($user, $project, 'admin')
        ->once()
        ->andReturn(false);

    expect($this->policy->delete($user, $service))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Restore Tests
|--------------------------------------------------------------------------
*/

it('delegates restore to delete', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $service = Mockery::mock(Service::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $service->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $environment->shouldReceive('getAttribute')
        ->with('project')
        ->andReturn($project);

    $this->authService->shouldReceive('hasMinimumRole')
        ->with($user, $project, 'admin')
        ->once()
        ->andReturn(true);

    expect($this->policy->restore($user, $service))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| ForceDelete Tests
|--------------------------------------------------------------------------
*/

it('delegates force delete to delete', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $service = Mockery::mock(Service::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $service->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $environment->shouldReceive('getAttribute')
        ->with('project')
        ->andReturn($project);

    $this->authService->shouldReceive('hasMinimumRole')
        ->with($user, $project, 'admin')
        ->once()
        ->andReturn(true);

    expect($this->policy->forceDelete($user, $service))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Stop Tests
|--------------------------------------------------------------------------
*/

it('delegates stop to update', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $service = Mockery::mock(Service::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $service->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $environment->shouldReceive('getAttribute')
        ->with('project')
        ->andReturn($project);

    $this->authService->shouldReceive('hasMinimumRole')
        ->with($user, $project, 'developer')
        ->once()
        ->andReturn(true);

    expect($this->policy->stop($user, $service))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| ManageEnvironment Tests
|--------------------------------------------------------------------------
*/

it('delegates manage environment to update', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $service = Mockery::mock(Service::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $service->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $environment->shouldReceive('getAttribute')
        ->with('project')
        ->andReturn($project);

    $this->authService->shouldReceive('hasMinimumRole')
        ->with($user, $project, 'developer')
        ->once()
        ->andReturn(true);

    expect($this->policy->manageEnvironment($user, $service))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| ViewSensitiveEnvironment Tests
|--------------------------------------------------------------------------
*/

it('denies viewing sensitive environment when environment is null', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $service = Mockery::mock(Service::class)->makePartial()->shouldIgnoreMissing();

    $service->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn(null);

    expect($this->policy->viewSensitiveEnvironment($user, $service))->toBeFalse();
});

it('allows viewing sensitive environment when user has admin role', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $service = Mockery::mock(Service::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $service->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $environment->shouldReceive('getAttribute')
        ->with('project')
        ->andReturn($project);

    $this->authService->shouldReceive('hasMinimumRole')
        ->with($user, $project, 'admin')
        ->once()
        ->andReturn(true);

    expect($this->policy->viewSensitiveEnvironment($user, $service))->toBeTrue();
});

it('denies viewing sensitive environment when user lacks admin role', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $service = Mockery::mock(Service::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $service->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $environment->shouldReceive('getAttribute')
        ->with('project')
        ->andReturn($project);

    $this->authService->shouldReceive('hasMinimumRole')
        ->with($user, $project, 'admin')
        ->once()
        ->andReturn(false);

    expect($this->policy->viewSensitiveEnvironment($user, $service))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Deploy Tests
|--------------------------------------------------------------------------
*/

it('denies deploying service when environment is null', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $service = Mockery::mock(Service::class)->makePartial()->shouldIgnoreMissing();

    $service->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn(null);

    expect($this->policy->deploy($user, $service))->toBeFalse();
});

it('allows deploying service when authorized', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $service = Mockery::mock(Service::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();

    $service->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $this->authService->shouldReceive('canDeploy')
        ->with($user, $environment)
        ->once()
        ->andReturn(true);

    expect($this->policy->deploy($user, $service))->toBeTrue();
});

it('denies deploying service when not authorized', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $service = Mockery::mock(Service::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();

    $service->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $this->authService->shouldReceive('canDeploy')
        ->with($user, $environment)
        ->once()
        ->andReturn(false);

    expect($this->policy->deploy($user, $service))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| AccessTerminal Tests
|--------------------------------------------------------------------------
*/

it('denies accessing terminal when environment is null', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $service = Mockery::mock(Service::class)->makePartial()->shouldIgnoreMissing();

    $service->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn(null);

    expect($this->policy->accessTerminal($user, $service))->toBeFalse();
});

it('allows accessing terminal when user has admin role', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $service = Mockery::mock(Service::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $service->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $environment->shouldReceive('getAttribute')
        ->with('project')
        ->andReturn($project);

    $this->authService->shouldReceive('hasMinimumRole')
        ->with($user, $project, 'admin')
        ->once()
        ->andReturn(true);

    expect($this->policy->accessTerminal($user, $service))->toBeTrue();
});

it('denies accessing terminal when user lacks admin role', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $service = Mockery::mock(Service::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $service->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $environment->shouldReceive('getAttribute')
        ->with('project')
        ->andReturn($project);

    $this->authService->shouldReceive('hasMinimumRole')
        ->with($user, $project, 'admin')
        ->once()
        ->andReturn(false);

    expect($this->policy->accessTerminal($user, $service))->toBeFalse();
});
