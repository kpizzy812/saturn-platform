import { useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription, Button, Input, Badge, Select } from '@/components/ui';
import {
    ArrowLeft, Save, Trash2, AlertTriangle, Plus, Pencil, Check, X,
    Eye, EyeOff, ExternalLink, Layers, Server, Bell, Users, Key,
} from 'lucide-react';

// --- Types ---

interface ResourcesCount {
    applications: number;
    services: number;
    databases: number;
}

interface Project {
    id: number;
    uuid: string;
    name: string;
    description: string | null;
    created_at: string;
    updated_at: string;
    is_empty: boolean;
    resources_count: ResourcesCount;
    total_resources: number;
    default_server_id: number | null;
}

interface Environment {
    id: number;
    uuid: string;
    name: string;
    created_at: string;
    is_empty: boolean;
}

interface SharedVariable {
    id: number;
    key: string;
    value: string;
    is_shown_once: boolean;
}

interface ServerOption {
    id: number;
    name: string;
    ip: string;
}

interface NotificationChannel {
    enabled: boolean;
    configured: boolean;
}

interface TeamMember {
    id: number;
    name: string;
    email: string;
    role: string;
}

interface Props {
    project: Project;
    environments: Environment[];
    sharedVariables: SharedVariable[];
    servers: ServerOption[];
    notificationChannels: Record<string, NotificationChannel>;
    teamMembers: TeamMember[];
    teamName: string;
}

// --- Component ---

