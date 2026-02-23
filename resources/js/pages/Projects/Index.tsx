import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Badge, Button, useConfirm, useToast } from '@/components/ui';
import { Dropdown, DropdownTrigger, DropdownContent, DropdownItem, DropdownDivider } from '@/components/ui/Dropdown';
import { Plus, FolderKanban, MoreVertical, Settings, Trash2, Box, Database, Layers, Clock } from 'lucide-react';
import type { Project } from '@/types';

interface Props {
    projects: Project[];
}

export default function ProjectsIndex({ projects = [] }: Props) {
    return (
        <AppLayout
            title="Projects"
            breadcrumbs={[{ label: 'Projects' }]}
        >
            <div className="mx-auto max-w-6xl">
            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-foreground">Projects</h1>
                    <p className="text-foreground-muted">Manage your applications and services</p>
                </div>
                <Link href="/projects/create">
                    <Button>
                        <Plus className="mr-2 h-4 w-4" />
                        New Project
                    </Button>
                </Link>
            </div>

            {/* Projects Grid */}
            {projects.length === 0 ? (
                <EmptyState />
            ) : (
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {projects.map((project) => (
                        <ProjectCard key={project.id} project={project} />
                    ))}
                </div>
            )}
            </div>
        </AppLayout>
    );
}

const ENV_BADGE_VARIANTS: Record<string, 'info' | 'warning' | 'success' | 'primary' | 'default'> = {
    development: 'info',
    dev: 'info',
    staging: 'warning',
    uat: 'warning',
    production: 'success',
    prod: 'success',
};

function getEnvBadgeVariant(name: string): 'info' | 'warning' | 'success' | 'primary' | 'default' {
    return ENV_BADGE_VARIANTS[name.toLowerCase()] || 'default';
}

function timeAgo(dateStr: string): string {
    const now = new Date();
    const date = new Date(dateStr);
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffMins < 1) return 'just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays < 30) return `${diffDays}d ago`;
    return new Date(dateStr).toLocaleDateString();
}

