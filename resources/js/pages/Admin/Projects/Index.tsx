import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Link } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Input } from '@/components/ui/Input';

import {
    Search,
    FolderKanban,
    Layers,
    Users,
} from 'lucide-react';

interface ProjectInfo {
    id: number;
    uuid: string;
    name: string;
    description?: string;
    team_id: number;
    team_name: string;
    environments_count: number;
    applications_count: number;
    services_count: number;
    databases_count: number;
    created_at: string;
}

interface Props {
    projects: {
        data: ProjectInfo[];
        total: number;
    };
}

function ProjectRow({ project }: { project: ProjectInfo }) {
    const totalResources = project.applications_count + project.services_count + project.databases_count;

    return (
        <div className="border-b border-border/50 py-4 last:border-0">
            <div className="flex items-start justify-between">
                <div className="flex-1">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-amber-500 to-orange-500 text-sm font-medium text-white">
                            <FolderKanban className="h-5 w-5" />
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <Link
                                    href={`/admin/projects/${project.id}`}
                                    className="font-medium text-foreground hover:text-primary"
                                >
                                    {project.name}
                                </Link>
                            </div>
                            {project.description && (
                                <p className="text-sm text-foreground-muted">{project.description}</p>
                            )}
                            <div className="mt-2 flex items-center gap-4 text-xs text-foreground-subtle">
                                <Link
                                    href={`/admin/teams/${project.team_id}`}
                                    className="flex items-center gap-1 hover:text-primary"
                                >
                                    <Users className="h-3 w-3" />
                                    <span>{project.team_name}</span>
                                </Link>
                                <div className="flex items-center gap-1">
                                    <Layers className="h-3 w-3" />
                                    <span>{project.environments_count} environments</span>
                                </div>
                                <span>{totalResources} resources</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="flex flex-col items-end gap-2">
                    <div className="flex gap-1">
                        {project.applications_count > 0 && (
                            <Badge variant="primary" size="sm">
                                {project.applications_count} apps
                            </Badge>
                        )}
                        {project.services_count > 0 && (
                            <Badge variant="success" size="sm">
                                {project.services_count} services
                            </Badge>
                        )}
                        {project.databases_count > 0 && (
                            <Badge variant="warning" size="sm">
                                {project.databases_count} databases
                            </Badge>
                        )}
                    </div>
                    <span className="text-xs text-foreground-subtle">
                        {new Date(project.created_at).toLocaleDateString()}
                    </span>
                </div>
            </div>
        </div>
    );
}

export default function AdminProjectsIndex({ projects: projectsData }: Props) {
    const items = projectsData?.data ?? [];
    const total = projectsData?.total ?? 0;
    const [searchQuery, setSearchQuery] = React.useState('');

    const filteredProjects = items.filter((project) => {
        return project.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            (project.description || '').toLowerCase().includes(searchQuery.toLowerCase()) ||
            project.team_name.toLowerCase().includes(searchQuery.toLowerCase());
    });

    const totalApps = items.reduce((sum, p) => sum + p.applications_count, 0);
    const totalServices = items.reduce((sum, p) => sum + p.services_count, 0);
    const totalDatabases = items.reduce((sum, p) => sum + p.databases_count, 0);

    return (
        <AdminLayout
            title="Projects"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Projects' },
            ]}
        >
            <div className="mx-auto max-w-7xl">
                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-2xl font-semibold text-foreground">Project Management</h1>
                    <p className="mt-1 text-sm text-foreground-muted">
                        View and manage all projects across teams
                    </p>
                </div>

                {/* Stats */}
                <div className="mb-6 grid gap-4 sm:grid-cols-4">
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Total Projects</p>
                                    <p className="text-2xl font-bold text-primary">{total}</p>
                                </div>
                                <FolderKanban className="h-8 w-8 text-primary/50" />
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
                                <Layers className="h-8 w-8 text-blue-500/50" />
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
                                <Layers className="h-8 w-8 text-warning/50" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <Card variant="glass" className="mb-6">
                    <CardContent className="p-4">
                        <div className="relative">
                            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                            <Input
                                placeholder="Search projects by name or team..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="pl-10"
                            />
                        </div>
                    </CardContent>
                </Card>

                {/* Projects List */}
                <Card variant="glass">
                    <CardContent className="p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <p className="text-sm text-foreground-muted">
                                Showing {filteredProjects.length} of {total} projects
                            </p>
                        </div>

                        {filteredProjects.length === 0 ? (
                            <div className="py-12 text-center">
                                <FolderKanban className="mx-auto h-12 w-12 text-foreground-muted" />
                                <p className="mt-4 text-sm text-foreground-muted">No projects found</p>
                                <p className="text-xs text-foreground-subtle">
                                    Try adjusting your search
                                </p>
                            </div>
                        ) : (
                            <div>
                                {filteredProjects.map((project) => (
                                    <ProjectRow key={project.id} project={project} />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
