import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { useConfirm } from '@/components/ui';
import {
    Server,
    Cpu,
    HardDrive,
    MemoryStick,
    Activity,
    Calendar,
    Users,
    Box,
    Database,
    Layers,
    RefreshCw,
    Trash2,
    CheckCircle,
    XCircle,
    ExternalLink,
    Globe,
    Terminal,
    Gauge,
    Tag,
    Plus,
    X,
    TrendingUp,
} from 'lucide-react';
import { Input } from '@/components/ui/Input';

interface ServerResource {
    id: number;
    uuid: string;
    name: string;
    status: string;
    type?: string;
}

interface HealthDataPoint {
    status: string;
    cpu_usage?: number;
    memory_usage?: number;
    disk_usage?: number;
    response_time_ms?: number;
    checked_at: string;
}

interface ServerDetails {
    id: number;
    uuid: string;
    name: string;
    description?: string;
    ip: string;
    port: number;
    user: string;
    is_reachable: boolean;
    is_usable: boolean;
    is_build_server: boolean;
    is_localhost: boolean;
    team_id: number;
    team_name: string;
    tags?: string[];
    settings: {
        is_reachable: boolean;
        is_usable: boolean;
        concurrent_builds: number;
        is_metrics_enabled: boolean;
        docker_version?: string;
        docker_compose_version?: string;
    };
    metrics?: {
        cpu_usage?: number;
        memory_usage?: number;
        disk_usage?: number;
    };
    resources: {
        applications: ServerResource[];
        databases: ServerResource[];
        services: ServerResource[];
    };
    created_at: string;
    updated_at: string;
}

interface Props {
    server: ServerDetails;
}

function ResourceRow({ resource, type }: { resource: ServerResource; type: 'application' | 'database' | 'service' }) {
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

    const statusConfig = getStatusConfig(resource.status);
    const href = type === 'application'
        ? `/admin/applications/${resource.uuid}`
        : type === 'database'
        ? `/admin/databases/${resource.uuid}`
        : `/admin/services/${resource.uuid}`;

    const Icon = type === 'application' ? Box : type === 'database' ? Database : Layers;

    return (
        <div className="flex items-center justify-between border-b border-border/50 py-3 last:border-0">
            <div className="flex items-center gap-3">
                <Icon className="h-5 w-5 text-foreground-muted" />
                <div>
                    <Link
                        href={href}
                        className="font-medium text-foreground hover:text-primary"
                    >
                        {resource.name}
                    </Link>
                    {resource.type && (
                        <p className="text-xs text-foreground-subtle">{resource.type}</p>
                    )}
                </div>
            </div>
            <div className="flex items-center gap-2">
                <Badge variant={statusConfig.variant} size="sm">
                    {statusConfig.label}
                </Badge>
                <Link href={href}>
                    <Button variant="ghost" size="sm">
                        <ExternalLink className="h-4 w-4" />
                    </Button>
                </Link>
            </div>
        </div>
    );
}

const iconColorMap: Record<string, string> = {
    primary: 'text-primary/50',
    success: 'text-success/50',
    warning: 'text-warning/50',
    danger: 'text-danger/50',
};

function MetricCard({
    label,
    value,
    unit = '%',
    icon: Icon,
    color
}: {
    label: string;
    value?: number;
    unit?: string;
    icon: React.ElementType;
    color: 'primary' | 'success' | 'warning' | 'danger';
}) {
    const getColorClass = (val?: number) => {
        if (val === undefined) return 'text-foreground-muted';
        if (val >= 90) return 'text-danger';
        if (val >= 70) return 'text-warning';
        return 'text-success';
    };

    return (
        <Card variant="glass">
            <CardContent className="p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <p className="text-sm text-foreground-subtle">{label}</p>
                        {value !== undefined ? (
                            <p className={`text-2xl font-bold ${getColorClass(value)}`}>
                                {value.toFixed(1)}{unit}
                            </p>
                        ) : (
                            <p className="text-lg text-foreground-muted">N/A</p>
                        )}
                    </div>
                    <Icon className={`h-8 w-8 ${iconColorMap[color] ?? 'text-foreground-muted/50'}`} />
                </div>
            </CardContent>
        </Card>
    );
}