function ProjectCard({ project }: { project: Project }) {
    const confirm = useConfirm();
    const { toast } = useToast();

    const counts = project.environments?.reduce(
        (acc, env) => ({
            apps: acc.apps + (env.applications?.length || 0),
            dbs: acc.dbs + (env.databases?.length || 0),
            services: acc.services + (env.services?.length || 0),
        }),
        { apps: 0, dbs: 0, services: 0 }
    ) || { apps: 0, dbs: 0, services: 0 };

    const totalResources = counts.apps + counts.dbs + counts.services;

    const handleDelete = async (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();

        const warningMessage = totalResources > 0
            ? `This project has ${totalResources} resource(s) that will be permanently deleted along with all containers and data. This action cannot be undone.`
            : `Are you sure you want to delete "${project.name}"? This action cannot be undone.`;

        const confirmed = await confirm({
            title: 'Delete Project',
            description: warningMessage,
            confirmText: totalResources > 0 ? 'Delete All' : 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            router.delete(`/projects/${project.uuid}`, {
                preserveScroll: true,
                preserveState: false,
                onSuccess: () => {
                    toast({
                        title: 'Project deleted',
                        description: 'The project has been removed successfully.',
                        variant: 'success',
                    });
                },
                onError: () => {
                    toast({
                        title: 'Failed to delete project',
                        description: 'Project may have resources that need to be deleted first.',
                        variant: 'error',
                    });
                },
            });
        }
    };

    const resourceSegments = [
        { count: counts.apps, label: 'app', icon: Box, color: 'text-info', dotColor: 'bg-info' },
        { count: counts.dbs, label: 'db', icon: Database, color: 'text-warning', dotColor: 'bg-warning' },
        { count: counts.services, label: 'svc', icon: Layers, color: 'text-primary', dotColor: 'bg-primary' },
    ].filter(s => s.count > 0);

    return (
        <Link
            href={`/projects/${project.uuid}`}
            className="group relative flex flex-col rounded-xl border border-border/50 bg-gradient-to-br from-background-secondary to-background-secondary/50 p-5 transition-all duration-300 hover:-translate-y-1 hover:border-border hover:shadow-xl hover:shadow-black/20"
        >
            {/* Subtle gradient overlay on hover */}
            <div className="absolute inset-0 rounded-xl bg-gradient-to-br from-white/[0.02] to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100" />

            {/* Header: icon + name + menu */}
            <div className="relative flex items-start justify-between">
                <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 transition-colors group-hover:bg-primary/20">
                        <FolderKanban className="h-5 w-5 text-primary" />
                    </div>
                    <div>
                        <h3 className="font-medium text-foreground transition-colors group-hover:text-white">{project.name}</h3>
                        <p className="text-sm text-foreground-muted">
                            {totalResources} resource{totalResources !== 1 ? 's' : ''}
                        </p>
                    </div>
                </div>
                <Dropdown>
                    <DropdownTrigger>
                        <button
                            className="rounded-md p-1.5 opacity-0 transition-all duration-200 hover:bg-white/10 group-hover:opacity-100"
                            onClick={(e) => e.preventDefault()}
                        >
                            <MoreVertical className="h-4 w-4 text-foreground-muted" />
                        </button>
                    </DropdownTrigger>
                    <DropdownContent align="right">
                        <DropdownItem
                            icon={<Settings className="h-4 w-4" />}
                            onClick={(e) => {
                                e.preventDefault();
                                router.visit(`/projects/${project.uuid}/settings`);
                            }}
                        >
                            Project Settings
                        </DropdownItem>
                        <DropdownDivider />
                        <DropdownItem
                            icon={<Trash2 className="h-4 w-4" />}
                            onClick={handleDelete}
                            danger
                        >
                            Delete Project
                        </DropdownItem>
                    </DropdownContent>
                </Dropdown>
            </div>

            {/* Resource breakdown */}
            {resourceSegments.length > 0 && (
                <div className="relative mt-3 flex items-center gap-3">
                    {resourceSegments.map((seg) => (
                        <div key={seg.label} className="flex items-center gap-1.5">
                            <span className={`h-1.5 w-1.5 rounded-full ${seg.dotColor}`} />
                            <span className="text-xs text-foreground-muted">
                                {seg.count} {seg.label}{seg.count !== 1 ? 's' : ''}
                            </span>
                        </div>
                    ))}
                </div>
            )}

            {/* Environment badges */}
            <div className="relative mt-3 flex flex-wrap gap-1.5">
                {project.environments?.slice(0, 4).map((env) => (
                    <Badge key={env.id} variant={getEnvBadgeVariant(env.name)} size="sm">
                        {env.name}
                    </Badge>
                ))}
                {(project.environments?.length || 0) > 4 && (
                    <Badge variant="default" size="sm">
                        +{(project.environments?.length || 0) - 4}
                    </Badge>
                )}
            </div>

            {/* Last updated */}
            <div className="relative mt-3 flex items-center gap-1.5 border-t border-border/30 pt-3">
                <Clock className="h-3 w-3 text-foreground-subtle" />
                <span className="text-xs text-foreground-subtle">
                    {timeAgo(project.updated_at)}
                </span>
            </div>
        </Link>
    );
}

function EmptyState() {
    return (
        <div className="flex flex-col items-center justify-center rounded-xl border border-border/50 bg-background-secondary/30 py-16">
            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary/50">
                <FolderKanban className="h-8 w-8 text-foreground-muted" />
            </div>
            <h3 className="mt-4 text-lg font-medium text-foreground">No projects yet</h3>
            <p className="mt-1 text-sm text-foreground-muted">
                Create your first project to start deploying applications.
            </p>
            <Link href="/projects/create" className="mt-6">
                <Button>
                    <Plus className="mr-2 h-4 w-4" />
                    Create Project
                </Button>
            </Link>
        </div>
    );
}
