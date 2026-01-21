import * as React from 'react';
import { SettingsLayout } from '../Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Button, Badge } from '@/components/ui';
import { Link } from '@inertiajs/react';
import { CreditCard, Download, TrendingUp, ArrowUpRight, Settings, ChevronRight } from 'lucide-react';

interface UsageMetric {
    label: string;
    current: number;
    limit: number;
    unit: string;
}

interface Invoice {
    id: number;
    date: string;
    amount: number;
    status: 'paid' | 'pending' | 'failed';
    downloadUrl?: string;
}

interface ServiceUsage {
    name: string;
    hours: number;
    cost: number;
}

const currentPlan = {
    name: 'Pro',
    price: 20,
    billingCycle: 'month',
    features: ['Unlimited projects', '1000 deployments/mo', '2500 build minutes', '100 GB bandwidth'],
};

const usageMetrics: UsageMetric[] = [
    { label: 'CPU Hours', current: 342, limit: 500, unit: 'hours/mo' },
    { label: 'Memory', current: 45.2, limit: 100, unit: 'GB/mo' },
    { label: 'Network', current: 1240, limit: 2500, unit: 'GB/mo' },
    { label: 'Storage', current: 25.8, limit: 50, unit: 'GB' },
];

const serviceUsage: ServiceUsage[] = [
    { name: 'Production API', hours: 142, cost: 45.20 },
    { name: 'Marketing Site', hours: 98, cost: 28.40 },
    { name: 'Staging Env', hours: 67, cost: 18.90 },
    { name: 'Documentation', hours: 35, cost: 9.50 },
];

const recentInvoices: Invoice[] = [
    { id: 1, date: '2024-03-01', amount: 102.00, status: 'paid', downloadUrl: '#' },
    { id: 2, date: '2024-02-01', amount: 98.50, status: 'paid', downloadUrl: '#' },
    { id: 3, date: '2024-01-01', amount: 105.20, status: 'paid', downloadUrl: '#' },
];

const paymentMethod = {
    type: 'Visa',
    last4: '4242',
    expiryMonth: '12',
    expiryYear: '2025',
};

