import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { formatRelativeTime } from '@/lib/utils';
import {
    Webhook,
    CheckCircle,
    XCircle,
    Clock,
    Activity,
    Users,
    Zap,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';

interface WebhookDelivery {
    id: number;
    uuid: string;
    event: string;
    status: string; // 'success' | 'failed' | 'pending'
    status_code: number | null;
    response_time_ms: number | null;
    attempts: number;
    webhook_url: string | null;
    team_name: string | null;
    team_id: number | null;
    created_at: string;
}

interface Props {
    deliveries: {
        data: WebhookDelivery[];
        current_page: number;
        last_page: number;
        total: number;
    };
    stats: {
        totalLast24h: number;
        failedLast24h: number;
        avgResponseTime: number;
        successRate: number;
    };
    eventTypes: string[];
    filters: {
        status?: string;
        event?: string;
        team_id?: string;
    };
}

function DeliveryRow({ delivery }: { delivery: WebhookDelivery }) {
    const getStatusVariant = (status: string): 'success' | 'danger' | 'warning' => {
        if (status === 'success') return 'success';
        if (status === 'failed') return 'danger';
        return 'warning';
    };

    const statusIcon = {
        success: <CheckCircle className="h-3 w-3" />,
        failed: <XCircle className="h-3 w-3" />,
        pending: <Clock className="h-3 w-3" />,
    }[delivery.status] || <Clock className="h-3 w-3" />;

    return (
        <div className="border-b border-border/50 py-4 last:border-0">
            <div className="flex items-start justify-between">
                <div className="flex-1">
                    <div className="flex items-center gap-3">
                        <Webhook className="h-5 w-5 text-foreground-muted" />
                        <div>
                            <div className="flex items-center gap-2">
                                <span className="font-medium text-foreground">
                                    {delivery.event}
                                </span>
                                <Badge variant={getStatusVariant(delivery.status)} size="sm" icon={statusIcon}>
                                    {delivery.status.charAt(0).toUpperCase() + delivery.status.slice(1)}
                                </Badge>
                                {delivery.status_code && (
                                    <Badge variant="secondary" size="sm">
                                        {delivery.status_code}
                                    </Badge>
                                )}
                            </div>
                            <div className="mt-1 flex items-center gap-3 text-xs text-foreground-subtle">
                                {delivery.team_name && (
                                    <>
                                        <span className="flex items-center gap-1">
                                            <Users className="h-3 w-3" />
                                            {delivery.team_name}
                                        </span>
                                        <span>·</span>
                                    </>
                                )}
                                {delivery.response_time_ms !== null && (
                                    <>
                                        <span className="flex items-center gap-1">
                                            <Zap className="h-3 w-3" />
                                            {delivery.response_time_ms}ms
                                        </span>
                                        <span>·</span>
                                    </>
                                )}
                                <span className="flex items-center gap-1">
                                    <Activity className="h-3 w-3" />
                                    {delivery.attempts} {delivery.attempts === 1 ? 'attempt' : 'attempts'}
                                </span>
                                <span>·</span>
                                <span className="flex items-center gap-1">
                                    <Clock className="h-3 w-3" />
                                    {formatRelativeTime(delivery.created_at)}
                                </span>
                            </div>
                            {delivery.webhook_url && (
                                <div className="mt-1 text-xs text-foreground-subtle">
                                    <code className="rounded bg-background-tertiary px-1.5 py-0.5 font-mono">
                                        {delivery.webhook_url}
                                    </code>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function AdminWebhookDeliveriesIndex({
    deliveries,
    stats,
    eventTypes,
    filters,
}: Props) {
    const [selectedStatus, setSelectedStatus] = React.useState(filters.status ?? '');
    const [selectedEvent, setSelectedEvent] = React.useState(filters.event ?? '');

    const handleFilterChange = (key: string, value: string) => {
        const params = new URLSearchParams();
        const merged = {
            status: filters.status,
            event: filters.event,
            team_id: filters.team_id,
            [key]: value || undefined,
        };

        Object.entries(merged).forEach(([k, v]) => {
            if (v) {
                params.set(k, v);
            }
        });

        router.get(`/admin/webhook-deliveries?${params.toString()}`, {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleStatusChange = (value: string) => {
        setSelectedStatus(value);
        handleFilterChange('status', value);
    };

    const handleEventChange = (value: string) => {
        setSelectedEvent(value);
        handleFilterChange('event', value);
    };

    const handlePageChange = (page: number) => {
        const params = new URLSearchParams(window.location.search);
        params.set('page', page.toString());
        router.get(`/admin/webhook-deliveries?${params.toString()}`, {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout
            title="Webhook Deliveries"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Webhook Deliveries' },
            ]}
        >
            <div className="mx-auto max-w-7xl">
                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-2xl font-semibold text-foreground">Webhook Deliveries</h1>
                    <p className="mt-1 text-sm text-foreground-muted">
                        Webhook delivery history and debugging
                    </p>
                </div>

                {/* Stats */}
                <div className="mb-6 grid gap-4 sm:grid-cols-4">
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Total (24h)</p>
                                    <p className="text-2xl font-bold text-primary">{stats.totalLast24h}</p>
                                </div>
                                <Webhook className="h-8 w-8 text-primary/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Failed (24h)</p>
                                    <p className="text-2xl font-bold text-danger">{stats.failedLast24h}</p>
                                </div>
                                <XCircle className="h-8 w-8 text-danger/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Avg Response Time (ms)</p>
                                    <p className="text-2xl font-bold text-success">{stats.avgResponseTime}</p>
                                </div>
                                <Zap className="h-8 w-8 text-success/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Success Rate (%)</p>
                                    <p className="text-2xl font-bold text-foreground">{stats.successRate}</p>
                                </div>
                                <CheckCircle className="h-8 w-8 text-foreground-muted/50" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <Card variant="glass" className="mb-6">
                    <CardContent className="p-4">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                            <div className="flex-1">
                                <label className="mb-1 block text-xs font-medium text-foreground-subtle">
                                    Status
                                </label>
                                <select
                                    value={selectedStatus}
                                    onChange={(e) => handleStatusChange(e.target.value)}
                                    className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
                                >
                                    <option value="">All Statuses</option>
                                    <option value="success">Success</option>
                                    <option value="failed">Failed</option>
                                    <option value="pending">Pending</option>
                                </select>
                            </div>
                            <div className="flex-1">
                                <label className="mb-1 block text-xs font-medium text-foreground-subtle">
                                    Event Type
                                </label>
                                <select
                                    value={selectedEvent}
                                    onChange={(e) => handleEventChange(e.target.value)}
                                    className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
                                >
                                    <option value="">All Events</option>
                                    {eventTypes.map((event) => (
                                        <option key={event} value={event}>
                                            {event}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Deliveries Table */}
                <Card variant="glass">
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Delivery History</CardTitle>
                                <CardDescription>
                                    Showing {deliveries.data.length} of {deliveries.total} deliveries
                                </CardDescription>
                            </div>
                            {deliveries.last_page > 1 && (
                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        disabled={deliveries.current_page === 1}
                                        onClick={() => handlePageChange(deliveries.current_page - 1)}
                                    >
                                        <ChevronLeft className="h-4 w-4" />
                                        Previous
                                    </Button>
                                    <span className="text-sm text-foreground-muted">
                                        Page {deliveries.current_page} of {deliveries.last_page}
                                    </span>
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        disabled={deliveries.current_page === deliveries.last_page}
                                        onClick={() => handlePageChange(deliveries.current_page + 1)}
                                    >
                                        Next
                                        <ChevronRight className="h-4 w-4" />
                                    </Button>
                                </div>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        {deliveries.data.length === 0 ? (
                            <div className="py-12 text-center">
                                <Webhook className="mx-auto h-12 w-12 text-foreground-muted" />
                                <p className="mt-4 text-sm text-foreground-muted">No webhook deliveries found</p>
                                <p className="text-xs text-foreground-subtle">
                                    {filters.status || filters.event
                                        ? 'Try adjusting your filters'
                                        : 'Webhook deliveries will appear here'}
                                </p>
                            </div>
                        ) : (
                            <div>
                                {deliveries.data.map((delivery) => (
                                    <DeliveryRow key={delivery.id} delivery={delivery} />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
