<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OpenApi\Attributes as OA;

/**
 * @property string $id
 * @property int $team_id
 * @property int|null $user_id
 * @property string $type
 * @property string $title
 * @property string|null $description
 * @property string|null $action_url
 * @property array|null $metadata
 * @property bool $is_read
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Team $team
 * @property-read User|null $user
 *
 * @method static Builder<static> unread()
 * @method static Builder<static> read()
 * @method static Builder<static> ofType(string $type)
 * @method static Builder<static> forCurrentTeam()
 * @method static static create(array $attributes = [])
 * @method static Builder<static> where($column, $operator = null, $value = null)
 */
#[OA\Schema(
    description: 'User notification model',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', description: 'The unique identifier of the notification.'),
        new OA\Property(property: 'team_id', type: 'integer', description: 'The team ID this notification belongs to.'),
        new OA\Property(property: 'user_id', type: 'integer', nullable: true, description: 'The user ID this notification is for (null for team-wide).'),
        new OA\Property(property: 'type', type: 'string', description: 'The type of notification.'),
        new OA\Property(property: 'title', type: 'string', description: 'The notification title.'),
        new OA\Property(property: 'description', type: 'string', nullable: true, description: 'The notification description.'),
        new OA\Property(property: 'action_url', type: 'string', nullable: true, description: 'URL for the notification action.'),
        new OA\Property(property: 'is_read', type: 'boolean', description: 'Whether the notification has been read.'),
        new OA\Property(property: 'read_at', type: 'string', format: 'date-time', nullable: true, description: 'When the notification was read.'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', description: 'When the notification was created.'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', description: 'When the notification was last updated.'),
    ]
)]
class UserNotification extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'team_id',
        'user_id',
        'type',
        'title',
        'description',
        'action_url',
        'metadata',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Valid notification types
     */
    public const TYPES = [
        'deployment_success',
        'deployment_failure',
        'deployment_approval',
        'team_invite',
        'billing_alert',
        'security_alert',
        'backup_success',
        'backup_failure',
        'server_alert',
        'info',
    ];

    /**
     * Get the team that owns the notification.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user that owns the notification.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get unread notifications.
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope to get read notifications.
     */
    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    /**
     * Scope to filter by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for current team.
     */
    public function scopeForCurrentTeam($query)
    {
        $team = currentTeam();
        if ($team) {
            return $query->where('team_id', $team->id);
        }

        return $query->whereRaw('1 = 0'); // Return empty if no team
    }

    /**
     * Mark the notification as read.
     */
    public function markAsRead(): bool
    {
        if ($this->is_read) {
            return true;
        }

        return $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Mark the notification as unread.
     */
    public function markAsUnread(): bool
    {
        return $this->update([
            'is_read' => false,
            'read_at' => null,
        ]);
    }

    /**
     * Create a notification for a team.
     */
    public static function createForTeam(
        Team $team,
        string $type,
        string $title,
        ?string $description = null,
        ?string $actionUrl = null,
        ?array $metadata = null,
        ?User $user = null
    ): self {
        return self::create([
            'team_id' => $team->id,
            'user_id' => $user?->id,
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'action_url' => $actionUrl,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Transform to frontend format.
     */
    public function toFrontendArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'timestamp' => $this->created_at->toIso8601String(),
            'isRead' => $this->is_read,
            'actionUrl' => $this->getRelativeActionUrl(),
        ];
    }

    /**
     * Get action URL as relative path (strips base_url for SPA navigation).
     * Also converts legacy Livewire deployment URLs to new Inertia format.
     */
    protected function getRelativeActionUrl(): ?string
    {
        if ($this->action_url === null) {
            return null;
        }

        // Strip the origin to get a relative path
        $path = $this->action_url;
        if (! str_starts_with($path, '/')) {
            $parsed = parse_url($path);
            if ($parsed !== false && isset($parsed['path'])) {
                $path = $parsed['path'];
                if (isset($parsed['query'])) {
                    $path .= '?'.$parsed['query'];
                }
            }
        }

        // Convert legacy Livewire deployment URLs to new Inertia format:
        // /project/{uuid}/environment/{uuid}/application/{uuid}/deployment/{uuid}
        // → /deployments/{uuid}
        if (preg_match('#/deployment/([a-zA-Z0-9-]+)$#', $path, $matches)) {
            return '/deployments/'.$matches[1];
        }

        return $path;
    }
}
