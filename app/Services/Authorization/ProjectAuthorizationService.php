<?php

namespace App\Services\Authorization;

use App\Models\Application;
use App\Models\Environment;
use App\Models\Project;
use App\Models\User;

/**
 * Service for handling project-level authorization.
 * Centralizes authorization logic for projects, environments, and deployments.
 *
 * This service integrates with PermissionService for permission-set-based
 * authorization while maintaining backward compatibility with role-based checks.
 */
class ProjectAuthorizationService
{
    private ?PermissionService $permissionService = null;

    /**
     * Get the PermissionService instance (lazy loaded).
     */
    private function getPermissionService(): PermissionService
    {
        if ($this->permissionService === null) {
            $this->permissionService = app(PermissionService::class);
        }

        return $this->permissionService;
    }

    /**
     * Check permission using the new Permission Sets system with fallback to legacy.
     */
    private function checkPermission(User $user, string $permissionKey, ?Project $project = null, ?Environment $environment = null): bool
    {
        return $this->getPermissionService()->userHasPermission($user, $permissionKey, $project, $environment);
    }

    /**
     * Check if a user can view a project.
     * User can view if they are:
     * - Platform admin/owner
     * - Direct project member
     * - Team member of the project's team WITH access to this project
     */
    public function canViewProject(User $user, Project $project): bool
    {
        // Platform admins can view everything
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        // Check direct project membership
        if ($user->projectMemberships()->where('project_id', $project->id)->exists()) {
            return true;
        }

        // Check team membership with project access restriction
        $teamMembership = $user->teams()->where('team_id', $project->team_id)->first();

        if (! $teamMembership) {
            return false;
        }

        // Owner/Admin always have full access to all projects
        if (in_array($teamMembership->pivot->role, ['owner', 'admin'])) {
            return true;
        }

        // Check allowed_projects restriction
        $allowedProjects = $teamMembership->pivot->allowed_projects;

        // null means all projects are allowed (default behavior)
        if ($allowedProjects === null) {
            return true;
        }

        // Check if this project is in the allowed list
        return in_array($project->id, $allowedProjects, true);
    }

    /**
     * Check if a user can manage (update settings) a project.
     * User can manage if they are:
     * - Platform admin/owner
     * - Project owner or admin
     * - Team owner or admin for the project's team
     */
    public function canManageProject(User $user, Project $project): bool
    {
        // Platform admins can manage everything
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        // Check project role
        $projectRole = $user->roleInProject($project);
        if (in_array($projectRole, ['owner', 'admin'])) {
            return true;
        }

        // Fallback to team role
        $teamMembership = $user->teams()->where('team_id', $project->team_id)->first();
        if ($teamMembership) {
            $teamRole = $teamMembership->pivot->role;

            return in_array($teamRole, ['owner', 'admin']);
        }

        return false;
    }

    /**
     * Check if a user can manage project members.
     * Same requirements as canManageProject.
     */
    public function canManageMembers(User $user, Project $project): bool
    {
        return $this->canManageProject($user, $project);
    }

    /**
     * Check if a user can delete a project.
     * Only project owner or platform owner can delete.
     */
    public function canDeleteProject(User $user, Project $project): bool
    {
        // Platform owner can delete anything
        if ($user->isPlatformOwner() || $user->isSuperAdmin()) {
            return true;
        }

        // Check if user is project owner
        $projectRole = $user->roleInProject($project);
        if ($projectRole === 'owner') {
            return true;
        }

        // Check if user is team owner
        $teamMembership = $user->teams()->where('team_id', $project->team_id)->first();
        if ($teamMembership && $teamMembership->pivot->role === 'owner') {
            return true;
        }

        return false;
    }

    /**
     * Check if a user can deploy to an environment.
     */
    public function canDeploy(User $user, Environment $environment): bool
    {
        return $user->canDeployToEnvironment($environment);
    }

