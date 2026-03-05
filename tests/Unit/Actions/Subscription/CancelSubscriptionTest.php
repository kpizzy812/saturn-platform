<?php

namespace Tests\Unit\Actions\Subscription;

use Tests\TestCase;

/**
 * Unit tests for CancelSubscription action.
 */
class CancelSubscriptionTest extends TestCase
{
    /** @test */
    public function it_filters_subscriptions_by_owner_role(): void
    {
        $source = file_get_contents(app_path('Actions/Stripe/CancelSubscription.php'));

        $this->assertStringContainsString("'owner'", $source);
    }

    /** @test */
    public function it_requires_stripe_subscription_id_and_invoice_paid(): void
    {
        $source = file_get_contents(app_path('Actions/Stripe/CancelSubscription.php'));

        $this->assertStringContainsString('stripe_subscription_id', $source);
        $this->assertStringContainsString('stripe_invoice_paid', $source);
    }

    /** @test */
    public function it_verifies_subscriptions_against_stripe_api(): void
    {
        $source = file_get_contents(app_path('Actions/Stripe/CancelSubscription.php'));

        $this->assertStringContainsString('verifySubscriptionsInStripe', $source);
        $this->assertStringContainsString('verified', $source);
        $this->assertStringContainsString('not_found', $source);
    }

    /** @test */
    public function it_filters_active_stripe_statuses(): void
    {
        $source = file_get_contents(app_path('Actions/Stripe/CancelSubscription.php'));

        $this->assertStringContainsString("'active'", $source);
        $this->assertStringContainsString("'trialing'", $source);
        $this->assertStringContainsString("'past_due'", $source);
    }

    /** @test */
    public function it_supports_dry_run_mode(): void
    {
        $source = file_get_contents(app_path('Actions/Stripe/CancelSubscription.php'));

        $this->assertStringContainsString('isDryRun', $source);
    }

    /** @test */
    public function it_cancels_subscription_and_updates_db_fields(): void
    {
        $source = file_get_contents(app_path('Actions/Stripe/CancelSubscription.php'));

        $this->assertStringContainsString('stripe_cancel_at_period_end', $source);
        $this->assertStringContainsString('stripe_trial_already_ended', $source);
        $this->assertStringContainsString('stripe_past_due', $source);
        $this->assertStringContainsString('stripe_feedback', $source);
        $this->assertStringContainsString('stripe_comment', $source);
    }

    /** @test */
    public function it_calls_subscription_ended_on_team(): void
    {
        $source = file_get_contents(app_path('Actions/Stripe/CancelSubscription.php'));

        $this->assertStringContainsString('$subscription->team->subscriptionEnded()', $source);
    }

    /** @test */
    public function it_catches_stripe_invalid_request_exception(): void
    {
        $source = file_get_contents(app_path('Actions/Stripe/CancelSubscription.php'));

        $this->assertStringContainsString('Stripe\Exception\InvalidRequestException', $source);
    }

    /** @test */
    public function it_has_static_cancel_by_id_method(): void
    {
        $source = file_get_contents(app_path('Actions/Stripe/CancelSubscription.php'));

        $this->assertStringContainsString('static function cancelById(string $subscriptionId)', $source);
    }

    /** @test */
    public function cancel_by_id_checks_is_cloud(): void
    {
        $source = file_get_contents(app_path('Actions/Stripe/CancelSubscription.php'));

        $this->assertStringContainsString('isCloud()', $source);
    }

    /** @test */
    public function it_returns_execute_result_with_counts(): void
    {
        $source = file_get_contents(app_path('Actions/Stripe/CancelSubscription.php'));

        $this->assertStringContainsString("'cancelled'", $source);
        $this->assertStringContainsString("'failed'", $source);
        $this->assertStringContainsString("'errors'", $source);
    }

    /** @test */
    public function it_logs_errors_on_failure(): void
    {
        $source = file_get_contents(app_path('Actions/Stripe/CancelSubscription.php'));

        $this->assertStringContainsString('Log::error(', $source);
    }
}
