import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import {
    Search,
    Server,
    Activity,
    AlertTriangle,
    Tag,
    X,
} from 'lucide-react';

interface ServerInfo {
    id: number;
    uuid: string;
    name: string;
    description?: string;
    ip: string;
    is_reachable: boolean;
    is_usable: boolean;
    is_build_server: boolean;
    tags?: string[];
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
    allTags?: string[];
    filters?: {
        search?: string;
        status?: string;
        tag?: string;
    };
}

function ServerRow({ server }: { server: ServerInfo }) {
    const getStatusBadge = () => {
        if (!server.is_reachable) {
            return <Badge variant="danger" size="sm">Unreachable</Badge>;
        }
        if (!server.is_usable) {
            return <Badge variant="warning" size="sm">Degraded</Badge>;
        }
        return <Badge variant="success" size="sm">Healthy</Badge>;
    };

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
                            {server.tags && server.tags.length > 0 && (
                                <div className="mt-2 flex flex-wrap gap-1">
                                    {server.tags.map((tag) => (
                                        <Badge key={tag} variant="secondary" size="sm" className="text-xs">
                                            <Tag className="mr-1 h-3 w-3" />
                                            {tag}
                                        </Badge>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                <div className="flex flex-col items-end gap-2">
                    {getStatusBadge()}
                    <span className="text-xs text-foreground-subtle">
                        {new Date(server.created_at).toLocaleDateString()}
                    </span>
                </div>
            </div>
        </div>
    );
}

export default function AdminServersIndex({ servers: serversData, allTags = [], filters = {} }: Props) {
    const items = serversData?.data ?? [];
    const total = serversData?.total ?? 0;
    const [searchQuery, setSearchQuery] = React.useState(filters.search ?? '');
    const [statusFilter, setStatusFilter] = React.useState(filters.status ?? 'all');
    const [tagFilter, setTagFilter] = React.useState(filters.tag ?? '');

    // Debounced search
    React.useEffect(() => {
        const timer = setTimeout(() => {
            if (searchQuery !== (filters.search ?? '')) {
                applyFilters({ search: searchQuery });
            }
        }, 300);
        return () => clearTimeout(timer);
    }, [searchQuery]);

    const applyFilters = (newFilters: Record<string, string | undefined>) => {
        const params = new URLSearchParams();
        const merged = {
            search: filters.search,
            status: filters.status,
            tag: filters.tag,
            ...newFilters,
        };

        Object.entries(merged).forEach(([key, value]) => {
            if (value && value !== 'all') {
                params.set(key, value);
            }
        });

        router.get(`/admin/servers?${params.toString()}`, {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleStatusChange = (status: string) => {
        setStatusFilter(status);
        applyFilters({ status: status === 'all' ? undefined : status });
    };

    const handleTagChange = (tag: string) => {
        setTagFilter(tag);
        applyFilters({ tag: tag || undefined });
    };

    const clearFilters = () => {
        setSearchQuery('');
        setStatusFilter('all');
        setTagFilter('');
        router.get('/admin/servers');
    };

    const healthyCount = items.filter((s) => s.is_reachable && s.is_usable).length;
    const degradedCount = items.filter((s) => s.is_reachable && !s.is_usable).length;
    const unreachableCount = items.filter((s) => !s.is_reachable).length;

    const hasActiveFilters = filters.search || filters.status || filters.tag;

    return (
        <AdminLayout
            title="Servers"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Servers' },
            ]}
        >
            <div className="mx-auto max-w-7xl">
                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-2xl font-semibold text-foreground">Server Management</h1>
                    <p className="mt-1 text-sm text-foreground-muted">
                        Monitor all servers across your Saturn Platform instance
                    </p>
                </div>

                {/* Stats */}
                <div className="mb-6 grid gap-4 sm:grid-cols-4">
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
                                    <p className="text-sm text-foreground-subtle">Healthy</p>
                                    <p className="text-2xl font-bold text-success">{healthyCount}</p>
                                </div>
                                <Activity className="h-8 w-8 text-success/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Degraded</p>
                                    <p className="text-2xl font-bold text-warning">{degradedCount}</p>
                                </div>
                                <AlertTriangle className="h-8 w-8 text-warning/50" />
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
                        <div className="flex flex-col gap-4">
                            <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                                <div className="relative flex-1">
                                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                                    <Input
                                        placeholder="Search servers by name, IP, or description..."
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
                                        className="pl-10"
                                    />
                                </div>
                                <div className="flex gap-2">
                                    <Button
                                        variant={statusFilter === 'all' ? 'primary' : 'secondary'}
                                        size="sm"
                                        onClick={() => handleStatusChange('all')}
                                    >
                                        All
                                    </Button>
                                    <Button
                                        variant={statusFilter === 'reachable' ? 'primary' : 'secondary'}
                                        size="sm"
                                        onClick={() => handleStatusChange('reachable')}
                                    >
                                        Healthy
                                    </Button>
                                    <Button
                                        variant={statusFilter === 'degraded' ? 'primary' : 'secondary'}
                                        size="sm"
                                        onClick={() => handleStatusChange('degraded')}
                                    >
                                        Degraded
                                    </Button>
                                    <Button
                                        variant={statusFilter === 'unreachable' ? 'primary' : 'secondary'}
                                        size="sm"
                                        onClick={() => handleStatusChange('unreachable')}
                                    >
                                        Unreachable
                                    </Button>
                                </div>
                            </div>

                            {allTags.length > 0 && (
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="text-sm text-foreground-muted">Filter by tag:</span>
                                    {allTags.map((tag) => (
                                        <Button
                                            key={tag}
                                            variant={tagFilter === tag ? 'primary' : 'ghost'}
                                            size="sm"
                                            onClick={() => handleTagChange(tagFilter === tag ? '' : tag)}
                                            className="h-7"
                                        >
                                            <Tag className="mr-1 h-3 w-3" />
                                            {tag}
                                        </Button>
                                    ))}
                                </div>
                            )}

                            {hasActiveFilters && (
                                <div className="flex items-center gap-2">
                                    <span className="text-sm text-foreground-muted">Active filters:</span>
                                    {filters.search && (
                                        <Badge variant="secondary" className="flex items-center gap-1">
                                            Search: {filters.search}
                                            <X className="h-3 w-3 cursor-pointer" onClick={() => { setSearchQuery(''); applyFilters({ search: undefined }); }} />
                                        </Badge>
                                    )}
                                    {filters.status && (
                                        <Badge variant="secondary" className="flex items-center gap-1">
                                            Status: {filters.status}
                                            <X className="h-3 w-3 cursor-pointer" onClick={() => handleStatusChange('all')} />
                                        </Badge>
                                    )}
                                    {filters.tag && (
                                        <Badge variant="secondary" className="flex items-center gap-1">
                                            Tag: {filters.tag}
                                            <X className="h-3 w-3 cursor-pointer" onClick={() => handleTagChange('')} />
                                        </Badge>
                                    )}
                                    <Button variant="ghost" size="sm" onClick={clearFilters}>
                                        Clear all
                                    </Button>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Servers List */}
                <Card variant="glass">
                    <CardContent className="p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <p className="text-sm text-foreground-muted">
                                Showing {items.length} of {total} servers
                            </p>
                        </div>

                        {items.length === 0 ? (
                            <div className="py-12 text-center">
                                <Server className="mx-auto h-12 w-12 text-foreground-muted" />
                                <p className="mt-4 text-sm text-foreground-muted">No servers found</p>
                                <p className="text-xs text-foreground-subtle">
                                    Try adjusting your search or filters
                                </p>
                            </div>
                        ) : (
                            <div>
                                {items.map((server) => (
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