export default function BillingIndex() {
    const getUsagePercentage = (current: number, limit: number) => {
        return (current / limit) * 100;
    };

    const getUsageColor = (percentage: number) => {
        if (percentage >= 90) return 'bg-danger';
        if (percentage >= 70) return 'bg-warning';
        return 'bg-primary';
    };

    const getStatusBadgeVariant = (status: string): 'success' | 'warning' | 'danger' => {
        switch (status) {
            case 'paid':
                return 'success';
            case 'pending':
                return 'warning';
            case 'failed':
                return 'danger';
            default:
                return 'success';
        }
    };

    const totalUsageCost = serviceUsage.reduce((sum, service) => sum + service.cost, 0);
    const estimatedCost = currentPlan.price + totalUsageCost;

    return (
        <SettingsLayout activeSection="billing">
            <div className="space-y-6">
                {/* Current Plan & Upgrade CTA */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Current Plan</CardTitle>
                                <CardDescription>
                                    Manage your subscription and billing
                                </CardDescription>
                            </div>
                            <Link href="/settings/billing/plans">
                                <Button>
                                    <TrendingUp className="mr-2 h-4 w-4" />
                                    Upgrade Plan
                                </Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="rounded-lg border border-primary/20 bg-primary/5 p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <h3 className="text-2xl font-bold text-foreground">
                                        {currentPlan.name} Plan
                                    </h3>
                                    <p className="mt-1 text-foreground-muted">
                                        ${currentPlan.price}/{currentPlan.billingCycle}
                                    </p>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {currentPlan.features.map((feature, index) => (
                                            <span
                                                key={index}
                                                className="inline-flex items-center rounded-full bg-background-secondary px-3 py-1 text-xs text-foreground-muted"
                                            >
                                                {feature}
                                            </span>
                                        ))}
                                    </div>
                                </div>
                                <Badge variant="success">Active</Badge>
                            </div>
                            <div className="mt-6 flex items-center justify-between rounded-lg border border-border bg-background p-4">
                                <div>
                                    <p className="text-sm font-medium text-foreground">Next billing date</p>
                                    <p className="text-xs text-foreground-muted">April 1, 2024</p>
                                </div>
                                <div className="text-right">
                                    <p className="text-sm font-medium text-foreground">Estimated cost</p>
                                    <p className="text-2xl font-bold text-foreground">${estimatedCost.toFixed(2)}</p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Usage This Month */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Usage This Month</CardTitle>
                                <CardDescription>
                                    Track your resource consumption
                                </CardDescription>
                            </div>
                            <Link href="/settings/billing/usage">
                                <Button variant="secondary" size="sm">
                                    View Details
                                    <ChevronRight className="ml-1 h-4 w-4" />
                                </Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {usageMetrics.map((metric) => {
                                const percentage = getUsagePercentage(metric.current, metric.limit);
                                const color = getUsageColor(percentage);

                                return (
                                    <div key={metric.label}>
                                        <div className="mb-2 flex items-center justify-between text-sm">
                                            <span className="font-medium text-foreground">{metric.label}</span>
                                            <span className="text-foreground-muted">
                                                {metric.current} / {metric.limit} {metric.unit}
                                            </span>
                                        </div>
                                        <div className="h-2 overflow-hidden rounded-full bg-background-tertiary">
                                            <div
                                                className={`h-full rounded-full transition-all ${color}`}
                                                style={{ width: `${Math.min(percentage, 100)}%` }}
                                            />
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </CardContent>
                </Card>

                {/* Usage Breakdown by Service */}
                <Card>
                    <CardHeader>
                        <CardTitle>Usage Breakdown by Service</CardTitle>
                        <CardDescription>
                            Current billing period cost breakdown
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {serviceUsage.map((service, index) => (
                                <div
                                    key={index}
                                    className="flex items-center justify-between rounded-lg border border-border bg-background p-4"
                                >
                                    <div className="flex-1">
                                        <p className="font-medium text-foreground">{service.name}</p>
                                        <p className="text-sm text-foreground-muted">{service.hours} hours</p>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-lg font-semibold text-foreground">
                                            ${service.cost.toFixed(2)}
                                        </p>
                                    </div>
                                </div>
                            ))}
                            <div className="rounded-lg border border-primary/20 bg-primary/5 p-4">
                                <div className="flex items-center justify-between">
                                    <p className="font-semibold text-foreground">Total Usage Cost</p>
                                    <p className="text-2xl font-bold text-primary">
                                        ${totalUsageCost.toFixed(2)}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-6 md:grid-cols-2">
                    {/* Payment Method */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>Payment Method</CardTitle>
                                    <CardDescription>
                                        Your default payment method
                                    </CardDescription>
                                </div>
                                <Link href="/settings/billing/payment-methods">
                                    <Button variant="ghost" size="sm">
                                        <Settings className="h-4 w-4" />
                                    </Button>
                                </Link>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center gap-4 rounded-lg border border-border bg-background p-4">
                                <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-background-tertiary">
                                    <CreditCard className="h-6 w-6 text-foreground-muted" />
                                </div>
                                <div>
                                    <p className="font-medium text-foreground">
                                        {paymentMethod.type} •••• {paymentMethod.last4}
                                    </p>
                                    <p className="text-sm text-foreground-muted">
                                        Expires {paymentMethod.expiryMonth}/{paymentMethod.expiryYear}
                                    </p>
                                </div>
                            </div>
                            <div className="mt-4">
                                <p className="text-xs text-foreground-subtle">
                                    Billing email: billing@example.com
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Recent Invoices */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>Recent Invoices</CardTitle>
                                    <CardDescription>
                                        View your billing history
                                    </CardDescription>
                                </div>
                                <Link href="/settings/billing/invoices">
                                    <Button variant="secondary" size="sm">
                                        View All
                                        <ChevronRight className="ml-1 h-4 w-4" />
                                    </Button>
                                </Link>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {recentInvoices.map((invoice) => (
                                    <div
                                        key={invoice.id}
                                        className="flex items-center justify-between rounded-lg border border-border bg-background p-3"
                                    >
                                        <div className="flex-1">
                                            <p className="text-sm font-medium text-foreground">
                                                {new Date(invoice.date).toLocaleDateString('en-US', {
                                                    month: 'short',
                                                    year: 'numeric',
                                                })}
                                            </p>
                                            <p className="text-xs text-foreground-muted">
                                                ${invoice.amount.toFixed(2)}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Badge variant={getStatusBadgeVariant(invoice.status)} className="text-xs">
                                                {invoice.status}
                                            </Badge>
                                            {invoice.downloadUrl && (
                                                <Button variant="ghost" size="sm">
                                                    <Download className="h-3 w-3" />
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </SettingsLayout>
    );
}
