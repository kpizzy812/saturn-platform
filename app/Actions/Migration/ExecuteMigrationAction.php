<?php

namespace App\Actions\Migration;

use App\Actions\Database\StartDatabase;
use App\Actions\Migration\Concerns\ResourceConfigFields;
use App\Actions\Service\StartService;
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
use Illuminate\Support\Facades\Log;
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
    use ResourceConfigFields;

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
                // Auto-backup before production promotion
                if ($targetEnv instanceof Environment && $targetEnv->isProduction()) {
                    $this->createPreMigrationBackup($migration, $source, $targetEnv);
                }

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

                $migration->markAsCompleted(get_class($target), $target->getAttribute('id'));

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
            $sourceEnv = $migration->sourceEnvironment;

            // Assign production domain if provided (first clone to production only)
            $fqdn = $options['fqdn'] ?? null;
            if ($fqdn && $target instanceof Application) {
                $migration->updateProgress(82, 'Assigning production domain...');
                $domainResult = AssignProductionDomainAction::run($target, $fqdn);
                if ($domainResult['success']) {
                    $migration->appendLog('Production domain assigned: '.$domainResult['fqdn']);
                } else {
                    Log::warning('Failed to assign production domain', [
                        'migration_id' => $migration->id,
                        'error' => $domainResult['error'] ?? 'Unknown error',
                    ]);
                    $migration->appendLog('Warning: Failed to assign production domain - '.$domainResult['error']);
                }
            }

            // Rotate credentials for databases cloned to production
            $rotatedCredentials = [];
            if ($targetEnv instanceof Environment && $targetEnv->isProduction() && RotateCredentialsAction::supportsRotation($target)) {
                $migration->updateProgress(83, 'Rotating database credentials for production...');
                $rotationResult = RotateCredentialsAction::run($target, $targetEnv);
                if ($rotationResult['success'] && ! empty($rotationResult['rotated_fields'])) {
                    $rotatedCredentials = $rotationResult['rotated_fields'];
                    $migration->appendLog('Database credentials rotated for production security ('.$rotationResult['updated_env_vars'].' env vars updated)');
                } elseif (! $rotationResult['success']) {
                    Log::warning('Credential rotation failed', [
                        'migration_id' => $migration->id,
                        'error' => $rotationResult['error'] ?? 'Unknown error',
                    ]);
                    $migration->appendLog('Warning: Credential rotation failed - '.$rotationResult['error']);
                }
            }

            // Rewire env var connections (replace source UUIDs with target UUIDs)
            $rewiredConnections = [];
            if (($options[EnvironmentMigration::OPTION_COPY_ENV_VARS] ?? true) && $sourceEnv) {
                $migration->updateProgress(85, 'Rewiring service connections...');
                $rewiredConnections = RewireConnectionsAction::run($target, $sourceEnv, $targetEnv);

                if (! empty($rewiredConnections)) {
                    $migration->appendLog('Rewired connections: '.implode(', ', array_keys($rewiredConnections)));
                }
            }

            // Clone ResourceLinks (architecture view connections)
            $clonedLinks = [];
            if ($sourceEnv) {
                $migration->updateProgress(90, 'Cloning resource links...');
                $clonedLinks = CloneResourceLinksAction::run($source, $target, $sourceEnv, $targetEnv);

                if (! empty($clonedLinks)) {
                    $linkNames = array_map(fn ($l) => $l['source'].' -> '.$l['target'], $clonedLinks);
                    $migration->appendLog('Cloned resource links: '.implode(', ', $linkNames));
                }
            }

            // Create history entry for target
            MigrationHistory::createForResource(
                $target,
                $migration,
                $this->getResourceConfig($target)
            );

            // Copy test data if requested (non-production only)
            $copyData = $options[EnvironmentMigration::OPTION_COPY_DATA] ?? false;
            if ($copyData && $this->isDatabase($source)) {
                if ($targetEnv instanceof Environment && $targetEnv->isProduction()) {
                    $migration->appendLog('Warning: Data copy to production is forbidden. Skipping.');
                } else {
                    $migration->updateProgress(86, 'Copying database data...');
                    $copyResult = CopyDatabaseDataAction::run($source, $target, $targetEnv, $migration);
                    if ($copyResult['success']) {
                        $migration->appendLog('Database data copied successfully.');
                    } else {
                        $migration->appendLog('Warning: Data copy failed - '.($copyResult['error'] ?? 'Unknown'));
                    }
                }
            }

            // Webhook reminder for first clone to production
            if ($mode === EnvironmentMigration::MODE_CLONE && $targetEnv instanceof Environment && $targetEnv->isProduction()) {
                if ($target instanceof Application && $target->getAttribute('source_id')) {
                    $migration->appendLog('Reminder: Configure webhook for production. Source repository webhooks point to the development environment and need manual reconfiguration for production deployments.');
                }
            }

            // Wait for health check if requested
            $waitForReady = $options[EnvironmentMigration::OPTION_WAIT_FOR_READY] ?? false;
            if ($waitForReady && $target instanceof Application && $target->health_check_enabled) {
                $migration->updateProgress(95, 'Waiting for health check to pass...');
                $healthResult = $this->waitForHealthCheck($target, $migration);
                if (! $healthResult['healthy']) {
                    $migration->appendLog('Warning: Health check did not pass within timeout. '.$healthResult['message']);
                } else {
                    $migration->appendLog('Health check passed successfully.');
                }
            }

            // Mark as completed
            $migration->markAsCompleted(get_class($target), $target->getAttribute('id'));

            return [
                'success' => true,
                'target' => $target,
                'rewired_connections' => $rewiredConnections,
                'cloned_links' => $clonedLinks,
                'rotated_credentials' => $rotatedCredentials,
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
            'instantDeploy' => $options[EnvironmentMigration::OPTION_AUTO_DEPLOY] ?? false,
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
            if ($options[EnvironmentMigration::OPTION_AUTO_DEPLOY] ?? false) {
                $migration->updateProgress(85, 'Starting service...');
                StartService::dispatch($result['service']);
            }

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

        if ($result['success'] && ($options[EnvironmentMigration::OPTION_AUTO_DEPLOY] ?? false)) {
            $migration->updateProgress(85, 'Starting database...');
            StartDatabase::dispatch($result['target']);
        }

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
            $overwrite = $options[EnvironmentMigration::OPTION_OVERWRITE_VALUES] ?? false;
            $this->syncEnvironmentVariables($source, $target, $overwrite);
        }

        $migration->updateProgress(80, 'Configuration updated');

        return [
            'success' => true,
            'target' => $target->fresh() ?? $target,
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

        // Get safe attributes to update using whitelist
        $attributes = $this->getUpdatableAttributes($source);
        $target->update($attributes);

        // Sync environment variables
        if ($options[EnvironmentMigration::OPTION_COPY_ENV_VARS] ?? true) {
            $migration->updateProgress(50, 'Syncing environment variables...');
            $overwrite = $options[EnvironmentMigration::OPTION_OVERWRITE_VALUES] ?? false;
            $this->syncEnvironmentVariables($source, $target, $overwrite);
        }

        // Sync volume configurations (but not data!)
        if ($options[EnvironmentMigration::OPTION_COPY_VOLUMES] ?? true) {
            $migration->updateProgress(60, 'Syncing volume configurations...');
            $this->syncVolumeConfigurations($source, $target);
        }

        $migration->updateProgress(80, 'Resource updated');

        return [
            'success' => true,
            'target' => $target->fresh() ?? $target,
        ];
    }

    /**
     * Get updatable attributes for full update using whitelist approach.
     * Only returns attributes from the config fields whitelist to prevent
     * accidental exposure of sensitive or identity fields.
     */
    protected function getUpdatableAttributes(Model $source): array
    {
        return $this->getSafeConfigAttributes($source);
    }

    /**
     * Sync environment variables from source to target using merge strategy.
     *
     * Merge behavior:
     * - Adds new variables from source that don't exist in target (with values)
     * - Does NOT delete target-only variables (e.g., SENTRY_DSN, PROD_API_KEY)
     * - For existing variables: updates only metadata (is_buildtime, etc.), NOT values
     * - When overwrite_values is true: also overwrites values of existing variables
     */
    protected function syncEnvironmentVariables(Model $source, Model $target, bool $overwriteValues = false): void
    {
        if (! method_exists($source, 'environment_variables')) {
            return;
        }

        /** @var \Illuminate\Support\Collection $sourceVars */
        $sourceVars = $source->getAttribute('environment_variables');
        /** @var \Illuminate\Support\Collection $targetVars */
        $targetVars = $target->getAttribute('environment_variables');

        // Target-only vars are intentionally preserved (not deleted)

        foreach ($sourceVars as $sourceVar) {
            $existingVar = $targetVars->firstWhere('key', $sourceVar->key);

            if ($existingVar) {
                // Update metadata flags only; preserve target value unless overwrite requested
                $updateData = [
                    'is_buildtime' => $sourceVar->is_buildtime,
                    'is_literal' => $sourceVar->is_literal,
                    'is_multiline' => $sourceVar->is_multiline,
                ];

                if ($overwriteValues) {
                    $updateData['value'] = $sourceVar->value;
                }

                $existingVar->update($updateData);
            } else {
                // New variable — add with value from source
                EnvironmentVariable::create([
                    'key' => $sourceVar->key,
                    'value' => $sourceVar->value,
                    'is_buildtime' => $sourceVar->is_buildtime ?? false,
                    'is_literal' => $sourceVar->is_literal ?? false,
                    'is_multiline' => $sourceVar->is_multiline ?? false,
                    'is_preview' => $sourceVar->is_preview ?? false,
                    'is_required' => $sourceVar->is_required ?? false,
                    'resourceable_type' => get_class($target),
                    'resourceable_id' => $target->getAttribute('id'),
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
            /** @var \Illuminate\Support\Collection $sourcePersistent */
            $sourcePersistent = $source->getAttribute('persistentStorages');
            /** @var \Illuminate\Support\Collection $targetPersistent */
            $targetPersistent = $target->getAttribute('persistentStorages');

            foreach ($sourcePersistent as $sourceStorage) {
                $existingStorage = $targetPersistent->firstWhere('mount_path', $sourceStorage->mount_path);

                if (! $existingStorage) {
                    // Create new storage config (not copying actual data!)
                    $newName = str_replace($source->getAttribute('uuid'), $target->getAttribute('uuid'), $sourceStorage->name);

                    LocalPersistentVolume::create([
                        'name' => $newName,
                        'mount_path' => $sourceStorage->mount_path,
                        'host_path' => $sourceStorage->host_path,
                        'resource_type' => get_class($target),
                        'resource_id' => $target->getAttribute('id'),
                    ]);
                }
            }
        }

        // Sync file storages
        if (method_exists($source, 'fileStorages') && method_exists($target, 'fileStorages')) {
            /** @var \Illuminate\Support\Collection $sourceFiles */
            $sourceFiles = $source->getAttribute('fileStorages');
            /** @var \Illuminate\Support\Collection $targetFiles */
            $targetFiles = $target->getAttribute('fileStorages');

            foreach ($sourceFiles as $sourceFile) {
                $existingFile = $targetFiles->firstWhere('mount_path', $sourceFile->mount_path);

                if (! $existingFile) {
                    LocalFileVolume::create([
                        'fs_path' => $sourceFile->fs_path,
                        'mount_path' => $sourceFile->mount_path,
                        'content' => $sourceFile->content,
                        'is_directory' => $sourceFile->is_directory,
                        'resource_type' => get_class($target),
                        'resource_id' => $target->getAttribute('id'),
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
        $name = $source->getAttribute('name');
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
     * Create a pre-migration backup of the target resource in production.
     * For promote mode: backs up the existing target before updating.
     */
    protected function createPreMigrationBackup(
        EnvironmentMigration $migration,
        Model $source,
        Environment $targetEnv
    ): void {
        // Find existing target resource in production
        $existingTarget = $this->findExistingTarget($source, $targetEnv);
        if (! $existingTarget) {
            return;
        }

        if (! CreatePreMigrationBackupAction::isBackupable($existingTarget)) {
            $migration->appendLog('Pre-migration backup skipped (not a backupable database type)');

            return;
        }

        $migration->updateProgress(5, 'Creating pre-migration backup...');
        $backupResult = CreatePreMigrationBackupAction::run($existingTarget, $migration);

        if ($backupResult['success']) {
            $migration->appendLog('Pre-migration backup completed: '.($backupResult['message'] ?? 'OK'));
        } else {
            // Backup failure is a warning, not a blocker
            Log::warning('Pre-migration backup failed', [
                'migration_id' => $migration->id,
                'error' => $backupResult['error'] ?? 'Unknown error',
            ]);
            $migration->appendLog('Warning: Pre-migration backup failed - '.($backupResult['error'] ?? 'Unknown'));
        }
    }

    /**
     * Wait for application health check to pass after deploy.
     * Polls status every 10 seconds for up to 5 minutes.
     *
     * @return array{healthy: bool, message: string}
     */
    protected function waitForHealthCheck(Application $application, EnvironmentMigration $migration): array
    {
        $maxAttempts = 30; // 30 * 10s = 5 minutes
        $intervalSeconds = 10;

        for ($i = 0; $i < $maxAttempts; $i++) {
            sleep($intervalSeconds);

            $application->refresh();
            $status = $application->status ?? '';

            // Docker status format: "running:healthy" or "running:unhealthy"
            if (str_contains($status, ':healthy')) {
                return ['healthy' => true, 'message' => 'Health check passed'];
            }

            if (str_contains($status, ':unhealthy')) {
                return [
                    'healthy' => false,
                    'message' => "Application is unhealthy after deploy (status: {$status})",
                ];
            }

            // Still starting up - update progress
            if ($i % 3 === 0) {
                $elapsed = ($i + 1) * $intervalSeconds;
                $migration->updateProgress(95 + min(4, intdiv($i, 6)), "Waiting for health check ({$elapsed}s elapsed)...");
            }
        }

        return [
            'healthy' => false,
            'message' => 'Health check did not pass within 5 minutes timeout',
        ];
    }

    /**
     * Get resource configuration for history.
     */
    protected function getResourceConfig(Model $resource): array
    {
        return [
            'type' => get_class($resource),
            'id' => $resource->getAttribute('id'),
            'attributes' => $resource->toArray(),
        ];
    }
}
