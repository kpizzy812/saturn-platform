<?php

/**
 * Fixes PHPStan property.notFound errors by adding missing @property annotations
 * to Eloquent models based on DB schema + $casts + $fillable analysis.
 *
 * Run: docker exec saturn php fix_phpstan.php
 */
chdir('/var/www/html');
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Classes and their missing properties (from PHPStan output)
$missingByClass = [
    'App\\Models\\Application' => ['dockerfile', 'docker_compose_domains', 'private_key_id', 'source_type', 'source_id', 'repository_project_id', 'base_directory', 'health_check_enabled', 'docker_registry_image_name', 'description', 'watch_paths', 'dockerfile_location', 'dockerfile_target_build', 'docker_registry_image_tag', 'auto_inject_database_url', 'health_check_port', 'health_check_host', 'health_check_scheme', 'health_check_method', 'health_check_path', 'last_restart_at', 'application_type', 'build_command', 'custom_network_aliases', 'custom_nginx_configuration', 'git_commit_sha', 'install_command', 'publish_directory', 'redirect', 'start_command', 'static_image', 'config_hash', 'compose_parsing_version', 'docker_compose_location', 'custom_healthcheck_found', 'health_check_interval', 'health_check_timeout', 'health_check_retries', 'health_check_start_period', 'limits_memory', 'limits_memory_swap', 'limits_memory_swappiness', 'limits_memory_reservation', 'limits_cpus', 'limits_cpuset', 'limits_cpu_shares', 'monorepo_group_id', 'preview_url_template', 'docker_compose', 'post_deployment_command', 'post_deployment_command_container', 'pre_deployment_command', 'pre_deployment_command_container', 'docker_compose_custom_start_command', 'docker_compose_custom_build_command', 'health_check_return_code', 'health_check_response_text', 'manual_webhook_secret_github', 'manual_webhook_secret_gitlab', 'manual_webhook_secret_bitbucket', 'manual_webhook_secret_gitea', 'swarm_replicas', 'swarm_placement_constraints', 'build_pack_explicitly_set'],
    'App\\Models\\Service' => ['docker_compose_raw', 'environment_id', 'server_id', 'destination_id', 'destination_type', 'name', 'description', 'connect_to_docker_network', 'limits_cpu_shares', 'limits_cpus', 'limits_cpuset', 'limits_memory', 'limits_memory_reservation', 'limits_memory_swap', 'limits_memory_swappiness', 'docker_compose', 'service_type', 'created_at', 'updated_at', 'config_hash', 'compose_parsing_version'],
    'App\\Models\\User' => ['email', 'name', 'created_at', 'updated_at', 'email_verified_at', 'two_factor_confirmed_at', 'marketing_emails', 'is_superadmin', 'platform_role', 'password', 'avatar', 'force_password_reset', 'two_factor_secret', 'suspension_reason', 'status', 'email_change_code', 'email_change_code_expires_at', 'pending_email'],
    'App\\Models\\ApplicationPreview' => ['status', 'fqdn', 'pull_request_issue_comment_id', 'pull_request_id', 'docker_compose_domains'],
    'App\\Models\\Environment' => ['name', 'project_id', 'type', 'requires_approval', 'default_server_id'],
    'App\\Models\\TeamInvitation' => ['email', 'invited_by', 'allowed_projects', 'permission_set_id', 'custom_permissions', 'team_id', 'role', 'created_at', 'link', 'uuid'],
    'App\\Models\\Server' => ['name', 'proxy', 'ip', 'team_id', 'hetzner_server_id', 'cloud_provider_token_id', 'user', 'private_key_id', 'port', 'description', 'created_at', 'updated_at', 'hetzner_server_status', 'sentinel_updated_at', 'unreachable_notification_sent', 'unreachable_count'],
    'App\\Models\\Project' => ['description', 'team_id', 'name'],
    'App\\Models\\ProjectSetting' => ['default_server_id', 'max_applications', 'max_services', 'max_databases', 'max_environments'],
    'App\\Models\\ServerSetting' => ['is_cloudflare_tunnel', 'is_logdrain_newrelic_enabled', 'is_logdrain_highlight_enabled', 'is_logdrain_axiom_enabled', 'is_logdrain_custom_enabled', 'logdrain_custom_config', 'logdrain_custom_config_parser', 'logdrain_newrelic_license_key', 'logdrain_newrelic_base_uri', 'logdrain_highlight_project_id', 'logdrain_axiom_dataset_name', 'logdrain_axiom_api_key', 'is_sentinel_enabled', 'force_disabled', 'is_reachable', 'is_usable', 'dynamic_timeout', 'force_docker_cleanup', 'docker_cleanup_threshold', 'server_timezone', 'sentinel_token', 'is_build_server', 'sentinel_push_interval_seconds', 'is_metrics_enabled', 'is_master_server', 'sentinel_custom_url', 'generate_exact_labels'],
    'App\\Models\\Team' => ['name', 'personal_team', 'max_servers', 'max_applications', 'max_databases', 'max_projects', 'description', 'created_at', 'updated_at', 'logo'],
    'App\\Models\\InstanceSettings' => ['update_check_frequency', 'instance_timezone', 'is_auto_update_enabled', 'auto_update_frequency', 'resource_monitoring_enabled', 'do_not_track', 'auto_provision_server_type', 'auto_provision_location', 'resource_critical_cpu_threshold', 'resource_warning_cpu_threshold', 'resource_critical_memory_threshold', 'resource_warning_memory_threshold', 'resource_critical_disk_threshold', 'resource_warning_disk_threshold', 'auto_provision_enabled', 'auto_provision_max_servers_per_day', 'is_cloudflare_protection_enabled', 'app_default_auto_deploy', 'app_default_force_https', 'app_default_preview_deployments', 'app_default_pr_deployments_public', 'app_default_git_submodules', 'app_default_git_lfs', 'app_default_git_shallow_clone', 'app_default_use_build_secrets', 'app_default_inject_build_args', 'app_default_include_commit_in_build', 'app_default_docker_images_to_keep', 'app_default_auto_rollback', 'app_default_rollback_validation_sec', 'app_default_rollback_max_restarts', 'app_default_rollback_on_health_fail', 'app_default_rollback_on_crash_loop', 'app_default_debug', 'app_default_build_pack', 'instance_name', 'cloudflare_api_token', 'cloudflare_account_id', 'cloudflare_tunnel_id', 'cloudflare_zone_id'],
    'App\\Models\\PrivateKey' => ['private_key', 'name', 'team_id'],
    'App\\Models\\ApplicationSetting' => ['is_pr_deployments_public_enabled', 'disable_build_cache', 'is_preserve_repository_enabled', 'custom_internal_name', 'is_consistent_container_name_enabled', 'include_source_commit_in_build', 'is_static', 'use_build_secrets', 'auto_rollback_enabled', 'rollback_on_crash_loop', 'rollback_on_health_check_fail', 'is_auto_deploy_enabled', 'is_preview_deployments_enabled', 'inject_build_args_to_dockerfile', 'is_git_submodules_enabled', 'is_git_lfs_enabled', 'is_raw_compose_deployment_enabled', 'is_env_sorting_enabled', 'is_container_label_readonly_enabled', 'is_container_label_escape_enabled', 'is_gpu_enabled', 'is_spa'],
    'App\\Models\\GithubApp' => ['contents', 'metadata', 'pull_requests', 'administration'],
];

