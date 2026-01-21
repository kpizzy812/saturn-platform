import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Link } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import {
    Search,
    Layers,
    CheckCircle,
    XCircle,
    AlertTriangle,
    Server,
} from 'lucide-react';

interface ServiceInfo {
    id: number;
    name: string;
    uuid: string;
    type: string;
    status: 'running' | 'stopped' | 'error' | 'starting';
    user: string;
    team: string;
    server: string;
    server_ip?: string;
    containers_count: number;
    fqdn?: string;
    created_at: string;
}

interface Props {
    services: ServiceInfo[];
    total: number;
}

const defaultServices: ServiceInfo[] = [
    {
        id: 1,
        name: 'production-stack',
        uuid: 'svc-1234-5678',
        type: 'docker-compose',
        status: 'running',
        user: 'john.doe@example.com',
        team: 'Production Team',
        server: 'production-1',
        server_ip: '192.168.1.100',
        containers_count: 5,
        fqdn: 'stack.example.com',
        created_at: '2024-01-15',
    },
    {
        id: 2,
        name: 'monitoring-suite',
        uuid: 'svc-2345-6789',
        type: 'docker-compose',
        status: 'running',
        user: 'jane.smith@example.com',
        team: 'DevOps Team',
        server: 'monitoring-1',
        server_ip: '192.168.1.105',
        containers_count: 3,
        fqdn: 'monitoring.example.com',
        created_at: '2024-02-01',
    },
    {
        id: 3,
        name: 'dev-stack',
        uuid: 'svc-3456-7890',
        type: 'docker-compose',
        status: 'starting',
        user: 'bob.wilson@example.com',
        team: 'Dev Team',
        server: 'development-1',
        server_ip: '192.168.1.102',
        containers_count: 4,
        created_at: '2024-03-01',
    },
    {
        id: 4,
        name: 'legacy-service',
        uuid: 'svc-4567-8901',
        type: 'docker-compose',
        status: 'stopped',
        user: 'admin@example.com',
        team: 'Infrastructure',
        server: 'legacy-1',
        server_ip: '192.168.1.110',
        containers_count: 2,
        created_at: '2023-12-10',
    },
    {
        id: 5,
        name: 'analytics-stack',
        uuid: 'svc-5678-9012',
        type: 'docker-compose',
        status: 'error',
        user: 'data@example.com',
        team: 'Data Team',
        server: 'analytics-1',
        server_ip: '192.168.1.120',
        containers_count: 6,
        fqdn: 'analytics.example.com',
        created_at: '2024-01-20',
    },
];

