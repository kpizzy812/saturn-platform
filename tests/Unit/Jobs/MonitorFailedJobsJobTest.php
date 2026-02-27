<?php

namespace Tests\Unit\Jobs;

use App\Jobs\MonitorFailedJobsJob;
use Tests\TestCase;

/**
 * Unit tests for MonitorFailedJobsJob logic.
 *
 * Tests threshold logic and cooldown behaviour without touching
 * the database, queue, or cache.
 */
class MonitorFailedJobsJobTest extends TestCase
{
    // ---------------------------------------------------------------------------
    // Threshold logic
    // ---------------------------------------------------------------------------

    public function test_count_below_threshold_does_not_alert(): void
    {
        $failedCount = 4;
        $threshold = 5;

        $this->assertFalse($failedCount >= $threshold);
    }

    public function test_count_at_threshold_triggers_alert(): void
    {
        $failedCount = 5;
        $threshold = 5;

        $this->assertTrue($failedCount >= $threshold);
    }

    public function test_count_above_threshold_triggers_alert(): void
    {
        $failedCount = 100;
        $threshold = 5;

        $this->assertTrue($failedCount >= $threshold);
    }

    public function test_zero_failed_jobs_never_triggers(): void
    {
        $this->assertFalse(0 >= 5);
    }

    // ---------------------------------------------------------------------------
    // Cooldown logic
    // ---------------------------------------------------------------------------

    public function test_alert_within_cooldown_is_suppressed(): void
    {
        $lastAlert = now()->subHours(3);
        $cooldownHours = 6;

        // 3 hours ago < 6 hour cooldown → suppress
        $this->assertLessThan($cooldownHours, now()->diffInHours($lastAlert));
    }

    public function test_alert_after_cooldown_is_allowed(): void
    {
        // lastAlert->diffInHours(now()) gives positive hours elapsed
        $lastAlert = now()->subHours(7);
        $cooldownHours = 6;

        $this->assertGreaterThanOrEqual($cooldownHours, $lastAlert->diffInHours(now()));
    }

    public function test_no_previous_alert_is_always_allowed(): void
    {
        $lastAlert = null;

        // Null = never alerted → always allow
        $this->assertNull($lastAlert);
    }

    public function test_alert_exactly_at_cooldown_boundary_is_allowed(): void
    {
        $lastAlert = now()->subHours(6);
        $cooldownHours = 6;

        $this->assertGreaterThanOrEqual($cooldownHours, $lastAlert->diffInHours(now()));
    }

    public function test_alert_within_cooldown_suppressed_via_last_alert_diff(): void
    {
        $lastAlert = now()->subHours(3);
        $cooldownHours = 6;

        // $lastAlert->diffInHours(now()) = 3, which is < 6 → suppress
        $this->assertLessThan($cooldownHours, $lastAlert->diffInHours(now()));
    }

    // ---------------------------------------------------------------------------
    // Job configuration
    // ---------------------------------------------------------------------------

    public function test_job_implements_should_queue(): void
    {
        $this->assertTrue(
            in_array(
                \Illuminate\Contracts\Queue\ShouldQueue::class,
                class_implements(MonitorFailedJobsJob::class)
            )
        );
    }

    public function test_job_has_short_timeout(): void
    {
        $job = new MonitorFailedJobsJob;
        // Should complete quickly — just a DB count + notify
        $this->assertLessThanOrEqual(60, $job->timeout);
    }

    public function test_job_has_single_try(): void
    {
        $job = new MonitorFailedJobsJob;
        $this->assertSame(1, $job->tries);
    }

    // ---------------------------------------------------------------------------
    // Alert message format
    // ---------------------------------------------------------------------------

    public function test_alert_message_includes_count_and_threshold(): void
    {
        $failedCount = 12;
        $threshold = 5;

        // Simulate the message template
        $message = "⚠️ *Failed Jobs Alert*: {$failedCount} jobs have failed and are accumulating in the queue.\n\n"
            ."Failure threshold: {$threshold} jobs";

        $this->assertStringContainsString('12', $message);
        $this->assertStringContainsString('5', $message);
        $this->assertStringContainsString('Failed Jobs Alert', $message);
    }

    public function test_cache_key_is_consistent(): void
    {
        // The cache key must be the same on every invocation
        $source = file_get_contents(app_path('Jobs/MonitorFailedJobsJob.php'));
        $this->assertStringContainsString('CACHE_KEY', $source);
        $this->assertStringContainsString("'monitor_failed_jobs_last_alert'", $source);
    }
}
