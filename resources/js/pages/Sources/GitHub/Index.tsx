import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button, Badge } from '@/components/ui';
import { Plus, Github, CheckCircle2, RefreshCw, Trash2, ExternalLink, ArrowLeft } from 'lucide-react';

interface GitHubApp {
    id: number;
    uuid: string;
    name: string;
    app_id: number;
    installation_id: number;
    status: 'active' | 'suspended' | 'pending';
    repos_count: number;
    organization?: string;
    created_at: string;
    last_synced_at?: string;
}

interface Props {
    apps: GitHubApp[];
}

export default function GitHubIndex({ apps = [] }: Props) {
    const handleSync = (uuid: string) => {
        router.post(`/sources/github/${uuid}/sync`);
    };

    const handleDelete = (uuid: string) => {
        if (confirm('Are you sure you want to disconnect this GitHub App?')) {
            router.delete(`/sources/github/${uuid}`);
        }
    };

    return (
        <AppLayout
            title="GitHub Apps"
            breadcrumbs={[
                { label: 'Dashboard', href: '/new' },
                { label: 'Sources', href: '/sources' },
                { label: 'GitHub' },
            ]}
        >
            <Head title="GitHub Apps" />

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
                        <Github className="h-10 w-10" />
                        <div>
                            <h1 className="text-2xl font-bold">GitHub Apps</h1>
                            <p className="text-foreground-muted">Manage your GitHub App installations</p>
                        </div>
                    </div>
                    <Link href="/sources/github/create">
                        <Button>
                            <Plus className="h-4 w-4 mr-2" />
                            Add GitHub App
                        </Button>
                    </Link>
                </div>

                {/* Apps List */}
                {apps.length === 0 ? (
                    <Card>
                        <CardContent className="p-12 text-center">
                            <Github className="h-16 w-16 mx-auto mb-4 opacity-50" />
                            <h3 className="text-lg font-semibold mb-2">No GitHub Apps connected</h3>
                            <p className="text-foreground-muted mb-6">
                                Create a GitHub App to enable automatic deployments from your repositories
                            </p>
                            <Link href="/sources/github/create">
                                <Button>
                                    <Plus className="h-4 w-4 mr-2" />
                                    Create GitHub App
                                </Button>
                            </Link>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-4">
                        {apps.map(app => (
                            <Card key={app.id}>
                                <CardContent className="p-6">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-4">
                                            <div className="h-12 w-12 rounded-full bg-background-secondary flex items-center justify-center">
                                                <Github className="h-6 w-6" />
                                            </div>
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <h3 className="font-semibold">{app.name}</h3>
                                                    <Badge variant={app.status === 'active' ? 'success' : 'warning'}>
                                                        {app.status}
                                                    </Badge>
                                                </div>
                                                <p className="text-sm text-foreground-muted">
                                                    {app.organization ? `@${app.organization}` : 'Personal'} •
                                                    App ID: {app.app_id} •
                                                    {app.repos_count} repositories
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Button variant="ghost" size="sm" onClick={() => handleSync(app.uuid)}>
                                                <RefreshCw className="h-4 w-4 mr-2" />
                                                Sync
                                            </Button>
                                            <a
                                                href={`https://github.com/settings/installations/${app.installation_id}`}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                <Button variant="ghost" size="sm">
                                                    <ExternalLink className="h-4 w-4 mr-2" />
                                                    GitHub Settings
                                                </Button>
                                            </a>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="text-danger hover:text-danger"
                                                onClick={() => handleDelete(app.uuid)}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                    {app.last_synced_at && (
                                        <p className="text-xs text-foreground-muted mt-4">
                                            Last synced: {new Date(app.last_synced_at).toLocaleString()}
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
                        <CardTitle className="text-base">How GitHub Apps work</CardTitle>
                    </CardHeader>
                    <CardContent className="text-sm text-foreground-muted space-y-2">
                        <p>1. Create a GitHub App in your GitHub settings</p>
                        <p>2. Install the app on your organization or personal account</p>
                        <p>3. Select which repositories the app can access</p>
                        <p>4. Saturn Platform will automatically receive webhook events for deployments</p>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
