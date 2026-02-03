<?php

namespace App\Services\Authorization;

use App\Models\Environment;
use App\Models\Permission;
use App\Models\PermissionSet;
use App\Models\PermissionSetUser;
use App\Models\Project;
use App\Models\Team;
use App\Models\TeamUser;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Service for checking user permissions using the Permission Sets system.
 * Provides backward compatibility with role-based authorization.
 */
class PermissionService
{
    /**
     * Cache TTL in seconds (5 minutes).
     */
    private const CACHE_TTL = 300;

    /**
     * Role to permission mappings for backward compatibility.
     * Maps legacy roles to permission sets.
     */
    private const ROLE_PERMISSION_MAP = [
        'owner' => 'owner',
        'admin' => 'admin',
        'developer' => 'developer',
        'member' => 'member',
        'viewer' => 'viewer',
    ];

    /**
     * Check if a user has a specific permission.
     *
     * @param  User  $user  The user to check
     * @param  string  $permissionKey  The permission key (e.g., 'applications.deploy')
     * @param  Project|null  $project  Optional project context for project-level permissions
     * @param  Environment|null  $environment  Optional environment for environment-specific checks
     */
    public function userHasPermission(
        User $user,
        string $permissionKey,
        ?Project $project = null,
        ?Environment $environment = null
    ): bool {
        // Platform admins and superadmins have all permissions
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        // Get the team context
        $team = $project?->team ?? currentTeam();

        if (! $team) {
            return false;
        }

        // Get environment name for restriction checks
        $environmentName = $environment?->name;

        // Check project-level permission set first (if project provided)
        if ($project) {
            $projectPermission = $this->checkProjectPermissionSet($user, $project, $permissionKey, $environmentName);
            if ($projectPermission !== null) {
                return $projectPermission;
            }
        }

        // Check team-level permission set
        return $this->checkTeamPermission($user, $team, $permissionKey, $environmentName);
    }

    /**
     * Check permission at the project level.
     *
     * @return bool|null null if no project permission set, bool if explicitly granted/denied
     */
    private function checkProjectPermissionSet(
        User $user,
        Project $project,
        string $permissionKey,
        ?string $environmentName = null
    ): ?bool {
        // Check if user has a project-level permission set assignment
        $assignment = PermissionSetUser::query()
            ->where('user_id', $user->id)
            ->where('scope_type', 'project')
            ->where('scope_id', $project->id)
            ->with('permissionSet.permissions')
            ->first();

        if (! $assignment) {
            return null;
        }

        return $this->checkPermissionInAssignment($assignment, $permissionKey, $environmentName);
    }

