<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Visus\Cuid2\Cuid2;

/**
 * TeamResourceTransfer Model
 *
 * Tracks resource transfers between teams, primarily used when deleting users
 * to preserve their resources by transferring them to another team.
 *
 * @property int $id
 * @property string $uuid
 * @property string $transfer_type
 * @property string $transferable_type
 * @property int $transferable_id
 * @property int $from_team_id
 * @property int $to_team_id
 * @property int|null $from_user_id
 * @property int|null $to_user_id
 * @property int|null $initiated_by
 * @property array|null $resource_snapshot
 * @property array|null $related_transfers
 * @property string|null $reason
 * @property string $status
 * @property string|null $error_message
 * @property string|null $notes
 * @property \Carbon\Carbon|null $completed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TeamResourceTransfer extends Model
{
    protected $table = 'team_resource_transfers';

    /**
     * SECURITY: Using $fillable instead of $guarded = [] to prevent mass assignment vulnerabilities.
     * Excludes: id, uuid (auto-generated), status (system-managed), completed_at (system-managed)
     */
    protected $fillable = [
        'transfer_type',
        'transferable_type',
        'transferable_id',
        'from_team_id',
        'to_team_id',
        'from_user_id',
        'to_user_id',
        'initiated_by',
        'resource_snapshot',
        'related_transfers',
        'reason',
        'error_message',
        'notes',
    ];

    protected $casts = [
        'resource_snapshot' => 'array',
        'related_transfers' => 'array',
        'completed_at' => 'datetime',
    ];

    // Status constants
    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_ROLLED_BACK = 'rolled_back';

    // Transfer type constants
    public const TYPE_PROJECT_TRANSFER = 'project_transfer';

    public const TYPE_TEAM_OWNERSHIP = 'team_ownership';

    public const TYPE_TEAM_MERGE = 'team_merge';

    public const TYPE_USER_DELETION = 'user_deletion';

    public const TYPE_ARCHIVE = 'archive';

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $model) {
            if (! $model->uuid) {
                $model->uuid = (string) new Cuid2;
            }
        });
    }

    /**
     * Get all possible statuses.
     */
    public static function getAllStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_ROLLED_BACK,
        ];
    }

    /**
     * Get all possible transfer types.
     */
    public static function getAllTypes(): array
    {
        return [
            self::TYPE_PROJECT_TRANSFER,
            self::TYPE_TEAM_OWNERSHIP,
            self::TYPE_TEAM_MERGE,
            self::TYPE_USER_DELETION,
            self::TYPE_ARCHIVE,
        ];
    }

    /**
     * The resource being transferred (Project, Team, Server, etc.).
     */
    public function transferable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The source team.
     */
    public function fromTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'from_team_id');
    }

    /**
     * The destination team.
     */
    public function toTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'to_team_id');
    }

    /**
     * The source user (previous owner).
     */
    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    /**
     * The destination user (new owner).
     */
    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    /**
     * The admin who initiated the transfer.
     */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    /**
     * Scope for pending transfers.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for completed transfers.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for transfers of a specific type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('transfer_type', $type);
    }

    /**
     * Scope for transfers involving a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('from_user_id', $userId)
                ->orWhere('to_user_id', $userId)
                ->orWhere('initiated_by', $userId);
        });
    }

    /**
     * Scope for transfers involving a specific team.
     */
    public function scopeForTeam($query, int $teamId)
    {
        return $query->where(function ($q) use ($teamId) {
            $q->where('from_team_id', $teamId)
                ->orWhere('to_team_id', $teamId);
        });
    }

    /**
     * Check if transfer is in progress.
     */
    public function isInProgress(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_IN_PROGRESS]);
    }

    /**
     * Check if transfer is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if transfer failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Mark transfer as in progress.
     */
    public function markAsInProgress(): void
    {
        $this->update(['status' => self::STATUS_IN_PROGRESS]);
    }

    /**
     * Mark transfer as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark transfer as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    /**
     * Get human-readable status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_ROLLED_BACK => 'Rolled Back',
            default => $this->status,
        };
    }

    /**
     * Get human-readable transfer type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->transfer_type) {
            self::TYPE_PROJECT_TRANSFER => 'Project Transfer',
            self::TYPE_TEAM_OWNERSHIP => 'Team Ownership Change',
            self::TYPE_TEAM_MERGE => 'Team Merge',
            self::TYPE_USER_DELETION => 'User Deletion',
            self::TYPE_ARCHIVE => 'Archive',
            default => $this->transfer_type,
        };
    }

    /**
     * Get the transferable resource name.
     */
    public function getResourceNameAttribute(): string
    {
        return $this->transferable?->getAttribute('name') ?? 'Unknown Resource';
    }

    /**
     * Get the transferable resource type for display.
     */
    public function getResourceTypeNameAttribute(): string
    {
        if (! $this->transferable_type) {
            return 'Unknown';
        }

        return class_basename($this->transferable_type);
    }
}
