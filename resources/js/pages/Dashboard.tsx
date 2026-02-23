import { useState, useEffect } from 'react';
import { AppLayout } from '@/components/layout';
import { Link, router } from '@inertiajs/react';
import { Plus, MoreHorizontal, Settings, Trash2, FolderOpen, FolderKanban, Clock } from 'lucide-react';
import { Dropdown, DropdownTrigger, DropdownContent, DropdownItem, DropdownDivider } from '@/components/ui/Dropdown';
import { Badge, useConfirm, Button } from '@/components/ui';
import { FadeIn, StaggerList, StaggerItem } from '@/components/animation';
import { useRealtimeStatus } from '@/hooks/useRealtimeStatus';
import type { Project as BaseProject } from '@/types';

interface Project {
    id: number;
    uuid?: string;
    name: string;
    lastActivity?: string;
    servicesCount?: number;
    status?: 'active' | 'inactive' | 'deploying';
    updated_at?: string;
    environments?: { name?: string; applications?: unknown[]; databases?: unknown[]; services?: unknown[] }[];
}

interface Props {
    projects?: Project[] | BaseProject[];
}

// Helper to format time ago
const formatTimeAgo = (date?: string): string => {
    if (!date) return 'Never';
    const now = new Date();
    const then = new Date(date);
    const diff = now.getTime() - then.getTime();
    const hours = Math.floor(diff / (1000 * 60 * 60));
    if (hours < 1) return 'Just now';
    if (hours < 24) return `${hours} hour${hours > 1 ? 's' : ''} ago`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `${days} day${days > 1 ? 's' : ''} ago`;
    const weeks = Math.floor(days / 7);
    return `${weeks} week${weeks > 1 ? 's' : ''} ago`;
};

// Helper to count resources by type
const countResources = (project: Project): { apps: number; dbs: number; services: number; total: number } => {
    if (!project.environments) {
        const total = typeof project.servicesCount === 'number' ? project.servicesCount : 0;
        return { apps: 0, dbs: 0, services: 0, total };
    }
    const counts = project.environments.reduce(
        (acc, env) => ({
            apps: acc.apps + (env.applications?.length || 0),
            dbs: acc.dbs + (env.databases?.length || 0),
            services: acc.services + (env.services?.length || 0),
        }),
        { apps: 0, dbs: 0, services: 0 }
    );
    return { ...counts, total: counts.apps + counts.dbs + counts.services };
};

const ENV_BADGE_VARIANTS: Record<string, 'info' | 'warning' | 'success' | 'default'> = {
    development: 'info',
    dev: 'info',
    staging: 'warning',
    uat: 'warning',
    production: 'success',
    prod: 'success',
};

const getEnvBadgeVariant = (name: string): 'info' | 'warning' | 'success' | 'default' => {
    return ENV_BADGE_VARIANTS[name.toLowerCase()] || 'default';
};

