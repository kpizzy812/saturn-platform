<?php

namespace Tests\Unit\Jobs;

use App\Jobs\BackupRestoreTestJob;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Tests\TestCase;

/**
 * Unit tests for BackupRestoreTestJob.
 *
 * Covers job configuration, constructor, method structure, and source-level
 * assertions for restore logic, security (escapeshellarg), and error handling.
 *
 * SSH-dependent paths (instant_remote_process, Docker commands) are NOT exercised
 * here — those require Feature tests with a real server. These unit tests catch
 * structural regressions early without needing Docker.
 */
class BackupRestoreTestJobTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();
        $this->source = file_get_contents(app_path('Jobs/BackupRestoreTestJob.php'));
    }

    // =========================================================================
    // Job configuration
    // =========================================================================

    public function test_job_implements_should_queue(): void
    {
        $this->assertContains(ShouldQueue::class, class_implements(BackupRestoreTestJob::class));
    }

    public function test_job_implements_should_be_encrypted(): void
    {
        // Backup files and credentials are sensitive — queue payload must be encrypted
        $this->assertContains(ShouldBeEncrypted::class, class_implements(BackupRestoreTestJob::class));
    }

    public function test_job_has_tries_set_to_1(): void
    {
        $reflection = new \ReflectionClass(BackupRestoreTestJob::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(1, $defaults['tries']);
    }

    public function test_job_has_1800_second_timeout(): void
    {
        // 30-minute timeout for potentially large database restores
        $reflection = new \ReflectionClass(BackupRestoreTestJob::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(1800, $defaults['timeout']);
    }

    public function test_job_dispatches_on_high_queue(): void
    {
        // Restore tests need fast feedback — must use high-priority queue
        $this->assertStringContainsString("'high'", $this->source);
        $this->assertStringContainsString('onQueue', $this->source);
    }

    public function test_job_generates_random_test_container_name(): void
    {
        // Each restore test uses an isolated container to prevent conflicts
        $this->assertStringContainsString('backup-test-', $this->source);
        $this->assertStringContainsString('Str::random', $this->source);
    }

    // =========================================================================
    // Restore status tracking
    // =========================================================================

    public function test_execution_status_set_to_pending_before_restore(): void
    {
        // UI must show "in progress" while restore test is running
        $this->assertStringContainsString("'restore_test_status' => 'pending'", $this->source);
    }

    public function test_execution_status_set_to_success_on_completion(): void
    {
        $this->assertStringContainsString("'restore_test_status' => 'success'", $this->source);
    }

    public function test_execution_status_set_to_failed_on_error(): void
    {
        $this->assertStringContainsString("'restore_test_status' => 'failed'", $this->source);
    }

    public function test_execution_records_restore_test_duration(): void
    {
        // Duration is shown in UI for performance tracking
        $this->assertStringContainsString('restore_test_duration_seconds', $this->source);
    }

    public function test_execution_records_restore_test_message(): void
    {
        $this->assertStringContainsString('restore_test_message', $this->source);
    }

    public function test_execution_records_restore_test_at_timestamp(): void
    {
        $this->assertStringContainsString('restore_test_at', $this->source);
    }

    // =========================================================================
    // Database type dispatch (match expression)
    // =========================================================================

    public function test_dispatches_postgres_restore_for_standalone_postgresql(): void
    {
        $this->assertStringContainsString('StandalonePostgresql', $this->source);
        $this->assertStringContainsString('testPostgresRestore', $this->source);
    }

    public function test_dispatches_mysql_restore_for_standalone_mysql(): void
    {
        $this->assertStringContainsString('StandaloneMysql', $this->source);
        $this->assertStringContainsString('testMysqlRestore', $this->source);
    }

    public function test_dispatches_mariadb_restore_for_standalone_mariadb(): void
    {
        $this->assertStringContainsString('StandaloneMariadb', $this->source);
        $this->assertStringContainsString('testMariadbRestore', $this->source);
    }

    public function test_dispatches_mongo_restore_for_standalone_mongodb(): void
    {
        $this->assertStringContainsString('StandaloneMongodb', $this->source);
        $this->assertStringContainsString('testMongoRestore', $this->source);
    }

    public function test_dispatches_service_database_restore(): void
    {
        $this->assertStringContainsString('ServiceDatabase', $this->source);
        $this->assertStringContainsString('testServiceDatabaseRestore', $this->source);
    }

    public function test_service_database_detects_postgres_from_image(): void
    {
        // ServiceDatabase type is determined from the Docker image string
        $this->assertStringContainsString("str_contains(\$image, 'postgres')", $this->source);
    }

    public function test_service_database_detects_mysql_from_image(): void
    {
        $this->assertStringContainsString("str_contains(\$image, 'mysql')", $this->source);
    }

    public function test_service_database_detects_mongo_from_image(): void
    {
        $this->assertStringContainsString("str_contains(\$image, 'mongo')", $this->source);
    }

    // =========================================================================
    // Restore command format
    // =========================================================================

    public function test_gz_backup_is_decompressed_with_gunzip(): void
    {
        $this->assertStringContainsString('str_ends_with($backupFile, \'.gz\')', $this->source);
        $this->assertStringContainsString('gunzip -c', $this->source);
    }

    public function test_dmp_backup_uses_pg_restore(): void
    {
        // PostgreSQL custom-format dump requires pg_restore, not psql
        $this->assertStringContainsString('str_ends_with($backupFile, \'.dmp\')', $this->source);
        $this->assertStringContainsString('pg_restore', $this->source);
    }

    public function test_postgres_restore_creates_isolated_database(): void
    {
        // Must restore into a fresh database to avoid contamination
        $this->assertStringContainsString('restore_test_', $this->source);
        $this->assertStringContainsString('POSTGRES_DB', $this->source);
    }

    // =========================================================================
    // Security — escapeshellarg
    // =========================================================================

    public function test_mysql_password_is_shell_escaped(): void
    {
        // SECURITY: DB password from DB model must be shell-escaped
        $this->assertStringContainsString('escapeshellarg($password)', $this->source);
    }

    public function test_s3_credentials_are_shell_escaped(): void
    {
        // SECURITY: S3 endpoint/key/secret from user config must be shell-escaped
        $this->assertStringContainsString('escapeshellarg($s3->endpoint)', $this->source);
        $this->assertStringContainsString('escapeshellarg($s3->key)', $this->source);
        $this->assertStringContainsString('escapeshellarg($s3->secret)', $this->source);
    }

    // =========================================================================
    // Container cleanup
    // =========================================================================

    public function test_cleanup_runs_in_finally_block(): void
    {
        // Test container must be removed even if restore fails
        $this->assertStringContainsString('finally', $this->source);
        $this->assertStringContainsString('$this->cleanupTestContainer()', $this->source);
    }

    public function test_cleanup_uses_docker_rm_f(): void
    {
        $this->assertStringContainsString('docker rm -f', $this->source);
    }

    public function test_cleanup_suppresses_errors_when_container_not_found(): void
    {
        // Container may already be gone if it never started
        $this->assertStringContainsString('2>/dev/null || true', $this->source);
    }

    // =========================================================================
    // S3 fallback
    // =========================================================================

    public function test_s3_download_attempted_when_local_file_missing(): void
    {
        $this->assertStringContainsString('s3_uploaded', $this->source);
        $this->assertStringContainsString('downloadFromS3', $this->source);
    }

    public function test_s3_download_uses_minio_mc_client(): void
    {
        $this->assertStringContainsString('minio/mc', $this->source);
    }

    public function test_s3_path_includes_team_slug_and_db_slug(): void
    {
        // Predictable path structure for multi-tenant backups
        $this->assertStringContainsString('Str::slug($team->name)', $this->source);
        $this->assertStringContainsString('teamSlug', $this->source);
        $this->assertStringContainsString('dbSlug', $this->source);
    }

    // =========================================================================
    // Notifications
    // =========================================================================

    public function test_success_notification_sent_to_team(): void
    {
        $this->assertStringContainsString('BackupRestoreTestSuccess', $this->source);
        $this->assertStringContainsString('$this->notifySuccess(', $this->source);
    }

    public function test_failure_notification_sent_to_team(): void
    {
        $this->assertStringContainsString('BackupRestoreTestFailed', $this->source);
        $this->assertStringContainsString('$this->notifyFailure(', $this->source);
    }

    public function test_notification_failure_does_not_crash_job(): void
    {
        // Notification errors must never crash the job itself
        $this->assertStringContainsString('Failed to send restore test', $this->source);
        $this->assertStringContainsString('Log::warning', $this->source);
    }

    // =========================================================================
    // Error handling
    // =========================================================================

    public function test_throwable_caught_and_logged_in_handle(): void
    {
        $this->assertStringContainsString('catch (Throwable $e)', $this->source);
        $this->assertStringContainsString('Log::error(', $this->source);
        $this->assertStringContainsString('Backup restore test failed', $this->source);
    }

    public function test_mark_failed_updates_execution_status(): void
    {
        $this->assertStringContainsString('private function markFailed(string $message): bool', $this->source);
        $this->assertStringContainsString("'restore_test_status' => 'failed'", $this->source);
    }

    public function test_mark_failed_returns_false(): void
    {
        $this->assertStringContainsString('return false;', $this->source);
    }

    public function test_job_returns_early_when_no_execution_available(): void
    {
        // If no successful backup exists, job must exit cleanly without failing
        $this->assertStringContainsString('No successful backup found', $this->source);
        $this->assertStringContainsString('Log::info(', $this->source);
    }

    public function test_job_returns_early_when_database_not_found(): void
    {
        $this->assertStringContainsString('Database not found', $this->source);
    }

    public function test_job_returns_early_when_server_not_found(): void
    {
        $this->assertStringContainsString('Server not found', $this->source);
    }

    // =========================================================================
    // Backup tracking
    // =========================================================================

    public function test_successful_restore_updates_last_restore_test_at(): void
    {
        // backup model tracks last successful restore for scheduling
        $this->assertStringContainsString('last_restore_test_at', $this->source);
    }

    public function test_fallback_to_latest_successful_execution_when_none_provided(): void
    {
        // Job can be dispatched without a specific execution (cron mode)
        $this->assertStringContainsString("'status', 'success'", $this->source);
        $this->assertStringContainsString('->latest()', $this->source);
        $this->assertStringContainsString('->first()', $this->source);
    }
}
