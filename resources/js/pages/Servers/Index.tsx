import { useState, useEffect } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Badge, Button, useConfirm } from '@/components/ui';
import { Dropdown, DropdownTrigger, DropdownContent, DropdownItem, DropdownDivider } from '@/components/ui/Dropdown';
import { Plus, Server, MoreVertical, CheckCircle, XCircle, Settings, Trash2, RefreshCw, Terminal } from 'lucide-react';
import { StaggerList, StaggerItem, FadeIn } from '@/components/animation';
import { useRealtimeStatus } from '@/hooks/useRealtimeStatus';
import { usePermissions } from '@/hooks/usePermissions';
import type { Server as ServerType } from '@/types';

interface Props {
    servers: ServerType[];
}

export default function ServersIndex({ servers = [] }: Props) {
    const { can } = usePermissions();
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
    const { isConnected: _isConnected } = useRealtimeStatus({
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
            <div className="mx-auto max-w-6xl">
            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-foreground">Servers</h1>
                    <p className="text-foreground-muted">Manage your connected servers</p>
                </div>
                {can('servers.create') && (
                    <Link href="/servers/create">
                        <Button className="group">
                            <Plus className="mr-2 h-4 w-4 group-hover:animate-wiggle" />
                            Add Server
                        </Button>
                    </Link>
                )}
            </div>

            {/* Servers List */}
            {servers.length === 0 ? (
                <EmptyState canCreate={can('servers.create')} />
            ) : (
                <StaggerList className="space-y-4">
                    {servers.map((server, i) => {
                        const status = getServerStatus(server);
                        return (
                            <StaggerItem key={server.id} index={i}>
                                <ServerCard
                                    server={{
                                        ...server,
                                        is_reachable: status.isReachable,
                                        is_usable: status.isUsable,
                                    }}
                                    can={can}
                                />
                            </StaggerItem>
                        );
                    })}
                </StaggerList>
            )}
            </div>
        </AppLayout>
    );
}

function ServerCard({ server, can }: { server: ServerType; can: (permission: string) => boolean }) {
    const confirm = useConfirm();
    const isOnline = server.is_reachable && server.is_usable;

    const handleDelete = async (e: React.MouseEvent) => {
        e.preventDefault();
        const confirmed = await confirm({
            title: 'Delete Server',
            description: `Are you sure you want to delete "${server.name}"? This action cannot be undone.`,
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            router.delete(`/servers/${server.uuid}`);
        }
    };

    return (
        <Link href={`/servers/${server.uuid}`} className="group">
            <Card className="transition-colors hover:border-primary/50">
                <CardContent className="p-4">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-4">
                            {/* Status Indicator */}
                            <div className={`flex h-12 w-12 items-center justify-center rounded-lg ${
                                isOnline ? 'bg-primary/10' : 'bg-danger/10'
                            }`}>
                                <Server className={`h-6 w-6 transition-transform duration-200 group-hover:animate-wiggle ${
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
                                    <DropdownItem
                                        icon={<RefreshCw className="h-4 w-4" />}
                                        onClick={(e) => {
                                            e.preventDefault();
                                            router.post(`/servers/${server.uuid}/validate`);
                                        }}
                                    >
                                        Validate Server
                                    </DropdownItem>
                                    {can('applications.terminal') && (
                                        <DropdownItem
                                            icon={<Terminal className="h-4 w-4" />}
                                            onClick={(e) => {
                                                e.preventDefault();
                                                router.visit(`/servers/${server.uuid}/terminal`);
                                            }}
                                        >
                                            Open Terminal
                                        </DropdownItem>
                                    )}
                                    <DropdownItem
                                        icon={<Settings className="h-4 w-4" />}
                                        onClick={(e) => {
                                            e.preventDefault();
                                            router.visit(`/servers/${server.uuid}/settings`);
                                        }}
                                    >
                                        Server Settings
                                    </DropdownItem>
                                    {!server.is_localhost && can('servers.delete') && (
                                        <>
                                            <DropdownDivider />
                                            <DropdownItem
                                                icon={<Trash2 className="h-4 w-4" />}
                                                onClick={handleDelete}
                                                danger
                                            >
                                                Delete Server
                                            </DropdownItem>
                                        </>
                                    )}
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

function EmptyState({ canCreate }: { canCreate: boolean }) {
    return (
        <FadeIn>
            <Card className="p-12 text-center">
                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary">
                    <Server className="h-8 w-8 text-foreground-muted animate-pulse-soft" />
                </div>
                <h3 className="mt-4 text-lg font-medium text-foreground">No servers connected</h3>
                <p className="mt-2 text-foreground-muted">
                    {canCreate
                        ? 'Add your first server to start deploying applications.'
                        : 'No servers have been connected yet. Contact a team admin to add a server.'}
                </p>
                {canCreate && (
                    <Link href="/servers/create" className="mt-6 inline-block">
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Server
                        </Button>
                    </Link>
                )}
            </Card>
        </FadeIn>
    );
}
