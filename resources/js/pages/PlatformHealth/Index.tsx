import * as React from 'react';
import { router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import {
    Server,
    AlertTriangle,
    Rocket,
    RefreshCw,
    CheckCircle,
    Box,
    Database,
    Layers,
} from 'lucide-react';

interface Summary {
    totalServers: number;
    healthyServers: number;
    degradedServers: number;
    downServers: number;
    totalApps: number;
    activeDeployments: number;
}

interface ServerData {
    id: number;
    name: string;
    ip: string;
    status: string;
    cpu: number | null;
    memory: number | null;
    disk: number | null;
    uptime: number | null;
    checkedAt: string | null;
}

interface Resource {
    id: number;
    uuid: string;
    name: string;
    type: string;
    status: string;
}

interface AlertData {
    id: number;
    alertName: string;
    value: number | null;
    triggeredAt: string;
}

interface DeploymentData {
    id: number;
    uuid: string;
    appName: string;
    serverName: string;
    status: string;
    commit: string | null;
    createdAt: string;
}

interface Props {
    summary?: Summary;
    servers?: ServerData[];
    resources?: Resource[];
    activeAlerts?: AlertData[];
    recentDeployments?: DeploymentData[];
}

const defaultSummary: Summary = {
    totalServers: 0,
    healthyServers: 0,
    degradedServers: 0,
    downServers: 0,
    totalApps: 0,
    activeDeployments: 0,
};

function statusBadgeVariant(status: string): 'success' | 'warning' | 'destructive' | 'secondary' | 'default' {
    switch (status) {
        case 'healthy':
        case 'running':
        case 'finished':
            return 'success';
        case 'degraded':
            return 'warning';
        case 'down':
        case 'unreachable':
        case 'failed':
        case 'exited':
            return 'destructive';
        case 'in_progress':
        case 'queued':
            return 'secondary';
        default:
            return 'default';
    }
}

function UsageBar({ value, label }: { value: number | null; label: string }) {
    if (value === null) return <span className="text-xs text-foreground-muted">N/A</span>;

    const color =
        value > 90 ? 'bg-red-500' : value > 70 ? 'bg-yellow-500' : 'bg-emerald-500';

    return (
        <div className="flex items-center gap-2">
            <span className="w-12 text-xs text-foreground-muted">{label}</span>
            <div className="h-2 flex-1 rounded-full bg-white/[0.06]">
                <div
                    className={`h-2 rounded-full ${color} transition-all`}
                    style={{ width: `${Math.min(value, 100)}%` }}
                />
            </div>
            <span className="w-10 text-right text-xs text-foreground-muted">{value.toFixed(0)}%</span>
        </div>
    );
}

function formatUptime(seconds: number | null): string {
    if (!seconds) return 'N/A';
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    if (days > 0) return `${days}d ${hours}h`;
    const minutes = Math.floor((seconds % 3600) / 60);
    return `${hours}h ${minutes}m`;
}

export default function PlatformHealth({
    summary = defaultSummary,
    servers = [],
    resources = [],
    activeAlerts = [],
    recentDeployments = [],
}: Props) {
    const [isRefreshing, setIsRefreshing] = React.useState(false);

    // Auto-refresh every 30s
    React.useEffect(() => {
        const interval = setInterval(() => {
            router.reload({ only: ['summary', 'servers', 'resources', 'activeAlerts', 'recentDeployments'] });
        }, 30000);
        return () => clearInterval(interval);
    }, []);

    const handleRefresh = React.useCallback(() => {
        setIsRefreshing(true);
        router.reload({
            onFinish: () => setIsRefreshing(false),
        });
    }, []);

    return (
        <AppLayout title="Platform Health" breadcrumbs={[{ label: 'Platform Health' }]}>
            <div className="mx-auto max-w-7xl space-y-6 p-6">
                {/* Page Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Platform Health</h1>
                        <p className="mt-1 text-sm text-foreground-muted">
                            Unified view of your infrastructure, resources, alerts, and deployments
                        </p>
                    </div>
                    <button
                        onClick={handleRefresh}
                        disabled={isRefreshing}
                        className="flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm text-foreground-muted transition-all hover:bg-background-secondary hover:text-foreground disabled:opacity-50"
                    >
                        <RefreshCw className={`h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`} />
                        Refresh
                    </button>
                </div>

                {/* Section 1: Summary Cards */}
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <Card variant="glass">
                        <CardContent className="flex items-center gap-4 !p-4">
                            <div className="rounded-lg bg-emerald-500/10 p-3">
                                <Server className="h-5 w-5 text-emerald-400" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold text-foreground">{summary.healthyServers}/{summary.totalServers}</p>
                                <p className="text-xs text-foreground-muted">Servers Healthy</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="flex items-center gap-4 !p-4">
                            <div className="rounded-lg bg-blue-500/10 p-3">
                                <Box className="h-5 w-5 text-blue-400" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold text-foreground">{summary.totalApps}</p>
                                <p className="text-xs text-foreground-muted">Total Resources</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="flex items-center gap-4 !p-4">
                            <div className="rounded-lg bg-yellow-500/10 p-3">
                                <AlertTriangle className="h-5 w-5 text-yellow-400" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold text-foreground">{activeAlerts.length}</p>
                                <p className="text-xs text-foreground-muted">Active Alerts</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="flex items-center gap-4 !p-4">
                            <div className="rounded-lg bg-purple-500/10 p-3">
                                <Rocket className="h-5 w-5 text-purple-400" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold text-foreground">{summary.activeDeployments}</p>
                                <p className="text-xs text-foreground-muted">Active Deploys</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Section 2: Servers Grid */}
                <Card variant="glass">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Server className="h-5 w-5" />
                            Servers
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {servers.length === 0 ? (
                            <p className="text-sm text-foreground-muted">No servers connected</p>
                        ) : (
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                {servers.map((server) => (
                                    <div
                                        key={server.id}
                                        className="space-y-3 rounded-lg border border-white/[0.06] bg-white/[0.02] p-4"
                                    >
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <div className={`h-2 w-2 rounded-full ${
                                                    server.status === 'healthy' ? 'bg-emerald-400' :
                                                    server.status === 'degraded' ? 'bg-yellow-400' : 'bg-red-400'
                                                }`} />
                                                <span className="font-medium text-foreground">{server.name}</span>
                                            </div>
                                            <span className="text-xs text-foreground-muted">{server.ip}</span>
                                        </div>
                                        <div className="space-y-1.5">
                                            <UsageBar value={server.cpu} label="CPU" />
                                            <UsageBar value={server.memory} label="RAM" />
                                            <UsageBar value={server.disk} label="Disk" />
                                        </div>
                                        <div className="flex items-center justify-between text-xs text-foreground-muted">
                                            <span>Uptime: {formatUptime(server.uptime)}</span>
                                            {server.checkedAt && <span>{server.checkedAt}</span>}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Section 3: Resources Table + Section 4: Active Alerts (side by side on large screens) */}
                <div className="grid gap-6 xl:grid-cols-3">
                    {/* Resources Table (2/3 width) */}
                    <Card variant="glass" className="xl:col-span-2">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Layers className="h-5 w-5" />
                                Resources ({resources.length})
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {resources.length === 0 ? (
                                <p className="text-sm text-foreground-muted">No resources deployed</p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full">
                                        <thead>
                                            <tr className="border-b border-white/[0.06] text-left text-xs text-foreground-muted">
                                                <th className="pb-2 pr-4">Name</th>
                                                <th className="pb-2 pr-4">Type</th>
                                                <th className="pb-2">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody className="text-sm">
                                            {resources.slice(0, 15).map((resource) => (
                                                <tr key={`${resource.type}-${resource.id}`} className="border-b border-white/[0.04]">
                                                    <td className="py-2 pr-4 font-medium text-foreground">
                                                        <div className="flex items-center gap-2">
                                                            {resource.type === 'Application' && <Box className="h-3.5 w-3.5 text-blue-400" />}
                                                            {resource.type === 'Service' && <Layers className="h-3.5 w-3.5 text-purple-400" />}
                                                            {resource.type === 'Database' && <Database className="h-3.5 w-3.5 text-orange-400" />}
                                                            {resource.name}
                                                        </div>
                                                    </td>
                                                    <td className="py-2 pr-4 text-foreground-muted">{resource.type}</td>
                                                    <td className="py-2">
                                                        <Badge variant={statusBadgeVariant(resource.status)}>
                                                            {resource.status}
                                                        </Badge>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                    {resources.length > 15 && (
                                        <p className="mt-2 text-xs text-foreground-muted">
                                            +{resources.length - 15} more resources
                                        </p>
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Active Alerts (1/3 width) */}
                    <Card variant="glass">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <AlertTriangle className="h-5 w-5" />
                                Active Alerts
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {activeAlerts.length === 0 ? (
                                <div className="flex flex-col items-center gap-2 py-6 text-center">
                                    <CheckCircle className="h-8 w-8 text-emerald-400" />
                                    <p className="text-sm text-foreground-muted">No active alerts</p>
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {activeAlerts.map((alert) => (
                                        <div
                                            key={alert.id}
                                            className="flex items-start gap-3 rounded-lg border border-yellow-500/20 bg-yellow-500/5 p-3"
                                        >
                                            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-yellow-400" />
                                            <div className="min-w-0">
                                                <p className="text-sm font-medium text-foreground">{alert.alertName}</p>
                                                <p className="text-xs text-foreground-muted">{alert.triggeredAt}</p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Section 5: Recent Deployments */}
                <Card variant="glass">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Rocket className="h-5 w-5" />
                            Recent Deployments
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {recentDeployments.length === 0 ? (
                            <p className="text-sm text-foreground-muted">No recent deployments</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b border-white/[0.06] text-left text-xs text-foreground-muted">
                                            <th className="pb-2 pr-4">Application</th>
                                            <th className="pb-2 pr-4">Server</th>
                                            <th className="pb-2 pr-4">Status</th>
                                            <th className="pb-2 pr-4">Commit</th>
                                            <th className="pb-2">Time</th>
                                        </tr>
                                    </thead>
                                    <tbody className="text-sm">
                                        {recentDeployments.map((deploy) => (
                                            <tr key={deploy.id} className="border-b border-white/[0.04]">
                                                <td className="py-2 pr-4 font-medium text-foreground">{deploy.appName}</td>
                                                <td className="py-2 pr-4 text-foreground-muted">{deploy.serverName}</td>
                                                <td className="py-2 pr-4">
                                                    <Badge variant={statusBadgeVariant(deploy.status)}>
                                                        {deploy.status}
                                                    </Badge>
                                                </td>
                                                <td className="py-2 pr-4">
                                                    {deploy.commit ? (
                                                        <code className="rounded bg-white/[0.06] px-1.5 py-0.5 text-xs">{deploy.commit}</code>
                                                    ) : (
                                                        <span className="text-foreground-muted">-</span>
                                                    )}
                                                </td>
                                                <td className="py-2 text-foreground-muted">{deploy.createdAt}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
