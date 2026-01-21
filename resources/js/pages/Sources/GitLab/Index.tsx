import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button, Badge } from '@/components/ui';
import { Plus, Gitlab, CheckCircle2, RefreshCw, Trash2, ExternalLink, ArrowLeft } from 'lucide-react';

interface GitLabConnection {
    id: number;
    uuid: string;
    name: string;
    instance_url: string;
    status: 'active' | 'suspended' | 'pending';
    repos_count: number;
    group?: string;
    created_at: string;
    last_synced_at?: string;
}

interface Props {
    connections: GitLabConnection[];
}

export default function GitLabIndex({ connections = [] }: Props) {
    const handleSync = (uuid: string) => {
        router.post(`/sources/gitlab/${uuid}/sync`);
    };

    const handleDelete = (uuid: string) => {
        if (confirm('Are you sure you want to disconnect this GitLab instance?')) {
            router.delete(`/sources/gitlab/${uuid}`);
        }
    };

    return (
        <AppLayout
            title="GitLab Connections"
            breadcrumbs={[
                { label: 'Dashboard', href: '/new' },
                { label: 'Sources', href: '/sources' },
                { label: 'GitLab' },
            ]}
        >
            <Head title="GitLab Connections" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Link href="/sources">
                        <Button variant="ghost" size="sm">
                            <ArrowLeft className="h-4 w-4 mr-2" />
                            Back
                        </Button>
                    </Link>
                </div>

                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <div className="h-14 w-14 rounded-xl bg-[#FC6D26] flex items-center justify-center">
                            <Gitlab className="h-7 w-7 text-white" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold">GitLab Connections</h1>
                            <p className="text-foreground-muted">Manage your GitLab instance connections</p>
                        </div>
                    </div>
                    <Link href="/sources/gitlab/create">
                        <Button>
                            <Plus className="h-4 w-4 mr-2" />
                            Add GitLab Connection
                        </Button>
                    </Link>
                </div>

                {/* Connections List */}
                {connections.length === 0 ? (
                    <Card>
                        <CardContent className="p-12 text-center">
                            <Gitlab className="h-16 w-16 mx-auto mb-4 opacity-50" />
                            <h3 className="text-lg font-semibold mb-2">No GitLab connections</h3>
                            <p className="text-foreground-muted mb-6">
                                Connect to GitLab.com or your self-hosted GitLab instance to enable automatic deployments
                            </p>
                            <Link href="/sources/gitlab/create">
                                <Button>
                                    <Plus className="h-4 w-4 mr-2" />
                                    Connect GitLab
                                </Button>
                            </Link>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-4">
                        {connections.map(connection => (
                            <Card key={connection.id}>
                                <CardContent className="p-6">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-4">
                                            <div className="h-12 w-12 rounded-full bg-[#FC6D26]/10 flex items-center justify-center">
                                                <Gitlab className="h-6 w-6 text-[#FC6D26]" />
                                            </div>
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <h3 className="font-semibold">{connection.name}</h3>
                                                    <Badge variant={connection.status === 'active' ? 'success' : 'warning'}>
                                                        {connection.status}
                                                    </Badge>
                                                </div>
                                                <p className="text-sm text-foreground-muted">
                                                    {connection.instance_url} •
                                                    {connection.group ? ` @${connection.group} •` : ''}
                                                    {' '}{connection.repos_count} repositories
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Button variant="ghost" size="sm" onClick={() => handleSync(connection.uuid)}>
                                                <RefreshCw className="h-4 w-4 mr-2" />
                                                Sync
                                            </Button>
                                            <a
                                                href={connection.instance_url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                <Button variant="ghost" size="sm">
                                                    <ExternalLink className="h-4 w-4 mr-2" />
                                                    GitLab
                                                </Button>
                                            </a>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="text-danger hover:text-danger"
                                                onClick={() => handleDelete(connection.uuid)}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                    {connection.last_synced_at && (
                                        <p className="text-xs text-foreground-muted mt-4">
                                            Last synced: {new Date(connection.last_synced_at).toLocaleString()}
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                {/* Instructions */}
                <Card className="bg-background-secondary">
                    <CardHeader>
                        <CardTitle className="text-base">About GitLab Integration</CardTitle>
                    </CardHeader>
                    <CardContent className="text-sm text-foreground-muted space-y-2">
                        <p><strong>GitLab.com:</strong> Connect to the official GitLab.com service</p>
                        <p><strong>Self-hosted GitLab:</strong> Connect to your own GitLab instance (Community or Enterprise Edition)</p>
                        <p className="pt-2">You'll need:</p>
                        <ul className="list-disc list-inside space-y-1 pl-2">
                            <li>A GitLab Personal Access Token or OAuth application</li>
                            <li>Access to the repositories you want to deploy</li>
                            <li>Webhook permissions (for automatic deployments)</li>
                        </ul>
                    </CardContent>
                </Card>

                {/* Features */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Card className="bg-background-secondary">
                        <CardContent className="p-4">
                            <div className="flex items-start gap-3">
                                <CheckCircle2 className="h-5 w-5 text-success flex-shrink-0 mt-0.5" />
                                <div>
                                    <p className="font-medium text-sm">Automatic Deployments</p>
                                    <p className="text-xs text-foreground-muted mt-1">
                                        Deploy on push to specific branches
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="bg-background-secondary">
                        <CardContent className="p-4">
                            <div className="flex items-start gap-3">
                                <CheckCircle2 className="h-5 w-5 text-success flex-shrink-0 mt-0.5" />
                                <div>
                                    <p className="font-medium text-sm">Merge Request Previews</p>
                                    <p className="text-xs text-foreground-muted mt-1">
                                        Test changes before merging
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="bg-background-secondary">
                        <CardContent className="p-4">
                            <div className="flex items-start gap-3">
                                <CheckCircle2 className="h-5 w-5 text-success flex-shrink-0 mt-0.5" />
                                <div>
                                    <p className="font-medium text-sm">Multiple Instances</p>
                                    <p className="text-xs text-foreground-muted mt-1">
                                        Connect to multiple GitLab servers
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
