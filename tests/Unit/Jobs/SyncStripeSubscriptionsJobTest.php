<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SyncStripeSubscriptionsJob;
use Tests\TestCase;

/**
 * Unit tests for SyncStripeSubscriptionsJob.
 */
class SyncStripeSubscriptionsJobTest extends TestCase
{
    /** @test */
    public function it_is_queued_on_high_queue(): void
    {
        $job = new SyncStripeSubscriptionsJob;

        $this->assertEquals('high', $job->queue);
    }

    /** @test */
    public function it_has_single_try(): void
    {
        $job = new SyncStripeSubscriptionsJob;

        $this->assertEquals(1, $job->tries);
    }

    /** @test */
    public function it_has_30_minute_timeout(): void
    {
        $job = new SyncStripeSubscriptionsJob;

        $this->assertEquals(1800, $job->timeout);
    }

    /** @test */
    public function it_accepts_fix_flag_in_constructor(): void
    {
        $jobDryRun = new SyncStripeSubscriptionsJob(fix: false);
        $this->assertFalse($jobDryRun->fix);

        $jobFix = new SyncStripeSubscriptionsJob(fix: true);
        $this->assertTrue($jobFix->fix);
    }

    /** @test */
    public function it_defaults_fix_to_false(): void
    {
        $job = new SyncStripeSubscriptionsJob;

        $this->assertFalse($job->fix);
    }

    // -------------------------------------------------------------------------
    // Source-level assertions
    // -------------------------------------------------------------------------

    /** @test */
    public function it_source_checks_cloud_and_stripe_configuration(): void
    {
        $source = file_get_contents(app_path('Jobs/SyncStripeSubscriptionsJob.php'));

        $this->assertStringContainsString('isCloud()', $source);
        $this->assertStringContainsString('isStripe()', $source);
    }

    /** @test */
    public function it_source_returns_early_when_not_cloud(): void
    {
        $source = file_get_contents(app_path('Jobs/SyncStripeSubscriptionsJob.php'));

        $this->assertStringContainsString("'error' => 'Not running on Cloud or Stripe not configured'", $source);
    }

    /** @test */
    public function it_source_queries_active_subscriptions_only(): void
    {
        $source = file_get_contents(app_path('Jobs/SyncStripeSubscriptionsJob.php'));

        $this->assertStringContainsString("whereNotNull('stripe_subscription_id')", $source);
        $this->assertStringContainsString("where('stripe_invoice_paid', true)", $source);
    }

    /** @test */
    public function it_source_detects_discrepancies_for_canceled_statuses(): void
    {
        $source = file_get_contents(app_path('Jobs/SyncStripeSubscriptionsJob.php'));

        $this->assertStringContainsString("'canceled', 'incomplete_expired', 'unpaid'", $source);
    }

    /** @test */
    public function it_source_only_fixes_when_fix_flag_is_true(): void
    {
        $source = file_get_contents(app_path('Jobs/SyncStripeSubscriptionsJob.php'));

        $this->assertStringContainsString('if ($this->fix)', $source);
        $this->assertStringContainsString("'stripe_invoice_paid' => false", $source);
    }

    /** @test */
    public function it_source_calls_subscription_ended_on_canceled(): void
    {
        $source = file_get_contents(app_path('Jobs/SyncStripeSubscriptionsJob.php'));

        $this->assertStringContainsString('subscriptionEnded()', $source);
    }

    /** @test */
    public function it_source_includes_rate_limit_delay(): void
    {
        $source = file_get_contents(app_path('Jobs/SyncStripeSubscriptionsJob.php'));

        $this->assertStringContainsString('usleep(100000)', $source);
    }

    /** @test */
    public function it_source_returns_structured_result(): void
    {
        $source = file_get_contents(app_path('Jobs/SyncStripeSubscriptionsJob.php'));

        $this->assertStringContainsString("'total_checked'", $source);
        $this->assertStringContainsString("'discrepancies'", $source);
        $this->assertStringContainsString("'errors'", $source);
        $this->assertStringContainsString("'fixed'", $source);
    }

    /** @test */
    public function it_source_sends_notification_only_when_fixing_discrepancies(): void
    {
        $source = file_get_contents(app_path('Jobs/SyncStripeSubscriptionsJob.php'));

        $this->assertStringContainsString('$this->fix && count($discrepancies) > 0', $source);
        $this->assertStringContainsString('send_internal_notification(', $source);
    }

    /** @test */
    public function it_has_failed_method(): void
    {
        $source = file_get_contents(app_path('Jobs/SyncStripeSubscriptionsJob.php'));

        $this->assertStringContainsString('public function failed(\Throwable $exception)', $source);
        $this->assertStringContainsString("Log::error('SyncStripeSubscriptionsJob failed'", $source);
    }
}
