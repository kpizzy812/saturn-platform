import { AppLayout } from '@/components/layout';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Checkbox } from '@/components/ui/Checkbox';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    ArrowLeft,
    ArrowRight,
    CheckCircle2,
    Zap,
    Settings,
    Server,
    Eye,
    EyeOff,
    Loader2,
} from 'lucide-react';
import * as Icons from 'lucide-react';

interface DeployFormData {
    projectName: string;
    envVars: Record<string, string>;
    serverId: string;
    autoDeploy: boolean;
}

// Mock data - would come from props in production
const template = {
    id: 'nextjs-starter',
    name: 'Next.js Starter',
    icon: <Icons.Zap className="h-6 w-6" />,
    iconBg: 'bg-gradient-to-br from-slate-800 to-slate-900',
    iconColor: 'text-white',
    envVars: [
        {
            name: 'DATABASE_URL',
            description: 'PostgreSQL connection string',
            required: true,
            default: 'postgresql://user:password@postgres:5432/mydb',
            sensitive: true,
        },
        {
            name: 'REDIS_URL',
            description: 'Redis connection string',
            required: true,
            default: 'redis://redis:6379',
            sensitive: false,
        },
        {
            name: 'NEXT_PUBLIC_API_URL',
            description: 'Public API URL',
            required: false,
            default: '',
            sensitive: false,
        },
        {
            name: 'SECRET_KEY',
            description: 'Secret key for sessions',
            required: true,
            default: '',
            sensitive: true,
        },
    ],
};

const servers = [
    { id: '1', name: 'Production Server', region: 'us-east-1', status: 'online' },
    { id: '2', name: 'Staging Server', region: 'us-west-2', status: 'online' },
    { id: '3', name: 'Development Server', region: 'eu-west-1', status: 'online' },
];

