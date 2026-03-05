<?php

namespace App\Actions\Environment;

use App\Models\Application;
use App\Models\Environment;
use App\Models\EnvironmentVariable;
use App\Models\ResourceLink;
use App\Models\ScheduledDatabaseBackup;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Visus\Cuid2\Cuid2;

class CloneEnvironmentAction
{
    /**
     * Clone an entire environment with all its resources.
     *
     * @param  array{
     *     name: string,
     *     description?: string,
     *     target_server_id?: int|null,
     *     clone_env_vars?: bool,
     *     clone_scheduled_tasks?: bool,
     *     clone_backup_configs?: bool,
     * }  $options
     *
     * @throws \Throwable
     */
    public function execute(Environment $source, array $options): Environment
    {
        $cloneEnvVars = $options['clone_env_vars'] ?? true;
        $cloneScheduledTasks = $options['clone_scheduled_tasks'] ?? true;
        $cloneBackupConfigs = $options['clone_backup_configs'] ?? true;
        $targetServerId = $options['target_server_id'] ?? null;

        return DB::transaction(function () use ($source, $options, $cloneEnvVars, $cloneScheduledTasks, $cloneBackupConfigs, $targetServerId) {
            // 1. Create the new environment
            $newEnv = Environment::create([
                'name' => $options['name'],
                'description' => $options['description'] ?? "Cloned from {$source->name}",
                'type' => 'development', // Clones are always dev environments
                'requires_approval' => false,
                'project_id' => $source->project_id,
                'default_server_id' => $targetServerId ?? $source->default_server_id,
                'default_git_branch' => $source->default_git_branch,
                'cloned_from_id' => $source->id,
            ]);

            // UUID mapping: old UUID → new resource (for depends_on + resource links rewiring)
            $uuidMap = [];

            // 2. Clone databases first (they have no depends_on)
            $this->cloneDatabases($source, $newEnv, $uuidMap, $cloneEnvVars, $cloneBackupConfigs, $targetServerId);

            // 3. Clone services
            $this->cloneServices($source, $newEnv, $uuidMap, $cloneEnvVars, $targetServerId);

            // 4. Clone applications
            $this->cloneApplications($source, $newEnv, $uuidMap, $cloneEnvVars, $cloneScheduledTasks, $targetServerId);

            // 5. Rewire depends_on references
            $this->rewireDependencies($uuidMap);

            // 6. Clone resource links
            $this->cloneResourceLinks($source, $newEnv, $uuidMap);

            // 7. Clone shared environment variables
            if ($cloneEnvVars) {
                $this->cloneSharedVariables($source, $newEnv);
            }

            Log::info("Environment cloned: {$source->name} → {$newEnv->name}", [
                'source_id' => $source->id,
                'target_id' => $newEnv->id,
                'resources' => count($uuidMap),
            ]);

            return $newEnv->fresh();
        });
    }

    private function cloneDatabases(Environment $source, Environment $target, array &$uuidMap, bool $cloneEnvVars, bool $cloneBackupConfigs, ?int $targetServerId): void
    {
        $dbTypes = [
            'postgresqls', 'mysqls', 'mariadbs', 'mongodbs',
            'redis', 'keydbs', 'dragonflies', 'clickhouses',
        ];

        foreach ($dbTypes as $type) {
            foreach ($source->$type as $db) {
                $newUuid = (string) new Cuid2;
                $newDb = $db->replicate();
                $newDb->uuid = $newUuid;
                $newDb->environment_id = $target->id;
                $newDb->status = 'exited';
                $newDb->name = $db->name.'-clone';

                // Generate fresh credentials for the clone
                if (method_exists($newDb, 'getFillable') && in_array('postgres_password', $newDb->getFillable())) {
                    $newDb->postgres_password = Str::password(32, symbols: false);
                }
                if (method_exists($newDb, 'getFillable') && in_array('mysql_root_password', $newDb->getFillable())) {
                    $newDb->mysql_root_password = Str::password(32, symbols: false);
                    $newDb->mysql_password = Str::password(32, symbols: false);
                }
                if (method_exists($newDb, 'getFillable') && in_array('mariadb_root_password', $newDb->getFillable())) {
                    $newDb->mariadb_root_password = Str::password(32, symbols: false);
                    $newDb->mariadb_password = Str::password(32, symbols: false);
                }
                if (method_exists($newDb, 'getFillable') && in_array('mongo_initdb_root_password', $newDb->getFillable())) {
                    $newDb->mongo_initdb_root_password = Str::password(32, symbols: false);
                }

                $newDb->save();

                $uuidMap[$db->uuid] = [
                    'model' => $newDb,
                    'type' => get_class($db),
                    'old_id' => $db->id,
                    'new_id' => $newDb->id,
                ];

                // Clone volumes (structure only)
                $this->cloneVolumes($db, $newDb);

                // Clone env vars
                if ($cloneEnvVars) {
                    $this->cloneEnvironmentVariables($db, $newDb);
                }

                // Clone backup configs
                if ($cloneBackupConfigs) {
                    $this->cloneBackupConfigs($db, $newDb);
                }
            }
        }
    }

