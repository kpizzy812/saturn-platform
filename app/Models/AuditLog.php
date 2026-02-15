<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'team_id',
        'action',
        'resource_type',
        'resource_id',
        'resource_name',
        'description',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the team associated with this audit log.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Scope a query to filter by team.
     */
    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Scope a query to filter by user.
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to filter by action.
     */
    public function scopeByAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    /**
     * Scope a query to filter by resource type and optionally resource ID.
     */
    public function scopeByResource(Builder $query, string $type, ?int $id = null): Builder
    {
        $query->where('resource_type', $type);

        if ($id !== null) {
            $query->where('resource_id', $id);
        }

        return $query;
    }

    /**
     * Scope a query to get recent logs within specified days.
     */
    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope a query to order by most recent first.
     */
    public function scopeLatest(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Static helper to log an action.
     *
     * @param  string  $action  The action being performed (e.g., 'create', 'update', 'delete', 'deploy')
     * @param  Model|null  $resource  The resource being acted upon
     * @param  string|null  $description  A human-readable description of the action
     * @param  array  $metadata  Additional metadata about the action
     */
    public static function log(
        string $action,
        ?Model $resource = null,
        ?string $description = null,
        array $metadata = []
    ): static {
        $user = Auth::user();
        $currentTeam = null;

        // Get current team from session if available
        if ($user && function_exists('currentTeam')) {
            try {
                $currentTeam = currentTeam();
            } catch (\Exception $e) {
                // Fallback: try to get team from resource if it has one
                if ($resource && method_exists($resource, 'team')) {
                    $currentTeam = $resource->team;
                }
            }
        }

        // Extract resource information
        $resourceType = $resource ? get_class($resource) : null;
        $resourceId = $resource?->id ?? null;
        $resourceName = null;

        // Try to get a human-readable name from the resource
        if ($resource) {
            $resourceName = $resource->getAttribute('name')
                ?? $resource->getAttribute('title')
                ?? $resource->getAttribute('key')
                ?? (method_exists($resource, 'getName') ? $resource->getName() : null)
                ?? (method_exists($resource, 'getTitle') ? $resource->getTitle() : null);
        }

        // If deleting a Team resource, the team no longer exists in DB
        // Set team_id to null to avoid FK constraint violation
        if ($action === 'delete' && $resource instanceof Team) {
            $currentTeam = null;
        }

        return static::create([
            'user_id' => $user?->id,
            'team_id' => $currentTeam?->id,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'resource_name' => $resourceName,
            'description' => $description,
            'metadata' => $metadata,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    /**
     * Get a formatted action name for display.
     */
    public function getFormattedActionAttribute(): string
    {
        return match ($this->action) {
            'create' => 'Created',
            'update' => 'Updated',
            'delete' => 'Deleted',
            'deploy' => 'Deployed',
            'rollback' => 'Rolled back',
            'login' => 'Logged in',
            'logout' => 'Logged out',
            'start' => 'Started',
            'stop' => 'Stopped',
            'restart' => 'Restarted',
            default => ucfirst($this->action),
        };
    }

    /**
     * Get a short description of the resource type.
     */
    public function getResourceTypeNameAttribute(): ?string
    {
        if (! $this->resource_type) {
            return null;
        }

        return class_basename($this->resource_type);
    }
}