    /**
     * Check if a user can deploy an application.
     */
    public function canDeployApplication(User $user, Application $application): bool
    {
        $environment = $application->environment;

        return $this->canDeploy($user, $environment);
    }

    /**
     * Check if a user's deployment requires approval for an environment.
     */
    public function requiresApproval(User $user, Environment $environment): bool
    {
        return $user->requiresApprovalForEnvironment($environment);
    }

    /**
     * Check if a user can approve deployments for an environment.
     * User can approve if they are:
     * - Platform admin/owner
     * - Project owner or admin
     * - Team owner or admin for the project's team
     */
    public function canApproveDeployment(User $user, Environment $environment): bool
    {
        // Platform admins can approve
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        $project = $environment->project;

        // Check project role
        $projectRole = $user->roleInProject($project);
        if (in_array($projectRole, ['owner', 'admin'])) {
            return true;
        }

        // Fallback to team role
        $teamMembership = $user->teams()->where('team_id', $project->team_id)->first();
        if ($teamMembership) {
            $teamRole = $teamMembership->pivot->role;

            return in_array($teamRole, ['owner', 'admin']);
        }

        return false;
    }

    /**
     * Check if a user can view an environment.
     */
    public function canViewEnvironment(User $user, Environment $environment): bool
    {
        return $this->canViewProject($user, $environment->project);
    }

    /**
     * Check if a user can manage (update settings) an environment.
     */
    public function canManageEnvironment(User $user, Environment $environment): bool
    {
        return $this->canManageProject($user, $environment->project);
    }

    /**
     * Check if user can view a production environment.
     * Developers and below cannot view production environments.
     */
    public function canViewProductionEnvironment(User $user, Environment $environment): bool
    {
        // Platform admins can view everything
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        // Non-production environments are visible to everyone with project access
        if (! $environment->isProduction()) {
            return true;
        }

        // Production environments require admin+ role
        return $this->hasMinimumRole($user, $environment->project, 'admin');
    }

    /**
     * Check if user can create environments.
     * Only owner/admin can create environments.
     */
    public function canCreateEnvironment(User $user, Project $project): bool
    {
        // Platform admins can create environments
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        return $this->hasMinimumRole($user, $project, 'admin');
    }

    /**
     * Filter environments visible to user (hide production from developers).
     *
     * @param  \Illuminate\Support\Collection  $environments
     * @return \Illuminate\Support\Collection
     */
    public function filterVisibleEnvironments(User $user, Project $project, $environments)
    {
        // Platform admins can see everything
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return $environments;
        }

        // Admins and above can see all environments
        if ($this->hasMinimumRole($user, $project, 'admin')) {
            return $environments;
        }

        // Developers and below cannot see production environments
        return $environments->filter(fn ($env) => ! $env->isProduction());
    }

    /**
     * Get user's effective role in a project.
     * First checks project_user, then falls back to team_user.
     */
    public function getUserProjectRole(User $user, Project $project): ?string
    {
        // Check direct project membership first
        $projectRole = $user->roleInProject($project);
        if ($projectRole) {
            return $projectRole;
        }

        // Fallback to team role
        $teamMembership = $user->teams()->where('team_id', $project->team_id)->first();
        if ($teamMembership) {
            return $teamMembership->pivot->role;
        }

        return null;
    }

    /**
     * Check if user has at least the specified role level in a project.
     * Role hierarchy: owner > admin > developer > member > viewer
     */
    public function hasMinimumRole(User $user, Project $project, string $minimumRole): bool
    {
        $hierarchy = [
            'viewer' => 1,
            'member' => 2,
            'developer' => 3,
            'admin' => 4,
            'owner' => 5,
        ];

        $userRole = $this->getUserProjectRole($user, $project);
        if (! $userRole) {
            return false;
        }

        $userLevel = $hierarchy[$userRole] ?? 0;
        $requiredLevel = $hierarchy[$minimumRole] ?? 0;

        return $userLevel >= $requiredLevel;
    }
}
