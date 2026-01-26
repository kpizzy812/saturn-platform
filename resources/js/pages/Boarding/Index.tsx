import { useState } from 'react';
import { router, Link } from '@inertiajs/react';
import { Card, CardContent, Button, Input, Select, Textarea, useConfirm } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import {
    Check,
    ChevronRight,
    ChevronLeft,
    Server as ServerIcon,
    GitBranch,
    Rocket,
    Sparkles,
    Github,
    Key,
    Plus,
    ExternalLink,
    AlertCircle,
} from 'lucide-react';
import { validateIPAddress, validatePort, validateSSHKey } from '@/lib/validation';
import type { PrivateKey } from '@/types';

interface GithubApp {
    id: number;
    uuid: string;
    name: string;
    installation_id: number | null;
}

interface Props {
    userName?: string;
    existingServers?: Array<{ id: number; name: string; ip: string }>;
    privateKeys?: PrivateKey[];
    githubApps?: GithubApp[];
}

type Step = 'welcome' | 'server' | 'git' | 'deploy' | 'complete';

type KeyMode = 'existing' | 'new';

export default function BoardingIndex({ userName, existingServers = [], privateKeys = [], githubApps = [] }: Props) {
    // Check if localhost server exists (id=0 or name='localhost')
    const localhostServer = existingServers.find(s => s.id === 0 || s.name === 'localhost');
    const hasLocalhost = !!localhostServer;

    const confirm = useConfirm();
    const { addToast } = useToast();

    const [currentStep, setCurrentStep] = useState<Step>('welcome');
    const [completedSteps, setCompletedSteps] = useState<Set<Step>>(new Set());
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Server form state
    const [serverName, setServerName] = useState('');
    const [serverIp, setServerIp] = useState('');
    const [serverPort, setServerPort] = useState('22');
    const [serverUser, setServerUser] = useState('root');
    const [useExistingServer, setUseExistingServer] = useState(hasLocalhost);
    const [selectedServerId, setSelectedServerId] = useState(localhostServer?.id?.toString() || '');

    // SSH Key state
    const [keyMode, setKeyMode] = useState<KeyMode>(privateKeys.length > 0 ? 'existing' : 'new');
    const [selectedKeyId, setSelectedKeyId] = useState<number | null>(privateKeys[0]?.id ?? null);
    const [privateKeyContent, setPrivateKeyContent] = useState('');

    // Validation errors
    const [ipError, setIpError] = useState<string>();
    const [portError, setPortError] = useState<string>();
    const [privateKeyError, setPrivateKeyError] = useState<string>();

    // Git source form state
    const [selectedGithubAppId, setSelectedGithubAppId] = useState(
        githubApps.length > 0 ? githubApps[0].id.toString() : ''
    );
    const hasGithubApp = githubApps.length > 0;

    // App deployment form state
    const [appName, setAppName] = useState('');
    const [gitRepository, setGitRepository] = useState('');
    const [gitBranch, setGitBranch] = useState('main');

    const steps: { id: Step; title: string; description: string }[] = [
        { id: 'welcome', title: 'Welcome', description: 'Get started with Saturn Platform' },
        { id: 'server', title: 'Add Server', description: 'Connect your first server' },
        { id: 'git', title: 'Connect Git', description: 'Link your GitHub account' },
        { id: 'deploy', title: 'Deploy App', description: 'Deploy your first application' },
        { id: 'complete', title: 'Complete', description: 'You are all set!' },
    ];

    const markStepComplete = (step: Step) => {
        setCompletedSteps(prev => new Set([...prev, step]));
    };

    const handleSkip = () => {
        router.post('/boarding/skip');
    };

    const handleIpChange = (value: string) => {
        setServerIp(value);
        if (value.trim()) {
            const { valid, error } = validateIPAddress(value);
            setIpError(valid ? undefined : error);
        } else {
            setIpError(undefined);
        }
    };

    const handlePortChange = (value: string) => {
        setServerPort(value);
        if (value.trim()) {
            const { valid, error } = validatePort(value);
            setPortError(valid ? undefined : error);
        } else {
            setPortError(undefined);
        }
    };

    const handlePrivateKeyChange = (value: string) => {
        setPrivateKeyContent(value);
        if (value.trim()) {
            const { valid, error } = validateSSHKey(value);
            setPrivateKeyError(valid ? undefined : error);
        } else {
            setPrivateKeyError(undefined);
        }
    };

    const handleServerSubmit = async () => {
        if (useExistingServer && selectedServerId) {
            markStepComplete('server');
            setCurrentStep('git');
            return;
        }

        if (!serverName || !serverIp) {
            await confirm({
                title: 'Missing Required Fields',
                description: 'Please fill in server name and IP address.',
                confirmText: 'OK',
                cancelText: '',
                variant: 'default',
            });
            return;
        }

        // Validate SSH key
        const hasValidKey = keyMode === 'existing' ? !!selectedKeyId : (!!privateKeyContent && !privateKeyError);
        if (!hasValidKey) {
            await confirm({
                title: 'SSH Key Required',
                description: 'Please select an existing SSH key or provide a new one.',
                confirmText: 'OK',
                cancelText: '',
                variant: 'default',
            });
            return;
        }

        // Validate IP and port
        if (ipError || portError) {
            await confirm({
                title: 'Validation Error',
                description: 'Please fix the validation errors before continuing.',
                confirmText: 'OK',
                cancelText: '',
                variant: 'default',
            });
            return;
        }

        setIsSubmitting(true);

        const payload = {
            name: serverName,
            ip: serverIp,
            port: parseInt(serverPort, 10),
            user: serverUser,
            ...(keyMode === 'existing' && selectedKeyId
                ? { private_key_id: selectedKeyId }
                : { private_key: privateKeyContent }),
        };

        router.post('/servers', payload, {
            onSuccess: () => {
                markStepComplete('server');
                setCurrentStep('git');
                setIsSubmitting(false);
            },
            onError: () => {
                setIsSubmitting(false);
            },
            preserveScroll: true,
        });
    };

    const handleGitSubmit = () => {
        if (hasGithubApp && selectedGithubAppId) {
            markStepComplete('git');
            setCurrentStep('deploy');
            return;
        }

        // Redirect to GitHub App creation page
        router.visit('/sources/github/create');
    };

    const handleDeploySubmit = async () => {
        if (!appName || !gitRepository) {
            await confirm({
                title: 'Missing Required Fields',
                description: 'Please fill in the application name and git repository URL.',
                confirmText: 'OK',
                cancelText: '',
                variant: 'default',
            });
            return;
        }

        if (!selectedServerId) {
            await confirm({
                title: 'No Server Selected',
                description: 'Please go back and select a server for deployment.',
                confirmText: 'OK',
                cancelText: '',
                variant: 'default',
            });
            return;
        }

        router.post('/boarding/deploy', {
            name: appName,
            git_repository: gitRepository,
            git_branch: gitBranch,
            server_id: selectedServerId,
            github_app_id: selectedGithubAppId || null,
        }, {
            onSuccess: () => {
                markStepComplete('deploy');
                setCurrentStep('complete');
            },
            onError: () => {
                addToast('error', 'Deploy failed. Please check your settings and try again.');
            },
            preserveScroll: true,
        });
    };

    return (
        <div className="min-h-screen bg-background">
            {/* Progress Bar */}
            <div className="border-b border-border bg-background-secondary">
                <div className="mx-auto max-w-4xl px-6 py-4">
                    <div className="flex items-center justify-between">
                        {steps.map((step, index) => (
                            <div key={step.id} className="flex items-center">
                                <div className="flex items-center gap-3">
                                    <div
                                        className={`flex h-8 w-8 items-center justify-center rounded-full border-2 transition-all ${
                                            completedSteps.has(step.id)
                                                ? 'border-primary bg-primary text-white'
                                                : currentStep === step.id
                                                ? 'border-primary bg-background text-primary'
                                                : 'border-border bg-background text-foreground-muted'
                                        }`}
                                    >
                                        {completedSteps.has(step.id) ? (
                                            <Check className="h-4 w-4" />
                                        ) : (
                                            <span className="text-xs font-medium">{index + 1}</span>
                                        )}
                                    </div>
                                    <div className="hidden md:block">
                                        <p className={`text-sm font-medium ${
                                            currentStep === step.id ? 'text-foreground' : 'text-foreground-muted'
                                        }`}>
                                            {step.title}
                                        </p>
                                    </div>
                                </div>
                                {index < steps.length - 1 && (
                                    <div
                                        className={`mx-4 h-0.5 w-12 md:w-24 transition-all ${
                                            completedSteps.has(step.id) ? 'bg-primary' : 'bg-border'
                                        }`}
                                    />
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            {/* Content */}
            <div className="mx-auto max-w-2xl px-6 py-12">
                {currentStep === 'welcome' && (
                    <WelcomeStep
                        userName={userName}
                        hasLocalhost={hasLocalhost}
                        onNext={() => {
                            if (hasLocalhost) {
                                // Skip server step if localhost is available
                                markStepComplete('server');
                                setCurrentStep('git');
                            } else {
                                setCurrentStep('server');
                            }
                        }}
                        onSkip={handleSkip}
                    />
                )}

                {currentStep === 'server' && (
                    <ServerStep
                        serverName={serverName}
                        setServerName={setServerName}
                        serverIp={serverIp}
                        onIpChange={handleIpChange}
                        ipError={ipError}
                        serverPort={serverPort}
                        onPortChange={handlePortChange}
                        portError={portError}
                        serverUser={serverUser}
                        setServerUser={setServerUser}
                        useExisting={useExistingServer}
                        setUseExisting={setUseExistingServer}
                        selectedServerId={selectedServerId}
                        setSelectedServerId={setSelectedServerId}
                        existingServers={existingServers}
                        privateKeys={privateKeys}
                        keyMode={keyMode}
                        setKeyMode={setKeyMode}
                        selectedKeyId={selectedKeyId}
                        setSelectedKeyId={setSelectedKeyId}
                        privateKeyContent={privateKeyContent}
                        onPrivateKeyChange={handlePrivateKeyChange}
                        privateKeyError={privateKeyError}
                        onNext={handleServerSubmit}
                        onBack={() => setCurrentStep('welcome')}
                        onSkip={handleSkip}
                        isSubmitting={isSubmitting}
                    />
                )}

                {currentStep === 'git' && (
                    <GitStep
                        githubApps={githubApps}
                        selectedGithubAppId={selectedGithubAppId}
                        setSelectedGithubAppId={setSelectedGithubAppId}
                        onNext={handleGitSubmit}
                        onBack={() => setCurrentStep('server')}
                        onSkip={handleSkip}
                    />
                )}

                {currentStep === 'deploy' && (
                    <DeployStep
                        appName={appName}
                        setAppName={setAppName}
                        gitRepository={gitRepository}
                        setGitRepository={setGitRepository}
                        gitBranch={gitBranch}
                        setGitBranch={setGitBranch}
                        onNext={handleDeploySubmit}
                        onBack={() => setCurrentStep('git')}
                        onSkip={handleSkip}
                    />
                )}

                {currentStep === 'complete' && (
                    <CompleteStep />
                )}
            </div>
        </div>
    );
}

interface WelcomeStepProps {
    userName?: string;
    hasLocalhost?: boolean;
    onNext: () => void;
    onSkip: () => void;
}

function WelcomeStep({ userName, hasLocalhost, onNext, onSkip }: WelcomeStepProps) {
    return (
        <Card>
            <CardContent className="p-12 text-center">
                <div className="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-primary/10 mb-6">
                    <Sparkles className="h-10 w-10 text-primary" />
                </div>
                <h1 className="text-3xl font-bold text-foreground mb-4">
                    Welcome to Saturn Platform{userName ? `, ${userName}` : ''}!
                </h1>
                <p className="text-lg text-foreground-muted mb-8 max-w-md mx-auto">
                    {hasLocalhost
                        ? "Your localhost server is ready. Let's deploy your first application!"
                        : "Let's get you started by setting up your first server and deploying your first application."}
                </p>
                {hasLocalhost && (
                    <div className="flex items-center justify-center gap-2 text-sm text-success mb-6">
                        <Check className="h-4 w-4" />
                        <span>Localhost server detected</span>
                    </div>
                )}
                <div className="flex justify-center gap-4">
                    <Button variant="outline" onClick={onSkip}>
                        Skip Setup
                    </Button>
                    <Button onClick={onNext}>
                        Get Started
                        <ChevronRight className="ml-2 h-4 w-4" />
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

interface ServerStepProps {
    serverName: string;
    setServerName: (value: string) => void;
    serverIp: string;
    onIpChange: (value: string) => void;
    ipError?: string;
    serverPort: string;
    onPortChange: (value: string) => void;
    portError?: string;
    serverUser: string;
    setServerUser: (value: string) => void;
    useExisting: boolean;
    setUseExisting: (value: boolean) => void;
    selectedServerId: string;
    setSelectedServerId: (value: string) => void;
    existingServers: Array<{ id: number; name: string; ip: string }>;
    // SSH Key props
    privateKeys: PrivateKey[];
    keyMode: KeyMode;
    setKeyMode: (value: KeyMode) => void;
    selectedKeyId: number | null;
    setSelectedKeyId: (value: number | null) => void;
    privateKeyContent: string;
    onPrivateKeyChange: (value: string) => void;
    privateKeyError?: string;
    // Actions
    onNext: () => void;
    onBack: () => void;
    onSkip: () => void;
    isSubmitting: boolean;
}

function ServerStep({
    serverName, setServerName, serverIp, onIpChange, ipError,
    serverPort, onPortChange, portError, serverUser, setServerUser,
    useExisting, setUseExisting, selectedServerId, setSelectedServerId,
    existingServers, privateKeys, keyMode, setKeyMode, selectedKeyId, setSelectedKeyId,
    privateKeyContent, onPrivateKeyChange, privateKeyError,
    onNext, onBack, onSkip, isSubmitting
}: ServerStepProps) {
    return (
        <Card>
            <CardContent className="p-8">
                <div className="flex items-center gap-3 mb-6">
                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                        <ServerIcon className="h-6 w-6 text-primary" />
                    </div>
                    <div>
                        <h2 className="text-2xl font-bold text-foreground">Connect Your Server</h2>
                        <p className="text-foreground-muted">Add a server to deploy your applications</p>
                    </div>
                </div>

                {existingServers.length > 0 && (
                    <div className="mb-6">
                        <label className="flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={useExisting}
                                onChange={(e) => setUseExisting(e.target.checked)}
                                className="rounded border-border"
                            />
                            <span className="text-sm text-foreground">Use existing server</span>
                        </label>
                    </div>
                )}

                {useExisting ? (
                    <div className="space-y-4">
                        <Select
                            value={selectedServerId}
                            onChange={(e) => setSelectedServerId(e.target.value)}
                        >
                            <option value="">Select a server</option>
                            {existingServers.map((server) => (
                                <option key={server.id} value={server.id}>
                                    {server.name} ({server.ip})
                                </option>
                            ))}
                        </Select>
                    </div>
                ) : (
                    <div className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-foreground mb-2">
                                Server Name
                            </label>
                            <Input
                                placeholder="e.g. Production Server"
                                value={serverName}
                                onChange={(e) => setServerName(e.target.value)}
                            />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-foreground mb-2">
                                    IP Address
                                </label>
                                <Input
                                    placeholder="e.g. 192.168.1.1"
                                    value={serverIp}
                                    onChange={(e) => onIpChange(e.target.value)}
                                    error={ipError}
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-foreground mb-2">
                                    Port
                                </label>
                                <Input
                                    placeholder="22"
                                    value={serverPort}
                                    onChange={(e) => onPortChange(e.target.value)}
                                    type="number"
                                    error={portError}
                                />
                            </div>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-foreground mb-2">
                                User
                            </label>
                            <Input
                                placeholder="root"
                                value={serverUser}
                                onChange={(e) => setServerUser(e.target.value)}
                            />
                        </div>

                        {/* SSH Key Selection */}
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
                                    value={privateKeyContent}
                                    onChange={(e) => onPrivateKeyChange(e.target.value)}
                                    rows={6}
                                    error={privateKeyError}
                                    className="font-mono text-sm"
                                />
                            )}
                            <p className="text-xs text-foreground-muted">
                                {keyMode === 'existing'
                                    ? 'Select an SSH key from your team\'s key storage.'
                                    : 'Paste your private SSH key for server authentication.'}
                            </p>
                        </div>
                    </div>
                )}

                <div className="mt-8 flex justify-between">
                    <Button variant="outline" onClick={onBack}>
                        <ChevronLeft className="mr-2 h-4 w-4" />
                        Back
                    </Button>
                    <div className="flex gap-3">
                        <Button variant="outline" onClick={onSkip}>Skip</Button>
                        <Button onClick={onNext} disabled={isSubmitting}>
                            {isSubmitting ? 'Creating...' : 'Continue'}
                            {!isSubmitting && <ChevronRight className="ml-2 h-4 w-4" />}
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

interface GitStepProps {
    githubApps: GithubApp[];
    selectedGithubAppId: string;
    setSelectedGithubAppId: (value: string) => void;
    onNext: () => void;
    onBack: () => void;
    onSkip: () => void;
}

function GitStep({
    githubApps, selectedGithubAppId, setSelectedGithubAppId,
    onNext, onBack, onSkip
}: GitStepProps) {
    const hasGithubApp = githubApps.length > 0;

    return (
        <Card>
            <CardContent className="p-8">
                <div className="flex items-center gap-3 mb-6">
                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                        <GitBranch className="h-6 w-6 text-primary" />
                    </div>
                    <div>
                        <h2 className="text-2xl font-bold text-foreground">Connect Git Source</h2>
                        <p className="text-foreground-muted">Link your GitHub to deploy repositories</p>
                    </div>
                </div>

                {hasGithubApp ? (
                    <div className="space-y-4">
                        <div className="flex items-center gap-2 text-sm text-success mb-4">
                            <Check className="h-4 w-4" />
                            <span>GitHub App connected</span>
                        </div>
                        {githubApps.length > 1 && (
                            <div>
                                <label className="block text-sm font-medium text-foreground mb-2">
                                    Select GitHub App
                                </label>
                                <Select
                                    value={selectedGithubAppId}
                                    onChange={(e) => setSelectedGithubAppId(e.target.value)}
                                >
                                    {githubApps.map((app) => (
                                        <option key={app.id} value={app.id}>
                                            {app.name}
                                        </option>
                                    ))}
                                </Select>
                            </div>
                        )}
                        {githubApps.length === 1 && (
                            <div className="bg-background-secondary p-4 rounded-lg">
                                <div className="flex items-center gap-3">
                                    <Github className="h-6 w-6" />
                                    <span className="font-medium">{githubApps[0].name}</span>
                                </div>
                            </div>
                        )}
                    </div>
                ) : (
                    <div className="space-y-4">
                        <div className="flex items-start gap-4 p-4 bg-warning/10 rounded-lg">
                            <AlertCircle className="h-5 w-5 text-warning flex-shrink-0 mt-0.5" />
                            <div>
                                <p className="font-medium text-foreground">GitHub App Required</p>
                                <p className="text-sm text-foreground-muted mt-1">
                                    To access your GitHub repositories, you need to create and install a GitHub App.
                                </p>
                            </div>
                        </div>
                        <div className="text-center py-4">
                            <Github className="h-12 w-12 mx-auto text-foreground-muted mb-4" />
                            <div className="flex justify-center gap-3">
                                <Link href="/sources/github/create">
                                    <Button className="gap-2">
                                        <Plus className="h-4 w-4" />
                                        Create GitHub App
                                    </Button>
                                </Link>
                                <a
                                    href="https://docs.github.com/en/developers/apps/building-github-apps/creating-a-github-app"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <Button variant="ghost" className="gap-2">
                                        Learn More
                                        <ExternalLink className="h-4 w-4" />
                                    </Button>
                                </a>
                            </div>
                        </div>
                    </div>
                )}

                <div className="mt-8 flex justify-between">
                    <Button variant="outline" onClick={onBack}>
                        <ChevronLeft className="mr-2 h-4 w-4" />
                        Back
                    </Button>
                    <div className="flex gap-3">
                        <Button variant="outline" onClick={onSkip}>Skip</Button>
                        {hasGithubApp && (
                            <Button onClick={onNext}>
                                Continue
                                <ChevronRight className="ml-2 h-4 w-4" />
                            </Button>
                        )}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

interface DeployStepProps {
    appName: string;
    setAppName: (value: string) => void;
    gitRepository: string;
    setGitRepository: (value: string) => void;
    gitBranch: string;
    setGitBranch: (value: string) => void;
    onNext: () => void;
    onBack: () => void;
    onSkip: () => void;
}

function DeployStep({
    appName, setAppName, gitRepository, setGitRepository, gitBranch, setGitBranch,
    onNext, onBack, onSkip
}: DeployStepProps) {
    return (
        <Card>
            <CardContent className="p-8">
                <div className="flex items-center gap-3 mb-6">
                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                        <Rocket className="h-6 w-6 text-primary" />
                    </div>
                    <div>
                        <h2 className="text-2xl font-bold text-foreground">Deploy Your First App</h2>
                        <p className="text-foreground-muted">Configure and deploy your application</p>
                    </div>
                </div>

                <div className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-foreground mb-2">
                            Application Name
                        </label>
                        <Input
                            placeholder="e.g. My Awesome App"
                            value={appName}
                            onChange={(e) => setAppName(e.target.value)}
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-foreground mb-2">
                            Git Repository URL
                        </label>
                        <Input
                            placeholder="e.g. https://github.com/username/repo"
                            value={gitRepository}
                            onChange={(e) => setGitRepository(e.target.value)}
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-foreground mb-2">
                            Branch
                        </label>
                        <Input
                            placeholder="main"
                            value={gitBranch}
                            onChange={(e) => setGitBranch(e.target.value)}
                        />
                    </div>
                </div>

                <div className="mt-8 flex justify-between">
                    <Button variant="outline" onClick={onBack}>
                        <ChevronLeft className="mr-2 h-4 w-4" />
                        Back
                    </Button>
                    <div className="flex gap-3">
                        <Button variant="outline" onClick={onSkip}>Skip</Button>
                        <Button onClick={onNext}>
                            <Rocket className="mr-2 h-4 w-4" />
                            Deploy Now
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function CompleteStep() {
    return (
        <Card>
            <CardContent className="p-12 text-center">
                <div className="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-success/10 mb-6">
                    <Check className="h-10 w-10 text-success" />
                </div>
                <h1 className="text-3xl font-bold text-foreground mb-4">
                    You're All Set!
                </h1>
                <p className="text-lg text-foreground-muted mb-8 max-w-md mx-auto">
                    Your first application is now deploying. You can monitor the progress from your dashboard.
                </p>
                <div className="flex justify-center gap-4">
                    <Button onClick={() => router.visit('/applications')}>
                        View Applications
                    </Button>
                    <Button variant="outline" onClick={() => router.visit('/dashboard')}>
                        Go to Dashboard
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}
