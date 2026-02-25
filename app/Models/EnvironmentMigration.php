<?php

namespace App\Models;

use App\Events\MigrationProgressUpdated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Visus\Cuid2\Cuid2;

/**
 * Model for environment migration workflow.
 * Handles resource migration between environments (dev -> uat -> prod).
 *
 * @property int $id
 * @property string $uuid
 * @property int $team_id
 * @property string $source_type
 * @property int $source_id
 * @property string|null $target_type
 * @property int|null $target_id
 * @property int $source_environment_id
 * @property int $target_environment_id
 * @property int|null $target_server_id
 * @property int $requested_by
 * @property string $status
 * @property int $progress
 * @property string|null $current_step
 * @property string|null $error_message
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Environment|null $sourceEnvironment
 * @property-read Environment|null $targetEnvironment
 * @property-read Server|null $targetServer
 * @property-read \Illuminate\Database\Eloquent\Model|null $source
 * @property-read \Illuminate\Database\Eloquent\Model|null $target
 * @property-read User|null $requestedBy
 * @property-read User|null $approvedBy
 */
class EnvironmentMigration extends Model
{
    /**
     * SECURITY: Using $fillable instead of $guarded = [] to prevent mass assignment vulnerabilities.
     * Excludes: id, uuid (auto-generated), status (system-managed), progress (system-managed),
     * approved_by, approved_at, started_at, completed_at, rolled_back_at (system-managed)
     */
    protected $fillable = [
        'team_id',
        'source_type',
        'source_id',
        'target_type',
        'target_id',
        'source_environment_id',
        'target_environment_id',
        'target_server_id',
        'requested_by',
        'requires_approval',
        'options',
        'rollback_snapshot',
        'current_step',
        'error_message',
        'rejection_reason',
        'logs',
    ];

    protected $casts = [
        'options' => 'array',
        'rollback_snapshot' => 'array',
        'requires_approval' => 'boolean',
        'progress' => 'integer',
        'approved_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'rolled_back_at' => 'datetime',
    ];

    // Status constants
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_ROLLED_BACK = 'rolled_back';

    public const STATUS_CANCELLED = 'cancelled';

    // Migration modes
    public const MODE_CLONE = 'clone';

    public const MODE_PROMOTE = 'promote';

    // Option keys
    public const OPTION_MODE = 'mode';

    public const OPTION_COPY_ENV_VARS = 'copy_env_vars';

    public const OPTION_COPY_VOLUMES = 'copy_volumes';

    public const OPTION_UPDATE_EXISTING = 'update_existing';

    public const OPTION_CONFIG_ONLY = 'config_only';

    public const OPTION_REWIRE_CONNECTIONS = 'rewire_connections';

    public const OPTION_AUTO_DEPLOY = 'auto_deploy';

    public const OPTION_OVERWRITE_VALUES = 'overwrite_values';

    public const OPTION_WAIT_FOR_READY = 'wait_for_ready';

    public const OPTION_COPY_DATA = 'copy_data';

