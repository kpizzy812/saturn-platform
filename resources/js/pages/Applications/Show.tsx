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
    History,
    AlertTriangle,
    Zap,
    Webhook,
    AlertCircle,
} from 'lucide-react';
import { useRealtimeStatus } from '@/hooks/useRealtimeStatus';
import { useApplicationMetrics } from '@/hooks/useApplicationMetrics';
import { getStatusLabel, getStatusVariant } from '@/lib/statusUtils';
import { CloneModal } from '@/components/transfer';
import type { Application, ApplicationStatus, Deployment, Environment, Project } from '@/types';

interface ApplicationWithRelations extends Application {
    project: Project;
    environment: Environment;
    recent_deployments?: Deployment[];
    environment_variables_count?: number;
    auto_deploy_status?: 'automatic' | 'manual_webhook' | 'not_configured';
    is_auto_deploy_enabled?: boolean;
    source_name?: string | null;
    webhook_url?: string;
    has_webhook_secret?: boolean;
}

interface Props {
    application: ApplicationWithRelations;
}

export default function ApplicationShow({ application: initialApplication }: Props) {
    const [application, setApplication] = useState<ApplicationWithRelations>(initialApplication);
    const [showEnvVars, setShowEnvVars] = useState(false);
    const [envVars, setEnvVars] = useState<Array<{ id: number; key: string; value: string; is_buildtime: boolean }>>([]);
    const [envVarsLoading, setEnvVarsLoading] = useState(false);
    const [envVarsLoaded, setEnvVarsLoaded] = useState(false);
    const [revealedVarIds, setRevealedVarIds] = useState<Set<number>>(new Set());
    const [showDeployModal, setShowDeployModal] = useState(false);
    const [requiresApproval, setRequiresApproval] = useState(false);
    const [forceRebuild, setForceRebuild] = useState(false);
    const [showCloneModal, setShowCloneModal] = useState(false);

    // Fetch real-time container metrics
    const isRunning = application.status?.startsWith('running') ?? false;
    const { metrics, isLoading: metricsLoading, error: metricsError } = useApplicationMetrics({
        applicationUuid: application.uuid,
        autoRefresh: isRunning,
        refreshInterval: 10000, // 10 seconds
        enabled: isRunning,
    });

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
            router.reload({ only: ['application'] });
        }, 5000);

        return () => clearInterval(interval);
    }, [application.recent_deployments]);

    // Update state when props change (from polling or WebSocket)
    useEffect(() => {
        setApplication(initialApplication);
    }, [initialApplication]);

    const handleToggleEnvVars = async () => {
        if (!showEnvVars && !envVarsLoaded) {
            setEnvVarsLoading(true);
            try {
                const response = await fetch(`/applications/${application.uuid}/envs/json`, {
                    headers: { 'Accept': 'application/json' },
                });
                if (response.ok) {
                    const data = await response.json();
                    setEnvVars(data);
                    setEnvVarsLoaded(true);
                }
            } catch {
                // Silently fail - user can retry
            } finally {
                setEnvVarsLoading(false);
            }
        }
        setShowEnvVars(!showEnvVars);
    };

    const handleToggleRevealVar = (id: number) => {
        setRevealedVarIds(prev => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    };

    const maskValue = (value: string) => '•'.repeat(Math.min(value.length || 8, 24));

    const handleAction = (action: 'start' | 'stop' | 'restart' | 'deploy') => {
        if (action === 'deploy') {
            setShowDeployModal(true);
            setRequiresApproval(false);
            setForceRebuild(false);
        } else {
            router.post(`/applications/${application.uuid}/${action}`, {}, {
                preserveScroll: true,
            });
        }
    };

    const handleForceRebuild = () => {
        setShowDeployModal(true);
        setRequiresApproval(false);
        setForceRebuild(true);
    };

    const confirmDeploy = () => {
        router.post(`/applications/${application.uuid}/deploy`, {
            force_rebuild: forceRebuild,
            requires_approval: requiresApproval,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setShowDeployModal(false);
                setRequiresApproval(false);
                setForceRebuild(false);
            },
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
            <div className="mx-auto max-w-7xl">
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
                                <DropdownItem onClick={handleForceRebuild}>
                                    <Rocket className="h-4 w-4" />
                                    Force Rebuild
                                </DropdownItem>
                                <DropdownItem onClick={() => router.visit(`/applications/${application.uuid}/rollback`)}>
                                    <History className="h-4 w-4" />
                                    Rollback
                                </DropdownItem>
                                <DropdownDivider />
                                {application.status?.startsWith('running') ? (
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
                                <DropdownItem onClick={() => setShowCloneModal(true)}>
                                    <Copy className="h-4 w-4" />
                                    Clone
                                </DropdownItem>
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
                                                href={application.fqdn.startsWith('http') ? application.fqdn : `https://${application.fqdn}`}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="flex items-center gap-2 text-primary hover:underline"
                                            >
                                                <Globe className="h-4 w-4" />
                                                {application.fqdn.replace(/^https?:\/\//, '')}
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
                                    icon={<AlertTriangle className="h-5 w-5" />}
                                    label="Incidents"
                                    onClick={() => router.visit(`/applications/${application.uuid}/incidents/view`)}
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
                        {/* Auto Deploy Status */}
                        <AutoDeployCard
                            status={application.auto_deploy_status || 'not_configured'}
                            enabled={application.is_auto_deploy_enabled ?? false}
                            sourceName={application.source_name}
                            gitBranch={application.git_branch}
                            applicationUuid={application.uuid}
                        />

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
                                        onClick={handleToggleEnvVars}
                                        className="rounded p-1.5 text-foreground-muted transition-colors hover:bg-background-tertiary hover:text-foreground"
                                        title={showEnvVars ? 'Hide variables' : 'Show variables'}
                                    >
                                        {envVarsLoading ? (
                                            <Loader2 className="h-4 w-4 animate-spin" />
                                        ) : showEnvVars ? (
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
                                    <Link href={`/applications/${application.uuid}/settings/variables`}>
                                        <Button variant="outline" size="sm">
                                            Manage
                                        </Button>
                                    </Link>
                                </div>
                                {showEnvVars && envVarsLoaded && (
                                    <div className="mt-4 space-y-1.5">
                                        {envVars.length === 0 ? (
                                            <p className="text-xs text-foreground-muted py-2">No variables configured</p>
                                        ) : (
                                            envVars.map((v) => (
                                                <div
                                                    key={v.id}
                                                    className="flex items-center gap-2 rounded-md bg-background-secondary px-3 py-2 font-mono text-xs"
                                                >
                                                    <span className="font-semibold text-foreground shrink-0">{v.key}</span>
                                                    <span className="text-foreground-muted">=</span>
                                                    <span className="text-foreground-muted truncate min-w-0 flex-1">
                                                        {revealedVarIds.has(v.id) ? v.value : maskValue(v.value)}
                                                    </span>
                                                    <button
                                                        onClick={() => handleToggleRevealVar(v.id)}
                                                        className="shrink-0 rounded p-1 text-foreground-muted transition-colors hover:text-foreground"
                                                        title={revealedVarIds.has(v.id) ? 'Hide value' : 'Show value'}
                                                    >
                                                        {revealedVarIds.has(v.id) ? (
                                                            <EyeOff className="h-3.5 w-3.5" />
                                                        ) : (
                                                            <Eye className="h-3.5 w-3.5" />
                                                        )}
                                                    </button>
                                                    <button
                                                        onClick={() => navigator.clipboard.writeText(v.value)}
                                                        className="shrink-0 rounded p-1 text-foreground-muted transition-colors hover:text-foreground"
                                                        title="Copy value"
                                                    >
                                                        <Copy className="h-3.5 w-3.5" />
                                                    </button>
                                                </div>
                                            ))
                                        )}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Resource Usage */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    Resource Usage
                                    {metricsLoading && isRunning && (
                                        <Loader2 className="h-4 w-4 animate-spin text-foreground-muted" />
                                    )}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {!isRunning ? (
                                    <div className="text-center py-4 text-foreground-muted">
                                        <Activity className="h-8 w-8 mx-auto mb-2 opacity-50" />
                                        <p className="text-sm">Container is not running</p>
                                    </div>
                                ) : metricsError ? (
                                    <div className="text-center py-4 text-foreground-muted">
                                        <XCircle className="h-8 w-8 mx-auto mb-2 text-danger opacity-50" />
                                        <p className="text-sm">{metricsError}</p>
                                    </div>
                                ) : metrics ? (
                                    <>
                                        <ResourceBarWithPercent
                                            label="CPU"
                                            percent={metrics.cpu.percent}
                                            formatted={metrics.cpu.formatted}
                                        />
                                        <ResourceBarWithPercent
                                            label="Memory"
                                            percent={metrics.memory.percent}
                                            formatted={`${metrics.memory.used} / ${metrics.memory.limit}`}
                                        />
                                        <div className="pt-2 border-t border-border">
                                            <div className="grid grid-cols-2 gap-4 text-sm">
                                                <div>
                                                    <span className="text-foreground-muted">Network In:</span>
                                                    <span className="ml-2 font-medium">{metrics.network.rx}</span>
                                                </div>
                                                <div>
                                                    <span className="text-foreground-muted">Network Out:</span>
                                                    <span className="ml-2 font-medium">{metrics.network.tx}</span>
                                                </div>
                                                <div>
                                                    <span className="text-foreground-muted">Disk Read:</span>
                                                    <span className="ml-2 font-medium">{metrics.disk.read}</span>
                                                </div>
                                                <div>
                                                    <span className="text-foreground-muted">Disk Write:</span>
                                                    <span className="ml-2 font-medium">{metrics.disk.write}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </>
                                ) : (
                                    <div className="text-center py-4 text-foreground-muted">
                                        <Loader2 className="h-8 w-8 mx-auto mb-2 animate-spin opacity-50" />
                                        <p className="text-sm">Loading metrics...</p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            {/* Deploy Confirmation Modal */}
            {showDeployModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                    <div className="bg-background border rounded-lg shadow-lg p-6 max-w-md w-full">
                        <h3 className="text-lg font-semibold mb-4">
                            {forceRebuild ? 'Force Rebuild & Deploy' : 'Deploy Application'}
                        </h3>
                        <p className="text-sm text-foreground-muted mb-4">
                            {forceRebuild
                                ? 'This will rebuild the application from scratch and deploy it.'
                                : 'This will deploy the latest version of your application.'}
                        </p>

                        <div className="mb-4">
                            <label className="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={requiresApproval}
                                    onChange={(e) => setRequiresApproval(e.target.checked)}
                                    className="h-4 w-4 rounded border-border text-primary focus:ring-2 focus:ring-primary focus:ring-offset-2"
                                />
                                <span className="text-sm font-medium">Require approval before deployment</span>
                            </label>
                            <p className="text-xs text-foreground-muted mt-1 ml-6">
                                Deployment will wait for manual approval from an admin before proceeding
                            </p>
                        </div>

                        <div className="flex gap-2 justify-end">
                            <Button
                                variant="ghost"
                                onClick={() => {
                                    setShowDeployModal(false);
                                    setRequiresApproval(false);
                                    setForceRebuild(false);
                                }}
                            >
                                Cancel
                            </Button>
                            <Button
                                variant="default"
                                onClick={confirmDeploy}
                            >
                                {forceRebuild ? 'Force Rebuild' : 'Deploy'}
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            {/* Clone Modal */}
            <CloneModal
                isOpen={showCloneModal}
                onClose={() => setShowCloneModal(false)}
                resource={application}
                resourceType="application"
            />
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
        pending_approval: <Clock className="h-4 w-4 text-warning" />,
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
            <Badge variant={getStatusVariant(deployment.status)}>
                {getStatusLabel(deployment.status)}
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

interface AutoDeployCardProps {
    status: 'automatic' | 'manual_webhook' | 'not_configured';
    enabled: boolean;
    sourceName?: string | null;
    gitBranch?: string | null;
    applicationUuid: string;
}

function AutoDeployCard({ status, enabled, sourceName, gitBranch, applicationUuid }: AutoDeployCardProps) {
    const statusConfig = {
        automatic: {
            borderClass: 'border-green-500/50',
            bgClass: 'bg-green-500/10',
            iconBg: 'bg-green-500/20',
            iconColor: 'text-green-500',
            icon: <Zap className="h-4 w-4" />,
            title: 'Auto Deploy Active',
            description: enabled
                ? `Pushes to ${gitBranch || 'main'} trigger deploys automatically`
                : 'Configured but currently disabled',
        },
        manual_webhook: {
            borderClass: 'border-amber-500/50',
            bgClass: 'bg-amber-500/10',
            iconBg: 'bg-amber-500/20',
            iconColor: 'text-amber-500',
            icon: <Webhook className="h-4 w-4" />,
            title: 'Manual Webhook',
            description: enabled
                ? 'Deploys via webhook — configure in your Git provider'
                : 'Webhook configured but auto-deploy disabled',
        },
        not_configured: {
            borderClass: 'border-border',
            bgClass: '',
            iconBg: 'bg-foreground-muted/20',
            iconColor: 'text-foreground-muted',
            icon: <AlertCircle className="h-4 w-4" />,
            title: 'Auto Deploy Not Set Up',
            description: 'Connect a GitHub App or set up webhooks for automatic deploys',
        },
    };

    const config = statusConfig[status];

    return (
        <Card className={`${config.borderClass} ${config.bgClass}`}>
            <CardContent className="p-4">
                <div className="flex items-start gap-3">
                    <div className={`flex h-8 w-8 items-center justify-center rounded-lg ${config.iconBg} ${config.iconColor} shrink-0`}>
                        {config.icon}
                    </div>
                    <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2">
                            <h3 className="text-sm font-semibold text-foreground">{config.title}</h3>
                            {status === 'automatic' && enabled && (
                                <span className="flex h-2 w-2 rounded-full bg-green-500 animate-pulse" />
                            )}
                        </div>
                        <p className="text-xs text-foreground-muted mt-0.5">{config.description}</p>
                        {sourceName && status === 'automatic' && (
                            <p className="text-xs text-foreground-subtle mt-1">
                                via {sourceName}
                            </p>
                        )}
                    </div>
                </div>
                {status === 'not_configured' && (
                    <div className="mt-3 pt-3 border-t border-border">
                        <Link
                            href={`/applications/${applicationUuid}/settings`}
                            className="inline-flex items-center gap-1.5 text-xs font-medium text-primary hover:underline"
                        >
                            <Settings className="h-3.5 w-3.5" />
                            Set up auto deploy
                        </Link>
                    </div>
                )}
                {status !== 'not_configured' && !enabled && (
                    <div className="mt-3 pt-3 border-t border-border">
                        <Link
                            href={`/applications/${applicationUuid}/settings`}
                            className="inline-flex items-center gap-1.5 text-xs font-medium text-primary hover:underline"
                        >
                            <Settings className="h-3.5 w-3.5" />
                            Enable in settings
                        </Link>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

interface ResourceBarWithPercentProps {
    label: string;
    percent: number;
    formatted: string;
}

function ResourceBarWithPercent({ label, percent, formatted }: ResourceBarWithPercentProps) {
    // Color based on usage level
    const getBarColor = (pct: number) => {
        if (pct >= 90) return 'bg-danger';
        if (pct >= 70) return 'bg-warning';
        return 'bg-primary';
    };

    return (
        <div>
            <div className="flex items-center justify-between mb-2">
                <span className="text-sm text-foreground-muted">{label}</span>
                <span className="text-sm font-medium text-foreground">{formatted}</span>
            </div>
            <div className="h-2 w-full rounded-full bg-background-tertiary overflow-hidden">
                <div
                    className={`h-full rounded-full transition-all ${getBarColor(percent)}`}
                    style={{ width: `${Math.min(percent, 100)}%` }}
                />
            </div>
        </div>
    );
}