export default function TemplateDeploy() {
    const [currentStep, setCurrentStep] = useState(1);
    const [isDeploying, setIsDeploying] = useState(false);
    const [showSecrets, setShowSecrets] = useState<Record<string, boolean>>({});
    const [formData, setFormData] = useState<DeployFormData>({
        projectName: '',
        envVars: template.envVars.reduce((acc, envVar) => {
            acc[envVar.name] = envVar.default || '';
            return acc;
        }, {} as Record<string, string>),
        serverId: servers[0].id,
        autoDeploy: true,
    });

    const totalSteps = 4;

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
        setIsDeploying(true);
        // Simulate deployment
        setTimeout(() => {
            // In production, this would redirect to the actual project
            router.visit('/dashboard');
        }, 3000);
    };

    const canProceed = () => {
        switch (currentStep) {
            case 1:
                return formData.projectName.trim().length > 0;
            case 2:
                return template.envVars
                    .filter(v => v.required)
                    .every(v => formData.envVars[v.name]?.trim().length > 0);
            case 3:
                return formData.serverId.length > 0;
            default:
                return true;
        }
    };

    const toggleSecretVisibility = (varName: string) => {
        setShowSecrets(prev => ({ ...prev, [varName]: !prev[varName] }));
    };

    return (
        <AppLayout title="Deploy Template" showNewProject={false}>
            <div className="mx-auto max-w-3xl px-4 py-8">
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
                    <div className={`flex h-14 w-14 items-center justify-center rounded-xl ${template.iconBg} ${template.iconColor} shadow-lg`}>
                        {template.icon}
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Deploy {template.name}</h1>
                        <p className="text-foreground-muted">Configure and deploy your template</p>
                    </div>
                </div>

                {/* Progress Indicator */}
                <div className="mb-8">
                    <div className="flex items-center justify-between">
                        {[1, 2, 3, 4].map((step) => (
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
                                        {step === 2 && 'Configure'}
                                        {step === 3 && 'Server'}
                                        {step === 4 && 'Review'}
                                    </span>
                                </div>
                                {step < 4 && (
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

                {/* Step Content */}
                <div className="mb-8 rounded-xl border border-border/50 bg-gradient-to-br from-background-secondary to-background-secondary/50 p-8">
                    {/* Step 1: Project Name */}
                    {currentStep === 1 && (
                        <div>
                            <div className="mb-6">
                                <Settings className="mb-3 h-8 w-8 text-primary" />
                                <h2 className="mb-2 text-xl font-semibold text-foreground">Configure Project</h2>
                                <p className="text-foreground-muted">
                                    Choose a name for your project. This will be used to identify your deployment.
                                </p>
                            </div>
                            <Input
                                label="Project Name"
                                placeholder="my-nextjs-app"
                                value={formData.projectName}
                                onChange={(e) =>
                                    setFormData({ ...formData, projectName: e.target.value })
                                }
                                hint="Use lowercase letters, numbers, and hyphens"
                            />
                        </div>
                    )}

                    {/* Step 2: Environment Variables */}
                    {currentStep === 2 && (
                        <div>
                            <div className="mb-6">
                                <Settings className="mb-3 h-8 w-8 text-primary" />
                                <h2 className="mb-2 text-xl font-semibold text-foreground">
                                    Set Environment Variables
                                </h2>
                                <p className="text-foreground-muted">
                                    Configure the environment variables for your application. Required fields are marked.
                                </p>
                            </div>
                            <div className="space-y-4">
                                {template.envVars.map((envVar) => (
                                    <div key={envVar.name} className="space-y-2">
                                        <div className="flex items-center gap-2">
                                            <label className="text-sm font-medium text-foreground">
                                                {envVar.name}
                                            </label>
                                            {envVar.required && (
                                                <Badge variant="danger" className="text-xs">
                                                    Required
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="text-xs text-foreground-muted">{envVar.description}</p>
                                        <div className="relative">
                                            <input
                                                type={
                                                    envVar.sensitive && !showSecrets[envVar.name]
                                                        ? 'password'
                                                        : 'text'
                                                }
                                                value={formData.envVars[envVar.name]}
                                                onChange={(e) =>
                                                    setFormData({
                                                        ...formData,
                                                        envVars: {
                                                            ...formData.envVars,
                                                            [envVar.name]: e.target.value,
                                                        },
                                                    })
                                                }
                                                placeholder={envVar.default || `Enter ${envVar.name}`}
                                                className="h-10 w-full rounded-md border border-border bg-background-secondary px-3 py-2 pr-10 text-sm text-foreground placeholder:text-foreground-subtle focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-background"
                                            />
                                            {envVar.sensitive && (
                                                <button
                                                    type="button"
                                                    onClick={() => toggleSecretVisibility(envVar.name)}
                                                    className="absolute right-2 top-1/2 -translate-y-1/2 text-foreground-muted hover:text-foreground"
                                                >
                                                    {showSecrets[envVar.name] ? (
                                                        <EyeOff className="h-4 w-4" />
                                                    ) : (
                                                        <Eye className="h-4 w-4" />
                                                    )}
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Step 3: Select Server */}
                    {currentStep === 3 && (
                        <div>
                            <div className="mb-6">
                                <Server className="mb-3 h-8 w-8 text-primary" />
                                <h2 className="mb-2 text-xl font-semibold text-foreground">Select Server</h2>
                                <p className="text-foreground-muted">
                                    Choose where you want to deploy your application.
                                </p>
                            </div>
                            <div className="space-y-3">
                                {servers.map((server) => (
                                    <button
                                        key={server.id}
                                        onClick={() => setFormData({ ...formData, serverId: server.id })}
                                        className={`w-full rounded-lg border p-4 text-left transition-all ${
                                            formData.serverId === server.id
                                                ? 'border-primary bg-primary/10 ring-2 ring-primary ring-offset-2 ring-offset-background'
                                                : 'border-border bg-background-secondary hover:border-border/80'
                                        }`}
                                    >
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <h3 className="font-semibold text-foreground">{server.name}</h3>
                                                <p className="text-sm text-foreground-muted">
                                                    Region: {server.region}
                                                </p>
                                            </div>
                                            <Badge variant="success">{server.status}</Badge>
                                        </div>
                                    </button>
                                ))}
                            </div>
                            <div className="mt-6">
                                <Checkbox
                                    id="auto-deploy"
                                    label="Enable automatic deployments"
                                    checked={formData.autoDeploy}
                                    onChange={(e) =>
                                        setFormData({ ...formData, autoDeploy: e.target.checked })
                                    }
                                />
                                <p className="ml-6 mt-1 text-xs text-foreground-muted">
                                    Automatically deploy when changes are pushed to your repository
                                </p>
                            </div>
                        </div>
                    )}

                    {/* Step 4: Review */}
                    {currentStep === 4 && (
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
                                    <h3 className="mb-2 text-sm font-semibold text-foreground-muted">
                                        Project Name
                                    </h3>
                                    <p className="text-foreground">{formData.projectName}</p>
                                </div>
                                <div className="rounded-lg border border-border/50 bg-background p-4">
                                    <h3 className="mb-2 text-sm font-semibold text-foreground-muted">
                                        Environment Variables
                                    </h3>
                                    <div className="space-y-2">
                                        {template.envVars.map((envVar) => (
                                            <div key={envVar.name} className="flex justify-between">
                                                <code className="text-sm text-foreground">{envVar.name}</code>
                                                <span className="text-sm text-foreground-muted">
                                                    {formData.envVars[envVar.name]
                                                        ? envVar.sensitive
                                                            ? '••••••••'
                                                            : formData.envVars[envVar.name]
                                                        : 'Not set'}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                                <div className="rounded-lg border border-border/50 bg-background p-4">
                                    <h3 className="mb-2 text-sm font-semibold text-foreground-muted">Server</h3>
                                    <p className="text-foreground">
                                        {servers.find(s => s.id === formData.serverId)?.name}
                                    </p>
                                </div>
                                <div className="rounded-lg border border-border/50 bg-background p-4">
                                    <h3 className="mb-2 text-sm font-semibold text-foreground-muted">Options</h3>
                                    <p className="text-foreground">
                                        {formData.autoDeploy
                                            ? 'Automatic deployments enabled'
                                            : 'Manual deployments only'}
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
                        disabled={currentStep === 1 || isDeploying}
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
                        <Button onClick={handleDeploy} disabled={isDeploying}>
                            {isDeploying ? (
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
                {isDeploying && (
                    <div className="mt-6 rounded-lg border border-primary/50 bg-primary/10 p-4">
                        <div className="flex items-center gap-3">
                            <Loader2 className="h-5 w-5 animate-spin text-primary" />
                            <div>
                                <p className="font-semibold text-foreground">Deploying your application...</p>
                                <p className="text-sm text-foreground-muted">
                                    This may take a few minutes. You'll be redirected when complete.
                                </p>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
