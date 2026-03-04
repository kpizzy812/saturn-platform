<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $fillable = [
        'name',
        'token',
        'abilities',
        'expires_at',
        'team_id',
        'rate_limit_per_minute',
    ];

    protected $casts = [
        'rate_limit_per_minute' => 'integer',
    ];

    /**
     * Ability-based default rate limits (requests per minute).
     * Checked in priority order: first matching ability wins.
     */
    private const ABILITY_DEFAULTS = [
        'root' => 200,
        'write' => 30,
        'deploy' => 10,
        'read:sensitive' => 60,
        'read' => 120,
    ];

    /**
     * Resolve the effective rate limit for this token.
     *
     * Priority:
     * 1. Explicit per-token rate_limit_per_minute (if set)
     * 2. Ability-based default (highest-priority matching ability)
     * 3. Global config fallback
     */
    public function effectiveRateLimit(): int
    {
        if ($this->rate_limit_per_minute !== null) {
            return $this->rate_limit_per_minute;
        }

        $abilities = $this->abilities ?? [];

        if (in_array('*', $abilities, true)) {
            return self::ABILITY_DEFAULTS['root'];
        }

        foreach (self::ABILITY_DEFAULTS as $ability => $limit) {
            if (in_array($ability, $abilities, true)) {
                return $limit;
            }
        }

        return (int) config('api.token_rate_limit', 60);
    }
}
