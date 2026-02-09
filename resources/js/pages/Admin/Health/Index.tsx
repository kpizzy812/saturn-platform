import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import {
    Activity,
    Server,
    Database,
    Cpu,
    MemoryStick,
    HardDrive,
    RefreshCw,
    CheckCircle,
    XCircle,
    AlertTriangle,
    Clock,
    Layers,
    ListOrdered,
} from 'lucide-react';

interface ServiceHealth {
    service: string;
    status: 'healthy' | 'degraded' | 'down';
    lastCheck: string;
    responseTime?: number;
    details?: string;
}

interface ServerHealth {
    id: number;
    uuid: string;
    name: string;
    ip: string;
    is_reachable: boolean;
    is_usable: boolean;
    metrics?: {
        cpu_usage?: number;
        memory_usage?: number;
        disk_usage?: number;
    };
    resources_count: number;
    last_check?: string;
}

interface QueueHealth {
    pending: number;
    processing: number;
    failed: number;
    workers: number;
}

interface Props {
    services: ServiceHealth[];
    servers: ServerHealth[];
    queues: QueueHealth;
    lastUpdated: string;
}

function ServiceHealthCard({ service }: { service: ServiceHealth }) {
    const statusConfig = {
        healthy: { variant: 'success' as const, icon: <CheckCircle className="h-5 w-5" />, label: 'Healthy', colorClass: 'text-success' },
        degraded: { variant: 'warning' as const, icon: <AlertTriangle className="h-5 w-5" />, label: 'Degraded', colorClass: 'text-warning' },
        down: { variant: 'danger' as const, icon: <XCircle className="h-5 w-5" />, label: 'Down', colorClass: 'text-danger' },
    };

    const config = statusConfig[service.status];

    return (
        <Card variant="glass">
            <CardContent className="p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className={config.colorClass}>
                            {config.icon}
                        </div>
                        <div>
                            <p className="font-medium text-foreground">{service.service}</p>
                            {service.responseTime && (
                                <p className="text-xs text-foreground-subtle">{service.responseTime}ms response</p>
                            )}
                        </div>
                    </div>
                    <Badge variant={config.variant}>{config.label}</Badge>
                </div>
                {service.details && (
                    <p className="mt-2 text-xs text-foreground-subtle">{service.details}</p>
                )}
            </CardContent>
        </Card>
    );
}

