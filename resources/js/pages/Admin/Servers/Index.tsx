import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Link } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import {
    Search,
    Server,
    Cpu,
    HardDrive,
    Activity,
    AlertTriangle,
} from 'lucide-react';

interface ServerInfo {
    id: number;
    name: string;
    ip: string;
    status: 'online' | 'offline' | 'error';
    user: string;
    team: string;
    cpu_usage?: number;
    memory_usage?: number;
    disk_usage?: number;
    uptime?: string;
    applications_count: number;
    last_seen?: string;
}

interface Props {
    servers: ServerInfo[];
    total: number;
}

const defaultServers: ServerInfo[] = [
    {
        id: 1,
        name: 'production-1',
        ip: '192.168.1.100',
        status: 'online',
        user: 'john.doe@example.com',
        team: 'Production Team',
        cpu_usage: 45,
        memory_usage: 67,
        disk_usage: 82,
        uptime: '45 days',
        applications_count: 8,
        last_seen: '2 minutes ago',
    },
    {
        id: 2,
        name: 'staging-1',
        ip: '192.168.1.101',
        status: 'online',
        user: 'jane.smith@example.com',
        team: 'Staging Team',
        cpu_usage: 23,
        memory_usage: 45,
        disk_usage: 56,
        uptime: '30 days',
        applications_count: 5,
        last_seen: '5 minutes ago',
    },
    {
        id: 3,
        name: 'development-1',
        ip: '192.168.1.102',
        status: 'offline',
        user: 'bob.wilson@example.com',
        team: 'Dev Team',
        cpu_usage: 0,
        memory_usage: 0,
        disk_usage: 45,
        applications_count: 3,
        last_seen: '2 hours ago',
    },
    {
        id: 4,
        name: 'backup-server',
        ip: '192.168.1.103',
        status: 'error',
        user: 'admin@example.com',
        team: 'Infrastructure',
        cpu_usage: 89,
        memory_usage: 95,
        disk_usage: 98,
        uptime: '12 days',
        applications_count: 2,
        last_seen: '30 minutes ago',
    },
];

function ResourceBar({ value, label }: { value: number; label: string }) {
    const getColor = (val: number) => {
        if (val >= 90) return 'bg-danger';
        if (val >= 75) return 'bg-warning';
        return 'bg-success';
    };

    return (
        <div>
            <div className="mb-1 flex items-center justify-between">
                <span className="text-xs text-foreground-subtle">{label}</span>
                <span className="text-xs font-medium text-foreground">{value}%</span>
            </div>
            <div className="h-1.5 w-full overflow-hidden rounded-full bg-background-tertiary">
                <div
                    className={`h-full transition-all ${getColor(value)}`}
                    style={{ width: `${value}%` }}
                />
            </div>
        </div>
    );
}

