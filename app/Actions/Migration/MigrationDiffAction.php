<?php

namespace App\Actions\Migration;

use App\Actions\Migration\Concerns\ResourceConfigFields;
use App\Models\Application;
use App\Models\Environment;
use App\Models\EnvironmentMigration;
use App\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Generates a structured diff preview of what a migration would change.
 * Used for dry-run mode and migration check previews.
 */
class MigrationDiffAction
{
    use AsAction;
    use ResourceConfigFields;

    /**
     * Generate migration diff/preview.
     *
     * @return array{mode: string, summary: array, attribute_diff?: array, env_var_diff?: array, volume_diff?: array, rewire_preview?: array}
     */
    public function handle(
        Model $resource,
        Environment $targetEnvironment,
        array $options = []
    ): array {
        $mode = $options[EnvironmentMigration::OPTION_MODE] ?? EnvironmentMigration::MODE_CLONE;
        $updateExisting = $options[EnvironmentMigration::OPTION_UPDATE_EXISTING] ?? false;

        // Find existing target for promote/update modes
        $existingTarget = null;
        if ($mode === EnvironmentMigration::MODE_PROMOTE || $updateExisting) {
            $existingTarget = $this->findExistingTarget($resource, $targetEnvironment);
        }

        if ($mode === EnvironmentMigration::MODE_PROMOTE && $existingTarget) {
            return $this->generatePromoteDiff($resource, $existingTarget, $targetEnvironment, $options);
        }

        if ($updateExisting && $existingTarget) {
            return $this->generateUpdateDiff($resource, $existingTarget, $options);
        }

        return $this->generateCloneSummary($resource, $options);
    }

    /**
     * Generate summary for clone mode (new resource creation).
     */
    protected function generateCloneSummary(Model $resource, array $options): array
    {
        $envVarCount = 0;
        if (method_exists($resource, 'environment_variables') && ($options[EnvironmentMigration::OPTION_COPY_ENV_VARS] ?? true)) {
            $envVarCount = $resource->environment_variables->count();
        }

        $volumeCount = 0;
        if (method_exists($resource, 'persistentStorages') && ($options[EnvironmentMigration::OPTION_COPY_VOLUMES] ?? true)) {
            $volumeCount = $resource->persistentStorages->count();
        }

        $fileCount = 0;
        if (method_exists($resource, 'fileStorages') && ($options[EnvironmentMigration::OPTION_COPY_VOLUMES] ?? true)) {
            $fileCount = $resource->fileStorages->count();
        }

        return [
            'mode' => 'clone',
            'summary' => [
                'action' => 'create_new',
                'resource_name' => $resource->getAttribute('name') ?? 'unnamed',
                'resource_type' => class_basename($resource),
                'env_vars_count' => $envVarCount,
                'persistent_volumes_count' => $volumeCount,
                'file_volumes_count' => $fileCount,
            ],
        ];
    }

    /**
     * Generate detailed diff for promote mode.
     */
    protected function generatePromoteDiff(
        Model $source,
        Model $target,
        Environment $targetEnvironment,
        array $options
    ): array {
        $diff = [
            'mode' => 'promote',
            'summary' => [
                'action' => 'update_existing',
                'resource_name' => $target->getAttribute('name') ?? 'unnamed',
                'resource_type' => class_basename($target),
                'target_id' => $target->getAttribute('id'),
            ],
            'attribute_diff' => $this->diffAttributes($source, $target),
        ];

        // Connection rewire preview
        if ($options[EnvironmentMigration::OPTION_REWIRE_CONNECTIONS] ?? true) {
            $diff['rewire_preview'] = $this->previewRewire($target, $targetEnvironment);
        }

        return $diff;
    }

    /**
     * Generate diff for update-existing mode.
     */
    protected function generateUpdateDiff(Model $source, Model $target, array $options): array
    {
        $configOnly = $options[EnvironmentMigration::OPTION_CONFIG_ONLY] ?? false;

        $diff = [
            'mode' => $configOnly ? 'config_only' : 'update_existing',
            'summary' => [
                'action' => 'update_existing',
                'resource_name' => $target->getAttribute('name') ?? 'unnamed',
                'resource_type' => class_basename($target),
                'target_id' => $target->getAttribute('id'),
            ],
            'attribute_diff' => $configOnly
                ? $this->diffSafeAttributes($source, $target)
                : $this->diffAttributes($source, $target),
        ];

        // Env var diff
        if ($options[EnvironmentMigration::OPTION_COPY_ENV_VARS] ?? true) {
            $diff['env_var_diff'] = $this->diffEnvVars($source, $target);
        }

        // Volume diff (only for non-config-only mode)
        if (! $configOnly && ($options[EnvironmentMigration::OPTION_COPY_VOLUMES] ?? true)) {
            $diff['volume_diff'] = $this->diffVolumes($source, $target);
        }

        return $diff;
    }