// Cast type → PHPStan type mapping
function castToPhpType(string $cast): string
{
    return match (true) {
        in_array($cast, ['bool', 'boolean']) => 'bool',
        in_array($cast, ['int', 'integer']) => 'int',
        in_array($cast, ['float', 'double', 'real']) => 'float',
        in_array($cast, ['string']) => 'string',
        in_array($cast, ['array', 'json', 'collection', 'object']) => 'array',
        str_starts_with($cast, 'datetime') || $cast === 'date' || $cast === 'immutable_date' || $cast === 'immutable_datetime' => '\\Carbon\\Carbon',
        $cast === 'encrypted' => 'string',
        $cast === 'encrypted:array' || $cast === 'encrypted:collection' => 'array',
        default => 'mixed',
    };
}

// Determine property type from DB column + casts
function getPropertyType(string $class, string $prop, array $casts, array $dbColumns): string
{
    // Special known nullable fields
    $alwaysNullable = ['created_at', 'updated_at', 'deleted_at', 'email_verified_at',
        'two_factor_confirmed_at', 'last_restart_at', 'sentinel_updated_at',
        'two_factor_secret', 'suspension_reason', 'email_change_code',
        'email_change_code_expires_at', 'pending_email', 'avatar',
        'hetzner_server_id', 'cloud_provider_token_id', 'hetzner_server_status'];

    if (isset($casts[$prop])) {
        $phpType = castToPhpType($casts[$prop]);
        // datetime-like or special nullable
        if (in_array($prop, $alwaysNullable) || str_starts_with($casts[$prop], 'datetime') || $casts[$prop] === 'date') {
            return $phpType.'|null';
        }

        return $phpType;
    }

    // Check DB column info
    if (isset($dbColumns[$prop])) {
        $col = $dbColumns[$prop];
        $nullable = $col['nullable'] ?? true;

        $type = match (true) {
            in_array($col['type_name'] ?? '', ['bool', 'boolean', 'tinyint(1)']) => 'bool',
            in_array($col['type_name'] ?? '', ['int', 'integer', 'bigint', 'smallint', 'tinyint']) => 'int',
            in_array($col['type_name'] ?? '', ['float', 'double', 'decimal', 'numeric']) => 'float',
            in_array($col['type_name'] ?? '', ['json', 'jsonb']) => 'array',
            in_array($col['type_name'] ?? '', ['timestamp', 'datetime', 'date']) => '\\Carbon\\Carbon',
            default => 'string',
        };

        if ($nullable || in_array($prop, $alwaysNullable)) {
            return $type.'|null';
        }

        return $type;
    }

    // Heuristics for known patterns
    if (in_array($prop, $alwaysNullable)) {
        return 'string|null';
    }
    if (str_starts_with($prop, 'is_') || str_starts_with($prop, 'has_') ||
        str_starts_with($prop, 'force_') || str_ends_with($prop, '_enabled') ||
        str_ends_with($prop, '_public') || $prop === 'personal_team' || $prop === 'marketing_emails' ||
        $prop === 'is_superadmin' || $prop === 'connect_to_docker_network' || $prop === 'do_not_track' ||
        $prop === 'resource_monitoring_enabled' || $prop === 'is_auto_update_enabled' ||
        $prop === 'unreachable_notification_sent' || $prop === 'generate_exact_labels') {
        return 'bool';
    }
    if (str_ends_with($prop, '_id') || str_ends_with($prop, '_count') ||
        in_array($prop, ['port', 'swarm_replicas', 'pull_request_id', 'pull_request_issue_comment_id',
            'health_check_interval', 'health_check_timeout', 'health_check_retries',
            'health_check_start_period', 'health_check_return_code', 'health_check_port',
            'limits_cpu_shares', 'unreachable_count', 'max_servers', 'max_applications',
            'max_databases', 'max_projects', 'max_environments', 'max_services',
            'dynamic_timeout', 'docker_cleanup_threshold', 'sentinel_push_interval_seconds',
            'auto_provision_max_servers_per_day', 'auto_update_frequency',
            'update_check_frequency', 'app_default_docker_images_to_keep',
            'app_default_rollback_validation_sec', 'app_default_rollback_max_restarts',
            'repository_project_id'])) {
        return 'int|null';
    }

    // Default to string|null (most Eloquent properties are nullable strings)
    return 'string|null';
}

