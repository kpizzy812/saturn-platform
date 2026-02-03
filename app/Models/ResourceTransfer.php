<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Visus\Cuid2\Cuid2;

class ResourceTransfer extends Model
{
    protected $guarded = [];

    protected $casts = [
        'transfer_options' => 'array',
        'error_details' => 'array',
        'progress' => 'integer',
        'transferred_bytes' => 'integer',
        'total_bytes' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Status constants
    public const STATUS_PENDING = 'pending';

    public const STATUS_PREPARING = 'preparing';

    public const STATUS_TRANSFERRING = 'transferring';

    public const STATUS_RESTORING = 'restoring';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    // Transfer mode constants
    public const MODE_CLONE = 'clone';

    public const MODE_DATA_ONLY = 'data_only';

    public const MODE_PARTIAL = 'partial';

    /**
     * Get all possible statuses.
     */
    public static function getAllStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_PREPARING,
            self::STATUS_TRANSFERRING,
            self::STATUS_RESTORING,
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * Get all possible transfer modes.
     */
    public static function getAllModes(): array
    {
        return [
            self::MODE_CLONE,
            self::MODE_DATA_ONLY,
            self::MODE_PARTIAL,
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Model $model) {
            if (! $model->uuid) {
                $model->uuid = (string) new Cuid2;
            }
        });
    }

    /**
     * Get query builder for transfers owned by current team.
     */
    public static function ownedByCurrentTeam()
    {
        return self::where('team_id', currentTeam()->id)->orderByDesc('created_at');
    }

