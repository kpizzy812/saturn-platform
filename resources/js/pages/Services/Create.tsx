import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Input, Textarea } from '@/components/ui';
import { ArrowLeft, Check, Container } from 'lucide-react';
import { validateDockerCompose } from '@/lib/validation';

type Step = 1 | 2;

export default function ServiceCreate() {
    const [step, setStep] = useState<Step>(1);
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [dockerCompose, setDockerCompose] = useState('');
    const [dockerComposeError, setDockerComposeError] = useState<string>();

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

    const isStepOneValid = name && dockerCompose && !dockerComposeError;

    return (
        <AppLayout title="Create Service" showNewProject={false}>
            <div className="flex min-h-full items-start justify-center py-12">
                <div className="w-full max-w-2xl px-4">
                    {/* Back link */}
                    <Link
                        href="/services"
                        className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
                    >
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Back to Services
                    </Link>

                    {/* Header */}
                    <div className="mb-8 text-center">
                        <h1 className="text-2xl font-semibold text-foreground">Create a new service</h1>
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
