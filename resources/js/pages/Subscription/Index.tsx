import * as React from 'react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Button, Badge } from '@/components/ui';
import { Link } from '@inertiajs/react';
import { CreditCard, TrendingUp, Server, Code, Users, ArrowUpRight, ChevronRight } from 'lucide-react';

interface UsageMetric {
    label: string;
    current: number;
    limit: number;
    unit: string;
    icon: React.ComponentType<{ className?: string }>;
}

interface CurrentSubscription {
    plan: string;
    price: number;
    billingCycle: 'monthly' | 'yearly';
    status: 'active' | 'cancelled' | 'trial';
    nextBillingDate: string;
    trialEndsAt?: string;
}

const currentSubscription: CurrentSubscription = {
    plan: 'Pro',
    price: 20,
    billingCycle: 'monthly',
    status: 'active',
    nextBillingDate: '2024-04-01',
};

const usageMetrics: UsageMetric[] = [
    { label: 'Servers', current: 5, limit: 10, unit: 'servers', icon: Server },
    { label: 'Applications', current: 12, limit: 50, unit: 'apps', icon: Code },
    { label: 'Team Members', current: 3, limit: 10, unit: 'members', icon: Users },
];

export default function SubscriptionIndex() {
    const getUsagePercentage = (current: number, limit: number) => {
        return (current / limit) * 100;
    };

    const getUsageColor = (percentage: number) => {
        if (percentage >= 90) return 'bg-danger';
        if (percentage >= 70) return 'bg-warning';
        return 'bg-primary';
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'active':
                return <Badge variant="success">Active</Badge>;
            case 'trial':
                return <Badge variant="info">Trial</Badge>;
            case 'cancelled':
                return <Badge variant="danger">Cancelled</Badge>;
            default:
                return null;
        }
    };

    return (
        <AppLayout
            title="Subscription"
            breadcrumbs={[{ label: 'Subscription' }]}
        >
            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-foreground">Subscription</h1>
                    <p className="text-foreground-muted">Manage your subscription and usage</p>
                </div>
                <Link href="/subscription/plans">
                    <Button>
                        <TrendingUp className="mr-2 h-4 w-4" />
                        Manage Plan
                    </Button>
                </Link>
            </div>

            <div className="space-y-6">
                {/* Current Plan */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Current Plan</CardTitle>
                                <CardDescription>
                                    Your active subscription plan
                                </CardDescription>
                            </div>
                            {getStatusBadge(currentSubscription.status)}
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="rounded-lg border border-primary/20 bg-primary/5 p-6">
                            <div className="flex items-start justify-between">
                                <div>
                                    <h3 className="text-3xl font-bold text-foreground">
                                        {currentSubscription.plan}
                                    </h3>
                                    <p className="mt-2 text-lg text-foreground-muted">
                                        ${currentSubscription.price}/
                                        {currentSubscription.billingCycle === 'monthly' ? 'month' : 'year'}
                                    </p>
                                    {currentSubscription.status === 'trial' && currentSubscription.trialEndsAt && (
                                        <p className="mt-2 text-sm text-warning">
                                            Trial ends on {new Date(currentSubscription.trialEndsAt).toLocaleDateString()}
                                        </p>
                                    )}
                                </div>
                                <div className="flex gap-2">
                                    <Link href="/subscription/plans">
                                        <Button variant="secondary">
                                            Change Plan
                                        </Button>
                                    </Link>
                                </div>
                            </div>
                            <div className="mt-6 flex items-center justify-between rounded-lg border border-border bg-background p-4">
                                <div>
                                    <p className="text-sm font-medium text-foreground">Next billing date</p>
                                    <p className="text-xs text-foreground-muted">
                                        {new Date(currentSubscription.nextBillingDate).toLocaleDateString('en-US', {
                                            month: 'long',
                                            day: 'numeric',
                                            year: 'numeric',
                                        })}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <p className="text-sm font-medium text-foreground">Amount due</p>
                                    <p className="text-2xl font-bold text-foreground">
                                        ${currentSubscription.price.toFixed(2)}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Usage */}
                <Card>
                    <CardHeader>
                        <CardTitle>Usage & Limits</CardTitle>
                        <CardDescription>
                            Track your resource usage against plan limits
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-6">
                            {usageMetrics.map((metric) => {
                                const percentage = getUsagePercentage(metric.current, metric.limit);
                                const color = getUsageColor(percentage);
                                const Icon = metric.icon;

                                return (
                                    <div key={metric.label}>
                                        <div className="mb-3 flex items-center justify-between">
                                            <div className="flex items-center gap-3">
                                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-background-tertiary">
                                                    <Icon className="h-5 w-5 text-foreground-muted" />
                                                </div>
                                                <div>
                                                    <p className="font-medium text-foreground">{metric.label}</p>
                                                    <p className="text-sm text-foreground-muted">
                                                        {metric.current} of {metric.limit} {metric.unit}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <p className="text-sm font-semibold text-foreground">
                                                    {percentage.toFixed(0)}%
                                                </p>
                                                <p className="text-xs text-foreground-muted">used</p>
                                            </div>
                                        </div>
                                        <div className="h-2 overflow-hidden rounded-full bg-background-tertiary">
                                            <div
                                                className={`h-full rounded-full transition-all ${color}`}
                                                style={{ width: `${Math.min(percentage, 100)}%` }}
                                            />
                                        </div>
                                        {percentage >= 90 && (
                                            <p className="mt-2 text-sm text-danger">
                                                You're approaching the limit. Consider upgrading your plan.
                                            </p>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-6 md:grid-cols-2">
                    {/* Payment Method */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Payment Method</CardTitle>
                            <CardDescription>
                                Manage your billing information
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center gap-4 rounded-lg border border-border bg-background p-4">
                                <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-background-tertiary">
                                    <CreditCard className="h-6 w-6 text-foreground-muted" />
                                </div>
                                <div className="flex-1">
                                    <p className="font-medium text-foreground">Visa •••• 4242</p>
                                    <p className="text-sm text-foreground-muted">Expires 12/2025</p>
                                </div>
                                <Link href="/settings/billing/payment-methods">
                                    <Button variant="ghost" size="sm">
                                        Update
                                    </Button>
                                </Link>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Quick Actions */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Quick Actions</CardTitle>
                            <CardDescription>
                                Manage your subscription
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                <Link href="/settings/billing/invoices" className="block">
                                    <div className="flex items-center justify-between rounded-lg border border-border bg-background p-3 transition-colors hover:border-primary/50">
                                        <span className="text-sm font-medium text-foreground">
                                            View Invoices
                                        </span>
                                        <ChevronRight className="h-4 w-4 text-foreground-muted" />
                                    </div>
                                </Link>
                                <Link href="/subscription/plans" className="block">
                                    <div className="flex items-center justify-between rounded-lg border border-border bg-background p-3 transition-colors hover:border-primary/50">
                                        <span className="text-sm font-medium text-foreground">
                                            Compare Plans
                                        </span>
                                        <ChevronRight className="h-4 w-4 text-foreground-muted" />
                                    </div>
                                </Link>
                                <Link href="/settings/billing" className="block">
                                    <div className="flex items-center justify-between rounded-lg border border-border bg-background p-3 transition-colors hover:border-primary/50">
                                        <span className="text-sm font-medium text-foreground">
                                            Billing Settings
                                        </span>
                                        <ChevronRight className="h-4 w-4 text-foreground-muted" />
                                    </div>
                                </Link>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Need Help */}
                <Card className="bg-background-secondary">
                    <CardContent className="py-6">
                        <div className="text-center">
                            <h3 className="text-lg font-semibold text-foreground">
                                Need help with your subscription?
                            </h3>
                            <p className="mt-2 text-sm text-foreground-muted">
                                Our support team is here to help you choose the right plan and manage your subscription.
                            </p>
                            <Button variant="secondary" className="mt-4">
                                Contact Support
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
