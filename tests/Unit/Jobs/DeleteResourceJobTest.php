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
}