function ServerHealthRow({ server }: { server: ServerHealth }) {
    const getMetricColor = (value?: number | null) => {
        if (value == null) return 'text-foreground-muted';
        if (value >= 90) return 'text-danger';
        if (value >= 70) return 'text-warning';
        return 'text-success';
    };

    return (
        <div className="flex items-center justify-between border-b border-border/50 py-4 last:border-0">
            <div className="flex items-center gap-4">
                <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${server.is_reachable ? 'bg-success/10' : 'bg-danger/10'}`}>
                    <Server className={`h-5 w-5 ${server.is_reachable ? 'text-success' : 'text-danger'}`} />
                </div>
                <div>
                    <div className="flex items-center gap-2">
                        <Link
                            href={`/admin/servers/${server.uuid}`}
                            className="font-medium text-foreground hover:text-primary"
                        >
                            {server.name}
                        </Link>
                        <Badge variant={server.is_reachable ? 'success' : 'danger'} size="sm">
                            {server.is_reachable ? 'Online' : 'Offline'}
                        </Badge>
                    </div>
                    <p className="text-xs text-foreground-subtle">
                        {server.ip} · {server.resources_count} resources
                    </p>
                </div>
            </div>
            <div className="flex items-center gap-6">
                {server.metrics ? (
                    <>
                        <div className="text-center">
                            <div className="flex items-center gap-1">
                                <Cpu className="h-4 w-4 text-foreground-muted" />
                                <span className={`text-sm font-medium ${getMetricColor(server.metrics.cpu_usage)}`}>
                                    {server.metrics.cpu_usage != null ? `${server.metrics.cpu_usage.toFixed(1)}%` : 'N/A'}
                                </span>
                            </div>
                            <p className="text-[10px] text-foreground-subtle">CPU</p>
                        </div>
                        <div className="text-center">
                            <div className="flex items-center gap-1">
                                <MemoryStick className="h-4 w-4 text-foreground-muted" />
                                <span className={`text-sm font-medium ${getMetricColor(server.metrics.memory_usage)}`}>
                                    {server.metrics.memory_usage != null ? `${server.metrics.memory_usage.toFixed(1)}%` : 'N/A'}
                                </span>
                            </div>
                            <p className="text-[10px] text-foreground-subtle">Memory</p>
                        </div>
                        <div className="text-center">
                            <div className="flex items-center gap-1">
                                <HardDrive className="h-4 w-4 text-foreground-muted" />
                                <span className={`text-sm font-medium ${getMetricColor(server.metrics.disk_usage)}`}>
                                    {server.metrics.disk_usage != null ? `${server.metrics.disk_usage.toFixed(1)}%` : 'N/A'}
                                </span>
                            </div>
                            <p className="text-[10px] text-foreground-subtle">Disk</p>
                        </div>
                    </>
                ) : (
                    <span className="text-xs text-foreground-muted">Metrics unavailable</span>
                )}
            </div>
        </div>
    );
}

export default function AdminHealthIndex({ services, servers, queues, lastUpdated }: Props) {
    const [isRefreshing, setIsRefreshing] = React.useState(false);
    const [autoRefresh, setAutoRefresh] = React.useState(true);

    // Auto-refresh every 30 seconds
    React.useEffect(() => {
        if (!autoRefresh) return;

        const interval = setInterval(() => {
            router.reload({ only: ['services', 'servers', 'queues', 'lastUpdated'] });
        }, 30000);

        return () => clearInterval(interval);
    }, [autoRefresh]);

    const handleRefresh = () => {
        setIsRefreshing(true);
        router.reload({
            only: ['services', 'servers', 'queues', 'lastUpdated'],
            onFinish: () => setIsRefreshing(false),
        });
    };

    const healthyServices = services?.filter(s => s.status === 'healthy').length || 0;
    const totalServices = services?.length || 0;
    const onlineServers = servers?.filter(s => s.is_reachable).length || 0;
    const totalServers = servers?.length || 0;

    return (
        <AdminLayout
            title="System Health"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Health' },
            ]}
        >
            <div className="mx-auto max-w-7xl">
                {/* Header */}
                <div className="mb-8 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">System Health</h1>
                        <p className="mt-1 text-sm text-foreground-muted">
                            Real-time monitoring of platform services and infrastructure
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="autoRefresh"
                                checked={autoRefresh}
                                onCheckedChange={(checked) => setAutoRefresh(!!checked)}
                            />
                            <label htmlFor="autoRefresh" className="text-sm text-foreground-muted cursor-pointer">
                                Auto-refresh (30s)
                            </label>
                        </div>
                        <Button
                            variant="secondary"
                            onClick={handleRefresh}
                            disabled={isRefreshing}
                        >
                            <RefreshCw className={`h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`} />
                            Refresh
                        </Button>
                    </div>
                </div>

                {/* Overview Stats */}
                <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Services</p>
                                    <p className="text-2xl font-bold text-success">
                                        {healthyServices}/{totalServices}
                                    </p>
                                    <p className="text-xs text-foreground-muted">Healthy</p>
                                </div>
                                <Activity className="h-8 w-8 text-success/50" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Servers</p>
                                    <p className="text-2xl font-bold text-primary">
                                        {onlineServers}/{totalServers}
                                    </p>
                                    <p className="text-xs text-foreground-muted">Online</p>
                                </div>
                                <Server className="h-8 w-8 text-primary/50" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Queue Jobs</p>
                                    <p className="text-2xl font-bold text-warning">{queues?.pending || 0}</p>
                                    <p className="text-xs text-foreground-muted">Pending</p>
                                </div>
                                <ListOrdered className="h-8 w-8 text-warning/50" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Failed Jobs</p>
                                    <p className={`text-2xl font-bold ${(queues?.failed || 0) > 0 ? 'text-danger' : 'text-success'}`}>
                                        {queues?.failed || 0}
                                    </p>
                                    <p className="text-xs text-foreground-muted">
                                        {(queues?.failed || 0) > 0 ? 'Needs attention' : 'All clear'}
                                    </p>
                                </div>
                                <AlertTriangle className={`h-8 w-8 ${(queues?.failed || 0) > 0 ? 'text-danger/50' : 'text-success/50'}`} />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Core Services */}
                <Card variant="glass" className="mb-6">
                    <CardHeader>
                        <CardTitle>Core Services</CardTitle>
                        <CardDescription>Status of essential platform services</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {services?.map((service) => (
                                <ServiceHealthCard key={service.service} service={service} />
                            )) || (
                                <p className="col-span-full py-4 text-center text-sm text-foreground-muted">
                                    No services to display
                                </p>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Queue Status */}
                <Card variant="glass" className="mb-6">
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Queue Status</CardTitle>
                                <CardDescription>Background job processing status</CardDescription>
                            </div>
                            <Link href="/admin/queues">
                                <Button variant="secondary" size="sm">
                                    View Details
                                </Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 sm:grid-cols-4">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-warning/10">
                                    <Clock className="h-5 w-5 text-warning" />
                                </div>
                                <div>
                                    <p className="text-2xl font-bold text-foreground">{queues?.pending || 0}</p>
                                    <p className="text-xs text-foreground-subtle">Pending</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                    <Activity className="h-5 w-5 text-primary" />
                                </div>
                                <div>
                                    <p className="text-2xl font-bold text-foreground">{queues?.processing || 0}</p>
                                    <p className="text-xs text-foreground-subtle">Processing</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-danger/10">
                                    <XCircle className="h-5 w-5 text-danger" />
                                </div>
                                <div>
                                    <p className="text-2xl font-bold text-foreground">{queues?.failed || 0}</p>
                                    <p className="text-xs text-foreground-subtle">Failed</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-success/10">
                                    <Layers className="h-5 w-5 text-success" />
                                </div>
                                <div>
                                    <p className="text-2xl font-bold text-foreground">{queues?.workers || 0}</p>
                                    <p className="text-xs text-foreground-subtle">Workers</p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Server Health */}
                <Card variant="glass">
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Server Health</CardTitle>
                                <CardDescription>Infrastructure status and resource utilization</CardDescription>
                            </div>
                            <Link href="/admin/servers">
                                <Button variant="secondary" size="sm">
                                    View All Servers
                                </Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {servers && servers.length > 0 ? (
                            servers.map((server) => (
                                <ServerHealthRow key={server.id} server={server} />
                            ))
                        ) : (
                            <p className="py-8 text-center text-sm text-foreground-muted">
                                No servers configured
                            </p>
                        )}
                    </CardContent>
                </Card>

                {/* Last Updated */}
                <div className="mt-4 text-center text-xs text-foreground-subtle">
                    Last updated: {lastUpdated ? new Date(lastUpdated).toLocaleString() : 'Never'}
                </div>
            </div>
        </AdminLayout>
    );
}
