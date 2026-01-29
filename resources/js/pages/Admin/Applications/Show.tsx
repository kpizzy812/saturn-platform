import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { useConfirm } from '@/components/ui';
import {
    Package,
    Calendar,
    Users,
    Server,
    FolderKanban,
    GitBranch,
    Globe,
    Play,
    Square,
    RefreshCw,
    Trash2,
    CheckCircle,
    XCircle,
    Clock,
    AlertTriangle,
    ExternalLink,
    Rocket,
    Activity,
    Box,
} from 'lucide-react';

interface Deployment {
    id: number;
    deployment_uuid: string;
    status: string;
    commit?: string;
    commit_message?: string;
    triggered_by?: string;
    created_at: string;
    finished_at?: string;
}

interface ApplicationDetails {
    id: number;
    uuid: string;
    name: string;
    description?: string;
    fqdn?: string;
    status: string;
    git_repository?: string;
    git_branch?: string;
    git_commit_sha?: string;
    build_pack?: string;
    dockerfile_location?: string;
    team_id: number;
    team_name: string;
    project_id: number;
    project_name: string;
    environment_id: number;
    environment_name: string;
    server_id?: number;
    server_name?: string;
    server_uuid?: string;
    recent_deployments: Deployment[];
    created_at: string;
    updated_at: string;
}

interface Props {
    application: ApplicationDetails;
}

function DeploymentRow({ deployment }: { deployment: Deployment }) {
    const statusConfig: Record<string, { variant: 'success' | 'default' | 'warning' | 'danger'; label: string; icon: React.ReactNode }> = {
        finished: { variant: 'success', label: 'Finished', icon: <CheckCircle className="h-3 w-3" /> },
        failed: { variant: 'danger', label: 'Failed', icon: <XCircle className="h-3 w-3" /> },
        cancelled: { variant: 'danger', label: 'Cancelled', icon: <XCircle className="h-3 w-3" /> },
        in_progress: { variant: 'warning', label: 'In Progress', icon: <Clock className="h-3 w-3" /> },
        queued: { variant: 'default', label: 'Queued', icon: <Clock className="h-3 w-3" /> },
    };

    const config = statusConfig[deployment.status] || { variant: 'default' as const, label: deployment.status || 'Unknown', icon: null };

    return (
        <div className="flex items-center justify-between border-b border-border/50 py-3 last:border-0">
            <div className="flex items-center gap-3">
                <Rocket className="h-5 w-5 text-foreground-muted" />
                <div>
                    <div className="flex items-center gap-2">
                        <span className="font-medium text-foreground">
                            {deployment.deployment_uuid.slice(0, 8)}
                        </span>
                        <Badge variant={config.variant} size="sm" icon={config.icon}>
                            {config.label}
                        </Badge>
                    </div>
                    <div className="flex items-center gap-2 text-xs text-foreground-subtle">
                        {deployment.commit && (
                            <span className="font-mono">{deployment.commit.slice(0, 7)}</span>
                        )}
                        {deployment.commit_message && (
                            <>
                                <span>·</span>
                                <span className="truncate max-w-xs">{deployment.commit_message}</span>
                            </>
                        )}
                        {deployment.triggered_by && (
                            <>
                                <span>·</span>
                                <span>by {deployment.triggered_by}</span>
                            </>
                        )}
                    </div>
                </div>
            </div>
            <div className="text-xs text-foreground-subtle">
                {new Date(deployment.created_at).toLocaleString()}
            </div>
        </div>
    );
}