export default function AdminServerShow({ server }: Props) {
    const confirm = useConfirm();
    const [isValidating, setIsValidating] = React.useState(false);
    const [isCleaningDocker, setIsCleaningDocker] = React.useState(false);
    const [healthHistory, setHealthHistory] = React.useState<HealthDataPoint[]>([]);
    const [historyPeriod, setHistoryPeriod] = React.useState('24h');
    const [isLoadingHistory, setIsLoadingHistory] = React.useState(false);
    const [tags, setTags] = React.useState<string[]>(server.tags ?? []);
    const [newTag, setNewTag] = React.useState('');
    const [isSavingTags, setIsSavingTags] = React.useState(false);

    const applications = server.resources?.applications ?? [];
    const databases = server.resources?.databases ?? [];
    const services = server.resources?.services ?? [];
    const totalResources = applications.length + databases.length + services.length;

    // Load health history
    const [historyError, setHistoryError] = React.useState<string | null>(null);
    React.useEffect(() => {
        const fetchHistory = async () => {
            setIsLoadingHistory(true);
            setHistoryError(null);
            try {
                const response = await fetch(`/admin/servers/${server.uuid}/health-history?period=${historyPeriod}`);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                const data = await response.json();
                setHealthHistory(data.data ?? []);
            } catch (error) {
                console.error('Failed to load health history:', error);
                setHistoryError('Failed to load health history');
                setHealthHistory([]);
            } finally {
                setIsLoadingHistory(false);
            }
        };
        fetchHistory();
    }, [server.uuid, historyPeriod]);

    const handleAddTag = () => {
        const trimmed = newTag.trim().toLowerCase();
        if (trimmed && !tags.includes(trimmed)) {
            setTags([...tags, trimmed]);
            setNewTag('');
        }
    };

    const handleRemoveTag = (tag: string) => {
        setTags(tags.filter(t => t !== tag));
    };

    const handleSaveTags = () => {
        setIsSavingTags(true);
        router.put(`/admin/servers/${server.uuid}/tags`, { tags }, {
            preserveScroll: true,
            onFinish: () => setIsSavingTags(false),
        });
    };

    const handleValidateConnection = async () => {
        setIsValidating(true);
        router.post(`/admin/servers/${server.uuid}/validate`, {}, {
            preserveScroll: true,
            onFinish: () => setIsValidating(false),
        });
    };

    const handleDockerCleanup = async () => {
        const confirmed = await confirm({
            title: 'Docker Cleanup',
            description: 'This will run docker system prune to remove unused containers, networks, and images. This action cannot be undone.',
            confirmText: 'Run Cleanup',
            variant: 'warning',
        });
        if (confirmed) {
            setIsCleaningDocker(true);
            router.post(`/admin/servers/${server.uuid}/docker-cleanup`, {}, {
                preserveScroll: true,
                onFinish: () => setIsCleaningDocker(false),
            });
        }
    };

    const handleDeleteServer = async () => {
        const confirmed = await confirm({
            title: 'Delete Server',
            description: `Are you sure you want to delete "${server.name}"? This will remove the server from Saturn but will not affect the actual server. All resources deployed on this server will become orphaned.`,
            confirmText: 'Delete Server',
            variant: 'danger',
        });
        if (confirmed) {
            router.delete(`/admin/servers/${server.uuid}`);
        }
    };

    return (
        <AdminLayout
            title={server.name}
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Servers', href: '/admin/servers' },
                { label: server.name },
            ]}
        >
            <div className="mx-auto max-w-7xl">
                {/* Header */}
                <div className="mb-8">
                    <div className="flex items-start justify-between">
                        <div className="flex items-center gap-4">
                            <div className="flex h-16 w-16 items-center justify-center rounded-lg bg-gradient-to-br from-green-500 to-emerald-600 text-white">
                                <Server className="h-8 w-8" />
                            </div>
                            <div>
                                <div className="flex items-center gap-2">
                                    <h1 className="text-2xl font-semibold text-foreground">{server.name}</h1>
                                    {server.is_build_server && (
                                        <Badge variant="primary">Build Server</Badge>
                                    )}
                                    {server.is_localhost && (
                                        <Badge variant="default">Localhost</Badge>
                                    )}
                                </div>
                                <p className="mt-1 text-sm text-foreground-muted">
                                    {server.ip}:{server.port} &middot; {server.user}@
                                </p>
                                {server.description && (
                                    <p className="mt-1 text-sm text-foreground-subtle">{server.description}</p>
                                )}
                            </div>
                        </div>
                        <div className="flex gap-2">
                            <Button
                                variant="secondary"
                                onClick={handleValidateConnection}
                                disabled={isValidating}
                            >
                                <RefreshCw className={`h-4 w-4 ${isValidating ? 'animate-spin' : ''}`} />
                                {isValidating ? 'Validating...' : 'Validate'}
                            </Button>
                            <Button
                                variant="warning"
                                onClick={handleDockerCleanup}
                                disabled={isCleaningDocker}
                            >
                                <Trash2 className="h-4 w-4" />
                                {isCleaningDocker ? 'Cleaning...' : 'Docker Cleanup'}
                            </Button>
                            <Button variant="danger" onClick={handleDeleteServer}>
                                <Trash2 className="h-4 w-4" />
                                Delete
                            </Button>
                        </div>
                    </div>
                </div>

                {/* Status & Stats */}
                <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Status</p>
                                    <div className="flex items-center gap-2">
                                        {server.is_reachable ? (
                                            <>
                                                <CheckCircle className="h-5 w-5 text-success" />
                                                <span className="text-lg font-bold text-success">Reachable</span>
                                            </>
                                        ) : (
                                            <>
                                                <XCircle className="h-5 w-5 text-danger" />
                                                <span className="text-lg font-bold text-danger">Unreachable</span>
                                            </>
                                        )}
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
                                    <p className="text-sm text-foreground-subtle">Resources</p>
                                    <p className="text-2xl font-bold text-primary">{totalResources}</p>
                                </div>
                                <Box className="h-8 w-8 text-primary/50" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Team</p>
                                    <Link
                                        href={`/admin/teams/${server.team_id}`}
                                        className="text-lg font-bold text-foreground hover:text-primary"
                                    >
                                        {server.team_name}
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
                                    <p className="text-sm text-foreground-subtle">Concurrent Builds</p>
                                    <p className="text-2xl font-bold text-foreground">
                                        {server.settings?.concurrent_builds ?? 2}
                                    </p>
                                </div>
                                <Gauge className="h-8 w-8 text-foreground-muted/50" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Created</p>
                                    <p className="text-lg font-bold text-foreground">
                                        {new Date(server.created_at).toLocaleDateString()}
                                    </p>
                                </div>
                                <Calendar className="h-8 w-8 text-foreground-muted/50" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Metrics */}
                {server.settings?.is_metrics_enabled && server.metrics && (
                    <div className="mb-6">
                        <h2 className="mb-4 text-lg font-semibold text-foreground">Server Metrics</h2>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <MetricCard
                                label="CPU Usage"
                                value={server.metrics.cpu_usage}
                                icon={Cpu}
                                color="primary"
                            />
                            <MetricCard
                                label="Memory Usage"
                                value={server.metrics.memory_usage}
                                icon={MemoryStick}
                                color="warning"
                            />
                            <MetricCard
                                label="Disk Usage"
                                value={server.metrics.disk_usage}
                                icon={HardDrive}
                                color="success"
                            />
                        </div>
                    </div>
                )}

                {/* Metrics History */}
                <Card variant="glass" className="mb-6">
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <TrendingUp className="h-5 w-5" />
                                    Metrics History
                                </CardTitle>
                                <CardDescription>Server health over time</CardDescription>
                            </div>
                            <div className="flex gap-2">
                                {['1h', '6h', '24h', '7d'].map((period) => (
                                    <Button
                                        key={period}
                                        variant={historyPeriod === period ? 'primary' : 'ghost'}
                                        size="sm"
                                        onClick={() => setHistoryPeriod(period)}
                                    >
                                        {period}
                                    </Button>
                                ))}
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {isLoadingHistory ? (
                            <div className="flex items-center justify-center py-8">
                                <RefreshCw className="h-6 w-6 animate-spin text-foreground-muted" />
                            </div>
                        ) : historyError ? (
                            <div className="py-8 text-center text-sm text-danger">
                                {historyError}
                            </div>
                        ) : healthHistory.length === 0 ? (
                            <div className="py-8 text-center text-sm text-foreground-muted">
                                No health data available for this period
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {/* Simple sparkline-style visualization */}
                                <div className="grid gap-4 sm:grid-cols-3">
                                    {/* CPU History */}
                                    <div className="rounded-lg border border-border/50 p-4">
                                        <p className="mb-2 text-xs font-medium text-foreground-subtle">CPU Usage</p>
                                        <div className="flex h-16 items-end gap-0.5">
                                            {healthHistory.slice(-48).map((point, i) => {
                                                const height = point.cpu_usage ?? 0;
                                                const color = height > 90 ? 'bg-danger' : height > 70 ? 'bg-warning' : 'bg-success';
                                                return (
                                                    <div
                                                        key={i}
                                                        className={`flex-1 rounded-t ${color}`}
                                                        style={{ height: `${Math.max(height, 2)}%` }}
                                                        title={`${height.toFixed(1)}% at ${new Date(point.checked_at).toLocaleTimeString()}`}
                                                    />
                                                );
                                            })}
                                        </div>
                                        <p className="mt-2 text-right text-xs text-foreground-muted">
                                            Avg: {(healthHistory.reduce((acc, p) => acc + (p.cpu_usage ?? 0), 0) / healthHistory.length).toFixed(1)}%
                                        </p>
                                    </div>

                                    {/* Memory History */}
                                    <div className="rounded-lg border border-border/50 p-4">
                                        <p className="mb-2 text-xs font-medium text-foreground-subtle">Memory Usage</p>
                                        <div className="flex h-16 items-end gap-0.5">
                                            {healthHistory.slice(-48).map((point, i) => {
                                                const height = point.memory_usage ?? 0;
                                                const color = height > 90 ? 'bg-danger' : height > 70 ? 'bg-warning' : 'bg-primary';
                                                return (
                                                    <div
                                                        key={i}
                                                        className={`flex-1 rounded-t ${color}`}
                                                        style={{ height: `${Math.max(height, 2)}%` }}
                                                        title={`${height.toFixed(1)}% at ${new Date(point.checked_at).toLocaleTimeString()}`}
                                                    />
                                                );
                                            })}
                                        </div>
                                        <p className="mt-2 text-right text-xs text-foreground-muted">
                                            Avg: {(healthHistory.reduce((acc, p) => acc + (p.memory_usage ?? 0), 0) / healthHistory.length).toFixed(1)}%
                                        </p>
                                    </div>

                                    {/* Disk History */}
                                    <div className="rounded-lg border border-border/50 p-4">
                                        <p className="mb-2 text-xs font-medium text-foreground-subtle">Disk Usage</p>
                                        <div className="flex h-16 items-end gap-0.5">
                                            {healthHistory.slice(-48).map((point, i) => {
                                                const height = point.disk_usage ?? 0;
                                                const color = height > 90 ? 'bg-danger' : height > 70 ? 'bg-warning' : 'bg-success';
                                                return (
                                                    <div
                                                        key={i}
                                                        className={`flex-1 rounded-t ${color}`}
                                                        style={{ height: `${Math.max(height, 2)}%` }}
                                                        title={`${height.toFixed(1)}% at ${new Date(point.checked_at).toLocaleTimeString()}`}
                                                    />
                                                );
                                            })}
                                        </div>
                                        <p className="mt-2 text-right text-xs text-foreground-muted">
                                            Avg: {(healthHistory.reduce((acc, p) => acc + (p.disk_usage ?? 0), 0) / healthHistory.length).toFixed(1)}%
                                        </p>
                                    </div>
                                </div>

                                {/* Status timeline */}
                                <div className="rounded-lg border border-border/50 p-4">
                                    <p className="mb-2 text-xs font-medium text-foreground-subtle">Status Timeline</p>
                                    <div className="flex h-6 gap-0.5 overflow-hidden rounded">
                                        {healthHistory.slice(-96).map((point, i) => {
                                            const color = point.status === 'healthy' ? 'bg-success' :
                                                         point.status === 'degraded' ? 'bg-warning' :
                                                         point.status === 'down' ? 'bg-danger' : 'bg-foreground-muted';
                                            return (
                                                <div
                                                    key={i}
                                                    className={`flex-1 ${color}`}
                                                    title={`${point.status} at ${new Date(point.checked_at).toLocaleString()}`}
                                                />
                                            );
                                        })}
                                    </div>
                                    <div className="mt-2 flex justify-between text-xs text-foreground-muted">
                                        <span>{healthHistory[0] ? new Date(healthHistory[0].checked_at).toLocaleTimeString() : ''}</span>
                                        <span>{healthHistory[healthHistory.length - 1] ? new Date(healthHistory[healthHistory.length - 1].checked_at).toLocaleTimeString() : ''}</span>
                                    </div>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Server Tags */}
                <Card variant="glass" className="mb-6">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Tag className="h-5 w-5" />
                            Server Tags
                        </CardTitle>
                        <CardDescription>Organize servers with tags for easy filtering</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            <div className="flex flex-wrap gap-2">
                                {tags.map((tag) => (
                                    <Badge key={tag} variant="secondary" className="flex items-center gap-1">
                                        {tag}
                                        <button
                                            onClick={() => handleRemoveTag(tag)}
                                            className="ml-1 rounded-full p-0.5 hover:bg-foreground/10"
                                        >
                                            <X className="h-3 w-3" />
                                        </button>
                                    </Badge>
                                ))}
                                {tags.length === 0 && (
                                    <span className="text-sm text-foreground-muted">No tags assigned</span>
                                )}
                            </div>
                            <div className="flex gap-2">
                                <Input
                                    placeholder="Add a tag..."
                                    value={newTag}
                                    onChange={(e) => setNewTag(e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && handleAddTag()}
                                    className="max-w-xs"
                                />
                                <Button variant="secondary" onClick={handleAddTag}>
                                    <Plus className="h-4 w-4" />
                                    Add
                                </Button>
                                {JSON.stringify(tags) !== JSON.stringify(server.tags ?? []) && (
                                    <Button
                                        variant="primary"
                                        onClick={handleSaveTags}
                                        disabled={isSavingTags}
                                    >
                                        {isSavingTags ? 'Saving...' : 'Save Tags'}
                                    </Button>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Server Info */}
                <Card variant="glass" className="mb-6">
                    <CardHeader>
                        <CardTitle>Server Information</CardTitle>
                        <CardDescription>Connection details and configuration</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div className="flex items-center gap-3">
                                <Globe className="h-5 w-5 text-foreground-muted" />
                                <div>
                                    <p className="text-xs text-foreground-subtle">IP Address</p>
                                    <p className="font-medium text-foreground">{server.ip}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <Terminal className="h-5 w-5 text-foreground-muted" />
                                <div>
                                    <p className="text-xs text-foreground-subtle">SSH Port</p>
                                    <p className="font-medium text-foreground">{server.port}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <Users className="h-5 w-5 text-foreground-muted" />
                                <div>
                                    <p className="text-xs text-foreground-subtle">SSH User</p>
                                    <p className="font-medium text-foreground">{server.user}</p>
                                </div>
                            </div>
                            {server.settings?.docker_version && (
                                <div className="flex items-center gap-3">
                                    <Box className="h-5 w-5 text-foreground-muted" />
                                    <div>
                                        <p className="text-xs text-foreground-subtle">Docker Version</p>
                                        <p className="font-medium text-foreground">{server.settings.docker_version}</p>
                                    </div>
                                </div>
                            )}
                            {server.settings?.docker_compose_version && (
                                <div className="flex items-center gap-3">
                                    <Layers className="h-5 w-5 text-foreground-muted" />
                                    <div>
                                        <p className="text-xs text-foreground-subtle">Compose Version</p>
                                        <p className="font-medium text-foreground">{server.settings.docker_compose_version}</p>
                                    </div>
                                </div>
                            )}
                            <div className="flex items-center gap-3">
                                <Activity className="h-5 w-5 text-foreground-muted" />
                                <div>
                                    <p className="text-xs text-foreground-subtle">Metrics Enabled</p>
                                    <p className="font-medium text-foreground">
                                        {server.settings?.is_metrics_enabled ? 'Yes' : 'No'}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Applications */}
                <Card variant="glass" className="mb-6">
                    <CardHeader>
                        <CardTitle>Applications ({applications.length})</CardTitle>
                        <CardDescription>Applications deployed on this server</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {applications.length === 0 ? (
                            <p className="py-4 text-center text-sm text-foreground-muted">No applications</p>
                        ) : (
                            applications.map((app) => (
                                <ResourceRow key={app.id} resource={app} type="application" />
                            ))
                        )}
                    </CardContent>
                </Card>

                {/* Databases */}
                <Card variant="glass" className="mb-6">
                    <CardHeader>
                        <CardTitle>Databases ({databases.length})</CardTitle>
                        <CardDescription>Databases running on this server</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {databases.length === 0 ? (
                            <p className="py-4 text-center text-sm text-foreground-muted">No databases</p>
                        ) : (
                            databases.map((db) => (
                                <ResourceRow key={db.id} resource={db} type="database" />
                            ))
                        )}
                    </CardContent>
                </Card>

                {/* Services */}
                <Card variant="glass">
                    <CardHeader>
                        <CardTitle>Services ({services.length})</CardTitle>
                        <CardDescription>Services running on this server</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {services.length === 0 ? (
                            <p className="py-4 text-center text-sm text-foreground-muted">No services</p>
                        ) : (
                            services.map((service) => (
                                <ResourceRow key={service.id} resource={service} type="service" />
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
