<?php

namespace Tests\Unit\Jobs;

use App\Jobs\DatabaseMetricsManagerJob;
use Tests\TestCase;

/**
 * Unit tests for Manager/Scheduler Jobs:
 * BackupRestoreTestManagerJob, PullChangelog, ResourceMonitoringManagerJob,
 * DatabaseMetricsManagerJob.
 */
class ManagerJobsTest extends TestCase
{
    // =========================================================================
    // BackupRestoreTestManagerJob
    // =========================================================================

    /** @test */
    public function backup_restore_test_manager_has_config(): void
    {
        $source = file_get_contents(app_path('Jobs/BackupRestoreTestManagerJob.php'));

        $this->assertStringContainsString('$tries = 1', $source);
        $this->assertStringContainsString('$timeout = 60', $source);
    }

    /** @test */
    public function backup_restore_test_manager_runs_on_high_queue(): void
    {
        $source = file_get_contents(app_path('Jobs/BackupRestoreTestManagerJob.php'));

        $this->assertStringContainsString("'high'", $source);
    }

    /** @test */
    public function backup_restore_test_manager_queries_enabled_backups(): void
    {
        $source = file_get_contents(app_path('Jobs/BackupRestoreTestManagerJob.php'));

        $this->assertStringContainsString('ScheduledDatabaseBackup', $source);
        $this->assertStringContainsString('enabled', $source);
        $this->assertStringContainsString('restore_test_enabled', $source);
    }

    /** @test */
    public function backup_restore_test_manager_checks_frequency(): void
    {
        $source = file_get_contents(app_path('Jobs/BackupRestoreTestManagerJob.php'));

        $this->assertStringContainsString('shouldRunRestoreTest', $source);
        $this->assertStringContainsString("'daily'", $source);
        $this->assertStringContainsString("'weekly'", $source);
        $this->assertStringContainsString("'monthly'", $source);
    }

    /** @test */
    public function backup_restore_test_manager_dispatches_test_job(): void
    {
        $source = file_get_contents(app_path('Jobs/BackupRestoreTestManagerJob.php'));

        $this->assertStringContainsString('BackupRestoreTestJob', $source);
        $this->assertStringContainsString('dispatchRestoreTest', $source);
    }

    /** @test */
    public function backup_restore_test_manager_logs_failure(): void
    {
        $source = file_get_contents(app_path('Jobs/BackupRestoreTestManagerJob.php'));

        $this->assertStringContainsString('BackupRestoreTestManagerJob permanently failed', $source);
    }

    // =========================================================================
    // PullChangelog
    // =========================================================================

    /** @test */
    public function pull_changelog_has_config(): void
    {
        $source = file_get_contents(app_path('Jobs/PullChangelog.php'));

        $this->assertStringContainsString('$timeout = 30', $source);
    }

    /** @test */
    public function pull_changelog_fetches_from_cdn(): void
    {
        $source = file_get_contents(app_path('Jobs/PullChangelog.php'));

        $this->assertStringContainsString("config('constants.saturn.releases_url')", $source);
        $this->assertStringContainsString('Http::retry(3, 1000)', $source);
    }

    /** @test */
    public function pull_changelog_transforms_releases(): void
    {
        $source = file_get_contents(app_path('Jobs/PullChangelog.php'));

        $this->assertStringContainsString('transformReleasesToChangelog', $source);
        $this->assertStringContainsString("'tag_name'", $source);
        $this->assertStringContainsString("'published_at'", $source);
    }

    /** @test */
    public function pull_changelog_saves_to_files(): void
    {
        $source = file_get_contents(app_path('Jobs/PullChangelog.php'));

        $this->assertStringContainsString('saveChangelogEntries', $source);
        $this->assertStringContainsString('changelogs/', $source);
    }

    /** @test */
    public function pull_changelog_skips_when_url_not_configured(): void
    {
        $source = file_get_contents(app_path('Jobs/PullChangelog.php'));

        $this->assertStringContainsString('releases_url is not configured, skipping', $source);
    }

    /** @test */
    public function pull_changelog_handles_fetch_failure(): void
    {
        $source = file_get_contents(app_path('Jobs/PullChangelog.php'));

        $this->assertStringContainsString('Failed to fetch from CDN', $source);
    }

