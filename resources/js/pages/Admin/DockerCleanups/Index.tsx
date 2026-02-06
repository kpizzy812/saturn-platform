import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { router } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import {
    Server,
    CheckCircle,
    XCircle,
    Trash2,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';

interface DockerCleanup {
    id: number;
    server_id: number;
    server_name: string;
    server_ip: string | null;
    status: string;
    message: string | null;
    created_at: string;
}

interface ServerOption {
    id: number;
    name: string;
}

interface Props {
    cleanups: {
        data: DockerCleanup[];
        current_page: number;
        last_page: number;
        total: number;
    };
    stats: {
        totalLast7d: number;
        failedLast7d: number;
        serversWithCleanup: number;
    };
    servers: ServerOption[];
    filters: {
        server_id?: string;
        status?: string;
    };
}

// Helper component for stats cards
function StatCard({
    label,
    value,
    icon,
    variant = 'default',
}: {
    label: string;
    value: number;
    icon: React.ReactNode;
    variant?: 'default' | 'success' | 'danger' | 'warning';
}) {
    const colorMap = {
        default: 'text-primary',
        success: 'text-success',
        danger: 'text-danger',
        warning: 'text-warning',
    };

    const iconColorMap = {
        default: 'text-primary/50',
        success: 'text-success/50',
        danger: 'text-danger/50',
        warning: 'text-warning/50',
    };

    return (
        <Card variant="glass">
            <CardContent className="p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <p className="text-sm text-foreground-subtle">{label}</p>
                        <p className={`text-2xl font-bold ${colorMap[variant]}`}>{value}</p>
                    </div>
                    <div className={iconColorMap[variant]}>{icon}</div>
                </div>
            </CardContent>
        </Card>
    );
}

// Helper function for relative time formatting
function formatRelativeTime(dateString: string): string {
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now.getTime() - date.getTime()) / 1000);

    if (seconds < 60) return `${seconds}s ago`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
    if (seconds < 2592000) return `${Math.floor(seconds / 86400)}d ago`;
    return date.toLocaleDateString();
}

