<?php

namespace App\Policies;

use App\Models\DeploymentApproval;
use App\Models\User;
use App\Services\Authorization\ProjectAuthorizationService;

class DeploymentApprovalPolicy
{
    public function __construct(
        protected ProjectAuthorizationService $authService
    ) {}

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DeploymentApproval $approval): bool
    {
        $deployment = $approval->deployment;
        if (! $deployment) {
            return false;
        }

        $application = $deployment->application;
        if (! $application) {
            return false;
        }

        $environment = $application->environment;
        if (! $environment) {
            return false;
        }

        return $this->authService->canViewEnvironment($user, $environment);
    }

    /**
     * Determine whether the user can approve the deployment.
     */
    public function approve(User $user, DeploymentApproval $approval): bool
    {
        if (! $approval->isPending()) {
            return false;
        }

        $deployment = $approval->deployment;
        if (! $deployment) {
            return false;
        }

        $application = $deployment->application;
        if (! $application) {
            return false;
        }

        $environment = $application->environment;
        if (! $environment) {
            return false;
        }

        return $this->authService->canApproveDeployment($user, $environment);
    }

    /**
     * Determine whether the user can reject the deployment.
     */
    public function reject(User $user, DeploymentApproval $approval): bool
    {
        // Same requirements as approve
        return $this->approve($user, $approval);
    }

    /**
     * Determine whether the user can cancel their own approval request.
     */
    public function cancel(User $user, DeploymentApproval $approval): bool
    {
        if (! $approval->isPending()) {
            return false;
        }

        // User can cancel their own request
        if ($approval->requested_by === $user->id) {
            return true;
        }

        // Admins can cancel any request
        $deployment = $approval->deployment;
        if (! $deployment) {
            return false;
        }

        $application = $deployment->application;
        if (! $application) {
            return false;
        }

        $environment = $application->environment;
        if (! $environment) {
            return false;
        }

        return $this->authService->canApproveDeployment($user, $environment);
    }
}
