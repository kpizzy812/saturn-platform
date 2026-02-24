<?php

namespace App\Models;

use App\Enums\ApplicationDeploymentStatus;
use App\Services\ConfigurationGenerator;
use App\Traits\Auditable;
use App\Traits\ClearsGlobalSearchCache;
use App\Traits\HasConfiguration;
use App\Traits\HasSafeStringAttribute;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;
use RuntimeException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Url\Url;
use Symfony\Component\Yaml\Yaml;
use Visus\Cuid2\Cuid2;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string|null $fqdn
 * @property string $ports_exposes
 * @property string|null $ports_mappings
 * @property string|null $git_repository
 * @property string|null $git_branch
 * @property string|null $custom_docker_run_options
 * @property string $status
 * @property int $pull_request_id
 * @property string|null $custom_labels
 * @property string|null $docker_compose_raw
 * @property string $build_pack
 * @property int $environment_id
 * @property string $destination_type
 * @property int $destination_id
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * @property-read ApplicationSetting|null $settings
 * @property-read StandaloneDocker|SwarmDocker|null $destination
 * @property-read Environment|null $environment
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Server> $additional_servers
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EnvironmentVariable> $environment_variables
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EnvironmentVariable> $environment_variables_preview
 * @property-read array $ports_mappings_array
 * @property-read array $ports_exposes_array
 * @property-read \Illuminate\Support\Collection $fqdns
 * @property-read string $image
 * @property-read string|null $internal_app_url
 * @property-read string $server_status
 */
#[OA\Schema(
    description: 'Application model',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', description: 'The application identifier in the database.'),
        new OA\Property(property: 'description', type: 'string', nullable: true, description: 'The application description.'),
        new OA\Property(property: 'repository_project_id', type: 'integer', nullable: true, description: 'The repository project identifier.'),
        new OA\Property(property: 'uuid', type: 'string', description: 'The application UUID.'),
        new OA\Property(property: 'name', type: 'string', description: 'The application name.'),
        new OA\Property(property: 'fqdn', type: 'string', nullable: true, description: 'The application domains.'),
        new OA\Property(property: 'config_hash', type: 'string', description: 'Configuration hash.'),
        new OA\Property(property: 'git_repository', type: 'string', description: 'Git repository URL.'),
        new OA\Property(property: 'git_branch', type: 'string', description: 'Git branch.'),
        new OA\Property(property: 'git_commit_sha', type: 'string', description: 'Git commit SHA.'),
        new OA\Property(property: 'git_full_url', type: 'string', nullable: true, description: 'Git full URL.'),
        new OA\Property(property: 'docker_registry_image_name', type: 'string', nullable: true, description: 'Docker registry image name.'),
        new OA\Property(property: 'docker_registry_image_tag', type: 'string', nullable: true, description: 'Docker registry image tag.'),
        new OA\Property(property: 'build_pack', type: 'string', description: 'Build pack.', enum: ['nixpacks', 'static', 'dockerfile', 'dockercompose']),
        new OA\Property(property: 'application_type', type: 'string', description: 'Application type. "web" for HTTP services, "worker" for background processes (no port), "both" for apps with web + worker.', enum: ['web', 'worker', 'both']),
        new OA\Property(property: 'static_image', type: 'string', description: 'Static image used when static site is deployed.'),
        new OA\Property(property: 'install_command', type: 'string', description: 'Install command.'),
        new OA\Property(property: 'build_command', type: 'string', description: 'Build command.'),
        new OA\Property(property: 'start_command', type: 'string', description: 'Start command.'),
        new OA\Property(property: 'ports_exposes', type: 'string', description: 'Ports exposes.'),
        new OA\Property(property: 'ports_mappings', type: 'string', nullable: true, description: 'Ports mappings.'),
        new OA\Property(property: 'custom_network_aliases', type: 'string', nullable: true, description: 'Network aliases for Docker container.'),
        new OA\Property(property: 'base_directory', type: 'string', description: 'Base directory for all commands.'),
        new OA\Property(property: 'publish_directory', type: 'string', description: 'Publish directory.'),
        new OA\Property(property: 'health_check_enabled', type: 'boolean', description: 'Health check enabled.'),
        new OA\Property(property: 'health_check_path', type: 'string', description: 'Health check path.'),
        new OA\Property(property: 'health_check_port', type: 'string', nullable: true, description: 'Health check port.'),
        new OA\Property(property: 'health_check_host', type: 'string', nullable: true, description: 'Health check host.'),
        new OA\Property(property: 'health_check_method', type: 'string', description: 'Health check method.'),
        new OA\Property(property: 'health_check_return_code', type: 'integer', description: 'Health check return code.'),
        new OA\Property(property: 'health_check_scheme', type: 'string', description: 'Health check scheme.'),
        new OA\Property(property: 'health_check_response_text', type: 'string', nullable: true, description: 'Health check response text.'),
        new OA\Property(property: 'health_check_interval', type: 'integer', description: 'Health check interval in seconds.'),
        new OA\Property(property: 'health_check_timeout', type: 'integer', description: 'Health check timeout in seconds.'),
        new OA\Property(property: 'health_check_retries', type: 'integer', description: 'Health check retries count.'),
        new OA\Property(property: 'health_check_start_period', type: 'integer', description: 'Health check start period in seconds.'),
        new OA\Property(property: 'limits_memory', type: 'string', description: 'Memory limit.'),
        new OA\Property(property: 'limits_memory_swap', type: 'string', description: 'Memory swap limit.'),
        new OA\Property(property: 'limits_memory_swappiness', type: 'integer', description: 'Memory swappiness.'),
        new OA\Property(property: 'limits_memory_reservation', type: 'string', description: 'Memory reservation.'),
        new OA\Property(property: 'limits_cpus', type: 'string', description: 'CPU limit.'),
        new OA\Property(property: 'limits_cpuset', type: 'string', nullable: true, description: 'CPU set.'),
        new OA\Property(property: 'limits_cpu_shares', type: 'integer', description: 'CPU shares.'),
        new OA\Property(property: 'status', type: 'string', description: 'Application status.'),
        new OA\Property(property: 'preview_url_template', type: 'string', description: 'Preview URL template.'),
        new OA\Property(property: 'destination_type', type: 'string', description: 'Destination type.'),
        new OA\Property(property: 'destination_id', type: 'integer', description: 'Destination identifier.'),
        new OA\Property(property: 'source_id', type: 'integer', nullable: true, description: 'Source identifier.'),
        new OA\Property(property: 'private_key_id', type: 'integer', nullable: true, description: 'Private key identifier.'),
        new OA\Property(property: 'environment_id', type: 'integer', description: 'Environment identifier.'),
        new OA\Property(property: 'dockerfile', type: 'string', nullable: true, description: 'Dockerfile content. Used for dockerfile build pack.'),
        new OA\Property(property: 'dockerfile_location', type: 'string', description: 'Dockerfile location.'),
        new OA\Property(property: 'custom_labels', type: 'string', nullable: true, description: 'Custom labels.'),
        new OA\Property(property: 'dockerfile_target_build', type: 'string', nullable: true, description: 'Dockerfile target build.'),
        new OA\Property(property: 'manual_webhook_secret_github', type: 'string', nullable: true, description: 'Manual webhook secret for GitHub.'),
        new OA\Property(property: 'manual_webhook_secret_gitlab', type: 'string', nullable: true, description: 'Manual webhook secret for GitLab.'),
        new OA\Property(property: 'manual_webhook_secret_bitbucket', type: 'string', nullable: true, description: 'Manual webhook secret for Bitbucket.'),
        new OA\Property(property: 'manual_webhook_secret_gitea', type: 'string', nullable: true, description: 'Manual webhook secret for Gitea.'),
        new OA\Property(property: 'docker_compose_location', type: 'string', description: 'Docker compose location.'),
        new OA\Property(property: 'docker_compose', type: 'string', nullable: true, description: 'Docker compose content. Used for docker compose build pack.'),
        new OA\Property(property: 'docker_compose_raw', type: 'string', nullable: true, description: 'Docker compose raw content.'),
        new OA\Property(property: 'docker_compose_domains', type: 'string', nullable: true, description: 'Docker compose domains.'),
        new OA\Property(property: 'docker_compose_custom_start_command', type: 'string', nullable: true, description: 'Docker compose custom start command.'),
        new OA\Property(property: 'docker_compose_custom_build_command', type: 'string', nullable: true, description: 'Docker compose custom build command.'),
        new OA\Property(property: 'swarm_replicas', type: 'integer', nullable: true, description: 'Swarm replicas. Only used for swarm deployments.'),
        new OA\Property(property: 'swarm_placement_constraints', type: 'string', nullable: true, description: 'Swarm placement constraints. Only used for swarm deployments.'),
        new OA\Property(property: 'custom_docker_run_options', type: 'string', nullable: true, description: 'Custom docker run options.'),
        new OA\Property(property: 'post_deployment_command', type: 'string', nullable: true, description: 'Post deployment command.'),
        new OA\Property(property: 'post_deployment_command_container', type: 'string', nullable: true, description: 'Post deployment command container.'),
        new OA\Property(property: 'pre_deployment_command', type: 'string', nullable: true, description: 'Pre deployment command.'),
        new OA\Property(property: 'pre_deployment_command_container', type: 'string', nullable: true, description: 'Pre deployment command container.'),
        new OA\Property(property: 'watch_paths', type: 'string', nullable: true, description: 'Watch paths.'),
        new OA\Property(property: 'custom_healthcheck_found', type: 'boolean', description: 'Custom healthcheck found.'),
        new OA\Property(property: 'redirect', type: 'string', nullable: true, description: 'How to set redirect with Traefik / Caddy. www<->non-www.', enum: ['www', 'non-www', 'both']),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', description: 'The date and time when the application was created.'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', description: 'The date and time when the application was last updated.'),
        new OA\Property(property: 'deleted_at', type: 'string', format: 'date-time', nullable: true, description: 'The date and time when the application was deleted.'),
        new OA\Property(property: 'compose_parsing_version', type: 'string', description: 'How Saturn Platform parse the compose file.'),
        new OA\Property(property: 'custom_nginx_configuration', type: 'string', nullable: true, description: 'Custom Nginx configuration base64 encoded.'),
        new OA\Property(property: 'is_http_basic_auth_enabled', type: 'boolean', description: 'HTTP Basic Authentication enabled.'),
        new OA\Property(property: 'http_basic_auth_username', type: 'string', nullable: true, description: 'Username for HTTP Basic Authentication'),
        new OA\Property(property: 'http_basic_auth_password', type: 'string', nullable: true, description: 'Password for HTTP Basic Authentication'),
    ]
)]

class Application extends BaseModel
{
    use Auditable, ClearsGlobalSearchCache, HasConfiguration, HasFactory, HasSafeStringAttribute, LogsActivity, SoftDeletes;

    private static $parserVersion = '5';

    protected $with = ['environment'];

