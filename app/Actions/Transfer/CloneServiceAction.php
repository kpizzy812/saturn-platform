<?php

namespace App\Actions\Transfer;

use App\Models\Environment;
use App\Models\EnvironmentVariable;
use App\Models\LocalFileVolume;
use App\Models\LocalPersistentVolume;
use App\Models\ResourceTransfer;
use App\Models\ScheduledTask;
use App\Models\Server;
use App\Models\Service;
use App\Models\ServiceApplication;
use App\Models\ServiceDatabase;
use Lorisleiva\Actions\Concerns\AsAction;
use Visus\Cuid2\Cuid2;

class CloneServiceAction
{
    use AsAction;

    protected array $uuidMapping = [];

    /**
     * Clone a service to a different environment/server.
     *
     * @param  Service  $sourceService  The service to clone
     * @param  Environment  $targetEnvironment  Target environment
     * @param  Server  $targetServer  Target server
     * @param  array  $options  Clone options: copyEnvVars, copyVolumes, copyScheduledTasks, copyTags, newName
     * @return array{success: bool, service?: Service, transfer?: ResourceTransfer, error?: string}
     */
    public function handle(
        Service $sourceService,
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
            // Create new UUID for the cloned service
            $newUuid = (string) new Cuid2;
            $this->uuidMapping[$sourceService->uuid] = $newUuid;

            // Clone the service
            $clonedService = $this->cloneService(
                $sourceService,
                $targetEnvironment,
                $targetServer,
                $destination,
                $newUuid,
                $options['newName']
            );

            // Clone service applications
            $this->cloneServiceApplications($sourceService, $clonedService, $options);

            // Clone service databases
            $this->cloneServiceDatabases($sourceService, $clonedService, $options);

            // Clone service-level environment variables
            if ($options['copyEnvVars']) {
                $this->cloneEnvironmentVariables($sourceService, $clonedService);
            }

            // Clone scheduled tasks
            if ($options['copyScheduledTasks']) {
                $this->cloneScheduledTasks($sourceService, $clonedService);
            }

            // Copy tags
            if ($options['copyTags']) {
                $this->cloneTags($sourceService, $clonedService);
            }

            // Update docker_compose with new UUIDs
            $this->updateDockerComposeUuids($clonedService);

            // Update transfer record if provided
            if ($options['transferId']) {
                $transfer = ResourceTransfer::find($options['transferId']);
                if ($transfer) {
                    $transfer->markAsCompleted(
                        $clonedService->getMorphClass(),
                        $clonedService->id
                    );
                }
            }

            return [
                'success' => true,
                'service' => $clonedService,
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
     * Clone the main service record.
     */
    protected function cloneService(
        Service $source,
        Environment $environment,
        Server $server,
        $destination,
        string $newUuid,
        ?string $newName = null
    ): Service {
        // Fields to exclude from cloning
        $excludeFields = [
            'id',
            'uuid',
            'created_at',
            'updated_at',
            'deleted_at',
            'environment_id',
            'server_id',
            'destination_id',
            'destination_type',
            'config_hash',
        ];

        // Get all attributes and filter out excluded ones
        $attributes = collect($source->getAttributes())
            ->except($excludeFields)
            ->toArray();

        // Set new values
        $attributes['uuid'] = $newUuid;
        $attributes['environment_id'] = $environment->id;
        $attributes['server_id'] = $server->id;
        $attributes['destination_id'] = $destination->id;
        $attributes['destination_type'] = $destination->getMorphClass();

        // Set new name if provided, otherwise append "(Clone)"
        if ($newName) {
            $attributes['name'] = $newName;
        } else {
            $attributes['name'] = $source->name.' (Clone)';
        }

        $clonedService = Service::create($attributes);

        return $clonedService;
    }

    /**
     * Clone service applications.
     */
    protected function cloneServiceApplications(Service $source, Service $target, array $options): void
    {
        foreach ($source->applications as $app) {
            $newAppUuid = (string) new Cuid2;
            $this->uuidMapping[$app->uuid] = $newAppUuid;

            $clonedApp = $this->cloneServiceApplication($app, $target, $newAppUuid);

            // Clone persistent storages
            if ($options['copyVolumes']) {
                $this->clonePersistentStorages($app, $clonedApp, $source->uuid, $target->uuid);
                $this->cloneFileStorages($app, $clonedApp);
            }

            // Clone environment variables
            if ($options['copyEnvVars']) {
                $this->cloneAppEnvironmentVariables($app, $clonedApp);
            }
        }
    }

    /**
     * Clone a single service application.
     */
    protected function cloneServiceApplication(ServiceApplication $source, Service $targetService, string $newUuid): ServiceApplication
    {
        $excludeFields = [
            'id',
            'uuid',
            'service_id',
            'status',
            'created_at',
            'updated_at',
            'deleted_at',
            'fqdn',
            'last_online_at',
        ];

        $attributes = collect($source->getAttributes())
            ->except($excludeFields)
            ->toArray();

        $attributes['uuid'] = $newUuid;
        $attributes['service_id'] = $targetService->id;
        $attributes['status'] = 'exited';
        $attributes['fqdn'] = null;

        return ServiceApplication::create($attributes);
    }

    /**
     * Clone service databases.
     */
    protected function cloneServiceDatabases(Service $source, Service $target, array $options): void
    {
        foreach ($source->databases as $db) {
            $newDbUuid = (string) new Cuid2;
            $this->uuidMapping[$db->uuid] = $newDbUuid;

            $clonedDb = $this->cloneServiceDatabase($db, $target, $newDbUuid);

            // Clone persistent storages
            if ($options['copyVolumes']) {
                $this->clonePersistentStorages($db, $clonedDb, $source->uuid, $target->uuid);
                $this->cloneFileStorages($db, $clonedDb);
            }
        }
    }

    /**
     * Clone a single service database.
     */
    protected function cloneServiceDatabase(ServiceDatabase $source, Service $targetService, string $newUuid): ServiceDatabase
    {
        $excludeFields = [
            'id',
            'uuid',
            'service_id',
            'status',
            'created_at',
            'updated_at',
            'deleted_at',
            'public_port',
            'last_online_at',
        ];

        $attributes = collect($source->getAttributes())
            ->except($excludeFields)
            ->toArray();

        $attributes['uuid'] = $newUuid;
        $attributes['service_id'] = $targetService->id;
        $attributes['status'] = 'exited';
        $attributes['public_port'] = null;

        return ServiceDatabase::create($attributes);
    }

    /**
     * Clone service-level environment variables.
     */
    protected function cloneEnvironmentVariables(Service $source, Service $target): void
    {
        foreach ($source->environment_variables as $envVar) {
            $value = $envVar->value;

            // Update any UUID references in the value
            foreach ($this->uuidMapping as $oldUuid => $newUuid) {
                $value = str_replace($oldUuid, $newUuid, $value);
            }

            EnvironmentVariable::create([
                'key' => $envVar->key,
                'value' => $value,
                'is_buildtime' => $envVar->is_buildtime,
                'is_literal' => $envVar->is_literal,
                'is_multiline' => $envVar->is_multiline,
                'is_preview' => $envVar->is_preview ?? false,
                'is_required' => $envVar->is_required,
                'resourceable_type' => $target->getMorphClass(),
                'resourceable_id' => $target->id,
            ]);
        }
    }

    /**
     * Clone service application environment variables.
     */
    protected function cloneAppEnvironmentVariables(ServiceApplication $source, ServiceApplication $target): void
    {
        foreach ($source->environment_variables as $envVar) {
            EnvironmentVariable::create([
                'key' => $envVar->key,
                'value' => $envVar->value,
                'is_buildtime' => $envVar->is_buildtime ?? false,
                'is_literal' => $envVar->is_literal ?? false,
                'is_multiline' => $envVar->is_multiline ?? false,
                'is_preview' => $envVar->is_preview ?? false,
                'is_required' => $envVar->is_required ?? false,
                'resourceable_type' => $target->getMorphClass(),
                'resourceable_id' => $target->id,
            ]);
        }
    }

    /**
     * Clone persistent storages with UUID replacement.
     */
    protected function clonePersistentStorages($source, $target, string $oldServiceUuid, string $newServiceUuid): void
    {
        foreach ($source->persistentStorages as $storage) {
            // Replace old service UUID with new one in volume name
            $newName = str_replace($oldServiceUuid, $newServiceUuid, $storage->name);

            // Also replace any component-level UUIDs
            foreach ($this->uuidMapping as $oldUuid => $newUuid) {
                $newName = str_replace($oldUuid, $newUuid, $newName);
            }

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
    protected function cloneFileStorages($source, $target): void
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
    protected function cloneScheduledTasks(Service $source, Service $target): void
    {
        foreach ($source->scheduled_tasks as $task) {
            ScheduledTask::create([
                'uuid' => (string) new Cuid2,
                'name' => $task->name,
                'command' => $task->command,
                'frequency' => $task->frequency,
                'container' => $task->container,
                'service_id' => $target->id,
                'enabled' => $task->enabled,
            ]);
        }
    }

    /**
     * Clone tags.
     */
    protected function cloneTags(Service $source, Service $target): void
    {
        $tagIds = $source->tags->pluck('id')->toArray();
        if (! empty($tagIds)) {
            $target->tags()->attach($tagIds);
        }
    }

    /**
     * Update docker_compose and docker_compose_raw with new UUIDs.
     */
    protected function updateDockerComposeUuids(Service $service): void
    {
        $dockerCompose = $service->docker_compose;
        $dockerComposeRaw = $service->docker_compose_raw;

        // Replace all old UUIDs with new ones
        foreach ($this->uuidMapping as $oldUuid => $newUuid) {
            if ($dockerCompose) {
                $dockerCompose = str_replace($oldUuid, $newUuid, $dockerCompose);
            }
            if ($dockerComposeRaw) {
                $dockerComposeRaw = str_replace($oldUuid, $newUuid, $dockerComposeRaw);
            }
        }

        $service->update([
            'docker_compose' => $dockerCompose,
            'docker_compose_raw' => $dockerComposeRaw,
        ]);
    }
}
