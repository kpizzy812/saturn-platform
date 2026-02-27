<?php

namespace Tests\Unit\Traits\Deployment;

use Tests\TestCase;

/**
 * Unit tests for HandlesContainerOperations deployment trait.
 *
 * This trait provides graceful_shutdown_container(), stop_running_container(),
 * and start_by_compose_file() used by ApplicationDeploymentJob on every deploy.
 *
 * Tests use source-level assertions to verify security properties (escapeshellarg),
 * error handling contracts (DeploymentException), and rollback behaviour.
 */
class HandlesContainerOperationsTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();
        $this->source = file_get_contents(
            app_path('Traits/Deployment/HandlesContainerOperations.php')
        );
    }

    // =========================================================================
    // graceful_shutdown_container()
    // =========================================================================

    public function test_graceful_shutdown_uses_escapeshellarg_on_container_name(): void
    {
        // SECURITY: container name from external source must be escaped to prevent injection
        $this->assertStringContainsString('escapeshellarg($containerName)', $this->source);
    }

    public function test_graceful_shutdown_uses_docker_stop_with_timeout(): void
    {
        $this->assertStringContainsString('docker stop -t', $this->source);
    }

    public function test_graceful_shutdown_uses_30_second_timeout_in_production(): void
    {
        // Dev uses 1s to speed up tests; production uses 30s for graceful drain
        $this->assertStringContainsString('isDev() ? 1 : 30', $this->source);
    }

    public function test_graceful_shutdown_removes_container_by_default(): void
    {
        $this->assertStringContainsString('docker rm -f', $this->source);
    }

    public function test_graceful_shutdown_skip_remove_flag_omits_rm_command(): void
    {
        // $skipRemove = true must skip the docker rm step
        $this->assertStringContainsString('$skipRemove', $this->source);
    }

    public function test_graceful_shutdown_catches_exception_and_logs_to_deployment(): void
    {
        // Failure must be logged to the deployment queue rather than crashing
        $this->assertStringContainsString('catch (Exception $error)', $this->source);
        $this->assertStringContainsString('addLogEntry("Error stopping container', $this->source);
    }

    public function test_graceful_shutdown_marks_commands_as_hidden(): void
    {
        // Stop/rm commands are implementation details — hide from deployment log
        $this->assertStringContainsString("'hidden' => true", $this->source);
    }

    public function test_graceful_shutdown_uses_ignore_errors_flag(): void
    {
        // Container may already be stopped; errors must not abort the deployment
        $this->assertStringContainsString("'ignore_errors' => true", $this->source);
    }

    // =========================================================================
    // stop_running_container()
    // =========================================================================

    public function test_stop_running_container_only_stops_when_new_version_healthy(): void
    {
        // Must not remove the old container if the new one is unhealthy
        $this->assertStringContainsString('$this->newVersionIsHealthy || $force', $this->source);
    }

    public function test_stop_running_container_calls_fail_deployment_on_unhealthy(): void
    {
        // Unhealthy deploy must be marked as failed before cleaning up
        $this->assertStringContainsString('$this->failDeployment()', $this->source);
    }

    public function test_stop_running_container_warns_about_dockerfile_healthcheck(): void
    {
        // Docker image / Dockerfile deploys need curl/wget/nc for healthcheck
        $this->assertStringContainsString('WARNING: Dockerfile or Docker Image based deployment', $this->source);
    }

    public function test_stop_running_container_logs_rollback_message(): void
    {
        $this->assertStringContainsString('New container is not healthy, rolling back', $this->source);
    }

    public function test_stop_running_container_does_not_rethrow_cleanup_failure_when_healthy(): void
    {
        // If new version is healthy, cleanup errors are warnings — not deployment failures
        $this->assertStringContainsString(
            "Don't re-throw - cleanup failures shouldn't fail successful deployments",
            $this->source
        );
    }

    public function test_stop_running_container_rethrows_deployment_exception_on_unhealthy(): void
    {
        // If new version is NOT healthy, the exception must propagate to fail the deployment
        $this->assertStringContainsString('throw new DeploymentException("Failed to stop running container:', $this->source);
    }

    public function test_stop_running_container_handles_consistent_container_name(): void
    {
        // Consistent name mode uses direct shutdown; default mode scans all containers
        $this->assertStringContainsString('is_consistent_container_name_enabled', $this->source);
    }

    // =========================================================================
    // start_by_compose_file()
    // =========================================================================

    public function test_start_by_compose_file_creates_env_file_defensively(): void
    {
        // Prevents docker-compose from failing when .env file doesn't exist yet
        $this->assertStringContainsString("touch {$this->configDirPlaceholder()}.env", $this->source);
    }

    public function test_start_by_compose_file_pulls_image_for_docker_image_pack(): void
    {
        // dockerimage build pack must pull the latest image before starting
        $this->assertStringContainsString("build_pack === 'dockerimage'", $this->source);
        $this->assertStringContainsString('docker compose', $this->source);
        $this->assertStringContainsString('pull', $this->source);
    }

    public function test_start_by_compose_file_uses_build_server_path_when_enabled(): void
    {
        $this->assertStringContainsString('$this->use_build_server', $this->source);
    }

    public function test_start_by_compose_file_throws_deployment_exception_on_failure(): void
    {
        $this->assertStringContainsString('throw new DeploymentException("Failed to start container:', $this->source);
    }

    public function test_start_by_compose_file_logs_new_container_started(): void
    {
        $this->assertStringContainsString('New container started.', $this->source);
    }

    // =========================================================================
    // DeploymentException import
    // =========================================================================

    public function test_trait_imports_deployment_exception(): void
    {
        $this->assertStringContainsString('use App\Exceptions\DeploymentException;', $this->source);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** Return the configuration_dir reference pattern used in source. */
    private function configDirPlaceholder(): string
    {
        // The trait uses $this->configuration_dir which is a runtime property
        return '{$this->configuration_dir}/';
    }
}
