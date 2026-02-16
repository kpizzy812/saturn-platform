<?php

namespace App\Actions\Migration;

use App\Models\Environment;
use App\Models\EnvironmentMigration;
use App\Models\EnvironmentVariable;
use App\Models\LocalPersistentVolume;
use App\Models\Server;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;
use Visus\Cuid2\Cuid2;

/**
 * Action to clone a database to a different environment/server.
 * IMPORTANT: For production migrations, only configuration is cloned, NOT data.
 */
class CloneDatabaseAction
{
    use AsAction;

    /**
     * Clone a database to a different environment/server.
     *
     * @param  Model  $sourceDatabase  The database to clone
     * @param  array  $options  Clone options: copy_env_vars, copy_volumes, config_only
     * @return array{success: bool, target?: Model, error?: string}
     */
    public function handle(
        Model $sourceDatabase,
        Environment $targetEnvironment,
        Server $targetServer,
        array $options = []
    ): array {
        // Default options
        $options = array_merge([
            EnvironmentMigration::OPTION_COPY_ENV_VARS => true,
            EnvironmentMigration::OPTION_COPY_VOLUMES => true,
            EnvironmentMigration::OPTION_CONFIG_ONLY => false,
        ], $options);

        // Validate server is functional
        if (! $targetServer->isFunctional()) {
            return [
                'success' => false,
                'error' => 'Target server is not functional.',
            ];
        }

        // Get destination (StandaloneDocker) from server
        $destinations = $targetServer->destinations();
        if ($destinations->count() === 0) {
            return [
                'success' => false,
                'error' => 'Target server has no Docker destinations configured.',
            ];
        }
        $destination = $destinations->first();

        try {
            // Create new UUID for the cloned database
            $newUuid = (string) new Cuid2;

            // Clone the database
            $clonedDatabase = $this->cloneDatabase(
                $sourceDatabase,
                $targetEnvironment,
                $destination,
                $newUuid,
                $options
            );

            // Clone environment variables
            if ($options[EnvironmentMigration::OPTION_COPY_ENV_VARS]) {
                $this->cloneEnvironmentVariables($sourceDatabase, $clonedDatabase);
            }

            // Clone volume configurations (not data!)
            // Note: Volume data is NOT copied - only the volume configuration
            if ($options[EnvironmentMigration::OPTION_COPY_VOLUMES]) {
                $this->cloneVolumeConfigurations($sourceDatabase, $clonedDatabase);
            }

            // Clone tags
            $this->cloneTags($sourceDatabase, $clonedDatabase);

            return [
                'success' => true,
                'target' => $clonedDatabase,
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Clone the main database record.
     */
    protected function cloneDatabase(
        Model $source,
        Environment $environment,
        $destination,
        string $newUuid,
        array $options
    ): Model {
        // Fields to exclude from cloning
        $excludeFields = [
            'id',
            'uuid',
            'status',
            'created_at',
            'updated_at',
            'deleted_at',
            'environment_id',
            'destination_id',
            'destination_type',
            'last_online_at',
            'restart_count',
            'last_restart_at',
            'last_restart_type',
        ];

        // Get all attributes and filter out excluded ones
        $attributes = collect($source->getAttributes())
            ->except($excludeFields)
            ->toArray();

        // Set new values
        $attributes['uuid'] = $newUuid;
        $attributes['environment_id'] = $environment->id;
        $attributes['destination_id'] = $destination->id;
        $attributes['destination_type'] = $destination->getMorphClass();
        $attributes['status'] = 'exited'; // Start as stopped

        // Keep original name — environment label distinguishes (same as applications)
        $attributes['name'] = $source->getAttribute('name');

        // Reassign public_port to avoid conflicts on the same server
        if (! empty($attributes['public_port']) && ! empty($attributes['is_public'])) {
            $server = $destination->server;
            if ($server && isPublicPortAlreadyUsed($server, (int) $attributes['public_port'])) {
                $attributes['public_port'] = getRandomPublicPort($destination);
            }
        }

        // Create the cloned database
        $class = get_class($source);
        $clonedDatabase = new $class($attributes);
        $clonedDatabase->save();

        return $clonedDatabase;
    }

    /**
     * Clone environment variables.
     */
    protected function cloneEnvironmentVariables(Model $source, Model $target): void
    {
        if (! method_exists($source, 'environment_variables')) {
            return;
        }

        foreach ($source->environment_variables as $envVar) {
            EnvironmentVariable::create([
                'key' => $envVar->key,
                'value' => $envVar->value,
                'is_buildtime' => $envVar->is_buildtime ?? false,
                'is_literal' => $envVar->is_literal ?? false,
                'is_multiline' => $envVar->is_multiline ?? false,
                'is_preview' => false,
                'is_required' => $envVar->is_required ?? false,
                'resourceable_type' => get_class($target),
                'resourceable_id' => $target->getAttribute('id'),
            ]);
        }
    }

    /**
     * Clone volume configurations (NOT data!).
     * Creates volume entries with new names but does not copy any data.
     */
    protected function cloneVolumeConfigurations(Model $source, Model $target): void
    {
        if (! method_exists($source, 'persistentStorages')) {
            return;
        }

        foreach ($source->persistentStorages as $storage) {
            // Generate new volume name with target UUID
            $newName = str_replace($source->getAttribute('uuid'), $target->getAttribute('uuid'), $storage->name);

            // Check if volume was already created by model boot event
            $existingVolume = LocalPersistentVolume::where('resource_type', get_class($target))
                ->where('resource_id', $target->getAttribute('id'))
                ->where('mount_path', $storage->mount_path)
                ->first();

            if ($existingVolume) {
                // Update existing volume configuration
                $existingVolume->update([
                    'name' => $newName,
                    'host_path' => $storage->host_path,
                ]);
            } else {
                // Create new volume configuration
                LocalPersistentVolume::create([
                    'name' => $newName,
                    'mount_path' => $storage->mount_path,
                    'host_path' => $storage->host_path,
                    'resource_type' => get_class($target),
                    'resource_id' => $target->getAttribute('id'),
                ]);
            }
        }
    }

    /**
     * Clone tags.
     */
    protected function cloneTags(Model $source, Model $target): void
    {
        if (! method_exists($source, 'tags') || ! method_exists($target, 'tags')) {
            return;
        }

        $tagIds = $source->tags->pluck('id')->toArray();
        if (! empty($tagIds)) {
            $target->tags()->attach($tagIds);
        }
    }

    /**
     * Get the database type name for display.
     */
    public static function getDatabaseTypeName(Model $database): string
    {
        $class = class_basename(get_class($database));

        return match ($class) {
            'StandalonePostgresql' => 'PostgreSQL',
            'StandaloneMysql' => 'MySQL',
            'StandaloneMariadb' => 'MariaDB',
            'StandaloneMongodb' => 'MongoDB',
            'StandaloneRedis' => 'Redis',
            'StandaloneClickhouse' => 'ClickHouse',
            'StandaloneKeydb' => 'KeyDB',
            'StandaloneDragonfly' => 'Dragonfly',
            default => $class,
        };
    }
}
