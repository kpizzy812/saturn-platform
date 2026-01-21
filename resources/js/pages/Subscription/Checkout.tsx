import * as React from 'react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Button, Badge } from '@/components/ui';
import { Link, router, usePage } from '@inertiajs/react';
import { ChevronLeft, CreditCard, Lock, Tag, Check, AlertCircle } from 'lucide-react';

interface CheckoutProps {
    plan?: string;
    cycle?: 'monthly' | 'yearly';
}

interface PlanDetails {
    id: string;
    name: string;
    price: number;
    billingCycle: 'monthly' | 'yearly';
    features: string[];
}

const planData: Record<string, { name: string; monthlyPrice: number; yearlyPrice: number; features: string[] }> = {
    starter: {
        name: 'Starter',
        monthlyPrice: 0,
        yearlyPrice: 0,
        features: ['Up to 2 servers', 'Up to 5 applications', '1 team member', '10 GB bandwidth'],
    },
    pro: {
        name: 'Pro',
        monthlyPrice: 20,
        yearlyPrice: 200,
        features: ['Up to 10 servers', 'Up to 50 applications', 'Up to 10 team members', '100 GB bandwidth', 'Priority support'],
    },
};

export default function SubscriptionCheckout() {
    const { url } = usePage();
    const urlParams = new URLSearchParams(url.split('?')[1] || '');
    const planId = urlParams.get('plan') || 'pro';
    const cycle = (urlParams.get('cycle') as 'monthly' | 'yearly') || 'monthly';

    const [promoCode, setPromoCode] = React.useState('');
    const [promoApplied, setPromoApplied] = React.useState(false);
    const [termsAccepted, setTermsAccepted] = React.useState(false);
    const [processing, setProcessing] = React.useState(false);

    const planInfo = planData[planId] || planData.pro;
    const basePrice = cycle === 'monthly' ? planInfo.monthlyPrice : planInfo.yearlyPrice;
    const discount = promoApplied ? basePrice * 0.2 : 0; // 20% discount for demo
    const totalPrice = basePrice - discount;

    const planDetails: PlanDetails = {
        id: planId,
        name: planInfo.name,
        price: basePrice,
        billingCycle: cycle,
        features: planInfo.features,
    };

    const handleApplyPromo = () => {
        if (promoCode.toLowerCase() === 'welcome20') {
            setPromoApplied(true);
        }
    };

    const handleCheckout = () => {
        if (!termsAccepted) {
            alert('Please accept the terms and conditions');
            return;
        }

        setProcessing(true);

        // Simulate payment processing
        setTimeout(() => {
            router.visit('/subscription/success');
        }, 2000);
    };

    return (
        <AppLayout
            title="Checkout"
            breadcrumbs={[
                { label: 'Subscription', href: '/subscription' },
                { label: 'Plans', href: '/subscription/plans' },
                { label: 'Checkout' },
            ]}
        >
            {/* Header */}
            <div className="mb-6">
                <Link href="/subscription/plans" className="mb-4 inline-flex items-center text-sm text-foreground-muted hover:text-foreground">
                    <ChevronLeft className="mr-1 h-4 w-4" />
                    Back to Plans
                </Link>
                <h1 className="text-2xl font-bold text-foreground">Complete Your Purchase</h1>
                <p className="text-foreground-muted">
                    Review your order and complete payment
                </p>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                {/* Payment Form */}
                <div className="lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Payment Details</CardTitle>
                            <CardDescription>
                                Enter your payment information
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {/* Stripe Payment Form Placeholder */}
                            <div className="rounded-lg border border-dashed border-border bg-background-secondary p-8 text-center">
                                <CreditCard className="mx-auto h-12 w-12 text-foreground-muted" />
                                <p className="mt-4 font-medium text-foreground">
                                    Stripe Payment Form
                                </p>
                                <p className="mt-2 text-sm text-foreground-muted">
                                    This is a placeholder for the Stripe payment form.
                                    <br />
                                    Integration will include card details, billing address, and payment processing.
                                </p>
                            </div>

                            {/* Promo Code */}
                            <div>
                                <label className="mb-2 block text-sm font-medium text-foreground">
                                    Promo Code
                                </label>
                                <div className="flex gap-2">
                                    <div className="relative flex-1">
                                        <Tag className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                                        <input
                                            type="text"
                                            value={promoCode}
                                            onChange={(e) => setPromoCode(e.target.value)}
                                            placeholder="Enter promo code"
                                            disabled={promoApplied}
                                            className="w-full rounded-lg border border-border bg-background py-2 pl-10 pr-4 text-sm text-foreground placeholder-foreground-subtle focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary disabled:opacity-50"
                                        />
                                    </div>
                                    <Button
                                        variant="secondary"
                                        onClick={handleApplyPromo}
                                        disabled={!promoCode || promoApplied}
                                    >
                                        Apply
                                    </Button>
                                </div>
                                {promoApplied && (
                                    <div className="mt-2 flex items-center gap-2 text-sm text-success">
                                        <Check className="h-4 w-4" />
                                        Promo code applied successfully!
                                    </div>
                                )}
                                <p className="mt-1 text-xs text-foreground-muted">
                                    Try "WELCOME20" for 20% off
                                </p>
                            </div>

                            {/* Terms & Conditions */}
                            <div className="rounded-lg border border-border bg-background p-4">
                                <label className="flex cursor-pointer items-start gap-3">
                                    <input
                                        type="checkbox"
                                        checked={termsAccepted}
                                        onChange={(e) => setTermsAccepted(e.target.checked)}
                                        className="mt-1 h-4 w-4 rounded border-border text-primary focus:ring-2 focus:ring-primary"
                                    />
                                    <span className="text-sm text-foreground">
                                        I agree to the{' '}
                                        <a href="#" className="text-primary hover:underline">
                                            Terms of Service
                                        </a>{' '}
                                        and{' '}
                                        <a href="#" className="text-primary hover:underline">
                                            Privacy Policy
                                        </a>
                                    </span>
                                </label>
                            </div>

                            {/* Security Notice */}
                            <div className="flex items-start gap-3 rounded-lg bg-background-secondary p-4">
                                <Lock className="h-5 w-5 flex-shrink-0 text-success" />
                                <div>
                                    <p className="text-sm font-medium text-foreground">
                                        Secure Payment
                                    </p>
                                    <p className="mt-1 text-xs text-foreground-muted">
                                        Your payment information is encrypted and secure. We use Stripe for payment processing.
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Order Summary */}
                <div>
                    <Card className="sticky top-6">
                        <CardHeader>
                            <CardTitle>Order Summary</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {/* Plan Details */}
                            <div>
                                <div className="flex items-start justify-between">
                                    <div>
                                        <p className="font-semibold text-foreground">{planDetails.name} Plan</p>
                                        <p className="text-sm text-foreground-muted">
                                            Billed {planDetails.billingCycle}
                                        </p>
                                    </div>
                                    <Badge variant="info">
                                        {planDetails.billingCycle === 'yearly' ? 'Save 17%' : 'Monthly'}
                                    </Badge>
                                </div>
                                <ul className="mt-3 space-y-2">
                                    {planDetails.features.map((feature, index) => (
                                        <li key={index} className="flex items-start gap-2 text-sm text-foreground-muted">
                                            <Check className="mt-0.5 h-4 w-4 flex-shrink-0 text-success" />
                                            <span>{feature}</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>

                            <div className="border-t border-border pt-4">
                                {/* Pricing Breakdown */}
                                <div className="space-y-2">
                                    <div className="flex justify-between text-sm">
                                        <span className="text-foreground-muted">Subtotal</span>
                                        <span className="text-foreground">${basePrice.toFixed(2)}</span>
                                    </div>
                                    {promoApplied && (
                                        <div className="flex justify-between text-sm">
                                            <span className="text-success">Discount (20%)</span>
                                            <span className="text-success">-${discount.toFixed(2)}</span>
                                        </div>
                                    )}
                                    <div className="border-t border-border pt-2">
                                        <div className="flex justify-between">
                                            <span className="font-semibold text-foreground">Total</span>
                                            <span className="text-2xl font-bold text-foreground">
                                                ${totalPrice.toFixed(2)}
                                            </span>
                                        </div>
                                        <p className="mt-1 text-xs text-foreground-muted">
                                            per {planDetails.billingCycle === 'monthly' ? 'month' : 'year'}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {/* Checkout Button */}
                            <Button
                                className="w-full"
                                size="lg"
                                onClick={handleCheckout}
                                disabled={processing || !termsAccepted}
                            >
                                {processing ? (
                                    <>
                                        <div className="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                                        Processing...
                                    </>
                                ) : (
                                    <>
                                        <Lock className="mr-2 h-4 w-4" />
                                        Complete Purchase
                                    </>
                                )}
                            </Button>

                            {/* Money Back Guarantee */}
                            <div className="flex items-start gap-2 rounded-lg bg-background-secondary p-3">
                                <AlertCircle className="h-4 w-4 flex-shrink-0 text-info" />
                                <p className="text-xs text-foreground-muted">
                                    30-day money-back guarantee. Cancel anytime, no questions asked.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
