import { useState, useEffect } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Badge, Button } from '@/components/ui';
import { Dropdown, DropdownTrigger, DropdownContent, DropdownItem, DropdownDivider } from '@/components/ui/Dropdown';
import { Plus, Server, Cpu, HardDrive, Activity, MoreVertical, CheckCircle, XCircle, Settings, Trash2, RefreshCw, Terminal } from 'lucide-react';
import { useRealtimeStatus } from '@/hooks/useRealtimeStatus';
import type { Server as ServerType } from '@/types';

interface Props {
    servers: ServerType[];
}

export default function ServersIndex({ servers = [] }: Props) {
    // Track server statuses in state
    const [serverStatuses, setServerStatuses] = useState<Record<number, { isReachable: boolean; isUsable: boolean }>>({});

    // Initialize statuses from props
    useEffect(() => {
        const initialStatuses: Record<number, { isReachable: boolean; isUsable: boolean }> = {};
        servers.forEach(server => {
            initialStatuses[server.id] = {
                isReachable: server.is_reachable,
                isUsable: server.is_usable,
            };
        });
        setServerStatuses(initialStatuses);
    }, [servers]);

    // Real-time server status updates
    const { isConnected } = useRealtimeStatus({
        onServerStatusChange: (data) => {
            // Update server status when WebSocket event arrives
            setServerStatuses(prev => ({
                ...prev,
                [data.serverId]: {
                    isReachable: data.isReachable,
                    isUsable: data.isUsable,
                },
            }));
        },
    });

    // Get current status for a server
    const getServerStatus = (server: ServerType) => {
        return serverStatuses[server.id] || {
            isReachable: server.is_reachable,
            isUsable: server.is_usable,
        };
    };

    return (
        <AppLayout
            title="Servers"
            breadcrumbs={[{ label: 'Servers' }]}
        >
            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-foreground">Servers</h1>
                    <p className="text-foreground-muted">Manage your connected servers</p>
                </div>
                <Link href="/servers/create">
                    <Button>
                        <Plus className="mr-2 h-4 w-4" />
                        Add Server
                    </Button>
                </Link>
            </div>

            {/* Servers List */}
            {servers.length === 0 ? (
                <EmptyState />
            ) : (
                <div className="space-y-4">
                    {servers.map((server) => {
                        const status = getServerStatus(server);
                        return (
                            <ServerCard
                                key={server.id}
                                server={{
                                    ...server,
                                    is_reachable: status.isReachable,
                                    is_usable: status.isUsable,
                                }}
                            />
                        );
                    })}
                </div>
            )}
        </AppLayout>
    );
}

function ServerCard({ server }: { server: ServerType }) {
    const isOnline = server.is_reachable && server.is_usable;

    return (
        <Link href={`/servers/${server.uuid}`}>
            <Card className="transition-colors hover:border-primary/50">
                <CardContent className="p-4">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-4">
                            {/* Status Indicator */}
                            <div className={`flex h-12 w-12 items-center justify-center rounded-lg ${
                                isOnline ? 'bg-primary/10' : 'bg-danger/10'
                            }`}>
                                <Server className={`h-6 w-6 ${
                                    isOnline ? 'text-primary' : 'text-danger'
                                }`} />
                            </div>

                            {/* Server Info */}
                            <div>
                                <div className="flex items-center gap-2">
                                    <h3 className="font-medium text-foreground">{server.name}</h3>
                                    {isOnline ? (
                                        <Badge variant="success">Online</Badge>
                                    ) : (
                                        <Badge variant="danger">Offline</Badge>
                                    )}
                                </div>
                                <p className="text-sm text-foreground-muted">
                                    {server.ip}:{server.port} • {server.user}
                                </p>
                            </div>
                        </div>

                        {/* Stats */}
                        <div className="flex items-center gap-6">
                            <div className="text-right">
                                <p className="text-sm text-foreground-muted">Status</p>
                                <div className="flex items-center gap-1">
                                    {isOnline ? (
                                        <CheckCircle className="h-4 w-4 text-primary" />
                                    ) : (
                                        <XCircle className="h-4 w-4 text-danger" />
                                    )}
                                    <span className="text-sm text-foreground">
                                        {isOnline ? 'Connected' : 'Disconnected'}
                                    </span>
                                </div>
                            </div>

                            <Dropdown>
                                <DropdownTrigger>
                                    <button
                                        className="rounded-md p-2 text-foreground-muted hover:bg-background-tertiary hover:text-foreground"
                                        onClick={(e) => e.preventDefault()}
                                    >
                                        <MoreVertical className="h-4 w-4" />
                                    </button>
                                </DropdownTrigger>
                                <DropdownContent align="right">
                                    <DropdownItem onClick={(e) => {
                                        e.preventDefault();
                                        router.post(`/servers/${server.uuid}/validate`);
                                    }}>
                                        <RefreshCw className="h-4 w-4" />
                                        Validate Server
                                    </DropdownItem>
                                    <DropdownItem onClick={(e) => {
                                        e.preventDefault();
                                        router.visit(`/servers/${server.uuid}/terminal`);
                                    }}>
                                        <Terminal className="h-4 w-4" />
                                        Open Terminal
                                    </DropdownItem>
                                    <DropdownItem onClick={(e) => {
                                        e.preventDefault();
                                        router.visit(`/servers/${server.uuid}/settings`);
                                    }}>
                                        <Settings className="h-4 w-4" />
                                        Server Settings
                                    </DropdownItem>
                                    <DropdownDivider />
                                    <DropdownItem onClick={(e) => {
                                        e.preventDefault();
                                        if (confirm(`Are you sure you want to delete "${server.name}"? This action cannot be undone.`)) {
                                            router.delete(`/servers/${server.uuid}`);
                                        }
                                    }} danger>
                                        <Trash2 className="h-4 w-4" />
                                        Delete Server
                                    </DropdownItem>
                                </DropdownContent>
                            </Dropdown>
                        </div>
                    </div>

                    {/* Description */}
                    {server.description && (
                        <p className="mt-3 text-sm text-foreground-muted">{server.description}</p>
                    )}
                </CardContent>
            </Card>
        </Link>
    );
}

function EmptyState() {
    return (
        <Card className="p-12 text-center">
            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary">
                <Server className="h-8 w-8 text-foreground-muted" />
            </div>
            <h3 className="mt-4 text-lg font-medium text-foreground">No servers connected</h3>
            <p className="mt-2 text-foreground-muted">
                Add your first server to start deploying applications.
            </p>
            <Link href="/servers/create" className="mt-6 inline-block">
                <Button>
                    <Plus className="mr-2 h-4 w-4" />
                    Add Server
                </Button>
            </Link>
        </Card>
    );
}
