import { useState, useEffect } from 'react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Badge, Button, Tabs, useConfirm } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import { useRealtimeStatus } from '@/hooks/useRealtimeStatus';
import { Link, router } from '@inertiajs/react';
import {
    Play, RotateCw, Trash2, Settings, Activity,
    Cpu, MemoryStick, Network, GitCommit, Clock,
    CheckCircle, XCircle, AlertCircle, ArrowLeft
} from 'lucide-react';
import type { Service, ServiceContainer } from '@/types';
import { getStatusLabel, getStatusVariant } from '@/lib/statusUtils';
import { DeploymentsTab } from './Deployments';
import { LogsTab } from './Logs';
import { VariablesTab } from './Variables';
import { SettingsTab } from './Settings';
import { RollbacksTab } from './Rollbacks';

interface Props {
    service: Service;
    containers?: ServiceContainer[];
}

export default function ServiceShow({ service, containers = [] }: Props) {
    const confirm = useConfirm();
    const [isDeploying, setIsDeploying] = useState(false);
    const [isRestarting, setIsRestarting] = useState(false);
    const [currentStatus, setCurrentStatus] = useState(service?.status || 'running');
    const { addToast } = useToast();

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

    // Real-time service status updates
    const { isConnected } = useRealtimeStatus({
        onServiceStatusChange: () => {
            // Reload page data when service status changes
            router.reload({ only: ['service', 'containers'] });
        },
    });

    // Sync local state when service prop changes
    useEffect(() => {
        setCurrentStatus(service?.status || 'running');
    }, [service?.status]);

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
            router.delete(`/services/${service.uuid}`);
        }
    };

    const tabs = [
        {
            label: 'Overview',
            content: <OverviewTab service={service} />,
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
        </AppLayout>
    );
}

function OverviewTab({ service }: { service: Service }) {
    const metrics = {
        cpu: '-',
        memory: '-',
        network: '-',
    };

    const recentDeployments: Array<{
        id: number;
        commit: string;
        message: string;
        status: 'finished' | 'failed' | 'in_progress';
        time: string;
        duration: string;
    }> = [];

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
                                <p className="text-2xl font-bold text-foreground">{metrics.cpu}</p>
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
                                <p className="text-2xl font-bold text-foreground">{metrics.memory}</p>
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
                                <p className="text-2xl font-bold text-foreground">{metrics.network}</p>
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
                    <div className="space-y-3">
                        {recentDeployments.map((deployment) => (
                            <Link
                                key={deployment.id}
                                href={`/services/${service.uuid}/deployments/${deployment.id}`}
                                className="block rounded-lg border border-border bg-background-secondary p-4 transition-all duration-200 hover:-translate-y-0.5 hover:border-border/80 hover:bg-background-secondary/80 hover:shadow-md"
                            >
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-3">
                                        {deployment.status === 'finished' ? (
                                            <CheckCircle className="h-4 w-4 text-primary" />
                                        ) : deployment.status === 'failed' ? (
                                            <XCircle className="h-4 w-4 text-danger" />
                                        ) : (
                                            <AlertCircle className="h-4 w-4 text-warning" />
                                        )}
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <GitCommit className="h-3.5 w-3.5 text-foreground-muted" />
                                                <code className="text-sm font-medium text-foreground">{deployment.commit}</code>
                                                <span className="text-sm text-foreground-muted">·</span>
                                                <span className="text-sm text-foreground">{deployment.message}</span>
                                            </div>
                                            <div className="mt-1 flex items-center gap-2 text-xs text-foreground-muted">
                                                <Clock className="h-3 w-3" />
                                                <span>{deployment.time}</span>
                                                <span>·</span>
                                                <span>{deployment.duration}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <Badge variant={getStatusVariant(deployment.status)}>
                                        {getStatusLabel(deployment.status)}
                                    </Badge>
                                </div>
                            </Link>
                        ))}
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
