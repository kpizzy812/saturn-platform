<?php

namespace App\Traits\Deployment;

use App\Exceptions\DeploymentException;
use App\Models\ApplicationDeploymentQueue;
use Exception;

/**
 * Trait for Docker image registry operations during deployment.
 *
 * Required properties from parent class:
 * - $application, $application_deployment_queue, $server, $deployment_uuid
 * - $production_image_name, $build_image_name, $dockerImage, $dockerImageTag
 * - $restart_only, $use_build_server, $build_pack
 * - $is_this_additional_server, $pull_request_id, $commit, $saved_outputs
 *
 * Required methods from parent class:
 * - execute_remote_command()
 */
trait HandlesImageRegistry
{
    /**
     * Push built image to Docker registry.
     */
    private function push_to_docker_registry()
    {
        $this->application_deployment_queue->setStage(ApplicationDeploymentQueue::STAGE_PUSH);

        if (str($this->application->docker_registry_image_name)->isEmpty()) {
            return;
        }
        if ($this->restart_only) {
            return;
        }
        if ($this->application->build_pack === 'dockerimage') {
            return;
        }
        if ($this->is_this_additional_server) {
            return;
        }
        try {
            instant_remote_process(["docker images --format '{{json .}}' {$this->production_image_name}"], $this->server);
            $this->application_deployment_queue->addLogEntry('----------------------------------------');
            $this->application_deployment_queue->addLogEntry("Pushing image to docker registry ({$this->production_image_name}).");
            $this->execute_remote_command(
                [
                    executeInDocker($this->deployment_uuid, "docker push {$this->production_image_name}"),
                    'hidden' => true,
                ],
            );
            if ($this->application->docker_registry_image_tag) {
                // Tag image with docker_registry_image_tag
                $this->application_deployment_queue->addLogEntry("Tagging and pushing image with {$this->application->docker_registry_image_tag} tag.");
                $this->execute_remote_command(
                    [
                        executeInDocker($this->deployment_uuid, "docker tag {$this->production_image_name} {$this->application->docker_registry_image_name}:{$this->application->docker_registry_image_tag}"),
                        'ignore_errors' => true,
                        'hidden' => true,
                    ],
                    [
                        executeInDocker($this->deployment_uuid, "docker push {$this->application->docker_registry_image_name}:{$this->application->docker_registry_image_tag}"),
                        'ignore_errors' => true,
                        'hidden' => true,
                    ],
                );
            }
        } catch (Exception $e) {
            $this->application_deployment_queue->addLogEntry('Failed to push image to docker registry. Please check debug logs for more information.');
            throw new DeploymentException(get_class($e).': '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Generate Docker image names based on build configuration.
     */
    private function generate_image_names()
    {
        if ($this->application->dockerfile) {
            if ($this->application->docker_registry_image_name) {
                $this->build_image_name = "{$this->application->docker_registry_image_name}:build";
                $this->production_image_name = "{$this->application->docker_registry_image_name}:latest";
            } else {
                $this->build_image_name = "{$this->application->uuid}:build";
                $this->production_image_name = "{$this->application->uuid}:latest";
            }
        } elseif ($this->application->build_pack === 'dockerimage') {
            // Check if this is an image hash deployment
            if (str($this->dockerImageTag)->startsWith('sha256-')) {
                $hash = str($this->dockerImageTag)->after('sha256-');
                $this->production_image_name = "{$this->dockerImage}@sha256:{$hash}";
            } else {
                $this->production_image_name = "{$this->dockerImage}:{$this->dockerImageTag}";
            }
        } elseif ($this->pull_request_id !== 0) {
            if ($this->application->docker_registry_image_name) {
                $this->build_image_name = "{$this->application->docker_registry_image_name}:pr-{$this->pull_request_id}-build";
                $this->production_image_name = "{$this->application->docker_registry_image_name}:pr-{$this->pull_request_id}";
            } else {
                $this->build_image_name = "{$this->application->uuid}:pr-{$this->pull_request_id}-build";
                $this->production_image_name = "{$this->application->uuid}:pr-{$this->pull_request_id}";
            }
        } else {
            $this->dockerImageTag = str($this->commit)->substr(0, 128);
            // if ($this->application->docker_registry_image_tag) {
            //     $this->dockerImageTag = $this->application->docker_registry_image_tag;
            // }
            if ($this->application->docker_registry_image_name) {
                $this->build_image_name = "{$this->application->docker_registry_image_name}:{$this->dockerImageTag}-build";
                $this->production_image_name = "{$this->application->docker_registry_image_name}:{$this->dockerImageTag}";
            } else {
                $this->build_image_name = "{$this->application->uuid}:{$this->dockerImageTag}-build";
                $this->production_image_name = "{$this->application->uuid}:{$this->dockerImageTag}";
            }
        }
    }

    /**
     * Check if Docker image exists locally or in remote registry.
     */
    private function check_image_locally_or_remotely()
    {
        $this->execute_remote_command([
            "docker images -q {$this->production_image_name} 2>/dev/null",
            'hidden' => true,
            'save' => 'local_image_found',
        ]);
        if (str($this->saved_outputs->get('local_image_found'))->isEmpty() && $this->application->docker_registry_image_name) {
            $this->execute_remote_command([
                "docker pull {$this->production_image_name} 2>/dev/null",
                'ignore_errors' => true,
                'hidden' => true,
            ]);
            $this->execute_remote_command([
                "docker images -q {$this->production_image_name} 2>/dev/null",
                'hidden' => true,
                'save' => 'local_image_found',
            ]);
        }
    }
}
