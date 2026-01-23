import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button, Input, Badge, useConfirm } from '@/components/ui';
import {
    Lock, Unlock, ArrowLeft, Edit, Trash2, Save, X,
    Building2, FolderKanban, Layers, Eye, EyeOff, AlertCircle
} from 'lucide-react';

interface SharedVariable {
    id: number;
    uuid: string;
    key: string;
    value: string;
    is_secret: boolean;
    scope: 'team' | 'project' | 'environment';
    scope_id: number;
    scope_name: string;
    team: { id: number; name: string };
    project?: { id: number; uuid: string; name: string };
    environment?: { id: number; uuid: string; name: string };
    created_at: string;
    updated_at: string;
}

interface Props {
    variable: SharedVariable;
}

export default function SharedVariableShow({ variable: initialVariable }: Props) {
    const [variable, setVariable] = useState<SharedVariable>(initialVariable);
    const [isEditing, setIsEditing] = useState(false);
    const [showValue, setShowValue] = useState(!variable.is_secret);
    const [formData, setFormData] = useState({
        key: variable.key,
        value: variable.value,
        is_secret: variable.is_secret,
    });
    const confirm = useConfirm();

    const handleUpdate = () => {
        router.put(`/shared-variables/${variable.uuid}`, formData, {
            preserveScroll: true,
            onSuccess: () => {
                setIsEditing(false);
                setVariable(prev => ({ ...prev, ...formData }));
            },
        });
    };

    const handleDelete = async () => {
        const confirmed = await confirm({
            title: 'Delete Variable',
            description: 'Are you sure you want to delete this variable? This action cannot be undone.',
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            router.delete(`/shared-variables/${variable.uuid}`);
        }
    };

    const getScopeIcon = (scope: string) => {
        switch (scope) {
            case 'team': return <Building2 className="h-5 w-5 text-info" />;
            case 'project': return <FolderKanban className="h-5 w-5 text-warning" />;
            case 'environment': return <Layers className="h-5 w-5 text-success" />;
            default: return null;
        }
    };

    const getScopeBadgeVariant = (scope: string) => {
        switch (scope) {
            case 'team': return 'info';
            case 'project': return 'warning';
            case 'environment': return 'success';
            default: return 'default';
        }
    };

    const getScopePath = () => {
        switch (variable.scope) {
            case 'team':
                return { label: variable.team.name, href: '/team/settings' };
            case 'project':
                return {
                    label: variable.project?.name || 'Unknown',
                    href: variable.project ? `/projects/${variable.project.uuid}` : '#'
                };
            case 'environment':
                return {
                    label: `${variable.project?.name} / ${variable.environment?.name}` || 'Unknown',
                    href: variable.environment && variable.project
                        ? `/projects/${variable.project.uuid}/environments/${variable.environment.uuid}`
                        : '#'
                };
            default:
                return { label: 'Unknown', href: '#' };
        }
    };

    const scopePath = getScopePath();

    return (
        <AppLayout
            title={variable.key}
            breadcrumbs={[
                { label: 'Dashboard', href: '/new' },
                { label: 'Shared Variables', href: '/shared-variables' },
                { label: variable.key },
            ]}
        >
            <Head title={`${variable.key} - Shared Variable`} />

            <div className="max-w-3xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4 mb-6">
                    <Link href="/shared-variables">
                        <Button variant="ghost" size="sm">
                            <ArrowLeft className="h-4 w-4 mr-2" />
                            Back
                        </Button>
                    </Link>
                </div>

                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <div className="h-14 w-14 rounded-xl bg-primary/10 flex items-center justify-center">
                            {variable.is_secret ? (
                                <Lock className="h-7 w-7 text-warning" />
                            ) : (
                                <Unlock className="h-7 w-7 text-primary" />
                            )}
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <code className="text-xl font-mono font-bold">{variable.key}</code>
                                {variable.is_secret && (
                                    <Badge variant="warning">
                                        <Lock className="h-3 w-3 mr-1" />
                                        Secret
                                    </Badge>
                                )}
                            </div>
                            <p className="text-foreground-muted text-sm mt-1">
                                Shared variable
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        {!isEditing && (
                            <>
                                <Button variant="secondary" onClick={() => setIsEditing(true)}>
                                    <Edit className="h-4 w-4 mr-2" />
                                    Edit
                                </Button>
                                <Button
                                    variant="ghost"
                                    className="text-danger hover:text-danger"
                                    onClick={handleDelete}
                                >
                                    <Trash2 className="h-4 w-4 mr-2" />
                                    Delete
                                </Button>
                            </>
                        )}
                    </div>
                </div>

                {/* Edit Form */}
                {isEditing ? (
                    <Card className="border-primary/50">
                        <CardHeader>
                            <CardTitle>Edit Variable</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium mb-2">
                                    Key <span className="text-danger">*</span>
                                </label>
                                <Input
                                    value={formData.key}
                                    onChange={(e) => setFormData({ ...formData, key: e.target.value })}
                                    placeholder="API_KEY"
                                />
                                <p className="text-xs text-foreground-muted mt-1">
                                    Use uppercase with underscores (e.g., DATABASE_URL)
                                </p>
                            </div>

                            <div>
                                <label className="block text-sm font-medium mb-2">
                                    Value <span className="text-danger">*</span>
                                </label>
                                <div className="relative">
                                    <Input
                                        type={formData.is_secret && !showValue ? 'password' : 'text'}
                                        value={formData.value}
                                        onChange={(e) => setFormData({ ...formData, value: e.target.value })}
                                        placeholder="variable value"
                                    />
                                    {formData.is_secret && (
                                        <button
                                            type="button"
                                            onClick={() => setShowValue(!showValue)}
                                            className="absolute right-3 top-1/2 -translate-y-1/2 text-foreground-muted hover:text-foreground"
                                        >
                                            {showValue ? (
                                                <EyeOff className="h-4 w-4" />
                                            ) : (
                                                <Eye className="h-4 w-4" />
                                            )}
                                        </button>
                                    )}
                                </div>
                            </div>

                            <div className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id="is_secret"
                                    checked={formData.is_secret}
                                    onChange={(e) => setFormData({ ...formData, is_secret: e.target.checked })}
                                    className="rounded border-border"
                                />
                                <label htmlFor="is_secret" className="text-sm font-medium cursor-pointer">
                                    Mark as secret (will be encrypted and hidden)
                                </label>
                            </div>

                            <div className="flex items-center gap-2 pt-4">
                                <Button onClick={handleUpdate}>
                                    <Save className="h-4 w-4 mr-2" />
                                    Save Changes
                                </Button>
                                <Button variant="ghost" onClick={() => {
                                    setIsEditing(false);
                                    setFormData({
                                        key: variable.key,
                                        value: variable.value,
                                        is_secret: variable.is_secret,
                                    });
                                }}>
                                    <X className="h-4 w-4 mr-2" />
                                    Cancel
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    /* View Mode */
                    <Card>
                        <CardHeader>
                            <CardTitle>Variable Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-2 gap-6">
                                <div>
                                    <label className="text-sm text-foreground-muted">Key</label>
                                    <code className="block mt-1 text-base font-mono bg-background-secondary px-3 py-2 rounded">
                                        {variable.key}
                                    </code>
                                </div>
                                <div>
                                    <label className="text-sm text-foreground-muted">Type</label>
                                    <div className="mt-1">
                                        {variable.is_secret ? (
                                            <Badge variant="warning">
                                                <Lock className="h-3 w-3 mr-1" />
                                                Secret
                                            </Badge>
                                        ) : (
                                            <Badge variant="default">
                                                <Unlock className="h-3 w-3 mr-1" />
                                                Plain Text
                                            </Badge>
                                        )}
                                    </div>
                                </div>
                            </div>

                            <div>
                                <div className="flex items-center justify-between mb-1">
                                    <label className="text-sm text-foreground-muted">Value</label>
                                    {variable.is_secret && (
                                        <button
                                            onClick={() => setShowValue(!showValue)}
                                            className="text-xs text-primary hover:underline flex items-center gap-1"
                                        >
                                            {showValue ? (
                                                <>
                                                    <EyeOff className="h-3 w-3" /> Hide
                                                </>
                                            ) : (
                                                <>
                                                    <Eye className="h-3 w-3" /> Show
                                                </>
                                            )}
                                        </button>
                                    )}
                                </div>
                                <div className="bg-background-secondary px-3 py-2 rounded font-mono text-sm break-all">
                                    {variable.is_secret && !showValue ? '••••••••••••••••' : variable.value}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Scope Information */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            {getScopeIcon(variable.scope)}
                            Scope
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <div className="flex items-center gap-2 mb-1">
                                    <Badge variant={getScopeBadgeVariant(variable.scope) as any}>
                                        <span className="capitalize">{variable.scope}</span>
                                    </Badge>
                                    {variable.scope !== 'team' && (
                                        <span className="text-sm text-foreground-muted">•</span>
                                    )}
                                    {variable.scope !== 'team' && (
                                        <Link href={scopePath.href} className="text-sm text-primary hover:underline">
                                            {scopePath.label}
                                        </Link>
                                    )}
                                </div>
                                <p className="text-sm text-foreground-muted">
                                    {variable.scope === 'team' && 'Available to all projects and environments in this team'}
                                    {variable.scope === 'project' && 'Available to all environments in this project'}
                                    {variable.scope === 'environment' && 'Available only to this specific environment'}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Metadata */}
                <Card className="bg-background-secondary">
                    <CardContent className="p-4">
                        <div className="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span className="text-foreground-muted">Created</span>
                                <p className="font-medium">{new Date(variable.created_at).toLocaleString()}</p>
                            </div>
                            <div>
                                <span className="text-foreground-muted">Last Updated</span>
                                <p className="font-medium">{new Date(variable.updated_at).toLocaleString()}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Warning */}
                {variable.is_secret && (
                    <Card className="bg-warning/5 border-warning/20">
                        <CardContent className="p-4 flex items-start gap-3">
                            <AlertCircle className="h-5 w-5 text-warning flex-shrink-0 mt-0.5" />
                            <div className="text-sm">
                                <p className="font-medium">Secret Variable</p>
                                <p className="text-foreground-muted mt-1">
                                    This variable is encrypted and stored securely. It will not be visible in
                                    logs or exposed in the UI unless explicitly shown.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