    /**
     * SECURITY: Using $fillable instead of $guarded = [] to prevent mass assignment vulnerabilities.
     * Critical fields (id, uuid) are excluded. Relationship IDs are fillable but validated in Actions.
     */
    protected $fillable = [
        'name',
        'environment_id',
        'destination_id',
        'destination_type',
        'source_id',
        'source_type',
        'description',
        'repository_project_id',
        'fqdn',
        'config_hash',
        'git_repository',
        'git_branch',
        'git_commit_sha',
        'docker_registry_image_name',
        'docker_registry_image_tag',
        'build_pack',
        'application_type',
        'static_image',
        'install_command',
        'build_command',
        'start_command',
        'ports_exposes',
        'ports_mappings',
        'custom_network_aliases',
        'base_directory',
        'publish_directory',
        'health_check_enabled',
        'health_check_path',
        'health_check_port',
        'health_check_host',
        'health_check_method',
        'health_check_return_code',
        'health_check_scheme',
        'health_check_response_text',
        'health_check_interval',
        'health_check_timeout',
        'health_check_retries',
        'health_check_start_period',
        'limits_memory',
        'limits_memory_swap',
        'limits_memory_swappiness',
        'limits_memory_reservation',
        'limits_cpus',
        'limits_cpuset',
        'limits_cpu_shares',
        'preview_url_template',
        'dockerfile',
        'dockerfile_location',
        'dockerfile_target_build',
        'custom_labels',
        'manual_webhook_secret_github',
        'manual_webhook_secret_gitlab',
        'manual_webhook_secret_bitbucket',
        'manual_webhook_secret_gitea',
        'docker_compose_location',
        'docker_compose',
        'docker_compose_raw',
        'docker_compose_domains',
        'docker_compose_custom_start_command',
        'docker_compose_custom_build_command',
        'swarm_replicas',
        'swarm_placement_constraints',
        'custom_docker_run_options',
        'post_deployment_command',
        'post_deployment_command_container',
        'pre_deployment_command',
        'pre_deployment_command_container',
        'watch_paths',
        'custom_healthcheck_found',
        'redirect',
        'compose_parsing_version',
        'custom_nginx_configuration',
        'is_http_basic_auth_enabled',
        'http_basic_auth_username',
        'http_basic_auth_password',
        'build_pack_explicitly_set',
        'monorepo_group_id',
        'auto_inject_database_url',
        'private_key_id',
        'status',
        'last_online_at',
        'restart_count',
        'last_restart_at',
        'last_restart_type',
        'last_successful_deployment_id',
    ];

    protected $appends = ['server_status'];

    // Security: Hide webhook secrets from serialization (Inertia, API responses)
    protected $hidden = [
        'manual_webhook_secret_github',
        'manual_webhook_secret_gitlab',
        'manual_webhook_secret_bitbucket',
        'manual_webhook_secret_gitea',
    ];

    protected $casts = [
        'http_basic_auth_password' => 'encrypted',
        // Security: Encrypt webhook secrets at rest
        'manual_webhook_secret_github' => 'encrypted',
        'manual_webhook_secret_gitlab' => 'encrypted',
        'manual_webhook_secret_bitbucket' => 'encrypted',
        'manual_webhook_secret_gitea' => 'encrypted',
        'restart_count' => 'integer',
        'last_restart_at' => 'datetime',
        'build_pack_explicitly_set' => 'boolean',
        'monorepo_group_id' => 'string',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'fqdn', 'git_repository', 'git_branch', 'build_pack', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected static function booted()
    {
        static::addGlobalScope('withRelations', function ($builder) {
            $builder->withCount([
                'additional_servers',
                'additional_networks',
            ]);
        });
        // New applications start with auto-detection enabled (build_pack_explicitly_set = false)
        // so Dockerfile auto-detect can switch from Nixpacks if a Dockerfile is found.
        static::creating(function ($application) {
            if (! isset($application->build_pack_explicitly_set)) {
                $application->build_pack_explicitly_set = false;
            }
        });
        static::saving(function ($application) {
            $payload = [];
            if ($application->isDirty('fqdn')) {
                if ($application->fqdn === '') {
                    $application->fqdn = null;
                }
                $payload['fqdn'] = $application->fqdn;
            }
            if ($application->isDirty('install_command')) {
                $payload['install_command'] = str($application->install_command)->trim();
            }
            if ($application->isDirty('build_command')) {
                $payload['build_command'] = str($application->build_command)->trim();
            }
            if ($application->isDirty('start_command')) {
                $payload['start_command'] = str($application->start_command)->trim();
            }
            if ($application->isDirty('base_directory')) {
                $payload['base_directory'] = str($application->base_directory)->trim();
            }
            if ($application->isDirty('publish_directory')) {
                $payload['publish_directory'] = str($application->publish_directory)->trim();
            }
            if ($application->isDirty('git_repository')) {
                $payload['git_repository'] = str($application->git_repository)->trim();
            }
            if ($application->isDirty('git_branch')) {
                $payload['git_branch'] = str($application->git_branch)->trim();
            }
            if ($application->isDirty('git_commit_sha')) {
                $payload['git_commit_sha'] = str($application->git_commit_sha)->trim();
            }
            if ($application->isDirty('status')) {
                $payload['last_online_at'] = now();
            }
            if ($application->isDirty('custom_nginx_configuration')) {
                if ($application->custom_nginx_configuration === '') {
                    $payload['custom_nginx_configuration'] = null;
                }
            }
            if (count($payload) > 0) {
                $application->forceFill($payload);
            }

            // Buildpack switching cleanup logic
            if ($application->isDirty('build_pack')) {
                $originalBuildPack = $application->getOriginal('build_pack');

                // Clear Docker Compose specific data when switching away from dockercompose
                if ($originalBuildPack === 'dockercompose') {
                    $application->docker_compose_domains = null;
                    $application->docker_compose_raw = null;

                    // Remove SERVICE_FQDN_* and SERVICE_URL_* environment variables
                    $application->environment_variables()
                        ->where(function ($q) {
                            $q->where('key', 'LIKE', 'SERVICE_FQDN_%')
                                ->orWhere('key', 'LIKE', 'SERVICE_URL_%');
                        })
                        ->delete();
                    $application->environment_variables_preview()
                        ->where(function ($q) {
                            $q->where('key', 'LIKE', 'SERVICE_FQDN_%')
                                ->orWhere('key', 'LIKE', 'SERVICE_URL_%');
                        })
                        ->delete();
                }

                // Clear Dockerfile specific data when switching away from dockerfile
                if ($originalBuildPack === 'dockerfile') {
                    $application->dockerfile = null;
                    $application->dockerfile_location = null;
                    $application->dockerfile_target_build = null;
                    $application->custom_healthcheck_found = false;
                }
            }
        });
        static::created(function ($application) {
            $defaults = InstanceSettings::get()->getApplicationDefaults();
            ApplicationSetting::create(array_merge(
                ['application_id' => $application->id],
                $defaults
            ));
            $application->compose_parsing_version = self::$parserVersion;
            $application->save();

            // NOTE: We no longer set a default NIXPACKS_NODE_VERSION here.
            // Saturn auto-detects Node.js version from .nvmrc or package.json engines field.
            // If not detected, Nixpacks will use its default (Node 18).
            // Users can manually set NIXPACKS_NODE_VERSION if needed.
        });
        // Re-sync master proxy route when FQDN changes
        static::updated(function ($application) {
            if ($application->wasChanged('fqdn')) {
                try {
                    $appServer = $application->destination?->server;
                    if ($appServer && ! $appServer->isMasterServer()) {
                        app(\App\Services\MasterProxyConfigService::class)->syncRemoteRoute($application);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed to sync proxy route on FQDN update', ['application_id' => $application->id, 'error' => $e->getMessage()]);
                }

                try {
                    if (instanceSettings()->isCloudflareProtectionActive()) {
                        \App\Jobs\SyncCloudflareRoutesJob::dispatch();
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed to dispatch Cloudflare sync on FQDN update', ['application_id' => $application->id, 'error' => $e->getMessage()]);
                }
            }
        });
        static::forceDeleting(function ($application) {
            // Remove master proxy route before clearing FQDN
            try {
                app(\App\Services\MasterProxyConfigService::class)->removeRemoteRoute($application);
            } catch (\Throwable $e) {
                Log::warning('Failed to remove proxy route on application deletion', ['application_id' => $application->id, 'error' => $e->getMessage()]);
            }

            // Sync Cloudflare routes after FQDN removal
            try {
                if (instanceSettings()->isCloudflareProtectionActive()) {
                    \App\Jobs\SyncCloudflareRoutesJob::dispatch();
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to dispatch Cloudflare sync on application deletion', ['application_id' => $application->id, 'error' => $e->getMessage()]);
            }

            $application->update(['fqdn' => null]);
            $application->settings()->delete();
            $application->persistentStorages()->delete();
            $application->environment_variables()->delete();
            $application->environment_variables_preview()->delete();
            foreach ($application->scheduled_tasks as $task) {
                $task->delete();
            }
            $application->tags()->detach();
            $application->previews()->delete();
            foreach ($application->deployment_queue as $deployment) {
                $deployment->delete();
            }

            // Clean up deployment notifications referencing this application
            UserNotification::where('metadata->application_uuid', $application->uuid)->delete();
        });
    }

    public function customNetworkAliases(): Attribute
    {
        return Attribute::make(
            set: function ($value) {
                if (is_null($value) || $value === '') {
                    return null;
                }

                // If it's already a JSON string, decode it
                if (is_string($value) && $this->isJson($value)) {
                    $value = json_decode($value, true);
                }

                // If it's a string but not JSON, treat it as a comma-separated list
                if (! is_array($value)) {
                    $value = explode(',', $value);
                }

                $value = collect($value)
                    ->map(function ($alias) {
                        if (is_string($alias)) {
                            return str_replace(' ', '-', trim($alias));
                        }

                        return null;
                    })
                    ->filter()
                    ->unique() // Remove duplicate values
                    ->values()
                    ->toArray();

                return empty($value) ? null : json_encode($value);
            },
            get: function ($value) {
                if (is_null($value)) {
                    return null;
                }

                if (is_string($value) && $this->isJson($value)) {
                    $decoded = json_decode($value, true);

                    // Return as comma-separated string, not array
                    return is_array($decoded) ? implode(',', $decoded) : $value;
                }

                return $value;
            }
        );
    }

    /**
     * Get custom_network_aliases as an array
     */
    public function customNetworkAliasesArray(): Attribute
    {
        return Attribute::make(
            get: function () {
                $value = $this->getRawOriginal('custom_network_aliases');
                if (is_null($value)) {
                    return null;
                }

                if (is_string($value) && $this->isJson($value)) {
                    return json_decode($value, true);
                }

                return is_array($value) ? $value : [];
            }
        );
    }

    /**
     * Check if a string is a valid JSON
     */
    private function isJson($string)
    {
        if (! is_string($string)) {
            return false;
        }
        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }

    public static function ownedByCurrentTeamAPI(int $teamId)
    {
        return Application::whereRelation('environment.project.team', 'id', $teamId)->orderBy('name');
    }

    /**
     * Get query builder for applications owned by current team.
     * If you need all applications without further query chaining, use ownedByCurrentTeamCached() instead.
     */
    public static function ownedByCurrentTeam()
    {
        return Application::whereRelation('environment.project.team', 'id', currentTeam()->id)->orderBy('name');
    }

    /**
     * Get all applications owned by current team (cached for request duration).
     */
    public static function ownedByCurrentTeamCached()
    {
        return once(function () {
            return Application::ownedByCurrentTeam()->get();
        });
    }

    public function getContainersToStop(Server $server, bool $previewDeployments = false): array
    {
        $containers = $previewDeployments
            ? getCurrentApplicationContainerStatus($server, $this->id, includePullrequests: true)
            : getCurrentApplicationContainerStatus($server, $this->id, 0);

        return $containers->pluck('Names')->toArray();
    }

    public function deleteConfigurations()
    {
        $server = data_get($this, 'destination.server');
        $workdir = $this->workdir();
        if (str($workdir)->endsWith($this->uuid)) {
            if (! preg_match('/^[a-zA-Z0-9\-_\/\.]+$/', $workdir)) {
                throw new \RuntimeException('Invalid workdir path: '.$workdir);
            }
            instant_remote_process(['rm -rf '.escapeshellarg($workdir)], $server, false);
        }
    }

    public function deleteVolumes()
    {
        $persistentStorages = $this->persistentStorages()->get();
        if ($this->build_pack === 'dockercompose') {
            $server = data_get($this, 'destination.server');
            instant_remote_process(["cd {$this->dirOnServer()} && docker compose down -v"], $server, false);
        } else {
            if ($persistentStorages->count() === 0) {
                return;
            }
            $server = data_get($this, 'destination.server');
            foreach ($persistentStorages as $storage) {
                instant_remote_process(['docker volume rm -f '.escapeshellarg($storage->name)], $server, false);
            }
        }
    }

    public function deleteConnectedNetworks()
    {
        $server = data_get($this, 'destination.server');
        instant_remote_process(['docker network disconnect '.escapeshellarg($this->uuid).' saturn-proxy'], $server, false);
        instant_remote_process(['docker network rm '.escapeshellarg($this->uuid)], $server, false);
    }

    /** @return BelongsToMany<Server, $this> */
    public function additional_servers(): BelongsToMany
    {
        return $this->belongsToMany(Server::class, 'additional_destinations')
            ->withPivot('standalone_docker_id', 'status');
    }

    /** @return BelongsToMany<StandaloneDocker, $this> */
    public function additional_networks(): BelongsToMany
    {
        return $this->belongsToMany(StandaloneDocker::class, 'additional_destinations')
            ->withPivot('server_id', 'status');
    }

    public function is_public_repository(): bool
    {
        if (data_get($this, 'source.is_public')) {
            return true;
        }

        return false;
    }

    public function is_github_based(): bool
    {
        if (data_get($this, 'source')) {
            return true;
        }

        return false;
    }

    public function isForceHttpsEnabled()
    {
        return data_get($this, 'settings.is_force_https_enabled', false);
    }

    public function isStripprefixEnabled()
    {
        return data_get($this, 'settings.is_stripprefix_enabled', true);
    }

    public function isGzipEnabled()
    {
        return data_get($this, 'settings.is_gzip_enabled', true);
    }

    public function link()
    {
        if ($this->uuid) {
            return route('applications.show', $this->uuid);
        }

        return null;
    }

    public function taskLink($task_uuid)
    {
        if (data_get($this, 'environment.project.uuid')) {
            $route = route('project.application.scheduled-tasks', [
                'project_uuid' => data_get($this, 'environment.project.uuid'),
                'environment_uuid' => data_get($this, 'environment.uuid'),
                'application_uuid' => data_get($this, 'uuid'),
                'task_uuid' => $task_uuid,
            ]);
            $settings = instanceSettings();
            if (data_get($settings, 'fqdn')) {
                $url = Url::fromString($route);
                $url = $url->withPort(null);
                $fqdn = data_get($settings, 'fqdn');
                $fqdn = str_replace(['http://', 'https://'], '', $fqdn);
                $url = $url->withHost($fqdn);

                return $url->__toString();
            }

            return $route;
        }

        return null;
    }

    /** @return HasOne<ApplicationSetting, $this> */
    public function settings(): HasOne
    {
        return $this->hasOne(ApplicationSetting::class);
    }

    /** @return BelongsTo<ApplicationDeploymentQueue, $this> */
    public function lastSuccessfulDeployment(): BelongsTo
    {
        return $this->belongsTo(ApplicationDeploymentQueue::class, 'last_successful_deployment_id');
    }

    public function rollbackEvents(): HasMany
    {
        return $this->hasMany(ApplicationRollbackEvent::class);
    }

    /** @return MorphMany<LocalPersistentVolume, $this> */
    public function persistentStorages(): MorphMany
    {
        return $this->morphMany(LocalPersistentVolume::class, 'resource');
    }

    /** @return MorphMany<LocalFileVolume, $this> */
    public function fileStorages(): MorphMany
    {
        return $this->morphMany(LocalFileVolume::class, 'resource');
    }

    public function type()
    {
        return 'application';
    }

    public function publishDirectory(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value ? '/'.ltrim($value, '/') : null,
        );
    }

    public function gitBranchLocation(): Attribute
    {
        return Attribute::make(
            get: function () {
                $base_dir = $this->base_directory ?? '/';
                if (! is_null($this->source?->getAttribute('html_url')) && $this->git_repository !== '' && $this->git_branch !== '') {
                    if (str($this->git_repository)->contains('bitbucket')) {
                        return "{$this->source->getAttribute('html_url')}/{$this->git_repository}/src/{$this->git_branch}{$base_dir}";
                    }

                    return "{$this->source->getAttribute('html_url')}/{$this->git_repository}/tree/{$this->git_branch}{$base_dir}";
                }
                // Convert the SSH URL to HTTPS URL
                if (strpos($this->git_repository, 'git@') === 0) {
                    $git_repository = str_replace(['git@', ':', '.git'], ['', '/', ''], $this->git_repository);

                    if (str($this->git_repository)->contains('bitbucket')) {
                        return "https://{$git_repository}/src/{$this->git_branch}{$base_dir}";
                    }

                    return "https://{$git_repository}/tree/{$this->git_branch}{$base_dir}";
                }

                return $this->git_repository;
            }
        );
    }

    public function gitWebhook(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! is_null($this->source?->getAttribute('html_url')) && $this->git_repository !== '' && $this->git_branch !== '') {
                    return "{$this->source->getAttribute('html_url')}/{$this->git_repository}/settings/hooks";
                }
                // Convert the SSH URL to HTTPS URL
                if (strpos($this->git_repository, 'git@') === 0) {
                    $git_repository = str_replace(['git@', ':', '.git'], ['', '/', ''], $this->git_repository);

                    return "https://{$git_repository}/settings/hooks";
                }

                return $this->git_repository;
            }
        );
    }

