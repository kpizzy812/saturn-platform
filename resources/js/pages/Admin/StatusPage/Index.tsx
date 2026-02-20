import * as React from 'react';
import { router } from '@inertiajs/react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Globe, Plus, Trash2, ExternalLink } from 'lucide-react';

interface AvailableResource {
    id: number;
    type: string;
    name: string;
    teamName: string;
    status: string;
}

interface ConfiguredResource {
    id: number;
    resource_type: string;
    resource_id: number;
    display_name: string;
    display_order: number;
    is_visible: boolean;
    group_name: string | null;
}

interface Settings {
    is_status_page_enabled: boolean;
    status_page_title: string;
    status_page_description: string;
}

interface Props {
    settings?: Settings;
    availableResources?: AvailableResource[];
    configuredResources?: ConfiguredResource[];
}

const defaultSettings: Settings = {
    is_status_page_enabled: false,
    status_page_title: '',
    status_page_description: '',
};

export default function AdminStatusPage({
    settings = defaultSettings,
    availableResources = [],
    configuredResources = [],
}: Props) {
    const [enabled, setEnabled] = React.useState(settings.is_status_page_enabled);
    const [title, setTitle] = React.useState(settings.status_page_title);
    const [description, setDescription] = React.useState(settings.status_page_description);
    const [selectedResource, setSelectedResource] = React.useState('');
    const [displayName, setDisplayName] = React.useState('');
    const [groupName, setGroupName] = React.useState('');

    const handleSaveSettings = () => {
        router.post('/admin/status-page/settings', {
            is_status_page_enabled: enabled,
            status_page_title: title,
            status_page_description: description,
        });
    };

    const handleAddResource = () => {
        if (!selectedResource || !displayName) return;

        const [type, id] = selectedResource.split('|');
        router.post('/admin/status-page/resources', {
            resource_type: type,
            resource_id: parseInt(id, 10),
            display_name: displayName,
            group_name: groupName || null,
        }, {
            onSuccess: () => {
                setSelectedResource('');
                setDisplayName('');
                setGroupName('');
            },
        });
    };

    const handleRemoveResource = (id: number) => {
        router.delete(`/admin/status-page/resources/${id}`);
    };

    return (
        <AdminLayout
            title="Status Page"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Status Page' },
            ]}
        >
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Public Status Page</h1>
                        <p className="mt-1 text-sm text-foreground-muted">
                            Configure your public-facing status page at /status
                        </p>
                    </div>
                    {enabled && (
                        <a
                            href="/status"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm text-foreground-muted hover:bg-background-secondary hover:text-foreground"
                        >
                            <ExternalLink className="h-4 w-4" />
                            View Status Page
                        </a>
                    )}
                </div>

                {/* Settings Card */}
                <Card variant="glass">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Globe className="h-5 w-5" />
                            Settings
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            <label className="flex items-center gap-3">
                                <input
                                    type="checkbox"
                                    checked={enabled}
                                    onChange={(e) => setEnabled(e.target.checked)}
                                    className="h-4 w-4 rounded border-border bg-background-secondary"
                                />
                                <span className="text-sm text-foreground">Enable public status page</span>
                            </label>

                            <div>
                                <label className="mb-1 block text-sm text-foreground-muted">Page Title</label>
                                <input
                                    type="text"
                                    value={title}
                                    onChange={(e) => setTitle(e.target.value)}
                                    placeholder="Saturn Platform"
                                    className="w-full rounded-lg border border-border bg-background-secondary px-3 py-2 text-sm text-foreground placeholder:text-foreground-muted"
                                />
                            </div>

                            <div>
                                <label className="mb-1 block text-sm text-foreground-muted">Description</label>
                                <textarea
                                    value={description}
                                    onChange={(e) => setDescription(e.target.value)}
                                    placeholder="Current status of our services"
                                    rows={2}
                                    className="w-full rounded-lg border border-border bg-background-secondary px-3 py-2 text-sm text-foreground placeholder:text-foreground-muted"
                                />
                            </div>

                            <button
                                onClick={handleSaveSettings}
                                className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-hover"
                            >
                                Save Settings
                            </button>
                        </div>
                    </CardContent>
                </Card>

                {/* Add Resource Card */}
                <Card variant="glass">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Plus className="h-5 w-5" />
                            Add Resource
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <label className="mb-1 block text-sm text-foreground-muted">Resource</label>
                                <select
                                    value={selectedResource}
                                    onChange={(e) => {
                                        setSelectedResource(e.target.value);
                                        if (e.target.value) {
                                            const res = availableResources.find(
                                                (r) => `${r.type}|${r.id}` === e.target.value
                                            );
                                            if (res && !displayName) setDisplayName(res.name);
                                        }
                                    }}
                                    className="w-full rounded-lg border border-border bg-background-secondary px-3 py-2 text-sm text-foreground"
                                >
                                    <option value="">Select resource...</option>
                                    {availableResources.map((r) => (
                                        <option key={`${r.type}-${r.id}`} value={`${r.type}|${r.id}`}>
                                            {r.name} ({r.teamName})
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm text-foreground-muted">Display Name</label>
                                <input
                                    type="text"
                                    value={displayName}
                                    onChange={(e) => setDisplayName(e.target.value)}
                                    placeholder="Frontend App"
                                    className="w-full rounded-lg border border-border bg-background-secondary px-3 py-2 text-sm text-foreground placeholder:text-foreground-muted"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm text-foreground-muted">Group</label>
                                <input
                                    type="text"
                                    value={groupName}
                                    onChange={(e) => setGroupName(e.target.value)}
                                    placeholder="Infrastructure"
                                    className="w-full rounded-lg border border-border bg-background-secondary px-3 py-2 text-sm text-foreground placeholder:text-foreground-muted"
                                />
                            </div>
                            <div className="flex items-end">
                                <button
                                    onClick={handleAddResource}
                                    disabled={!selectedResource || !displayName}
                                    className="w-full rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-hover disabled:opacity-50"
                                >
                                    Add
                                </button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Configured Resources */}
                <Card variant="glass">
                    <CardHeader>
                        <CardTitle>Monitored Resources ({configuredResources.length})</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {configuredResources.length === 0 ? (
                            <p className="text-sm text-foreground-muted">
                                No resources added to the status page yet.
                            </p>
                        ) : (
                            <div className="space-y-2">
                                {configuredResources.map((resource) => (
                                    <div
                                        key={resource.id}
                                        className="flex items-center justify-between rounded-lg border border-white/[0.06] bg-white/[0.02] px-4 py-3"
                                    >
                                        <div className="flex items-center gap-3">
                                            <span className="font-medium text-foreground">{resource.display_name}</span>
                                            {resource.group_name && (
                                                <Badge variant="secondary">{resource.group_name}</Badge>
                                            )}
                                            {!resource.is_visible && (
                                                <Badge variant="default">Hidden</Badge>
                                            )}
                                        </div>
                                        <button
                                            onClick={() => handleRemoveResource(resource.id)}
                                            className="rounded-lg p-2 text-foreground-muted hover:bg-red-500/10 hover:text-red-400"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
