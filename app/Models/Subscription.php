<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    /**
     * SECURITY: Using $fillable instead of $guarded = [] to prevent mass assignment vulnerabilities.
     * Excludes: id, team_id (security)
     */
    protected $fillable = [
        'stripe_subscription_id',
        'stripe_customer_id',
        'stripe_plan_id',
        'stripe_invoice_paid',
        'lemon_subscription_id',
        'lemon_customer_id',
        'lemon_plan_id',
        'lemon_variant_id',
        'lemon_order_id',
        'lemon_product_id',
        'lemon_update_payment_link',
        'lemon_renews_at',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function type()
    {
        if (isStripe()) {
            if (! $this->stripe_plan_id) {
                return 'zero';
            }
            $subscription = Subscription::where('id', $this->id)->first();
            if (! $subscription) {
                return null;
            }
            $subscriptionPlanId = data_get($subscription, 'stripe_plan_id');
            if (! $subscriptionPlanId) {
                return null;
            }
            $subscriptionInvoicePaid = data_get($subscription, 'stripe_invoice_paid');
            if (! $subscriptionInvoicePaid) {
                return null;
            }
            $subscriptionConfigs = collect(config('subscription'));
            $stripePlanId = null;
            $subscriptionConfigs->map(function ($value, $key) use ($subscriptionPlanId, &$stripePlanId) {
                if ($value === $subscriptionPlanId) {
                    $stripePlanId = $key;
                }
            })->first();
            if ($stripePlanId) {
                return str($stripePlanId)->after('stripe_price_id_')->before('_')->lower();
            }
        }

        return 'zero';
    }
}
