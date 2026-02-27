<?php

namespace Tests\Unit\Jobs;

use App\Jobs\MonitorBackupStalenessJob;
use Tests\TestCase;

/**
 * Tests for stale backup threshold calculation logic.
 *
 * Uses reflection to test the private getStaleThresholdHours() method
 * without needing database or queue infrastructure.
 */
class MonitorBackupStalenessJobTest extends TestCase
{
    private MonitorBackupStalenessJob $job;

    protected function setUp(): void
    {
        parent::setUp();
        $this->job = new MonitorBackupStalenessJob;
    }

    private function getThreshold(string $frequency): int
    {
        $method = new \ReflectionMethod(MonitorBackupStalenessJob::class, 'getStaleThresholdHours');

        return $method->invoke($this->job, $frequency);
    }

    // ---------------------------------------------------------------------------
    // Hourly patterns
    // ---------------------------------------------------------------------------

    public function test_hourly_cron_gives_2_hour_threshold(): void
    {
        $this->assertSame(2, $this->getThreshold('0 * * * *'));
    }

    public function test_every_minute_cron_gives_2_hour_threshold(): void
    {
        $this->assertSame(2, $this->getThreshold('* * * * *'));
    }

    // ---------------------------------------------------------------------------
    // Every N hours
    // ---------------------------------------------------------------------------

    public function test_every_6_hours_gives_12_hour_threshold(): void
    {
        $this->assertSame(12, $this->getThreshold('0 */6 * * *'));
    }

    public function test_every_12_hours_gives_24_hour_threshold(): void
    {
        $this->assertSame(24, $this->getThreshold('0 */12 * * *'));
    }

    // ---------------------------------------------------------------------------
    // Daily patterns
    // ---------------------------------------------------------------------------

    public function test_midnight_daily_gives_48_hour_threshold(): void
    {
        $this->assertSame(48, $this->getThreshold('0 0 * * *'));
    }

    public function test_arbitrary_daily_time_gives_48_hour_threshold(): void
    {
        $this->assertSame(48, $this->getThreshold('30 3 * * *'));
    }

    // ---------------------------------------------------------------------------
    // Weekly patterns
    // ---------------------------------------------------------------------------

    public function test_weekly_sunday_gives_336_hour_threshold(): void
    {
        $this->assertSame(336, $this->getThreshold('0 0 * * 0'));
    }

    public function test_weekly_friday_gives_336_hour_threshold(): void
    {
        $this->assertSame(336, $this->getThreshold('0 2 * * 5'));
    }

    // ---------------------------------------------------------------------------
    // Monthly patterns
    // ---------------------------------------------------------------------------

    public function test_monthly_first_day_gives_1488_hour_threshold(): void
    {
        $this->assertSame(1488, $this->getThreshold('0 0 1 * *'));
    }

    // ---------------------------------------------------------------------------
    // Custom / unknown patterns default to 48h
    // ---------------------------------------------------------------------------

    public function test_unknown_cron_defaults_to_48_hours(): void
    {
        $this->assertSame(48, $this->getThreshold('15 10 * * 1-5'));
    }

    public function test_empty_string_defaults_to_48_hours(): void
    {
        $this->assertSame(48, $this->getThreshold(''));
    }

    // ---------------------------------------------------------------------------
    // Multiple-hour patterns (comma-separated hours)
    // ---------------------------------------------------------------------------

    public function test_four_times_daily_gives_12_hour_threshold(): void
    {
        // "0 0,6,12,18 * * *" = every 6 hours → threshold = 12h
        $threshold = $this->getThreshold('0 0,6,12,18 * * *');
        $this->assertSame(12, $threshold);
    }

    public function test_twice_daily_gives_24_hour_threshold(): void
    {
        // "0 0,12 * * *" = every 12 hours → threshold = 24h
        $threshold = $this->getThreshold('0 0,12 * * *');
        $this->assertSame(24, $threshold);
    }
}