function CleanupRow({ cleanup }: { cleanup: DockerCleanup }) {
    const statusConfig: Record<string, { variant: 'success' | 'danger'; icon: React.ReactNode }> = {
        success: { variant: 'success', icon: <CheckCircle className="h-3 w-3" /> },
        failed: { variant: 'danger', icon: <XCircle className="h-3 w-3" /> },
    };

    const config = statusConfig[cleanup.status] || { variant: 'success' as const, icon: null };

    return (
        <div className="border-b border-border/50 py-4 last:border-0">
            <div className="flex items-start justify-between">
                <div className="flex-1">
                    <div className="flex items-center gap-3">
                        <Server className="h-5 w-5 text-foreground-muted" />
                        <div className="flex-1">
                            <div className="flex items-center gap-2">
                                <span className="font-medium text-foreground">
                                    {cleanup.server_name}
                                </span>
                                {cleanup.server_ip && (
                                    <span className="text-sm text-foreground-subtle">
                                        ({cleanup.server_ip})
                                    </span>
                                )}
                            </div>
                            <div className="mt-1 flex items-center gap-3">
                                <Badge variant={config.variant} size="sm" icon={config.icon}>
                                    {cleanup.status}
                                </Badge>
                                {cleanup.message && (
                                    <span className="text-xs text-foreground-subtle truncate max-w-[500px]">
                                        {cleanup.message.length > 80
                                            ? `${cleanup.message.substring(0, 80)}...`
                                            : cleanup.message}
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
                <span className="text-xs text-foreground-subtle">
                    {formatRelativeTime(cleanup.created_at)}
                </span>
            </div>
        </div>
    );
}

export default function AdminDockerCleanupsIndex({ cleanups, stats, servers, filters }: Props) {
    const items = cleanups?.data ?? [];
    const currentPage = cleanups?.current_page ?? 1;
    const lastPage = cleanups?.last_page ?? 1;
    const total = cleanups?.total ?? 0;

    const handleFilterChange = (key: string, value: string) => {
        const params = new URLSearchParams();
        const merged = { ...filters, [key]: value };

        Object.entries(merged).forEach(([k, v]) => {
            if (v && v !== 'all') {
                params.set(k, v);
            }
        });

        router.get(`/admin/docker-cleanups?${params.toString()}`, {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handlePageChange = (page: number) => {
        const params = new URLSearchParams();
        Object.entries(filters).forEach(([k, v]) => {
            if (v && v !== 'all') {
                params.set(k, v);
            }
        });
        params.set('page', String(page));

        router.get(`/admin/docker-cleanups?${params.toString()}`, {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout
            title="Docker Cleanups"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Docker Cleanups' },
            ]}
        >
            <div className="mx-auto max-w-7xl">
                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-2xl font-semibold text-foreground">Docker Cleanups</h1>
                    <p className="mt-1 text-sm text-foreground-muted">
                        Docker cleanup execution history
                    </p>
                </div>

                {/* Stats */}
                <div className="mb-6 grid gap-4 sm:grid-cols-3">
                    <StatCard
                        label="Total (7d)"
                        value={stats.totalLast7d}
                        icon={<Trash2 className="h-8 w-8" />}
                        variant="default"
                    />
                    <StatCard
                        label="Failed (7d)"
                        value={stats.failedLast7d}
                        icon={<XCircle className="h-8 w-8" />}
                        variant="danger"
                    />
                    <StatCard
                        label="Servers Active"
                        value={stats.serversWithCleanup}
                        icon={<Server className="h-8 w-8" />}
                        variant="success"
                    />
                </div>

                {/* Filters */}
                <Card variant="glass" className="mb-6">
                    <CardContent className="p-4">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                            <div className="flex-1">
                                <select
                                    value={filters.server_id || ''}
                                    onChange={(e) => handleFilterChange('server_id', e.target.value)}
                                    className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground"
                                >
                                    <option value="">All Servers</option>
                                    {servers.map((server) => (
                                        <option key={server.id} value={String(server.id)}>
                                            {server.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="flex-1">
                                <select
                                    value={filters.status || 'all'}
                                    onChange={(e) => handleFilterChange('status', e.target.value)}
                                    className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground"
                                >
                                    <option value="all">All Statuses</option>
                                    <option value="success">Success</option>
                                    <option value="failed">Failed</option>
                                </select>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Cleanups Table */}
                <Card variant="glass">
                    <CardContent className="p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <p className="text-sm text-foreground-muted">
                                Showing {items.length} of {total} cleanups
                            </p>
                        </div>

                        {items.length === 0 ? (
                            <div className="py-12 text-center">
                                <Trash2 className="mx-auto h-12 w-12 text-foreground-muted" />
                                <p className="mt-4 text-sm text-foreground-muted">No cleanups found</p>
                                <p className="text-xs text-foreground-subtle">
                                    Try adjusting your filters
                                </p>
                            </div>
                        ) : (
                            <>
                                <div>
                                    {items.map((cleanup) => (
                                        <CleanupRow key={cleanup.id} cleanup={cleanup} />
                                    ))}
                                </div>

                                {/* Pagination */}
                                {lastPage > 1 && (
                                    <div className="mt-6 flex items-center justify-between border-t border-border/50 pt-4">
                                        <Button
                                            variant="secondary"
                                            size="sm"
                                            onClick={() => handlePageChange(currentPage - 1)}
                                            disabled={currentPage === 1}
                                        >
                                            <ChevronLeft className="mr-1 h-4 w-4" />
                                            Previous
                                        </Button>
                                        <span className="text-sm text-foreground-muted">
                                            Page {currentPage} of {lastPage}
                                        </span>
                                        <Button
                                            variant="secondary"
                                            size="sm"
                                            onClick={() => handlePageChange(currentPage + 1)}
                                            disabled={currentPage === lastPage}
                                        >
                                            Next
                                            <ChevronRight className="ml-1 h-4 w-4" />
                                        </Button>
                                    </div>
                                )}
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
