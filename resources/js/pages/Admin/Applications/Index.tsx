import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import { useRealtimeStatus } from '@/hooks/useRealtimeStatus';
import {
    Search,
    Package,
    AlertTriangle,
    CheckCircle,
    XCircle,
    Clock,
    Activity,
    Loader2,
} from 'lucide-react';

interface Application {
    id: number;
    name: string;
    uuid: string;
    description?: string;
    status: string;
    git_repository?: string;
    git_branch?: string;
    build_pack?: string;
    team_id?: number;
    team_name?: string;
    environment_id?: number;
    environment_name?: string;
    project_name?: string;
    created_at: string;
    updated_at?: string;
}

interface Props {
    applications: {
        data: Application[];
        total: number;
        current_page: number;
        last_page: number;
        per_page: number;
    };
    stats: {
        runningCount: number;
        deployingCount: number;
        errorCount: number;
    };
    filters: {
        search?: string;
        status?: string;
    };
}

function ApplicationRow({ app }: { app: Application }) {
    const statusConfig: Record<string, { variant: 'success' | 'default' | 'warning' | 'danger'; label: string; icon: React.ReactNode }> = {
        running: { variant: 'success', label: 'Running', icon: <CheckCircle className="h-3 w-3" /> },
        stopped: { variant: 'default', label: 'Stopped', icon: <XCircle className="h-3 w-3" /> },
        deploying: { variant: 'warning', label: 'Deploying', icon: <Clock className="h-3 w-3" /> },
        error: { variant: 'danger', label: 'Error', icon: <AlertTriangle className="h-3 w-3" /> },
        exited: { variant: 'danger', label: 'Exited', icon: <XCircle className="h-3 w-3" /> },
    };

    const config = statusConfig[app.status] || { variant: 'default' as const, label: app.status || 'Unknown', icon: null };

    return (
        <div className="border-b border-border/50 py-4 last:border-0">
            <div className="flex items-start justify-between">
                <div className="flex-1">
                    <div className="flex items-center gap-3">
                        <Package className="h-5 w-5 text-foreground-muted" />
                        <div>
                            <div className="flex items-center gap-2">
                                <Link
                                    href={`/admin/applications/${app.uuid}`}
                                    className="font-medium text-foreground hover:text-primary"
                                >
                                    {app.name}
                                </Link>
                                <Badge variant={config.variant} size="sm" icon={config.icon}>
                                    {config.label}
                                </Badge>
                                {app.build_pack && (
                                    <Badge variant="default" size="sm">
                                        {app.build_pack}
                                    </Badge>
                                )}
                            </div>
                            <div className="mt-1 flex items-center gap-3 text-xs text-foreground-subtle">
                                {app.team_name && <span>{app.team_name}</span>}
                                {app.project_name && (
                                    <>
                                        <span>·</span>
                                        <span>{app.project_name}</span>
                                    </>
                                )}
                                {app.git_repository && (
                                    <>
                                        <span>·</span>
                                        <span className="truncate max-w-xs">{app.git_repository}</span>
                                    </>
                                )}
                                {app.git_branch && (
                                    <>
                                        <span>·</span>
                                        <span>{app.git_branch}</span>
                                    </>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function AdminApplicationsIndex({ applications: appsData, stats, filters = {} }: Props) {
    const items = appsData?.data ?? [];
    const total = appsData?.total ?? 0;
    const currentPage = appsData?.current_page ?? 1;
    const lastPage = appsData?.last_page ?? 1;

    const [searchQuery, setSearchQuery] = React.useState(filters.search ?? '');
    const [statusFilter, setStatusFilter] = React.useState<string>(filters.status ?? 'all');
    const [isFiltering, setIsFiltering] = React.useState(false);

    const runningCount = stats?.runningCount ?? 0;
    const deployingCount = stats?.deployingCount ?? 0;
    const errorCount = stats?.errorCount ?? 0;

    // Real-time status updates via WebSocket
    useRealtimeStatus({
        onApplicationStatusChange: () => {
            router.reload({ only: ['applications', 'stats'] });
        },
    });

    // Server-side search with debounce
    const searchTimerRef = React.useRef<ReturnType<typeof setTimeout>>(null);
    React.useEffect(() => {
        return () => {
            if (searchTimerRef.current) clearTimeout(searchTimerRef.current);
        };
    }, []);

    const applyFilters = React.useCallback((search: string, status: string) => {
        setIsFiltering(true);
        const params: Record<string, string> = {};
        if (search) params.search = search;
        if (status && status !== 'all') params.status = status;

        router.get('/admin/applications', params, {
            preserveState: true,
            preserveScroll: true,
            only: ['applications', 'stats', 'filters'],
            onFinish: () => setIsFiltering(false),
        });
    }, []);

    const handleSearchChange = (value: string) => {
        setSearchQuery(value);
        if (searchTimerRef.current) clearTimeout(searchTimerRef.current);
        searchTimerRef.current = setTimeout(() => {
            applyFilters(value, statusFilter);
        }, 300);
    };

    const handleStatusChange = (status: string) => {
        setStatusFilter(status);
        applyFilters(searchQuery, status);
    };

    const handlePageChange = (page: number) => {
        setIsFiltering(true);
        const params: Record<string, string> = { page: String(page) };
        if (searchQuery) params.search = searchQuery;
        if (statusFilter && statusFilter !== 'all') params.status = statusFilter;

        router.get('/admin/applications', params, {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => setIsFiltering(false),
        });
    };

    return (
        <AdminLayout
            title="Applications"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Applications' },
            ]}
        >
            <div className="mx-auto max-w-7xl">
                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-2xl font-semibold text-foreground">Application Management</h1>
                    <p className="mt-1 text-sm text-foreground-muted">
                        Monitor all applications across your Saturn Platform instance
                    </p>
                </div>

                {/* Stats */}
                <div className="mb-6 grid gap-4 sm:grid-cols-3">
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Running</p>
                                    <p className="text-2xl font-bold text-success">{runningCount}</p>
                                </div>
                                <Activity className="h-8 w-8 text-success/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Deploying</p>
                                    <p className="text-2xl font-bold text-warning">{deployingCount}</p>
                                </div>
                                <Clock className="h-8 w-8 text-warning/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Errors</p>
                                    <p className="text-2xl font-bold text-danger">{errorCount}</p>
                                </div>
                                <AlertTriangle className="h-8 w-8 text-danger/50" />
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
                                    placeholder="Search applications by name, domain, or team..."
                                    value={searchQuery}
                                    onChange={(e) => handleSearchChange(e.target.value)}
                                    className="pl-10"
                                />
                            </div>
                            <div className="flex items-center gap-2">
                                {isFiltering && <Loader2 className="h-4 w-4 animate-spin text-foreground-muted" />}
                                <Button
                                    variant={statusFilter === 'all' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => handleStatusChange('all')}
                                >
                                    All
                                </Button>
                                <Button
                                    variant={statusFilter === 'running' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => handleStatusChange('running')}
                                >
                                    Running
                                </Button>
                                <Button
                                    variant={statusFilter === 'deploying' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => handleStatusChange('deploying')}
                                >
                                    Deploying
                                </Button>
                                <Button
                                    variant={statusFilter === 'error' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => handleStatusChange('error')}
                                >
                                    Errors
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Applications List */}
                <Card variant="glass">
                    <CardContent className="p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <p className="text-sm text-foreground-muted">
                                Showing {items.length} of {total} applications
                                {currentPage > 1 && ` (page ${currentPage})`}
                            </p>
                        </div>

                        {items.length === 0 ? (
                            <div className="py-12 text-center">
                                <Package className="mx-auto h-12 w-12 text-foreground-muted" />
                                <p className="mt-4 text-sm text-foreground-muted">No applications found</p>
                                <p className="text-xs text-foreground-subtle">
                                    Try adjusting your search or filters
                                </p>
                            </div>
                        ) : (
                            <div>
                                {items.map((app) => (
                                    <ApplicationRow key={app.id} app={app} />
                                ))}
                            </div>
                        )}

                        {/* Pagination */}
                        {lastPage > 1 && (
                            <div className="mt-6 flex items-center justify-center gap-2">
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    disabled={currentPage <= 1 || isFiltering}
                                    onClick={() => handlePageChange(currentPage - 1)}
                                >
                                    Previous
                                </Button>
                                <span className="text-sm text-foreground-muted">
                                    Page {currentPage} of {lastPage}
                                </span>
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    disabled={currentPage >= lastPage || isFiltering}
                                    onClick={() => handlePageChange(currentPage + 1)}
                                >
                                    Next
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
