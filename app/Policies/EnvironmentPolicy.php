<?php

namespace App\Policies;

use App\Models\Environment;
use App\Models\User;
use App\Services\Authorization\ProjectAuthorizationService;

class EnvironmentPolicy
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
    public function view(User $user, Environment $environment): bool
    {
        return $this->authService->canViewEnvironment($user, $environment);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Any authenticated team member can create environments
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Environment $environment): bool
    {
        return $this->authService->canManageEnvironment($user, $environment);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Environment $environment): bool
    {
        return $this->authService->canManageEnvironment($user, $environment);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Environment $environment): bool
    {
        return $this->authService->canManageEnvironment($user, $environment);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Environment $environment): bool
    {
        return $this->authService->canManageEnvironment($user, $environment);
    }

    /**
     * Determine whether the user can deploy to the environment.
     */
    public function deploy(User $user, Environment $environment): bool
    {
        return $this->authService->canDeploy($user, $environment);
    }

    /**
     * Determine whether the user can approve deployments for the environment.
     */
    public function approveDeployments(User $user, Environment $environment): bool
    {
        return $this->authService->canApproveDeployment($user, $environment);
    }
}
