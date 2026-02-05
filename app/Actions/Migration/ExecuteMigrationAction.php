<?php

namespace App\Actions\Migration;

use App\Actions\Transfer\CloneApplicationAction;
use App\Actions\Transfer\CloneServiceAction;
use App\Models\Application;
use App\Models\Environment;
use App\Models\EnvironmentMigration;
use App\Models\EnvironmentVariable;
use App\Models\LocalFileVolume;
use App\Models\LocalPersistentVolume;
use App\Models\MigrationHistory;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Action to execute an approved migration.
 * Handles cloning, promoting, or updating resources in the target environment.
 *
 * Migration Modes:
 * - Clone: Creates a new copy of the resource in target environment (includes env vars, volumes)
 * - Promote: Updates existing resource config in target environment (excludes env vars, rewires connections)
 */
class ExecuteMigrationAction
{
    use AsAction;

    /**
     * Execute the migration.
     *
     * @return array{success: bool, target?: Model, error?: string}
     */
    public function handle(EnvironmentMigration $migration): array
    {
        // Mark as in progress
        $migration->markAsInProgress('Initializing migration...');

        try {
            $source = $migration->source;
            $targetEnv = $migration->targetEnvironment;
            $targetServer = $migration->targetServer;
            $options = $migration->options ?? [];

            if (! $source) {
                throw new \RuntimeException('Source resource not found.');
            }

            // Check migration mode
            $mode = $options[EnvironmentMigration::OPTION_MODE] ?? EnvironmentMigration::MODE_CLONE;

            // Promote mode: update existing resource config without copying env vars
            if ($mode === EnvironmentMigration::MODE_PROMOTE) {
                $result = PromoteResourceAction::run($migration);

                if (! $result['success']) {
                    $migration->markAsFailed($result['error'] ?? 'Promote failed');

                    return $result;
                }

                $target = $result['target'];

                // Create history entry
                MigrationHistory::createForResource(
                    $target,
                    $migration,
                    $this->getResourceConfig($target)
                );

                $migration->markAsCompleted(get_class($target), $target->id);

                return [
                    'success' => true,
                    'target' => $target,
                    'rewired_connections' => $result['rewired_connections'] ?? [],
                ];
            }

            // Clone mode (default): Determine if we're updating existing or creating new
            $updateExisting = $options[EnvironmentMigration::OPTION_UPDATE_EXISTING] ?? false;
            $configOnly = $options[EnvironmentMigration::OPTION_CONFIG_ONLY] ?? false;

            if ($updateExisting || $configOnly) {
                $result = $this->updateExistingResource($migration, $source, $targetEnv, $options);
            } else {
                $result = $this->cloneResource($migration, $source, $targetEnv, $targetServer, $options);
            }

            if (! $result['success']) {
                $migration->markAsFailed($result['error'] ?? 'Unknown error');

                return $result;
            }

            $target = $result['target'];

            // Create history entry for target
            MigrationHistory::createForResource(
                $target,
                $migration,
                $this->getResourceConfig($target)
            );

            // Mark as completed
            $migration->markAsCompleted(get_class($target), $target->id);

            return [
                'success' => true,
                'target' => $target,
            ];

        } catch (\Throwable $e) {
            $migration->markAsFailed($e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Clone the resource to target environment.
     */
    protected function cloneResource(
        EnvironmentMigration $migration,
        Model $source,
        Environment $targetEnv,
        Server $targetServer,
        array $options
    ): array {
        $migration->updateProgress(10, 'Preparing to clone resource...');

        if ($source instanceof Application) {
            return $this->cloneApplication($migration, $source, $targetEnv, $targetServer, $options);
        }

        if ($source instanceof Service) {
            return $this->cloneService($migration, $source, $targetEnv, $targetServer, $options);
        }

        if ($this->isDatabase($source)) {
            return $this->cloneDatabase($migration, $source, $targetEnv, $targetServer, $options);
        }

        return [
            'success' => false,
            'error' => 'Unsupported resource type for migration: '.get_class($source),
        ];
    }

    /**
     * Clone an application.
     */
    protected function cloneApplication(
        EnvironmentMigration $migration,
        Application $source,
        Environment $targetEnv,
        Server $targetServer,
        array $options
    ): array {
        $migration->updateProgress(20, 'Cloning application...');

        $cloneOptions = [
            'copyEnvVars' => $options[EnvironmentMigration::OPTION_COPY_ENV_VARS] ?? true,
            'copyVolumes' => $options[EnvironmentMigration::OPTION_COPY_VOLUMES] ?? true,
            'copyScheduledTasks' => true,
            'copyTags' => true,
            'instantDeploy' => false,
            'newName' => $source->name, // Keep original name — environment label distinguishes
        ];

        $result = CloneApplicationAction::run($source, $targetEnv, $targetServer, $cloneOptions);

        $migration->updateProgress(80, 'Application cloned successfully');

        if ($result['success']) {
            return [
                'success' => true,
                'target' => $result['application'],
            ];
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'Failed to clone application',
        ];
    }

    /**
     * Clone a service.
     */
    protected function cloneService(
        EnvironmentMigration $migration,
        Service $source,
        Environment $targetEnv,
        Server $targetServer,
        array $options
    ): array {
        $migration->updateProgress(20, 'Cloning service...');

        $cloneOptions = [
            'copyEnvVars' => $options[EnvironmentMigration::OPTION_COPY_ENV_VARS] ?? true,
            'copyVolumes' => $options[EnvironmentMigration::OPTION_COPY_VOLUMES] ?? true,
            'copyScheduledTasks' => true,
            'copyTags' => true,
            'instantDeploy' => false,
            'newName' => $source->name, // Keep original name — environment label distinguishes
        ];

        $result = CloneServiceAction::run($source, $targetEnv, $targetServer, $cloneOptions);

        $migration->updateProgress(80, 'Service cloned successfully');

        if ($result['success']) {
            return [
                'success' => true,
                'target' => $result['service'],
            ];
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'Failed to clone service',
        ];
    }

    /**
     * Clone a database.
     */
    protected function cloneDatabase(
        EnvironmentMigration $migration,
        Model $source,
        Environment $targetEnv,
        Server $targetServer,
        array $options
    ): array {
        $migration->updateProgress(20, 'Cloning database configuration...');

        // Use the CloneDatabaseAction
        $result = CloneDatabaseAction::run($source, $targetEnv, $targetServer, $options);

        $migration->updateProgress(80, 'Database cloned successfully');

        return $result;
    }

    /**
     * Update an existing resource in target environment.
     * If resource doesn't exist, falls back to cloning.
     */
    protected function updateExistingResource(
        EnvironmentMigration $migration,
        Model $source,
        Environment $targetEnv,
        array $options
    ): array {
        $migration->updateProgress(20, 'Finding existing resource...');

        $existingTarget = $this->findExistingTarget($source, $targetEnv);

        if (! $existingTarget) {
            // Resource doesn't exist in target - fall back to cloning
            $migration->appendLog('Resource not found in target environment, creating new copy...');

            $targetServer = $migration->targetServer;
            if (! $targetServer) {
                return [
                    'success' => false,
                    'error' => 'Target server not specified for migration.',
                ];
            }

            return $this->cloneResource($migration, $source, $targetEnv, $targetServer, $options);
        }

        $migration->updateProgress(30, 'Updating resource configuration...');

        $configOnly = $options[EnvironmentMigration::OPTION_CONFIG_ONLY] ?? false;

        if ($configOnly) {
            // Only update configuration, don't touch volumes or recreate container
            return $this->updateConfigOnly($migration, $source, $existingTarget, $options);
        }

        // Full update: sync all configuration
        return $this->updateFullResource($migration, $source, $existingTarget, $options);
    }

    /**
     * Update only configuration (for production databases).
     */
    protected function updateConfigOnly(
        EnvironmentMigration $migration,
        Model $source,
        Model $target,
        array $options
    ): array {
        $migration->updateProgress(40, 'Updating configuration...');

        // Get safe attributes to update (exclude data-related and identity fields)
        $safeAttributes = $this->getSafeConfigAttributes($source);

        // Update target with safe attributes
        $target->update($safeAttributes);

        // Update environment variables if requested
        if ($options[EnvironmentMigration::OPTION_COPY_ENV_VARS] ?? true) {
            $migration->updateProgress(60, 'Syncing environment variables...');
            $this->syncEnvironmentVariables($source, $target);
        }

        $migration->updateProgress(80, 'Configuration updated');

        return [
            'success' => true,
            'target' => $target->fresh(),
        ];
    }

    /**
     * Full resource update (sync everything except data volumes).
     */
    protected function updateFullResource(
        EnvironmentMigration $migration,
        Model $source,
        Model $target,
        array $options
    ): array {
        $migration->updateProgress(40, 'Updating full resource...');

        // Get all attributes to update (excluding identity fields)
        $attributes = $this->getUpdatableAttributes($source);
        $target->update($attributes);

        // Sync environment variables
        if ($options[EnvironmentMigration::OPTION_COPY_ENV_VARS] ?? true) {
            $migration->updateProgress(50, 'Syncing environment variables...');
            $this->syncEnvironmentVariables($source, $target);
        }

        // Sync volume configurations (but not data!)
        if ($options[EnvironmentMigration::OPTION_COPY_VOLUMES] ?? true) {
            $migration->updateProgress(60, 'Syncing volume configurations...');
            $this->syncVolumeConfigurations($source, $target);
        }

        $migration->updateProgress(80, 'Resource updated');

        return [
            'success' => true,
            'target' => $target->fresh(),
        ];
    }

    /**
     * Get safe configuration attributes (for config_only mode).
     */
    protected function getSafeConfigAttributes(Model $source): array
    {
        // Fields to exclude from config-only update
        $excludeFields = [
            'id', 'uuid', 'created_at', 'updated_at', 'deleted_at',
            'environment_id', 'destination_id', 'destination_type',
            'status', 'last_online_at', 'name',
            // Don't update volume-related fields in config-only mode
            'host_path', 'mount_path',
        ];

        return collect($source->getAttributes())
            ->except($excludeFields)
            ->filter(fn ($value) => $value !== null)
            ->toArray();
    }

    /**
     * Get updatable attributes for full update.
     */
    protected function getUpdatableAttributes(Model $source): array
    {
        // Fields to exclude from update
        $excludeFields = [
            'id', 'uuid', 'created_at', 'updated_at', 'deleted_at',
            'environment_id', 'destination_id', 'destination_type',
            'status', 'last_online_at',
        ];

        return collect($source->getAttributes())
            ->except($excludeFields)
            ->filter(fn ($value) => $value !== null)
            ->toArray();
    }

    /**
     * Sync environment variables from source to target.
     *
     * Full sync: adds new vars with values, updates existing, removes
     * vars that no longer exist in source. This ensures target env
     * matches source exactly (safe for dev→uat→prod migrations).
     */
    protected function syncEnvironmentVariables(Model $source, Model $target): void
    {
        if (! method_exists($source, 'environment_variables')) {
            return;
        }

        $sourceVars = $source->environment_variables;
        $targetVars = $target->environment_variables;
        $sourceKeys = $sourceVars->pluck('key')->toArray();

        // 1. Delete vars that no longer exist in source
        foreach ($targetVars as $targetVar) {
            if (! in_array($targetVar->key, $sourceKeys)) {
                $targetVar->delete();
            }
        }

        // 2. Add new or update existing vars (with values!)
        foreach ($sourceVars as $sourceVar) {
            $existingVar = $targetVars->firstWhere('key', $sourceVar->key);

            if ($existingVar) {
                $existingVar->update([
                    'value' => $sourceVar->value,
                    'is_buildtime' => $sourceVar->is_buildtime,
                    'is_literal' => $sourceVar->is_literal,
                    'is_multiline' => $sourceVar->is_multiline,
                ]);
            } else {
                EnvironmentVariable::create([
                    'key' => $sourceVar->key,
                    'value' => $sourceVar->value,
                    'is_buildtime' => $sourceVar->is_buildtime ?? false,
                    'is_literal' => $sourceVar->is_literal ?? false,
                    'is_multiline' => $sourceVar->is_multiline ?? false,
                    'is_preview' => $sourceVar->is_preview ?? false,
                    'is_required' => $sourceVar->is_required ?? false,
                    'resourceable_type' => get_class($target),
                    'resourceable_id' => $target->id,
                ]);
            }
        }
    }

    /**
     * Sync volume configurations (not data!) from source to target.
     */
    protected function syncVolumeConfigurations(Model $source, Model $target): void
    {
        // Sync persistent storages configuration
        if (method_exists($source, 'persistentStorages') && method_exists($target, 'persistentStorages')) {
            $sourcePersistent = $source->persistentStorages;
            $targetPersistent = $target->persistentStorages;

            foreach ($sourcePersistent as $sourceStorage) {
                $existingStorage = $targetPersistent->firstWhere('mount_path', $sourceStorage->mount_path);

                if (! $existingStorage) {
                    // Create new storage config (not copying actual data!)
                    $newName = str_replace($source->uuid, $target->uuid, $sourceStorage->name);

                    LocalPersistentVolume::create([
                        'name' => $newName,
                        'mount_path' => $sourceStorage->mount_path,
                        'host_path' => $sourceStorage->host_path,
                        'resource_type' => get_class($target),
                        'resource_id' => $target->id,
                    ]);
                }
            }
        }

        // Sync file storages
        if (method_exists($source, 'fileStorages') && method_exists($target, 'fileStorages')) {
            $sourceFiles = $source->fileStorages;
            $targetFiles = $target->fileStorages;

            foreach ($sourceFiles as $sourceFile) {
                $existingFile = $targetFiles->firstWhere('mount_path', $sourceFile->mount_path);

                if (! $existingFile) {
                    LocalFileVolume::create([
                        'fs_path' => $sourceFile->fs_path,
                        'mount_path' => $sourceFile->mount_path,
                        'content' => $sourceFile->content,
                        'is_directory' => $sourceFile->is_directory,
                        'resource_type' => get_class($target),
                        'resource_id' => $target->id,
                    ]);
                } else {
                    // Update content
                    $existingFile->update([
                        'content' => $sourceFile->content,
                    ]);
                }
            }
        }
    }

    /**
     * Find existing resource in target environment.
     */
    protected function findExistingTarget(Model $source, Environment $targetEnv): ?Model
    {
        $name = $source->name ?? null;
        if (! $name) {
            return null;
        }

        if ($source instanceof Application) {
            return $targetEnv->applications()->where('name', $name)->first();
        }

        if ($source instanceof Service) {
            return $targetEnv->services()->where('name', $name)->first();
        }

        // For databases
        $relationMethod = $this->getDatabaseRelationMethod(get_class($source));
        if ($relationMethod && method_exists($targetEnv, $relationMethod)) {
            return $targetEnv->$relationMethod()->where('name', $name)->first();
        }

        return null;
    }

    /**
     * Check if resource is a database.
     */
    protected function isDatabase(Model $resource): bool
    {
        $databaseClasses = [
            'App\Models\StandalonePostgresql',
            'App\Models\StandaloneMysql',
            'App\Models\StandaloneMariadb',
            'App\Models\StandaloneMongodb',
            'App\Models\StandaloneRedis',
            'App\Models\StandaloneClickhouse',
            'App\Models\StandaloneKeydb',
            'App\Models\StandaloneDragonfly',
        ];

        return in_array(get_class($resource), $databaseClasses);
    }

    /**
     * Get the relation method for a database class.
     */
    protected function getDatabaseRelationMethod(string $class): ?string
    {
        $map = [
            'App\Models\StandalonePostgresql' => 'postgresqls',
            'App\Models\StandaloneMysql' => 'mysqls',
            'App\Models\StandaloneMariadb' => 'mariadbs',
            'App\Models\StandaloneMongodb' => 'mongodbs',
            'App\Models\StandaloneRedis' => 'redis',
            'App\Models\StandaloneClickhouse' => 'clickhouses',
            'App\Models\StandaloneKeydb' => 'keydbs',
            'App\Models\StandaloneDragonfly' => 'dragonflies',
        ];

        return $map[$class] ?? null;
    }

    /**
     * Get resource configuration for history.
     */
    protected function getResourceConfig(Model $resource): array
    {
        return [
            'type' => get_class($resource),
            'id' => $resource->id,
            'attributes' => $resource->toArray(),
        ];
    }
}
