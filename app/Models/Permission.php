<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Permission model - represents a single permission in the catalog.
 *
 * @property int $id
 * @property string $key Unique permission key (e.g., 'applications.deploy')
 * @property string $name Human-readable name
 * @property string|null $description
 * @property string $resource Resource type (applications, servers, databases, etc.)
 * @property string $action Action type (view, create, update, delete, deploy, etc.)
 * @property string $category Category for grouping (resources, team, settings)
 * @property bool $is_sensitive Whether this permission grants access to sensitive data
 * @property int $sort_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Relations\Pivot|null $pivot
 */
class Permission extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'resource',
        'action',
        'category',
        'is_sensitive',
        'sort_order',
    ];

    protected $casts = [
        'is_sensitive' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Permission sets that include this permission.
     */
    public function permissionSets(): BelongsToMany
    {
        return $this->belongsToMany(PermissionSet::class, 'permission_set_permissions')
            ->withPivot('environment_restrictions')
            ->withTimestamps();
    }

    /**
     * Scope to filter by category.
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to filter by resource.
     */
    public function scopeResource($query, string $resource)
    {
        return $query->where('resource', $resource);
    }

    /**
     * Scope to filter only sensitive permissions.
     */
    public function scopeSensitive($query)
    {
        return $query->where('is_sensitive', true);
    }

    /**
     * Get permissions grouped by category.
     */
    public static function getGroupedByCategory(): array
    {
        return static::orderBy('category')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('category')
            ->toArray();
    }

    /**
     * Get permissions grouped by resource.
     */
    public static function getGroupedByResource(): array
    {
        return static::orderBy('resource')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('resource')
            ->toArray();
    }
}
