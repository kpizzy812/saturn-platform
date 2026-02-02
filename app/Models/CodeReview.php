<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * AI-powered code review for deployment diffs.
 *
 * @property int $id
 * @property int|null $deployment_id
 * @property int $application_id
 * @property string $commit_sha
 * @property string|null $base_commit_sha
 * @property string $status
 * @property string|null $summary
 * @property array|null $files_analyzed
 * @property int $violations_count
 * @property int $critical_count
 * @property string|null $llm_provider
 * @property string|null $llm_model
 * @property int|null $llm_tokens_used
 * @property bool $llm_failed
 * @property string $cache_key
 * @property \Carbon\Carbon|null $started_at
 * @property \Carbon\Carbon|null $finished_at
 * @property int|null $duration_ms
 * @property string|null $error_message
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read ApplicationDeploymentQueue|null $deployment
 * @property-read Application $application
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CodeReviewViolation> $violations
 */
class CodeReview extends Model
{
    protected $fillable = [
        'deployment_id',
        'application_id',
        'commit_sha',
        'base_commit_sha',
        'status',
        'summary',
        'files_analyzed',
        'violations_count',
        'critical_count',
        'llm_provider',
        'llm_model',
        'llm_tokens_used',
        'llm_failed',
        'cache_key',
        'started_at',
        'finished_at',
        'duration_ms',
        'error_message',
    ];

    protected $casts = [
        'files_analyzed' => 'array',
        'violations_count' => 'integer',
        'critical_count' => 'integer',
        'llm_tokens_used' => 'integer',
        'llm_failed' => 'boolean',
        'duration_ms' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'pending',
        'violations_count' => 0,
        'critical_count' => 0,
        'llm_failed' => false,
    ];

    // Status constants
    public const STATUS_PENDING = 'pending';

    public const STATUS_ANALYZING = 'analyzing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /**
     * Get the deployment this review belongs to.
     */
    public function deployment(): BelongsTo
    {
        return $this->belongsTo(ApplicationDeploymentQueue::class, 'deployment_id');
    }

    /**
     * Get the application this review belongs to.
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Get all violations for this review.
     */
    public function violations(): HasMany
    {
        return $this->hasMany(CodeReviewViolation::class);
    }

    /**
     * Scope: only completed reviews.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope: only pending reviews.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope: only failed reviews.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope: reviews with violations.
     */
    public function scopeWithViolations(Builder $query): Builder
    {
        return $query->where('violations_count', '>', 0);
    }

    /**
     * Scope: reviews with critical violations.
     */
    public function scopeWithCritical(Builder $query): Builder
    {
        return $query->where('critical_count', '>', 0);
    }

    /**
     * Check if review is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if review is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if review failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if review is in progress.
     */
    public function isAnalyzing(): bool
    {
        return $this->status === self::STATUS_ANALYZING;
    }

    /**
     * Check if review found any issues.
     */
    public function hasViolations(): bool
    {
        return $this->violations_count > 0;
    }

    /**
     * Check if review found critical issues.
     */
    public function hasCriticalViolations(): bool
    {
        return $this->critical_count > 0;
    }

    /**
     * Get status badge color for UI.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_COMPLETED => $this->hasCriticalViolations() ? 'red' : ($this->hasViolations() ? 'yellow' : 'green'),
            self::STATUS_ANALYZING => 'blue',
            self::STATUS_FAILED => 'gray',
            default => 'gray',
        };
    }

    /**
     * Get status label for UI.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_ANALYZING => 'Analyzing',
            self::STATUS_COMPLETED => $this->hasCriticalViolations() ? 'Critical Issues' : ($this->hasViolations() ? 'Issues Found' : 'Passed'),
            self::STATUS_FAILED => 'Failed',
            default => 'Unknown',
        };
    }

    /**
     * Get violations grouped by severity.
     */
    public function getViolationsBySeverity(): array
    {
        return $this->violations
            ->groupBy('severity')
            ->map(fn ($items) => $items->count())
            ->toArray();
    }

    /**
     * Get critical violations only.
     */
    public function getCriticalViolations()
    {
        return $this->violations()->where('severity', 'critical')->get();
    }

    /**
     * Mark review as started.
     */
    public function markAsAnalyzing(): void
    {
        $this->update([
            'status' => self::STATUS_ANALYZING,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark review as completed.
     */
    public function markAsCompleted(array $data = []): void
    {
        $this->update(array_merge([
            'status' => self::STATUS_COMPLETED,
            'finished_at' => now(),
            'duration_ms' => $this->calculateDurationMs(),
        ], $data));
    }

    /**
     * Mark review as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'finished_at' => now(),
            'duration_ms' => $this->calculateDurationMs(),
        ]);
    }

    /**
     * Calculate duration in milliseconds as integer.
     */
    private function calculateDurationMs(): ?int
    {
        if (! $this->started_at) {
            return null;
        }

        $durationMs = now()->diffInMilliseconds($this->started_at, false);

        // Ensure non-negative integer
        return max(0, (int) abs($durationMs));
    }
}
