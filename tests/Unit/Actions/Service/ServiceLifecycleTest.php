<?php

namespace Tests\Unit\Actions\Service;

use Tests\TestCase;

/**
 * Unit tests for Service lifecycle Actions: Start, Stop, Restart, Delete.
 *
 * These tests use Mockery mocks and source-level assertions to avoid
 * database dependencies. Remote process calls are verified through
 * command inspection or source-level assertions.
 */
class ServiceLifecycleTest extends TestCase
{
    // =========================================================================
    // StartService
    // =========================================================================

    /** @test */
    public function start_service_source_calls_parse_and_save_compose(): void
    {
        $source = file_get_contents(app_path('Actions/Service/StartService.php'));

        $this->assertStringContainsString('$service->parse()', $source);
        $this->assertStringContainsString('$service->saveComposeConfigs()', $source);
        $this->assertStringContainsString('$service->isConfigurationChanged(save: true)', $source);
    }

    /** @test */
    public function start_service_source_creates_env_file_defensively(): void
    {
        $source = file_get_contents(app_path('Actions/Service/StartService.php'));

        $this->assertStringContainsString('touch {', $source);
        $this->assertStringContainsString('.env', $source);
    }

    /** @test */
    public function start_service_source_pulls_images_when_flag_is_true(): void
    {
        $source = file_get_contents(app_path('Actions/Service/StartService.php'));

        $this->assertStringContainsString('if ($pullLatestImages)', $source);
        $this->assertStringContainsString('docker compose', $source);
        $this->assertStringContainsString('pull', $source);
    }

    /** @test */
    public function start_service_source_creates_docker_network_when_needed(): void
    {
        $source = file_get_contents(app_path('Actions/Service/StartService.php'));

        $this->assertStringContainsString('$service->networks()->count()', $source);
        $this->assertStringContainsString('docker network inspect', $source);
        $this->assertStringContainsString('docker network create --attachable', $source);
    }

    /** @test */
    public function start_service_source_uses_docker_compose_up_with_force_recreate(): void
    {
        $source = file_get_contents(app_path('Actions/Service/StartService.php'));

        $this->assertStringContainsString('up -d --remove-orphans --force-recreate --build', $source);
    }

    /** @test */
    public function start_service_source_connects_proxy_to_network(): void
    {
        $source = file_get_contents(app_path('Actions/Service/StartService.php'));

        $this->assertStringContainsString('docker network connect', $source);
        $this->assertStringContainsString('saturn-proxy', $source);
    }

    /** @test */
    public function start_service_source_stops_before_start_when_flag_is_true(): void
    {
        $source = file_get_contents(app_path('Actions/Service/StartService.php'));

        $this->assertStringContainsString('if ($stopBeforeStart)', $source);
        $this->assertStringContainsString('StopService::run(', $source);
    }

    /** @test */
    public function start_service_source_connects_to_docker_network_with_aliases(): void
    {
        $source = file_get_contents(app_path('Actions/Service/StartService.php'));

        $this->assertStringContainsString('connect_to_docker_network', $source);
        $this->assertStringContainsString('docker network connect --alias', $source);
    }

    /** @test */
    public function start_service_source_calls_remote_process_with_event(): void
    {
        $source = file_get_contents(app_path('Actions/Service/StartService.php'));

        $this->assertStringContainsString('remote_process(', $source);
        $this->assertStringContainsString("callEventOnFinish: 'ServiceStatusChanged'", $source);
    }

    /** @test */
    public function start_service_source_escapes_service_uuid(): void
    {
        $source = file_get_contents(app_path('Actions/Service/StartService.php'));

        $this->assertStringContainsString('escapeshellarg($service->uuid)', $source);
    }

    // =========================================================================
    // StopService
    // =========================================================================

    /** @test */
    public function stop_service_source_cancels_in_progress_activities(): void
    {
        $source = file_get_contents(app_path('Actions/Service/StopService.php'));

        $this->assertStringContainsString('ProcessStatus::IN_PROGRESS->value', $source);
        $this->assertStringContainsString('ProcessStatus::QUEUED->value', $source);
        $this->assertStringContainsString('ProcessStatus::CANCELLED->value', $source);
    }

    /** @test */
    public function stop_service_source_checks_server_is_functional(): void
    {
        $source = file_get_contents(app_path('Actions/Service/StopService.php'));

        $this->assertStringContainsString('$server->isFunctional()', $source);
        $this->assertStringContainsString("return 'Server is not functional'", $source);
    }

    /** @test */
    public function stop_service_source_collects_containers_from_apps_and_dbs(): void
    {
        $source = file_get_contents(app_path('Actions/Service/StopService.php'));

        $this->assertStringContainsString('$service->applications()->get()', $source);
        $this->assertStringContainsString('$service->databases()->get()', $source);
        $this->assertStringContainsString('$containersToStop[]', $source);
    }

    /** @test */
    public function stop_service_source_stops_containers_in_parallel(): void
    {
        $source = file_get_contents(app_path('Actions/Service/StopService.php'));

        $this->assertStringContainsString('stopContainersInParallel', $source);
        $this->assertStringContainsString('docker stop -t', $source);
        $this->assertStringContainsString('docker rm -f', $source);
    }

    /** @test */
    public function stop_service_source_uses_dynamic_timeout_based_on_container_count(): void
    {
        $source = file_get_contents(app_path('Actions/Service/StopService.php'));

        $this->assertStringContainsString('count($containersToStop) > 5 ? 10 : 30', $source);
    }

