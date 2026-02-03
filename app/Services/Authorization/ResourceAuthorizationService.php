<?php

namespace App\Services\Authorization;

use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Service for handling team-level resource authorization.
 * Centralizes authorization logic for servers, databases, and other team resources.
 *
 * This service integrates with PermissionService for granular permission control
 * via Permission Sets, while maintaining backward compatibility with role-based checks.
 */
class ResourceAuthorizationService
{
    public function __construct(
        protected PermissionService $permissionService
    ) {}

    /**
     * Get user's role in a team.
     */
    public function getUserTeamRole(User $user, int $teamId): ?string
    {
        $teamMembership = $user->teams()->where('team_id', $teamId)->first();
        if (! $teamMembership) {
            return null;
        }

        return $teamMembership->pivot->role;
    }

    /**
     * Check if user has a specific permission.
     * Uses PermissionService which respects Permission Sets configuration.
     *
     * Note: teamId is accepted for context but PermissionService
     * determines the team from currentTeam() or the permission context.
     */
    public function hasPermission(User $user, string $permissionKey, ?int $teamId = null): bool
    {
        // Platform admins bypass all checks
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        // PermissionService handles team context resolution internally
        return $this->permissionService->userHasPermission($user, $permissionKey);
    }

    /**
     * Check if user belongs to the team that owns the resource.
     */
    public function userBelongsToResourceTeam(User $user, Model $resource): bool
    {
        $teamId = $this->getResourceTeamId($resource);
        if ($teamId === null) {
            return false;
        }

        return $user->teams->contains('id', $teamId);
    }

    /**
     * Get the team ID for a resource.
     */
    private function getResourceTeamId(Model $resource): ?int
    {
        // Direct team_id attribute
        if (isset($resource->team_id)) {
            return $resource->team_id;
        }

        // Through team() relationship
        if (method_exists($resource, 'team')) {
            $team = $resource->team()->first();

            return $team?->id;
        }

        // Through environment->project->team chain (for databases)
        if (method_exists($resource, 'environment')) {
            $environment = $resource->environment;
            if ($environment && $environment->project) {
                return $environment->project->team_id;
            }
        }

        return null;
    }

    // ==========================================
    // SERVER AUTHORIZATION
    // ==========================================

    /**
     * Check if user can view a server.
     * Permission: servers.view
     */
    public function canViewServer(User $user, Server $server): bool
    {
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        if (! $this->userBelongsToResourceTeam($user, $server)) {
            return false;
        }

        return $this->hasPermission($user, 'servers.view', $server->team_id);
    }

    /**
     * Check if user can create servers.
     * Permission: servers.create
     */
    public function canCreateServer(User $user, int $teamId): bool
    {
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        return $this->hasPermission($user, 'servers.create', $teamId);
    }

    /**
     * Check if user can update a server.
     * Permission: servers.update
     */
    public function canUpdateServer(User $user, Server $server): bool
    {
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        if (! $this->userBelongsToResourceTeam($user, $server)) {
            return false;
        }

        return $this->hasPermission($user, 'servers.update', $server->team_id);
    }

    /**
     * Check if user can delete a server.
     * Permission: servers.delete (critical operation)
     */
    public function canDeleteServer(User $user, Server $server): bool
    {
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        if (! $this->userBelongsToResourceTeam($user, $server)) {
            return false;
        }

        return $this->hasPermission($user, 'servers.delete', $server->team_id);
    }

    /**
     * Check if user can manage server proxy (start/stop/restart).
     * Permission: servers.proxy
     */
    public function canManageServerProxy(User $user, Server $server): bool
    {
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        if (! $this->userBelongsToResourceTeam($user, $server)) {
            return false;
        }

        return $this->hasPermission($user, 'servers.proxy', $server->team_id);
    }

    /**
     * Check if user can manage server sentinel.
     * Permission: servers.update
     */
    public function canManageServerSentinel(User $user, Server $server): bool
    {
        return $this->canUpdateServer($user, $server);
    }

    /**
     * Check if user can manage server CA certificates.
     * Permission: servers.update
     */
    public function canManageServerCaCertificate(User $user, Server $server): bool
    {
        return $this->canUpdateServer($user, $server);
    }

