<?php

namespace App\Services\SaturnYaml;

use App\Models\Application;
use App\Models\Environment;
use App\Models\EnvironmentVariable;
use App\Models\ScheduledDatabaseBackup;
use App\Models\ScheduledTask;
use App\Models\StandaloneClickhouse;
use App\Models\StandaloneDragonfly;
use App\Models\StandaloneKeydb;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use App\Models\StandaloneRedis;
use App\Services\SaturnYaml\DTOs\ReconciliationPlan;
use App\Services\SaturnYaml\DTOs\SaturnYamlApplication;
use App\Services\SaturnYaml\DTOs\SaturnYamlConfig;
use App\Services\SaturnYaml\DTOs\SaturnYamlDatabase;
use Illuminate\Support\Str;
use Visus\Cuid2\Cuid2;

/**
 * Compares saturn.yaml config with current environment state and creates/updates resources.
 *
 * SAFETY: Never deletes resources automatically — only create and update.
 */
class SaturnYamlReconciler
{
    /**
     * Generate a plan without executing it (dry-run).
     */
    public function plan(SaturnYamlConfig $config, Environment $environment): ReconciliationPlan
    {
        $plan = new ReconciliationPlan;
        $this->planDatabases($config, $environment, $plan);
        $this->planApplications($config, $environment, $plan);
        $this->planCronJobs($config, $environment, $plan);

        return $plan;
    }

    /**
     * Execute reconciliation: create/update resources to match saturn.yaml.
     */
    public function reconcile(SaturnYamlConfig $config, Environment $environment): ReconciliationPlan
    {
        $plan = new ReconciliationPlan;

        // 1. Databases first (applications may depend on them)
        $dbNameToUuid = $this->reconcileDatabases($config, $environment, $plan);

        // 2. Applications
        $appNameToUuid = $this->reconcileApplications($config, $environment, $plan, $dbNameToUuid);

        // 3. Resolve depends_on by name → UUID
        $nameToUuid = array_merge($dbNameToUuid, $appNameToUuid);
        $this->resolveDependsOn($config, $environment, $nameToUuid);

        // 4. Cron jobs
        $this->reconcileCronJobs($config, $environment, $plan, $appNameToUuid);

        // 5. Shared variables
        $this->reconcileSharedVariables($config, $environment);

        // 6. Update environment yaml hash
        $environment->update([
            'saturn_yaml_hash' => $config->hash(),
            'saturn_yaml_last_synced_at' => now(),
        ]);

        return $plan;
    }

    /**
     * @return array<string, string> name → uuid mapping
     */
    private function reconcileDatabases(SaturnYamlConfig $config, Environment $environment, ReconciliationPlan $plan): array
    {
        $nameToUuid = [];

        foreach ($config->databases as $name => $dbConfig) {
            $existing = $this->findDatabaseByYamlName($environment, $name);

            if ($existing) {
                $this->updateDatabase($existing, $dbConfig);
                $plan->addUpdate('database', $name);
                $nameToUuid[$name] = $existing->uuid;
            } else {
                $db = $this->createDatabase($environment, $name, $dbConfig);
                $plan->addCreate('database', $name);
                $nameToUuid[$name] = $db->uuid;
            }
        }

        return $nameToUuid;
    }

    /**
     * @return array<string, string> name → uuid mapping
     */
    private function reconcileApplications(SaturnYamlConfig $config, Environment $environment, ReconciliationPlan $plan, array $dbNameToUuid): array
    {
        $nameToUuid = [];

        foreach ($config->applications as $name => $appConfig) {
            $existing = Application::where('environment_id', $environment->id)
                ->where('yaml_resource_name', $name)
                ->first();

            if ($existing) {
                $this->updateApplication($existing, $appConfig);
                $this->syncEnvironmentVariables($existing, $appConfig->environment, $dbNameToUuid);
                $plan->addUpdate('application', $name);
                $nameToUuid[$name] = $existing->uuid;
            } else {
                $app = $this->createApplication($environment, $name, $appConfig);
                $this->syncEnvironmentVariables($app, $appConfig->environment, $dbNameToUuid);
                $plan->addCreate('application', $name);
                $nameToUuid[$name] = $app->uuid;
            }
        }

        return $nameToUuid;
    }

