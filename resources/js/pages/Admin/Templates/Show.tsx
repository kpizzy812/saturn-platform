import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import {
    ArrowLeft,
    Save,
    Plus,
    X,
    Info,
    Copy,
    Trash2,
    CheckCircle,
    Star,
    Download,
    Calendar,
} from 'lucide-react';

interface TemplateConfig {
    build_pack: string;
    ports_exposes?: string;
    install_command?: string;
    build_command?: string;
    start_command?: string;
    base_directory?: string;
    publish_directory?: string;
    environment_variables?: Array<{ key: string; value: string; is_secret: boolean }>;
}

interface Template {
    id: number;
    uuid: string;
    name: string;
    slug: string;
    description?: string;
    category: string;
    icon?: string;
    is_official: boolean;
    is_public: boolean;
    version: string;
    tags: string[];
    config: TemplateConfig;
    usage_count: number;
    rating?: number;
    rating_count: number;
    created_by?: string;
    created_at: string;
    updated_at: string;
}

interface Props {
    template: Template;
    categories: Record<string, string>;
}

export default function AdminTemplatesShow({ template: initialTemplate, categories }: Props) {
    const [isEditing, setIsEditing] = React.useState(false);
    const [name, setName] = React.useState(initialTemplate.name);
    const [description, setDescription] = React.useState(initialTemplate.description || '');
    const [category, setCategory] = React.useState(initialTemplate.category);
    const [icon, setIcon] = React.useState(initialTemplate.icon || '');
    const [isOfficial, setIsOfficial] = React.useState(initialTemplate.is_official);
    const [isPublic, setIsPublic] = React.useState(initialTemplate.is_public);
    const [tags, setTags] = React.useState<string[]>(initialTemplate.tags || []);
    const [tagInput, setTagInput] = React.useState('');
    const [config, setConfig] = React.useState<TemplateConfig>({
        build_pack: initialTemplate.config?.build_pack || 'nixpacks',
        ports_exposes: initialTemplate.config?.ports_exposes || '3000',
        install_command: initialTemplate.config?.install_command || '',
        build_command: initialTemplate.config?.build_command || '',
        start_command: initialTemplate.config?.start_command || '',
        base_directory: initialTemplate.config?.base_directory || '/',
        publish_directory: initialTemplate.config?.publish_directory || '',
        environment_variables: initialTemplate.config?.environment_variables || [],
    });
    const [saving, setSaving] = React.useState(false);
    const [errors, setErrors] = React.useState<Record<string, string>>({});

    const handleAddTag = () => {
        if (tagInput.trim() && !tags.includes(tagInput.trim())) {
            setTags([...tags, tagInput.trim()]);
            setTagInput('');
        }
    };

    const handleRemoveTag = (tag: string) => {
        setTags(tags.filter((t) => t !== tag));
    };

    const handleAddEnvVar = () => {
        setConfig({
            ...config,
            environment_variables: [
                ...(config.environment_variables || []),
                { key: '', value: '', is_secret: false },
            ],
        });
    };

    const handleRemoveEnvVar = (index: number) => {
        const newEnvVars = [...(config.environment_variables || [])];
        newEnvVars.splice(index, 1);
        setConfig({ ...config, environment_variables: newEnvVars });
    };

    const handleEnvVarChange = (index: number, field: string, value: string | boolean) => {
        const newEnvVars = [...(config.environment_variables || [])];
        newEnvVars[index] = { ...newEnvVars[index], [field]: value };
        setConfig({ ...config, environment_variables: newEnvVars });
    };

    const handleSave = () => {
        setSaving(true);
        setErrors({});

        router.put(
            `/admin/templates/${initialTemplate.uuid}`,
            {
                name,
                description,
                category,
                icon,
                is_official: isOfficial,
                is_public: isPublic,
                tags,
                config: config as any,
            },
            {
                onSuccess: () => {
                    setIsEditing(false);
                },
                onError: (errs) => {
                    setErrors(errs);
                },
                onFinish: () => setSaving(false),
            }
        );
    };

    const handleDuplicate = () => {
        if (confirm('Duplicate this template?')) {
            router.post(`/admin/templates/${initialTemplate.uuid}/duplicate`);
        }
    };

    const handleDelete = () => {
        if (
            confirm(
                'Are you sure you want to delete this template? This action cannot be undone.'
            )
        ) {
            router.delete(`/admin/templates/${initialTemplate.uuid}`);
        }
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    return (
        <AdminLayout
            title={initialTemplate.name}
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Templates', href: '/admin/templates' },
                { label: initialTemplate.name },
            ]}
        >
            <div className="mx-auto max-w-4xl">
                {/* Header */}
                <div className="mb-8 flex items-start justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="sm" onClick={() => router.visit('/admin/templates')}>
                            <ArrowLeft className="h-4 w-4" />
                        </Button>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-2xl font-semibold text-foreground">
                                    {initialTemplate.name}
                                </h1>
                                {initialTemplate.is_official && (
                                    <Badge
                                        variant="success"
                                        size="sm"
                                        icon={<CheckCircle className="h-3 w-3" />}
                                    >
                                        Official
                                    </Badge>
                                )}
                            </div>
                            <p className="mt-1 text-sm text-foreground-muted">
                                {categories[initialTemplate.category]} template
                            </p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        {isEditing ? (
                            <>
                                <Button variant="secondary" onClick={() => setIsEditing(false)}>
                                    Cancel
                                </Button>
                                <Button variant="primary" onClick={handleSave} disabled={saving}>
                                    <Save className="mr-2 h-4 w-4" />
                                    {saving ? 'Saving...' : 'Save Changes'}
                                </Button>
                            </>
                        ) : (
                            <>
                                <Button variant="secondary" onClick={() => setIsEditing(true)}>
                                    Edit
                                </Button>
                                <Button variant="secondary" onClick={handleDuplicate}>
                                    <Copy className="mr-2 h-4 w-4" />
                                    Duplicate
                                </Button>
                                <Button variant="danger" onClick={handleDelete}>
                                    <Trash2 className="mr-2 h-4 w-4" />
                                    Delete
                                </Button>
                            </>
                        )}
                    </div>
                </div>

                {/* Stats */}
                <div className="mb-6 grid gap-4 sm:grid-cols-4">
                    <Card variant="glass">
                        <CardContent className="p-4 text-center">
                            <Download className="mx-auto h-6 w-6 text-foreground-muted" />
                            <p className="mt-2 text-2xl font-bold">{initialTemplate.usage_count}</p>
                            <p className="text-xs text-foreground-muted">Uses</p>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4 text-center">
                            <Star className="mx-auto h-6 w-6 text-warning" />
                            <p className="mt-2 text-2xl font-bold">
                                {initialTemplate.rating?.toFixed(1) || 'N/A'}
                            </p>
                            <p className="text-xs text-foreground-muted">
                                Rating ({initialTemplate.rating_count})
                            </p>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4 text-center">
                            <Calendar className="mx-auto h-6 w-6 text-foreground-muted" />
                            <p className="mt-2 text-sm font-medium">
                                {formatDate(initialTemplate.created_at)}
                            </p>
                            <p className="text-xs text-foreground-muted">Created</p>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4 text-center">
                            <Calendar className="mx-auto h-6 w-6 text-foreground-muted" />
                            <p className="mt-2 text-sm font-medium">
                                {formatDate(initialTemplate.updated_at)}
                            </p>
                            <p className="text-xs text-foreground-muted">Updated</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Basic Information */}
                <Card variant="glass" className="mb-6">
                    <CardHeader>
                        <CardTitle>Basic Information</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {isEditing ? (
                            <>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label className="text-sm font-medium text-foreground" htmlFor="name">Template Name *</label>
                                        <Input
                                            id="name"
                                            value={name}
                                            onChange={(e) => setName(e.target.value)}
                                            required
                                        />
                                        {errors.name && (
                                            <p className="mt-1 text-sm text-danger">{errors.name}</p>
                                        )}
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-foreground" htmlFor="category">Category *</label>
                                        <select
                                            id="category"
                                            value={category}
                                            onChange={(e) => setCategory(e.target.value)}
                                            className="w-full rounded-md border border-border bg-background px-3 py-2 text-foreground"
                                        >
                                            {Object.entries(categories).map(([key, label]) => (
                                                <option key={key} value={key}>
                                                    {label}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-foreground" htmlFor="description">Description</label>
                                    <textarea
                                        id="description"
                                        value={description}
                                        onChange={(e) => setDescription(e.target.value)}
                                        rows={3}
                                        className="w-full rounded-md border border-border bg-background px-3 py-2 text-foreground"
                                    />
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label className="text-sm font-medium text-foreground" htmlFor="icon">Icon</label>
                                        <Input
                                            id="icon"
                                            value={icon}
                                            onChange={(e) => setIcon(e.target.value)}
                                            maxLength={5}
                                        />
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-foreground">Tags</label>
                                        <div className="flex gap-2">
                                            <Input
                                                value={tagInput}
                                                onChange={(e) => setTagInput(e.target.value)}
                                                placeholder="Add tag..."
                                                onKeyDown={(e) => {
                                                    if (e.key === 'Enter') {
                                                        e.preventDefault();
                                                        handleAddTag();
                                                    }
                                                }}
                                            />
                                            <Button type="button" variant="secondary" onClick={handleAddTag}>
                                                <Plus className="h-4 w-4" />
                                            </Button>
                                        </div>
                                        {tags.length > 0 && (
                                            <div className="mt-2 flex flex-wrap gap-1">
                                                {tags.map((tag) => (
                                                    <span
                                                        key={tag}
                                                        className="inline-flex items-center gap-1 rounded-full bg-primary/20 px-2 py-1 text-xs text-primary"
                                                    >
                                                        {tag}
                                                        <button
                                                            type="button"
                                                            onClick={() => handleRemoveTag(tag)}
                                                            className="hover:text-danger"
                                                        >
                                                            <X className="h-3 w-3" />
                                                        </button>
                                                    </span>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                </div>
                                <div className="flex gap-6">
                                    <label className="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            checked={isOfficial}
                                            onChange={(e) => setIsOfficial(e.target.checked)}
                                            className="rounded border-border"
                                        />
                                        <span className="text-sm text-foreground">Official Template</span>
                                    </label>
                                    <label className="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            checked={isPublic}
                                            onChange={(e) => setIsPublic(e.target.checked)}
                                            className="rounded border-border"
                                        />
                                        <span className="text-sm text-foreground">Public</span>
                                    </label>
                                </div>
                            </>
                        ) : (
                            <>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <p className="text-sm text-foreground-muted">Name</p>
                                        <p className="font-medium">{initialTemplate.name}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-foreground-muted">Category</p>
                                        <p className="font-medium">{categories[initialTemplate.category]}</p>
                                    </div>
                                </div>
                                <div>
                                    <p className="text-sm text-foreground-muted">Description</p>
                                    <p>{initialTemplate.description || 'No description'}</p>
                                </div>
                                <div>
                                    <p className="mb-2 text-sm text-foreground-muted">Tags</p>
                                    <div className="flex flex-wrap gap-1">
                                        {initialTemplate.tags?.length > 0 ? (
                                            initialTemplate.tags.map((tag) => (
                                                <Badge key={tag} variant="default" size="sm">
                                                    {tag}
                                                </Badge>
                                            ))
                                        ) : (
                                            <span className="text-foreground-muted">No tags</span>
                                        )}
                                    </div>
                                </div>
                                <div className="flex gap-4">
                                    <div>
                                        <p className="text-sm text-foreground-muted">Visibility</p>
                                        <Badge variant={initialTemplate.is_public ? 'success' : 'warning'}>
                                            {initialTemplate.is_public ? 'Public' : 'Private'}
                                        </Badge>
                                    </div>
                                    {initialTemplate.created_by && (
                                        <div>
                                            <p className="text-sm text-foreground-muted">Created by</p>
                                            <p>{initialTemplate.created_by}</p>
                                        </div>
                                    )}
                                </div>
                            </>
                        )}
                    </CardContent>
                </Card>

                {/* Build Configuration */}
                <Card variant="glass" className="mb-6">
                    <CardHeader>
                        <CardTitle>Build Configuration</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {isEditing ? (
                            <>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label className="text-sm font-medium text-foreground" htmlFor="build_pack">Build Pack *</label>
                                        <select
                                            id="build_pack"
                                            value={config.build_pack}
                                            onChange={(e) =>
                                                setConfig({ ...config, build_pack: e.target.value })
                                            }
                                            className="w-full rounded-md border border-border bg-background px-3 py-2 text-foreground"
                                        >
                                            <option value="nixpacks">Nixpacks</option>
                                            <option value="dockerfile">Dockerfile</option>
                                            <option value="dockercompose">Docker Compose</option>
                                            <option value="static">Static</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-foreground" htmlFor="ports_exposes">Exposed Ports</label>
                                        <Input
                                            id="ports_exposes"
                                            value={config.ports_exposes}
                                            onChange={(e) =>
                                                setConfig({ ...config, ports_exposes: e.target.value })
                                            }
                                        />
                                    </div>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label className="text-sm font-medium text-foreground" htmlFor="base_directory">Base Directory</label>
                                        <Input
                                            id="base_directory"
                                            value={config.base_directory}
                                            onChange={(e) =>
                                                setConfig({ ...config, base_directory: e.target.value })
                                            }
                                        />
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-foreground" htmlFor="publish_directory">Publish Directory</label>
                                        <Input
                                            id="publish_directory"
                                            value={config.publish_directory}
                                            onChange={(e) =>
                                                setConfig({ ...config, publish_directory: e.target.value })
                                            }
                                        />
                                    </div>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-foreground" htmlFor="install_command">Install Command</label>
                                    <Input
                                        id="install_command"
                                        value={config.install_command}
                                        onChange={(e) =>
                                            setConfig({ ...config, install_command: e.target.value })
                                        }
                                    />
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-foreground" htmlFor="build_command">Build Command</label>
                                    <Input
                                        id="build_command"
                                        value={config.build_command}
                                        onChange={(e) =>
                                            setConfig({ ...config, build_command: e.target.value })
                                        }
                                    />
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-foreground" htmlFor="start_command">Start Command</label>
                                    <Input
                                        id="start_command"
                                        value={config.start_command}
                                        onChange={(e) =>
                                            setConfig({ ...config, start_command: e.target.value })
                                        }
                                    />
                                </div>
                            </>
                        ) : (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <p className="text-sm text-foreground-muted">Build Pack</p>
                                    <p className="font-medium">{initialTemplate.config?.build_pack || 'nixpacks'}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-foreground-muted">Exposed Ports</p>
                                    <p className="font-medium">{initialTemplate.config?.ports_exposes || '-'}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-foreground-muted">Base Directory</p>
                                    <p className="font-medium">{initialTemplate.config?.base_directory || '/'}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-foreground-muted">Publish Directory</p>
                                    <p className="font-medium">{initialTemplate.config?.publish_directory || '-'}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-foreground-muted">Install Command</p>
                                    <code className="text-sm">{initialTemplate.config?.install_command || '-'}</code>
                                </div>
                                <div>
                                    <p className="text-sm text-foreground-muted">Build Command</p>
                                    <code className="text-sm">{initialTemplate.config?.build_command || '-'}</code>
                                </div>
                                <div className="sm:col-span-2">
                                    <p className="text-sm text-foreground-muted">Start Command</p>
                                    <code className="text-sm">{initialTemplate.config?.start_command || '-'}</code>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Environment Variables */}
                <Card variant="glass">
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Environment Variables</CardTitle>
                        {isEditing && (
                            <Button type="button" variant="secondary" size="sm" onClick={handleAddEnvVar}>
                                <Plus className="mr-1 h-4 w-4" />
                                Add Variable
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent>
                        {isEditing ? (
                            <>
                                <div className="mb-4 flex items-start gap-2 rounded-lg bg-info/10 p-3 text-sm text-info">
                                    <Info className="mt-0.5 h-4 w-4 flex-shrink-0" />
                                    <p>
                                        Use placeholders like <code className="rounded bg-info/20 px-1">{'{{VALUE}}'}</code>{' '}
                                        for values that should be set during creation.
                                    </p>
                                </div>
                                {(config.environment_variables?.length || 0) === 0 ? (
                                    <p className="text-center text-sm text-foreground-muted">
                                        No environment variables defined
                                    </p>
                                ) : (
                                    <div className="space-y-3">
                                        {config.environment_variables?.map((envVar, index) => (
                                            <div key={index} className="flex items-center gap-2">
                                                <Input
                                                    value={envVar.key}
                                                    onChange={(e) =>
                                                        handleEnvVarChange(index, 'key', e.target.value)
                                                    }
                                                    placeholder="KEY"
                                                    className="flex-1"
                                                />
                                                <span className="text-foreground-muted">=</span>
                                                <Input
                                                    value={envVar.value}
                                                    onChange={(e) =>
                                                        handleEnvVarChange(index, 'value', e.target.value)
                                                    }
                                                    placeholder="value"
                                                    className="flex-1"
                                                />
                                                <label className="flex items-center gap-1 text-xs text-foreground-muted">
                                                    <input
                                                        type="checkbox"
                                                        checked={envVar.is_secret}
                                                        onChange={(e) =>
                                                            handleEnvVarChange(index, 'is_secret', e.target.checked)
                                                        }
                                                        className="rounded border-border"
                                                    />
                                                    Secret
                                                </label>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => handleRemoveEnvVar(index)}
                                                >
                                                    <X className="h-4 w-4 text-danger" />
                                                </Button>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </>
                        ) : (
                            <>
                                {(initialTemplate.config?.environment_variables?.length || 0) === 0 ? (
                                    <p className="text-center text-sm text-foreground-muted">
                                        No environment variables defined
                                    </p>
                                ) : (
                                    <div className="space-y-2">
                                        {initialTemplate.config?.environment_variables?.map((envVar, index) => (
                                            <div
                                                key={index}
                                                className="flex items-center gap-2 rounded bg-background/50 p-2"
                                            >
                                                <code className="font-medium">{envVar.key}</code>
                                                <span className="text-foreground-muted">=</span>
                                                <code className="text-foreground-muted">
                                                    {envVar.is_secret ? '••••••••' : envVar.value}
                                                </code>
                                                {envVar.is_secret && (
                                                    <Badge variant="warning" size="sm">
                                                        Secret
                                                    </Badge>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
