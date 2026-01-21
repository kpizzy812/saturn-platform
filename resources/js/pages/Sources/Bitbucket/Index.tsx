import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button, Badge } from '@/components/ui';
import { Plus, RefreshCw, Trash2, ExternalLink, ArrowLeft, CheckCircle2 } from 'lucide-react';
import { Bitbucket } from '@/components/icons/Bitbucket';

interface BitbucketConnection {
    id: number;
    uuid: string;
    name: string;
    workspace: string;
    status: 'active' | 'suspended' | 'pending';
    repos_count: number;
    type: 'cloud' | 'server';
    created_at: string;
    last_synced_at?: string;
}

interface Props {
    connections: BitbucketConnection[];
}

export default function BitbucketIndex({ connections = [] }: Props) {
    const handleSync = (uuid: string) => {
        router.post(`/sources/bitbucket/${uuid}/sync`);
    };

    const handleDelete = (uuid: string) => {
        if (confirm('Are you sure you want to disconnect this Bitbucket workspace?')) {
            router.delete(`/sources/bitbucket/${uuid}`);
        }
    };

    return (
        <AppLayout
            title="Bitbucket Connections"
            breadcrumbs={[
                { label: 'Dashboard', href: '/new' },
                { label: 'Sources', href: '/sources' },
                { label: 'Bitbucket' },
            ]}
        >
            <Head title="Bitbucket Connections" />

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
                        <div className="h-14 w-14 rounded-xl bg-[#0052CC] flex items-center justify-center">
                            <Bitbucket className="h-7 w-7 text-white" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold">Bitbucket Connections</h1>
                            <p className="text-foreground-muted">Manage your Bitbucket workspace connections</p>
                        </div>
                    </div>
                    <Link href="/sources/bitbucket/create">
                        <Button>
                            <Plus className="h-4 w-4 mr-2" />
                            Add Bitbucket Connection
                        </Button>
                    </Link>
                </div>

                {/* Connections List */}
                {connections.length === 0 ? (
                    <Card>
                        <CardContent className="p-12 text-center">
                            <Bitbucket className="h-16 w-16 mx-auto mb-4 opacity-50" />
                            <h3 className="text-lg font-semibold mb-2">No Bitbucket connections</h3>
                            <p className="text-foreground-muted mb-6">
                                Connect to Bitbucket Cloud or Bitbucket Server to enable automatic deployments
                            </p>
                            <Link href="/sources/bitbucket/create">
                                <Button>
                                    <Plus className="h-4 w-4 mr-2" />
                                    Connect Bitbucket
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
                                            <div className="h-12 w-12 rounded-full bg-[#0052CC]/10 flex items-center justify-center">
                                                <Bitbucket className="h-6 w-6 text-[#0052CC]" />
                                            </div>
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <h3 className="font-semibold">{connection.name}</h3>
                                                    <Badge variant={connection.status === 'active' ? 'success' : 'warning'}>
                                                        {connection.status}
                                                    </Badge>
                                                    <Badge variant="info">
                                                        {connection.type === 'cloud' ? 'Cloud' : 'Server'}
                                                    </Badge>
                                                </div>
                                                <p className="text-sm text-foreground-muted">
                                                    @{connection.workspace} • {connection.repos_count} repositories
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Button variant="ghost" size="sm" onClick={() => handleSync(connection.uuid)}>
                                                <RefreshCw className="h-4 w-4 mr-2" />
                                                Sync
                                            </Button>
                                            <a
                                                href={
                                                    connection.type === 'cloud'
                                                        ? `https://bitbucket.org/${connection.workspace}`
                                                        : '#'
                                                }
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                <Button variant="ghost" size="sm">
                                                    <ExternalLink className="h-4 w-4 mr-2" />
                                                    Bitbucket
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
                        <CardTitle className="text-base">About Bitbucket Integration</CardTitle>
                    </CardHeader>
                    <CardContent className="text-sm text-foreground-muted space-y-2">
                        <p><strong>Bitbucket Cloud:</strong> Connect to the official Bitbucket.org service</p>
                        <p><strong>Bitbucket Server:</strong> Connect to your self-hosted Bitbucket instance</p>
                        <p className="pt-2">You'll need:</p>
                        <ul className="list-disc list-inside space-y-1 pl-2">
                            <li>A Bitbucket App Password or OAuth consumer</li>
                            <li>Access to the workspace and repositories you want to deploy</li>
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
                                    <p className="font-medium text-sm">Pull Request Previews</p>
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
                                    <p className="font-medium text-sm">Multiple Workspaces</p>
                                    <p className="text-xs text-foreground-muted mt-1">
                                        Connect to multiple workspaces
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Compare */}
                <Card className="bg-info/5 border-info/20">
                    <CardHeader>
                        <CardTitle className="text-base">Bitbucket Cloud vs Server</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                            <div>
                                <h4 className="font-medium mb-2">Bitbucket Cloud</h4>
                                <ul className="text-foreground-muted space-y-1 list-disc list-inside">
                                    <li>Hosted at bitbucket.org</li>
                                    <li>Uses OAuth 2.0</li>
                                    <li>Works with workspaces</li>
                                    <li>No server maintenance needed</li>
                                </ul>
                            </div>
                            <div>
                                <h4 className="font-medium mb-2">Bitbucket Server/Data Center</h4>
                                <ul className="text-foreground-muted space-y-1 list-disc list-inside">
                                    <li>Self-hosted on your infrastructure</li>
                                    <li>Uses HTTP Basic or Personal Access Tokens</li>
                                    <li>Works with projects</li>
                                    <li>Full control over data and access</li>
                                </ul>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
