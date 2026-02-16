import * as React from 'react';
import { SettingsLayout } from './Index';
import {
    Card, CardHeader, CardTitle, CardDescription, CardContent,
    Input, Button, Select, Modal, ModalFooter, Badge, useToast,
} from '@/components/ui';
import { Textarea } from '@/components/ui/Input';
import { router, usePage } from '@inertiajs/react';
import {
    Building2, Upload, Trash2, Globe, X, Server, FolderOpen,
    AppWindow, Users, Calendar, Clock, Info,
} from 'lucide-react';

// region Types
interface WorkspaceOwner {
    name: string;
    email: string;
}

interface WorkspaceData {
    id: number;
    name: string;
    slug: string;
    logo?: string | null;
    description: string;
    timezone: string;
    defaultEnvironment: string;
    locale: string;
    dateFormat: string;
    personalTeam: boolean;
    createdAt: string;
    owner: WorkspaceOwner | null;
}

interface WorkspaceStats {
    projects: number;
    servers: number;
    applications: number;
    members: number;
}

interface OptionItem {
    value: string;
    label: string;
}

interface WorkspacePageProps {
    workspace: WorkspaceData;
    stats: WorkspaceStats;
    timezones: string[];
    environmentOptions: OptionItem[];
    localeOptions: OptionItem[];
    dateFormatOptions: OptionItem[];
    canEdit: boolean;
    [key: string]: unknown;
}
// endregion

// region SearchableSelect
interface SearchableSelectProps {
    label: string;
    value: string;
    options: string[];
    onChange: (value: string) => void;
    disabled?: boolean;
}

