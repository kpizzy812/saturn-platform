<?php

namespace App\Traits\Deployment;

use App\Exceptions\DeploymentException;
use Exception;

/**
 * Trait for handling container lifecycle operations during deployment.
 *
 * Required properties from parent class:
 * - $application, $application_deployment_queue, $server
 * - $container_name, $pull_request_id, $deployment_uuid
 * - $newVersionIsHealthy, $configuration_dir, $workdir
 * - $saturn_variables, $docker_compose_location
 * - $use_build_server
 *
 * Required methods from parent class:
 * - execute_remote_command(), failDeployment()
 */
trait HandlesContainerOperations
{
    /**
     * Gracefully stop and optionally remove a container.
     */
    private function graceful_shutdown_container(string $containerName, bool $skipRemove = false): void
    {
        try {
            $timeout = isDev() ? 1 : 30;
            if ($skipRemove) {
                $this->execute_remote_command(
                    ["docker stop -t $timeout $containerName", 'hidden' => true, 'ignore_errors' => true]
                );
            } else {
                $this->execute_remote_command(
                    ["docker stop -t $timeout $containerName", 'hidden' => true, 'ignore_errors' => true],
                    ["docker rm -f $containerName", 'hidden' => true, 'ignore_errors' => true]
                );
            }
        } catch (Exception $error) {
            $this->application_deployment_queue->addLogEntry("Error stopping container $containerName: ".$error->getMessage(), 'stderr');
        }
    }

    /**
     * Stop the currently running container(s).
     */
    private function stop_running_container(bool $force = false): void
    {
        try {
            $this->application_deployment_queue->addLogEntry('Removing old containers.');
            if ($this->newVersionIsHealthy || $force) {
                if ($this->application->settings->is_consistent_container_name_enabled || str($this->application->settings->custom_internal_name)->isNotEmpty()) {
                    $this->graceful_shutdown_container($this->container_name);
                } else {
                    $containers = getCurrentApplicationContainerStatus($this->server, $this->application->id, $this->pull_request_id);
                    if ($this->pull_request_id === 0) {
                        $containers = $containers->filter(function ($container) {
                            return data_get($container, 'Names') !== $this->container_name && data_get($container, 'Names') !== addPreviewDeploymentSuffix($this->container_name, $this->pull_request_id);
                        });
                    }
                    $containers->each(function ($container) {
                        $this->graceful_shutdown_container(data_get($container, 'Names'));
                    });
                }
            } else {
                if ($this->application->dockerfile || $this->application->build_pack === 'dockerfile' || $this->application->build_pack === 'dockerimage') {
                    $this->application_deployment_queue->addLogEntry('----------------------------------------');
                    $this->application_deployment_queue->addLogEntry("WARNING: Dockerfile or Docker Image based deployment detected. The healthcheck needs a curl or wget command to check the health of the application. Please make sure that it is available in the image or turn off healthcheck on Saturn Platform's UI.");
                    $this->application_deployment_queue->addLogEntry('----------------------------------------');
                }
                $this->application_deployment_queue->addLogEntry('New container is not healthy, rolling back to the old container.');
                $this->failDeployment();
                $this->graceful_shutdown_container($this->container_name);
            }
        } catch (Exception $e) {
            // If new version is healthy, this is just cleanup - don't fail the deployment
            if ($this->newVersionIsHealthy || $force) {
                $this->application_deployment_queue->addLogEntry(
                    "Warning: Could not remove old container: {$e->getMessage()}",
                    'stderr',
                    hidden: true
                );

                return; // Don't re-throw - cleanup failures shouldn't fail successful deployments
            }

            // Only re-throw if deployment hasn't succeeded yet
            throw new DeploymentException("Failed to stop running container: {$e->getMessage()}", $e->getCode(), $e);
        }
    }

    /**
     * Start container(s) using docker compose.
     */
    private function start_by_compose_file(): void
    {
        try {
            // Ensure .env file exists before docker compose tries to load it (defensive programming)
            $this->execute_remote_command(
                ["touch {$this->configuration_dir}/.env", 'hidden' => true],
            );

            if ($this->application->build_pack === 'dockerimage') {
                $this->application_deployment_queue->addLogEntry('Pulling latest images from the registry.');
                $this->execute_remote_command(
                    [executeInDocker($this->deployment_uuid, "docker compose --project-name {$this->application->uuid} --project-directory {$this->workdir} pull"), 'hidden' => true],
                    [executeInDocker($this->deployment_uuid, "{$this->saturn_variables} docker compose --project-name {$this->application->uuid} --project-directory {$this->workdir} up --build -d"), 'hidden' => true],
                );
            } else {
                if ($this->use_build_server) {
                    $this->execute_remote_command(
                        ["{$this->saturn_variables} docker compose --project-name {$this->application->uuid} --project-directory {$this->configuration_dir} -f {$this->configuration_dir}{$this->docker_compose_location} up --pull always --build -d", 'hidden' => true],
                    );
                } else {
                    $this->execute_remote_command(
                        [executeInDocker($this->deployment_uuid, "{$this->saturn_variables} docker compose --project-name {$this->application->uuid} --project-directory {$this->workdir} -f {$this->workdir}{$this->docker_compose_location} up --build -d"), 'hidden' => true],
                    );
                }
            }
            $this->application_deployment_queue->addLogEntry('New container started.');
        } catch (Exception $e) {
            throw new DeploymentException("Failed to start container: {$e->getMessage()}", $e->getCode(), $e);
        }
    }
}
