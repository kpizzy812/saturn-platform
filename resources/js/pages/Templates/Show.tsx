import { AppLayout } from '@/components/layout';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Link } from '@inertiajs/react';
import { ArrowLeft, Download, CheckCircle2, Zap } from 'lucide-react';
import * as Icons from 'lucide-react';

interface TemplateDetail {
    id: string;
    name: string;
    description: string;
    longDescription: string;
    icon: React.ReactNode;
    iconBg: string;
    iconColor: string;
    category: string;
    tags: string[];
    deployCount: number;
    featured?: boolean;
    services: {
        name: string;
        icon: React.ReactNode;
        description: string;
    }[];
    features: string[];
    envVars: {
        name: string;
        description: string;
        required: boolean;
        default?: string;
    }[];
    documentation: {
        gettingStarted: string[];
        configuration: string[];
        deployment: string[];
    };
}

interface Props {
    template?: TemplateDetail;
}

export default function TemplateShow({ template }: Props) {
    // Loading state
    if (!template) {
        return (
            <AppLayout title="Loading..." showNewProject={false}>
                <div className="mx-auto max-w-5xl">
                    <div className="flex items-center justify-center py-12">
                        <div className="text-center">
                            <div className="mb-4 h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent mx-auto" />
                            <p className="text-foreground-muted">Loading template...</p>
                        </div>
                    </div>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout title={template.name} showNewProject={false}>
            <div className="mx-auto max-w-5xl">
                {/* Back link */}
                <Link
                    href="/templates"
                    className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="mr-2 h-4 w-4" />
                    Back to Templates
                </Link>

                {/* Header */}
                <div className="mb-8 rounded-xl border border-border/50 bg-gradient-to-br from-background-secondary to-background-secondary/50 p-8">
                    <div className="flex items-start gap-6">
                        <div className={`flex h-20 w-20 items-center justify-center rounded-2xl ${template.iconBg} ${template.iconColor} shadow-2xl`}>
                            {template.icon}
                        </div>
                        <div className="flex-1">
                            <div className="mb-3 flex items-center gap-3">
                                <h1 className="text-3xl font-bold text-foreground">{template.name}</h1>
                                {template.featured && (
                                    <Badge variant="success" className="animate-pulse">
                                        Featured
                                    </Badge>
                                )}
                            </div>
                            <p className="mb-4 text-lg text-foreground-muted">{template.longDescription}</p>
                            <div className="flex flex-wrap items-center gap-3">
                                <Badge variant="default">{template.category}</Badge>
                                <div className="flex items-center gap-1.5 text-sm text-foreground-muted">
                                    <Download className="h-4 w-4" />
                                    <span>{template.deployCount.toLocaleString()} deploys</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Deploy Button */}
                    <div className="mt-6 flex gap-3">
                        <Link href={`/templates/${template.id}/deploy`} className="flex-1">
                            <Button className="w-full" size="lg">
                                <Zap className="mr-2 h-5 w-5" />
                                Deploy Now
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Tags */}
                <div className="mb-8">
                    <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-foreground-muted">
                        Technologies
                    </h2>
                    <div className="flex flex-wrap gap-2">
                        {template.tags.map((tag) => (
                            <Badge key={tag} variant="default" className="text-sm">
                                {tag}
                            </Badge>
                        ))}
                    </div>
                </div>

                {/* Included Services */}
                <div className="mb-8">
                    <h2 className="mb-4 text-xl font-semibold text-foreground">Included Services</h2>
                    <div className="grid gap-4 md:grid-cols-3">
                        {template.services.map((service, index) => (
                            <div
                                key={index}
                                className="rounded-lg border border-border/50 bg-background-secondary p-4"
                            >
                                <div className="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-primary/20 text-primary">
                                    {service.icon}
                                </div>
                                <h3 className="mb-1 font-semibold text-foreground">{service.name}</h3>
                                <p className="text-sm text-foreground-muted">{service.description}</p>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Features */}
                <div className="mb-8">
                    <h2 className="mb-4 text-xl font-semibold text-foreground">Features</h2>
                    <div className="grid gap-3 md:grid-cols-2">
                        {template.features.map((feature, index) => (
                            <div key={index} className="flex items-start gap-3">
                                <CheckCircle2 className="mt-0.5 h-5 w-5 flex-shrink-0 text-primary" />
                                <span className="text-foreground-muted">{feature}</span>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Environment Variables */}
                <div className="mb-8">
                    <h2 className="mb-4 text-xl font-semibold text-foreground">Configuration Options</h2>
                    <div className="space-y-3">
                        {template.envVars.map((envVar, index) => (
                            <div
                                key={index}
                                className="rounded-lg border border-border/50 bg-background-secondary p-4"
                            >
                                <div className="flex items-start justify-between">
                                    <div className="flex-1">
                                        <div className="mb-1 flex items-center gap-2">
                                            <code className="text-sm font-mono font-semibold text-foreground">
                                                {envVar.name}
                                            </code>
                                            {envVar.required && (
                                                <Badge variant="danger" className="text-xs">
                                                    Required
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="text-sm text-foreground-muted">{envVar.description}</p>
                                        {envVar.default && (
                                            <div className="mt-2">
                                                <span className="text-xs text-foreground-subtle">Default: </span>
                                                <code className="text-xs text-foreground-muted">{envVar.default}</code>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Documentation */}
                <div className="mb-8">
                    <h2 className="mb-4 text-xl font-semibold text-foreground">Documentation</h2>
                    <div className="space-y-4">
                        {/* Getting Started */}
                        <div className="rounded-lg border border-border/50 bg-background-secondary p-5">
                            <h3 className="mb-3 font-semibold text-foreground">Getting Started</h3>
                            <ol className="space-y-2">
                                {template.documentation.gettingStarted.map((step, index) => (
                                    <li key={index} className="flex gap-3 text-foreground-muted">
                                        <span className="font-semibold text-primary">{index + 1}.</span>
                                        <span>{step}</span>
                                    </li>
                                ))}
                            </ol>
                        </div>

                        {/* Configuration */}
                        <div className="rounded-lg border border-border/50 bg-background-secondary p-5">
                            <h3 className="mb-3 font-semibold text-foreground">Configuration</h3>
                            <ul className="space-y-2">
                                {template.documentation.configuration.map((item, index) => (
                                    <li key={index} className="flex gap-3 text-foreground-muted">
                                        <CheckCircle2 className="mt-0.5 h-4 w-4 flex-shrink-0 text-primary" />
                                        <span>{item}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>

                        {/* Deployment */}
                        <div className="rounded-lg border border-border/50 bg-background-secondary p-5">
                            <h3 className="mb-3 font-semibold text-foreground">Deployment</h3>
                            <ul className="space-y-2">
                                {template.documentation.deployment.map((item, index) => (
                                    <li key={index} className="flex gap-3 text-foreground-muted">
                                        <CheckCircle2 className="mt-0.5 h-4 w-4 flex-shrink-0 text-primary" />
                                        <span>{item}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                </div>

                {/* CTA */}
                <div className="rounded-xl border border-border/50 bg-gradient-to-br from-primary/10 to-primary/5 p-8 text-center">
                    <h2 className="mb-2 text-2xl font-bold text-foreground">Ready to deploy?</h2>
                    <p className="mb-6 text-foreground-muted">
                        Get your {template.name} up and running in minutes.
                    </p>
                    <Link href={`/templates/${template.id}/deploy`}>
                        <Button size="lg">
                            <Zap className="mr-2 h-5 w-5" />
                            Deploy Now
                        </Button>
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}
