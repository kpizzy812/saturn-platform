import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Button, Badge, Card, CardContent, CardHeader, CardTitle } from '@/components/ui';
import {
    ArrowRight,
    Clock,
    CheckCircle,
    XCircle,
    AlertCircle,
    Loader2,
    ArrowLeft,
    Shield,
    FileText,
    ChevronDown,
    ChevronUp,
    RotateCcw,
    Server,
    Box,
    Database,
    Wifi,
    WifiOff,
} from 'lucide-react';
import { useMigrationProgress } from '@/hooks/useMigrationProgress';
import type { EnvironmentMigration, EnvironmentMigrationStatus } from '@/types';
import { formatDistanceToNow, format } from 'date-fns';
import { useState, useEffect } from 'react';

interface Props {
    migration: EnvironmentMigration;
}

const statusConfig: Record<EnvironmentMigrationStatus, {
    icon: typeof Clock;
    color: string;
    bgColor: string;
    label: string;
}> = {
    pending: { icon: Clock, color: 'text-yellow-500', bgColor: 'bg-yellow-500/10', label: 'Pending Approval' },
    approved: { icon: Shield, color: 'text-blue-500', bgColor: 'bg-blue-500/10', label: 'Approved' },
    rejected: { icon: XCircle, color: 'text-red-500', bgColor: 'bg-red-500/10', label: 'Rejected' },
    in_progress: { icon: Loader2, color: 'text-blue-500', bgColor: 'bg-blue-500/10', label: 'In Progress' },
    completed: { icon: CheckCircle, color: 'text-green-500', bgColor: 'bg-green-500/10', label: 'Completed' },
    failed: { icon: XCircle, color: 'text-red-500', bgColor: 'bg-red-500/10', label: 'Failed' },
    rolled_back: { icon: RotateCcw, color: 'text-orange-500', bgColor: 'bg-orange-500/10', label: 'Rolled Back' },
};

function getSourceTypeName(sourceType: string): string {
    const className = sourceType.split('\\').pop() || '';
    const map: Record<string, string> = {
        Application: 'Application',
        Service: 'Service',
        StandalonePostgresql: 'PostgreSQL',
        StandaloneMysql: 'MySQL',
        StandaloneMariadb: 'MariaDB',
        StandaloneMongodb: 'MongoDB',
        StandaloneRedis: 'Redis',
        StandaloneClickhouse: 'ClickHouse',
        StandaloneKeydb: 'KeyDB',
        StandaloneDragonfly: 'Dragonfly',
    };
    return map[className] || className;
}

function getResourceIcon(sourceType: string) {
    const className = sourceType.split('\\').pop() || '';
    if (className === 'Application') return Box;
    if (className === 'Service') return Server;
    return Database;
}

