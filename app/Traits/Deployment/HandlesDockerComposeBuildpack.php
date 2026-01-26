<?php

namespace App\Traits\Deployment;

use Symfony\Component\Yaml\Yaml;

/**
 * Trait for handling Docker Compose buildpack deployment.
 *
 * Required properties from parent class:
 * - $application, $application_deployment_queue, $server, $build_server
 * - $docker_compose_location, $docker_compose_custom_start_command, $docker_compose_custom_build_command
 * - $workdir, $basedir, $deployment_uuid, $pull_request_id, $preview
 * - $customRepository, $commit, $preserveRepository, $force_rebuild
 * - $saturn_variables, $build_args, $build_secrets, $dockerBuildkitSupported
 * - $docker_compose_base64, $saved_outputs
 *
 * Required methods from parent class:
 * - execute_remote_command(), prepare_builder_image()
 * - check_git_if_build_needed(), clone_repository(), cleanup_git()
 * - generate_image_names(), generate_build_env_variables()
 * - save_buildtime_environment_variables(), save_runtime_environment_variables()
 * - stop_running_container(), write_deployment_configurations()
 * - add_build_secrets_to_compose(), modify_dockerfiles_for_compose()
 */
trait HandlesDockerComposeBuildpack
{
    /**
     * Deploy using Docker Compose buildpack.
     */
    private function deploy_docker_compose_buildpack()
    {
        if (data_get($this->application, 'docker_compose_location')) {
            $this->docker_compose_location = $this->application->docker_compose_location;
        }
        if (data_get($this->application, 'docker_compose_custom_start_command')) {
            $this->docker_compose_custom_start_command = $this->application->docker_compose_custom_start_command;
            if (! str($this->docker_compose_custom_start_command)->contains('--project-directory')) {
                $this->docker_compose_custom_start_command = str($this->docker_compose_custom_start_command)->replaceFirst('compose', 'compose --project-directory '.$this->workdir)->value();
            }
        }
        if (data_get($this->application, 'docker_compose_custom_build_command')) {
            $this->docker_compose_custom_build_command = $this->application->docker_compose_custom_build_command;
            if (! str($this->docker_compose_custom_build_command)->contains('--project-directory')) {
                $this->docker_compose_custom_build_command = str($this->docker_compose_custom_build_command)->replaceFirst('compose', 'compose --project-directory '.$this->workdir)->value();
            }
        }
        if ($this->pull_request_id === 0) {
            $this->application_deployment_queue->addLogEntry("Starting deployment of {$this->application->name} to {$this->server->name}.");
        } else {
            $this->application_deployment_queue->addLogEntry("Starting pull request (#{$this->pull_request_id}) deployment of {$this->customRepository}:{$this->application->git_branch} to {$this->server->name}.");
        }
        $this->prepare_builder_image();
        $this->check_git_if_build_needed();
        $this->clone_repository();
        if ($this->preserveRepository) {
            foreach ($this->application->fileStorages as $fileStorage) {
                $path = $fileStorage->fs_path;
                $saveName = 'file_stat_'.$fileStorage->id;
                $realPathInGit = str($path)->replace($this->application->workdir(), $this->workdir)->value();
                // check if the file is a directory or a file inside the repository
                $this->execute_remote_command(
                    [executeInDocker($this->deployment_uuid, "stat -c '%F' {$realPathInGit}"), 'hidden' => true, 'ignore_errors' => true, 'save' => $saveName]
                );
                if ($this->saved_outputs->has($saveName)) {
                    $fileStat = $this->saved_outputs->get($saveName);
                    if ($fileStat->value() === 'directory' && ! $fileStorage->is_directory) {
                        $fileStorage->is_directory = true;
                        $fileStorage->content = null;
                        $fileStorage->save();
                        $fileStorage->deleteStorageOnServer();
                        $fileStorage->saveStorageOnServer();
                    } elseif ($fileStat->value() === 'regular file' && $fileStorage->is_directory) {
                        $fileStorage->is_directory = false;
                        $fileStorage->is_based_on_git = true;
                        $fileStorage->save();
                        $fileStorage->deleteStorageOnServer();
                        $fileStorage->saveStorageOnServer();
                    }
                }
            }
        }
        $this->generate_image_names();
        $this->cleanup_git();

        $this->generate_build_env_variables();

        $this->application->loadComposeFile(isInit: false);
        if ($this->application->settings->is_raw_compose_deployment_enabled) {
            $this->application->oldRawParser();
            $yaml = $composeFile = $this->application->docker_compose_raw;

            // For raw compose, we cannot automatically add secrets configuration
            // User must define it manually in their docker-compose file
            if ($this->application->settings->use_build_secrets && $this->dockerBuildkitSupported && ! empty($this->build_secrets)) {
                $this->application_deployment_queue->addLogEntry('Build secrets are configured. Ensure your docker-compose file includes build.secrets configuration for services that need them.');
            }
        } else {
            $composeFile = $this->application->parse(pull_request_id: $this->pull_request_id, preview_id: data_get($this->preview, 'id'), commit: $this->commit);
            // Always add .env file to services
            $services = collect(data_get($composeFile, 'services', []));
            $services = $services->map(function ($service, $name) {
                $service['env_file'] = ['.env'];

                return $service;
            });
            $composeFile['services'] = $services->toArray();
            if (empty($composeFile)) {
                $this->application_deployment_queue->addLogEntry('Failed to parse docker-compose file.');
                $this->fail('Failed to parse docker-compose file.');

                return;
            }

            // Add build secrets to compose file if enabled and BuildKit is supported
            if ($this->application->settings->use_build_secrets && $this->dockerBuildkitSupported && ! empty($this->build_secrets)) {
                $composeFile = $this->add_build_secrets_to_compose($composeFile);
            }

            $yaml = Yaml::dump(convertToArray($composeFile), 10);
        }
        $this->docker_compose_base64 = base64_encode($yaml);
        $this->execute_remote_command([
            executeInDocker($this->deployment_uuid, "echo '{$this->docker_compose_base64}' | base64 -d | tee {$this->workdir}{$this->docker_compose_location} > /dev/null"),
            'hidden' => true,
        ]);

        // Modify Dockerfiles for ARGs and build secrets
        $this->modify_dockerfiles_for_compose($composeFile);
        // Build new container to limit downtime.
        $this->application_deployment_queue->addLogEntry('Pulling & building required images.');

        // Save build-time .env file BEFORE the build
        $this->save_buildtime_environment_variables();

        if ($this->docker_compose_custom_build_command) {
            // Auto-inject -f (compose file) and --env-file flags using helper function
            $build_command = injectDockerComposeFlags(
                $this->docker_compose_custom_build_command,
                "{$this->workdir}{$this->docker_compose_location}",
                self::BUILD_TIME_ENV_PATH
            );

            // Prepend DOCKER_BUILDKIT=1 if BuildKit is supported
            if ($this->dockerBuildkitSupported) {
                $build_command = "DOCKER_BUILDKIT=1 {$build_command}";
            }

            // Inject build arguments after build subcommand if not using build secrets
            if (! $this->application->settings->use_build_secrets && $this->build_args instanceof \Illuminate\Support\Collection && $this->build_args->isNotEmpty()) {
                $build_args_string = $this->build_args->implode(' ');
                // Escape single quotes for bash -c context used by executeInDocker
                $build_args_string = str_replace("'", "'\\''", $build_args_string);

                // Inject build args right after 'build' subcommand (not at the end)
                $original_command = $build_command;
                $build_command = injectDockerComposeBuildArgs($build_command, $build_args_string);

                // Only log if build args were actually injected (command was modified)
                if ($build_command !== $original_command) {
                    $this->application_deployment_queue->addLogEntry('Adding build arguments to custom Docker Compose build command.');
                }
            }

            $this->execute_remote_command(
                [executeInDocker($this->deployment_uuid, "cd {$this->basedir} && {$build_command}"), 'hidden' => true],
            );
        } else {
            $command = "{$this->saturn_variables} docker compose";
            // Prepend DOCKER_BUILDKIT=1 if BuildKit is supported
            if ($this->dockerBuildkitSupported) {
                $command = "DOCKER_BUILDKIT=1 {$command}";
            }
            // Use build-time .env file from /artifacts (outside Docker context to prevent it from being in the image)
            $command .= ' --env-file '.self::BUILD_TIME_ENV_PATH;
            if ($this->force_rebuild) {
                $command .= " --project-name {$this->application->uuid} --project-directory {$this->workdir} -f {$this->workdir}{$this->docker_compose_location} build --pull --no-cache";
            } else {
                $command .= " --project-name {$this->application->uuid} --project-directory {$this->workdir} -f {$this->workdir}{$this->docker_compose_location} build --pull";
            }

            if (! $this->application->settings->use_build_secrets && $this->build_args instanceof \Illuminate\Support\Collection && $this->build_args->isNotEmpty()) {
                $build_args_string = $this->build_args->implode(' ');
                // Escape single quotes for bash -c context used by executeInDocker
                $build_args_string = str_replace("'", "'\\''", $build_args_string);
                $command .= " {$build_args_string}";
                $this->application_deployment_queue->addLogEntry('Adding build arguments to Docker Compose build command.');
            }

            $this->execute_remote_command(
                [executeInDocker($this->deployment_uuid, $command), 'hidden' => true],
            );
        }

        // Save runtime environment variables AFTER the build
        // This overwrites the build-time .env with ALL variables (build-time + runtime)
        $this->save_runtime_environment_variables();

        $this->stop_running_container(force: true);
        $this->application_deployment_queue->addLogEntry('Starting new application.');
        $networkId = $this->application->uuid;
        if ($this->pull_request_id !== 0) {
            $networkId = "{$this->application->uuid}-{$this->pull_request_id}";
        }
        if ($this->server->isSwarm()) {
            // TODO
        } else {
            $this->execute_remote_command([
                "docker network inspect '{$networkId}' >/dev/null 2>&1 || docker network create --attachable '{$networkId}' >/dev/null || true",
                'hidden' => true,
                'ignore_errors' => true,
            ], [
                "docker network connect {$networkId} saturn-proxy >/dev/null 2>&1 || true",
                'hidden' => true,
                'ignore_errors' => true,
            ]);
        }

        // Start compose file
        $server_workdir = $this->application->workdir();
        if ($this->application->settings->is_raw_compose_deployment_enabled) {
            if ($this->docker_compose_custom_start_command) {
                // Auto-inject -f (compose file) and --env-file flags using helper function
                $start_command = injectDockerComposeFlags(
                    $this->docker_compose_custom_start_command,
                    "{$server_workdir}{$this->docker_compose_location}",
                    "{$server_workdir}/.env"
                );

                $this->write_deployment_configurations();
                $this->execute_remote_command(
                    [executeInDocker($this->deployment_uuid, "cd {$this->workdir} && {$start_command}"), 'hidden' => true],
                );
            } else {
                $this->write_deployment_configurations();
                $this->docker_compose_location = '/docker-compose.yaml';

                $command = "{$this->saturn_variables} docker compose";
                // Always use .env file
                $command .= " --env-file {$server_workdir}/.env";
                $command .= " --project-directory {$server_workdir} -f {$server_workdir}{$this->docker_compose_location} up -d";
                $this->execute_remote_command(
                    ['command' => $command, 'hidden' => true],
                );
            }
        } else {
            if ($this->docker_compose_custom_start_command) {
                // Auto-inject -f (compose file) and --env-file flags using helper function
                // Use $this->workdir for non-preserve-repository mode
                $workdir_path = $this->preserveRepository ? $server_workdir : $this->workdir;
                $start_command = injectDockerComposeFlags(
                    $this->docker_compose_custom_start_command,
                    "{$workdir_path}{$this->docker_compose_location}",
                    "{$workdir_path}/.env"
                );

                $this->write_deployment_configurations();
                $this->execute_remote_command(
                    [executeInDocker($this->deployment_uuid, "cd {$this->basedir} && {$start_command}"), 'hidden' => true],
                );
            } else {
                $command = "{$this->saturn_variables} docker compose";
                if ($this->preserveRepository) {
                    // Always use .env file
                    $command .= " --env-file {$server_workdir}/.env";
                    $command .= " --project-name {$this->application->uuid} --project-directory {$server_workdir} -f {$server_workdir}{$this->docker_compose_location} up -d";
                    $this->write_deployment_configurations();

                    $this->execute_remote_command(
                        ['command' => $command, 'hidden' => true],
                    );
                } else {
                    // Always use .env file
                    $command .= " --env-file {$this->workdir}/.env";
                    $command .= " --project-name {$this->application->uuid} --project-directory {$this->workdir} -f {$this->workdir}{$this->docker_compose_location} up -d";
                    $this->execute_remote_command(
                        [executeInDocker($this->deployment_uuid, $command), 'hidden' => true],
                    );
                    $this->write_deployment_configurations();
                }
            }
        }

        $this->application_deployment_queue->addLogEntry('New container started.');
    }
}
