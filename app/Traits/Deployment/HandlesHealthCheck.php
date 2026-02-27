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
                            $this->application_deployment_queue->addLogEntry('New container is healthy.');
                            if ($this->perform_smoke_test()) {
                                $this->newVersionIsHealthy = true;
                                $this->application->update(['status' => 'running']);
                            } else {
                                $this->newVersionIsHealthy = false;
                            }
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
                "docker logs -n 100 {$this->container_name} 2>&1",
                'hidden' => true,
                'save' => 'query_logs_output',
                'ignore_errors' => true,
            ],
        );

        $logOutput = trim($this->saved_outputs->get('query_logs_output', ''));
        if (empty($logOutput)) {
            $this->application_deployment_queue->addLogEntry('(no container logs available — the application crashed before producing any output)', type: 'stderr');
        } else {
            $this->application_deployment_queue->addLogEntry($logOutput, type: 'stderr');
        }
        $this->application_deployment_queue->addLogEntry('----------------------------------------');
    }

    /**
     * Perform an HTTP smoke test from the deployment server to the container.
     *
     * Uses docker inspect to get the container's internal IP, then runs curl
     * from the server targeting that IP directly. This validates the app is
     * reachable via the server's network stack — something docker healthcheck
     * (which runs inside the container) cannot verify.
     *
     * Requires curl on the deployment server (standard on all Linux distros).
     * Does NOT require curl inside the container.
     *
     * @return bool True if smoke test passed or is disabled.
     */
    private function perform_smoke_test(): bool
    {
        if (! $this->application->smoke_test_enabled) {
            return true;
        }

        if (! $this->container_name) {
            $this->application_deployment_queue->addLogEntry('Smoke test skipped: no container name available.', type: 'warning');

            return true;
        }

        $port = $this->application->health_check_port
            ?? ($this->application->ports_exposes_array[0] ?? 80);
        $path = ltrim($this->application->smoke_test_path ?? '/', '/');
        $timeout = max(5, (int) ($this->application->smoke_test_timeout ?? 30));
        $connectTimeout = min(10, $timeout);

        $this->application_deployment_queue->addLogEntry('----------------------------------------');
        $this->application_deployment_queue->addLogEntry("Running smoke test (/{$path}, timeout: {$timeout}s)...");

        // Get container IP from docker inspect (works from host without curl in container)
        $this->execute_remote_command(
            [
                "docker inspect --format='{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' {$this->container_name} 2>/dev/null | awk '{print $1}'",
                'hidden' => true,
                'save' => 'smoke_test_container_ip',
                'ignore_errors' => true,
            ],
        );

        $containerIp = trim($this->saved_outputs->get('smoke_test_container_ip', ''));

        if (empty($containerIp)) {
            $this->application_deployment_queue->addLogEntry('Smoke test skipped: could not determine container IP address.', type: 'warning');

            return true;
        }

        $url = "http://{$containerIp}:{$port}/{$path}";

        $this->execute_remote_command(
            [
                "curl -sf -o /dev/null -w '%{http_code}' --max-time {$timeout} --connect-timeout {$connectTimeout} '{$url}' 2>/dev/null || echo 'CURL_FAILED'",
                'hidden' => true,
                'save' => 'smoke_test_http_code',
                'ignore_errors' => true,
            ],
        );

        $result = trim($this->saved_outputs->get('smoke_test_http_code', ''));

        if ($result === 'CURL_FAILED' || empty($result)) {
            $this->application_deployment_queue->addLogEntry("Smoke test FAILED: could not reach {$url} (curl error or timeout).", type: 'error');
            $this->application_deployment_queue->addLogEntry('The application container is running but not responding to HTTP requests.', type: 'error');

            return false;
        }

        $httpCode = (int) $result;

        if ($httpCode >= 200 && $httpCode < 500) {
            $this->application_deployment_queue->addLogEntry("Smoke test passed (HTTP {$httpCode}).");
            $this->application_deployment_queue->addLogEntry('----------------------------------------');

            return true;
        }

        $this->application_deployment_queue->addLogEntry("Smoke test FAILED: HTTP {$httpCode} from {$url}.", type: 'error');
        $this->application_deployment_queue->addLogEntry('The application is returning an unexpected HTTP status code.', type: 'error');
        $this->application_deployment_queue->addLogEntry('----------------------------------------');

        return false;
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

        $this->application_deployment_queue->addLogEntry("Container is running stably ({$checks} checks over ".($checks * $intervalSeconds).'s passed).');

        if (! $this->perform_smoke_test()) {
            $this->newVersionIsHealthy = false;
            throw new DeploymentException('Smoke test failed after container stability check. The application is not responding to HTTP requests.');
        }

        $this->newVersionIsHealthy = true;
        $this->application->update(['status' => 'running']);
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

        $logs = trim($this->saved_outputs->get('failure_logs', ''));

        $this->application_deployment_queue->addLogEntry('----------------------------------------');
        $this->application_deployment_queue->addLogEntry('🔍 DIAGNOSIS:', type: 'stderr');

        // When logs are empty, use docker inspect to diagnose
        if (empty($logs)) {
            $this->diagnoseFromContainerInspect();

            return;
        }

        $this->diagnoseFromLogs($logs);
        $this->application_deployment_queue->addLogEntry('----------------------------------------');
    }

    /**
     * Diagnose failure from container logs using pattern matching.
     */
    private function diagnoseFromLogs(string $logs): void
    {
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
    }

    /**
     * Diagnose container failure using docker inspect when logs are empty.
     * Extracts exit code, OOM status, and Docker error details.
     */
    private function diagnoseFromContainerInspect(): void
    {
        $this->execute_remote_command(
            [
                "docker inspect --format='{{json .State}}' {$this->container_name}",
                'hidden' => true,
                'save' => 'inspect_state_json',
                'ignore_errors' => true,
            ],
            [
                "docker inspect --format='ENTRYPOINT={{json .Config.Entrypoint}} CMD={{json .Config.Cmd}} IMAGE={{.Config.Image}}' {$this->container_name}",
                'hidden' => true,
                'save' => 'inspect_config',
                'ignore_errors' => true,
            ],
        );

        $stateJson = trim($this->saved_outputs->get('inspect_state_json', ''));
        $state = json_decode($stateJson, true);

        // If we can't even inspect the container
        if (! $state) {
            $this->application_deployment_queue->addLogEntry('');
            $this->application_deployment_queue->addLogEntry('❌ Container failed to start and produced no logs.', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('   Could not inspect container state (container may have been removed).', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('');
            $this->application_deployment_queue->addLogEntry('Try redeploying. If the issue persists, check:', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('- Docker image builds correctly', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('- Start command / Dockerfile CMD is valid', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('- Required environment variables are set', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('----------------------------------------');

            return;
        }

        $exitCode = $state['ExitCode'] ?? null;
        $oomKilled = $state['OOMKilled'] ?? false;
        $dockerError = $state['Error'] ?? '';

        if ($oomKilled || $exitCode === 137) {
            $this->application_deployment_queue->addLogEntry('');
            $this->application_deployment_queue->addLogEntry('❌ ERROR: Out of memory (OOM Kill)', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('');
            $this->application_deployment_queue->addLogEntry('The container was killed because it exceeded its memory limit.', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('Solutions:', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('1. Increase memory limit in Application → Resource Limits', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('2. Optimize the application\'s memory usage', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('3. Check for memory leaks on startup', type: 'stderr');
        } elseif (! empty($dockerError)) {
            $this->application_deployment_queue->addLogEntry('');
            $this->application_deployment_queue->addLogEntry('❌ ERROR: Docker runtime error', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('');
            $this->application_deployment_queue->addLogEntry("Details: {$dockerError}", type: 'stderr');

            if (str_contains($dockerError, 'executable file not found') || str_contains($dockerError, 'not found')) {
                $config = trim($this->saved_outputs->get('inspect_config', ''));
                $this->application_deployment_queue->addLogEntry('');
                $this->application_deployment_queue->addLogEntry('The start command or entrypoint binary does not exist in the container.', type: 'stderr');
                if (! empty($config)) {
                    $this->application_deployment_queue->addLogEntry("Container config: {$config}", type: 'stderr');
                }
                $this->application_deployment_queue->addLogEntry('');
                $this->application_deployment_queue->addLogEntry('Solutions:', type: 'stderr');
                $this->application_deployment_queue->addLogEntry('1. Check that your Dockerfile installs all required binaries', type: 'stderr');
                $this->application_deployment_queue->addLogEntry('2. Verify the start command in Application settings', type: 'stderr');
            }
        } elseif ($exitCode !== null && $exitCode !== 0) {
            $exitCodeMeanings = [
                1 => 'General application error — the app crashed on startup',
                2 => 'Shell misuse or missing command argument',
                126 => 'Command found but not executable (permission issue)',
                127 => 'Command not found — the start command does not exist in the container',
                139 => 'Segmentation fault (SIGSEGV) — native code crash',
                143 => 'Process terminated (SIGTERM)',
            ];

            $meaning = $exitCodeMeanings[$exitCode] ?? 'Unknown error';
            $config = trim($this->saved_outputs->get('inspect_config', ''));

            $this->application_deployment_queue->addLogEntry('');
            $this->application_deployment_queue->addLogEntry("❌ ERROR: Container exited with code {$exitCode}", type: 'stderr');
            $this->application_deployment_queue->addLogEntry('');
            $this->application_deployment_queue->addLogEntry("Meaning: {$meaning}", type: 'stderr');

            if (! empty($config)) {
                $this->application_deployment_queue->addLogEntry("Container config: {$config}", type: 'stderr');
            }

            $this->application_deployment_queue->addLogEntry('');
            if ($exitCode === 127) {
                $this->application_deployment_queue->addLogEntry('Solutions:', type: 'stderr');
                $this->application_deployment_queue->addLogEntry('1. Check that the start command exists in the container', type: 'stderr');
                $this->application_deployment_queue->addLogEntry('2. Verify your Dockerfile CMD/ENTRYPOINT', type: 'stderr');
                $this->application_deployment_queue->addLogEntry('3. Try setting a custom Start Command in Application settings', type: 'stderr');
            } elseif ($exitCode === 126) {
                $this->application_deployment_queue->addLogEntry('Solutions:', type: 'stderr');
                $this->application_deployment_queue->addLogEntry('1. Make the start script executable: chmod +x in your Dockerfile', type: 'stderr');
                $this->application_deployment_queue->addLogEntry('2. Check file permissions in the container', type: 'stderr');
            } else {
                $this->application_deployment_queue->addLogEntry('The application crashed immediately without producing any logs.', type: 'stderr');
                $this->application_deployment_queue->addLogEntry('Try running the container locally to debug: docker run --rm <image>', type: 'stderr');
            }
        } else {
            $this->application_deployment_queue->addLogEntry('');
            $this->application_deployment_queue->addLogEntry('❌ Container failed to start and produced no logs.', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('');
            $this->application_deployment_queue->addLogEntry('Common issues:', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('- Missing environment variables', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('- Incorrect start command / Dockerfile CMD', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('- Missing dependencies in the Docker image', type: 'stderr');
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
        // Note: no /i flag on capture group — env var names must be UPPERCASE to avoid false positives like "is"
        if (preg_match_all('/([A-Z][A-Z0-9_]+(?:\s+and\s+[A-Z][A-Z0-9_]+)*)\s+(?i:must\s+be\s+(?:defined|set|provided))/', $logs, $matches)) {
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
        // Note: no /i on capture group — prevents false positives ("is", "or" etc.)
        if (preg_match_all('/([A-Z][A-Z0-9_]+)\s+(?i:(?:is\s+)?(?:required|not\s+set|not\s+defined|missing|undefined))/', $logs, $matches)) {
            foreach ($matches[1] as $var) {
                $var = trim($var);
                if (! empty($var) && ! in_array($var, $missingVars)) {
                    $missingVars[] = $var;
                }
            }
        }

        // Pattern: "Missing environment variable: VAR_NAME" or "Missing required env var VAR_NAME"
        if (preg_match_all('/(?i:missing\s+(?:required\s+)?(?:environment\s+)?(?:variable|env\s+var)s?)[:\s]+([A-Z][A-Z0-9_]+)/', $logs, $matches)) {
            foreach ($matches[1] as $var) {
                $var = trim($var);
                if (! empty($var) && ! in_array($var, $missingVars)) {
                    $missingVars[] = $var;
                }
            }
        }

        // Pattern: "process.env.VAR_NAME is undefined" (JavaScript)
        if (preg_match_all('/process\.env\.([A-Z][A-Z0-9_]+)\s+(?i:is\s+undefined)/', $logs, $matches)) {
            foreach ($matches[1] as $var) {
                $var = trim($var);
                if (! empty($var) && ! in_array($var, $missingVars)) {
                    $missingVars[] = $var;
                }
            }
        }

        // Pattern: "Config validation error: VAR_NAME should not be empty"
        if (preg_match_all('/(?i:(?:Config|Configuration|Validation)\s+(?:validation\s+)?error[^:]*:)\s*([A-Z][A-Z0-9_]+)\s+(?i:(?:should\s+not\s+be\s+empty|is\s+required))/', $logs, $matches)) {
            foreach ($matches[1] as $var) {
                $var = trim($var);
                if (! empty($var) && ! in_array($var, $missingVars)) {
                    $missingVars[] = $var;
                }
            }
        }

        // Pattern: Python/Django style "ImproperlyConfigured: Set the VAR_NAME environment variable"
        if (preg_match_all('/(?i:set\s+the\s+)([A-Z][A-Z0-9_]+)(?i:\s+environment\s+variable)/', $logs, $matches)) {
            foreach ($matches[1] as $var) {
                $var = trim($var);
                if (! empty($var) && ! in_array($var, $missingVars)) {
                    $missingVars[] = $var;
                }
            }
        }

        // Pattern: Quoted variable names — "VAR_NAME" or 'VAR_NAME' followed by error keywords
        // Handles mixed-case names since quotes disambiguate from English words
        if (preg_match_all('/["\']([A-Z][A-Z0-9_]+)["\']\s+(?i:(?:is\s+)?(?:required|not\s+set|not\s+defined|not\s+configured|missing|undefined))/', $logs, $matches)) {
            foreach ($matches[1] as $var) {
                $var = trim($var);
                if (! empty($var) && ! in_array($var, $missingVars)) {
                    $missingVars[] = $var;
                }
            }
        }

        // Pattern: Error keyword followed by quoted variable — missing "VAR_NAME" / required 'VAR_NAME'
        if (preg_match_all('/(?i:(?:missing|required|undefined|not\s+found|not\s+set|not\s+configured)\s+(?:(?:environment\s+)?(?:variable|env\s+var|config)\s+)?)["\']([A-Z][A-Z0-9_]+)["\']/', $logs, $matches)) {
            foreach ($matches[1] as $var) {
                $var = trim($var);
                if (! empty($var) && ! in_array($var, $missingVars)) {
                    $missingVars[] = $var;
                }
            }
        }

        // Filter out false positives — generic ALL-CAPS words that could match env var patterns
        // Most are prevented by removing /i flag, but keep as a safety net
        $falsePositives = ['FIELD', 'THIS', 'ERROR', 'VALUE', 'INPUT', 'TYPE', 'STRING', 'INTEGER', 'BOOLEAN', 'NULL', 'TRUE', 'FALSE', 'NONE'];
        $missingVars = array_values(array_filter($missingVars, fn ($var) => ! in_array($var, $falsePositives)));

        return $missingVars;
    }
}