    private function reconcileCronJobs(SaturnYamlConfig $config, Environment $environment, ReconciliationPlan $plan, array $appNameToUuid): void
    {
        foreach ($config->cron as $name => $cronConfig) {
            $appUuid = $appNameToUuid[$cronConfig->container] ?? null;
            if (! $appUuid) {
                $plan->addWarning("Cron '{$name}': container '{$cronConfig->container}' not found, skipping.");

                continue;
            }

            $app = Application::where('uuid', $appUuid)->first();
            if (! $app) {
                continue;
            }

            $existing = ScheduledTask::where('application_id', $app->id)
                ->where('name', $name)
                ->first();

            if ($existing) {
                $existing->update([
                    'command' => $cronConfig->command,
                    'frequency' => $cronConfig->schedule,
                    'timeout' => $cronConfig->timeout,
                ]);
                $plan->addUpdate('cron', $name);
            } else {
                ScheduledTask::create([
                    'name' => $name,
                    'command' => $cronConfig->command,
                    'frequency' => $cronConfig->schedule,
                    'timeout' => $cronConfig->timeout,
                    'application_id' => $app->id,
                    'team_id' => $environment->project->team_id,
                    'enabled' => true,
                ]);
                $plan->addCreate('cron', $name);
            }
        }
    }

    private function reconcileSharedVariables(SaturnYamlConfig $config, Environment $environment): void
    {
        foreach ($config->sharedVariables as $key => $value) {
            // Skip reference values (handled at runtime)
            if (str_starts_with($value, '@')) {
                continue;
            }

            $environment->environment_variables()->updateOrCreate(
                ['key' => $key],
                ['value' => $value],
            );
        }
    }

    private function createApplication(Environment $environment, string $name, SaturnYamlApplication $config): Application
    {
        $destination = $environment->defaultServer?->standaloneDockers()->first()
            ?? \App\Models\StandaloneDocker::first();

        $app = Application::create([
            'uuid' => (string) new Cuid2,
            'name' => $name,
            'environment_id' => $environment->id,
            'destination_id' => $destination?->id,
            'destination_type' => $destination ? get_class($destination) : 'App\Models\StandaloneDocker',
            'build_pack' => $config->build,
            'build_pack_explicitly_set' => true,
            'application_type' => $config->applicationType,
            'git_branch' => $config->gitBranch ?? $environment->default_git_branch ?? 'main',
            'base_directory' => $config->baseDirectory ?? '/',
            'publish_directory' => $config->publishDirectory,
            'install_command' => $config->installCommand,
            'build_command' => $config->buildCommand,
            'start_command' => $config->startCommand,
            'dockerfile' => $config->dockerfile,
            'dockerfile_location' => $config->dockerfileLocation ?? '/Dockerfile',
            'fqdn' => ! empty($config->domains) ? implode(',', array_map(fn ($d) => "https://{$d}", $config->domains)) : null,
            'ports_exposes' => $config->ports ?? '3000',
            'watch_paths' => ! empty($config->watchPaths) ? implode("\n", $config->watchPaths) : null,
            'pre_deployment_command' => $config->hooks['pre_deploy'] ?? null,
            'post_deployment_command' => $config->hooks['post_deploy'] ?? null,
            'health_check_enabled' => ! empty($config->healthcheck),
            'health_check_path' => $config->healthcheck['path'] ?? '/healthz',
            'health_check_interval' => $config->healthcheck['interval'] ?? 30,
            'health_check_timeout' => $config->healthcheck['timeout'] ?? 10,
            'health_check_retries' => $config->healthcheck['retries'] ?? 3,
            'managed_by_yaml' => true,
            'yaml_resource_name' => $name,
        ]);

        return $app;
    }

