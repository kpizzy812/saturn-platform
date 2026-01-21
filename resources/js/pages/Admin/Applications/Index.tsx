import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Link } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import {
    Search,
    Package,
    Activity,
    AlertTriangle,
    CheckCircle,
    XCircle,
    Clock,
} from 'lucide-react';

interface Application {
    id: number;
    name: string;
    uuid: string;
    status: 'running' | 'stopped' | 'deploying' | 'error';
    user: string;
    team: string;
    fqdn?: string;
    git_repository?: string;
    cpu_usage?: number;
    memory_usage?: number;
    deployed_at?: string;
    last_deployment_status?: 'success' | 'failed' | 'in_progress';
}

interface Props {
    applications: Application[];
    total: number;
}

const defaultApplications: Application[] = [
    {
        id: 1,
        name: 'production-api',
        uuid: 'app-1234-5678',
        status: 'running',
        user: 'john.doe@example.com',
        team: 'Production Team',
        fqdn: 'api.example.com',
        git_repository: 'github.com/company/api',
        cpu_usage: 45,
        memory_usage: 512,
        deployed_at: '2024-03-10 14:30',
        last_deployment_status: 'success',
    },
    {
        id: 2,
        name: 'staging-web',
        uuid: 'app-2345-6789',
        status: 'running',
        user: 'jane.smith@example.com',
        team: 'Staging Team',
        fqdn: 'staging.example.com',
        git_repository: 'github.com/company/web',
        cpu_usage: 23,
        memory_usage: 256,
        deployed_at: '2024-03-10 12:15',
        last_deployment_status: 'success',
    },
    {
        id: 3,
        name: 'worker-service',
        uuid: 'app-3456-7890',
        status: 'deploying',
        user: 'bob.wilson@example.com',
        team: 'Dev Team',
        git_repository: 'github.com/company/worker',
        cpu_usage: 0,
        memory_usage: 0,
        last_deployment_status: 'in_progress',
    },
    {
        id: 4,
        name: 'legacy-app',
        uuid: 'app-4567-8901',
        status: 'error',
        user: 'admin@example.com',
        team: 'Infrastructure',
        fqdn: 'legacy.example.com',
        git_repository: 'github.com/company/legacy',
        cpu_usage: 0,
        memory_usage: 0,
        deployed_at: '2024-03-09 18:00',
        last_deployment_status: 'failed',
    },
];

function ResourceUsage({ cpu, memory }: { cpu?: number; memory?: number }) {
    if (cpu === undefined && memory === undefined) {
        return <span className="text-xs text-foreground-subtle">N/A</span>;
    }

    return (
        <div className="flex flex-col gap-1">
            {cpu !== undefined && (
                <span className="text-xs text-foreground-muted">CPU: {cpu}%</span>
            )}
            {memory !== undefined && (
                <span className="text-xs text-foreground-muted">Mem: {memory}MB</span>
            )}
        </div>
    );
}

