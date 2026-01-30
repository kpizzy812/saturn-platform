<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot model for team_user relationship.
 *
 * @property int $id
 * @property int $team_id
 * @property int $user_id
 * @property string $role
 * @property array<int>|null $allowed_projects
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TeamUser extends Pivot
{
    protected $table = 'team_user';

    public $incrementing = true;

    protected $casts = [
        'allowed_projects' => 'array',
    ];

    protected $fillable = [
        'team_id',
        'user_id',
        'role',
        'allowed_projects',
    ];

    /**
     * Check if user has access to all projects.
     * '*' in array means full access to all projects.
     */
    public function hasFullProjectAccess(): bool
    {
        return is_array($this->allowed_projects) && in_array('*', $this->allowed_projects, true);
    }

    /**
     * Check if user has no access to any projects.
     * null or empty array means no access.
     */
    public function hasNoProjectAccess(): bool
    {
        return $this->allowed_projects === null ||
               (is_array($this->allowed_projects) && empty($this->allowed_projects));
    }

    /**
     * Check if user can access a specific project.
     * Returns false by default (deny-by-default security model).
     */
    public function canAccessProject(int $projectId): bool
    {
        // No access by default (null or empty array)
        if ($this->allowed_projects === null || empty($this->allowed_projects)) {
            return false;
        }

        // '*' means access to all projects
        if (in_array('*', $this->allowed_projects, true)) {
            return true;
        }

        // Check if specific project ID is in the allowed list
        return in_array($projectId, $this->allowed_projects, true);
    }
}
