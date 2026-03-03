<?php

namespace App\Traits\Deployment;

use App\Exceptions\DeploymentException;

/**
 * Trait for Railpack buildpack deployment operations.
 *
 * Railpack is the successor to Nixpacks — zero-config builder using BuildKit.
 * It requires a running BuildKit daemon (buildkitd) which this trait manages automatically.
 *
 * Required properties from parent class:
 * - $application, $application_deployment_queue, $server, $deployment_uuid
 * - $use_build_server, $build_server, $original_server
 * - $customRepository, $force_rebuild, $pull_request_id
 * - $workdir, $docker_compose_location, $saved_outputs
 * - $env_args, $production_image_name
 *
 * Required methods from parent class:
 * - execute_remote_command(), checkForCancellation()
 * - prepare_builder_image(), check_git_if_build_needed(), generate_image_names()
 * - check_image_locally_or_remotely(), should_skip_build()
 * - clone_repository(), cleanup_git(), generate_compose_file()
 * - save_buildtime_environment_variables(), generate_build_env_variables()
 * - save_runtime_environment_variables()
 * - push_to_docker_registry(), rolling_update()
 * - generate_env_variables(), generate_saturn_env_variables()
 * - autoDetectAndSwitchToDockerfile() (from HandlesNixpacksBuildpack)
 */
trait HandlesRailpackBuildpack
{
    private string $railpack_type = '';

    private ?array $railpack_plan_json = null;

    private string $env_railpack_args = '';

    /**
     * Deploy using Railpack buildpack.
     */
    private function deploy_railpack_buildpack(): void
    {
        if ($this->use_build_server) {
            $this->server = $this->build_server;
        }
        $this->application_deployment_queue->addLogEntry("Starting Railpack deployment of {$this->customRepository}:{$this->application->git_branch} to {$this->server->name}.");
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

        // Auto-detect Dockerfile: if one exists and user hasn't explicitly set build pack,
        // automatically switch to the dockerfile buildpack.
        if ($this->autoDetectAndSwitchToDockerfile()) {
            return;
        }

        $this->ensure_railpack_installed();
        $this->ensure_buildkitd_running();
        $this->generate_railpack_plan();
        $this->autoDetectPortFromRailpack();
        $this->generate_compose_file();

        $this->save_buildtime_environment_variables();
        $this->generate_build_env_variables();
        $this->build_railpack_image();

        $this->save_runtime_environment_variables();
        $this->push_to_docker_registry();
        $this->rolling_update();
    }

    /**
     * Ensure railpack CLI is installed on the server.
     * Downloads from railpack.com if not present.
     */
    private function ensure_railpack_installed(): void
    {
        $this->execute_remote_command(
            [executeInDocker($this->deployment_uuid, 'command -v railpack >/dev/null 2>&1 && echo "installed" || echo "missing"'), 'save' => 'railpack_check', 'hidden' => true],
        );

        if (trim($this->saved_outputs->get('railpack_check', '')) === 'missing') {
            $this->application_deployment_queue->addLogEntry('Railpack not found. Installing...');
            $this->execute_remote_command(
                [executeInDocker($this->deployment_uuid, 'curl -sSL https://railpack.com/install.sh | sh'), 'hidden' => true],
            );
            $this->application_deployment_queue->addLogEntry('Railpack installed successfully.');
        } else {
            $this->execute_remote_command(
                [executeInDocker($this->deployment_uuid, 'railpack --version'), 'save' => 'railpack_version', 'hidden' => true],
            );
            $version = trim($this->saved_outputs->get('railpack_version', ''));
            $this->application_deployment_queue->addLogEntry("Using Railpack {$version}.");
        }
    }

    /**
     * Ensure buildkitd container is running on the host.
     * Starts a persistent buildkitd container if not present.
     */
    private function ensure_buildkitd_running(): void
    {
        $this->execute_remote_command(
            [executeInDocker($this->deployment_uuid, 'docker inspect buildkitd >/dev/null 2>&1 && echo "running" || echo "missing"'), 'save' => 'buildkitd_check', 'hidden' => true],
        );

        if (trim($this->saved_outputs->get('buildkitd_check', '')) === 'missing') {
            $this->application_deployment_queue->addLogEntry('Starting BuildKit daemon...');
            $this->execute_remote_command(
                [executeInDocker($this->deployment_uuid, 'docker run -d --restart=unless-stopped --privileged --name buildkitd moby/buildkit'), 'hidden' => true],
            );
            // Brief wait for buildkitd to be ready
            $this->execute_remote_command(
                [executeInDocker($this->deployment_uuid, 'sleep 3'), 'hidden' => true],
            );
            $this->application_deployment_queue->addLogEntry('BuildKit daemon started.');
        }
    }

