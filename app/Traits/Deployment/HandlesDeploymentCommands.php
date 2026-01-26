<?php

namespace App\Traits\Deployment;

use App\Exceptions\DeploymentException;
use Exception;

/**
 * Trait for pre/post deployment command execution.
 *
 * Required properties from parent class:
 * - $application, $application_deployment_queue, $server
 * - $pull_request_id, $saved_outputs
 *
 * Required methods from parent class:
 * - execute_remote_command()
 */
trait HandlesDeploymentCommands
{
    /**
     * Execute pre-deployment command on running container.
     */
    private function run_pre_deployment_command()
    {
        if (empty($this->application->pre_deployment_command)) {
            return;
        }
        $containers = getCurrentApplicationContainerStatus($this->server, $this->application->id, $this->pull_request_id);
        if ($containers->count() == 0) {
            return;
        }
        $this->application_deployment_queue->addLogEntry('Executing pre-deployment command (see debug log for output/errors).');

        foreach ($containers as $container) {
            $containerName = data_get($container, 'Names');
            if ($containers->count() == 1 || str_starts_with($containerName, $this->application->pre_deployment_command_container.'-'.$this->application->uuid)) {
                $cmd = "sh -c '".str_replace("'", "'\''", $this->application->pre_deployment_command)."'";
                $exec = "docker exec {$containerName} {$cmd}";
                $this->execute_remote_command(
                    [
                        'command' => $exec,
                        'hidden' => true,
                    ],
                );

                return;
            }
        }
        throw new DeploymentException('Pre-deployment command: Could not find a valid container. Is the container name correct?');
    }

    /**
     * Execute post-deployment command on running container.
     */
    private function run_post_deployment_command()
    {
        if (empty($this->application->post_deployment_command)) {
            return;
        }
        $this->application_deployment_queue->addLogEntry('----------------------------------------');
        $this->application_deployment_queue->addLogEntry('Executing post-deployment command (see debug log for output).');

        $containers = getCurrentApplicationContainerStatus($this->server, $this->application->id, $this->pull_request_id);
        foreach ($containers as $container) {
            $containerName = data_get($container, 'Names');
            if ($containers->count() == 1 || str_starts_with($containerName, $this->application->post_deployment_command_container.'-'.$this->application->uuid)) {
                $cmd = "sh -c '".str_replace("'", "'\''", $this->application->post_deployment_command)."'";
                $exec = "docker exec {$containerName} {$cmd}";
                try {
                    $this->execute_remote_command(
                        [
                            'command' => $exec,
                            'hidden' => true,
                            'save' => 'post-deployment-command-output',
                        ],
                    );
                } catch (Exception $e) {
                    $post_deployment_command_output = $this->saved_outputs->get('post-deployment-command-output');
                    if ($post_deployment_command_output) {
                        $this->application_deployment_queue->addLogEntry('Post-deployment command failed.');
                        $this->application_deployment_queue->addLogEntry($post_deployment_command_output, 'stderr');
                    }
                }

                return;
            }
        }
        throw new DeploymentException('Post-deployment command: Could not find a valid container. Is the container name correct?');
    }
}
