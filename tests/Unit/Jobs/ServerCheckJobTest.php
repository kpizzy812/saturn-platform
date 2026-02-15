<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ServerCheckJob;
use App\Models\Server;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for ServerCheckJob.
 *
 * These tests focus on testing the job's configuration, middleware, and constructor.
 * Full integration tests for handle() require SSH/Docker and are in tests/Feature/.
 */
class ServerCheckJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_job_implements_required_interfaces(): void
    {
        $interfaces = class_implements(ServerCheckJob::class);

        $this->assertTrue(in_array(ShouldBeEncrypted::class, $interfaces));
        $this->assertTrue(in_array(ShouldQueue::class, $interfaces));
    }

    public function test_job_has_correct_timeout_and_tries(): void
    {
        $server = Mockery::mock(Server::class)->makePartial();

        $job = new ServerCheckJob($server);

        $this->assertEquals(60, $job->timeout);
        $this->assertEquals(1, $job->tries);
    }

    public function test_job_has_without_overlapping_middleware(): void
    {
        $server = Mockery::mock(Server::class)->makePartial();
        $server->uuid = 'test-uuid-456';

        $job = new ServerCheckJob($server);
        $middleware = $job->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
    }

    public function test_job_stores_server(): void
    {
        $server = Mockery::mock(Server::class)->makePartial();

        $job = new ServerCheckJob($server);

        $this->assertSame($server, $job->server);
    }

    public function test_job_initializes_containers_property(): void
    {
        $server = Mockery::mock(Server::class)->makePartial();

        $job = new ServerCheckJob($server);

        $this->assertNull($job->containers);
    }
}