export default function ProjectSettings({
    project,
    environments: initialEnvironments,
    sharedVariables: initialVariables,
    servers,
    notificationChannels,
    teamMembers,
    teamName,
}: Props) {
    // General form
    const { data, setData, patch, processing, errors } = useForm({
        name: project.name,
        description: project.description || '',
    });

    // Default server form
    const serverForm = useForm({
        default_server_id: project.default_server_id?.toString() || '',
    });

    // Delete project
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
    const [deleteConfirmName, setDeleteConfirmName] = useState('');

    // Environments state
    const [environments, setEnvironments] = useState<Environment[]>(initialEnvironments);
    const [newEnvName, setNewEnvName] = useState('');
    const [creatingEnv, setCreatingEnv] = useState(false);
    const [editingEnvId, setEditingEnvId] = useState<number | null>(null);
    const [editEnvName, setEditEnvName] = useState('');
    const [envError, setEnvError] = useState('');

    // Shared variables state
    const [variables, setVariables] = useState<SharedVariable[]>(initialVariables);
    const [newVarKey, setNewVarKey] = useState('');
    const [newVarValue, setNewVarValue] = useState('');
    const [creatingVar, setCreatingVar] = useState(false);
    const [editingVarId, setEditingVarId] = useState<number | null>(null);
    const [editVarKey, setEditVarKey] = useState('');
    const [editVarValue, setEditVarValue] = useState('');
    const [visibleVarIds, setVisibleVarIds] = useState<Set<number>>(new Set());
    const [varError, setVarError] = useState('');

    // --- Handlers ---

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        patch(`/projects/${project.uuid}`, { preserveScroll: true });
    };

    const handleDelete = () => {
        if (deleteConfirmName !== project.name) return;
        router.delete(`/projects/${project.uuid}`);
    };

    const handleSaveDefaultServer = () => {
        serverForm.patch(`/projects/${project.uuid}/settings/default-server`, {
            preserveScroll: true,
        });
    };

    // Environment handlers
    const handleCreateEnv = async () => {
        if (!newEnvName.trim()) return;
        setCreatingEnv(true);
        setEnvError('');
        try {
            const res = await fetch(`/projects/${project.uuid}/environments`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
                body: JSON.stringify({ name: newEnvName.trim() }),
            });
            if (!res.ok) {
                const err = await res.json();
                setEnvError(err.message || 'Failed to create environment');
                return;
            }
            const env = await res.json();
            setEnvironments([...environments, { ...env, is_empty: true }]);
            setNewEnvName('');
        } catch {
            setEnvError('Failed to create environment');
        } finally {
            setCreatingEnv(false);
        }
    };

    const handleRenameEnv = async (env: Environment) => {
        if (!editEnvName.trim() || editEnvName.trim() === env.name) {
            setEditingEnvId(null);
            return;
        }
        setEnvError('');
        try {
            const res = await fetch(`/projects/${project.uuid}/environments/${env.uuid}`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
                body: JSON.stringify({ name: editEnvName.trim() }),
            });
            if (!res.ok) {
                const err = await res.json();
                setEnvError(err.message || 'Failed to rename environment');
                return;
            }
            const updated = await res.json();
            setEnvironments(environments.map(e => e.id === env.id ? { ...e, name: updated.name } : e));
            setEditingEnvId(null);
        } catch {
            setEnvError('Failed to rename environment');
        }
    };

    const handleDeleteEnv = async (env: Environment) => {
        if (!env.is_empty) return;
        if (!confirm(`Delete environment "${env.name}"? This action cannot be undone.`)) return;
        setEnvError('');
        try {
            const res = await fetch(`/projects/${project.uuid}/environments/${env.uuid}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
            });
            if (!res.ok) {
                const err = await res.json();
                setEnvError(err.message || 'Failed to delete environment');
                return;
            }
            setEnvironments(environments.filter(e => e.id !== env.id));
        } catch {
            setEnvError('Failed to delete environment');
        }
    };

    // Shared variable handlers
    const handleCreateVar = async () => {
        if (!newVarKey.trim() || !newVarValue.trim()) return;
        setCreatingVar(true);
        setVarError('');
        try {
            const res = await fetch(`/projects/${project.uuid}/shared-variables`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
                body: JSON.stringify({ key: newVarKey.trim(), value: newVarValue }),
            });
            if (!res.ok) {
                const err = await res.json();
                setVarError(err.message || 'Failed to create variable');
                return;
            }
            const variable = await res.json();
            setVariables([...variables, variable]);
            setNewVarKey('');
            setNewVarValue('');
        } catch {
            setVarError('Failed to create variable');
        } finally {
            setCreatingVar(false);
        }
    };

    const handleUpdateVar = async (v: SharedVariable) => {
        if (!editVarKey.trim()) {
            setEditingVarId(null);
            return;
        }
        setVarError('');
        try {
            const res = await fetch(`/projects/${project.uuid}/shared-variables/${v.id}`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
                body: JSON.stringify({ key: editVarKey.trim(), value: editVarValue }),
            });
            if (!res.ok) {
                const err = await res.json();
                setVarError(err.message || 'Failed to update variable');
                return;
            }
            const updated = await res.json();
            setVariables(variables.map(item => item.id === v.id ? { ...item, key: updated.key, value: updated.value } : item));
            setEditingVarId(null);
        } catch {
            setVarError('Failed to update variable');
        }
    };

    const handleDeleteVar = async (v: SharedVariable) => {
        if (!confirm(`Delete variable "${v.key}"?`)) return;
        setVarError('');
        try {
            const res = await fetch(`/projects/${project.uuid}/shared-variables/${v.id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
            });
            if (!res.ok) {
                const err = await res.json();
                setVarError(err.message || 'Failed to delete variable');
                return;
            }
            setVariables(variables.filter(item => item.id !== v.id));
        } catch {
            setVarError('Failed to delete variable');
        }
    };

    const toggleVarVisibility = (id: number) => {
        setVisibleVarIds(prev => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    };

    const channelIcons: Record<string, string> = {
        discord: 'Discord',
        slack: 'Slack',
        telegram: 'Telegram',
        email: 'Email',
        webhook: 'Webhook',
    };

    return (
        <AppLayout
            title={`${project.name} - Settings`}
            breadcrumbs={[
                { label: 'Projects', href: '/projects' },
                { label: project.name, href: `/projects/${project.uuid}` },
                { label: 'Settings' },
            ]}
        >
            <div className="mx-auto max-w-3xl px-6 py-8">
                {/* Back link */}
                <Link
                    href={`/projects/${project.uuid}`}
                    className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="mr-2 h-4 w-4" />
                    Back to Project
                </Link>

                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-2xl font-bold text-foreground">Project Settings</h1>
                    <p className="mt-1 text-foreground-muted">
                        Manage your project configuration
                    </p>
                </div>

                {/* 1. General Settings */}
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle>General</CardTitle>
                        <CardDescription>Basic project information</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <label htmlFor="name" className="block text-sm font-medium text-foreground">
                                    Project Name
                                </label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="My Project"
                                    className="mt-1"
                                />
                                {errors.name && (
                                    <p className="mt-1 text-sm text-danger">{errors.name}</p>
                                )}
                            </div>

                            <div>
                                <label htmlFor="description" className="block text-sm font-medium text-foreground">
                                    Description
                                </label>
                                <textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Optional project description..."
                                    rows={3}
                                    className="mt-1 w-full rounded-lg border border-border bg-background-secondary px-3 py-2 text-foreground placeholder:text-foreground-muted focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                                />
                                {errors.description && (
                                    <p className="mt-1 text-sm text-danger">{errors.description}</p>
                                )}
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    <Save className="mr-2 h-4 w-4" />
                                    {processing ? 'Saving...' : 'Save Changes'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* 2. Environment Management */}
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Layers className="h-5 w-5" />
                            Environments
                        </CardTitle>
                        <CardDescription>Manage project environments</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {envError && (
                            <div className="mb-4 rounded-lg border border-danger/50 bg-danger/5 p-3 text-sm text-danger">
                                {envError}
                            </div>
                        )}

                        {/* Add environment form */}
                        <div className="mb-4 flex items-end gap-3">
                            <div className="flex-1">
                                <Input
                                    value={newEnvName}
                                    onChange={(e) => setNewEnvName(e.target.value)}
                                    placeholder="Environment name (e.g. staging)"
                                    onKeyDown={(e) => e.key === 'Enter' && handleCreateEnv()}
                                />
                            </div>
                            <Button onClick={handleCreateEnv} disabled={!newEnvName.trim() || creatingEnv} size="default">
                                <Plus className="mr-2 h-4 w-4" />
                                Add
                            </Button>
                        </div>

                        {/* Environments list */}
                        <div className="divide-y divide-border rounded-lg border border-border">
                            {environments.length === 0 ? (
                                <div className="p-4 text-center text-sm text-foreground-muted">No environments</div>
                            ) : (
                                environments.map((env) => (
                                    <div key={env.id} className="flex items-center justify-between px-4 py-3">
                                        {editingEnvId === env.id ? (
                                            <div className="flex flex-1 items-center gap-2">
                                                <Input
                                                    value={editEnvName}
                                                    onChange={(e) => setEditEnvName(e.target.value)}
                                                    className="max-w-xs"
                                                    onKeyDown={(e) => {
                                                        if (e.key === 'Enter') handleRenameEnv(env);
                                                        if (e.key === 'Escape') setEditingEnvId(null);
                                                    }}
                                                    autoFocus
                                                />
                                                <button
                                                    onClick={() => handleRenameEnv(env)}
                                                    className="rounded p-1 text-success hover:bg-success/10"
                                                >
                                                    <Check className="h-4 w-4" />
                                                </button>
                                                <button
                                                    onClick={() => setEditingEnvId(null)}
                                                    className="rounded p-1 text-foreground-muted hover:bg-background-secondary"
                                                >
                                                    <X className="h-4 w-4" />
                                                </button>
                                            </div>
                                        ) : (
                                            <>
                                                <div className="flex items-center gap-3">
                                                    <span className="font-medium text-foreground">{env.name}</span>
                                                    {!env.is_empty && (
                                                        <Badge variant="warning" size="sm">has resources</Badge>
                                                    )}
                                                </div>
                                                <div className="flex items-center gap-1">
                                                    <button
                                                        onClick={() => {
                                                            setEditingEnvId(env.id);
                                                            setEditEnvName(env.name);
                                                        }}
                                                        className="rounded p-1.5 text-foreground-muted hover:bg-background-secondary hover:text-foreground"
                                                        title="Rename"
                                                    >
                                                        <Pencil className="h-3.5 w-3.5" />
                                                    </button>
                                                    <button
                                                        onClick={() => handleDeleteEnv(env)}
                                                        disabled={!env.is_empty}
                                                        className="rounded p-1.5 text-foreground-muted hover:bg-danger/10 hover:text-danger disabled:cursor-not-allowed disabled:opacity-40"
                                                        title={env.is_empty ? 'Delete' : 'Remove resources first'}
                                                    >
                                                        <Trash2 className="h-3.5 w-3.5" />
                                                    </button>
                                                </div>
                                            </>
                                        )}
                                    </div>
                                ))
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* 3. Shared Environment Variables */}
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Key className="h-5 w-5" />
                            Shared Variables
                        </CardTitle>
                        <CardDescription>
                            Project-level environment variables shared across all environments
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {varError && (
                            <div className="mb-4 rounded-lg border border-danger/50 bg-danger/5 p-3 text-sm text-danger">
                                {varError}
                            </div>
                        )}

                        {/* Add variable form */}
                        <div className="mb-4 flex items-end gap-3">
                            <div className="flex-1">
                                <Input
                                    value={newVarKey}
                                    onChange={(e) => setNewVarKey(e.target.value.toUpperCase())}
                                    placeholder="KEY"
                                    className="font-mono"
                                />
                            </div>
                            <div className="flex-1">
                                <Input
                                    value={newVarValue}
                                    onChange={(e) => setNewVarValue(e.target.value)}
                                    placeholder="value"
                                />
                            </div>
                            <Button onClick={handleCreateVar} disabled={!newVarKey.trim() || !newVarValue.trim() || creatingVar} size="default">
                                <Plus className="mr-2 h-4 w-4" />
                                Add
                            </Button>
                        </div>

                        {/* Variables list */}
                        <div className="divide-y divide-border rounded-lg border border-border">
                            {variables.length === 0 ? (
                                <div className="p-4 text-center text-sm text-foreground-muted">No shared variables</div>
                            ) : (
                                variables.map((v) => (
                                    <div key={v.id} className="flex items-center justify-between px-4 py-3">
                                        {editingVarId === v.id ? (
                                            <div className="flex flex-1 items-center gap-2">
                                                <Input
                                                    value={editVarKey}
                                                    onChange={(e) => setEditVarKey(e.target.value.toUpperCase())}
                                                    className="max-w-[180px] font-mono"
                                                    autoFocus
                                                />
                                                <Input
                                                    value={editVarValue}
                                                    onChange={(e) => setEditVarValue(e.target.value)}
                                                    className="flex-1"
                                                />
                                                <button
                                                    onClick={() => handleUpdateVar(v)}
                                                    className="rounded p-1 text-success hover:bg-success/10"
                                                >
                                                    <Check className="h-4 w-4" />
                                                </button>
                                                <button
                                                    onClick={() => setEditingVarId(null)}
                                                    className="rounded p-1 text-foreground-muted hover:bg-background-secondary"
                                                >
                                                    <X className="h-4 w-4" />
                                                </button>
                                            </div>
                                        ) : (
                                            <>
                                                <div className="flex items-center gap-3 overflow-hidden">
                                                    <span className="shrink-0 font-mono text-sm font-medium text-primary">{v.key}</span>
                                                    <span className="truncate text-sm text-foreground-muted">
                                                        {visibleVarIds.has(v.id) ? v.value : '••••••••'}
                                                    </span>
                                                </div>
                                                <div className="flex shrink-0 items-center gap-1">
                                                    <button
                                                        onClick={() => toggleVarVisibility(v.id)}
                                                        className="rounded p-1.5 text-foreground-muted hover:bg-background-secondary hover:text-foreground"
                                                        title={visibleVarIds.has(v.id) ? 'Hide' : 'Show'}
                                                    >
                                                        {visibleVarIds.has(v.id) ? <EyeOff className="h-3.5 w-3.5" /> : <Eye className="h-3.5 w-3.5" />}
                                                    </button>
                                                    <button
                                                        onClick={() => {
                                                            setEditingVarId(v.id);
                                                            setEditVarKey(v.key);
                                                            setEditVarValue(v.value);
                                                        }}
                                                        className="rounded p-1.5 text-foreground-muted hover:bg-background-secondary hover:text-foreground"
                                                        title="Edit"
                                                    >
                                                        <Pencil className="h-3.5 w-3.5" />
                                                    </button>
                                                    <button
                                                        onClick={() => handleDeleteVar(v)}
                                                        className="rounded p-1.5 text-foreground-muted hover:bg-danger/10 hover:text-danger"
                                                        title="Delete"
                                                    >
                                                        <Trash2 className="h-3.5 w-3.5" />
                                                    </button>
                                                </div>
                                            </>
                                        )}
                                    </div>
                                ))
                            )}
                        </div>

                        <div className="mt-3 text-right">
                            <Link
                                href="/shared-variables"
                                className="inline-flex items-center gap-1 text-sm text-foreground-muted hover:text-foreground"
                            >
                                All shared variables
                                <ExternalLink className="h-3.5 w-3.5" />
                            </Link>
                        </div>
                    </CardContent>
                </Card>

                {/* 4. Default Server */}
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Server className="h-5 w-5" />
                            Default Server
                        </CardTitle>
                        <CardDescription>
                            Server suggested when creating new resources in this project
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex items-end gap-3">
                            <div className="flex-1">
                                <Select
                                    value={serverForm.data.default_server_id}
                                    onChange={(e) => serverForm.setData('default_server_id', e.target.value)}
                                    options={[
                                        { value: '', label: 'No default server' },
                                        ...servers.map(s => ({
                                            value: s.id.toString(),
                                            label: `${s.name} (${s.ip})`,
                                        })),
                                    ]}
                                />
                            </div>
                            <Button onClick={handleSaveDefaultServer} disabled={serverForm.processing}>
                                <Save className="mr-2 h-4 w-4" />
                                {serverForm.processing ? 'Saving...' : 'Save'}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {/* 5. Notifications */}
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Bell className="h-5 w-5" />
                            Notifications
                        </CardTitle>
                        <CardDescription>
                            Notification channels are configured at team level
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {Object.entries(notificationChannels).map(([channel, status]) => (
                                <div key={channel} className="flex items-center justify-between">
                                    <span className="text-sm font-medium capitalize text-foreground">
                                        {channelIcons[channel] || channel}
                                    </span>
                                    <div className="flex items-center gap-2">
                                        {status.configured ? (
                                            <Badge variant={status.enabled ? 'success' : 'secondary'} size="sm">
                                                {status.enabled ? 'Enabled' : 'Disabled'}
                                            </Badge>
                                        ) : (
                                            <Badge variant="secondary" size="sm">Not configured</Badge>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                        <div className="mt-4 border-t border-border pt-4">
                            <Link
                                href="/settings/notifications"
                                className="inline-flex items-center gap-1 text-sm text-foreground-muted hover:text-foreground"
                            >
                                Configure Notifications
                                <ExternalLink className="h-3.5 w-3.5" />
                            </Link>
                        </div>
                    </CardContent>
                </Card>

                {/* 6. Team Access */}
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Users className="h-5 w-5" />
                            Team Access
                        </CardTitle>
                        <CardDescription>
                            Members of <span className="font-medium text-foreground">{teamName}</span> have access to this project
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="divide-y divide-border rounded-lg border border-border">
                            {teamMembers.map(member => (
                                <div key={member.id} className="flex items-center justify-between px-4 py-3">
                                    <div>
                                        <span className="font-medium text-foreground">{member.name}</span>
                                        <span className="ml-2 text-sm text-foreground-muted">{member.email}</span>
                                    </div>
                                    <Badge variant="secondary" size="sm">{member.role}</Badge>
                                </div>
                            ))}
                        </div>
                        <div className="mt-3 text-right">
                            <Link
                                href="/settings/team"
                                className="inline-flex items-center gap-1 text-sm text-foreground-muted hover:text-foreground"
                            >
                                Manage Team
                                <ExternalLink className="h-3.5 w-3.5" />
                            </Link>
                        </div>
                    </CardContent>
                </Card>

                {/* 7. Project Info */}
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle>Project Information</CardTitle>
                        <CardDescription>Read-only project metadata</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <dl className="space-y-3">
                            <div className="flex justify-between">
                                <dt className="text-foreground-muted">UUID</dt>
                                <dd className="font-mono text-sm text-foreground">{project.uuid}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-foreground-muted">Created</dt>
                                <dd className="text-foreground">
                                    {new Date(project.created_at).toLocaleDateString('en-US', {
                                        year: 'numeric',
                                        month: 'long',
                                        day: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit',
                                    })}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-foreground-muted">Last Updated</dt>
                                <dd className="text-foreground">
                                    {new Date(project.updated_at).toLocaleDateString('en-US', {
                                        year: 'numeric',
                                        month: 'long',
                                        day: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit',
                                    })}
                                </dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                {/* 8. Danger Zone */}
                <Card className="border-danger/50">
                    <CardHeader>
                        <CardTitle className="text-danger">Danger Zone</CardTitle>
                        <CardDescription>
                            Irreversible actions that affect your project
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {!project.is_empty && (
                            <div className="mb-4 rounded-lg border border-warning/50 bg-warning/5 p-4">
                                <div className="flex items-start gap-3">
                                    <AlertTriangle className="mt-0.5 h-5 w-5 text-warning" />
                                    <div>
                                        <p className="font-medium text-warning">
                                            Project cannot be deleted
                                        </p>
                                        <p className="mt-1 text-sm text-foreground-muted">
                                            This project contains {project.total_resources} resource(s) that must be removed first:
                                        </p>
                                        <ul className="mt-2 list-inside list-disc text-sm text-foreground-muted">
                                            {project.resources_count.applications > 0 && (
                                                <li>{project.resources_count.applications} application(s)</li>
                                            )}
                                            {project.resources_count.services > 0 && (
                                                <li>{project.resources_count.services} service(s)</li>
                                            )}
                                            {project.resources_count.databases > 0 && (
                                                <li>{project.resources_count.databases} database(s)</li>
                                            )}
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        )}

                        {!showDeleteConfirm ? (
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="font-medium text-foreground">Delete Project</p>
                                    <p className="text-sm text-foreground-muted">
                                        Permanently delete this project and all its data
                                    </p>
                                </div>
                                <Button
                                    variant="danger"
                                    onClick={() => setShowDeleteConfirm(true)}
                                    disabled={!project.is_empty}
                                >
                                    <Trash2 className="mr-2 h-4 w-4" />
                                    Delete Project
                                </Button>
                            </div>
                        ) : (
                            <div className="rounded-lg border border-danger/50 bg-danger/5 p-4">
                                <div className="flex items-start gap-3">
                                    <AlertTriangle className="mt-0.5 h-5 w-5 text-danger" />
                                    <div className="flex-1">
                                        <p className="font-medium text-danger">
                                            Are you sure you want to delete this project?
                                        </p>
                                        <p className="mt-1 text-sm text-foreground-muted">
                                            This action cannot be undone. All environments will be permanently deleted.
                                        </p>
                                        <p className="mt-3 text-sm text-foreground">
                                            Type <span className="font-mono font-bold">{project.name}</span> to confirm:
                                        </p>
                                        <Input
                                            value={deleteConfirmName}
                                            onChange={(e) => setDeleteConfirmName(e.target.value)}
                                            placeholder={project.name}
                                            className="mt-2"
                                        />
                                        <div className="mt-4 flex gap-2">
                                            <Button
                                                variant="ghost"
                                                onClick={() => {
                                                    setShowDeleteConfirm(false);
                                                    setDeleteConfirmName('');
                                                }}
                                            >
                                                Cancel
                                            </Button>
                                            <Button
                                                variant="danger"
                                                onClick={handleDelete}
                                                disabled={deleteConfirmName !== project.name}
                                            >
                                                <Trash2 className="mr-2 h-4 w-4" />
                                                Delete Project
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
