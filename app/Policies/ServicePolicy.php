<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;
use App\Services\Authorization\ProjectAuthorizationService;

class ServicePolicy
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
     * Requires: team membership + environment visibility check
     */
    public function view(User $user, Service $service): bool
    {
        $environment = $service->environment;
        if (! $environment) {
            return false;
        }

        // Check if user can view the environment (production hidden from developers)
        return $this->authService->canViewProductionEnvironment($user, $environment);
    }

    /**
     * Determine whether the user can create models.
     * Requires: developer+ role
     */
    public function create(User $user): bool
    {
        $team = currentTeam();
        if (! $team) {
            return false;
        }

        // Check if user is at least a developer in the team
        $teamMembership = $user->teams()->where('team_id', $team->id)->first();
        if (! $teamMembership) {
            return false;
        }

        $role = $teamMembership->pivot->role;

        return in_array($role, ['owner', 'admin', 'developer']);
    }

    /**
     * Determine whether the user can update the model.
     * Requires: developer+ role in project
     */
    public function update(User $user, Service $service): bool
    {
        $environment = $service->environment;
        if (! $environment) {
            return false;
        }

        $project = $environment->project;
        if (! $project) {
            return false;
        }

        // Developers and above can update services
        return $this->authService->hasMinimumRole($user, $project, 'developer');
    }

    /**
     * Determine whether the user can delete the model.
     * Requires: admin+ role (critical operation)
     */
    public function delete(User $user, Service $service): bool
    {
        $environment = $service->environment;
        if (! $environment) {
            return false;
        }

        $project = $environment->project;
        if (! $project) {
            return false;
        }

        // Only admins and above can delete services
        return $this->authService->hasMinimumRole($user, $project, 'admin');
    }

    /**
     * Determine whether the user can restore the model.
     * Requires: admin+ role
     */
    public function restore(User $user, Service $service): bool
    {
        return $this->delete($user, $service);
    }

    /**
     * Determine whether the user can permanently delete the model.
     * Requires: admin+ role
     */
    public function forceDelete(User $user, Service $service): bool
    {
        return $this->delete($user, $service);
    }

    /**
     * Determine whether the user can stop the service.
     * Requires: developer+ role
     */
    public function stop(User $user, Service $service): bool
    {
        return $this->update($user, $service);
    }

    /**
     * Determine whether the user can manage environment variables.
     * Requires: developer+ role
     */
    public function manageEnvironment(User $user, Service $service): bool
    {
        return $this->update($user, $service);
    }

    /**
     * Determine whether the user can view sensitive environment variables.
     * Requires: admin+ role
     */
    public function viewSensitiveEnvironment(User $user, Service $service): bool
    {
        $environment = $service->environment;
        if (! $environment) {
            return false;
        }

        $project = $environment->project;
        if (! $project) {
            return false;
        }

        // Only admins and above can view sensitive data
        return $this->authService->hasMinimumRole($user, $project, 'admin');
    }

    /**
     * Determine whether the user can deploy the service.
     * Requires: deploy permission in environment
     */
    public function deploy(User $user, Service $service): bool
    {
        $environment = $service->environment;
        if (! $environment) {
            return false;
        }

        return $this->authService->canDeploy($user, $environment);
    }

    /**
     * Determine whether the user can access terminal.
     * Requires: admin+ role (security sensitive)
     */
    public function accessTerminal(User $user, Service $service): bool
    {
        $environment = $service->environment;
        if (! $environment) {
            return false;
        }

        $project = $environment->project;
        if (! $project) {
            return false;
        }

        // Only admins and above can access terminal (security sensitive)
        return $this->authService->hasMinimumRole($user, $project, 'admin');
    }
}
