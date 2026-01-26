import { useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription, Button, Input } from '@/components/ui';
import { ArrowLeft, Save, Trash2, AlertTriangle } from 'lucide-react';

interface ResourcesCount {
    applications: number;
    services: number;
    databases: number;
}

interface Project {
    id: number;
    uuid: string;
    name: string;
    description: string | null;
    created_at: string;
    updated_at: string;
    is_empty: boolean;
    resources_count: ResourcesCount;
    total_resources: number;
}

interface Props {
    project: Project;
}

export default function ProjectSettings({ project }: Props) {
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
    const [deleteConfirmName, setDeleteConfirmName] = useState('');

    const { data, setData, patch, processing, errors } = useForm({
        name: project.name,
        description: project.description || '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        patch(`/projects/${project.uuid}`, {
            preserveScroll: true,
        });
    };

    const handleDelete = () => {
        if (deleteConfirmName !== project.name) {
            return;
        }
        router.delete(`/projects/${project.uuid}`);
    };

    return (
        <AppLayout
            title={`${project.name} - Settings`}
            breadcrumbs={[
                { label: 'Projects', href: '/projects' },
                { label: project.name, href: `/projects/${project.uuid}` },
                { label: 'Settings' },
            ]}
        >
            <div className="mx-auto max-w-2xl px-6 py-8">
                {/* Back link */}
                <Link
                    href={`/projects/${project.uuid}`}
                    className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="mr-2 h-4 w-4" />
                    Back to Project
                </Link>

                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-2xl font-bold text-foreground">Project Settings</h1>
                    <p className="mt-1 text-foreground-muted">
                        Manage your project configuration
                    </p>
                </div>

                {/* General Settings */}
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle>General</CardTitle>
                        <CardDescription>Basic project information</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <label htmlFor="name" className="block text-sm font-medium text-foreground">
                                    Project Name
                                </label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="My Project"
                                    className="mt-1"
                                />
                                {errors.name && (
                                    <p className="mt-1 text-sm text-danger">{errors.name}</p>
                                )}
                            </div>

                            <div>
                                <label htmlFor="description" className="block text-sm font-medium text-foreground">
                                    Description
                                </label>
                                <textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Optional project description..."
                                    rows={3}
                                    className="mt-1 w-full rounded-lg border border-border bg-background-secondary px-3 py-2 text-foreground placeholder:text-foreground-muted focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                                />
                                {errors.description && (
                                    <p className="mt-1 text-sm text-danger">{errors.description}</p>
                                )}
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    <Save className="mr-2 h-4 w-4" />
                                    {processing ? 'Saving...' : 'Save Changes'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* Project Info */}
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle>Project Information</CardTitle>
                        <CardDescription>Read-only project metadata</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <dl className="space-y-3">
                            <div className="flex justify-between">
                                <dt className="text-foreground-muted">UUID</dt>
                                <dd className="font-mono text-sm text-foreground">{project.uuid}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-foreground-muted">Created</dt>
                                <dd className="text-foreground">
                                    {new Date(project.created_at).toLocaleDateString('en-US', {
                                        year: 'numeric',
                                        month: 'long',
                                        day: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit',
                                    })}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-foreground-muted">Last Updated</dt>
                                <dd className="text-foreground">
                                    {new Date(project.updated_at).toLocaleDateString('en-US', {
                                        year: 'numeric',
                                        month: 'long',
                                        day: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit',
                                    })}
                                </dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                {/* Danger Zone */}
                <Card className="border-danger/50">
                    <CardHeader>
                        <CardTitle className="text-danger">Danger Zone</CardTitle>
                        <CardDescription>
                            Irreversible actions that affect your project
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {/* Show warning if project has resources */}
                        {!project.is_empty && (
                            <div className="mb-4 rounded-lg border border-warning/50 bg-warning/5 p-4">
                                <div className="flex items-start gap-3">
                                    <AlertTriangle className="mt-0.5 h-5 w-5 text-warning" />
                                    <div>
                                        <p className="font-medium text-warning">
                                            Project cannot be deleted
                                        </p>
                                        <p className="mt-1 text-sm text-foreground-muted">
                                            This project contains {project.total_resources} resource(s) that must be removed first:
                                        </p>
                                        <ul className="mt-2 list-inside list-disc text-sm text-foreground-muted">
                                            {project.resources_count.applications > 0 && (
                                                <li>{project.resources_count.applications} application(s)</li>
                                            )}
                                            {project.resources_count.services > 0 && (
                                                <li>{project.resources_count.services} service(s)</li>
                                            )}
                                            {project.resources_count.databases > 0 && (
                                                <li>{project.resources_count.databases} database(s)</li>
                                            )}
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        )}

                        {!showDeleteConfirm ? (
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="font-medium text-foreground">Delete Project</p>
                                    <p className="text-sm text-foreground-muted">
                                        Permanently delete this project and all its data
                                    </p>
                                </div>
                                <Button
                                    variant="danger"
                                    onClick={() => setShowDeleteConfirm(true)}
                                    disabled={!project.is_empty}
                                >
                                    <Trash2 className="mr-2 h-4 w-4" />
                                    Delete Project
                                </Button>
                            </div>
                        ) : (
                            <div className="rounded-lg border border-danger/50 bg-danger/5 p-4">
                                <div className="flex items-start gap-3">
                                    <AlertTriangle className="mt-0.5 h-5 w-5 text-danger" />
                                    <div className="flex-1">
                                        <p className="font-medium text-danger">
                                            Are you sure you want to delete this project?
                                        </p>
                                        <p className="mt-1 text-sm text-foreground-muted">
                                            This action cannot be undone. All environments will be permanently deleted.
                                        </p>
                                        <p className="mt-3 text-sm text-foreground">
                                            Type <span className="font-mono font-bold">{project.name}</span> to confirm:
                                        </p>
                                        <Input
                                            value={deleteConfirmName}
                                            onChange={(e) => setDeleteConfirmName(e.target.value)}
                                            placeholder={project.name}
                                            className="mt-2"
                                        />
                                        <div className="mt-4 flex gap-2">
                                            <Button
                                                variant="ghost"
                                                onClick={() => {
                                                    setShowDeleteConfirm(false);
                                                    setDeleteConfirmName('');
                                                }}
                                            >
                                                Cancel
                                            </Button>
                                            <Button
                                                variant="danger"
                                                onClick={handleDelete}
                                                disabled={deleteConfirmName !== project.name}
                                            >
                                                <Trash2 className="mr-2 h-4 w-4" />
                                                Delete Project
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
