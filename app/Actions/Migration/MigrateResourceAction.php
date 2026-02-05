<?php

namespace App\Actions\Migration;

use App\Jobs\ExecuteMigrationJob;
use App\Models\Application;
use App\Models\Environment;
use App\Models\EnvironmentMigration;
use App\Models\MigrationHistory;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use App\Notifications\Migration\MigrationApprovalRequired;
use App\Services\Authorization\MigrationAuthorizationService;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Main action for initiating a resource migration.
 * Validates permissions, creates migration record, and dispatches job if no approval needed.
 */
class MigrateResourceAction
{
    use AsAction;

    /**
     * Initiate a resource migration.
     *
     * @param  Model  $resource  The resource to migrate (Application, Service, or Database)
     * @param  array  $options  Migration options: copy_env_vars, copy_volumes, update_existing, config_only
     * @return array{success: bool, migration?: EnvironmentMigration, requires_approval?: bool, error?: string}
     */
    public function handle(
        Model $resource,
        Environment $targetEnvironment,
        Server $targetServer,
        User $requestedBy,
        array $options = []
    ): array {
        // Get source environment from resource
        $sourceEnvironment = $this->getResourceEnvironment($resource);
        if (! $sourceEnvironment) {
            return [
                'success' => false,
                'error' => 'Could not determine source environment for resource.',
            ];
        }

        // Validate migration chain
        $chainValidation = ValidateMigrationChainAction::run($sourceEnvironment, $targetEnvironment);
        if (! $chainValidation['valid']) {
            return [
                'success' => false,
                'error' => $chainValidation['error'],
            ];
        }

        // Check authorization
        $authService = app(MigrationAuthorizationService::class);
        if (! $authService->canInitiateMigration($requestedBy, $resource, $sourceEnvironment, $targetEnvironment)) {
            return [
                'success' => false,
                'error' => 'You do not have permission to initiate this migration.',
            ];
        }

        // Validate server is functional
        if (! $targetServer->isFunctional()) {
            return [
                'success' => false,
                'error' => 'Target server is not functional.',
            ];
        }

        // Normalize options with defaults
        $options = $this->normalizeOptions($options, $resource, $targetEnvironment);

        // Determine if approval is required
        $requiresApproval = $authService->requiresApproval($requestedBy, $sourceEnvironment, $targetEnvironment);

        // Create rollback snapshot before migration
        $rollbackSnapshot = $this->createRollbackSnapshot($resource, $targetEnvironment, $options);

        // Create migration record
        $migration = EnvironmentMigration::create([
            'source_type' => get_class($resource),
            'source_id' => $resource->id,
            'source_environment_id' => $sourceEnvironment->id,
            'target_environment_id' => $targetEnvironment->id,
            'target_server_id' => $targetServer->id,
            'options' => $options,
            'status' => $requiresApproval ? EnvironmentMigration::STATUS_PENDING : EnvironmentMigration::STATUS_PENDING,
            'requires_approval' => $requiresApproval,
            'requested_by' => $requestedBy->id,
            'rollback_snapshot' => $rollbackSnapshot,
            'team_id' => currentTeam()->id,
        ]);

        // Create migration history entry for source resource
        MigrationHistory::createForResource(
            $resource,
            $migration,
            $this->getResourceConfig($resource)
        );

        // If approval required, notify approvers
        if ($requiresApproval) {
            $this->notifyApprovers($migration, $authService);

            return [
                'success' => true,
                'migration' => $migration->fresh(),
                'requires_approval' => true,
            ];
        }

        // No approval needed - dispatch job immediately
        ExecuteMigrationJob::dispatch($migration);

        return [
            'success' => true,
            'migration' => $migration->fresh(),
            'requires_approval' => false,
        ];
    }

    /**
     * Get the environment for a resource.
     */
    protected function getResourceEnvironment(Model $resource): ?Environment
    {
        if (method_exists($resource, 'environment')) {
            return $resource->environment;
        }

        return null;
    }

