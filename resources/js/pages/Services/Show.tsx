import { useState, useEffect } from 'react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Badge, Button, Tabs, useConfirm } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import { useRealtimeStatus } from '@/hooks/useRealtimeStatus';
import { Link, router } from '@inertiajs/react';
import {
    Play, RotateCw, Trash2, Settings, Activity,
    Cpu, MemoryStick, Network, GitCommit, Clock,
    CheckCircle, XCircle, AlertCircle, ArrowLeft, Copy
} from 'lucide-react';
import type { Service, ServiceContainer } from '@/types';
import { getStatusLabel, getStatusVariant } from '@/lib/statusUtils';
import { CloneModal } from '@/components/transfer';
import { formatRelativeTime, formatBytes } from '@/lib/utils';

// Map activity log status to deployment status
function mapDeploymentStatus(status?: string): 'finished' | 'failed' | 'in_progress' | 'queued' {
    if (!status) return 'finished';
    const s = status.toLowerCase();
    if (s === 'failed' || s === 'error') return 'failed';
    if (s === 'in_progress' || s === 'running' || s === 'deploying') return 'in_progress';
    if (s === 'queued' || s === 'pending') return 'queued';
    return 'finished';
}
import { DeploymentsTab } from './Deployments';
import { LogsTab } from './Logs';
import { VariablesTab } from './Variables';
import { SettingsTab } from './Settings';
import { RollbacksTab } from './Rollbacks';
import { DomainsTab } from './Domains';

interface Props {
    service: Service;
    containers?: ServiceContainer[];
}

