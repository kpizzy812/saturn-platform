<?php

namespace Tests\Unit\Jobs;

use App\Jobs\StripeProcessJob;
use Tests\TestCase;

/**
 * Unit tests for StripeProcessJob.
 *
 * Since StripeProcessJob interacts with Stripe API and Eloquent statics,
 * we use source-level assertions and constructor tests to verify behavior
 * without database or API dependencies.
 */
class StripeProcessJobTest extends TestCase
{
    /** @test */
    public function it_is_queued_on_high_queue(): void
    {
        $event = ['type' => 'test', 'data' => ['object' => []]];
        $job = new StripeProcessJob($event);

        $this->assertEquals('high', $job->queue);
    }

    /** @test */
    public function it_has_retry_and_timeout_configured(): void
    {
        $event = ['type' => 'test', 'data' => ['object' => []]];
        $job = new StripeProcessJob($event);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(30, $job->timeout);
    }

    /** @test */
    public function it_stores_event_data(): void
    {
        $event = ['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_test']]];
        $job = new StripeProcessJob($event);

        $this->assertEquals($event, $job->event);
    }

    // -------------------------------------------------------------------------
    // Event type handling — source-level assertions
    // -------------------------------------------------------------------------

    /** @test */
    public function it_handles_radar_early_fraud_warning(): void
    {
        $source = file_get_contents(app_path('Jobs/StripeProcessJob.php'));

        $this->assertStringContainsString("case 'radar.early_fraud_warning.created':", $source);
        $this->assertStringContainsString('$stripe->refunds->create(', $source);
        $this->assertStringContainsString('$stripe->subscriptions->cancel(', $source);
    }

    /** @test */
    public function it_handles_checkout_session_completed(): void
    {
        $source = file_get_contents(app_path('Jobs/StripeProcessJob.php'));

        $this->assertStringContainsString("case 'checkout.session.completed':", $source);
        $this->assertStringContainsString('client_reference_id', $source);
        $this->assertStringContainsString('Subscription::create(', $source);
    }

    /** @test */
    public function it_validates_user_is_admin_on_checkout(): void
    {
        $source = file_get_contents(app_path('Jobs/StripeProcessJob.php'));

        $this->assertStringContainsString('$found->isAdmin()', $source);
    }

    /** @test */
    public function it_handles_invoice_paid(): void
    {
        $source = file_get_contents(app_path('Jobs/StripeProcessJob.php'));

        $this->assertStringContainsString("case 'invoice.paid':", $source);
        $this->assertStringContainsString('stripe_invoice_paid', $source);
    }

    /** @test */
    public function it_checks_excluded_plans_on_invoice_paid(): void
    {
        $source = file_get_contents(app_path('Jobs/StripeProcessJob.php'));

        $this->assertStringContainsString('excludedPlans', $source);
        $this->assertStringContainsString("config('subscription.stripe_excluded_plans')", $source);
    }

    /** @test */
    public function it_handles_invoice_payment_failed(): void
    {
        $source = file_get_contents(app_path('Jobs/StripeProcessJob.php'));

        $this->assertStringContainsString("case 'invoice.payment_failed':", $source);
        $this->assertStringContainsString('SubscriptionInvoiceFailedJob::dispatch(', $source);
    }

    /** @test */
    public function it_verifies_payment_intent_before_sending_failure(): void
    {
        $source = file_get_contents(app_path('Jobs/StripeProcessJob.php'));

        $this->assertStringContainsString('$stripe->paymentIntents->retrieve(', $source);
        $this->assertStringContainsString("'processing', 'succeeded', 'requires_action', 'requires_confirmation'", $source);
    }

    /** @test */
    public function it_handles_payment_intent_payment_failed(): void
    {
        $source = file_get_contents(app_path('Jobs/StripeProcessJob.php'));

        $this->assertStringContainsString("case 'payment_intent.payment_failed':", $source);
    }

    /** @test */
    public function it_handles_customer_subscription_created(): void
    {
        $source = file_get_contents(app_path('Jobs/StripeProcessJob.php'));

        $this->assertStringContainsString("case 'customer.subscription.created':", $source);
        $this->assertStringContainsString('metadata.team_id', $source);
        $this->assertStringContainsString('metadata.user_id', $source);
    }

    /** @test */
    public function it_handles_customer_subscription_updated(): void
    {
        $source = file_get_contents(app_path('Jobs/StripeProcessJob.php'));

        $this->assertStringContainsString("case 'customer.subscription.updated':", $source);
        $this->assertStringContainsString('cancel_at_period_end', $source);
        $this->assertStringContainsString('cancellation_details.feedback', $source);
    }

    /** @test */
    public function it_handles_subscription_status_transitions(): void
    {
        $source = file_get_contents(app_path('Jobs/StripeProcessJob.php'));

        // Verify all status transitions are handled
        $this->assertStringContainsString("'paused'", $source);
        $this->assertStringContainsString("'incomplete_expired'", $source);
        $this->assertStringContainsString("'past_due'", $source);
        $this->assertStringContainsString("'unpaid'", $source);
        $this->assertStringContainsString("'active'", $source);
    }

    /** @test */
    public function it_handles_dynamic_plan_server_limit(): void
    {
        $source = file_get_contents(app_path('Jobs/StripeProcessJob.php'));

        $this->assertStringContainsString("str(\$lookup_key)->contains('dynamic')", $source);
        $this->assertStringContainsString('custom_server_limit', $source);
        $this->assertStringContainsString('ServerLimitCheckJob::dispatch(', $source);
    }

    /** @test */
    public function it_handles_customer_subscription_deleted(): void
    {
        $source = file_get_contents(app_path('Jobs/StripeProcessJob.php'));

        $this->assertStringContainsString("case 'customer.subscription.deleted':", $source);
        $this->assertStringContainsString('$team->subscriptionEnded()', $source);
    }

    /** @test */
    public function it_throws_on_unhandled_event_type(): void
    {
        $source = file_get_contents(app_path('Jobs/StripeProcessJob.php'));

        $this->assertStringContainsString('default:', $source);
        $this->assertStringContainsString('Unhandled event type', $source);
    }

    /** @test */
    public function it_catches_exceptions_and_sends_internal_notification(): void
    {
        $source = file_get_contents(app_path('Jobs/StripeProcessJob.php'));

        $this->assertStringContainsString('catch (\Exception $e)', $source);
        $this->assertStringContainsString("send_internal_notification('StripeProcessJob error: '", $source);
    }

    /** @test */
    public function it_has_failed_method_that_logs_and_notifies(): void
    {
        $source = file_get_contents(app_path('Jobs/StripeProcessJob.php'));

        $this->assertStringContainsString('public function failed(\Throwable $exception)', $source);
        $this->assertStringContainsString("Log::error('StripeProcessJob permanently failed'", $source);
        $this->assertStringContainsString('send_internal_notification(', $source);
    }

    /** @test */
    public function it_dispatches_verify_job_for_unknown_subscription_statuses(): void
    {
        $source = file_get_contents(app_path('Jobs/StripeProcessJob.php'));

        $this->assertStringContainsString('VerifyStripeSubscriptionStatusJob::dispatch(', $source);
    }

    /** @test */
    public function it_delays_failure_notification_for_new_subscriptions(): void
    {
        $source = file_get_contents(app_path('Jobs/StripeProcessJob.php'));

        $this->assertStringContainsString('diffInMinutes(now()) < 5', $source);
        $this->assertStringContainsString('->delay(now()->addSeconds(60))', $source);
    }
}
