import * as React from 'react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Button, Badge } from '@/components/ui';
import { Link, router } from '@inertiajs/react';
import { Check, X, Zap, Crown, Building2, ChevronLeft } from 'lucide-react';

interface PlanFeature {
    name: string;
    included: boolean;
    value?: string;
}

interface Plan {
    id: string;
    name: string;
    description: string;
    monthlyPrice: number;
    yearlyPrice: number;
    features: PlanFeature[];
    recommended?: boolean;
    current?: boolean;
    icon: React.ComponentType<{ className?: string }>;
}

const plans: Plan[] = [
    {
        id: 'starter',
        name: 'Starter',
        description: 'Perfect for hobby projects and experimentation',
        monthlyPrice: 0,
        yearlyPrice: 0,
        icon: Zap,
        features: [
            { name: 'Up to 2 servers', included: true },
            { name: 'Up to 5 applications', included: true },
            { name: '1 team member', included: true },
            { name: '10 GB bandwidth/month', included: true },
            { name: '5 GB storage', included: true },
            { name: 'Community support', included: true },
            { name: 'Custom domains', included: true },
            { name: 'SSL certificates', included: true },
            { name: 'Docker support', included: true },
            { name: 'Automated backups', included: false },
            { name: 'Priority support', included: false },
            { name: 'Advanced monitoring', included: false },
        ],
    },
    {
        id: 'pro',
        name: 'Pro',
        description: 'For professionals and growing teams',
        monthlyPrice: 20,
        yearlyPrice: 200,
        recommended: true,
        current: true,
        icon: Crown,
        features: [
            { name: 'Up to 10 servers', included: true },
            { name: 'Up to 50 applications', included: true },
            { name: 'Up to 10 team members', included: true },
            { name: '100 GB bandwidth/month', included: true },
            { name: '50 GB storage', included: true },
            { name: 'Email support', included: true },
            { name: 'Custom domains', included: true },
            { name: 'SSL certificates', included: true },
            { name: 'Docker support', included: true },
            { name: 'Automated backups', included: true },
            { name: 'Priority support', included: true },
            { name: 'Advanced monitoring', included: true },
        ],
    },
    {
        id: 'enterprise',
        name: 'Enterprise',
        description: 'For large teams with advanced needs',
        monthlyPrice: 0,
        yearlyPrice: 0,
        icon: Building2,
        features: [
            { name: 'Unlimited servers', included: true },
            { name: 'Unlimited applications', included: true },
            { name: 'Unlimited team members', included: true },
            { name: 'Custom bandwidth', included: true, value: 'Custom' },
            { name: 'Custom storage', included: true, value: 'Custom' },
            { name: '24/7 Priority support', included: true },
            { name: 'Custom domains', included: true },
            { name: 'SSL certificates', included: true },
            { name: 'Docker support', included: true },
            { name: 'Automated backups', included: true },
            { name: 'Advanced monitoring', included: true },
            { name: 'Dedicated support engineer', included: true },
            { name: 'Custom SLA', included: true },
            { name: 'SSO & SAML', included: true },
        ],
    },
];

