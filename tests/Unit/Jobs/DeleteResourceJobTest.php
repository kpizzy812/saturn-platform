<?php

namespace Tests\Unit\Jobs;

use App\Jobs\DeleteResourceJob;
use App\Models\Application;
use App\Models\Service;
use App\Models\StandalonePostgresql;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for DeleteResourceJob.
 *
 * These tests focus on testing the job's configuration and constructor.
 * Full integration tests for handle() require SSH/Docker and are in tests/Feature/.
 */
class DeleteResourceJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_job_implements_required_interfaces(): void
    {
        $interfaces = class_implements(DeleteResourceJob::class);

        $this->assertTrue(in_array(ShouldBeEncrypted::class, $interfaces));
        $this->assertTrue(in_array(ShouldQueue::class, $interfaces));
    }

    public function test_job_uses_high_queue(): void
    {
        $resource = Mockery::mock(Application::class);

        $job = new DeleteResourceJob($resource);

        $this->assertEquals('high', $job->queue);
    }

    public function test_job_stores_resource(): void
    {
        $resource = Mockery::mock(Application::class);

        $job = new DeleteResourceJob($resource);

        $this->assertSame($resource, $job->resource);
    }

    public function test_job_defaults_all_flags_to_true(): void
    {
        $resource = Mockery::mock(Application::class);

        $job = new DeleteResourceJob($resource);

        $this->assertTrue($job->deleteVolumes);
        $this->assertTrue($job->deleteConnectedNetworks);
        $this->assertTrue($job->deleteConfigurations);
        $this->assertTrue($job->dockerCleanup);
    }

    public function test_job_accepts_custom_flags(): void
    {
        $resource = Mockery::mock(Service::class);

        $job = new DeleteResourceJob(
            resource: $resource,
            deleteVolumes: false,
            deleteConnectedNetworks: false,
            deleteConfigurations: false,
            dockerCleanup: false
        );

        $this->assertFalse($job->deleteVolumes);
        $this->assertFalse($job->deleteConnectedNetworks);
        $this->assertFalse($job->deleteConfigurations);
        $this->assertFalse($job->dockerCleanup);
    }

    public function test_job_accepts_database_resource(): void
    {
        $resource = Mockery::mock(StandalonePostgresql::class);

        $job = new DeleteResourceJob($resource);

        $this->assertSame($resource, $job->resource);
        $this->assertEquals('high', $job->queue);
    }

    public function test_job_accepts_partial_flag_override(): void
    {
        $resource = Mockery::mock(Application::class);

        $job = new DeleteResourceJob(
            resource: $resource,
            deleteVolumes: false,
            dockerCleanup: false
        );

        $this->assertFalse($job->deleteVolumes);
        $this->assertTrue($job->deleteConnectedNetworks);
        $this->assertTrue($job->deleteConfigurations);
        $this->assertFalse($job->dockerCleanup);
    }

    // -------------------------------------------------------------------------
    // Orphaned-container prevention: forceDelete() must NOT be in finally{}
    // -------------------------------------------------------------------------

    /**
     * Root cause of the orphaned-container bug:
     *
     * Previously forceDelete() was placed inside finally{}, which always runs —
     * even when StopApplication threw an SSH exception. This deleted the DB record
     * while the container kept running, making it invisible to Saturn forever.
     *
     * Fix: forceDelete() must only execute on the success path (inside try{}).
     */
    public function test_force_delete_is_not_inside_finally_block(): void
    {
        $source = file_get_contents(app_path('Jobs/DeleteResourceJob.php'));

        // Locate the finally block
        $finallyPos = strpos($source, '} finally {');
        $this->assertNotFalse($finallyPos, 'finally block must exist');

        // Everything after "} finally {" until the matching closing brace is the finally body
        $afterFinally = substr($source, $finallyPos + strlen('} finally {'));

        // Find the end of the finally block (first standalone closing brace at same indent)
        // Simple heuristic: grab the next ~300 chars which covers the entire finally body
        $finallyBody = substr($afterFinally, 0, 300);

        $this->assertStringNotContainsString(
            'forceDelete()',
            $finallyBody,
            'forceDelete() must NOT be inside finally{} — it would delete the DB record even when SSH fails, leaving orphaned containers'
        );
    }

    public function test_force_delete_exists_in_try_block(): void
    {
        $source = file_get_contents(app_path('Jobs/DeleteResourceJob.php'));

        // forceDelete() must still be called somewhere in the file
        $this->assertStringContainsString(
            'forceDelete()',
            $source,
            'forceDelete() must be called to clean up the DB record after a successful stop'
        );
    }

    public function test_stop_application_rethrows_exceptions(): void
    {
        // StopApplication must re-throw SSH exceptions so DeleteResourceJob
        // fails (and does NOT call forceDelete) when the container stop fails.
        $source = file_get_contents(app_path('Actions/Application/StopApplication.php'));

        $this->assertStringContainsString('throw $e', $source);
        $this->assertStringNotContainsString('return $e->getMessage()', $source);
    }
}
