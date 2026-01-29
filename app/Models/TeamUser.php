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
     * Check if user has access to all projects (no restrictions).
     */
    public function hasFullProjectAccess(): bool
    {
        return $this->allowed_projects === null;
    }

    /**
     * Check if user can access a specific project.
     */
    public function canAccessProject(int $projectId): bool
    {
        // null means all projects allowed
        if ($this->allowed_projects === null) {
            return true;
        }

        return in_array($projectId, $this->allowed_projects, true);
    }
}
