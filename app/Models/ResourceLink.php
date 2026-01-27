<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Represents a link between two resources (e.g., Application -> Database or Application -> Application).
 * Used for automatic URL injection and visual canvas connections.
 */
class ResourceLink extends Model
{
    protected $guarded = [];

    protected $casts = [
        'auto_inject' => 'boolean',
        'use_external_url' => 'boolean',
    ];

    /**
     * Get the source resource (usually an Application).
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the target resource (Database or Application).
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
            Application::class => 'APP_URL',
            default => 'CONNECTION_URL',
        };
    }

    /**
     * Get the env key to use for injection (for database targets).
     */
    public function getEnvKey(): string
    {
        return $this->inject_as ?? self::getDefaultEnvKey($this->target_type);
    }

    /**
     * Get a smart env key for app-to-app links based on target application name.
     * E.g., target "my-backend" -> "MY_BACKEND_URL"
     */
    public function getSmartAppEnvKey(): string
    {
        if ($this->inject_as) {
            return $this->inject_as;
        }

        if ($this->target_type === Application::class && $this->target) {
            $name = str($this->target->name)
                ->upper()
                ->replace(['-', ' ', '.'], '_')
                ->value();

            return "{$name}_URL";
        }

        return self::getDefaultEnvKey($this->target_type);
    }

    /**
     * Get the internal URL of the target resource (database or application).
     */
    public function getTargetInternalUrl(): ?string
    {
        if (! $this->target) {
            return null;
        }

        if (isset($this->target->internal_db_url)) {
            return $this->target->internal_db_url;
        }

        if (isset($this->target->internal_app_url)) {
            return $this->target->internal_app_url;
        }

        return null;
    }

    /**
     * Check if target has an internal URL attribute.
     */
    public function targetHasInternalUrl(): bool
    {
        if (! $this->target) {
            return false;
        }

        return method_exists($this->target, 'getInternalDbUrlAttribute')
            || isset($this->target->internal_db_url)
            || isset($this->target->internal_app_url);
    }
}
