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
} from 'lucide-react';

interface ServiceInfo {
    id: number;
    name: string;
    uuid: string;
    description?: string;
    status: string;
    service_type?: string;
    team_id?: number;
    team_name?: string;
    environment_id?: number;
    environment_name?: string;
    project_name?: string;
    created_at: string;
    updated_at?: string;
}

interface Props {
    services: {
        data: ServiceInfo[];
        total: number;
    };
}

function ServiceRow({ service }: { service: ServiceInfo }) {
    const statusConfig: Record<string, { variant: 'success' | 'default' | 'danger' | 'warning'; label: string; icon: React.ReactNode }> = {
        running: { variant: 'success', label: 'Running', icon: <CheckCircle className="h-3 w-3" /> },
        stopped: { variant: 'default', label: 'Stopped', icon: <XCircle className="h-3 w-3" /> },
        error: { variant: 'danger', label: 'Error', icon: <AlertTriangle className="h-3 w-3" /> },
        starting: { variant: 'warning', label: 'Starting', icon: <AlertTriangle className="h-3 w-3" /> },
        exited: { variant: 'danger', label: 'Exited', icon: <XCircle className="h-3 w-3" /> },
    };

    const config = statusConfig[service.status] || { variant: 'default' as const, label: service.status || 'Unknown', icon: null };

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
                                {service.service_type && (
                                    <Badge variant="default" size="sm">
                                        {service.service_type}
                                    </Badge>
                                )}
                            </div>
                            <div className="mt-1 flex items-center gap-3 text-xs text-foreground-subtle">
                                {service.team_name && <span>{service.team_name}</span>}
                                {service.project_name && (
                                    <>
                                        <span>·</span>
                                        <span>{service.project_name}</span>
                                    </>
                                )}
                                {service.environment_name && (
                                    <>
                                        <span>·</span>
                                        <span>{service.environment_name}</span>
                                    </>
                                )}
                            </div>
                            <div className="mt-1 text-xs text-foreground-subtle">
                                Created: {new Date(service.created_at).toLocaleDateString()}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function AdminServicesIndex({ services: servicesData }: Props) {
    const items = servicesData?.data ?? [];
    const total = servicesData?.total ?? 0;
    const [searchQuery, setSearchQuery] = React.useState('');
    const [statusFilter, setStatusFilter] = React.useState<string>('all');

    const filteredServices = items.filter((service) => {
        const matchesSearch =
            service.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            (service.team_name || '').toLowerCase().includes(searchQuery.toLowerCase()) ||
            (service.project_name || '').toLowerCase().includes(searchQuery.toLowerCase());
        const matchesStatus = statusFilter === 'all' || service.status === statusFilter;
        return matchesSearch && matchesStatus;
    });

    const runningCount = items.filter((s) => s.status === 'running').length;
    const stoppedCount = items.filter((s) => s.status === 'stopped' || s.status === 'exited').length;
    const errorCount = items.filter((s) => s.status === 'error').length;

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
                <div className="mb-6 grid gap-4 sm:grid-cols-3">
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