export default function AdminApplicationShow({ application }: Props) {
    const confirm = useConfirm();
    const [isRestarting, setIsRestarting] = React.useState(false);
    const [isStopping, setIsStopping] = React.useState(false);
    const [isStarting, setIsStarting] = React.useState(false);
    const [isRedeploying, setIsRedeploying] = React.useState(false);

    const statusConfig: Record<string, { variant: 'success' | 'default' | 'warning' | 'danger'; label: string; icon: React.ReactNode }> = {
        running: { variant: 'success', label: 'Running', icon: <CheckCircle className="h-4 w-4" /> },
        stopped: { variant: 'default', label: 'Stopped', icon: <XCircle className="h-4 w-4" /> },
        deploying: { variant: 'warning', label: 'Deploying', icon: <Clock className="h-4 w-4" /> },
        error: { variant: 'danger', label: 'Error', icon: <AlertTriangle className="h-4 w-4" /> },
        exited: { variant: 'danger', label: 'Exited', icon: <XCircle className="h-4 w-4" /> },
    };

    const config = statusConfig[application.status] || { variant: 'default' as const, label: application.status || 'Unknown', icon: null };

    const handleRestart = async () => {
        const confirmed = await confirm({
            title: 'Restart Application',
            description: `Are you sure you want to restart "${application.name}"? This will cause a brief downtime.`,
            confirmText: 'Restart',
            variant: 'warning',
        });
        if (confirmed) {
            setIsRestarting(true);
            router.post(`/admin/applications/${application.uuid}/restart`, {}, {
                preserveScroll: true,
                onFinish: () => setIsRestarting(false),
            });
        }
    };

    const handleStop = async () => {
        const confirmed = await confirm({
            title: 'Stop Application',
            description: `Are you sure you want to stop "${application.name}"? The application will become unavailable.`,
            confirmText: 'Stop',
            variant: 'danger',
        });
        if (confirmed) {
            setIsStopping(true);
            router.post(`/admin/applications/${application.uuid}/stop`, {}, {
                preserveScroll: true,
                onFinish: () => setIsStopping(false),
            });
        }
    };

    const handleStart = async () => {
        setIsStarting(true);
        router.post(`/admin/applications/${application.uuid}/start`, {}, {
            preserveScroll: true,
            onFinish: () => setIsStarting(false),
        });
    };

    const handleRedeploy = async () => {
        const confirmed = await confirm({
            title: 'Redeploy Application',
            description: `Are you sure you want to redeploy "${application.name}"? This will trigger a new deployment with the latest code.`,
            confirmText: 'Redeploy',
            variant: 'warning',
        });
        if (confirmed) {
            setIsRedeploying(true);
            router.post(`/admin/applications/${application.uuid}/redeploy`, {}, {
                preserveScroll: true,
                onFinish: () => setIsRedeploying(false),
            });
        }
    };

    const handleDelete = async () => {
        const confirmed = await confirm({
            title: 'Delete Application',
            description: `Are you sure you want to delete "${application.name}"? This action cannot be undone and will remove all associated data.`,
            confirmText: 'Delete Application',
            variant: 'danger',
        });
        if (confirmed) {
            router.delete(`/admin/applications/${application.id}`);
        }
    };

    return (
        <AdminLayout
            title={application.name}
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Applications', href: '/admin/applications' },
                { label: application.name },
            ]}
        >
            <div className="mx-auto max-w-7xl">
                {/* Header */}
                <div className="mb-8">
                    <div className="flex items-start justify-between">
                        <div className="flex items-center gap-4">
                            <div className="flex h-16 w-16 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 text-white">
                                <Package className="h-8 w-8" />
                            </div>
                            <div>
                                <div className="flex items-center gap-2">
                                    <h1 className="text-2xl font-semibold text-foreground">{application.name}</h1>
                                    <Badge variant={config.variant} icon={config.icon}>
                                        {config.label}
                                    </Badge>
                                    {application.build_pack && (
                                        <Badge variant="default">{application.build_pack}</Badge>
                                    )}
                                </div>
                                {application.fqdn && (
                                    <a
                                        href={application.fqdn}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="mt-1 flex items-center gap-1 text-sm text-primary hover:underline"
                                    >
                                        <Globe className="h-4 w-4" />
                                        {application.fqdn}
                                        <ExternalLink className="h-3 w-3" />
                                    </a>
                                )}
                                {application.description && (
                                    <p className="mt-1 text-sm text-foreground-subtle">{application.description}</p>
                                )}
                            </div>
                        </div>
                        <div className="flex gap-2">
                            {application.status === 'stopped' || application.status === 'exited' ? (
                                <Button
                                    variant="success"
                                    onClick={handleStart}
                                    disabled={isStarting}
                                >
                                    <Play className={`h-4 w-4 ${isStarting ? 'animate-pulse' : ''}`} />
                                    {isStarting ? 'Starting...' : 'Start'}
                                </Button>
                            ) : (
                                <>
                                    <Button
                                        variant="secondary"
                                        onClick={handleRestart}
                                        disabled={isRestarting}
                                    >
                                        <RefreshCw className={`h-4 w-4 ${isRestarting ? 'animate-spin' : ''}`} />
                                        {isRestarting ? 'Restarting...' : 'Restart'}
                                    </Button>
                                    <Button
                                        variant="warning"
                                        onClick={handleStop}
                                        disabled={isStopping}
                                    >
                                        <Square className="h-4 w-4" />
                                        {isStopping ? 'Stopping...' : 'Stop'}
                                    </Button>
                                </>
                            )}
                            <Button
                                variant="primary"
                                onClick={handleRedeploy}
                                disabled={isRedeploying}
                            >
                                <Rocket className={`h-4 w-4 ${isRedeploying ? 'animate-pulse' : ''}`} />
                                {isRedeploying ? 'Deploying...' : 'Redeploy'}
                            </Button>
                            <Button variant="danger" onClick={handleDelete}>
                                <Trash2 className="h-4 w-4" />
                                Delete
                            </Button>
                        </div>
                    </div>
                </div>

                {/* Stats */}
                <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Status</p>
                                    <div className="flex items-center gap-2">
                                        {config.icon}
                                        <span className={`text-lg font-bold ${config.variant === 'success' ? 'text-success' : config.variant === 'danger' ? 'text-danger' : config.variant === 'warning' ? 'text-warning' : 'text-foreground'}`}>
                                            {config.label}
                                        </span>
                                    </div>
                                </div>
                                <Activity className="h-8 w-8 text-primary/50" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Team</p>
                                    <Link
                                        href={`/admin/teams/${application.team_id}`}
                                        className="text-lg font-bold text-foreground hover:text-primary"
                                    >
                                        {application.team_name}
                                    </Link>
                                </div>
                                <Users className="h-8 w-8 text-warning/50" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Project</p>
                                    <Link
                                        href={`/admin/projects/${application.project_id}`}
                                        className="text-lg font-bold text-foreground hover:text-primary"
                                    >
                                        {application.project_name}
                                    </Link>
                                </div>
                                <FolderKanban className="h-8 w-8 text-success/50" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Server</p>
                                    {application.server_uuid ? (
                                        <Link
                                            href={`/admin/servers/${application.server_uuid}`}
                                            className="text-lg font-bold text-foreground hover:text-primary"
                                        >
                                            {application.server_name || 'Unknown'}
                                        </Link>
                                    ) : (
                                        <span className="text-lg font-bold text-foreground-muted">N/A</span>
                                    )}
                                </div>
                                <Server className="h-8 w-8 text-green-500/50" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Created</p>
                                    <p className="text-lg font-bold text-foreground">
                                        {new Date(application.created_at).toLocaleDateString()}
                                    </p>
                                </div>
                                <Calendar className="h-8 w-8 text-foreground-muted/50" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Application Info */}
                <Card variant="glass" className="mb-6">
                    <CardHeader>
                        <CardTitle>Application Details</CardTitle>
                        <CardDescription>Configuration and source information</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {application.git_repository && (
                                <div className="flex items-center gap-3">
                                    <Box className="h-5 w-5 text-foreground-muted" />
                                    <div>
                                        <p className="text-xs text-foreground-subtle">Repository</p>
                                        <p className="font-medium text-foreground truncate max-w-xs">
                                            {application.git_repository}
                                        </p>
                                    </div>
                                </div>
                            )}
                            {application.git_branch && (
                                <div className="flex items-center gap-3">
                                    <GitBranch className="h-5 w-5 text-foreground-muted" />
                                    <div>
                                        <p className="text-xs text-foreground-subtle">Branch</p>
                                        <p className="font-medium text-foreground">{application.git_branch}</p>
                                    </div>
                                </div>
                            )}
                            {application.git_commit_sha && (
                                <div className="flex items-center gap-3">
                                    <GitBranch className="h-5 w-5 text-foreground-muted" />
                                    <div>
                                        <p className="text-xs text-foreground-subtle">Last Commit</p>
                                        <p className="font-medium font-mono text-foreground">
                                            {application.git_commit_sha.slice(0, 7)}
                                        </p>
                                    </div>
                                </div>
                            )}
                            {application.build_pack && (
                                <div className="flex items-center gap-3">
                                    <Package className="h-5 w-5 text-foreground-muted" />
                                    <div>
                                        <p className="text-xs text-foreground-subtle">Build Pack</p>
                                        <p className="font-medium text-foreground">{application.build_pack}</p>
                                    </div>
                                </div>
                            )}
                            {application.dockerfile_location && (
                                <div className="flex items-center gap-3">
                                    <Box className="h-5 w-5 text-foreground-muted" />
                                    <div>
                                        <p className="text-xs text-foreground-subtle">Dockerfile</p>
                                        <p className="font-medium text-foreground">{application.dockerfile_location}</p>
                                    </div>
                                </div>
                            )}
                            <div className="flex items-center gap-3">
                                <FolderKanban className="h-5 w-5 text-foreground-muted" />
                                <div>
                                    <p className="text-xs text-foreground-subtle">Environment</p>
                                    <p className="font-medium text-foreground">{application.environment_name}</p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Recent Deployments */}
                <Card variant="glass">
                    <CardHeader>
                        <CardTitle>Recent Deployments ({application.recent_deployments?.length || 0})</CardTitle>
                        <CardDescription>Latest deployment history for this application</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {!application.recent_deployments || application.recent_deployments.length === 0 ? (
                            <p className="py-4 text-center text-sm text-foreground-muted">No deployments yet</p>
                        ) : (
                            application.recent_deployments.map((deployment) => (
                                <DeploymentRow key={deployment.id} deployment={deployment} />
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
