import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import { ArrowLeft, Save, Plus, X, Info } from 'lucide-react';

interface Props {
    categories: Record<string, string>;
}

interface TemplateConfig {
    build_pack: string;
    ports_exposes: string;
    install_command: string;
    build_command: string;
    start_command: string;
    base_directory: string;
    publish_directory: string;
    environment_variables: Array<{ key: string; value: string; is_secret: boolean }>;
}

export default function AdminTemplatesCreate({ categories }: Props) {
    const [name, setName] = React.useState('');
    const [description, setDescription] = React.useState('');
    const [category, setCategory] = React.useState('general');
    const [icon, setIcon] = React.useState('');
    const [isOfficial, setIsOfficial] = React.useState(false);
    const [isPublic, setIsPublic] = React.useState(true);
    const [tags, setTags] = React.useState<string[]>([]);
    const [tagInput, setTagInput] = React.useState('');
    const [config, setConfig] = React.useState<TemplateConfig>({
        build_pack: 'nixpacks',
        ports_exposes: '3000',
        install_command: '',
        build_command: '',
        start_command: '',
        base_directory: '/',
        publish_directory: '',
        environment_variables: [],
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
                ...config.environment_variables,
                { key: '', value: '', is_secret: false },
            ],
        });
    };

    const handleRemoveEnvVar = (index: number) => {
        const newEnvVars = [...config.environment_variables];
        newEnvVars.splice(index, 1);
        setConfig({ ...config, environment_variables: newEnvVars });
    };

    const handleEnvVarChange = (index: number, field: string, value: string | boolean) => {
        const newEnvVars = [...config.environment_variables];
        newEnvVars[index] = { ...newEnvVars[index], [field]: value };
        setConfig({ ...config, environment_variables: newEnvVars });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);
        setErrors({});

        router.post(
            '/admin/templates',
            {
                name,
                description,
                category,
                icon,
                is_official: isOfficial,
                is_public: isPublic,
                tags,
                config: JSON.stringify(config),
            },
            {
                onError: (errs) => {
                    setErrors(errs);
                    setSaving(false);
                },
                onFinish: () => setSaving(false),
            }
        );
    };

    return (
        <AdminLayout
            title="Create Template"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Templates', href: '/admin/templates' },
                { label: 'Create' },
            ]}
        >
            <div className="mx-auto max-w-4xl">
                {/* Header */}
                <div className="mb-8 flex items-center gap-4">
                    <Button variant="ghost" size="sm" onClick={() => window.history.back()}>
                        <ArrowLeft className="h-4 w-4" />
                    </Button>
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">Create Template</h1>
                        <p className="mt-1 text-sm text-foreground-muted">
                            Create a new application template for quick deployments
                        </p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Basic Information */}
                    <Card variant="glass">
                        <CardHeader>
                            <CardTitle>Basic Information</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="text-sm font-medium text-foreground" htmlFor="name">Template Name *</label>
                                    <Input
                                        id="name"
                                        value={name}
                                        onChange={(e) => setName(e.target.value)}
                                        placeholder="e.g., Node.js Express API"
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
                                        required
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
                                    placeholder="Describe what this template is for..."
                                    rows={3}
                                    className="w-full rounded-md border border-border bg-background px-3 py-2 text-foreground"
                                />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="text-sm font-medium text-foreground" htmlFor="icon">Icon (emoji or letter)</label>
                                    <Input
                                        id="icon"
                                        value={icon}
                                        onChange={(e) => setIcon(e.target.value)}
                                        placeholder="e.g., N or emoji"
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
                                    <span className="text-sm text-foreground">Public (visible to all)</span>
                                </label>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Build Configuration */}
                    <Card variant="glass">
                        <CardHeader>
                            <CardTitle>Build Configuration</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
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
                                        required
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
                                        placeholder="e.g., 3000,8080"
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
                                        placeholder="/"
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
                                        placeholder="e.g., dist, build, public"
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
                                    placeholder="e.g., npm install"
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
                                    placeholder="e.g., npm run build"
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
                                    placeholder="e.g., npm start"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Environment Variables */}
                    <Card variant="glass">
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle>Environment Variables</CardTitle>
                            <Button type="button" variant="secondary" size="sm" onClick={handleAddEnvVar}>
                                <Plus className="mr-1 h-4 w-4" />
                                Add Variable
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <div className="mb-4 flex items-start gap-2 rounded-lg bg-info/10 p-3 text-sm text-info">
                                <Info className="mt-0.5 h-4 w-4 flex-shrink-0" />
                                <p>
                                    Environment variables defined here will be added when creating
                                    applications from this template. Use placeholders like{' '}
                                    <code className="rounded bg-info/20 px-1">{'{{VALUE}}'}</code> for
                                    values that should be set during creation.
                                </p>
                            </div>

                            {config.environment_variables.length === 0 ? (
                                <p className="text-center text-sm text-foreground-muted">
                                    No environment variables defined
                                </p>
                            ) : (
                                <div className="space-y-3">
                                    {config.environment_variables.map((envVar, index) => (
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
                                                        handleEnvVarChange(
                                                            index,
                                                            'is_secret',
                                                            e.target.checked
                                                        )
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
                        </CardContent>
                    </Card>

                    {/* Actions */}
                    <div className="flex justify-end gap-3">
                        <Button type="button" variant="secondary" onClick={() => window.history.back()}>
                            Cancel
                        </Button>
                        <Button type="submit" variant="primary" disabled={saving}>
                            <Save className="mr-2 h-4 w-4" />
                            {saving ? 'Creating...' : 'Create Template'}
                        </Button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
