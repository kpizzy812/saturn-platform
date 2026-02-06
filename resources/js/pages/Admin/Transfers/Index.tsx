import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { router } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import {
    ArrowRightLeft,
    CheckCircle,
    XCircle,
    Clock,
    Info,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';

interface Transfer {
    id: number;
    transfer_type: string;
    transfer_type_label: string;
    status: string;
    status_label: string;
    transferable_type: string | null;
    transferable_id: number | null;
    from_team: { id: number; name: string } | null;
    to_team: { id: number; name: string } | null;
    initiated_by_name: string;
    notes: string | null;
    error_message: string | null;
    created_at: string;
    updated_at: string;
}

interface Props {
    transfers: {
        data: Transfer[];
        current_page: number;
        last_page: number;
        total: number;
    };
    stats: {
        total: number;
        completed: number;
        failed: number;
        last30d: number;
    };
    filters: {
        status?: string;
        type?: string;
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

function TransferRow({ transfer }: { transfer: Transfer }) {
    const statusConfig: Record<string, { variant: 'success' | 'danger' | 'warning' | 'info'; icon: React.ReactNode }> = {
        completed: { variant: 'success', icon: <CheckCircle className="h-3 w-3" /> },
        failed: { variant: 'danger', icon: <XCircle className="h-3 w-3" /> },
        in_progress: { variant: 'warning', icon: <Clock className="h-3 w-3" /> },
        pending: { variant: 'info', icon: <Info className="h-3 w-3" /> },
    };

    const config = statusConfig[transfer.status] || { variant: 'info' as const, icon: null };

    return (
        <div className="border-b border-border/50 py-4 last:border-0">
            <div className="flex items-start justify-between">
                <div className="flex-1">
                    <div className="flex items-center gap-3">
                        <ArrowRightLeft className="h-5 w-5 text-foreground-muted" />
                        <div className="flex-1">
                            <div className="flex items-center gap-2">
                                <Badge variant="default" size="sm">
                                    {transfer.transfer_type_label}
                                </Badge>
                                <Badge variant={config.variant} size="sm" icon={config.icon}>
                                    {transfer.status_label}
                                </Badge>
                            </div>
                            <div className="mt-1 flex flex-col gap-1">
                                {transfer.transferable_type && transfer.transferable_id && (
                                    <span className="text-xs text-foreground-subtle">
                                        Resource: {transfer.transferable_type} #{transfer.transferable_id}
                                    </span>
                                )}
                                <div className="flex items-center gap-2 text-xs text-foreground-subtle">
                                    {transfer.from_team && transfer.to_team ? (
                                        <>
                                            <span>{transfer.from_team.name}</span>
                                            <span>→</span>
                                            <span>{transfer.to_team.name}</span>
                                        </>
                                    ) : transfer.from_team ? (
                                        <span>From: {transfer.from_team.name}</span>
                                    ) : transfer.to_team ? (
                                        <span>To: {transfer.to_team.name}</span>
                                    ) : null}
                                    {transfer.initiated_by_name && (
                                        <>
                                            <span>·</span>
                                            <span>By {transfer.initiated_by_name}</span>
                                        </>
                                    )}
                                </div>
                                {(transfer.notes || transfer.error_message) && (
                                    <span className="text-xs text-foreground-subtle truncate max-w-[600px]">
                                        {((transfer.notes || transfer.error_message || '').length > 80
                                            ? `${(transfer.notes || transfer.error_message)!.substring(0, 80)}...`
                                            : (transfer.notes || transfer.error_message))}
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
                <span className="text-xs text-foreground-subtle">
                    {formatRelativeTime(transfer.created_at)}
                </span>
            </div>
        </div>
    );
}

export default function AdminTransfersIndex({ transfers, stats, filters }: Props) {
    const items = transfers?.data ?? [];
    const currentPage = transfers?.current_page ?? 1;
    const lastPage = transfers?.last_page ?? 1;
    const total = transfers?.total ?? 0;

    const handleFilterChange = (key: string, value: string) => {
        const params = new URLSearchParams();
        const merged = { ...filters, [key]: value };

        Object.entries(merged).forEach(([k, v]) => {
            if (v && v !== 'all') {
                params.set(k, v);
            }
        });

        router.get(`/admin/transfers?${params.toString()}`, {}, {
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

        router.get(`/admin/transfers?${params.toString()}`, {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout
            title="Resource Transfers"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Transfers' },
            ]}
        >
            <div className="mx-auto max-w-7xl">
                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-2xl font-semibold text-foreground">Resource Transfers</h1>
                    <p className="mt-1 text-sm text-foreground-muted">
                        Audit log of resource transfers between teams
                    </p>
                </div>

                {/* Stats */}
                <div className="mb-6 grid gap-4 sm:grid-cols-4">
                    <StatCard
                        label="Total"
                        value={stats.total}
                        icon={<ArrowRightLeft className="h-8 w-8" />}
                        variant="default"
                    />
                    <StatCard
                        label="Completed"
                        value={stats.completed}
                        icon={<CheckCircle className="h-8 w-8" />}
                        variant="success"
                    />
                    <StatCard
                        label="Failed"
                        value={stats.failed}
                        icon={<XCircle className="h-8 w-8" />}
                        variant="danger"
                    />
                    <StatCard
                        label="Last 30 Days"
                        value={stats.last30d}
                        icon={<Clock className="h-8 w-8" />}
                        variant="warning"
                    />
                </div>

                {/* Filters */}
                <Card variant="glass" className="mb-6">
                    <CardContent className="p-4">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                            <div className="flex-1">
                                <select
                                    value={filters.status || 'all'}
                                    onChange={(e) => handleFilterChange('status', e.target.value)}
                                    className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground"
                                >
                                    <option value="all">All Statuses</option>
                                    <option value="pending">Pending</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="completed">Completed</option>
                                    <option value="failed">Failed</option>
                                </select>
                            </div>
                            <div className="flex-1">
                                <select
                                    value={filters.type || 'all'}
                                    onChange={(e) => handleFilterChange('type', e.target.value)}
                                    className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground"
                                >
                                    <option value="all">All Types</option>
                                    <option value="project_transfer">Project Transfer</option>
                                    <option value="team_ownership">Team Ownership</option>
                                    <option value="team_merge">Team Merge</option>
                                    <option value="user_deletion">User Deletion</option>
                                    <option value="archive">Archive</option>
                                </select>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Transfers Table */}
                <Card variant="glass">
                    <CardContent className="p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <p className="text-sm text-foreground-muted">
                                Showing {items.length} of {total} transfers
                            </p>
                        </div>

                        {items.length === 0 ? (
                            <div className="py-12 text-center">
                                <ArrowRightLeft className="mx-auto h-12 w-12 text-foreground-muted" />
                                <p className="mt-4 text-sm text-foreground-muted">No transfers found</p>
                                <p className="text-xs text-foreground-subtle">
                                    Try adjusting your filters
                                </p>
                            </div>
                        ) : (
                            <>
                                <div>
                                    {items.map((transfer) => (
                                        <TransferRow key={transfer.id} transfer={transfer} />
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
