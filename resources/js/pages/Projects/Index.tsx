import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Badge, Button } from '@/components/ui';
import { Dropdown, DropdownTrigger, DropdownContent, DropdownItem, DropdownDivider } from '@/components/ui/Dropdown';
import { Plus, FolderKanban, MoreVertical, Globe, GitBranch, Settings, Trash2 } from 'lucide-react';
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
            <div className="mx-auto max-w-6xl px-6 py-8">
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

function ProjectCard({ project }: { project: Project }) {
    const serviceCount = project.environments?.reduce(
        (acc, env) => acc + (env.applications?.length || 0) + (env.databases?.length || 0),
        0
    ) || 0;

    return (
        <Link href={`/projects/${project.uuid}`}>
            <Card className="transition-colors hover:border-primary/50">
                <CardContent className="p-4">
                    <div className="flex items-start justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                <FolderKanban className="h-5 w-5 text-primary" />
                            </div>
                            <div>
                                <h3 className="font-medium text-foreground">{project.name}</h3>
                                <p className="text-sm text-foreground-muted">
                                    {serviceCount} service{serviceCount !== 1 ? 's' : ''}
                                </p>
                            </div>
                        </div>
                        <Dropdown>
                            <DropdownTrigger>
                                <button
                                    className="rounded-md p-1 text-foreground-muted hover:bg-background-tertiary hover:text-foreground"
                                    onClick={(e) => e.preventDefault()}
                                >
                                    <MoreVertical className="h-4 w-4" />
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
                                    onClick={(e) => {
                                        e.preventDefault();
                                        if (confirm(`Are you sure you want to delete "${project.name}"? This action cannot be undone.`)) {
                                            router.delete(`/projects/${project.uuid}`);
                                        }
                                    }}
                                    danger
                                >
                                    Delete Project
                                </DropdownItem>
                            </DropdownContent>
                        </Dropdown>
                    </div>

                    {/* Environments */}
                    <div className="mt-4 flex flex-wrap gap-2">
                        {project.environments?.slice(0, 3).map((env) => (
                            <Badge key={env.id} variant="default">
                                {env.name}
                            </Badge>
                        ))}
                        {(project.environments?.length || 0) > 3 && (
                            <Badge variant="default">
                                +{(project.environments?.length || 0) - 3} more
                            </Badge>
                        )}
                    </div>

                    {/* Last updated */}
                    <p className="mt-4 text-xs text-foreground-subtle">
                        Updated {new Date(project.updated_at).toLocaleDateString()}
                    </p>
                </CardContent>
            </Card>
        </Link>
    );
}

function EmptyState() {
    return (
        <Card className="p-12 text-center">
            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary">
                <FolderKanban className="h-8 w-8 text-foreground-muted" />
            </div>
            <h3 className="mt-4 text-lg font-medium text-foreground">No projects yet</h3>
            <p className="mt-2 text-foreground-muted">
                Create your first project to start deploying applications.
            </p>
            <Link href="/projects/create" className="mt-6 inline-block">
                <Button>
                    <Plus className="mr-2 h-4 w-4" />
                    Create Project
                </Button>
            </Link>
        </Card>
    );
}
