<?php

namespace App\Jobs;

use App\Actions\Docker\GetContainersStatus;
use App\Enums\ApplicationDeploymentStatus;
use App\Enums\ProcessStatus;
use App\Events\ServiceStatusChanged;
use App\Exceptions\DeploymentException;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\ApplicationPreview;
use App\Models\GithubApp;
use App\Models\GitlabApp;
use App\Models\Server;
use App\Models\StandaloneDocker;
use App\Models\SwarmDocker;
use App\Traits\Deployment\HandlesBuildSecrets;
use App\Traits\Deployment\HandlesBuildtimeEnvGeneration;
use App\Traits\Deployment\HandlesComposeFileGeneration;
use App\Traits\Deployment\HandlesContainerOperations;
use App\Traits\Deployment\HandlesDeploymentCommands;
use App\Traits\Deployment\HandlesDeploymentConfiguration;
use App\Traits\Deployment\HandlesDeploymentStatus;
use App\Traits\Deployment\HandlesDockerComposeBuildpack;
use App\Traits\Deployment\HandlesDockerfileModification;
use App\Traits\Deployment\HandlesEnvExampleDetection;
use App\Traits\Deployment\HandlesGitOperations;
use App\Traits\Deployment\HandlesHealthCheck;
use App\Traits\Deployment\HandlesImageBuilding;
use App\Traits\Deployment\HandlesImageRegistry;
use App\Traits\Deployment\HandlesNixpacksBuildpack;
use App\Traits\Deployment\HandlesRuntimeEnvGeneration;
use App\Traits\Deployment\HandlesSaturnEnvVariables;
use App\Traits\Deployment\HandlesStaticBuildpack;
use App\Traits\EnvironmentVariableAnalyzer;
use App\Traits\ExecuteRemoteCommand;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Visus\Cuid2\Cuid2;

class ApplicationDeploymentJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, EnvironmentVariableAnalyzer, ExecuteRemoteCommand, HandlesBuildSecrets, HandlesBuildtimeEnvGeneration, HandlesComposeFileGeneration, HandlesContainerOperations, HandlesDeploymentCommands, HandlesDeploymentConfiguration, HandlesDeploymentStatus, HandlesDockerComposeBuildpack, HandlesDockerfileModification, HandlesEnvExampleDetection, HandlesGitOperations, HandlesHealthCheck, HandlesImageBuilding, HandlesImageRegistry, HandlesNixpacksBuildpack, HandlesRuntimeEnvGeneration, HandlesSaturnEnvVariables, HandlesStaticBuildpack, InteractsWithQueue, Queueable, SerializesModels;

    public const BUILD_TIME_ENV_PATH = '/artifacts/build-time.env';

    private const BUILD_SCRIPT_PATH = '/artifacts/build.sh';

    private const NIXPACKS_PLAN_PATH = '/artifacts/thegameplan.json';

    /**
     * Number of times the job may be attempted.
     * FIX: Changed from 1 to 3 to handle temporary network failures.
     */
    public $tries = 3;

    public $backoff = [30, 60, 120];

    public $timeout = 3600;

    public static int $batch_counter = 0;

    private bool $newVersionIsHealthy = false;

    private ?ApplicationDeploymentQueue $application_deployment_queue = null;

    private ?Application $application = null;

    private string $deployment_uuid;

    private int $pull_request_id;

    private string $commit;

    private bool $rollback;

    private bool $force_rebuild;

    private bool $restart_only;

    private ?string $dockerImage = null;

    private ?string $dockerImageTag = null;

    private GithubApp|GitlabApp|string $source = 'other';

    private StandaloneDocker|SwarmDocker $destination;

    // Deploy to Server
    private Server $server;

    // Build Server
    private Server $build_server;

    private bool $use_build_server = false;

    // Save original server between phases
    private Server $original_server;

    private Server $mainServer;

    private bool $is_this_additional_server = false;

    private ?ApplicationPreview $preview = null;

    private ?string $git_type = null;

    private bool $only_this_server = false;

    private string $container_name;

    private string $basedir;

    private string $workdir;

    private ?string $build_pack = null;

    private string $configuration_dir;

    private string $build_image_name;

    private string $production_image_name;

    private Collection|string $build_args;

    private $env_args;

    private $env_nixpacks_args;

    private $docker_compose;

    private $docker_compose_base64;

    private ?string $nixpacks_plan = null;

    private Collection $nixpacks_plan_json;

    private ?string $nixpacks_type = null;

    private ?string $requiredNodeVersion = null;

    private string $dockerfile_location = '/Dockerfile';

    private string $docker_compose_location = '/docker-compose.yaml';

    private ?string $docker_compose_custom_start_command = null;

    private ?string $docker_compose_custom_build_command = null;

    private ?string $addHosts = null;

    private ?string $buildTarget = null;

    private bool $disableBuildCache = false;

    private Collection $saved_outputs;

    private ?string $secrets_hash_key = null;

    private ?string $full_healthcheck_url = null;

    private string $serverUserHomeDir = '/root';

    private string $dockerConfigFileExists = 'NOK';

    private int $customPort = 22;

    private ?string $customRepository = null;

    private ?string $fullRepoUrl = null;

    private ?string $branch = null;

    private ?string $saturn_variables = null;

    private bool $preserveRepository = false;

    private bool $dockerBuildkitSupported = false;

    private string $build_secrets;

    public function tags()
    {
        // Do not remove this one, it needs to properly identify which worker is running the job
        return ['App\Models\ApplicationDeploymentQueue:'.$this->application_deployment_queue_id];
    }

    /**
     * Determine the time to wait before retrying the job (exponential backoff).
     *
     * @return array<int>
     */
    public function backoff(): array
    {
        return [30, 60, 120]; // Wait 30s, 60s, 120s between retries
    }

    public function __construct(public int $application_deployment_queue_id)
    {
        $this->onQueue('high');

        $this->application_deployment_queue = ApplicationDeploymentQueue::find($this->application_deployment_queue_id);

        // SECURITY FIX: Null check to prevent NPE if deployment queue was deleted
        if (! $this->application_deployment_queue) {
            Log::error('ApplicationDeploymentJob: Deployment queue not found', [
                'deployment_queue_id' => $this->application_deployment_queue_id,
            ]);
            throw new \RuntimeException("Deployment queue #{$this->application_deployment_queue_id} not found - may have been deleted");
        }

        $this->nixpacks_plan_json = collect([]);

        $this->application = Application::find($this->application_deployment_queue->application_id);

        // SECURITY FIX: Null check to prevent NPE if application was deleted
        if (! $this->application) {
            Log::error('ApplicationDeploymentJob: Application not found', [
                'application_id' => $this->application_deployment_queue->application_id,
                'deployment_queue_id' => $this->application_deployment_queue_id,
            ]);
            $this->application_deployment_queue->update(['status' => 'failed']);
            throw new \RuntimeException("Application #{$this->application_deployment_queue->application_id} not found - may have been deleted");
        }
        $this->build_pack = data_get($this->application, 'build_pack');
        $this->build_args = collect([]);
        $this->build_secrets = '';

        $this->deployment_uuid = $this->application_deployment_queue->deployment_uuid;
        $this->pull_request_id = $this->application_deployment_queue->pull_request_id;
        $this->commit = $this->application_deployment_queue->commit;
        $this->rollback = $this->application_deployment_queue->rollback;
        $this->disableBuildCache = $this->application->settings->disable_build_cache;
        $this->force_rebuild = $this->application_deployment_queue->force_rebuild;
        if ($this->disableBuildCache) {
            $this->force_rebuild = true;
        }
        $this->restart_only = $this->application_deployment_queue->restart_only;
        $this->restart_only = $this->restart_only && $this->application->build_pack !== 'dockerimage' && $this->application->build_pack !== 'dockerfile';
        $this->only_this_server = $this->application_deployment_queue->only_this_server;

        $this->git_type = data_get($this->application_deployment_queue, 'git_type');

        $source = data_get($this->application, 'source');
        if ($source) {
            $this->source = $source->getMorphClass()::where('id', $source->getKey())->first();
        }
        $this->server = Server::find($this->application_deployment_queue->server_id);
        $this->timeout = $this->server->settings->dynamic_timeout;
        $this->destination = $this->server->destinations()->where('id', $this->application_deployment_queue->destination_id)->first();
        $this->server = $this->mainServer = $this->destination->server;
        $this->is_this_additional_server = $this->application->additional_servers()->wherePivot('server_id', $this->server->id)->count() > 0;
        $this->preserveRepository = $this->application->settings->is_preserve_repository_enabled;

        $this->basedir = $this->application->generateBaseDir($this->deployment_uuid);
        $this->workdir = "{$this->basedir}".rtrim($this->application->base_directory, '/');
        $this->configuration_dir = application_configuration_dir()."/{$this->application->uuid}";
        $this->container_name = generateApplicationContainerName($this->application, $this->pull_request_id);
        if ($this->application->settings->custom_internal_name && ! $this->application->settings->is_consistent_container_name_enabled) {
            if ($this->pull_request_id === 0) {
                $this->container_name = $this->application->settings->custom_internal_name;
            } else {
                $this->container_name = addPreviewDeploymentSuffix($this->application->settings->custom_internal_name, $this->pull_request_id);
            }
        }

        $this->saved_outputs = collect();

        // Set preview fqdn
        if ($this->pull_request_id !== 0) {
            $this->preview = ApplicationPreview::findPreviewByApplicationAndPullId($this->application->id, $this->pull_request_id);
            if ($this->preview) {
                if ($this->application->build_pack === 'dockercompose') {
                    $this->preview->generate_preview_fqdn_compose();
                } else {
                    $this->preview->generate_preview_fqdn();
                }
            }
            if ($this->application->is_github_based()) {
                ApplicationPullRequestUpdateJob::dispatch(application: $this->application, preview: $this->preview, deployment_uuid: $this->deployment_uuid, status: ProcessStatus::IN_PROGRESS);
            }
            if ($this->application->build_pack === 'dockerfile') {
                if (data_get($this->application, 'dockerfile_location')) {
                    $this->dockerfile_location = $this->normalizeDockerfileLocation($this->application->dockerfile_location);
                }
            }
        }
    }

    public function handle(): void
    {
        // Check if deployment was cancelled before we even started
        $this->application_deployment_queue->refresh();
        if ($this->application_deployment_queue->status === ApplicationDeploymentStatus::CANCELLED_BY_USER->value) {
            $this->application_deployment_queue->addLogEntry('Deployment was cancelled before starting.');

            return;
        }

        // Check if deployment requires approval
        if ($this->application_deployment_queue->requires_approval) {
            if ($this->application_deployment_queue->approval_status === 'pending') {
                $this->application_deployment_queue->addLogEntry('Deployment is waiting for approval. Please approve via admin panel or API.');
                $this->application_deployment_queue->update([
                    'status' => ApplicationDeploymentStatus::QUEUED->value,
                ]);

                // Release the job back to queue to retry later
                $this->release(60); // Retry in 60 seconds

                return;
            }

            if ($this->application_deployment_queue->approval_status === 'rejected') {
                $this->application_deployment_queue->addLogEntry('Deployment was rejected by admin.');
                $this->application_deployment_queue->update([
                    'status' => ApplicationDeploymentStatus::CANCELLED_BY_USER->value,
                ]);

                return;
            }

            // If approved, log it and continue
            if ($this->application_deployment_queue->approval_status === 'approved') {
                $approver = $this->application_deployment_queue->approved_by ? " by user #{$this->application_deployment_queue->approved_by}" : '';
                $note = $this->application_deployment_queue->approval_note ? " Note: {$this->application_deployment_queue->approval_note}" : '';
                $this->application_deployment_queue->addLogEntry("Deployment approved{$approver}.{$note}");
            }
        }

        // Prevent race condition: lock application row and check for concurrent deployments
        $canProceed = DB::transaction(function () {
            $lockedApp = Application::lockForUpdate()->find($this->application->id);
            if (! $lockedApp) {
                return false;
            }

            // Check if another deployment is already in progress for this application
            $activeDeployment = ApplicationDeploymentQueue::where('application_id', $this->application->id)
                ->where('status', ApplicationDeploymentStatus::IN_PROGRESS->value)
                ->where('id', '!=', $this->application_deployment_queue->id)
                ->exists();

            if ($activeDeployment) {
                return false;
            }

            // Refresh our models inside the lock to get latest data
            $this->application = $lockedApp;
            $this->application_deployment_queue->update([
                'status' => ApplicationDeploymentStatus::IN_PROGRESS->value,
                'started_at' => Carbon::now(),
                'horizon_job_worker' => gethostname(),
            ]);

            return true;
        });

        if (! $canProceed) {
            // Another deployment is already running — re-queue with backoff
            $this->application_deployment_queue->addLogEntry('Another deployment is already in progress. Waiting...');
            $this->release(30);

            return;
        }

        if ($this->server->isFunctional() === false) {
            $this->application_deployment_queue->addLogEntry('Server is not functional.');
            $this->fail('Server is not functional.');

            return;
        }
        try {
            // Make sure the private key is stored in the filesystem
            $this->server->privateKey->storeInFileSystem();
            // Generate custom host<->ip mapping
            $allContainers = instant_remote_process(["docker network inspect {$this->destination->network} -f '{{json .Containers}}' "], $this->server);

            $allContainers = format_docker_command_output_to_json($allContainers);
            $ips = collect([]);
            if (count($allContainers) > 0) {
                $allContainers = $allContainers[0];
                $allContainers = collect($allContainers)->sort()->values();
                foreach ($allContainers as $container) {
                    $containerName = data_get($container, 'Name');
                    if ($containerName === 'saturn-proxy') {
                        continue;
                    }
                    if (preg_match('/-(\d{12})/', $containerName)) {
                        continue;
                    }
                    $containerIp = data_get($container, 'IPv4Address');
                    if ($containerName && $containerIp) {
                        $containerIp = str($containerIp)->before('/');
                        $ips->put($containerName, $containerIp->value());
                    }
                }
                $this->addHosts = $ips->map(function ($ip, $name) {
                    return "--add-host $name:$ip";
                })->implode(' ');
            }

            if ($this->application->dockerfile_target_build) {
                $this->buildTarget = " --target {$this->application->dockerfile_target_build} ";
            }

            // Check custom port
            ['git_repository' => $this->customRepository, 'git_port' => $this->customPort] = $this->application->customRepository();

            if (data_get($this->application, 'settings.is_build_server_enabled')) {
                $teamId = data_get($this->application, 'environment.project.team.id');
                DB::transaction(function () use ($teamId) {
                    // Lock the deployment queue record to prevent concurrent writes
                    $deployment = ApplicationDeploymentQueue::lockForUpdate()
                        ->find($this->application_deployment_queue->id);

                    if (! $deployment) {
                        $this->application_deployment_queue->addLogEntry('Deployment queue record not found. Using the deployment server.');
                        $this->build_server = $this->server;
                        $this->original_server = $this->server;

                        return;
                    }

                    $buildServers = Server::buildServers($teamId)->get();
                    if ($buildServers->count() === 0) {
                        $deployment->addLogEntry('No suitable build server found. Using the deployment server.');
                        $this->build_server = $this->server;
                        $this->original_server = $this->server;
                    } else {
                        $this->build_server = $buildServers->random();
                        $deployment->update(['build_server_id' => $this->build_server->id]);
                        $deployment->addLogEntry("Found a suitable build server ({$this->build_server->name}).");
                        $this->original_server = $this->server;
                        $this->use_build_server = true;
                    }
                    $this->application_deployment_queue = $deployment;
                });
            } else {
                // Set build server & original_server to the same as deployment server
                $this->build_server = $this->server;
                $this->original_server = $this->server;
            }
            $this->detectBuildKitCapabilities();
            $this->decide_what_to_do();
        } catch (Exception $e) {
            if ($this->pull_request_id !== 0 && $this->application->is_github_based()) {
                ApplicationPullRequestUpdateJob::dispatch(application: $this->application, preview: $this->preview, deployment_uuid: $this->deployment_uuid, status: ProcessStatus::ERROR);
            }
            $this->fail($e);
            throw $e;
        } finally {
            // Wrap cleanup operations in try-catch to prevent exceptions from interfering
            // with Laravel's job failure handling and status updates
            try {
                $this->application_deployment_queue->update([
                    'finished_at' => Carbon::now()->toImmutable(),
                ]);
            } catch (Exception $e) {
                // Log but don't fail - finished_at is not critical
                Log::warning('Failed to update finished_at for deployment '.$this->deployment_uuid.': '.$e->getMessage());
            }

            try {
                if ($this->use_build_server) {
                    $this->server = $this->build_server;
                } else {
                    $this->write_deployment_configurations();
                }
            } catch (Exception $e) {
                // Log but don't fail - configuration writing errors shouldn't prevent status updates
                $this->application_deployment_queue->addLogEntry('Warning: Failed to write deployment configurations: '.$e->getMessage(), 'stderr');
            }

            try {
                $this->application_deployment_queue->addLogEntry("Gracefully shutting down build container: {$this->deployment_uuid}");
                $this->graceful_shutdown_container($this->deployment_uuid, skipRemove: true);
            } catch (Exception $e) {
                // Log but don't fail - container cleanup errors are expected when container is already gone
                Log::warning('Failed to shutdown container '.$this->deployment_uuid.': '.$e->getMessage());
            }

            try {
                ServiceStatusChanged::dispatch(data_get($this->application, 'environment.project.team.id'));
            } catch (Exception $e) {
                // Log but don't fail - event dispatch errors shouldn't prevent status updates
                Log::warning('Failed to dispatch ServiceStatusChanged for deployment '.$this->deployment_uuid.': '.$e->getMessage());
            }
        }
    }

    // BuildKit detection moved to HandlesDeploymentConfiguration trait

    private function decide_what_to_do()
    {
        if ($this->restart_only) {
            $this->just_restart();

            return;
        } elseif ($this->pull_request_id !== 0) {
            $this->deploy_pull_request();
        } elseif ($this->application->dockerfile) {
            $this->deploy_simple_dockerfile();
        } elseif ($this->application->build_pack === 'dockercompose') {
            $this->deploy_docker_compose_buildpack();
        } elseif ($this->application->build_pack === 'dockerimage') {
            $this->deploy_dockerimage_buildpack();
        } elseif ($this->application->build_pack === 'dockerfile') {
            $this->deploy_dockerfile_buildpack();
        } elseif ($this->application->build_pack === 'static') {
            $this->deploy_static_buildpack();
        } else {
            $this->deploy_nixpacks_buildpack();
        }
        $this->post_deployment();
    }

    private function post_deployment()
    {
        // Mark deployment as complete FIRST, before any other operations
        // This ensures the deployment status is FINISHED even if subsequent operations fail
        $this->completeDeployment();

        // Then handle side effects - these should not fail the deployment
        try {
            GetContainersStatus::dispatch($this->server);
        } catch (\Exception $e) {
            Log::warning('Failed to dispatch GetContainersStatus for deployment '.$this->deployment_uuid.': '.$e->getMessage());
        }

        if ($this->pull_request_id !== 0) {
            if ($this->application->is_github_based()) {
                try {
                    ApplicationPullRequestUpdateJob::dispatch(application: $this->application, preview: $this->preview, deployment_uuid: $this->deployment_uuid, status: ProcessStatus::FINISHED);
                } catch (\Exception $e) {
                    Log::warning('Failed to dispatch PR update for deployment '.$this->deployment_uuid.': '.$e->getMessage());
                }
            }
        }

        try {
            $this->run_post_deployment_command();
        } catch (\Exception $e) {
            Log::warning('Post deployment command failed for '.$this->deployment_uuid.': '.$e->getMessage());
        }

        try {
            $this->application->isConfigurationChanged(true);
        } catch (\Exception $e) {
            Log::warning('Failed to mark configuration as changed for deployment '.$this->deployment_uuid.': '.$e->getMessage());
        }

        try {
            if (instanceSettings()->isCloudflareProtectionActive()) {
                SyncCloudflareRoutesJob::dispatch();
            }
        } catch (\Exception $e) {
            Log::warning('Cloudflare sync failed for deployment '.$this->deployment_uuid.': '.$e->getMessage());
        }
    }

    private function deploy_simple_dockerfile()
    {
        if ($this->use_build_server) {
            $this->server = $this->build_server;
        }
        $dockerfile_base64 = base64_encode($this->application->dockerfile);
        $this->application_deployment_queue->addLogEntry("Starting deployment of {$this->application->name} to {$this->server->name}.");
        $this->prepare_builder_image();
        $this->execute_remote_command(
            [
                executeInDocker($this->deployment_uuid, "echo '$dockerfile_base64' | base64 -d | tee {$this->workdir}{$this->dockerfile_location} > /dev/null"),
            ],
        );
        $this->generate_image_names();
        $this->generate_compose_file();

        // Save build-time .env file BEFORE the build
        $this->save_buildtime_environment_variables();

        $this->generate_build_env_variables();
        $this->add_build_env_variables_to_dockerfile();
        $this->build_image();

        // Save runtime environment variables AFTER the build
        // This overwrites the build-time .env with ALL variables (build-time + runtime)
        $this->save_runtime_environment_variables();

        $this->push_to_docker_registry();
        $this->rolling_update();
    }

    private function deploy_dockerimage_buildpack()
    {
        $this->dockerImage = $this->application->docker_registry_image_name;
        if (str($this->application->docker_registry_image_tag)->isEmpty()) {
            $this->dockerImageTag = 'latest';
        } else {
            $this->dockerImageTag = $this->application->docker_registry_image_tag;
        }

        // Check if this is an image hash deployment
        $isImageHash = str($this->dockerImageTag)->startsWith('sha256-');
        $displayName = $isImageHash ? "{$this->dockerImage}@sha256:".str($this->dockerImageTag)->after('sha256-') : "{$this->dockerImage}:{$this->dockerImageTag}";

        $this->application_deployment_queue->addLogEntry("Starting deployment of {$displayName} to {$this->server->name}.");
        $this->generate_image_names();
        $this->prepare_builder_image();
        $this->generate_compose_file();

        // Save runtime environment variables (including empty .env file if no variables defined)
        $this->save_runtime_environment_variables();

        $this->rolling_update();
    }

    private function deploy_dockerfile_buildpack()
    {
        $this->application_deployment_queue->addLogEntry("Starting deployment of {$this->customRepository}:{$this->application->git_branch} to {$this->server->name}.");
        if ($this->use_build_server) {
            $this->server = $this->build_server;
        }
        if (data_get($this->application, 'dockerfile_location')) {
            $this->dockerfile_location = $this->normalizeDockerfileLocation($this->application->dockerfile_location);
        }
        $this->prepare_builder_image();
        $this->check_git_if_build_needed();
        $this->generate_image_names();
        $this->clone_repository();
        if (! $this->force_rebuild) {
            $this->check_image_locally_or_remotely();
            if ($this->should_skip_build()) {
                return;
            }
        }
        $this->cleanup_git();
        $this->generate_compose_file();

        // Save build-time .env file BEFORE the build
        $this->save_buildtime_environment_variables();

        $this->generate_build_env_variables();
        $this->add_build_env_variables_to_dockerfile();
        $this->build_image();

        // Save runtime environment variables AFTER the build
        // This overwrites the build-time .env with ALL variables (build-time + runtime)
        $this->save_runtime_environment_variables();

        $this->push_to_docker_registry();
        $this->rolling_update();
    }

    private function deploy_static_buildpack()
    {
        if ($this->use_build_server) {
            $this->server = $this->build_server;
        }
        $this->application_deployment_queue->addLogEntry("Starting deployment of {$this->customRepository}:{$this->application->git_branch} to {$this->server->name}.");
        $this->prepare_builder_image();
        $this->check_git_if_build_needed();
        $this->generate_image_names();
        if (! $this->force_rebuild) {
            $this->check_image_locally_or_remotely();
            if ($this->should_skip_build()) {
                return;
            }
        }
        $this->clone_repository();
        $this->cleanup_git();
        $this->generate_compose_file();

        // Save build-time .env file BEFORE the build
        $this->save_buildtime_environment_variables();

        $this->build_static_image();

        // Save runtime environment variables AFTER the build
        // This overwrites the build-time .env with ALL variables (build-time + runtime)
        $this->save_runtime_environment_variables();

        $this->push_to_docker_registry();
        $this->rolling_update();
    }

    // write_deployment_configurations moved to HandlesDeploymentConfiguration trait

    private function just_restart()
    {
        $this->application_deployment_queue->addLogEntry("Restarting {$this->customRepository}:{$this->application->git_branch} on {$this->server->name}.");
        $this->prepare_builder_image();
        $this->check_git_if_build_needed();
        $this->generate_image_names();
        $this->check_image_locally_or_remotely();
        $this->should_skip_build();
        $this->completeDeployment();
    }

    private function should_skip_build()
    {
        if (str($this->saved_outputs->get('local_image_found'))->isNotEmpty()) {
            if ($this->is_this_additional_server) {
                $this->application_deployment_queue->addLogEntry("Image found ({$this->production_image_name}) with the same Git Commit SHA. Build step skipped.");
                $this->generate_compose_file();

                // Save runtime environment variables even when skipping build
                $this->save_runtime_environment_variables();

                $this->push_to_docker_registry();
                $this->rolling_update();

                return true;
            }
            if (! $this->application->isConfigurationChanged()) {
                $this->application_deployment_queue->addLogEntry("No configuration changed & image found ({$this->production_image_name}) with the same Git Commit SHA. Build step skipped.");
                $this->generate_compose_file();

                // Save runtime environment variables even when skipping build
                $this->save_runtime_environment_variables();

                $this->push_to_docker_registry();
                $this->rolling_update();

                return true;
            } else {
                $this->application_deployment_queue->addLogEntry('Configuration changed. Rebuilding image.');
            }
        } else {
            $this->application_deployment_queue->addLogEntry("Image not found ({$this->production_image_name}). Building new image.");
        }
        if ($this->restart_only) {
            $this->restart_only = false;
            $this->decide_what_to_do();
        }

        return false;
    }

    private function save_buildtime_environment_variables()
    {
        // Auto-inject DATABASE_URL from linked databases before generating env vars
        $this->injectLinkedDatabaseUrls();

        // Generate build-time environment variables locally
        $environment_variables = $this->generate_buildtime_environment_variables();

        // Save .env file for build phase in /artifacts to prevent it from being copied into Docker images
        if ($environment_variables->isNotEmpty()) {
            $envs_base64 = base64_encode($environment_variables->implode("\n"));

            $this->application_deployment_queue->addLogEntry('Creating build-time .env file in /artifacts (outside Docker context).', hidden: true);

            $this->execute_remote_command(
                [
                    executeInDocker($this->deployment_uuid, "echo '$envs_base64' | base64 -d | tee ".self::BUILD_TIME_ENV_PATH.' > /dev/null'),
                ],
                [
                    executeInDocker($this->deployment_uuid, 'cat '.self::BUILD_TIME_ENV_PATH),
                    'hidden' => true,
                ],
            );
        } elseif ($this->build_pack === 'dockercompose' || $this->build_pack === 'dockerfile') {
            // For Docker Compose and Dockerfile, create an empty .env file even if there are no build-time variables
            // This ensures the file exists when referenced in build commands
            $this->application_deployment_queue->addLogEntry('Creating empty build-time .env file in /artifacts (no build-time variables defined).', hidden: true);

            $this->execute_remote_command(
                [
                    executeInDocker($this->deployment_uuid, 'touch '.self::BUILD_TIME_ENV_PATH),
                ]
            );
        }
    }

    /**
     * Inject DATABASE_URL and other connection strings from linked databases.
     * This uses ResourceLinks to determine which databases to connect.
     */
    private function injectLinkedDatabaseUrls(): void
    {
        if (! $this->application->auto_inject_database_url) {
            return;
        }

        // Get count of linked databases for this application
        $linksCount = \App\Models\ResourceLink::where('source_type', \App\Models\Application::class)
            ->where('source_id', $this->application->id)
            ->where('auto_inject', true)
            ->count();

        if ($linksCount === 0) {
            return;
        }

        $this->application_deployment_queue->addLogEntry("Auto-injecting connection URLs from {$linksCount} linked database(s)...");

        // Call the model method which handles the actual injection
        $this->application->autoInjectDatabaseUrl();

        // Refresh the application to ensure we have the latest env vars
        $this->application->refresh();

        $this->application_deployment_queue->addLogEntry('Database connection URLs injected successfully.');
    }

    /**
     * Auto-detect port from Nixpacks plan variables.
     * Only updates ports_exposes if it's empty or set to default '80'.
     */
    private function deploy_pull_request()
    {
        if ($this->application->build_pack === 'dockercompose') {
            $this->deploy_docker_compose_buildpack();

            return;
        }
        if ($this->use_build_server) {
            $this->server = $this->build_server;
        }
        $this->newVersionIsHealthy = true;
        $this->generate_image_names();
        $this->application_deployment_queue->addLogEntry("Starting pull request (#{$this->pull_request_id}) deployment of {$this->customRepository}:{$this->application->git_branch}.");
        $this->prepare_builder_image();
        $this->check_git_if_build_needed();
        $this->clone_repository();
        $this->cleanup_git();
        if ($this->application->build_pack === 'nixpacks') {
            $this->generate_nixpacks_confs();
            $this->autoDetectPortFromNixpacks();
        }
        $this->generate_compose_file();

        // Save build-time .env file BEFORE the build
        $this->save_buildtime_environment_variables();

        $this->generate_build_env_variables();
        if ($this->application->build_pack === 'dockerfile') {
            $this->add_build_env_variables_to_dockerfile();
        }
        $this->build_image();

        // This overwrites the build-time .env with ALL variables (build-time + runtime)
        $this->save_runtime_environment_variables();
        $this->push_to_docker_registry();
        $this->rolling_update();
    }

    /**
     * Strip base_directory prefix from dockerfile_location to prevent path doubling.
     * E.g. base_directory="/backend" + dockerfile_location="/backend/Dockerfile" → "/Dockerfile"
     */
    private function normalizeDockerfileLocation(string $dockerfileLocation): string
    {
        $baseDir = rtrim($this->application->base_directory, '/');

        if ($baseDir && $baseDir !== '/' && str_starts_with($dockerfileLocation, $baseDir.'/')) {
            $normalized = str_replace($baseDir, '', $dockerfileLocation);
            $this->application_deployment_queue->addLogEntry(
                "Normalized dockerfile_location: '{$dockerfileLocation}' → '{$normalized}' (base_directory '{$baseDir}' prefix stripped to avoid path doubling)."
            );

            return $normalized;
        }

        return $dockerfileLocation;
    }

    private function create_workdir()
    {
        if ($this->use_build_server) {
            $this->server = $this->original_server;
            $this->execute_remote_command(
                [
                    'command' => "mkdir -p {$this->configuration_dir}",
                ],
            );
            $this->server = $this->build_server;
            $this->execute_remote_command(
                [
                    'command' => executeInDocker($this->deployment_uuid, "mkdir -p {$this->workdir}"),
                ],
                [
                    'command' => "mkdir -p {$this->configuration_dir}",
                ],
            );
        } else {
            $this->execute_remote_command(
                [
                    'command' => executeInDocker($this->deployment_uuid, "mkdir -p {$this->workdir}"),
                ],
                [
                    'command' => "mkdir -p {$this->configuration_dir}",
                ],
            );
        }
    }

    private function prepare_builder_image(bool $firstTry = true)
    {
        $this->application_deployment_queue->setStage(ApplicationDeploymentQueue::STAGE_PREPARE);
        $this->checkForCancellation();
        $helperImage = config('constants.saturn.helper_image');
        $helperImage = "{$helperImage}:".getHelperVersion();
        // Get user home directory
        $this->serverUserHomeDir = instant_remote_process(['echo $HOME'], $this->server);
        $this->dockerConfigFileExists = instant_remote_process(["test -f {$this->serverUserHomeDir}/.docker/config.json && echo 'OK' || echo 'NOK'"], $this->server);

        $env_flags = $this->generate_docker_env_flags_for_secrets();
        if ($this->use_build_server) {
            if ($this->dockerConfigFileExists === 'NOK') {
                throw new DeploymentException('Docker config file (~/.docker/config.json) not found on the build server. Please run "docker login" to login to the docker registry on the server.');
            }
            $runCommand = "docker run -d --name {$this->deployment_uuid} {$env_flags} --rm -v {$this->serverUserHomeDir}/.docker/config.json:/root/.docker/config.json:ro -v /var/run/docker.sock:/var/run/docker.sock {$helperImage}";
        } else {
            if ($this->dockerConfigFileExists === 'OK') {
                $runCommand = "docker run -d --network {$this->destination->network} --name {$this->deployment_uuid} {$env_flags} --rm -v {$this->serverUserHomeDir}/.docker/config.json:/root/.docker/config.json:ro -v /var/run/docker.sock:/var/run/docker.sock {$helperImage}";
            } else {
                $runCommand = "docker run -d --network {$this->destination->network} --name {$this->deployment_uuid} {$env_flags} --rm -v /var/run/docker.sock:/var/run/docker.sock {$helperImage}";
            }
        }
        if ($firstTry) {
            $this->application_deployment_queue->addLogEntry("Preparing container with helper image: $helperImage");
        } else {
            $this->application_deployment_queue->addLogEntry('Preparing container with helper image with updated envs.');
        }

        $this->graceful_shutdown_container($this->deployment_uuid, skipRemove: true);
        $this->execute_remote_command(
            [
                $runCommand,
                'hidden' => true,
            ],
            [
                'command' => executeInDocker($this->deployment_uuid, "mkdir -p {$this->basedir}"),
            ],
        );
        $this->run_pre_deployment_command();
    }

    private function restart_builder_container_with_actual_commit()
    {
        // Stop the current helper container (no need for rm -f as it was started with --rm)
        $this->graceful_shutdown_container($this->deployment_uuid, skipRemove: true);

        // Clear cached env_args to force regeneration with actual SOURCE_COMMIT value
        $this->env_args = null;

        // Restart the helper container with updated environment variables (including actual SOURCE_COMMIT)
        $this->prepare_builder_image(firstTry: false);
    }

    private function deploy_to_additional_destinations()
    {
        if ($this->application->additional_networks->count() === 0) {
            return;
        }
        if ($this->pull_request_id !== 0) {
            return;
        }
        $destination_ids = $this->application->additional_networks->pluck('id');
        if ($this->server->isSwarm()) {
            $this->application_deployment_queue->addLogEntry('Additional destinations are not supported in swarm mode.');

            return;
        }
        if ($destination_ids->contains($this->destination->id)) {
            return;
        }
        foreach ($destination_ids as $destination_id) {
            $destination = StandaloneDocker::find($destination_id);
            if (! $destination) {
                continue;
            }
            $server = $destination->server;
            if ($server->team_id !== $this->mainServer->team_id) {
                $this->application_deployment_queue->addLogEntry("Skipping deployment to {$server->name}. Not in the same team?!");

                continue;
            }
            $deployment_uuid = new Cuid2;
            queue_application_deployment(
                deployment_uuid: $deployment_uuid,
                application: $this->application,
                server: $server,
                destination: $destination,
                no_questions_asked: true,
            );
            $this->application_deployment_queue->addLogEntry("Deployment to {$server->name}. Logs: ".route('project.application.deployment.show', [
                'project_uuid' => data_get($this->application, 'environment.project.uuid'),
                'application_uuid' => data_get($this->application, 'uuid'),
                'deployment_uuid' => $deployment_uuid,
                'environment_uuid' => data_get($this->application, 'environment.uuid'),
            ]));
        }
    }

    private function generate_env_variables()
    {
        $this->env_args = collect([]);

        // Only include SOURCE_COMMIT in build args if enabled in settings
        if ($this->application->settings->include_source_commit_in_build) {
            $this->env_args->put('SOURCE_COMMIT', $this->commit);
        }

        $saturn_envs = $this->generate_saturn_env_variables(forBuildTime: true);
        $saturn_envs->each(function ($value, $key) {
            $this->env_args->put($key, $value);
        });

        // For build process, include only environment variables where is_buildtime = true
        if ($this->pull_request_id === 0) {
            $envs = $this->application->environment_variables()
                ->where('key', 'not like', 'NIXPACKS_%')
                ->where('is_buildtime', true)
                ->get();

            foreach ($envs as $env) {
                $this->env_args->put($env->key, $env->real_value);
            }
        } else {
            $envs = $this->application->environment_variables_preview()
                ->where('key', 'not like', 'NIXPACKS_%')
                ->where('is_buildtime', true)
                ->get();

            foreach ($envs as $env) {
                $this->env_args->put($env->key, $env->real_value);
            }
        }
    }

    private function generate_local_persistent_volumes()
    {
        $local_persistent_volumes = [];
        foreach ($this->application->persistentStorages as $persistentStorage) {
            if ($persistentStorage->host_path !== '' && $persistentStorage->host_path !== null) {
                $volume_name = $persistentStorage->host_path;
            } else {
                $volume_name = $persistentStorage->name;
            }
            if ($this->pull_request_id !== 0) {
                $volume_name = addPreviewDeploymentSuffix($volume_name, $this->pull_request_id);
            }
            $local_persistent_volumes[] = $volume_name.':'.$persistentStorage->mount_path;
        }

        return $local_persistent_volumes;
    }

    private function generate_local_persistent_volumes_only_volume_names()
    {
        $local_persistent_volumes_names = [];
        foreach ($this->application->persistentStorages as $persistentStorage) {
            if ($persistentStorage->host_path) {
                continue;
            }
            $name = $persistentStorage->name;

            if ($this->pull_request_id !== 0) {
                $name = addPreviewDeploymentSuffix($name, $this->pull_request_id);
            }

            $local_persistent_volumes_names[$name] = [
                'name' => $name,
                'external' => false,
            ];
        }

        return $local_persistent_volumes_names;
    }

    private function generate_healthcheck_commands()
    {
        if (! $this->application->health_check_port) {
            $health_check_port = $this->application->ports_exposes_array[0];
        } else {
            $health_check_port = $this->application->health_check_port;
        }
        if ($this->application->settings->is_static || $this->application->build_pack === 'static') {
            $health_check_port = 80;
        }

        $host = $this->application->health_check_host;
        $scheme = $this->application->health_check_scheme;
        $method = $this->application->health_check_method;
        $path = $this->application->health_check_path ?: '/';

        $this->full_healthcheck_url = "{$method}: {$scheme}://{$host}:{$health_check_port}{$path}";
        $url = "{$scheme}://{$host}:{$health_check_port}{$path}";

        // Fallback chain: curl (HTTP) → wget (HTTP) → nc (TCP) → bash /dev/tcp (TCP)
        // CMD-SHELL uses /bin/sh, so /dev/tcp (bash-only) must be wrapped in explicit bash -c
        $curlCmd = "curl -s -X {$method} -f {$url} > /dev/null 2>&1";
        $wgetCmd = "wget -q -O- {$url} > /dev/null 2>&1";
        $ncCmd = "nc -w5 -z {$host} {$health_check_port} 2>/dev/null";
        $bashTcpCmd = "bash -c 'echo > /dev/tcp/{$host}/{$health_check_port}' 2>/dev/null";

        return "{$curlCmd} || {$wgetCmd} || {$ncCmd} || {$bashTcpCmd} || exit 1";
    }

    // Static buildpack methods moved to HandlesStaticBuildpack trait

    /**
     * Wrap a docker build command with environment export from build-time .env file
     * This enables shell interpolation of variables (e.g., APP_URL=$SATURN_URL)
     *
     * @param  string  $build_command  The docker build command to wrap
     * @return string The wrapped command with export statement
     */
    private function wrap_build_command_with_env_export(string $build_command): string
    {
        return "cd {$this->workdir} && set -a && source ".self::BUILD_TIME_ENV_PATH." && set +a && {$build_command}";
    }

    // Image building moved to HandlesImageBuilding trait

    // Container operations moved to HandlesContainerOperations trait

    private function analyzeBuildTimeVariables($variables)
    {
        $userDefinedVariables = collect([]);

        $dbVariables = $this->pull_request_id === 0
            ? $this->application->environment_variables()
                ->where('is_buildtime', true)
                ->pluck('key')
            : $this->application->environment_variables_preview()
                ->where('is_buildtime', true)
                ->pluck('key');

        foreach ($variables as $key => $value) {
            if ($dbVariables->contains($key)) {
                $userDefinedVariables->put($key, $value);
            }
        }

        if ($userDefinedVariables->isEmpty()) {
            return;
        }

        $variablesArray = $userDefinedVariables->toArray();
        $warnings = self::analyzeBuildVariables($variablesArray);

        if (empty($warnings)) {
            return;
        }
        $this->application_deployment_queue->addLogEntry('----------------------------------------');
        foreach ($warnings as $warning) {
            $messages = self::formatBuildWarning($warning);
            foreach ($messages as $message) {
                $this->application_deployment_queue->addLogEntry($message, type: 'warning');
            }
            $this->application_deployment_queue->addLogEntry('');
        }

        // Add general advice
        $this->application_deployment_queue->addLogEntry('💡 Tips to resolve build issues:', type: 'info');
        $this->application_deployment_queue->addLogEntry('   1. Set these variables as "Runtime only" in the environment variables settings', type: 'info');
        $this->application_deployment_queue->addLogEntry('   2. Use different values for build-time (e.g., NODE_ENV=development for build)', type: 'info');
        $this->application_deployment_queue->addLogEntry('   3. Consider using multi-stage Docker builds to separate build and runtime environments', type: 'info');
    }

    private function generate_build_env_variables()
    {
        if ($this->application->build_pack === 'nixpacks') {
            $variables = collect($this->nixpacks_plan_json->get('variables'));
        } else {
            $this->generate_env_variables();
            $variables = collect([])->merge($this->env_args);
        }
        // Analyze build variables for potential issues
        if ($variables->isNotEmpty()) {
            $this->analyzeBuildTimeVariables($variables);
        }

        if ($this->dockerBuildkitSupported && $this->application->settings->use_build_secrets) {
            $this->generate_build_secrets($variables);
            $this->build_args = '';
        } else {
            $secrets_hash = '';
            if ($variables->isNotEmpty()) {
                $secrets_hash = $this->generate_secrets_hash($variables);
            }

            $env_vars = $this->pull_request_id === 0
                ? $this->application->environment_variables()->where('is_buildtime', true)->get()
                : $this->application->environment_variables_preview()->where('is_buildtime', true)->get();

            // Map variables to include is_multiline flag
            $vars_with_metadata = $variables->map(function ($value, $key) use ($env_vars) {
                $env = $env_vars->firstWhere('key', $key);

                return [
                    'key' => $key,
                    'value' => $value,
                    'is_multiline' => $env ? $env->is_multiline : false,
                ];
            });

            $this->build_args = generateDockerBuildArgs($vars_with_metadata);

            if ($secrets_hash) {
                $this->build_args->push("--build-arg SATURN_BUILD_SECRETS_HASH={$secrets_hash}");
            }
        }
    }

    protected function findFromInstructionLines($dockerfile): array
    {
        $fromLines = [];
        foreach ($dockerfile as $index => $line) {
            $trimmedLine = trim($line);
            // Check if line starts with FROM (case-insensitive)
            if (preg_match('/^FROM\s+/i', $trimmedLine)) {
                $fromLines[] = $index;
            }
        }

        return $fromLines;
    }

    /**
     * Check if the deployment was cancelled and abort if it was
     */
    private function checkForCancellation(): void
    {
        $this->application_deployment_queue->refresh();
        if ($this->application_deployment_queue->status === ApplicationDeploymentStatus::CANCELLED_BY_USER->value) {
            $this->application_deployment_queue->addLogEntry('Deployment cancelled by user, stopping execution.');
            throw new DeploymentException('Deployment cancelled by user', 69420);
        }
    }

    // Status management methods moved to HandlesDeploymentStatus trait

    public function failed(Throwable $exception): void
    {
        $this->failDeployment();

        // Log comprehensive error information
        $errorMessage = $exception->getMessage() ?: 'Unknown error occurred';
        $errorCode = $exception->getCode();
        $errorClass = get_class($exception);

        $this->application_deployment_queue->addLogEntry('========================================', 'stderr');
        $this->application_deployment_queue->addLogEntry("Deployment failed: {$errorMessage}", 'stderr');
        $this->application_deployment_queue->addLogEntry("Error type: {$errorClass}", 'stderr', hidden: true);
        $this->application_deployment_queue->addLogEntry("Error code: {$errorCode}", 'stderr', hidden: true);

        // Log the exception file and line for debugging
        $this->application_deployment_queue->addLogEntry("Location: {$exception->getFile()}:{$exception->getLine()}", 'stderr', hidden: true);

        // Log previous exceptions if they exist (for chained exceptions)
        $previous = $exception->getPrevious();
        if ($previous) {
            $this->application_deployment_queue->addLogEntry('Caused by:', 'stderr', hidden: true);
            $previousMessage = $previous->getMessage() ?: 'No message';
            $previousClass = get_class($previous);
            $this->application_deployment_queue->addLogEntry("  {$previousClass}: {$previousMessage}", 'stderr', hidden: true);
            $this->application_deployment_queue->addLogEntry("  at {$previous->getFile()}:{$previous->getLine()}", 'stderr', hidden: true);
        }

        // Log first few lines of stack trace for debugging
        $trace = $exception->getTraceAsString();
        $traceLines = explode("\n", $trace);
        $this->application_deployment_queue->addLogEntry('Stack trace (first 5 lines):', 'stderr', hidden: true);
        foreach (array_slice($traceLines, 0, 5) as $traceLine) {
            $this->application_deployment_queue->addLogEntry("  {$traceLine}", 'stderr', hidden: true);
        }
        $this->application_deployment_queue->addLogEntry('========================================', 'stderr');

        if ($this->application->build_pack !== 'dockercompose') {
            $code = $exception->getCode();
            if ($code !== 69420) {
                // 69420 means failed to push the image to the registry, so we don't need to remove the new version as it is the currently running one
                if ($this->application->settings->is_consistent_container_name_enabled || str($this->application->settings->custom_internal_name)->isNotEmpty() || $this->pull_request_id !== 0) {
                    // do not remove already running container for PR deployments
                } else {
                    $this->application_deployment_queue->addLogEntry('Deployment failed. Removing the new version of your application.', 'stderr');
                    $this->execute_remote_command(
                        ['docker rm -f '.escapeshellarg($this->container_name).' >/dev/null 2>&1', 'hidden' => true, 'ignore_errors' => true]
                    );
                }
            }
        }

        // Update container status to reflect the failed deployment state
        try {
            GetContainersStatus::dispatch($this->server);
        } catch (\Throwable $e) {
            Log::warning('Failed to dispatch GetContainersStatus on failed deployment: '.$e->getMessage());
        }
    }
}
