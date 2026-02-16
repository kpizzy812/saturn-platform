import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, CardHeader, CardTitle, Button, Input, Badge, StatusBadge, useConfirm } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import {
    Tag as TagIcon,
    Edit2,
    Trash2,
    Save,
    X,
    Rocket,
    Package,
    Database as DatabaseIcon,
    Globe,
} from 'lucide-react';
import type { Tag, Application, Service, StandaloneDatabase } from '@/types';

interface ApplicationWithRelations extends Application {
    project_name?: string;
    environment_name?: string;
}

interface Props {
    tag: Tag;
    applications: ApplicationWithRelations[];
    services: Service[];
    databases?: StandaloneDatabase[];
}

export default function TagShow({ tag: initialTag, applications = [], services = [], databases = [] }: Props) {
    const confirm = useConfirm();
    const { addToast } = useToast();
    const [tag, setTag] = useState(initialTag);
    const [isEditing, setIsEditing] = useState(false);
    const [editName, setEditName] = useState(tag.name);
    const [editColor, setEditColor] = useState(tag.color || '#6366f1');

    const totalResources = applications.length + services.length + databases.length;
    const tagColor = tag.color || '#6366f1';

    const handleSave = () => {
        router.patch(`/tags/${tag.id}`, {
            name: editName,
            color: editColor,
        }, {
            onSuccess: () => {
                setTag({ ...tag, name: editName, color: editColor });
                setIsEditing(false);
            },
            preserveScroll: true,
        });
    };

    const handleCancel = () => {
        setEditName(tag.name);
        setEditColor(tag.color || '#6366f1');
        setIsEditing(false);
    };

    const handleDelete = async () => {
        if (totalResources > 0) {
            addToast('error', 'Cannot delete tag with resources. Remove all resources from this tag first.');
            return;
        }

        const confirmed = await confirm({
            title: 'Delete Tag',
            description: 'Are you sure you want to delete this tag? This action cannot be undone.',
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            router.delete(`/tags/${tag.id}`, {
                onSuccess: () => {
                    router.visit('/tags');
                },
            });
        }
    };

    const colorPresets = [
        '#6366f1', // indigo
        '#8b5cf6', // violet
        '#ec4899', // pink
        '#f59e0b', // amber
        '#10b981', // emerald
        '#06b6d4', // cyan
        '#ef4444', // red
        '#64748b', // slate
    ];

    return (
        <AppLayout
            title={tag.name}
            breadcrumbs={[
                { label: 'Tags', href: '/tags' },
                { label: tag.name },
            ]}
        >
            <div className="mx-auto max-w-7xl">
                {/* Header */}
                <div className="mb-6">
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-start justify-between">
                                <div className="flex items-start gap-4 flex-1">
                                    <div
                                        className="flex h-16 w-16 items-center justify-center rounded-lg shrink-0"
                                        style={{ backgroundColor: `${tagColor}20` }}
                                    >
                                        <TagIcon className="h-8 w-8" style={{ color: tagColor }} />
                                    </div>
                                    {isEditing ? (
                                        <div className="flex-1 space-y-4">
                                            <Input
                                                value={editName}
                                                onChange={(e) => setEditName(e.target.value)}
                                                className="text-2xl font-bold"
                                                autoFocus
                                            />
                                            <div>
                                                <label className="block text-sm font-medium text-foreground mb-2">
                                                    Color
                                                </label>
                                                <div className="flex gap-2 flex-wrap">
                                                    {colorPresets.map((presetColor) => (
                                                        <button
                                                            key={presetColor}
                                                            type="button"
                                                            className={`h-10 w-10 rounded-lg border-2 transition-all ${
                                                                editColor === presetColor
                                                                    ? 'border-foreground scale-110'
                                                                    : 'border-transparent hover:border-foreground-muted'
                                                            }`}
                                                            style={{ backgroundColor: presetColor }}
                                                            onClick={() => setEditColor(presetColor)}
                                                        />
                                                    ))}
                                                    <input
                                                        type="color"
                                                        value={editColor}
                                                        onChange={(e) => setEditColor(e.target.value)}
                                                        className="h-10 w-10 cursor-pointer rounded-lg border-2 border-border"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    ) : (
                                        <div>
                                            <h1 className="text-2xl font-bold text-foreground">{tag.name}</h1>
                                            <p className="mt-1 text-sm text-foreground-muted">
                                                {totalResources} {totalResources === 1 ? 'resource' : 'resources'}
                                            </p>
                                        </div>
                                    )}
                                </div>

                                <div className="flex items-center gap-2">
                                    {isEditing ? (
                                        <>
                                            <Button variant="outline" size="sm" onClick={handleCancel}>
                                                <X className="mr-2 h-4 w-4" />
                                                Cancel
                                            </Button>
                                            <Button size="sm" onClick={handleSave}>
                                                <Save className="mr-2 h-4 w-4" />
                                                Save
                                            </Button>
                                        </>
                                    ) : (
                                        <>
                                            <Button variant="outline" size="sm" onClick={() => setIsEditing(true)}>
                                                <Edit2 className="mr-2 h-4 w-4" />
                                                Edit
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={handleDelete}
                                                className="text-destructive hover:bg-destructive/10"
                                            >
                                                <Trash2 className="mr-2 h-4 w-4" />
                                                Delete
                                            </Button>
                                        </>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Main Content */}
                    <div className="space-y-6 lg:col-span-2">
                        {/* Applications */}
                        {applications.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <CardTitle className="flex items-center gap-2">
                                            <Rocket className="h-5 w-5" />
                                            Applications ({applications.length})
                                        </CardTitle>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-3">
                                        {applications.map((app) => (
                                            <ApplicationItem key={app.id} application={app} />
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Services */}
                        {services.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <CardTitle className="flex items-center gap-2">
                                            <Package className="h-5 w-5" />
                                            Services ({services.length})
                                        </CardTitle>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-3">
                                        {services.map((service) => (
                                            <ServiceItem key={service.id} service={service} />
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Databases */}
                        {databases.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <CardTitle className="flex items-center gap-2">
                                            <DatabaseIcon className="h-5 w-5" />
                                            Databases ({databases.length})
                                        </CardTitle>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-3">
                                        {databases.map((db) => (
                                            <DatabaseItem key={db.id} database={db} />
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Empty State */}
                        {totalResources === 0 && (
                            <Card className="p-12 text-center">
                                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary">
                                    <TagIcon className="h-8 w-8 text-foreground-muted" />
                                </div>
                                <h3 className="mt-4 text-lg font-medium text-foreground">No resources yet</h3>
                                <p className="mt-2 text-foreground-muted">
                                    Add this tag to applications, services, or databases to organize them.
                                </p>
                            </Card>
                        )}
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        {/* Stats Card */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Resource Summary</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <StatItem
                                    icon={<Rocket className="h-4 w-4" />}
                                    label="Applications"
                                    value={applications.length}
                                />
                                <StatItem
                                    icon={<Package className="h-4 w-4" />}
                                    label="Services"
                                    value={services.length}
                                />
                                <StatItem
                                    icon={<DatabaseIcon className="h-4 w-4" />}
                                    label="Databases"
                                    value={databases.length}
                                />
                            </CardContent>
                        </Card>

                        {/* Info Card */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Information</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div>
                                    <p className="text-xs text-foreground-muted">Created</p>
                                    <p className="mt-1 text-sm font-medium text-foreground">
                                        {new Date(tag.created_at).toLocaleDateString()}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-foreground-muted">Last Updated</p>
                                    <p className="mt-1 text-sm font-medium text-foreground">
                                        {new Date(tag.updated_at).toLocaleDateString()}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function ApplicationItem({ application }: { application: ApplicationWithRelations }) {
    return (
        <Link
            href={`/applications/${application.uuid}`}
            className="flex items-center justify-between rounded-lg border border-border p-3 transition-colors hover:border-primary/50 hover:bg-background-secondary"
        >
            <div className="flex items-center gap-3 flex-1 min-w-0">
                <Rocket className="h-4 w-4 text-primary shrink-0" />
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-foreground truncate">{application.name}</p>
                    <div className="flex items-center gap-2 text-xs text-foreground-muted">
                        <span>{application.project_name}</span>
                        <span>/</span>
                        <span>{application.environment_name}</span>
                    </div>
                </div>
            </div>
            <div className="flex items-center gap-3">
                {application.fqdn && (
                    <Globe className="h-3 w-3 text-foreground-muted" />
                )}
                <StatusBadge status={application.status} />
            </div>
        </Link>
    );
}

function ServiceItem({ service }: { service: Service }) {
    return (
        <Link
            href={`/services/${service.uuid}`}
            className="flex items-center justify-between rounded-lg border border-border p-3 transition-colors hover:border-primary/50 hover:bg-background-secondary"
        >
            <div className="flex items-center gap-3 flex-1 min-w-0">
                <Package className="h-4 w-4 text-primary shrink-0" />
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-foreground truncate">{service.name}</p>
                    {service.description && (
                        <p className="text-xs text-foreground-muted truncate">{service.description}</p>
                    )}
                </div>
            </div>
        </Link>
    );
}

function DatabaseItem({ database }: { database: StandaloneDatabase }) {
    return (
        <Link
            href={`/databases/${database.uuid}`}
            className="flex items-center justify-between rounded-lg border border-border p-3 transition-colors hover:border-primary/50 hover:bg-background-secondary"
        >
            <div className="flex items-center gap-3 flex-1 min-w-0">
                <DatabaseIcon className="h-4 w-4 text-primary shrink-0" />
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-foreground truncate">{database.name}</p>
                    <div className="flex items-center gap-2">
                        <Badge variant="outline" className="text-xs">
                            {database.database_type}
                        </Badge>
                        <StatusBadge status={typeof database.status === 'object' ? database.status.state : database.status} />
                    </div>
                </div>
            </div>
        </Link>
    );
}

interface StatItemProps {
    icon: React.ReactNode;
    label: string;
    value: number;
}

function StatItem({ icon, label, value }: StatItemProps) {
    return (
        <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
                <div className="text-foreground-muted">{icon}</div>
                <span className="text-sm text-foreground-muted">{label}</span>
            </div>
            <span className="text-sm font-medium text-foreground">{value}</span>
        </div>
    );
}
