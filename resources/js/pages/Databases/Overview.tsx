import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Badge } from '@/components/ui';
import {
    ArrowLeft,
    Database,
    Activity,
    HardDrive,
    Users,
    Clock,
    RotateCw,
    Download,
    Trash2,
    TrendingUp,
    Server,
    Zap,
    AlertCircle
} from 'lucide-react';
import { useDatabaseMetrics, formatMetricValue } from '@/hooks';
import type { StandaloneDatabase, DatabaseType } from '@/types';

interface Props {
    database: StandaloneDatabase;
}

const databaseTypeConfig: Record<DatabaseType, { color: string; bgColor: string; displayName: string }> = {
    postgresql: { color: 'text-blue-500', bgColor: 'bg-blue-500/10', displayName: 'PostgreSQL' },
    mysql: { color: 'text-orange-500', bgColor: 'bg-orange-500/10', displayName: 'MySQL' },
    mariadb: { color: 'text-orange-600', bgColor: 'bg-orange-600/10', displayName: 'MariaDB' },
    mongodb: { color: 'text-green-500', bgColor: 'bg-green-500/10', displayName: 'MongoDB' },
    redis: { color: 'text-red-500', bgColor: 'bg-red-500/10', displayName: 'Redis' },
    keydb: { color: 'text-red-600', bgColor: 'bg-red-600/10', displayName: 'KeyDB' },
    dragonfly: { color: 'text-purple-500', bgColor: 'bg-purple-500/10', displayName: 'Dragonfly' },
    clickhouse: { color: 'text-yellow-500', bgColor: 'bg-yellow-500/10', displayName: 'ClickHouse' },
};

