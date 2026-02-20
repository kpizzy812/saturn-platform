import { Head, Link } from '@inertiajs/react';
import { Button, Badge } from '@/components/ui';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card';
import { ArrowLeft, ArrowRight, Clock, CheckCircle, XCircle, Loader2, AlertCircle, RotateCcw } from 'lucide-react';
import { formatDistanceToNow } from 'date-fns';
import type { EnvironmentMigration, EnvironmentMigrationStatus } from '@/types';

interface EnvInfo {
    id: number;
    name: string;
    type: string;
}

interface Props {
    project: { id: number; uuid: string; name: string };
    environments: EnvInfo[];
    migrations: EnvironmentMigration[];
}

const ENV_ORDER: Record<string, number> = {
    development: 1,
    uat: 2,
    staging: 2,
    production: 3,
};

const statusConfig: Record<string, { icon: any; color: string; bg: string; label: string }> = {
    pending: { icon: Clock, color: 'text-foreground-muted', bg: 'bg-foreground-muted/10', label: 'Pending' },
    approved: { icon: CheckCircle, color: 'text-info', bg: 'bg-info/10', label: 'Approved' },
    in_progress: { icon: Loader2, color: 'text-primary', bg: 'bg-primary/10', label: 'In Progress' },
    completed: { icon: CheckCircle, color: 'text-success', bg: 'bg-success/10', label: 'Completed' },
    failed: { icon: XCircle, color: 'text-danger', bg: 'bg-danger/10', label: 'Failed' },
    rolled_back: { icon: RotateCcw, color: 'text-warning', bg: 'bg-warning/10', label: 'Rolled Back' },
    rejected: { icon: XCircle, color: 'text-danger', bg: 'bg-danger/10', label: 'Rejected' },
    cancelled: { icon: AlertCircle, color: 'text-foreground-muted', bg: 'bg-foreground-muted/10', label: 'Cancelled' },
};

export default function MigrationTimeline({ project, environments, migrations }: Props) {
    // Sort environments by promotion order
    const sortedEnvs = [...environments].sort(
        (a, b) => (ENV_ORDER[a.type] || 0) - (ENV_ORDER[b.type] || 0)
    );

    // Group migrations by target environment
    const migrationsByEnv: Record<number, EnvironmentMigration[]> = {};
    for (const env of sortedEnvs) {
        migrationsByEnv[env.id] = [];
    }
    for (const m of migrations) {
        const targetId = m.target_environment?.id ?? (m as any).target_environment_id;
        if (targetId && migrationsByEnv[targetId]) {
            migrationsByEnv[targetId].push(m);
        }
    }

    return (
        <>
            <Head title={`Migration Timeline — ${project.name}`} />
            <div className="mx-auto max-w-7xl px-4 py-8">
                {/* Header */}
                <div className="mb-6 flex items-center gap-3">
                    <Link href={`/projects/${project.uuid}`}>
                        <Button variant="ghost" size="sm">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            {project.name}
                        </Button>
                    </Link>
                </div>

                <div className="mb-6">
                    <h1 className="text-2xl font-semibold text-foreground">Migration Timeline</h1>
                    <p className="mt-1 text-sm text-foreground-muted">
                        Promotion history across environments
                    </p>
                </div>

                {/* Chain header */}
                <div className="mb-6 flex items-center justify-center gap-2">
                    {sortedEnvs.map((env, idx) => (
                        <div key={env.id} className="flex items-center gap-2">
                            {idx > 0 && <ArrowRight className="h-4 w-4 text-foreground-subtle" />}
                            <Badge variant={env.type === 'production' ? 'danger' : env.type === 'uat' ? 'warning' : 'secondary'} size="sm">
                                {env.name}
                            </Badge>
                        </div>
                    ))}
                </div>

                {/* Column layout */}
                {migrations.length === 0 ? (
                    <Card variant="glass">
                        <CardContent className="p-8 text-center text-foreground-muted">
                            No migrations found for this project.
                        </CardContent>
                    </Card>
                ) : (
                    <div className={`grid gap-4`} style={{ gridTemplateColumns: `repeat(${sortedEnvs.length}, 1fr)` }}>
                        {sortedEnvs.map((env) => (
                            <div key={env.id}>
                                <Card variant="glass">
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-sm">{env.name}</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-2 pt-0">
                                        {(migrationsByEnv[env.id] || []).length === 0 ? (
                                            <p className="py-4 text-center text-xs text-foreground-subtle">No migrations</p>
                                        ) : (
                                            (migrationsByEnv[env.id] || []).map((m) => {
                                                const config = statusConfig[m.status] || statusConfig.pending;
                                                const StatusIcon = config.icon;
                                                const resourceName = m.source?.name || 'Unknown';
                                                const sourceEnv = m.source_environment?.name || '?';

                                                return (
                                                    <Link
                                                        key={m.uuid}
                                                        href={`/migrations/${m.uuid}`}
                                                        className="block"
                                                    >
                                                        <div className={`rounded-lg border border-border/50 p-3 transition-colors hover:bg-foreground/[0.02] ${config.bg}`}>
                                                            <div className="flex items-start justify-between gap-2">
                                                                <div className="min-w-0 flex-1">
                                                                    <p className="truncate text-xs font-medium text-foreground">
                                                                        {resourceName}
                                                                    </p>
                                                                    <p className="mt-0.5 text-xs text-foreground-subtle">
                                                                        from {sourceEnv}
                                                                    </p>
                                                                </div>
                                                                <StatusIcon className={`h-3.5 w-3.5 flex-shrink-0 ${config.color} ${m.status === 'in_progress' ? 'animate-spin' : ''}`} />
                                                            </div>
                                                            <div className="mt-2 flex items-center justify-between">
                                                                <Badge
                                                                    variant="outline"
                                                                    size="sm"
                                                                    className={`${config.color} text-[10px]`}
                                                                >
                                                                    {config.label}
                                                                </Badge>
                                                                <span className="text-[10px] text-foreground-subtle">
                                                                    {formatDistanceToNow(new Date(m.created_at), { addSuffix: true })}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </Link>
                                                );
                                            })
                                        )}
                                    </CardContent>
                                </Card>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