    /**
     * Get all possible statuses.
     */
    public static function getAllStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_ROLLED_BACK,
            self::STATUS_CANCELLED,
        ];
    }

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
     * Get query builder for migrations owned by current team.
     */
    public static function ownedByCurrentTeam()
    {
        return self::where('team_id', currentTeam()->id)->orderByDesc('created_at');
    }

    /**
     * Get all migrations owned by current team (cached for request duration).
     */
    public static function ownedByCurrentTeamCached()
    {
        return once(function () {
            return self::ownedByCurrentTeam()->get();
        });
    }

    /**
     * The source resource (Application, Service, or Database).
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The target resource (created/updated after migration).
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The source environment.
     */
    public function sourceEnvironment(): BelongsTo
    {
        return $this->belongsTo(Environment::class, 'source_environment_id');
    }

    /**
     * The target environment.
     */
    public function targetEnvironment(): BelongsTo
    {
        return $this->belongsTo(Environment::class, 'target_environment_id');
    }

    /**
     * The target server.
     */
    public function targetServer(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'target_server_id');
    }

    /**
     * The user who requested the migration.
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * The user who approved/rejected the migration.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * The team that owns this migration.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Migration history entries for this migration.
     */
    public function history(): HasMany
    {
        return $this->hasMany(MigrationHistory::class, 'environment_migration_id');
    }

    // Status check methods

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isRolledBack(): bool
    {
        return $this->status === self::STATUS_ROLLED_BACK;
    }

    /**
     * Check if migration is waiting for approval.
     */
    public function isAwaitingApproval(): bool
    {
        return $this->requires_approval && $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if migration can be executed.
     */
    public function canBeExecuted(): bool
    {
        if ($this->requires_approval) {
            return $this->status === self::STATUS_APPROVED;
        }

        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if migration can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
        ]);
    }

    /**
     * Cancel a pending/approved migration.
     */
    public function markAsCancelled(): void
    {
        $this->forceFill([
            'status' => self::STATUS_CANCELLED,
        ])->save();

        $this->broadcastProgress('Migration cancelled');
    }

    /**
     * Check if migration can be rolled back.
     */
    public function canBeRolledBack(): bool
    {
        return $this->status === self::STATUS_COMPLETED && $this->rollback_snapshot !== null;
    }

    // Approval methods

    /**
     * Approve the migration request.
     *
     * @throws \LogicException if migration is not in pending status
     */
    public function approve(User $approver, ?string $comment = null): void
    {
        if ($this->status !== self::STATUS_PENDING) {
            throw new \LogicException("Cannot approve migration in status: {$this->status}");
        }

        $this->forceFill([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $approver->id,
            'approved_at' => Carbon::now(),
        ])->save();
    }

    /**
     * Reject the migration request.
     *
     * @throws \LogicException if migration is not in pending status
     */
    public function reject(User $approver, string $reason): void
    {
        if ($this->status !== self::STATUS_PENDING) {
            throw new \LogicException("Cannot reject migration in status: {$this->status}");
        }

        $this->forceFill([
            'status' => self::STATUS_REJECTED,
            'approved_by' => $approver->id,
            'rejection_reason' => $reason,
            'approved_at' => Carbon::now(),
        ])->save();
    }

    // Status update methods

    /**
     * Mark migration as in progress.
     *
     * @throws \LogicException if migration is not in pending or approved status
     */
    public function markAsInProgress(?string $step = null): void
    {
        if (! in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED])) {
            throw new \LogicException("Cannot mark migration as in_progress from status: {$this->status}");
        }

        $this->forceFill([
            'status' => self::STATUS_IN_PROGRESS,
            'current_step' => $step ?? 'Starting migration...',
            'started_at' => $this->started_at ?? now(),
        ])->save();

        $this->broadcastProgress($step ?? 'Starting migration...');
    }

    /**
     * Mark migration as completed.
     *
     * @throws \LogicException if migration is not in_progress
     */
    public function markAsCompleted(?string $targetType = null, ?int $targetId = null): void
    {
        if ($this->status !== self::STATUS_IN_PROGRESS) {
            throw new \LogicException("Cannot mark migration as completed from status: {$this->status}");
        }

        $data = [
            'status' => self::STATUS_COMPLETED,
            'progress' => 100,
            'current_step' => 'Migration completed',
            'completed_at' => now(),
        ];

        if ($targetType && $targetId) {
            $data['target_type'] = $targetType;
            $data['target_id'] = $targetId;
        }

        $this->forceFill($data)->save();

        $this->broadcastProgress('Migration completed');
    }

    /**
     * Mark migration as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        // Idempotent: if already failed, just update the error message
        if ($this->status === self::STATUS_FAILED) {
            $this->update(['error_message' => $errorMessage]);

            return;
        }

        // Cannot transition from terminal or rejected states
        if (in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_ROLLED_BACK, self::STATUS_REJECTED])) {
            throw new \LogicException("Cannot mark migration as failed from status: {$this->status}");
        }

        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'current_step' => 'Migration failed',
            'completed_at' => now(),
        ])->save();

        $this->broadcastProgress('Migration failed');
    }

    /**
     * Mark migration as rolled back.
     *
     * @throws \LogicException if migration is not completed
     */
    public function markAsRolledBack(): void
    {
        if ($this->status !== self::STATUS_COMPLETED) {
            throw new \LogicException("Cannot mark migration as rolled_back from status: {$this->status}");
        }

        $this->forceFill([
            'status' => self::STATUS_ROLLED_BACK,
            'current_step' => 'Migration rolled back',
            'rolled_back_at' => now(),
        ])->save();
    }

    /**
     * Update progress and broadcast via WebSocket.
     */
    public function updateProgress(int $progress, ?string $step = null): void
    {
        $data = ['progress' => min(100, max(0, $progress))];

        if ($step !== null) {
            $data['current_step'] = $step;
        }

        $this->update($data);

        $this->broadcastProgress($step);
    }

    /**
     * Append to logs and broadcast via WebSocket.
     */
    public function appendLog(string $message): void
    {
        $timestamp = now()->format('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] {$message}";

        $this->update([
            'logs' => $this->logs ? $this->logs."\n".$logEntry : $logEntry,
        ]);

        $this->broadcastProgress(logEntry: $logEntry);
    }

    /**
     * Broadcast current migration state via WebSocket.
     */
    protected function broadcastProgress(?string $step = null, ?string $logEntry = null): void
    {
        try {
            MigrationProgressUpdated::dispatch(
                $this->uuid,
                $this->status,
                $this->progress ?? 0,
                $step ?? $this->current_step,
                $logEntry,
                $this->error_message,
                $this->team_id,
            );
        } catch (\Throwable $e) {
            Log::debug('Failed to broadcast migration progress update', [
                'migration_uuid' => $this->uuid,
                'status' => $this->status,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // Query scopes

    /**
     * Scope for pending migrations.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for migrations requiring approval.
     */
    public function scopeRequiringApproval($query)
    {
        return $query->where('requires_approval', true)
            ->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for migrations in progress.
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    /**
     * Scope for completed migrations.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for failed migrations.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for a specific team.
     */
    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Get pending migrations for a user who can approve them (admin/owner).
     */
    public static function pendingForApprover(User $user)
    {
        // Get projects where user is admin or owner
        $projectIds = $user->projectMemberships()
            ->wherePivotIn('role', ['owner', 'admin'])
            ->pluck('projects.id');

        // Also include projects from teams where user is admin/owner
        $teamIds = $user->teams()
            ->wherePivotIn('role', ['owner', 'admin'])
            ->pluck('teams.id');

        $teamProjectIds = Project::whereIn('team_id', $teamIds)->pluck('id');

        $allProjectIds = $projectIds->merge($teamProjectIds)->unique();

        return static::where('status', self::STATUS_PENDING)
            ->where('requires_approval', true)
            ->whereHas('sourceEnvironment', function ($query) use ($allProjectIds) {
                $query->whereIn('project_id', $allProjectIds);
            })
            ->with(['source', 'sourceEnvironment.project', 'targetEnvironment', 'requestedBy'])
            ->orderBy('created_at', 'desc');
    }

    // Attribute getters

    /**
     * Get human-readable status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_ROLLED_BACK => 'Rolled Back',
            default => $this->status,
        };
    }

    /**
     * Get source resource type name for display.
     */
    public function getSourceTypeNameAttribute(): string
    {
        if (! $this->source_type) {
            return 'Unknown';
        }

        $class = class_basename($this->source_type);

        return match ($class) {
            'Application' => 'Application',
            'Service' => 'Service',
            'StandalonePostgresql' => 'PostgreSQL',
            'StandaloneMysql' => 'MySQL',
            'StandaloneMariadb' => 'MariaDB',
            'StandaloneMongodb' => 'MongoDB',
            'StandaloneRedis' => 'Redis',
            'StandaloneClickhouse' => 'ClickHouse',
            'StandaloneKeydb' => 'KeyDB',
            'StandaloneDragonfly' => 'Dragonfly',
            default => $class,
        };
    }

    /**
     * Get the migration direction (e.g., "dev → uat").
     */
    public function getMigrationDirectionAttribute(): string
    {
        $source = $this->sourceEnvironment->name ?? 'Unknown';
        $target = $this->targetEnvironment->name ?? 'Unknown';

        return "{$source} → {$target}";
    }

    /**
     * Check if this is a database migration.
     */
    public function isDatabaseMigration(): bool
    {
        if (! $this->source_type) {
            return false;
        }

        $class = class_basename($this->source_type);

        return in_array($class, [
            'StandalonePostgresql',
            'StandaloneMysql',
            'StandaloneMariadb',
            'StandaloneMongodb',
            'StandaloneRedis',
            'StandaloneClickhouse',
            'StandaloneKeydb',
            'StandaloneDragonfly',
        ]);
    }

    /**
     * Check if option is enabled.
     */
    public function hasOption(string $option): bool
    {
        return isset($this->options[$option]) && $this->options[$option] === true;
    }

    /**
     * Get option value.
     */
    public function getOption(string $option, mixed $default = null): mixed
    {
        return $this->options[$option] ?? $default;
    }
}
