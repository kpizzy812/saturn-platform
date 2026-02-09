import * as React from 'react';
import { Link, router } from '@inertiajs/react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Card, CardContent, CardHeader, Badge, Button } from '@/components/ui';
import { LogsContainer, type LogLine } from '@/components/features/LogsContainer';
import { DeploymentGraph, parseDeploymentLogs } from '@/components/features/DeploymentGraph';
import { formatRelativeTime } from '@/lib/utils';
import { getStatusIcon, getStatusVariant } from '@/lib/statusUtils';
import { useLogStream } from '@/hooks/useLogStream';
import {
    GitCommit,
    Clock,
    Terminal,
    ChevronLeft,
    Calendar,
    Activity,
    User,
    Rocket,
} from 'lucide-react';

interface DeploymentData {
    id: number;
    uuid: string;
    application_id?: number;
    application_uuid?: string;
    status: string;
    commit?: string;
    commit_message?: string;
    created_at: string;
    updated_at?: string;
    service_name?: string;
    trigger?: string;
    duration?: string;
    build_logs?: string[];
    deploy_logs?: string[];
    author?: {
        name: string;
        email: string;
        avatar?: string;
    };
}

interface Props {
    deployment?: DeploymentData;
}

export default function AdminDeploymentShow({ deployment }: Props) {
    const [activeTab, setActiveTab] = React.useState<'build' | 'deploy'>('build');

    // Real-time log streaming for in-progress deployments
    const {
        logs: streamedLogs,
        isStreaming,
    } = useLogStream({
        resourceType: 'deployment',
        resourceId: deployment?.uuid || '',
        enableWebSocket: deployment?.status === 'in_progress',
    });

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

    const deploymentStages = React.useMemo(() => {
        const allLogs = isStreaming && streamedLogs.length > 0
            ? streamedLogs.map(log => ({ output: log.message, timestamp: log.timestamp }))
            : [
                ...(deployment?.build_logs || []).map(log => ({ output: log })),
                ...(deployment?.deploy_logs || []).map(log => ({ output: log })),
            ];
        return parseDeploymentLogs(allLogs, deployment?.status);
    }, [isStreaming, streamedLogs, deployment?.build_logs, deployment?.deploy_logs, deployment?.status]);

    const currentStage = React.useMemo(() => {
        const runningStage = deploymentStages.find(s => s.status === 'running');
        return runningStage?.id;
    }, [deploymentStages]);

    if (!deployment) {
        return (
            <AdminLayout
                title="Deployment"
                breadcrumbs={[
                    { label: 'Admin', href: '/admin' },
                    { label: 'Deployments', href: '/admin/deployments' },
                    { label: 'Not Found' },
                ]}
            >
                <div className="flex flex-col items-center justify-center py-16">
                    <Rocket className="h-12 w-12 text-foreground-muted" />
                    <h3 className="mt-4 text-lg font-medium text-foreground">Deployment not found</h3>
                    <p className="mt-2 max-w-md text-center text-sm text-foreground-muted">
                        This deployment may have been removed or the logs were cleaned up.
                    </p>
                    <Link href="/admin/deployments">
                        <Button variant="secondary" size="sm" className="mt-4">
                            <ChevronLeft className="mr-2 h-4 w-4" />
                            Back to Deployments
                        </Button>
                    </Link>
                </div>
            </AdminLayout>
        );
    }

    const initials = deployment.author?.name
        ?.split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2) || '?';

    return (
        <AdminLayout
            title={`Deployment ${deployment.commit?.substring(0, 8) || deployment.uuid.substring(0, 8)}`}
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Deployments', href: '/admin/deployments' },
                { label: deployment.service_name || deployment.commit?.substring(0, 8) || 'Deployment' },
            ]}
        >
            <div className="mx-auto max-w-7xl">
                {/* Back Button */}
                <div className="mb-4">
                    <Link href="/admin/deployments">
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
                            <div className="mt-1">{getStatusIcon(deployment.status)}</div>

                            <div className="flex-1 space-y-4">
                                {/* Header */}
                                <div>
                                    <div className="flex items-center gap-3">
                                        <h2 className="text-xl font-semibold text-foreground">
                                            {deployment.service_name || 'Deployment'}
                                        </h2>
                                        <Badge variant={getStatusVariant(deployment.status)} className="text-sm">
                                            {deployment.status.replace('_', ' ')}
                                        </Badge>
                                    </div>
                                    {deployment.commit_message && (
                                        <p className="mt-1 text-sm text-foreground-muted">
                                            {deployment.commit_message}
                                        </p>
                                    )}
                                    {deployment.commit && (
                                        <div className="mt-2 flex items-center gap-2">
                                            <GitCommit className="h-4 w-4 text-foreground-muted" />
                                            <code className="text-sm font-medium text-primary">
                                                {deployment.commit.substring(0, 7)}
                                            </code>
                                        </div>
                                    )}
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
                                                <div className="text-xs text-foreground-muted">Triggered by</div>
                                                <div className="text-sm font-medium text-foreground">
                                                    {deployment.author.name}
                                                </div>
                                                <div className="text-xs text-foreground-subtle">
                                                    {deployment.author.email}
                                                </div>
                                            </div>
                                        </div>
                                    )}

                                    {/* Trigger */}
                                    {deployment.trigger && (
                                        <div>
                                            <div className="text-xs text-foreground-muted">Trigger type</div>
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
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Deployment Graph */}
                <Card className="mb-6">
                    <CardContent className="p-6">
                        <DeploymentGraph
                            stages={deploymentStages}
                            currentStage={currentStage}
                            isLive={deployment.status === 'in_progress' && isStreaming}
                        />
                    </CardContent>
                </Card>

                {/* Log Tabs */}
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
                        </div>
                    </CardHeader>

                    <CardContent className="p-0">
                        {activeTab === 'build' && (
                            <LogsContainer
                                logs={buildLogsFormatted}
                                storageKey={`admin-deployment-${deployment.uuid}-build`}
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
                                storageKey={`admin-deployment-${deployment.uuid}-deploy`}
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
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
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
