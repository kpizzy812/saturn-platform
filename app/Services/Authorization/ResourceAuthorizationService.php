<?php

namespace App\Services\Authorization;

use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Service for handling team-level resource authorization.
 * Centralizes authorization logic for servers, databases, and other team resources.
 *
 * Role hierarchy for team resources:
 * - owner: Full access (create, update, delete, manage)
 * - admin: Most operations (create, update, manage) but not delete critical resources
 * - developer: Read access, limited operations
 * - member: Read-only access
 * - viewer: Read-only access
 */
class ResourceAuthorizationService
{
    /**
     * Role hierarchy levels for team resources.
     */
    private const ROLE_HIERARCHY = [
        'viewer' => 1,
        'member' => 2,
        'developer' => 3,
        'admin' => 4,
        'owner' => 5,
    ];

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
     * Check if user has at least the specified role level in a team.
     */
    public function hasMinimumTeamRole(User $user, int $teamId, string $minimumRole): bool
    {
        // Platform admins bypass all checks
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        $userRole = $this->getUserTeamRole($user, $teamId);
        if (! $userRole) {
            return false;
        }

        $userLevel = self::ROLE_HIERARCHY[$userRole] ?? 0;
        $requiredLevel = self::ROLE_HIERARCHY[$minimumRole] ?? 0;

        return $userLevel >= $requiredLevel;
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
     * Requires: team membership
     */
    public function canViewServer(User $user, Server $server): bool
    {
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        return $this->userBelongsToResourceTeam($user, $server);
    }

    /**
     * Check if user can create servers.
     * Requires: admin+ role in current team
     */
    public function canCreateServer(User $user, int $teamId): bool
    {
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        return $this->hasMinimumTeamRole($user, $teamId, 'admin');
    }

    /**
     * Check if user can update a server.
     * Requires: admin+ role in server's team
     */
    public function canUpdateServer(User $user, Server $server): bool
    {
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        if (! $this->userBelongsToResourceTeam($user, $server)) {
            return false;
        }

        return $this->hasMinimumTeamRole($user, $server->team_id, 'admin');
    }

    /**
     * Check if user can delete a server.
     * Requires: owner role in server's team (critical operation)
     */
    public function canDeleteServer(User $user, Server $server): bool
    {
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        if (! $this->userBelongsToResourceTeam($user, $server)) {
            return false;
        }

        return $this->hasMinimumTeamRole($user, $server->team_id, 'owner');
    }

    /**
     * Check if user can manage server proxy (start/stop/restart).
     * Requires: admin+ role
     */
    public function canManageServerProxy(User $user, Server $server): bool
    {
        return $this->canUpdateServer($user, $server);
    }

    /**
     * Check if user can manage server sentinel.
     * Requires: admin+ role
     */
    public function canManageServerSentinel(User $user, Server $server): bool
    {
        return $this->canUpdateServer($user, $server);
    }

    /**
     * Check if user can manage server CA certificates.
     * Requires: admin+ role
     */
    public function canManageServerCaCertificate(User $user, Server $server): bool
    {
        return $this->canUpdateServer($user, $server);
    }

    /**
     * Check if user can view server security settings.
     * Requires: admin+ role (contains sensitive information)
     */
    public function canViewServerSecurity(User $user, Server $server): bool
    {
        return $this->canUpdateServer($user, $server);
    }

    // ==========================================
    // DATABASE AUTHORIZATION
    // ==========================================

    /**
     * Check if user can view a database.
     * Requires: team membership
     */
    public function canViewDatabase(User $user, Model $database): bool
    {
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        return $this->userBelongsToResourceTeam($user, $database);
    }

    /**
     * Check if user can view database credentials (connection strings, passwords).
     * Requires: admin+ role (sensitive data)
     */
    public function canViewDatabaseCredentials(User $user, Model $database): bool
    {
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        $teamId = $this->getResourceTeamId($database);
        if ($teamId === null) {
            return false;
        }

        if (! $this->userBelongsToResourceTeam($user, $database)) {
            return false;
        }

        return $this->hasMinimumTeamRole($user, $teamId, 'admin');
    }

    /**
     * Check if user can create databases.
     * Requires: admin+ role
     */
    public function canCreateDatabase(User $user, int $teamId): bool
    {
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        return $this->hasMinimumTeamRole($user, $teamId, 'admin');
    }

    /**
     * Check if user can update a database.
     * Requires: admin+ role
     */
    public function canUpdateDatabase(User $user, Model $database): bool
    {
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        $teamId = $this->getResourceTeamId($database);
        if ($teamId === null) {
            return false;
        }

        if (! $this->userBelongsToResourceTeam($user, $database)) {
            return false;
        }

        return $this->hasMinimumTeamRole($user, $teamId, 'admin');
    }

    /**
     * Check if user can delete a database.
     * Requires: owner role (critical operation - data loss)
     */
    public function canDeleteDatabase(User $user, Model $database): bool
    {
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        $teamId = $this->getResourceTeamId($database);
        if ($teamId === null) {
            return false;
        }

        if (! $this->userBelongsToResourceTeam($user, $database)) {
            return false;
        }

        return $this->hasMinimumTeamRole($user, $teamId, 'owner');
    }

    /**
     * Check if user can manage a database (start/stop).
     * Requires: admin+ role
     */
    public function canManageDatabase(User $user, Model $database): bool
    {
        return $this->canUpdateDatabase($user, $database);
    }

    /**
     * Check if user can manage database backups.
     * Requires: admin+ role
     */
    public function canManageDatabaseBackups(User $user, Model $database): bool
    {
        return $this->canUpdateDatabase($user, $database);
    }

    /**
     * Check if user can manage database environment variables.
     * Requires: admin+ role
     */
    public function canManageDatabaseEnvironment(User $user, Model $database): bool
    {
        return $this->canUpdateDatabase($user, $database);
    }

    // ==========================================
    // SENSITIVE DATA ACCESS
    // ==========================================

    /**
     * Check if user can access sensitive data (env vars, credentials, secrets).
     * Requires: admin+ role in the team
     */
    public function canAccessSensitiveData(User $user, int $teamId): bool
    {
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        return $this->hasMinimumTeamRole($user, $teamId, 'admin');
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
