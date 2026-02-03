<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * PermissionSetUser pivot model - represents user assignment to a permission set.
 *
 * @property int $id
 * @property int $permission_set_id
 * @property int $user_id
 * @property string $scope_type 'team' or 'project'
 * @property int $scope_id team_id or project_id
 * @property array|null $environment_overrides Per-user environment restrictions
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read PermissionSet $permissionSet
 * @property-read User $user
 */
class PermissionSetUser extends Pivot
{
    protected $table = 'permission_set_user';

    public $incrementing = true;

    protected $fillable = [
        'permission_set_id',
        'user_id',
        'scope_type',
        'scope_id',
        'environment_overrides',
    ];

    protected $casts = [
        'permission_set_id' => 'integer',
        'user_id' => 'integer',
        'scope_id' => 'integer',
        'environment_overrides' => 'array',
    ];

    /**
     * Get the permission set.
     */
    public function permissionSet(): BelongsTo
    {
        return $this->belongsTo(PermissionSet::class);
    }

    /**
     * Get the user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if user has permission with environment override check.
     */
    public function hasPermission(string $permissionKey, ?string $environment = null): bool
    {
        // First check if the permission set has this permission
        if (! $this->permissionSet->hasPermission($permissionKey, $environment)) {
            return false;
        }

        // Then check user-level environment overrides
        if ($environment !== null && ! empty($this->environment_overrides)) {
            if (isset($this->environment_overrides[$environment]) && $this->environment_overrides[$environment] === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the team if this is a team-scoped assignment.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'scope_id');
    }

    /**
     * Get the project if this is a project-scoped assignment.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'scope_id');
    }

    /**
     * Check if this is a team-scoped assignment.
     */
    public function isTeamScoped(): bool
    {
        return $this->scope_type === 'team';
    }

    /**
     * Check if this is a project-scoped assignment.
     */
    public function isProjectScoped(): bool
    {
        return $this->scope_type === 'project';
    }
}
