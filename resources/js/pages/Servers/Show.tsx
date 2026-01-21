import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Badge, Button, Tabs } from '@/components/ui';
import {
    Server, Settings, RefreshCw, Terminal, Activity,
    HardDrive, Cpu, MemoryStick, Network, Clock,
    CheckCircle, XCircle, AlertTriangle, Globe
} from 'lucide-react';
import type { Server as ServerType } from '@/types';

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
    return (
        <div className="grid gap-4 md:grid-cols-3">
            <Card>
                <CardContent className="p-4">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-info/10">
                            <Cpu className="h-5 w-5 text-info" />
                        </div>
                        <div>
                            <p className="text-sm text-foreground-muted">CPU Usage</p>
                            <p className="text-2xl font-bold text-foreground">--</p>
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
                            <p className="text-2xl font-bold text-foreground">--</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="p-4">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                            <HardDrive className="h-5 w-5 text-primary" />
                        </div>
                        <div>
                            <p className="text-sm text-foreground-muted">Disk</p>
                            <p className="text-2xl font-bold text-foreground">--</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
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
    return (
        <Card>
            <CardHeader>
                <CardTitle>Server Settings</CardTitle>
            </CardHeader>
            <CardContent>
                <p className="text-foreground-muted">
                    Configure server settings here.
                </p>
                <Link href={`/servers/${server.uuid}/settings`} className="mt-4 inline-block">
                    <Button>Open Settings</Button>
                </Link>
            </CardContent>
        </Card>
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
