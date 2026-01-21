import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Badge, Button } from '@/components/ui';
import {
    Server, Play, Square, RotateCw, FileText, Settings,
    Activity, Globe, ShieldCheck, CheckCircle, XCircle
} from 'lucide-react';
import type { Server as ServerType } from '@/types';

interface ProxyData {
    type: string;
    status: 'running' | 'stopped' | 'unknown';
    version?: string;
    uptime?: string;
    domains_count?: number;
    ssl_count?: number;
}

interface Props {
    server: ServerType;
    proxy: ProxyData;
}

export default function ProxyIndex({ server, proxy }: Props) {
    const isRunning = proxy.status === 'running';

    const handleRestart = () => {
        router.post(`/servers/${server.uuid}/proxy/restart`);
    };

    const handleStart = () => {
        router.post(`/servers/${server.uuid}/proxy/start`);
    };

    const handleStop = () => {
        router.post(`/servers/${server.uuid}/proxy/stop`);
    };

    return (
        <AppLayout
            title={`Proxy - ${server.name}`}
            breadcrumbs={[
                { label: 'Servers', href: '/servers' },
                { label: server.name, href: `/servers/${server.uuid}` },
                { label: 'Proxy' },
            ]}
        >
            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div className="flex items-center gap-4">
                    <div className={`flex h-14 w-14 items-center justify-center rounded-xl ${
                        isRunning ? 'bg-primary/10' : 'bg-danger/10'
                    }`}>
                        <Server className={`h-7 w-7 ${isRunning ? 'text-primary' : 'text-danger'}`} />
                    </div>
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold text-foreground">
                                {proxy.type.charAt(0).toUpperCase() + proxy.type.slice(1)} Proxy
                            </h1>
                            {isRunning ? (
                                <Badge variant="success">Running</Badge>
                            ) : (
                                <Badge variant="danger">Stopped</Badge>
                            )}
                        </div>
                        {proxy.version && (
                            <p className="text-foreground-muted">Version: {proxy.version}</p>
                        )}
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    {isRunning ? (
                        <>
                            <Button
                                variant="secondary"
                                size="sm"
                                onClick={handleRestart}
                            >
                                <RotateCw className="mr-2 h-4 w-4" />
                                Restart
                            </Button>
                            <Button
                                variant="danger"
                                size="sm"
                                onClick={handleStop}
                            >
                                <Square className="mr-2 h-4 w-4" />
                                Stop
                            </Button>
                        </>
                    ) : (
                        <Button
                            variant="primary"
                            size="sm"
                            onClick={handleStart}
                        >
                            <Play className="mr-2 h-4 w-4" />
                            Start
                        </Button>
                    )}
                </div>
            </div>

            {/* Stats Grid */}
            <div className="mb-6 grid gap-4 md:grid-cols-4">
                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${
                                isRunning ? 'bg-primary/10' : 'bg-danger/10'
                            }`}>
                                <Activity className={`h-5 w-5 ${isRunning ? 'text-primary' : 'text-danger'}`} />
                            </div>
                            <div>
                                <p className="text-sm text-foreground-muted">Status</p>
                                <p className="text-xl font-bold text-foreground capitalize">{proxy.status}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-info/10">
                                <Globe className="h-5 w-5 text-info" />
                            </div>
                            <div>
                                <p className="text-sm text-foreground-muted">Domains</p>
                                <p className="text-xl font-bold text-foreground">{proxy.domains_count || 0}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-success/10">
                                <ShieldCheck className="h-5 w-5 text-success" />
                            </div>
                            <div>
                                <p className="text-sm text-foreground-muted">SSL Certificates</p>
                                <p className="text-xl font-bold text-foreground">{proxy.ssl_count || 0}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-warning/10">
                                <Activity className="h-5 w-5 text-warning" />
                            </div>
                            <div>
                                <p className="text-sm text-foreground-muted">Uptime</p>
                                <p className="text-xl font-bold text-foreground">{proxy.uptime || '--'}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Quick Actions */}
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Link href={`/servers/${server.uuid}/proxy/configuration`}>
                    <Card className="cursor-pointer transition-all hover:border-primary/50">
                        <CardContent className="flex items-center gap-4 p-6">
                            <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                <Settings className="h-6 w-6 text-primary" />
                            </div>
                            <div>
                                <h3 className="font-semibold text-foreground">Configuration</h3>
                                <p className="text-sm text-foreground-muted">Edit proxy config</p>
                            </div>
                        </CardContent>
                    </Card>
                </Link>

                <Link href={`/servers/${server.uuid}/proxy/logs`}>
                    <Card className="cursor-pointer transition-all hover:border-primary/50">
                        <CardContent className="flex items-center gap-4 p-6">
                            <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-info/10">
                                <FileText className="h-6 w-6 text-info" />
                            </div>
                            <div>
                                <h3 className="font-semibold text-foreground">Logs</h3>
                                <p className="text-sm text-foreground-muted">View proxy logs</p>
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
                                <p className="text-sm text-foreground-muted">Manage domains</p>
                            </div>
                        </CardContent>
                    </Card>
                </Link>

                <Link href={`/servers/${server.uuid}/proxy/settings`}>
                    <Card className="cursor-pointer transition-all hover:border-primary/50">
                        <CardContent className="flex items-center gap-4 p-6">
                            <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-warning/10">
                                <Settings className="h-6 w-6 text-warning" />
                            </div>
                            <div>
                                <h3 className="font-semibold text-foreground">Settings</h3>
                                <p className="text-sm text-foreground-muted">Proxy settings</p>
                            </div>
                        </CardContent>
                    </Card>
                </Link>
            </div>

            {/* Proxy Information */}
            <div className="mt-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Proxy Information</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <InfoRow label="Type" value={proxy.type.charAt(0).toUpperCase() + proxy.type.slice(1)} />
                        <InfoRow label="Status" value={
                            <div className="flex items-center gap-1">
                                {isRunning ? (
                                    <>
                                        <CheckCircle className="h-4 w-4 text-primary" />
                                        <span className="text-primary">Running</span>
                                    </>
                                ) : (
                                    <>
                                        <XCircle className="h-4 w-4 text-danger" />
                                        <span className="text-danger">Stopped</span>
                                    </>
                                )}
                            </div>
                        } />
                        {proxy.version && <InfoRow label="Version" value={proxy.version} />}
                        {proxy.uptime && <InfoRow label="Uptime" value={proxy.uptime} />}
                        <InfoRow label="Configured Domains" value={String(proxy.domains_count || 0)} />
                        <InfoRow label="SSL Certificates" value={String(proxy.ssl_count || 0)} />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
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
