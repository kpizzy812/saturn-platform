import * as React from 'react';
import { SettingsLayout } from './Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Input, Button, Select, Modal, ModalFooter, useToast } from '@/components/ui';
import { router, usePage } from '@inertiajs/react';
import { Building2, Upload, Trash2, Globe } from 'lucide-react';

interface WorkspaceData {
    id: number;
    name: string;
    slug: string;
    description?: string;
    timezone: string;
    defaultEnvironment: string;
    personalTeam: boolean;
}

interface EnvironmentOption {
    value: string;
    label: string;
}

interface PageProps {
    workspace: WorkspaceData;
    timezones: string[];
    environmentOptions: EnvironmentOption[];
}

export default function WorkspaceSettings() {
    const { workspace: initialWorkspace, timezones, environmentOptions } = usePage<PageProps>().props;

    const [workspace, setWorkspace] = React.useState<WorkspaceData>(initialWorkspace);
    const [showDeleteModal, setShowDeleteModal] = React.useState(false);
    const [deleteConfirmation, setDeleteConfirmation] = React.useState('');
    const [isSaving, setIsSaving] = React.useState(false);
    const [isDeleting, setIsDeleting] = React.useState(false);
    const { addToast } = useToast();

    // Update local state when props change (e.g., after successful save)
    React.useEffect(() => {
        setWorkspace(initialWorkspace);
    }, [initialWorkspace]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSaving(true);

        router.post('/settings/workspace', {
            name: workspace.name,
            description: workspace.description,
            timezone: workspace.timezone,
            defaultEnvironment: workspace.defaultEnvironment,
        }, {
            onSuccess: () => {
                addToast({
                    title: 'Workspace updated',
                    description: 'Your workspace settings have been saved successfully.',
                });
            },
            onError: (errors) => {
                const errorMessage = Object.values(errors).flat().join(', ') || 'An error occurred while saving your workspace settings.';
                addToast({
                    title: 'Failed to save workspace',
                    description: errorMessage,
                    variant: 'danger',
                });
            },
            onFinish: () => {
                setIsSaving(false);
            }
        });
    };

    const handleDeleteWorkspace = () => {
        if (deleteConfirmation === workspace.name) {
            setIsDeleting(true);

            router.delete('/settings/workspace', {
                onSuccess: () => {
                    addToast({
                        title: 'Workspace deleted',
                        description: 'Your workspace has been deleted successfully.',
                    });
                    setShowDeleteModal(false);
                    setDeleteConfirmation('');
                    // User will likely be redirected by the backend
                },
                onError: (errors) => {
                    const errorMessage = Object.values(errors).flat().join(', ') || 'An error occurred while deleting your workspace.';
                    addToast({
                        title: 'Failed to delete workspace',
                        description: errorMessage,
                        variant: 'danger',
                    });
                },
                onFinish: () => {
                    setIsDeleting(false);
                }
            });
        }
    };

    const generateSlug = (name: string) => {
        return name
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    };

    const handleNameChange = (name: string) => {
        setWorkspace({
            ...workspace,
            name,
            slug: generateSlug(name),
        });
    };

    return (
        <SettingsLayout activeSection="workspace">
            <div className="space-y-6">
                {/* Workspace Details */}
                <Card>
                    <CardHeader>
                        <CardTitle>Workspace Details</CardTitle>
                        <CardDescription>
                            Update your workspace name and settings
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            {/* Workspace Avatar */}
                            <div>
                                <label className="mb-2 block text-sm font-medium text-foreground">
                                    Workspace Logo
                                </label>
                                <div className="flex items-center gap-4">
                                    <div className="flex h-16 w-16 items-center justify-center rounded-lg bg-primary text-2xl font-semibold text-white">
                                        <Building2 className="h-8 w-8" />
                                    </div>
                                    <div>
                                        <input
                                            type="file"
                                            id="workspace-logo"
                                            accept="image/png,image/jpeg,image/svg+xml"
                                            className="hidden"
                                            onChange={(e) => {
                                                const file = e.target.files?.[0];
                                                if (!file) return;
                                                if (file.size > 2 * 1024 * 1024) {
                                                    addToast('error', 'File size must be less than 2MB');
                                                    return;
                                                }
                                                router.post('/settings/workspace/logo', { logo: file }, {
                                                    forceFormData: true,
                                                    onSuccess: () => addToast('success', 'Logo uploaded'),
                                                    onError: () => addToast('error', 'Failed to upload logo'),
                                                });
                                            }}
                                        />
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            size="sm"
                                            onClick={() => document.getElementById('workspace-logo')?.click()}
                                        >
                                            <Upload className="mr-2 h-4 w-4" />
                                            Upload Logo
                                        </Button>
                                    </div>
                                </div>
                                <p className="mt-2 text-xs text-foreground-subtle">
                                    Recommended size: 256x256px. Max file size: 2MB. PNG, JPG, or SVG.
                                </p>
                            </div>

                            <Input
                                label="Workspace Name"
                                value={workspace.name}
                                onChange={(e) => handleNameChange(e.target.value)}
                                placeholder="My Workspace"
                                required
                            />

                            <div>
                                <Input
                                    label="Workspace Slug"
                                    value={workspace.slug}
                                    onChange={(e) => setWorkspace({ ...workspace, slug: e.target.value })}
                                    placeholder="my-workspace"
                                    required
                                    disabled
                                />
                                <p className="mt-1 text-xs text-foreground-subtle">
                                    Used in URLs: saturn.app/w/{workspace.slug}
                                </p>
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" loading={isSaving}>
                                    Save Changes
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* Default Settings */}
                <Card>
                    <CardHeader>
                        <CardTitle>Default Settings</CardTitle>
                        <CardDescription>
                            Configure default settings for new projects
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <Select
                                label="Default Environment"
                                value={workspace.defaultEnvironment}
                                onChange={(e) => setWorkspace({ ...workspace, defaultEnvironment: e.target.value })}
                            >
                                {environmentOptions.map((env) => (
                                    <option key={env.value} value={env.value}>
                                        {env.label}
                                    </option>
                                ))}
                            </Select>

                            <Select
                                label="Timezone"
                                value={workspace.timezone}
                                onChange={(e) => setWorkspace({ ...workspace, timezone: e.target.value })}
                            >
                                {timezones.map((tz) => (
                                    <option key={tz} value={tz}>
                                        {tz}
                                    </option>
                                ))}
                            </Select>

                            <div className="flex justify-end">
                                <Button type="submit" loading={isSaving}>
                                    Save Changes
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* Workspace URL */}
                <Card>
                    <CardHeader>
                        <CardTitle>Workspace URL</CardTitle>
                        <CardDescription>
                            Your workspace is accessible at this URL
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex items-center gap-3 rounded-lg bg-background-secondary p-4">
                            <Globe className="h-5 w-5 text-foreground-muted" />
                            <code className="flex-1 text-sm text-foreground">
                                https://saturn.app/w/{workspace.slug}
                            </code>
                        </div>
                    </CardContent>
                </Card>

                {/* Danger Zone - Only show if not personal team */}
                {!workspace.personalTeam && (
                    <Card className="border-danger/50">
                        <CardHeader>
                            <CardTitle className="text-danger">Danger Zone</CardTitle>
                            <CardDescription>
                                Irreversible actions that affect your workspace
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-danger/10">
                                        <Trash2 className="h-5 w-5 text-danger" />
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-foreground">Delete Workspace</p>
                                        <p className="text-xs text-foreground-muted">
                                            Permanently delete this workspace and all projects
                                        </p>
                                    </div>
                                </div>
                                <Button variant="danger" onClick={() => setShowDeleteModal(true)}>
                                    Delete Workspace
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* Delete Confirmation Modal */}
            <Modal
                isOpen={showDeleteModal}
                onClose={() => setShowDeleteModal(false)}
                title="Delete Workspace"
                description="This action cannot be undone. This will permanently delete your workspace, all projects, deployments, and data."
            >
                <div className="space-y-4">
                    <div className="rounded-lg bg-danger/10 p-4">
                        <p className="text-sm font-medium text-danger">
                            Please type <strong>{workspace.name}</strong> to confirm deletion
                        </p>
                    </div>
                    <Input
                        value={deleteConfirmation}
                        onChange={(e) => setDeleteConfirmation(e.target.value)}
                        placeholder={workspace.name}
                    />
                </div>

                <ModalFooter>
                    <Button variant="secondary" onClick={() => setShowDeleteModal(false)} disabled={isDeleting}>
                        Cancel
                    </Button>
                    <Button
                        variant="danger"
                        onClick={handleDeleteWorkspace}
                        disabled={deleteConfirmation !== workspace.name}
                        loading={isDeleting}
                    >
                        Delete Workspace
                    </Button>
                </ModalFooter>
            </Modal>
        </SettingsLayout>
    );
}
