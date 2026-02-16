import { useState } from 'react';
import { useForm, router } from '@inertiajs/react';
import { AuthLayout } from '@/components/layout';
import { Input, Button, Card, CardContent, BrandIcon } from '@/components/ui';
import { Rocket, FolderKanban, Github, Zap, CheckCircle2, ChevronRight } from 'lucide-react';

interface Template {
    id: string;
    name: string;
    description: string;
    icon: string;
}

interface Props {
    userName?: string;
    templates?: Template[];
}

export default function Onboarding({ userName: initialUserName, templates = [] }: Props) {
    const [step, setStep] = useState(1);
    const totalSteps = 4;

    const { data, setData, post, processing, errors } = useForm({
        name: initialUserName || '',
        project_name: '',
        project_type: 'blank', // 'blank' or 'template'
        template_id: '',
        connect_github: false,
        deploy_service: '',
    });

    const defaultTemplates: Template[] = [
        {
            id: 'nodejs',
            name: 'Node.js App',
            description: 'A simple Node.js application with Express',
            icon: '🟢',
        },
        {
            id: 'nextjs',
            name: 'Next.js',
            description: 'Full-stack React framework with SSR',
            icon: '▲',
        },
        {
            id: 'laravel',
            name: 'Laravel',
            description: 'PHP framework for web artisans',
            icon: '🔺',
        },
        {
            id: 'docker',
            name: 'Docker Compose',
            description: 'Multi-container application',
            icon: '🐳',
        },
    ];

    const availableTemplates = templates.length > 0 ? templates : defaultTemplates;

    const services = [
        { id: 'postgresql', name: 'PostgreSQL', icon: <BrandIcon name="postgresql" className="h-8 w-8" /> },
        { id: 'redis', name: 'Redis', icon: <BrandIcon name="redis" className="h-8 w-8" /> },
        { id: 'mongodb', name: 'MongoDB', icon: <BrandIcon name="mongodb" className="h-8 w-8" /> },
        { id: 'mysql', name: 'MySQL', icon: <BrandIcon name="mysql" className="h-8 w-8" /> },
    ];

    const handleNext = () => {
        if (step < totalSteps) {
            setStep(step + 1);
        }
    };

    const handleBack = () => {
        if (step > 1) {
            setStep(step - 1);
        }
    };

    const handleSkip = () => {
        router.visit('/dashboard');
    };

    const handleComplete = () => {
        post('/onboarding/complete', {
            onSuccess: () => {
                router.visit('/dashboard');
            },
        });
    };

    const isStepValid = () => {
        switch (step) {
            case 1:
                return data.name.trim().length > 0;
            case 2:
                return data.project_name.trim().length > 0;
            case 3:
                return true; // GitHub connection is optional
            case 4:
                return true; // Service deployment is optional
            default:
                return false;
        }
    };

    return (
        <AuthLayout
            title="Welcome to Saturn"
            subtitle="Let's get you set up in just a few steps."
        >
            <div className="space-y-6">
                {/* Progress Indicator */}
                <div className="flex items-center justify-between">
                    {Array.from({ length: totalSteps }).map((_, index) => {
                        const stepNumber = index + 1;
                        const isActive = step === stepNumber;
                        const isCompleted = step > stepNumber;

                        return (
                            <div key={stepNumber} className="flex flex-1 items-center">
                                <div className="flex flex-col items-center">
                                    <div
                                        className={`flex h-8 w-8 items-center justify-center rounded-full text-sm font-semibold transition-all ${
                                            isCompleted
                                                ? 'bg-primary text-white'
                                                : isActive
                                                ? 'border-2 border-primary bg-background text-primary'
                                                : 'border-2 border-border bg-background text-foreground-muted'
                                        }`}
                                    >
                                        {isCompleted ? (
                                            <CheckCircle2 className="h-5 w-5" />
                                        ) : (
                                            stepNumber
                                        )}
                                    </div>
                                    <div
                                        className={`mt-2 text-xs ${
                                            isActive ? 'text-foreground' : 'text-foreground-muted'
                                        }`}
                                    >
                                        Step {stepNumber}
                                    </div>
                                </div>
                                {stepNumber < totalSteps && (
                                    <div
                                        className={`mx-2 h-0.5 flex-1 ${
                                            isCompleted ? 'bg-primary' : 'bg-border'
                                        }`}
                                    />
                                )}
                            </div>
                        );
                    })}
                </div>

                {/* Step 1: Welcome + Name */}
                {step === 1 && (
                    <div className="space-y-6">
                        <div className="flex justify-center">
                            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10">
                                <Rocket className="h-8 w-8 text-primary" />
                            </div>
                        </div>

                        <div className="text-center">
                            <h2 className="text-2xl font-bold text-foreground">
                                Welcome to Saturn!
                            </h2>
                            <p className="mt-2 text-foreground-muted">
                                Let's personalize your experience
                            </p>
                        </div>

                        <Input
                            label="What should we call you?"
                            type="text"
                            placeholder="Your name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            error={errors.name}
                            required
                            autoFocus
                        />
                    </div>
                )}

                {/* Step 2: Create First Project */}
                {step === 2 && (
                    <div className="space-y-6">
                        <div className="flex justify-center">
                            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10">
                                <FolderKanban className="h-8 w-8 text-primary" />
                            </div>
                        </div>

                        <div className="text-center">
                            <h2 className="text-2xl font-bold text-foreground">
                                Create Your First Project
                            </h2>
                            <p className="mt-2 text-foreground-muted">
                                Start with a blank project or use a template
                            </p>
                        </div>

                        <Input
                            label="Project Name"
                            type="text"
                            placeholder="My Awesome Project"
                            value={data.project_name}
                            onChange={(e) => setData('project_name', e.target.value)}
                            error={errors.project_name}
                            required
                        />

                        <div className="space-y-3">
                            <label className="text-sm font-medium text-foreground">
                                Choose a starting point
                            </label>
                            <div className="grid grid-cols-2 gap-3">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setData('project_type', 'blank');
                                        setData('template_id', '');
                                    }}
                                    className={`rounded-lg border-2 p-4 text-left transition-all ${
                                        data.project_type === 'blank'
                                            ? 'border-primary bg-primary/5'
                                            : 'border-border bg-background hover:border-primary/50'
                                    }`}
                                >
                                    <div className="text-2xl">📦</div>
                                    <div className="mt-2 font-semibold text-foreground">
                                        Blank Project
                                    </div>
                                    <div className="mt-1 text-xs text-foreground-muted">
                                        Start from scratch
                                    </div>
                                </button>

                                <button
                                    type="button"
                                    onClick={() => setData('project_type', 'template')}
                                    className={`rounded-lg border-2 p-4 text-left transition-all ${
                                        data.project_type === 'template'
                                            ? 'border-primary bg-primary/5'
                                            : 'border-border bg-background hover:border-primary/50'
                                    }`}
                                >
                                    <div className="text-2xl">⚡</div>
                                    <div className="mt-2 font-semibold text-foreground">
                                        Use Template
                                    </div>
                                    <div className="mt-1 text-xs text-foreground-muted">
                                        Quick start
                                    </div>
                                </button>
                            </div>
                        </div>

                        {data.project_type === 'template' && (
                            <div className="space-y-3">
                                <label className="text-sm font-medium text-foreground">
                                    Select a template
                                </label>
                                <div className="grid gap-2">
                                    {availableTemplates.map((template) => (
                                        <button
                                            key={template.id}
                                            type="button"
                                            onClick={() => setData('template_id', template.id)}
                                            className={`flex items-center gap-3 rounded-lg border p-3 text-left transition-all ${
                                                data.template_id === template.id
                                                    ? 'border-primary bg-primary/5'
                                                    : 'border-border bg-background hover:border-primary/50'
                                            }`}
                                        >
                                            <div className="text-2xl">{template.icon}</div>
                                            <div className="flex-1">
                                                <div className="font-semibold text-foreground">
                                                    {template.name}
                                                </div>
                                                <div className="text-xs text-foreground-muted">
                                                    {template.description}
                                                </div>
                                            </div>
                                            {data.template_id === template.id && (
                                                <CheckCircle2 className="h-5 w-5 text-primary" />
                                            )}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {/* Step 3: Connect GitHub */}
                {step === 3 && (
                    <div className="space-y-6">
                        <div className="flex justify-center">
                            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10">
                                <Github className="h-8 w-8 text-primary" />
                            </div>
                        </div>

                        <div className="text-center">
                            <h2 className="text-2xl font-bold text-foreground">
                                Connect GitHub
                            </h2>
                            <p className="mt-2 text-foreground-muted">
                                Deploy from your repositories automatically
                            </p>
                        </div>

                        <Card>
                            <CardContent className="p-6">
                                <div className="space-y-4">
                                    <div className="flex items-start gap-4">
                                        <div className="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-lg bg-background">
                                            <Github className="h-6 w-6 text-foreground" />
                                        </div>
                                        <div className="flex-1 space-y-2">
                                            <h3 className="font-semibold text-foreground">
                                                Why connect GitHub?
                                            </h3>
                                            <ul className="space-y-1 text-sm text-foreground-muted">
                                                <li className="flex items-start gap-2">
                                                    <span className="mt-0.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-primary" />
                                                    Deploy directly from your repositories
                                                </li>
                                                <li className="flex items-start gap-2">
                                                    <span className="mt-0.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-primary" />
                                                    Automatic deployments on push
                                                </li>
                                                <li className="flex items-start gap-2">
                                                    <span className="mt-0.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-primary" />
                                                    Pull request previews
                                                </li>
                                            </ul>
                                        </div>
                                    </div>

                                    <Button
                                        type="button"
                                        className="w-full"
                                        onClick={() => {
                                            window.location.href = '/auth/github/redirect';
                                        }}
                                    >
                                        <Github className="mr-2 h-4 w-4" />
                                        Connect GitHub Account
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {/* Step 4: Deploy First Service */}
                {step === 4 && (
                    <div className="space-y-6">
                        <div className="flex justify-center">
                            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10">
                                <Zap className="h-8 w-8 text-primary" />
                            </div>
                        </div>

                        <div className="text-center">
                            <h2 className="text-2xl font-bold text-foreground">
                                Deploy Your First Service
                            </h2>
                            <p className="mt-2 text-foreground-muted">
                                Choose a database or service to get started
                            </p>
                        </div>

                        <div className="space-y-3">
                            <label className="text-sm font-medium text-foreground">
                                Quick start services
                            </label>
                            <div className="grid gap-3">
                                {services.map((service) => (
                                    <button
                                        key={service.id}
                                        type="button"
                                        onClick={() => setData('deploy_service', service.id)}
                                        className={`flex items-center gap-3 rounded-lg border p-4 text-left transition-all ${
                                            data.deploy_service === service.id
                                                ? 'border-primary bg-primary/5'
                                                : 'border-border bg-background hover:border-primary/50'
                                        }`}
                                    >
                                        <div className="flex h-8 w-8 items-center justify-center">{service.icon}</div>
                                        <div className="flex-1 font-semibold text-foreground">
                                            {service.name}
                                        </div>
                                        {data.deploy_service === service.id && (
                                            <CheckCircle2 className="h-5 w-5 text-primary" />
                                        )}
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div className="rounded-lg border border-blue-500/20 bg-blue-500/10 p-4">
                            <p className="text-sm text-blue-600 dark:text-blue-400">
                                Don't worry! You can always add more services later from your
                                dashboard.
                            </p>
                        </div>
                    </div>
                )}

                {/* Navigation Buttons */}
                <div className="flex items-center justify-between gap-3 pt-4">
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={handleSkip}
                        disabled={processing}
                    >
                        Skip for now
                    </Button>

                    <div className="flex gap-3">
                        {step > 1 && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={handleBack}
                                disabled={processing}
                            >
                                Back
                            </Button>
                        )}

                        {step < totalSteps ? (
                            <Button
                                type="button"
                                onClick={handleNext}
                                disabled={!isStepValid() || processing}
                            >
                                Next
                                <ChevronRight className="ml-2 h-4 w-4" />
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                onClick={handleComplete}
                                loading={processing}
                            >
                                <Rocket className="mr-2 h-4 w-4" />
                                Complete Setup
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </AuthLayout>
    );
}
