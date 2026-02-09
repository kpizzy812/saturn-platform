import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
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
    ChevronLeft,
    ChevronRight,
    RefreshCw,
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
        current_page: number;
        last_page: number;
        per_page: number;
    };
    stats: {
        successCount: number;
        failedCount: number;
        inProgressCount: number;
    };
    filters: {
        search?: string;
        status?: string;
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
                                        <span>&middot;</span>
                                        <code className="rounded bg-background-tertiary px-1.5 py-0.5 font-mono">
                                            {deployment.commit.substring(0, 7)}
                                        </code>
                                    </>
                                )}
                                {deployment.commit_message && (
                                    <>
                                        <span>&middot;</span>
                                        <span className="truncate max-w-xs">{deployment.commit_message}</span>
                                    </>
                                )}
                            </div>
                            <div className="mt-1 flex items-center gap-3 text-xs text-foreground-subtle">
                                <span>{new Date(deployment.created_at).toLocaleString()}</span>
                                {isInProgress && (
                                    <>
                                        <span>&middot;</span>
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

export default function AdminDeploymentsIndex({ deployments: deploymentsData, stats, filters = {} }: Props) {
    const items = deploymentsData?.data ?? [];
    const total = deploymentsData?.total ?? 0;
    const currentPage = deploymentsData?.current_page ?? 1;
    const lastPage = deploymentsData?.last_page ?? 1;

    const [searchQuery, setSearchQuery] = React.useState(filters.search ?? '');
    const [statusFilter, setStatusFilter] = React.useState(filters.status ?? 'all');
    const [isRefreshing, setIsRefreshing] = React.useState(false);

    // Computed stats - use server-provided stats or fallback to client-side count
    const successCount = stats?.successCount ?? items.filter((d) => d.status === 'finished').length;
    const failedCount = stats?.failedCount ?? items.filter((d) => d.status === 'failed').length;
    const inProgressCount = stats?.inProgressCount ?? items.filter((d) => d.status === 'in_progress' || d.status === 'queued').length;

    const hasActiveDeployments = inProgressCount > 0;

    // Auto-refresh every 10s when there are active deployments
    React.useEffect(() => {
        if (!hasActiveDeployments) return;

        const interval = setInterval(() => {
            router.reload({
                only: ['deployments', 'stats'],
            });
        }, 10000);

        return () => clearInterval(interval);
    }, [hasActiveDeployments]);

    // Debounced server-side search
    React.useEffect(() => {
        const timer = setTimeout(() => {
            if (searchQuery !== (filters.search ?? '')) {
                applyFilters({ search: searchQuery });
            }
        }, 300);
        return () => clearTimeout(timer);
    }, [searchQuery]);

    const applyFilters = (newFilters: Record<string, string | undefined>) => {
        const params = new URLSearchParams();
        const merged = {
            search: filters.search,
            status: filters.status,
            ...newFilters,
        };

        Object.entries(merged).forEach(([key, value]) => {
            if (value && value !== 'all') {
                params.set(key, value);
            }
        });

        router.get(`/admin/deployments?${params.toString()}`, {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleStatusFilter = (status: string) => {
        setStatusFilter(status);
        applyFilters({ status: status === 'all' ? undefined : status });
    };

    const handlePageChange = (page: number) => {
        const params = new URLSearchParams();
        if (filters.search) params.set('search', filters.search);
        if (filters.status && filters.status !== 'all') params.set('status', filters.status);
        params.set('page', page.toString());

        router.get(`/admin/deployments?${params.toString()}`, {}, {
            preserveState: true,
            preserveScroll: false,
        });
    };

    const handleRefresh = () => {
        setIsRefreshing(true);
        router.reload({
            only: ['deployments', 'stats'],
            onFinish: () => setIsRefreshing(false),
        });
    };

    return (
        <AdminLayout
            title="Deployments"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Deployments' },
            ]}
        >
            <div className="mx-auto max-w-7xl">
                {/* Header */}
                <div className="mb-8 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">Deployment Management</h1>
                        <p className="mt-1 text-sm text-foreground-muted">
                            Monitor recent deployments across your Saturn Platform instance
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        {hasActiveDeployments && (
                            <span className="flex items-center gap-1.5 text-xs text-primary">
                                <span className="relative flex h-2 w-2">
                                    <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75" />
                                    <span className="relative inline-flex rounded-full h-2 w-2 bg-primary" />
                                </span>
                                Auto-refresh
                            </span>
                        )}
                        <Button
                            variant="secondary"
                            size="sm"
                            onClick={handleRefresh}
                            disabled={isRefreshing}
                        >
                            <RefreshCw className={`h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`} />
                            Refresh
                        </Button>
                    </div>
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
                                    placeholder="Search deployments by app, team, or commit..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="pl-10"
                                />
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    variant={statusFilter === 'all' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => handleStatusFilter('all')}
                                >
                                    All
                                </Button>
                                <Button
                                    variant={statusFilter === 'finished' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => handleStatusFilter('finished')}
                                >
                                    Success
                                </Button>
                                <Button
                                    variant={statusFilter === 'failed' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => handleStatusFilter('failed')}
                                >
                                    Failed
                                </Button>
                                <Button
                                    variant={statusFilter === 'in_progress' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => handleStatusFilter('in_progress')}
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
                                Showing {items.length} of {total} deployments
                            </p>
                            {lastPage > 1 && (
                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => handlePageChange(currentPage - 1)}
                                        disabled={currentPage === 1}
                                    >
                                        <ChevronLeft className="h-4 w-4" />
                                        Previous
                                    </Button>
                                    <span className="text-sm text-foreground-muted">
                                        Page {currentPage} of {lastPage}
                                    </span>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => handlePageChange(currentPage + 1)}
                                        disabled={currentPage === lastPage}
                                    >
                                        Next
                                        <ChevronRight className="h-4 w-4" />
                                    </Button>
                                </div>
                            )}
                        </div>

                        {items.length === 0 ? (
                            <div className="py-12 text-center">
                                <Rocket className="mx-auto h-12 w-12 text-foreground-muted" />
                                <p className="mt-4 text-sm text-foreground-muted">No deployments found</p>
                                <p className="text-xs text-foreground-subtle">
                                    Try adjusting your search or filters
                                </p>
                            </div>
                        ) : (
                            <div>
                                {items.map((deployment) => (
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