    /**
     * Get all transfers owned by current team (cached for request duration).
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
     * The target resource (created after cloning).
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
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
     * The user who initiated the transfer.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The team that owns this transfer.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Scope for pending transfers.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for transfers in progress.
     */
    public function scopeInProgress($query)
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING,
            self::STATUS_PREPARING,
            self::STATUS_TRANSFERRING,
            self::STATUS_RESTORING,
        ]);
    }

    /**
     * Scope for completed transfers.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for failed transfers.
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
     * Check if transfer is in progress.
     */
    public function isInProgress(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_PREPARING,
            self::STATUS_TRANSFERRING,
            self::STATUS_RESTORING,
        ]);
    }

    /**
     * Check if transfer is active (alias for isInProgress).
     */
    public function isActive(): bool
    {
        return $this->isInProgress();
    }

    /**
     * Check if transfer can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_PREPARING,
            self::STATUS_TRANSFERRING,
        ]);
    }

    /**
     * Mark transfer as preparing.
     */
    public function markAsPreparing(?string $step = null): void
    {
        $this->update([
            'status' => self::STATUS_PREPARING,
            'current_step' => $step ?? 'Preparing transfer...',
            'started_at' => $this->started_at ?? now(),
        ]);
    }

    /**
     * Mark transfer as transferring data.
     */
    public function markAsTransferring(?string $step = null): void
    {
        $this->update([
            'status' => self::STATUS_TRANSFERRING,
            'current_step' => $step ?? 'Transferring data...',
        ]);
    }

    /**
     * Mark transfer as restoring.
     */
    public function markAsRestoring(?string $step = null): void
    {
        $this->update([
            'status' => self::STATUS_RESTORING,
            'current_step' => $step ?? 'Restoring data on target...',
        ]);
    }

    /**
     * Mark transfer as completed.
     */
    public function markAsCompleted(?string $targetType = null, ?int $targetId = null): void
    {
        $data = [
            'status' => self::STATUS_COMPLETED,
            'progress' => 100,
            'current_step' => 'Transfer completed',
            'completed_at' => now(),
        ];

        if ($targetType && $targetId) {
            $data['target_type'] = $targetType;
            $data['target_id'] = $targetId;
        }

        $this->update($data);
    }

    /**
     * Mark transfer as failed.
     */
    public function markAsFailed(string $errorMessage, ?array $errorDetails = null): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'error_details' => $errorDetails,
            'current_step' => 'Transfer failed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark transfer as cancelled.
     */
    public function markAsCancelled(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'current_step' => 'Transfer cancelled',
            'completed_at' => now(),
        ]);
    }

    /**
     * Update progress.
     */
    public function updateProgress(int $progress, ?string $step = null, ?int $transferredBytes = null): void
    {
        $data = ['progress' => min(100, max(0, $progress))];

        if ($step !== null) {
            $data['current_step'] = $step;
        }

        if ($transferredBytes !== null) {
            $data['transferred_bytes'] = $transferredBytes;
        }

        $this->update($data);
    }

    /**
     * Append to logs.
     */
    public function appendLog(string $message): void
    {
        $timestamp = now()->format('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] {$message}";

        $this->update([
            'logs' => $this->logs ? $this->logs."\n".$logEntry : $logEntry,
        ]);
    }

    /**
     * Get human-readable status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PREPARING => 'Preparing',
            self::STATUS_TRANSFERRING => 'Transferring',
            self::STATUS_RESTORING => 'Restoring',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_CANCELLED => 'Cancelled',
            default => $this->status,
        };
    }

    /**
     * Get human-readable transfer mode label.
     */
    public function getModeLabelAttribute(): string
    {
        return match ($this->transfer_mode) {
            self::MODE_CLONE => 'Full Clone',
            self::MODE_DATA_ONLY => 'Data Only',
            self::MODE_PARTIAL => 'Partial',
            default => $this->transfer_mode,
        };
    }

    /**
     * Get source database type for display.
     */
    public function getSourceTypeNameAttribute(): string
    {
        if (! $this->source) {
            return 'Unknown';
        }

        $class = class_basename($this->source_type);

        return match ($class) {
            'StandalonePostgresql' => 'PostgreSQL',
            'StandaloneMysql' => 'MySQL',
            'StandaloneMariadb' => 'MariaDB',
            'StandaloneMongodb' => 'MongoDB',
            'StandaloneRedis' => 'Redis',
            'StandaloneClickhouse' => 'ClickHouse',
            'StandaloneKeydb' => 'KeyDB',
            'StandaloneDragonfly' => 'Dragonfly',
            'Application' => 'Application',
            'Service' => 'Service',
            default => $class,
        };
    }

    /**
     * Get formatted transfer progress for display.
     */
    public function getFormattedProgressAttribute(): string
    {
        if ($this->total_bytes && $this->total_bytes > 0) {
            $transferred = $this->formatBytes($this->transferred_bytes);
            $total = $this->formatBytes($this->total_bytes);

            return "{$transferred} / {$total} ({$this->progress}%)";
        }

        return "{$this->progress}%";
    }

    /**
     * Format bytes to human-readable string.
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);

        return number_format($bytes / pow(1024, $power), 2).' '.$units[$power];
    }

    /**
     * Get estimated time remaining based on transfer speed.
     */
    public function getEstimatedTimeRemainingAttribute(): ?string
    {
        if (! $this->total_bytes || ! $this->transferred_bytes || ! $this->started_at) {
            return null;
        }

        $remaining = $this->total_bytes - $this->transferred_bytes;
        if ($remaining <= 0) {
            return null;
        }

        $elapsed = now()->diffInSeconds($this->started_at);
        if ($elapsed <= 0 || $this->transferred_bytes <= 0) {
            return null;
        }

        $speed = $this->transferred_bytes / $elapsed;
        $secondsRemaining = (int) ($remaining / $speed);

        if ($secondsRemaining < 60) {
            return "{$secondsRemaining} seconds";
        }

        if ($secondsRemaining < 3600) {
            $minutes = (int) ($secondsRemaining / 60);

            return "{$minutes} minutes";
        }

        $hours = (int) ($secondsRemaining / 3600);
        $minutes = (int) (($secondsRemaining % 3600) / 60);

        return "{$hours}h {$minutes}m";
    }
}
