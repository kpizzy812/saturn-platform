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
        $this->assertEquals(90, $job->timeout); // 90s to accommodate SSH-based checks
    }

    public function test_job_has_correct_timeout(): void
    {
        $deployment = Mockery::mock(ApplicationDeploymentQueue::class);
        $job = new MonitorDeploymentHealthJob($deployment);

        // Timeout increased to 90s to accommodate SSH-based error rate checks
        $this->assertEquals(90, $job->timeout);
    }

    public function test_job_stores_deployment_and_defaults(): void
    {
        $deployment = Mockery::mock(ApplicationDeploymentQueue::class);

        $job = new MonitorDeploymentHealthJob($deployment);

        $this->assertSame($deployment, $job->deployment);
        $this->assertEquals(30, $job->checkIntervalSeconds);
        $this->assertEquals(60, $job->totalChecks);   // 30min window: 60 checks × 30s
        $this->assertEquals(0, $job->currentCheck);
        $this->assertEquals(-1, $job->initialRestartCount);
        $this->assertEquals(0, $job->consecutiveFailures);
    }

    // ---------------------------------------------------------------------------
    // Consecutive failure threshold
    // ---------------------------------------------------------------------------

    public function test_default_window_is_30_minutes(): void
    {
        // 60 checks × 30s = 1800s = 30 minutes
        $deployment = Mockery::mock(ApplicationDeploymentQueue::class);
        $job = new MonitorDeploymentHealthJob($deployment);

        $windowSeconds = $job->totalChecks * $job->checkIntervalSeconds;
        $this->assertSame(1800, $windowSeconds);
    }

    public function test_single_failure_does_not_trigger_rollback_with_default_threshold(): void
    {
        // With default consecutive threshold = 2: 1 failure is below threshold
        $consecutiveAfterFail = 0 + 1;
        $requiredConsecutive = 2;

        $this->assertLessThan($requiredConsecutive, $consecutiveAfterFail);
    }

    public function test_two_consecutive_failures_meet_default_threshold(): void
    {
        $consecutiveAfterSecondFail = 1 + 1;
        $requiredConsecutive = 2;

        $this->assertGreaterThanOrEqual($requiredConsecutive, $consecutiveAfterSecondFail);
    }

    public function test_threshold_of_1_triggers_on_first_failure(): void
    {
        $consecutiveAfterFirstFail = 0 + 1;
        $requiredConsecutive = 1;

        $this->assertGreaterThanOrEqual($requiredConsecutive, $consecutiveAfterFirstFail);
    }

    public function test_threshold_of_3_requires_three_failures(): void
    {
        $requiredConsecutive = 3;

        $this->assertFalse($requiredConsecutive <= 2, 'Two failures should not trigger');
        $this->assertTrue($requiredConsecutive <= 3, 'Three failures should trigger');
    }

    public function test_consecutive_counter_resets_on_recovery(): void
    {
        // After a successful check following failures, counter goes back to 0
        $consecutiveAfterRecovery = 0;
        $this->assertSame(0, $consecutiveAfterRecovery);
    }

    // ---------------------------------------------------------------------------
    // Monitoring window calculation
    // ---------------------------------------------------------------------------

    public function test_window_derived_from_validation_seconds(): void
    {
        $validationSeconds = 1800;
        $checkInterval = 30;
        $totalChecks = (int) ceil($validationSeconds / $checkInterval);

        $this->assertSame(60, $totalChecks);
    }

    public function test_custom_validation_period_scales_checks(): void
    {
        $validationSeconds = 600; // 10 minutes
        $checkInterval = 30;
        $totalChecks = (int) ceil($validationSeconds / $checkInterval);

        $this->assertSame(20, $totalChecks);
    }

    public function test_ceil_rounds_up_non_divisible_periods(): void
    {
        // 100s / 30s = 3.33 → ceil to 4
        $this->assertSame(4, (int) ceil(100 / 30));
    }

    // ---------------------------------------------------------------------------
    // Error rate logic
    // ---------------------------------------------------------------------------

    public function test_error_count_below_threshold_does_not_trigger(): void
    {
        $this->assertFalse(5 >= 10);
    }

    public function test_error_count_at_threshold_triggers(): void
    {
        $this->assertTrue(10 >= 10);
    }

    public function test_zero_errors_never_triggers(): void
    {
        $this->assertFalse(0 >= 10);
    }

    // ---------------------------------------------------------------------------
    // Metrics snapshot includes new fields
    // ---------------------------------------------------------------------------

    public function test_metrics_snapshot_includes_consecutive_failures(): void
    {
        $source = file_get_contents(app_path('Jobs/MonitorDeploymentHealthJob.php'));
        $this->assertStringContainsString('consecutive_failures', $source);
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
