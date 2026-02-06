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
    CheckCircle,
    XCircle,
    GitCommit,
    Webhook,
    Code2,
    ExternalLink,
} from 'lucide-react';

interface SystemStats {
    totalUsers: number;
    activeUsers: number;
    totalServers: number;
    totalDeployments: number;
    failedDeployments: number;
    totalTeams: number;
    totalApplications: number;
    totalServices: number;
    totalDatabases: number;
    deploymentSuccessRate24h: number;
    deploymentSuccessRate7d: number;
    queuePending: number;
    queueFailed: number;
    diskUsage: number;
    cpuUsage: number;
}

interface RecentActivity {
    id: number;
    deployment_uuid?: string;
    action: string | null;
    status?: string;
    description: string | null;
    commit?: string | null;
    user_name: string | null;
    user_email?: string | null;
    team_name: string | null;
    resource_type: string | null;
    resource_name: string | null;
    application_uuid?: string;
    triggered_by?: string;
    is_webhook?: boolean;
    is_api?: boolean;
    created_at: string;
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
    totalApplications: 0,
    totalServices: 0,
    totalDatabases: 0,
    deploymentSuccessRate24h: 100,
    deploymentSuccessRate7d: 100,
    queuePending: 0,
    queueFailed: 0,
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

function getActionIcon(action: string | null | undefined) {
    if (!action) return <Activity className="h-4 w-4 text-foreground-muted" />;
    const actionLower = action.toLowerCase();
    if (actionLower.includes('completed') || actionLower.includes('finished')) {
        return <CheckCircle className="h-4 w-4 text-success" />;
    }
    if (actionLower.includes('failed') || actionLower.includes('cancelled')) {
        return <XCircle className="h-4 w-4 text-danger" />;
    }
    if (actionLower.includes('started') || actionLower.includes('progress') || actionLower.includes('queued')) {
        return <Activity className="h-4 w-4 text-primary" />;
    }
    if (actionLower.includes('deploy') || actionLower.includes('rollback')) {
        return <Activity className="h-4 w-4 text-primary" />;
    }
    if (actionLower.includes('delete')) {
        return <XCircle className="h-4 w-4 text-danger" />;
    }
    if (actionLower.includes('create') || actionLower.includes('add')) {
        return <CheckCircle className="h-4 w-4 text-success" />;
    }
    if (actionLower.includes('server')) {
        return <Server className="h-4 w-4 text-primary" />;
    }
    if (actionLower.includes('user') || actionLower.includes('team')) {
        return <Users className="h-4 w-4 text-info" />;
    }
    return <Activity className="h-4 w-4 text-foreground-muted" />;
}

function formatDescription(description: string | null): string {
    if (!description) return 'No description';

    // Check if description is JSON array or object
    if (description.startsWith('[') || description.startsWith('{')) {
        try {
            const parsed = JSON.parse(description);
            // If it's an array of log entries, extract meaningful output
            if (Array.isArray(parsed)) {
                const outputs = parsed
                    .filter((item: { type?: string; output?: string }) => item.output)
                    .map((item: { type?: string; output?: string }) => item.output)
                    .slice(0, 2);
                if (outputs.length > 0) {
                    const summary = outputs.join(' ').slice(0, 100);
                    return summary + (summary.length >= 100 ? '...' : '');
                }
                return 'Activity logged';
            }
            // If it's an object with message/output
            if (parsed.message) return parsed.message;
            if (parsed.output) return parsed.output;
            return 'Activity logged';
        } catch {
            // Not valid JSON, return truncated original
            return description.slice(0, 100) + (description.length > 100 ? '...' : '');
        }
    }

    return description.slice(0, 150) + (description.length > 150 ? '...' : '');
}

function formatRelativeTime(dateString: string | null | undefined): string {
    if (!dateString) return 'Unknown';
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`;
    if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
    if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
    return date.toLocaleDateString();
}

function getStatusBadgeVariant(status: string | undefined): 'success' | 'danger' | 'warning' | 'info' | 'default' {
    switch (status) {
        case 'finished': return 'success';
        case 'failed': case 'cancelled': return 'danger';
        case 'in_progress': return 'warning';
        case 'queued': return 'info';
        default: return 'default';
    }
}

function ActivityItem({ activity }: { activity: RecentActivity }) {
    const deploymentUrl = activity.deployment_uuid ? `/deployments/${activity.deployment_uuid}` : null;

    const content = (
        <div className={`flex items-start gap-3 border-b border-border/50 pb-3 last:border-0 last:pb-0 rounded-lg p-2 -m-2 ${deploymentUrl ? 'hover:bg-background-tertiary/50 cursor-pointer transition-colors' : ''}`}>
            <div className="mt-1 rounded-full bg-background-tertiary p-2">
                {getActionIcon(activity.action)}
            </div>
            <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 flex-wrap">
                    <span className="text-sm font-medium text-foreground capitalize">
                        {(activity.action || 'unknown').replace(/_/g, ' ')}
                    </span>
                    {activity.status && (
                        <Badge variant={getStatusBadgeVariant(activity.status)} size="sm">
                            {activity.status}
                        </Badge>
                    )}
                    {activity.commit && (
                        <span className="flex items-center gap-1 text-xs text-foreground-muted font-mono bg-background-tertiary px-1.5 py-0.5 rounded">
                            <GitCommit className="h-3 w-3" />
                            {activity.commit}
                        </span>
                    )}
                    {activity.is_webhook && (
                        <span className="flex items-center gap-1 text-xs text-info" title="Triggered by webhook">
                            <Webhook className="h-3 w-3" />
                        </span>
                    )}
                    {activity.is_api && (
                        <span className="flex items-center gap-1 text-xs text-info" title="Triggered by API">
                            <Code2 className="h-3 w-3" />
                        </span>
                    )}
                    {deploymentUrl && (
                        <ExternalLink className="h-3 w-3 text-foreground-muted ml-auto" />
                    )}
                </div>
                <p className="text-sm text-foreground-muted mt-0.5">
                    {formatDescription(activity.description)}
                </p>
                {activity.resource_name && (
                    <p className="text-xs text-foreground-subtle mt-0.5">
                        Resource: {activity.resource_name}
                    </p>
                )}
                <div className="flex items-center gap-2 mt-1 flex-wrap">
                    {activity.user_name && (
                        <span
                            className="text-xs text-foreground-muted cursor-default"
                            title={activity.user_email || undefined}
                        >
                            {activity.user_name}
                        </span>
                    )}
                    {activity.team_name && activity.user_name !== activity.team_name && (
                        <>
                            <span className="text-xs text-foreground-subtle">•</span>
                            <span className="text-xs text-foreground-subtle">{activity.team_name}</span>
                        </>
                    )}
                    {activity.created_at && (
                        <>
                            <span className="text-xs text-foreground-subtle">•</span>
                            <span className="text-xs text-foreground-subtle">
                                {formatRelativeTime(activity.created_at)}
                            </span>
                        </>
                    )}
                </div>
            </div>
        </div>
    );

    if (deploymentUrl) {
        return <Link href={deploymentUrl}>{content}</Link>;
    }
    return content;
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
            <div className="mx-auto max-w-7xl">
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
                        subtitle={`${stats.activeUsers} active (30d)`}
                        icon={Users}
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
                    <StatCard
                        title="Applications"
                        value={stats.totalApplications}
                        subtitle="Deployed apps"
                        icon={Activity}
                    />
                    <StatCard
                        title="Services"
                        value={stats.totalServices}
                        subtitle="Running services"
                        icon={Server}
                    />
                    <StatCard
                        title="Databases"
                        value={stats.totalDatabases}
                        subtitle="All types"
                        icon={Database}
                    />
                    <StatCard
                        title="Deploy Success Rate"
                        value={`${stats.deploymentSuccessRate24h}%`}
                        subtitle={`${stats.deploymentSuccessRate7d}% over 7d`}
                        icon={CheckCircle}
                    />
                </div>

                {/* Queue Status */}
                {(stats.queuePending > 0 || stats.queueFailed > 0) && (
                    <div className="mb-8 flex gap-4">
                        {stats.queuePending > 0 && (
                            <Badge variant="warning">
                                {stats.queuePending} deployment{stats.queuePending > 1 ? 's' : ''} in queue
                            </Badge>
                        )}
                        {stats.queueFailed > 0 && (
                            <Badge variant="danger">
                                {stats.queueFailed} failed job{stats.queueFailed > 1 ? 's' : ''}
                            </Badge>
                        )}
                    </div>
                )}

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
                            {recentActivity.length > 0 ? (
                                <div className="space-y-4">
                                    {recentActivity.map((activity) => (
                                        <ActivityItem key={activity.id} activity={activity} />
                                    ))}
                                </div>
                            ) : (
                                <div className="flex flex-col items-center justify-center py-8 text-center">
                                    <Activity className="h-8 w-8 text-foreground-muted mb-2" />
                                    <p className="text-sm text-foreground-muted">No recent activity</p>
                                    <p className="text-xs text-foreground-subtle mt-1">Activity will appear here as actions are performed</p>
                                </div>
                            )}
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
