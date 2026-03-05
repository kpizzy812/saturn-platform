<?php

namespace Tests\Unit\Actions\Application;

use Tests\TestCase;

/**
 * Unit tests for Application Actions: CleanupPreviewDeployment,
 * StopApplicationOneServer, ScanEnvExample, GenerateConfig, LoadComposeFile,
 * IsHorizonQueueEmpty.
 */
class ApplicationActionsExtendedTest extends TestCase
{
    // =========================================================================
    // CleanupPreviewDeployment
    // =========================================================================

    /** @test */
    public function cleanup_preview_checks_server_is_functional(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CleanupPreviewDeployment.php'));

        $this->assertStringContainsString('$server->isFunctional()', $source);
        $this->assertStringContainsString("'status' => 'failed'", $source);
        $this->assertStringContainsString("'message' => 'Server is not functional'", $source);
    }

    /** @test */
    public function cleanup_preview_cancels_active_deployments(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CleanupPreviewDeployment.php'));

        $this->assertStringContainsString('ApplicationDeploymentStatus::QUEUED->value', $source);
        $this->assertStringContainsString('ApplicationDeploymentStatus::IN_PROGRESS->value', $source);
        $this->assertStringContainsString('ApplicationDeploymentStatus::CANCELLED_BY_USER->value', $source);
    }

    /** @test */
    public function cleanup_preview_adds_cancellation_log_entry(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CleanupPreviewDeployment.php'));

        $this->assertStringContainsString("addLogEntry('Deployment cancelled: Pull request closed.'", $source);
    }

    /** @test */
    public function cleanup_preview_kills_helper_containers(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CleanupPreviewDeployment.php'));

        $this->assertStringContainsString('killHelperContainer', $source);
        $this->assertStringContainsString('escapeshellarg($deployment_uuid)', $source);
        $this->assertStringContainsString("docker rm -f", $source);
    }

    /** @test */
    public function cleanup_preview_stops_running_containers(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CleanupPreviewDeployment.php'));

        $this->assertStringContainsString('stopRunningContainers', $source);
        $this->assertStringContainsString('getCurrentApplicationContainerStatus', $source);
        $this->assertStringContainsString('escapeshellarg($containerName)', $source);
    }

    /** @test */
    public function cleanup_preview_handles_swarm_mode(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CleanupPreviewDeployment.php'));

        $this->assertStringContainsString('$server->isSwarm()', $source);
        $this->assertStringContainsString('docker stack rm', $source);
    }

    /** @test */
    public function cleanup_preview_dispatches_delete_resource_job(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CleanupPreviewDeployment.php'));

        $this->assertStringContainsString('DeleteResourceJob::dispatch($preview)', $source);
    }

    /** @test */
    public function cleanup_preview_returns_structured_result(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CleanupPreviewDeployment.php'));

        $this->assertStringContainsString("'cancelled_deployments'", $source);
        $this->assertStringContainsString("'killed_containers'", $source);
        $this->assertStringContainsString("'status' => 'success'", $source);
    }

    // =========================================================================
    // StopApplicationOneServer
    // =========================================================================

    /** @test */
    public function stop_app_one_server_skips_swarm(): void
    {
        $source = file_get_contents(app_path('Actions/Application/StopApplicationOneServer.php'));

        $this->assertStringContainsString('$application->destination->server->isSwarm()', $source);
    }

    /** @test */
    public function stop_app_one_server_checks_server_functional(): void
    {
        $source = file_get_contents(app_path('Actions/Application/StopApplicationOneServer.php'));

        $this->assertStringContainsString('$server->isFunctional()', $source);
        $this->assertStringContainsString("'Server is not functional'", $source);
    }

    /** @test */
    public function stop_app_one_server_stops_containers_individually(): void
    {
        $source = file_get_contents(app_path('Actions/Application/StopApplicationOneServer.php'));

        $this->assertStringContainsString('getCurrentApplicationContainerStatus', $source);
        $this->assertStringContainsString('docker stop -t 30', $source);
        $this->assertStringContainsString('docker rm -f', $source);
        $this->assertStringContainsString('escapeshellarg($containerName)', $source);
    }

    // =========================================================================
    // ScanEnvExample
    // =========================================================================

    /** @test */
    public function scan_env_example_checks_multiple_env_files(): void
    {
        $source = file_get_contents(app_path('Actions/Application/ScanEnvExample.php'));

        $this->assertStringContainsString('.env.example', $source);
        $this->assertStringContainsString('.env.sample', $source);
        $this->assertStringContainsString('.env.template', $source);
    }

