import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Card, CardContent, Button, Input, Select } from '@/components/ui';
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
    Globe,
    Database,
    Package,
} from 'lucide-react';

interface Props {
    userName?: string;
    existingServers?: any[];
    existingGitSources?: any[];
}

type Step = 'welcome' | 'server' | 'git' | 'deploy' | 'complete';

export default function BoardingIndex({ userName, existingServers = [], existingGitSources = [] }: Props) {
    const [currentStep, setCurrentStep] = useState<Step>('welcome');
    const [completedSteps, setCompletedSteps] = useState<Set<Step>>(new Set());

    // Server form state
    const [serverName, setServerName] = useState('');
    const [serverIp, setServerIp] = useState('');
    const [serverPort, setServerPort] = useState('22');
    const [serverUser, setServerUser] = useState('root');
    const [useExistingServer, setUseExistingServer] = useState(false);
    const [selectedServerId, setSelectedServerId] = useState('');

    // Git source form state
    const [useExistingGit, setUseExistingGit] = useState(false);
    const [selectedGitSourceId, setSelectedGitSourceId] = useState('');

    // App deployment form state
    const [appName, setAppName] = useState('');
    const [gitRepository, setGitRepository] = useState('');
    const [gitBranch, setGitBranch] = useState('main');
    const [deployType, setDeployType] = useState<'simple' | 'custom'>('simple');

    const steps: { id: Step; title: string; description: string }[] = [
        { id: 'welcome', title: 'Welcome', description: 'Get started with Saturn Platform' },
        { id: 'server', title: 'Add Server', description: 'Connect your first server' },
        { id: 'git', title: 'Connect Git', description: 'Link your GitHub account' },
        { id: 'deploy', title: 'Deploy App', description: 'Deploy your first application' },
        { id: 'complete', title: 'Complete', description: 'You are all set!' },
    ];

    const currentStepIndex = steps.findIndex(s => s.id === currentStep);

    const markStepComplete = (step: Step) => {
        setCompletedSteps(prev => new Set([...prev, step]));
    };

    const handleSkip = () => {
        router.post('/boarding/skip');
    };

    const handleServerSubmit = () => {
        if (useExistingServer && selectedServerId) {
            markStepComplete('server');
            setCurrentStep('git');
            return;
        }

        if (!serverName || !serverIp) {
            alert('Please fill in all required fields');
            return;
        }

        // TODO: Create server via API
        router.post('/servers', {
            name: serverName,
            ip: serverIp,
            port: serverPort,
            user: serverUser,
        }, {
            onSuccess: () => {
                markStepComplete('server');
                setCurrentStep('git');
            },
            preserveScroll: true,
        });
    };

    const handleGitSubmit = () => {
        if (useExistingGit && selectedGitSourceId) {
            markStepComplete('git');
            setCurrentStep('deploy');
            return;
        }

        // Redirect to GitHub OAuth
        window.location.href = '/auth/github/redirect?onboarding=true';
    };

    const handleDeploySubmit = () => {
        if (!appName || !gitRepository) {
            alert('Please fill in all required fields');
            return;
        }

        // TODO: Create and deploy application via API
        router.post('/applications', {
            name: appName,
            git_repository: gitRepository,
            git_branch: gitBranch,
            auto_deploy: true,
        }, {
            onSuccess: () => {
                markStepComplete('deploy');
                setCurrentStep('complete');
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
                        onNext={() => setCurrentStep('server')}
                        onSkip={handleSkip}
                    />
                )}

                {currentStep === 'server' && (
                    <ServerStep
                        serverName={serverName}
                        setServerName={setServerName}
                        serverIp={serverIp}
                        setServerIp={setServerIp}
                        serverPort={serverPort}
                        setServerPort={setServerPort}
                        serverUser={serverUser}
                        setServerUser={setServerUser}
                        useExisting={useExistingServer}
                        setUseExisting={setUseExistingServer}
                        selectedServerId={selectedServerId}
                        setSelectedServerId={setSelectedServerId}
                        existingServers={existingServers}
                        onNext={handleServerSubmit}
                        onBack={() => setCurrentStep('welcome')}
                        onSkip={handleSkip}
                    />
                )}

                {currentStep === 'git' && (
                    <GitStep
                        useExisting={useExistingGit}
                        setUseExisting={setUseExistingGit}
                        selectedGitSourceId={selectedGitSourceId}
                        setSelectedGitSourceId={setSelectedGitSourceId}
                        existingGitSources={existingGitSources}
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
                        deployType={deployType}
                        setDeployType={setDeployType}
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
    onNext: () => void;
    onSkip: () => void;
}

function WelcomeStep({ userName, onNext, onSkip }: WelcomeStepProps) {
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
                    Let's get you started by setting up your first server and deploying your first application.
                    This will only take a few minutes.
                </p>
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
    setServerIp: (value: string) => void;
    serverPort: string;
    setServerPort: (value: string) => void;
    serverUser: string;
    setServerUser: (value: string) => void;
    useExisting: boolean;
    setUseExisting: (value: boolean) => void;
    selectedServerId: string;
    setSelectedServerId: (value: string) => void;
    existingServers: any[];
    onNext: () => void;
    onBack: () => void;
    onSkip: () => void;
}

function ServerStep({
    serverName, setServerName, serverIp, setServerIp, serverPort, setServerPort,
    serverUser, setServerUser, useExisting, setUseExisting, selectedServerId, setSelectedServerId,
    existingServers, onNext, onBack, onSkip
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
                                    onChange={(e) => setServerIp(e.target.value)}
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-foreground mb-2">
                                    Port
                                </label>
                                <Input
                                    placeholder="22"
                                    value={serverPort}
                                    onChange={(e) => setServerPort(e.target.value)}
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
                    </div>
                )}

                <div className="mt-8 flex justify-between">
                    <Button variant="outline" onClick={onBack}>
                        <ChevronLeft className="mr-2 h-4 w-4" />
                        Back
                    </Button>
                    <div className="flex gap-3">
                        <Button variant="outline" onClick={onSkip}>Skip</Button>
                        <Button onClick={onNext}>
                            Continue
                            <ChevronRight className="ml-2 h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

interface GitStepProps {
    useExisting: boolean;
    setUseExisting: (value: boolean) => void;
    selectedGitSourceId: string;
    setSelectedGitSourceId: (value: string) => void;
    existingGitSources: any[];
    onNext: () => void;
    onBack: () => void;
    onSkip: () => void;
}

function GitStep({
    useExisting, setUseExisting, selectedGitSourceId, setSelectedGitSourceId,
    existingGitSources, onNext, onBack, onSkip
}: GitStepProps) {
    return (
        <Card>
            <CardContent className="p-8">
                <div className="flex items-center gap-3 mb-6">
                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                        <GitBranch className="h-6 w-6 text-primary" />
                    </div>
                    <div>
                        <h2 className="text-2xl font-bold text-foreground">Connect Git Source</h2>
                        <p className="text-foreground-muted">Link your GitHub account to deploy repositories</p>
                    </div>
                </div>

                {existingGitSources.length > 0 && (
                    <div className="mb-6">
                        <label className="flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={useExisting}
                                onChange={(e) => setUseExisting(e.target.checked)}
                                className="rounded border-border"
                            />
                            <span className="text-sm text-foreground">Use existing Git source</span>
                        </label>
                    </div>
                )}

                {useExisting ? (
                    <div className="space-y-4">
                        <Select
                            value={selectedGitSourceId}
                            onChange={(e) => setSelectedGitSourceId(e.target.value)}
                        >
                            <option value="">Select a Git source</option>
                            {existingGitSources.map((source: any) => (
                                <option key={source.id} value={source.id}>
                                    {source.name} ({source.type})
                                </option>
                            ))}
                        </Select>
                    </div>
                ) : (
                    <div className="text-center py-8">
                        <Github className="h-16 w-16 mx-auto text-foreground-muted mb-4" />
                        <p className="text-foreground-muted mb-6">
                            Connect your GitHub account to access your repositories
                        </p>
                        <Button onClick={onNext} className="gap-2">
                            <Github className="h-4 w-4" />
                            Connect GitHub
                        </Button>
                    </div>
                )}

                <div className="mt-8 flex justify-between">
                    <Button variant="outline" onClick={onBack}>
                        <ChevronLeft className="mr-2 h-4 w-4" />
                        Back
                    </Button>
                    <div className="flex gap-3">
                        <Button variant="outline" onClick={onSkip}>Skip</Button>
                        {useExisting && (
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
    deployType: 'simple' | 'custom';
    setDeployType: (value: 'simple' | 'custom') => void;
    onNext: () => void;
    onBack: () => void;
    onSkip: () => void;
}

function DeployStep({
    appName, setAppName, gitRepository, setGitRepository, gitBranch, setGitBranch,
    deployType, setDeployType, onNext, onBack, onSkip
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
