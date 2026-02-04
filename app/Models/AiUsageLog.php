<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $team_id
 * @property int|null $message_id
 * @property string $provider
 * @property string $model
 * @property string $operation
 * @property int $input_tokens
 * @property int $output_tokens
 * @property float $cost_usd
 * @property int|null $response_time_ms
 * @property bool $success
 * @property string|null $error_message
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read User $user
 * @property-read Team $team
 * @property-read AiChatMessage|null $message
 */
class AiUsageLog extends Model
{
    protected $fillable = [
        'user_id',
        'team_id',
        'message_id',
        'provider',
        'model',
        'operation',
        'input_tokens',
        'output_tokens',
        'cost_usd',
        'response_time_ms',
        'success',
        'error_message',
    ];

    protected $casts = [
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'cost_usd' => 'decimal:6',
        'response_time_ms' => 'integer',
        'success' => 'boolean',
    ];

    protected $attributes = [
        'input_tokens' => 0,
        'output_tokens' => 0,
        'cost_usd' => 0,
        'success' => true,
    ];

    /**
     * User who made the request.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Team the usage belongs to.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Associated chat message.
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(AiChatMessage::class, 'message_id');
    }

    /**
     * Get total tokens used.
     */
    public function getTotalTokensAttribute(): int
    {
        return $this->input_tokens + $this->output_tokens;
    }

    /**
     * Check if request was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->success;
    }

    /**
     * Scope to successful requests.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('success', true);
    }

    /**
     * Scope to failed requests.
     */
    public function scopeFailed($query)
    {
        return $query->where('success', false);
    }

    /**
     * Scope to specific provider.
     */
    public function scopeForProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope to specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to specific team.
     */
    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Scope to date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Calculate total cost for a query.
     */
    public static function calculateTotalCost($query): float
    {
        return (float) $query->sum('cost_usd');
    }

    /**
     * Get usage statistics for a team.
     */
    public static function getTeamStats(int $teamId, ?string $period = '30d'): array
    {
        $startDate = match ($period) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            default => now()->subDays(30),
        };

        $query = static::forTeam($teamId)->where('created_at', '>=', $startDate);

        return [
            'total_requests' => $query->count(),
            'successful_requests' => (clone $query)->successful()->count(),
            'failed_requests' => (clone $query)->failed()->count(),
            'total_tokens' => (clone $query)->sum(\DB::raw('input_tokens + output_tokens')),
            'total_cost_usd' => (float) (clone $query)->sum('cost_usd'),
            'avg_response_time_ms' => (float) (clone $query)->avg('response_time_ms'),
            'by_provider' => (clone $query)
                ->selectRaw('provider, COUNT(*) as count, SUM(cost_usd) as total_cost')
                ->groupBy('provider')
                ->get()
                ->keyBy('provider')
                ->toArray(),
        ];
    }

    /**
     * Get global usage statistics (for admin panel).
     */
    public static function getGlobalStats(?string $period = '30d'): array
    {
        $startDate = match ($period) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            default => now()->subDays(30),
        };

        $query = static::where('created_at', '>=', $startDate);

        return [
            'totalRequests' => (clone $query)->count(),
            'successfulRequests' => (clone $query)->successful()->count(),
            'failedRequests' => (clone $query)->failed()->count(),
            'totalTokens' => (int) (clone $query)->sum(\DB::raw('input_tokens + output_tokens')),
            'totalCostUsd' => (float) (clone $query)->sum('cost_usd'),
            'avgResponseTimeMs' => (float) (clone $query)->avg('response_time_ms') ?: 0,
        ];
    }
}
