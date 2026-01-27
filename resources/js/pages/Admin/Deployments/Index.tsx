import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Link } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import {
    Search,
    Rocket,
    CheckCircle,
    XCircle,
    Clock,
    AlertTriangle,
} from 'lucide-react';

interface Deployment {
    id: number;
    deployment_uuid: string;
    application_id?: number;
    application_name?: string;
    status: string;
    commit?: string;
    commit_message?: string;
    is_webhook?: boolean;
    is_api?: boolean;
    team_id?: number;
    team_name?: string;
    created_at: string;
    updated_at?: string;
}

interface Props {
    deployments: {
        data: Deployment[];
        total: number;
    };
}

function DeploymentRow({ deployment }: { deployment: Deployment }) {
    const statusConfig: Record<string, { variant: 'success' | 'danger' | 'warning' | 'default'; label: string; icon: React.ReactNode }> = {
        finished: { variant: 'success', label: 'Success', icon: <CheckCircle className="h-3 w-3" /> },
        failed: { variant: 'danger', label: 'Failed', icon: <XCircle className="h-3 w-3" /> },
        in_progress: { variant: 'warning', label: 'In Progress', icon: <Clock className="h-3 w-3" /> },
        queued: { variant: 'default', label: 'Queued', icon: <Clock className="h-3 w-3" /> },
        cancelled: { variant: 'default', label: 'Cancelled', icon: <AlertTriangle className="h-3 w-3" /> },
    };

    const config = statusConfig[deployment.status] || { variant: 'default' as const, label: deployment.status || 'Unknown', icon: null };
    const isInProgress = deployment.status === 'in_progress';

    return (
        <div className={`border-b border-border/50 py-4 last:border-0 ${deployment.status === 'failed' ? 'bg-danger/5' : ''}`}>
            <div className="flex items-start justify-between">
                <div className="flex-1">
                    <div className="flex items-center gap-3">
                        <Rocket className="h-5 w-5 text-foreground-muted" />
                        <div>
                            <div className="flex items-center gap-2">
                                <span className="font-medium text-foreground">
                                    {deployment.application_name || `App #${deployment.application_id}`}
                                </span>
                                <Badge variant={config.variant} size="sm" icon={config.icon}>
                                    {config.label}
                                </Badge>
                                {deployment.is_webhook && (
                                    <Badge variant="default" size="sm">Webhook</Badge>
                                )}
                                {deployment.is_api && (
                                    <Badge variant="default" size="sm">API</Badge>
                                )}
                            </div>
                            <div className="mt-1 flex items-center gap-3 text-xs text-foreground-subtle">
                                {deployment.team_name && <span>{deployment.team_name}</span>}
                                {deployment.commit && (
                                    <>
                                        <span>·</span>
                                        <code className="rounded bg-background-tertiary px-1.5 py-0.5 font-mono">
                                            {deployment.commit.substring(0, 7)}
                                        </code>
                                    </>
                                )}
                                {deployment.commit_message && (
                                    <>
                                        <span>·</span>
                                        <span className="truncate max-w-xs">{deployment.commit_message}</span>
                                    </>
                                )}
                            </div>
                            <div className="mt-1 flex items-center gap-3 text-xs text-foreground-subtle">
                                <span>{new Date(deployment.created_at).toLocaleString()}</span>
                                {isInProgress && (
                                    <>
                                        <span>·</span>
                                        <span className="animate-pulse text-warning">Deploying...</span>
                                    </>
                                )}
                            </div>
                        </div>
                    </div>
                </div>

                <Link
                    href={`/admin/deployments/${deployment.deployment_uuid}`}
                    className="text-sm text-primary hover:underline"
                >
                    View Logs
                </Link>
            </div>
        </div>
    );
}

export default function AdminDeploymentsIndex({ deployments: deploymentsData }: Props) {
    const items = deploymentsData?.data ?? [];
    const total = deploymentsData?.total ?? 0;
    const [searchQuery, setSearchQuery] = React.useState('');
    const [statusFilter, setStatusFilter] = React.useState<string>('all');

    const filteredDeployments = items.filter((deployment) => {
        const matchesSearch =
            (deployment.application_name || '').toLowerCase().includes(searchQuery.toLowerCase()) ||
            (deployment.team_name || '').toLowerCase().includes(searchQuery.toLowerCase()) ||
            (deployment.commit || '').toLowerCase().includes(searchQuery.toLowerCase());
        const matchesStatus = statusFilter === 'all' || deployment.status === statusFilter;
        return matchesSearch && matchesStatus;
    });

    const successCount = items.filter((d) => d.status === 'finished').length;
    const failedCount = items.filter((d) => d.status === 'failed').length;
    const inProgressCount = items.filter((d) => d.status === 'in_progress' || d.status === 'queued').length;

    return (
        <AdminLayout
            title="Deployments"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Deployments' },
            ]}
        >
            <div className="mx-auto max-w-7xl px-6 py-8">
                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-2xl font-semibold text-foreground">Deployment Management</h1>
                    <p className="mt-1 text-sm text-foreground-muted">
                        Monitor recent deployments across your Saturn Platform instance
                    </p>
                </div>

                {/* Stats */}
                <div className="mb-6 grid gap-4 sm:grid-cols-3">
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Successful</p>
                                    <p className="text-2xl font-bold text-success">{successCount}</p>
                                </div>
                                <CheckCircle className="h-8 w-8 text-success/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Failed</p>
                                    <p className="text-2xl font-bold text-danger">{failedCount}</p>
                                </div>
                                <XCircle className="h-8 w-8 text-danger/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">In Progress</p>
                                    <p className="text-2xl font-bold text-warning">{inProgressCount}</p>
                                </div>
                                <Clock className="h-8 w-8 text-warning/50" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <Card variant="glass" className="mb-6">
                    <CardContent className="p-4">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="relative flex-1">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                                <Input
                                    placeholder="Search deployments by app, user, team, commit, or branch..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="pl-10"
                                />
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    variant={statusFilter === 'all' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => setStatusFilter('all')}
                                >
                                    All
                                </Button>
                                <Button
                                    variant={statusFilter === 'success' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => setStatusFilter('success')}
                                >
                                    Success
                                </Button>
                                <Button
                                    variant={statusFilter === 'failed' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => setStatusFilter('failed')}
                                >
                                    Failed
                                </Button>
                                <Button
                                    variant={statusFilter === 'in_progress' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => setStatusFilter('in_progress')}
                                >
                                    In Progress
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Deployments List */}
                <Card variant="glass">
                    <CardContent className="p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <p className="text-sm text-foreground-muted">
                                Showing {filteredDeployments.length} of {total} recent deployments
                            </p>
                        </div>

                        {filteredDeployments.length === 0 ? (
                            <div className="py-12 text-center">
                                <Rocket className="mx-auto h-12 w-12 text-foreground-muted" />
                                <p className="mt-4 text-sm text-foreground-muted">No deployments found</p>
                                <p className="text-xs text-foreground-subtle">
                                    Try adjusting your search or filters
                                </p>
                            </div>
                        ) : (
                            <div>
                                {filteredDeployments.map((deployment) => (
                                    <DeploymentRow key={deployment.id} deployment={deployment} />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
