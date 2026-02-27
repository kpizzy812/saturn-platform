<?php

use App\Models\Application;
use App\Models\Environment;
use App\Models\Project;
use App\Models\User;
use App\Policies\ApplicationPolicy;
use App\Services\Authorization\ProjectAuthorizationService;

beforeEach(function () {
    $this->authService = Mockery::mock(ProjectAuthorizationService::class);
    $this->policy = new ApplicationPolicy($this->authService);
});

afterEach(function () {
    Mockery::close();
});

/*
|--------------------------------------------------------------------------
| ViewAny Tests
|--------------------------------------------------------------------------
*/

it('allows viewing any applications', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();

    expect($this->policy->viewAny($user))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| View Tests
|--------------------------------------------------------------------------
*/

it('allows viewing orphaned application when user is platform admin', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(true);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);

    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $application->shouldReceive('getAttribute')->with('environment')->andReturn(null);

    expect($this->policy->view($user, $application))->toBeTrue();
});

it('denies viewing orphaned application when user is not admin', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);

    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $application->shouldReceive('getAttribute')->with('environment')->andReturn(null);

    expect($this->policy->view($user, $application))->toBeFalse();
});

it('allows viewing application when authorized', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();

    $application->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $this->authService->shouldReceive('canViewEnvironment')
        ->with($user, $environment)
        ->once()
        ->andReturn(true);

    expect($this->policy->view($user, $application))->toBeTrue();
});

it('denies viewing application when not authorized', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();

    $application->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $this->authService->shouldReceive('canViewEnvironment')
        ->with($user, $environment)
        ->once()
        ->andReturn(false);

    expect($this->policy->view($user, $application))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Create Tests
|--------------------------------------------------------------------------
*/

it('allows creating applications', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();

    expect($this->policy->create($user))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Update Tests
|--------------------------------------------------------------------------
*/

it('allows updating orphaned application when user is platform admin', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(true);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);

    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $application->shouldReceive('getAttribute')->with('environment')->andReturn(null);

    expect($this->policy->update($user, $application)->allowed())->toBeTrue();
});

it('denies updating orphaned application when user is not admin', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);

    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $application->shouldReceive('getAttribute')->with('environment')->andReturn(null);

    expect($this->policy->update($user, $application)->allowed())->toBeFalse();
});

it('allows updating application when user can manage project', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $application->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $environment->shouldReceive('getAttribute')
        ->with('project')
        ->andReturn($project);

    $this->authService->shouldReceive('canManageProject')
        ->with($user, $project)
        ->once()
        ->andReturn(true);

    $result = $this->policy->update($user, $application);
    expect($result->allowed())->toBeTrue();
});

it('allows updating application when user has developer role', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $application->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $environment->shouldReceive('getAttribute')
        ->with('project')
        ->andReturn($project);

    $this->authService->shouldReceive('canManageProject')
        ->with($user, $project)
        ->once()
        ->andReturn(false);

    $this->authService->shouldReceive('hasMinimumRole')
        ->with($user, $project, 'developer')
        ->once()
        ->andReturn(true);

    $result = $this->policy->update($user, $application);
    expect($result->allowed())->toBeTrue();
});

it('denies updating application when user has no permission', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $application->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $environment->shouldReceive('getAttribute')
        ->with('project')
        ->andReturn($project);

    $this->authService->shouldReceive('canManageProject')
        ->with($user, $project)
        ->once()
        ->andReturn(false);

    $this->authService->shouldReceive('hasMinimumRole')
        ->with($user, $project, 'developer')
        ->once()
        ->andReturn(false);

    $result = $this->policy->update($user, $application);
    expect($result->allowed())->toBeFalse();
    expect($result->message())->toContain('developer permissions');
});

/*
|--------------------------------------------------------------------------
| Delete Tests
|--------------------------------------------------------------------------
*/

it('allows deleting orphaned application when user is platform admin', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(true);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);

    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $application->shouldReceive('getAttribute')->with('environment')->andReturn(null);

    expect($this->policy->delete($user, $application))->toBeTrue();
});