export default function MigrationShow({ migration: initialMigration }: Props) {
    const [showLogs, setShowLogs] = useState(true);

    const {
        migration: liveMigration,
        isConnected,
        logEntries,
        refetch,
    } = useMigrationProgress({
        migrationUuid: initialMigration.uuid,
        onComplete: () => {
            // Refresh full page data on completion
            router.reload({ only: ['migration'] });
        },
        onFailed: () => {
            router.reload({ only: ['migration'] });
        },
    });

    // Use live data if available, otherwise initial
    const migration = liveMigration || initialMigration;

    const config = statusConfig[migration.status] || statusConfig.pending;
    const StatusIcon = config.icon;
    const isAnimated = migration.status === 'in_progress';
    const isActive = ['pending', 'approved', 'in_progress'].includes(migration.status);
    const canRollback = migration.status === 'completed' && migration.rollback_snapshot;
    const ResourceIcon = getResourceIcon(migration.source_type);

    const mode = migration.options?.mode || 'clone';
    const sourceName = migration.source?.name || 'Unknown';
    const sourceEnvName = migration.source_environment?.name || 'Source';
    const targetEnvName = migration.target_environment?.name || 'Target';
    const projectName = (migration.source_environment as any)?.project?.name;

    const handleRollback = () => {
        if (confirm('Are you sure you want to rollback this migration? This will restore the previous configuration.')) {
            fetch(`/api/v1/migrations/${migration.uuid}/rollback`, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                credentials: 'include',
            }).then(() => router.reload());
        }
    };

    const handleApprove = () => {
        fetch(`/api/v1/migrations/${migration.uuid}/approve`, {
            method: 'POST',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
            credentials: 'include',
        }).then(() => router.reload());
    };

    const handleReject = () => {
        const reason = prompt('Rejection reason:');
        if (reason) {
            fetch(`/api/v1/migrations/${migration.uuid}/reject`, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ reason }),
            }).then(() => router.reload());
        }
    };

    // Auto-scroll logs to bottom
    const logsRef = useState<HTMLPreElement | null>(null);
    useEffect(() => {
        if (logsRef[0]) {
            logsRef[0].scrollTop = logsRef[0].scrollHeight;
        }
    }, [logEntries.length]);

    return (
        <AppLayout
            title={`Migration - ${sourceName}`}
            breadcrumbs={[
                ...(projectName ? [{ label: projectName, href: `/projects/${(migration.source_environment as any)?.project?.uuid}` }] : []),
                { label: 'Migration' },
                { label: sourceName },
            ]}
        >
            <div className="mx-auto max-w-4xl">
                {/* Back button */}
                {projectName && (
                    <Link
                        href={`/projects/${(migration.source_environment as any)?.project?.uuid}`}
                        className="inline-flex items-center gap-1 text-sm text-foreground-muted hover:text-foreground mb-4"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Back to project
                    </Link>
                )}

                {/* Header */}
                <div className="mb-6 flex items-start justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-foreground flex items-center gap-3">
                            <div className={`rounded-full p-2 ${config.bgColor}`}>
                                <StatusIcon className={`h-5 w-5 ${config.color} ${isAnimated ? 'animate-spin' : ''}`} />
                            </div>
                            <span className="flex items-center gap-2">
                                <ResourceIcon className="h-5 w-5 text-foreground-muted" />
                                {sourceName}
                            </span>
                        </h1>
                        <p className="mt-1 text-foreground-muted flex items-center gap-2">
                            <Badge variant="outline" className={mode === 'clone' ? 'bg-green-500/10 text-green-500' : 'bg-blue-500/10 text-blue-500'}>
                                {mode === 'clone' ? 'Clone' : 'Promote'}
                            </Badge>
                            {sourceEnvName}
                            <ArrowRight className="h-3 w-3" />
                            {targetEnvName}
                            {isConnected ? (
                                <span className="flex items-center gap-1 text-xs text-green-500">
                                    <Wifi className="h-3 w-3" /> Live
                                </span>
                            ) : isActive ? (
                                <span className="flex items-center gap-1 text-xs text-yellow-500">
                                    <WifiOff className="h-3 w-3" /> Polling
                                </span>
                            ) : null}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        {migration.status === 'pending' && migration.requires_approval && (
                            <>
                                <Button variant="success" onClick={handleApprove}>Approve</Button>
                                <Button variant="danger" onClick={handleReject}>Reject</Button>
                            </>
                        )}
                        {canRollback && (
                            <Button variant="danger" onClick={handleRollback}>
                                <RotateCcw className="h-4 w-4 mr-1" />
                                Rollback
                            </Button>
                        )}
                        <Badge variant="outline" className={`${config.bgColor} ${config.color}`}>
                            {config.label}
                        </Badge>
                    </div>
                </div>

                {/* Progress bar */}
                {(isActive || migration.status === 'in_progress') && (
                    <Card className="mb-6">
                        <CardContent className="py-4">
                            <div className="flex items-center justify-between text-sm text-foreground-muted mb-2">
                                <span>{migration.current_step || 'Waiting...'}</span>
                                <span>{migration.progress}%</span>
                            </div>
                            <div className="h-2 bg-background-tertiary rounded-full overflow-hidden">
                                <div
                                    className="h-full bg-primary transition-all duration-500 ease-out"
                                    style={{ width: `${migration.progress}%` }}
                                />
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Approval waiting state */}
                {migration.status === 'pending' && migration.requires_approval && (
                    <Card className="mb-6 border-yellow-500/50">
                        <CardContent className="py-4">
                            <div className="flex items-start gap-3">
                                <Shield className="h-5 w-5 text-yellow-500 shrink-0 mt-0.5" />
                                <div>
                                    <p className="font-medium text-yellow-500">Waiting for Approval</p>
                                    <p className="text-sm text-foreground-muted mt-1">
                                        This migration to {targetEnvName} requires approval from a project admin before execution.
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Rejection reason */}
                {migration.status === 'rejected' && migration.rejection_reason && (
                    <Card className="mb-6 border-red-500/50">
                        <CardContent className="py-4">
                            <div className="flex items-start gap-3">
                                <XCircle className="h-5 w-5 text-red-500 shrink-0 mt-0.5" />
                                <div>
                                    <p className="font-medium text-red-500">Migration Rejected</p>
                                    <p className="text-sm text-foreground-muted mt-1">{migration.rejection_reason}</p>
                                    {migration.approved_by_user && (
                                        <p className="text-xs text-foreground-muted mt-1">
                                            By {migration.approved_by_user.name}
                                            {migration.approved_at && ` at ${format(new Date(migration.approved_at), 'PPpp')}`}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Error message */}
                {migration.status === 'failed' && migration.error_message && (
                    <Card className="mb-6 border-red-500/50">
                        <CardContent className="py-4">
                            <div className="flex items-start gap-3">
                                <XCircle className="h-5 w-5 text-red-500 shrink-0 mt-0.5" />
                                <div>
                                    <p className="font-medium text-red-500">Migration Failed</p>
                                    <p className="text-sm text-foreground-muted mt-1 font-mono">{migration.error_message}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Completed summary */}
                {migration.status === 'completed' && (
                    <Card className="mb-6 border-green-500/50">
                        <CardContent className="py-4">
                            <div className="flex items-start gap-3">
                                <CheckCircle className="h-5 w-5 text-green-500 shrink-0 mt-0.5" />
                                <div>
                                    <p className="font-medium text-green-500">Migration Completed</p>
                                    <p className="text-sm text-foreground-muted mt-1">
                                        {mode === 'clone' ? 'Resource cloned' : 'Resource promoted'} to {targetEnvName} successfully.
                                        {migration.completed_at && ` Completed ${formatDistanceToNow(new Date(migration.completed_at), { addSuffix: true })}.`}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Details Grid */}
                <div className="grid gap-6 md:grid-cols-2">
                    {/* Source */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base flex items-center gap-2">
                                <ResourceIcon className="h-4 w-4" />
                                Source
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <DetailRow label="Resource" value={sourceName} />
                            <DetailRow label="Type" value={getSourceTypeName(migration.source_type)} />
                            <DetailRow label="Environment" value={sourceEnvName} />
                            {projectName && <DetailRow label="Project" value={projectName} />}
                        </CardContent>
                    </Card>

                    {/* Target */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base flex items-center gap-2">
                                <ArrowRight className="h-4 w-4" />
                                Target
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <DetailRow label="Environment" value={targetEnvName} />
                            {migration.target_server && (
                                <DetailRow
                                    label="Server"
                                    value={
                                        <span className="flex items-center gap-1">
                                            <Server className="h-3 w-3" />
                                            {migration.target_server.name}
                                        </span>
                                    }
                                />
                            )}
                            {migration.target?.name && (
                                <DetailRow label="Target Resource" value={migration.target.name} />
                            )}
                        </CardContent>
                    </Card>

                    {/* Options */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Options</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <DetailRow label="Mode" value={mode === 'clone' ? 'Clone (new copy)' : 'Promote (update existing)'} />
                            <DetailRow label="Copy Env Vars" value={migration.options?.copy_env_vars !== false ? 'Yes' : 'No'} />
                            <DetailRow label="Copy Volumes" value={migration.options?.copy_volumes ? 'Yes' : 'No'} />
                            <DetailRow label="Auto Deploy" value={migration.options?.auto_deploy ? 'Yes' : 'No'} />
                            {migration.options?.fqdn && (
                                <DetailRow label="Production Domain" value={migration.options.fqdn} />
                            )}
                        </CardContent>
                    </Card>

                    {/* Timeline */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base flex items-center gap-2">
                                <Clock className="h-4 w-4" />
                                Timeline
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <DetailRow
                                label="Created"
                                value={format(new Date(migration.created_at), 'PPpp')}
                            />
                            {migration.requested_by_user && (
                                <DetailRow label="Requested By" value={migration.requested_by_user.name} />
                            )}
                            {migration.approved_at && (
                                <DetailRow
                                    label={migration.status === 'rejected' ? 'Rejected' : 'Approved'}
                                    value={format(new Date(migration.approved_at), 'PPpp')}
                                />
                            )}
                            {migration.started_at && (
                                <DetailRow
                                    label="Started"
                                    value={format(new Date(migration.started_at), 'PPpp')}
                                />
                            )}
                            {migration.completed_at && (
                                <DetailRow
                                    label="Completed"
                                    value={format(new Date(migration.completed_at), 'PPpp')}
                                />
                            )}
                            {migration.rolled_back_at && (
                                <DetailRow
                                    label="Rolled Back"
                                    value={format(new Date(migration.rolled_back_at), 'PPpp')}
                                />
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Logs */}
                <Card className="mt-6">
                    <CardHeader
                        className="cursor-pointer"
                        onClick={() => setShowLogs(!showLogs)}
                    >
                        <CardTitle className="text-base flex items-center justify-between">
                            <span className="flex items-center gap-2">
                                <FileText className="h-4 w-4" />
                                Migration Logs
                                {isActive && logEntries.length > 0 && (
                                    <Badge variant="outline" className="text-xs">
                                        {logEntries.length} entries
                                    </Badge>
                                )}
                            </span>
                            {showLogs ? (
                                <ChevronUp className="h-4 w-4" />
                            ) : (
                                <ChevronDown className="h-4 w-4" />
                            )}
                        </CardTitle>
                    </CardHeader>
                    {showLogs && (
                        <CardContent>
                            {logEntries.length > 0 ? (
                                <pre
                                    ref={(el) => { logsRef[1](el); }}
                                    className="text-xs bg-background-tertiary rounded-lg p-4 overflow-x-auto max-h-96 overflow-y-auto font-mono"
                                >
                                    {logEntries.join('\n')}
                                </pre>
                            ) : (
                                <p className="text-sm text-foreground-muted">No logs yet.</p>
                            )}
                        </CardContent>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}

function DetailRow({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="flex justify-between">
            <span className="text-sm text-foreground-muted">{label}</span>
            <span className="text-sm font-medium text-foreground">{value}</span>
        </div>
    );
}
