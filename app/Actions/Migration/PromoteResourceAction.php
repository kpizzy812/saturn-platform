<?php

namespace App\Actions\Migration;

use App\Actions\Migration\Concerns\ResourceConfigFields;
use App\Actions\Service\RestartService;
use App\Models\Application;
use App\Models\Environment;
use App\Models\EnvironmentMigration;
use App\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Action for promoting a resource to target environment.
 *
 * Promote mode:
 * - Updates existing resource configuration (code, build settings, health checks)
 * - Does NOT copy environment variables (they are environment-specific)
 * - Automatically rewires service connections to target environment resources
 * - Optionally triggers deployment after promotion
 */
class PromoteResourceAction
{
    use AsAction;
    use ResourceConfigFields;

    /**
     * Environment variable patterns that contain service connections.
     */
    protected const CONNECTION_VAR_PATTERNS = [
        'DATABASE_URL',
        'DB_HOST',
        'DB_CONNECTION',
        'REDIS_URL',
        'REDIS_HOST',
        'MONGO_URL',
        'MONGODB_URL',
        'MYSQL_HOST',
        'POSTGRES_HOST',
        'PG_HOST',
        '*_DATABASE_URL',
        '*_DB_HOST',
        '*_REDIS_URL',
    ];

    /**
     * Promote a resource to target environment.
     *
     * @return array{success: bool, target?: Model, rewired_connections?: array, error?: string}
     */
    public function handle(EnvironmentMigration $migration): array
    {
        $source = $migration->source;
        $targetEnv = $migration->targetEnvironment;
        $options = $migration->options ?? [];

        if (! $source) {
            return [
                'success' => false,
                'error' => 'Source resource not found.',
            ];
        }

        $migration->updateProgress(10, 'Finding target resource...');

        // Find existing resource in target environment by name
        $target = $this->findTargetResource($source, $targetEnv);

        if (! $target) {
            return [
                'success' => false,
                'error' => "Resource '{$source->name}' not found in target environment '{$targetEnv->name}'. For promote mode, the resource must already exist in target environment.",
            ];
        }

        $migration->appendLog("Found target resource: {$target->name} (ID: {$target->id})");

        try {
            // Update configuration (code, build settings, etc.)
            $migration->updateProgress(30, 'Updating resource configuration...');
            $this->updateConfiguration($source, $target, $options);
            $migration->appendLog('Configuration updated');

            // Rewire connections if requested
            $rewiredConnections = [];
            if ($options[EnvironmentMigration::OPTION_REWIRE_CONNECTIONS] ?? true) {
                $migration->updateProgress(60, 'Rewiring service connections...');
                $rewiredConnections = $this->rewireConnections($target, $targetEnv);

                if (! empty($rewiredConnections)) {
                    $migration->appendLog('Rewired connections: '.implode(', ', array_keys($rewiredConnections)));
                }
            }

            // Trigger deployment if requested
            if ($options[EnvironmentMigration::OPTION_AUTO_DEPLOY] ?? false) {
                $migration->updateProgress(80, 'Triggering deployment...');
                $this->triggerDeployment($target, $migration);
                $migration->appendLog('Deployment triggered');
            }

            $migration->updateProgress(100, 'Promotion completed');

            return [
                'success' => true,
                'target' => $target->fresh(),
                'rewired_connections' => $rewiredConnections,
            ];

        } catch (\Throwable $e) {
            Log::error('Promote resource failed', [
                'migration_id' => $migration->id,
                'source_id' => $source->id,
                'target_id' => $target->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Find the target resource in target environment by name.
     */
    protected function findTargetResource(Model $source, Environment $targetEnv): ?Model
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
     * Update target configuration from source.
     */
    protected function updateConfiguration(Model $source, Model $target, array $options): void
    {
        // Fields to update (code/config related)
        $configFields = $this->getConfigFields($source);

        // Fields to NEVER update
        $excludeFields = [
            'id',
            'uuid',
            'created_at',
            'updated_at',
            'deleted_at',
            'environment_id',
            'destination_id',
            'destination_type',
            'server_id',
            'status',
            'last_online_at',
            'name', // Keep target name
            'fqdn', // Keep target FQDN
        ];

        $attributes = collect($source->getAttributes())
            ->only($configFields)
            ->except($excludeFields)
            ->filter(fn ($value) => $value !== null)
            ->toArray();

        $target->update($attributes);

        // Update settings if applicable
        if ($source instanceof Application && method_exists($source, 'settings')) {
            $this->updateApplicationSettings($source, $target);
        }
    }

    /**
     * Update application settings.
     */
    protected function updateApplicationSettings(Application $source, Application $target): void
    {
        $sourceSettings = $source->settings;
        $targetSettings = $target->settings;

        if (! $sourceSettings || ! $targetSettings) {
            return;
        }

        // Settings to copy (build/deploy related)
        $settingsToUpdate = [
            'is_static',
            'is_spa',
            'is_build_server_enabled',
            'is_preserve_repository_enabled',
            'is_git_submodules_enabled',
            'is_git_lfs_enabled',
            'is_git_shallow_clone_enabled',
            'is_auto_deploy_enabled',
            'is_force_https_enabled',
            'is_preview_deployments_enabled',
            'is_container_label_escape_enabled',
            'is_container_label_readonly_enabled',
            'gpu_driver',
            'gpu_count',
            'gpu_device_ids',
            'gpu_options',
        ];

        $attributes = collect($sourceSettings->getAttributes())
            ->only($settingsToUpdate)
            ->filter(fn ($value) => $value !== null)
            ->toArray();

        $targetSettings->update($attributes);
    }

    /**
     * Rewire environment variable connections to point to target environment resources.
     * Delegates to the shared RewireConnectionsAction for UUID-based replacement.
     *
     * @return array<string, array{old: string, new: string}> Rewired connections
     */
    protected function rewireConnections(Model $target, Environment $targetEnv): array
    {
        if (! method_exists($target, 'environment_variables')) {
            return [];
        }

        // Determine source environment from the target resource's original env
        $sourceEnv = $this->resolveSourceEnvironment($target, $targetEnv);
        if (! $sourceEnv) {
            return [];
        }

        return RewireConnectionsAction::run($target, $sourceEnv, $targetEnv);
    }

    /**
     * Resolve the source environment for rewiring.
     * For promote mode, the source environment is the one before the target.
     */
    protected function resolveSourceEnvironment(Model $target, Environment $targetEnv): ?Environment
    {
        // The target resource lives in targetEnv, but its env vars may reference
        // resources from the source env. Find the source env via the project's
        // environment chain (e.g., dev -> uat -> production).
        $project = $targetEnv->project()->first();
        if (! $project) {
            return null;
        }

        // Get environments ordered by typical promotion chain
        $environments = $project->environments()->orderBy('id')->get();
        $targetIndex = $environments->search(fn ($env) => $env->id === $targetEnv->id);

        if ($targetIndex === false || $targetIndex === 0) {
            return null;
        }

        // Source is the previous environment in the chain
        return $environments[$targetIndex - 1] ?? null;
    }

    /**
     * Check if variable key is a connection variable.
     */
    protected function isConnectionVariable(string $key): bool
    {
        $key = strtoupper($key);

        foreach (self::CONNECTION_VAR_PATTERNS as $pattern) {
            if ($pattern === $key) {
                return true;
            }

            // Handle wildcard patterns
            if (str_contains($pattern, '*')) {
                $regex = '/^'.str_replace('*', '.*', $pattern).'$/i';
                if (preg_match($regex, $key)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Mask sensitive values for logging.
     */
    protected function maskSensitiveValue(string $value): string
    {
        // Mask passwords in URLs
        return preg_replace('/:([^:@]+)@/', ':****@', $value);
    }

    /**
     * Trigger deployment for the promoted resource.
     */
    protected function triggerDeployment(Model $resource, EnvironmentMigration $migration): void
    {
        if ($resource instanceof Application) {
            $deployment_uuid = new \Visus\Cuid2\Cuid2;

            // Require deployment approval when deploying to production
            $requiresApproval = $migration->targetEnvironment?->isProduction() ?? false;

            queue_application_deployment(
                application: $resource,
                deployment_uuid: (string) $deployment_uuid,
                no_questions_asked: true,
                requires_approval: $requiresApproval,
            );
        }

        // Restart services via the RestartService action
        if ($resource instanceof Service) {
            RestartService::run($resource, pullLatestImages: false);
        }
    }
}
