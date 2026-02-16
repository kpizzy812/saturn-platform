<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $application_id
 * @property int|null $failed_deployment_id
 * @property int|null $rollback_deployment_id
 * @property int|null $triggered_by_user_id
 * @property string|null $trigger_reason
 * @property string|null $trigger_type
 * @property array|null $metrics_snapshot
 * @property string $status
 * @property string|null $error_message
 * @property string|null $from_commit
 * @property string|null $to_commit
 * @property int|null $from_deployment_id
 * @property int|null $to_deployment_id
 * @property \Carbon\Carbon|null $triggered_at
 * @property \Carbon\Carbon|null $completed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Application|null $application
 * @property-read ApplicationDeploymentQueue|null $failedDeployment
 * @property-read ApplicationDeploymentQueue|null $rollbackDeployment
 * @property-read User|null $triggeredByUser
 */
class ApplicationRollbackEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'failed_deployment_id',
        'rollback_deployment_id',
        'triggered_by_user_id',
        'trigger_reason',
        'trigger_type',
        'metrics_snapshot',
        'status',
        'error_message',
        'from_commit',
        'to_commit',
        'triggered_at',
        'completed_at',
    ];

    protected $casts = [
        'metrics_snapshot' => 'array',
        'triggered_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Trigger reasons
    public const REASON_CRASH_LOOP = 'crash_loop';

    public const REASON_HEALTH_CHECK_FAILED = 'health_check_failed';

    public const REASON_CONTAINER_EXITED = 'container_exited';

    public const REASON_MANUAL = 'manual';

    public const REASON_ERROR_RATE = 'error_rate_exceeded';

    // Statuses
    public const STATUS_TRIGGERED = 'triggered';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function failedDeployment(): BelongsTo
    {
        return $this->belongsTo(ApplicationDeploymentQueue::class, 'failed_deployment_id');
    }

    public function rollbackDeployment(): BelongsTo
    {
        return $this->belongsTo(ApplicationDeploymentQueue::class, 'rollback_deployment_id');
    }

    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    public function markInProgress(int $rollbackDeploymentId): void
    {
        $this->update([
            'status' => self::STATUS_IN_PROGRESS,
            'rollback_deployment_id' => $rollbackDeploymentId,
        ]);
    }

    public function markSuccess(): void
    {
        $this->update([
            'status' => self::STATUS_SUCCESS,
            'completed_at' => now(),
        ]);
    }

    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    public function getReasonLabel(): string
    {
        return match ($this->trigger_reason) {
            self::REASON_CRASH_LOOP => 'Crash Loop Detected',
            self::REASON_HEALTH_CHECK_FAILED => 'Health Check Failed',
            self::REASON_CONTAINER_EXITED => 'Container Exited',
            self::REASON_MANUAL => 'Manual Rollback',
            self::REASON_ERROR_RATE => 'Error Rate Exceeded',
            default => ucfirst(str_replace('_', ' ', $this->trigger_reason)),
        };
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_SUCCESS => 'bg-green-500/20 text-green-400',
            self::STATUS_FAILED => 'bg-red-500/20 text-red-400',
            self::STATUS_IN_PROGRESS => 'bg-yellow-500/20 text-yellow-400',
            self::STATUS_TRIGGERED => 'bg-blue-500/20 text-blue-400',
            self::STATUS_SKIPPED => 'bg-neutral-500/20 text-neutral-400',
            default => 'bg-neutral-500/20 text-neutral-400',
        };
    }

    /**
     * Create a new rollback event
     */
    public static function createEvent(
        Application $application,
        string $reason,
        string $type = 'automatic',
        ?ApplicationDeploymentQueue $failedDeployment = null,
        ?User $user = null,
        ?array $metrics = null
    ): self {
        return self::create([
            'application_id' => $application->id,
            'failed_deployment_id' => $failedDeployment?->id,
            'triggered_by_user_id' => $user?->id,
            'trigger_reason' => $reason,
            'trigger_type' => $type,
            'metrics_snapshot' => $metrics,
            'status' => self::STATUS_TRIGGERED,
            'from_commit' => $failedDeployment?->commit,
            'triggered_at' => now(),
        ]);
    }

    /**
     * Scope for recent events
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope for automatic rollbacks
     */
    public function scopeAutomatic($query)
    {
        return $query->where('trigger_type', 'automatic');
    }
}
