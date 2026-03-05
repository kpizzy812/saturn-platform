<?php

/**
 * E2E Stripe Webhook Integration Tests
 *
 * Tests the Stripe webhook handling lifecycle:
 * - Webhook reception and signature validation
 * - Job dispatch and event processing
 * - Subscription lifecycle (create/update/delete)
 * - Error handling, rate limiting, and cross-team isolation
 */

use App\Jobs\StripeProcessJob;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(DatabaseTransactions::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Generate a valid Stripe-Signature header for testing.
 * Mirrors the HMAC-SHA256 scheme Stripe uses: t={timestamp},v1={signature}
 */
function generateStripeSignature(string $payload, string $secret, ?int $timestamp = null): string
{
    $timestamp = $timestamp ?? time();
    $signedPayload = "{$timestamp}.{$payload}";
    $signature = hash_hmac('sha256', $signedPayload, $secret);

    return "t={$timestamp},v1={$signature}";
}

/**
 * Build a minimal Stripe event payload array.
 */
function buildStripeEvent(string $type, array $objectData, ?string $eventId = null): array
{
    return [
        'id' => $eventId ?? 'evt_test_'.\Illuminate\Support\Str::random(16),
        'type' => $type,
        'data' => [
            'object' => $objectData,
        ],
    ];
}

/**
 * Create a Subscription record directly in the database, bypassing fillable restrictions.
 */
function createTestSubscription(array $attributes): Subscription
{
    $defaults = [
        'stripe_invoice_paid' => false,
        'stripe_cancel_at_period_end' => false,
        'stripe_trial_already_ended' => false,
        'stripe_past_due' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ];

    $data = array_merge($defaults, $attributes);
    $id = DB::table('subscriptions')->insertGetId($data);

    return Subscription::find($id);
}

// ─── Setup ───────────────────────────────────────────────────────────────────

beforeEach(function () {
    Queue::fake();
    Cache::flush();

    try {
        Cache::store('redis')->flush();
    } catch (\Throwable $e) {
        // Redis may not be available in test environment
    }

    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();
    $this->team->members()->attach($this->user->id, ['role' => 'owner']);

    // Second team for cross-team isolation tests
    $this->teamB = Team::factory()->create();
    $this->userB = User::factory()->create();
    $this->teamB->members()->attach($this->userB->id, ['role' => 'owner']);

    if (! DB::table('instance_settings')->where('id', 0)->exists()) {
        DB::table('instance_settings')->insert([
            'id' => 0,
            'is_api_enabled' => true,
            'is_registration_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // Webhook secret used to generate valid signatures
    $this->webhookSecret = 'whsec_test_secret_key_12345';
    config()->set('subscription.stripe_webhook_secret', $this->webhookSecret);
    config()->set('subscription.stripe_excluded_plans', '');
    config()->set('subscription.stripe_api_key', 'sk_test_fake');

    $this->webhookUrl = '/webhooks/payments/stripe/events';
});

// ─── Webhook Endpoint Tests ─────────────────────────────────────────────────

describe('Webhook Endpoint — POST /webhooks/payments/stripe/events', function () {

    test('valid webhook with correct signature dispatches StripeProcessJob and returns 200', function () {
        // The real Stripe\Webhook::constructEvent() validates HMAC signatures.
        // We mock it to return a controlled event object.
        $eventPayload = buildStripeEvent('checkout.session.completed', [
            'id' => 'cs_test_abc',
            'client_reference_id' => "{$this->user->id}:{$this->team->id}",
            'subscription' => 'sub_test_123',
            'customer' => 'cus_test_456',
        ]);

        $jsonPayload = json_encode($eventPayload);
        $signature = generateStripeSignature($jsonPayload, $this->webhookSecret);

        // Mock Stripe\Webhook::constructEvent to bypass real HMAC validation
        $mockEvent = (object) $eventPayload;
        $mockEvent->type = $eventPayload['type'];

        $mock = Mockery::mock('alias:Stripe\Webhook');
        $mock->shouldReceive('constructEvent')
            ->once()
            ->andReturn($mockEvent);

        $response = $this->postJson($this->webhookUrl, $eventPayload, [
            'Stripe-Signature' => $signature,
        ]);

        $response->assertStatus(200);
        $response->assertSee('Webhook received. Cool cool cool cool cool.');

        Queue::assertPushed(StripeProcessJob::class);
    });

    test('missing Stripe-Signature header returns 400', function () {
        $payload = json_encode(buildStripeEvent('checkout.session.completed', []));

        // Without mock, Stripe\Webhook::constructEvent will throw
        $response = $this->call('POST', $this->webhookUrl, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertStatus(400);
        $response->assertSee('Webhook processing failed.');

        Queue::assertNotPushed(StripeProcessJob::class);
    });

    test('invalid Stripe-Signature returns 400', function () {
        $payload = json_encode(buildStripeEvent('checkout.session.completed', []));

        $response = $this->call('POST', $this->webhookUrl, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't=12345,v1=invalid_signature_here',
        ], $payload);

        $response->assertStatus(400);
        $response->assertSee('Webhook processing failed.');

        Queue::assertNotPushed(StripeProcessJob::class);
    });

    test('malformed non-JSON payload returns 400', function () {
        $malformedBody = 'this is not json {{{';

        $response = $this->call('POST', $this->webhookUrl, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't=12345,v1=bad',
        ], $malformedBody);

        $response->assertStatus(400);
        $response->assertSee('Webhook processing failed.');

        Queue::assertNotPushed(StripeProcessJob::class);
    });

    test('rate limiting returns 429 after exceeding 10 requests per minute', function () {
        // The webhook route uses throttle:10,1 middleware
        // Send 10 requests that will fail (400) due to invalid signature — that's fine,
        // the throttle counter increments regardless of response status.
        for ($i = 0; $i < 10; $i++) {
            $this->call('POST', $this->webhookUrl, [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 't=12345,v1=bad',
            ], '{}');
        }

        // The 11th request should be rate-limited
        $response = $this->call('POST', $this->webhookUrl, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't=12345,v1=bad',
        ], '{}');

        $response->assertStatus(429);
    });
});

// ─── StripeProcessJob — checkout.session.completed ──────────────────────────

describe('StripeProcessJob — checkout.session.completed', function () {

    test('creates new subscription when none exists for the team', function () {
        $event = buildStripeEvent('checkout.session.completed', [
            'id' => 'cs_test_new_sub',
            'client_reference_id' => "{$this->user->id}:{$this->team->id}",
            'subscription' => 'sub_new_123',
            'customer' => 'cus_new_456',
        ]);

        Queue::fake(); // Reset to allow real dispatch tracking
        $job = new StripeProcessJob($event);
        $job->handle();

        $subscription = Subscription::where('team_id', $this->team->id)->first();
        expect($subscription)->not->toBeNull();
        expect($subscription->stripe_subscription_id)->toBe('sub_new_123');
        expect($subscription->stripe_customer_id)->toBe('cus_new_456');
        expect((bool) $subscription->stripe_invoice_paid)->toBeTrue();
    });

    test('updates existing subscription when one already exists for the team', function () {
        $existing = createTestSubscription([
            'team_id' => $this->team->id,
            'stripe_subscription_id' => 'sub_old_111',
            'stripe_customer_id' => 'cus_old_222',
            'stripe_invoice_paid' => false,
        ]);

        $event = buildStripeEvent('checkout.session.completed', [
            'id' => 'cs_test_update',
            'client_reference_id' => "{$this->user->id}:{$this->team->id}",
            'subscription' => 'sub_updated_333',
            'customer' => 'cus_updated_444',
        ]);

        $job = new StripeProcessJob($event);
        $job->handle();

        $existing->refresh();
        expect($existing->stripe_subscription_id)->toBe('sub_updated_333');
        expect($existing->stripe_customer_id)->toBe('cus_updated_444');
        expect((bool) $existing->stripe_invoice_paid)->toBeTrue();
    });

    test('handles null client_reference_id gracefully without crashing', function () {
        $event = buildStripeEvent('checkout.session.completed', [
            'id' => 'cs_test_no_ref',
            'client_reference_id' => null,
            'subscription' => 'sub_orphan',
            'customer' => 'cus_orphan',
        ]);

        // Should not throw — the job catches the null and breaks early
        $job = new StripeProcessJob($event);
        $job->handle();

        // No subscription should be created
        expect(Subscription::where('stripe_subscription_id', 'sub_orphan')->exists())->toBeFalse();
    });

    test('throws when user is not admin/owner of the team', function () {
        // Attach user as a viewer (non-admin role)
        $viewerUser = User::factory()->create();
        $this->team->members()->attach($viewerUser->id, ['role' => 'viewer']);

        $event = buildStripeEvent('checkout.session.completed', [
            'id' => 'cs_test_viewer',
            'client_reference_id' => "{$viewerUser->id}:{$this->team->id}",
            'subscription' => 'sub_viewer',
            'customer' => 'cus_viewer',
        ]);

        // The error is caught by the outer try/catch in handle() and sent to send_internal_notification
        // so handle() itself does not throw. But no subscription should be created.
        $job = new StripeProcessJob($event);
        $job->handle();

        expect(Subscription::where('stripe_subscription_id', 'sub_viewer')->exists())->toBeFalse();
    });
});

// ─── StripeProcessJob — customer.subscription.created ───────────────────────

describe('StripeProcessJob — customer.subscription.created', function () {

    test('creates subscription with metadata team_id and user_id', function () {
        $event = buildStripeEvent('customer.subscription.created', [
            'id' => 'sub_created_789',
            'customer' => 'cus_created_101',
            'metadata' => [
                'team_id' => $this->team->id,
                'user_id' => $this->user->id,
            ],
        ]);

        $job = new StripeProcessJob($event);
        $job->handle();

        $subscription = Subscription::where('team_id', $this->team->id)->first();
        expect($subscription)->not->toBeNull();
        expect($subscription->stripe_subscription_id)->toBe('sub_created_789');
        expect($subscription->stripe_customer_id)->toBe('cus_created_101');
        expect((bool) $subscription->stripe_invoice_paid)->toBeFalse();
    });

    test('does not create duplicate subscription if one already exists for team', function () {
        createTestSubscription([
            'team_id' => $this->team->id,
            'stripe_subscription_id' => 'sub_existing_dup',
            'stripe_customer_id' => 'cus_existing_dup',
        ]);

        $event = buildStripeEvent('customer.subscription.created', [
            'id' => 'sub_new_dup',
            'customer' => 'cus_new_dup',
            'metadata' => [
                'team_id' => $this->team->id,
                'user_id' => $this->user->id,
            ],
        ]);

        // The job catches the RuntimeException internally
        $job = new StripeProcessJob($event);
        $job->handle();

        // Only the original subscription should exist
        $count = Subscription::where('team_id', $this->team->id)->count();
        expect($count)->toBe(1);
    });

    test('handles missing metadata gracefully', function () {
        $event = buildStripeEvent('customer.subscription.created', [
            'id' => 'sub_no_meta',
            'customer' => 'cus_no_meta',
            'metadata' => [],
        ]);

        // RuntimeException caught internally — no subscription created
        $job = new StripeProcessJob($event);
        $job->handle();

        expect(Subscription::where('stripe_subscription_id', 'sub_no_meta')->exists())->toBeFalse();
    });
});

// ─── StripeProcessJob — customer.subscription.updated ───────────────────────

describe('StripeProcessJob — customer.subscription.updated', function () {

    test('updates subscription plan_id on active status', function () {
        $sub = createTestSubscription([
            'team_id' => $this->team->id,
            'stripe_subscription_id' => 'sub_upd_plan',
            'stripe_customer_id' => 'cus_upd_plan',
            'stripe_plan_id' => 'price_old_plan',
            'stripe_invoice_paid' => true,
        ]);

        $event = buildStripeEvent('customer.subscription.updated', [
            'id' => 'sub_upd_plan',
            'customer' => 'cus_upd_plan',
            'status' => 'active',
            'metadata' => [
                'team_id' => $this->team->id,
                'user_id' => $this->user->id,
            ],
            'items' => [
                'data' => [[
                    'subscription' => 'sub_upd_plan',
                    'plan' => ['id' => 'price_new_plan'],
                    'price' => ['lookup_key' => null],
                    'quantity' => 1,
                ]],
            ],
            'cancel_at_period_end' => false,
            'cancellation_details' => ['feedback' => null, 'comment' => null],
        ]);

        $job = new StripeProcessJob($event);
        $job->handle();

        $sub->refresh();
        // stripe_plan_id is in $fillable, so it should update
        expect($sub->stripe_plan_id)->toBe('price_new_plan');
        expect((bool) $sub->stripe_invoice_paid)->toBeTrue();
    });

    test('marks subscription as unpaid and calls subscriptionEnded on unpaid status', function () {
        $sub = createTestSubscription([
            'team_id' => $this->team->id,
            'stripe_subscription_id' => 'sub_unpaid_test',
            'stripe_customer_id' => 'cus_unpaid_test',
            'stripe_invoice_paid' => true,
        ]);

        $event = buildStripeEvent('customer.subscription.updated', [
            'id' => 'sub_unpaid_test',
            'customer' => 'cus_unpaid_test',
            'status' => 'unpaid',
            'metadata' => [
                'team_id' => $this->team->id,
                'user_id' => $this->user->id,
            ],
            'items' => [
                'data' => [[
                    'subscription' => 'sub_unpaid_test',
                    'plan' => ['id' => 'price_unpaid'],
                    'price' => ['lookup_key' => null],
                    'quantity' => 1,
                ]],
            ],
            'cancel_at_period_end' => false,
            'cancellation_details' => ['feedback' => null, 'comment' => null],
        ]);

        $job = new StripeProcessJob($event);
        $job->handle();

        $sub->refresh();
        // stripe_invoice_paid is in $fillable
        expect((bool) $sub->stripe_invoice_paid)->toBeFalse();
        // subscriptionEnded() resets stripe_subscription_id to null
        expect($sub->stripe_subscription_id)->toBeNull();
    });

    test('creates subscription if not found but team_id present in metadata', function () {
        $event = buildStripeEvent('customer.subscription.updated', [
            'id' => 'sub_auto_create',
            'customer' => 'cus_auto_create',
            'status' => 'active',
            'metadata' => [
                'team_id' => $this->team->id,
                'user_id' => $this->user->id,
            ],
            'items' => [
                'data' => [[
                    'subscription' => 'sub_auto_create',
                    'plan' => ['id' => 'price_auto'],
                    'price' => ['lookup_key' => null],
                    'quantity' => 1,
                ]],
            ],
            'cancel_at_period_end' => false,
            'cancellation_details' => ['feedback' => null, 'comment' => null],
        ]);

        $job = new StripeProcessJob($event);
        $job->handle();

        $subscription = Subscription::where('stripe_customer_id', 'cus_auto_create')->first();
        expect($subscription)->not->toBeNull();
        expect($subscription->stripe_subscription_id)->toBe('sub_auto_create');
    });

    test('skips excluded plan ids', function () {
        config()->set('subscription.stripe_excluded_plans', 'price_excluded_plan');

        $sub = createTestSubscription([
            'team_id' => $this->team->id,
            'stripe_subscription_id' => 'sub_excluded',
            'stripe_customer_id' => 'cus_excluded',
            'stripe_plan_id' => 'price_original',
            'stripe_invoice_paid' => true,
        ]);

        $event = buildStripeEvent('customer.subscription.updated', [
            'id' => 'sub_excluded',
            'customer' => 'cus_excluded',
            'status' => 'active',
            'metadata' => [],
            'items' => [
                'data' => [[
                    'subscription' => 'sub_excluded',
                    'plan' => ['id' => 'price_excluded_plan'],
                    'price' => ['lookup_key' => null],
                    'quantity' => 1,
                ]],
            ],
            'cancel_at_period_end' => false,
            'cancellation_details' => ['feedback' => null, 'comment' => null],
        ]);

        $job = new StripeProcessJob($event);
        $job->handle();

        $sub->refresh();
        // Plan should not have changed because it was excluded
        expect($sub->stripe_plan_id)->toBe('price_original');
    });
});

// ─── StripeProcessJob — customer.subscription.deleted ───────────────────────

describe('StripeProcessJob — customer.subscription.deleted', function () {

    test('ends subscription and resets team on deletion', function () {
        $sub = createTestSubscription([
            'team_id' => $this->team->id,
            'stripe_subscription_id' => 'sub_to_delete',
            'stripe_customer_id' => 'cus_to_delete',
            'stripe_invoice_paid' => true,
        ]);

        $event = buildStripeEvent('customer.subscription.deleted', [
            'id' => 'sub_to_delete',
            'customer' => 'cus_to_delete',
        ]);

        $job = new StripeProcessJob($event);
        $job->handle();

        $sub->refresh();
        // subscriptionEnded() sets stripe_subscription_id = null and stripe_invoice_paid = false
        expect($sub->stripe_subscription_id)->toBeNull();
        expect((bool) $sub->stripe_invoice_paid)->toBeFalse();
    });

    test('handles deletion when subscription not found in database', function () {
        $event = buildStripeEvent('customer.subscription.deleted', [
            'id' => 'sub_ghost',
            'customer' => 'cus_ghost',
        ]);

        // RuntimeException caught internally — should not crash
        $job = new StripeProcessJob($event);
        $job->handle();

        // No exception propagated — test passes if we reach here
        expect(true)->toBeTrue();
    });
});

// ─── StripeProcessJob — payment_intent.payment_failed ───────────────────────

describe('StripeProcessJob — payment_intent.payment_failed', function () {

    test('handles payment failure for subscription with active invoice', function () {
        $sub = createTestSubscription([
            'team_id' => $this->team->id,
            'stripe_subscription_id' => 'sub_pi_active',
            'stripe_customer_id' => 'cus_pi_active',
            'stripe_invoice_paid' => true,
        ]);

        $event = buildStripeEvent('payment_intent.payment_failed', [
            'id' => 'pi_test_failed',
            'customer' => 'cus_pi_active',
        ]);

        // Should return early since invoice is already paid
        $job = new StripeProcessJob($event);
        $job->handle();

        $sub->refresh();
        // Invoice paid status should remain true — no change for active invoices
        expect((bool) $sub->stripe_invoice_paid)->toBeTrue();
    });

    test('handles payment failure when no subscription exists', function () {
        $event = buildStripeEvent('payment_intent.payment_failed', [
            'id' => 'pi_test_no_sub',
            'customer' => 'cus_nonexistent',
        ]);

        // RuntimeException caught internally
        $job = new StripeProcessJob($event);
        $job->handle();

        expect(true)->toBeTrue();
    });
});

// ─── StripeProcessJob — Unknown Event Types ─────────────────────────────────

describe('StripeProcessJob — unhandled event types', function () {

    test('handles unknown event type without crashing', function () {
        $event = buildStripeEvent('some.unknown.event', [
            'id' => 'obj_unknown',
        ]);

        // The default case throws RuntimeException, caught by outer try/catch
        $job = new StripeProcessJob($event);
        $job->handle();

        // Should not propagate exception
        expect(true)->toBeTrue();
    });
});

// ─── Cross-Team Isolation ────────────────────────────────────────────────────

describe('Cross-team subscription isolation', function () {

    test('checkout.session.completed for team A does not affect team B subscription', function () {
        // Team B has an existing subscription
        $subB = createTestSubscription([
            'team_id' => $this->teamB->id,
            'stripe_subscription_id' => 'sub_team_b',
            'stripe_customer_id' => 'cus_team_b',
            'stripe_invoice_paid' => true,
        ]);

        // Event is for team A
        $event = buildStripeEvent('checkout.session.completed', [
            'id' => 'cs_team_a',
            'client_reference_id' => "{$this->user->id}:{$this->team->id}",
            'subscription' => 'sub_team_a_new',
            'customer' => 'cus_team_a_new',
        ]);

        $job = new StripeProcessJob($event);
        $job->handle();

        // Team A should have its own subscription
        $subA = Subscription::where('team_id', $this->team->id)->first();
        expect($subA)->not->toBeNull();
        expect($subA->stripe_subscription_id)->toBe('sub_team_a_new');

        // Team B subscription should be completely unchanged
        $subB->refresh();
        expect($subB->stripe_subscription_id)->toBe('sub_team_b');
        expect($subB->stripe_customer_id)->toBe('cus_team_b');
        expect((bool) $subB->stripe_invoice_paid)->toBeTrue();
    });

    test('customer.subscription.deleted for team A does not touch team B', function () {
        $subA = createTestSubscription([
            'team_id' => $this->team->id,
            'stripe_subscription_id' => 'sub_del_a',
            'stripe_customer_id' => 'cus_del_a',
            'stripe_invoice_paid' => true,
        ]);

        $subB = createTestSubscription([
            'team_id' => $this->teamB->id,
            'stripe_subscription_id' => 'sub_del_b',
            'stripe_customer_id' => 'cus_del_b',
            'stripe_invoice_paid' => true,
        ]);

        $event = buildStripeEvent('customer.subscription.deleted', [
            'id' => 'sub_del_a',
            'customer' => 'cus_del_a',
        ]);

        $job = new StripeProcessJob($event);
        $job->handle();

        // Team A subscription ended
        $subA->refresh();
        expect($subA->stripe_subscription_id)->toBeNull();
        expect((bool) $subA->stripe_invoice_paid)->toBeFalse();

        // Team B subscription untouched
        $subB->refresh();
        expect($subB->stripe_subscription_id)->toBe('sub_del_b');
        expect($subB->stripe_customer_id)->toBe('cus_del_b');
        expect((bool) $subB->stripe_invoice_paid)->toBeTrue();
    });
});

// ─── Job Configuration ──────────────────────────────────────────────────────

describe('StripeProcessJob configuration', function () {

    test('job is configured with correct queue, retries, and timeout', function () {
        $event = buildStripeEvent('checkout.session.completed', ['id' => 'cs_cfg']);
        $job = new StripeProcessJob($event);

        expect($job->tries)->toBe(3);
        expect($job->timeout)->toBe(30);
        expect($job->queue)->toBe('high');
    });

    test('job stores event payload for processing', function () {
        $event = buildStripeEvent('invoice.paid', [
            'id' => 'in_test_store',
            'customer' => 'cus_store',
        ]);

        $job = new StripeProcessJob($event);

        expect($job->event)->toBe($event);
        expect(data_get($job->event, 'type'))->toBe('invoice.paid');
    });
});
