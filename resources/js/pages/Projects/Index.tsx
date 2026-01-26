import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Badge, Button, useConfirm, useToast } from '@/components/ui';
import { Dropdown, DropdownTrigger, DropdownContent, DropdownItem, DropdownDivider } from '@/components/ui/Dropdown';
import { Plus, FolderKanban, MoreVertical, Settings, Trash2 } from 'lucide-react';
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
    const confirm = useConfirm();
    const { toast } = useToast();

    const serviceCount = project.environments?.reduce(
        (acc, env) => acc + (env.applications?.length || 0) + (env.databases?.length || 0),
        0
    ) || 0;

    const handleDelete = async (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();

        // Check if project has resources
        if (serviceCount > 0) {
            toast({
                title: 'Cannot delete project',
                description: `This project has ${serviceCount} resource(s). Delete all applications and databases first.`,
                variant: 'error',
            });
            return;
        }

        const confirmed = await confirm({
            title: 'Delete Project',
            description: `Are you sure you want to delete "${project.name}"? This action cannot be undone.`,
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            router.delete(`/projects/${project.uuid}`, {
                preserveScroll: true,
                preserveState: false, // Force refresh data after delete
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

    return (
        <Link
            href={`/projects/${project.uuid}`}
            className="group relative flex flex-col rounded-xl border border-border/50 bg-gradient-to-br from-background-secondary to-background-secondary/50 p-5 transition-all duration-300 hover:-translate-y-1 hover:border-border hover:shadow-xl hover:shadow-black/20"
        >
            {/* Subtle gradient overlay on hover */}
            <div className="absolute inset-0 rounded-xl bg-gradient-to-br from-white/[0.02] to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100" />

            <div className="relative flex items-start justify-between">
                <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 transition-colors group-hover:bg-primary/20">
                        <FolderKanban className="h-5 w-5 text-primary" />
                    </div>
                    <div>
                        <h3 className="font-medium text-foreground transition-colors group-hover:text-white">{project.name}</h3>
                        <p className="text-sm text-foreground-muted">
                            {serviceCount} service{serviceCount !== 1 ? 's' : ''}
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

            {/* Environments */}
            <div className="relative mt-4 flex flex-wrap gap-2">
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
            <p className="relative mt-4 text-xs text-foreground-subtle">
                Updated {new Date(project.updated_at).toLocaleDateString()}
            </p>
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