export default function DatabaseOverview({ database }: Props) {
    const config = databaseTypeConfig[database.database_type] || databaseTypeConfig.postgresql;
    const [isRestarting, setIsRestarting] = useState(false);

    // Fetch real-time metrics from backend
    const { metrics: dbMetrics, isLoading } = useDatabaseMetrics({
        uuid: database.uuid,
        autoRefresh: true,
        refreshInterval: 30000,
    });

    // Format metrics for display
    const metrics = {
        storageUsed: {
            value: isLoading ? '...' : ((dbMetrics as any)?.databaseSize || 'N/A'),
            total: 'N/A',
            percentage: 0,
        },
        activeConnections: {
            value: isLoading ? 0 : ((dbMetrics as any)?.activeConnections ?? 0),
            max: (dbMetrics as any)?.maxConnections || 100,
            percentage: isLoading ? 0 : Math.round(((dbMetrics as any)?.activeConnections || 0) / ((dbMetrics as any)?.maxConnections || 100) * 100),
        },
        queriesPerSec: {
            value: isLoading ? 0 : ((dbMetrics as any)?.queriesPerSec ?? 0),
            change: 'N/A',
        },
        avgResponseTime: { value: 'N/A', change: 'N/A' },
        uptime: 'N/A',
        lastBackup: 'N/A',
    };

    // Recent queries are not available from metrics - would need separate API
    const recentQueries: { id: number; query: string; duration: string; time: string }[] = [];

    const handleRestart = () => {
        if (confirm(`Are you sure you want to restart ${database.name}? This will cause brief downtime.`)) {
            setIsRestarting(true);
            // Simulate restart
            setTimeout(() => {
                setIsRestarting(false);
            }, 3000);
        }
    };

    const handleBackup = () => {
        router.visit(`/databases/${database.uuid}/backups`);
    };

    return (
        <AppLayout
            title={`${database.name} - Overview`}
            breadcrumbs={[
                { label: 'Databases', href: '/databases' },
                { label: database.name, href: `/databases/${database.uuid}` },
                { label: 'Overview' }
            ]}
        >
            {/* Back Button */}
            <Link
                href={`/databases/${database.uuid}`}
                className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
            >
                <ArrowLeft className="mr-2 h-4 w-4" />
                Back to Database
            </Link>

            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div className="flex items-center gap-4">
                    <div className={`flex h-12 w-12 items-center justify-center rounded-lg ${config.bgColor}`}>
                        <Database className={`h-6 w-6 ${config.color}`} />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">{database.name}</h1>
                        <div className="flex items-center gap-2">
                            <Badge variant="default">{config.displayName}</Badge>
                            <ConnectionStatusBadge status={database.status} />
                        </div>
                    </div>
                </div>
                <div className="flex gap-2">
                    <Button
                        variant="secondary"
                        size="sm"
                        onClick={handleRestart}
                        disabled={isRestarting}
                    >
                        <RotateCw className={`mr-2 h-4 w-4 ${isRestarting ? 'animate-spin' : ''}`} />
                        {isRestarting ? 'Restarting...' : 'Restart'}
                    </Button>
                    <Button variant="secondary" size="sm" onClick={handleBackup}>
                        <Download className="mr-2 h-4 w-4" />
                        Backup
                    </Button>
                </div>
            </div>

            {/* Quick Stats Grid */}
            <div className="mb-6 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    icon={HardDrive}
                    label="Storage"
                    value={metrics.storageUsed.value}
                    subtext={`of ${metrics.storageUsed.total}`}
                    percentage={metrics.storageUsed.percentage}
                    color="blue"
                />
                <StatCard
                    icon={Users}
                    label="Active Connections"
                    value={metrics.activeConnections.value.toString()}
                    subtext={`of ${metrics.activeConnections.max}`}
                    percentage={metrics.activeConnections.percentage}
                    color="green"
                />
                <StatCard
                    icon={Zap}
                    label="Queries/sec"
                    value={metrics.queriesPerSec.value.toLocaleString()}
                    subtext={metrics.queriesPerSec.change}
                    color="purple"
                    trend="up"
                />
                <StatCard
                    icon={Clock}
                    label="Avg Response"
                    value={metrics.avgResponseTime.value}
                    subtext={metrics.avgResponseTime.change}
                    color="yellow"
                    trend="down"
                />
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                {/* Performance Metrics */}
                <Card>
                    <CardContent className="p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-lg font-medium text-foreground">Performance Metrics</h3>
                            <Activity className="h-5 w-5 text-foreground-muted" />
                        </div>
                        <div className="space-y-4">
                            <MetricRow label="Uptime" value={metrics.uptime} />
                            <MetricRow label="Last Backup" value={metrics.lastBackup} />
                            <MetricRow
                                label="Storage Usage"
                                value={`${metrics.storageUsed.percentage}%`}
                                progress={metrics.storageUsed.percentage}
                            />
                            <MetricRow
                                label="Connection Usage"
                                value={`${metrics.activeConnections.percentage}%`}
                                progress={metrics.activeConnections.percentage}
                            />
                        </div>
                        <div className="mt-4 pt-4 border-t border-border">
                            <Link href={`/databases/${database.uuid}/metrics`}>
                                <Button variant="secondary" size="sm" className="w-full">
                                    <TrendingUp className="mr-2 h-4 w-4" />
                                    View Detailed Metrics
                                </Button>
                            </Link>
                        </div>
                    </CardContent>
                </Card>

                {/* Recent Queries */}
                <Card>
                    <CardContent className="p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-lg font-medium text-foreground">Recent Queries</h3>
                            <Server className="h-5 w-5 text-foreground-muted" />
                        </div>
                        <div className="space-y-3">
                            {recentQueries.map((query) => (
                                <div
                                    key={query.id}
                                    className="rounded-lg border border-border bg-background-secondary/50 p-3"
                                >
                                    <div className="mb-2 flex items-center justify-between">
                                        <span className="text-xs font-medium text-green-500">{query.duration}</span>
                                        <span className="text-xs text-foreground-subtle">{query.time}</span>
                                    </div>
                                    <code className="block font-mono text-xs text-foreground-muted line-clamp-1">
                                        {query.query}
                                    </code>
                                </div>
                            ))}
                        </div>
                        <div className="mt-4 pt-4 border-t border-border">
                            <Link href={`/databases/${database.uuid}/query`}>
                                <Button variant="secondary" size="sm" className="w-full">
                                    <Database className="mr-2 h-4 w-4" />
                                    Open Query Browser
                                </Button>
                            </Link>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Quick Actions */}
            <Card className="mt-6">
                <CardContent className="p-6">
                    <h3 className="mb-4 text-lg font-medium text-foreground">Quick Actions</h3>
                    <div className="grid gap-3 md:grid-cols-3">
                        <ActionCard
                            icon={Download}
                            title="Create Backup"
                            description="Backup your database now"
                            href={`/databases/${database.uuid}/backups`}
                        />
                        <ActionCard
                            icon={Users}
                            title="Manage Users"
                            description="Add or remove database users"
                            href={`/databases/${database.uuid}/users`}
                        />
                        <ActionCard
                            icon={Server}
                            title="View Logs"
                            description="Check database logs"
                            href={`/databases/${database.uuid}/logs`}
                        />
                    </div>
                </CardContent>
            </Card>
        </AppLayout>
    );
}

