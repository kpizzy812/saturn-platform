import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Input, Textarea, Select } from '@/components/ui';
import { ArrowLeft, ChevronRight, Cloud, Check, Loader2 } from 'lucide-react';
import type { S3Provider } from '@/types';

interface ProviderOption {
    type: S3Provider;
    displayName: string;
    description: string;
    iconBg: string;
    icon: string;
    defaultRegion: string;
    defaultEndpoint?: string;
}

const providerTypes: ProviderOption[] = [
    {
        type: 'aws',
        displayName: 'AWS S3',
        description: 'Amazon Web Services S3 storage',
        iconBg: 'bg-gradient-to-br from-orange-500 to-orange-600',
        icon: '🔶',
        defaultRegion: 'us-east-1',
    },
    {
        type: 'wasabi',
        displayName: 'Wasabi',
        description: 'Hot cloud storage at 1/5th the price',
        iconBg: 'bg-gradient-to-br from-green-500 to-green-600',
        icon: '🌿',
        defaultRegion: 'us-east-1',
        defaultEndpoint: 'https://s3.wasabisys.com',
    },
    {
        type: 'backblaze',
        displayName: 'Backblaze B2',
        description: 'Low-cost cloud storage',
        iconBg: 'bg-gradient-to-br from-red-500 to-red-600',
        icon: '🔴',
        defaultRegion: 'us-west-000',
        defaultEndpoint: 'https://s3.us-west-000.backblazeb2.com',
    },
    {
        type: 'minio',
        displayName: 'MinIO',
        description: 'Self-hosted S3-compatible storage',
        iconBg: 'bg-gradient-to-br from-pink-500 to-pink-600',
        icon: '🪣',
        defaultRegion: 'us-east-1',
        defaultEndpoint: 'https://minio.example.com',
    },
    {
        type: 'custom',
        displayName: 'Custom S3',
        description: 'Any S3-compatible storage provider',
        iconBg: 'bg-gradient-to-br from-blue-500 to-blue-600',
        icon: '☁️',
        defaultRegion: 'us-east-1',
    },
];

type Step = 1 | 2 | 3;

