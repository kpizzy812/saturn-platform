<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SyncCloudflareRoutesJob;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Tests\TestCase;

class SyncCloudflareRoutesJobTest extends TestCase
{
    public function test_dispatches_with_correct_middleware(): void
    {
        $job = new SyncCloudflareRoutesJob;
        $middleware = $job->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
    }

    public function test_has_correct_retry_configuration(): void
    {
        $job = new SyncCloudflareRoutesJob;

        $this->assertEquals(3, $job->tries);
        $this->assertEquals([10, 30, 60], $job->backoff);
        $this->assertEquals(120, $job->timeout);
    }

    public function test_implements_should_be_encrypted(): void
    {
        $job = new SyncCloudflareRoutesJob;

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldBeEncrypted::class, $job);
    }

    public function test_implements_should_queue(): void
    {
        $job = new SyncCloudflareRoutesJob;

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }
}