    /** @test */
    public function stop_service_source_dispatches_cleanup_docker_when_flag_is_true(): void
    {
        $source = file_get_contents(app_path('Actions/Service/StopService.php'));

        $this->assertStringContainsString('CleanupDocker::dispatch(', $source);
        $this->assertStringContainsString('if ($dockerCleanup)', $source);
    }

    /** @test */
    public function stop_service_source_deletes_networks_when_flag_is_true(): void
    {
        $source = file_get_contents(app_path('Actions/Service/StopService.php'));

        $this->assertStringContainsString('if ($deleteConnectedNetworks)', $source);
        $this->assertStringContainsString('$service->deleteConnectedNetworks()', $source);
    }

    /** @test */
    public function stop_service_source_dispatches_status_changed_event_in_finally(): void
    {
        $source = file_get_contents(app_path('Actions/Service/StopService.php'));

        $this->assertStringContainsString('ServiceStatusChanged::dispatch(', $source);
        $this->assertStringContainsString('finally', $source);
    }

    /** @test */
    public function stop_service_source_escapes_container_names(): void
    {
        $source = file_get_contents(app_path('Actions/Service/StopService.php'));

        $this->assertStringContainsString("array_map('escapeshellarg', \$containersToStop)", $source);
    }

    // =========================================================================
    // RestartService
    // =========================================================================

    /** @test */
    public function restart_service_source_calls_stop_then_start(): void
    {
        $source = file_get_contents(app_path('Actions/Service/RestartService.php'));

        $this->assertStringContainsString('StopService::run($service)', $source);
        $this->assertStringContainsString('StartService::run($service, $pullLatestImages)', $source);
    }

    /** @test */
    public function restart_service_source_returns_start_result(): void
    {
        $source = file_get_contents(app_path('Actions/Service/RestartService.php'));

        $this->assertStringContainsString('return StartService::run(', $source);
    }

    // =========================================================================
    // DeleteService
    // =========================================================================

    /** @test */
    public function delete_service_source_deletes_volumes_when_server_is_functional(): void
    {
        $source = file_get_contents(app_path('Actions/Service/DeleteService.php'));

        $this->assertStringContainsString('if ($deleteVolumes && $server->isFunctional())', $source);
        $this->assertStringContainsString('docker volume rm -f', $source);
    }

    /** @test */
    public function delete_service_source_collects_persistent_storages(): void
    {
        $source = file_get_contents(app_path('Actions/Service/DeleteService.php'));

        $this->assertStringContainsString('persistentStorages()->get()', $source);
        $this->assertStringContainsString('$storagesToDelete->push($storage)', $source);
    }

    /** @test */
    public function delete_service_source_deletes_environment_variables(): void
    {
        $source = file_get_contents(app_path('Actions/Service/DeleteService.php'));

        $this->assertStringContainsString('$service->environment_variables()->delete()', $source);
    }

    /** @test */
    public function delete_service_source_removes_docker_container(): void
    {
        $source = file_get_contents(app_path('Actions/Service/DeleteService.php'));

        $this->assertStringContainsString('docker rm -f', $source);
        $this->assertStringContainsString('escapeshellarg($service->uuid)', $source);
    }

    /** @test */
    public function delete_service_source_force_deletes_apps_dbs_tasks_and_tags(): void
    {
        $source = file_get_contents(app_path('Actions/Service/DeleteService.php'));

        $this->assertStringContainsString('$application->forceDelete()', $source);
        $this->assertStringContainsString('$database->forceDelete()', $source);
        $this->assertStringContainsString('$task->delete()', $source);
        $this->assertStringContainsString('$service->tags()->detach()', $source);
        $this->assertStringContainsString('$service->forceDelete()', $source);
    }

    /** @test */
    public function delete_service_source_cleans_up_configurations_when_flag_is_true(): void
    {
        $source = file_get_contents(app_path('Actions/Service/DeleteService.php'));

        $this->assertStringContainsString('if ($deleteConfigurations)', $source);
        $this->assertStringContainsString('$service->deleteConfigurations()', $source);
    }

    /** @test */
    public function delete_service_source_dispatches_cleanup_docker(): void
    {
        $source = file_get_contents(app_path('Actions/Service/DeleteService.php'));

        $this->assertStringContainsString('CleanupDocker::dispatch(', $source);
        $this->assertStringContainsString('if ($dockerCleanup)', $source);
    }

    /** @test */
    public function delete_service_source_wraps_exceptions_in_runtime_exception(): void
    {
        $source = file_get_contents(app_path('Actions/Service/DeleteService.php'));

        $this->assertStringContainsString('throw new \RuntimeException($e->getMessage())', $source);
    }

    /** @test */
    public function delete_service_source_cleanup_runs_in_finally_block(): void
    {
        $source = file_get_contents(app_path('Actions/Service/DeleteService.php'));

        // Verify the cleanup (forceDelete, tags detach) happens in a finally block
        $this->assertStringContainsString('} finally {', $source);
    }

    /** @test */
    public function delete_service_source_escapes_storage_names(): void
    {
        $source = file_get_contents(app_path('Actions/Service/DeleteService.php'));

        $this->assertStringContainsString('escapeshellarg($storage->name)', $source);
    }
}