export default function SubscriptionPlans() {
    const [billingCycle, setBillingCycle] = React.useState<'monthly' | 'yearly'>('monthly');

    const getPrice = (plan: Plan) => {
        if (plan.id === 'enterprise') {
            return 'Custom';
        }
        if (plan.id === 'starter') {
            return 'Free';
        }
        const price = billingCycle === 'monthly' ? plan.monthlyPrice : plan.yearlyPrice;
        return `$${price}`;
    };

    const getSavings = (plan: Plan) => {
        if (billingCycle === 'yearly' && plan.monthlyPrice > 0) {
            const yearlySavings = plan.monthlyPrice * 12 - plan.yearlyPrice;
            if (yearlySavings > 0) {
                return `Save $${yearlySavings}/year`;
            }
        }
        return null;
    };

    const handleSelectPlan = (planId: string) => {
        if (planId === 'enterprise') {
            // Redirect to contact sales or open support
            window.location.href = 'mailto:sales@example.com';
        } else {
            // Redirect to checkout
            router.visit(`/subscription/checkout?plan=${planId}&cycle=${billingCycle}`);
        }
    };

    return (
        <AppLayout
            title="Plans"
            breadcrumbs={[
                { label: 'Subscription', href: '/subscription' },
                { label: 'Plans' },
            ]}
        >
            {/* Header */}
            <div className="mb-6">
                <Link href="/subscription" className="mb-4 inline-flex items-center text-sm text-foreground-muted hover:text-foreground">
                    <ChevronLeft className="mr-1 h-4 w-4" />
                    Back to Subscription
                </Link>
                <h1 className="text-2xl font-bold text-foreground">Choose Your Plan</h1>
                <p className="text-foreground-muted">
                    Select the perfect plan for your needs. Upgrade, downgrade, or cancel anytime.
                </p>
            </div>

            <div className="space-y-6">
                {/* Billing Cycle Toggle */}
                <div className="flex justify-center">
                    <div className="inline-flex items-center rounded-lg bg-background-secondary p-1">
                        <button
                            onClick={() => setBillingCycle('monthly')}
                            className={`rounded-md px-6 py-2 text-sm font-medium transition-colors ${
                                billingCycle === 'monthly'
                                    ? 'bg-primary text-white'
                                    : 'text-foreground-muted hover:text-foreground'
                            }`}
                        >
                            Monthly
                        </button>
                        <button
                            onClick={() => setBillingCycle('yearly')}
                            className={`rounded-md px-6 py-2 text-sm font-medium transition-colors ${
                                billingCycle === 'yearly'
                                    ? 'bg-primary text-white'
                                    : 'text-foreground-muted hover:text-foreground'
                            }`}
                        >
                            Yearly
                            <span className="ml-2 text-xs text-success">(Save 17%)</span>
                        </button>
                    </div>
                </div>

                {/* Plans Grid */}
                <div className="grid gap-6 lg:grid-cols-3">
                    {plans.map((plan) => {
                        const Icon = plan.icon;

                        return (
                            <Card
                                key={plan.id}
                                className={
                                    plan.recommended
                                        ? 'relative border-primary shadow-lg shadow-primary/20'
                                        : ''
                                }
                            >
                                {plan.recommended && (
                                    <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                                        <Badge variant="success" className="px-4 py-1">
                                            Recommended
                                        </Badge>
                                    </div>
                                )}
                                {plan.current && (
                                    <div className="absolute right-4 top-4">
                                        <Badge variant="info" className="px-3 py-1">
                                            Current Plan
                                        </Badge>
                                    </div>
                                )}

                                <CardHeader>
                                    <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                        <Icon className="h-6 w-6 text-primary" />
                                    </div>
                                    <CardTitle className="text-2xl">{plan.name}</CardTitle>
                                    <CardDescription className="mt-2">{plan.description}</CardDescription>

                                    <div className="mt-4">
                                        <div className="flex items-baseline gap-1">
                                            <span className="text-4xl font-bold text-foreground">
                                                {getPrice(plan)}
                                            </span>
                                            {plan.id !== 'enterprise' && plan.id !== 'starter' && (
                                                <span className="text-foreground-muted">
                                                    /{billingCycle === 'monthly' ? 'mo' : 'yr'}
                                                </span>
                                            )}
                                        </div>
                                        {getSavings(plan) && (
                                            <p className="mt-1 text-sm text-success">{getSavings(plan)}</p>
                                        )}
                                    </div>
                                </CardHeader>

                                <CardContent>
                                    <div className="space-y-4">
                                        {/* Features List */}
                                        <ul className="space-y-3">
                                            {plan.features.map((feature, index) => (
                                                <li key={index} className="flex items-start gap-3">
                                                    {feature.included ? (
                                                        <Check className="mt-0.5 h-5 w-5 flex-shrink-0 text-success" />
                                                    ) : (
                                                        <X className="mt-0.5 h-5 w-5 flex-shrink-0 text-foreground-subtle" />
                                                    )}
                                                    <span
                                                        className={
                                                            feature.included
                                                                ? 'text-sm text-foreground'
                                                                : 'text-sm text-foreground-muted line-through'
                                                        }
                                                    >
                                                        {feature.name}
                                                        {feature.value && (
                                                            <span className="ml-1 text-xs text-foreground-muted">
                                                                ({feature.value})
                                                            </span>
                                                        )}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>

                                        {/* CTA Button */}
                                        <div className="pt-4">
                                            {plan.current ? (
                                                <Button
                                                    variant="secondary"
                                                    className="w-full"
                                                    disabled
                                                >
                                                    Current Plan
                                                </Button>
                                            ) : plan.id === 'enterprise' ? (
                                                <Button
                                                    variant="default"
                                                    className="w-full"
                                                    onClick={() => handleSelectPlan(plan.id)}
                                                >
                                                    Contact Sales
                                                </Button>
                                            ) : (
                                                <Button
                                                    variant={plan.recommended ? 'default' : 'secondary'}
                                                    className="w-full"
                                                    onClick={() => handleSelectPlan(plan.id)}
                                                >
                                                    {plan.id === 'starter' ? 'Get Started' : `Choose ${plan.name}`}
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>

                {/* FAQ or Additional Info */}
                <Card className="bg-background-secondary">
                    <CardContent className="py-6">
                        <div className="text-center">
                            <h3 className="text-lg font-semibold text-foreground">
                                Need help choosing a plan?
                            </h3>
                            <p className="mt-2 text-sm text-foreground-muted">
                                Contact our sales team to find the perfect plan for your organization or learn more about our features.
                            </p>
                            <div className="mt-4 flex justify-center gap-3">
                                <Button variant="secondary">
                                    Contact Sales
                                </Button>
                                <Button variant="ghost">
                                    Compare Features
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Trust Indicators */}
                <div className="grid gap-4 md:grid-cols-3">
                    <Card className="border-none bg-background-secondary">
                        <CardContent className="py-4 text-center">
                            <p className="text-sm font-medium text-foreground">Cancel Anytime</p>
                            <p className="mt-1 text-xs text-foreground-muted">
                                No long-term commitments required
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="border-none bg-background-secondary">
                        <CardContent className="py-4 text-center">
                            <p className="text-sm font-medium text-foreground">30-Day Money Back</p>
                            <p className="mt-1 text-xs text-foreground-muted">
                                Full refund if you're not satisfied
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="border-none bg-background-secondary">
                        <CardContent className="py-4 text-center">
                            <p className="text-sm font-medium text-foreground">Secure Payments</p>
                            <p className="mt-1 text-xs text-foreground-muted">
                                Powered by Stripe
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