function extractDomains(service: Service) {
    if (!service.applications) return [];
    return service.applications
        .filter((app) => app.fqdn)
        .flatMap((app, _appIdx) =>
            (app.fqdn ?? '').split(',').map((fqdn, idx) => {
                const trimmed = fqdn.trim();
                // Strip protocol and port for display
                const domain = trimmed.replace(/^https?:\/\//, '').replace(/:\d+$/, '');
                return {
                    id: app.id * 100 + idx,
                    domain,
                    isPrimary: idx === 0,
                    sslStatus: (trimmed.startsWith('https://') ? 'active' : 'none') as 'active' | 'pending' | 'failed' | 'none',
                    sslProvider: (trimmed.startsWith('https://') ? 'letsencrypt' : null) as 'letsencrypt' | 'custom' | null,
                    createdAt: app.created_at,
                };
            })
        );
}

export default function ServiceShow({ service, containers = [] }: Props) {
    const confirm = useConfirm();
    const [isDeploying, setIsDeploying] = useState(false);
    const [isRestarting, setIsRestarting] = useState(false);
    const [currentStatus, setCurrentStatus] = useState(service?.status || 'running');
    const [showCloneModal, setShowCloneModal] = useState(false);
    const { addToast } = useToast();

    // Real-time service status updates
    useRealtimeStatus({
        onServiceStatusChange: () => {
            // Reload page data when service status changes
            router.reload({ only: ['service', 'containers'] });
        },
    });

    // Sync local state when service prop changes
    useEffect(() => {
        setCurrentStatus(service?.status || 'running');
    }, [service?.status]);

    // Show loading state if service is not available
    if (!service) {
        return (
            <AppLayout title="Loading...">
                <div className="flex h-96 items-center justify-center">
                    <div className="text-center">
                        <div className="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-primary border-t-transparent" />
                        <p className="mt-4 text-foreground-muted">Loading service...</p>
                    </div>
                </div>
            </AppLayout>
        );
    }

    const status = currentStatus;

    const handleRedeploy = () => {
        setIsDeploying(true);
        router.post(`/api/v1/services/${service.uuid}/start`, {}, {
            onFinish: () => setIsDeploying(false),
            onError: () => {
                setIsDeploying(false);
                addToast('error', 'Failed to redeploy service');
            },
        });
    };

    const handleRestart = () => {
        setIsRestarting(true);
        router.post(`/api/v1/services/${service.uuid}/restart`, {}, {
            onFinish: () => setIsRestarting(false),
            onError: () => {
                setIsRestarting(false);
                addToast('error', 'Failed to restart service');
            },
        });
    };

    const handleDelete = async () => {
        const confirmed = await confirm({
            title: 'Delete Service',
            description: `Are you sure you want to delete "${service.name}"? This action cannot be undone.`,
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            router.delete(`/services/${service.uuid}`, {
                onSuccess: () => {
                    addToast('success', 'Service deleted successfully');
                    router.visit('/services');
                },
                onError: () => {
                    addToast('error', 'Failed to delete service');
                },
            });
        }
    };

    const tabs = [
        {
            label: 'Overview',
            content: <OverviewTab service={service} />,
        },
        {
            label: 'Domains',
            content: <DomainsTab service={service} domains={extractDomains(service)} />,
        },
        {
            label: 'Deployments',
            content: <DeploymentsTab service={service} />,
        },
        {
            label: 'Rollbacks',
            content: <RollbacksTab service={service} />,
        },
        {
            label: 'Logs',
            content: <LogsTab service={service} containers={containers} />,
        },
        {
            label: 'Variables',
            content: <VariablesTab service={service} />,
        },
        {
            label: 'Settings',
            content: <SettingsTab service={service} />,
        },
    ];

    return (
        <AppLayout
            title={service.name}
            breadcrumbs={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Services', href: '/services' },
                { label: service.name },
            ]}
        >
            {/* Back Button */}
            <Link
                href="/services"
                className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
            >
                <ArrowLeft className="mr-2 h-4 w-4" />
                Back to Services
            </Link>

            {/* Service Header */}
            <div className="mb-6 flex items-center justify-between">
                <div className="flex items-center gap-4">
                    <div className={`flex h-14 w-14 items-center justify-center rounded-xl ${
                        status?.startsWith('running') ? 'bg-primary/10' : 'bg-danger/10'
                    }`}>
                        <Activity className={`h-7 w-7 ${status?.startsWith('running') ? 'text-primary' : 'text-danger'}`} />
                    </div>
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold text-foreground">{service.name}</h1>
                            {status?.startsWith('running') ? (
                                <Badge variant="success">Running</Badge>
                            ) : status?.startsWith('deploying') ? (
                                <Badge variant="warning">Deploying</Badge>
                            ) : (
                                <Badge variant="danger">Stopped</Badge>
                            )}
                        </div>
                        {service.description && (
                            <p className="text-foreground-muted">{service.description}</p>
                        )}
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <Button variant="secondary" size="sm" onClick={handleRedeploy} disabled={isDeploying}>
                        <Play className={`mr-2 h-4 w-4 ${isDeploying ? 'animate-pulse' : ''}`} />
                        {isDeploying ? 'Deploying...' : 'Redeploy'}
                    </Button>
                    <Button variant="secondary" size="sm" onClick={handleRestart} disabled={isRestarting}>
                        <RotateCw className={`mr-2 h-4 w-4 ${isRestarting ? 'animate-spin' : ''}`} />
                        {isRestarting ? 'Restarting...' : 'Restart'}
                    </Button>
                    <Button variant="secondary" size="sm" onClick={() => setShowCloneModal(true)}>
                        <Copy className="mr-2 h-4 w-4" />
                        Clone
                    </Button>
                    <Button variant="danger" size="sm" onClick={handleDelete}>
                        <Trash2 className="mr-2 h-4 w-4" />
                        Delete
                    </Button>
                    <Link href={`/services/${service.uuid}/settings`}>
                        <Button variant="ghost" size="icon">
                            <Settings className="h-4 w-4" />
                        </Button>
                    </Link>
                </div>
            </div>

            {/* Tabs */}
            <Tabs tabs={tabs} />

            {/* Clone Modal */}
            <CloneModal
                isOpen={showCloneModal}
                onClose={() => setShowCloneModal(false)}
                resource={service}
                resourceType="service"
            />
        </AppLayout>
    );
}

interface ServiceMetrics {
    cpu: string;
    memory: string;
    network: string;
}

interface ServiceDeployment {
    id: number;
    uuid: string;
    commit: string;
    message: string;
    status: 'finished' | 'failed' | 'in_progress' | 'queued';
    time: string;
    duration: string;
}

function OverviewTab({ service }: { service: Service }) {
    const [metrics, setMetrics] = useState<ServiceMetrics>({
        cpu: '-',
        memory: '-',
        network: '-',
    });
    const [recentDeployments, setRecentDeployments] = useState<ServiceDeployment[]>([]);
    const [isLoadingMetrics, setIsLoadingMetrics] = useState(true);
    const [isLoadingDeployments, setIsLoadingDeployments] = useState(true);

    // Fetch container metrics
    useEffect(() => {
        const fetchMetrics = async () => {
            try {
                const response = await fetch(`/_internal/services/${service.uuid}/container-stats`);
                if (response.ok) {
                    const data = await response.json();
                    // Aggregate metrics from all containers
                    if (data.containers && data.containers.length > 0) {
                        let totalCpu = 0;
                        let totalMemory = 0;
                        let memoryLimit = 0;
                        let totalNetworkRx = 0;
                        let totalNetworkTx = 0;

                        data.containers.forEach((container: {
                            cpu_percent?: number;
                            memory_usage?: number;
                            memory_limit?: number;
                            network_rx?: number;
                            network_tx?: number;
                        }) => {
                            totalCpu += container.cpu_percent || 0;
                            totalMemory += container.memory_usage || 0;
                            memoryLimit += container.memory_limit || 0;
                            totalNetworkRx += container.network_rx || 0;
                            totalNetworkTx += container.network_tx || 0;
                        });

                        setMetrics({
                            cpu: `${totalCpu.toFixed(1)}%`,
                            memory: memoryLimit > 0
                                ? `${formatBytes(totalMemory)} / ${formatBytes(memoryLimit)}`
                                : formatBytes(totalMemory),
                            network: `↓${formatBytes(totalNetworkRx)} ↑${formatBytes(totalNetworkTx)}`,
                        });
                    }
                }
            } catch {
                // Keep default values on error
            } finally {
                setIsLoadingMetrics(false);
            }
        };

        fetchMetrics();
        // Refresh metrics every 10 seconds
        const interval = setInterval(fetchMetrics, 10000);
        return () => clearInterval(interval);
    }, [service.uuid]);

    // Parse deployment data from API response
    const parseDeployments = (data: unknown): ServiceDeployment[] => {
        const raw = (Array.isArray(data) ? data : (data as { deployments?: unknown[] })?.deployments || []) as Array<{
            id: number;
            uuid?: string;
            commit?: string;
            commit_message?: string;
            status?: string;
            duration?: string;
            author?: string;
            properties?: {
                commit?: string;
                commit_message?: string;
                duration?: string;
                status?: string;
            };
            description?: string;
            created_at: string;
        }>;
        return raw.map((d) => {
            const rawMessage = d.commit_message || d.properties?.commit_message || d.description || '';
            // Clean up empty/useless messages (e.g. "[]", empty string)
            const message = (!rawMessage || rawMessage === '[]' || rawMessage.trim() === '')
                ? 'Service deployment'
                : rawMessage;
            const commit = d.commit?.substring(0, 7) || d.properties?.commit?.substring(0, 7) || '';
            const duration = d.duration || d.properties?.duration || null;
            return {
                id: d.id,
                uuid: d.uuid || String(d.id),
                commit,
                message,
                status: mapDeploymentStatus(d.status || d.properties?.status),
                time: formatRelativeTime(d.created_at),
                duration: duration || '',
            };
        });
    };

    // Fetch recent deployments with optional polling
    const fetchDeployments = async () => {
        try {
            const response = await fetch(`/api/v1/services/${service.uuid}/deployments?take=5`);
            if (response.ok) {
                const data = await response.json();
                setRecentDeployments(parseDeployments(data));
            }
        } catch {
            // Keep existing data on error
        } finally {
            setIsLoadingDeployments(false);
        }
    };

    // Initial fetch
    useEffect(() => {
        fetchDeployments();
    }, [service.uuid]);

    // Poll deployments every 5s when there's an active deployment
    useEffect(() => {
        const hasActiveDeployment = recentDeployments.some(
            (d) => d.status === 'in_progress' || d.status === 'queued'
        );
        if (!hasActiveDeployment) return;

        const interval = setInterval(fetchDeployments, 5000);
        return () => clearInterval(interval);
    }, [recentDeployments, service.uuid]);

    return (
        <div className="space-y-6">
            {/* Metrics Cards */}
            <div className="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-info/10">
                                <Cpu className="h-5 w-5 text-info" />
                            </div>
                            <div>
                                <p className="text-sm text-foreground-muted">CPU Usage</p>
                                {isLoadingMetrics ? (
                                    <div className="h-8 w-16 animate-pulse rounded bg-background-tertiary" />
                                ) : (
                                    <p className="text-2xl font-bold text-foreground">{metrics.cpu}</p>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-warning/10">
                                <MemoryStick className="h-5 w-5 text-warning" />
                            </div>
                            <div>
                                <p className="text-sm text-foreground-muted">Memory</p>
                                {isLoadingMetrics ? (
                                    <div className="h-8 w-24 animate-pulse rounded bg-background-tertiary" />
                                ) : (
                                    <p className="text-2xl font-bold text-foreground">{metrics.memory}</p>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                <Network className="h-5 w-5 text-primary" />
                            </div>
                            <div>
                                <p className="text-sm text-foreground-muted">Network</p>
                                {isLoadingMetrics ? (
                                    <div className="h-8 w-28 animate-pulse rounded bg-background-tertiary" />
                                ) : (
                                    <p className="text-2xl font-bold text-foreground">{metrics.network}</p>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Recent Deployments */}
            <Card>
                <CardHeader>
                    <CardTitle>Recent Deployments</CardTitle>
                </CardHeader>
                <CardContent>
                    {isLoadingDeployments ? (
                        <div className="space-y-3">
                            {[1, 2, 3].map((i) => (
                                <div key={i} className="animate-pulse rounded-lg border border-border bg-background-secondary p-4">
                                    <div className="flex items-center gap-3">
                                        <div className="h-4 w-4 rounded-full bg-background-tertiary" />
                                        <div className="flex-1 space-y-2">
                                            <div className="h-4 w-3/4 rounded bg-background-tertiary" />
                                            <div className="h-3 w-1/2 rounded bg-background-tertiary" />
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : recentDeployments.length === 0 ? (
                        <div className="py-8 text-center">
                            <Activity className="mx-auto h-12 w-12 text-foreground-subtle" />
                            <p className="mt-4 text-foreground-muted">No deployments yet</p>
                            <p className="mt-1 text-sm text-foreground-subtle">
                                Deployments will appear here after you deploy this service
                            </p>
                        </div>
                    ) : (
                        <div className="space-y-2">
                            {recentDeployments.map((deployment) => (
                                <div
                                    key={deployment.id}
                                    className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-3"
                                >
                                    <div className="flex items-center gap-3 min-w-0 flex-1">
                                        {deployment.status === 'finished' ? (
                                            <CheckCircle className="h-4 w-4 shrink-0 text-primary" />
                                        ) : deployment.status === 'failed' ? (
                                            <XCircle className="h-4 w-4 shrink-0 text-danger" />
                                        ) : deployment.status === 'in_progress' ? (
                                            <Activity className="h-4 w-4 shrink-0 text-warning animate-pulse" />
                                        ) : (
                                            <Clock className="h-4 w-4 shrink-0 text-foreground-muted" />
                                        )}
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                {deployment.commit && (
                                                    <>
                                                        <GitCommit className="h-3.5 w-3.5 shrink-0 text-foreground-muted" />
                                                        <code className="text-sm font-medium text-foreground">{deployment.commit}</code>
                                                        <span className="text-foreground-subtle">·</span>
                                                    </>
                                                )}
                                                <span className="text-sm text-foreground truncate">{deployment.message}</span>
                                            </div>
                                            <div className="mt-0.5 flex items-center gap-2 text-xs text-foreground-muted">
                                                <span>{deployment.time}</span>
                                                {deployment.duration && (
                                                    <>
                                                        <span>·</span>
                                                        <span>{deployment.duration}</span>
                                                    </>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                    <Badge variant={getStatusVariant(deployment.status)} className="shrink-0 ml-2">
                                        {getStatusLabel(deployment.status)}
                                    </Badge>
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
