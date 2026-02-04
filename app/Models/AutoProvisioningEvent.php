<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutoProvisioningEvent extends Model
{
    /**
     * SECURITY: Using $fillable instead of $guarded = [] to prevent mass assignment vulnerabilities.
     * Excludes: id, status (system-managed), triggered_at, provisioned_at, ready_at (system-managed)
     */
    protected $fillable = [
        'team_id',
        'trigger_server_id',
        'provisioned_server_id',
        'trigger_reason',
        'trigger_metrics',
        'provider_server_id',
        'server_config',
        'error_message',
    ];

    protected $casts = [
        'trigger_metrics' => 'array',
        'server_config' => 'array',
        'triggered_at' => 'datetime',
        'provisioned_at' => 'datetime',
        'ready_at' => 'datetime',
    ];

    // Status constants
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROVISIONING = 'provisioning';

    public const STATUS_INSTALLING = 'installing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    // Trigger reason constants
    public const TRIGGER_CPU_CRITICAL = 'cpu_critical';

    public const TRIGGER_MEMORY_CRITICAL = 'memory_critical';

    public const TRIGGER_MANUAL = 'manual';

    /**
     * The server that triggered the auto-provisioning (overloaded server).
     */
    public function triggerServer(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'trigger_server_id');
    }

    /**
     * The newly provisioned server.
     */
    public function provisionedServer(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'provisioned_server_id');
    }

    /**
     * The team that owns this event.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Scope for pending events.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for events in progress (pending, provisioning, or installing).
     */
    public function scopeInProgress($query)
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING,
            self::STATUS_PROVISIONING,
            self::STATUS_INSTALLING,
        ]);
    }

    /**
     * Scope for today's events.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('triggered_at', today());
    }

    /**
     * Scope for a specific team.
     */
    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Mark as provisioning (VPS creation started).
     */
    public function markAsProvisioning(?string $providerServerId = null): void
    {
        $this->update([
            'status' => self::STATUS_PROVISIONING,
            'provider_server_id' => $providerServerId,
        ]);
    }

    /**
     * Mark as installing (Docker installation started).
     */
    public function markAsInstalling(int $provisionedServerId): void
    {
        $this->update([
            'status' => self::STATUS_INSTALLING,
            'provisioned_server_id' => $provisionedServerId,
            'provisioned_at' => now(),
        ]);
    }

    /**
     * Mark as ready (server is fully configured).
     */
    public function markAsReady(): void
    {
        $this->update([
            'status' => self::STATUS_READY,
            'ready_at' => now(),
        ]);
    }

    /**
     * Mark as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Check if there's an active provisioning in progress.
     */
    public static function hasActiveProvisioning(): bool
    {
        return self::inProgress()->exists();
    }

    /**
     * Get the count of servers provisioned today.
     */
    public static function countProvisionedToday(): int
    {
        return self::today()
            ->whereIn('status', [self::STATUS_READY, self::STATUS_PROVISIONING, self::STATUS_INSTALLING])
            ->count();
    }

    /**
     * Get human-readable trigger reason.
     */
    public function getTriggerReasonLabelAttribute(): string
    {
        return match ($this->trigger_reason) {
            self::TRIGGER_CPU_CRITICAL => 'CPU Overload',
            self::TRIGGER_MEMORY_CRITICAL => 'Memory Overload',
            self::TRIGGER_MANUAL => 'Manual Request',
            default => $this->trigger_reason,
        };
    }

    /**
     * Get human-readable status.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROVISIONING => 'Creating VPS',
            self::STATUS_INSTALLING => 'Installing Docker',
            self::STATUS_READY => 'Ready',
            self::STATUS_FAILED => 'Failed',
            default => $this->status,
        };
    }
}
