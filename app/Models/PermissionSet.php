<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * PermissionSet model - represents a named set of permissions.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $scope_type 'team' or 'project'
 * @property int $scope_id team_id or project_id
 * @property bool $is_system Built-in permission sets cannot be deleted
 * @property int|null $parent_id For inheritance
 * @property string|null $color
 * @property string|null $icon
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Team|Project|null $scope
 * @property-read PermissionSet|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection|Permission[] $permissions
 * @property-read \Illuminate\Database\Eloquent\Collection|User[] $users
 */
class PermissionSet extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'scope_type',
        'scope_id',
        'is_system',
        'parent_id',
        'color',
        'icon',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'scope_id' => 'integer',
        'parent_id' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (PermissionSet $model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

    /**
     * Get the scope entity (Team or Project).
     */
    public function scope(): BelongsTo
    {
        if ($this->scope_type === 'team') {
            return $this->belongsTo(Team::class, 'scope_id');
        }

        return $this->belongsTo(Project::class, 'scope_id');
    }

    /**
     * Get the team if scope_type is 'team'.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'scope_id')
            ->where('scope_type', 'team');
    }

    /**
     * Get the project if scope_type is 'project'.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'scope_id')
            ->where('scope_type', 'project');
    }

    /**
     * Parent permission set for inheritance.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(PermissionSet::class, 'parent_id');
    }

    /**
     * Child permission sets that inherit from this one.
     */
    public function children(): HasMany
    {
        return $this->hasMany(PermissionSet::class, 'parent_id');
    }

    /**
     * Permissions assigned to this set.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_set_permissions')
            ->withPivot('environment_restrictions')
            ->withTimestamps();
    }

    /**
     * Users assigned to this permission set.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'permission_set_user')
            ->withPivot(['scope_type', 'scope_id', 'environment_overrides'])
            ->withTimestamps();
    }

    /**
     * Scope to filter by team.
     */
    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('scope_type', 'team')
            ->where('scope_id', $teamId);
    }

    /**
     * Scope to filter by project.
     */
    public function scopeForProject($query, int $projectId)
    {
        return $query->where('scope_type', 'project')
            ->where('scope_id', $projectId);
    }

    /**
     * Scope to filter system permission sets.
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Scope to filter custom (non-system) permission sets.
     */
    public function scopeCustom($query)
    {
        return $query->where('is_system', false);
    }

    /**
     * Check if this permission set has a specific permission.
     */
    public function hasPermission(string $permissionKey, ?string $environment = null): bool
    {
        $permission = $this->permissions->firstWhere('key', $permissionKey);

        if (! $permission) {
            // Check parent permission set
            if ($this->parent) {
                return $this->parent->hasPermission($permissionKey, $environment);
            }

            return false;
        }

        // Check environment restrictions if environment is provided
        if ($environment !== null) {
            $restrictions = $permission->pivot->environment_restrictions ?? [];

            if (! empty($restrictions)) {
                $restrictionsArray = is_string($restrictions) ? json_decode($restrictions, true) : $restrictions;

                // If environment is explicitly restricted to false, deny
                if (isset($restrictionsArray[$environment]) && $restrictionsArray[$environment] === false) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get all permission keys for this set (including inherited).
     */
    public function getAllPermissionKeys(): array
    {
        $keys = $this->permissions->pluck('key')->toArray();

        if ($this->parent) {
            $parentKeys = $this->parent->getAllPermissionKeys();
            $keys = array_unique(array_merge($parentKeys, $keys));
        }

        return $keys;
    }

    /**
     * Sync permissions with environment restrictions.
     *
     * @param  array  $permissions  Format: [['permission_id' => 1, 'environment_restrictions' => [...]], ...]
     */
    public function syncPermissionsWithRestrictions(array $permissions): void
    {
        $syncData = [];

        foreach ($permissions as $perm) {
            $permId = $perm['permission_id'] ?? $perm['id'] ?? null;
            if ($permId) {
                $syncData[$permId] = [
                    'environment_restrictions' => $perm['environment_restrictions'] ?? null,
                ];
            }
        }

        $this->permissions()->sync($syncData);
    }

    /**
     * Check if this permission set can be deleted.
     */
    public function canBeDeleted(): bool
    {
        // System permission sets cannot be deleted
        if ($this->is_system) {
            return false;
        }

        // Check if any users are assigned to this set
        if ($this->users()->exists()) {
            return false;
        }

        // Check if any child permission sets exist
        if ($this->children()->exists()) {
            return false;
        }

        return true;
    }

    /**
     * Get system permission set by slug for a team.
     */
    public static function getSystemSetForTeam(int $teamId, string $slug): ?self
    {
        return static::forTeam($teamId)
            ->system()
            ->where('slug', $slug)
            ->first();
    }
}
