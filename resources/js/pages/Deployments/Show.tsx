import * as React from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, CardHeader, Badge, Button, useConfirm } from '@/components/ui';
import { LogsContainer, type LogLine } from '@/components/features/LogsContainer';
import { AIAnalysisCard } from '@/components/features/AIAnalysisCard';
import { DeploymentGraph, parseDeploymentLogs } from '@/components/features/DeploymentGraph';
import { formatRelativeTime } from '@/lib/utils';
import { getStatusIcon, getStatusVariant } from '@/lib/statusUtils';
import { useLogStream } from '@/hooks/useLogStream';
import type { Deployment } from '@/types';
import {
    GitCommit,
    Clock,
    Play,
    RotateCw,
    Terminal,
    ChevronLeft,
    GitBranch,
    Calendar,
    Activity,
    Package,
    FileText,
    Code,
    StopCircle,
    Download,
} from 'lucide-react';

interface Props {
    deployment?: Deployment & {
        author?: {
            name: string;
            email: string;
            avatar?: string;
        };
        duration?: string;
        trigger?: 'push' | 'manual' | 'rollback' | 'scheduled';
        service_name?: string;
        build_logs?: string[];
        deploy_logs?: string[];
        environment?: Record<string, string>;
        artifacts?: Array<{
            name: string;
            size: string;
            url: string;
        }>;
        previous_deployment_uuid?: string;
    };
}