function ApplicationRow({ app }: { app: Application }) {
    const statusConfig = {
        running: { variant: 'success' as const, label: 'Running', icon: <CheckCircle className="h-3 w-3" /> },
        stopped: { variant: 'default' as const, label: 'Stopped', icon: <XCircle className="h-3 w-3" /> },
        deploying: { variant: 'warning' as const, label: 'Deploying', icon: <Clock className="h-3 w-3" /> },
        error: { variant: 'danger' as const, label: 'Error', icon: <AlertTriangle className="h-3 w-3" /> },
    };

    const deploymentConfig = {
        success: { variant: 'success' as const, label: 'Success' },
        failed: { variant: 'danger' as const, label: 'Failed' },
        in_progress: { variant: 'warning' as const, label: 'In Progress' },
    };

    const config = statusConfig[app.status];
    const deploymentStatus = app.last_deployment_status ? deploymentConfig[app.last_deployment_status] : null;

    return (
        <div className="border-b border-border/50 py-4 last:border-0">
            <div className="flex items-start justify-between">
                <div className="flex-1">
                    <div className="flex items-center gap-3">
                        <Package className="h-5 w-5 text-foreground-muted" />
                        <div>
                            <div className="flex items-center gap-2">
                                <Link
                                    href={`/admin/applications/${app.uuid}`}
                                    className="font-medium text-foreground hover:text-primary"
                                >
                                    {app.name}
                                </Link>
                                <Badge variant={config.variant} size="sm" icon={config.icon}>
                                    {config.label}
                                </Badge>
                                {deploymentStatus && (
                                    <Badge variant={deploymentStatus.variant} size="sm">
                                        {deploymentStatus.label}
                                    </Badge>
                                )}
                            </div>
                            {app.fqdn && (
                                <p className="text-sm text-foreground-muted">{app.fqdn}</p>
                            )}
                            <div className="mt-1 flex items-center gap-3 text-xs text-foreground-subtle">
                                <span>{app.user}</span>
                                <span>·</span>
                                <span>{app.team}</span>
                                {app.git_repository && (
                                    <>
                                        <span>·</span>
                                        <span className="truncate max-w-xs">{app.git_repository}</span>
                                    </>
                                )}
                            </div>
                            {app.deployed_at && (
                                <div className="mt-1 text-xs text-foreground-subtle">
                                    Last deployed: {app.deployed_at}
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                <div className="ml-4">
                    <ResourceUsage cpu={app.cpu_usage} memory={app.memory_usage} />
                </div>
            </div>
        </div>
    );
}

export default function AdminApplicationsIndex({ applications = defaultApplications, total = 4 }: Props) {
    const [searchQuery, setSearchQuery] = React.useState('');
    const [statusFilter, setStatusFilter] = React.useState<'all' | 'running' | 'stopped' | 'deploying' | 'error'>('all');

    const filteredApplications = applications.filter((app) => {
        const matchesSearch =
            app.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            (app.fqdn && app.fqdn.toLowerCase().includes(searchQuery.toLowerCase())) ||
            app.user.toLowerCase().includes(searchQuery.toLowerCase()) ||
            app.team.toLowerCase().includes(searchQuery.toLowerCase()) ||
            (app.git_repository && app.git_repository.toLowerCase().includes(searchQuery.toLowerCase()));
        const matchesStatus = statusFilter === 'all' || app.status === statusFilter;
        return matchesSearch && matchesStatus;
    });

    const runningCount = applications.filter((a) => a.status === 'running').length;
    const errorCount = applications.filter((a) => a.status === 'error').length;
    const deployingCount = applications.filter((a) => a.status === 'deploying').length;

    return (
        <AdminLayout
            title="Applications"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Applications' },
            ]}
        >
            <div className="mx-auto max-w-7xl px-6 py-8">
                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-2xl font-semibold text-foreground">Application Management</h1>
                    <p className="mt-1 text-sm text-foreground-muted">
                        Monitor all applications across your Saturn Platform instance
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
                                <Activity className="h-8 w-8 text-success/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Deploying</p>
                                    <p className="text-2xl font-bold text-warning">{deployingCount}</p>
                                </div>
                                <Clock className="h-8 w-8 text-warning/50" />
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
                                    placeholder="Search applications by name, domain, user, or repository..."
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
                                    variant={statusFilter === 'deploying' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => setStatusFilter('deploying')}
                                >
                                    Deploying
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

                {/* Applications List */}
                <Card variant="glass">
                    <CardContent className="p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <p className="text-sm text-foreground-muted">
                                Showing {filteredApplications.length} of {total} applications
                            </p>
                        </div>

                        {filteredApplications.length === 0 ? (
                            <div className="py-12 text-center">
                                <Package className="mx-auto h-12 w-12 text-foreground-muted" />
                                <p className="mt-4 text-sm text-foreground-muted">No applications found</p>
                                <p className="text-xs text-foreground-subtle">
                                    Try adjusting your search or filters
                                </p>
                            </div>
                        ) : (
                            <div>
                                {filteredApplications.map((app) => (
                                    <ApplicationRow key={app.id} app={app} />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