it('denies deleting orphaned application when user is not admin', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);

    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $application->shouldReceive('getAttribute')->with('environment')->andReturn(null);

    expect($this->policy->delete($user, $application))->toBeFalse();
});

it('allows deleting application when authorized', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $application->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $environment->shouldReceive('getAttribute')
        ->with('project')
        ->andReturn($project);

    $this->authService->shouldReceive('canManageProject')
        ->with($user, $project)
        ->once()
        ->andReturn(true);

    expect($this->policy->delete($user, $application))->toBeTrue();
});

it('denies deleting application when not authorized', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $application->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $environment->shouldReceive('getAttribute')
        ->with('project')
        ->andReturn($project);

    $this->authService->shouldReceive('canManageProject')
        ->with($user, $project)
        ->once()
        ->andReturn(false);

    expect($this->policy->delete($user, $application))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Restore Tests
|--------------------------------------------------------------------------
*/

it('allows restoring orphaned application when user is platform admin', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(true);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);

    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $application->shouldReceive('getAttribute')->with('environment')->andReturn(null);

    expect($this->policy->restore($user, $application))->toBeTrue();
});

it('denies restoring orphaned application when user is not admin', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);

    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $application->shouldReceive('getAttribute')->with('environment')->andReturn(null);

    expect($this->policy->restore($user, $application))->toBeFalse();
});

it('allows restoring application when authorized', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $application->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $environment->shouldReceive('getAttribute')
        ->with('project')
        ->andReturn($project);

    $this->authService->shouldReceive('canManageProject')
        ->with($user, $project)
        ->once()
        ->andReturn(true);

    expect($this->policy->restore($user, $application))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| ForceDelete Tests
|--------------------------------------------------------------------------
*/

it('allows force deleting orphaned application when user is platform admin', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(true);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);

    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $application->shouldReceive('getAttribute')->with('environment')->andReturn(null);

    expect($this->policy->forceDelete($user, $application))->toBeTrue();
});

it('denies force deleting orphaned application when user is not admin', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);

    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $application->shouldReceive('getAttribute')->with('environment')->andReturn(null);

    expect($this->policy->forceDelete($user, $application))->toBeFalse();
});

it('allows force deleting application when authorized', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $application->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $environment->shouldReceive('getAttribute')
        ->with('project')
        ->andReturn($project);

    $this->authService->shouldReceive('canManageProject')
        ->with($user, $project)
        ->once()
        ->andReturn(true);

    expect($this->policy->forceDelete($user, $application))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Deploy Tests
|--------------------------------------------------------------------------
*/

it('allows deploying orphaned application when user is platform admin', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(true);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);

    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $application->shouldReceive('getAttribute')->with('environment')->andReturn(null);

    expect($this->policy->deploy($user, $application))->toBeTrue();
});

it('denies deploying orphaned application when user is not admin', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);

    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $application->shouldReceive('getAttribute')->with('environment')->andReturn(null);

    expect($this->policy->deploy($user, $application))->toBeFalse();
});

it('allows deploying application when authorized', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();

    $application->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $this->authService->shouldReceive('canDeployApplication')
        ->with($user, $application)
        ->once()
        ->andReturn(true);

    expect($this->policy->deploy($user, $application))->toBeTrue();
});

it('denies deploying application when not authorized', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();

    $application->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $this->authService->shouldReceive('canDeployApplication')
        ->with($user, $application)
        ->once()
        ->andReturn(false);

    expect($this->policy->deploy($user, $application))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| ManageDeployments Tests
|--------------------------------------------------------------------------
*/

it('allows managing deployments of orphaned application when user is platform admin', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(true);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);

    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $application->shouldReceive('getAttribute')->with('environment')->andReturn(null);

    expect($this->policy->manageDeployments($user, $application))->toBeTrue();
});

it('denies managing deployments of orphaned application when user is not admin', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);

    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $application->shouldReceive('getAttribute')->with('environment')->andReturn(null);

    expect($this->policy->manageDeployments($user, $application))->toBeFalse();
});

