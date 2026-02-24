import { useState } from 'react';
import { router, Link } from '@inertiajs/react';
import { Card, CardContent, Button, Input, Select, useConfirm } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import {
    Check,
    ChevronRight,
    ChevronLeft,
    GitBranch,
    Rocket,
    Sparkles,
    Github,
    Plus,
    ExternalLink,
    AlertCircle,
} from 'lucide-react';

interface GithubApp {
    id: number;
    uuid: string;
    name: string;
    installation_id: number | null;
}

interface Props {
    userName?: string;
    githubApps?: GithubApp[];
}

type Step = 'welcome' | 'git' | 'deploy' | 'complete';

export default function BoardingIndex({ userName, githubApps = [] }: Props) {
    const confirm = useConfirm();
    const { addToast } = useToast();

    const [currentStep, setCurrentStep] = useState<Step>('welcome');
    const [completedSteps, setCompletedSteps] = useState<Set<Step>>(new Set());

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

        router.post('/boarding/deploy', {
            name: appName,
            git_repository: gitRepository,
            git_branch: gitBranch,
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
                <div className="mx-auto max-w-4xl">
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
            <div className="mx-auto max-w-2xl py-8">
                {currentStep === 'welcome' && (
                    <WelcomeStep
                        userName={userName}
                        onNext={() => setCurrentStep('git')}
                        onSkip={handleSkip}
                    />
                )}

                {currentStep === 'git' && (
                    <GitStep
                        githubApps={githubApps}
                        selectedGithubAppId={selectedGithubAppId}
                        setSelectedGithubAppId={setSelectedGithubAppId}
                        onNext={handleGitSubmit}
                        onBack={() => setCurrentStep('welcome')}
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
                    Saturn automatically manages servers for your deployments.
                    Let's connect your Git source and deploy your first application!
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
