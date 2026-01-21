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
    GitBranch,
} from 'lucide-react';

interface Deployment {
    id: number;
    uuid: string;
    application_name: string;
    application_uuid: string;
    status: 'success' | 'failed' | 'in_progress' | 'cancelled';
    user: string;
    team: string;
    commit?: string;
    branch?: string;
    duration?: string;
    started_at: string;
    finished_at?: string;
}

interface Props {
    deployments: Deployment[];
    total: number;
}

const defaultDeployments: Deployment[] = [
    {
        id: 1,
        uuid: 'dep-1234-5678',
        application_name: 'production-api',
        application_uuid: 'app-1234-5678',
        status: 'success',
        user: 'john.doe@example.com',
        team: 'Production Team',
        commit: 'abc123f',
        branch: 'main',
        duration: '2m 34s',
        started_at: '2024-03-10 14:30:00',
        finished_at: '2024-03-10 14:32:34',
    },
    {
        id: 2,
        uuid: 'dep-2345-6789',
        application_name: 'staging-web',
        application_uuid: 'app-2345-6789',
        status: 'in_progress',
        user: 'jane.smith@example.com',
        team: 'Staging Team',
        commit: 'def456a',
        branch: 'develop',
        started_at: '2024-03-10 14:25:00',
    },
    {
        id: 3,
        uuid: 'dep-3456-7890',
        application_name: 'worker-service',
        application_uuid: 'app-3456-7890',
        status: 'failed',
        user: 'bob.wilson@example.com',
        team: 'Dev Team',
        commit: 'ghi789b',
        branch: 'feature/new-worker',
        duration: '1m 12s',
        started_at: '2024-03-10 14:20:00',
        finished_at: '2024-03-10 14:21:12',
    },
    {
        id: 4,
        uuid: 'dep-4567-8901',
        application_name: 'production-api',
        application_uuid: 'app-1234-5678',
        status: 'success',
        user: 'john.doe@example.com',
        team: 'Production Team',
        commit: 'jkl012c',
        branch: 'main',
        duration: '3m 45s',
        started_at: '2024-03-10 13:00:00',
        finished_at: '2024-03-10 13:03:45',
    },
    {
        id: 5,
        uuid: 'dep-5678-9012',
        application_name: 'legacy-app',
        application_uuid: 'app-4567-8901',
        status: 'cancelled',
        user: 'admin@example.com',
        team: 'Infrastructure',
        commit: 'mno345d',
        branch: 'hotfix/urgent',
        duration: '30s',
        started_at: '2024-03-10 12:30:00',
        finished_at: '2024-03-10 12:30:30',
    },
];

function DeploymentRow({ deployment }: { deployment: Deployment }) {
    const statusConfig = {
        success: { variant: 'success' as const, label: 'Success', icon: <CheckCircle className="h-3 w-3" /> },
        failed: { variant: 'danger' as const, label: 'Failed', icon: <XCircle className="h-3 w-3" /> },
        in_progress: { variant: 'warning' as const, label: 'In Progress', icon: <Clock className="h-3 w-3" /> },
        cancelled: { variant: 'default' as const, label: 'Cancelled', icon: <AlertTriangle className="h-3 w-3" /> },
    };

    const config = statusConfig[deployment.status];
    const isInProgress = deployment.status === 'in_progress';

    return (
        <div className={`border-b border-border/50 py-4 last:border-0 ${deployment.status === 'failed' ? 'bg-danger/5' : ''}`}>
            <div className="flex items-start justify-between">
                <div className="flex-1">
                    <div className="flex items-center gap-3">
                        <Rocket className="h-5 w-5 text-foreground-muted" />
                        <div>
                            <div className="flex items-center gap-2">
                                <Link
                                    href={`/admin/applications/${deployment.application_uuid}`}
                                    className="font-medium text-foreground hover:text-primary"
                                >
                                    {deployment.application_name}
                                </Link>
                                <Badge variant={config.variant} size="sm" icon={config.icon}>
                                    {config.label}
                                </Badge>
                                {deployment.branch && (
                                    <Badge variant="default" size="sm" icon={<GitBranch className="h-3 w-3" />}>
                                        {deployment.branch}
                                    </Badge>
                                )}
                            </div>
                            <div className="mt-1 flex items-center gap-3 text-xs text-foreground-subtle">
                                <span>{deployment.user}</span>
                                <span>·</span>
                                <span>{deployment.team}</span>
                                {deployment.commit && (
                                    <>
                                        <span>·</span>
                                        <code className="rounded bg-background-tertiary px-1.5 py-0.5 font-mono">
                                            {deployment.commit}
                                        </code>
                                    </>
                                )}
                            </div>
                            <div className="mt-1 flex items-center gap-3 text-xs text-foreground-subtle">
                                <span>Started: {new Date(deployment.started_at).toLocaleString()}</span>
                                {deployment.finished_at && (
                                    <>
                                        <span>·</span>
                                        <span>Finished: {new Date(deployment.finished_at).toLocaleString()}</span>
                                    </>
                                )}
                                {deployment.duration && (
                                    <>
                                        <span>·</span>
                                        <span>Duration: {deployment.duration}</span>
                                    </>
                                )}
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
                    href={`/admin/deployments/${deployment.uuid}`}
                    className="text-sm text-primary hover:underline"
                >
                    View Logs
                </Link>
            </div>
        </div>
    );
}

export default function AdminDeploymentsIndex({ deployments = defaultDeployments, total = 5 }: Props) {
    const [searchQuery, setSearchQuery] = React.useState('');
    const [statusFilter, setStatusFilter] = React.useState<'all' | 'success' | 'failed' | 'in_progress' | 'cancelled'>('all');

    const filteredDeployments = deployments.filter((deployment) => {
        const matchesSearch =
            deployment.application_name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            deployment.user.toLowerCase().includes(searchQuery.toLowerCase()) ||
            deployment.team.toLowerCase().includes(searchQuery.toLowerCase()) ||
            (deployment.commit && deployment.commit.toLowerCase().includes(searchQuery.toLowerCase())) ||
            (deployment.branch && deployment.branch.toLowerCase().includes(searchQuery.toLowerCase()));
        const matchesStatus = statusFilter === 'all' || deployment.status === statusFilter;
        return matchesSearch && matchesStatus;
    });

    const successCount = deployments.filter((d) => d.status === 'success').length;
    const failedCount = deployments.filter((d) => d.status === 'failed').length;
    const inProgressCount = deployments.filter((d) => d.status === 'in_progress').length;

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
