<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ServerStorageCheckJob;
use App\Models\Server;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Laravel\Horizon\Contracts\Silenced;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for ServerStorageCheckJob.
 *
 * These tests focus on testing the job's configuration and constructor.
 * Full integration tests for handle() require SSH/Docker and are in tests/Feature/.
 */
class ServerStorageCheckJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_job_implements_required_interfaces(): void
    {
        $interfaces = class_implements(ServerStorageCheckJob::class);

        $this->assertTrue(in_array(ShouldBeEncrypted::class, $interfaces));
        $this->assertTrue(in_array(ShouldQueue::class, $interfaces));
        $this->assertTrue(in_array(Silenced::class, $interfaces));
    }

    public function test_job_has_correct_timeout_and_tries(): void
    {
        $server = Mockery::mock(Server::class)->makePartial();

        $job = new ServerStorageCheckJob($server);

        $this->assertEquals(60, $job->timeout);
        $this->assertEquals(1, $job->tries);
    }

    public function test_job_stores_server(): void
    {
        $server = Mockery::mock(Server::class)->makePartial();

        $job = new ServerStorageCheckJob($server);

        $this->assertSame($server, $job->server);
    }

    public function test_job_defaults_percentage_to_null(): void
    {
        $server = Mockery::mock(Server::class)->makePartial();

        $job = new ServerStorageCheckJob($server);

        $this->assertNull($job->percentage);
    }

    public function test_job_accepts_percentage_as_int(): void
    {
        $server = Mockery::mock(Server::class)->makePartial();

        $job = new ServerStorageCheckJob($server, 85);

        $this->assertEquals(85, $job->percentage);
    }

    public function test_job_accepts_percentage_as_string(): void
    {
        $server = Mockery::mock(Server::class)->makePartial();

        $job = new ServerStorageCheckJob($server, '75');

        $this->assertEquals('75', $job->percentage);
    }

    public function test_job_backoff_returns_1_in_dev(): void
    {
        // Mock isDev() to return true
        $this->markTestSkipped('Cannot easily mock global isDev() helper without modifying source');
    }

    public function test_job_backoff_returns_3_in_production(): void
    {
        // Mock isDev() to return false
        $this->markTestSkipped('Cannot easily mock global isDev() helper without modifying source');
    }
}