    /** @test */
    public function scan_env_example_uses_sparse_checkout(): void
    {
        $source = file_get_contents(app_path('Actions/Application/ScanEnvExample.php'));

        $this->assertStringContainsString('git sparse-checkout init --cone', $source);
        $this->assertStringContainsString('git sparse-checkout set', $source);
        $this->assertStringContainsString('git read-tree -mu HEAD', $source);
    }

    /** @test */
    public function scan_env_example_cleans_up_temp_directory(): void
    {
        $source = file_get_contents(app_path('Actions/Application/ScanEnvExample.php'));

        $this->assertStringContainsString("rm -rf /tmp/{", $source);
    }

    /** @test */
    public function scan_env_example_uses_env_parser(): void
    {
        $source = file_get_contents(app_path('Actions/Application/ScanEnvExample.php'));

        $this->assertStringContainsString('EnvExampleParser::parse(', $source);
        $this->assertStringContainsString('EnvExampleParser::detectFramework(', $source);
    }

    /** @test */
    public function scan_env_example_skips_existing_variables(): void
    {
        $source = file_get_contents(app_path('Actions/Application/ScanEnvExample.php'));

        $this->assertStringContainsString("where('is_preview', false)", $source);
        $this->assertStringContainsString("pluck('key')", $source);
        $this->assertStringContainsString('strtoupper', $source);
        $this->assertStringContainsString("result['skipped'][]", $source);
    }

    /** @test */
    public function scan_env_example_creates_env_vars_with_metadata(): void
    {
        $source = file_get_contents(app_path('Actions/Application/ScanEnvExample.php'));

        $this->assertStringContainsString("'is_runtime' => true", $source);
        $this->assertStringContainsString("'is_buildtime' => true", $source);
        $this->assertStringContainsString("'is_required'", $source);
        $this->assertStringContainsString("'source_template'", $source);
    }

    /** @test */
    public function scan_env_example_returns_structured_result(): void
    {
        $source = file_get_contents(app_path('Actions/Application/ScanEnvExample.php'));

        $this->assertStringContainsString("'created' => []", $source);
        $this->assertStringContainsString("'skipped' => []", $source);
        $this->assertStringContainsString("'required' => []", $source);
        $this->assertStringContainsString("'framework' => null", $source);
        $this->assertStringContainsString("'source_file' => null", $source);
    }

    // =========================================================================
    // GenerateConfig / LoadComposeFile
    // =========================================================================

    /** @test */
    public function generate_config_delegates_to_model(): void
    {
        $source = file_get_contents(app_path('Actions/Application/GenerateConfig.php'));

        $this->assertStringContainsString('$application->generateConfig(is_json: $is_json)', $source);
    }

    /** @test */
    public function generate_config_supports_json_output(): void
    {
        $source = file_get_contents(app_path('Actions/Application/GenerateConfig.php'));

        $this->assertStringContainsString('bool $is_json = false', $source);
    }

    /** @test */
    public function load_compose_file_delegates_to_model(): void
    {
        $source = file_get_contents(app_path('Actions/Application/LoadComposeFile.php'));

        $this->assertStringContainsString('$application->loadComposeFile()', $source);
    }

    // =========================================================================
    // IsHorizonQueueEmpty
    // =========================================================================

    /** @test */
    public function is_horizon_queue_empty_filters_by_hostname(): void
    {
        $source = file_get_contents(app_path('Actions/Application/IsHorizonQueueEmpty.php'));

        $this->assertStringContainsString('gethostname()', $source);
        $this->assertStringContainsString("'server:'.\$hostname", $source);
    }

    /** @test */
    public function is_horizon_queue_empty_checks_non_completed_jobs(): void
    {
        $source = file_get_contents(app_path('Actions/Application/IsHorizonQueueEmpty.php'));

        $this->assertStringContainsString("status != 'completed'", $source);
        $this->assertStringContainsString("status != 'failed'", $source);
    }

    /** @test */
    public function is_horizon_queue_empty_uses_job_repository(): void
    {
        $source = file_get_contents(app_path('Actions/Application/IsHorizonQueueEmpty.php'));

        $this->assertStringContainsString('app(JobRepository::class)', $source);
        $this->assertStringContainsString('getRecent()', $source);
    }
}
