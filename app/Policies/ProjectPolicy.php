<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Services\Authorization\ProjectAuthorizationService;

class ProjectPolicy
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
    public function view(User $user, Project $project): bool
    {
        return $this->authService->canViewProject($user, $project);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Any authenticated team member can create projects
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Project $project): bool
    {
        return $this->authService->canManageProject($user, $project);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Project $project): bool
    {
        return $this->authService->canDeleteProject($user, $project);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Project $project): bool
    {
        return $this->authService->canDeleteProject($user, $project);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Project $project): bool
    {
        return $this->authService->canDeleteProject($user, $project);
    }

    /**
     * Determine whether the user can manage project members.
     */
    public function manageMembers(User $user, Project $project): bool
    {
        return $this->authService->canManageMembers($user, $project);
    }
}
