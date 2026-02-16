<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Model for tracking resource configuration versions during migrations.
 *
 * @property int $id
 * @property string $resource_type
 * @property int $resource_id
 * @property int|null $environment_migration_id
 * @property string|null $version_hash
 * @property array|null $config_snapshot
 * @property string|null $source_environment_type
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model|null $resource
 * @property-read EnvironmentMigration|null $environmentMigration
 */
class MigrationHistory extends Model
{
    protected $table = 'migration_history';

    /**
     * SECURITY: Using $fillable instead of $guarded = [] to prevent mass assignment vulnerabilities.
     * Excludes only: id (auto-increment)
     * Note: Polymorphic relationship fields (resource_type, resource_id) must be fillable
     * to allow createForResource() to set them via mass assignment.
     */
    protected $fillable = [
        'resource_type',
        'resource_id',
        'environment_migration_id',
        'version_hash',
        'config_snapshot',
        'source_environment_type',
    ];

    protected $casts = [
        'config_snapshot' => 'array',
    ];

    /**
     * The resource this history entry is for.
     */
    public function resource(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The migration that created this history entry.
     */
    public function environmentMigration(): BelongsTo
    {
        return $this->belongsTo(EnvironmentMigration::class, 'environment_migration_id');
    }

    /**
     * Create a version hash from config data.
     */
    public static function createVersionHash(array $config): string
    {
        // Sort keys for consistent hashing
        ksort($config);

        return hash('sha256', json_encode($config));
    }

    /**
     * Create a history entry for a resource.
     */
    public static function createForResource(
        Model $resource,
        EnvironmentMigration $migration,
        array $configSnapshot
    ): self {
        return self::create([
            'resource_type' => get_class($resource),
            'resource_id' => $resource->getAttribute('id'),
            'environment_migration_id' => $migration->id,
            'version_hash' => self::createVersionHash($configSnapshot),
            'config_snapshot' => $configSnapshot,
            'source_environment_type' => $migration->sourceEnvironment?->type,
        ]);
    }

    /**
     * Get the latest history entry for a resource.
     */
    public static function latestForResource(Model $resource): ?self
    {
        return self::where('resource_type', get_class($resource))
            ->where('resource_id', $resource->getAttribute('id'))
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Check if config has changed since last migration.
     */
    public static function hasConfigChanged(Model $resource, array $currentConfig): bool
    {
        $latest = self::latestForResource($resource);

        if (! $latest) {
            return true;
        }

        $currentHash = self::createVersionHash($currentConfig);

        return $latest->version_hash !== $currentHash;
    }

    /**
     * Get migration history for a resource.
     */
    public static function forResource(Model $resource)
    {
        return self::where('resource_type', get_class($resource))
            ->where('resource_id', $resource->getAttribute('id'))
            ->with('environmentMigration')
            ->orderByDesc('created_at');
    }
}