    private function cloneApplications(Environment $source, Environment $target, array &$uuidMap, bool $cloneEnvVars, bool $cloneScheduledTasks, ?int $targetServerId): void
    {
        foreach ($source->applications as $app) {
            $newUuid = (string) new Cuid2;

            $excludeFields = ['id', 'uuid', 'environment_id', 'status', 'created_at', 'updated_at', 'deleted_at',
                'last_online_at', 'config_hash', 'last_successful_deployment_id',
                'restart_count', 'last_restart_at', 'last_restart_type'];

            $newApp = $app->replicate($excludeFields);
            $newApp->uuid = $newUuid;
            $newApp->environment_id = $target->id;
            $newApp->status = 'exited';
            $newApp->name = $app->name.'-clone';
            $newApp->fqdn = null; // Must be reconfigured to avoid domain conflicts
            $newApp->save();

            // Clone application settings
            if ($app->settings) {
                $newSettings = $app->settings->replicate(['id', 'application_id']);
                $newSettings->application_id = $newApp->id;
                $newSettings->save();
            }

            $uuidMap[$app->uuid] = [
                'model' => $newApp,
                'type' => Application::class,
                'old_id' => $app->id,
                'new_id' => $newApp->id,
            ];

            // Clone volumes
            $this->cloneVolumes($app, $newApp);

            // Clone env vars
            if ($cloneEnvVars) {
                $this->cloneEnvironmentVariables($app, $newApp);
            }

            // Clone scheduled tasks
            if ($cloneScheduledTasks) {
                foreach ($app->scheduled_tasks ?? [] as $task) {
                    $newTask = $task->replicate(['id', 'application_id']);
                    $newTask->application_id = $newApp->id;
                    $newTask->save();
                }
            }
        }
    }

    private function cloneServices(Environment $source, Environment $target, array &$uuidMap, bool $cloneEnvVars, ?int $targetServerId): void
    {
        foreach ($source->services as $service) {
            $newUuid = (string) new Cuid2;

            $newService = $service->replicate(['id', 'uuid', 'environment_id', 'config_hash', 'created_at', 'updated_at', 'deleted_at']);
            $newService->uuid = $newUuid;
            $newService->environment_id = $target->id;
            $newService->name = $service->name.'-clone';
            $newService->save();

            $uuidMap[$service->uuid] = [
                'model' => $newService,
                'type' => Service::class,
                'old_id' => $service->id,
                'new_id' => $newService->id,
            ];

            if ($cloneEnvVars) {
                $this->cloneEnvironmentVariables($service, $newService);
            }
        }
    }

    private function cloneVolumes($source, $target): void
    {
        foreach ($source->persistentStorages ?? collect() as $volume) {
            $newVolume = $volume->replicate(['id']);
            $newVolume->name = $volume->name.'-'.Str::random(6);
            $newVolume->resource_id = $target->id;
            $newVolume->resource_type = get_class($target);
            $newVolume->save();
        }
    }

    private function cloneEnvironmentVariables($source, $target): void
    {
        $vars = EnvironmentVariable::where('resourceable_type', get_class($source))
            ->where('resourceable_id', $source->id)
            ->get();

        foreach ($vars as $var) {
            $newVar = $var->replicate(['id', 'uuid', 'resourceable_id']);
            $newVar->uuid = (string) new Cuid2;
            $newVar->resourceable_id = $target->id;
            $newVar->resourceable_type = get_class($target);
            $newVar->save();
        }
    }

    private function cloneBackupConfigs($source, $target): void
    {
        $backups = ScheduledDatabaseBackup::where('database_type', get_class($source))
            ->where('database_id', $source->id)
            ->get();

        foreach ($backups as $backup) {
            $newBackup = $backup->replicate(['id', 'uuid', 'database_id']);
            $newBackup->uuid = (string) new Cuid2;
            $newBackup->database_id = $target->id;
            $newBackup->database_type = get_class($target);
            $newBackup->enabled = false; // Disabled by default for safety
            $newBackup->save();
        }
    }

    private function cloneSharedVariables(Environment $source, Environment $target): void
    {
        foreach ($source->environment_variables as $var) {
            $newVar = $var->replicate(['id']);
            $newVar->environment_id = $target->id;
            $newVar->save();
        }
    }

    private function cloneResourceLinks(Environment $source, Environment $target, array $uuidMap): void
    {
        $links = ResourceLink::where('environment_id', $source->id)->get();

        foreach ($links as $link) {
            // Find new source and target resources
            $newSourceModel = null;
            $newTargetModel = null;

            foreach ($uuidMap as $oldUuid => $mapped) {
                if ($mapped['old_id'] === $link->source_id && $mapped['type'] === $link->source_type) {
                    $newSourceModel = $mapped['model'];
                }
                if ($mapped['old_id'] === $link->target_id && $mapped['type'] === $link->target_type) {
                    $newTargetModel = $mapped['model'];
                }
            }

            if ($newSourceModel && $newTargetModel) {
                ResourceLink::create([
                    'source_type' => $link->source_type,
                    'source_id' => $newSourceModel->id,
                    'target_type' => $link->target_type,
                    'target_id' => $newTargetModel->id,
                    'environment_id' => $target->id,
                    'inject_as' => $link->inject_as,
                    'auto_inject' => $link->auto_inject,
                    'use_external_url' => $link->use_external_url,
                ]);
            }
        }
    }

    /**
     * Rewrite depends_on arrays to reference new UUIDs instead of old ones.
     */
    private function rewireDependencies(array $uuidMap): void
    {
        // Build old→new UUID map
        $oldToNew = [];
        foreach ($uuidMap as $oldUuid => $mapped) {
            $oldToNew[$oldUuid] = $mapped['model']->uuid;
        }

        foreach ($uuidMap as $oldUuid => $mapped) {
            $model = $mapped['model'];
            if (! isset($model->depends_on) || empty($model->depends_on)) {
                continue;
            }

            $newDeps = [];
            foreach ($model->depends_on as $depUuid) {
                $newDeps[] = $oldToNew[$depUuid] ?? $depUuid;
            }

            $model->depends_on = $newDeps;
            $model->save();
        }
    }
}
