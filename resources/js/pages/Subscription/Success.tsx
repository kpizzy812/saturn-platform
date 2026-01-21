import * as React from 'react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Button } from '@/components/ui';
import { Link } from '@inertiajs/react';
import { CheckCircle, Server, Code, ArrowRight, Mail, FileText } from 'lucide-react';

interface NextStep {
    title: string;
    description: string;
    icon: React.ComponentType<{ className?: string }>;
    href: string;
    buttonText: string;
}

const nextSteps: NextStep[] = [
    {
        title: 'Connect Your First Server',
        description: 'Add a server to start deploying applications',
        icon: Server,
        href: '/servers/create',
        buttonText: 'Add Server',
    },
    {
        title: 'Deploy Your First Application',
        description: 'Deploy an application from your Git repository',
        icon: Code,
        href: '/applications/create',
        buttonText: 'Deploy App',
    },
];

export default function SubscriptionSuccess() {
    return (
        <AppLayout
            title="Success"
            breadcrumbs={[
                { label: 'Subscription', href: '/subscription' },
                { label: 'Success' },
            ]}
        >
            <div className="mx-auto max-w-3xl">
                {/* Success Message */}
                <Card className="border-success/20 bg-success/5">
                    <CardContent className="py-12 text-center">
                        <div className="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-success/10">
                            <CheckCircle className="h-12 w-12 text-success" />
                        </div>
                        <h1 className="mt-6 text-3xl font-bold text-foreground">
                            Welcome to Saturn Platform!
                        </h1>
                        <p className="mt-3 text-lg text-foreground-muted">
                            Your subscription has been activated successfully
                        </p>
                    </CardContent>
                </Card>

                {/* Plan Details */}
                <Card className="mt-6">
                    <CardHeader>
                        <CardTitle>Subscription Details</CardTitle>
                        <CardDescription>
                            Your new plan is now active
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            <div className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-4">
                                <div>
                                    <p className="text-sm text-foreground-muted">Plan</p>
                                    <p className="text-lg font-semibold text-foreground">Pro</p>
                                </div>
                                <div className="text-right">
                                    <p className="text-sm text-foreground-muted">Amount</p>
                                    <p className="text-lg font-semibold text-foreground">$20/month</p>
                                </div>
                            </div>

                            <div className="rounded-lg border border-border bg-background p-4">
                                <p className="text-sm font-medium text-foreground">What's Included</p>
                                <ul className="mt-3 space-y-2">
                                    <li className="flex items-start gap-2 text-sm text-foreground-muted">
                                        <CheckCircle className="mt-0.5 h-4 w-4 flex-shrink-0 text-success" />
                                        <span>Up to 10 servers</span>
                                    </li>
                                    <li className="flex items-start gap-2 text-sm text-foreground-muted">
                                        <CheckCircle className="mt-0.5 h-4 w-4 flex-shrink-0 text-success" />
                                        <span>Up to 50 applications</span>
                                    </li>
                                    <li className="flex items-start gap-2 text-sm text-foreground-muted">
                                        <CheckCircle className="mt-0.5 h-4 w-4 flex-shrink-0 text-success" />
                                        <span>Up to 10 team members</span>
                                    </li>
                                    <li className="flex items-start gap-2 text-sm text-foreground-muted">
                                        <CheckCircle className="mt-0.5 h-4 w-4 flex-shrink-0 text-success" />
                                        <span>100 GB bandwidth per month</span>
                                    </li>
                                    <li className="flex items-start gap-2 text-sm text-foreground-muted">
                                        <CheckCircle className="mt-0.5 h-4 w-4 flex-shrink-0 text-success" />
                                        <span>Priority support</span>
                                    </li>
                                </ul>
                            </div>

                            <div className="rounded-lg bg-background-secondary p-4">
                                <div className="flex items-start gap-3">
                                    <Mail className="mt-0.5 h-5 w-5 text-primary" />
                                    <div>
                                        <p className="text-sm font-medium text-foreground">
                                            Confirmation Email Sent
                                        </p>
                                        <p className="mt-1 text-xs text-foreground-muted">
                                            We've sent a confirmation email with your subscription details and receipt.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Next Steps */}
                <div className="mt-6">
                    <h2 className="mb-4 text-xl font-bold text-foreground">Next Steps</h2>
                    <div className="space-y-4">
                        {nextSteps.map((step, index) => {
                            const Icon = step.icon;

                            return (
                                <Card key={index}>
                                    <CardContent className="p-6">
                                        <div className="flex items-start gap-4">
                                            <div className="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-lg bg-primary/10">
                                                <Icon className="h-6 w-6 text-primary" />
                                            </div>
                                            <div className="flex-1">
                                                <h3 className="font-semibold text-foreground">{step.title}</h3>
                                                <p className="mt-1 text-sm text-foreground-muted">
                                                    {step.description}
                                                </p>
                                            </div>
                                            <Link href={step.href}>
                                                <Button variant="secondary">
                                                    {step.buttonText}
                                                    <ArrowRight className="ml-2 h-4 w-4" />
                                                </Button>
                                            </Link>
                                        </div>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                </div>

                {/* Quick Links */}
                <Card className="mt-6 bg-background-secondary">
                    <CardContent className="py-6">
                        <div className="grid gap-4 md:grid-cols-3">
                            <Link href="/subscription" className="block">
                                <div className="rounded-lg border border-border bg-background p-4 text-center transition-colors hover:border-primary/50">
                                    <FileText className="mx-auto h-6 w-6 text-foreground-muted" />
                                    <p className="mt-2 text-sm font-medium text-foreground">
                                        View Subscription
                                    </p>
                                </div>
                            </Link>
                            <Link href="/settings/billing/invoices" className="block">
                                <div className="rounded-lg border border-border bg-background p-4 text-center transition-colors hover:border-primary/50">
                                    <FileText className="mx-auto h-6 w-6 text-foreground-muted" />
                                    <p className="mt-2 text-sm font-medium text-foreground">
                                        View Invoices
                                    </p>
                                </div>
                            </Link>
                            <a href="mailto:support@example.com" className="block">
                                <div className="rounded-lg border border-border bg-background p-4 text-center transition-colors hover:border-primary/50">
                                    <Mail className="mx-auto h-6 w-6 text-foreground-muted" />
                                    <p className="mt-2 text-sm font-medium text-foreground">
                                        Contact Support
                                    </p>
                                </div>
                            </a>
                        </div>
                    </CardContent>
                </Card>

                {/* Return to Dashboard */}
                <div className="mt-8 text-center">
                    <Link href="/dashboard">
                        <Button size="lg">
                            Go to Dashboard
                            <ArrowRight className="ml-2 h-4 w-4" />
                        </Button>
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}
