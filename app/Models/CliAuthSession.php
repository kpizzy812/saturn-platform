<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $secret
 * @property string $status
 * @property int|null $user_id
 * @property int|null $team_id
 * @property string|null $token_plain
 * @property string $ip_address
 * @property string|null $user_agent
 * @property \Carbon\Carbon $expires_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read User|null $user
 * @property-read Team|null $team
 */
class CliAuthSession extends Model
{
    use MassPrunable;

    protected $fillable = [
        'code',
        'secret',
        'status',
        'user_id',
        'team_id',
        'token_plain',
        'ip_address',
        'user_agent',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'token_plain' => 'encrypted',
        ];
    }

    /**
     * Get the prunable model query.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        /** @var Builder<static> */
        return static::where('expires_at', '<', now()->subHour());
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this> */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Team, $this> */
    public function team(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
