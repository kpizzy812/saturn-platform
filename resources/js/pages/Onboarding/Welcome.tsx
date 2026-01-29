import { Link } from '@inertiajs/react';
import { AuthLayout } from '@/components/layout';
import { Card, CardContent, Button } from '@/components/ui';
import {
    Rocket,
    Github,
    FileCode,
    BookOpen,
    ArrowRight,
    Zap,
    Shield,
    BarChart,
    Clock,
    CheckCircle,
} from 'lucide-react';

interface Props {
    user?: {
        name: string;
        email: string;
    };
}

export default function OnboardingWelcome({ user }: Props) {
    const userName = user?.name || 'there';
    const firstName = userName.split(' ')[0];

    return (
        <AuthLayout title="Welcome to Saturn">
            <div className="min-h-screen bg-background py-12">
                <div className="mx-auto max-w-6xl">
                    {/* Welcome Header */}
                    <div className="mb-12 text-center">
                        <div className="mb-4 flex justify-center">
                            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-purple-500">
                                <Rocket className="h-8 w-8 text-white" />
                            </div>
                        </div>
                        <h1 className="mb-2 text-4xl font-bold text-foreground">
                            Welcome to Saturn, {firstName}!
                        </h1>
                        <p className="text-lg text-foreground-muted">
                            Let's get you started with deploying your first application
                        </p>
                    </div>

                    {/* Quick Start Options */}
                    <div className="mb-12">
                        <h2 className="mb-6 text-center text-xl font-semibold text-foreground">
                            Choose how you want to get started
                        </h2>
                        <div className="grid gap-6 md:grid-cols-3">
                            {/* Deploy from GitHub */}
                            <Link href="/onboarding/connect-repo?provider=github">
                                <Card className="h-full transition-all hover:scale-105 hover:border-primary/50">
                                    <CardContent className="p-6 text-center">
                                        <div className="mb-4 flex justify-center">
                                            <div className="flex h-14 w-14 items-center justify-center rounded-lg bg-primary/10">
                                                <Github className="h-7 w-7 text-primary" />
                                            </div>
                                        </div>
                                        <h3 className="mb-2 text-lg font-semibold text-foreground">
                                            Deploy from GitHub
                                        </h3>
                                        <p className="mb-4 text-sm text-foreground-muted">
                                            Connect your GitHub repository and deploy automatically
                                        </p>
                                        <Button className="w-full">
                                            Connect GitHub
                                            <ArrowRight className="ml-2 h-4 w-4" />
                                        </Button>
                                    </CardContent>
                                </Card>
                            </Link>

                            {/* Deploy from Template */}
                            <Link href="/templates">
                                <Card className="h-full transition-all hover:scale-105 hover:border-primary/50">
                                    <CardContent className="p-6 text-center">
                                        <div className="mb-4 flex justify-center">
                                            <div className="flex h-14 w-14 items-center justify-center rounded-lg bg-info/10">
                                                <FileCode className="h-7 w-7 text-info" />
                                            </div>
                                        </div>
                                        <h3 className="mb-2 text-lg font-semibold text-foreground">
                                            Deploy from Template
                                        </h3>
                                        <p className="mb-4 text-sm text-foreground-muted">
                                            Start with a pre-configured template
                                        </p>
                                        <Button variant="ghost" className="w-full">
                                            Browse Templates
                                            <ArrowRight className="ml-2 h-4 w-4" />
                                        </Button>
                                    </CardContent>
                                </Card>
                            </Link>

                            {/* Create Empty Project */}
                            <Link href="/projects/create">
                                <Card className="h-full transition-all hover:scale-105 hover:border-primary/50">
                                    <CardContent className="p-6 text-center">
                                        <div className="mb-4 flex justify-center">
                                            <div className="flex h-14 w-14 items-center justify-center rounded-lg bg-warning/10">
                                                <Zap className="h-7 w-7 text-warning" />
                                            </div>
                                        </div>
                                        <h3 className="mb-2 text-lg font-semibold text-foreground">
                                            Create Empty Project
                                        </h3>
                                        <p className="mb-4 text-sm text-foreground-muted">
                                            Start from scratch with a blank project
                                        </p>
                                        <Button variant="ghost" className="w-full">
                                            Create Project
                                            <ArrowRight className="ml-2 h-4 w-4" />
                                        </Button>
                                    </CardContent>
                                </Card>
                            </Link>
                        </div>
                    </div>

                    {/* Feature Highlights */}
                    <div className="mb-12">
                        <h2 className="mb-6 text-center text-xl font-semibold text-foreground">
                            What you can do with Saturn
                        </h2>
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                            <FeatureCard
                                icon={<Rocket className="h-5 w-5 text-primary" />}
                                title="Deploy Instantly"
                                description="Push to deploy with automatic builds and zero downtime"
                            />
                            <FeatureCard
                                icon={<Shield className="h-5 w-5 text-primary" />}
                                title="SSL & Security"
                                description="Automatic SSL certificates and security best practices"
                            />
                            <FeatureCard
                                icon={<BarChart className="h-5 w-5 text-primary" />}
                                title="Monitor & Scale"
                                description="Real-time metrics and effortless horizontal scaling"
                            />
                            <FeatureCard
                                icon={<Clock className="h-5 w-5 text-primary" />}
                                title="Scheduled Tasks"
                                description="Cron jobs and one-time tasks made simple"
                            />
                        </div>
                    </div>

                    {/* Documentation Links */}
                    <Card className="mb-8">
                        <CardContent className="p-6">
                            <div className="flex flex-col items-center justify-between gap-4 md:flex-row">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-background-tertiary">
                                        <BookOpen className="h-5 w-5 text-foreground-muted" />
                                    </div>
                                    <div>
                                        <h3 className="font-semibold text-foreground">
                                            Need help getting started?
                                        </h3>
                                        <p className="text-sm text-foreground-muted">
                                            Check out our comprehensive documentation
                                        </p>
                                    </div>
                                </div>
                                <div className="flex gap-3">
                                    <Link href="/settings">
                                        <Button variant="ghost">View Settings</Button>
                                    </Link>
                                    <Link href="/servers">
                                        <Button variant="ghost">Add Server</Button>
                                    </Link>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Getting Started Checklist */}
                    <Card>
                        <CardContent className="p-6">
                            <h3 className="mb-4 text-lg font-semibold text-foreground">
                                Getting Started Checklist
                            </h3>
                            <div className="space-y-3">
                                <ChecklistItem
                                    completed
                                    text="Create your Saturn account"
                                />
                                <ChecklistItem
                                    completed={false}
                                    text="Connect a Git repository"
                                />
                                <ChecklistItem
                                    completed={false}
                                    text="Deploy your first application"
                                />
                                <ChecklistItem
                                    completed={false}
                                    text="Set up custom domain and SSL"
                                />
                                <ChecklistItem
                                    completed={false}
                                    text="Configure environment variables"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Skip Onboarding */}
                    <div className="mt-8 text-center">
                        <Link href="/dashboard">
                            <Button variant="ghost" size="sm">
                                Skip onboarding and go to dashboard
                            </Button>
                        </Link>
                    </div>
                </div>
            </div>
        </AuthLayout>
    );
}

function FeatureCard({
    icon,
    title,
    description,
}: {
    icon: React.ReactNode;
    title: string;
    description: string;
}) {
    return (
        <Card>
            <CardContent className="p-4">
                <div className="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                    {icon}
                </div>
                <h4 className="mb-1 font-semibold text-foreground">{title}</h4>
                <p className="text-sm text-foreground-muted">{description}</p>
            </CardContent>
        </Card>
    );
}

function ChecklistItem({ completed, text }: { completed: boolean; text: string }) {
    return (
        <div className="flex items-center gap-3">
            {completed ? (
                <div className="flex h-6 w-6 items-center justify-center rounded-full bg-primary">
                    <CheckCircle className="h-4 w-4 text-white" />
                </div>
            ) : (
                <div className="h-6 w-6 rounded-full border-2 border-border"></div>
            )}
            <span
                className={
                    completed
                        ? 'text-foreground-muted line-through'
                        : 'text-foreground'
                }
            >
                {text}
            </span>
        </div>
    );
}
