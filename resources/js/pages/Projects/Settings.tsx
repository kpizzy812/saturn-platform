import React, { useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription, Button, Input, Badge, Select } from '@/components/ui';
import {
    ArrowLeft, Save, Trash2, AlertTriangle, Plus, Pencil, Check, X,
    Eye, EyeOff, ExternalLink, Layers, Server, Bell, Users, Key, UserCog,
    Activity, Tag, Archive, ArchiveRestore, Download, ArrowRightLeft, Copy,
    Gauge, GitBranch, Loader2,
} from 'lucide-react';
import { useProjectActivity } from '@/hooks/useProjectActivity';
import { useGitBranches } from '@/hooks/useGitBranches';
import { BranchSelector } from '@/components/ui/BranchSelector';

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
    is_archived: boolean;
    archived_at: string | null;
}

interface Environment {
    id: number;
    uuid: string;
    name: string;
    created_at: string;
    is_empty: boolean;
    default_git_branch: string | null;
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

interface TagItem {
    id: number;
    name: string;
}

interface TeamOption {
    id: number;
    name: string;
}

interface QuotaItem {
    current: number;
    limit: number | null;
}

interface DeploymentDefaults {
    default_build_pack: string | null;
    default_auto_deploy: boolean | null;
    default_force_https: boolean | null;
    default_preview_deployments: boolean | null;
    default_auto_rollback: boolean | null;
}

interface NotificationOverrides {
    deployment_success: boolean | null;
    deployment_failure: boolean | null;
    backup_success: boolean | null;
    backup_failure: boolean | null;
    status_change: boolean | null;
    custom_discord_webhook: string | null;
    custom_slack_webhook: string | null;
    custom_webhook_url: string | null;
}

interface Props {
    project: Project;
    environments: Environment[];
    sharedVariables: SharedVariable[];
    servers: ServerOption[];
    notificationChannels: Record<string, NotificationChannel>;
    teamMembers: TeamMember[];
    teamName: string;
    projectTags: TagItem[];
    availableTags: TagItem[];
    userTeams: TeamOption[];
    quotas: Record<string, QuotaItem>;
    deploymentDefaults: DeploymentDefaults;
    projectRepositories: string[];
    notificationOverrides: NotificationOverrides;
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
    projectTags: initialTags,
    availableTags,
    userTeams,
    quotas,
    deploymentDefaults,
    projectRepositories,
    notificationOverrides,
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

    // Tags state
    const [tags, setTags] = useState<TagItem[]>(initialTags);
    const [tagInput, setTagInput] = useState('');
    const [tagError, setTagError] = useState('');

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

    // Tag handlers
    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const handleAddTag = async (tagId?: number, name?: string) => {
        setTagError('');
        try {
            const res = await fetch(`/projects/${project.uuid}/tags`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body: JSON.stringify(tagId ? { tag_id: tagId } : { name }),
            });
            if (!res.ok) {
                const err = await res.json();
                setTagError(err.message || 'Failed to add tag');
                return;
            }
            const tag = await res.json();
            if (!tags.find(t => t.id === tag.id)) {
                setTags([...tags, tag]);
            }
            setTagInput('');
        } catch {
            setTagError('Failed to add tag');
        }
    };