export default function DeploymentShow({ deployment: propDeployment }: Props) {
    const deployment = propDeployment;
    const [activeTab, setActiveTab] = React.useState<'build' | 'deploy' | 'environment' | 'artifacts'>('build');
    const confirm = useConfirm();

    // Real-time log streaming for in-progress deployments
    const {
        logs: streamedLogs,
        isStreaming,
    } = useLogStream({
        resourceType: 'deployment',
        resourceId: deployment?.uuid || '',
        enableWebSocket: deployment?.status === 'in_progress',
    });

    // Action handlers with confirmation dialogs
    const handleCancelDeployment = async () => {
        if (!deployment) return;
        const confirmed = await confirm({
            title: 'Cancel Deployment',
            description: 'Are you sure you want to cancel this deployment? This action cannot be undone.',
            confirmText: 'Cancel Deployment',
            variant: 'danger',
        });
        if (confirmed) {
            await fetch(`/api/v1/deployments/${deployment.deployment_uuid || deployment.uuid}/cancel`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
                },
            });
            router.reload();
        }
    };

    const handleRollback = async () => {
        if (!deployment?.application_uuid) return;
        const confirmed = await confirm({
            title: 'Rollback Deployment',
            description: 'Are you sure you want to rollback to this deployment? This will redeploy the application with this version.',
            confirmText: 'Rollback',
            variant: 'warning',
        });
        if (confirmed) {
            await fetch(`/api/v1/applications/${deployment.application_uuid}/rollback/${deployment.deployment_uuid || deployment.uuid}`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
                },
            });
            router.reload();
        }
    };

    const handleRedeploy = async () => {
        if (!deployment?.application_uuid) return;
        const confirmed = await confirm({
            title: 'Redeploy Application',
            description: 'Are you sure you want to redeploy this application?',
            confirmText: 'Redeploy',
        });
        if (confirmed) {
            await fetch(`/api/v1/deploy?uuid=${deployment.application_uuid}`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
                },
            });
            router.visit('/deployments');
        }
    };

    const handleRetry = async () => {
        if (!deployment?.application_uuid) return;
        const confirmed = await confirm({
            title: 'Retry Deployment',
            description: 'Are you sure you want to retry this deployment?',
            confirmText: 'Retry',
        });
        if (confirmed) {
            await fetch(`/api/v1/deploy?uuid=${deployment.application_uuid}`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
                },
            });
            router.visit('/deployments');
        }
    };

    // Transform logs to LogLine format for LogsContainer
    // Use streamed logs if deployment is in progress and streaming is active,
    // otherwise use the logs from props
    const buildLogsFormatted: LogLine[] = React.useMemo(() => {
        const buildLogs = isStreaming && streamedLogs.length > 0
            ? streamedLogs.filter(log => !log.source || log.source === 'build').map(log => log.message)
            : (deployment?.build_logs || []);
        return buildLogs.map((log, index) => ({
            id: `build-${index}`,
            content: log,
        }));
    }, [isStreaming, streamedLogs, deployment?.build_logs]);

    const deployLogsFormatted: LogLine[] = React.useMemo(() => {
        const deployLogs = isStreaming && streamedLogs.length > 0
            ? streamedLogs.filter(log => log.source === 'deploy').map(log => log.message)
            : (deployment?.deploy_logs || []);
        return deployLogs.map((log, index) => ({
            id: `deploy-${index}`,
            content: log,
        }));
    }, [isStreaming, streamedLogs, deployment?.deploy_logs]);

    // Parse deployment stages from logs for the visual graph
    const deploymentStages = React.useMemo(() => {
        const allLogs = isStreaming && streamedLogs.length > 0
            ? streamedLogs.map(log => ({ output: log.message, timestamp: log.timestamp }))
            : [
                ...(deployment?.build_logs || []).map(log => ({ output: log })),
                ...(deployment?.deploy_logs || []).map(log => ({ output: log })),
            ];
        return parseDeploymentLogs(allLogs);
    }, [isStreaming, streamedLogs, deployment?.build_logs, deployment?.deploy_logs]);

    // Determine current stage for the graph
    const currentStage = React.useMemo(() => {
        const runningStage = deploymentStages.find(s => s.status === 'running');
        return runningStage?.id;
    }, [deploymentStages]);

    if (!deployment) {
        return (
            <AppLayout title="Deployment" breadcrumbs={[{ label: 'Deployments', href: '/deployments' }, { label: 'Not Found' }]}>
                <div className="flex flex-col items-center justify-center py-16">
                    <Play className="h-12 w-12 text-foreground-muted" />
                    <h3 className="mt-4 text-lg font-medium text-foreground">Deployment not found</h3>
                    <Link href="/deployments">
                        <Button variant="secondary" size="sm" className="mt-4">
                            <ChevronLeft className="mr-2 h-4 w-4" />
                            Back to Deployments
                        </Button>
                    </Link>
                </div>
            </AppLayout>
        );
    }

    const initials = deployment.author?.name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);

    return (
        <AppLayout
            title={`Deployment ${deployment.commit?.substring(0, 8)}`}
            breadcrumbs={[
                { label: 'Deployments', href: '/deployments' },
                { label: deployment.commit?.substring(0, 8) || 'Deployment' },
            ]}
        >
            {/* Back Button */}
            <div className="mb-4">
                <Link href="/deployments">
                    <Button variant="ghost" size="sm">
                        <ChevronLeft className="mr-1 h-4 w-4" />
                        Back to Deployments
                    </Button>
                </Link>
            </div>

            {/* Deployment Overview */}
            <Card className="mb-6">
                <CardContent className="p-6">
                    <div className="flex items-start gap-4">
                        {/* Status Icon */}
                        <div className="mt-1">{getStatusIcon(deployment.status)}</div>

                        {/* Main Info */}
                        <div className="flex-1 space-y-4">
                            {/* Header */}
                            <div>
                                <div className="flex items-center gap-3">
                                    <h2 className="text-xl font-semibold text-foreground">
                                        {deployment.commit_message}
                                    </h2>
                                    <Badge variant={getStatusVariant(deployment.status)} className="text-sm">
                                        {deployment.status.replace('_', ' ')}
                                    </Badge>
                                </div>
                                <div className="mt-2 flex items-center gap-2">
                                    <GitCommit className="h-4 w-4 text-foreground-muted" />
                                    <code className="text-sm font-medium text-primary">
                                        {deployment.commit}
                                    </code>
                                </div>
                            </div>

                            {/* Metadata Grid */}
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                {/* Author */}
                                {deployment.author && (
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-purple-500 text-sm font-medium text-white">
                                            {deployment.author.avatar ? (
                                                <img
                                                    src={deployment.author.avatar}
                                                    alt={deployment.author.name}
                                                    className="h-full w-full rounded-full object-cover"
                                                />
                                            ) : (
                                                initials
                                            )}
                                        </div>
                                        <div>
                                            <div className="text-xs text-foreground-muted">Author</div>
                                            <div className="text-sm font-medium text-foreground">
                                                {deployment.author.name}
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {/* Trigger */}
                                {deployment.trigger && (
                                    <div>
                                        <div className="text-xs text-foreground-muted">Triggered by</div>
                                        <div className="mt-1 text-sm font-medium text-foreground capitalize">
                                            {deployment.trigger}
                                        </div>
                                    </div>
                                )}

                                {/* Duration */}
                                {deployment.duration && (
                                    <div>
                                        <div className="text-xs text-foreground-muted">Duration</div>
                                        <div className="mt-1 flex items-center gap-1.5 text-sm font-medium text-foreground">
                                            <Clock className="h-4 w-4" />
                                            {deployment.duration}
                                        </div>
                                    </div>
                                )}

                                {/* Created */}
                                <div>
                                    <div className="text-xs text-foreground-muted">Started</div>
                                    <div className="mt-1 flex items-center gap-1.5 text-sm font-medium text-foreground">
                                        <Calendar className="h-4 w-4" />
                                        {formatRelativeTime(deployment.created_at)}
                                    </div>
                                </div>
                            </div>

                            {/* Actions */}
                            <div className="flex flex-wrap items-center gap-2 pt-2">
                                {(deployment.status === 'in_progress' || deployment.status === 'queued') && (
                                    <Button
                                        variant="danger"
                                        size="sm"
                                        onClick={handleCancelDeployment}
                                    >
                                        <StopCircle className="mr-2 h-4 w-4" />
                                        Cancel Deployment
                                    </Button>
                                )}
                                {deployment.status === 'finished' && deployment.application_uuid && (
                                    <>
                                        <Button size="sm" onClick={handleRollback}>
                                            <RotateCw className="mr-2 h-4 w-4" />
                                            Rollback
                                        </Button>
                                        <Button variant="secondary" size="sm" onClick={handleRedeploy}>
                                            <Play className="mr-2 h-4 w-4" />
                                            Redeploy
                                        </Button>
                                    </>
                                )}
                                {deployment.status === 'failed' && deployment.application_uuid && (
                                    <Button size="sm" onClick={handleRetry}>
                                        <RotateCw className="mr-2 h-4 w-4" />
                                        Retry
                                    </Button>
                                )}
                                {deployment.previous_deployment_uuid && (
                                    <Link href={`/deployments/${deployment.previous_deployment_uuid}/diff`}>
                                        <Button variant="secondary" size="sm">
                                            <GitBranch className="mr-2 h-4 w-4" />
                                            View Diff
                                        </Button>
                                    </Link>
                                )}
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* AI Analysis Card - shown for failed deployments */}
            {deployment.uuid && (
                <AIAnalysisCard
                    deploymentUuid={deployment.uuid}
                    deploymentStatus={deployment.status}
                />
            )}

            {/* Tabs */}
            <Card>
                <CardHeader className="border-b border-border">
                    <div className="flex items-center gap-4">
                        <TabButton
                            icon={<Terminal className="h-4 w-4" />}
                            label="Build Logs"
                            active={activeTab === 'build'}
                            onClick={() => setActiveTab('build')}
                        />
                        <TabButton
                            icon={<Activity className="h-4 w-4" />}
                            label="Deploy Logs"
                            active={activeTab === 'deploy'}
                            onClick={() => setActiveTab('deploy')}
                        />
                        <TabButton
                            icon={<Code className="h-4 w-4" />}
                            label="Environment"
                            active={activeTab === 'environment'}
                            onClick={() => setActiveTab('environment')}
                        />
                        <TabButton
                            icon={<Package className="h-4 w-4" />}
                            label="Artifacts"
                            active={activeTab === 'artifacts'}
                            onClick={() => setActiveTab('artifacts')}
                        />
                    </div>
                </CardHeader>

                <CardContent className="p-0">
                    {activeTab === 'build' && (
                        <LogsContainer
                            logs={buildLogsFormatted}
                            storageKey={`deployment-${deployment.uuid}-build`}
                            title="Build Logs"
                            height={600}
                            isStreaming={isStreaming && deployment.status === 'in_progress'}
                            showSearch
                            showLevelFilter
                            showDownload
                            showCopy
                            showLineNumbers
                        />
                    )}
                    {activeTab === 'deploy' && (
                        <LogsContainer
                            logs={deployLogsFormatted}
                            storageKey={`deployment-${deployment.uuid}-deploy`}
                            title="Deploy Logs"
                            height={600}
                            isStreaming={isStreaming && deployment.status === 'in_progress'}
                            showSearch
                            showLevelFilter
                            showDownload
                            showCopy
                            showLineNumbers
                        />
                    )}
                    {activeTab === 'environment' && (
                        <EnvironmentView environment={deployment.environment || {}} />
                    )}
                    {activeTab === 'artifacts' && (
                        <ArtifactsView artifacts={deployment.artifacts || []} />
                    )}
                </CardContent>
            </Card>
        </AppLayout>
    );
}

