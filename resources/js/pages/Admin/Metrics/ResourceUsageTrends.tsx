import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Cpu, HardDrive, MemoryStick, Server } from 'lucide-react';

interface ServerCheck {
    cpu: number;
    memory: number;
    disk: number;
    time: string;
}

interface ServerUsage {
    server_id: number;
    server_name: string;
    server_ip: string;
    checks: ServerCheck[];
    latest: { cpu: number; memory: number; disk: number };
}

interface Props {
    data: {
        servers: ServerUsage[];
        period: string;
    };
}

function UsageBar({ value, color }: { value: number; color: string }) {
    return (
        <div className="h-2 w-full rounded-full bg-border/50">
            <div
                className={`h-2 rounded-full ${color}`}
                style={{ width: `${Math.min(value, 100)}%` }}
            />
        </div>
    );
}

function getUsageLevel(value: number): { variant: 'success' | 'warning' | 'danger'; label: string } {
    if (value >= 90) return { variant: 'danger', label: 'Critical' };
    if (value >= 75) return { variant: 'warning', label: 'High' };
    return { variant: 'success', label: 'Normal' };
}

export function ResourceUsageTrends({ data }: Props) {
    if (!data?.servers?.length) {
        return (
            <Card variant="glass">
                <CardContent className="p-8 text-center text-foreground-muted">
                    No server health data available. Ensure servers have health checks enabled.
                </CardContent>
            </Card>
        );
    }

    return (
        <div className="space-y-4">
            {data.servers.map((server) => {
                const cpuLevel = getUsageLevel(server.latest.cpu);
                const memLevel = getUsageLevel(server.latest.memory);
                const diskLevel = getUsageLevel(server.latest.disk);

                return (
                    <Card key={server.server_id} variant="glass" hover>
                        <CardHeader className="pb-3">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <Server className="h-5 w-5 text-primary" />
                                    <div>
                                        <CardTitle className="text-base">{server.server_name}</CardTitle>
                                        <CardDescription>{server.server_ip}</CardDescription>
                                    </div>
                                </div>
                                <Badge variant="secondary" size="sm">
                                    {server.checks.length} checks
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent className="pt-0">
                            <div className="grid gap-4 sm:grid-cols-3">
                                {/* CPU */}
                                <div className="space-y-2">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <Cpu className="h-4 w-4 text-foreground-muted" />
                                            <span className="text-sm text-foreground-muted">CPU</span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm font-semibold">{server.latest.cpu}%</span>
                                            <Badge variant={cpuLevel.variant} size="sm">{cpuLevel.label}</Badge>
                                        </div>
                                    </div>
                                    <UsageBar value={server.latest.cpu} color="bg-primary" />
                                </div>

                                {/* Memory */}
                                <div className="space-y-2">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <MemoryStick className="h-4 w-4 text-foreground-muted" />
                                            <span className="text-sm text-foreground-muted">Memory</span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm font-semibold">{server.latest.memory}%</span>
                                            <Badge variant={memLevel.variant} size="sm">{memLevel.label}</Badge>
                                        </div>
                                    </div>
                                    <UsageBar value={server.latest.memory} color="bg-info" />
                                </div>

                                {/* Disk */}
                                <div className="space-y-2">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <HardDrive className="h-4 w-4 text-foreground-muted" />
                                            <span className="text-sm text-foreground-muted">Disk</span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm font-semibold">{server.latest.disk}%</span>
                                            <Badge variant={diskLevel.variant} size="sm">{diskLevel.label}</Badge>
                                        </div>
                                    </div>
                                    <UsageBar value={server.latest.disk} color="bg-warning" />
                                </div>
                            </div>

                            {/* Sparkline-like mini trend */}
                            {server.checks.length > 1 && (
                                <div className="mt-4 border-t border-border/30 pt-3">
                                    <p className="mb-2 text-xs text-foreground-subtle">
                                        CPU trend ({data.period}) — {server.checks.length} data points
                                    </p>
                                    <div className="flex h-8 items-end gap-px">
                                        {server.checks.slice(-60).map((check, i) => (
                                            <div
                                                key={i}
                                                className={`flex-1 rounded-t ${check.cpu >= 90 ? 'bg-danger' : check.cpu >= 75 ? 'bg-warning' : 'bg-primary/60'}`}
                                                style={{ height: `${Math.max(check.cpu, 2)}%` }}
                                                title={`${check.cpu}% at ${new Date(check.time).toLocaleTimeString()}`}
                                            />
                                        ))}
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                );
            })}
        </div>
    );
}
