<?php

use App\Models\Subscription;
use App\Models\Team;

// Fillable Security Tests
test('fillable attributes are defined and not empty', function () {
    $subscription = new Subscription;
    expect($subscription->getFillable())->not->toBeEmpty();
});

test('fillable does not include id or team_id', function () {
    $fillable = (new Subscription)->getFillable();

    expect($fillable)
        ->not->toContain('id')
        ->not->toContain('team_id');
});

test('fillable includes Stripe fields', function () {
    $fillable = (new Subscription)->getFillable();

    expect($fillable)
        ->toContain('stripe_subscription_id')
        ->toContain('stripe_customer_id')
        ->toContain('stripe_plan_id')
        ->toContain('stripe_invoice_paid');
});

test('fillable includes Lemon fields', function () {
    $fillable = (new Subscription)->getFillable();

    expect($fillable)
        ->toContain('lemon_subscription_id')
        ->toContain('lemon_customer_id')
        ->toContain('lemon_plan_id')
        ->toContain('lemon_variant_id')
        ->toContain('lemon_order_id')
        ->toContain('lemon_product_id')
        ->toContain('lemon_update_payment_link')
        ->toContain('lemon_renews_at');
});

// Relationship Tests
test('team relationship returns belongsTo', function () {
    $relation = (new Subscription)->team();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Team::class);
});

// Method Existence Tests
test('type method exists', function () {
    expect(method_exists(new Subscription, 'type'))->toBeTrue();
});

// Attribute Tests
test('stripe_subscription_id attribute works', function () {
    $subscription = new Subscription;
    $subscription->stripe_subscription_id = 'sub_123';

    expect($subscription->stripe_subscription_id)->toBe('sub_123');
});

test('stripe_customer_id attribute works', function () {
    $subscription = new Subscription;
    $subscription->stripe_customer_id = 'cus_456';

    expect($subscription->stripe_customer_id)->toBe('cus_456');
});

test('lemon_subscription_id attribute works', function () {
    $subscription = new Subscription;
    $subscription->lemon_subscription_id = 'lemon_789';

    expect($subscription->lemon_subscription_id)->toBe('lemon_789');
});