    const handleRemoveTag = async (tagId: number) => {
        setTagError('');
        try {
            const res = await fetch(`/projects/${project.uuid}/tags/${tagId}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken() },
            });
            if (!res.ok) {
                setTagError('Failed to remove tag');
                return;
            }
            setTags(tags.filter(t => t.id !== tagId));
        } catch {
            setTagError('Failed to remove tag');
        }
    };

    const filteredAvailableTags = (availableTags || []).filter(
        t => !tags.find(pt => pt.id === t.id) && t.name.includes(tagInput.toLowerCase())
    );

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
            <div className="mx-auto max-w-3xl">
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

                {/* 5. Tags */}
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Tag className="h-5 w-5" />
                            Tags
                        </CardTitle>
                        <CardDescription>Organize your project with labels</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {tagError && (
                            <div className="mb-4 rounded-lg border border-danger/50 bg-danger/5 p-3 text-sm text-danger">
                                {tagError}
                            </div>
                        )}

                        {/* Current tags */}
                        <div className="mb-4 flex flex-wrap gap-2">
                            {tags.length === 0 && (
                                <span className="text-sm text-foreground-muted">No tags</span>
                            )}
                            {tags.map((tag) => (
                                <span
                                    key={tag.id}
                                    className="inline-flex items-center gap-1 rounded-full bg-primary/10 px-3 py-1 text-sm font-medium text-primary"
                                >
                                    {tag.name}
                                    <button
                                        onClick={() => handleRemoveTag(tag.id)}
                                        className="ml-0.5 rounded-full p-0.5 hover:bg-primary/20"
                                    >
                                        <X className="h-3 w-3" />
                                    </button>
                                </span>
                            ))}
                        </div>

                        {/* Add tag input */}
                        <div className="relative">
                            <div className="flex items-end gap-3">
                                <div className="flex-1">
                                    <Input
                                        value={tagInput}
                                        onChange={(e) => setTagInput(e.target.value)}
                                        placeholder="Search or create tag..."
                                        onKeyDown={(e) => {
                                            if (e.key === 'Enter' && tagInput.trim()) {
                                                const existing = filteredAvailableTags.find(t => t.name === tagInput.toLowerCase().trim());
                                                handleAddTag(existing?.id, existing ? undefined : tagInput.trim());
                                            }
                                        }}
                                    />
                                </div>
                                <Button
                                    onClick={() => {
                                        if (!tagInput.trim()) return;
                                        const existing = filteredAvailableTags.find(t => t.name === tagInput.toLowerCase().trim());
                                        handleAddTag(existing?.id, existing ? undefined : tagInput.trim());
                                    }}
                                    disabled={!tagInput.trim()}
                                    size="default"
                                >
                                    <Plus className="mr-2 h-4 w-4" />
                                    Add
                                </Button>
                            </div>

                            {/* Suggestions dropdown */}
                            {tagInput && filteredAvailableTags.length > 0 && (
                                <div className="absolute z-10 mt-1 w-full rounded-lg border border-border bg-background shadow-lg">
                                    {filteredAvailableTags.slice(0, 8).map((tag) => (
                                        <button
                                            key={tag.id}
                                            onClick={() => handleAddTag(tag.id)}
                                            className="block w-full px-4 py-2 text-left text-sm text-foreground hover:bg-background-secondary"
                                        >
                                            {tag.name}
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* 6. Notifications */}
                <NotificationOverridesSection
                    projectUuid={project.uuid}
                    overrides={notificationOverrides}
                    channels={notificationChannels}
                />

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
                        <div className="mt-4 flex items-center justify-between border-t border-border pt-4">
                            <Link
                                href={`/projects/${project.uuid}/members`}
                                className="inline-flex items-center gap-2 rounded-lg bg-primary/10 px-3 py-2 text-sm font-medium text-primary hover:bg-primary/20"
                            >
                                <UserCog className="h-4 w-4" />
                                Manage Project Members & Roles
                            </Link>
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

                {/* 7. Resource Limits */}
                <ResourceLimitsSection projectUuid={project.uuid} quotas={quotas} />

                {/* 8. Default Deployment Settings */}
                <DeploymentDefaultsSection projectUuid={project.uuid} defaults={deploymentDefaults} environments={environments} repositories={projectRepositories} />

                {/* 9. Project Info */}
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

                        {/* Export & Clone buttons */}
                        <div className="mt-4 flex gap-3 border-t border-border pt-4">
                            <Button variant="secondary" size="sm" onClick={() => window.open(`/projects/${project.uuid}/export`, '_blank')}>
                                <Download className="mr-2 h-4 w-4" />
                                Export Config
                            </Button>
                            <CloneProjectButton projectUuid={project.uuid} projectName={project.name} />
                        </div>
                    </CardContent>
                </Card>

                {/* 8. Activity Log */}
                <ActivityLogSection projectUuid={project.uuid} />

                {/* 9. Danger Zone */}
                <Card className="border-danger/50">
                    <CardHeader>
                        <CardTitle className="text-danger">Danger Zone</CardTitle>
                        <CardDescription>
                            Actions that affect your project
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {/* Transfer */}
                        {userTeams.length > 0 && (
                            <DangerZoneTransfer projectUuid={project.uuid} projectName={project.name} teams={userTeams} />
                        )}

                        {/* Archive / Unarchive */}
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="font-medium text-foreground">
                                    {project.is_archived ? 'Unarchive Project' : 'Archive Project'}
                                </p>
                                <p className="text-sm text-foreground-muted">
                                    {project.is_archived
                                        ? 'Restore this project to active state'
                                        : 'Disable the project and prevent new deployments'}
                                </p>
                            </div>
                            <Button
                                variant="secondary"
                                onClick={() => router.post(`/projects/${project.uuid}/${project.is_archived ? 'unarchive' : 'archive'}`)}
                            >
                                {project.is_archived
                                    ? <><ArchiveRestore className="mr-2 h-4 w-4" /> Unarchive</>
                                    : <><Archive className="mr-2 h-4 w-4" /> Archive</>}
                            </Button>
                        </div>

                        <div className="border-t border-border" />

                        {/* Delete */}
                        {!showDeleteConfirm ? (
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="font-medium text-foreground">Delete Project</p>
                                    <p className="text-sm text-foreground-muted">
                                        Permanently delete this project and all its resources
                                    </p>
                                </div>
                                <Button
                                    variant="danger"
                                    onClick={() => setShowDeleteConfirm(true)}
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
                                            This action cannot be undone.
                                        </p>

                                        {!project.is_empty && (
                                            <div className="mt-3 rounded-lg border border-warning/50 bg-warning/5 p-3">
                                                <p className="text-sm font-medium text-warning">
                                                    This will also delete {project.total_resources} resource(s):
                                                </p>
                                                <ul className="mt-1 list-inside list-disc text-sm text-foreground-muted">
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
                                        )}

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

// --- Activity Log Section ---

const ACTION_OPTIONS = [
    { value: '', label: 'All Actions' },
    { value: 'create', label: 'Created' },
    { value: 'update', label: 'Updated' },
    { value: 'delete', label: 'Deleted' },
    { value: 'deployment_completed', label: 'Deployment Completed' },
    { value: 'deployment_failed', label: 'Deployment Failed' },
    { value: 'deployment_started', label: 'Deployment Started' },
];

function ActivityLogSection({ projectUuid }: { projectUuid: string }) {
    const { activities, loading, error, actionFilter, setActionFilter, loadMore, hasMore } = useProjectActivity({
        projectUuid,
    });

    return (
        <Card className="mb-6">
            <CardHeader>
                <div className="flex items-center justify-between">
                    <div>
                        <CardTitle className="flex items-center gap-2">
                            <Activity className="h-5 w-5" />
                            Activity Log
                        </CardTitle>
                        <CardDescription>Recent activity in this project</CardDescription>
                    </div>
                    <select
                        value={actionFilter}
                        onChange={(e) => setActionFilter(e.target.value)}
                        className="rounded-lg border border-border bg-background-secondary px-3 py-1.5 text-sm text-foreground"
                    >
                        {ACTION_OPTIONS.map((opt) => (
                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                        ))}
                    </select>
                </div>
            </CardHeader>
            <CardContent>
                {error && (
                    <div className="mb-4 rounded-lg border border-danger/50 bg-danger/5 p-3 text-sm text-danger">
                        {error.message}
                    </div>
                )}

                {loading && activities.length === 0 ? (
                    <div className="flex items-center justify-center py-8 text-foreground-muted">
                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                        Loading activities...
                    </div>
                ) : activities.length === 0 ? (
                    <div className="py-8 text-center text-sm text-foreground-muted">
                        No activity recorded yet
                    </div>
                ) : (
                    <div className="space-y-3">
                        {activities.map((item) => (
                            <div key={item.id} className="flex items-start gap-3 rounded-lg border border-border p-3">
                                <div className="mt-0.5 h-2 w-2 shrink-0 rounded-full" style={{
                                    backgroundColor: item.action.includes('failed') ? 'var(--color-danger)' :
                                        item.action.includes('completed') ? 'var(--color-success)' :
                                        item.action === 'delete' ? 'var(--color-warning)' :
                                        'var(--color-primary)',
                                }} />
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm text-foreground">{item.description}</p>
                                    <div className="mt-1 flex items-center gap-2 text-xs text-foreground-muted">
                                        <span>{item.user?.name ?? 'System'}</span>
                                        <span>&middot;</span>
                                        <span>{new Date(item.timestamp).toLocaleString()}</span>
                                        {item.resource?.type && (
                                            <>
                                                <span>&middot;</span>
                                                <Badge variant="secondary" size="sm">{item.resource.type}</Badge>
                                            </>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ))}

                        {hasMore && (
                            <div className="pt-2 text-center">
                                <Button variant="ghost" size="sm" onClick={loadMore} disabled={loading}>
                                    {loading ? (
                                        <><Loader2 className="mr-2 h-3 w-3 animate-spin" /> Loading...</>
                                    ) : (
                                        'Load More'
                                    )}
                                </Button>
                            </div>
                        )}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

// --- Resource Limits Section ---

const QUOTA_LABELS: Record<string, string> = {
    application: 'Applications',
    service: 'Services',
    database: 'Databases',
    environment: 'Environments',
};

function ResourceLimitsSection({ projectUuid, quotas }: { projectUuid: string; quotas: Record<string, QuotaItem> }) {
    const { data, setData, patch, processing } = useForm({
        max_applications: quotas.application?.limit?.toString() ?? '',
        max_services: quotas.service?.limit?.toString() ?? '',
        max_databases: quotas.database?.limit?.toString() ?? '',
        max_environments: quotas.environment?.limit?.toString() ?? '',
    });

    const handleSave = (e: React.FormEvent) => {
        e.preventDefault();
        patch(`/projects/${projectUuid}/settings/quotas`, { preserveScroll: true });
    };

    return (
        <Card className="mb-6">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Gauge className="h-5 w-5" />
                    Resource Limits
                </CardTitle>
                <CardDescription>Set limits for resources in this project. Empty = unlimited.</CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={handleSave} className="space-y-4">
                    {Object.entries(quotas).map(([type, quota]) => (
                        <div key={type} className="flex items-center justify-between">
                            <div>
                                <span className="font-medium text-foreground">{QUOTA_LABELS[type] || type}</span>
                                <span className="ml-2 text-sm text-foreground-muted">
                                    {quota.current} / {quota.limit ?? '\u221E'}
                                </span>
                            </div>
                            <Input
                                type="number"
                                min="0"
                                placeholder="Unlimited"
                                className="w-28"
                                value={(data as Record<string, string>)[`max_${type}s`] ?? ''}
                                onChange={(e) => setData(`max_${type}s` as keyof typeof data, e.target.value === '' ? '' : e.target.value)}
                            />
                        </div>
                    ))}
                    <div className="flex justify-end">
                        <Button type="submit" disabled={processing}>
                            <Save className="mr-2 h-4 w-4" />
                            {processing ? 'Saving...' : 'Save Limits'}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

// --- Deployment Defaults Section ---

const BUILD_PACK_OPTIONS = [
    { value: '', label: 'No default' },
    { value: 'nixpacks', label: 'Nixpacks' },
    { value: 'dockerfile', label: 'Dockerfile' },
    { value: 'dockerimage', label: 'Docker Image' },
    { value: 'dockercompose', label: 'Docker Compose' },
    { value: 'static', label: 'Static' },
];

function DeploymentDefaultsSection({ projectUuid, defaults, environments, repositories }: { projectUuid: string; defaults: DeploymentDefaults; environments: Environment[]; repositories: string[] }) {
    const { data, setData, patch, processing } = useForm({
        default_build_pack: defaults.default_build_pack ?? '',
        default_auto_deploy: defaults.default_auto_deploy ?? false,
        default_force_https: defaults.default_force_https ?? false,
        default_preview_deployments: defaults.default_preview_deployments ?? false,
        default_auto_rollback: defaults.default_auto_rollback ?? false,
    });

    // Per-environment branch state
    const [envBranches, setEnvBranches] = useState<Record<number, string>>(
        Object.fromEntries(environments.map(env => [env.id, env.default_git_branch ?? '']))
    );
    const [savingBranches, setSavingBranches] = useState(false);

    // Fetch branches from first project repository (if any)
    const { branches: gitBranches, isLoading: branchesLoading, error: branchesError, fetchBranches } = useGitBranches();
    const [branchesFetched, setBranchesFetched] = useState(false);

    // Auto-fetch branches from the first repository on mount
    const primaryRepo = (repositories || [])[0];
    React.useEffect(() => {
        if (primaryRepo && !branchesFetched) {
            fetchBranches(primaryRepo);
            setBranchesFetched(true);
        }
    }, [primaryRepo, branchesFetched, fetchBranches]);

    const handleSave = (e: React.FormEvent) => {
        e.preventDefault();
        patch(`/projects/${projectUuid}/settings/deployment-defaults`, { preserveScroll: true });
    };

    const handleSaveBranches = async () => {
        setSavingBranches(true);
        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const token = csrfMeta?.getAttribute('content') || '';
            await fetch(`/projects/${projectUuid}/settings/environment-branches`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({
                    branches: Object.entries(envBranches).map(([envId, branch]) => ({
                        environment_id: Number(envId),
                        branch: branch || null,
                    })),
                }),
            });
        } finally {
            setSavingBranches(false);
        }
    };

    return (
        <Card className="mb-6">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <GitBranch className="h-5 w-5" />
                    Default Deployment Settings
                </CardTitle>
                <CardDescription>Applied to new applications created in this project</CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={handleSave} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-foreground">Build Pack</label>
                        <Select
                            value={data.default_build_pack}
                            onChange={(e) => setData('default_build_pack', e.target.value)}
                            options={BUILD_PACK_OPTIONS}
                            className="mt-1 max-w-xs"
                        />
                    </div>

                    <div className="space-y-3">
                        {[
                            { key: 'default_auto_deploy' as const, label: 'Auto Deploy' },
                            { key: 'default_force_https' as const, label: 'Force HTTPS' },
                            { key: 'default_preview_deployments' as const, label: 'Preview Deployments' },
                            { key: 'default_auto_rollback' as const, label: 'Auto Rollback on Failure' },
                        ].map(({ key, label }) => (
                            <label key={key} className="flex items-center gap-3">
                                <input
                                    type="checkbox"
                                    checked={data[key] as boolean}
                                    onChange={(e) => setData(key, e.target.checked)}
                                    className="h-4 w-4 rounded border-border text-primary focus:ring-primary"
                                />
                                <span className="text-sm text-foreground">{label}</span>
                            </label>
                        ))}
                    </div>

                    <div className="flex justify-end">
                        <Button type="submit" disabled={processing}>
                            <Save className="mr-2 h-4 w-4" />
                            {processing ? 'Saving...' : 'Save Defaults'}
                        </Button>
                    </div>
                </form>

                {/* Per-environment Git Branches */}
                <div className="mt-6 border-t border-border pt-4">
                    <p className="mb-1 text-sm font-medium text-foreground">Git Branch per Environment</p>
                    <p className="mb-3 text-xs text-foreground-muted">
                        Default branch for new applications in each environment.
                        {primaryRepo && (
                            <> Branches loaded from <span className="font-mono">{primaryRepo.replace(/^https?:\/\//, '').replace(/\.git$/, '')}</span></>
                        )}
                    </p>
                    <div className="space-y-3">
                        {environments.map((env) => (
                            <div key={env.id} className="flex items-center gap-3">
                                <span className="w-32 shrink-0 text-sm font-medium text-foreground">{env.name}</span>
                                <div className="max-w-xs flex-1">
                                    <BranchSelector
                                        value={envBranches[env.id] ?? ''}
                                        onChange={(val) => setEnvBranches({ ...envBranches, [env.id]: val })}
                                        branches={gitBranches}
                                        isLoading={branchesLoading}
                                        error={branchesError}
                                        placeholder={env.name === 'production' ? 'main' : env.name === 'uat' ? 'staging' : 'dev'}
                                    />
                                </div>
                            </div>
                        ))}
                    </div>
                    <div className="mt-3 flex justify-end">
                        <Button type="button" onClick={handleSaveBranches} disabled={savingBranches}>
                            <Save className="mr-2 h-4 w-4" />
                            {savingBranches ? 'Saving...' : 'Save Branches'}
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

// --- Clone Project Button ---

function CloneProjectButton({ projectUuid, projectName }: { projectUuid: string; projectName: string }) {
    const [showModal, setShowModal] = useState(false);
    const { data, setData, post, processing } = useForm({
        name: `${projectName} (Copy)`,
        clone_shared_vars: false,
        clone_tags: true,
        clone_settings: true,
    });

    const handleClone = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/projects/${projectUuid}/clone`);
    };

    if (!showModal) {
        return (
            <Button variant="secondary" size="sm" onClick={() => setShowModal(true)}>
                <Copy className="mr-2 h-4 w-4" />
                Clone Project
            </Button>
        );
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setShowModal(false)}>
            <div className="w-full max-w-md rounded-lg border border-border bg-background p-6 shadow-xl" onClick={e => e.stopPropagation()}>
                <h3 className="text-lg font-semibold text-foreground">Clone Project</h3>
                <p className="mt-1 text-sm text-foreground-muted">Create a copy of this project structure (no resources are cloned).</p>

                <form onSubmit={handleClone} className="mt-4 space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-foreground">New Project Name</label>
                        <Input value={data.name} onChange={(e) => setData('name', e.target.value)} className="mt-1" />
                    </div>
                    <div className="space-y-2">
                        {[
                            { key: 'clone_settings' as const, label: 'Clone settings (default server, quotas, deploy defaults)' },
                            { key: 'clone_tags' as const, label: 'Clone tags' },
                            { key: 'clone_shared_vars' as const, label: 'Clone shared variables (values included)' },
                        ].map(({ key, label }) => (
                            <label key={key} className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={data[key] as boolean}
                                    onChange={(e) => setData(key, e.target.checked)}
                                    className="h-4 w-4 rounded border-border"
                                />
                                <span className="text-sm text-foreground">{label}</span>
                            </label>
                        ))}
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button variant="ghost" type="button" onClick={() => setShowModal(false)}>Cancel</Button>
                        <Button type="submit" disabled={processing || !data.name.trim()}>
                            {processing ? 'Cloning...' : 'Clone'}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// --- Transfer Project Section ---

function DangerZoneTransfer({ projectUuid, projectName, teams }: { projectUuid: string; projectName: string; teams: TeamOption[] }) {
    const [showTransfer, setShowTransfer] = useState(false);
    const [confirmName, setConfirmName] = useState('');
    const { data, setData, post, processing } = useForm({
        target_team_id: '',
    });

    const handleTransfer = () => {
        if (confirmName !== projectName || !data.target_team_id) return;
        post(`/projects/${projectUuid}/transfer`);
    };

    if (!showTransfer) {
        return (
            <div className="flex items-center justify-between">
                <div>
                    <p className="font-medium text-foreground">Transfer Project</p>
                    <p className="text-sm text-foreground-muted">Move this project to another team</p>
                </div>
                <Button variant="secondary" onClick={() => setShowTransfer(true)}>
                    <ArrowRightLeft className="mr-2 h-4 w-4" />
                    Transfer
                </Button>
            </div>
        );
    }

    return (
        <div className="rounded-lg border border-warning/50 bg-warning/5 p-4">
            <p className="font-medium text-foreground">Transfer to another team</p>
            <div className="mt-3 space-y-3">
                <div>
                    <label className="block text-sm font-medium text-foreground">Target Team</label>
                    <Select
                        value={data.target_team_id}
                        onChange={(e) => setData('target_team_id', e.target.value)}
                        options={[
                            { value: '', label: 'Select team...' },
                            ...teams.map(t => ({ value: t.id.toString(), label: t.name })),
                        ]}
                        className="mt-1"
                    />
                </div>
                <div>
                    <label className="block text-sm text-foreground">
                        Type <span className="font-mono font-bold">{projectName}</span> to confirm:
                    </label>
                    <Input value={confirmName} onChange={(e) => setConfirmName(e.target.value)} className="mt-1" />
                </div>
                <div className="flex gap-2">
                    <Button variant="ghost" onClick={() => { setShowTransfer(false); setConfirmName(''); }}>Cancel</Button>
                    <Button variant="danger" onClick={handleTransfer} disabled={processing || confirmName !== projectName || !data.target_team_id}>
                        {processing ? 'Transferring...' : 'Transfer Project'}
                    </Button>
                </div>
            </div>
        </div>
    );
}

// --- Notification Overrides Section ---

const NOTIFICATION_EVENTS = [
    { key: 'deployment_success', label: 'Deployment Success' },
    { key: 'deployment_failure', label: 'Deployment Failure' },
    { key: 'backup_success', label: 'Backup Success' },
    { key: 'backup_failure', label: 'Backup Failure' },
    { key: 'status_change', label: 'Status Change' },
] as const;

type TriState = boolean | null;

function triStateLabel(value: TriState): string {
    if (value === null) return 'Inherit';
    return value ? 'Enabled' : 'Disabled';
}

function triStateBadge(value: TriState): 'secondary' | 'success' | 'warning' {
    if (value === null) return 'secondary';
    return value ? 'success' : 'warning';
}

function cycleTriState(value: TriState): TriState {
    if (value === null) return true;
    if (value === true) return false;
    return null;
}

function NotificationOverridesSection({
    projectUuid,
    overrides,
    channels,
}: {
    projectUuid: string;
    overrides: NotificationOverrides;
    channels: Record<string, NotificationChannel>;
}) {
    const { data, setData, patch, processing } = useForm({
        deployment_success: overrides.deployment_success,
        deployment_failure: overrides.deployment_failure,
        backup_success: overrides.backup_success,
        backup_failure: overrides.backup_failure,
        status_change: overrides.status_change,
        custom_discord_webhook: '',
        custom_slack_webhook: '',
        custom_webhook_url: '',
    });

    const handleSave = (e: React.FormEvent) => {
        e.preventDefault();
        patch(`/projects/${projectUuid}/notification-overrides`, { preserveScroll: true });
    };

    const channelIcons: Record<string, string> = {
        discord: 'Discord',
        slack: 'Slack',
        telegram: 'Telegram',
        email: 'Email',
        webhook: 'Webhook',
    };

    return (
        <Card className="mb-6">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Bell className="h-5 w-5" />
                    Notification Overrides
                </CardTitle>
                <CardDescription>
                    Override team notification settings for this project. Click to cycle: Inherit &rarr; Enabled &rarr; Disabled.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={handleSave} className="space-y-4">
                    {/* Event toggles */}
                    <div className="space-y-3">
                        {NOTIFICATION_EVENTS.map(({ key, label }) => {
                            const value = data[key] as TriState;
                            return (
                                <div key={key} className="flex items-center justify-between">
                                    <span className="text-sm font-medium text-foreground">{label}</span>
                                    <button
                                        type="button"
                                        onClick={() => setData(key, cycleTriState(value))}
                                        className="min-w-[80px]"
                                    >
                                        <Badge variant={triStateBadge(value)} size="sm">
                                            {triStateLabel(value)}
                                        </Badge>
                                    </button>
                                </div>
                            );
                        })}
                    </div>

                    {/* Team channels summary */}
                    <div className="border-t border-border pt-4">
                        <p className="mb-2 text-xs font-medium uppercase text-foreground-muted">Team Channels</p>
                        <div className="flex flex-wrap gap-2">
                            {Object.entries(channels).map(([channel, status]) => (
                                <Badge key={channel} variant={status.enabled ? 'success' : 'secondary'} size="sm">
                                    {channelIcons[channel] || channel}
                                </Badge>
                            ))}
                        </div>
                    </div>

                    {/* Custom webhooks */}
                    <div className="border-t border-border pt-4">
                        <p className="mb-3 text-xs font-medium uppercase text-foreground-muted">Custom Webhooks (optional)</p>
                        <div className="space-y-3">
                            <div>
                                <label className="block text-sm text-foreground">Discord Webhook URL</label>
                                <Input
                                    value={data.custom_discord_webhook}
                                    onChange={(e) => setData('custom_discord_webhook', e.target.value)}
                                    placeholder={overrides.custom_discord_webhook ? 'Currently set (enter to change)' : 'https://discord.com/api/webhooks/...'}
                                    className="mt-1"
                                />
                            </div>
                            <div>
                                <label className="block text-sm text-foreground">Slack Webhook URL</label>
                                <Input
                                    value={data.custom_slack_webhook}
                                    onChange={(e) => setData('custom_slack_webhook', e.target.value)}
                                    placeholder={overrides.custom_slack_webhook ? 'Currently set (enter to change)' : 'https://hooks.slack.com/services/...'}
                                    className="mt-1"
                                />
                            </div>
                            <div>
                                <label className="block text-sm text-foreground">Generic Webhook URL</label>
                                <Input
                                    value={data.custom_webhook_url}
                                    onChange={(e) => setData('custom_webhook_url', e.target.value)}
                                    placeholder={overrides.custom_webhook_url ? 'Currently set (enter to change)' : 'https://...'}
                                    className="mt-1"
                                />
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center justify-between">
                        <Link
                            href="/settings/notifications"
                            className="inline-flex items-center gap-1 text-sm text-foreground-muted hover:text-foreground"
                        >
                            Team Notifications
                            <ExternalLink className="h-3.5 w-3.5" />
                        </Link>
                        <Button type="submit" disabled={processing}>
                            <Save className="mr-2 h-4 w-4" />
                            {processing ? 'Saving...' : 'Save Overrides'}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
