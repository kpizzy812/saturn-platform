<?php

namespace Tests\Unit\Actions\Docker;

use Tests\TestCase;

/**
 * Unit tests for Docker Actions: GetContainersStatus, CleanupDocker.
 * And Proxy Actions: CheckProxy.
 */
class DockerActionsTest extends TestCase
{
    // =========================================================================
    // GetContainersStatus
    // =========================================================================

    /** @test */
    public function get_containers_status_uses_lock(): void
    {
        $source = file_get_contents(app_path('Actions/Docker/GetContainersStatus.php'));

        $this->assertStringContainsString('Status check already in progress.', $source);
    }

    /** @test */
    public function get_containers_status_checks_server_functional(): void
    {
        $source = file_get_contents(app_path('Actions/Docker/GetContainersStatus.php'));

        $this->assertStringContainsString('Server is not functional.', $source);
    }

    /** @test */
    public function get_containers_status_reads_saturn_labels(): void
    {
        $source = file_get_contents(app_path('Actions/Docker/GetContainersStatus.php'));

        $this->assertStringContainsString('saturn.name', $source);
        $this->assertStringContainsString('saturn.applicationId', $source);
        $this->assertStringContainsString('saturn.serviceId', $source);
        $this->assertStringContainsString('saturn.pullRequestId', $source);
    }

    /** @test */
    public function get_containers_status_reads_docker_state(): void
    {
        $source = file_get_contents(app_path('Actions/Docker/GetContainersStatus.php'));

        $this->assertStringContainsString('State.Status', $source);
        $this->assertStringContainsString('State.Health.Status', $source);
    }

    /** @test */
    public function get_containers_status_aggregates_application_status(): void
    {
        $source = file_get_contents(app_path('Actions/Docker/GetContainersStatus.php'));

        $this->assertStringContainsString('aggregateApplicationStatus', $source);
        $this->assertStringContainsString('ContainerStatusAggregator', $source);
    }

    /** @test */
    public function get_containers_status_aggregates_service_statuses(): void
    {
        $source = file_get_contents(app_path('Actions/Docker/GetContainersStatus.php'));

        $this->assertStringContainsString('aggregateServiceContainerStatuses', $source);
    }

    /** @test */
    public function get_containers_status_checks_active_deployments(): void
    {
        $source = file_get_contents(app_path('Actions/Docker/GetContainersStatus.php'));

        $this->assertStringContainsString('hasActiveOrRecentDeployment', $source);
    }

    /** @test */
    public function get_containers_status_tracks_restart_info(): void
    {
        $source = file_get_contents(app_path('Actions/Docker/GetContainersStatus.php'));

        $this->assertStringContainsString('last_restart_at', $source);
        $this->assertStringContainsString('restart_count', $source);
        $this->assertStringContainsString('last_restart_type', $source);
    }

    /** @test */
    public function get_containers_status_handles_exited_status(): void
    {
        $source = file_get_contents(app_path('Actions/Docker/GetContainersStatus.php'));

        $this->assertStringContainsString("'exited'", $source);
    }

    // =========================================================================
    // CleanupDocker
    // =========================================================================

    /** @test */
    public function cleanup_docker_prunes_containers(): void
    {
        $source = file_get_contents(app_path('Actions/Server/CleanupDocker.php'));

        $this->assertStringContainsString('docker container prune -f', $source);
    }

    /** @test */
    public function cleanup_docker_filters_by_saturn_labels(): void
    {
        $source = file_get_contents(app_path('Actions/Server/CleanupDocker.php'));

        $this->assertStringContainsString('saturn.managed', $source);
    }

    /** @test */
    public function cleanup_docker_prunes_builder_cache(): void
    {
        $source = file_get_contents(app_path('Actions/Server/CleanupDocker.php'));

        $this->assertStringContainsString('docker builder prune -af', $source);
    }

