# Billing Feature with Stripe Integration

This directory contains the billing management interface for Saturn, designed to integrate with Stripe for payment processing.

## Overview

The billing feature provides:
- **Billing Overview** - Current plan, usage statistics, and upcoming charges
- **Plan Selection** - Compare and upgrade/downgrade subscription plans
- **Payment Methods** - Manage credit cards and payment sources
- **Invoice History** - View and download past invoices
- **Usage Analytics** - Detailed resource consumption tracking

## Files Structure

```
resources/js/pages/Settings/Billing/
├── Index.tsx              # Billing overview dashboard
├── Plans.tsx              # Plan comparison and selection
├── PaymentMethods.tsx     # Payment method management
├── Invoices.tsx           # Invoice history and downloads
├── Usage.tsx              # Detailed usage analytics
└── README.md              # This file

resources/js/components/features/billing/
└── StripeCardElement.tsx  # Stripe card input component

resources/js/hooks/
└── useBilling.ts          # Billing-related React hooks

tests/Frontend/pages/Settings/
└── Billing.test.tsx       # Tests for billing functionality
```

## Setup Instructions

### 1. Install Stripe Dependencies

For full Stripe integration, install the required packages:

```bash
npm install @stripe/stripe-js @stripe/react-stripe-js
```

### 2. Configure Stripe

Add your Stripe publishable key to your environment configuration:

```env
STRIPE_PUBLISHABLE_KEY=pk_test_...
STRIPE_SECRET_KEY=sk_test_...
```

### 3. Backend Implementation

The API routes are defined in `routes/api.php` with placeholder implementations. You'll need to:

1. Create a `BillingController` in `app/Http/Controllers/Api/`
2. Implement Stripe integration using the Laravel Cashier package:
   ```bash
   composer require laravel/cashier
   ```
3. Update the API routes to use the controller
4. Configure webhook endpoints for Stripe events

### 4. Update StripeCardElement

Replace the demonstration `StripeCardElement` component with the official Stripe Elements:

```tsx
import { Elements, CardElement } from '@stripe/react-stripe-js';
import { loadStripe } from '@stripe/stripe-js';

const stripePromise = loadStripe(import.meta.env.VITE_STRIPE_PUBLISHABLE_KEY);

export function PaymentMethodForm() {
    return (
        <Elements stripe={stripePromise}>
            <CardElement
                options={{
                    style: {
                        base: {
                            fontSize: '16px',
                            color: '#424770',
                            '::placeholder': {
                                color: '#aab7c4',
                            },
                        },
                        invalid: {
                            color: '#9e2146',
                        },
                    },
                }}
            />
        </Elements>
    );
}
```

## Usage

### Hooks

The billing functionality is powered by custom React hooks:

```tsx
import {
    useBillingInfo,
    usePaymentMethods,
    useInvoices,
    useUsageDetails,
    useSubscription
} from '@/hooks';

function BillingPage() {
    // Get billing information
    const { billingInfo, isLoading, error, refetch } = useBillingInfo();

    // Manage payment methods
    const {
        paymentMethods,
        addPaymentMethod,
        removePaymentMethod,
        setDefaultPaymentMethod
    } = usePaymentMethods();

    // Fetch invoices
    const { invoices, downloadInvoice } = useInvoices();

    // Get usage details
    const { usage } = useUsageDetails();

    // Manage subscription
    const {
        updateSubscription,
        cancelSubscription,
        resumeSubscription
    } = useSubscription();
}
```

### Navigation

The billing pages are accessible via:
- Main billing: `/settings/billing`
- Plans: `/settings/billing/plans`
- Payment Methods: `/settings/billing/payment-methods`
- Invoices: `/settings/billing/invoices`
- Usage: `/settings/billing/usage`

## API Endpoints

All billing API endpoints are prefixed with `/api/v1/billing/`:

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/billing/info` | GET | Get billing overview |
| `/billing/payment-methods` | GET | List payment methods |
| `/billing/payment-methods` | POST | Add payment method |
| `/billing/payment-methods/{id}` | DELETE | Remove payment method |
| `/billing/payment-methods/{id}/default` | POST | Set default payment method |
| `/billing/invoices` | GET | List invoices |
| `/billing/invoices/{id}/download` | GET | Download invoice PDF |
| `/billing/usage` | GET | Get usage details |
| `/billing/subscription` | PATCH | Update subscription |
| `/billing/subscription/cancel` | POST | Cancel subscription |
| `/billing/subscription/resume` | POST | Resume subscription |

## Testing

Run the test suite:

```bash
npm run test:frontend
```

Or run tests in watch mode:

```bash
npm run test -- --watch
```

## Security Considerations

1. **PCI Compliance**: Never store raw card numbers. Always use Stripe's tokenization
2. **API Keys**: Keep Stripe secret keys secure and never expose them client-side
3. **Webhooks**: Verify Stripe webhook signatures to prevent tampering
4. **Authorization**: Ensure users can only access their own billing information

## Laravel Cashier Integration

For production use, integrate with Laravel Cashier:

```php
// app/Models/User.php or Team.php
use Laravel\Cashier\Billable;

class Team extends Model
{
    use Billable;
}

// Create a subscription
$team->newSubscription('default', 'price_xxx')
    ->create($paymentMethod);

// Update subscription
$team->subscription('default')
    ->swap('price_yyy');

// Cancel subscription
$team->subscription('default')
    ->cancel();
```

## Stripe Webhook Handler

Create a webhook controller to handle Stripe events:

```php
// app/Http/Controllers/StripeWebhookController.php
class StripeWebhookController extends CashierController
{
    public function handleInvoicePaymentSucceeded($payload)
    {
        // Handle successful payment
    }

    public function handleCustomerSubscriptionDeleted($payload)
    {
        // Handle cancelled subscription
    }
}
```

Register webhook route in `routes/webhooks.php`:

```php
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook']);
```

## Customization

### Adding New Plans

Update the plans array in `Plans.tsx`:

```tsx
const plans: Plan[] = [
    {
        id: 'starter',
        name: 'Starter',
        monthlyPrice: 10,
        yearlyPrice: 100,
        features: [/* ... */],
    },
    // Add more plans
];
```

### Custom Usage Metrics

Modify the usage metrics in `Usage.tsx`:

```tsx
const usageMetrics: UsageMetric[] = [
    {
        label: 'Custom Metric',
        current: 42,
        limit: 100,
        unit: 'units',
        icon: Activity,
        color: 'primary',
    },
];
```

## Troubleshooting

### Common Issues

1. **Stripe is not defined**: Ensure Stripe.js is properly loaded
2. **Payment method creation fails**: Check Stripe API keys and network requests
3. **Invoice downloads fail**: Verify invoice URLs are accessible
4. **Usage data not updating**: Check API endpoint responses

## Resources

- [Stripe Documentation](https://stripe.com/docs)
- [Laravel Cashier](https://laravel.com/docs/billing)
- [Stripe Elements](https://stripe.com/docs/stripe-js)
- [PCI Compliance](https://stripe.com/docs/security/guide)

## License

This billing feature is part of the Saturn project and follows the same license.
