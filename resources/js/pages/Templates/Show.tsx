import { AppLayout } from '@/components/layout';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Link } from '@inertiajs/react';
import { ArrowLeft, Download, ExternalLink, Zap, Box } from 'lucide-react';

interface Template {
    id: string;
    name: string;
    description: string;
    logo?: string | null;
    category: string;
    originalCategory?: string;
    tags: string[];
    deployCount: number;
    featured?: boolean;
    documentation?: string | null;
    port?: string | null;
}

interface Props {
    template?: Template;
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

    const logoUrl = template.logo ? `/${template.logo}` : null;

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
                        <div className="flex h-20 w-20 items-center justify-center rounded-2xl bg-background-tertiary shadow-2xl">
                            {logoUrl ? (
                                <img
                                    src={logoUrl}
                                    alt={template.name}
                                    className="h-12 w-12 object-contain"
                                    onError={(e) => {
                                        e.currentTarget.style.display = 'none';
                                        e.currentTarget.nextElementSibling?.classList.remove('hidden');
                                    }}
                                />
                            ) : null}
                            <Box className={`h-10 w-10 text-foreground-muted ${logoUrl ? 'hidden' : ''}`} />
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
                            <p className="mb-4 text-lg text-foreground-muted">{template.description}</p>
                            <div className="flex flex-wrap items-center gap-3">
                                <Badge variant="default">{template.category}</Badge>
                                {template.originalCategory && template.originalCategory !== template.category && (
                                    <Badge variant="default" className="opacity-60">{template.originalCategory}</Badge>
                                )}
                                <div className="flex items-center gap-1.5 text-sm text-foreground-muted">
                                    <Download className="h-4 w-4" />
                                    <span>{template.deployCount.toLocaleString()} deploys</span>
                                </div>
                                {template.port && (
                                    <div className="text-sm text-foreground-muted">
                                        Port: <code className="rounded bg-background-tertiary px-1.5 py-0.5">{template.port}</code>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Action Buttons */}
                    <div className="mt-6 flex gap-3">
                        <Link href={`/templates/${template.id}/deploy`} className="flex-1">
                            <Button className="w-full" size="lg">
                                <Zap className="mr-2 h-5 w-5" />
                                Deploy Now
                            </Button>
                        </Link>
                        {template.documentation && (
                            <a
                                href={template.documentation}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="flex-shrink-0"
                            >
                                <Button variant="secondary" size="lg">
                                    <ExternalLink className="mr-2 h-5 w-5" />
                                    Documentation
                                </Button>
                            </a>
                        )}
                    </div>
                </div>

                {/* Tags */}
                {template.tags.length > 0 && (
                    <div className="mb-8">
                        <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-foreground-muted">
                            Tags
                        </h2>
                        <div className="flex flex-wrap gap-2">
                            {template.tags.map((tag) => (
                                <Badge key={tag} variant="default" className="text-sm">
                                    {tag}
                                </Badge>
                            ))}
                        </div>
                    </div>
                )}

                {/* Info Card */}
                <div className="mb-8 rounded-xl border border-border/50 bg-background-secondary p-6">
                    <h2 className="mb-4 text-xl font-semibold text-foreground">About this template</h2>
                    <p className="text-foreground-muted mb-4">
                        {template.description}
                    </p>
                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="rounded-lg bg-background-tertiary p-4">
                            <div className="text-2xl font-bold text-foreground">{template.deployCount.toLocaleString()}</div>
                            <div className="text-sm text-foreground-muted">Total Deployments</div>
                        </div>
                        <div className="rounded-lg bg-background-tertiary p-4">
                            <div className="text-2xl font-bold text-foreground">{template.category}</div>
                            <div className="text-sm text-foreground-muted">Category</div>
                        </div>
                        <div className="rounded-lg bg-background-tertiary p-4">
                            <div className="text-2xl font-bold text-foreground">{template.tags.length}</div>
                            <div className="text-sm text-foreground-muted">Tags</div>
                        </div>
                    </div>
                </div>

                {/* CTA */}
                <div className="rounded-xl border border-border/50 bg-gradient-to-br from-primary/10 to-primary/5 p-8 text-center">
                    <h2 className="mb-2 text-2xl font-bold text-foreground">Ready to deploy?</h2>
                    <p className="mb-6 text-foreground-muted">
                        Get {template.name} up and running on your server in minutes.
                    </p>
                    <div className="flex justify-center gap-3">
                        <Link href={`/templates/${template.id}/deploy`}>
                            <Button size="lg">
                                <Zap className="mr-2 h-5 w-5" />
                                Deploy Now
                            </Button>
                        </Link>
                        {template.documentation && (
                            <a
                                href={template.documentation}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <Button variant="secondary" size="lg">
                                    <ExternalLink className="mr-2 h-5 w-5" />
                                    View Docs
                                </Button>
                            </a>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
