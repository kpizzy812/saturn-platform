import { useState, useMemo } from 'react';
import { router, Link } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Input, Textarea } from '@/components/ui';
import { ArrowLeft, Check, Container, Search, Box, Sparkles, FileCode, ArrowRight } from 'lucide-react';
import { validateDockerCompose } from '@/lib/validation';

interface Template {
    id: string;
    name: string;
    description: string;
    logo?: string | null;
    category: string;
    tags: string[];
    deployCount: number;
    featured?: boolean;
}

interface Props {
    templates?: Template[];
}

type CreateMode = 'select' | 'template' | 'custom';
type Step = 1 | 2;

export default function ServiceCreate({ templates = [] }: Props) {
    const [mode, setMode] = useState<CreateMode>('select');
    const [step, setStep] = useState<Step>(1);
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [dockerCompose, setDockerCompose] = useState('');
    const [dockerComposeError, setDockerComposeError] = useState<string>();
    const [searchQuery, setSearchQuery] = useState('');

    const handleDockerComposeChange = (value: string) => {
        setDockerCompose(value);
        if (value.trim()) {
            const { valid, error } = validateDockerCompose(value);
            setDockerComposeError(valid ? undefined : error);
        } else {
            setDockerComposeError(undefined);
        }
    };

    const handleSubmit = () => {
        router.post('/services', {
            name,
            description,
            docker_compose_raw: dockerCompose,
        });
    };

    const filteredTemplates = useMemo(() => {
        if (!searchQuery.trim()) return templates.slice(0, 12);
        return templates.filter((template) =>
            template.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            template.description.toLowerCase().includes(searchQuery.toLowerCase()) ||
            template.tags.some(tag => tag.toLowerCase().includes(searchQuery.toLowerCase()))
        ).slice(0, 12);
    }, [templates, searchQuery]);

    const featuredTemplates = useMemo(() => {
        return templates.filter(t => t.featured).slice(0, 6);
    }, [templates]);

    const isStepOneValid = name && dockerCompose && !dockerComposeError;

    // Mode selection screen
    if (mode === 'select') {
        return (
            <AppLayout title="Create Service" showNewProject={false}>
                <div className="flex min-h-full items-start justify-center py-12">
                    <div className="w-full max-w-3xl px-4">
                        <Link
                            href="/services"
                            className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
                        >
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Services
                        </Link>

                        <div className="mb-8 text-center">
                            <h1 className="text-2xl font-semibold text-foreground">Create a new service</h1>
                            <p className="mt-2 text-foreground-muted">
                                Choose how you want to create your service
                            </p>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            {/* Template Option */}
                            <button
                                onClick={() => setMode('template')}
                                className="group relative flex flex-col rounded-xl border border-border/50 bg-gradient-to-br from-background-secondary to-background-secondary/50 p-6 text-left transition-all duration-300 hover:-translate-y-1 hover:border-primary/50 hover:shadow-xl hover:shadow-primary/10"
                            >
                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-primary/80 text-white shadow-lg">
                                    <Sparkles className="h-6 w-6" />
                                </div>
                                <h3 className="mb-2 text-lg font-semibold text-foreground">Choose from Templates</h3>
                                <p className="mb-4 text-sm text-foreground-muted">
                                    Deploy pre-configured services like n8n, WordPress, PostgreSQL, Redis, and {templates.length - 4}+ more
                                </p>
                                <div className="mt-auto flex items-center text-sm font-medium text-primary">
                                    Browse {templates.length} templates
                                    <ArrowRight className="ml-2 h-4 w-4 transition-transform group-hover:translate-x-1" />
                                </div>
                            </button>

                            {/* Custom Docker Compose Option */}
                            <button
                                onClick={() => setMode('custom')}
                                className="group relative flex flex-col rounded-xl border border-border/50 bg-gradient-to-br from-background-secondary to-background-secondary/50 p-6 text-left transition-all duration-300 hover:-translate-y-1 hover:border-border hover:shadow-xl hover:shadow-black/20"
                            >
                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-purple-500 to-purple-600 text-white shadow-lg">
                                    <FileCode className="h-6 w-6" />
                                </div>
                                <h3 className="mb-2 text-lg font-semibold text-foreground">Custom Docker Compose</h3>
                                <p className="mb-4 text-sm text-foreground-muted">
                                    Paste your own docker-compose.yml to deploy any multi-container application
                                </p>
                                <div className="mt-auto flex items-center text-sm font-medium text-foreground-muted group-hover:text-foreground">
                                    Create custom service
                                    <ArrowRight className="ml-2 h-4 w-4 transition-transform group-hover:translate-x-1" />
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </AppLayout>
        );
    }

    // Template selection screen
    if (mode === 'template') {
        return (
            <AppLayout title="Create Service" showNewProject={false}>
                <div className="mx-auto max-w-6xl py-8 px-4">
                    <button
                        onClick={() => setMode('select')}
                        className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
                    >
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Back to options
                    </button>

                    <div className="mb-8">
                        <h1 className="mb-2 text-2xl font-semibold text-foreground">Choose a Template</h1>
                        <p className="text-foreground-muted">
                            Select a pre-configured service to deploy
                        </p>
                    </div>

                    {/* Search */}
                    <div className="relative mb-6">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-subtle" />
                        <input
                            type="text"
                            placeholder="Search templates..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="h-11 w-full rounded-lg border border-border bg-background-secondary pl-10 pr-4 text-sm text-foreground placeholder:text-foreground-subtle focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-background"
                        />
                    </div>

                    {/* Featured Templates */}
                    {!searchQuery && featuredTemplates.length > 0 && (
                        <div className="mb-8">
                            <h2 className="mb-4 text-lg font-medium text-foreground">Popular Templates</h2>
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {featuredTemplates.map((template) => (
                                    <TemplateCard key={template.id} template={template} />
                                ))}
                            </div>
                        </div>
                    )}

                    {/* All/Filtered Templates */}
                    <div>
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="text-lg font-medium text-foreground">
                                {searchQuery ? `Results for "${searchQuery}"` : 'All Templates'}
                            </h2>
                            <Link
                                href="/templates"
                                className="text-sm text-primary hover:underline"
                            >
                                View all {templates.length} templates
                            </Link>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            {filteredTemplates.map((template) => (
                                <TemplateCard key={template.id} template={template} />
                            ))}
                        </div>

                        {filteredTemplates.length === 0 && (
                            <div className="rounded-xl border border-border/50 bg-background-secondary p-8 text-center">
                                <Search className="mx-auto mb-3 h-10 w-10 text-foreground-subtle" />
                                <p className="text-foreground-muted">No templates found matching "{searchQuery}"</p>
                            </div>
                        )}
                    </div>
                </div>
            </AppLayout>
        );
    }

    // Custom Docker Compose form
    return (
        <AppLayout title="Create Service" showNewProject={false}>
            <div className="flex min-h-full items-start justify-center py-12">
                <div className="w-full max-w-2xl px-4">
                    <button
                        onClick={() => setMode('select')}
                        className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
                    >
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Back to options
                    </button>

                    <div className="mb-8 text-center">
                        <h1 className="text-2xl font-semibold text-foreground">Custom Docker Compose</h1>
                        <p className="mt-2 text-foreground-muted">
                            Deploy multi-container applications with Docker Compose
                        </p>
                    </div>

                    {/* Progress Indicator */}
                    <div className="mb-8 flex items-center justify-center gap-2">
                        <StepIndicator step={1} currentStep={step} label="Configure" />
                        <div className="h-px w-12 bg-border" />
                        <StepIndicator step={2} currentStep={step} label="Review" />
                    </div>

                    {/* Step Content */}
                    <div className="space-y-3">
                        {step === 1 && (
                            <Card>
                                <CardContent className="p-6">
                                    <div className="mb-6 flex items-center gap-3">
                                        <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-purple-500 to-purple-600 text-white shadow-lg">
                                            <Container className="h-5 w-5" />
                                        </div>
                                        <div>
                                            <h3 className="font-medium text-foreground">Docker Compose Service</h3>
                                            <p className="text-sm text-foreground-muted">Deploy multi-container applications</p>
                                        </div>
                                    </div>

                                    <div className="space-y-4">
                                        <Input
                                            label="Service Name"
                                            placeholder="my-service"
                                            value={name}
                                            onChange={(e) => setName(e.target.value)}
                                            hint="A unique name for your service"
                                        />

                                        <Input
                                            label="Description (Optional)"
                                            placeholder="Production multi-container application"
                                            value={description}
                                            onChange={(e) => setDescription(e.target.value)}
                                        />

                                        <Textarea
                                            label="Docker Compose Configuration"
                                            placeholder={`version: '3.8'
services:
  web:
    image: nginx:alpine
    ports:
      - "80:80"
  app:
    image: node:18-alpine
    command: npm start`}
                                            value={dockerCompose}
                                            onChange={(e) => handleDockerComposeChange(e.target.value)}
                                            rows={12}
                                            error={dockerComposeError}
                                            hint={!dockerComposeError ? "Paste your docker-compose.yml content" : undefined}
                                            className="font-mono text-sm"
                                        />
                                    </div>

                                    <div className="mt-6 flex gap-3">
                                        <Button
                                            onClick={() => setStep(2)}
                                            disabled={!isStepOneValid}
                                            className="flex-1"
                                        >
                                            Continue
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {step === 2 && (
                            <Card>
                                <CardContent className="p-6">
                                    <h3 className="mb-4 text-lg font-medium text-foreground">Review Configuration</h3>

                                    <div className="space-y-4">
                                        <div className="flex items-center gap-3 rounded-lg border border-border bg-background-secondary p-4">
                                            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-purple-500 to-purple-600 text-white shadow-lg">
                                                <Container className="h-5 w-5" />
                                            </div>
                                            <div className="flex-1">
                                                <p className="font-medium text-foreground">{name}</p>
                                                <p className="text-sm text-foreground-muted">
                                                    Docker Compose Service
                                                </p>
                                            </div>
                                        </div>

                                        {description && (
                                            <div className="rounded-lg border border-border bg-background-secondary p-4">
                                                <label className="mb-1 block text-sm font-medium text-foreground-muted">
                                                    Description
                                                </label>
                                                <p className="text-sm text-foreground">{description}</p>
                                            </div>
                                        )}

                                        <div className="rounded-lg border border-border bg-background-secondary p-4">
                                            <label className="mb-2 block text-sm font-medium text-foreground-muted">
                                                Docker Compose Configuration
                                            </label>
                                            <pre className="max-h-64 overflow-auto rounded bg-background-tertiary p-3 font-mono text-xs text-foreground">
                                                {dockerCompose}
                                            </pre>
                                        </div>

                                        <div className="rounded-lg border border-border bg-background-secondary p-4">
                                            <h4 className="mb-2 font-medium text-foreground">What happens next?</h4>
                                            <ul className="space-y-2 text-sm text-foreground-muted">
                                                <li className="flex items-start gap-2">
                                                    <Check className="mt-0.5 h-4 w-4 text-green-500" />
                                                    <span>Service will be created on your server</span>
                                                </li>
                                                <li className="flex items-start gap-2">
                                                    <Check className="mt-0.5 h-4 w-4 text-green-500" />
                                                    <span>Docker containers will be deployed</span>
                                                </li>
                                                <li className="flex items-start gap-2">
                                                    <Check className="mt-0.5 h-4 w-4 text-green-500" />
                                                    <span>You'll be redirected to the service dashboard</span>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div className="mt-6 flex gap-3">
                                        <Button variant="secondary" onClick={() => setStep(1)}>
                                            Back
                                        </Button>
                                        <Button onClick={handleSubmit} className="flex-1">
                                            Create Service
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function TemplateCard({ template }: { template: Template }) {
    const logoUrl = template.logo ? `/${template.logo}` : null;

    return (
        <Link
            href={`/templates/${template.id}/deploy`}
            className="group flex items-center gap-3 rounded-lg border border-border/50 bg-background-secondary p-4 transition-all hover:border-border hover:bg-background-tertiary"
        >
            <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-background-tertiary">
                {logoUrl ? (
                    <img src={logoUrl} alt={template.name} className="h-6 w-6 object-contain" />
                ) : (
                    <Box className="h-5 w-5 text-foreground-muted" />
                )}
            </div>
            <div className="min-w-0 flex-1">
                <h3 className="truncate font-medium text-foreground">{template.name}</h3>
                <p className="truncate text-xs text-foreground-muted">{template.description}</p>
            </div>
        </Link>
    );
}

function StepIndicator({ step, currentStep, label }: { step: Step; currentStep: Step; label: string }) {
    const isActive = step === currentStep;
    const isCompleted = step < currentStep;

    return (
        <div className="flex flex-col items-center gap-1">
            <div
                className={`flex h-8 w-8 items-center justify-center rounded-full border-2 transition-colors ${
                    isActive
                        ? 'border-primary bg-primary text-white'
                        : isCompleted
                          ? 'border-primary bg-primary text-white'
                          : 'border-border bg-background text-foreground-muted'
                }`}
            >
                {isCompleted ? <Check className="h-4 w-4" /> : <span className="text-sm">{step}</span>}
            </div>
            <span
                className={`text-xs ${
                    isActive ? 'text-foreground' : isCompleted ? 'text-foreground-muted' : 'text-foreground-subtle'
                }`}
            >
                {label}
            </span>
        </div>
    );
}
