<?php

namespace Tests\Unit\Jobs;

use App\Jobs\MonitorCanaryDeploymentJob;
use App\Models\ApplicationDeploymentQueue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MonitorCanaryDeploymentJobTest extends TestCase
{
    #[Test]
    public function job_class_exists(): void
    {
        $this->assertTrue(class_exists(MonitorCanaryDeploymentJob::class));
    }

    #[Test]
    public function job_implements_should_queue(): void
    {
        $interfaces = class_implements(MonitorCanaryDeploymentJob::class);
        $this->assertArrayHasKey(\Illuminate\Contracts\Queue\ShouldQueue::class, $interfaces);
    }

    #[Test]
    public function job_has_correct_timeout_and_tries(): void
    {
        $deployment = \Mockery::mock(ApplicationDeploymentQueue::class)->makePartial();
        $deployment->shouldReceive('getAttribute')->with('application_id')->andReturn(1);

        $job = new MonitorCanaryDeploymentJob(
            deployment: $deployment,
            canaryContainer: 'app-canary',
            stableContainer: 'app-stable',
        );

        $this->assertEquals(1, $job->tries);
        $this->assertEquals(120, $job->timeout);
    }

    #[Test]
    public function job_constructor_stores_parameters(): void
    {
        $deployment = \Mockery::mock(ApplicationDeploymentQueue::class)->makePartial();
        $deployment->shouldReceive('getAttribute')->with('application_id')->andReturn(1);

        $job = new MonitorCanaryDeploymentJob(
            deployment: $deployment,
            canaryContainer: 'myapp-canary-20260227',
            stableContainer: 'myapp-stable-20260226',
            currentStep: 1,
            consecutiveFailures: 0,
        );

        $this->assertSame($deployment, $job->deployment);
        $this->assertEquals('myapp-canary-20260227', $job->canaryContainer);
        $this->assertEquals('myapp-stable-20260226', $job->stableContainer);
        $this->assertEquals(1, $job->currentStep);
        $this->assertEquals(0, $job->consecutiveFailures);
    }

    #[Test]
    public function job_has_failed_method(): void
    {
        $class = new \ReflectionClass(MonitorCanaryDeploymentJob::class);
        $this->assertTrue($class->hasMethod('failed'));
    }

    #[Test]
    public function job_uses_canary_deployment_trait(): void
    {
        $traits = class_uses_recursive(MonitorCanaryDeploymentJob::class);
        $this->assertArrayHasKey(\App\Traits\Deployment\HandlesCanaryDeployment::class, $traits);
    }

    #[Test]
    public function job_default_step_is_zero(): void
    {
        $deployment = \Mockery::mock(ApplicationDeploymentQueue::class)->makePartial();
        $deployment->shouldReceive('getAttribute')->with('application_id')->andReturn(1);

        $job = new MonitorCanaryDeploymentJob(
            deployment: $deployment,
            canaryContainer: 'app-canary',
            stableContainer: 'app-stable',
        );

        $this->assertEquals(0, $job->currentStep);
        $this->assertEquals(0, $job->consecutiveFailures);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
