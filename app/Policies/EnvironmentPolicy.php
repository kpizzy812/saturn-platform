<?php

namespace App\Policies;

use App\Models\Environment;
use App\Models\Project;
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
     * Checks both project access AND production environment visibility.
     */
    public function view(User $user, Environment $environment): bool
    {
        // First check basic project access
        if (! $this->authService->canViewProject($user, $environment->project)) {
            return false;
        }

        // Then check production environment visibility (developers cannot see production)
        return $this->authService->canViewProductionEnvironment($user, $environment);
    }

    /**
     * Determine whether the user can create models.
     * Only owner/admin can create environments.
     */
    public function create(User $user, ?Project $project = null): bool
    {
        if (! $project) {
            return false;
        }

        return $this->authService->canCreateEnvironment($user, $project);
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
