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
    Activity,
    AlertTriangle,
} from 'lucide-react';

interface ServerInfo {
    id: number;
    uuid: string;
    name: string;
    description?: string;
    ip: string;
    is_reachable: boolean;
    is_build_server: boolean;
    team_id?: number;
    team_name?: string;
    created_at: string;
    updated_at?: string;
}

interface Props {
    servers: {
        data: ServerInfo[];
        total: number;
    };
}

function ServerRow({ server }: { server: ServerInfo }) {
    return (
        <div className="border-b border-border/50 py-4 last:border-0">
            <div className="flex items-start justify-between">
                <div className="flex-1">
                    <div className="flex items-center gap-3">
                        <Server className="h-5 w-5 text-foreground-muted" />
                        <div>
                            <div className="flex items-center gap-2">
                                <Link
                                    href={`/admin/servers/${server.uuid}`}
                                    className="font-medium text-foreground hover:text-primary"
                                >
                                    {server.name}
                                </Link>
                                {server.is_build_server && (
                                    <Badge variant="primary" size="sm">Build Server</Badge>
                                )}
                            </div>
                            <p className="text-sm text-foreground-muted">{server.ip}</p>
                            <div className="mt-1 flex items-center gap-3 text-xs text-foreground-subtle">
                                {server.team_name && <span>{server.team_name}</span>}
                                {server.description && (
                                    <>
                                        <span>·</span>
                                        <span>{server.description}</span>
                                    </>
                                )}
                            </div>
                        </div>
                    </div>
                </div>

                <div className="flex flex-col items-end gap-2">
                    <Badge variant={server.is_reachable ? 'success' : 'danger'} size="sm">
                        {server.is_reachable ? 'Reachable' : 'Unreachable'}
                    </Badge>
                    <span className="text-xs text-foreground-subtle">
                        {new Date(server.created_at).toLocaleDateString()}
                    </span>
                </div>
            </div>
        </div>
    );
}

export default function AdminServersIndex({ servers: serversData }: Props) {
    const items = serversData?.data ?? [];
    const total = serversData?.total ?? 0;
    const [searchQuery, setSearchQuery] = React.useState('');
    const [statusFilter, setStatusFilter] = React.useState<'all' | 'reachable' | 'unreachable'>('all');

    const filteredServers = items.filter((server) => {
        const matchesSearch =
            server.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            server.ip.includes(searchQuery) ||
            (server.team_name || '').toLowerCase().includes(searchQuery.toLowerCase());
        const matchesStatus = statusFilter === 'all' ||
            (statusFilter === 'reachable' && server.is_reachable) ||
            (statusFilter === 'unreachable' && !server.is_reachable);
        return matchesSearch && matchesStatus;
    });

    const reachableCount = items.filter((s) => s.is_reachable).length;
    const unreachableCount = items.filter((s) => !s.is_reachable).length;

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
                                    <p className="text-sm text-foreground-subtle">Total</p>
                                    <p className="text-2xl font-bold text-primary">{total}</p>
                                </div>
                                <Server className="h-8 w-8 text-primary/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Reachable</p>
                                    <p className="text-2xl font-bold text-success">{reachableCount}</p>
                                </div>
                                <Activity className="h-8 w-8 text-success/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Unreachable</p>
                                    <p className="text-2xl font-bold text-danger">{unreachableCount}</p>
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
                                    variant={statusFilter === 'reachable' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => setStatusFilter('reachable')}
                                >
                                    Reachable
                                </Button>
                                <Button
                                    variant={statusFilter === 'unreachable' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => setStatusFilter('unreachable')}
                                >
                                    Unreachable
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
