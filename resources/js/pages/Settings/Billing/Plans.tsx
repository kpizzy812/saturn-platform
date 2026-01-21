import * as React from 'react';
import { SettingsLayout } from '../Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Button, Badge } from '@/components/ui';
import { Check, X, Mail } from 'lucide-react';

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
}

const plans: Plan[] = [
    {
        id: 'free',
        name: 'Free',
        description: 'Perfect for hobby projects and experimentation',
        monthlyPrice: 0,
        yearlyPrice: 0,
        features: [
            { name: 'Up to 3 projects', included: true },
            { name: '100 deployments/month', included: true },
            { name: '500 build minutes/month', included: true },
            { name: '10 GB bandwidth/month', included: true },
            { name: '1 GB storage', included: true },
            { name: 'Community support', included: true },
            { name: 'Custom domains', included: false },
            { name: 'Team collaboration', included: false },
            { name: 'Priority support', included: false },
            { name: 'SSO & advanced security', included: false },
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
        features: [
            { name: 'Unlimited projects', included: true },
            { name: '1,000 deployments/month', included: true },
            { name: '2,500 build minutes/month', included: true },
            { name: '100 GB bandwidth/month', included: true },
            { name: '50 GB storage', included: true },
            { name: 'Email support', included: true },
            { name: 'Custom domains', included: true },
            { name: 'Up to 10 team members', included: true, value: '10 members' },
            { name: 'Priority support', included: false },
            { name: 'SSO & advanced security', included: false },
        ],
    },
    {
        id: 'enterprise',
        name: 'Enterprise',
        description: 'For large teams with advanced needs',
        monthlyPrice: 0,
        yearlyPrice: 0,
        features: [
            { name: 'Unlimited projects', included: true },
            { name: 'Unlimited deployments', included: true },
            { name: 'Custom build minutes', included: true, value: 'Custom' },
            { name: 'Custom bandwidth', included: true, value: 'Custom' },
            { name: 'Custom storage', included: true, value: 'Custom' },
            { name: '24/7 Priority support', included: true },
            { name: 'Custom domains', included: true },
            { name: 'Unlimited team members', included: true },
            { name: 'Priority support', included: true },
            { name: 'SSO & advanced security', included: true },
            { name: 'Dedicated support engineer', included: true },
            { name: 'Custom SLA', included: true },
        ],
    },
];

export default function BillingPlans() {
    const [billingCycle, setBillingCycle] = React.useState<'monthly' | 'yearly'>('monthly');

    const getPrice = (plan: Plan) => {
        if (plan.id === 'enterprise') {
            return 'Custom';
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

    return (
        <SettingsLayout activeSection="billing">
            <div className="space-y-6">
                {/* Header */}
                <div className="text-center">
                    <h2 className="text-3xl font-bold text-foreground">Choose Your Plan</h2>
                    <p className="mt-2 text-foreground-muted">
                        Select the perfect plan for your needs. Upgrade, downgrade, or cancel anytime.
                    </p>
                </div>

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
                    {plans.map((plan) => (
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
                                <CardTitle className="text-2xl">{plan.name}</CardTitle>
                                <CardDescription className="mt-2">{plan.description}</CardDescription>

                                <div className="mt-4">
                                    <div className="flex items-baseline gap-1">
                                        <span className="text-4xl font-bold text-foreground">
                                            {getPrice(plan)}
                                        </span>
                                        {plan.id !== 'enterprise' && (
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
                                                            : 'text-sm text-foreground-muted'
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
                                            >
                                                <Mail className="mr-2 h-4 w-4" />
                                                Contact Sales
                                            </Button>
                                        ) : (
                                            <Button
                                                variant={plan.recommended ? 'default' : 'secondary'}
                                                className="w-full"
                                            >
                                                Choose {plan.name}
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {/* Additional Info */}
                <Card className="bg-background-secondary">
                    <CardContent className="py-6">
                        <div className="text-center">
                            <h3 className="text-lg font-semibold text-foreground">
                                Need help choosing a plan?
                            </h3>
                            <p className="mt-2 text-sm text-foreground-muted">
                                Contact our sales team to find the perfect plan for your organization.
                            </p>
                            <Button variant="secondary" className="mt-4">
                                <Mail className="mr-2 h-4 w-4" />
                                Contact Sales
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    );
}
