<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SubscriptionInvoiceFailedJob;
use App\Models\Team;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for SubscriptionInvoiceFailedJob.
 */
class SubscriptionInvoiceFailedJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_implements_should_be_encrypted(): void
    {
        $team = Mockery::mock(Team::class)->makePartial()->shouldIgnoreMissing();
        $job = new SubscriptionInvoiceFailedJob($team);

        $this->assertInstanceOf(ShouldBeEncrypted::class, $job);
    }

    /** @test */
    public function it_is_queued_on_high_queue(): void
    {
        $team = Mockery::mock(Team::class)->makePartial()->shouldIgnoreMissing();
        $job = new SubscriptionInvoiceFailedJob($team);

        $this->assertEquals('high', $job->queue);
    }

    /** @test */
    public function it_has_retry_and_timeout_configured(): void
    {
        $team = Mockery::mock(Team::class)->makePartial()->shouldIgnoreMissing();
        $job = new SubscriptionInvoiceFailedJob($team);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(120, $job->timeout);
        $this->assertEquals(2, $job->maxExceptions);
        $this->assertEquals([10, 30, 60], $job->backoff);
    }

    // -------------------------------------------------------------------------
    // Source-level assertions
    // -------------------------------------------------------------------------

    /** @test */
    public function it_source_double_checks_subscription_status_before_notification(): void
    {
        $source = file_get_contents(app_path('Jobs/SubscriptionInvoiceFailedJob.php'));

        $this->assertStringContainsString('$this->team->subscription', $source);
        $this->assertStringContainsString('stripe_customer_id', $source);
    }

    /** @test */
    public function it_source_checks_stripe_subscription_status(): void
    {
        $source = file_get_contents(app_path('Jobs/SubscriptionInvoiceFailedJob.php'));

        $this->assertStringContainsString('$stripe->subscriptions->retrieve(', $source);
        $this->assertStringContainsString("'active', 'trialing'", $source);
    }

    /** @test */
    public function it_source_auto_fixes_active_subscription(): void
    {
        $source = file_get_contents(app_path('Jobs/SubscriptionInvoiceFailedJob.php'));

        $this->assertStringContainsString('! $subscription->stripe_invoice_paid', $source);
        $this->assertStringContainsString("'stripe_invoice_paid' => true", $source);
    }

    /** @test */
    public function it_source_checks_recent_invoices(): void
    {
        $source = file_get_contents(app_path('Jobs/SubscriptionInvoiceFailedJob.php'));

        $this->assertStringContainsString('$stripe->invoices->all(', $source);
        $this->assertStringContainsString('$invoice->paid', $source);
        $this->assertStringContainsString('time() - 3600', $source);
    }

    /** @test */
    public function it_source_sends_email_to_team_admins(): void
    {
        $source = file_get_contents(app_path('Jobs/SubscriptionInvoiceFailedJob.php'));

        $this->assertStringContainsString('$this->team->members()', $source);
        $this->assertStringContainsString('$member->isAdmin()', $source);
        $this->assertStringContainsString('send_user_an_email(', $source);
    }

    /** @test */
    public function it_source_uses_subscription_invoice_failed_email_template(): void
    {
        $source = file_get_contents(app_path('Jobs/SubscriptionInvoiceFailedJob.php'));

        $this->assertStringContainsString('emails.subscription-invoice-failed', $source);
        $this->assertStringContainsString('stripeCustomerPortal', $source);
    }

    /** @test */
    public function it_source_gets_stripe_customer_portal_session(): void
    {
        $source = file_get_contents(app_path('Jobs/SubscriptionInvoiceFailedJob.php'));

        $this->assertStringContainsString('getStripeCustomerPortalSession($this->team)', $source);
    }

    /** @test */
    public function it_source_rethrows_exceptions_after_notification(): void
    {
        $source = file_get_contents(app_path('Jobs/SubscriptionInvoiceFailedJob.php'));

        $this->assertStringContainsString('catch (\Throwable $e)', $source);
        $this->assertStringContainsString('send_internal_notification(', $source);
        $this->assertStringContainsString('throw $e;', $source);
    }

    /** @test */
    public function it_has_failed_method_that_logs(): void
    {
        $source = file_get_contents(app_path('Jobs/SubscriptionInvoiceFailedJob.php'));

        $this->assertStringContainsString('public function failed(\Throwable $exception)', $source);
        $this->assertStringContainsString("Log::error('SubscriptionInvoiceFailedJob permanently failed'", $source);
    }
}
