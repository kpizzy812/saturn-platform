<?php

namespace Tests\Unit\Jobs;

use App\Jobs\MonitorDeploymentHealthJob;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\ApplicationSetting;
use Mockery;
use Tests\TestCase;

class MonitorDeploymentHealthJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_job_has_correct_configuration(): void
    {
        $deployment = Mockery::mock(ApplicationDeploymentQueue::class);

        $job = new MonitorDeploymentHealthJob($deployment);

        $this->assertEquals(1, $job->tries);
        $this->assertEquals(600, $job->timeout);
    }

    public function test_job_stores_deployment_and_defaults(): void
    {
        $deployment = Mockery::mock(ApplicationDeploymentQueue::class);

        $job = new MonitorDeploymentHealthJob($deployment);

        $this->assertSame($deployment, $job->deployment);
        $this->assertEquals(30, $job->checkIntervalSeconds);
        $this->assertEquals(10, $job->totalChecks);
    }

    public function test_job_accepts_custom_interval_and_checks(): void
    {
        $deployment = Mockery::mock(ApplicationDeploymentQueue::class);

        $job = new MonitorDeploymentHealthJob($deployment, 15, 20);

        $this->assertEquals(15, $job->checkIntervalSeconds);
        $this->assertEquals(20, $job->totalChecks);
    }

    public function test_job_skips_when_auto_rollback_disabled(): void
    {
        $settings = Mockery::mock(ApplicationSetting::class);
        $settings->shouldReceive('getAttribute')->with('auto_rollback_enabled')->andReturn(false);

        $application = Mockery::mock(Application::class);
        $application->shouldReceive('getAttribute')->with('settings')->andReturn($settings);

        $deployment = Mockery::mock(ApplicationDeploymentQueue::class);
        $deployment->shouldReceive('getAttribute')->with('application')->andReturn($application);

        $job = new MonitorDeploymentHealthJob($deployment);

        // Should return early without errors
        $job->handle();

        $this->assertTrue(true); // If we got here, job exited gracefully
    }

    public function test_job_skips_pr_deployments(): void
    {
        $settings = Mockery::mock(ApplicationSetting::class);
        $settings->shouldReceive('getAttribute')->with('auto_rollback_enabled')->andReturn(true);

        $application = Mockery::mock(Application::class);
        $application->shouldReceive('getAttribute')->with('settings')->andReturn($settings);

        $deployment = Mockery::mock(ApplicationDeploymentQueue::class);
        $deployment->shouldReceive('getAttribute')->with('application')->andReturn($application);
        $deployment->shouldReceive('getAttribute')->with('pull_request_id')->andReturn(42);

        $job = new MonitorDeploymentHealthJob($deployment);
        $job->handle();

        $this->assertTrue(true);
    }

    public function test_job_skips_non_finished_deployments(): void
    {
        $settings = Mockery::mock(ApplicationSetting::class);
        $settings->shouldReceive('getAttribute')->with('auto_rollback_enabled')->andReturn(true);

        $application = Mockery::mock(Application::class);
        $application->shouldReceive('getAttribute')->with('settings')->andReturn($settings);

        $deployment = Mockery::mock(ApplicationDeploymentQueue::class);
        $deployment->shouldReceive('getAttribute')->with('application')->andReturn($application);
        $deployment->shouldReceive('getAttribute')->with('pull_request_id')->andReturn(0);
        $deployment->shouldReceive('getAttribute')->with('status')->andReturn('failed');

        $job = new MonitorDeploymentHealthJob($deployment);
        $job->handle();

        $this->assertTrue(true);
    }
}