function ServerRow({ server }: { server: ServerInfo }) {
    const statusConfig = {
        online: { variant: 'success' as const, label: 'Online' },
        offline: { variant: 'default' as const, label: 'Offline' },
        error: { variant: 'danger' as const, label: 'Error' },
    };

    const config = statusConfig[server.status];
    const hasHighUsage = (server.cpu_usage ?? 0) > 80 || (server.memory_usage ?? 0) > 80 || (server.disk_usage ?? 0) > 80;

    return (
        <div className="border-b border-border/50 py-4 last:border-0">
            <div className="flex items-start justify-between">
                <div className="flex-1">
                    <div className="flex items-center gap-3">
                        <Server className="h-5 w-5 text-foreground-muted" />
                        <div>
                            <div className="flex items-center gap-2">
                                <Link
                                    href={`/admin/servers/${server.id}`}
                                    className="font-medium text-foreground hover:text-primary"
                                >
                                    {server.name}
                                </Link>
                                {hasHighUsage && (
                                    <AlertTriangle className="h-4 w-4 text-warning" />
                                )}
                            </div>
                            <p className="text-sm text-foreground-muted">{server.ip}</p>
                            <div className="mt-1 flex items-center gap-3 text-xs text-foreground-subtle">
                                <span>{server.user}</span>
                                <span>·</span>
                                <span>{server.team}</span>
                                <span>·</span>
                                <span>{server.applications_count} apps</span>
                                {server.uptime && (
                                    <>
                                        <span>·</span>
                                        <span>Uptime: {server.uptime}</span>
                                    </>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Resource Usage */}
                    {server.status === 'online' && (
                        <div className="ml-8 mt-4 grid gap-3 sm:grid-cols-3">
                            {server.cpu_usage !== undefined && (
                                <ResourceBar value={server.cpu_usage} label="CPU" />
                            )}
                            {server.memory_usage !== undefined && (
                                <ResourceBar value={server.memory_usage} label="Memory" />
                            )}
                            {server.disk_usage !== undefined && (
                                <ResourceBar value={server.disk_usage} label="Disk" />
                            )}
                        </div>
                    )}
                </div>

                <div className="flex flex-col items-end gap-2">
                    <Badge variant={config.variant} size="sm">
                        {config.label}
                    </Badge>
                    {server.last_seen && (
                        <span className="text-xs text-foreground-subtle">
                            Last seen: {server.last_seen}
                        </span>
                    )}
                </div>
            </div>
        </div>
    );
}

export default function AdminServersIndex({ servers = defaultServers, total = 4 }: Props) {
    const [searchQuery, setSearchQuery] = React.useState('');
    const [statusFilter, setStatusFilter] = React.useState<'all' | 'online' | 'offline' | 'error'>('all');

    const filteredServers = servers.filter((server) => {
        const matchesSearch =
            server.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            server.ip.includes(searchQuery) ||
            server.user.toLowerCase().includes(searchQuery.toLowerCase()) ||
            server.team.toLowerCase().includes(searchQuery.toLowerCase());
        const matchesStatus = statusFilter === 'all' || server.status === statusFilter;
        return matchesSearch && matchesStatus;
    });

    const onlineCount = servers.filter((s) => s.status === 'online').length;
    const offlineCount = servers.filter((s) => s.status === 'offline').length;
    const errorCount = servers.filter((s) => s.status === 'error').length;

    return (
        <AdminLayout
            title="Servers"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Servers' },
            ]}
        >
            <div className="mx-auto max-w-7xl px-6 py-8">
                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-2xl font-semibold text-foreground">Server Management</h1>
                    <p className="mt-1 text-sm text-foreground-muted">
                        Monitor all servers across your Saturn Platform instance
                    </p>
                </div>

                {/* Stats */}
                <div className="mb-6 grid gap-4 sm:grid-cols-3">
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Online</p>
                                    <p className="text-2xl font-bold text-success">{onlineCount}</p>
                                </div>
                                <Activity className="h-8 w-8 text-success/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Offline</p>
                                    <p className="text-2xl font-bold text-foreground-muted">{offlineCount}</p>
                                </div>
                                <Server className="h-8 w-8 text-foreground-muted/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Errors</p>
                                    <p className="text-2xl font-bold text-danger">{errorCount}</p>
                                </div>
                                <AlertTriangle className="h-8 w-8 text-danger/50" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <Card variant="glass" className="mb-6">
                    <CardContent className="p-4">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="relative flex-1">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                                <Input
                                    placeholder="Search servers by name, IP, user, or team..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="pl-10"
                                />
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    variant={statusFilter === 'all' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => setStatusFilter('all')}
                                >
                                    All
                                </Button>
                                <Button
                                    variant={statusFilter === 'online' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => setStatusFilter('online')}
                                >
                                    Online
                                </Button>
                                <Button
                                    variant={statusFilter === 'offline' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => setStatusFilter('offline')}
                                >
                                    Offline
                                </Button>
                                <Button
                                    variant={statusFilter === 'error' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => setStatusFilter('error')}
                                >
                                    Errors
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Servers List */}
                <Card variant="glass">
                    <CardContent className="p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <p className="text-sm text-foreground-muted">
                                Showing {filteredServers.length} of {total} servers
                            </p>
                        </div>

                        {filteredServers.length === 0 ? (
                            <div className="py-12 text-center">
                                <Server className="mx-auto h-12 w-12 text-foreground-muted" />
                                <p className="mt-4 text-sm text-foreground-muted">No servers found</p>
                                <p className="text-xs text-foreground-subtle">
                                    Try adjusting your search or filters
                                </p>
                            </div>
                        ) : (
                            <div>
                                {filteredServers.map((server) => (
                                    <ServerRow key={server.id} server={server} />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
