import * as React from 'react';
import { Link } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, CardHeader, CardTitle, Badge, Button } from '@/components/ui';
import { LogsContainer, type LogLine } from '@/components/features/LogsContainer';
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

// Mock data for demo
const MOCK_DEPLOYMENT: Props['deployment'] = {
    id: 1,
    uuid: 'dep-1',
    application_id: 1,
    status: 'finished',
    commit: 'a1b2c3d4e5f6g7h8',
    commit_message: 'feat: Add user authentication and JWT tokens',
    created_at: new Date(Date.now() - 1000 * 60 * 30).toISOString(),
    updated_at: new Date(Date.now() - 1000 * 60 * 25).toISOString(),
    author: {
        name: 'John Doe',
        email: 'john@example.com',
    },
    duration: '3m 45s',
    trigger: 'push',
    service_name: 'production-api',
    previous_deployment_uuid: 'dep-0',
    build_logs: [
        '[2026-01-01 14:30:01] Starting build process...',
        '[2026-01-01 14:30:02] Cloning repository from git@github.com:company/production-api.git',
        '[2026-01-01 14:30:05] Checking out commit a1b2c3d4e5f6g7h8',
        '[2026-01-01 14:30:06] Installing dependencies...',
        '[2026-01-01 14:30:08] npm install',
        '[2026-01-01 14:30:45] added 847 packages, and audited 848 packages in 37s',
        '[2026-01-01 14:30:45] 127 packages are looking for funding',
        '[2026-01-01 14:30:45] found 0 vulnerabilities',
        '[2026-01-01 14:30:46] Building application...',
        '[2026-01-01 14:30:46] npm run build',
        '[2026-01-01 14:30:48] > production-api@1.0.0 build',
        '[2026-01-01 14:30:48] > vite build',
        '[2026-01-01 14:30:50] vite v5.0.0 building for production...',
        '[2026-01-01 14:30:52] transforming...',
        '[2026-01-01 14:31:45] ✓ 234 modules transformed.',
        '[2026-01-01 14:31:46] rendering chunks...',
        '[2026-01-01 14:31:48] computing gzip size...',
        '[2026-01-01 14:31:49] dist/index.html                   0.45 kB │ gzip:  0.30 kB',
        '[2026-01-01 14:31:49] dist/assets/index-a1b2c3d4.css  12.34 kB │ gzip:  3.45 kB',
        '[2026-01-01 14:31:49] dist/assets/index-e5f6g7h8.js  145.67 kB │ gzip: 45.67 kB',
        '[2026-01-01 14:31:49] ✓ built in 63.2s',
        '[2026-01-01 14:31:50] Building Docker image...',
        '[2026-01-01 14:31:52] Step 1/8 : FROM node:18-alpine',
        '[2026-01-01 14:31:54] ---> a1b2c3d4e5f6',
        '[2026-01-01 14:31:54] Step 2/8 : WORKDIR /app',
        '[2026-01-01 14:31:55] ---> Using cache',
        '[2026-01-01 14:31:55] ---> b2c3d4e5f6g7',
        '[2026-01-01 14:31:55] Step 3/8 : COPY package*.json ./',
        '[2026-01-01 14:31:56] ---> Using cache',
        '[2026-01-01 14:31:56] ---> c3d4e5f6g7h8',
        '[2026-01-01 14:31:56] Step 4/8 : RUN npm ci --only=production',
        '[2026-01-01 14:31:58] ---> Running in d4e5f6g7h8i9',
        '[2026-01-01 14:32:12] added 234 packages in 14.2s',
        '[2026-01-01 14:32:12] ---> e5f6g7h8i9j0',
        '[2026-01-01 14:32:13] Step 5/8 : COPY dist ./dist',
        '[2026-01-01 14:32:15] ---> f6g7h8i9j0k1',
        '[2026-01-01 14:32:15] Successfully built f6g7h8i9j0k1',
        '[2026-01-01 14:32:15] Successfully tagged production-api:a1b2c3d4',
        '[2026-01-01 14:32:16] Build completed successfully',
    ],
    deploy_logs: [
        '[2026-01-01 14:32:17] Starting deployment...',
        '[2026-01-01 14:32:18] Pushing image to registry...',
        '[2026-01-01 14:32:45] Image pushed successfully',
        '[2026-01-01 14:32:46] Connecting to server prod-server-1...',
        '[2026-01-01 14:32:47] Pulling image on server...',
        '[2026-01-01 14:33:12] Image pulled successfully',
        '[2026-01-01 14:33:13] Stopping old container...',
        '[2026-01-01 14:33:15] Container stopped successfully',
        '[2026-01-01 14:33:16] Starting new container...',
        '[2026-01-01 14:33:18] Container started with ID: abc123def456',
        '[2026-01-01 14:33:19] Waiting for health check...',
        '[2026-01-01 14:33:25] Health check passed',
        '[2026-01-01 14:33:26] Updating proxy configuration...',
        '[2026-01-01 14:33:28] Proxy configuration updated',
        '[2026-01-01 14:33:29] Deployment completed successfully',
    ],
    environment: {
        NODE_ENV: 'production',
        API_URL: 'https://api.example.com',
        DATABASE_URL: '***REDACTED***',
        JWT_SECRET: '***REDACTED***',
        REDIS_URL: '***REDACTED***',
    },
    artifacts: [
        { name: 'build-output.tar.gz', size: '12.4 MB', url: '/artifacts/build-output.tar.gz' },
        { name: 'docker-image.tar', size: '156.7 MB', url: '/artifacts/docker-image.tar' },
    ],
};

