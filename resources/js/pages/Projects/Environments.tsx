import { useState } from 'react';
import { router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, CardHeader, CardTitle, Button, Badge, Input, useConfirm } from '@/components/ui';
import {
    Plus, GitBranch, Box, Database, Layers,
    Trash2, Calendar,
} from 'lucide-react';

interface EnvironmentData {
    id: number;
    uuid: string;
    name: string;
    created_at: string;
    updated_at: string;
    applications_count: number;
    services_count: number;
    databases_count: number;
}

interface Props {
    project: {
        id: number;
        uuid: string;
        name: string;
    };
    environments?: EnvironmentData[];
}

export default function ProjectEnvironments({ project, environments: propEnvironments }: Props) {
    const environments = propEnvironments ?? [];
    const confirm = useConfirm();
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);

    const totalResources = environments.reduce(
        (acc, env) => acc + env.applications_count + env.services_count + env.databases_count,
        0
    );

    const handleDelete = async (env: EnvironmentData) => {
        const resourceCount = env.applications_count + env.services_count + env.databases_count;
        if (resourceCount > 0) {
            await confirm({
                title: 'Cannot Delete',
                description: `Environment "${env.name}" has ${resourceCount} resource(s). Remove all applications, services, and databases first.`,
                confirmText: 'OK',
                variant: 'warning',
            });
            return;
        }

        const confirmed = await confirm({
            title: 'Delete Environment',
            description: `Are you sure you want to delete "${env.name}"? This action cannot be undone.`,
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            fetch(`/api/v1/projects/${project.uuid}/environments/${env.uuid}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
                },
            }).then(() => router.reload());
        }
    };

    return (
        <AppLayout
            title={`${project.name} - Environments`}
            breadcrumbs={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Projects', href: '/projects' },
                { label: project.name, href: `/projects/${project.uuid}` },
                { label: 'Environments' },
            ]}
        >
            <div className="mx-auto max-w-5xl px-6 py-8">
                {/* Header */}
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Environments</h1>
                        <p className="mt-1 text-foreground-muted">
                            Manage environments for {project.name}
                        </p>
                    </div>
                    <Button onClick={() => setIsCreateModalOpen(true)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Create Environment
                    </Button>
                </div>

                {/* Stats */}
                <div className="mb-6 grid gap-4 sm:grid-cols-3">
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-muted">Environments</p>
                                    <p className="text-2xl font-bold text-foreground">{environments.length}</p>
                                </div>
                                <GitBranch className="h-8 w-8 text-primary/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-muted">Total Resources</p>
                                    <p className="text-2xl font-bold text-foreground">{totalResources}</p>
                                </div>
                                <Layers className="h-8 w-8 text-foreground-muted/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-muted">Applications</p>
                                    <p className="text-2xl font-bold text-foreground">
                                        {environments.reduce((acc, e) => acc + e.applications_count, 0)}
                                    </p>
                                </div>
                                <Box className="h-8 w-8 text-foreground-muted/50" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Environments List */}
                {environments.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-16">
                            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary">
                                <GitBranch className="h-8 w-8 text-foreground-muted" />
                            </div>
                            <h3 className="mt-4 text-lg font-medium text-foreground">No environments</h3>
                            <p className="mt-2 text-center text-sm text-foreground-muted">
                                Create your first environment to start deploying resources.
                            </p>
                            <Button className="mt-4" onClick={() => setIsCreateModalOpen(true)}>
                                <Plus className="mr-2 h-4 w-4" />
                                Create Environment
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-3">
                        {environments.map((env) => (
                            <EnvironmentCard
                                key={env.id}
                                environment={env}
                                projectUuid={project.uuid}
                                onDelete={() => handleDelete(env)}
                            />
                        ))}
                    </div>
                )}

                {/* Create Environment Modal */}
                {isCreateModalOpen && (
                    <CreateEnvironmentModal
                        projectUuid={project.uuid}
                        onClose={() => setIsCreateModalOpen(false)}
                        existingNames={environments.map((e) => e.name.toLowerCase())}
                    />
                )}
            </div>
        </AppLayout>
    );
}

interface EnvironmentCardProps {
    environment: EnvironmentData;
    projectUuid: string;
    onDelete: () => void;
}

function EnvironmentCard({ environment, projectUuid, onDelete }: EnvironmentCardProps) {
    const resourceCount = environment.applications_count + environment.services_count + environment.databases_count;

    return (
        <Card className="transition-all hover:border-primary/50 hover:shadow-md">
            <CardContent className="p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                            <GitBranch className="h-5 w-5 text-primary" />
                        </div>
                        <div>
                            <h3 className="font-medium text-foreground">{environment.name}</h3>
                            <div className="mt-1 flex items-center gap-3 text-xs text-foreground-muted">
                                <span className="flex items-center gap-1">
                                    <Calendar className="h-3 w-3" />
                                    {new Date(environment.created_at).toLocaleDateString()}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        {/* Resource counts */}
                        <div className="flex items-center gap-2">
                            {environment.applications_count > 0 && (
                                <Badge variant="default" size="sm">
                                    <Box className="mr-1 h-3 w-3" />
                                    {environment.applications_count} app{environment.applications_count !== 1 ? 's' : ''}
                                </Badge>
                            )}
                            {environment.services_count > 0 && (
                                <Badge variant="default" size="sm">
                                    <Layers className="mr-1 h-3 w-3" />
                                    {environment.services_count} service{environment.services_count !== 1 ? 's' : ''}
                                </Badge>
                            )}
                            {environment.databases_count > 0 && (
                                <Badge variant="default" size="sm">
                                    <Database className="mr-1 h-3 w-3" />
                                    {environment.databases_count} db{environment.databases_count !== 1 ? 's' : ''}
                                </Badge>
                            )}
                            {resourceCount === 0 && (
                                <span className="text-xs text-foreground-subtle">No resources</span>
                            )}
                        </div>

                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={onDelete}
                            className="text-foreground-muted hover:text-danger"
                        >
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

interface CreateEnvironmentModalProps {
    projectUuid: string;
    onClose: () => void;
    existingNames: string[];
}

function CreateEnvironmentModal({ projectUuid, onClose, existingNames }: CreateEnvironmentModalProps) {
    const [name, setName] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [error, setError] = useState('');

    const isDuplicate = existingNames.includes(name.toLowerCase());

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!name || isDuplicate || isSubmitting) return;

        setIsSubmitting(true);
        setError('');

        router.post(
            `/projects/${projectUuid}/environments`,
            { name },
            {
                onSuccess: () => onClose(),
                onError: (errors) => {
                    setError(Object.values(errors).flat().join(', ') || 'Failed to create environment');
                    setIsSubmitting(false);
                },
                onFinish: () => setIsSubmitting(false),
            }
        );
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div className="w-full max-w-md rounded-lg border border-border bg-background p-6 shadow-xl">
                <h2 className="text-xl font-semibold text-foreground">Create Environment</h2>
                <form onSubmit={handleSubmit} className="mt-4 space-y-4">
                    <div>
                        <label className="mb-2 block text-sm font-medium text-foreground">
                            Environment Name
                        </label>
                        <Input
                            type="text"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            placeholder="e.g. production, staging, development"
                            required
                            autoFocus
                        />
                        {isDuplicate && (
                            <p className="mt-1 text-xs text-danger">
                                An environment with this name already exists.
                            </p>
                        )}
                        {error && (
                            <p className="mt-1 text-xs text-danger">{error}</p>
                        )}
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="secondary" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={!name || isDuplicate || isSubmitting}>
                            {isSubmitting ? 'Creating...' : 'Create Environment'}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}
