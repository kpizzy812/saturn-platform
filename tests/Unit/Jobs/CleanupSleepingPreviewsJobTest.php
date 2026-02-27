<?php

namespace Tests\Unit\Jobs;

use App\Jobs\CleanupSleepingPreviewsJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Tests\TestCase;

/**
 * Unit tests for CleanupSleepingPreviewsJob.
 *
 * Covers job configuration, method structure, and source-level assertions
 * for both auto-sleep and auto-delete strategies.
 *
 * SSH-dependent paths (instant_remote_process, CleanupPreviewDeployment) are
 * covered in Feature tests; these unit tests focus on the job's structure and
 * static logic to catch regressions early without needing Docker.
 */
class CleanupSleepingPreviewsJobTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Job configuration
    // -------------------------------------------------------------------------

    public function test_job_implements_should_queue(): void
    {
        $this->assertContains(ShouldQueue::class, class_implements(CleanupSleepingPreviewsJob::class));
    }

    public function test_job_has_tries_set_to_1(): void
    {
        $job = new CleanupSleepingPreviewsJob;

        $this->assertEquals(1, $job->tries);
    }

    public function test_job_has_600_second_timeout(): void
    {
        $job = new CleanupSleepingPreviewsJob;

        $this->assertEquals(600, $job->timeout);
    }

    // -------------------------------------------------------------------------
    // Protected method structure (handleAutoSleep, handleAutoDelete)
    // -------------------------------------------------------------------------

    public function test_handle_auto_sleep_method_exists_and_is_protected(): void
    {
        $reflection = new \ReflectionClass(CleanupSleepingPreviewsJob::class);

        $this->assertTrue($reflection->hasMethod('handleAutoSleep'));

        $method = $reflection->getMethod('handleAutoSleep');
        $this->assertTrue($method->isProtected());
    }

    public function test_handle_auto_delete_method_exists_and_is_protected(): void
    {
        $reflection = new \ReflectionClass(CleanupSleepingPreviewsJob::class);

        $this->assertTrue($reflection->hasMethod('handleAutoDelete'));

        $method = $reflection->getMethod('handleAutoDelete');
        $this->assertTrue($method->isProtected());
    }

    public function test_sleep_preview_method_exists_and_is_protected(): void
    {
        $reflection = new \ReflectionClass(CleanupSleepingPreviewsJob::class);

        $this->assertTrue($reflection->hasMethod('sleepPreview'));
        $method = $reflection->getMethod('sleepPreview');
        $this->assertTrue($method->isProtected());
    }

    public function test_delete_preview_method_exists_and_is_protected(): void
    {
        $reflection = new \ReflectionClass(CleanupSleepingPreviewsJob::class);

        $this->assertTrue($reflection->hasMethod('deletePreview'));
        $method = $reflection->getMethod('deletePreview');
        $this->assertTrue($method->isProtected());
    }

    public function test_handle_calls_both_strategy_methods(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupSleepingPreviewsJob.php'));

        $this->assertStringContainsString('$this->handleAutoSleep()', $source);
        $this->assertStringContainsString('$this->handleAutoDelete()', $source);
    }

    // -------------------------------------------------------------------------
    // Auto-sleep query — security assertions (parameterized INTERVAL)
    // -------------------------------------------------------------------------

    public function test_auto_sleep_filters_only_enabled_previews(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupSleepingPreviewsJob.php'));

        $this->assertStringContainsString("'auto_sleep_enabled', true", $source);
    }

    public function test_auto_sleep_filters_out_already_sleeping_previews(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupSleepingPreviewsJob.php'));

        $this->assertStringContainsString("'is_sleeping', false", $source);
    }

    public function test_auto_sleep_uses_parameterized_interval_not_raw_column(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupSleepingPreviewsJob.php'));

        // SECURITY: Must use parameterized interval multiplication, not raw column.
        // The source contains escaped single quotes because it is inside a PHP string.
        $this->assertStringContainsString('auto_sleep_after_minutes * interval', $source);
        $this->assertStringContainsString('1 minute', $source);
        // Must NOT use raw unparameterized column reference like: INTERVAL auto_sleep_after_minutes MINUTE
        $this->assertStringNotContainsString('INTERVAL auto_sleep_after_minutes', $source);
    }

    public function test_auto_sleep_marks_preview_as_sleeping_and_records_slept_at(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupSleepingPreviewsJob.php'));

        $this->assertStringContainsString("'is_sleeping' => true", $source);
        $this->assertStringContainsString("'slept_at'", $source);
    }

    // -------------------------------------------------------------------------
    // Auto-delete query — security assertions (parameterized INTERVAL)
    // -------------------------------------------------------------------------

    public function test_auto_delete_filters_only_enabled_previews(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupSleepingPreviewsJob.php'));

        $this->assertStringContainsString("'auto_delete_enabled', true", $source);
    }

    public function test_auto_delete_uses_parameterized_interval_not_raw_column(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupSleepingPreviewsJob.php'));

        // SECURITY: Must use parameterized interval multiplication.
        // The source contains escaped single quotes because it is inside a PHP string.
        $this->assertStringContainsString('auto_delete_after_days * interval', $source);
        $this->assertStringContainsString('1 day', $source);
        $this->assertStringNotContainsString('INTERVAL auto_delete_after_days', $source);
    }

    public function test_auto_delete_uses_cleanup_preview_deployment_action(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupSleepingPreviewsJob.php'));

        $this->assertStringContainsString('CleanupPreviewDeployment::run(', $source);
    }

    // -------------------------------------------------------------------------
    // Error handling — each preview failure must be caught independently
    // -------------------------------------------------------------------------

    public function test_sleep_preview_loop_catches_exceptions_per_preview(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupSleepingPreviewsJob.php'));

        // Each preview sleep must be wrapped in its own try-catch so one failure
        // doesn't abort the entire cleanup run
        $occurrences = substr_count($source, 'catch (\Exception $e)');
        $this->assertGreaterThanOrEqual(2, $occurrences, 'Expected separate try-catch blocks for sleep and delete loops');
    }

    public function test_sleep_and_delete_failures_are_logged(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupSleepingPreviewsJob.php'));

        $this->assertStringContainsString('Log::error(', $source);
    }

    // -------------------------------------------------------------------------
    // Summary logging
    // -------------------------------------------------------------------------

    public function test_handle_logs_info_after_processing(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupSleepingPreviewsJob.php'));

        $this->assertStringContainsString('Log::info(', $source);
    }
}
