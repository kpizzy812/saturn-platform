import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import {
    Users,
    Server,
    Activity,
    AlertTriangle,
    TrendingUp,
    Database,
    Clock,
    CheckCircle,
    XCircle,
} from 'lucide-react';

interface SystemStats {
    totalUsers: number;
    activeUsers: number;
    totalServers: number;
    totalDeployments: number;
    failedDeployments: number;
    totalTeams: number;
    diskUsage: number;
    cpuUsage: number;
}

interface RecentActivity {
    id: number;
    type: 'user_registered' | 'deployment_failed' | 'server_added' | 'team_created';
    message: string;
    timestamp: string;
    user?: string;
}

interface HealthCheck {
    service: string;
    status: 'healthy' | 'degraded' | 'down';
    lastCheck: string;
    responseTime?: number;
}

interface Props {
    stats: SystemStats;
    recentActivity: RecentActivity[];
    healthChecks: HealthCheck[];
}

const defaultStats: SystemStats = {
    totalUsers: 0,
    activeUsers: 0,
    totalServers: 0,
    totalDeployments: 0,
    failedDeployments: 0,
    totalTeams: 0,
    diskUsage: 0,
    cpuUsage: 0,
};

const defaultActivity: RecentActivity[] = [];

const defaultHealthChecks: HealthCheck[] = [];