    /** @test */
    public function cleanup_docker_prunes_images(): void
    {
        $source = file_get_contents(app_path('Actions/Server/CleanupDocker.php'));

        $this->assertStringContainsString('docker images', $source);
        $this->assertStringContainsString('docker rmi', $source);
    }

    /** @test */
    public function cleanup_docker_supports_volume_prune(): void
    {
        $source = file_get_contents(app_path('Actions/Server/CleanupDocker.php'));

        $this->assertStringContainsString('docker volume prune', $source);
    }

    /** @test */
    public function cleanup_docker_supports_network_prune(): void
    {
        $source = file_get_contents(app_path('Actions/Server/CleanupDocker.php'));

        $this->assertStringContainsString('docker network prune', $source);
    }

    /** @test */
    public function cleanup_docker_keeps_helper_images(): void
    {
        $source = file_get_contents(app_path('Actions/Server/CleanupDocker.php'));

        $this->assertStringContainsString('coollabsio/coolify-helper', $source);
    }

    /** @test */
    public function cleanup_docker_removes_pr_images(): void
    {
        $source = file_get_contents(app_path('Actions/Server/CleanupDocker.php'));

        $this->assertStringContainsString('pr-', $source);
    }

    /** @test */
    public function cleanup_docker_cleans_application_images(): void
    {
        $source = file_get_contents(app_path('Actions/Server/CleanupDocker.php'));

        $this->assertStringContainsString('cleanupApplicationImages', $source);
        $this->assertStringContainsString('buildImagePruneCommand', $source);
    }

    // =========================================================================
    // CheckProxy
    // =========================================================================

    /** @test */
    public function check_proxy_validates_port_range(): void
    {
        $source = file_get_contents(app_path('Actions/Proxy/CheckProxy.php'));

        $this->assertStringContainsString('validatePort', $source);
        $this->assertStringContainsString('Invalid port number', $source);
    }

    /** @test */
    public function check_proxy_checks_port_conflicts(): void
    {
        $source = file_get_contents(app_path('Actions/Proxy/CheckProxy.php'));

        $this->assertStringContainsString('checkPortConflicts', $source);
        $this->assertStringContainsString('port_conflict', $source);
        $this->assertStringContainsString('port_free', $source);
        $this->assertStringContainsString('proxy_using_port', $source);
    }

    /** @test */
    public function check_proxy_detects_saturn_proxy_container(): void
    {
        $source = file_get_contents(app_path('Actions/Proxy/CheckProxy.php'));

        $this->assertStringContainsString('saturn-proxy', $source);
    }

    /** @test */
    public function check_proxy_handles_custom_proxy_type(): void
    {
        $source = file_get_contents(app_path('Actions/Proxy/CheckProxy.php'));

        $this->assertStringContainsString('Proxy should not run. You selected the Custom Proxy.', $source);
    }

    /** @test */
    public function check_proxy_supports_traefik_and_caddy(): void
    {
        $source = file_get_contents(app_path('Actions/Proxy/CheckProxy.php'));

        $this->assertStringContainsString('services.traefik.ports', $source);
        $this->assertStringContainsString('services.caddy.ports', $source);
    }

    /** @test */
    public function check_proxy_supports_cloudflare_tunnel(): void
    {
        $source = file_get_contents(app_path('Actions/Proxy/CheckProxy.php'));

        $this->assertStringContainsString('is_cloudflare_tunnel', $source);
    }

    /** @test */
    public function check_proxy_detects_dual_stack_ipv6(): void
    {
        $source = file_get_contents(app_path('Actions/Proxy/CheckProxy.php'));

        $this->assertStringContainsString('0.0.0.0:', $source);
        $this->assertStringContainsString(':::', $source);
    }

    /** @test */
    public function check_proxy_uses_multiple_port_check_tools(): void
    {
        $source = file_get_contents(app_path('Actions/Proxy/CheckProxy.php'));

        $this->assertStringContainsString('LISTEN', $source);
    }
}