    private function updateApplication(Application $app, SaturnYamlApplication $config): void
    {
        $updates = [
            'build_pack' => $config->build,
            'application_type' => $config->applicationType,
            'base_directory' => $config->baseDirectory ?? $app->base_directory,
            'publish_directory' => $config->publishDirectory,
            'install_command' => $config->installCommand,
            'build_command' => $config->buildCommand,
            'start_command' => $config->startCommand,
            'watch_paths' => ! empty($config->watchPaths) ? implode("\n", $config->watchPaths) : null,
            'pre_deployment_command' => $config->hooks['pre_deploy'] ?? $app->pre_deployment_command,
            'post_deployment_command' => $config->hooks['post_deploy'] ?? $app->post_deployment_command,
        ];

        if (! empty($config->domains)) {
            $updates['fqdn'] = implode(',', array_map(fn ($d) => "https://{$d}", $config->domains));
        }

        if ($config->ports) {
            $updates['ports_exposes'] = $config->ports;
        }

        if (! empty($config->healthcheck)) {
            $updates['health_check_enabled'] = true;
            $updates['health_check_path'] = $config->healthcheck['path'] ?? $app->health_check_path;
            $updates['health_check_interval'] = $config->healthcheck['interval'] ?? $app->health_check_interval;
        }

        if ($config->gitBranch) {
            $updates['git_branch'] = $config->gitBranch;
        }

        $app->update($updates);
    }

    private function syncEnvironmentVariables(Application $app, array $envVars, array $dbNameToUuid): void
    {
        foreach ($envVars as $key => $value) {
            // Resolve database references: @db.connection_string → actual URL
            $resolvedValue = $this->resolveEnvValue($value, $dbNameToUuid);

            EnvironmentVariable::updateOrCreate(
                [
                    'key' => $key,
                    'resourceable_type' => Application::class,
                    'resourceable_id' => $app->id,
                    'is_preview' => false,
                ],
                [
                    'value' => $resolvedValue,
                    'is_build_time' => false,
                ],
            );
        }
    }

    /**
     * Resolve @db.connection_string references to actual database URLs.
     */
    private function resolveEnvValue(string $value, array $dbNameToUuid): string
    {
        if (! str_starts_with($value, '@')) {
            return $value;
        }

        // Parse @resource_name.property
        $parts = explode('.', substr($value, 1), 2);
        $resourceName = $parts[0] ?? '';
        $property = $parts[1] ?? 'connection_string';

        $uuid = $dbNameToUuid[$resourceName] ?? null;
        if (! $uuid) {
            return $value; // Keep as-is if not resolvable
        }

        // Find the database and get its URL
        $dbModels = [
            StandalonePostgresql::class, StandaloneMysql::class, StandaloneMariadb::class,
            StandaloneMongodb::class, StandaloneRedis::class, StandaloneKeydb::class,
            StandaloneDragonfly::class, StandaloneClickhouse::class,
        ];

        foreach ($dbModels as $modelClass) {
            $db = $modelClass::where('uuid', $uuid)->first();
            if ($db) {
                return match ($property) {
                    'connection_string', 'url' => $db->internal_db_url ?? $value,
                    'host' => $db->uuid, // Internal Docker hostname
                    default => $value,
                };
            }
        }

        return $value;
    }