function StatCard({ title, value, subtitle, icon: Icon, trend }: {
    title: string;
    value: number | string;
    subtitle?: string;
    icon: React.ComponentType<{ className?: string }>;
    trend?: 'up' | 'down';
}) {
    return (
        <Card variant="glass" hover>
            <CardContent className="p-6">
                <div className="flex items-start justify-between">
                    <div>
                        <p className="text-sm text-foreground-muted">{title}</p>
                        <p className="mt-2 text-3xl font-bold text-foreground">{value}</p>
                        {subtitle && (
                            <p className="mt-1 text-xs text-foreground-subtle">{subtitle}</p>
                        )}
                    </div>
                    <div className="rounded-lg bg-primary/10 p-3">
                        <Icon className="h-6 w-6 text-primary" />
                    </div>
                </div>
                {trend && (
                    <div className="mt-4 flex items-center gap-1">
                        <TrendingUp className={`h-4 w-4 ${trend === 'up' ? 'text-success' : 'text-danger'}`} />
                        <span className={`text-xs ${trend === 'up' ? 'text-success' : 'text-danger'}`}>
                            {trend === 'up' ? '+12%' : '-5%'} from last month
                        </span>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function ActivityItem({ activity }: { activity: RecentActivity }) {
    const iconMap = {
        user_registered: <Users className="h-4 w-4 text-success" />,
        deployment_failed: <XCircle className="h-4 w-4 text-danger" />,
        server_added: <Server className="h-4 w-4 text-primary" />,
        team_created: <Users className="h-4 w-4 text-info" />,
    };

    return (
        <div className="flex items-start gap-3 border-b border-border/50 pb-3 last:border-0 last:pb-0">
            <div className="mt-1 rounded-full bg-background-tertiary p-2">
                {iconMap[activity.type]}
            </div>
            <div className="flex-1 min-w-0">
                <p className="text-sm text-foreground">{activity.message}</p>
                {activity.user && (
                    <p className="text-xs text-foreground-muted">{activity.user}</p>
                )}
                <p className="mt-1 text-xs text-foreground-subtle">{activity.timestamp}</p>
            </div>
        </div>
    );
}

function HealthCheckRow({ check }: { check: HealthCheck }) {
    const statusConfig = {
        healthy: { badge: 'success' as const, icon: CheckCircle },
        degraded: { badge: 'warning' as const, icon: AlertTriangle },
        down: { badge: 'danger' as const, icon: XCircle },
    };

    const config = statusConfig[check.status];
    const Icon = config.icon;

    return (
        <div className="flex items-center justify-between border-b border-border/50 py-3 last:border-0">
            <div className="flex items-center gap-3">
                <Icon className={`h-4 w-4 ${
                    check.status === 'healthy' ? 'text-success' :
                    check.status === 'degraded' ? 'text-warning' : 'text-danger'
                }`} />
                <div>
                    <p className="text-sm font-medium text-foreground">{check.service}</p>
                    <p className="text-xs text-foreground-subtle">Last check: {check.lastCheck}</p>
                </div>
            </div>
            <div className="flex items-center gap-3">
                {check.responseTime && (
                    <span className="text-xs text-foreground-muted">{check.responseTime}ms</span>
                )}
                <Badge variant={config.badge} size="sm">
                    {check.status}
                </Badge>
            </div>
        </div>
    );
}

export default function AdminDashboard({
    stats = defaultStats,
    recentActivity = defaultActivity,
    healthChecks = defaultHealthChecks,
}: Props) {
    return (
        <AdminLayout title="Dashboard">
            <div className="mx-auto max-w-7xl px-6 py-8">
                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-2xl font-semibold text-foreground">Admin Dashboard</h1>
                    <p className="mt-1 text-sm text-foreground-muted">
                        Monitor and manage your Saturn Platform cloud instance
                    </p>
                </div>

                {/* Stats Grid */}
                <div className="mb-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="Total Users"
                        value={stats.totalUsers}
                        subtitle={`${stats.activeUsers} active`}
                        icon={Users}
                        trend="up"
                    />
                    <StatCard
                        title="Servers"
                        value={stats.totalServers}
                        subtitle="Connected servers"
                        icon={Server}
                    />
                    <StatCard
                        title="Deployments"
                        value={stats.totalDeployments}
                        subtitle={`${stats.failedDeployments} failed`}
                        icon={Activity}
                    />
                    <StatCard
                        title="Teams"
                        value={stats.totalTeams}
                        subtitle="Active teams"
                        icon={Users}
                    />
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    {/* System Health */}
                    <Card variant="glass">
                        <CardHeader>
                            <CardTitle>System Health</CardTitle>
                            <CardDescription>Real-time service status</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-0">
                                {healthChecks.map((check) => (
                                    <HealthCheckRow key={check.service} check={check} />
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Recent Activity */}
                    <Card variant="glass">
                        <CardHeader>
                            <CardTitle>Recent Activity</CardTitle>
                            <CardDescription>Latest system events</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                {recentActivity.map((activity) => (
                                    <ActivityItem key={activity.id} activity={activity} />
                                ))}
                            </div>
                            <div className="mt-4">
                                <Link
                                    href="/admin/logs"
                                    className="text-sm text-primary hover:underline"
                                >
                                    View all logs →
                                </Link>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Quick Actions */}
                <Card variant="glass" className="mt-6">
                    <CardHeader>
                        <CardTitle>Quick Actions</CardTitle>
                        <CardDescription>Common administrative tasks</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <Link
                                href="/admin/users"
                                className="flex items-center gap-3 rounded-lg border border-border bg-background-secondary p-4 transition-colors hover:bg-background-tertiary"
                            >
                                <Users className="h-5 w-5 text-primary" />
                                <div>
                                    <p className="text-sm font-medium text-foreground">Manage Users</p>
                                    <p className="text-xs text-foreground-muted">View all users</p>
                                </div>
                            </Link>
                            <Link
                                href="/admin/servers"
                                className="flex items-center gap-3 rounded-lg border border-border bg-background-secondary p-4 transition-colors hover:bg-background-tertiary"
                            >
                                <Server className="h-5 w-5 text-primary" />
                                <div>
                                    <p className="text-sm font-medium text-foreground">View Servers</p>
                                    <p className="text-xs text-foreground-muted">All servers</p>
                                </div>
                            </Link>
                            <Link
                                href="/admin/teams"
                                className="flex items-center gap-3 rounded-lg border border-border bg-background-secondary p-4 transition-colors hover:bg-background-tertiary"
                            >
                                <Users className="h-5 w-5 text-primary" />
                                <div>
                                    <p className="text-sm font-medium text-foreground">Manage Teams</p>
                                    <p className="text-xs text-foreground-muted">All teams</p>
                                </div>
                            </Link>
                            <Link
                                href="/admin/settings"
                                className="flex items-center gap-3 rounded-lg border border-border bg-background-secondary p-4 transition-colors hover:bg-background-tertiary"
                            >
                                <Database className="h-5 w-5 text-primary" />
                                <div>
                                    <p className="text-sm font-medium text-foreground">System Settings</p>
                                    <p className="text-xs text-foreground-muted">Configure</p>
                                </div>
                            </Link>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
