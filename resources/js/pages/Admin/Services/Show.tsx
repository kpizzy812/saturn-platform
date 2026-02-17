import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { useConfirm } from '@/components/ui';
import {
    Layers,
    Calendar,
    Users,
    Server,
    FolderKanban,
    Play,
    Square,
    RefreshCw,
    Trash2,
    CheckCircle,
    XCircle,
    Clock,
    Activity,
    Box,
    Container,
    ExternalLink,
    Globe,
} from 'lucide-react';

interface ServiceApplication {
    id: number;
    uuid: string;
    name: string;
    fqdn?: string;
    status: string;
}

interface ServiceDatabase {
    id: number;
    uuid: string;
    name: string;
    type: string;
    status: string;
}

interface ServiceDetails {
    id: number;
    uuid: string;
    name: string;
    description?: string;
    status: string;
    service_type?: string;
    team_id: number;
    team_name: string;
    project_id: number;
    project_name: string;
    environment_id: number;
    environment_name: string;
    server_id?: number;
    server_name?: string;
    server_uuid?: string;
    applications: ServiceApplication[];
    databases: ServiceDatabase[];
    created_at: string;
    updated_at: string;
}

interface Props {
    service: ServiceDetails;
}

function ContainerRow({ app }: { app: ServiceApplication }) {
    const getStatusConfig = (status: string) => {
        const statusLower = status?.toLowerCase() || 'unknown';
        if (statusLower.includes('running') || statusLower.includes('healthy')) {
            return { variant: 'success' as const, label: 'Running' };
        }
        if (statusLower.includes('stopped') || statusLower.includes('exited')) {
            return { variant: 'danger' as const, label: 'Stopped' };
        }
        if (statusLower.includes('starting') || statusLower.includes('restarting')) {
            return { variant: 'warning' as const, label: 'Starting' };
        }
        return { variant: 'default' as const, label: status || 'Unknown' };
    };

    const config = getStatusConfig(app.status);

    return (
        <div className="flex items-center justify-between border-b border-border/50 py-3 last:border-0">
            <div className="flex items-center gap-3">
                <Container className="h-5 w-5 text-foreground-muted" />
                <div>
                    <div className="flex items-center gap-2">
                        <span className="font-medium text-foreground">{app.name}</span>
                        <Badge variant={config.variant} size="sm">
                            {config.label}
                        </Badge>
                    </div>
                    {app.fqdn && (
                        <a
                            href={app.fqdn}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="flex items-center gap-1 text-xs text-primary hover:underline"
                        >
                            <Globe className="h-3 w-3" />
                            {app.fqdn}
                            <ExternalLink className="h-2 w-2" />
                        </a>
                    )}
                </div>
            </div>
        </div>
    );
}

