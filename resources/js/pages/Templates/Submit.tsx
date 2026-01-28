import { AppLayout } from '@/components/layout';
import { Button, Input, Textarea, Checkbox } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import { Link, router } from '@inertiajs/react';
import { useState, FormEvent } from 'react';
import * as Icons from 'lucide-react';
import { cn } from '@/lib/utils';

interface EnvironmentVariable {
    key: string;
    description: string;
    defaultValue: string;
    required: boolean;
}

export default function TemplateSubmit() {
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [githubUrl, setGithubUrl] = useState('');
    const [dockerCompose, setDockerCompose] = useState('');
    const [category, setCategory] = useState('');
    const [envVars, setEnvVars] = useState<EnvironmentVariable[]>([]);
    const [termsAccepted, setTermsAccepted] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [showPreview, setShowPreview] = useState(false);
    const { addToast } = useToast();

    const addEnvVar = () => {
        setEnvVars([
            ...envVars,
            { key: '', description: '', defaultValue: '', required: false },
        ]);
    };

    const updateEnvVar = (index: number, field: keyof EnvironmentVariable, value: string | boolean) => {
        const updated = [...envVars];
        updated[index] = { ...updated[index], [field]: value };
        setEnvVars(updated);
    };

    const removeEnvVar = (index: number) => {
        setEnvVars(envVars.filter((_, i) => i !== index));
    };

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();

        if (!termsAccepted) {
            addToast('error', 'Please accept the terms and conditions');
            return;
        }

        setIsSubmitting(true);

        router.post('/templates/submit', {
            name,
            description,
            github_url: githubUrl,
            docker_compose: dockerCompose,
            category,
            environment_variables: envVars,
        } as any, {
            onSuccess: () => {
                addToast('success', 'Template submitted successfully for review');
            },
            onError: (errors) => {
                addToast('error', 'Failed to submit template');
                setIsSubmitting(false);
            },
            onFinish: () => {
                setIsSubmitting(false);
            },
        });
    };

    const isFormValid =
        name.trim() !== '' &&
        description.trim() !== '' &&
        githubUrl.trim() !== '' &&
        dockerCompose.trim() !== '' &&
        category !== '' &&
        termsAccepted;

    return (
        <AppLayout title="Submit Template" showNewProject={false}>
            <div className="mx-auto max-w-4xl">
                {/* Back link */}
                <Link
                    href="/templates"
                    className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
                >
                    <Icons.ArrowLeft className="mr-2 h-4 w-4" />
                    Back to Templates
                </Link>

                {/* Header */}
                <div className="mb-8">
                    <h1 className="mb-2 text-3xl font-bold text-foreground">Submit Custom Template</h1>
                    <p className="text-foreground-muted">
                        Share your template with the community. We'll review it and make it available to everyone.
                    </p>
                </div>

                {/* Form */}
                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Basic Information */}
                    <div className="rounded-xl border border-border/50 bg-background-secondary p-6">
                        <h2 className="mb-4 text-lg font-semibold text-foreground">Basic Information</h2>
                        <div className="space-y-4">
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-foreground">
                                    Template Name *
                                </label>
                                <Input
                                    value={name}
                                    onChange={(e) => setName(e.target.value)}
                                    placeholder="e.g., Next.js + PostgreSQL Starter"
                                    required
                                />
                            </div>

                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-foreground">
                                    Description *
                                </label>
                                <Textarea
                                    value={description}
                                    onChange={(e) => setDescription(e.target.value)}
                                    placeholder="Describe what your template does and what makes it useful..."
                                    rows={4}
                                    required
                                />
                            </div>

                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-foreground">
                                    Category *
                                </label>
                                <select
                                    value={category}
                                    onChange={(e) => setCategory(e.target.value)}
                                    className="h-10 w-full rounded-lg border border-border bg-background px-3 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-background"
                                    required
                                >
                                    <option value="">Select a category...</option>
                                    <option value="web-apps">Web Apps</option>
                                    <option value="databases">Databases</option>
                                    <option value="apis">APIs</option>
                                    <option value="full-stack">Full Stack</option>
                                    <option value="gaming">Gaming</option>
                                    <option value="cms">CMS</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {/* GitHub Repository */}
                    <div className="rounded-xl border border-border/50 bg-background-secondary p-6">
                        <h2 className="mb-4 text-lg font-semibold text-foreground">GitHub Repository</h2>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-foreground">
                                Repository URL *
                            </label>
                            <Input
                                value={githubUrl}
                                onChange={(e) => setGithubUrl(e.target.value)}
                                placeholder="https://github.com/username/repository"
                                type="url"
                                required
                            />
                            <p className="mt-1.5 text-xs text-foreground-subtle">
                                Public GitHub repository containing your template code
                            </p>
                        </div>
                    </div>

                    {/* Docker Compose */}
                    <div className="rounded-xl border border-border/50 bg-background-secondary p-6">
                        <h2 className="mb-4 text-lg font-semibold text-foreground">Docker Compose Configuration</h2>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-foreground">
                                docker-compose.yml *
                            </label>
                            <textarea
                                value={dockerCompose}
                                onChange={(e) => setDockerCompose(e.target.value)}
                                placeholder={`version: '3.8'
services:
  app:
    image: node:18-alpine
    ports:
      - "3000:3000"
    environment:
      - NODE_ENV=production`}
                                rows={12}
                                className="w-full rounded-lg border border-border bg-background p-3 font-mono text-sm text-foreground placeholder:text-foreground-subtle focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-background"
                                required
                            />
                        </div>
                    </div>

                    {/* Environment Variables */}
                    <div className="rounded-xl border border-border/50 bg-background-secondary p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="text-lg font-semibold text-foreground">Environment Variables</h2>
                            <Button
                                type="button"
                                onClick={addEnvVar}
                                variant="secondary"
                                size="sm"
                            >
                                <Icons.Plus className="mr-1.5 h-4 w-4" />
                                Add Variable
                            </Button>
                        </div>

                        {envVars.length === 0 ? (
                            <p className="text-sm text-foreground-muted">
                                No environment variables defined yet. Click "Add Variable" to add one.
                            </p>
                        ) : (
                            <div className="space-y-4">
                                {envVars.map((envVar, index) => (
                                    <div
                                        key={index}
                                        className="rounded-lg border border-border/30 bg-background/50 p-4"
                                    >
                                        <div className="mb-3 flex items-center justify-between">
                                            <span className="text-sm font-medium text-foreground">
                                                Variable {index + 1}
                                            </span>
                                            <button
                                                type="button"
                                                onClick={() => removeEnvVar(index)}
                                                className="text-foreground-subtle transition-colors hover:text-red-500"
                                            >
                                                <Icons.Trash2 className="h-4 w-4" />
                                            </button>
                                        </div>
                                        <div className="grid gap-3 sm:grid-cols-2">
                                            <div>
                                                <label className="mb-1 block text-xs font-medium text-foreground-muted">
                                                    Key
                                                </label>
                                                <Input
                                                    value={envVar.key}
                                                    onChange={(e) =>
                                                        updateEnvVar(index, 'key', e.target.value)
                                                    }
                                                    placeholder="DATABASE_URL"
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-xs font-medium text-foreground-muted">
                                                    Default Value
                                                </label>
                                                <Input
                                                    value={envVar.defaultValue}
                                                    onChange={(e) =>
                                                        updateEnvVar(index, 'defaultValue', e.target.value)
                                                    }
                                                    placeholder="(optional)"
                                                />
                                            </div>
                                            <div className="sm:col-span-2">
                                                <label className="mb-1 block text-xs font-medium text-foreground-muted">
                                                    Description
                                                </label>
                                                <Input
                                                    value={envVar.description}
                                                    onChange={(e) =>
                                                        updateEnvVar(index, 'description', e.target.value)
                                                    }
                                                    placeholder="Connection string for PostgreSQL database"
                                                />
                                            </div>
                                            <div className="flex items-center sm:col-span-2">
                                                <Checkbox
                                                    checked={envVar.required}
                                                    onCheckedChange={(checked) =>
                                                        updateEnvVar(index, 'required', checked as boolean)
                                                    }
                                                />
                                                <label className="ml-2 text-sm text-foreground">
                                                    Required
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Preview */}
                    <div className="rounded-xl border border-border/50 bg-background-secondary p-6">
                        <button
                            type="button"
                            onClick={() => setShowPreview(!showPreview)}
                            className="flex w-full items-center justify-between text-left"
                        >
                            <h2 className="text-lg font-semibold text-foreground">Preview</h2>
                            <Icons.ChevronDown
                                className={cn(
                                    'h-5 w-5 text-foreground-muted transition-transform',
                                    showPreview && 'rotate-180'
                                )}
                            />
                        </button>

                        {showPreview && (
                            <div className="mt-4 rounded-lg border border-border/30 bg-background p-4">
                                <h3 className="mb-2 text-xl font-semibold text-foreground">{name || 'Template Name'}</h3>
                                <p className="mb-3 text-sm text-foreground-muted">
                                    {description || 'Enter a description above to see how your template will look to others'}
                                </p>
                                {category && (
                                    <div className="mb-3">
                                        <span className="inline-block rounded-md bg-primary/20 px-2.5 py-1 text-xs font-medium text-primary">
                                            {category}
                                        </span>
                                    </div>
                                )}
                                {envVars.length > 0 && (
                                    <div>
                                        <p className="mb-2 text-xs font-medium text-foreground-muted">
                                            Required Environment Variables:
                                        </p>
                                        <div className="space-y-1">
                                            {envVars.filter(v => v.required).map((v, i) => (
                                                <div key={i} className="text-xs text-foreground-muted">
                                                    • {v.key || 'ENV_VAR'}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Confirmation */}
                    <div className="rounded-xl border border-border/50 bg-background-secondary p-6">
                        <div className="flex items-start gap-3">
                            <Checkbox
                                checked={termsAccepted}
                                onCheckedChange={(checked) => setTermsAccepted(checked as boolean)}
                            />
                            <label className="text-sm text-foreground">
                                I confirm that this template is my own work or I have permission to share it.
                            </label>
                        </div>
                    </div>

                    {/* Submit */}
                    <div className="flex gap-3">
                        <Link href="/templates">
                            <Button type="button" variant="secondary">
                                Cancel
                            </Button>
                        </Link>
                        <Button
                            type="submit"
                            disabled={!isFormValid || isSubmitting}
                            className="flex-1"
                        >
                            {isSubmitting ? (
                                <>
                                    <Icons.Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Submitting...
                                </>
                            ) : (
                                <>
                                    <Icons.Send className="mr-2 h-4 w-4" />
                                    Submit Template
                                </>
                            )}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
