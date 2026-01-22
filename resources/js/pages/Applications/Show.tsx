import { useState, useEffect } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, CardHeader, CardTitle, Button, Badge, StatusBadge } from '@/components/ui';
import { Dropdown, DropdownTrigger, DropdownContent, DropdownItem, DropdownDivider } from '@/components/ui/Dropdown';
import {
    Rocket,
    Play,
    Square,
    RotateCw,
    MoreVertical,
    GitBranch,
    Globe,
    Server as ServerIcon,
    Database as DatabaseIcon,
    Clock,
    Activity,
    Settings,
    Terminal,
    FileText,
    ExternalLink,
    Eye,
    EyeOff,
    Copy,
    CheckCircle2,
    XCircle,
    Loader2,
} from 'lucide-react';
import { useRealtimeStatus } from '@/hooks/useRealtimeStatus';
import type { Application, ApplicationStatus, Deployment, Environment, Project } from '@/types';

interface ApplicationWithRelations extends Application {
    project: Project;
    environment: Environment;
    recent_deployments?: Deployment[];
    environment_variables_count?: number;
}

interface Props {
    application: ApplicationWithRelations;
}

export default function ApplicationShow({ application: initialApplication }: Props) {
    const [application, setApplication] = useState<ApplicationWithRelations>(initialApplication);
    const [showEnvVars, setShowEnvVars] = useState(false);

    // Real-time status updates via WebSocket
    useRealtimeStatus({
        onApplicationStatusChange: (data) => {
            if (data.applicationId === application.id) {
                setApplication(prev => ({ ...prev, status: data.status }));
            }
        },
        onDeploymentCreated: (data) => {
            if (data.applicationId === application.id) {
                router.reload({ only: ['application'] });
            }
        },
        onDeploymentFinished: (data) => {
            if (data.applicationId === application.id) {
                router.reload({ only: ['application'] });
            }
        },
    });

    // Polling fallback for real-time updates when WebSocket is not available
    // or when there's an active deployment
    useEffect(() => {
        const hasActiveDeployment = application.recent_deployments?.some(
            (d) => d.status === 'in_progress' || d.status === 'queued'
        );

        // Poll more frequently when there's an active deployment
        if (!hasActiveDeployment) return;

        const interval = setInterval(() => {
            router.reload({ only: ['application'], preserveScroll: true });
        }, 5000);

        return () => clearInterval(interval);
    }, [application.recent_deployments]);

    // Update state when props change (from polling or WebSocket)
    useEffect(() => {
        setApplication(initialApplication);
    }, [initialApplication]);

    const handleAction = (action: 'start' | 'stop' | 'restart' | 'deploy') => {
        router.post(`/applications/${application.uuid}/${action}`, {}, {
            preserveScroll: true,
        });
    };

    const recentDeployments = application.recent_deployments || [];
    const hasDeployments = recentDeployments.length > 0;

    return (
        <AppLayout
            title={application.name}
            breadcrumbs={[
                { label: 'Applications', href: '/applications' },
                { label: application.name },
            ]}
        >
            <div className="mx-auto max-w-7xl px-6 py-8">
                {/* Header */}
                <div className="mb-6 flex items-start justify-between">
                    <div className="flex items-start gap-4">
                        <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                            <Rocket className="h-6 w-6 text-primary" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold text-foreground">{application.name}</h1>
                            <div className="mt-1 flex items-center gap-2 text-sm text-foreground-muted">
                                <Link
                                    href={`/projects/${application.project.uuid}`}
                                    className="hover:text-foreground hover:underline"
                                >
                                    {application.project.name}
                                </Link>
                                <span>/</span>
                                <span>{application.environment.name}</span>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => handleAction('deploy')}
                        >
                            <Rocket className="mr-2 h-4 w-4" />
                            Deploy
                        </Button>
                        <Dropdown>
                            <DropdownTrigger>
                                <Button variant="outline" size="sm">
                                    <MoreVertical className="h-4 w-4" />
                                </Button>
                            </DropdownTrigger>
                            <DropdownContent align="right">
                                <DropdownItem onClick={() => handleAction('restart')}>
                                    <RotateCw className="h-4 w-4" />
                                    Restart
                                </DropdownItem>
                                <DropdownDivider />
                                {application.status === 'running' ? (
                                    <DropdownItem onClick={() => handleAction('stop')}>
                                        <Square className="h-4 w-4" />
                                        Stop
                                    </DropdownItem>
                                ) : (
                                    <DropdownItem onClick={() => handleAction('start')}>
                                        <Play className="h-4 w-4" />
                                        Start
                                    </DropdownItem>
                                )}
                                <DropdownDivider />
                                <DropdownItem onClick={() => router.visit(`/applications/${application.uuid}/settings`)}>
                                    <Settings className="h-4 w-4" />
                                    Settings
                                </DropdownItem>
                                <DropdownItem onClick={() => router.visit(`/applications/${application.uuid}/terminal`)}>
                                    <Terminal className="h-4 w-4" />
                                    Terminal
                                </DropdownItem>
                                <DropdownItem onClick={() => router.visit(`/applications/${application.uuid}/logs`)}>
                                    <FileText className="h-4 w-4" />
                                    Logs
                                </DropdownItem>
                            </DropdownContent>
                        </Dropdown>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Main Content */}
                    <div className="space-y-6 lg:col-span-2">
                        {/* Status Card */}
                        <Card>
                            <CardContent className="p-6">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm text-foreground-muted mb-2">Status</p>
                                        <StatusBadge status={application.status} size="lg" />
                                    </div>
                                    {application.fqdn && (
                                        <div className="text-right">
                                            <p className="text-sm text-foreground-muted mb-2">Domain</p>
                                            <a
                                                href={`https://${application.fqdn}`}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="flex items-center gap-2 text-primary hover:underline"
                                            >
                                                <Globe className="h-4 w-4" />
                                                {application.fqdn}
                                                <ExternalLink className="h-3 w-3" />
                                            </a>
                                        </div>
                                    )}
                                </div>

                                {application.description && (
                                    <p className="mt-4 text-sm text-foreground-muted">
                                        {application.description}
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        {/* Recent Deployments */}
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <CardTitle>Recent Deployments</CardTitle>
                                    <Link href={`/applications/${application.uuid}/deployments`}>
                                        <Button variant="outline" size="sm">
                                            View All
                                        </Button>
                                    </Link>
                                </div>
                            </CardHeader>
                            <CardContent>
                                {!hasDeployments ? (
                                    <div className="py-8 text-center">
                                        <Rocket className="mx-auto h-8 w-8 text-foreground-muted mb-3" />
                                        <p className="text-sm text-foreground-muted">No deployments yet</p>
                                        <Button
                                            size="sm"
                                            className="mt-4"
                                            onClick={() => handleAction('deploy')}
                                        >
                                            Deploy Now
                                        </Button>
                                    </div>
                                ) : (
                                    <div className="space-y-3">
                                        {recentDeployments.slice(0, 5).map((deployment) => (
                                            <DeploymentItem
                                                key={deployment.id}
                                                deployment={deployment}
                                                applicationUuid={application.uuid}
                                            />
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Quick Actions */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Quick Actions</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-3 sm:grid-cols-2">
                                <ActionButton
                                    icon={<Terminal className="h-5 w-5" />}
                                    label="Terminal"
                                    onClick={() => router.visit(`/applications/${application.uuid}/terminal`)}
                                />
                                <ActionButton
                                    icon={<FileText className="h-5 w-5" />}
                                    label="View Logs"
                                    onClick={() => router.visit(`/applications/${application.uuid}/logs`)}
                                />
                                <ActionButton
                                    icon={<Activity className="h-5 w-5" />}
                                    label="Metrics"
                                    onClick={() => router.visit(`/applications/${application.uuid}/metrics`)}
                                />
                                <ActionButton
                                    icon={<Settings className="h-5 w-5" />}
                                    label="Settings"
                                    onClick={() => router.visit(`/applications/${application.uuid}/settings`)}
                                />
                            </CardContent>
                        </Card>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        {/* Application Info */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Information</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <InfoItem
                                    icon={<GitBranch className="h-4 w-4" />}
                                    label="Repository"
                                    value={application.git_repository || 'N/A'}
                                />
                                <InfoItem
                                    icon={<GitBranch className="h-4 w-4" />}
                                    label="Branch"
                                    value={application.git_branch || 'N/A'}
                                />
                                <InfoItem
                                    icon={<DatabaseIcon className="h-4 w-4" />}
                                    label="Build Pack"
                                    value={
                                        <Badge variant="outline" className="text-xs">
                                            {application.build_pack}
                                        </Badge>
                                    }
                                />
                                <InfoItem
                                    icon={<Clock className="h-4 w-4" />}
                                    label="Created"
                                    value={new Date(application.created_at).toLocaleDateString()}
                                />
                                <InfoItem
                                    icon={<Clock className="h-4 w-4" />}
                                    label="Updated"
                                    value={new Date(application.updated_at).toLocaleDateString()}
                                />
                            </CardContent>
                        </Card>

                        {/* Environment Variables Preview */}
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <CardTitle>Environment Variables</CardTitle>
                                    <button
                                        onClick={() => setShowEnvVars(!showEnvVars)}
                                        className="text-sm text-primary hover:underline"
                                    >
                                        {showEnvVars ? (
                                            <EyeOff className="h-4 w-4" />
                                        ) : (
                                            <Eye className="h-4 w-4" />
                                        )}
                                    </button>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="flex items-center justify-between">
                                    <p className="text-sm text-foreground-muted">
                                        {application.environment_variables_count || 0} variables configured
                                    </p>
                                    <Link href={`/applications/${application.uuid}/environment-variables`}>
                                        <Button variant="outline" size="sm">
                                            Manage
                                        </Button>
                                    </Link>
                                </div>
                                {showEnvVars && (
                                    <div className="mt-4 rounded-lg bg-background-secondary p-3">
                                        <p className="text-xs text-foreground-muted">
                                            Click "Manage" to view and edit environment variables
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Resource Usage (Placeholder) */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Resource Usage</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <ResourceBar label="CPU" value={45} max={100} unit="%" />
                                <ResourceBar label="Memory" value={512} max={1024} unit="MB" />
                                <ResourceBar label="Disk" value={2.3} max={10} unit="GB" />
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

interface DeploymentItemProps {
    deployment: Deployment;
    applicationUuid: string;
}

function DeploymentItem({ deployment, applicationUuid }: DeploymentItemProps) {
    const statusIcons = {
        queued: <Clock className="h-4 w-4 text-foreground-muted" />,
        in_progress: <Loader2 className="h-4 w-4 text-warning animate-spin" />,
        finished: <CheckCircle2 className="h-4 w-4 text-success" />,
        failed: <XCircle className="h-4 w-4 text-destructive" />,
        cancelled: <XCircle className="h-4 w-4 text-foreground-muted" />,
    };

    // Use deployment_uuid if available (from backend), otherwise fallback to uuid
    const deploymentUuid = (deployment as { deployment_uuid?: string }).deployment_uuid || deployment.uuid;

    return (
        <Link
            href={`/applications/${applicationUuid}/deployments/${deploymentUuid}`}
            className="flex items-center justify-between rounded-lg border border-border p-3 transition-colors hover:border-primary/50 hover:bg-background-secondary"
        >
            <div className="flex items-center gap-3 flex-1 min-w-0">
                {statusIcons[deployment.status]}
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-foreground truncate">
                        {deployment.commit_message || 'Manual deployment'}
                    </p>
                    <p className="text-xs text-foreground-muted">
                        {new Date(deployment.created_at).toLocaleString()}
                    </p>
                </div>
            </div>
            <Badge variant={deployment.status === 'finished' ? 'success' : deployment.status === 'failed' ? 'destructive' : 'default'}>
                {deployment.status}
            </Badge>
        </Link>
    );
}

interface ActionButtonProps {
    icon: React.ReactNode;
    label: string;
    onClick: () => void;
}

function ActionButton({ icon, label, onClick }: ActionButtonProps) {
    return (
        <button
            onClick={onClick}
            className="flex items-center gap-3 rounded-lg border border-border p-3 transition-colors hover:border-primary/50 hover:bg-background-secondary"
        >
            <div className="text-foreground-muted">{icon}</div>
            <span className="text-sm font-medium text-foreground">{label}</span>
        </button>
    );
}

interface InfoItemProps {
    icon: React.ReactNode;
    label: string;
    value: React.ReactNode;
}

function InfoItem({ icon, label, value }: InfoItemProps) {
    return (
        <div className="flex items-start gap-3">
            <div className="text-foreground-muted mt-0.5">{icon}</div>
            <div className="flex-1 min-w-0">
                <p className="text-xs text-foreground-muted">{label}</p>
                <div className="mt-1 text-sm font-medium text-foreground break-words">
                    {value}
                </div>
            </div>
        </div>
    );
}

interface ResourceBarProps {
    label: string;
    value: number;
    max: number;
    unit: string;
}

function ResourceBar({ label, value, max, unit }: ResourceBarProps) {
    const percentage = (value / max) * 100;

    return (
        <div>
            <div className="flex items-center justify-between mb-2">
                <span className="text-sm text-foreground-muted">{label}</span>
                <span className="text-sm font-medium text-foreground">
                    {value} {unit} / {max} {unit}
                </span>
            </div>
            <div className="h-2 w-full rounded-full bg-background-tertiary overflow-hidden">
                <div
                    className="h-full rounded-full bg-primary transition-all"
                    style={{ width: `${percentage}%` }}
                />
            </div>
        </div>
    );
}
