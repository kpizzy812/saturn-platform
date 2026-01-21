import { Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Sparkline } from '@/components/ui/Chart';
import {
    Activity,
    Cpu,
    MemoryStick,
    HardDrive,
    AlertTriangle,
    CheckCircle2,
    ArrowRight,
} from 'lucide-react';
import { useSentinelMetrics } from '@/hooks/useSentinelMetrics';
import type { Server } from '@/types';

interface SentinelWidgetProps {
    server: Server;
    compact?: boolean;
}

export function SentinelWidget({ server, compact = false }: SentinelWidgetProps) {
    const { metrics, alerts, isLoading } = useSentinelMetrics({
        serverUuid: server.uuid,
        autoRefresh: true,
        refreshInterval: 30000, // 30 seconds for widget
    });

    const healthStatus = metrics ? (
        metrics.cpu.percentage < 75 && metrics.memory.percentage < 85 && metrics.disk.percentage < 90
            ? 'healthy'
            : metrics.cpu.percentage >= 90 || metrics.memory.percentage >= 95 || metrics.disk.percentage >= 95
            ? 'critical'
            : 'degraded'
    ) : 'unknown';

    const activeAlertCount = alerts?.length || 0;

    const healthConfig = {
        healthy: {
            icon: <CheckCircle2 className="h-4 w-4" />,
            text: 'Healthy',
            variant: 'success' as const,
            dot: 'bg-success',
        },
        degraded: {
            icon: <AlertTriangle className="h-4 w-4" />,
            text: 'Degraded',
            variant: 'warning' as const,
            dot: 'bg-warning',
        },
        critical: {
            icon: <AlertTriangle className="h-4 w-4" />,
            text: 'Critical',
            variant: 'danger' as const,
            dot: 'bg-danger',
        },
        unknown: {
            icon: <Activity className="h-4 w-4" />,
            text: 'Unknown',
            variant: 'secondary' as const,
            dot: 'bg-foreground-subtle',
        },
    };

    const health = healthConfig[healthStatus];

    if (compact) {
        return (
            <Link href={`/servers/${server.uuid}/sentinel`}>
                <Card hover>
                    <CardContent className="p-4">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <div className={`h-2 w-2 rounded-full ${health.dot} animate-pulse`} />
                                <span className="text-sm font-medium text-foreground">
                                    Server Health
                                </span>
                            </div>
                            <div className="flex items-center gap-2">
                                <Badge variant={health.variant} size="sm">
                                    {health.text}
                                </Badge>
                                {activeAlertCount > 0 && (
                                    <Badge variant="danger" size="sm">
                                        {activeAlertCount}
                                    </Badge>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </Link>
        );
    }

    return (
        <Card>
            <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                    <CardTitle className="text-base">Server Health</CardTitle>
                    <Link
                        href={`/servers/${server.uuid}/sentinel`}
                        className="flex items-center gap-1 text-xs font-medium text-primary transition-colors hover:text-primary/80"
                    >
                        View Details
                        <ArrowRight className="h-3 w-3" />
                    </Link>
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                {/* Status Overview */}
                <div className="flex items-center justify-between rounded-lg bg-background-tertiary/50 p-3">
                    <div className="flex items-center gap-2">
                        <div className={`h-2 w-2 rounded-full ${health.dot} animate-pulse`} />
                        <span className="text-sm font-medium text-foreground">Status</span>
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge variant={health.variant} size="sm">
                            {health.icon}
                            <span className="ml-1">{health.text}</span>
                        </Badge>
                        {activeAlertCount > 0 && (
                            <Badge variant="danger" size="sm">
                                {activeAlertCount} alert{activeAlertCount !== 1 ? 's' : ''}
                            </Badge>
                        )}
                    </div>
                </div>

                {/* Quick Stats */}
                {isLoading ? (
                    <div className="space-y-3">
                        {[1, 2, 3].map((i) => (
                            <div key={i} className="h-12 animate-pulse rounded-lg bg-background-tertiary/50" />
                        ))}
                    </div>
                ) : (
                    <div className="space-y-3">
                        {/* CPU */}
                        <div className="flex items-center gap-3">
                            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-info/10">
                                <Cpu className="h-4 w-4 text-info" />
                            </div>
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs text-foreground-muted">CPU</span>
                                    <span className="text-sm font-semibold text-foreground">
                                        {metrics?.cpu.current || '0%'}
                                    </span>
                                </div>
                                {metrics?.cpu.trend && metrics.cpu.trend.length > 0 && (
                                    <div className="mt-1">
                                        <Sparkline
                                            data={metrics.cpu.trend}
                                            color="rgb(99, 102, 241)"
                                        />
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Memory */}
                        <div className="flex items-center gap-3">
                            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-warning/10">
                                <MemoryStick className="h-4 w-4 text-warning" />
                            </div>
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs text-foreground-muted">Memory</span>
                                    <span className="text-sm font-semibold text-foreground">
                                        {metrics?.memory.current || '0 GB'}
                                    </span>
                                </div>
                                {metrics?.memory.trend && metrics.memory.trend.length > 0 && (
                                    <div className="mt-1">
                                        <Sparkline
                                            data={metrics.memory.trend}
                                            color="rgb(245, 158, 11)"
                                        />
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Disk */}
                        <div className="flex items-center gap-3">
                            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-success/10">
                                <HardDrive className="h-4 w-4 text-success" />
                            </div>
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs text-foreground-muted">Disk</span>
                                    <span className="text-sm font-semibold text-foreground">
                                        {metrics?.disk.current || '0 GB'}
                                    </span>
                                </div>
                                {metrics?.disk.trend && metrics.disk.trend.length > 0 && (
                                    <div className="mt-1">
                                        <Sparkline
                                            data={metrics.disk.trend}
                                            color="rgb(34, 197, 94)"
                                        />
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
