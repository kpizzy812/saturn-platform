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
     * Validate a Docker image name/tag to prevent shell injection.
     * Allows: alphanumeric, dots, dashes, underscores, slashes, colons.
     */
    private function validateDockerImageRef(string $ref, string $label = 'image reference'): string
    {
        if (! preg_match('/^[a-zA-Z0-9._\/:@-]+$/', $ref)) {
            throw new DeploymentException("Invalid {$label}: contains disallowed characters.");
        }

        return $ref;
    }

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
            // Validate image references to prevent shell injection
            $this->validateDockerImageRef($this->production_image_name, 'production image name');
            $escapedImageName = escapeshellarg($this->production_image_name);

            instant_remote_process(["docker images --format '{{json .}}' {$escapedImageName}"], $this->server);
            $this->application_deployment_queue->addLogEntry('----------------------------------------');
            $this->application_deployment_queue->addLogEntry("Pushing image to docker registry ({$this->production_image_name}).");
            $this->execute_remote_command(
                [
                    executeInDocker($this->deployment_uuid, "docker push {$this->production_image_name}"),
                    'hidden' => true,
                ],
            );
            if ($this->application->docker_registry_image_tag) {
                $registryName = $this->validateDockerImageRef($this->application->docker_registry_image_name, 'registry image name');
                $registryTag = $this->validateDockerImageRef($this->application->docker_registry_image_tag, 'registry image tag');
                $fullRef = "{$registryName}:{$registryTag}";

                $this->application_deployment_queue->addLogEntry("Tagging and pushing image with {$registryTag} tag.");
                $this->execute_remote_command(
                    [
                        executeInDocker($this->deployment_uuid, "docker tag {$this->production_image_name} {$fullRef}"),
                        'ignore_errors' => true,
                        'hidden' => true,
                    ],
                    [
                        executeInDocker($this->deployment_uuid, "docker push {$fullRef}"),
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
     * Handle image reuse for promotion deployments (dev → staging → prod).
     *
     * Tries to find the source environment's image and tag it for this deployment.
     * On success, marks local_image_found so should_skip_build() skips the build step.
     * On failure, logs a message and allows fallback to a full rebuild.
     */
    private function handle_promotion_image(): void
    {
        if (! $this->is_promotion || ! $this->promoted_from_image) {
            return;
        }

        // Validate image references to prevent shell injection
        $this->validateDockerImageRef($this->promoted_from_image, 'promoted source image');
        $this->validateDockerImageRef($this->production_image_name, 'production image name');
        $escapedPromoted = escapeshellarg($this->promoted_from_image);

        // Try to pull source image — works when a registry is configured or image is on a remote server
        $this->execute_remote_command([
            "docker pull {$escapedPromoted} 2>/dev/null",
            'ignore_errors' => true,
            'hidden' => true,
        ]);

        // Check if the source image is now available locally
        $this->execute_remote_command([
            "docker images -q {$escapedPromoted} 2>/dev/null",
            'hidden' => true,
            'save' => 'promotion_source_found',
        ]);

        if (str($this->saved_outputs->get('promotion_source_found'))->isEmpty()) {
            $this->application_deployment_queue->addLogEntry(
                "Promotion: Source image {$this->promoted_from_image} not found. Falling back to full build."
            );

            return;
        }

        // Tag source image as this deployment's production image when names differ
        if ($this->promoted_from_image !== $this->production_image_name) {
            $escapedProd = escapeshellarg($this->production_image_name);
            $this->execute_remote_command([
                "docker tag {$escapedPromoted} {$escapedProd}",
                'hidden' => true,
            ]);
            $this->application_deployment_queue->addLogEntry(
                "Promotion: Image reused from {$this->promoted_from_image} → tagged as {$this->production_image_name}."
            );
        } else {
            $this->application_deployment_queue->addLogEntry(
                "Promotion: Image {$this->production_image_name} already available (shared registry)."
            );
        }

        // Mark as found so should_skip_build() returns true and skips the build step
        $this->saved_outputs->put('local_image_found', 'promoted');
    }

    /**
     * Check if Docker image exists locally or in remote registry.
     */
    private function check_image_locally_or_remotely()
    {
        $this->validateDockerImageRef($this->production_image_name, 'production image name');
        $escapedImageName = escapeshellarg($this->production_image_name);

        $this->execute_remote_command([
            "docker images -q {$escapedImageName} 2>/dev/null",
            'hidden' => true,
            'save' => 'local_image_found',
        ]);
        if (str($this->saved_outputs->get('local_image_found'))->isEmpty() && $this->application->docker_registry_image_name) {
            $this->execute_remote_command([
                "docker pull {$escapedImageName} 2>/dev/null",
                'ignore_errors' => true,
                'hidden' => true,
            ]);
            $this->execute_remote_command([
                "docker images -q {$escapedImageName} 2>/dev/null",
                'hidden' => true,
                'save' => 'local_image_found',
            ]);
        }
    }
}