// Get DB columns for a table
function getDbColumns(string $table): array
{
    try {
        $columns = DB::select("SELECT column_name, data_type, is_nullable FROM information_schema.columns WHERE table_name = ? AND table_schema = 'public'", [$table]);
        $result = [];
        foreach ($columns as $col) {
            $result[$col->column_name] = [
                'type_name' => $col->data_type,
                'nullable' => $col->is_nullable === 'YES',
            ];
        }

        return $result;
    } catch (\Exception $e) {
        return [];
    }
}

// Get table name from model class
function getTableName(string $class): string
{
    try {
        $model = new $class;

        return $model->getTable();
    } catch (\Exception $e) {
        // Fallback: snake_case + plural
        $short = basename(str_replace('\\', '/', $class));

        return \Illuminate\Support\Str::snake(\Illuminate\Support\Str::plural($short));
    }
}

// Get casts from model
function getModelCasts(string $class): array
{
    try {
        $model = new $class;

        return $model->getCasts();
    } catch (\Exception $e) {
        return [];
    }
}

// Add @property annotations to a model file
function addPropertiesToModel(string $class, array $props): bool
{
    $file = '/var/www/html/'.str_replace('\\', '/', str_replace('App\\', 'app/', $class)).'.php';

    if (! file_exists($file)) {
        // Try different path for vendor models
        echo "  [SKIP] File not found: $file\n";

        return false;
    }

    $table = getTableName($class);
    $casts = getModelCasts($class);
    $dbColumns = getDbColumns($table);

    $content = file_get_contents($file);

    // Find existing @property lines to avoid duplicates
    preg_match_all('/@property(?:-read|-write)?\s+\S+\s+\$(\w+)/', $content, $existing);
    $existingProps = array_flip($existing[1]);

    // Filter only truly missing
    $toAdd = array_filter($props, fn ($p) => ! isset($existingProps[$p]));

    if (empty($toAdd)) {
        echo "  [SKIP] All properties already declared in $class\n";

        return false;
    }

    // Generate @property lines
    $newLines = [];
    foreach ($toAdd as $prop) {
        $type = getPropertyType($class, $prop, $casts, $dbColumns);
        $newLines[] = " * @property $type \$$prop";
    }

    $newBlock = implode("\n", $newLines);

    // Find the class docblock and insert before the last */ or before the class keyword
    if (preg_match('/^(\s*\/\*\*.*?\*\/)\s*(?:#\[|class\s)/ms', $content, $m, PREG_OFFSET_CAPTURE)) {
        // There's a docblock - find the closing */ and insert before it
        $docBlock = $m[1][0];
        $offset = $m[1][1];
        $closingPos = strrpos($docBlock, ' */');

        if ($closingPos !== false) {
            $before = substr($docBlock, 0, $closingPos);
            $after = substr($docBlock, $closingPos);
            $newDocBlock = $before."\n".$newBlock."\n".$after;
            $content = substr($content, 0, $offset).$newDocBlock.substr($content, $offset + strlen($docBlock));
        }
    } else {
        // No docblock - insert one before the class declaration
        if (preg_match('/^(class\s)/m', $content, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1];
            $doc = "/**\n".$newBlock."\n */\n";
            $content = substr($content, 0, $pos).$doc.substr($content, $pos);
        }
    }

    file_put_contents($file, $content);

    return true;
}

echo "=== Fixing PHPStan property.notFound errors ===\n\n";

$fixed = 0;
$skipped = 0;

foreach ($missingByClass as $class => $props) {
    // Skip vendor classes (e.g., Laravel\Sanctum\PersonalAccessToken)
    if (! str_starts_with($class, 'App\\')) {
        echo "[$class] Skipping vendor class\n";
        $skipped++;

        continue;
    }

    echo "[$class] Adding ".count($props)." properties...\n";
    if (addPropertiesToModel($class, $props)) {
        echo "  -> Done\n";
        $fixed++;
    } else {
        $skipped++;
    }
}

echo "\n=== Done: $fixed models updated, $skipped skipped ===\n";
