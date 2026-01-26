<?php

namespace App\Traits\Deployment;

use App\Exceptions\DeploymentException;
use Exception;
use Illuminate\Support\Sleep;

/**
 * Trait for health check and rolling update operations during deployment.
 *
 * Required properties from parent class:
 * - $application, $application_deployment_queue, $server, $deployment_uuid
 * - $use_build_server, $build_server, $original_server
 * - $pull_request_id, $container_name, $workdir, $docker_compose_location
 * - $newVersionIsHealthy, $full_healthcheck_url, $saved_outputs
 *
 * Required methods from parent class:
 * - execute_remote_command(), checkForCancellation()
 * - write_deployment_configurations(), stop_running_container()
 * - start_by_compose_file()
 */
trait HandlesHealthCheck
{
    /**
     * Perform rolling update of the application.
     */
    private function rolling_update()
    {
        try {
            $this->checkForCancellation();
            if ($this->server->isSwarm()) {
                $this->application_deployment_queue->addLogEntry('Rolling update started.');
                $this->execute_remote_command(
                    [
                        executeInDocker($this->deployment_uuid, "docker stack deploy --detach=true --with-registry-auth -c {$this->workdir}{$this->docker_compose_location} {$this->application->uuid}"),
                    ],
                );
                $this->application_deployment_queue->addLogEntry('Rolling update completed.');
            } else {
                if ($this->use_build_server) {
                    $this->write_deployment_configurations();
                    $this->server = $this->original_server;
                }
                if (count($this->application->ports_mappings_array) > 0 || (bool) $this->application->settings->is_consistent_container_name_enabled || str($this->application->settings->custom_internal_name)->isNotEmpty() || $this->pull_request_id !== 0 || str($this->application->custom_docker_run_options)->contains('--ip') || str($this->application->custom_docker_run_options)->contains('--ip6')) {
                    $this->application_deployment_queue->addLogEntry('----------------------------------------');
                    if (count($this->application->ports_mappings_array) > 0) {
                        $this->application_deployment_queue->addLogEntry('Application has ports mapped to the host system, rolling update is not supported.');
                    }
                    if ((bool) $this->application->settings->is_consistent_container_name_enabled) {
                        $this->application_deployment_queue->addLogEntry('Consistent container name feature enabled, rolling update is not supported.');
                    }
                    if (str($this->application->settings->custom_internal_name)->isNotEmpty()) {
                        $this->application_deployment_queue->addLogEntry('Custom internal name is set, rolling update is not supported.');
                    }
                    if ($this->pull_request_id !== 0) {
                        $this->application->settings->is_consistent_container_name_enabled = true;
                        $this->application_deployment_queue->addLogEntry('Pull request deployment, rolling update is not supported.');
                    }
                    if (str($this->application->custom_docker_run_options)->contains('--ip') || str($this->application->custom_docker_run_options)->contains('--ip6')) {
                        $this->application_deployment_queue->addLogEntry('Custom IP address is set, rolling update is not supported.');
                    }
                    $this->stop_running_container(force: true);
                    $this->start_by_compose_file();
                } else {
                    $this->application_deployment_queue->addLogEntry('----------------------------------------');
                    $this->application_deployment_queue->addLogEntry('Rolling update started.');
                    $this->start_by_compose_file();
                    $this->health_check();
                    $this->stop_running_container();
                    $this->application_deployment_queue->addLogEntry('Rolling update completed.');
                }
            }
        } catch (Exception $e) {
            throw new DeploymentException('Rolling update failed ('.get_class($e).'): '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Perform health check on the new container.
     */
    private function health_check()
    {
        try {
            if ($this->server->isSwarm()) {
                // Implement healthcheck for swarm
            } else {
                if ($this->application->isHealthcheckDisabled() && $this->application->custom_healthcheck_found === false) {
                    // Even without healthcheck, verify container is not crashing
                    $this->verify_container_stability();

                    return;
                }
                if ($this->application->custom_healthcheck_found) {
                    $this->application_deployment_queue->addLogEntry('Custom healthcheck found in Dockerfile.');
                }
                if ($this->container_name) {
                    $counter = 1;
                    $this->application_deployment_queue->addLogEntry('Waiting for healthcheck to pass on the new container.');
                    if ($this->full_healthcheck_url && ! $this->application->custom_healthcheck_found) {
                        $this->application_deployment_queue->addLogEntry("Healthcheck URL (inside the container): {$this->full_healthcheck_url}");
                    }
                    $this->application_deployment_queue->addLogEntry("Waiting for the start period ({$this->application->health_check_start_period} seconds) before starting healthcheck.");
                    $sleeptime = 0;
                    while ($sleeptime < $this->application->health_check_start_period) {
                        Sleep::for(1)->seconds();
                        $sleeptime++;
                    }
                    while ($counter <= $this->application->health_check_retries) {
                        $this->execute_remote_command(
                            [
                                "docker inspect --format='{{json .State.Health.Status}}' {$this->container_name}",
                                'hidden' => true,
                                'save' => 'health_check',
                                'append' => false,
                            ],
                            [
                                "docker inspect --format='{{json .State.Health.Log}}' {$this->container_name}",
                                'hidden' => true,
                                'save' => 'health_check_logs',
                                'append' => false,
                            ],
                        );
                        $this->application_deployment_queue->addLogEntry("Attempt {$counter} of {$this->application->health_check_retries} | Healthcheck status: {$this->saved_outputs->get('health_check')}");
                        $health_check_logs = data_get(collect(json_decode($this->saved_outputs->get('health_check_logs')))->last(), 'Output', '(no logs)');
                        if (empty($health_check_logs)) {
                            $health_check_logs = '(no logs)';
                        }
                        $health_check_return_code = data_get(collect(json_decode($this->saved_outputs->get('health_check_logs')))->last(), 'ExitCode', '(no return code)');
                        if ($health_check_logs !== '(no logs)' || $health_check_return_code !== '(no return code)') {
                            $this->application_deployment_queue->addLogEntry("Healthcheck logs: {$health_check_logs} | Return code: {$health_check_return_code}");
                        }

                        if (str($this->saved_outputs->get('health_check'))->replace('"', '')->value() === 'healthy') {
                            $this->newVersionIsHealthy = true;
                            $this->application->update(['status' => 'running']);
                            $this->application_deployment_queue->addLogEntry('New container is healthy.');
                            break;
                        } elseif (str($this->saved_outputs->get('health_check'))->replace('"', '')->value() === 'unhealthy') {
                            $this->newVersionIsHealthy = false;
                            $this->application_deployment_queue->addLogEntry('New container is unhealthy.', type: 'error');
                            $this->query_logs();
                            break;
                        }
                        $counter++;
                        $sleeptime = 0;
                        while ($sleeptime < $this->application->health_check_interval) {
                            Sleep::for(1)->seconds();
                            $sleeptime++;
                        }
                    }
                    if (str($this->saved_outputs->get('health_check'))->replace('"', '')->value() === 'starting') {
                        $this->query_logs();
                    }
                }
            }
        } catch (Exception $e) {
            throw new DeploymentException('Health check failed ('.get_class($e).'): '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Query container logs for debugging.
     */
    private function query_logs()
    {
        $this->application_deployment_queue->addLogEntry('----------------------------------------');
        $this->application_deployment_queue->addLogEntry('Container logs:');
        $this->execute_remote_command(
            [
                'command' => "docker logs -n 100 {$this->container_name}",
                'type' => 'stderr',
                'ignore_errors' => true,
            ],
        );
        $this->application_deployment_queue->addLogEntry('----------------------------------------');
    }

    /**
     * Verify container is stable and not crash-looping.
     * This is a lightweight check for when healthcheck is disabled.
     */
    private function verify_container_stability(): void
    {
        if (! $this->container_name) {
            $this->newVersionIsHealthy = true;

            return;
        }

        $this->application_deployment_queue->addLogEntry('Verifying container stability (healthcheck disabled)...');

        // Wait a few seconds for container to potentially crash
        Sleep::for(5)->seconds();

        // Check container state
        $this->execute_remote_command(
            [
                "docker inspect --format='{{.State.Status}} {{.State.Restarting}}' {$this->container_name}",
                'hidden' => true,
                'save' => 'container_state',
                'ignore_errors' => true,
            ],
        );

        $state = trim($this->saved_outputs->get('container_state', ''));
        $parts = explode(' ', $state);
        $status = $parts[0] ?? '';
        $isRestarting = ($parts[1] ?? '') === 'true';

        if ($isRestarting || $status === 'restarting') {
            $this->newVersionIsHealthy = false;
            $this->application_deployment_queue->addLogEntry('⚠️ Container is restarting/crash-looping!', type: 'error');
            $this->application_deployment_queue->addLogEntry('This usually means the start command failed or is missing.', type: 'error');
            $this->query_logs();

            throw new DeploymentException('Container is crash-looping. Check the logs above for details.');
        }

        if ($status !== 'running') {
            $this->newVersionIsHealthy = false;
            $this->application_deployment_queue->addLogEntry("⚠️ Container is not running (status: {$status})!", type: 'error');
            $this->query_logs();

            throw new DeploymentException("Container failed to start (status: {$status}). Check the logs above for details.");
        }

        $this->newVersionIsHealthy = true;
        $this->application->update(['status' => 'running']);
        $this->application_deployment_queue->addLogEntry('Container is running stably.');
    }
}