    /**
     * Check if user can view server security settings.
     * Permission: servers.security (sensitive)
     */
    public function canViewServerSecurity(User $user, Server $server): bool
    {
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        if (! $this->userBelongsToResourceTeam($user, $server)) {
            return false;
        }

        return $this->hasPermission($user, 'servers.security', $server->team_id);
    }

    // ==========================================
    // DATABASE AUTHORIZATION
    // ==========================================

    /**
     * Check if user can view a database.
     * Permission: databases.view
     */
    public function canViewDatabase(User $user, Model $database): bool
    {
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        $teamId = $this->getResourceTeamId($database);
        if ($teamId === null || ! $this->userBelongsToResourceTeam($user, $database)) {
            return false;
        }

        return $this->hasPermission($user, 'databases.view', $teamId);
    }

    /**
     * Check if user can view database credentials (connection strings, passwords).
     * Permission: databases.credentials (sensitive)
     */
    public function canViewDatabaseCredentials(User $user, Model $database): bool
    {
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        $teamId = $this->getResourceTeamId($database);
        if ($teamId === null || ! $this->userBelongsToResourceTeam($user, $database)) {
            return false;
        }

        return $this->hasPermission($user, 'databases.credentials', $teamId);
    }

    /**
     * Check if user can create databases.
     * Permission: databases.create
     */
    public function canCreateDatabase(User $user, int $teamId): bool
    {
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        return $this->hasPermission($user, 'databases.create', $teamId);
    }

    /**
     * Check if user can update a database.
     * Permission: databases.update
     */
    public function canUpdateDatabase(User $user, Model $database): bool
    {
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        $teamId = $this->getResourceTeamId($database);
        if ($teamId === null || ! $this->userBelongsToResourceTeam($user, $database)) {
            return false;
        }

        return $this->hasPermission($user, 'databases.update', $teamId);
    }

    /**
     * Check if user can delete a database.
     * Permission: databases.delete (critical operation - data loss)
     */
    public function canDeleteDatabase(User $user, Model $database): bool
    {
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        $teamId = $this->getResourceTeamId($database);
        if ($teamId === null || ! $this->userBelongsToResourceTeam($user, $database)) {
            return false;
        }

        return $this->hasPermission($user, 'databases.delete', $teamId);
    }

    /**
     * Check if user can manage a database (start/stop).
     * Permission: databases.manage
     */
    public function canManageDatabase(User $user, Model $database): bool
    {
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        $teamId = $this->getResourceTeamId($database);
        if ($teamId === null || ! $this->userBelongsToResourceTeam($user, $database)) {
            return false;
        }

        return $this->hasPermission($user, 'databases.manage', $teamId);
    }

    /**
     * Check if user can manage database backups.
     * Permission: databases.backups
     */
    public function canManageDatabaseBackups(User $user, Model $database): bool
    {
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        $teamId = $this->getResourceTeamId($database);
        if ($teamId === null || ! $this->userBelongsToResourceTeam($user, $database)) {
            return false;
        }

        return $this->hasPermission($user, 'databases.backups', $teamId);
    }

    /**
     * Check if user can manage database environment variables.
     * Permission: databases.env_vars
     */
    public function canManageDatabaseEnvironment(User $user, Model $database): bool
    {
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        $teamId = $this->getResourceTeamId($database);
        if ($teamId === null || ! $this->userBelongsToResourceTeam($user, $database)) {
            return false;
        }

        return $this->hasPermission($user, 'databases.env_vars', $teamId);
    }

    // ==========================================
    // SENSITIVE DATA ACCESS
    // ==========================================

    /**
     * Check if user can access sensitive data (env vars, credentials, secrets).
     * Permission: applications.env_vars_sensitive
     */
    public function canAccessSensitiveData(User $user, int $teamId): bool
    {
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        return $this->hasPermission($user, 'applications.env_vars_sensitive', $teamId);
    }

    /**
     * Check if user can access sensitive data for a resource.
     */
    public function canAccessResourceSensitiveData(User $user, Model $resource): bool
    {
        $teamId = $this->getResourceTeamId($resource);
        if ($teamId === null) {
            return false;
        }

        return $this->canAccessSensitiveData($user, $teamId);
    }
}
