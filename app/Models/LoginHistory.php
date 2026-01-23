<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Request;

class LoginHistory extends Model
{
    use HasFactory;

    protected $table = 'login_history';

    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'status',
        'location',
        'failure_reason',
        'logged_at',
    ];

    protected $casts = [
        'logged_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user associated with this login attempt.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record a login attempt.
     */
    public static function record(
        ?User $user,
        string $status = 'success',
        ?string $reason = null
    ): self {
        return self::create([
            'user_id' => $user?->id,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'status' => $status,
            'failure_reason' => $reason,
            'logged_at' => now(),
        ]);
    }

    /**
     * Scope a query to filter by user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to get successful logins.
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope a query to get failed logins.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope a query to get recent records.
     */
    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('logged_at', '>=', now()->subDays($days));
    }

    /**
     * Scope a query to order by most recent first.
     */
    public function scopeLatest(Builder $query): Builder
    {
        return $query->orderByDesc('logged_at');
    }

    /**
     * Clean up old records, keeping only the most recent N records per user.
     *
     * @param  int  $keepLast  Number of records to keep per user
     * @return int Number of deleted records
     */
    public static function cleanupOld(int $keepLast = 100): int
    {
        $deleted = 0;

        // Get all user IDs with login history
        $userIds = self::whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            // Get the IDs of records to keep
            $keepIds = self::where('user_id', $userId)
                ->orderByDesc('logged_at')
                ->limit($keepLast)
                ->pluck('id');

            // Delete records not in the keep list
            $deleted += self::where('user_id', $userId)
                ->whereNotIn('id', $keepIds)
                ->delete();
        }

        // Also clean up records without user_id (failed logins for non-existent users)
        // Keep only the last 1000 of those
        $nullUserKeepIds = self::whereNull('user_id')
            ->orderByDesc('logged_at')
            ->limit(1000)
            ->pluck('id');

        $deleted += self::whereNull('user_id')
            ->whereNotIn('id', $nullUserKeepIds)
            ->delete();

        return $deleted;
    }

    /**
     * Check if there were suspicious login attempts (multiple failures from different IPs).
     */
    public static function hasSuspiciousActivity(int $userId, int $threshold = 5, int $hours = 24): bool
    {
        return self::where('user_id', $userId)
            ->where('status', 'failed')
            ->where('logged_at', '>=', now()->subHours($hours))
            ->distinct('ip_address')
            ->count('ip_address') >= $threshold;
    }
}