    public function gitCommits(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! is_null($this->source?->getAttribute('html_url')) && $this->git_repository !== '' && $this->git_branch !== '') {
                    return "{$this->source->getAttribute('html_url')}/{$this->git_repository}/commits/{$this->git_branch}";
                }
                // Convert the SSH URL to HTTPS URL
                if (strpos($this->git_repository, 'git@') === 0) {
                    $git_repository = str_replace(['git@', ':', '.git'], ['', '/', ''], $this->git_repository);

                    return "https://{$git_repository}/commits/{$this->git_branch}";
                }

                return $this->git_repository;
            }
        );
    }

    public function gitCommitLink($link): string
    {
        if (! is_null(data_get($this, 'source.html_url')) && ! is_null(data_get($this, 'git_repository')) && ! is_null(data_get($this, 'git_branch'))) {
            $htmlUrl = data_get($this->source, 'html_url');
            if (str($htmlUrl)->contains('bitbucket')) {
                return "{$htmlUrl}/{$this->git_repository}/commits/{$link}";
            }

            return "{$htmlUrl}/{$this->git_repository}/commit/{$link}";
        }
        if (str($this->git_repository)->contains('bitbucket')) {
            $git_repository = str_replace('.git', '', $this->git_repository);
            $url = Url::fromString($git_repository);
            $url = $url->withUserInfo('');
            $url = $url->withPath($url->getPath().'/commits/'.$link);

            return $url->__toString();
        }
        if (strpos($this->git_repository, 'git@') === 0) {
            $git_repository = str_replace(['git@', ':', '.git'], ['', '/', ''], $this->git_repository);
            if (data_get($this, 'source.html_url')) {
                return "{$this->source->getAttribute('html_url')}/{$git_repository}/commit/{$link}";
            }

            return "{$git_repository}/commit/{$link}";
        }

        return $this->git_repository;
    }

    public function dockerfileLocation(): Attribute
    {
        return Attribute::make(
            set: function ($value) {
                if (is_null($value) || $value === '') {
                    return '/Dockerfile';
                } else {
                    if ($value !== '/') {
                        return Str::start(Str::replaceEnd('/', '', $value), '/');
                    }

                    return Str::start($value, '/');
                }
            }
        );
    }

    public function dockerComposeLocation(): Attribute
    {
        return Attribute::make(
            set: function ($value) {
                if (is_null($value) || $value === '') {
                    return '/docker-compose.yaml';
                } else {
                    if ($value !== '/') {
                        return Str::start(Str::replaceEnd('/', '', $value), '/');
                    }

                    return Str::start($value, '/');
                }
            }
        );
    }

    public function baseDirectory(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => '/'.ltrim($value, '/'),
        );
    }

    public function portsMappings(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value === '' ? null : $value,
        );
    }

    public function portsMappingsArray(): Attribute
    {
        return Attribute::make(
            get: fn () => is_null($this->ports_mappings)
                ? []
                : explode(',', $this->ports_mappings),

        );
    }

    public function isRunning()
    {
        return (bool) str($this->status)->startsWith('running');
    }

    public function isExited()
    {
        return (bool) str($this->status)->startsWith('exited');
    }

    public function realStatus()
    {
        return $this->getRawOriginal('status');
    }

    protected function serverStatus(): Attribute
    {
        return Attribute::make(
            get: function () {
                // Check main server infrastructure health
                $main_server_functional = $this->destination?->server?->isFunctional() ?? false;

                if (! $main_server_functional) {
                    return false;
                }

                // Check additional servers infrastructure health (not container status!)
                if ($this->relationLoaded('additional_servers') && $this->additional_servers->count() > 0) {
                    foreach ($this->additional_servers as $server) {
                        if (! $server->isFunctional()) {
                            return false;  // Real server infrastructure problem
                        }
                    }
                }

                return true;
            }
        );
    }

    public function status(): Attribute
    {
        return Attribute::make(
            set: function ($value) {
                if ($this->additional_servers->count() === 0) {
                    if (str($value)->contains('(')) {
                        $status = str($value)->before('(')->trim()->value();
                        $health = str($value)->after('(')->before(')')->trim()->value();
                    } elseif (str($value)->contains(':')) {
                        $status = str($value)->before(':')->trim()->value();
                        $health = str($value)->after(':')->trim()->value();
                    } else {
                        $status = $value;
                        $health = 'unhealthy';
                    }

                    return "$status:$health";
                } else {
                    if (str($value)->contains('(')) {
                        $status = str($value)->before('(')->trim()->value();
                        $health = str($value)->after('(')->before(')')->trim()->value();
                    } elseif (str($value)->contains(':')) {
                        $status = str($value)->before(':')->trim()->value();
                        $health = str($value)->after(':')->trim()->value();
                    } else {
                        $status = $value;
                        $health = 'unhealthy';
                    }

                    return "$status:$health";
                }
            },
            get: function ($value) {
                if ($this->additional_servers->count() === 0) {
                    // running (healthy)
                    if (str($value)->contains('(')) {
                        $status = str($value)->before('(')->trim()->value();
                        $health = str($value)->after('(')->before(')')->trim()->value();
                    } elseif (str($value)->contains(':')) {
                        $status = str($value)->before(':')->trim()->value();
                        $health = str($value)->after(':')->trim()->value();
                    } else {
                        $status = $value;
                        $health = 'unhealthy';
                    }

                    return "$status:$health";
                } else {
                    $complex_status = null;
                    $complex_health = null;
                    $complex_status = $main_server_status = str($value)->before(':')->value();
                    $complex_health = $main_server_health = str($value)->after(':')->value();
                    $additional_servers_status = $this->additional_servers->pluck('pivot.status');
                    foreach ($additional_servers_status as $status) {
                        $server_status = str($status)->before(':')->value();
                        $server_health = str($status)->after(':')->value();
                        if ($main_server_status !== $server_status) {
                            $complex_status = 'degraded';
                        }
                        if ($main_server_health !== $server_health) {
                            $complex_health = 'unhealthy';
                        }
                    }

                    return "$complex_status:$complex_health";
                }
            },
        );
    }

    public function customNginxConfiguration(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => base64_encode($value),
            get: fn ($value) => base64_decode($value),
        );
    }

    public function portsExposesArray(): Attribute
    {
        return Attribute::make(
            get: fn () => empty($this->ports_exposes)
                ? []
                : explode(',', $this->ports_exposes)
        );
    }

    /**
     * Internal Docker network URL for app-to-app connections.
     * Uses container UUID as hostname (Docker DNS) and first exposed port.
     */
    protected function internalAppUrl(): Attribute
    {
        return new Attribute(
            get: function () {
                $port = $this->ports_exposes_array[0] ?? '80';

                return "http://{$this->uuid}:{$port}";
            }
        );
    }

    /** @return MorphToMany<Tag, $this> */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function project()
    {
        return data_get($this, 'environment.project');
    }

    public function team()
    {
        return data_get($this, 'environment.project.team');
    }

    public function serviceType()
    {
        $found = str(collect(SPECIFIC_SERVICES)->filter(function ($service) {
            return str($this->image)->before(':')->value() === $service;
        })->first());
        if ($found->isNotEmpty()) {
            return $found;
        }

        return null;
    }

    public function main_port()
    {
        if ($this->isWorker()) {
            return [];
        }

        return $this->settings->is_static ? [80] : $this->ports_exposes_array;
    }

    public function detectPortFromEnvironment(?bool $isPreview = false): ?int
    {
        $envVars = $isPreview
            ? $this->environment_variables_preview
            : $this->environment_variables;

        $portVar = $envVars->firstWhere('key', 'PORT');

        if ($portVar && $portVar->real_value) {
            $portValue = trim($portVar->real_value);
            if (is_numeric($portValue)) {
                return (int) $portValue;
            }
        }

        return null;
    }

    /** @return MorphMany<EnvironmentVariable, $this> */
    public function environment_variables(): MorphMany
    {
        return $this->morphMany(EnvironmentVariable::class, 'resourceable')
            ->where('is_preview', false)
            ->orderByRaw("
                CASE
                    WHEN is_required = true THEN 1
                    WHEN LOWER(key) LIKE 'service_%' THEN 2
                    ELSE 3
                END,
                LOWER(key) ASC
            ");
    }

    /** @return MorphMany<EnvironmentVariable, $this> */
    public function runtime_environment_variables(): MorphMany
    {
        return $this->morphMany(EnvironmentVariable::class, 'resourceable')
            ->where('is_preview', false)
            ->where('key', 'not like', 'NIXPACKS_%');
    }

    /** @return MorphMany<EnvironmentVariable, $this> */
    public function nixpacks_environment_variables(): MorphMany
    {
        return $this->morphMany(EnvironmentVariable::class, 'resourceable')
            ->where('is_preview', false)
            ->where('key', 'like', 'NIXPACKS_%');
    }

    /** @return MorphMany<EnvironmentVariable, $this> */
    public function environment_variables_preview(): MorphMany
    {
        return $this->morphMany(EnvironmentVariable::class, 'resourceable')
            ->where('is_preview', true)
            ->orderByRaw("
                CASE
                    WHEN is_required = true THEN 1
                    WHEN LOWER(key) LIKE 'service_%' THEN 2
                    ELSE 3
                END,
                LOWER(key) ASC
            ");
    }

    /** @return MorphMany<EnvironmentVariable, $this> */
    public function runtime_environment_variables_preview(): MorphMany
    {
        return $this->morphMany(EnvironmentVariable::class, 'resourceable')
            ->where('is_preview', true)
            ->where('key', 'not like', 'NIXPACKS_%');
    }

    /** @return MorphMany<EnvironmentVariable, $this> */
    public function nixpacks_environment_variables_preview(): MorphMany
    {
        return $this->morphMany(EnvironmentVariable::class, 'resourceable')
            ->where('is_preview', true)
            ->where('key', 'like', 'NIXPACKS_%');
    }

    public function scheduled_tasks(): HasMany
    {
        return $this->hasMany(ScheduledTask::class)->orderBy('name', 'asc');
    }

    /** @return BelongsTo<PrivateKey, $this> */
    public function private_key(): BelongsTo
    {
        return $this->belongsTo(PrivateKey::class);
    }

    /** @return BelongsTo<Environment, $this> */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    /** @return HasMany<ApplicationPreview, $this> */
    public function previews(): HasMany
    {
        return $this->hasMany(ApplicationPreview::class)->orderBy('pull_request_id', 'desc');
    }

    /** @return HasMany<ApplicationDeploymentQueue, $this> */
    public function deployment_queue(): HasMany
    {
        return $this->hasMany(ApplicationDeploymentQueue::class);
    }

    public function destination(): MorphTo
    {
        return $this->morphTo();
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function isDeploymentInprogress()
    {
        $deployments = ApplicationDeploymentQueue::where('application_id', $this->id)->whereIn('status', [ApplicationDeploymentStatus::IN_PROGRESS, ApplicationDeploymentStatus::QUEUED])->count();
        if ($deployments > 0) {
            return true;
        }

        return false;
    }

    public function get_last_successful_deployment()
    {
        return ApplicationDeploymentQueue::where('application_id', $this->id)->where('status', ApplicationDeploymentStatus::FINISHED)->where('pull_request_id', 0)->orderBy('created_at', 'desc')->first();
    }

    public function get_last_days_deployments()
    {
        return ApplicationDeploymentQueue::where('application_id', $this->id)->where('created_at', '>=', now()->subDays(7))->orderBy('created_at', 'desc')->get();
    }

    public function deployments(int $skip = 0, int $take = 10, ?string $pullRequestId = null)
    {
        $deployments = ApplicationDeploymentQueue::where('application_id', $this->id)->orderBy('created_at', 'desc');

        if ($pullRequestId) {
            $deployments = $deployments->where('pull_request_id', $pullRequestId);
        }

        $count = $deployments->count();
        $deployments = $deployments->skip($skip)->take($take)->get();

        return [
            'count' => $count,
            'deployments' => $deployments,
        ];
    }

    public function get_deployment(string $deployment_uuid)
    {
        return Activity::where('subject_id', $this->id)->where('properties->type_uuid', '=', $deployment_uuid)->first();
    }

    public function isDeployable(): bool
    {
        return (bool) $this->settings?->is_auto_deploy_enabled;
    }

    public function isPRDeployable(): bool
    {
        return (bool) $this->settings?->is_preview_deployments_enabled;
    }

    public function deploymentType()
    {
        if (isDev() && data_get($this, 'private_key_id') === 0) {
            return 'deploy_key';
        }
        if (data_get($this, 'private_key_id')) {
            return 'deploy_key';
        } elseif (data_get($this, 'source')) {
            return 'source';
        } else {
            return 'other';
        }
    }

    public function could_set_build_commands(): bool
    {
        if ($this->build_pack === 'nixpacks') {
            return true;
        }

        return false;
    }

    public function git_based(): bool
    {
        if ($this->dockerfile) {
            return false;
        }
        if ($this->build_pack === 'dockerimage') {
            return false;
        }

        return true;
    }

    public function isHealthcheckDisabled(): bool
    {
        if (data_get($this, 'health_check_enabled') === false) {
            return true;
        }

        // Workers have no HTTP port, so healthcheck is always disabled
        if ($this->isWorker()) {
            return true;
        }

        return false;
    }

    /**
     * Check if this application is a worker process (no HTTP port).
     */
    public function isWorker(): bool
    {
        return $this->application_type === 'worker';
    }

    /**
     * Check if this application needs proxy configuration (domain + ports).
     */
    public function needsProxy(): bool
    {
        return ! $this->isWorker() && ! empty($this->fqdn);
    }

    public function workdir()
    {
        return application_configuration_dir()."/{$this->uuid}";
    }

    public function isLogDrainEnabled()
    {
        return data_get($this, 'settings.is_log_drain_enabled', false);
    }

    public function isConfigurationChanged(bool $save = false)
    {
        $newConfigHash = base64_encode($this->fqdn.$this->git_repository.$this->git_branch.$this->git_commit_sha.$this->build_pack.$this->static_image.$this->install_command.$this->build_command.$this->start_command.$this->ports_exposes.$this->ports_mappings.$this->custom_network_aliases.$this->base_directory.$this->publish_directory.$this->dockerfile.$this->dockerfile_location.$this->custom_labels.$this->custom_docker_run_options.$this->dockerfile_target_build.$this->redirect.$this->custom_nginx_configuration.$this->settings->use_build_secrets.$this->settings->inject_build_args_to_dockerfile.$this->settings->include_source_commit_in_build);
        if ($this->pull_request_id === 0 || $this->pull_request_id === null) {
            $newConfigHash .= json_encode($this->environment_variables()->get(['value',  'is_multiline', 'is_literal', 'is_buildtime', 'is_runtime'])->sort());
        } else {
            $newConfigHash .= json_encode($this->environment_variables_preview()->get(['value',  'is_multiline', 'is_literal', 'is_buildtime', 'is_runtime'])->sort());
        }
        $newConfigHash = md5($newConfigHash);
        $oldConfigHash = data_get($this, 'config_hash');
        if ($oldConfigHash === null) {
            if ($save) {
                $this->config_hash = $newConfigHash;
                $this->save();
            }

            return true;
        }
        if ($oldConfigHash === $newConfigHash) {
            return false;
        } else {
            if ($save) {
                $this->config_hash = $newConfigHash;
                $this->save();
            }

            return true;
        }
    }

    public function customRepository()
    {
        $source = $this->source instanceof \App\Models\GithubApp ? $this->source : null;

        return convertGitUrl($this->git_repository, $this->deploymentType(), $source);
    }

    public function generateBaseDir(string $uuid)
    {
        return "/artifacts/{$uuid}";
    }

    public function dirOnServer()
    {
        return application_configuration_dir()."/{$this->uuid}";
    }

    public function setGitImportSettings(string $deployment_uuid, string $git_clone_command, bool $public = false)
    {
        $baseDir = $this->generateBaseDir($deployment_uuid);
        $escapedBaseDir = escapeshellarg($baseDir);
        $isShallowCloneEnabled = $this->settings->is_git_shallow_clone_enabled ?? false;

        if ($this->git_commit_sha !== 'HEAD') {
            // Escape commit SHA for shell safety (defense in depth - API also validates format)
            $escapedCommitSha = escapeshellarg($this->git_commit_sha);
            // If shallow clone is enabled and we need a specific commit,
            // we need to fetch that specific commit with depth=1
            if ($isShallowCloneEnabled) {
                $git_clone_command = "{$git_clone_command} && cd {$escapedBaseDir} && GIT_SSH_COMMAND=\"ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null\" git fetch --depth=1 origin {$escapedCommitSha} && git -c advice.detachedHead=false checkout {$escapedCommitSha} >/dev/null 2>&1";
            } else {
                $git_clone_command = "{$git_clone_command} && cd {$escapedBaseDir} && GIT_SSH_COMMAND=\"ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null\" git -c advice.detachedHead=false checkout {$escapedCommitSha} >/dev/null 2>&1";
            }
        }
        if ($this->settings->is_git_submodules_enabled) {
            // Check if .gitmodules file exists before running submodule commands
            $git_clone_command = "{$git_clone_command} && cd {$escapedBaseDir} && if [ -f .gitmodules ]; then";
            if ($public) {
                $git_clone_command = "{$git_clone_command} sed -i \"s#git@\(.*\):#https://\\1/#g\" {$escapedBaseDir}/.gitmodules || true &&";
            }
            // Add shallow submodules flag if shallow clone is enabled
            $submoduleFlags = $isShallowCloneEnabled ? '--depth=1' : '';
            $git_clone_command = "{$git_clone_command} git submodule sync && GIT_SSH_COMMAND=\"ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null\" git submodule update --init --recursive {$submoduleFlags}; fi";
        }
        if ($this->settings->is_git_lfs_enabled) {
            $git_clone_command = "{$git_clone_command} && cd {$escapedBaseDir} && GIT_SSH_COMMAND=\"ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null\" git lfs pull";
        }

        return $git_clone_command;
    }

    public function getGitRemoteStatus(string $deployment_uuid)
    {
        try {
            ['commands' => $lsRemoteCommand] = $this->generateGitLsRemoteCommands(deployment_uuid: $deployment_uuid, exec_in_docker: false);
            instant_remote_process([$lsRemoteCommand], $this->destination->server, true);

            return [
                'is_accessible' => true,
                'error' => null,
            ];
        } catch (\RuntimeException $ex) {
            return [
                'is_accessible' => false,
                'error' => $ex->getMessage(),
            ];
        }
    }

    public function generateGitLsRemoteCommands(string $deployment_uuid, bool $exec_in_docker = true)
    {
        $branch = $this->git_branch;
        ['git_repository' => $customRepository, 'git_port' => $customPort] = $this->customRepository();
        $commands = collect([]);
        $base_command = 'git ls-remote';

        if ($this->deploymentType() === 'source') {
            $source_html_url = data_get($this, 'source.html_url');
            $url = parse_url(filter_var($source_html_url, FILTER_SANITIZE_URL));
            $source_html_url_host = $url['host'];
            $source_html_url_scheme = $url['scheme'];

            if ($this->source instanceof \App\Models\GithubApp) {
                $escapedCustomRepository = escapeshellarg($customRepository);
                if ($this->source->getAttribute('is_public')) {
                    $escapedRepoUrl = escapeshellarg("{$this->source->getAttribute('html_url')}/{$customRepository}");
                    $fullRepoUrl = "{$this->source->getAttribute('html_url')}/{$customRepository}";
                    $base_command = "{$base_command} {$escapedRepoUrl}";
                } else {
                    $github_access_token = generateGithubInstallationToken($this->source);

                    if ($exec_in_docker) {
                        $repoUrl = "$source_html_url_scheme://x-access-token:$github_access_token@$source_html_url_host/{$customRepository}.git";
                        $escapedRepoUrl = escapeshellarg($repoUrl);
                        $base_command = "{$base_command} {$escapedRepoUrl}";
                        $fullRepoUrl = $repoUrl;
                    } else {
                        $repoUrl = "$source_html_url_scheme://x-access-token:$github_access_token@$source_html_url_host/{$customRepository}";
                        $escapedRepoUrl = escapeshellarg($repoUrl);
                        $base_command = "{$base_command} {$escapedRepoUrl}";
                        $fullRepoUrl = $repoUrl;
                    }
                }

                if ($exec_in_docker) {
                    $commands->push(executeInDocker($deployment_uuid, $base_command));
                } else {
                    $commands->push($base_command);
                }

                return [
                    'commands' => $commands->implode(' && '),
                    'branch' => $branch,
                    'fullRepoUrl' => $fullRepoUrl,
                ];
            }
        }

        if ($this->deploymentType() === 'deploy_key') {
            $fullRepoUrl = $customRepository;
            $private_key = data_get($this, 'private_key.private_key');
            if (is_null($private_key)) {
                throw new RuntimeException('Private key not found. Please add a private key to the application and try again.');
            }
            $private_key = base64_encode($private_key);
            // When used with executeInDocker (which uses bash -c '...'), we need to escape for bash context
            // Replace ' with '\'' to safely escape within single-quoted bash strings
            $escapedCustomRepository = str_replace("'", "'\\''", $customRepository);
            $base_command = "GIT_SSH_COMMAND=\"ssh -o ConnectTimeout=30 -p {$customPort} -o Port={$customPort} -o LogLevel=ERROR -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -i /root/.ssh/id_rsa\" {$base_command} '{$escapedCustomRepository}'";

            if ($exec_in_docker) {
                $commands = collect([
                    executeInDocker($deployment_uuid, 'mkdir -p /root/.ssh'),
                    executeInDocker($deployment_uuid, "echo '{$private_key}' | base64 -d | tee /root/.ssh/id_rsa > /dev/null"),
                    executeInDocker($deployment_uuid, 'chmod 600 /root/.ssh/id_rsa'),
                ]);
            } else {
                $commands = collect([
                    'mkdir -p /root/.ssh',
                    "echo '{$private_key}' | base64 -d | tee /root/.ssh/id_rsa > /dev/null",
                    'chmod 600 /root/.ssh/id_rsa',
                ]);
            }

            if ($exec_in_docker) {
                $commands->push(executeInDocker($deployment_uuid, $base_command));
            } else {
                $commands->push($base_command);
            }

            return [
                'commands' => $commands->implode(' && '),
                'branch' => $branch,
                'fullRepoUrl' => $fullRepoUrl,
            ];
        }

        if ($this->deploymentType() === 'other') {
            // For public HTTPS repositories, use the original full URL (not the extracted path)
            $originalGitRepo = $this->git_repository;
            if (str($originalGitRepo)->startsWith('http://') || str($originalGitRepo)->startsWith('https://')) {
                $fullRepoUrl = $originalGitRepo;
                $escapedCustomRepository = escapeshellarg($originalGitRepo);
            } else {
                $fullRepoUrl = $customRepository;
                $escapedCustomRepository = escapeshellarg($customRepository);
            }
            $base_command = "{$base_command} {$escapedCustomRepository}";

            if ($exec_in_docker) {
                $commands->push(executeInDocker($deployment_uuid, $base_command));
            } else {
                $commands->push($base_command);
            }

            return [
                'commands' => $commands->implode(' && '),
                'branch' => $branch,
                'fullRepoUrl' => $fullRepoUrl,
            ];
        }
    }

    public function generateGitImportCommands(string $deployment_uuid, int $pull_request_id = 0, ?string $git_type = null, bool $exec_in_docker = true, bool $only_checkout = false, ?string $custom_base_dir = null, ?string $commit = null)
    {
        $branch = $this->git_branch;
        ['git_repository' => $customRepository, 'git_port' => $customPort] = $this->customRepository();
        $baseDir = $custom_base_dir ?? $this->generateBaseDir($deployment_uuid);

        // Escape shell arguments for safety to prevent command injection
        $escapedBranch = escapeshellarg($branch);
        $escapedBaseDir = escapeshellarg($baseDir);

        $commands = collect([]);

        // Check if shallow clone is enabled
        $isShallowCloneEnabled = $this->settings->is_git_shallow_clone_enabled ?? false;
        $depthFlag = $isShallowCloneEnabled ? ' --depth=1' : '';

        $submoduleFlags = '';
        if ($this->settings->is_git_submodules_enabled) {
            $submoduleFlags = ' --recurse-submodules';
            if ($isShallowCloneEnabled) {
                $submoduleFlags .= ' --shallow-submodules';
            }
        }

        $git_clone_command = "git clone{$depthFlag}{$submoduleFlags} -b {$escapedBranch}";
        if ($only_checkout) {
            $git_clone_command = "git clone{$depthFlag}{$submoduleFlags} --no-checkout -b {$escapedBranch}";
        }
        if ($pull_request_id !== 0) {
            $pr_branch_name = "pr-{$pull_request_id}-saturn";
        }
        if ($this->deploymentType() === 'source') {
            $source_html_url = data_get($this, 'source.html_url');
            $url = parse_url(filter_var($source_html_url, FILTER_SANITIZE_URL));
            $source_html_url_host = $url['host'];
            $source_html_url_scheme = $url['scheme'];

            if ($this->source instanceof \App\Models\GithubApp) {
                if ($this->source->getAttribute('is_public')) {
                    $fullRepoUrl = "{$this->source->getAttribute('html_url')}/{$customRepository}";
                    $escapedRepoUrl = escapeshellarg("{$this->source->getAttribute('html_url')}/{$customRepository}");
                    $git_clone_command = "{$git_clone_command} {$escapedRepoUrl} {$escapedBaseDir}";
                    if (! $only_checkout) {
                        $git_clone_command = $this->setGitImportSettings($deployment_uuid, $git_clone_command, public: true);
                    }
                    if ($exec_in_docker) {
                        $commands->push(executeInDocker($deployment_uuid, $git_clone_command));
                    } else {
                        $commands->push($git_clone_command);
                    }
                } else {
                    $github_access_token = generateGithubInstallationToken($this->source);
                    if ($exec_in_docker) {
                        $repoUrl = "$source_html_url_scheme://x-access-token:$github_access_token@$source_html_url_host/{$customRepository}.git";
                        $escapedRepoUrl = escapeshellarg($repoUrl);
                        $git_clone_command = "{$git_clone_command} {$escapedRepoUrl} {$escapedBaseDir}";
                        $fullRepoUrl = $repoUrl;
                    } else {
                        $repoUrl = "$source_html_url_scheme://x-access-token:$github_access_token@$source_html_url_host/{$customRepository}";
                        $escapedRepoUrl = escapeshellarg($repoUrl);
                        $git_clone_command = "{$git_clone_command} {$escapedRepoUrl} {$escapedBaseDir}";
                        $fullRepoUrl = $repoUrl;
                    }
                    if (! $only_checkout) {
                        $git_clone_command = $this->setGitImportSettings($deployment_uuid, $git_clone_command, public: false);
                    }
                    if ($exec_in_docker) {
                        $commands->push(executeInDocker($deployment_uuid, $git_clone_command));
                    } else {
                        $commands->push($git_clone_command);
                    }
                }
                if ($pull_request_id !== 0) {
                    $branch = "pull/{$pull_request_id}/head:$pr_branch_name";

                    $git_checkout_command = $this->buildGitCheckoutCommand($pr_branch_name);
                    $escapedPrBranch = escapeshellarg($branch);
                    if ($exec_in_docker) {
                        $commands->push(executeInDocker($deployment_uuid, "cd {$escapedBaseDir} && git fetch origin {$escapedPrBranch} && $git_checkout_command"));
                    } else {
                        $commands->push("cd {$escapedBaseDir} && git fetch origin {$escapedPrBranch} && $git_checkout_command");
                    }
                }

                return [
                    'commands' => $commands->implode(' && '),
                    'branch' => $branch,
                    'fullRepoUrl' => $fullRepoUrl,
                ];
            }
        }
        if ($this->deploymentType() === 'deploy_key') {
            $fullRepoUrl = $customRepository;
            $private_key = data_get($this, 'private_key.private_key');
            if (is_null($private_key)) {
                throw new RuntimeException('Private key not found. Please add a private key to the application and try again.');
            }
            $private_key = base64_encode($private_key);
            $escapedCustomRepository = escapeshellarg($customRepository);
            $git_clone_command_base = "GIT_SSH_COMMAND=\"ssh -o ConnectTimeout=30 -p {$customPort} -o Port={$customPort} -o LogLevel=ERROR -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -i /root/.ssh/id_rsa\" {$git_clone_command} {$escapedCustomRepository} {$escapedBaseDir}";
            if ($only_checkout) {
                $git_clone_command = $git_clone_command_base;
            } else {
                $git_clone_command = $this->setGitImportSettings($deployment_uuid, $git_clone_command_base);
            }
            if ($exec_in_docker) {
                $commands = collect([
                    executeInDocker($deployment_uuid, 'mkdir -p /root/.ssh'),
                    executeInDocker($deployment_uuid, "echo '{$private_key}' | base64 -d | tee /root/.ssh/id_rsa > /dev/null"),
                    executeInDocker($deployment_uuid, 'chmod 600 /root/.ssh/id_rsa'),
                ]);
            } else {
                $commands = collect([
                    'mkdir -p /root/.ssh',
                    "echo '{$private_key}' | base64 -d | tee /root/.ssh/id_rsa > /dev/null",
                    'chmod 600 /root/.ssh/id_rsa',
                ]);
            }
            if ($pull_request_id !== 0) {
                if ($git_type === 'gitlab') {
                    $branch = "merge-requests/{$pull_request_id}/head:$pr_branch_name";
                    if ($exec_in_docker) {
                        $commands->push(executeInDocker($deployment_uuid, "echo 'Checking out $branch'"));
                    } else {
                        $commands->push("echo 'Checking out $branch'");
                    }
                    $git_clone_command = "{$git_clone_command} && cd {$escapedBaseDir} && GIT_SSH_COMMAND=\"ssh -o ConnectTimeout=30 -p {$customPort} -o Port={$customPort} -o LogLevel=ERROR -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -i /root/.ssh/id_rsa\" git fetch origin $branch && ".$this->buildGitCheckoutCommand($pr_branch_name);
                } elseif ($git_type === 'github' || $git_type === 'gitea') {
                    $branch = "pull/{$pull_request_id}/head:$pr_branch_name";
                    if ($exec_in_docker) {
                        $commands->push(executeInDocker($deployment_uuid, "echo 'Checking out $branch'"));
                    } else {
                        $commands->push("echo 'Checking out $branch'");
                    }
                    $git_clone_command = "{$git_clone_command} && cd {$escapedBaseDir} && GIT_SSH_COMMAND=\"ssh -o ConnectTimeout=30 -p {$customPort} -o Port={$customPort} -o LogLevel=ERROR -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -i /root/.ssh/id_rsa\" git fetch origin $branch && ".$this->buildGitCheckoutCommand($pr_branch_name);
                } elseif ($git_type === 'bitbucket') {
                    if ($exec_in_docker) {
                        $commands->push(executeInDocker($deployment_uuid, "echo 'Checking out $branch'"));
                    } else {
                        $commands->push("echo 'Checking out $branch'");
                    }
                    $git_clone_command = "{$git_clone_command} && cd {$escapedBaseDir} && GIT_SSH_COMMAND=\"ssh -o ConnectTimeout=30 -p {$customPort} -o Port={$customPort} -o LogLevel=ERROR -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -i /root/.ssh/id_rsa\" ".$this->buildGitCheckoutCommand($commit);
                }
            }

            if ($exec_in_docker) {
                $commands->push(executeInDocker($deployment_uuid, $git_clone_command));
            } else {
                $commands->push($git_clone_command);
            }

            return [
                'commands' => $commands->implode(' && '),
                'branch' => $branch,
                'fullRepoUrl' => $fullRepoUrl,
            ];
        }
        if ($this->deploymentType() === 'other') {
            // For public HTTPS repositories, use the original full URL (not the extracted path)
            $originalGitRepo = $this->git_repository;
            if (str($originalGitRepo)->startsWith('http://') || str($originalGitRepo)->startsWith('https://')) {
                $fullRepoUrl = $originalGitRepo;
                $escapedCustomRepository = escapeshellarg($originalGitRepo);
            } else {
                $fullRepoUrl = $customRepository;
                $escapedCustomRepository = escapeshellarg($customRepository);
            }
            $git_clone_command = "{$git_clone_command} {$escapedCustomRepository} {$escapedBaseDir}";
            $git_clone_command = $this->setGitImportSettings($deployment_uuid, $git_clone_command, public: true);

            if ($pull_request_id !== 0) {
                if ($git_type === 'gitlab') {
                    $branch = "merge-requests/{$pull_request_id}/head:$pr_branch_name";
                    if ($exec_in_docker) {
                        $commands->push(executeInDocker($deployment_uuid, "echo 'Checking out $branch'"));
                    } else {
                        $commands->push("echo 'Checking out $branch'");
                    }
                    $git_clone_command = "{$git_clone_command} && cd {$escapedBaseDir} && GIT_SSH_COMMAND=\"ssh -o ConnectTimeout=30 -p {$customPort} -o Port={$customPort} -o LogLevel=ERROR -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -i /root/.ssh/id_rsa\" git fetch origin $branch && ".$this->buildGitCheckoutCommand($pr_branch_name);
                } elseif ($git_type === 'github' || $git_type === 'gitea') {
                    $branch = "pull/{$pull_request_id}/head:$pr_branch_name";
                    if ($exec_in_docker) {
                        $commands->push(executeInDocker($deployment_uuid, "echo 'Checking out $branch'"));
                    } else {
                        $commands->push("echo 'Checking out $branch'");
                    }
                    $git_clone_command = "{$git_clone_command} && cd {$escapedBaseDir} && GIT_SSH_COMMAND=\"ssh -o ConnectTimeout=30 -p {$customPort} -o Port={$customPort} -o LogLevel=ERROR -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -i /root/.ssh/id_rsa\" git fetch origin $branch && ".$this->buildGitCheckoutCommand($pr_branch_name);
                } elseif ($git_type === 'bitbucket') {
                    if ($exec_in_docker) {
                        $commands->push(executeInDocker($deployment_uuid, "echo 'Checking out $branch'"));
                    } else {
                        $commands->push("echo 'Checking out $branch'");
                    }
                    $git_clone_command = "{$git_clone_command} && cd {$escapedBaseDir} && GIT_SSH_COMMAND=\"ssh -o ConnectTimeout=30 -p {$customPort} -o Port={$customPort} -o LogLevel=ERROR -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -i /root/.ssh/id_rsa\" ".$this->buildGitCheckoutCommand($commit);
                }
            }

            if ($exec_in_docker) {
                $commands->push(executeInDocker($deployment_uuid, $git_clone_command));
            } else {
                $commands->push($git_clone_command);
            }

            return [
                'commands' => $commands->implode(' && '),
                'branch' => $branch,
                'fullRepoUrl' => $fullRepoUrl,
            ];
        }
    }

    public function oldRawParser()
    {
        try {
            $yaml = Yaml::parse($this->docker_compose_raw);
        } catch (\Exception $e) {
            throw new \RuntimeException($e->getMessage());
        }
        $services = data_get($yaml, 'services');

        $commands = collect([]);
        $services = collect($services)->map(function ($service) use ($commands) {
            $serviceVolumes = collect(data_get($service, 'volumes', []));
            if ($serviceVolumes->count() > 0) {
                foreach ($serviceVolumes as $volume) {
                    $workdir = $this->workdir();
                    $type = null;
                    $source = null;
                    if (is_string($volume)) {
                        $source = str($volume)->before(':');
                        if ($source->startsWith('./') || $source->startsWith('/') || $source->startsWith('~')) {
                            $type = str('bind');
                        }
                    } elseif (is_array($volume)) {
                        $type = data_get_str($volume, 'type');
                        $source = data_get_str($volume, 'source');
                    }
                    if ($type?->value() === 'bind') {
                        if ($source->value() === '/var/run/docker.sock') {
                            continue;
                        }
                        if ($source->value() === '/tmp' || $source->value() === '/tmp/') {
                            continue;
                        }
                        if ($source->startsWith('.')) {
                            $source = $source->after('.');
                            $source = $workdir.$source;
                        }
                        $commands->push("mkdir -p $source > /dev/null 2>&1 || true");
                    }
                }
            }
            $labels = collect(data_get($service, 'labels', []));
            if (! $labels->contains('saturn.managed')) {
                $labels->push('saturn.managed=true');
            }
            if (! $labels->contains('saturn.applicationId')) {
                $labels->push('saturn.applicationId='.$this->id);
            }
            if (! $labels->contains('saturn.type')) {
                $labels->push('saturn.type=application');
            }
            data_set($service, 'labels', $labels->toArray());

            return $service;
        });
        data_set($yaml, 'services', $services->toArray());
        $this->docker_compose_raw = Yaml::dump($yaml, 10, 2);

        instant_remote_process($commands, $this->destination->server, false);
    }

    public function parse(int $pull_request_id = 0, ?int $preview_id = null, ?string $commit = null)
    {
        if ((int) $this->compose_parsing_version >= 3) {
            return applicationParser($this, $pull_request_id, $preview_id, $commit);
        } elseif ($this->docker_compose_raw) {
            return parseDockerComposeFile(resource: $this, isNew: false, pull_request_id: $pull_request_id, preview_id: $preview_id);
        } else {
            return collect([]);
        }
    }

    public function loadComposeFile($isInit = false, ?string $restoreBaseDirectory = null, ?string $restoreDockerComposeLocation = null)
    {
        // Use provided restore values or capture current values as fallback
        $initialDockerComposeLocation = $restoreDockerComposeLocation ?? $this->docker_compose_location;
        $initialBaseDirectory = $restoreBaseDirectory ?? $this->base_directory;
        if ($isInit && $this->docker_compose_raw) {
            return;
        }
        $uuid = new Cuid2;
        ['commands' => $cloneCommand] = $this->generateGitImportCommands(deployment_uuid: $uuid, only_checkout: true, exec_in_docker: false, custom_base_dir: '.');
        $workdir = rtrim($this->base_directory, '/');
        $composeFile = $this->docker_compose_location;
        $fileList = collect([".$workdir$composeFile"]);
        $gitRemoteStatus = $this->getGitRemoteStatus(deployment_uuid: $uuid);
        if (! $gitRemoteStatus['is_accessible']) {
            throw new \RuntimeException("Failed to read Git source:\n\n{$gitRemoteStatus['error']}");
        }
        $getGitVersion = instant_remote_process(['git --version'], $this->destination->server, false);
        $gitVersion = str($getGitVersion)->explode(' ')->last();

        if (version_compare($gitVersion, '2.35.1', '<')) {
            $fileList = $fileList->map(function ($file) {
                $parts = explode('/', trim($file, '.'));
                $paths = collect();
                $currentPath = '';
                foreach ($parts as $part) {
                    $currentPath .= ($currentPath ? '/' : '').$part;
                    if (str($currentPath)->isNotEmpty()) {
                        $paths->push($currentPath);
                    }
                }

                return $paths;
            })->flatten()->unique()->values();
            $commands = collect([
                "rm -rf /tmp/{$uuid}",
                "mkdir -p /tmp/{$uuid}",
                "cd /tmp/{$uuid}",
                $cloneCommand,
                'git sparse-checkout init',
                "git sparse-checkout set {$fileList->implode(' ')}",
                'git read-tree -mu HEAD',
                "cat .$workdir$composeFile",
            ]);
        } else {
            $commands = collect([
                "rm -rf /tmp/{$uuid}",
                "mkdir -p /tmp/{$uuid}",
                "cd /tmp/{$uuid}",
                $cloneCommand,
                'git sparse-checkout init --cone',
                "git sparse-checkout set {$fileList->implode(' ')}",
                'git read-tree -mu HEAD',
                "cat .$workdir$composeFile",
            ]);
        }
        try {
            $composeFileContent = instant_remote_process($commands, $this->destination->server);
        } catch (\Exception $e) {
            if (str($e->getMessage())->contains('No such file')) {
                throw new \RuntimeException("Docker Compose file not found at: $workdir$composeFile<br><br>Check if you used the right extension (.yaml or .yml) in the compose file name.");
            }
            if (str($e->getMessage())->contains('fatal: repository') && str($e->getMessage())->contains('does not exist')) {
                if ($this->deploymentType() === 'deploy_key') {
                    throw new \RuntimeException('Your deploy key does not have access to the repository. Please check your deploy key and try again.');
                }
                throw new \RuntimeException('Repository does not exist. Please check your repository URL and try again.');
            }
            throw new \RuntimeException($e->getMessage());
        } finally {
            $this->docker_compose_location = $initialDockerComposeLocation;
            $this->base_directory = $initialBaseDirectory;
            $this->save();
            $commands = collect([
                "rm -rf /tmp/{$uuid}",
            ]);
            instant_remote_process($commands, $this->destination->server, false);
        }
        if ($composeFileContent) {
            $this->docker_compose_raw = $composeFileContent;
            $this->save();
            $parsedServices = $this->parse();
            if ($this->docker_compose_domains) {
                $decoded = json_decode($this->docker_compose_domains, true);
                $json = collect(is_array($decoded) ? $decoded : []);
                $normalized = collect();
                foreach ($json as $key => $value) {
                    $normalizedKey = (string) str($key)->replace('-', '_')->replace('.', '_');
                    $normalized->put($normalizedKey, $value);
                }
                $json = $normalized;
                $services = collect(data_get($parsedServices, 'services', []));
                foreach ($services as $name => $service) {
                    if (str($name)->contains('-') || str($name)->contains('.')) {
                        $replacedName = str($name)->replace('-', '_')->replace('.', '_');
                        $services->put((string) $replacedName, $service);
                        $services->forget((string) $name);
                    }
                }
                $names = collect($services)->keys()->toArray();
                $jsonNames = $json->keys()->toArray();
                $diff = array_diff($jsonNames, $names);
                $json = $json->filter(function ($value, $key) use ($diff) {
                    return ! in_array($key, $diff);
                });
                if ($json->isNotEmpty()) {
                    $this->docker_compose_domains = json_encode($json);
                } else {
                    $this->docker_compose_domains = null;
                }
                $this->save();
            }

            return [
                'parsedServices' => $parsedServices,
                'initialDockerComposeLocation' => $this->docker_compose_location,
            ];
        } else {
            throw new \RuntimeException("Docker Compose file not found at: $workdir$composeFile<br><br>Check if you used the right extension (.yaml or .yml) in the compose file name.");
        }
    }

    public function parseContainerLabels(?ApplicationPreview $preview = null)
    {
        $customLabels = data_get($this, 'custom_labels');
        if (! $customLabels) {
            return;
        }
        if (base64_encode(base64_decode($customLabels, true)) !== $customLabels) {
            $this->custom_labels = str($customLabels)->replace(',', "\n");
            $this->custom_labels = base64_encode($customLabels);
        }
        $customLabels = base64_decode($this->custom_labels);
        if (mb_detect_encoding($customLabels, 'ASCII', true) === false) {
            $customLabels = str(implode('|saturn|', generateLabelsApplication($this, $preview)))->replace('|saturn|', "\n");
        }
        $this->custom_labels = base64_encode($customLabels);
        $this->save();

        return $customLabels;
    }

    public function fqdns(): Attribute
    {
        return Attribute::make(
            get: fn () => is_null($this->fqdn)
                ? []
                : explode(',', $this->fqdn),
        );
    }

    protected function buildGitCheckoutCommand($target): string
    {
        $escapedTarget = escapeshellarg($target);
        $command = "git checkout {$escapedTarget}";

        if ($this->settings->is_git_submodules_enabled) {
            $command .= ' && git submodule update --init --recursive';
        }

        return $command;
    }

    private function parseWatchPaths($value)
    {
        if ($value) {
            $watch_paths = collect(explode("\n", $value))
                ->map(function (string $path): string {
                    // Trim whitespace
                    $path = trim($path);

                    if (str_starts_with($path, '!')) {
                        $negation = '!';
                        $pathWithoutNegation = substr($path, 1);
                        $pathWithoutNegation = ltrim(trim($pathWithoutNegation), '/');

                        return $negation.$pathWithoutNegation;
                    }

                    return ltrim($path, '/');
                })
                ->filter(function (string $path): bool {
                    return strlen($path) > 0;
                });

            return trim($watch_paths->implode("\n"));
        }
    }

    public function watchPaths(): Attribute
    {
        return Attribute::make(
            set: function ($value) {
                if ($value) {
                    return $this->parseWatchPaths($value);
                }
            }
        );
    }

    public function matchWatchPaths(Collection $modified_files, ?Collection $watch_paths): Collection
    {
        return self::matchPaths($modified_files, $watch_paths);
    }

    /**
     * Static method to match paths against watch patterns with negation support
     * Uses order-based matching: last matching pattern wins
     */
    public static function matchPaths(Collection $modified_files, ?Collection $watch_paths): Collection
    {
        if (is_null($watch_paths) || $watch_paths->isEmpty()) {
            return collect([]);
        }

        return $modified_files->filter(function ($file) use ($watch_paths) {
            $shouldInclude = null; // null means no patterns matched

            // Process patterns in order - last match wins
            foreach ($watch_paths as $pattern) {
                $pattern = trim($pattern);
                if (empty($pattern)) {
                    continue;
                }

                $isExclusion = str_starts_with($pattern, '!');
                $matchPattern = $isExclusion ? substr($pattern, 1) : $pattern;

                if (self::globMatch($matchPattern, $file)) {
                    // This pattern matches - it determines the current state
                    $shouldInclude = ! $isExclusion;
                }
            }

            // If no patterns matched and we only have exclusion patterns, include by default
            if ($shouldInclude === null) {
                // Check if we only have exclusion patterns
                $hasInclusionPatterns = $watch_paths->contains(fn ($p) => ! str_starts_with(trim($p), '!'));

                return ! $hasInclusionPatterns;
            }

            return $shouldInclude;
        })->values();
    }

    /**
     * Check if a path matches a glob pattern
     * Supports: *, **, ?, [abc], [!abc]
     */
    public static function globMatch(string $pattern, string $path): bool
    {
        $regex = self::globToRegex($pattern);

        return preg_match($regex, $path) === 1;
    }

    /**
     * Convert a glob pattern to a regular expression
     */
    public static function globToRegex(string $pattern): string
    {
        $regex = '';
        $inGroup = false;
        $chars = str_split($pattern);
        $len = count($chars);

        for ($i = 0; $i < $len; $i++) {
            $c = $chars[$i];

            switch ($c) {
                case '*':
                    // Check for **
                    if ($i + 1 < $len && $chars[$i + 1] === '*') {
                        // ** matches any number of directories
                        $regex .= '.*';
                        $i++; // Skip next *
                        // Skip optional /
                        if ($i + 1 < $len && $chars[$i + 1] === '/') {
                            $i++;
                        }
                    } else {
                        // * matches anything except /
                        $regex .= '[^/]*';
                    }
                    break;

                case '?':
                    // ? matches any single character except /
                    $regex .= '[^/]';
                    break;

                case '[':
                    // Character class
                    $inGroup = true;
                    $regex .= '[';
                    // Check for negation
                    if ($i + 1 < $len && ($chars[$i + 1] === '!' || $chars[$i + 1] === '^')) {
                        $regex .= '^';
                        $i++;
                    }
                    break;

                case ']':
                    if ($inGroup) {
                        $inGroup = false;
                        $regex .= ']';
                    } else {
                        $regex .= preg_quote($c, '#');
                    }
                    break;

                case '.':
                case '(':
                case ')':
                case '+':
                case '{':
                case '}':
                case '$':
                case '^':
                case '|':
                case '\\':
                    // Escape regex special characters
                    $regex .= '\\'.$c;
                    break;

                default:
                    $regex .= $c;
                    break;
            }
        }

        // Wrap in delimiters and anchors
        return '#^'.$regex.'$#';
    }

    public function normalizeWatchPaths(): void
    {
        if (is_null($this->watch_paths)) {
            return;
        }

        $normalized = $this->parseWatchPaths($this->watch_paths);
        if ($normalized !== $this->watch_paths) {
            $this->watch_paths = $normalized;
            $this->save();
        }
    }

    public function isWatchPathsTriggered(Collection $modified_files): bool
    {
        if (is_null($this->watch_paths)) {
            return false;
        }

        $this->normalizeWatchPaths();

        $watch_paths = collect(explode("\n", $this->watch_paths));

        if ($watch_paths->isEmpty()) {
            return false;
        }
        $matches = $this->matchWatchPaths($modified_files, $watch_paths);

        return $matches->count() > 0;
    }

    public function getFilesFromServer(bool $isInit = false)
    {
        getFilesystemVolumesFromServer($this, $isInit);
    }

    public function parseHealthcheckFromDockerfile($dockerfile, bool $isInit = false)
    {
        $dockerfile = str($dockerfile)->trim()->explode("\n");
        $hasHealthcheck = str($dockerfile)->contains('HEALTHCHECK');

        // Always check if healthcheck was removed, regardless of health_check_enabled setting
        if (! $hasHealthcheck && $this->custom_healthcheck_found) {
            // HEALTHCHECK was removed from Dockerfile, reset to defaults
            $this->custom_healthcheck_found = false;
            $this->health_check_interval = 5;
            $this->health_check_timeout = 5;
            $this->health_check_retries = 10;
            $this->health_check_start_period = 5;
            $this->save();

            return;
        }

        if ($hasHealthcheck && ($this->isHealthcheckDisabled() || $isInit)) {
            $healthcheckCommand = null;
            $lines = $dockerfile->toArray();
            foreach ($lines as $line) {
                $trimmedLine = trim($line);
                if (str_starts_with($trimmedLine, 'HEALTHCHECK')) {
                    $healthcheckCommand .= trim($trimmedLine, '\\ ');

                    continue;
                }
                if (isset($healthcheckCommand) && str_contains($trimmedLine, '\\')) {
                    $healthcheckCommand .= ' '.trim($trimmedLine, '\\ ');
                }
                if (isset($healthcheckCommand) && ! str_contains($trimmedLine, '\\') && ! empty($healthcheckCommand)) {
                    $healthcheckCommand .= ' '.$trimmedLine;
                    break;
                }
            }
            if (str($healthcheckCommand)->isNotEmpty()) {
                $interval = str($healthcheckCommand)->match('/--interval=([0-9]+[a-zµ]*)/');
                $timeout = str($healthcheckCommand)->match('/--timeout=([0-9]+[a-zµ]*)/');
                $start_period = str($healthcheckCommand)->match('/--start-period=([0-9]+[a-zµ]*)/');
                $retries = str($healthcheckCommand)->match('/--retries=(\d+)/');

                if ($interval->isNotEmpty()) {
                    $this->health_check_interval = parseDockerfileInterval($interval);
                }
                if ($timeout->isNotEmpty()) {
                    $this->health_check_timeout = parseDockerfileInterval($timeout);
                }
                if ($start_period->isNotEmpty()) {
                    $this->health_check_start_period = parseDockerfileInterval($start_period);
                }
                if ($retries->isNotEmpty()) {
                    $this->health_check_retries = $retries->toInteger();
                }
                if ($interval->isNotEmpty() || $timeout->isNotEmpty() || $start_period->isNotEmpty() || $retries->isNotEmpty()) {
                    $this->custom_healthcheck_found = true;
                    $this->save();
                }
            }
        }
    }

    public static function getDomainsByUuid(string $uuid): array
    {
        $application = self::where('uuid', $uuid)->first();

        if ($application) {
            return $application->fqdns->all();
        }

        return [];
    }

    public function getCpuMetrics(int $mins = 5)
    {
        $server = $this->destination->server;
        $container_name = $this->uuid;
        if ($server->isMetricsEnabled()) {
            $from = now()->subMinutes($mins)->toIso8601ZuluString();
            $metrics = instant_remote_process(["docker exec saturn-sentinel sh -c 'curl -H \"Authorization: Bearer {$server->settings->sentinel_token}\" http://localhost:8888/api/container/{$container_name}/cpu/history?from=$from'"], $server, false);
            if (str($metrics)->contains('error')) {
                $error = json_decode($metrics, true);
                $error = data_get($error, 'error', 'Something is not okay, are you okay?');
                if ($error === 'Unauthorized') {
                    $error = 'Unauthorized, please check your metrics token or restart Sentinel to set a new token.';
                }
                throw new \Exception($error);
            }
            $metrics = json_decode($metrics, true);
            $parsedCollection = collect($metrics)->map(function ($metric) {
                return [(int) $metric['time'], (float) $metric['percent']];
            });

            return $parsedCollection->toArray();
        }
    }

    public function getMemoryMetrics(int $mins = 5)
    {
        $server = $this->destination->server;
        $container_name = $this->uuid;
        if ($server->isMetricsEnabled()) {
            $from = now()->subMinutes($mins)->toIso8601ZuluString();
            $metrics = instant_remote_process(["docker exec saturn-sentinel sh -c 'curl -H \"Authorization: Bearer {$server->settings->sentinel_token}\" http://localhost:8888/api/container/{$container_name}/memory/history?from=$from'"], $server, false);
            if (str($metrics)->contains('error')) {
                $error = json_decode($metrics, true);
                $error = data_get($error, 'error', 'Something is not okay, are you okay?');
                if ($error === 'Unauthorized') {
                    $error = 'Unauthorized, please check your metrics token or restart Sentinel to set a new token.';
                }
                throw new \Exception($error);
            }
            $metrics = json_decode($metrics, true);
            $parsedCollection = collect($metrics)->map(function ($metric) {
                return [(int) $metric['time'], (float) $metric['used']];
            });

            return $parsedCollection->toArray();
        }
    }

    public function getLimits(): array
    {
        return [
            'limits_memory' => $this->limits_memory,
            'limits_memory_swap' => $this->limits_memory_swap,
            'limits_memory_swappiness' => $this->limits_memory_swappiness,
            'limits_memory_reservation' => $this->limits_memory_reservation,
            'limits_cpus' => $this->limits_cpus,
            'limits_cpuset' => $this->limits_cpuset,
            'limits_cpu_shares' => $this->limits_cpu_shares,
        ];
    }

    public function generateConfig($is_json = false)
    {
        $generator = new ConfigurationGenerator($this);

        if ($is_json) {
            return $generator->toJson();
        }

        return $generator->toArray();
    }

    public function setConfig($config)
    {
        $validator = Validator::make(['config' => $config], [
            'config' => 'required|json',
        ]);
        if ($validator->fails()) {
            throw new \Exception('Invalid JSON format');
        }
        $config = json_decode($config, true);

        $deepValidator = Validator::make(['config' => $config], [
            'config.build_pack' => 'required|string',
            'config.base_directory' => 'required|string',
            'config.publish_directory' => 'required|string',
            'config.ports_exposes' => 'required|string',
            'config.settings.is_static' => 'required|boolean',
        ]);
        if ($deepValidator->fails()) {
            throw new \Exception('Invalid data');
        }
        $config = $deepValidator->validated()['config'];

        try {
            $settings = data_get($config, 'settings', []);
            data_forget($config, 'settings');
            $this->update($config);
            $this->settings()->update($settings);
        } catch (\Exception $e) {
            throw new \Exception('Failed to update application settings');
        }
    }

    /**
     * Get resource links where this application is the source.
     *
     * @return MorphMany<ResourceLink, $this>
     */
    public function resourceLinks(): MorphMany
    {
        return $this->morphMany(ResourceLink::class, 'source');
    }

    /**
     * Auto-inject connection URLs from linked resources (databases and applications).
     * This method checks for ResourceLinks and injects the appropriate env variables.
     * SECURITY: Validates that target belongs to the same team to prevent cross-team data leakage.
     */
    public function autoInjectDatabaseUrl(): void
    {
        if (! $this->auto_inject_database_url) {
            return;
        }

        // Get this application's team ID for security validation
        $sourceTeamId = data_get($this, 'environment.project.team.id');

        // Get links where this application is the source and auto_inject is enabled
        $links = ResourceLink::where('source_type', self::class)
            ->where('source_id', $this->id)
            ->where('auto_inject', true)
            ->with('target')
            ->get();

        foreach ($links as $link) {
            $target = $link->target;

            if (! $target) {
                continue;
            }

            // SECURITY: Validate target belongs to the same team
            $targetTeamId = data_get($target, 'environment.project.team.id');
            if ($sourceTeamId && $targetTeamId && $sourceTeamId !== $targetTeamId) {
                // Log security violation and skip this link
                \Illuminate\Support\Facades\Log::warning('ResourceLink cross-team access blocked', [
                    'source_application_id' => $this->id,
                    'source_team_id' => $sourceTeamId,
                    'target_id' => $target->id ?? null,
                    'target_team_id' => $targetTeamId,
                ]);

                continue;
            }

            // Determine the URL to inject based on target type
            $url = null;
            $envKey = null;

            if ($target instanceof self) {
                // Application-to-Application link
                $shouldUseExternal = $link->use_external_url;

                // Auto-detect cross-server: internal Docker URL won't work
                if (! $shouldUseExternal) {
                    $sourceServer = $this->destination?->server;
                    $targetServer = $target->destination?->server;
                    if ($sourceServer && $targetServer && $sourceServer->id !== $targetServer->id) {
                        $shouldUseExternal = true;
                    }
                }

                if ($shouldUseExternal && $target->fqdn) {
                    // fqdn may contain multiple comma-separated domains; take the first one
                    $fqdn = str_contains($target->fqdn, ',')
                        ? trim(explode(',', $target->fqdn)[0])
                        : $target->fqdn;

                    // Ensure the URL has a protocol (new parser stores bare domains without scheme)
                    if (! preg_match('#^https?://#', $fqdn)) {
                        $fqdn = 'https://'.$fqdn;
                    }
                    $url = $fqdn;
                } else {
                    $url = $target->internal_app_url;
                }
                $envKey = $link->getSmartAppEnvKey();
            } elseif (isset($target->internal_db_url)) {
                // Application-to-Database link
                $url = $target->internal_db_url;
                $envKey = $link->getEnvKey();
            }

            if (! $url || ! $envKey) {
                continue;
            }

            // For app-to-app links, make buildtime too (Next.js etc. need URL at docker build)
            $isBuildtime = $target instanceof self;

            // Create or update the environment variable
            $this->environment_variables()->updateOrCreate(
                ['key' => $envKey, 'is_preview' => false],
                [
                    'value' => $url,
                    'is_buildtime' => $isBuildtime,
                    'is_runtime' => true,
                ]
            );
        }
    }

    /**
     * Get sibling applications in the same monorepo group
     *
     * Note: This returns a query builder, not a HasMany relationship,
     * because we're querying by a non-foreign-key column.
     */
    public function monorepoSiblings(): \Illuminate\Database\Eloquent\Builder
    {
        if (! $this->monorepo_group_id) {
            return static::query()->whereRaw('1=0'); // Empty query
        }

        return static::query()
            ->where('monorepo_group_id', $this->monorepo_group_id)
            ->where('id', '!=', $this->id);
    }

    /**
     * Check if application is part of a monorepo group
     */
    public function isPartOfMonorepo(): bool
    {
        return $this->monorepo_group_id !== null;
    }

    /**
     * Get all applications in the monorepo group (including this one)
     */
    public function getMonorepoGroup(): Collection
    {
        if (! $this->monorepo_group_id) {
            return new Collection([$this]);
        }

        return static::where('monorepo_group_id', $this->monorepo_group_id)->get();
    }

    /**
     * Get count of apps in monorepo group
     */
    public function getMonorepoGroupCount(): int
    {
        if (! $this->monorepo_group_id) {
            return 1;
        }

        return static::where('monorepo_group_id', $this->monorepo_group_id)->count();
    }

    /**
     * Scope to filter by monorepo group
     */
    public function scopeInMonorepoGroup(\Illuminate\Database\Eloquent\Builder $query, string $groupId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('monorepo_group_id', $groupId);
    }

    /**
     * Scope to get only monorepo apps
     */
    public function scopeMonorepoApps(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNotNull('monorepo_group_id');
    }

    /**
     * Scope to get standalone apps (not in monorepo)
     */
    public function scopeStandaloneApps(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNull('monorepo_group_id');
    }
}