function ProjectCard({ project }: { project: Project }) {
    const confirm = useConfirm();

    const handleDelete = async (e: React.MouseEvent) => {
        e.preventDefault();
        const confirmed = await confirm({
            title: 'Delete Project',
            description: `Are you sure you want to delete "${project.name}"? This action cannot be undone.`,
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            router.delete(`/projects/${project.uuid || project.id}`);
        }
    };

    const statusConfig = {
        active: {
            dot: 'bg-success shadow-success/50 shadow-sm',
            glow: 'group-hover:shadow-success/10',
        },
        inactive: {
            dot: 'bg-foreground-subtle',
            glow: '',
        },
        deploying: {
            dot: 'bg-warning shadow-warning/50 shadow-sm animate-pulse',
            glow: 'group-hover:shadow-warning/10',
        },
    };

    const status = project.status || 'active';
    const config = statusConfig[status];
    const { apps, dbs, services: svcs, total } = countResources(project);
    const lastActivity = project.lastActivity || formatTimeAgo(project.updated_at);
    const projectUrl = project.uuid ? `/projects/${project.uuid}` : `/projects/${project.id}`;

    const resourceSegments = [
        { count: apps, label: 'app', dotColor: 'bg-info' },
        { count: dbs, label: 'db', dotColor: 'bg-warning' },
        { count: svcs, label: 'svc', dotColor: 'bg-primary' },
    ].filter(s => s.count > 0);

    return (
        <Link
            href={projectUrl}
            className={`group relative flex flex-col rounded-xl border border-border/50 bg-gradient-to-br from-background-secondary to-background-secondary/50 p-5 transition-all duration-300 hover:-translate-y-1 hover:border-primary/50 hover:shadow-xl hover:shadow-primary/20 ${config.glow}`}
        >
            {/* Subtle gradient overlay on hover */}
            <div className="absolute inset-0 rounded-xl bg-gradient-to-br from-primary/[0.03] to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100" />

            {/* Header: icon + status + name + menu */}
            <div className="relative flex items-start justify-between">
                <div className="flex items-center gap-3">
                    <div className="relative flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 transition-colors group-hover:bg-primary/20">
                        <FolderKanban className="h-5 w-5 text-primary" />
                        <span className={`absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full ring-2 ring-background-secondary ${config.dot}`} />
                    </div>
                    <div>
                        <h3 className="font-medium text-foreground transition-colors group-hover:text-primary">{project.name}</h3>
                        <p className="text-sm text-foreground-muted">
                            {total} resource{total !== 1 ? 's' : ''}
                        </p>
                    </div>
                </div>
                <Dropdown>
                    <DropdownTrigger>
                        <button
                            onClick={(e) => e.preventDefault()}
                            className="rounded-md p-1.5 opacity-0 transition-all duration-200 hover:bg-primary/10 group-hover:opacity-100"
                        >
                            <MoreHorizontal className="h-4 w-4 text-foreground-muted" />
                        </button>
                    </DropdownTrigger>
                    <DropdownContent align="right">
                        <DropdownItem
                            icon={<Settings className="h-4 w-4" />}
                            onClick={(e) => {
                                e.preventDefault();
                                router.visit(`/projects/${project.uuid || project.id}/settings`);
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
            {project.environments && project.environments.length > 0 && (
                <div className="relative mt-3 flex flex-wrap gap-1.5">
                    {project.environments.slice(0, 4).map((env, i) => (
                        <Badge key={i} variant={getEnvBadgeVariant(env.name || '')} size="sm">
                            {env.name || 'unknown'}
                        </Badge>
                    ))}
                    {project.environments.length > 4 && (
                        <Badge variant="default" size="sm">
                            +{project.environments.length - 4}
                        </Badge>
                    )}
                </div>
            )}

            {/* Last updated */}
            <div className="relative mt-3 flex items-center gap-1.5 border-t border-border/30 pt-3">
                <Clock className="h-3 w-3 text-foreground-subtle" />
                <span className="text-xs text-foreground-subtle">{lastActivity}</span>
            </div>
        </Link>
    );
}

function NewProjectCard() {
    return (
        <Link
            href="/projects/create"
            className="group relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-border/50 bg-gradient-to-br from-background-secondary/30 to-transparent p-5 transition-all duration-300 hover:-translate-y-1 hover:border-primary/50 hover:bg-primary/5 hover:shadow-xl hover:shadow-primary/10"
            style={{ minHeight: '118px' }}
        >
            {/* Animated gradient background */}
            <div className="absolute inset-0 rounded-xl bg-gradient-to-br from-primary/5 via-transparent to-purple-500/5 opacity-0 transition-opacity duration-500 group-hover:opacity-100" />

            <div className="relative flex h-12 w-12 items-center justify-center rounded-full border border-border/50 bg-background-tertiary/50 transition-all duration-300 group-hover:scale-110 group-hover:border-primary/30 group-hover:bg-primary/10">
                <Plus className="h-5 w-5 text-foreground-muted transition-colors group-hover:text-primary" />
            </div>
            <span className="relative mt-3 text-sm font-medium text-foreground-muted transition-colors group-hover:text-foreground">
                New Project
            </span>
        </Link>
    );
}

export default function Dashboard({ projects = [] }: Props) {
    // Track application statuses in state
    const [applicationStatuses, setApplicationStatuses] = useState<Record<number, 'active' | 'inactive' | 'deploying'>>({});

    // Initialize statuses from props
    useEffect(() => {
        const initialStatuses: Record<number, 'active' | 'inactive' | 'deploying'> = {};
        projects.forEach(project => {
            const p = project as Project;
            if (p.id) {
                initialStatuses[p.id] = p.status || 'active';
            }
        });
        setApplicationStatuses(initialStatuses);
    }, [projects]);

    // Real-time status updates
    const { isConnected: _isConnected } = useRealtimeStatus({
        onApplicationStatusChange: (data) => {
            // Update application status when WebSocket event arrives
            setApplicationStatuses(prev => ({
                ...prev,
                [data.applicationId]: data.status as 'active' | 'inactive' | 'deploying',
            }));
        },
    });

    // Get current status for a project
    const getProjectStatus = (project: Project): 'active' | 'inactive' | 'deploying' => {
        return applicationStatuses[project.id] || project.status || 'active';
    };

    const activeCount = projects.filter(p => {
        const project = p as Project;
        const status = getProjectStatus(project);
        return status === 'active' || !status;
    }).length;

    return (
        <AppLayout title="Dashboard">
            <div className="mx-auto max-w-6xl">
                {/* Workspace Header */}
                <FadeIn>
                    <div className="mb-8 flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-semibold text-foreground">My Workspace</h1>
                            <p className="mt-1 text-sm text-foreground-muted">
                                {projects.length} projects · {activeCount} active
                            </p>
                        </div>
                        <Link href="/projects/create">
                            <Button>
                                <Plus className="mr-2 h-4 w-4" />
                                New Project
                            </Button>
                        </Link>
                    </div>
                </FadeIn>

                {/* Projects Grid */}
                {projects.length === 0 ? (
                    <FadeIn direction="up" delay={0.1}>
                        <div className="flex flex-col items-center justify-center rounded-xl border border-border/50 bg-background-secondary/30 py-16">
                            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary/50">
                                <FolderOpen className="h-8 w-8 text-foreground-muted" />
                            </div>
                            <h3 className="mt-4 text-lg font-medium text-foreground">No projects yet</h3>
                            <p className="mt-1 text-sm text-foreground-muted">Create your first project to get started</p>
                            <Link
                                href="/projects/create"
                                className="mt-6 flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-white shadow-lg shadow-primary/20 transition-all duration-200 hover:-translate-y-0.5 hover:bg-primary/90"
                            >
                                <Plus className="h-4 w-4" />
                                Create Project
                            </Link>
                        </div>
                    </FadeIn>
                ) : (
                    <StaggerList className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {projects.map((project, index) => {
                            const proj = project as Project;
                            return (
                                <StaggerItem key={proj.id} index={index}>
                                    <ProjectCard
                                        project={{ ...proj, status: getProjectStatus(proj) }}
                                    />
                                </StaggerItem>
                            );
                        })}
                        <StaggerItem index={projects.length}>
                            <NewProjectCard />
                        </StaggerItem>
                    </StaggerList>
                )}
            </div>
        </AppLayout>
    );
}
