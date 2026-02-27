import { useState } from 'react';
import { router, Link } from '@inertiajs/react';
import type { RouterPayload } from '@/types/inertia';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Input, Textarea, Select } from '@/components/ui';
import { ArrowLeft, Check, Server, Key, Cloud } from 'lucide-react';
import { validateIPAddress, validatePort, validateSSHKey } from '@/lib/validation';
import type { PrivateKey } from '@/types';
import type { CloudProviderToken } from '@/types/models';
import HetznerTab from './HetznerTab';

type Step = 1 | 2;
type KeyMode = 'existing' | 'new';
type Tab = 'manual' | 'cloud';

interface Props {
    privateKeys?: PrivateKey[];
    cloudTokens?: CloudProviderToken[];
}

export default function ServerCreate({ privateKeys = [], cloudTokens = [] }: Props) {
    const [tab, setTab] = useState<Tab>('manual');
    const [step, setStep] = useState<Step>(1);
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [ip, setIp] = useState('');
    const [port, setPort] = useState('22');
    const [user, setUser] = useState('root');
    const [keyMode, setKeyMode] = useState<KeyMode>(privateKeys.length > 0 ? 'existing' : 'new');
    const [selectedKeyId, setSelectedKeyId] = useState<number | null>(privateKeys[0]?.id ?? null);
    const [privateKey, setPrivateKey] = useState('');

    // Validation errors
    const [ipError, setIpError] = useState<string>();
    const [portError, setPortError] = useState<string>();
    const [privateKeyError, setPrivateKeyError] = useState<string>();

    const handleIpChange = (value: string) => {
        setIp(value);
        if (value.trim()) {
            const { valid, error } = validateIPAddress(value);
            setIpError(valid ? undefined : error);
        } else {
            setIpError(undefined);
        }
    };

    const handlePortChange = (value: string) => {
        setPort(value);
        if (value.trim()) {
            const { valid, error } = validatePort(value);
            setPortError(valid ? undefined : error);
        } else {
            setPortError(undefined);
        }
    };

    const handlePrivateKeyChange = (value: string) => {
        setPrivateKey(value);
        if (value.trim()) {
            const { valid, error } = validateSSHKey(value);
            setPrivateKeyError(valid ? undefined : error);
        } else {
            setPrivateKeyError(undefined);
        }
    };

    const handleSubmit = () => {
        const data: RouterPayload = {
            name,
            description,
            ip,
            port: parseInt(port),
            user,
        };

        if (keyMode === 'existing' && selectedKeyId) {
            data.private_key_id = selectedKeyId;
        } else {
            data.private_key = privateKey;
        }

        router.post('/servers', data);
    };

    const hasValidKey = keyMode === 'existing' ? !!selectedKeyId : (!!privateKey && !privateKeyError);
    const isStepOneValid = name && ip && port && user && !ipError && !portError && hasValidKey;

    return (
        <AppLayout title="Add Server" showNewProject={false}>
            <div className="flex min-h-full items-start justify-center py-12">
                <div className="w-full max-w-2xl px-4">
                    {/* Back link */}
                    <Link
                        href="/servers"
                        className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
                    >
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Back to Servers
                    </Link>

                    {/* Header */}
                    <div className="mb-8 text-center">
                        <h1 className="text-2xl font-semibold text-foreground">Add a new server</h1>
                        <p className="mt-2 text-foreground-muted">
                            Connect your server to start deploying applications
                        </p>
                    </div>

                    {/* Tab Switcher */}
                    <div className="mb-6 flex rounded-lg border border-border bg-background-secondary p-1">
                        <button
                            type="button"
                            onClick={() => setTab('manual')}
                            className={`flex flex-1 items-center justify-center gap-2 rounded-md py-2 text-sm font-medium transition-colors ${
                                tab === 'manual'
                                    ? 'bg-background text-foreground shadow-sm'
                                    : 'text-foreground-muted hover:text-foreground'
                            }`}
                        >
                            <Server className="h-4 w-4" />
                            Manual SSH
                        </button>
                        <button
                            type="button"
                            onClick={() => setTab('cloud')}
                            className={`flex flex-1 items-center justify-center gap-2 rounded-md py-2 text-sm font-medium transition-colors ${
                                tab === 'cloud'
                                    ? 'bg-background text-foreground shadow-sm'
                                    : 'text-foreground-muted hover:text-foreground'
                            }`}
                        >
                            <Cloud className="h-4 w-4" />
                            Cloud Provider
                        </button>
                    </div>

                    {/* Cloud Provider Tab */}
                    {tab === 'cloud' && (
                        <HetznerTab cloudTokens={cloudTokens} privateKeys={privateKeys} />
                    )}

                    {/* Manual Tab — Progress Indicator */}
                    {tab === 'manual' && (
                    <div className="mb-8 flex items-center justify-center gap-2">
                        <StepIndicator step={1} currentStep={step} label="Server Info" />
                        <div className="h-px w-12 bg-border" />
                        <StepIndicator step={2} currentStep={step} label="Review" />
                    </div>
                    )}

                    {/* Step Content */}
                    {tab === 'manual' && <div className="space-y-3">
                        {step === 1 && (
                            <Card>
                                <CardContent className="p-6">
                                    <div className="mb-6 flex items-center gap-3">
                                        <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 text-white shadow-lg">
                                            <Server className="h-5 w-5" />
                                        </div>
                                        <div>
                                            <h3 className="font-medium text-foreground">Server Configuration</h3>
                                            <p className="text-sm text-foreground-muted">Configure SSH connection details</p>
                                        </div>
                                    </div>

                                    <div className="space-y-4">
                                        <Input
                                            label="Server Name"
                                            placeholder="production-server"
                                            value={name}
                                            onChange={(e) => setName(e.target.value)}
                                            hint="A friendly name for your server"
                                        />

                                        <Input
                                            label="Description (Optional)"
                                            placeholder="Main production server"
                                            value={description}
                                            onChange={(e) => setDescription(e.target.value)}
                                        />

                                        <div className="grid gap-4 md:grid-cols-3">
                                            <div className="md:col-span-2">
                                                <Input
                                                    label="IP Address"
                                                    placeholder="192.168.1.1"
                                                    value={ip}
                                                    onChange={(e) => handleIpChange(e.target.value)}
                                                    error={ipError}
                                                    hint={!ipError ? "Server IP or hostname" : undefined}
                                                />
                                            </div>
                                            <Input
                                                label="SSH Port"
                                                placeholder="22"
                                                value={port}
                                                onChange={(e) => handlePortChange(e.target.value)}
                                                type="number"
                                                error={portError}
                                            />
                                        </div>

                                        <Input
                                            label="SSH User"
                                            placeholder="root"
                                            value={user}
                                            onChange={(e) => setUser(e.target.value)}
                                            hint="User with Docker privileges"
                                        />

                                        {/* SSH Key Mode Selection */}
                                        <div className="space-y-3">
                                            <label className="block text-sm font-medium text-foreground">
                                                SSH Key
                                            </label>
                                            {privateKeys.length > 0 && (
                                                <div className="flex gap-2">
                                                    <Button
                                                        type="button"
                                                        variant={keyMode === 'existing' ? 'default' : 'secondary'}
                                                        size="sm"
                                                        onClick={() => setKeyMode('existing')}
                                                    >
                                                        <Key className="mr-2 h-4 w-4" />
                                                        Use Existing Key
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant={keyMode === 'new' ? 'default' : 'secondary'}
                                                        size="sm"
                                                        onClick={() => setKeyMode('new')}
                                                    >
                                                        Add New Key
                                                    </Button>
                                                </div>
                                            )}

                                            {keyMode === 'existing' && privateKeys.length > 0 ? (
                                                <Select
                                                    value={selectedKeyId?.toString() ?? ''}
                                                    onChange={(e) => setSelectedKeyId(parseInt(e.target.value))}
                                                >
                                                    {privateKeys.map((key) => (
                                                        <option key={key.id} value={key.id}>
                                                            {key.name}
                                                        </option>
                                                    ))}
                                                </Select>
                                            ) : (
                                                <Textarea
                                                    placeholder="-----BEGIN OPENSSH PRIVATE KEY-----
...
-----END OPENSSH PRIVATE KEY-----"
                                                    value={privateKey}
                                                    onChange={(e) => handlePrivateKeyChange(e.target.value)}
                                                    rows={8}
                                                    error={privateKeyError}
                                                    hint={!privateKeyError ? "Your private key for SSH authentication" : undefined}
                                                    className="font-mono text-sm"
                                                />
                                            )}
                                        </div>
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
                                    <h3 className="mb-4 text-lg font-medium text-foreground">Review Server Configuration</h3>

                                    <div className="space-y-4">
                                        <div className="flex items-center gap-3 rounded-lg border border-border bg-background-secondary p-4">
                                            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 text-white shadow-lg">
                                                <Server className="h-5 w-5" />
                                            </div>
                                            <div className="flex-1">
                                                <p className="font-medium text-foreground">{name}</p>
                                                <p className="text-sm text-foreground-muted">
                                                    {ip}:{port} • {user}
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
                                            <label className="mb-1 block text-sm font-medium text-foreground-muted">
                                                Connection Details
                                            </label>
                                            <div className="mt-2 space-y-1 text-sm">
                                                <div className="flex justify-between">
                                                    <span className="text-foreground-muted">IP Address:</span>
                                                    <span className="font-medium text-foreground">{ip}</span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span className="text-foreground-muted">Port:</span>
                                                    <span className="font-medium text-foreground">{port}</span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span className="text-foreground-muted">User:</span>
                                                    <span className="font-medium text-foreground">{user}</span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span className="text-foreground-muted">Private Key:</span>
                                                    <span className="font-medium text-foreground">
                                                        {keyMode === 'existing' && selectedKeyId
                                                            ? privateKeys.find(k => k.id === selectedKeyId)?.name ?? 'Selected'
                                                            : privateKey ? 'New key provided' : 'Not provided'}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="rounded-lg border border-border bg-background-secondary p-4">
                                            <h4 className="mb-2 font-medium text-foreground">What happens next?</h4>
                                            <ul className="space-y-2 text-sm text-foreground-muted">
                                                <li className="flex items-start gap-2">
                                                    <Check className="mt-0.5 h-4 w-4 text-green-500" />
                                                    <span>Server connection will be validated</span>
                                                </li>
                                                <li className="flex items-start gap-2">
                                                    <Check className="mt-0.5 h-4 w-4 text-green-500" />
                                                    <span>Docker installation will be checked</span>
                                                </li>
                                                <li className="flex items-start gap-2">
                                                    <Check className="mt-0.5 h-4 w-4 text-green-500" />
                                                    <span>Server will be ready for deployments</span>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div className="mt-6 flex gap-3">
                                        <Button variant="secondary" onClick={() => setStep(1)}>
                                            Back
                                        </Button>
                                        <Button onClick={handleSubmit} className="flex-1">
                                            Add Server
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>}
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
