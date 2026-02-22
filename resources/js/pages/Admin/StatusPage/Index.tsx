import * as React from 'react';
import { router } from '@inertiajs/react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Globe, Plus, Trash2, ExternalLink, AlertTriangle, MessageSquare } from 'lucide-react';

interface AvailableResource {
    id: number;
    type: string;
    name: string;
    teamName: string;
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

interface IncidentUpdate {
    id: number;
    status: string;
    message: string;
    posted_at: string;
}

interface IncidentData {
    id: number;
    title: string;
    severity: string;
    status: string;
    started_at: string;
    resolved_at: string | null;
    is_visible: boolean;
    updates: IncidentUpdate[];
}

interface Settings {
    is_status_page_enabled: boolean;
    status_page_title: string;
    status_page_description: string;
    status_page_mode: string;
}

interface Props {
    settings?: Settings;
    availableResources?: AvailableResource[];
    configuredResources?: ConfiguredResource[];
    incidents?: IncidentData[];
}

const defaultSettings: Settings = {
    is_status_page_enabled: false,
    status_page_title: '',
    status_page_description: '',
    status_page_mode: 'auto',
};

export default function AdminStatusPage({
    settings = defaultSettings,
    availableResources = [],
    configuredResources = [],
    incidents = [],
}: Props) {
    const [enabled, setEnabled] = React.useState(settings.is_status_page_enabled);
    const [title, setTitle] = React.useState(settings.status_page_title);
    const [description, setDescription] = React.useState(settings.status_page_description);
    const [mode, setMode] = React.useState(settings.status_page_mode);
    const [selectedResource, setSelectedResource] = React.useState('');
    const [displayName, setDisplayName] = React.useState('');
    const [groupName, setGroupName] = React.useState('');

    // Incident form state
    const [incidentTitle, setIncidentTitle] = React.useState('');
    const [incidentSeverity, setIncidentSeverity] = React.useState('minor');
    const [incidentMessage, setIncidentMessage] = React.useState('');

    // Incident update state
    const [updateIncidentId, setUpdateIncidentId] = React.useState<number | null>(null);
    const [updateStatus, setUpdateStatus] = React.useState('identified');
    const [updateMessage, setUpdateMessage] = React.useState('');

    const handleSaveSettings = () => {
        router.post('/admin/status-page/settings', {
            is_status_page_enabled: enabled,
            status_page_title: title,
            status_page_description: description,
            status_page_mode: mode,
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

    const handleCreateIncident = () => {
        if (!incidentTitle || !incidentMessage) return;
        router.post('/admin/status-page/incidents', {
            title: incidentTitle,
            severity: incidentSeverity,
            message: incidentMessage,
        }, {
            onSuccess: () => {
                setIncidentTitle('');
                setIncidentSeverity('minor');
                setIncidentMessage('');
            },
        });
    };

    const handleUpdateIncident = (incidentId: number) => {
        if (!updateMessage) return;
        router.put(`/admin/status-page/incidents/${incidentId}`, {
            status: updateStatus,
            message: updateMessage,
        }, {
            onSuccess: () => {
                setUpdateIncidentId(null);
                setUpdateStatus('identified');
                setUpdateMessage('');
            },
        });
    };

    const handleDeleteIncident = (id: number) => {
        router.delete(`/admin/status-page/incidents/${id}`);
    };

    const severityColors: Record<string, string> = {
        minor: 'bg-yellow-500/20 text-yellow-400',
        major: 'bg-orange-500/20 text-orange-400',
        critical: 'bg-red-500/20 text-red-400',
        maintenance: 'bg-blue-500/20 text-blue-400',
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

                            {/* Mode Toggle */}
                            <div>
                                <label className="mb-2 block text-sm text-foreground-muted">Mode</label>
                                <div className="flex gap-3">
                                    <label className="flex items-center gap-2 rounded-lg border border-border px-4 py-2 cursor-pointer hover:bg-background-secondary">
                                        <input
                                            type="radio"
                                            name="mode"
                                            value="auto"
                                            checked={mode === 'auto'}
                                            onChange={() => setMode('auto')}
                                            className="h-4 w-4"
                                        />
                                        <div>
                                            <div className="text-sm font-medium text-foreground">Auto</div>
                                            <div className="text-xs text-foreground-muted">Show all servers & resources automatically</div>
                                        </div>
                                    </label>
                                    <label className="flex items-center gap-2 rounded-lg border border-border px-4 py-2 cursor-pointer hover:bg-background-secondary">
                                        <input
                                            type="radio"
                                            name="mode"
                                            value="manual"
                                            checked={mode === 'manual'}
                                            onChange={() => setMode('manual')}
                                            className="h-4 w-4"
                                        />
                                        <div>
                                            <div className="text-sm font-medium text-foreground">Manual</div>
                                            <div className="text-xs text-foreground-muted">Select specific resources to display</div>
                                        </div>
                                    </label>
                                </div>
                            </div>

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

                {/* Add Resource Card — only in manual mode */}
                {mode === 'manual' && (
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
                )}

                {/* Configured Resources — only in manual mode */}
                {mode === 'manual' && (
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
                )}

                {/* Incidents Card */}
                <Card variant="glass">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <AlertTriangle className="h-5 w-5" />
                            Incidents
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {/* Create Incident Form */}
                        <div className="mb-6 space-y-3 rounded-lg border border-white/[0.06] bg-white/[0.02] p-4">
                            <h4 className="text-sm font-medium text-foreground">Create New Incident</h4>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-xs text-foreground-muted">Title</label>
                                    <input
                                        type="text"
                                        value={incidentTitle}
                                        onChange={(e) => setIncidentTitle(e.target.value)}
                                        placeholder="Service degradation"
                                        className="w-full rounded-lg border border-border bg-background-secondary px-3 py-2 text-sm text-foreground placeholder:text-foreground-muted"
                                    />
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs text-foreground-muted">Severity</label>
                                    <select
                                        value={incidentSeverity}
                                        onChange={(e) => setIncidentSeverity(e.target.value)}
                                        className="w-full rounded-lg border border-border bg-background-secondary px-3 py-2 text-sm text-foreground"
                                    >
                                        <option value="minor">Minor</option>
                                        <option value="major">Major</option>
                                        <option value="critical">Critical</option>
                                        <option value="maintenance">Maintenance</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label className="mb-1 block text-xs text-foreground-muted">Initial Message</label>
                                <textarea
                                    value={incidentMessage}
                                    onChange={(e) => setIncidentMessage(e.target.value)}
                                    placeholder="We are investigating reports of..."
                                    rows={2}
                                    className="w-full rounded-lg border border-border bg-background-secondary px-3 py-2 text-sm text-foreground placeholder:text-foreground-muted"
                                />
                            </div>
                            <button
                                onClick={handleCreateIncident}
                                disabled={!incidentTitle || !incidentMessage}
                                className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-hover disabled:opacity-50"
                            >
                                Create Incident
                            </button>
                        </div>

                        {/* Existing Incidents */}
                        {incidents.length === 0 ? (
                            <p className="text-sm text-foreground-muted">No incidents.</p>
                        ) : (
                            <div className="space-y-3">
                                {incidents.map((incident) => (
                                    <div
                                        key={incident.id}
                                        className="rounded-lg border border-white/[0.06] bg-white/[0.02] p-4"
                                    >
                                        <div className="flex items-start justify-between">
                                            <div className="flex items-center gap-2">
                                                <h4 className="text-sm font-medium text-foreground">{incident.title}</h4>
                                                <span className={`rounded-full px-2 py-0.5 text-[10px] font-medium uppercase ${severityColors[incident.severity] || ''}`}>
                                                    {incident.severity}
                                                </span>
                                                <span className="rounded-full bg-white/5 px-2 py-0.5 text-[10px] font-medium text-foreground-muted uppercase">
                                                    {incident.status}
                                                </span>
                                                {incident.resolved_at && (
                                                    <span className="text-xs text-emerald-400">Resolved</span>
                                                )}
                                            </div>
                                            <div className="flex items-center gap-1">
                                                {!incident.resolved_at && (
                                                    <button
                                                        onClick={() => setUpdateIncidentId(
                                                            updateIncidentId === incident.id ? null : incident.id
                                                        )}
                                                        className="rounded-lg p-2 text-foreground-muted hover:bg-blue-500/10 hover:text-blue-400"
                                                        title="Post update"
                                                    >
                                                        <MessageSquare className="h-4 w-4" />
                                                    </button>
                                                )}
                                                <button
                                                    onClick={() => handleDeleteIncident(incident.id)}
                                                    className="rounded-lg p-2 text-foreground-muted hover:bg-red-500/10 hover:text-red-400"
                                                    title="Delete incident"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            </div>
                                        </div>

                                        {/* Update form */}
                                        {updateIncidentId === incident.id && (
                                            <div className="mt-3 space-y-2 rounded-md border border-white/[0.06] bg-white/[0.02] p-3">
                                                <div className="grid gap-2 sm:grid-cols-2">
                                                    <div>
                                                        <label className="mb-1 block text-xs text-foreground-muted">Status</label>
                                                        <select
                                                            value={updateStatus}
                                                            onChange={(e) => setUpdateStatus(e.target.value)}
                                                            className="w-full rounded-lg border border-border bg-background-secondary px-3 py-1.5 text-sm text-foreground"
                                                        >
                                                            <option value="investigating">Investigating</option>
                                                            <option value="identified">Identified</option>
                                                            <option value="monitoring">Monitoring</option>
                                                            <option value="resolved">Resolved</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div>
                                                    <label className="mb-1 block text-xs text-foreground-muted">Message</label>
                                                    <textarea
                                                        value={updateMessage}
                                                        onChange={(e) => setUpdateMessage(e.target.value)}
                                                        placeholder="Update message..."
                                                        rows={2}
                                                        className="w-full rounded-lg border border-border bg-background-secondary px-3 py-1.5 text-sm text-foreground placeholder:text-foreground-muted"
                                                    />
                                                </div>
                                                <button
                                                    onClick={() => handleUpdateIncident(incident.id)}
                                                    disabled={!updateMessage}
                                                    className="rounded-lg bg-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-hover disabled:opacity-50"
                                                >
                                                    Post Update
                                                </button>
                                            </div>
                                        )}

                                        {/* Existing updates */}
                                        {incident.updates && incident.updates.length > 0 && (
                                            <div className="mt-2 space-y-1 border-l-2 border-white/10 pl-3">
                                                {incident.updates.map((update) => (
                                                    <div key={update.id} className="text-xs text-foreground-muted">
                                                        <span className="font-medium">{update.status}</span>
                                                        <span className="mx-1">—</span>
                                                        <span>{update.message}</span>
                                                        <span className="ml-2 text-foreground-muted/50">
                                                            {new Date(update.posted_at).toLocaleString()}
                                                        </span>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
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
