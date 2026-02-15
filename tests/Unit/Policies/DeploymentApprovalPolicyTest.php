<?php

use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\DeploymentApproval;
use App\Models\Environment;
use App\Models\User;
use App\Policies\DeploymentApprovalPolicy;
use App\Services\Authorization\ProjectAuthorizationService;

beforeEach(function () {
    $this->authService = Mockery::mock(ProjectAuthorizationService::class);
    $this->policy = new DeploymentApprovalPolicy($this->authService);
});

afterEach(function () {
    Mockery::close();
});

/*
|--------------------------------------------------------------------------
| ViewAny Tests
|--------------------------------------------------------------------------
*/

it('allows viewing any deployment approvals', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();

    expect($this->policy->viewAny($user))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| View Tests
|--------------------------------------------------------------------------
*/

it('denies viewing approval when deployment is null', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $approval = Mockery::mock(DeploymentApproval::class)->makePartial()->shouldIgnoreMissing();

    $approval->shouldReceive('getAttribute')
        ->with('deployment')
        ->andReturn(null);

    expect($this->policy->view($user, $approval))->toBeFalse();
});

it('denies viewing approval when application is null', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $approval = Mockery::mock(DeploymentApproval::class)->makePartial()->shouldIgnoreMissing();
    $deployment = Mockery::mock(ApplicationDeploymentQueue::class)->makePartial()->shouldIgnoreMissing();

    $approval->shouldReceive('getAttribute')
        ->with('deployment')
        ->andReturn($deployment);

    $deployment->shouldReceive('getAttribute')
        ->with('application')
        ->andReturn(null);

    expect($this->policy->view($user, $approval))->toBeFalse();
});

it('denies viewing approval when environment is null', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $approval = Mockery::mock(DeploymentApproval::class)->makePartial()->shouldIgnoreMissing();
    $deployment = Mockery::mock(ApplicationDeploymentQueue::class)->makePartial()->shouldIgnoreMissing();
    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();

    $approval->shouldReceive('getAttribute')
        ->with('deployment')
        ->andReturn($deployment);

    $deployment->shouldReceive('getAttribute')
        ->with('application')
        ->andReturn($application);

    $application->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn(null);

    expect($this->policy->view($user, $approval))->toBeFalse();
});

it('allows viewing approval when authorized', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $approval = Mockery::mock(DeploymentApproval::class)->makePartial()->shouldIgnoreMissing();
    $deployment = Mockery::mock(ApplicationDeploymentQueue::class)->makePartial()->shouldIgnoreMissing();
    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();

    $approval->shouldReceive('getAttribute')
        ->with('deployment')
        ->andReturn($deployment);

    $deployment->shouldReceive('getAttribute')
        ->with('application')
        ->andReturn($application);

    $application->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $this->authService->shouldReceive('canViewEnvironment')
        ->with($user, $environment)
        ->once()
        ->andReturn(true);

    expect($this->policy->view($user, $approval))->toBeTrue();
});

it('denies viewing approval when not authorized', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $approval = Mockery::mock(DeploymentApproval::class)->makePartial()->shouldIgnoreMissing();
    $deployment = Mockery::mock(ApplicationDeploymentQueue::class)->makePartial()->shouldIgnoreMissing();
    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();

    $approval->shouldReceive('getAttribute')
        ->with('deployment')
        ->andReturn($deployment);

    $deployment->shouldReceive('getAttribute')
        ->with('application')
        ->andReturn($application);

    $application->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $this->authService->shouldReceive('canViewEnvironment')
        ->with($user, $environment)
        ->once()
        ->andReturn(false);

    expect($this->policy->view($user, $approval))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Approve Tests
|--------------------------------------------------------------------------
*/

it('denies approving when approval is not pending', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $approval = Mockery::mock(DeploymentApproval::class)->makePartial()->shouldIgnoreMissing();

    $approval->shouldReceive('isPending')
        ->once()
        ->andReturn(false);

    expect($this->policy->approve($user, $approval))->toBeFalse();
});

it('denies approving when deployment is null', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $approval = Mockery::mock(DeploymentApproval::class)->makePartial()->shouldIgnoreMissing();

    $approval->shouldReceive('isPending')
        ->once()
        ->andReturn(true);

    $approval->shouldReceive('getAttribute')
        ->with('deployment')
        ->andReturn(null);

    expect($this->policy->approve($user, $approval))->toBeFalse();
});

it('denies approving when application is null', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $approval = Mockery::mock(DeploymentApproval::class)->makePartial()->shouldIgnoreMissing();
    $deployment = Mockery::mock(ApplicationDeploymentQueue::class)->makePartial()->shouldIgnoreMissing();

    $approval->shouldReceive('isPending')
        ->once()
        ->andReturn(true);

    $approval->shouldReceive('getAttribute')
        ->with('deployment')
        ->andReturn($deployment);

    $deployment->shouldReceive('getAttribute')
        ->with('application')
        ->andReturn(null);

    expect($this->policy->approve($user, $approval))->toBeFalse();
});

it('denies approving when environment is null', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $approval = Mockery::mock(DeploymentApproval::class)->makePartial()->shouldIgnoreMissing();
    $deployment = Mockery::mock(ApplicationDeploymentQueue::class)->makePartial()->shouldIgnoreMissing();
    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();

    $approval->shouldReceive('isPending')
        ->once()
        ->andReturn(true);

    $approval->shouldReceive('getAttribute')
        ->with('deployment')
        ->andReturn($deployment);

    $deployment->shouldReceive('getAttribute')
        ->with('application')
        ->andReturn($application);

    $application->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn(null);

    expect($this->policy->approve($user, $approval))->toBeFalse();
});

