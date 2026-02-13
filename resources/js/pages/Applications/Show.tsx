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
    Plus,
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

function AutoDeployCard({ status: initialStatus, enabled: initialEnabled, sourceName: initialSourceName, gitBranch, applicationUuid }: AutoDeployCardProps) {
    const [enabled, setEnabled] = useState(initialEnabled);
    const [status, setStatus] = useState(initialStatus);
    const [sourceName, setSourceName] = useState(initialSourceName);
    const [isToggling, setIsToggling] = useState(false);
    const [webhookUrl, setWebhookUrl] = useState<string | null>(null);
    const [webhookSecret, setWebhookSecret] = useState<string | null>(null);
    const [showSecret, setShowSecret] = useState(false);
    const [copied, setCopied] = useState<string | null>(null);
    const [githubApps, setGithubApps] = useState<Array<{ id: number; name: string; organization: string | null }>>([]);
    const [showAppSelector, setShowAppSelector] = useState(false);
    const [isLinking, setIsLinking] = useState(false);
    const [detailsLoaded, setDetailsLoaded] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';

    // Fetch full details on mount (webhook URL, secret, github apps)
    useEffect(() => {
        const loadDetails = async () => {
            try {
                const [appRes, ghRes] = await Promise.all([
                    fetch(`/web-api/applications/${applicationUuid}`, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'include',
                    }),
                    fetch('/web-api/github-apps/active', {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'include',
                    }),
                ]);
                if (appRes.ok) {
                    const data = await appRes.json();
                    setWebhookUrl(data.webhook_url || null);
                    setWebhookSecret(data.manual_webhook_secret_github || null);
                    setStatus(data.auto_deploy_status || 'not_configured');
                    setEnabled(data.is_auto_deploy_enabled ?? false);
                    setSourceName(data.source_info?.name || null);
                }
                if (ghRes.ok) {
                    const data = await ghRes.json();
                    setGithubApps(data.github_apps || []);
                }
                setDetailsLoaded(true);
            } catch {
                setDetailsLoaded(true);
                setError('Failed to load auto-deploy details');
            }
        };
        loadDetails();
    }, [applicationUuid]);

    const handleToggle = async () => {
        if (isToggling) return;
        const newValue = !enabled;
        setIsToggling(true);
        setEnabled(newValue);
        setError(null);
        try {
            const res = await fetch(`/web-api/applications/${applicationUuid}`, {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'include',
                body: JSON.stringify({ is_auto_deploy_enabled: newValue }),
            });
            if (!res.ok) {
                setEnabled(!newValue);
                setError('Failed to update auto-deploy');
            }
        } catch {
            setEnabled(!newValue);
            setError('Failed to update auto-deploy');
        } finally {
            setIsToggling(false);
        }
    };

    const handleLinkGithubApp = async (appId: number) => {
        setIsLinking(true);
        setError(null);
        try {
            const res = await fetch(`/web-api/applications/${applicationUuid}`, {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'include',
                body: JSON.stringify({ github_app_id: appId }),
            });
            if (res.ok) {
                // Re-fetch to get updated status
                const appRes = await fetch(`/web-api/applications/${applicationUuid}`, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'include',
                });
                if (appRes.ok) {
                    const data = await appRes.json();
                    setStatus(data.auto_deploy_status || 'not_configured');
                    setSourceName(data.source_info?.name || null);
                    setEnabled(data.is_auto_deploy_enabled ?? false);
                    setWebhookUrl(data.webhook_url || null);
                    setWebhookSecret(data.manual_webhook_secret_github || null);
                }
                setShowAppSelector(false);
            } else {
                const data = await res.json().catch(() => ({}));
                setError(data.message || 'Failed to connect GitHub App');
            }
        } catch {
            setError('Failed to connect GitHub App');
        } finally {
            setIsLinking(false);
        }
    };

    const copyText = (text: string, field: string) => {
        navigator.clipboard.writeText(text);
        setCopied(field);
        setTimeout(() => setCopied(null), 2000);
    };

    const statusConfig = {
        automatic: {
            borderClass: 'border-green-500/50',
            bgClass: 'bg-green-500/10',
            iconBg: 'bg-green-500/20',
            iconColor: 'text-green-500',
            icon: <Zap className="h-4 w-4" />,
            title: 'Auto Deploy',
        },
        manual_webhook: {
            borderClass: 'border-amber-500/50',
            bgClass: 'bg-amber-500/10',
            iconBg: 'bg-amber-500/20',
            iconColor: 'text-amber-500',
            icon: <Webhook className="h-4 w-4" />,
            title: 'Auto Deploy',
        },
        not_configured: {
            borderClass: 'border-border',
            bgClass: '',
            iconBg: 'bg-foreground-muted/20',
            iconColor: 'text-foreground-muted',
            icon: <AlertCircle className="h-4 w-4" />,
            title: 'Auto Deploy',
        },
    };

    const config = statusConfig[status];

    return (
        <Card className={`${config.borderClass} ${config.bgClass}`}>
            <CardContent className="p-4">
                {/* Header with toggle */}
                <div className="flex items-center justify-between mb-3">
                    <div className="flex items-center gap-2">
                        <div className={`flex h-7 w-7 items-center justify-center rounded-md ${config.iconBg} ${config.iconColor} shrink-0`}>
                            {config.icon}
                        </div>
                        <h3 className="text-sm font-semibold text-foreground">{config.title}</h3>
                        {status === 'automatic' && enabled && (
                            <span className="flex h-2 w-2 rounded-full bg-green-500 animate-pulse" />
                        )}
                    </div>
                    <button
                        onClick={handleToggle}
                        disabled={isToggling}
                        role="switch"
                        aria-checked={enabled}
                        aria-label="Toggle auto-deploy"
                        className={`relative h-5 w-9 rounded-full transition-colors ${enabled ? 'bg-primary' : 'bg-gray-600'} ${isToggling ? 'opacity-50' : ''}`}
                    >
                        <span className={`absolute top-0.5 h-4 w-4 rounded-full bg-white transition-all ${enabled ? 'left-[18px]' : 'left-0.5'}`} />
                    </button>
                </div>

                {/* Error message */}
                {error && (
                    <div className="mb-3 rounded bg-red-500/10 border border-red-500/30 px-3 py-2 text-xs text-red-400">
                        {error}
                    </div>
                )}

                {/* Status description */}
                <p className="text-xs text-foreground-muted mb-3">
                    {status === 'automatic' && enabled && `Pushes to ${gitBranch || 'main'} auto-deploy via ${sourceName || 'GitHub App'}`}
                    {status === 'automatic' && !enabled && 'GitHub App connected, toggle on to activate'}
                    {status === 'manual_webhook' && enabled && 'Active via webhook — configure in your Git provider'}
                    {status === 'manual_webhook' && !enabled && 'Webhook ready, toggle on to activate'}
                    {status === 'not_configured' && 'Connect GitHub App for automatic deploys'}
                </p>

                {/* Webhook details for manual_webhook mode */}
                {status === 'manual_webhook' && detailsLoaded && (
                    <div className="space-y-2">
                        {webhookUrl && (
                            <div className="rounded bg-background/60 px-3 py-2">
                                <p className="text-[10px] uppercase tracking-wider text-foreground-subtle mb-1">Webhook URL</p>
                                <div className="flex items-center gap-1.5">
                                    <code className="flex-1 truncate text-xs text-foreground">{webhookUrl}</code>
                                    <button onClick={() => copyText(webhookUrl, 'url')} className="shrink-0 rounded p-1 text-foreground-muted hover:text-foreground">
                                        {copied === 'url' ? <CheckCircle2 className="h-3.5 w-3.5 text-green-500" /> : <Copy className="h-3.5 w-3.5" />}
                                    </button>
                                </div>
                            </div>
                        )}
                        {webhookSecret && (
                            <div className="rounded bg-background/60 px-3 py-2">
                                <p className="text-[10px] uppercase tracking-wider text-foreground-subtle mb-1">Secret</p>
                                <div className="flex items-center gap-1.5">
                                    <code className="flex-1 truncate text-xs text-foreground">
                                        {showSecret ? webhookSecret : '\u2022'.repeat(20)}
                                    </code>
                                    <button onClick={() => setShowSecret(!showSecret)} className="shrink-0 rounded p-1 text-foreground-muted hover:text-foreground">
                                        {showSecret ? <EyeOff className="h-3.5 w-3.5" /> : <Eye className="h-3.5 w-3.5" />}
                                    </button>
                                    <button onClick={() => copyText(webhookSecret, 'secret')} className="shrink-0 rounded p-1 text-foreground-muted hover:text-foreground">
                                        {copied === 'secret' ? <CheckCircle2 className="h-3.5 w-3.5 text-green-500" /> : <Copy className="h-3.5 w-3.5" />}
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {/* GitHub App connector for not_configured or manual_webhook upgrade */}
                {(status === 'not_configured' || status === 'manual_webhook') && githubApps.length > 0 && detailsLoaded && (
                    <div className={status === 'manual_webhook' ? 'mt-3 pt-3 border-t border-border/50' : ''}>
                        {status === 'manual_webhook' && (
                            <p className="text-[10px] uppercase tracking-wider text-foreground-subtle mb-2">Upgrade to automatic</p>
                        )}
                        <button
                            onClick={() => setShowAppSelector(!showAppSelector)}
                            disabled={isLinking}
                            className="flex w-full items-center justify-between rounded-md border border-border bg-background/60 px-3 py-2 text-xs text-foreground hover:bg-background-secondary transition-colors"
                        >
                            <span>{isLinking ? 'Connecting...' : 'Connect GitHub App'}</span>
                            {isLinking ? (
                                <Loader2 className="h-3.5 w-3.5 animate-spin" />
                            ) : (
                                <Zap className="h-3.5 w-3.5 text-foreground-muted" />
                            )}
                        </button>
                        {showAppSelector && !isLinking && (
                            <div className="mt-1.5 rounded-md border border-border bg-background shadow-lg overflow-hidden">
                                {githubApps.map((app) => (
                                    <div
                                        key={app.id}
                                        className="flex w-full items-center gap-2.5 px-3 py-2 text-xs"
                                    >
                                        <div className="flex h-6 w-6 items-center justify-center rounded bg-[#24292e] shrink-0">
                                            <svg viewBox="0 0 24 24" className="h-3.5 w-3.5" fill="#fff">
                                                <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                                            </svg>
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <p className="font-medium text-foreground truncate">{app.name}</p>
                                            {app.organization && <p className="text-foreground-muted truncate">{app.organization}</p>}
                                        </div>
                                        <button
                                            onClick={() => handleLinkGithubApp(app.id)}
                                            className="shrink-0 rounded-md bg-primary px-3 py-1 text-xs font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
                                        >
                                            Connect
                                        </button>
                                    </div>
                                ))}
                                <a
                                    href="/sources/github/create"
                                    className="flex w-full items-center gap-2.5 border-t border-border px-3 py-2 text-xs hover:bg-background-secondary transition-colors"
                                >
                                    <div className="flex h-6 w-6 items-center justify-center rounded border border-dashed border-foreground-muted/40 shrink-0">
                                        <Plus className="h-3.5 w-3.5 text-foreground-muted" />
                                    </div>
                                    <span className="text-foreground-muted">Add new GitHub App</span>
                                </a>
                            </div>
                        )}
                    </div>
                )}

                {/* No GitHub Apps available hint */}
                {status === 'not_configured' && githubApps.length === 0 && detailsLoaded && (
                    <a
                        href="/sources"
                        className="inline-flex items-center gap-1.5 text-xs text-primary hover:underline"
                    >
                        <Settings className="h-3 w-3" />
                        Configure GitHub App in Sources
                    </a>
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