    /**
     * Generate Railpack build plan JSON.
     * Uses `railpack prepare` to analyze the project and detect app type/port/config.
     */
    private function generate_railpack_plan(): void
    {
        $this->generate_railpack_env_variables();

        $prepare_cmd = "railpack prepare {$this->env_railpack_args} --plan-out ".self::RAILPACK_PLAN_PATH." {$this->workdir}";

        $this->application_deployment_queue->addLogEntry("Analyzing project with Railpack: {$prepare_cmd}");

        $this->execute_remote_command(
            [executeInDocker($this->deployment_uuid, $prepare_cmd), 'save' => 'railpack_prepare_output', 'hidden' => true],
        );

        // Read and parse the generated plan
        $this->execute_remote_command(
            [executeInDocker($this->deployment_uuid, 'cat '.self::RAILPACK_PLAN_PATH.' 2>/dev/null || echo ""'), 'save' => 'railpack_plan_content', 'hidden' => true],
        );

        $planContent = trim($this->saved_outputs->get('railpack_plan_content', ''));

        if (empty($planContent)) {
            throw new DeploymentException('Railpack failed to generate a build plan. Please check your project structure and Railpack documentation at https://railpack.com/getting-started');
        }

        $parsed = json_decode($planContent, true);

        if (! $parsed) {
            throw new DeploymentException('Railpack plan is not valid JSON. Please check the Railpack documentation at https://railpack.com/getting-started');
        }

        $this->railpack_plan_json = $parsed;

        // Detect application type from plan
        $this->railpack_type = data_get($parsed, 'provider', data_get($parsed, 'language', 'unknown'));

        $this->application_deployment_queue->addLogEntry("Railpack detected application type: {$this->railpack_type}.");
        $this->application_deployment_queue->addLogEntry('For customization, add a railpack.json to your project root. See https://railpack.com/config/file');
        $this->application_deployment_queue->addLogEntry('Railpack plan saved to '.self::RAILPACK_PLAN_PATH, hidden: true);
    }

    /**
     * Auto-detect application port from Railpack plan or environment variables.
     */
    private function autoDetectPortFromRailpack(): void
    {
        $currentPort = $this->application->ports_exposes;
        if (! empty($currentPort) && $currentPort !== '80') {
            return;
        }

        // Check PORT from environment variables first
        $envPort = $this->application->detectPortFromEnvironment($this->pull_request_id !== 0);
        if ($envPort) {
            $this->application->update(['ports_exposes' => (string) $envPort]);
            $this->application_deployment_queue->addLogEntry("Auto-detected port from environment: {$envPort}");

            return;
        }

        // Try to get PORT from Railpack plan
        if ($this->railpack_plan_json) {
            $portFromPlan = data_get($this->railpack_plan_json, 'deploy.variables.PORT');
            if (! $portFromPlan) {
                $portFromPlan = data_get($this->railpack_plan_json, 'variables.PORT');
            }
            if (is_numeric($portFromPlan)) {
                $this->application->update(['ports_exposes' => (string) $portFromPlan]);
                $this->application_deployment_queue->addLogEntry("Auto-detected port from Railpack plan: {$portFromPlan}");
            }
        }
    }

    /**
     * Build the Docker image using Railpack via BuildKit.
     */
    private function build_railpack_image(): void
    {
        $this->application_deployment_queue->addLogEntry('----------------------------------------');
        $this->application_deployment_queue->addLogEntry('Building Docker image with Railpack started.');

        $build_cmd = $this->railpack_build_cmd();

        $this->application_deployment_queue->addLogEntry("Running: {$build_cmd}", hidden: true);

        $this->execute_remote_command(
            [executeInDocker($this->deployment_uuid, $build_cmd), 'hidden' => false],
        );

        // Clean up plan file
        $this->execute_remote_command(
            [executeInDocker($this->deployment_uuid, 'rm -f '.self::RAILPACK_PLAN_PATH), 'hidden' => true],
        );

        $this->application_deployment_queue->addLogEntry('Building Docker image with Railpack completed.');
    }

    /**
     * Construct the railpack build command.
     */
    private function railpack_build_cmd(): string
    {
        $no_cache = $this->force_rebuild ? '--no-cache ' : '';

        $cmd = "BUILDKIT_HOST=docker-container://buildkitd railpack build {$no_cache}--name {$this->production_image_name} {$this->workdir}";

        return $cmd;
    }

    /**
     * Generate Railpack environment variable arguments.
     * Passes RAILPACK_* prefixed env vars and SATURN_* vars to the build.
     */
    private function generate_railpack_env_variables(): void
    {
        $args = collect([]);

        // Pass RAILPACK_* environment variables (build-time configuration, e.g. RAILPACK_PYTHON_VERSION)
        if ($this->pull_request_id === 0) {
            $railpackEnvs = $this->application->railpack_environment_variables;
        } else {
            $railpackEnvs = $this->application->railpack_environment_variables_preview;
        }

        foreach ($railpackEnvs as $env) {
            if (! is_null($env->real_value) && $env->real_value !== '') {
                $args->push('--env '.escapeshellarg("{$env->key}={$env->real_value}"));
            }
        }

        // Add SATURN_* environment variables to Railpack build context
        $saturn_envs = $this->generate_saturn_env_variables(forBuildTime: true);
        $saturn_envs->each(function ($value, $key) use ($args) {
            if (! is_null($value) && $value !== '') {
                $args->push('--env '.escapeshellarg("{$key}={$value}"));
            }
        });

        $this->env_railpack_args = $args->implode(' ');
    }
}
