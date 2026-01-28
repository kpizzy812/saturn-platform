import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription, Button, Badge } from '@/components/ui';
import { Shield, Server, AlertTriangle, CheckCircle, Settings, ArrowLeft } from 'lucide-react';
import type { Environment, Project } from '@/types';

interface Props {
    environment: Environment & {
        type?: 'development' | 'uat' | 'production';
        requires_approval?: boolean;
    };
    project: Project;
    canManage: boolean;
    userRole: string;
}

const environmentTypes = [
    {
        value: 'development',
        label: 'Development',
        description: 'Free deployment for all project members (except viewers)',
        color: 'bg-blue-500/20 text-blue-400 border-blue-500/30',
    },
    {
        value: 'uat',
        label: 'UAT / Staging',
        description: 'Testing environment. Deployments allowed for developers and above',
        color: 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30',
    },
    {
        value: 'production',
        label: 'Production',
        description: 'Live environment. May require approval for deployments',
        color: 'bg-red-500/20 text-red-400 border-red-500/30',
    },
];

export default function EnvironmentSettings({ environment, project, canManage, userRole }: Props) {
    const [type, setType] = useState(environment.type || 'development');
    const [requiresApproval, setRequiresApproval] = useState(environment.requires_approval || false);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');

    // Auto-enable approval for production
    useEffect(() => {
        if (type === 'production' && !requiresApproval) {
            // Suggest enabling approval for production, but don't force it
        }
    }, [type]);

    const handleSave = async () => {
        setSaving(true);
        setError('');
        setSuccess('');

        try {
            const res = await fetch(`/api/v1/environments/${environment.uuid}/settings`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ type, requires_approval: requiresApproval }),
            });

            if (!res.ok) {
                const data = await res.json();
                throw new Error(data.message || 'Failed to save settings');
            }

            setSuccess('Settings saved successfully');
            setTimeout(() => setSuccess(''), 3000);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to save settings');
        } finally {
            setSaving(false);
        }
    };

    // If user can't manage, show read-only view
    if (!canManage) {
        return (
            <AppLayout
                title={`${environment.name} Settings`}
                breadcrumbs={[
                    { label: 'Projects', href: '/projects' },
                    { label: project.name, href: `/projects/${project.uuid}` },
                    { label: environment.name },
                    { label: 'Settings' },
                ]}
            >
                <div className="mx-auto max-w-2xl">
                    <Card className="border-warning/30">
                        <CardContent className="py-8 text-center">
                            <Shield className="mx-auto mb-4 h-12 w-12 text-warning" />
                            <h3 className="text-lg font-medium text-foreground">Access Restricted</h3>
                            <p className="mt-2 text-foreground-muted">
                                You need admin or owner role to manage environment settings.
                            </p>
                            <p className="mt-1 text-sm text-foreground-muted">
                                Your current role: <Badge variant="secondary">{userRole}</Badge>
                            </p>
                            <Button
                                variant="secondary"
                                className="mt-6"
                                onClick={() => router.visit(`/projects/${project.uuid}`)}
                            >
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Back to Project
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout
            title={`${environment.name} Settings`}
            breadcrumbs={[
                { label: 'Projects', href: '/projects' },
                { label: project.name, href: `/projects/${project.uuid}` },
                { label: environment.name },
                { label: 'Settings' },
            ]}
        >
            <div className="mx-auto max-w-2xl space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Environment Settings</h1>
                        <p className="mt-1 text-foreground-muted">
                            Configure deployment rules for <span className="font-medium">{environment.name}</span>
                        </p>
                    </div>
                    <Button
                        variant="secondary"
                        onClick={() => router.visit(`/projects/${project.uuid}`)}
                    >
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Back
                    </Button>
                </div>

                {/* Error/Success Messages */}
                {error && (
                    <Card className="border-danger/50 bg-danger/5">
                        <CardContent className="flex items-center gap-3 py-3">
                            <AlertTriangle className="h-5 w-5 text-danger" />
                            <span className="text-danger">{error}</span>
                        </CardContent>
                    </Card>
                )}
                {success && (
                    <Card className="border-success/50 bg-success/5">
                        <CardContent className="flex items-center gap-3 py-3">
                            <CheckCircle className="h-5 w-5 text-success" />
                            <span className="text-success">{success}</span>
                        </CardContent>
                    </Card>
                )}

                {/* Environment Type */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Server className="h-5 w-5" />
                            Environment Type
                        </CardTitle>
                        <CardDescription>
                            Choose the type that best describes this environment's purpose
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {environmentTypes.map((envType) => (
                            <button
                                key={envType.value}
                                onClick={() => setType(envType.value as typeof type)}
                                className={`w-full rounded-lg border p-4 text-left transition-all ${
                                    type === envType.value
                                        ? `${envType.color} border-2`
                                        : 'border-border hover:border-border/80 hover:bg-background-secondary'
                                }`}
                            >
                                <div className="flex items-center justify-between">
                                    <span className="font-medium text-foreground">{envType.label}</span>
                                    {type === envType.value && (
                                        <CheckCircle className="h-5 w-5" />
                                    )}
                                </div>
                                <p className="mt-1 text-sm text-foreground-muted">{envType.description}</p>
                            </button>
                        ))}
                    </CardContent>
                </Card>

                {/* Deployment Approval */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Shield className="h-5 w-5" />
                            Deployment Approval
                        </CardTitle>
                        <CardDescription>
                            Require admin approval before deployments go live
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <button
                            onClick={() => setRequiresApproval(!requiresApproval)}
                            className={`w-full rounded-lg border p-4 text-left transition-all ${
                                requiresApproval
                                    ? 'border-2 border-primary/50 bg-primary/10'
                                    : 'border-border hover:border-border/80 hover:bg-background-secondary'
                            }`}
                        >
                            <div className="flex items-center justify-between">
                                <div>
                                    <span className="font-medium text-foreground">
                                        Require Approval for Deployments
                                    </span>
                                    <p className="mt-1 text-sm text-foreground-muted">
                                        When enabled, developers must request approval from admins/owners before
                                        their deployments are executed. Admins and owners can deploy directly.
                                    </p>
                                </div>
                                <div
                                    className={`flex h-6 w-11 items-center rounded-full p-1 transition-colors ${
                                        requiresApproval ? 'bg-primary' : 'bg-background-tertiary'
                                    }`}
                                >
                                    <div
                                        className={`h-4 w-4 rounded-full bg-white shadow-sm transition-transform ${
                                            requiresApproval ? 'translate-x-5' : 'translate-x-0'
                                        }`}
                                    />
                                </div>
                            </div>
                        </button>

                        {type === 'production' && !requiresApproval && (
                            <div className="mt-4 flex items-start gap-3 rounded-lg border border-warning/30 bg-warning/5 p-4">
                                <AlertTriangle className="mt-0.5 h-5 w-5 flex-shrink-0 text-warning" />
                                <div className="text-sm">
                                    <p className="font-medium text-warning">Recommendation</p>
                                    <p className="mt-1 text-foreground-muted">
                                        Consider enabling approval for production environments to prevent
                                        accidental deployments and ensure code review before going live.
                                    </p>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Permissions Summary */}
                <Card className="border-info/30 bg-info/5">
                    <CardHeader>
                        <CardTitle className="text-base">Deployment Permissions Summary</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2 text-sm">
                            <div className="flex items-center justify-between">
                                <span className="text-foreground-muted">Owners & Admins</span>
                                <Badge variant="success">Can deploy directly</Badge>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-foreground-muted">Developers</span>
                                <Badge variant={requiresApproval ? 'warning' : 'success'}>
                                    {requiresApproval ? 'Requires approval' : 'Can deploy directly'}
                                </Badge>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-foreground-muted">Members</span>
                                <Badge variant={type === 'development' ? (requiresApproval ? 'warning' : 'success') : 'danger'}>
                                    {type === 'development'
                                        ? requiresApproval ? 'Requires approval' : 'Can deploy'
                                        : 'Cannot deploy'}
                                </Badge>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-foreground-muted">Viewers</span>
                                <Badge variant="danger">Cannot deploy</Badge>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Save Button */}
                <div className="flex justify-end gap-3">
                    <Button
                        variant="secondary"
                        onClick={() => router.visit(`/projects/${project.uuid}`)}
                    >
                        Cancel
                    </Button>
                    <Button onClick={handleSave} disabled={saving}>
                        <Settings className="mr-2 h-4 w-4" />
                        {saving ? 'Saving...' : 'Save Settings'}
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