function ConnectionStatusBadge({ status }: { status: string }) {
    const isRunning = status.toLowerCase() === 'running';
    return (
        <div className="flex items-center gap-1.5">
            <div className={`h-2 w-2 rounded-full ${isRunning ? 'bg-green-500' : 'bg-red-500'}`} />
            <span className="text-sm text-foreground-muted">{isRunning ? 'Connected' : 'Disconnected'}</span>
        </div>
    );
}

interface StatCardProps {
    icon: any;
    label: string;
    value: string;
    subtext: string;
    percentage?: number;
    color: 'blue' | 'green' | 'purple' | 'yellow';
    trend?: 'up' | 'down';
}

function StatCard({ icon: Icon, label, value, subtext, percentage, color, trend }: StatCardProps) {
    const colorClasses = {
        blue: { icon: 'text-blue-500', bg: 'bg-blue-500/10', progress: 'bg-blue-500' },
        green: { icon: 'text-green-500', bg: 'bg-green-500/10', progress: 'bg-green-500' },
        purple: { icon: 'text-purple-500', bg: 'bg-purple-500/10', progress: 'bg-purple-500' },
        yellow: { icon: 'text-yellow-500', bg: 'bg-yellow-500/10', progress: 'bg-yellow-500' },
    };

    const colors = colorClasses[color];

    return (
        <Card>
            <CardContent className="p-6">
                <div className="flex items-center gap-3">
                    <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${colors.bg}`}>
                        <Icon className={`h-5 w-5 ${colors.icon}`} />
                    </div>
                    <div className="flex-1">
                        <p className="text-sm text-foreground-muted">{label}</p>
                        <div className="flex items-baseline gap-2">
                            <p className="text-2xl font-bold text-foreground">{value}</p>
                            {trend && (
                                <span className={`text-xs ${trend === 'up' ? 'text-green-500' : 'text-red-500'}`}>
                                    {subtext}
                                </span>
                            )}
                        </div>
                        {!trend && <p className="text-xs text-foreground-muted">{subtext}</p>}
                    </div>
                </div>
                {percentage !== undefined && (
                    <div className="mt-3">
                        <div className="h-1.5 w-full overflow-hidden rounded-full bg-background-tertiary">
                            <div
                                className={`h-full ${colors.progress} transition-all`}
                                style={{ width: `${percentage}%` }}
                            />
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

interface MetricRowProps {
    label: string;
    value: string;
    progress?: number;
}

function MetricRow({ label, value, progress }: MetricRowProps) {
    return (
        <div>
            <div className="flex items-center justify-between mb-1">
                <span className="text-sm text-foreground-muted">{label}</span>
                <span className="text-sm font-medium text-foreground">{value}</span>
            </div>
            {progress !== undefined && (
                <div className="h-1.5 w-full overflow-hidden rounded-full bg-background-tertiary">
                    <div
                        className={`h-full transition-all ${
                            progress > 80 ? 'bg-red-500' : progress > 60 ? 'bg-yellow-500' : 'bg-green-500'
                        }`}
                        style={{ width: `${progress}%` }}
                    />
                </div>
            )}
        </div>
    );
}

interface ActionCardProps {
    icon: any;
    title: string;
    description: string;
    href: string;
}

function ActionCard({ icon: Icon, title, description, href }: ActionCardProps) {
    return (
        <Link href={href}>
            <div className="rounded-lg border border-border bg-background-secondary p-4 transition-colors hover:bg-background-tertiary">
                <Icon className="mb-2 h-5 w-5 text-foreground-muted" />
                <h4 className="mb-1 text-sm font-medium text-foreground">{title}</h4>
                <p className="text-xs text-foreground-muted">{description}</p>
            </div>
        </Link>
    );
}