function SearchableSelect({ label, value, options, onChange, disabled }: SearchableSelectProps) {
    const [search, setSearch] = React.useState('');
    const [isOpen, setIsOpen] = React.useState(false);
    const containerRef = React.useRef<HTMLDivElement>(null);

    const filtered = React.useMemo(() => {
        if (!search) return options.slice(0, 50);
        const lower = search.toLowerCase();
        return options.filter(tz => tz.toLowerCase().includes(lower)).slice(0, 50);
    }, [options, search]);

    // Group by region
    const grouped = React.useMemo(() => {
        const groups: Record<string, string[]> = {};
        for (const tz of filtered) {
            const parts = tz.split('/');
            const region = parts.length > 1 ? parts[0] : 'Other';
            if (!groups[region]) groups[region] = [];
            groups[region].push(tz);
        }
        return groups;
    }, [filtered]);

    React.useEffect(() => {
        function handleClickOutside(e: MouseEvent) {
            if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
                setIsOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    return (
        <div className="space-y-1.5" ref={containerRef}>
            <label className="text-sm font-medium text-foreground">{label}</label>
            <div className="relative">
                <input
                    type="text"
                    className="flex h-10 w-full rounded-lg border border-white/[0.08] bg-background px-3 py-2 text-sm text-foreground placeholder:text-foreground-subtle focus:border-primary/50 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:bg-background-secondary hover:border-white/[0.12] disabled:cursor-not-allowed disabled:opacity-50 disabled:bg-background-tertiary"
                    value={isOpen ? search : value}
                    onChange={(e) => {
                        setSearch(e.target.value);
                        if (!isOpen) setIsOpen(true);
                    }}
                    onFocus={() => {
                        setIsOpen(true);
                        setSearch('');
                    }}
                    placeholder="Search timezone..."
                    disabled={disabled}
                />
                <Clock className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-subtle" />
            </div>
            {isOpen && (
                <div className="absolute z-50 mt-1 max-h-64 w-full overflow-auto rounded-lg border border-border bg-background-secondary shadow-lg">
                    {Object.keys(grouped).length === 0 ? (
                        <div className="px-3 py-2 text-sm text-foreground-muted">No timezones found</div>
                    ) : (
                        Object.entries(grouped).map(([region, tzs]) => (
                            <div key={region}>
                                <div className="sticky top-0 bg-background-tertiary px-3 py-1 text-xs font-semibold text-foreground-muted">
                                    {region}
                                </div>
                                {tzs.map(tz => (
                                    <button
                                        key={tz}
                                        type="button"
                                        className={`w-full px-3 py-1.5 text-left text-sm transition-colors hover:bg-primary/10 ${tz === value ? 'bg-primary/20 text-primary font-medium' : 'text-foreground'}`}
                                        onClick={() => {
                                            onChange(tz);
                                            setIsOpen(false);
                                            setSearch('');
                                        }}
                                    >
                                        {tz.split('/').slice(1).join('/') || tz}
                                    </button>
                                ))}
                            </div>
                        ))
                    )}
                </div>
            )}
        </div>
    );
}
// endregion

export default function WorkspaceSettings() {
    const {
        workspace: initialWorkspace,
        stats,
        timezones,
        environmentOptions,
        localeOptions,
        dateFormatOptions,
        canEdit,
    } = usePage<WorkspacePageProps>().props;

    const [workspace, setWorkspace] = React.useState<WorkspaceData>(initialWorkspace);
    const [showDeleteModal, setShowDeleteModal] = React.useState(false);
    const [deleteConfirmation, setDeleteConfirmation] = React.useState('');
    const [isSaving, setIsSaving] = React.useState(false);
    const [isDeleting, setIsDeleting] = React.useState(false);
    const [isUploadingLogo, setIsUploadingLogo] = React.useState(false);
    const { addToast } = useToast();

    // Track unsaved changes
    const hasChanges = React.useMemo(() => {
        return (
            workspace.name !== initialWorkspace.name ||
            workspace.description !== initialWorkspace.description ||
            workspace.timezone !== initialWorkspace.timezone ||
            workspace.defaultEnvironment !== initialWorkspace.defaultEnvironment ||
            workspace.locale !== initialWorkspace.locale ||
            workspace.dateFormat !== initialWorkspace.dateFormat
        );
    }, [workspace, initialWorkspace]);

    // Warn on navigation with unsaved changes
    React.useEffect(() => {
        const handleBeforeUnload = (e: BeforeUnloadEvent) => {
            if (hasChanges) {
                e.preventDefault();
            }
        };
        window.addEventListener('beforeunload', handleBeforeUnload);
        return () => window.removeEventListener('beforeunload', handleBeforeUnload);
    }, [hasChanges]);

    const handleLogoUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;

        if (file.size > 2 * 1024 * 1024) {
            addToast('error', 'File too large', 'Please select an image smaller than 2MB.');
            return;
        }

        setIsUploadingLogo(true);
        router.post('/settings/workspace/logo', { logo: file }, {
            forceFormData: true,
            onSuccess: () => {
                addToast('success', 'Logo uploaded', 'Workspace logo has been updated successfully.');
            },
            onError: () => {
                addToast('error', 'Upload failed', 'Failed to upload workspace logo.');
            },
            onFinish: () => {
                setIsUploadingLogo(false);
                e.target.value = '';
            }
        });
    };

    const handleRemoveLogo = () => {
        setIsUploadingLogo(true);
        router.delete('/settings/workspace/logo', {
            onSuccess: () => {
                addToast('success', 'Logo removed', 'Workspace logo has been removed successfully.');
            },
            onError: () => {
                addToast('error', 'Remove failed', 'Failed to remove workspace logo.');
            },
            onFinish: () => {
                setIsUploadingLogo(false);
            }
        });
    };

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
            locale: workspace.locale,
            dateFormat: workspace.dateFormat,
        }, {
            onSuccess: () => {
                addToast('success', 'Workspace updated', 'Your workspace settings have been saved successfully.');
            },
            onError: (errors) => {
                const errorMessage = Object.values(errors).flat().join(', ') || 'An error occurred while saving your workspace settings.';
                addToast('error', 'Failed to save workspace', errorMessage);
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
                    addToast('success', 'Workspace deleted', 'Your workspace has been deleted successfully.');
                    setShowDeleteModal(false);
                    setDeleteConfirmation('');
                },
                onError: (errors) => {
                    const errorMessage = Object.values(errors).flat().join(', ') || 'An error occurred while deleting your workspace.';
                    addToast('error', 'Failed to delete workspace', errorMessage);
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

    const statItems = [
        { label: 'Projects', value: stats.projects, icon: FolderOpen, color: 'text-blue-400' },
        { label: 'Servers', value: stats.servers, icon: Server, color: 'text-green-400' },
        { label: 'Applications', value: stats.applications, icon: AppWindow, color: 'text-purple-400' },
        { label: 'Members', value: stats.members, icon: Users, color: 'text-amber-400' },
    ];

    return (
        <SettingsLayout activeSection="workspace">
            <div className="space-y-6">
                {/* Unsaved Changes Banner */}
                {hasChanges && (
                    <div className="flex items-center justify-between rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3">
                        <div className="flex items-center gap-2">
                            <Info className="h-4 w-4 text-amber-400" />
                            <span className="text-sm font-medium text-amber-200">You have unsaved changes</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setWorkspace(initialWorkspace)}
                            >
                                Discard
                            </Button>
                            <Button size="sm" onClick={handleSubmit} loading={isSaving}>
                                Save Changes
                            </Button>
                        </div>
                    </div>
                )}

                {/* Workspace Statistics */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-4">
                                <div className="relative">
                                    {workspace.logo ? (
                                        <img
                                            src={`/storage/${workspace.logo}`}
                                            alt={workspace.name}
                                            className="h-14 w-14 rounded-xl object-cover"
                                        />
                                    ) : (
                                        <div className="flex h-14 w-14 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-purple-500 text-xl font-semibold text-white">
                                            {workspace.name.charAt(0).toUpperCase()}
                                        </div>
                                    )}
                                </div>
                                <div>
                                    <CardTitle>{workspace.name}</CardTitle>
                                    <CardDescription className="flex items-center gap-2">
                                        {workspace.personalTeam && (
                                            <Badge variant="info">Personal</Badge>
                                        )}
                                        {workspace.owner && (
                                            <span>Owner: {workspace.owner.name}</span>
                                        )}
                                    </CardDescription>
                                </div>
                            </div>
                            {workspace.createdAt && (
                                <div className="flex items-center gap-1.5 text-xs text-foreground-subtle">
                                    <Calendar className="h-3.5 w-3.5" />
                                    Created {new Date(workspace.createdAt).toLocaleDateString()}
                                </div>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-4 gap-4">
                            {statItems.map((stat) => {
                                const Icon = stat.icon;
                                return (
                                    <div key={stat.label} className="rounded-lg bg-background-tertiary p-4 text-center">
                                        <div className="mb-1 flex items-center justify-center">
                                            <Icon className={`h-5 w-5 ${stat.color}`} />
                                        </div>
                                        <p className="text-2xl font-bold text-foreground">{stat.value}</p>
                                        <p className="text-sm text-foreground-muted">{stat.label}</p>
                                    </div>
                                );
                            })}
                        </div>
                    </CardContent>
                </Card>

                {/* Workspace Settings Form */}
                <form onSubmit={handleSubmit}>
                    {/* General Settings */}
                    <Card className="mb-6">
                        <CardHeader>
                            <CardTitle>General</CardTitle>
                            <CardDescription>
                                Basic workspace information
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {/* Logo */}
                            <div>
                                <label className="mb-2 block text-sm font-medium text-foreground">
                                    Workspace Logo
                                </label>
                                <div className="flex items-center gap-4">
                                    <div className="relative">
                                        {workspace.logo ? (
                                            <img
                                                src={`/storage/${workspace.logo}`}
                                                alt={workspace.name}
                                                className="h-16 w-16 rounded-lg object-cover"
                                            />
                                        ) : (
                                            <div className="flex h-16 w-16 items-center justify-center rounded-lg bg-primary text-2xl font-semibold text-white">
                                                <Building2 className="h-8 w-8" />
                                            </div>
                                        )}
                                        {workspace.logo && (
                                            <button
                                                type="button"
                                                onClick={handleRemoveLogo}
                                                disabled={isUploadingLogo || !canEdit}
                                                className="absolute -right-1 -top-1 flex h-5 w-5 items-center justify-center rounded-full bg-danger text-white hover:bg-danger/80 disabled:opacity-50"
                                                title="Remove logo"
                                            >
                                                <X className="h-3 w-3" />
                                            </button>
                                        )}
                                    </div>
                                    <div>
                                        <input
                                            type="file"
                                            id="workspace-logo"
                                            accept="image/png,image/jpeg,image/jpg,image/gif,image/webp,image/svg+xml"
                                            className="hidden"
                                            onChange={handleLogoUpload}
                                            disabled={isUploadingLogo || !canEdit}
                                        />
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            size="sm"
                                            onClick={() => document.getElementById('workspace-logo')?.click()}
                                            loading={isUploadingLogo}
                                            disabled={!canEdit}
                                        >
                                            <Upload className="mr-2 h-4 w-4" />
                                            {workspace.logo ? 'Change Logo' : 'Upload Logo'}
                                        </Button>
                                        <p className="mt-1 text-xs text-foreground-subtle">
                                            Max 2MB. PNG, JPG, GIF, WebP, or SVG.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <Input
                                label="Workspace Name"
                                value={workspace.name}
                                onChange={(e) => handleNameChange(e.target.value)}
                                placeholder="My Workspace"
                                required
                                disabled={!canEdit}
                            />

                            <div>
                                <Input
                                    label="Workspace Slug"
                                    value={workspace.slug}
                                    placeholder="my-workspace"
                                    disabled
                                    hint="Auto-generated from workspace name. Used in URLs."
                                />
                            </div>

                            <Textarea
                                label="Description"
                                value={workspace.description}
                                onChange={(e) => setWorkspace({ ...workspace, description: e.target.value })}
                                placeholder="A brief description of your workspace..."
                                rows={3}
                                disabled={!canEdit}
                                hint="Optional. Helps team members understand the purpose of this workspace."
                            />
                        </CardContent>
                    </Card>

                    {/* Defaults & Regional */}
                    <Card className="mb-6">
                        <CardHeader>
                            <CardTitle>Defaults & Regional</CardTitle>
                            <CardDescription>
                                Configure default settings for new projects and regional preferences
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <Select
                                    label="Default Environment"
                                    value={workspace.defaultEnvironment}
                                    onChange={(e) => setWorkspace({ ...workspace, defaultEnvironment: e.target.value })}
                                    disabled={!canEdit}
                                >
                                    {environmentOptions.map((env) => (
                                        <option key={env.value} value={env.value}>
                                            {env.label}
                                        </option>
                                    ))}
                                </Select>

                                <Select
                                    label="Language"
                                    value={workspace.locale}
                                    onChange={(e) => setWorkspace({ ...workspace, locale: e.target.value })}
                                    disabled={!canEdit}
                                >
                                    {localeOptions.map((loc) => (
                                        <option key={loc.value} value={loc.value}>
                                            {loc.label}
                                        </option>
                                    ))}
                                </Select>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="relative">
                                    <SearchableSelect
                                        label="Timezone"
                                        value={workspace.timezone}
                                        options={timezones}
                                        onChange={(tz) => setWorkspace({ ...workspace, timezone: tz })}
                                        disabled={!canEdit}
                                    />
                                </div>

                                <Select
                                    label="Date Format"
                                    value={workspace.dateFormat}
                                    onChange={(e) => setWorkspace({ ...workspace, dateFormat: e.target.value })}
                                    disabled={!canEdit}
                                >
                                    {dateFormatOptions.map((df) => (
                                        <option key={df.value} value={df.value}>
                                            {df.label}
                                        </option>
                                    ))}
                                </Select>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Workspace URL */}
                    <Card className="mb-6">
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
                                    https://saturn.ac/w/{workspace.slug}
                                </code>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Save Button */}
                    {canEdit && (
                        <div className="flex justify-end mb-6">
                            <Button type="submit" loading={isSaving} disabled={!hasChanges}>
                                Save Changes
                            </Button>
                        </div>
                    )}
                </form>

                {/* Danger Zone */}
                {!workspace.personalTeam && canEdit && (
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
