<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Represents a link between two resources (e.g., Application -> Database).
 * Used for automatic DATABASE_URL injection and visual canvas connections.
 */
class ResourceLink extends Model
{
    protected $guarded = [];

    protected $casts = [
        'auto_inject' => 'boolean',
    ];

    /**
     * Get the source resource (usually an Application).
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the target resource (usually a Database).
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the environment this link belongs to.
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    /**
     * Get the default env variable key based on target type.
     */
    public static function getDefaultEnvKey(string $targetClass): string
    {
        return match ($targetClass) {
            StandalonePostgresql::class, StandaloneMysql::class, StandaloneMariadb::class => 'DATABASE_URL',
            StandaloneRedis::class, StandaloneKeydb::class, StandaloneDragonfly::class => 'REDIS_URL',
            StandaloneMongodb::class => 'MONGODB_URL',
            StandaloneClickhouse::class => 'CLICKHOUSE_URL',
            default => 'CONNECTION_URL',
        };
    }

    /**
     * Get the env key to use for injection.
     */
    public function getEnvKey(): string
    {
        return $this->inject_as ?? self::getDefaultEnvKey($this->target_type);
    }

    /**
     * Check if target has internal_db_url attribute.
     */
    public function targetHasInternalUrl(): bool
    {
        if (! $this->target) {
            return false;
        }

        return method_exists($this->target, 'getInternalDbUrlAttribute')
            || isset($this->target->internal_db_url);
    }
}
