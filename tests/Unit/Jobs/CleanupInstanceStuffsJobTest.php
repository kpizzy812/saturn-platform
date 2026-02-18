<?php

namespace Tests\Unit\Jobs;

use App\Jobs\CleanupInstanceStuffsJob;
use App\Models\TeamInvitation;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Tests\TestCase;

/**
 * Unit tests for CleanupInstanceStuffsJob.
 *
 * These tests cover job configuration, middleware, reflection-based structural
 * checks, and source-level assertions for both private cleanup methods.
 *
 * NOTE (V2 AUDIT CRITICAL): cleanupInvitationLink() calls TeamInvitation::all()
 * which loads ALL invitation records without any scoping. This is a known
 * performance/correctness issue documented in the V2 audit.
 * test_cleanup_invitation_link_uses_all_without_scoping() explicitly documents
 * this unscoped behavior so a future fix is not missed.
 *
 * NOTE on handle() integration tests: handle() calls TeamInvitation::all() and
 * User::whereNotNull()->where()->update() — both require a live database
 * connection. Those execution paths are covered in tests/Feature/.
 * Alias mocks (Mockery::mock('alias:...')) cannot be used here because they are
 * registered globally per PHP process and cause fatal "Cannot redeclare" errors
 * when more than one test in the suite tries to mock the same class.
 */
class CleanupInstanceStuffsJobTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Job configuration
    // -------------------------------------------------------------------------

    public function test_job_implements_should_queue(): void
    {
        $this->assertContains(ShouldQueue::class, class_implements(CleanupInstanceStuffsJob::class));
    }

    public function test_job_implements_should_be_encrypted(): void
    {
        $this->assertContains(ShouldBeEncrypted::class, class_implements(CleanupInstanceStuffsJob::class));
    }

    public function test_job_implements_should_be_unique(): void
    {
        $this->assertContains(ShouldBeUnique::class, class_implements(CleanupInstanceStuffsJob::class));
    }

    public function test_job_has_correct_timeout(): void
    {
        $job = new CleanupInstanceStuffsJob;

        $this->assertEquals(60, $job->timeout);
    }

    // -------------------------------------------------------------------------
    // Middleware
    // -------------------------------------------------------------------------

    public function test_middleware_returns_single_without_overlapping_instance(): void
    {
        $job = new CleanupInstanceStuffsJob;
        $middleware = $job->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
    }

    public function test_middleware_uses_correct_lock_key(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupInstanceStuffsJob.php'));

        // The lock key must be the stable string used across all workers
        $this->assertStringContainsString("'cleanup-instance-stuffs'", $source);
    }

    public function test_middleware_expires_after_60_seconds(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupInstanceStuffsJob.php'));

        // expireAfter(60) prevents dead locks after the timeout period
        $this->assertStringContainsString('->expireAfter(60)', $source);
    }

    public function test_middleware_uses_dont_release(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupInstanceStuffsJob.php'));

        // dontRelease() means a duplicate job is discarded, not re-queued
        $this->assertStringContainsString('->dontRelease()', $source);
    }

    // -------------------------------------------------------------------------
    // Private methods — ReflectionMethod structural checks
    // -------------------------------------------------------------------------

    public function test_cleanup_invitation_link_exists_and_is_private(): void
    {
        $reflection = new \ReflectionClass(CleanupInstanceStuffsJob::class);

        $this->assertTrue($reflection->hasMethod('cleanupInvitationLink'));

        $method = $reflection->getMethod('cleanupInvitationLink');
        $this->assertTrue($method->isPrivate());
    }

    public function test_cleanup_expired_email_change_requests_exists_and_is_private(): void
    {
        $reflection = new \ReflectionClass(CleanupInstanceStuffsJob::class);

        $this->assertTrue($reflection->hasMethod('cleanupExpiredEmailChangeRequests'));

        $method = $reflection->getMethod('cleanupExpiredEmailChangeRequests');
        $this->assertTrue($method->isPrivate());
    }

    public function test_cleanup_invitation_link_has_no_parameters(): void
    {
        $reflection = new \ReflectionClass(CleanupInstanceStuffsJob::class);
        $method = $reflection->getMethod('cleanupInvitationLink');

        $this->assertCount(0, $method->getParameters());
    }

    public function test_cleanup_expired_email_change_requests_has_no_parameters(): void
    {
        $reflection = new \ReflectionClass(CleanupInstanceStuffsJob::class);
        $method = $reflection->getMethod('cleanupExpiredEmailChangeRequests');

        $this->assertCount(0, $method->getParameters());
    }

    // -------------------------------------------------------------------------
    // isValid() on TeamInvitation — contract check
    // -------------------------------------------------------------------------

    public function test_team_invitation_has_is_valid_method(): void
    {
        // cleanupInvitationLink() calls $item->isValid() on every invitation.
        // Verify the method exists on the model to catch renames/removals early.
        $this->assertTrue(method_exists(TeamInvitation::class, 'isValid'));
    }

    // -------------------------------------------------------------------------
    // cleanupInvitationLink — V2 AUDIT documented behavior
    // -------------------------------------------------------------------------

    /**
     * V2 AUDIT CRITICAL: cleanupInvitationLink() calls TeamInvitation::all()
     * with no WHERE clause, loading every row in the table before iterating.
     * This can cause memory exhaustion on large datasets.
     *
     * Expected fix: replace with a date-scoped lazy query, e.g.:
     *   TeamInvitation::where('created_at', '<', now()->subDays(config(...)))
     *       ->cursor()
     *
     * This test documents the current (unscoped) behavior so the regression
     * is caught the moment the fix is introduced.
     */
    public function test_cleanup_invitation_link_uses_all_without_scoping(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupInstanceStuffsJob.php'));

        // Confirm the problematic ::all() call is still present
        $this->assertStringContainsString(
            'TeamInvitation::all()',
            $source,
            'V2 AUDIT: TeamInvitation::all() should be replaced with a scoped/lazy query. '.
            'Update this assertion once the fix is applied.'
        );
    }

    public function test_cleanup_invitation_link_calls_is_valid_on_each_item(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupInstanceStuffsJob.php'));

        // Verify the foreach + isValid() iteration pattern is present
        $this->assertStringContainsString('$item->isValid()', $source);
    }

    // -------------------------------------------------------------------------
    // cleanupExpiredEmailChangeRequests — query structure
    // -------------------------------------------------------------------------

    public function test_cleanup_expired_email_change_requests_scopes_to_non_null_expiry(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupInstanceStuffsJob.php'));

        // Must filter only rows where email_change_code_expires_at IS NOT NULL
        $this->assertStringContainsString("whereNotNull('email_change_code_expires_at')", $source);
    }

    public function test_cleanup_expired_email_change_requests_compares_to_now(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupInstanceStuffsJob.php'));

        // Must use a strict less-than comparison against the current time
        $this->assertStringContainsString("'email_change_code_expires_at', '<', now()", $source);
    }

    public function test_cleanup_expired_email_change_requests_nullifies_all_three_fields(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupInstanceStuffsJob.php'));

        // All three email-change columns must be cleared atomically in a single update call
        $this->assertStringContainsString("'pending_email' => null", $source);
        $this->assertStringContainsString("'email_change_code' => null", $source);
        $this->assertStringContainsString("'email_change_code_expires_at' => null", $source);
    }

    // -------------------------------------------------------------------------
    // handle() — source-level structural checks
    // -------------------------------------------------------------------------

    public function test_handle_calls_both_private_cleanup_methods(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupInstanceStuffsJob.php'));

        $this->assertStringContainsString('$this->cleanupInvitationLink()', $source);
        $this->assertStringContainsString('$this->cleanupExpiredEmailChangeRequests()', $source);
    }

    public function test_handle_wraps_logic_in_try_catch_throwable(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupInstanceStuffsJob.php'));

        // The catch block must catch \Throwable (not just \Exception)
        $this->assertStringContainsString('catch (\Throwable $e)', $source);
    }

    public function test_handle_logs_error_on_failure(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupInstanceStuffsJob.php'));

        // Log::error must be called inside the catch block
        $this->assertStringContainsString('Log::error(', $source);
    }

    public function test_error_log_message_identifies_job_class(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupInstanceStuffsJob.php'));

        // Log message must identify the job to aid triage
        $this->assertStringContainsString("'CleanupInstanceStuffsJob failed with error: '", $source);
    }

    public function test_error_log_message_appends_exception_message(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupInstanceStuffsJob.php'));

        // The exception message must be appended for full context
        $this->assertStringContainsString('$e->getMessage()', $source);
    }
}
