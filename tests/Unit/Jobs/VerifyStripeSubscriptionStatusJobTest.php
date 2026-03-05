<?php

namespace Tests\Unit\Jobs;

use App\Jobs\VerifyStripeSubscriptionStatusJob;
use App\Models\Subscription;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for VerifyStripeSubscriptionStatusJob.
 */
class VerifyStripeSubscriptionStatusJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_is_queued_on_high_queue(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial()->shouldIgnoreMissing();
        $job = new VerifyStripeSubscriptionStatusJob($subscription);

        $this->assertEquals('high', $job->queue);
    }

    /** @test */
    public function it_has_retry_and_backoff_configured(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial()->shouldIgnoreMissing();
        $job = new VerifyStripeSubscriptionStatusJob($subscription);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals([10, 30, 60], $job->backoff);
    }

    /** @test */
    public function it_stores_subscription(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial()->shouldIgnoreMissing();
        $job = new VerifyStripeSubscriptionStatusJob($subscription);

        $this->assertSame($subscription, $job->subscription);
    }

    // -------------------------------------------------------------------------
    // Source-level assertions
    // -------------------------------------------------------------------------

    /** @test */
    public function it_source_resolves_subscription_id_via_customer_when_missing(): void
    {
        $source = file_get_contents(app_path('Jobs/VerifyStripeSubscriptionStatusJob.php'));

        $this->assertStringContainsString('! $this->subscription->stripe_subscription_id', $source);
        $this->assertStringContainsString('$this->subscription->stripe_customer_id', $source);
        $this->assertStringContainsString('$stripe->subscriptions->all(', $source);
    }

    /** @test */
    public function it_source_returns_early_when_no_subscription_id(): void
    {
        $source = file_get_contents(app_path('Jobs/VerifyStripeSubscriptionStatusJob.php'));

        // Second check after resolution attempt
        $occurrences = substr_count($source, '! $this->subscription->stripe_subscription_id');
        $this->assertGreaterThanOrEqual(2, $occurrences);
    }

    /** @test */
    public function it_source_handles_active_status(): void
    {
        $source = file_get_contents(app_path('Jobs/VerifyStripeSubscriptionStatusJob.php'));

        $this->assertStringContainsString("case 'active':", $source);
        $this->assertStringContainsString("'stripe_invoice_paid' => true", $source);
        $this->assertStringContainsString("'stripe_past_due' => false", $source);
    }

    /** @test */
    public function it_source_handles_past_due_status(): void
    {
        $source = file_get_contents(app_path('Jobs/VerifyStripeSubscriptionStatusJob.php'));

        $this->assertStringContainsString("case 'past_due':", $source);
        $this->assertStringContainsString("'stripe_past_due' => true", $source);
    }

    /** @test */
    public function it_source_handles_canceled_and_expired_statuses(): void
    {
        $source = file_get_contents(app_path('Jobs/VerifyStripeSubscriptionStatusJob.php'));

        $this->assertStringContainsString("case 'canceled':", $source);
        $this->assertStringContainsString("case 'incomplete_expired':", $source);
        $this->assertStringContainsString("case 'unpaid':", $source);
        $this->assertStringContainsString("'stripe_invoice_paid' => false", $source);
    }

    /** @test */
    public function it_source_calls_subscription_ended_on_canceled(): void
    {
        $source = file_get_contents(app_path('Jobs/VerifyStripeSubscriptionStatusJob.php'));

        $this->assertStringContainsString("if (\$stripeSubscription->status === 'canceled')", $source);
        $this->assertStringContainsString('$team->subscriptionEnded()', $source);
    }

    /** @test */
    public function it_source_saves_cancel_at_period_end(): void
    {
        $source = file_get_contents(app_path('Jobs/VerifyStripeSubscriptionStatusJob.php'));

        $this->assertStringContainsString('cancel_at_period_end', $source);
        $this->assertStringContainsString('$stripeSubscription->cancel_at_period_end', $source);
    }

    /** @test */
    public function it_source_handles_unknown_status_with_notification(): void
    {
        $source = file_get_contents(app_path('Jobs/VerifyStripeSubscriptionStatusJob.php'));

        $this->assertStringContainsString('default:', $source);
        $this->assertStringContainsString('Unknown subscription status', $source);
        $this->assertStringContainsString('send_internal_notification(', $source);
    }

    /** @test */
    public function it_source_catches_api_exceptions(): void
    {
        $source = file_get_contents(app_path('Jobs/VerifyStripeSubscriptionStatusJob.php'));

        $this->assertStringContainsString('catch (\Exception $e)', $source);
        $this->assertStringContainsString('VerifyStripeSubscriptionStatusJob failed for subscription ID', $source);
    }

    /** @test */
    public function it_has_failed_method(): void
    {
        $source = file_get_contents(app_path('Jobs/VerifyStripeSubscriptionStatusJob.php'));

        $this->assertStringContainsString('public function failed(\Throwable $exception)', $source);
        $this->assertStringContainsString("Log::error('VerifyStripeSubscriptionStatusJob failed'", $source);
    }
}
