<?php

namespace Tests\Unit\Jobs;

use App\Jobs\CleanupHelperContainersJob;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Tests\TestCase;

/**
 * Unit tests for CleanupHelperContainersJob.
 *
 * Tests cover job configuration, constructor, and source-level assertions
 * for the orphan detection and active deployment guard logic.
 *
 * Integration paths (instant_remote_process_with_timeout) are covered in
 * Feature tests; these tests focus on structure without SSH.
 */
class CleanupHelperContainersJobTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Job configuration
    // -------------------------------------------------------------------------

    public function test_job_implements_should_queue(): void
    {
        $this->assertContains(ShouldQueue::class, class_implements(CleanupHelperContainersJob::class));
    }

    public function test_job_implements_should_be_encrypted(): void
    {
        $this->assertContains(ShouldBeEncrypted::class, class_implements(CleanupHelperContainersJob::class));
    }

    public function test_job_implements_should_be_unique(): void
    {
        $this->assertContains(ShouldBeUnique::class, class_implements(CleanupHelperContainersJob::class));
    }

    public function test_job_has_tries_set_to_1(): void
    {
        $reflection = new \ReflectionClass(CleanupHelperContainersJob::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(1, $defaults['tries']);
    }

    public function test_job_has_120_second_timeout(): void
    {
        $reflection = new \ReflectionClass(CleanupHelperContainersJob::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(120, $defaults['timeout']);
    }

    // -------------------------------------------------------------------------
    // Active deployment guard — source-level assertions
    // -------------------------------------------------------------------------

    public function test_handle_queries_in_progress_and_queued_deployments(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupHelperContainersJob.php'));

        // Must check for both in-progress and queued deployments before removing containers
        $this->assertStringContainsString('IN_PROGRESS', $source);
        $this->assertStringContainsString('QUEUED', $source);
    }

    public function test_handle_skips_containers_belonging_to_active_deployments(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupHelperContainersJob.php'));

        // Active deployment containers must be preserved, not removed
        $this->assertStringContainsString('$isActiveDeployment', $source);
        $this->assertStringContainsString('continue', $source);
    }

    public function test_handle_filters_containers_by_helper_image(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupHelperContainersJob.php'));

        // Must filter to only helper image containers, not all containers
        $this->assertStringContainsString('helper_image', $source);
        $this->assertStringContainsString('contains(', $source);
    }

    public function test_handle_uses_docker_container_ps_to_list_containers(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupHelperContainersJob.php'));

        $this->assertStringContainsString('docker container ps', $source);
    }

    public function test_handle_removes_orphaned_containers_with_docker_rm(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupHelperContainersJob.php'));

        // Orphaned containers must be forcefully removed
        $this->assertStringContainsString('docker container rm -f', $source);
    }

    // -------------------------------------------------------------------------
    // Scoping by server — critical isolation check
    // -------------------------------------------------------------------------

    public function test_handle_scopes_deployment_query_to_server(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupHelperContainersJob.php'));

        // Must filter deployments to the specific server, not all servers
        $this->assertStringContainsString('server_id', $source);
        $this->assertStringContainsString('$this->server->id', $source);
    }

    // -------------------------------------------------------------------------
    // Error handling
    // -------------------------------------------------------------------------

    public function test_handle_wraps_logic_in_try_catch_throwable(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupHelperContainersJob.php'));

        $this->assertStringContainsString('catch (\Throwable $e)', $source);
    }

    public function test_handle_sends_internal_notification_on_failure(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupHelperContainersJob.php'));

        // Failures must be surfaced via internal notification, not silently swallowed
        $this->assertStringContainsString('send_internal_notification(', $source);
    }

    public function test_failed_method_exists_and_logs_permanent_failure(): void
    {
        $reflection = new \ReflectionClass(CleanupHelperContainersJob::class);

        $this->assertTrue($reflection->hasMethod('failed'));

        $source = file_get_contents(app_path('Jobs/CleanupHelperContainersJob.php'));
        $this->assertStringContainsString('public function failed(', $source);
        $this->assertStringContainsString('Log::error(', $source);
    }

    // -------------------------------------------------------------------------
    // Logging
    // -------------------------------------------------------------------------

    public function test_handle_logs_active_deployments_before_processing(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupHelperContainersJob.php'));

        $this->assertStringContainsString('CleanupHelperContainersJob - Active deployments', $source);
    }

    public function test_handle_logs_when_skipping_active_deployment_container(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupHelperContainersJob.php'));

        $this->assertStringContainsString('Skipping active deployment container', $source);
    }

    public function test_handle_logs_when_removing_orphaned_container(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupHelperContainersJob.php'));

        $this->assertStringContainsString('Removing orphaned helper container', $source);
    }
}
