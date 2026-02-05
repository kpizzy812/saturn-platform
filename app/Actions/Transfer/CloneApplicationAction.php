<?php

namespace App\Actions\Transfer;

use App\Models\Application;
use App\Models\Environment;
use App\Models\EnvironmentVariable;
use App\Models\LocalFileVolume;
use App\Models\LocalPersistentVolume;
use App\Models\ResourceTransfer;
use App\Models\ScheduledTask;
use App\Models\Server;
use Lorisleiva\Actions\Concerns\AsAction;
use Visus\Cuid2\Cuid2;

class CloneApplicationAction
{
    use AsAction;

    /**
     * Clone an application to a different environment/server.
     *
     * @param  Application  $sourceApplication  The application to clone
     * @param  Environment  $targetEnvironment  Target environment
     * @param  Server  $targetServer  Target server
     * @param  array  $options  Clone options: copyEnvVars, copyVolumes, copyScheduledTasks, copyTags, newName
     * @return array{success: bool, application?: Application, transfer?: ResourceTransfer, error?: string}
     */
    public function handle(
        Application $sourceApplication,
        Environment $targetEnvironment,
        Server $targetServer,
        array $options = []
    ): array {
        // Default options
        $options = array_merge([
            'copyEnvVars' => true,
            'copyVolumes' => true,
            'copyScheduledTasks' => true,
            'copyTags' => true,
            'newName' => null,
            'instantDeploy' => false,
            'transferId' => null,
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
            // Create new UUID for the cloned application
            $newUuid = (string) new Cuid2;

            // Clone the application
            $clonedApplication = $this->cloneApplication(
                $sourceApplication,
                $targetEnvironment,
                $destination,
                $newUuid,
                $options['newName']
            );

            // Clone settings
            $this->cloneSettings($sourceApplication, $clonedApplication);

            // Clone environment variables
            if ($options['copyEnvVars']) {
                $this->cloneEnvironmentVariables($sourceApplication, $clonedApplication);
            }

            // Clone persistent volumes
            if ($options['copyVolumes']) {
                $this->clonePersistentStorages($sourceApplication, $clonedApplication);
                $this->cloneFileStorages($sourceApplication, $clonedApplication);
            }

            // Clone scheduled tasks
            if ($options['copyScheduledTasks']) {
                $this->cloneScheduledTasks($sourceApplication, $clonedApplication);
            }

            // Copy tags
            if ($options['copyTags']) {
                $this->cloneTags($sourceApplication, $clonedApplication);
            }

            // Update transfer record if provided
            if ($options['transferId']) {
                $transfer = ResourceTransfer::find($options['transferId']);
                if ($transfer) {
                    $transfer->markAsCompleted(
                        $clonedApplication->getMorphClass(),
                        $clonedApplication->id
                    );
                }
            }

            // Trigger instant deploy if requested
            if ($options['instantDeploy']) {
                $this->triggerDeploy($clonedApplication);
            }

            return [
                'success' => true,
                'application' => $clonedApplication,
            ];

        } catch (\Throwable $e) {
            // Mark transfer as failed if we have a transfer ID
            if ($options['transferId']) {
                $transfer = ResourceTransfer::find($options['transferId']);
                if ($transfer) {
                    $transfer->markAsFailed($e->getMessage());
                }
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Clone the main application record.
     */
    protected function cloneApplication(
        Application $source,
        Environment $environment,
        $destination,
        string $newUuid,
        ?string $newName = null
    ): Application {
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
            'fqdn',
            'config_hash',
            'git_commit_sha',
            'last_online_at',
            'restart_count',
            'last_restart_at',
            // Webhook secrets should be regenerated
            'manual_webhook_secret_github',
            'manual_webhook_secret_gitlab',
            'manual_webhook_secret_bitbucket',
            'manual_webhook_secret_gitea',
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
        $attributes['status'] = 'exited';

        // Set new name if provided, otherwise append "(Clone)"
        if ($newName) {
            $attributes['name'] = $newName;
        } else {
            $attributes['name'] = $source->name.' (Clone)';
        }

        // Clear FQDN - will be auto-generated if server supports it
        $attributes['fqdn'] = null;

        $clonedApplication = Application::create($attributes);

        return $clonedApplication;
    }

    /**
     * Clone application settings.
     */
    protected function cloneSettings(Application $source, Application $target): void
    {
        $sourceSettings = $source->settings;
        if (! $sourceSettings) {
            return;
        }

        // The settings are auto-created when application is created,
        // so we just need to update them
        $targetSettings = $target->settings;
        if (! $targetSettings) {
            return;
        }

        $excludeFields = ['id', 'application_id', 'created_at', 'updated_at'];

        $attributes = collect($sourceSettings->getAttributes())
            ->except($excludeFields)
            ->toArray();

        $targetSettings->update($attributes);
    }

    /**
     * Clone environment variables.
     */
    protected function cloneEnvironmentVariables(Application $source, Application $target): void
    {
        // Clone regular environment variables
        foreach ($source->environment_variables as $envVar) {
            $this->cloneEnvVar($envVar, $target, false);
        }

        // Clone preview environment variables
        foreach ($source->environment_variables_preview as $envVar) {
            $this->cloneEnvVar($envVar, $target, true);
        }
    }

    /**
     * Clone a single environment variable.
     */
    protected function cloneEnvVar(EnvironmentVariable $envVar, Application $target, bool $isPreview): void
    {
        EnvironmentVariable::create([
            'key' => $envVar->key,
            'value' => $envVar->value,
            'is_buildtime' => $envVar->is_buildtime ?? false,
            'is_literal' => $envVar->is_literal,
            'is_multiline' => $envVar->is_multiline,
            'is_preview' => $isPreview,
            'is_required' => $envVar->is_required,
            'resourceable_type' => $target->getMorphClass(),
            'resourceable_id' => $target->id,
        ]);
    }

    /**
     * Clone persistent storages with new names.
     */
    protected function clonePersistentStorages(Application $source, Application $target): void
    {
        foreach ($source->persistentStorages as $storage) {
            // Generate new volume name with target UUID
            $newName = str_replace($source->uuid, $target->uuid, $storage->name);

            LocalPersistentVolume::create([
                'name' => $newName,
                'mount_path' => $storage->mount_path,
                'host_path' => $storage->host_path,
                'resource_type' => $target->getMorphClass(),
                'resource_id' => $target->id,
            ]);
        }
    }

    /**
     * Clone file storages.
     */
    protected function cloneFileStorages(Application $source, Application $target): void
    {
        foreach ($source->fileStorages as $file) {
            LocalFileVolume::create([
                'fs_path' => $file->fs_path,
                'mount_path' => $file->mount_path,
                'content' => $file->content,
                'is_directory' => $file->is_directory,
                'resource_type' => $target->getMorphClass(),
                'resource_id' => $target->id,
            ]);
        }
    }

    /**
     * Clone scheduled tasks.
     */
    protected function cloneScheduledTasks(Application $source, Application $target): void
    {
        foreach ($source->scheduled_tasks as $task) {
            ScheduledTask::create([
                'uuid' => (string) new Cuid2,
                'name' => $task->name,
                'command' => $task->command,
                'frequency' => $task->frequency,
                'container' => $task->container,
                'application_id' => $target->id,
                'enabled' => $task->enabled,
            ]);
        }
    }

    /**
     * Clone tags.
     */
    protected function cloneTags(Application $source, Application $target): void
    {
        $tagIds = $source->tags->pluck('id')->toArray();
        if (! empty($tagIds)) {
            $target->tags()->attach($tagIds);
        }
    }

    /**
     * Trigger deployment for the cloned application.
     */
    protected function triggerDeploy(Application $application): void
    {
        $deployment_uuid = (string) new Cuid2;

        queue_application_deployment(
            application: $application,
            deployment_uuid: $deployment_uuid,
            no_questions_asked: true,
        );
    }
}