export default function StorageCreate() {
    const [step, setStep] = useState<Step>(1);
    const [selectedProvider, setSelectedProvider] = useState<S3Provider | null>(null);
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [accessKey, setAccessKey] = useState('');
    const [secretKey, setSecretKey] = useState('');
    const [bucket, setBucket] = useState('');
    const [region, setRegion] = useState('');
    const [endpoint, setEndpoint] = useState('');
    const [path, setPath] = useState('');
    const [testing, setTesting] = useState(false);
    const [testResult, setTestResult] = useState<{ success: boolean; message: string } | null>(null);

    const selectedProviderType = providerTypes.find(p => p.type === selectedProvider);

    const handleProviderSelect = (type: S3Provider) => {
        setSelectedProvider(type);
        const providerType = providerTypes.find(p => p.type === type);
        if (providerType) {
            setRegion(providerType.defaultRegion);
            setEndpoint(providerType.defaultEndpoint || '');
        }
        setStep(2);
    };

    const handleTestConnection = async () => {
        setTesting(true);
        setTestResult(null);

        try {
            const xsrfToken = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || '');
            const response = await fetch('/storage/test-connection', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': xsrfToken,
                },
                credentials: 'include',
                body: JSON.stringify({
                    key: accessKey,
                    secret: secretKey,
                    bucket,
                    region,
                    endpoint: endpoint || null,
                }),
            });

            const data = await response.json();
            setTestResult({
                success: data.success,
                message: data.message,
            });
        } catch {
            setTestResult({
                success: false,
                message: 'An error occurred while testing the connection.',
            });
        } finally {
            setTesting(false);
        }
    };

    const handleSubmit = () => {
        router.post('/storage', {
            name,
            description,
            key: accessKey,
            secret: secretKey,
            bucket,
            region,
            endpoint: endpoint || null,
            path: path || null,
        });
    };

    const isStepTwoValid = name && accessKey && secretKey && bucket && region;

    return (
        <AppLayout title="Add Storage" showNewProject={false}>
            <div className="flex min-h-full items-start justify-center py-12">
                <div className="w-full max-w-2xl px-4">
                    {/* Back link */}
                    <Link
                        href="/storage"
                        className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
                    >
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Back to Storage
                    </Link>

                    {/* Header */}
                    <div className="mb-8 text-center">
                        <h1 className="text-2xl font-semibold text-foreground">Add S3 storage</h1>
                        <p className="mt-2 text-foreground-muted">
                            Choose a provider and configure your S3-compatible storage
                        </p>
                    </div>

                    {/* Progress Indicator */}
                    <div className="mb-8 flex items-center justify-center gap-2">
                        <StepIndicator step={1} currentStep={step} label="Provider" />
                        <div className="h-px w-12 bg-border" />
                        <StepIndicator step={2} currentStep={step} label="Configure" />
                        <div className="h-px w-12 bg-border" />
                        <StepIndicator step={3} currentStep={step} label="Review" />
                    </div>

                    {/* Step Content */}
                    <div className="space-y-3">
                        {step === 1 && (
                            <div className="space-y-3">
                                {providerTypes.map((provider) => (
                                    <button
                                        key={provider.type}
                                        onClick={() => handleProviderSelect(provider.type)}
                                        className="group w-full text-left"
                                    >
                                        <Card className="transition-all duration-300 hover:-translate-y-0.5 hover:border-border hover:shadow-xl hover:shadow-black/20">
                                            <CardContent className="flex items-center justify-between p-4">
                                                <div className="flex items-center gap-4">
                                                    <div className={`flex h-11 w-11 items-center justify-center rounded-xl ${provider.iconBg} text-white text-xl shadow-lg transition-transform duration-300 group-hover:scale-110`}>
                                                        {provider.icon}
                                                    </div>
                                                    <div>
                                                        <h3 className="font-medium text-foreground transition-colors group-hover:text-white">
                                                            {provider.displayName}
                                                        </h3>
                                                        <p className="mt-0.5 text-sm text-foreground-muted">
                                                            {provider.description}
                                                        </p>
                                                    </div>
                                                </div>
                                                <ChevronRight className="h-5 w-5 text-foreground-subtle transition-transform duration-300 group-hover:translate-x-1 group-hover:text-foreground-muted" />
                                            </CardContent>
                                        </Card>
                                    </button>
                                ))}
                            </div>
                        )}

                        {step === 2 && selectedProviderType && (
                            <Card>
                                <CardContent className="p-6">
                                    <div className="mb-6 flex items-center gap-3">
                                        <div className={`flex h-11 w-11 items-center justify-center rounded-xl ${selectedProviderType.iconBg} text-white text-xl shadow-lg`}>
                                            {selectedProviderType.icon}
                                        </div>
                                        <div>
                                            <h3 className="font-medium text-foreground">{selectedProviderType.displayName}</h3>
                                            <p className="text-sm text-foreground-muted">{selectedProviderType.description}</p>
                                        </div>
                                    </div>

                                    <div className="space-y-4">
                                        <Input
                                            label="Storage Name"
                                            placeholder="production-backups"
                                            value={name}
                                            onChange={(e) => setName(e.target.value)}
                                            hint="A friendly name for this storage"
                                        />

                                        <Textarea
                                            label="Description (Optional)"
                                            placeholder="Production database backups"
                                            value={description}
                                            onChange={(e) => setDescription(e.target.value)}
                                            rows={2}
                                        />

                                        <div className="grid gap-4 md:grid-cols-2">
                                            <Input
                                                label="Access Key ID"
                                                placeholder="AKIAIOSFODNN7EXAMPLE"
                                                value={accessKey}
                                                onChange={(e) => setAccessKey(e.target.value)}
                                                hint="Your S3 access key"
                                            />

                                            <Input
                                                label="Secret Access Key"
                                                type="password"
                                                placeholder="wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY"
                                                value={secretKey}
                                                onChange={(e) => setSecretKey(e.target.value)}
                                                hint="Your S3 secret key"
                                            />
                                        </div>

                                        <div className="grid gap-4 md:grid-cols-2">
                                            <Input
                                                label="Bucket Name"
                                                placeholder="my-backups"
                                                value={bucket}
                                                onChange={(e) => setBucket(e.target.value)}
                                                hint="S3 bucket name"
                                            />

                                            <Input
                                                label="Region"
                                                placeholder="us-east-1"
                                                value={region}
                                                onChange={(e) => setRegion(e.target.value)}
                                                hint="AWS region"
                                            />
                                        </div>

                                        {selectedProvider !== 'aws' && (
                                            <Input
                                                label="Endpoint URL"
                                                placeholder="https://s3.example.com"
                                                value={endpoint}
                                                onChange={(e) => setEndpoint(e.target.value)}
                                                hint="S3-compatible endpoint URL"
                                            />
                                        )}

                                        <Input
                                            label="Path (Optional)"
                                            placeholder="/backups/saturn"
                                            value={path}
                                            onChange={(e) => setPath(e.target.value)}
                                            hint="Optional path prefix within the bucket"
                                        />

                                        {/* Test Connection */}
                                        <div className="rounded-lg border border-border bg-background-secondary p-4">
                                            <div className="flex items-center justify-between">
                                                <div>
                                                    <h4 className="font-medium text-foreground">Test Connection</h4>
                                                    <p className="text-sm text-foreground-muted">
                                                        Verify that Saturn Platform can connect to your storage
                                                    </p>
                                                </div>
                                                <Button
                                                    variant="secondary"
                                                    onClick={handleTestConnection}
                                                    disabled={!isStepTwoValid || testing}
                                                >
                                                    {testing ? (
                                                        <>
                                                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                            Testing...
                                                        </>
                                                    ) : (
                                                        'Test Connection'
                                                    )}
                                                </Button>
                                            </div>

                                            {testResult && (
                                                <div
                                                    className={`mt-3 rounded-md p-3 text-sm ${
                                                        testResult.success
                                                            ? 'bg-success/10 text-success'
                                                            : 'bg-danger/10 text-danger'
                                                    }`}
                                                >
                                                    {testResult.message}
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    <div className="mt-6 flex gap-3">
                                        <Button variant="secondary" onClick={() => setStep(1)}>
                                            Back
                                        </Button>
                                        <Button
                                            onClick={() => setStep(3)}
                                            disabled={!isStepTwoValid}
                                            className="flex-1"
                                        >
                                            Continue
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {step === 3 && selectedProviderType && (
                            <Card>
                                <CardContent className="p-6">
                                    <h3 className="mb-4 text-lg font-medium text-foreground">Review Configuration</h3>

                                    <div className="space-y-4">
                                        <div className="flex items-center gap-3 rounded-lg border border-border bg-background-secondary p-4">
                                            <div className={`flex h-11 w-11 items-center justify-center rounded-xl ${selectedProviderType.iconBg} text-white text-xl shadow-lg`}>
                                                {selectedProviderType.icon}
                                            </div>
                                            <div className="flex-1">
                                                <p className="font-medium text-foreground">{name}</p>
                                                <p className="text-sm text-foreground-muted">
                                                    {selectedProviderType.displayName}
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
                                                Connection Details
                                            </label>
                                            <div className="space-y-1 text-sm">
                                                <div className="flex justify-between">
                                                    <span className="text-foreground-muted">Bucket:</span>
                                                    <span className="font-medium text-foreground">{bucket}</span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span className="text-foreground-muted">Region:</span>
                                                    <span className="font-medium text-foreground">{region}</span>
                                                </div>
                                                {endpoint && (
                                                    <div className="flex justify-between">
                                                        <span className="text-foreground-muted">Endpoint:</span>
                                                        <span className="font-medium text-foreground truncate ml-2">{endpoint}</span>
                                                    </div>
                                                )}
                                                {path && (
                                                    <div className="flex justify-between">
                                                        <span className="text-foreground-muted">Path:</span>
                                                        <span className="font-medium text-foreground">{path}</span>
                                                    </div>
                                                )}
                                                <div className="flex justify-between">
                                                    <span className="text-foreground-muted">Access Key:</span>
                                                    <span className="font-medium text-foreground">
                                                        {accessKey.substring(0, 8)}...
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="rounded-lg border border-border bg-background-secondary p-4">
                                            <h4 className="mb-2 font-medium text-foreground">What happens next?</h4>
                                            <ul className="space-y-2 text-sm text-foreground-muted">
                                                <li className="flex items-start gap-2">
                                                    <Check className="mt-0.5 h-4 w-4 text-green-500" />
                                                    <span>Storage will be configured and validated</span>
                                                </li>
                                                <li className="flex items-start gap-2">
                                                    <Check className="mt-0.5 h-4 w-4 text-green-500" />
                                                    <span>Available for database backup destinations</span>
                                                </li>
                                                <li className="flex items-start gap-2">
                                                    <Check className="mt-0.5 h-4 w-4 text-green-500" />
                                                    <span>Connection will be tested periodically</span>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div className="mt-6 flex gap-3">
                                        <Button variant="secondary" onClick={() => setStep(2)}>
                                            Back
                                        </Button>
                                        <Button onClick={handleSubmit} className="flex-1">
                                            Add Storage
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
