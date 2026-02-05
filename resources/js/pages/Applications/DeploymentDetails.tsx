import * as React from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, CardHeader, CardTitle, Button, Badge, useConfirm } from '@/components/ui';
import { Dropdown, DropdownTrigger, DropdownContent, DropdownItem } from '@/components/ui/Dropdown';
import { LogsContainer, type LogLine } from '@/components/features/LogsContainer';
import { AIAnalysisCard } from '@/components/features/AIAnalysisCard';
import { CodeReviewCard } from '@/components/features/CodeReviewCard';
import { DeploymentGraph, parseDeploymentLogs } from '@/components/features/DeploymentGraph';
import {
    Rocket,
    GitCommit,
    Clock,
    Server,
    GitBranch,
    RefreshCw,
    XCircle,
    ExternalLink,
    AlertCircle,
    CheckCircle2,
    Loader2,
    Ban,
    ArrowLeft,
    MoreVertical,
} from 'lucide-react';
import { getEcho } from '@/lib/echo';
import { cn } from '@/lib/utils';

// Types
interface LogEntry {
    output: string;
    type: 'stdout' | 'stderr';
    timestamp: string | null;
    order: number;
}

interface DeploymentData {
    id: number;
    deployment_uuid: string;
    status: 'queued' | 'in_progress' | 'finished' | 'failed' | 'cancelled' | 'pending_approval';
    commit: string | null;
    commit_message: string | null;
    is_webhook: boolean;
    is_api: boolean;
    force_rebuild: boolean;
    rollback: boolean;
    only_this_server: boolean;
    created_at: string;
    updated_at: string;
    duration: number | null;
    server_name: string;
    server_id: number;
}

interface ApplicationData {
    id: number;
    uuid: string;
    name: string;
    git_repository: string | null;
    git_branch: string | null;
}

interface Props {
    application: ApplicationData;
    deployment: DeploymentData;
    logs: LogEntry[];
    projectUuid: string;
    environmentUuid: string;
}

