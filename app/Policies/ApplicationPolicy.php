<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;
use App\Services\Authorization\ProjectAuthorizationService;
use Illuminate\Auth\Access\Response;

class ApplicationPolicy
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
    public function view(User $user, Application $application): bool
    {
        $environment = $application->environment;
        if (! $environment) {
            // Orphaned application (no environment) — restrict to admins only
            return $user->isPlatformAdmin() || $user->isSuperAdmin();
        }

        return $this->authService->canViewEnvironment($user, $environment);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Any authenticated team member can create applications
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Application $application): Response
    {
        $environment = $application->environment;
        if (! $environment) {
            // Orphaned application — restrict to admins only
            return ($user->isPlatformAdmin() || $user->isSuperAdmin())
                ? Response::allow()
                : Response::deny('You do not have permission to update this application.');
        }

        $project = $environment->project;
        if ($this->authService->canManageProject($user, $project)) {
            return Response::allow();
        }

        // Developers can update applications but not project settings
        if ($this->authService->hasMinimumRole($user, $project, 'developer')) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to update this application. You need at least developer permissions.');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Application $application): bool
    {
        $environment = $application->environment;
        if (! $environment) {
            return $user->isPlatformAdmin() || $user->isSuperAdmin();
        }

        return $this->authService->canManageProject($user, $environment->project);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Application $application): bool
    {
        $environment = $application->environment;
        if (! $environment) {
            return $user->isPlatformAdmin() || $user->isSuperAdmin();
        }

        return $this->authService->canManageProject($user, $environment->project);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Application $application): bool
    {
        $environment = $application->environment;
        if (! $environment) {
            return $user->isPlatformAdmin() || $user->isSuperAdmin();
        }

        return $this->authService->canManageProject($user, $environment->project);
    }

    /**
     * Determine whether the user can deploy the application.
     */
    public function deploy(User $user, Application $application): bool
    {
        $environment = $application->environment;
        if (! $environment) {
            return $user->isPlatformAdmin() || $user->isSuperAdmin();
        }

        return $this->authService->canDeployApplication($user, $application);
    }

    /**
     * Determine whether the user can manage deployments.
     */
    public function manageDeployments(User $user, Application $application): bool
    {
        $environment = $application->environment;
        if (! $environment) {
            return $user->isPlatformAdmin() || $user->isSuperAdmin();
        }

        return $this->authService->canManageProject($user, $environment->project);
    }

    /**
     * Determine whether the user can manage environment variables.
     */
    public function manageEnvironment(User $user, Application $application): bool
    {
        $environment = $application->environment;
        if (! $environment) {
            return $user->isPlatformAdmin() || $user->isSuperAdmin();
        }

        // Developers and above can manage env vars
        return $this->authService->hasMinimumRole($user, $environment->project, 'developer');
    }

    /**
     * Determine whether the user can view sensitive environment variables (values, secrets).
     * Requires: admin+ role
     */
    public function viewSensitiveEnvironment(User $user, Application $application): bool
    {
        $environment = $application->environment;
        if (! $environment) {
            return $user->isPlatformAdmin() || $user->isSuperAdmin();
        }

        // Only admins and above can view sensitive data
        return $this->authService->hasMinimumRole($user, $environment->project, 'admin');
    }

    /**
     * Determine whether the user can cleanup deployment queue.
     */
    public function cleanupDeploymentQueue(User $user): bool
    {
        return $user->isPlatformAdmin() || $user->isSuperAdmin();
    }
}
