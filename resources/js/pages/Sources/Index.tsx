import { Head, Link } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Badge } from '@/components/ui';
import { Plus, Github, GitlabIcon as GitLab, CheckCircle2, XCircle } from 'lucide-react';
import { Bitbucket } from '@/components/icons/Bitbucket';
import { StaggerList, StaggerItem, FadeIn } from '@/components/animation';

interface GitSource {
    id: number;
    uuid: string;
    name: string;
    type: 'github' | 'gitlab' | 'bitbucket';
    status: 'connected' | 'disconnected' | 'error';
    repos_count: number;
    last_synced_at?: string;
}

interface Props {
    sources: GitSource[];
}

export default function SourcesIndex({ sources = [] }: Props) {
    const getSourceIcon = (type: string) => {
        switch (type) {
            case 'github': return <Github className="h-8 w-8" />;
            case 'gitlab': return <GitLab className="h-8 w-8" />;
            case 'bitbucket': return <Bitbucket className="h-8 w-8 text-blue-500" />;
            default: return null;
        }
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'connected':
                return <Badge variant="success"><CheckCircle2 className="h-3 w-3 mr-1" /> Connected</Badge>;
            case 'disconnected':
                return <Badge variant="warning"><XCircle className="h-3 w-3 mr-1" /> Disconnected</Badge>;
            case 'error':
                return <Badge variant="danger"><XCircle className="h-3 w-3 mr-1" /> Error</Badge>;
            default:
                return <Badge>Unknown</Badge>;
        }
    };

    const sourceTypes = [
        { type: 'github', name: 'GitHub', description: 'Connect via GitHub App for better security and features', href: '/sources/github' },
        { type: 'gitlab', name: 'GitLab', description: 'Connect to GitLab.com or self-hosted GitLab', href: '/sources/gitlab' },
        { type: 'bitbucket', name: 'Bitbucket', description: 'Connect to Bitbucket Cloud', href: '/sources/bitbucket' },
    ];

    return (
        <AppLayout
            title="Git Sources"
            breadcrumbs={[
                { label: 'Dashboard', href: '/new' },
                { label: 'Sources' },
            ]}
        >
            <Head title="Git Sources" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Git Sources</h1>
                        <p className="text-foreground-muted mt-1">
                            Connect your Git providers to deploy applications
                        </p>
                    </div>
                </div>

                {/* Connected Sources */}
                {sources.length > 0 && (
                    <div className="space-y-4">
                        <h2 className="text-lg font-semibold">Connected Sources</h2>
                        <StaggerList className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {sources.map((source, i) => (
                                <StaggerItem key={source.id} index={i}>
                                <Card className="hover:border-primary/50 transition-colors">
                                    <CardContent className="p-6">
                                        <div className="flex items-start justify-between">
                                            <div className="flex items-center gap-4">
                                                {getSourceIcon(source.type)}
                                                <div>
                                                    <h3 className="font-semibold">{source.name}</h3>
                                                    <p className="text-sm text-foreground-muted capitalize">{source.type}</p>
                                                </div>
                                            </div>
                                            {getStatusBadge(source.status)}
                                        </div>
                                        <div className="mt-4 pt-4 border-t border-border">
                                            <div className="flex items-center justify-between text-sm">
                                                <span className="text-foreground-muted">{source.repos_count} repositories</span>
                                                <Link href={`/sources/${source.type}/${source.uuid}`} className="text-primary hover:underline">
                                                    Manage
                                                </Link>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                                </StaggerItem>
                            ))}
                        </StaggerList>
                    </div>
                )}

                {/* Add New Source */}
                <div className="space-y-4">
                    <h2 className="text-lg font-semibold">Add New Source</h2>
                    <StaggerList className="grid gap-4 md:grid-cols-3">
                        {sourceTypes.map((source, i) => (
                            <StaggerItem key={source.type} index={i}>
                            <Link href={source.href}>
                                <Card className="h-full hover:border-primary/50 transition-colors cursor-pointer">
                                    <CardContent className="p-6 flex flex-col items-center text-center">
                                        <div className="mb-4">
                                            {getSourceIcon(source.type)}
                                        </div>
                                        <h3 className="font-semibold mb-2">{source.name}</h3>
                                        <p className="text-sm text-foreground-muted">{source.description}</p>
                                        <Button variant="ghost" className="mt-4">
                                            <Plus className="h-4 w-4 mr-2" />
                                            Connect
                                        </Button>
                                    </CardContent>
                                </Card>
                            </Link>
                            </StaggerItem>
                        ))}
                    </StaggerList>
                </div>

                {/* Help */}
                <Card className="bg-info/5 border-info/20">
                    <CardContent className="p-6">
                        <h3 className="font-semibold mb-2">Why connect a Git source?</h3>
                        <ul className="text-sm text-foreground-muted space-y-2">
                            <li>• Automatically deploy on push to your repository</li>
                            <li>• Preview deployments for pull requests</li>
                            <li>• Secure access to private repositories</li>
                            <li>• Webhook-based deployments for instant updates</li>
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
