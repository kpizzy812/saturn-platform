<?php

namespace Tests\Unit\Jobs;

use App\Jobs\DockerCleanupJob;
use App\Models\Server;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for DockerCleanupJob.
 *
 * These tests focus on testing the job's configuration, middleware, and constructor.
 * Full integration tests for handle() require SSH/Docker and are in tests/Feature/.
 */
class DockerCleanupJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_job_implements_required_interfaces(): void
    {
        $interfaces = class_implements(DockerCleanupJob::class);

        $this->assertTrue(in_array(ShouldBeEncrypted::class, $interfaces));
        $this->assertTrue(in_array(ShouldQueue::class, $interfaces));
    }

    public function test_job_has_correct_timeout_and_tries(): void
    {
        $server = Mockery::mock(Server::class)->makePartial();

        $job = new DockerCleanupJob($server);

        $this->assertEquals(600, $job->timeout);
        $this->assertEquals(1, $job->tries);
    }

    public function test_job_has_without_overlapping_middleware(): void
    {
        $server = Mockery::mock(Server::class)->makePartial();
        $server->uuid = 'test-uuid-123';

        $job = new DockerCleanupJob($server);
        $middleware = $job->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
    }

    public function test_job_stores_server(): void
    {
        $server = Mockery::mock(Server::class)->makePartial();

        $job = new DockerCleanupJob($server);

        $this->assertSame($server, $job->server);
    }

    public function test_job_defaults_all_flags_to_false(): void
    {
        $server = Mockery::mock(Server::class)->makePartial();

        $job = new DockerCleanupJob($server);

        $this->assertFalse($job->manualCleanup);
        $this->assertFalse($job->deleteUnusedVolumes);
        $this->assertFalse($job->deleteUnusedNetworks);
    }

    public function test_job_accepts_custom_flags(): void
    {
        $server = Mockery::mock(Server::class)->makePartial();

        $job = new DockerCleanupJob(
            server: $server,
            manualCleanup: true,
            deleteUnusedVolumes: true,
            deleteUnusedNetworks: true
        );

        $this->assertTrue($job->manualCleanup);
        $this->assertTrue($job->deleteUnusedVolumes);
        $this->assertTrue($job->deleteUnusedNetworks);
    }

    public function test_job_initializes_properties(): void
    {
        $server = Mockery::mock(Server::class)->makePartial();

        $job = new DockerCleanupJob($server);

        $this->assertNull($job->usageBefore);
        $this->assertNull($job->execution_log);
    }
}