function ServiceRow({ service }: { service: ServiceInfo }) {
    const statusConfig = {
        running: { variant: 'success' as const, label: 'Running', icon: <CheckCircle className="h-3 w-3" /> },
        stopped: { variant: 'default' as const, label: 'Stopped', icon: <XCircle className="h-3 w-3" /> },
        error: { variant: 'danger' as const, label: 'Error', icon: <AlertTriangle className="h-3 w-3" /> },
        starting: { variant: 'warning' as const, label: 'Starting', icon: <AlertTriangle className="h-3 w-3" /> },
    };

    const config = statusConfig[service.status];

    return (
        <div className="border-b border-border/50 py-4 last:border-0">
            <div className="flex items-start justify-between">
                <div className="flex-1">
                    <div className="flex items-center gap-3">
                        <Layers className="h-5 w-5 text-foreground-muted" />
                        <div>
                            <div className="flex items-center gap-2">
                                <Link
                                    href={`/admin/services/${service.uuid}`}
                                    className="font-medium text-foreground hover:text-primary"
                                >
                                    {service.name}
                                </Link>
                                <Badge variant={config.variant} size="sm" icon={config.icon}>
                                    {config.label}
                                </Badge>
                                <Badge variant="default" size="sm">
                                    {service.containers_count} containers
                                </Badge>
                            </div>
                            {service.fqdn && (
                                <p className="text-sm text-foreground-muted">{service.fqdn}</p>
                            )}
                            <div className="mt-1 flex items-center gap-3 text-xs text-foreground-subtle">
                                <span>{service.user}</span>
                                <span>·</span>
                                <span>{service.team}</span>
                                <span>·</span>
                                <span className="flex items-center gap-1">
                                    <Server className="h-3 w-3" />
                                    {service.server}
                                    {service.server_ip && ` (${service.server_ip})`}
                                </span>
                            </div>
                            <div className="mt-1 text-xs text-foreground-subtle">
                                Type: {service.type} · Created: {new Date(service.created_at).toLocaleDateString()}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function AdminServicesIndex({ services = defaultServices, total = 5 }: Props) {
    const [searchQuery, setSearchQuery] = React.useState('');
    const [statusFilter, setStatusFilter] = React.useState<'all' | 'running' | 'stopped' | 'error' | 'starting'>('all');

    const filteredServices = services.filter((service) => {
        const matchesSearch =
            service.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            (service.fqdn && service.fqdn.toLowerCase().includes(searchQuery.toLowerCase())) ||
            service.user.toLowerCase().includes(searchQuery.toLowerCase()) ||
            service.team.toLowerCase().includes(searchQuery.toLowerCase()) ||
            service.server.toLowerCase().includes(searchQuery.toLowerCase());
        const matchesStatus = statusFilter === 'all' || service.status === statusFilter;
        return matchesSearch && matchesStatus;
    });

    const runningCount = services.filter((s) => s.status === 'running').length;
    const stoppedCount = services.filter((s) => s.status === 'stopped').length;
    const errorCount = services.filter((s) => s.status === 'error').length;
    const totalContainers = services.reduce((sum, s) => sum + s.containers_count, 0);

    return (
        <AdminLayout
            title="Services"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Services' },
            ]}
        >
            <div className="mx-auto max-w-7xl px-6 py-8">
                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-2xl font-semibold text-foreground">Service Management</h1>
                    <p className="mt-1 text-sm text-foreground-muted">
                        Monitor all services across your Saturn Platform instance
                    </p>
                </div>

                {/* Stats */}
                <div className="mb-6 grid gap-4 sm:grid-cols-4">
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Running</p>
                                    <p className="text-2xl font-bold text-success">{runningCount}</p>
                                </div>
                                <CheckCircle className="h-8 w-8 text-success/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Stopped</p>
                                    <p className="text-2xl font-bold text-foreground-muted">{stoppedCount}</p>
                                </div>
                                <XCircle className="h-8 w-8 text-foreground-muted/50" />
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
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Containers</p>
                                    <p className="text-2xl font-bold text-foreground">{totalContainers}</p>
                                </div>
                                <Layers className="h-8 w-8 text-foreground-muted/50" />
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
                                    placeholder="Search services by name, domain, user, team, or server..."
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
                                    variant={statusFilter === 'running' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => setStatusFilter('running')}
                                >
                                    Running
                                </Button>
                                <Button
                                    variant={statusFilter === 'stopped' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => setStatusFilter('stopped')}
                                >
                                    Stopped
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

                {/* Services List */}
                <Card variant="glass">
                    <CardContent className="p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <p className="text-sm text-foreground-muted">
                                Showing {filteredServices.length} of {total} services
                            </p>
                        </div>

                        {filteredServices.length === 0 ? (
                            <div className="py-12 text-center">
                                <Layers className="mx-auto h-12 w-12 text-foreground-muted" />
                                <p className="mt-4 text-sm text-foreground-muted">No services found</p>
                                <p className="text-xs text-foreground-subtle">
                                    Try adjusting your search or filters
                                </p>
                            </div>
                        ) : (
                            <div>
                                {filteredServices.map((service) => (
                                    <ServiceRow key={service.id} service={service} />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
