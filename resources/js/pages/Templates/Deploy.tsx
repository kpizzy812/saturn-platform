import { AppLayout } from '@/components/layout';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Link, useForm } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import {
    ArrowLeft,
    ArrowRight,
    CheckCircle2,
    Zap,
    Server as ServerIcon,
    FolderOpen,
    Loader2,
    Box,
    AlertCircle,
    Plus,
} from 'lucide-react';

interface Template {
    id: string;
    name: string;
    description: string;
    logo?: string | null;
    category: string;
    tags: string[];
    deployCount: number;
    featured?: boolean;
    documentation?: string | null;
    port?: string | null;
}

interface Environment {
    id: number;
    uuid: string;
    name: string;
}

interface Project {
    id: number;
    uuid: string;
    name: string;
    environments: Environment[];
}

interface Server {
    id: number;
    uuid: string;
    name: string;
    ip: string;
}

interface Props {
    template?: Template;
    projects: Project[];
    localhost?: Server | null;
    userServers: Server[];
    needsProject: boolean;
}

export default function TemplateDeploy({ template, projects, localhost, userServers, needsProject }: Props) {
    const [currentStep, setCurrentStep] = useState(1);

    const { data, setData, post, processing, errors } = useForm({
        project_uuid: projects[0]?.uuid || '',
        environment_uuid: projects[0]?.environments[0]?.uuid || '',
        server_uuid: localhost?.uuid || userServers[0]?.uuid || '',
        instant_deploy: true,
    });

    // Combine servers
    const allServers = useMemo(() => {
        const servers: Server[] = [];
        if (localhost) {
            servers.push({ ...localhost, name: localhost.name || 'Platform Server (localhost)' });
        }
        servers.push(...userServers);
        return servers;
    }, [localhost, userServers]);

    // Get selected project's environments
    const selectedProject = projects.find(p => p.uuid === data.project_uuid);
    const environments = selectedProject?.environments || [];

    // Loading state
    if (!template) {
        return (
            <AppLayout title="Loading..." showNewProject={false}>
                <div className="mx-auto max-w-3xl">
                    <div className="flex items-center justify-center py-12">
                        <div className="text-center">
                            <div className="mb-4 h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent mx-auto" />
                            <p className="text-foreground-muted">Loading template...</p>
                        </div>
                    </div>
                </div>
            </AppLayout>
        );
    }

    const logoUrl = template.logo ? `/${template.logo}` : null;
    const totalSteps = 3;

    const handleNext = () => {
        if (currentStep < totalSteps) {
            setCurrentStep(currentStep + 1);
        }
    };

    const handleBack = () => {
        if (currentStep > 1) {
            setCurrentStep(currentStep - 1);
        }
    };

    const handleDeploy = () => {
        post(`/templates/${template.id}/deploy`);
    };

    const canProceed = () => {
        switch (currentStep) {
            case 1:
                return data.project_uuid && data.environment_uuid;
            case 2:
                return data.server_uuid;
            default:
                return true;
        }
    };

    const handleProjectChange = (projectUuid: string) => {
        const project = projects.find(p => p.uuid === projectUuid);
        setData({
            ...data,
            project_uuid: projectUuid,
            environment_uuid: project?.environments[0]?.uuid || '',
        });
    };

    // If user needs to create a project first
    if (needsProject) {
        return (
            <AppLayout title={`Deploy ${template.name}`} showNewProject={false}>
                <div className="mx-auto max-w-3xl">
                    <Link
                        href={`/templates/${template.id}`}
                        className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
                    >
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Back to Template
                    </Link>

                    <div className="rounded-xl border border-border/50 bg-gradient-to-br from-background-secondary to-background-secondary/50 p-8 text-center">
                        <AlertCircle className="mx-auto mb-4 h-12 w-12 text-warning" />
                        <h2 className="mb-2 text-xl font-semibold text-foreground">Create a Project First</h2>
                        <p className="mb-6 text-foreground-muted">
                            You need to create a project before deploying templates. Projects help organize your services and applications.
                        </p>
                        <Link href="/projects/create">
                            <Button size="lg">
                                <Plus className="mr-2 h-5 w-5" />
                                Create Project
                            </Button>
                        </Link>
                    </div>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout title={`Deploy ${template.name}`} showNewProject={false}>
            <div className="mx-auto max-w-3xl">
                {/* Back link */}
                <Link
                    href={`/templates/${template.id}`}
                    className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="mr-2 h-4 w-4" />
                    Back to Template
                </Link>

                {/* Header */}
                <div className="mb-8 flex items-center gap-4">
                    <div className="flex h-14 w-14 items-center justify-center rounded-xl bg-background-tertiary shadow-lg">
                        {logoUrl ? (
                            <img src={logoUrl} alt={template.name} className="h-9 w-9 object-contain" />
                        ) : (
                            <Box className="h-8 w-8 text-foreground-muted" />
                        )}
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Deploy {template.name}</h1>
                        <p className="text-foreground-muted">Configure and deploy your service</p>
                    </div>
                </div>

                {/* Progress Indicator */}
                <div className="mb-8">
                    <div className="flex items-center justify-between">
                        {[1, 2, 3].map((step) => (
                            <div key={step} className="flex flex-1 items-center">
                                <div className="flex flex-col items-center">
                                    <div
                                        className={`flex h-10 w-10 items-center justify-center rounded-full border-2 transition-all ${
                                            step < currentStep
                                                ? 'border-primary bg-primary text-white'
                                                : step === currentStep
                                                ? 'border-primary bg-background text-primary'
                                                : 'border-border bg-background-secondary text-foreground-subtle'
                                        }`}
                                    >
                                        {step < currentStep ? (
                                            <CheckCircle2 className="h-5 w-5" />
                                        ) : (
                                            <span className="text-sm font-semibold">{step}</span>
                                        )}
                                    </div>
                                    <span className="mt-2 text-xs text-foreground-muted">
                                        {step === 1 && 'Project'}
                                        {step === 2 && 'Server'}
                                        {step === 3 && 'Review'}
                                    </span>
                                </div>
                                {step < 3 && (
                                    <div
                                        className={`mx-2 h-0.5 flex-1 ${
                                            step < currentStep ? 'bg-primary' : 'bg-border'
                                        }`}
                                    />
                                )}
                            </div>
                        ))}
                    </div>
                </div>

                {/* Error Display */}
                {Object.keys(errors).length > 0 && (
                    <div className="mb-6 rounded-lg border border-danger/50 bg-danger/10 p-4">
                        <div className="flex items-start gap-3">
                            <AlertCircle className="h-5 w-5 text-danger flex-shrink-0 mt-0.5" />
                            <div>
                                <p className="font-semibold text-danger">Deployment Error</p>
                                {Object.values(errors).map((error, index) => (
                                    <p key={index} className="text-sm text-danger/80">{error}</p>
                                ))}
                            </div>
                        </div>
                    </div>
                )}

                {/* Step Content */}
                <div className="mb-8 rounded-xl border border-border/50 bg-gradient-to-br from-background-secondary to-background-secondary/50 p-8">
                    {/* Step 1: Select Project & Environment */}
                    {currentStep === 1 && (
                        <div>
                            <div className="mb-6">
                                <FolderOpen className="mb-3 h-8 w-8 text-primary" />
                                <h2 className="mb-2 text-xl font-semibold text-foreground">Select Project</h2>
                                <p className="text-foreground-muted">
                                    Choose where to deploy this service.
                                </p>
                            </div>

                            {/* Project Selection */}
                            <div className="mb-6">
                                <label className="mb-2 block text-sm font-medium text-foreground">Project</label>
                                <div className="space-y-2">
                                    {projects.map((project) => (
                                        <button
                                            key={project.uuid}
                                            onClick={() => handleProjectChange(project.uuid)}
                                            className={`w-full rounded-lg border p-4 text-left transition-all ${
                                                data.project_uuid === project.uuid
                                                    ? 'border-primary bg-primary/10 ring-2 ring-primary ring-offset-2 ring-offset-background'
                                                    : 'border-border bg-background-secondary hover:border-border/80'
                                            }`}
                                        >
                                            <h3 className="font-semibold text-foreground">{project.name}</h3>
                                            <p className="text-sm text-foreground-muted">
                                                {project.environments.length} environment{project.environments.length !== 1 ? 's' : ''}
                                            </p>
                                        </button>
                                    ))}
                                </div>
                            </div>

                            {/* Environment Selection */}
                            {environments.length > 0 && (
                                <div>
                                    <label className="mb-2 block text-sm font-medium text-foreground">Environment</label>
                                    <div className="space-y-2">
                                        {environments.map((env) => (
                                            <button
                                                key={env.uuid}
                                                onClick={() => setData('environment_uuid', env.uuid)}
                                                className={`w-full rounded-lg border p-3 text-left transition-all ${
                                                    data.environment_uuid === env.uuid
                                                        ? 'border-primary bg-primary/10 ring-2 ring-primary ring-offset-2 ring-offset-background'
                                                        : 'border-border bg-background hover:border-border/80'
                                                }`}
                                            >
                                                <span className="font-medium text-foreground">{env.name}</span>
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Step 2: Select Server */}
                    {currentStep === 2 && (
                        <div>
                            <div className="mb-6">
                                <ServerIcon className="mb-3 h-8 w-8 text-primary" />
                                <h2 className="mb-2 text-xl font-semibold text-foreground">Select Server</h2>
                                <p className="text-foreground-muted">
                                    Choose where to run this service.
                                </p>
                            </div>

                            <div className="space-y-3">
                                {allServers.map((server) => (
                                    <button
                                        key={server.uuid}
                                        onClick={() => setData('server_uuid', server.uuid)}
                                        className={`w-full rounded-lg border p-4 text-left transition-all ${
                                            data.server_uuid === server.uuid
                                                ? 'border-primary bg-primary/10 ring-2 ring-primary ring-offset-2 ring-offset-background'
                                                : 'border-border bg-background-secondary hover:border-border/80'
                                        }`}
                                    >
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <h3 className="font-semibold text-foreground">{server.name}</h3>
                                                <p className="text-sm text-foreground-muted">
                                                    {server.ip}
                                                </p>
                                            </div>
                                            <Badge variant="success">Online</Badge>
                                        </div>
                                    </button>
                                ))}
                            </div>

                            {allServers.length === 0 && (
                                <div className="rounded-lg border border-warning/50 bg-warning/10 p-4 text-center">
                                    <AlertCircle className="mx-auto mb-2 h-8 w-8 text-warning" />
                                    <p className="text-foreground-muted">No servers available. Please add a server first.</p>
                                </div>
                            )}

                            <div className="mt-6">
                                <Checkbox
                                    id="instant-deploy"
                                    label="Start service immediately after creation"
                                    checked={data.instant_deploy}
                                    onChange={(e) => setData('instant_deploy', e.target.checked)}
                                />
                                <p className="ml-6 mt-1 text-xs text-foreground-muted">
                                    The service will be started automatically after deployment
                                </p>
                            </div>
                        </div>
                    )}

                    {/* Step 3: Review */}
                    {currentStep === 3 && (
                        <div>
                            <div className="mb-6">
                                <CheckCircle2 className="mb-3 h-8 w-8 text-primary" />
                                <h2 className="mb-2 text-xl font-semibold text-foreground">Review & Deploy</h2>
                                <p className="text-foreground-muted">
                                    Review your configuration before deploying.
                                </p>
                            </div>

                            <div className="space-y-4">
                                <div className="rounded-lg border border-border/50 bg-background p-4">
                                    <h3 className="mb-2 text-sm font-semibold text-foreground-muted">Template</h3>
                                    <div className="flex items-center gap-3">
                                        {logoUrl && <img src={logoUrl} alt={template.name} className="h-6 w-6 object-contain" />}
                                        <span className="text-foreground">{template.name}</span>
                                    </div>
                                </div>

                                <div className="rounded-lg border border-border/50 bg-background p-4">
                                    <h3 className="mb-2 text-sm font-semibold text-foreground-muted">Project</h3>
                                    <p className="text-foreground">{selectedProject?.name}</p>
                                </div>

                                <div className="rounded-lg border border-border/50 bg-background p-4">
                                    <h3 className="mb-2 text-sm font-semibold text-foreground-muted">Environment</h3>
                                    <p className="text-foreground">
                                        {environments.find(e => e.uuid === data.environment_uuid)?.name}
                                    </p>
                                </div>

                                <div className="rounded-lg border border-border/50 bg-background p-4">
                                    <h3 className="mb-2 text-sm font-semibold text-foreground-muted">Server</h3>
                                    <p className="text-foreground">
                                        {allServers.find(s => s.uuid === data.server_uuid)?.name}
                                    </p>
                                </div>

                                <div className="rounded-lg border border-border/50 bg-background p-4">
                                    <h3 className="mb-2 text-sm font-semibold text-foreground-muted">Options</h3>
                                    <p className="text-foreground">
                                        {data.instant_deploy
                                            ? 'Service will start immediately'
                                            : 'Service will be created but not started'}
                                    </p>
                                </div>
                            </div>
                        </div>
                    )}
                </div>

                {/* Navigation */}
                <div className="flex items-center justify-between">
                    <Button
                        variant="ghost"
                        onClick={handleBack}
                        disabled={currentStep === 1 || processing}
                    >
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Back
                    </Button>

                    {currentStep < totalSteps ? (
                        <Button onClick={handleNext} disabled={!canProceed()}>
                            Next
                            <ArrowRight className="ml-2 h-4 w-4" />
                        </Button>
                    ) : (
                        <Button onClick={handleDeploy} disabled={processing}>
                            {processing ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Deploying...
                                </>
                            ) : (
                                <>
                                    <Zap className="mr-2 h-4 w-4" />
                                    Deploy Now
                                </>
                            )}
                        </Button>
                    )}
                </div>

                {/* Deploying State */}
                {processing && (
                    <div className="mt-6 rounded-lg border border-primary/50 bg-primary/10 p-4">
                        <div className="flex items-center gap-3">
                            <Loader2 className="h-5 w-5 animate-spin text-primary" />
                            <div>
                                <p className="font-semibold text-foreground">Deploying {template.name}...</p>
                                <p className="text-sm text-foreground-muted">
                                    This may take a moment. You'll be redirected when complete.
                                </p>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