    /**
     * Normalize options with defaults.
     */
    protected function normalizeOptions(array $options, Model $resource, Environment $targetEnv): array
    {
        $defaults = [
            EnvironmentMigration::OPTION_COPY_ENV_VARS => true,
            EnvironmentMigration::OPTION_COPY_VOLUMES => true,
            EnvironmentMigration::OPTION_UPDATE_EXISTING => false,
            EnvironmentMigration::OPTION_CONFIG_ONLY => false,
        ];

        $options = array_merge($defaults, $options);

        // For databases migrating to production, force config_only mode
        if ($this->isDatabase($resource) && $targetEnv->isProduction()) {
            $options[EnvironmentMigration::OPTION_CONFIG_ONLY] = true;
        }

        return $options;
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
     * Create rollback snapshot for recovery.
     */
    protected function createRollbackSnapshot(Model $resource, Environment $targetEnv, array $options): array
    {
        $snapshot = [
            'source_config' => $this->getResourceConfig($resource),
            'options' => $options,
            'created_at' => now()->toIso8601String(),
        ];

        // If updating existing, also snapshot the existing target
        if ($options[EnvironmentMigration::OPTION_UPDATE_EXISTING] ?? false) {
            $existingTarget = $this->findExistingTarget($resource, $targetEnv);
            if ($existingTarget) {
                $snapshot['existing_target_config'] = $this->getResourceConfig($existingTarget);
                $snapshot['existing_target_type'] = get_class($existingTarget);
                $snapshot['existing_target_id'] = $existingTarget->id;
            }
        }

        return $snapshot;
    }

    /**
     * Get resource configuration for snapshot.
     */
    protected function getResourceConfig(Model $resource): array
    {
        $config = [
            'type' => get_class($resource),
            'id' => $resource->id,
            'attributes' => $resource->toArray(),
        ];

        // Add environment variables
        if (method_exists($resource, 'environment_variables')) {
            $config['environment_variables'] = $resource->environment_variables->map(fn ($var) => [
                'key' => $var->key,
                'value' => $var->value,
                'is_buildtime' => $var->is_buildtime ?? false,
                'is_literal' => $var->is_literal ?? false,
                'is_preview' => $var->is_preview ?? false,
            ])->toArray();
        }

        // Add persistent volumes
        if (method_exists($resource, 'persistentStorages')) {
            $config['persistent_storages'] = $resource->persistentStorages->map(fn ($storage) => [
                'name' => $storage->name,
                'mount_path' => $storage->mount_path,
                'host_path' => $storage->host_path,
            ])->toArray();
        }

        // Add file volumes
        if (method_exists($resource, 'fileStorages')) {
            $config['file_storages'] = $resource->fileStorages->map(fn ($file) => [
                'fs_path' => $file->fs_path,
                'mount_path' => $file->mount_path,
                'content' => $file->content,
                'is_directory' => $file->is_directory,
            ])->toArray();
        }

        return $config;
    }

    /**
     * Find existing resource of same type in target environment.
     */
    protected function findExistingTarget(Model $resource, Environment $targetEnv): ?Model
    {
        $name = $resource->name ?? null;
        if (! $name) {
            return null;
        }

        $class = get_class($resource);

        if ($resource instanceof Application) {
            return $targetEnv->applications()->where('name', $name)->first();
        }

        if ($resource instanceof Service) {
            return $targetEnv->services()->where('name', $name)->first();
        }

        // For databases, check in the appropriate collection
        $relationMethod = $this->getDatabaseRelationMethod($class);
        if ($relationMethod && method_exists($targetEnv, $relationMethod)) {
            return $targetEnv->$relationMethod()->where('name', $name)->first();
        }

        return null;
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
     * Notify approvers about pending migration.
     */
    protected function notifyApprovers(EnvironmentMigration $migration, MigrationAuthorizationService $authService): void
    {
        $project = $migration->sourceEnvironment->project;
        $approvers = $authService->getApprovers($project);

        foreach ($approvers as $approver) {
            try {
                $approver->notify(new MigrationApprovalRequired($migration));
            } catch (\Throwable $e) {
                // Log but don't fail the migration request
                ray('Failed to notify approver', [
                    'approver' => $approver->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