function DatabaseRow({ db }: { db: ServiceDatabase }) {
    const getStatusConfig = (status: string) => {
        const statusLower = status?.toLowerCase() || 'unknown';
        if (statusLower.includes('running') || statusLower.includes('healthy')) {
            return { variant: 'success' as const, label: 'Running' };
        }
        if (statusLower.includes('stopped') || statusLower.includes('exited')) {
            return { variant: 'danger' as const, label: 'Stopped' };
        }
        if (statusLower.includes('starting') || statusLower.includes('restarting')) {
            return { variant: 'warning' as const, label: 'Starting' };
        }
        return { variant: 'default' as const, label: status || 'Unknown' };
    };

    const config = getStatusConfig(db.status);

    return (
        <div className="flex items-center justify-between border-b border-border/50 py-3 last:border-0">
            <div className="flex items-center gap-3">
                <Box className="h-5 w-5 text-foreground-muted" />
                <div>
                    <div className="flex items-center gap-2">
                        <span className="font-medium text-foreground">{db.name}</span>
                        <Badge variant="default" size="sm">
                            {db.type}
                        </Badge>
                        <Badge variant={config.variant} size="sm">
                            {config.label}
                        </Badge>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function AdminServiceShow({ service }: Props) {
    const confirm = useConfirm();
    const [isRestarting, setIsRestarting] = React.useState(false);
    const [isStopping, setIsStopping] = React.useState(false);
    const [isStarting, setIsStarting] = React.useState(false);

    const getStatusConfig = (status: string) => {
        const statusLower = status?.toLowerCase() || 'unknown';
        if (statusLower.includes('running') || statusLower.includes('healthy')) {
            return { variant: 'success' as const, label: 'Running', icon: <CheckCircle className="h-4 w-4" /> };
        }
        if (statusLower.includes('stopped') || statusLower.includes('exited')) {
            return { variant: 'danger' as const, label: 'Stopped', icon: <XCircle className="h-4 w-4" /> };
        }
        if (statusLower.includes('starting') || statusLower.includes('restarting')) {
            return { variant: 'warning' as const, label: 'Starting', icon: <Clock className="h-4 w-4" /> };
        }
        return { variant: 'default' as const, label: status || 'Unknown', icon: null };
    };

    const config = getStatusConfig(service.status);

    const handleRestart = async () => {
        const confirmed = await confirm({
            title: 'Restart Service',
            description: `Are you sure you want to restart "${service.name}"? This will restart all containers in the service.`,
            confirmText: 'Restart',
            variant: 'warning',
        });
        if (confirmed) {
            setIsRestarting(true);
            router.post(`/admin/services/${service.uuid}/restart`, {}, {
                preserveScroll: true,
                onFinish: () => setIsRestarting(false),
            });
        }
    };

    const handleStop = async () => {
        const confirmed = await confirm({
            title: 'Stop Service',
            description: `Are you sure you want to stop "${service.name}"? All containers in the service will be stopped.`,
            confirmText: 'Stop',
            variant: 'danger',
        });
        if (confirmed) {
            setIsStopping(true);
            router.post(`/admin/services/${service.uuid}/stop`, {}, {
                preserveScroll: true,
                onFinish: () => setIsStopping(false),
            });
        }
    };

    const handleStart = async () => {
        setIsStarting(true);
        router.post(`/admin/services/${service.uuid}/start`, {}, {
            preserveScroll: true,
            onFinish: () => setIsStarting(false),
        });
    };

    const handleDelete = async () => {
        const confirmed = await confirm({
            title: 'Delete Service',
            description: `Are you sure you want to delete "${service.name}"? This action cannot be undone and will remove all associated containers and data.`,
            confirmText: 'Delete Service',
            variant: 'danger',
        });
        if (confirmed) {
            router.delete(`/admin/services/${service.id}`);
        }
    };

    const isStopped = service.status?.toLowerCase().includes('stopped') ||
                      service.status?.toLowerCase().includes('exited');

    const totalContainers = (service.applications?.length || 0) + (service.databases?.length || 0);

    return (
        <AdminLayout
            title={service.name}
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Services', href: '/admin/services' },
                { label: service.name },
            ]}
        >
            <div className="mx-auto max-w-7xl">
                {/* Header */}
                <div className="mb-8">
                    <div className="flex items-start justify-between">
                        <div className="flex items-center gap-4">
                            <div className="flex h-16 w-16 items-center justify-center rounded-lg bg-gradient-to-br from-purple-500 to-violet-600 text-white">
                                <Layers className="h-8 w-8" />
                            </div>
                            <div>
                                <div className="flex items-center gap-2">
                                    <h1 className="text-2xl font-semibold text-foreground">{service.name}</h1>
                                    <Badge variant={config.variant} icon={config.icon}>
                                        {config.label}
                                    </Badge>
                                    {service.service_type && (
                                        <Badge variant="default">{service.service_type}</Badge>
                                    )}
                                </div>
                                {service.description && (
                                    <p className="mt-1 text-sm text-foreground-subtle">{service.description}</p>
                                )}
                            </div>
                        </div>
                        <div className="flex gap-2">
                            {isStopped ? (
                                <Button
                                    variant="success"
                                    onClick={handleStart}
                                    disabled={isStarting}
                                >
                                    <Play className={`h-4 w-4 ${isStarting ? 'animate-pulse' : ''}`} />
                                    {isStarting ? 'Starting...' : 'Start'}
                                </Button>
                            ) : (
                                <>
                                    <Button
                                        variant="secondary"
                                        onClick={handleRestart}
                                        disabled={isRestarting}
                                    >
                                        <RefreshCw className={`h-4 w-4 ${isRestarting ? 'animate-spin' : ''}`} />
                                        {isRestarting ? 'Restarting...' : 'Restart'}
                                    </Button>
                                    <Button
                                        variant="warning"
                                        onClick={handleStop}
                                        disabled={isStopping}
                                    >
                                        <Square className="h-4 w-4" />
                                        {isStopping ? 'Stopping...' : 'Stop'}
                                    </Button>
                                </>
                            )}
                            <Button variant="danger" onClick={handleDelete}>
                                <Trash2 className="h-4 w-4" />
                                Delete
                            </Button>
                        </div>
                    </div>
                </div>

                {/* Stats */}
                <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Status</p>
                                    <div className="flex items-center gap-2">
                                        {config.icon}
                                        <span className={`text-lg font-bold ${config.variant === 'success' ? 'text-success' : config.variant === 'danger' ? 'text-danger' : config.variant === 'warning' ? 'text-warning' : 'text-foreground'}`}>
                                            {config.label}
                                        </span>
                                    </div>
                                </div>
                                <Activity className="h-8 w-8 text-primary/50" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Team</p>
                                    <Link
                                        href={`/admin/teams/${service.team_id}`}
                                        className="text-lg font-bold text-foreground hover:text-primary"
                                    >
                                        {service.team_name}
                                    </Link>
                                </div>
                                <Users className="h-8 w-8 text-warning/50" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Project</p>
                                    <Link
                                        href={`/admin/projects/${service.project_id}`}
                                        className="text-lg font-bold text-foreground hover:text-primary"
                                    >
                                        {service.project_name}
                                    </Link>
                                </div>
                                <FolderKanban className="h-8 w-8 text-success/50" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Server</p>
                                    {service.server_uuid ? (
                                        <Link
                                            href={`/admin/servers/${service.server_uuid}`}
                                            className="text-lg font-bold text-foreground hover:text-primary"
                                        >
                                            {service.server_name || 'Unknown'}
                                        </Link>
                                    ) : (
                                        <span className="text-lg font-bold text-foreground-muted">N/A</span>
                                    )}
                                </div>
                                <Server className="h-8 w-8 text-green-500/50" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Containers</p>
                                    <p className="text-2xl font-bold text-foreground">{totalContainers}</p>
                                </div>
                                <Container className="h-8 w-8 text-foreground-muted/50" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Service Info */}
                <Card variant="glass" className="mb-6">
                    <CardHeader>
                        <CardTitle>Service Information</CardTitle>
                        <CardDescription>Details and configuration</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {service.service_type && (
                                <div className="flex items-center gap-3">
                                    <Layers className="h-5 w-5 text-foreground-muted" />
                                    <div>
                                        <p className="text-xs text-foreground-subtle">Service Type</p>
                                        <p className="font-medium text-foreground">{service.service_type}</p>
                                    </div>
                                </div>
                            )}
                            <div className="flex items-center gap-3">
                                <FolderKanban className="h-5 w-5 text-foreground-muted" />
                                <div>
                                    <p className="text-xs text-foreground-subtle">Environment</p>
                                    <p className="font-medium text-foreground">{service.environment_name}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <Calendar className="h-5 w-5 text-foreground-muted" />
                                <div>
                                    <p className="text-xs text-foreground-subtle">Created</p>
                                    <p className="font-medium text-foreground">
                                        {new Date(service.created_at).toLocaleDateString()}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <Calendar className="h-5 w-5 text-foreground-muted" />
                                <div>
                                    <p className="text-xs text-foreground-subtle">Updated</p>
                                    <p className="font-medium text-foreground">
                                        {new Date(service.updated_at).toLocaleDateString()}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Applications */}
                <Card variant="glass" className="mb-6">
                    <CardHeader>
                        <CardTitle>Applications ({service.applications?.length || 0})</CardTitle>
                        <CardDescription>Application containers in this service</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {!service.applications || service.applications.length === 0 ? (
                            <p className="py-4 text-center text-sm text-foreground-muted">No applications in this service</p>
                        ) : (
                            service.applications.map((app) => (
                                <ContainerRow key={app.id} app={app} />
                            ))
                        )}
                    </CardContent>
                </Card>

                {/* Databases */}
                <Card variant="glass">
                    <CardHeader>
                        <CardTitle>Databases ({service.databases?.length || 0})</CardTitle>
                        <CardDescription>Database containers in this service</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {!service.databases || service.databases.length === 0 ? (
                            <p className="py-4 text-center text-sm text-foreground-muted">No databases in this service</p>
                        ) : (
                            service.databases.map((db) => (
                                <DatabaseRow key={db.id} db={db} />
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