export default function DeploymentDetails({
    application,
    deployment: initialDeployment,
    logs: initialLogs,
    projectUuid,
    environmentUuid,
}: Props) {
    const confirm = useConfirm();
    const [deployment, setDeployment] = React.useState(initialDeployment);
    const [logs, setLogs] = React.useState<LogEntry[]>(initialLogs);
    const [isConnected, setIsConnected] = React.useState(false);

    const isInProgress = deployment.status === 'in_progress' || deployment.status === 'queued';

    // Subscribe to real-time log updates
    React.useEffect(() => {
        if (!isInProgress) return;

        const echo = getEcho();
        if (!echo) {
            return;
        }

        const channelName = `deployment.${deployment.deployment_uuid}.logs`;
        const channel = echo.private(channelName);

        channel.listen('DeploymentLogEntry', (event: { message: string; timestamp: string; type: string; order: number }) => {
            setLogs((prev) => {
                const newLog: LogEntry = {
                    output: event.message,
                    type: event.type as 'stdout' | 'stderr',
                    timestamp: event.timestamp,
                    order: event.order,
                };
                // Avoid duplicates
                if (prev.some((log) => log.order === event.order)) {
                    return prev;
                }
                return [...prev, newLog].sort((a, b) => a.order - b.order);
            });
        });

        setIsConnected(true);

        return () => {
            echo.leave(channelName);
            setIsConnected(false);
        };
    }, [deployment.deployment_uuid, isInProgress]);

    // Poll for status updates when in progress
    React.useEffect(() => {
        if (!isInProgress) return;

        const interval = setInterval(() => {
            router.reload({ only: ['deployment', 'logs'] });
        }, 5000);

        return () => clearInterval(interval);
    }, [isInProgress]);

    // Update state when props change (from polling)
    React.useEffect(() => {
        setDeployment(initialDeployment);
        setLogs(initialLogs);
    }, [initialDeployment, initialLogs]);

    // Transform logs to LogLine format for LogsContainer
    const transformedLogs: LogLine[] = React.useMemo(() => {
        return logs.map((log, index) => ({
            id: `${log.order}-${index}`,
            content: log.output,
            timestamp: log.timestamp || undefined,
            level: log.type === 'stderr' ? 'stderr' : 'stdout',
        }));
    }, [logs]);

    // Parse deployment stages for pipeline graph
    const deploymentStages = React.useMemo(() => {
        const logsForParsing = logs.map(log => ({
            output: log.output,
            timestamp: log.timestamp || undefined,
        }));
        return parseDeploymentLogs(logsForParsing);
    }, [logs]);

    // Determine current stage for the graph
    const currentStage = React.useMemo(() => {
        const runningStage = deploymentStages.find(s => s.status === 'running');
        return runningStage?.id;
    }, [deploymentStages]);

    const handleCancel = async () => {
        const confirmed = await confirm({
            title: 'Cancel Deployment',
            description: 'Are you sure you want to cancel this deployment? This action cannot be undone.',
            confirmText: 'Cancel Deployment',
            variant: 'danger',
        });
        if (!confirmed) return;
        router.post(`/api/v1/deployments/${deployment.deployment_uuid}/cancel`);
    };

    const handleRedeploy = () => {
        router.post(`/applications/${application.uuid}/deploy`, {}, {
            onSuccess: () => {
                router.visit(`/applications/${application.uuid}`);
            },
        });
    };

    const handleForceRebuild = () => {
        router.post(`/applications/${application.uuid}/deploy`, { force_rebuild: true }, {
            onSuccess: () => {
                router.visit(`/applications/${application.uuid}`);
            },
        });
    };

    const formatDuration = (seconds: number | null) => {
        if (!seconds) return 'N/A';
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        if (mins > 0) return `${mins}m ${secs}s`;
        return `${secs}s`;
    };

    const formatTimestamp = (timestamp: string) => {
        return new Date(timestamp).toLocaleString();
    };

    const getStatusConfig = (status: DeploymentData['status']) => {
        switch (status) {
            case 'finished':
                return {
                    icon: <CheckCircle2 className="h-5 w-5" />,
                    label: 'Successful',
                    variant: 'success' as const,
                    bgColor: 'bg-success/10',
                    textColor: 'text-success',
                    borderColor: 'border-success/30',
                };
            case 'failed':
                return {
                    icon: <XCircle className="h-5 w-5" />,
                    label: 'Failed',
                    variant: 'destructive' as const,
                    bgColor: 'bg-destructive/10',
                    textColor: 'text-destructive',
                    borderColor: 'border-destructive/30',
                };
            case 'in_progress':
                return {
                    icon: <Loader2 className="h-5 w-5 animate-spin" />,
                    label: 'In Progress',
                    variant: 'warning' as const,
                    bgColor: 'bg-warning/10',
                    textColor: 'text-warning',
                    borderColor: 'border-warning/30',
                };
            case 'queued':
                return {
                    icon: <Clock className="h-5 w-5" />,
                    label: 'Queued',
                    variant: 'default' as const,
                    bgColor: 'bg-foreground-muted/10',
                    textColor: 'text-foreground-muted',
                    borderColor: 'border-foreground-muted/30',
                };
            case 'cancelled':
                return {
                    icon: <Ban className="h-5 w-5" />,
                    label: 'Cancelled',
                    variant: 'default' as const,
                    bgColor: 'bg-foreground-muted/10',
                    textColor: 'text-foreground-muted',
                    borderColor: 'border-foreground-muted/30',
                };
            case 'pending_approval':
                return {
                    icon: <AlertCircle className="h-5 w-5" />,
                    label: 'Pending Approval',
                    variant: 'warning' as const,
                    bgColor: 'bg-warning/10',
                    textColor: 'text-warning',
                    borderColor: 'border-warning/30',
                };
        }
    };

    const statusConfig = getStatusConfig(deployment.status);

    const breadcrumbs = [
        { label: 'Applications', href: '/applications' },
        { label: application.name, href: `/applications/${application.uuid}` },
        { label: 'Deployments', href: `/applications/${application.uuid}/deployments` },
        { label: `#${deployment.id}` },
    ];

    const getTriggerLabel = () => {
        if (deployment.rollback) return 'Rollback';
        if (deployment.is_webhook) return 'Webhook';
        if (deployment.is_api) return 'API';
        return 'Manual';
    };

    return (
        <AppLayout title={`Deployment #${deployment.id}`} breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-7xl">
                {/* Back button */}
                <Link
                    href={`/applications/${application.uuid}/deployments`}
                    className="inline-flex items-center gap-2 text-sm text-foreground-muted hover:text-foreground mb-6 transition-colors"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back to Deployments
                </Link>

                {/* Header */}
                <div className="mb-8">
                    <div className="flex items-start justify-between gap-4">
                        <div className="flex items-start gap-4">
                            <div
                                className={cn(
                                    'flex h-14 w-14 items-center justify-center rounded-xl border',
                                    statusConfig.bgColor,
                                    statusConfig.textColor,
                                    statusConfig.borderColor
                                )}
                            >
                                {statusConfig.icon}
                            </div>
                            <div>
                                <div className="flex items-center gap-3 mb-1">
                                    <h1 className="text-2xl font-bold text-foreground">
                                        Deployment #{deployment.id}
                                    </h1>
                                    <Badge variant={statusConfig.variant}>{statusConfig.label}</Badge>
                                </div>
                                <p className="text-foreground-muted">
                                    {application.name} &bull; Started {formatTimestamp(deployment.created_at)}
                                </p>
                            </div>
                        </div>

                        <div className="flex items-center gap-2">
                            {isInProgress && (
                                <Button variant="danger" size="sm" onClick={handleCancel}>
                                    <XCircle className="mr-2 h-4 w-4" />
                                    Cancel
                                </Button>
                            )}
                            {!isInProgress && (
                                <>
                                    <Button variant="outline" size="sm" onClick={handleRedeploy}>
                                        <RefreshCw className="mr-2 h-4 w-4" />
                                        Redeploy
                                    </Button>
                                    <Dropdown>
                                        <DropdownTrigger>
                                            <Button variant="outline" size="sm">
                                                <MoreVertical className="h-4 w-4" />
                                            </Button>
                                        </DropdownTrigger>
                                        <DropdownContent align="right">
                                            <DropdownItem onClick={handleForceRebuild}>
                                                <Rocket className="h-4 w-4" />
                                                Force Rebuild
                                            </DropdownItem>
                                        </DropdownContent>
                                    </Dropdown>
                                </>
                            )}
                        </div>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Main content - Logs */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Deployment Pipeline Graph */}
                        <Card>
                            <CardContent className="p-6">
                                <DeploymentGraph
                                    stages={deploymentStages}
                                    currentStage={currentStage}
                                    isLive={isInProgress}
                                />
                            </CardContent>
                        </Card>

                        {/* AI Analysis Card - shown for failed deployments */}
                        <AIAnalysisCard
                            deploymentUuid={deployment.deployment_uuid}
                            deploymentStatus={deployment.status}
                        />

                        {/* Code Review Card - security vulnerability scanner */}
                        {deployment.commit && (
                            <CodeReviewCard
                                deploymentUuid={deployment.deployment_uuid}
                                commitSha={deployment.commit}
                            />
                        )}

                        {/* Logs Card */}
                        <Card className="overflow-hidden">
                            <LogsContainer
                                logs={transformedLogs}
                                storageKey={`deployment-${deployment.deployment_uuid}`}
                                title="Build Logs"
                                height={500}
                                isStreaming={isInProgress}
                                isConnected={isConnected}
                                showSearch
                                showLevelFilter
                                showCopy
                                showDownload
                                showLineNumbers
                                emptyMessage="No logs available"
                                loadingMessage="Waiting for logs..."
                            />
                        </Card>
                    </div>

                    {/* Sidebar - Info */}
                    <div className="space-y-6">
                        {/* Deployment Info */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Deployment Info</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <InfoRow
                                    icon={<Rocket className="h-4 w-4" />}
                                    label="Trigger"
                                    value={
                                        <Badge variant="outline" className="text-xs">
                                            {getTriggerLabel()}
                                        </Badge>
                                    }
                                />

                                {deployment.commit && (
                                    <InfoRow
                                        icon={<GitCommit className="h-4 w-4" />}
                                        label="Commit"
                                        value={
                                            <code className="text-xs bg-background-secondary px-2 py-1 rounded">
                                                {deployment.commit.slice(0, 7)}
                                            </code>
                                        }
                                    />
                                )}

                                {deployment.commit_message && (
                                    <InfoRow
                                        icon={<GitCommit className="h-4 w-4" />}
                                        label="Message"
                                        value={
                                            <span className="text-sm truncate max-w-[180px] block" title={deployment.commit_message}>
                                                {deployment.commit_message}
                                            </span>
                                        }
                                    />
                                )}

                                <InfoRow
                                    icon={<Server className="h-4 w-4" />}
                                    label="Server"
                                    value={deployment.server_name}
                                />

                                <InfoRow
                                    icon={<Clock className="h-4 w-4" />}
                                    label="Duration"
                                    value={
                                        isInProgress ? (
                                            <span className="flex items-center gap-1.5">
                                                <Loader2 className="h-3 w-3 animate-spin" />
                                                Running...
                                            </span>
                                        ) : (
                                            formatDuration(deployment.duration)
                                        )
                                    }
                                />

                                <InfoRow
                                    icon={<Clock className="h-4 w-4" />}
                                    label="Started"
                                    value={formatTimestamp(deployment.created_at)}
                                />

                                {!isInProgress && (
                                    <InfoRow
                                        icon={<Clock className="h-4 w-4" />}
                                        label="Finished"
                                        value={formatTimestamp(deployment.updated_at)}
                                    />
                                )}
                            </CardContent>
                        </Card>

                        {/* Application Info */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Application</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <InfoRow
                                    icon={<Rocket className="h-4 w-4" />}
                                    label="Name"
                                    value={
                                        <Link
                                            href={`/applications/${application.uuid}`}
                                            className="text-primary hover:underline flex items-center gap-1"
                                        >
                                            {application.name}
                                            <ExternalLink className="h-3 w-3" />
                                        </Link>
                                    }
                                />

                                {application.git_repository && (
                                    <InfoRow
                                        icon={<GitBranch className="h-4 w-4" />}
                                        label="Repository"
                                        value={
                                            <span className="text-sm truncate max-w-[180px] block" title={application.git_repository}>
                                                {application.git_repository.replace('https://github.com/', '')}
                                            </span>
                                        }
                                    />
                                )}

                                {application.git_branch && (
                                    <InfoRow
                                        icon={<GitBranch className="h-4 w-4" />}
                                        label="Branch"
                                        value={application.git_branch}
                                    />
                                )}
                            </CardContent>
                        </Card>

                        {/* Build Options */}
                        {(deployment.force_rebuild || deployment.only_this_server) && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Build Options</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2">
                                    {deployment.force_rebuild && (
                                        <div className="flex items-center gap-2 text-sm">
                                            <AlertCircle className="h-4 w-4 text-warning" />
                                            <span>Force rebuild (no cache)</span>
                                        </div>
                                    )}
                                    {deployment.only_this_server && (
                                        <div className="flex items-center gap-2 text-sm">
                                            <Server className="h-4 w-4 text-foreground-muted" />
                                            <span>Deploy to this server only</span>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

// Info row component
interface InfoRowProps {
    icon: React.ReactNode;
    label: string;
    value: React.ReactNode;
}

function InfoRow({ icon, label, value }: InfoRowProps) {
    return (
        <div className="flex items-start gap-3">
            <div className="text-foreground-muted mt-0.5">{icon}</div>
            <div className="flex-1 min-w-0">
                <p className="text-xs text-foreground-muted">{label}</p>
                <div className="mt-1 text-sm font-medium text-foreground">{value}</div>
            </div>
        </div>
    );
}
