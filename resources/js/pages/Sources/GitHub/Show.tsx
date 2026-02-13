import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Button, Badge, Input, useConfirm } from '@/components/ui';
import {
    Github, ArrowLeft, CheckCircle2, AlertCircle, ExternalLink,
    Trash2, Settings, Save, Copy, Shield, RefreshCw
} from 'lucide-react';
import { useToast } from '@/components/ui/Toast';

declare function route(name: string, params?: Record<string, any>): string;

interface GitHubSource {
    id: number;
    uuid: string;
    name: string;
    app_id: number | null;
    client_id: string | null;
    installation_id: number | null;
    html_url: string;
    api_url: string;
    organization: string | null;
    is_public: boolean;
    is_system_wide: boolean;
    connected: boolean;
    created_at: string | null;
    updated_at: string | null;
}

interface Props {
    source: GitHubSource;
    installationPath: string | null;
    applicationsCount: number;
}

export default function GitHubShow({ source, installationPath, applicationsCount }: Props) {
    const { addToast } = useToast();
    const confirm = useConfirm();
    const [editing, setEditing] = useState(false);
    const [name, setName] = useState(source.name);
    const [organization, setOrganization] = useState(source.organization || '');
    const [saving, setSaving] = useState(false);

    const handleSave = () => {
        setSaving(true);
        router.put(`/sources/github/${source.id}`, {
            name,
            organization: organization || null,
        }, {
            onSuccess: () => {
                addToast('success', 'GitHub App updated');
                setEditing(false);
                setSaving(false);
            },
            onError: () => {
                addToast('error', 'Failed to update GitHub App');
                setSaving(false);
            },
        });
    };

    const handleDelete = async () => {
        if (applicationsCount > 0) {
            addToast('error', `Cannot delete: ${applicationsCount} application(s) using this source`);
            return;
        }

        const confirmed = await confirm({
            title: 'Delete GitHub App',
            description: 'Are you sure you want to delete this GitHub App? This action cannot be undone.',
            confirmText: 'Delete',
            variant: 'danger',
        });

        if (confirmed) {
            router.delete(`/sources/github/${source.id}`, {
                onSuccess: () => addToast('success', 'GitHub App deleted'),
                onError: () => addToast('error', 'Failed to delete GitHub App'),
            });
        }
    };

    const copyToClipboard = (text: string) => {
        navigator.clipboard.writeText(text);
        addToast('success', 'Copied to clipboard');
    };

    return (
        <AppLayout
            title={source.name}
            breadcrumbs={[
                { label: 'Dashboard', href: '/new' },
                { label: 'Sources', href: '/sources' },
                { label: 'GitHub', href: '/sources/github' },
                { label: source.name },
            ]}
        >
            <Head title={`${source.name} - GitHub App`} />

            <div className="max-w-4xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Link href="/sources/github">
                        <Button variant="ghost" size="sm">
                            <ArrowLeft className="h-4 w-4 mr-2" />
                            Back
                        </Button>
                    </Link>
                </div>

                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <div className="h-14 w-14 rounded-xl bg-foreground flex items-center justify-center">
                            <Github className="h-7 w-7 text-background" />
                        </div>
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="text-2xl font-bold">{source.name}</h1>
                                <Badge variant={source.connected ? 'success' : 'warning'}>
                                    {source.connected ? (
                                        <><CheckCircle2 className="mr-1 h-3 w-3" />Connected</>
                                    ) : (
                                        <><AlertCircle className="mr-1 h-3 w-3" />Not Connected</>
                                    )}
                                </Badge>
                            </div>
                            <p className="text-foreground-muted">
                                {source.organization || 'Personal'} &middot; {applicationsCount} application{applicationsCount !== 1 ? 's' : ''}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        {source.installation_id && (
                            <a
                                href={`${source.html_url}/settings/installations/${source.installation_id}`}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <Button variant="secondary" size="sm">
                                    <ExternalLink className="h-4 w-4 mr-2" />
                                    GitHub Settings
                                </Button>
                            </a>
                        )}
                    </div>
                </div>

                {/* Connection Details */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Connection Details</CardTitle>
                                <CardDescription>GitHub App configuration and credentials</CardDescription>
                            </div>
                            {!editing ? (
                                <Button variant="secondary" size="sm" onClick={() => setEditing(true)}>
                                    <Settings className="h-4 w-4 mr-2" />
                                    Edit
                                </Button>
                            ) : (
                                <div className="flex gap-2">
                                    <Button variant="ghost" size="sm" onClick={() => { setEditing(false); setName(source.name); setOrganization(source.organization || ''); }}>
                                        Cancel
                                    </Button>
                                    <Button size="sm" onClick={handleSave} disabled={saving}>
                                        <Save className="h-4 w-4 mr-2" />
                                        {saving ? 'Saving...' : 'Save'}
                                    </Button>
                                </div>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium mb-1">Name</label>
                                    {editing ? (
                                        <Input value={name} onChange={(e) => setName(e.target.value)} />
                                    ) : (
                                        <p className="text-sm text-foreground-muted">{source.name}</p>
                                    )}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium mb-1">Organization</label>
                                    {editing ? (
                                        <Input value={organization} onChange={(e) => setOrganization(e.target.value)} placeholder="Personal" />
                                    ) : (
                                        <p className="text-sm text-foreground-muted">{source.organization || 'Personal'}</p>
                                    )}
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium mb-1">App ID</label>
                                    <div className="flex items-center gap-2">
                                        <p className="text-sm text-foreground-muted font-mono">{source.app_id || '—'}</p>
                                        {source.app_id && (
                                            <Button variant="ghost" size="sm" onClick={() => copyToClipboard(String(source.app_id))}>
                                                <Copy className="h-3 w-3" />
                                            </Button>
                                        )}
                                    </div>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium mb-1">Client ID</label>
                                    <div className="flex items-center gap-2">
                                        <p className="text-sm text-foreground-muted font-mono">{source.client_id || '—'}</p>
                                        {source.client_id && (
                                            <Button variant="ghost" size="sm" onClick={() => copyToClipboard(source.client_id!)}>
                                                <Copy className="h-3 w-3" />
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium mb-1">Installation ID</label>
                                    <p className="text-sm text-foreground-muted font-mono">{source.installation_id || '—'}</p>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium mb-1">API URL</label>
                                    <p className="text-sm text-foreground-muted">{source.api_url}</p>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium mb-1">Visibility</label>
                                    <Badge variant={source.is_public ? 'default' : 'secondary'}>
                                        <Shield className="mr-1 h-3 w-3" />
                                        {source.is_public ? 'Public' : 'Private'}
                                    </Badge>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium mb-1">Created</label>
                                    <p className="text-sm text-foreground-muted">
                                        {source.created_at ? new Date(source.created_at).toLocaleDateString() : '—'}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Setup Instructions (when not connected) */}
                {!source.connected && (
                    <Card className="border-warning/30 bg-warning/5">
                        <CardHeader>
                            <CardTitle className="text-base flex items-center gap-2">
                                <AlertCircle className="h-5 w-5 text-warning" />
                                Setup Required
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3 text-sm text-foreground-muted">
                                {!source.app_id && (
                                    <p>The GitHub App has not been fully configured. Please ensure you have completed the app creation on GitHub and entered all credentials.</p>
                                )}
                                {source.app_id && !source.installation_id && (
                                    <>
                                        <p>The GitHub App needs to be installed on your GitHub account or organization.</p>
                                        <div className="flex gap-2 mt-2">
                                            {installationPath ? (
                                                <a
                                                    href={installationPath}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                >
                                                    <Button size="sm">
                                                        <ExternalLink className="h-4 w-4 mr-2" />
                                                        Install App on GitHub
                                                    </Button>
                                                </a>
                                            ) : (
                                                <a
                                                    href={`${source.html_url}/settings/apps`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                >
                                                    <Button size="sm">
                                                        <ExternalLink className="h-4 w-4 mr-2" />
                                                        Go to GitHub Apps
                                                    </Button>
                                                </a>
                                            )}
                                            <Button
                                                variant="secondary"
                                                size="sm"
                                                onClick={() => router.post(`/sources/github/${source.id}/sync`)}
                                            >
                                                <RefreshCw className="h-4 w-4 mr-2" />
                                                Check Connection
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Danger Zone */}
                <Card className="border-danger/30">
                    <CardHeader>
                        <CardTitle className="text-base text-danger">Danger Zone</CardTitle>
                        <CardDescription>Irreversible actions</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="font-medium text-sm">Delete GitHub App</p>
                                <p className="text-sm text-foreground-muted">
                                    {applicationsCount > 0
                                        ? `Cannot delete: ${applicationsCount} application(s) are using this source`
                                        : 'Permanently remove this GitHub App integration'
                                    }
                                </p>
                            </div>
                            <Button
                                variant="danger"
                                size="sm"
                                onClick={handleDelete}
                                disabled={applicationsCount > 0}
                            >
                                <Trash2 className="h-4 w-4 mr-2" />
                                Delete
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
