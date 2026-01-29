import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { useConfirm } from '@/components/ui';
import {
    Dropdown,
    DropdownTrigger,
    DropdownContent,
    DropdownItem,
    DropdownDivider,
} from '@/components/ui/Dropdown';
import {
    FolderKanban,
    Layers,
    Calendar,
    Users,
    MoreHorizontal,
    Eye,
    Trash2,
    Database,
    Box,
    Globe,
    Play,
    Pause,
    ExternalLink,
} from 'lucide-react';

interface Environment {
    id: number;
    uuid: string;
    name: string;
    applications: {
        id: number;
        uuid: string;
        name: string;
        fqdn?: string;
        status: string;
    }[];
    services: {
        id: number;
        uuid: string;
        name: string;
        status: string;
    }[];
    databases: {
        id: number;
        uuid: string;
        name: string;
        type: string;
        status: string;
    }[];
}

interface ProjectDetails {
    id: number;
    uuid: string;
    name: string;
    description?: string;
    team_id: number;
    team_name: string;
    created_at: string;
    updated_at: string;
    environments: Environment[];
}

interface Props {
    project: ProjectDetails;
}

function ApplicationRow({ app, projectUuid, envUuid }: { app: Environment['applications'][0]; projectUuid: string; envUuid: string }) {
    const confirm = useConfirm();

    const statusConfig: Record<string, { variant: 'success' | 'danger' | 'warning' | 'default'; label: string }> = {
        running: { variant: 'success', label: 'Running' },
        stopped: { variant: 'danger', label: 'Stopped' },
        starting: { variant: 'warning', label: 'Starting' },
        restarting: { variant: 'warning', label: 'Restarting' },
    };

    const config = statusConfig[app.status] || { variant: 'default', label: app.status };

    const handleDelete = async () => {
        const confirmed = await confirm({
            title: 'Delete Application',
            description: `Are you sure you want to delete "${app.name}"? This action cannot be undone.`,
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            router.delete(`/admin/applications/${app.id}`);
        }
    };

    return (
        <div className="flex items-center justify-between border-b border-border/50 py-3 last:border-0">
            <div className="flex items-center gap-3">
                <Box className="h-5 w-5 text-blue-500" />
                <div>
                    <p className="font-medium text-foreground">{app.name}</p>
                    {app.fqdn && (
                        <p className="text-sm text-foreground-muted">{app.fqdn}</p>
                    )}
                </div>
            </div>
            <div className="flex items-center gap-2">
                <Badge variant={config.variant} size="sm">
                    {config.label}
                </Badge>
                <Dropdown>
                    <DropdownTrigger>
                        <Button variant="ghost" size="sm">
                            <MoreHorizontal className="h-4 w-4" />
                        </Button>
                    </DropdownTrigger>
                    <DropdownContent align="right">
                        <DropdownItem onClick={() => router.visit(`/project/${projectUuid}/${envUuid}/application/${app.uuid}`)}>
                            <Eye className="h-4 w-4" />
                            View Application
                        </DropdownItem>
                        {app.fqdn && (
                            <DropdownItem onClick={() => window.open(app.fqdn, '_blank')}>
                                <Globe className="h-4 w-4" />
                                Open URL
                            </DropdownItem>
                        )}
                        <DropdownDivider />
                        <DropdownItem onClick={handleDelete} className="text-danger">
                            <Trash2 className="h-4 w-4" />
                            Delete
                        </DropdownItem>
                    </DropdownContent>
                </Dropdown>
            </div>
        </div>
    );
}

function ServiceRow({ service, projectUuid, envUuid }: { service: Environment['services'][0]; projectUuid: string; envUuid: string }) {
    const confirm = useConfirm();

    const handleDelete = async () => {
        const confirmed = await confirm({
            title: 'Delete Service',
            description: `Are you sure you want to delete "${service.name}"? This action cannot be undone.`,
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            router.delete(`/admin/services/${service.id}`);
        }
    };

    return (
        <div className="flex items-center justify-between border-b border-border/50 py-3 last:border-0">
            <div className="flex items-center gap-3">
                <Layers className="h-5 w-5 text-green-500" />
                <div>
                    <p className="font-medium text-foreground">{service.name}</p>
                </div>
            </div>
            <div className="flex items-center gap-2">
                <Badge variant="success" size="sm">
                    Service
                </Badge>
                <Dropdown>
                    <DropdownTrigger>
                        <Button variant="ghost" size="sm">
                            <MoreHorizontal className="h-4 w-4" />
                        </Button>
                    </DropdownTrigger>
                    <DropdownContent align="right">
                        <DropdownItem onClick={() => router.visit(`/project/${projectUuid}/${envUuid}/service/${service.uuid}`)}>
                            <Eye className="h-4 w-4" />
                            View Service
                        </DropdownItem>
                        <DropdownDivider />
                        <DropdownItem onClick={handleDelete} className="text-danger">
                            <Trash2 className="h-4 w-4" />
                            Delete
                        </DropdownItem>
                    </DropdownContent>
                </Dropdown>
            </div>
        </div>
    );
}

function DatabaseRow({ database, projectUuid, envUuid }: { database: Environment['databases'][0]; projectUuid: string; envUuid: string }) {
    const confirm = useConfirm();

    const handleDelete = async () => {
        const confirmed = await confirm({
            title: 'Delete Database',
            description: `Are you sure you want to delete "${database.name}"? This action cannot be undone and all data will be lost.`,
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            router.delete(`/admin/databases/${database.id}`);
        }
    };

    return (
        <div className="flex items-center justify-between border-b border-border/50 py-3 last:border-0">
            <div className="flex items-center gap-3">
                <Database className="h-5 w-5 text-amber-500" />
                <div>
                    <p className="font-medium text-foreground">{database.name}</p>
                    <p className="text-sm text-foreground-muted">{database.type}</p>
                </div>
            </div>
            <div className="flex items-center gap-2">
                <Badge variant="warning" size="sm">
                    {database.type}
                </Badge>
                <Dropdown>
                    <DropdownTrigger>
                        <Button variant="ghost" size="sm">
                            <MoreHorizontal className="h-4 w-4" />
                        </Button>
                    </DropdownTrigger>
                    <DropdownContent align="right">
                        <DropdownItem onClick={() => router.visit(`/project/${projectUuid}/${envUuid}/database/${database.uuid}`)}>
                            <Eye className="h-4 w-4" />
                            View Database
                        </DropdownItem>
                        <DropdownDivider />
                        <DropdownItem onClick={handleDelete} className="text-danger">
                            <Trash2 className="h-4 w-4" />
                            Delete
                        </DropdownItem>
                    </DropdownContent>
                </Dropdown>
            </div>
        </div>
    );
}

export default function AdminProjectShow({ project }: Props) {
    const confirm = useConfirm();
    const environments = project?.environments ?? [];

    const totalApps = environments.reduce((sum, env) => sum + env.applications.length, 0);
    const totalServices = environments.reduce((sum, env) => sum + env.services.length, 0);
    const totalDatabases = environments.reduce((sum, env) => sum + env.databases.length, 0);

    const handleDeleteProject = async () => {
        const confirmed = await confirm({
            title: 'Delete Project',
            description: `Are you sure you want to delete "${project.name}"? This will delete all environments, applications, services, and databases. This action cannot be undone.`,
            confirmText: 'Delete Project',
            variant: 'danger',
        });
        if (confirmed) {
            router.delete(`/admin/projects/${project.id}`);
        }
    };

    return (
        <AdminLayout
            title={project.name}
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Projects', href: '/admin/projects' },
                { label: project.name },
            ]}
        >
            <div className="mx-auto max-w-7xl">
                {/* Header */}
                <div className="mb-8">
                    <div className="flex items-start justify-between">
                        <div className="flex items-center gap-4">
                            <div className="flex h-16 w-16 items-center justify-center rounded-lg bg-gradient-to-br from-amber-500 to-orange-500 text-2xl font-medium text-white">
                                <FolderKanban className="h-8 w-8" />
                            </div>
                            <div>
                                <h1 className="text-2xl font-semibold text-foreground">{project.name}</h1>
                                {project.description && (
                                    <p className="mt-1 text-sm text-foreground-muted">{project.description}</p>
                                )}
                                <div className="mt-2 flex items-center gap-2">
                                    <Link
                                        href={`/admin/teams/${project.team_id}`}
                                        className="flex items-center gap-1 text-sm text-foreground-muted hover:text-primary"
                                    >
                                        <Users className="h-4 w-4" />
                                        {project.team_name}
                                    </Link>
                                </div>
                            </div>
                        </div>
                        <div className="flex gap-2">
                            <Link href={`/project/${project.uuid}`}>
                                <Button variant="secondary">
                                    <ExternalLink className="h-4 w-4" />
                                    Open Project
                                </Button>
                            </Link>
                            <Button variant="danger" onClick={handleDeleteProject}>
                                <Trash2 className="h-4 w-4" />
                                Delete Project
                            </Button>
                        </div>
                    </div>
                </div>

                {/* Stats */}
                <div className="mb-6 grid gap-4 sm:grid-cols-4">
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Environments</p>
                                    <p className="text-2xl font-bold text-primary">{environments.length}</p>
                                </div>
                                <Layers className="h-8 w-8 text-primary/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Applications</p>
                                    <p className="text-2xl font-bold text-blue-500">{totalApps}</p>
                                </div>
                                <Box className="h-8 w-8 text-blue-500/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Services</p>
                                    <p className="text-2xl font-bold text-success">{totalServices}</p>
                                </div>
                                <Layers className="h-8 w-8 text-success/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Databases</p>
                                    <p className="text-2xl font-bold text-warning">{totalDatabases}</p>
                                </div>
                                <Database className="h-8 w-8 text-warning/50" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Environments */}
                {environments.length === 0 ? (
                    <Card variant="glass">
                        <CardContent className="p-6">
                            <div className="py-12 text-center">
                                <Layers className="mx-auto h-12 w-12 text-foreground-muted" />
                                <p className="mt-4 text-sm text-foreground-muted">No environments</p>
                                <p className="text-xs text-foreground-subtle">
                                    This project has no environments yet
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    environments.map((env) => (
                        <Card key={env.id} variant="glass" className="mb-6">
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <div>
                                        <CardTitle>{env.name}</CardTitle>
                                        <CardDescription>
                                            {env.applications.length} apps, {env.services.length} services, {env.databases.length} databases
                                        </CardDescription>
                                    </div>
                                    <Link href={`/project/${project.uuid}/${env.uuid}`}>
                                        <Button variant="secondary" size="sm">
                                            <ExternalLink className="h-4 w-4" />
                                            Open Environment
                                        </Button>
                                    </Link>
                                </div>
                            </CardHeader>
                            <CardContent>
                                {/* Applications */}
                                {env.applications.length > 0 && (
                                    <div className="mb-4">
                                        <h4 className="mb-2 text-sm font-medium text-foreground-muted">Applications</h4>
                                        {env.applications.map((app) => (
                                            <ApplicationRow
                                                key={app.id}
                                                app={app}
                                                projectUuid={project.uuid}
                                                envUuid={env.uuid}
                                            />
                                        ))}
                                    </div>
                                )}

                                {/* Services */}
                                {env.services.length > 0 && (
                                    <div className="mb-4">
                                        <h4 className="mb-2 text-sm font-medium text-foreground-muted">Services</h4>
                                        {env.services.map((service) => (
                                            <ServiceRow
                                                key={service.id}
                                                service={service}
                                                projectUuid={project.uuid}
                                                envUuid={env.uuid}
                                            />
                                        ))}
                                    </div>
                                )}

                                {/* Databases */}
                                {env.databases.length > 0 && (
                                    <div>
                                        <h4 className="mb-2 text-sm font-medium text-foreground-muted">Databases</h4>
                                        {env.databases.map((database) => (
                                            <DatabaseRow
                                                key={database.id}
                                                database={database}
                                                projectUuid={project.uuid}
                                                envUuid={env.uuid}
                                            />
                                        ))}
                                    </div>
                                )}

                                {env.applications.length === 0 && env.services.length === 0 && env.databases.length === 0 && (
                                    <p className="py-4 text-center text-sm text-foreground-muted">
                                        No resources in this environment
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    ))
                )}
            </div>
        </AdminLayout>
    );
}