function TabButton({
    icon,
    label,
    active,
    onClick,
}: {
    icon: React.ReactNode;
    label: string;
    active: boolean;
    onClick: () => void;
}) {
    return (
        <button
            onClick={onClick}
            className={`flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium transition-colors ${
                active
                    ? 'border-primary text-primary'
                    : 'border-transparent text-foreground-muted hover:text-foreground'
            }`}
        >
            {icon}
            {label}
        </button>
    );
}

function EnvironmentView({ environment }: { environment: Record<string, string> }) {
    const entries = Object.entries(environment);

    return (
        <div className="p-4">
            <div className="space-y-2">
                {entries.length === 0 ? (
                    <div className="py-8 text-center text-foreground-muted">
                        No environment variables configured
                    </div>
                ) : (
                    entries.map(([key, value]) => (
                        <div
                            key={key}
                            className="flex items-start gap-4 rounded-lg border border-border bg-background-secondary p-3"
                        >
                            <Code className="mt-0.5 h-4 w-4 shrink-0 text-foreground-muted" />
                            <div className="flex-1 space-y-1">
                                <div className="font-mono text-sm font-medium text-foreground">{key}</div>
                                <div className="font-mono text-sm text-foreground-muted">{value}</div>
                            </div>
                        </div>
                    ))
                )}
            </div>
        </div>
    );
}

function ArtifactsView({ artifacts }: { artifacts: Array<{ name: string; size: string; url: string }> }) {
    return (
        <div className="p-4">
            <div className="space-y-2">
                {artifacts.length === 0 ? (
                    <div className="py-8 text-center text-foreground-muted">
                        No artifacts available
                    </div>
                ) : (
                    artifacts.map((artifact) => (
                        <div
                            key={artifact.name}
                            className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-4 transition-colors hover:bg-background-tertiary"
                        >
                            <div className="flex items-center gap-3">
                                <FileText className="h-5 w-5 text-foreground-muted" />
                                <div>
                                    <div className="font-medium text-foreground">{artifact.name}</div>
                                    <div className="text-sm text-foreground-muted">{artifact.size}</div>
                                </div>
                            </div>
                            <a href={artifact.url} download>
                                <Button variant="secondary" size="sm">
                                    <Download className="mr-2 h-4 w-4" />
                                    Download
                                </Button>
                            </a>
                        </div>
                    ))
                )}
            </div>
        </div>
    );
}