export default function DeploymentShow({ deployment: propDeployment }: Props) {
    const deployment = propDeployment || MOCK_DEPLOYMENT;
    const [activeTab, setActiveTab] = React.useState<'build' | 'deploy' | 'environment' | 'artifacts'>('build');

    // Real-time log streaming for in-progress deployments
    const {
        logs: streamedLogs,
        isStreaming,
        isConnected,
        clearLogs,
        downloadLogs,
    } = useLogStream({
        resourceType: 'deployment',
        resourceId: deployment.uuid,
        enableWebSocket: deployment.status === 'in_progress',
    });

    // Use streamed logs if deployment is in progress and streaming is active,
    // otherwise use the logs from props
    const buildLogs = isStreaming && streamedLogs.length > 0
        ? streamedLogs.filter(log => !log.source || log.source === 'build').map(log => log.message)
        : (deployment.build_logs || []);

    const deployLogs = isStreaming && streamedLogs.length > 0
        ? streamedLogs.filter(log => log.source === 'deploy').map(log => log.message)
        : (deployment.deploy_logs || []);

    // Transform logs to LogLine format for LogsContainer
    const buildLogsFormatted: LogLine[] = React.useMemo(() => {
        return buildLogs.map((log, index) => ({
            id: `build-${index}`,
            content: log,
        }));
    }, [buildLogs]);

    const deployLogsFormatted: LogLine[] = React.useMemo(() => {
        return deployLogs.map((log, index) => ({
            id: `deploy-${index}`,
            content: log,
        }));
    }, [deployLogs]);

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
                                {deployment.status === 'in_progress' && (
                                    <Button variant="danger" size="sm">
                                        <StopCircle className="mr-2 h-4 w-4" />
                                        Cancel Deployment
                                    </Button>
                                )}
                                {deployment.status === 'finished' && (
                                    <>
                                        <Button size="sm">
                                            <RotateCw className="mr-2 h-4 w-4" />
                                            Rollback
                                        </Button>
                                        <Button variant="secondary" size="sm">
                                            <Play className="mr-2 h-4 w-4" />
                                            Redeploy
                                        </Button>
                                    </>
                                )}
                                {deployment.status === 'failed' && (
                                    <Button size="sm">
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
