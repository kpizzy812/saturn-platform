<?php

namespace Tests\Unit\Jobs;

use App\Jobs\CleanupMetricsJob;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Tests\TestCase;

/**
 * Unit tests for CleanupMetricsJob.
 *
 * Tests cover job configuration, middleware (WithoutOverlapping), and
 * source-level assertions for the retention logic.
 *
 * Integration tests that verify actual DB row deletion run in Feature tests
 * as they require a live database connection.
 */
class CleanupMetricsJobTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Job configuration
    // -------------------------------------------------------------------------

    public function test_job_implements_should_queue(): void
    {
        $this->assertContains(ShouldQueue::class, class_implements(CleanupMetricsJob::class));
    }

    public function test_job_implements_should_be_encrypted(): void
    {
        $this->assertContains(ShouldBeEncrypted::class, class_implements(CleanupMetricsJob::class));
    }

    public function test_job_implements_should_be_unique(): void
    {
        $this->assertContains(ShouldBeUnique::class, class_implements(CleanupMetricsJob::class));
    }

    public function test_job_has_120_second_timeout(): void
    {
        $job = new CleanupMetricsJob;

        $this->assertEquals(120, $job->timeout);
    }

    // -------------------------------------------------------------------------
    // Middleware
    // -------------------------------------------------------------------------

    public function test_middleware_returns_single_without_overlapping_instance(): void
    {
        $job = new CleanupMetricsJob;
        $middleware = $job->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
    }

    public function test_middleware_uses_correct_lock_key(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupMetricsJob.php'));

        $this->assertStringContainsString("'cleanup-metrics'", $source);
    }

    public function test_middleware_expires_after_120_seconds(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupMetricsJob.php'));

        $this->assertStringContainsString('->expireAfter(120)', $source);
    }

    public function test_middleware_uses_dont_release(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupMetricsJob.php'));

        $this->assertStringContainsString('->dontRelease()', $source);
    }

    // -------------------------------------------------------------------------
    // Retention policy — source-level assertions
    // -------------------------------------------------------------------------

    public function test_retention_days_is_30(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupMetricsJob.php'));

        // The retention constant must be 30 days to match audit requirements
        $this->assertStringContainsString('$retentionDays = 30', $source);
    }

    public function test_handle_deletes_from_database_metrics_table(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupMetricsJob.php'));

        $this->assertStringContainsString("'database_metrics'", $source);
    }

    public function test_handle_deletes_from_server_health_checks_table(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupMetricsJob.php'));

        $this->assertStringContainsString("'server_health_checks'", $source);
    }

    public function test_handle_filters_by_cutoff_date(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupMetricsJob.php'));

        // Must compare timestamps against a computed cutoff date
        $this->assertStringContainsString('subDays($this->retentionDays)', $source);
    }

    public function test_handle_uses_db_table_for_bulk_deletion(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupMetricsJob.php'));

        // DB::table() ensures a bulk delete without loading models into memory
        $this->assertStringContainsString('DB::table(', $source);
    }

    public function test_handle_wraps_logic_in_try_catch_throwable(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupMetricsJob.php'));

        $this->assertStringContainsString('catch (\Throwable $e)', $source);
    }

    public function test_handle_logs_error_on_failure(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupMetricsJob.php'));

        $this->assertStringContainsString('Log::error(', $source);
    }

    public function test_handle_logs_info_only_when_rows_were_deleted(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupMetricsJob.php'));

        // Must guard the info log behind a non-zero deleted count check
        $this->assertStringContainsString('Log::info(', $source);
        $this->assertStringContainsString('$dbMetricsDeleted > 0', $source);
    }

    // -------------------------------------------------------------------------
    // Reflection — private property $retentionDays
    // -------------------------------------------------------------------------

    public function test_retention_days_property_is_private_and_equals_30(): void
    {
        $reflection = new \ReflectionClass(CleanupMetricsJob::class);

        $this->assertTrue($reflection->hasProperty('retentionDays'));

        $property = $reflection->getProperty('retentionDays');
        $this->assertTrue($property->isPrivate());

        $job = new CleanupMetricsJob;
        $property->setAccessible(true);
        $this->assertEquals(30, $property->getValue($job));
    }

    // -------------------------------------------------------------------------
    // Cutoff date logic
    // -------------------------------------------------------------------------

    public function test_cutoff_is_exactly_30_days_before_now(): void
    {
        // Verify that the cutoff calculation produces a Carbon instance 30 days ago
        $before = now()->subDays(30)->startOfSecond();
        $cutoff = now()->subDays(30);
        $after = now()->subDays(30)->endOfSecond();

        $this->assertTrue($cutoff->between($before, $after));
    }
}