    /**
     * Diff whitelisted config attributes between source and target.
     */
    protected function diffAttributes(Model $source, Model $target): array
    {
        $configFields = $this->getConfigFields($source);
        $changed = [];

        foreach ($configFields as $field) {
            $sourceVal = $source->getAttribute($field);
            $targetVal = $target->getAttribute($field);

            if ($sourceVal !== $targetVal && $sourceVal !== null) {
                $changed[$field] = [
                    'from' => $targetVal,
                    'to' => $sourceVal,
                ];
            }
        }

        return $changed;
    }

    /**
     * Diff safe config attributes (whitelist-based).
     */
    protected function diffSafeAttributes(Model $source, Model $target): array
    {
        return $this->diffAttributes($source, $target);
    }

    /**
     * Diff environment variables between source and target.
     */
    protected function diffEnvVars(Model $source, Model $target): array
    {
        if (! method_exists($source, 'environment_variables') || ! method_exists($target, 'environment_variables')) {
            return ['added' => [], 'removed' => [], 'changed' => []];
        }

        $sourceVars = $source->environment_variables->keyBy('key');
        $targetVars = $target->environment_variables->keyBy('key');

        $added = [];
        $removed = [];
        $changed = [];

        // Find added and changed vars
        foreach ($sourceVars as $key => $sourceVar) {
            if (! $targetVars->has($key)) {
                $added[] = $key;
            } elseif ($sourceVar->value !== $targetVars[$key]->value) {
                $changed[] = $key;
            }
        }

        // Find removed vars
        foreach ($targetVars as $key => $targetVar) {
            if (! $sourceVars->has($key)) {
                $removed[] = $key;
            }
        }

        return [
            'added' => $added,
            'removed' => $removed,
            'changed' => $changed,
        ];
    }

    /**
     * Diff volume configurations between source and target.
     */
    protected function diffVolumes(Model $source, Model $target): array
    {
        $added = [];
        $removed = [];

        if (method_exists($source, 'persistentStorages') && method_exists($target, 'persistentStorages')) {
            $sourceMounts = $source->persistentStorages->pluck('mount_path')->toArray();
            $targetMounts = $target->persistentStorages->pluck('mount_path')->toArray();

            $added = array_values(array_diff($sourceMounts, $targetMounts));
            $removed = array_values(array_diff($targetMounts, $sourceMounts));
        }

        return [
            'added' => $added,
            'removed' => $removed,
        ];
    }

    /**
     * Preview which connection variables would be rewired.
     */
    protected function previewRewire(Model $target, Environment $targetEnvironment): array
    {
        if (! method_exists($target, 'environment_variables')) {
            return [];
        }

        $connectionPatterns = [
            'DATABASE_URL', 'DB_HOST', 'DB_CONNECTION',
            'REDIS_URL', 'REDIS_HOST', 'MONGO_URL', 'MONGODB_URL',
            'MYSQL_HOST', 'POSTGRES_HOST', 'PG_HOST',
        ];

        $preview = [];
        foreach ($target->environment_variables as $envVar) {
            $key = strtoupper($envVar->key);
            foreach ($connectionPatterns as $pattern) {
                if ($key === $pattern || str_ends_with($key, '_'.$pattern)) {
                    $preview[] = [
                        'key' => $envVar->key,
                        'current_value_masked' => $this->maskValue($envVar->value),
                        'will_rewire' => true,
                    ];
                    break;
                }
            }
        }

        return $preview;
    }

    /**
     * Find existing target resource in environment.
     */
    protected function findExistingTarget(Model $resource, Environment $targetEnv): ?Model
    {
        $name = $resource->getAttribute('name');
        if (! $name) {
            return null;
        }

        if ($resource instanceof Application) {
            return $targetEnv->applications()->where('name', $name)->first();
        }

        if ($resource instanceof Service) {
            return $targetEnv->services()->where('name', $name)->first();
        }

        $relationMethod = $this->getDatabaseRelationMethod(get_class($resource));
        if ($relationMethod && method_exists($targetEnv, $relationMethod)) {
            return $targetEnv->$relationMethod()->where('name', $name)->first();
        }

        return null;
    }

    /**
     * Mask sensitive values for display.
     */
    protected function maskValue(?string $value): string
    {
        if (empty($value)) {
            return '(empty)';
        }

        // Mask passwords in URLs
        $masked = preg_replace('/:([^:@]+)@/', ':****@', $value);

        if (strlen($masked) > 50) {
            return substr($masked, 0, 30).'...'.substr($masked, -10);
        }

        return $masked;
    }
}
