<?php

namespace App\Traits\Deployment;

use App\Exceptions\DeploymentException;
use App\Models\ApplicationDeploymentQueue;
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
        $this->application_deployment_queue->setStage(ApplicationDeploymentQueue::STAGE_DEPLOY);

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
        $this->application_deployment_queue->setStage(ApplicationDeploymentQueue::STAGE_HEALTHCHECK);

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
                            $this->checkContainerState();
                            $this->query_logs();
                            $this->analyzeContainerFailure();
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
                        $this->application_deployment_queue->addLogEntry('Healthcheck timed out (still starting after all retries).', type: 'error');
                        $this->checkContainerState();
                        $this->query_logs();
                        $this->analyzeContainerFailure();
                    }
                }
            }
        } catch (Exception $e) {
            throw new DeploymentException('Health check failed ('.get_class($e).'): '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Check if the container has crashed/exited vs just failing healthcheck.
     * This helps distinguish between app crashes and healthcheck configuration issues.
     */
    private function checkContainerState(): void
    {
        $this->execute_remote_command(
            [
                "docker inspect --format='{{.State.Status}} {{.State.Restarting}} {{.RestartCount}}' {$this->container_name}",
                'hidden' => true,
                'save' => 'container_state_check',
                'ignore_errors' => true,
            ],
        );

        $state = trim($this->saved_outputs->get('container_state_check', ''));
        $parts = explode(' ', $state);
        $status = $parts[0];
        $isRestarting = (isset($parts[1]) && $parts[1] === 'true');
        $restartCount = (int) ($parts[2] ?? 0);

        if ($isRestarting || $restartCount > 0 || $status === 'restarting') {
            $this->application_deployment_queue->addLogEntry(
                "Container is crash-looping (status: {$status}, restarts: {$restartCount}). The application is crashing on startup.",
                type: 'error'
            );
        } elseif ($status === 'exited' || $status === 'dead') {
            $this->application_deployment_queue->addLogEntry(
                "Container has exited (status: {$status}). The application failed to start.",
                type: 'error'
            );
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
                'command' => "docker logs -n 100 {$this->container_name} 2>&1",
                'type' => 'stderr',
                'ignore_errors' => true,
            ],
        );
        $this->application_deployment_queue->addLogEntry('----------------------------------------');
    }

    /**
     * Verify container is stable and not crash-looping.
     * Performs 3 checks over 30 seconds to catch delayed crashes.
     */
    private function verify_container_stability(): void
    {
        if (! $this->container_name) {
            $this->newVersionIsHealthy = true;

            return;
        }

        $this->application_deployment_queue->addLogEntry('Verifying container stability (healthcheck disabled)...');

        $checks = 3;
        $intervalSeconds = 10;

        for ($i = 1; $i <= $checks; $i++) {
            Sleep::for($intervalSeconds)->seconds();

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
            $status = $parts[0];
            $isRestarting = (isset($parts[1]) && $parts[1] === 'true');

            if ($isRestarting || $status === 'restarting') {
                $this->newVersionIsHealthy = false;
                $this->application_deployment_queue->addLogEntry('Container is restarting/crash-looping!', type: 'error');
                $this->query_logs();
                $this->analyzeContainerFailure();

                throw new DeploymentException('Container is crash-looping. Check the logs above for details.');
            }

            if ($status !== 'running') {
                $this->newVersionIsHealthy = false;
                $this->application_deployment_queue->addLogEntry("Container is not running (status: {$status})!", type: 'error');
                $this->query_logs();
                $this->analyzeContainerFailure();

                throw new DeploymentException("Container failed to start (status: {$status}). Check the logs above for details.");
            }

            if ($i < $checks) {
                $this->application_deployment_queue->addLogEntry("Stability check {$i}/{$checks} passed, waiting...");
            }
        }

        $this->newVersionIsHealthy = true;
        $this->application->update(['status' => 'running']);
        $this->application_deployment_queue->addLogEntry("Container is running stably ({$checks} checks over ".($checks * $intervalSeconds).'s passed).');
    }

    /**
     * Analyze container logs and provide helpful error messages.
     */
    private function analyzeContainerFailure(): void
    {
        // Get container logs for analysis
        $this->execute_remote_command(
            [
                "docker logs {$this->container_name} 2>&1 | tail -50",
                'hidden' => true,
                'save' => 'failure_logs',
                'ignore_errors' => true,
            ],
        );

        $logs = $this->saved_outputs->get('failure_logs', '');

        $this->application_deployment_queue->addLogEntry('----------------------------------------');
        $this->application_deployment_queue->addLogEntry('🔍 DIAGNOSIS:', type: 'stderr');

        // Check for missing environment variables first (most common issue)
        $missingEnvVars = $this->detectMissingEnvVars($logs);
        if (! empty($missingEnvVars)) {
            $this->application_deployment_queue->addLogEntry('');
            $this->application_deployment_queue->addLogEntry('❌ ERROR: Missing required environment variables', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('');
            $this->application_deployment_queue->addLogEntry('The following variables must be configured:', type: 'stderr');
            foreach ($missingEnvVars as $var) {
                $this->application_deployment_queue->addLogEntry("  • {$var}", type: 'stderr');
            }
            $this->application_deployment_queue->addLogEntry('');
            $this->application_deployment_queue->addLogEntry('➡️ Go to Application → Environment Variables and add them.', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('   Then redeploy the application.', type: 'stderr');
            // Check for common Node.js errors
        } elseif (str_contains($logs, 'MODULE_NOT_FOUND') || str_contains($logs, 'Cannot find module')) {
            $this->application_deployment_queue->addLogEntry('');
            $this->application_deployment_queue->addLogEntry('❌ ERROR: Module/file not found', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('');
            $this->application_deployment_queue->addLogEntry('Common causes:', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('1. Build artifacts (dist/) not created or overwritten', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('2. dist/ is in .gitignore and Nixpacks overwrites it', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('3. Wrong path in start command', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('');
            $this->application_deployment_queue->addLogEntry('Solution for monorepos: Create nixpacks.toml with onlyIncludeFiles', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('to preserve build artifacts. See: https://nixpacks.com/docs/configuration/file', type: 'stderr');
        } elseif (str_contains($logs, '-c: option requires an argument') || str_contains($logs, 'bash: -c:')) {
            $this->application_deployment_queue->addLogEntry('');
            $this->application_deployment_queue->addLogEntry('❌ ERROR: No start command found', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('');
            $this->application_deployment_queue->addLogEntry('The container has no command to run.', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('');
            $this->application_deployment_queue->addLogEntry('Solutions:', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('1. Add "start" script to package.json: "start": "node dist/main.js"', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('2. Or set Start Command in Saturn application settings', type: 'stderr');
        } elseif (str_contains($logs, 'ECONNREFUSED') || str_contains($logs, 'connection refused')) {
            $this->application_deployment_queue->addLogEntry('');
            $this->application_deployment_queue->addLogEntry('❌ ERROR: Connection refused to database/service', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('');
            $this->application_deployment_queue->addLogEntry('The application cannot connect to a required service.', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('Check that database/redis/etc are running and accessible.', type: 'stderr');
        } elseif (str_contains($logs, 'ENOENT') || str_contains($logs, 'no such file')) {
            $this->application_deployment_queue->addLogEntry('');
            $this->application_deployment_queue->addLogEntry('❌ ERROR: File or directory not found', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('');
            $this->application_deployment_queue->addLogEntry('A required file is missing in the container.', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('Check your build process and file paths.', type: 'stderr');
        } elseif (str_contains($logs, 'permission denied') || str_contains($logs, 'Permission denied')) {
            $this->application_deployment_queue->addLogEntry('');
            $this->application_deployment_queue->addLogEntry('❌ ERROR: Permission denied', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('');
            $this->application_deployment_queue->addLogEntry('The application lacks permission to access a resource.', type: 'stderr');
        } else {
            $this->application_deployment_queue->addLogEntry('');
            $this->application_deployment_queue->addLogEntry('❌ The container failed to start. Review the logs above.', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('');
            $this->application_deployment_queue->addLogEntry('Common issues:', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('- Missing environment variables', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('- Database connection errors', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('- Missing dependencies', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('- Incorrect start command', type: 'stderr');
        }

        $this->application_deployment_queue->addLogEntry('----------------------------------------');
    }

    /**
     * Detect missing environment variables from error logs.
     * Parses common error patterns from various frameworks.
     *
     * @return array<string> List of detected missing variable names
     */
    private function detectMissingEnvVars(string $logs): array
    {
        $missingVars = [];

        // Pattern: Pydantic ValidationError (Python)
        // Format: "ValidationError: N validation error(s) for ClassName\nVAR_NAME\n  Field required [type=missing, ...]"
        if (preg_match_all('/^([A-Z][A-Z0-9_]+)\s*\n\s*Field\s+required/m', $logs, $matches)) {
            foreach ($matches[1] as $var) {
                $var = trim($var);
                if (! empty($var) && ! in_array($var, $missingVars)) {
                    $missingVars[] = $var;
                }
            }
        }

        // Pattern: Pydantic v2 inline format - "VAR_NAME  Field required [type=missing"
        if (preg_match_all('/^([A-Z][A-Z0-9_]+)\s{2,}Field\s+required/m', $logs, $matches)) {
            foreach ($matches[1] as $var) {
                $var = trim($var);
                if (! empty($var) && ! in_array($var, $missingVars)) {
                    $missingVars[] = $var;
                }
            }
        }

        // Pattern: "VAR_NAME must be defined" or "VAR_NAME and VAR2 must be defined"
        if (preg_match_all('/([A-Z][A-Z0-9_]+(?:\s+and\s+[A-Z][A-Z0-9_]+)*)\s+must\s+be\s+(?:defined|set|provided)/i', $logs, $matches)) {
            foreach ($matches[1] as $match) {
                // Split "VAR1 and VAR2" into separate vars
                $vars = preg_split('/\s+and\s+/i', $match);
                foreach ($vars as $var) {
                    $var = trim($var);
                    if (! empty($var) && ! in_array($var, $missingVars)) {
                        $missingVars[] = $var;
                    }
                }
            }
        }

        // Pattern: "VAR_NAME is required" or "VAR_NAME is not set"
        if (preg_match_all('/([A-Z][A-Z0-9_]+)\s+(?:is\s+)?(?:required|not\s+set|not\s+defined|missing|undefined)/i', $logs, $matches)) {
            foreach ($matches[1] as $var) {
                $var = trim($var);
                if (! empty($var) && ! in_array($var, $missingVars)) {
                    $missingVars[] = $var;
                }
            }
        }

        // Pattern: "Missing environment variable: VAR_NAME" or "Missing required env var VAR_NAME"
        if (preg_match_all('/[Mm]issing\s+(?:required\s+)?(?:environment\s+)?(?:variable|env\s+var)s?[:\s]+([A-Z][A-Z0-9_]+)/i', $logs, $matches)) {
            foreach ($matches[1] as $var) {
                $var = trim($var);
                if (! empty($var) && ! in_array($var, $missingVars)) {
                    $missingVars[] = $var;
                }
            }
        }

        // Pattern: "process.env.VAR_NAME is undefined" (JavaScript)
        if (preg_match_all('/process\.env\.([A-Z][A-Z0-9_]+)\s+is\s+undefined/i', $logs, $matches)) {
            foreach ($matches[1] as $var) {
                $var = trim($var);
                if (! empty($var) && ! in_array($var, $missingVars)) {
                    $missingVars[] = $var;
                }
            }
        }

        // Pattern: "Config validation error: VAR_NAME should not be empty"
        if (preg_match_all('/(?:Config|Configuration|Validation)\s+(?:validation\s+)?error[^:]*:\s*([A-Z][A-Z0-9_]+)\s+(?:should\s+not\s+be\s+empty|is\s+required)/i', $logs, $matches)) {
            foreach ($matches[1] as $var) {
                $var = trim($var);
                if (! empty($var) && ! in_array($var, $missingVars)) {
                    $missingVars[] = $var;
                }
            }
        }

        // Pattern: Python/Django style "ImproperlyConfigured: Set the VAR_NAME environment variable"
        if (preg_match_all('/Set\s+the\s+([A-Z][A-Z0-9_]+)\s+environment\s+variable/i', $logs, $matches)) {
            foreach ($matches[1] as $var) {
                $var = trim($var);
                if (! empty($var) && ! in_array($var, $missingVars)) {
                    $missingVars[] = $var;
                }
            }
        }

        // Filter out false positives — generic words that match [A-Z][A-Z0-9_]+ with /i flag
        // e.g. "Field required" from Pydantic logs matches the generic "VAR is required" pattern
        $falsePositives = ['Field', 'This', 'Error', 'Value', 'Input', 'Type', 'String', 'Integer', 'Boolean'];
        $missingVars = array_values(array_filter($missingVars, fn ($var) => ! in_array($var, $falsePositives)));

        return $missingVars;
    }
}