    // =========================================================================
    // ResourceMonitoringManagerJob
    // =========================================================================

    /** @test */
    public function resource_monitoring_has_config(): void
    {
        $source = file_get_contents(app_path('Jobs/ResourceMonitoringManagerJob.php'));

        $this->assertStringContainsString('$tries = 1', $source);
        $this->assertStringContainsString('$timeout = 60', $source);
    }

    /** @test */
    public function resource_monitoring_checks_enabled_setting(): void
    {
        $source = file_get_contents(app_path('Jobs/ResourceMonitoringManagerJob.php'));

        $this->assertStringContainsString('resource_monitoring_enabled', $source);
    }

    /** @test */
    public function resource_monitoring_excludes_placeholder_servers(): void
    {
        $source = file_get_contents(app_path('Jobs/ResourceMonitoringManagerJob.php'));

        $this->assertStringContainsString("'1.2.3.4'", $source);
    }

    /** @test */
    public function resource_monitoring_excludes_build_servers(): void
    {
        $source = file_get_contents(app_path('Jobs/ResourceMonitoringManagerJob.php'));

        $this->assertStringContainsString('isBuildServer', $source);
    }

    /** @test */
    public function resource_monitoring_dispatches_check_job(): void
    {
        $source = file_get_contents(app_path('Jobs/ResourceMonitoringManagerJob.php'));

        $this->assertStringContainsString('CheckServerResourcesJob::dispatch($server)', $source);
    }

    /** @test */
    public function resource_monitoring_handles_cloud_mode(): void
    {
        $source = file_get_contents(app_path('Jobs/ResourceMonitoringManagerJob.php'));

        $this->assertStringContainsString('isCloud()', $source);
        $this->assertStringContainsString('stripe_invoice_paid', $source);
    }

    /** @test */
    public function resource_monitoring_logs_failure(): void
    {
        $source = file_get_contents(app_path('Jobs/ResourceMonitoringManagerJob.php'));

        $this->assertStringContainsString('ResourceMonitoringManagerJob permanently failed', $source);
    }

    // =========================================================================
    // DatabaseMetricsManagerJob
    // =========================================================================

    /** @test */
    public function database_metrics_manager_constructor_config(): void
    {
        $job = new DatabaseMetricsManagerJob;

        $this->assertEquals(1, $job->tries);
        $this->assertEquals(120, $job->timeout);
    }

    /** @test */
    public function database_metrics_manager_dispatches_per_server(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseMetricsManagerJob.php'));

        $this->assertStringContainsString('CollectDatabaseMetricsJob::dispatch($server)', $source);
        $this->assertStringContainsString('$server->isFunctional()', $source);
        $this->assertStringContainsString('$server->databases()', $source);
    }

    /** @test */
    public function database_metrics_manager_cleans_up_old_metrics(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseMetricsManagerJob.php'));

        $this->assertStringContainsString('cleanupOldMetrics', $source);
        $this->assertStringContainsString('DatabaseMetric::cleanupOldMetrics(30)', $source);
    }

    /** @test */
    public function database_metrics_manager_excludes_placeholder_ip(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseMetricsManagerJob.php'));

        $this->assertStringContainsString("'1.2.3.4'", $source);
    }

    /** @test */
    public function database_metrics_manager_handles_cloud_mode(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseMetricsManagerJob.php'));

        $this->assertStringContainsString('isCloud()', $source);
        $this->assertStringContainsString('stripe_invoice_paid', $source);
        $this->assertStringContainsString('Team::find(0)', $source);
    }

    /** @test */
    public function database_metrics_manager_logs_dispatch_errors(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseMetricsManagerJob.php'));

        $this->assertStringContainsString('Failed to dispatch CollectDatabaseMetricsJob', $source);
        $this->assertStringContainsString("Log::channel('scheduled-errors')", $source);
    }

    /** @test */
    public function database_metrics_manager_has_failed_method(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseMetricsManagerJob.php'));

        $this->assertStringContainsString('public function failed(\Throwable $exception)', $source);
        $this->assertStringContainsString('DatabaseMetricsManagerJob failed', $source);
    }
}
