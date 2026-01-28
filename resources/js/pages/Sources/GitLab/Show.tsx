import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Button, Badge, Input, useConfirm } from '@/components/ui';
import {
    GitBranch, ArrowLeft, CheckCircle2, AlertCircle, ExternalLink,
    Trash2, Settings, Save, Copy, Shield, Globe
} from 'lucide-react';
import { useToast } from '@/components/ui/Toast';

interface GitLabSource {
    id: number;
    uuid: string;
    name: string;
    api_url: string;
    html_url: string;
    app_id: number | null;
    group_name: string | null;
    deploy_key_id: number | null;
    is_public: boolean;
    is_system_wide: boolean;
    connected: boolean;
    created_at: string | null;
    updated_at: string | null;
}

interface Props {
    source: GitLabSource;
    applicationsCount: number;
}

export default function GitLabShow({ source, applicationsCount }: Props) {
    const { addToast } = useToast();
    const confirm = useConfirm();
    const [editing, setEditing] = useState(false);
    const [name, setName] = useState(source.name);
    const [apiUrl, setApiUrl] = useState(source.api_url);
    const [htmlUrl, setHtmlUrl] = useState(source.html_url);
    const [groupName, setGroupName] = useState(source.group_name || '');
    const [saving, setSaving] = useState(false);

    const handleSave = () => {
        setSaving(true);
        router.put(`/sources/gitlab/${source.id}`, {
            name,
            api_url: apiUrl,
            html_url: htmlUrl,
            group_name: groupName || null,
        }, {
            onSuccess: () => {
                addToast('success', 'GitLab connection updated');
                setEditing(false);
                setSaving(false);
            },
            onError: () => {
                addToast('error', 'Failed to update GitLab connection');
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
            title: 'Delete GitLab Connection',
            description: 'Are you sure you want to delete this GitLab connection? This action cannot be undone.',
            confirmText: 'Delete',
            variant: 'danger',
        });

        if (confirmed) {
            router.delete(`/sources/gitlab/${source.id}`, {
                onSuccess: () => addToast('success', 'GitLab connection deleted'),
                onError: () => addToast('error', 'Failed to delete GitLab connection'),
            });
        }
    };

    const connectionMethod = source.deploy_key_id ? 'Deploy Key' : source.app_id ? 'OAuth' : 'Not configured';

    return (
        <AppLayout
            title={source.name}
            breadcrumbs={[
                { label: 'Dashboard', href: '/new' },
                { label: 'Sources', href: '/sources' },
                { label: 'GitLab', href: '/sources/gitlab' },
                { label: source.name },
            ]}
        >
            <Head title={`${source.name} - GitLab`} />

            <div className="max-w-4xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Link href="/sources/gitlab">
                        <Button variant="ghost" size="sm">
                            <ArrowLeft className="h-4 w-4 mr-2" />
                            Back
                        </Button>
                    </Link>
                </div>

                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <div className="h-14 w-14 rounded-xl bg-[#FC6D26] flex items-center justify-center">
                            <GitBranch className="h-7 w-7 text-white" />
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
                                {connectionMethod} &middot; {applicationsCount} application{applicationsCount !== 1 ? 's' : ''}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        {source.html_url && (
                            <a href={source.html_url} target="_blank" rel="noopener noreferrer">
                                <Button variant="secondary" size="sm">
                                    <ExternalLink className="h-4 w-4 mr-2" />
                                    Open GitLab
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
                                <CardDescription>GitLab instance configuration</CardDescription>
                            </div>
                            {!editing ? (
                                <Button variant="secondary" size="sm" onClick={() => setEditing(true)}>
                                    <Settings className="h-4 w-4 mr-2" />
                                    Edit
                                </Button>
                            ) : (
                                <div className="flex gap-2">
                                    <Button variant="ghost" size="sm" onClick={() => {
                                        setEditing(false);
                                        setName(source.name);
                                        setApiUrl(source.api_url);
                                        setHtmlUrl(source.html_url);
                                        setGroupName(source.group_name || '');
                                    }}>
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
                                    <label className="block text-sm font-medium mb-1">Group</label>
                                    {editing ? (
                                        <Input value={groupName} onChange={(e) => setGroupName(e.target.value)} placeholder="Optional" />
                                    ) : (
                                        <p className="text-sm text-foreground-muted">{source.group_name || '—'}</p>
                                    )}
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium mb-1">API URL</label>
                                    {editing ? (
                                        <Input value={apiUrl} onChange={(e) => setApiUrl(e.target.value)} />
                                    ) : (
                                        <div className="flex items-center gap-2">
                                            <Globe className="h-4 w-4 text-foreground-muted" />
                                            <p className="text-sm text-foreground-muted">{source.api_url}</p>
                                        </div>
                                    )}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium mb-1">HTML URL</label>
                                    {editing ? (
                                        <Input value={htmlUrl} onChange={(e) => setHtmlUrl(e.target.value)} />
                                    ) : (
                                        <div className="flex items-center gap-2">
                                            <Globe className="h-4 w-4 text-foreground-muted" />
                                            <p className="text-sm text-foreground-muted">{source.html_url}</p>
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium mb-1">Connection Method</label>
                                    <Badge variant="secondary">{connectionMethod}</Badge>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium mb-1">App ID</label>
                                    <p className="text-sm text-foreground-muted font-mono">{source.app_id || '—'}</p>
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
                                <p>This GitLab connection is not fully configured. To enable automatic deployments, you need to set up either:</p>
                                <ul className="list-disc list-inside space-y-1 pl-2">
                                    <li><strong>OAuth Application:</strong> Create an OAuth app in GitLab settings and enter the App ID and Secret</li>
                                    <li><strong>Deploy Key:</strong> Set up a deploy key for repository access</li>
                                </ul>
                                {source.html_url && (
                                    <a href={`${source.html_url}/-/settings/applications`} target="_blank" rel="noopener noreferrer">
                                        <Button size="sm" className="mt-2">
                                            <ExternalLink className="h-4 w-4 mr-2" />
                                            GitLab Application Settings
                                        </Button>
                                    </a>
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
                                <p className="font-medium text-sm">Delete GitLab Connection</p>
                                <p className="text-sm text-foreground-muted">
                                    {applicationsCount > 0
                                        ? `Cannot delete: ${applicationsCount} application(s) are using this source`
                                        : 'Permanently remove this GitLab integration'
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
