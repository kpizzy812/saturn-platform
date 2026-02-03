<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot model for team_user relationship.
 *
 * @property int $id
 * @property int $team_id
 * @property int $user_id
 * @property string $role
 * @property array<int>|null $allowed_projects
 * @property int|null $permission_set_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read PermissionSet|null $permissionSet
 * @property-read Team $team
 * @property-read User $user
 */
class TeamUser extends Pivot
{
    protected $table = 'team_user';

    public $incrementing = true;

    protected $casts = [
        'allowed_projects' => 'array',
        'permission_set_id' => 'integer',
    ];

    protected $fillable = [
        'team_id',
        'user_id',
        'role',
        'allowed_projects',
        'permission_set_id',
    ];

    /**
     * Check if user has access to all projects.
     * null = all projects (default), '*' in array = also full access.
     */
    public function hasFullProjectAccess(): bool
    {
        return $this->allowed_projects === null ||
               (is_array($this->allowed_projects) && in_array('*', $this->allowed_projects, true));
    }

    /**
     * Check if user has no access to any projects.
     * Empty array [] means no access.
     */
    public function hasNoProjectAccess(): bool
    {
        return is_array($this->allowed_projects) && empty($this->allowed_projects);
    }

    /**
     * Check if user can access a specific project.
     * null = all projects (allow-by-default for existing members).
     */
    public function canAccessProject(int $projectId): bool
    {
        // null means access to all projects
        if ($this->allowed_projects === null) {
            return true;
        }

        // Empty array means no access
        if (empty($this->allowed_projects)) {
            return false;
        }

        // '*' means access to all projects
        if (in_array('*', $this->allowed_projects, true)) {
            return true;
        }

        // Check if specific project ID is in the allowed list
        return in_array($projectId, $this->allowed_projects, true);
    }

    /**
     * Get the permission set assigned to this team membership.
     */
    public function permissionSet(): BelongsTo
    {
        return $this->belongsTo(PermissionSet::class);
    }

    /**
     * Get the team.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if this membership uses permission set based authorization.
     */
    public function usesPermissionSet(): bool
    {
        return $this->permission_set_id !== null;
    }

    /**
     * Check if user has a specific permission through this team membership.
     * Falls back to role-based check if no permission set is assigned.
     */
    public function hasPermission(string $permissionKey, ?string $environment = null): bool
    {
        if ($this->usesPermissionSet() && $this->permissionSet) {
            return $this->permissionSet->hasPermission($permissionKey, $environment);
        }

        // Fallback to role-based permission (handled by PermissionService)
        return false;
    }
}
