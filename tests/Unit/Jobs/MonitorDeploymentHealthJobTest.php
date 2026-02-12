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
        $this->assertEquals(60, $job->timeout);
    }

    public function test_job_stores_deployment_and_defaults(): void
    {
        $deployment = Mockery::mock(ApplicationDeploymentQueue::class);

        $job = new MonitorDeploymentHealthJob($deployment);

        $this->assertSame($deployment, $job->deployment);
        $this->assertEquals(30, $job->checkIntervalSeconds);
        $this->assertEquals(10, $job->totalChecks);
        $this->assertEquals(0, $job->currentCheck);
        $this->assertEquals(-1, $job->initialRestartCount);
    }

    public function test_job_accepts_custom_parameters(): void
    {
        $deployment = Mockery::mock(ApplicationDeploymentQueue::class);

        $job = new MonitorDeploymentHealthJob(
            deployment: $deployment,
            checkIntervalSeconds: 15,
            totalChecks: 20,
            currentCheck: 5,
            initialRestartCount: 3
        );

        $this->assertEquals(15, $job->checkIntervalSeconds);
        $this->assertEquals(20, $job->totalChecks);
        $this->assertEquals(5, $job->currentCheck);
        $this->assertEquals(3, $job->initialRestartCount);
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

    public function test_job_implements_should_queue(): void
    {
        $this->assertTrue(
            in_array(
                \Illuminate\Contracts\Queue\ShouldQueue::class,
                class_implements(MonitorDeploymentHealthJob::class)
            )
        );
    }

    public function test_self_dispatching_pattern_in_source(): void
    {
        $source = file_get_contents(app_path('Jobs/MonitorDeploymentHealthJob.php'));

        // Verify self-dispatching pattern exists
        $this->assertStringContainsString('self::dispatch(', $source);
        $this->assertStringContainsString('->delay(', $source);

        // Verify no actual sleep() calls in code (ignore comments)
        $lines = explode("\n", $source);
        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if (str_starts_with($trimmed, '*') || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '/*')) {
                continue;
            }
            $this->assertStringNotContainsString('sleep(', $line, "Found sleep() call in non-comment code: {$line}");
        }
    }
}