it('allows managing deployments when authorized', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $application->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $environment->shouldReceive('getAttribute')
        ->with('project')
        ->andReturn($project);

    $this->authService->shouldReceive('canManageProject')
        ->with($user, $project)
        ->once()
        ->andReturn(true);

    expect($this->policy->manageDeployments($user, $application))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| ManageEnvironment Tests
|--------------------------------------------------------------------------
*/

it('allows managing environment of orphaned application when user is platform admin', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(true);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);

    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $application->shouldReceive('getAttribute')->with('environment')->andReturn(null);

    expect($this->policy->manageEnvironment($user, $application))->toBeTrue();
});

it('denies managing environment of orphaned application when user is not admin', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);

    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $application->shouldReceive('getAttribute')->with('environment')->andReturn(null);

    expect($this->policy->manageEnvironment($user, $application))->toBeFalse();
});

it('allows managing environment when user has developer role', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $application->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $environment->shouldReceive('getAttribute')
        ->with('project')
        ->andReturn($project);

    $this->authService->shouldReceive('hasMinimumRole')
        ->with($user, $project, 'developer')
        ->once()
        ->andReturn(true);

    expect($this->policy->manageEnvironment($user, $application))->toBeTrue();
});

it('denies managing environment when user lacks developer role', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $application->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $environment->shouldReceive('getAttribute')
        ->with('project')
        ->andReturn($project);

    $this->authService->shouldReceive('hasMinimumRole')
        ->with($user, $project, 'developer')
        ->once()
        ->andReturn(false);

    expect($this->policy->manageEnvironment($user, $application))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| ViewSensitiveEnvironment Tests
|--------------------------------------------------------------------------
*/

it('allows viewing sensitive environment of orphaned application when user is platform admin', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(true);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);

    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $application->shouldReceive('getAttribute')->with('environment')->andReturn(null);

    expect($this->policy->viewSensitiveEnvironment($user, $application))->toBeTrue();
});

it('denies viewing sensitive environment of orphaned application when user is not admin', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);

    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $application->shouldReceive('getAttribute')->with('environment')->andReturn(null);

    expect($this->policy->viewSensitiveEnvironment($user, $application))->toBeFalse();
});

it('allows viewing sensitive environment when user has admin role', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $application->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $environment->shouldReceive('getAttribute')
        ->with('project')
        ->andReturn($project);

    $this->authService->shouldReceive('hasMinimumRole')
        ->with($user, $project, 'admin')
        ->once()
        ->andReturn(true);

    expect($this->policy->viewSensitiveEnvironment($user, $application))->toBeTrue();
});

it('denies viewing sensitive environment when user lacks admin role', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();

    $application->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $environment->shouldReceive('getAttribute')
        ->with('project')
        ->andReturn($project);

    $this->authService->shouldReceive('hasMinimumRole')
        ->with($user, $project, 'admin')
        ->once()
        ->andReturn(false);

    expect($this->policy->viewSensitiveEnvironment($user, $application))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| CleanupDeploymentQueue Tests
|--------------------------------------------------------------------------
*/

it('allows cleanup deployment queue when user is platform admin', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();

    $user->shouldReceive('isPlatformAdmin')
        ->andReturn(true);

    $user->shouldReceive('isSuperAdmin')
        ->andReturn(false);

    expect($this->policy->cleanupDeploymentQueue($user))->toBeTrue();
});

it('allows cleanup deployment queue when user is super admin', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();

    $user->shouldReceive('isPlatformAdmin')
        ->andReturn(false);

    $user->shouldReceive('isSuperAdmin')
        ->andReturn(true);

    expect($this->policy->cleanupDeploymentQueue($user))->toBeTrue();
});

it('denies cleanup deployment queue when user is neither platform nor super admin', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();

    $user->shouldReceive('isPlatformAdmin')
        ->andReturn(false);

    $user->shouldReceive('isSuperAdmin')
        ->andReturn(false);

    expect($this->policy->cleanupDeploymentQueue($user))->toBeFalse();
});