it('allows approving when authorized', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $approval = Mockery::mock(DeploymentApproval::class)->makePartial()->shouldIgnoreMissing();
    $deployment = Mockery::mock(ApplicationDeploymentQueue::class)->makePartial()->shouldIgnoreMissing();
    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();

    $approval->shouldReceive('isPending')
        ->once()
        ->andReturn(true);

    $approval->shouldReceive('getAttribute')
        ->with('deployment')
        ->andReturn($deployment);

    $deployment->shouldReceive('getAttribute')
        ->with('application')
        ->andReturn($application);

    $application->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $this->authService->shouldReceive('canApproveDeployment')
        ->with($user, $environment)
        ->once()
        ->andReturn(true);

    expect($this->policy->approve($user, $approval))->toBeTrue();
});

it('denies approving when not authorized', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $approval = Mockery::mock(DeploymentApproval::class)->makePartial()->shouldIgnoreMissing();
    $deployment = Mockery::mock(ApplicationDeploymentQueue::class)->makePartial()->shouldIgnoreMissing();
    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();

    $approval->shouldReceive('isPending')
        ->once()
        ->andReturn(true);

    $approval->shouldReceive('getAttribute')
        ->with('deployment')
        ->andReturn($deployment);

    $deployment->shouldReceive('getAttribute')
        ->with('application')
        ->andReturn($application);

    $application->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $this->authService->shouldReceive('canApproveDeployment')
        ->with($user, $environment)
        ->once()
        ->andReturn(false);

    expect($this->policy->approve($user, $approval))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Reject Tests
|--------------------------------------------------------------------------
*/

it('delegates reject to approve', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $approval = Mockery::mock(DeploymentApproval::class)->makePartial()->shouldIgnoreMissing();
    $deployment = Mockery::mock(ApplicationDeploymentQueue::class)->makePartial()->shouldIgnoreMissing();
    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();

    $approval->shouldReceive('isPending')
        ->once()
        ->andReturn(true);

    $approval->shouldReceive('getAttribute')
        ->with('deployment')
        ->andReturn($deployment);

    $deployment->shouldReceive('getAttribute')
        ->with('application')
        ->andReturn($application);

    $application->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $this->authService->shouldReceive('canApproveDeployment')
        ->with($user, $environment)
        ->once()
        ->andReturn(true);

    expect($this->policy->reject($user, $approval))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Cancel Tests
|--------------------------------------------------------------------------
*/

it('denies cancelling when approval is not pending', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->id = 1;
    $approval = Mockery::mock(DeploymentApproval::class)->makePartial()->shouldIgnoreMissing();

    $approval->shouldReceive('isPending')
        ->once()
        ->andReturn(false);

    expect($this->policy->cancel($user, $approval))->toBeFalse();
});

it('allows cancelling when user is the requester', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->id = 1;
    $approval = Mockery::mock(DeploymentApproval::class)->makePartial()->shouldIgnoreMissing();

    $approval->shouldReceive('isPending')
        ->once()
        ->andReturn(true);

    $approval->shouldReceive('getAttribute')
        ->with('requested_by')
        ->andReturn(1);

    expect($this->policy->cancel($user, $approval))->toBeTrue();
});

it('denies cancelling when deployment is null and user is not requester', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->id = 1;
    $approval = Mockery::mock(DeploymentApproval::class)->makePartial()->shouldIgnoreMissing();

    $approval->shouldReceive('isPending')
        ->once()
        ->andReturn(true);

    $approval->shouldReceive('getAttribute')
        ->with('requested_by')
        ->andReturn(2);

    $approval->shouldReceive('getAttribute')
        ->with('deployment')
        ->andReturn(null);

    expect($this->policy->cancel($user, $approval))->toBeFalse();
});

it('allows cancelling when user can approve deployment', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->id = 1;
    $approval = Mockery::mock(DeploymentApproval::class)->makePartial()->shouldIgnoreMissing();
    $deployment = Mockery::mock(ApplicationDeploymentQueue::class)->makePartial()->shouldIgnoreMissing();
    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();

    $approval->shouldReceive('isPending')
        ->once()
        ->andReturn(true);

    $approval->shouldReceive('getAttribute')
        ->with('requested_by')
        ->andReturn(2);

    $approval->shouldReceive('getAttribute')
        ->with('deployment')
        ->andReturn($deployment);

    $deployment->shouldReceive('getAttribute')
        ->with('application')
        ->andReturn($application);

    $application->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $this->authService->shouldReceive('canApproveDeployment')
        ->with($user, $environment)
        ->once()
        ->andReturn(true);

    expect($this->policy->cancel($user, $approval))->toBeTrue();
});

it('denies cancelling when user cannot approve deployment and is not requester', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->id = 1;
    $approval = Mockery::mock(DeploymentApproval::class)->makePartial()->shouldIgnoreMissing();
    $deployment = Mockery::mock(ApplicationDeploymentQueue::class)->makePartial()->shouldIgnoreMissing();
    $application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $environment = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();

    $approval->shouldReceive('isPending')
        ->once()
        ->andReturn(true);

    $approval->shouldReceive('getAttribute')
        ->with('requested_by')
        ->andReturn(2);

    $approval->shouldReceive('getAttribute')
        ->with('deployment')
        ->andReturn($deployment);

    $deployment->shouldReceive('getAttribute')
        ->with('application')
        ->andReturn($application);

    $application->shouldReceive('getAttribute')
        ->with('environment')
        ->andReturn($environment);

    $this->authService->shouldReceive('canApproveDeployment')
        ->with($user, $environment)
        ->once()
        ->andReturn(false);

    expect($this->policy->cancel($user, $approval))->toBeFalse();
});