    /**
     * Check permission at the team level.
     */
    private function checkTeamPermission(
        User $user,
        Team $team,
        string $permissionKey,
        ?string $environmentName = null
    ): bool {
        $cacheKey = $this->getCacheKey($user, $team, $permissionKey, $environmentName);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user, $team, $permissionKey, $environmentName) {
            // Get team membership
            $teamMembership = $user->teamMembership($team->id);

            if (! $teamMembership) {
                return false;
            }

            // Check if using permission set
            if ($teamMembership->usesPermissionSet()) {
                $permissionSet = $teamMembership->permissionSet;
                if ($permissionSet) {
                    return $permissionSet->hasPermission($permissionKey, $environmentName);
                }
            }

            // Fallback to role-based permission
            return $this->checkRoleBasedPermission($teamMembership->role, $team->id, $permissionKey);
        });
    }

    /**
     * Check permission within a permission set assignment.
     */
    private function checkPermissionInAssignment(
        PermissionSetUser $assignment,
        string $permissionKey,
        ?string $environmentName = null
    ): bool {
        $permissionSet = $assignment->permissionSet;

        if (! $permissionSet) {
            return false;
        }

        // First check if the set has this permission
        if (! $permissionSet->hasPermission($permissionKey, $environmentName)) {
            return false;
        }

        // Then check user-level environment overrides
        if ($environmentName !== null && ! empty($assignment->environment_overrides)) {
            if (isset($assignment->environment_overrides[$environmentName]) &&
                $assignment->environment_overrides[$environmentName] === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Fallback: Check permission using legacy role system.
     * Maps roles to system permission sets and checks permissions there.
     */
    private function checkRoleBasedPermission(string $role, int $teamId, string $permissionKey): bool
    {
        $permissionSetSlug = self::ROLE_PERMISSION_MAP[$role] ?? 'viewer';

        // Get the system permission set for this role
        $permissionSet = PermissionSet::getSystemSetForTeam($teamId, $permissionSetSlug);

        if (! $permissionSet) {
            // If no system permission set exists, use hardcoded logic
            return $this->getHardcodedRolePermission($role, $permissionKey);
        }

        return $permissionSet->hasPermission($permissionKey);
    }

    /**
     * Hardcoded permission checks for backward compatibility when no permission sets exist.
     */
    private function getHardcodedRolePermission(string $role, string $permissionKey): bool
    {
        $roleRank = match ($role) {
            'owner' => 5,
            'admin' => 4,
            'developer' => 3,
            'member' => 2,
            'viewer' => 1,
            default => 0,
        };

        // Parse permission key
        [$resource, $action] = explode('.', $permissionKey, 2) + [null, null];

        // Basic permission requirements
        $actionRequirements = [
            'view' => 1,     // Viewer and above
            'logs' => 1,     // Viewer and above
            'deploy' => 2,   // Member and above
            'manage' => 2,   // Member and above
            'create' => 3,   // Developer and above
            'update' => 3,   // Developer and above
            'delete' => 4,   // Admin and above
            'invite' => 4,   // Admin and above
            'manage_members' => 4, // Admin and above
            'manage_roles' => 5,   // Owner only
            'security' => 4,       // Admin and above
            'tokens' => 4,         // Admin and above
            'billing' => 5,        // Owner only
            'env_vars' => 3,       // Developer and above
            'env_vars_sensitive' => 4, // Admin and above
        ];

        $requiredRank = $actionRequirements[$action] ?? 3;

        return $roleRank >= $requiredRank;
    }

    /**
     * Get all effective permissions for a user.
     *
     * @return array<string, bool> Map of permission key to granted status
     */
    public function getUserEffectivePermissions(User $user, ?Project $project = null): array
    {
        $team = $project?->team ?? currentTeam();

        if (! $team) {
            return [];
        }

        // Platform admins have all permissions
        if ($user->isPlatformAdmin() || $user->isSuperAdmin()) {
            return Permission::all()
                ->pluck('key')
                ->mapWithKeys(fn ($key) => [$key => true])
                ->toArray();
        }

        $permissions = [];
        $allPermissions = Permission::all();

        foreach ($allPermissions as $permission) {
            $permissions[$permission->key] = $this->userHasPermission($user, $permission->key, $project);
        }

        return $permissions;
    }

    /**
     * Get all effective permissions grouped by category.
     *
     * @return array<string, array<string, bool>> Map of category to permission key to granted status
     */
    public function getUserEffectivePermissionsGrouped(User $user, ?Project $project = null): array
    {
        $team = $project?->team ?? currentTeam();

        if (! $team) {
            return [];
        }

        $permissions = [];
        $allPermissions = Permission::orderBy('sort_order')->get();

        foreach ($allPermissions as $permission) {
            $category = $permission->category;
            if (! isset($permissions[$category])) {
                $permissions[$category] = [];
            }

            $permissions[$category][$permission->key] = [
                'id' => $permission->id,
                'name' => $permission->name,
                'description' => $permission->description,
                'granted' => $this->userHasPermission($user, $permission->key, $project),
            ];
        }

        return $permissions;
    }

    /**
     * Get the user's permission set for a given scope.
     */
    public function getUserPermissionSet(User $user, ?Project $project = null): ?PermissionSet
    {
        // Check project-level assignment first
        if ($project) {
            $projectAssignment = PermissionSetUser::query()
                ->where('user_id', $user->id)
                ->where('scope_type', 'project')
                ->where('scope_id', $project->id)
                ->with('permissionSet')
                ->first();

            if ($projectAssignment) {
                return $projectAssignment->permissionSet;
            }
        }

        // Check team-level assignment
        $team = $project?->team ?? currentTeam();

        if (! $team) {
            return null;
        }

        $teamMembership = $user->teamMembership($team->id);

        if ($teamMembership && $teamMembership->usesPermissionSet()) {
            return $teamMembership->permissionSet;
        }

        // Return system permission set based on role
        if ($teamMembership) {
            $slug = self::ROLE_PERMISSION_MAP[$teamMembership->role] ?? 'viewer';

            return PermissionSet::getSystemSetForTeam($team->id, $slug);
        }

        return null;
    }

    /**
     * Clear cached permissions for a user.
     */
    public function clearUserCache(User $user, ?Team $team = null): void
    {
        if ($team) {
            // Clear specific team cache
            $pattern = "user_permissions:{$user->id}:{$team->id}:*";
            $this->clearCacheByPattern($pattern);
        } else {
            // Clear all user cache
            $pattern = "user_permissions:{$user->id}:*";
            $this->clearCacheByPattern($pattern);
        }
    }

    /**
     * Clear cached permissions for all users in a team.
     */
    public function clearTeamCache(Team $team): void
    {
        $pattern = "user_permissions:*:{$team->id}:*";
        $this->clearCacheByPattern($pattern);
    }

    /**
     * Clear cache by pattern.
     */
    private function clearCacheByPattern(string $pattern): void
    {
        // Note: For Redis, this works well. For file-based cache, tags might be better.
        // This implementation assumes Redis or similar cache driver.
        $redis = Cache::getStore();

        if (method_exists($redis, 'connection')) {
            $connection = $redis->connection();
            $keys = $connection->keys(config('cache.prefix', 'laravel_cache').'_'.$pattern);
            foreach ($keys as $key) {
                $connection->del($key);
            }
        } else {
            // Fallback: flush specific keys if we know them
            // In production, consider using cache tags
        }
    }

    /**
     * Generate cache key for permission check.
     */
    private function getCacheKey(
        User $user,
        Team $team,
        string $permissionKey,
        ?string $environmentName = null
    ): string {
        $key = "user_permissions:{$user->id}:{$team->id}:{$permissionKey}";

        if ($environmentName) {
            $key .= ":{$environmentName}";
        }

        return $key;
    }

    /**
     * Assign a permission set to a user for a specific scope.
     */
    public function assignPermissionSet(
        User $user,
        PermissionSet $permissionSet,
        string $scopeType,
        int $scopeId,
        ?array $environmentOverrides = null
    ): PermissionSetUser {
        // Remove existing assignment for this scope
        PermissionSetUser::query()
            ->where('user_id', $user->id)
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->delete();

        // Create new assignment
        $assignment = PermissionSetUser::create([
            'permission_set_id' => $permissionSet->id,
            'user_id' => $user->id,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'environment_overrides' => $environmentOverrides,
        ]);

        // Also update team_user or project_user table for quick reference
        if ($scopeType === 'team') {
            TeamUser::query()
                ->where('team_id', $scopeId)
                ->where('user_id', $user->id)
                ->update(['permission_set_id' => $permissionSet->id]);
        }

        // Clear cache
        $this->clearUserCache($user);

        return $assignment;
    }

    /**
     * Remove permission set assignment from a user.
     */
    public function removePermissionSetAssignment(User $user, string $scopeType, int $scopeId): void
    {
        PermissionSetUser::query()
            ->where('user_id', $user->id)
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->delete();

        // Also clear from team_user or project_user
        if ($scopeType === 'team') {
            TeamUser::query()
                ->where('team_id', $scopeId)
                ->where('user_id', $user->id)
                ->update(['permission_set_id' => null]);
        }

        // Clear cache
        $this->clearUserCache($user);
    }

    /**
     * Check multiple permissions at once.
     *
     * @param  array<string>  $permissionKeys
     * @return array<string, bool>
     */
    public function checkMultiplePermissions(
        User $user,
        array $permissionKeys,
        ?Project $project = null,
        ?Environment $environment = null
    ): array {
        $results = [];

        foreach ($permissionKeys as $key) {
            $results[$key] = $this->userHasPermission($user, $key, $project, $environment);
        }

        return $results;
    }

    /**
     * Check if user can perform any of the given permissions.
     */
    public function userHasAnyPermission(
        User $user,
        array $permissionKeys,
        ?Project $project = null,
        ?Environment $environment = null
    ): bool {
        foreach ($permissionKeys as $key) {
            if ($this->userHasPermission($user, $key, $project, $environment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has all of the given permissions.
     */
    public function userHasAllPermissions(
        User $user,
        array $permissionKeys,
        ?Project $project = null,
        ?Environment $environment = null
    ): bool {
        foreach ($permissionKeys as $key) {
            if (! $this->userHasPermission($user, $key, $project, $environment)) {
                return false;
            }
        }

        return true;
    }
}
