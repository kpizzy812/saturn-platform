<?php

namespace App\Traits\Deployment;

use Exception;

/**
 * Trait for deployment configuration operations.
 *
 * Required properties from parent class:
 * - $application, $application_deployment_queue, $deployment_uuid
 * - $server, $build_server, $original_server, $use_build_server
 * - $workdir, $configuration_dir, $docker_compose_base64, $docker_compose_location
 * - $preserveRepository, $pull_request_id, $dockerBuildkitSupported
 *
 * Required methods from parent class:
 * - execute_remote_command()
 */
trait HandlesDeploymentConfiguration
{
    /**
     * Detect Docker BuildKit capabilities on the server.
     */
    private function detectBuildKitCapabilities(): void
    {
        // If build secrets are not enabled, skip detection and use traditional args
        if (! $this->application->settings->use_build_secrets) {
            $this->dockerBuildkitSupported = false;

            return;
        }

        $serverToCheck = $this->use_build_server ? $this->build_server : $this->server;
        $serverName = $this->use_build_server ? "build server ({$serverToCheck->name})" : "deployment server ({$serverToCheck->name})";

        try {
            $dockerVersion = instant_remote_process(
                ["docker version --format '{{.Server.Version}}'"],
                $serverToCheck
            );

            $versionParts = explode('.', $dockerVersion);
            $majorVersion = (int) $versionParts[0];
            $minorVersion = (int) ($versionParts[1] ?? 0);

            if ($majorVersion < 18 || ($majorVersion == 18 && $minorVersion < 9)) {
                $this->dockerBuildkitSupported = false;
                $this->application_deployment_queue->addLogEntry("Docker {$dockerVersion} on {$serverName} does not support BuildKit (requires 18.09+). Build secrets feature disabled.");

                return;
            }

            $buildkitEnabled = instant_remote_process(
                ["docker buildx version >/dev/null 2>&1 && echo 'available' || echo 'not-available'"],
                $serverToCheck
            );

            if (trim($buildkitEnabled) !== 'available') {
                $buildkitTest = instant_remote_process(
                    ["DOCKER_BUILDKIT=1 docker build --help 2>&1 | grep -q 'secret' && echo 'supported' || echo 'not-supported'"],
                    $serverToCheck
                );

                if (trim($buildkitTest) === 'supported') {
                    $this->dockerBuildkitSupported = true;
                    $this->application_deployment_queue->addLogEntry("Docker {$dockerVersion} with BuildKit secrets support detected on {$serverName}.");
                    $this->application_deployment_queue->addLogEntry('Build secrets are enabled and will be used for enhanced security.');
                } else {
                    $this->dockerBuildkitSupported = false;
                    $this->application_deployment_queue->addLogEntry("Docker {$dockerVersion} on {$serverName} does not have BuildKit secrets support.");
                    $this->application_deployment_queue->addLogEntry('Build secrets feature is enabled but not supported. Using traditional build arguments.');
                }
            } else {
                // Buildx is available, which means BuildKit is available
                // Now specifically test for secrets support
                $secretsTest = instant_remote_process(
                    ["docker build --help 2>&1 | grep -q 'secret' && echo 'supported' || echo 'not-supported'"],
                    $serverToCheck
                );

                if (trim($secretsTest) === 'supported') {
                    $this->dockerBuildkitSupported = true;
                    $this->application_deployment_queue->addLogEntry("Docker {$dockerVersion} with BuildKit and Buildx detected on {$serverName}.");
                    $this->application_deployment_queue->addLogEntry('Build secrets are enabled and will be used for enhanced security.');
                } else {
                    $this->dockerBuildkitSupported = false;
                    $this->application_deployment_queue->addLogEntry("Docker {$dockerVersion} with Buildx on {$serverName}, but secrets not supported.");
                    $this->application_deployment_queue->addLogEntry('Build secrets feature is enabled but not supported. Using traditional build arguments.');
                }
            }
        } catch (Exception $e) {
            $this->dockerBuildkitSupported = false;
            $this->application_deployment_queue->addLogEntry("Could not detect BuildKit capabilities on {$serverName}: {$e->getMessage()}");
            $this->application_deployment_queue->addLogEntry('Build secrets feature is enabled but detection failed. Using traditional build arguments.');
        }
    }

    /**
     * Write deployment configurations to the server.
     */
    private function write_deployment_configurations()
    {
        if ($this->preserveRepository) {
            if ($this->use_build_server) {
                $this->server = $this->original_server;
            }
            if (str($this->configuration_dir)->isNotEmpty()) {
                $this->execute_remote_command(
                    [
                        "mkdir -p $this->configuration_dir",
                    ],
                    [
                        "docker cp {$this->deployment_uuid}:{$this->workdir}/. {$this->configuration_dir}",
                    ],
                );
            }
            foreach ($this->application->fileStorages as $fileStorage) {
                if (! $fileStorage->is_based_on_git && ! $fileStorage->is_directory) {
                    $fileStorage->saveStorageOnServer();
                }
            }
            if ($this->use_build_server) {
                $this->server = $this->build_server;
            }
        }
        if (isset($this->docker_compose_base64)) {
            if ($this->use_build_server) {
                $this->server = $this->original_server;
            }
            $readme = generate_readme_file($this->application->name, $this->application_deployment_queue->updated_at);

            $mainDir = $this->configuration_dir;
            if ($this->application->settings->is_raw_compose_deployment_enabled) {
                $mainDir = $this->application->workdir();
            }
            if ($this->pull_request_id === 0) {
                $composeFileName = "$mainDir/docker-compose.yaml";
            } else {
                $composeFileName = "$mainDir/".addPreviewDeploymentSuffix('docker-compose', $this->pull_request_id).'.yaml';
                $this->docker_compose_location = '/'.addPreviewDeploymentSuffix('docker-compose', $this->pull_request_id).'.yaml';
            }
            $this->execute_remote_command(
                [
                    "mkdir -p $mainDir",
                ],
                [
                    "echo '{$this->docker_compose_base64}' | base64 -d | tee $composeFileName > /dev/null",
                ],
                [
                    "echo '{$readme}' > $mainDir/README.md",
                ]
            );
            if ($this->use_build_server) {
                $this->server = $this->build_server;
            }
        }
    }
}
