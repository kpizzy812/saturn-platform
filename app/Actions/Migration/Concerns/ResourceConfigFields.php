<?php

namespace App\Actions\Migration\Concerns;

use App\Models\Application;
use App\Models\Service;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared trait for migration actions providing resource type detection,
 * database relation mapping, and config field whitelists.
 */
trait ResourceConfigFields
{
    /**
     * Database model class names.
     */
    protected static array $databaseModels = [
        'App\Models\StandalonePostgresql',
        'App\Models\StandaloneMysql',
        'App\Models\StandaloneMariadb',
        'App\Models\StandaloneMongodb',
        'App\Models\StandaloneRedis',
        'App\Models\StandaloneClickhouse',
        'App\Models\StandaloneKeydb',
        'App\Models\StandaloneDragonfly',
    ];

    /**
     * Database class to environment relation method mapping.
     */
    protected static array $databaseRelationMap = [
        'App\Models\StandalonePostgresql' => 'postgresqls',
        'App\Models\StandaloneMysql' => 'mysqls',
        'App\Models\StandaloneMariadb' => 'mariadbs',
        'App\Models\StandaloneMongodb' => 'mongodbs',
        'App\Models\StandaloneRedis' => 'redis',
        'App\Models\StandaloneClickhouse' => 'clickhouses',
        'App\Models\StandaloneKeydb' => 'keydbs',
        'App\Models\StandaloneDragonfly' => 'dragonflies',
    ];

    /**
     * Check if resource is a database.
     */
    protected function isDatabase(Model $resource): bool
    {
        return in_array(get_class($resource), static::$databaseModels);
    }

    /**
     * Get the relation method for a database class.
     */
    protected function getDatabaseRelationMethod(string $class): ?string
    {
        return static::$databaseRelationMap[$class] ?? null;
    }

    /**
     * Get whitelisted configuration fields for a resource type.
     * Used for safe config-only updates (promote mode).
     */
    protected function getConfigFields(Model $source): array
    {
        if ($source instanceof Application) {
            return [
                // Git/source settings
                'git_repository',
                'git_branch',
                'git_full_url',
                'repository_project_id',
                'deploy_key_id',
                'source_id',
                'source_type',
                // Build settings
                'build_pack',
                'static_image',
                'install_command',
                'build_command',
                'start_command',
                'base_directory',
                'publish_directory',
                'dockerfile',
                'dockerfile_location',
                'dockerfile_target_build',
                'docker_compose_location',
                'docker_compose_custom_start_command',
                'docker_compose_custom_build_command',
                'docker_compose',
                'docker_compose_raw',
                'docker_compose_domains',
                'docker_registry_image_name',
                'docker_registry_image_tag',
                // Runtime settings
                'ports_exposes',
                'ports_mappings',
                'custom_labels',
                'custom_docker_run_options',
                'post_deployment_command',
                'post_deployment_command_container',
                'pre_deployment_command',
                'pre_deployment_command_container',
                // Resource limits
                'limits_memory',
                'limits_memory_swap',
                'limits_memory_swappiness',
                'limits_memory_reservation',
                'limits_cpus',
                'limits_cpuset',
                'limits_cpu_shares',
                // Health check settings
                'health_check_enabled',
                'health_check_path',
                'health_check_port',
                'health_check_host',
                'health_check_method',
                'health_check_scheme',
                'health_check_return_code',
                'health_check_response_text',
                'health_check_interval',
                'health_check_timeout',
                'health_check_retries',
                'health_check_start_period',
            ];
        }

        if ($source instanceof Service) {
            return [
                'docker_compose_raw',
                'docker_compose',
                'connect_to_docker_network',
                'is_container_label_escape_enabled',
                'is_container_label_readonly_enabled',
                'limits_memory',
                'limits_memory_swap',
                'limits_memory_swappiness',
                'limits_memory_reservation',
                'limits_cpus',
                'limits_cpuset',
                'limits_cpu_shares',
            ];
        }

        // For databases - update connection settings but not data
        if ($this->isDatabase($source)) {
            return [
                'image',
                'is_public',
                'ports_mappings',
                'limits_memory',
                'limits_memory_swap',
                'limits_memory_swappiness',
                'limits_memory_reservation',
                'limits_cpus',
                'limits_cpuset',
                'limits_cpu_shares',
            ];
        }

        return [];
    }

    /**
     * Get safe configuration attributes using whitelist approach.
     * Only returns attributes that are in the whitelist for the resource type.
     */
    protected function getSafeConfigAttributes(Model $source): array
    {
        $whitelist = $this->getConfigFields($source);

        return collect($source->getAttributes())
            ->only($whitelist)
            ->filter(fn ($value) => $value !== null)
            ->toArray();
    }
}
