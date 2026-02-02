import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Badge, Button, Tabs, useConfirm } from '@/components/ui';
import {
    Server, Settings, RefreshCw, Terminal, Activity,
    HardDrive, Cpu, MemoryStick, Network, Clock,
    CheckCircle, XCircle, AlertTriangle, Globe, Loader2, Trash2
} from 'lucide-react';
import type { Server as ServerType } from '@/types';
import { useSentinelMetrics } from '@/hooks/useSentinelMetrics';

interface Props {
    server: ServerType;
}

export default function ServerShow({ server }: Props) {
    const isOnline = server.is_reachable && server.is_usable;

    const tabs = [
        {
            label: 'Overview',
            content: <OverviewTab server={server} />,
        },
        {
            label: 'Resources',
            content: <ResourcesTab server={server} />,
        },
        {
            label: 'Proxy',
            content: <ProxyTab server={server} />,
        },
        {
            label: 'Logs',
            content: <LogsTab />,
        },
        {
            label: 'Settings',
            content: <SettingsTab server={server} />,
        },
    ];

    return (
        <AppLayout
            title={server.name}
            breadcrumbs={[
                { label: 'Servers', href: '/servers' },
                { label: server.name },
            ]}
        >
            {/* Server Header */}
            <div className="mb-6 flex items-center justify-between">
                <div className="flex items-center gap-4">
                    <div className={`flex h-14 w-14 items-center justify-center rounded-xl ${
                        isOnline ? 'bg-primary/10' : 'bg-danger/10'
                    }`}>
                        <Server className={`h-7 w-7 ${isOnline ? 'text-primary' : 'text-danger'}`} />
                    </div>
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold text-foreground">{server.name}</h1>
                            {isOnline ? (
                                <Badge variant="success">Online</Badge>
                            ) : (
                                <Badge variant="danger">Offline</Badge>
                            )}
                        </div>
                        <p className="text-foreground-muted">
                            {server.ip}:{server.port}
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <Button
                        variant="secondary"
                        size="sm"
                        onClick={() => router.post(`/servers/${server.uuid}/validate`)}
                    >
                        <RefreshCw className="mr-2 h-4 w-4" />
                        Validate
                    </Button>
                    <Button
                        variant="secondary"
                        size="sm"
                        onClick={() => router.visit(`/servers/${server.uuid}/terminal`)}
                    >
                        <Terminal className="mr-2 h-4 w-4" />
                        Terminal
                    </Button>
                    <Link href={`/servers/${server.uuid}/settings`}>
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

function OverviewTab({ server }: { server: ServerType }) {
    return (
        <div className="grid gap-4 md:grid-cols-2">
            {/* Connection Details */}
            <Card>
                <CardHeader>
                    <CardTitle>Connection Details</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                    <InfoRow label="IP Address" value={server.ip} />
                    <InfoRow label="Port" value={String(server.port)} />
                    <InfoRow label="User" value={server.user} />
                    <InfoRow label="Status" value={
                        <div className="flex items-center gap-1">
                            {server.is_reachable ? (
                                <>
                                    <CheckCircle className="h-4 w-4 text-primary" />
                                    <span className="text-primary">Reachable</span>
                                </>
                            ) : (
                                <>
                                    <XCircle className="h-4 w-4 text-danger" />
                                    <span className="text-danger">Unreachable</span>
                                </>
                            )}
                        </div>
                    } />
                </CardContent>
            </Card>

            {/* Server Settings */}
            <Card>
                <CardHeader>
                    <CardTitle>Settings</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                    <InfoRow
                        label="Build Server"
                        value={server.settings?.is_build_server ? 'Yes' : 'No'}
                    />
                    <InfoRow
                        label="Concurrent Builds"
                        value={String(server.settings?.concurrent_builds || 2)}
                    />
                    <InfoRow
                        label="Created"
                        value={new Date(server.created_at).toLocaleDateString()}
                    />
                </CardContent>
            </Card>
        </div>
    );
}

function ResourcesTab({ server }: { server: ServerType }) {
    const { metrics, isLoading, error, refetch } = useSentinelMetrics({
        serverUuid: server.uuid,
        autoRefresh: true,
        refreshInterval: 10000,
    });

    const getStatusColor = (percentage: number) => {
        if (percentage >= 90) return 'danger';
        if (percentage >= 75) return 'warning';
        return 'success';
    };

    if (error) {
        return (
            <div className="space-y-4">
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-8">
                        <AlertTriangle className="h-8 w-8 text-warning" />
                        <p className="mt-2 text-sm text-foreground-muted">
                            {error.message.includes('503') || error.message.includes('Sentinel')
                                ? 'Sentinel is not enabled on this server'
                                : 'Failed to load metrics'}
                        </p>
                        <Button variant="secondary" size="sm" className="mt-3" onClick={() => refetch()}>
                            <RefreshCw className="mr-2 h-4 w-4" />
                            Retry
                        </Button>
                    </CardContent>
                </Card>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div className="flex justify-end">
                <Button
                    variant="secondary"
                    size="sm"
                    onClick={() => refetch()}
                    disabled={isLoading}
                >
                    <RefreshCw className={`mr-2 h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
                    Refresh
                </Button>
            </div>

            <div className="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-info/10">
                                    <Cpu className="h-5 w-5 text-info" />
                                </div>
                                <div>
                                    <p className="text-sm text-foreground-muted">CPU Usage</p>
                                    {isLoading && !metrics ? (
                                        <div className="flex items-center gap-2">
                                            <Loader2 className="h-4 w-4 animate-spin text-foreground-muted" />
                                        </div>
                                    ) : (
                                        <p className="text-2xl font-bold text-foreground">
                                            {metrics?.cpu.current || '--'}
                                        </p>
                                    )}
                                </div>
                            </div>
                            {metrics && (
                                <Badge variant={getStatusColor(metrics.cpu.percentage)}>
                                    {metrics.cpu.percentage}%
                                </Badge>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-warning/10">
                                    <MemoryStick className="h-5 w-5 text-warning" />
                                </div>
                                <div>
                                    <p className="text-sm text-foreground-muted">Memory</p>
                                    {isLoading && !metrics ? (
                                        <div className="flex items-center gap-2">
                                            <Loader2 className="h-4 w-4 animate-spin text-foreground-muted" />
                                        </div>
                                    ) : (
                                        <p className="text-2xl font-bold text-foreground">
                                            {metrics?.memory.current || '--'}
                                        </p>
                                    )}
                                </div>
                            </div>
                            {metrics && (
                                <Badge variant={getStatusColor(metrics.memory.percentage)}>
                                    {metrics.memory.percentage}%
                                </Badge>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                    <HardDrive className="h-5 w-5 text-primary" />
                                </div>
                                <div>
                                    <p className="text-sm text-foreground-muted">Disk</p>
                                    {isLoading && !metrics ? (
                                        <div className="flex items-center gap-2">
                                            <Loader2 className="h-4 w-4 animate-spin text-foreground-muted" />
                                        </div>
                                    ) : (
                                        <p className="text-2xl font-bold text-foreground">
                                            {metrics?.disk.current || '--'}
                                        </p>
                                    )}
                                </div>
                            </div>
                            {metrics && (
                                <Badge variant={getStatusColor(metrics.disk.percentage)}>
                                    {metrics.disk.percentage}%
                                </Badge>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Link href={`/servers/${server.uuid}/metrics`} className="inline-block">
                <Button variant="secondary" size="sm">
                    <Activity className="mr-2 h-4 w-4" />
                    View Detailed Metrics
                </Button>
            </Link>
        </div>
    );
}

function LogsTab() {
    return (
        <Card>
            <CardContent className="flex flex-col items-center justify-center py-16">
                <Terminal className="h-12 w-12 text-foreground-subtle" />
                <h3 className="mt-4 font-medium text-foreground">No logs available</h3>
                <p className="mt-1 text-sm text-foreground-muted">
                    Server logs will appear here once activity is detected
                </p>
            </CardContent>
        </Card>
    );
}

function ProxyTab({ server }: { server: ServerType }) {
    return (
        <div className="space-y-4">
            <div className="grid gap-4 md:grid-cols-2">
                <Link href={`/servers/${server.uuid}/proxy`}>
                    <Card className="cursor-pointer transition-all hover:border-primary/50">
                        <CardContent className="flex items-center gap-4 p-6">
                            <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                <Globe className="h-6 w-6 text-primary" />
                            </div>
                            <div>
                                <h3 className="font-semibold text-foreground">Proxy Overview</h3>
                                <p className="text-sm text-foreground-muted">View proxy status and stats</p>
                            </div>
                        </CardContent>
                    </Card>
                </Link>

                <Link href={`/servers/${server.uuid}/proxy/configuration`}>
                    <Card className="cursor-pointer transition-all hover:border-primary/50">
                        <CardContent className="flex items-center gap-4 p-6">
                            <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-info/10">
                                <Settings className="h-6 w-6 text-info" />
                            </div>
                            <div>
                                <h3 className="font-semibold text-foreground">Configuration</h3>
                                <p className="text-sm text-foreground-muted">Edit proxy configuration</p>
                            </div>
                        </CardContent>
                    </Card>
                </Link>

                <Link href={`/servers/${server.uuid}/proxy/domains`}>
                    <Card className="cursor-pointer transition-all hover:border-primary/50">
                        <CardContent className="flex items-center gap-4 p-6">
                            <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-success/10">
                                <Globe className="h-6 w-6 text-success" />
                            </div>
                            <div>
                                <h3 className="font-semibold text-foreground">Domains</h3>
                                <p className="text-sm text-foreground-muted">Manage domains and SSL</p>
                            </div>
                        </CardContent>
                    </Card>
                </Link>

                <Link href={`/servers/${server.uuid}/proxy/logs`}>
                    <Card className="cursor-pointer transition-all hover:border-primary/50">
                        <CardContent className="flex items-center gap-4 p-6">
                            <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-warning/10">
                                <Terminal className="h-6 w-6 text-warning" />
                            </div>
                            <div>
                                <h3 className="font-semibold text-foreground">Logs</h3>
                                <p className="text-sm text-foreground-muted">View proxy logs</p>
                            </div>
                        </CardContent>
                    </Card>
                </Link>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Proxy Information</CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="text-foreground-muted">
                        Manage your server's proxy configuration, domains, SSL certificates, and more.
                    </p>
                    <Link href={`/servers/${server.uuid}/proxy`} className="mt-4 inline-block">
                        <Button>Open Proxy Management</Button>
                    </Link>
                </CardContent>
            </Card>
        </div>
    );
}

function SettingsTab({ server }: { server: ServerType }) {
    const confirm = useConfirm();

    const settingsSections = [
        {
            title: 'General Settings',
            description: 'Configure server name, description, and basic information',
            icon: <Settings className="h-6 w-6" />,
            href: `/servers/${server.uuid}/settings/general`,
            iconBg: 'bg-primary/10',
            iconColor: 'text-primary',
        },
        {
            title: 'Docker Configuration',
            description: 'Manage Docker settings and build configurations',
            icon: <HardDrive className="h-6 w-6" />,
            href: `/servers/${server.uuid}/settings/docker`,
            iconBg: 'bg-info/10',
            iconColor: 'text-info',
        },
        {
            title: 'Network Settings',
            description: 'Configure network, firewall, and connectivity settings',
            icon: <Network className="h-6 w-6" />,
            href: `/servers/${server.uuid}/settings/network`,
            iconBg: 'bg-success/10',
            iconColor: 'text-success',
        },
    ];

    return (
        <div className="space-y-6">
            {/* Settings Grid */}
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                {settingsSections.map((section) => (
                    <Link key={section.href} href={section.href}>
                        <Card className="cursor-pointer transition-all hover:border-primary/50">
                            <CardContent className="p-6">
                                <div className="mb-4 flex items-center gap-3">
                                    <div className={`flex h-12 w-12 items-center justify-center rounded-lg ${section.iconBg}`}>
                                        <div className={section.iconColor}>{section.icon}</div>
                                    </div>
                                </div>
                                <h3 className="font-semibold text-foreground">{section.title}</h3>
                                <p className="mt-1 text-sm text-foreground-muted">{section.description}</p>
                            </CardContent>
                        </Card>
                    </Link>
                ))}
            </div>

            {/* Danger Zone */}
            <Card className="border-danger/50">
                <CardHeader>
                    <CardTitle className="text-danger">Danger Zone</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="flex items-center justify-between">
                        <div>
                            <h4 className="font-medium text-foreground">Delete this server</h4>
                            <p className="mt-1 text-sm text-foreground-muted">
                                Once you delete a server, there is no going back. Please be certain.
                            </p>
                        </div>
                        <Button
                            variant="danger"
                            onClick={async () => {
                                const confirmed = await confirm({
                                    title: 'Delete Server',
                                    description: `Are you sure you want to delete "${server.name}"? This action cannot be undone.`,
                                    confirmText: 'Delete',
                                    variant: 'danger',
                                });
                                if (confirmed) {
                                    router.delete(`/servers/${server.uuid}`);
                                }
                            }}
                        >
                            <Trash2 className="mr-2 h-4 w-4" />
                            Delete Server
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="flex items-center justify-between">
            <span className="text-sm text-foreground-muted">{label}</span>
            <span className="text-sm text-foreground">{value}</span>
        </div>
    );
}
