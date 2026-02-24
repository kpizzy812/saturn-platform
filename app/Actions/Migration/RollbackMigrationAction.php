<?php

namespace App\Actions\Migration;

use App\Actions\Migration\Concerns\ResourceConfigFields;
use App\Models\Application;
use App\Models\EnvironmentMigration;
use App\Models\EnvironmentVariable;
use App\Models\LocalFileVolume;
use App\Models\LocalPersistentVolume;
use App\Models\Service;
use App\Models\StandaloneClickhouse;
use App\Models\StandaloneDragonfly;
use App\Models\StandaloneKeydb;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use App\Models\StandaloneRedis;
use App\Models\User;
use App\Notifications\Migration\MigrationRolledBack;
use App\Services\Authorization\MigrationAuthorizationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Action to rollback a completed migration.
 * Restores the previous state from the rollback snapshot.
 */
class RollbackMigrationAction
{
    use AsAction;
    use ResourceConfigFields;

    /**
     * Rollback a completed migration.
     *
     * @return array{success: bool, error?: string}
     */
    public function handle(EnvironmentMigration $migration, User $user): array
    {
        // Check authorization
        $authService = app(MigrationAuthorizationService::class);
        if (! $authService->canRollbackMigration($user, $migration)) {
            return [
                'success' => false,
                'error' => 'You do not have permission to rollback this migration.',
            ];
        }

        // Verify migration can be rolled back
        if (! $migration->canBeRolledBack()) {
            return [
                'success' => false,
                'error' => 'This migration cannot be rolled back. Status: '.$migration->status,
            ];
        }

        $snapshot = $migration->rollback_snapshot;
        if (empty($snapshot)) {
            return [
                'success' => false,
                'error' => 'No rollback snapshot available for this migration.',
            ];
        }

        try {
            $migration->appendLog('Starting rollback by '.$user->name);

            // Determine rollback strategy based on what was done
            $updateExisting = $migration->options[EnvironmentMigration::OPTION_UPDATE_EXISTING] ?? false;
            $configOnly = $migration->options[EnvironmentMigration::OPTION_CONFIG_ONLY] ?? false;

            if ($updateExisting || $configOnly) {
                // We updated an existing resource - restore from snapshot
                $result = $this->rollbackExistingUpdate($migration, $snapshot);
            } else {
                // We created a new resource - delete it
                $result = $this->rollbackNewResource($migration);
            }

            if (! $result['success']) {
                $migration->appendLog('Rollback failed: '.$result['error']);

                return $result;
            }

            // Mark migration as rolled back
            $migration->markAsRolledBack();
            $migration->appendLog('Rollback completed successfully');

            // Notify requester
            $this->notifyRequester($migration);

            return ['success' => true];

        } catch (\Throwable $e) {
            $migration->appendLog('Rollback error: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Allowed resource types for rollback to prevent arbitrary class instantiation.
     */
    protected const ALLOWED_TARGET_TYPES = [
        Application::class,
        Service::class,
        StandalonePostgresql::class,
        StandaloneMysql::class,
        StandaloneMariadb::class,
        StandaloneMongodb::class,
        StandaloneRedis::class,
        StandaloneClickhouse::class,
        StandaloneKeydb::class,
        StandaloneDragonfly::class,
    ];

    /**
     * Rollback by restoring existing resource to previous state.
     */
    protected function rollbackExistingUpdate(EnvironmentMigration $migration, array $snapshot): array
    {
        $existingTargetConfig = $snapshot['existing_target_config'] ?? null;

        if (! $existingTargetConfig) {
            return [
                'success' => false,
                'error' => 'No existing target configuration in snapshot.',
            ];
        }

        $targetType = $snapshot['existing_target_type'] ?? null;
        $targetId = $snapshot['existing_target_id'] ?? null;

        if (! $targetType || ! $targetId) {
            return [
                'success' => false,
                'error' => 'Missing target identification in snapshot.',
            ];
        }

        // Validate target type against allowed list to prevent arbitrary class loading
        if (! in_array($targetType, self::ALLOWED_TARGET_TYPES)) {
            return [
                'success' => false,
                'error' => 'Invalid target type in snapshot.',
            ];
        }

        // Find the target resource
        $target = $targetType::find($targetId);
        if (! $target) {
            return [
                'success' => false,
                'error' => 'Target resource no longer exists.',
            ];
        }

        $migration->appendLog('Restoring resource configuration...');

        // Restore attributes
        $attributes = $existingTargetConfig['attributes'] ?? [];
        $safeAttributes = $this->getSafeRestoreAttributes($attributes);
        $target->update($safeAttributes);

        // Restore environment variables
        if (isset($existingTargetConfig['environment_variables'])) {
            $migration->appendLog('Restoring environment variables...');
            $this->restoreEnvironmentVariables($target, $existingTargetConfig['environment_variables']);
        }

        // Restore volume configurations
        if (isset($existingTargetConfig['persistent_storages'])) {
            $migration->appendLog('Restoring persistent storage configurations...');
            $this->restorePersistentStorages($target, $existingTargetConfig['persistent_storages']);
        }

        if (isset($existingTargetConfig['file_storages'])) {
            $migration->appendLog('Restoring file storage configurations...');
            $this->restoreFileStorages($target, $existingTargetConfig['file_storages']);
        }

        // Restore application settings if present in snapshot
        if (isset($existingTargetConfig['application_settings']) && $target instanceof Application) {
            $migration->appendLog('Restoring application settings...');
            $this->restoreApplicationSettings($target, $existingTargetConfig['application_settings']);
        }

        return ['success' => true];
    }

    /**
     * Rollback by deleting the newly created resource.
     */
    protected function rollbackNewResource(EnvironmentMigration $migration): array
    {
        $target = $migration->target;

        if (! $target) {
            // Already deleted or never created
            $migration->appendLog('Target resource not found - may have been already deleted');

            return ['success' => true];
        }

        $migration->appendLog('Deleting created resource: '.get_class($target).' #'.$target->getAttribute('id'));

        try {
            // For applications and services, use their delete methods
            if ($target instanceof Application || $target instanceof Service) {
                $this->deleteResource($target);
            } elseif ($this->isDatabase($target)) {
                $this->deleteDatabase($target);
            } else {
                $target->delete();
            }

            return ['success' => true];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => 'Failed to delete created resource: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Get safe attributes to restore (exclude identity fields).
     */
    protected function getSafeRestoreAttributes(array $attributes): array
    {
        $excludeFields = [
            'id', 'uuid', 'created_at', 'updated_at', 'deleted_at',
            'environment_id', 'destination_id', 'destination_type',
        ];

        return collect($attributes)
            ->except($excludeFields)
            ->filter(fn ($value) => $value !== null)
            ->toArray();
    }

    /**
     * Restore environment variables from snapshot.
     */
    protected function restoreEnvironmentVariables(Model $target, array $vars): void
    {
        // Get current vars
        /** @var \Illuminate\Support\Collection $currentVars */
        $currentVars = $target->getAttribute('environment_variables');

        // Delete vars not in snapshot
        $snapshotKeys = collect($vars)->pluck('key')->toArray();
        $currentVars->whereNotIn('key', $snapshotKeys)->each->delete();

        // Restore/update vars from snapshot
        foreach ($vars as $varData) {
            $existingVar = $currentVars->firstWhere('key', $varData['key']);

            if ($existingVar) {
                $existingVar->update([
                    'value' => $varData['value'],
                    'is_buildtime' => $varData['is_buildtime'] ?? $varData['is_build_time'] ?? false,
                    'is_literal' => $varData['is_literal'] ?? false,
                    'is_preview' => $varData['is_preview'] ?? false,
                ]);
            } else {
                EnvironmentVariable::create([
                    'key' => $varData['key'],
                    'value' => $varData['value'],
                    'is_buildtime' => $varData['is_buildtime'] ?? $varData['is_build_time'] ?? false,
                    'is_literal' => $varData['is_literal'] ?? false,
                    'is_preview' => $varData['is_preview'] ?? false,
                    'resourceable_type' => get_class($target),
                    'resourceable_id' => $target->getAttribute('id'),
                ]);
            }
        }
    }

    /**
     * Restore persistent storages from snapshot.
     */
    protected function restorePersistentStorages(Model $target, array $storages): void
    {
        if (! method_exists($target, 'persistentStorages')) {
            return;
        }

        /** @var \Illuminate\Support\Collection $currentStorages */
        $currentStorages = $target->getAttribute('persistentStorages');

        // Delete storages not in snapshot
        $snapshotMounts = collect($storages)->pluck('mount_path')->toArray();
        $currentStorages->whereNotIn('mount_path', $snapshotMounts)->each->delete();

        // Restore storages from snapshot
        foreach ($storages as $storageData) {
            $existingStorage = $currentStorages->firstWhere('mount_path', $storageData['mount_path']);

            if (! $existingStorage) {
                LocalPersistentVolume::create([
                    'name' => $storageData['name'],
                    'mount_path' => $storageData['mount_path'],
                    'host_path' => $storageData['host_path'],
                    'resource_type' => get_class($target),
                    'resource_id' => $target->getAttribute('id'),
                ]);
            }
        }
    }

    /**
     * Restore file storages from snapshot.
     */
    protected function restoreFileStorages(Model $target, array $files): void
    {
        if (! method_exists($target, 'fileStorages')) {
            return;
        }

        /** @var \Illuminate\Support\Collection $currentFiles */
        $currentFiles = $target->getAttribute('fileStorages');

        // Delete files not in snapshot
        $snapshotMounts = collect($files)->pluck('mount_path')->toArray();
        $currentFiles->whereNotIn('mount_path', $snapshotMounts)->each->delete();

        // Restore files from snapshot
        foreach ($files as $fileData) {
            $existingFile = $currentFiles->firstWhere('mount_path', $fileData['mount_path']);

            if ($existingFile) {
                $existingFile->update([
                    'content' => $fileData['content'],
                ]);
            } else {
                LocalFileVolume::create([
                    'fs_path' => $fileData['fs_path'],
                    'mount_path' => $fileData['mount_path'],
                    'content' => $fileData['content'],
                    'is_directory' => $fileData['is_directory'] ?? false,
                    'resource_type' => get_class($target),
                    'resource_id' => $target->getAttribute('id'),
                ]);
            }
        }
    }

    /**
     * Delete a resource (Application or Service).
     */
    protected function deleteResource(Model $resource): void
    {
        // Delete related items first
        if (method_exists($resource, 'environment_variables')) {
            $resource->environment_variables()->delete();
        }
        if (method_exists($resource, 'persistentStorages')) {
            $resource->persistentStorages()->delete();
        }
        if (method_exists($resource, 'fileStorages')) {
            $resource->fileStorages()->delete();
        }
        if (method_exists($resource, 'scheduled_tasks')) {
            $resource->scheduled_tasks()->delete();
        }
        if (method_exists($resource, 'tags')) {
            $resource->tags()->detach();
        }

        $resource->delete();
    }

    /**
     * Delete a database resource.
     */
    protected function deleteDatabase(Model $database): void
    {
        // Delete related items
        if (method_exists($database, 'environment_variables')) {
            $database->environment_variables()->delete();
        }
        if (method_exists($database, 'persistentStorages')) {
            $database->persistentStorages()->delete();
        }
        if (method_exists($database, 'fileStorages')) {
            $database->fileStorages()->delete();
        }
        if (method_exists($database, 'scheduledBackups')) {
            $database->scheduledBackups()->delete();
        }
        if (method_exists($database, 'tags')) {
            $database->tags()->detach();
        }

        $database->delete();
    }

    /**
     * Restore application settings from snapshot.
     */
    protected function restoreApplicationSettings(Application $target, array $settingsData): void
    {
        $targetSettings = $target->settings;
        if (! $targetSettings) {
            return;
        }

        // Only restore safe settings fields (matches ApplicationSetting::$fillable minus application_id)
        $safeFields = [
            'is_static',
            'is_spa',
            'is_build_server_enabled',
            'is_preserve_repository_enabled',
            'is_container_label_escape_enabled',
            'is_container_label_readonly_enabled',
            'use_build_secrets',
            'inject_build_args_to_dockerfile',
            'include_source_commit_in_build',
            'is_auto_deploy_enabled',
            'is_force_https_enabled',
            'is_debug_enabled',
            'is_preview_deployments_enabled',
            'is_pr_deployments_public_enabled',
            'is_git_submodules_enabled',
            'is_git_lfs_enabled',
            'is_git_shallow_clone_enabled',
            'docker_images_to_keep',
            'auto_rollback_enabled',
            'rollback_validation_seconds',
            'rollback_max_restarts',
            'rollback_on_health_check_fail',
            'rollback_on_crash_loop',
        ];

        $attributes = collect($settingsData)
            ->only($safeFields)
            ->filter(fn ($value) => $value !== null)
            ->toArray();

        $targetSettings->update($attributes);
    }

    /**
     * Notify the requester about the rollback.
     */
    protected function notifyRequester(EnvironmentMigration $migration): void
    {
        try {
            $requester = $migration->requestedBy;
            if ($requester) {
                $requester->notify(new MigrationRolledBack($migration));
            }
        } catch (\Throwable $e) {
            // Log but don't fail
            Log::warning('Failed to notify about rollback', ['error' => $e->getMessage()]);
        }
    }
}