    private function createDatabase(Environment $environment, string $name, SaturnYamlDatabase $config): object
    {
        $destination = $environment->defaultServer?->standaloneDockers()->first()
            ?? \App\Models\StandaloneDocker::first();

        $modelClass = match ($config->type) {
            'postgresql' => StandalonePostgresql::class,
            'mysql' => StandaloneMysql::class,
            'mariadb' => StandaloneMariadb::class,
            'mongodb' => StandaloneMongodb::class,
            'redis' => StandaloneRedis::class,
            'keydb' => StandaloneKeydb::class,
            'dragonfly' => StandaloneDragonfly::class,
            'clickhouse' => StandaloneClickhouse::class,
            default => StandalonePostgresql::class,
        };

        $fields = [
            'uuid' => (string) new Cuid2,
            'name' => $name,
            'environment_id' => $environment->id,
            'destination_id' => $destination?->id,
            'destination_type' => $destination ? get_class($destination) : 'App\Models\StandaloneDocker',
            'managed_by_yaml' => true,
            'yaml_resource_name' => $name,
            'status' => 'exited',
        ];

        // Set image if specified
        if ($config->image) {
            $fields['image'] = $config->image;
        } elseif ($config->version) {
            $defaultImages = [
                'postgresql' => "postgres:{$config->version}",
                'mysql' => "mysql:{$config->version}",
                'mariadb' => "mariadb:{$config->version}",
                'mongodb' => "mongo:{$config->version}",
                'redis' => "redis:{$config->version}",
                'keydb' => "eqalpha/keydb:{$config->version}",
                'dragonfly' => "docker.dragonflydb.io/dragonflydb/dragonfly:{$config->version}",
                'clickhouse' => "clickhouse/clickhouse-server:{$config->version}",
            ];
            $fields['image'] = $defaultImages[$config->type] ?? null;
        }

        // Type-specific credential fields
        if ($config->type === 'postgresql') {
            $fields['postgres_user'] = 'saturn';
            $fields['postgres_password'] = Str::password(32, symbols: false);
            $fields['postgres_db'] = $name;
        } elseif (in_array($config->type, ['mysql', 'mariadb'])) {
            $prefix = $config->type === 'mysql' ? 'mysql' : 'mariadb';
            $fields["{$prefix}_root_password"] = Str::password(32, symbols: false);
            $fields["{$prefix}_user"] = 'saturn';
            $fields["{$prefix}_password"] = Str::password(32, symbols: false);
            $fields["{$prefix}_database"] = $name;
        } elseif ($config->type === 'mongodb') {
            $fields['mongo_initdb_root_username'] = 'saturn';
            $fields['mongo_initdb_root_password'] = Str::password(32, symbols: false);
            $fields['mongo_initdb_database'] = $name;
        }

        $db = $modelClass::create($fields);

        // Create backup schedule if configured
        if (! empty($config->backups['schedule'])) {
            ScheduledDatabaseBackup::create([
                'uuid' => (string) new Cuid2,
                'database_id' => $db->id,
                'database_type' => get_class($db),
                'frequency' => $config->backups['schedule'],
                'number_of_backups_locally' => $config->backups['retention'] ?? 7,
                'enabled' => true,
            ]);
        }

        return $db;
    }

    private function updateDatabase(object $db, SaturnYamlDatabase $config): void
    {
        $updates = [];

        if ($config->image) {
            $updates['image'] = $config->image;
        }

        if ($config->isPublic !== (bool) ($db->is_public ?? false)) {
            $updates['is_public'] = $config->isPublic;
        }

        if (! empty($updates)) {
            $db->update($updates);
        }
    }

    private function findDatabaseByYamlName(Environment $environment, string $name): ?object
    {
        $databases = $environment->databases();

        foreach ($databases as $db) {
            if (($db->yaml_resource_name ?? null) === $name) {
                return $db;
            }
        }

        return null;
    }

    private function resolveDependsOn(SaturnYamlConfig $config, Environment $environment, array $nameToUuid): void
    {
        foreach ($config->applications as $name => $appConfig) {
            if (empty($appConfig->dependsOn)) {
                continue;
            }

            $app = Application::where('environment_id', $environment->id)
                ->where('yaml_resource_name', $name)
                ->first();

            if (! $app) {
                continue;
            }

            $resolvedDeps = [];
            foreach ($appConfig->dependsOn as $depName) {
                if (isset($nameToUuid[$depName])) {
                    $resolvedDeps[] = $nameToUuid[$depName];
                }
            }

            $app->update(['depends_on' => $resolvedDeps]);
        }
    }

    private function planDatabases(SaturnYamlConfig $config, Environment $environment, ReconciliationPlan $plan): void
    {
        foreach ($config->databases as $name => $dbConfig) {
            $existing = $this->findDatabaseByYamlName($environment, $name);
            if ($existing) {
                $plan->addUpdate('database', $name);
            } else {
                $plan->addCreate('database', $name);
            }
        }
    }

    private function planApplications(SaturnYamlConfig $config, Environment $environment, ReconciliationPlan $plan): void
    {
        foreach ($config->applications as $name => $appConfig) {
            $existing = Application::where('environment_id', $environment->id)
                ->where('yaml_resource_name', $name)
                ->first();
            if ($existing) {
                $plan->addUpdate('application', $name);
            } else {
                $plan->addCreate('application', $name);
            }
        }
    }

    private function planCronJobs(SaturnYamlConfig $config, Environment $environment, ReconciliationPlan $plan): void
    {
        foreach ($config->cron as $name => $cronConfig) {
            $plan->addCreate('cron', $name, ['container' => $cronConfig->container]);
        }
    }
}
