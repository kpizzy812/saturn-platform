import { useState } from 'react';
import { router, Link } from '@inertiajs/react';
import { Button, Input, Select, useConfirm } from '@/components/ui';
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
import Dashboard from '@/pages/Dashboard';
import { RepoSelector, type RepoSelectorResult } from '@/components/features/RepoSelector';

interface GithubApp {
    id: number;
    uuid: string;
    name: string;
    installation_id: number | null;
}

interface Project {
    id: number;
    uuid?: string;
    name: string;
    updated_at?: string;
    environments?: { name?: string; applications?: unknown[]; databases?: unknown[]; services?: unknown[] }[];
}

interface Props {
    userName?: string;
    githubApps?: GithubApp[];
    projects?: Project[];
}

type Step = 'welcome' | 'git' | 'deploy' | 'complete';

const STEPS: { id: Step; label: string }[] = [
    { id: 'welcome', label: 'Welcome' },
    { id: 'git', label: 'Git' },
    { id: 'deploy', label: 'Deploy' },
    { id: 'complete', label: 'Done' },
];

export default function BoardingIndex({ userName, githubApps = [], projects = [] }: Props) {
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

        // Redirect to GitHub App creation page with return context
        router.visit('/sources/github/create?from=boarding');
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
            onError: (errors) => {
                const msg = Object.values(errors).flat().join('. ') || 'Deploy failed. Please check your settings and try again.';
                addToast('error', msg);
            },
            preserveScroll: true,
        });
    };

    const currentStepIndex = STEPS.findIndex(s => s.id === currentStep);
    const isWideStep = currentStep === 'git' || currentStep === 'deploy';

    return (
        <>
            {/* Blurred Dashboard background */}
            <div
                className="fixed inset-0 overflow-hidden pointer-events-none select-none"
                aria-hidden="true"
            >
                <div className="blur-sm brightness-[0.3] scale-[1.01]">
                    <Dashboard projects={projects} />
                </div>
            </div>

            {/* Overlay backdrop */}
            <div className="fixed inset-0 bg-black/40 backdrop-blur-[2px] z-40" />

            {/* Onboarding Modal */}
            <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div
                    className={`w-full transition-all duration-300 bg-background/90 backdrop-blur-xl border border-border/50 rounded-2xl shadow-2xl ${
                        isWideStep ? 'max-w-2xl' : 'max-w-lg'
                    }`}
                >
                    {/* Modal Header: step indicator */}
                    <div className="flex items-center justify-between px-6 pt-5 pb-4 border-b border-border/40">
                        <div className="flex items-center gap-2">
                            {STEPS.map((step, index) => (
                                <div key={step.id} className="flex items-center">
                                    <div className="flex items-center gap-1.5">
                                        <div
                                            className={`flex h-6 w-6 items-center justify-center rounded-full border transition-all duration-200 text-xs font-medium ${
                                                completedSteps.has(step.id)
                                                    ? 'border-primary bg-primary text-white'
                                                    : currentStep === step.id
                                                    ? 'border-primary bg-primary/10 text-primary'
                                                    : 'border-border bg-background-secondary text-foreground-muted'
                                            }`}
                                        >
                                            {completedSteps.has(step.id) ? (
                                                <Check className="h-3 w-3" />
                                            ) : (
                                                <span>{index + 1}</span>
                                            )}
                                        </div>
                                        <span
                                            className={`hidden sm:block text-xs font-medium transition-colors duration-200 ${
                                                currentStep === step.id
                                                    ? 'text-foreground'
                                                    : 'text-foreground-muted'
                                            }`}
                                        >
                                            {step.label}
                                        </span>
                                    </div>
                                    {index < STEPS.length - 1 && (
                                        <div
                                            className={`mx-2 h-px w-6 sm:w-8 transition-all duration-300 ${
                                                completedSteps.has(step.id) ? 'bg-primary' : 'bg-border/60'
                                            }`}
                                        />
                                    )}
                                </div>
                            ))}
                        </div>
                        <span className="text-xs text-foreground-muted">
                            {currentStepIndex + 1} / {STEPS.length}
                        </span>
                    </div>

                    {/* Modal Body: step content */}
                    <div className="px-6 py-6">
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
                                githubApps={githubApps}
                                setSelectedGithubAppId={setSelectedGithubAppId}
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
            </div>
        </>
    );
}

interface WelcomeStepProps {
    userName?: string;
    onNext: () => void;
    onSkip: () => void;
}

function WelcomeStep({ userName, onNext, onSkip }: WelcomeStepProps) {
    return (
        <div className="text-center">
            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 mb-5">
                <Sparkles className="h-8 w-8 text-primary" />
            </div>
            <h1 className="text-2xl font-bold text-foreground mb-3">
                Welcome to Saturn{userName ? `, ${userName}` : ''}
            </h1>
            <p className="text-sm text-foreground-muted mb-7 max-w-sm mx-auto">
                Saturn automatically manages servers for your deployments.
                Let's connect your Git source and deploy your first application.
            </p>
            <div className="flex justify-center gap-3">
                <Button variant="outline" onClick={onSkip}>
                    Skip Setup
                </Button>
                <Button onClick={onNext}>
                    Get Started
                    <ChevronRight className="ml-2 h-4 w-4" />
                </Button>
            </div>
        </div>
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
        <div>
            <div className="flex items-center gap-3 mb-5">
                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                    <GitBranch className="h-5 w-5 text-primary" />
                </div>
                <div>
                    <h2 className="text-xl font-bold text-foreground">Connect Git Source</h2>
                    <p className="text-sm text-foreground-muted">Link your GitHub to deploy repositories</p>
                </div>
            </div>

            {hasGithubApp ? (
                <div className="space-y-3">
                    <div className="flex items-center gap-2 text-sm text-success">
                        <Check className="h-4 w-4" />
                        <span>GitHub App connected</span>
                    </div>
                    {githubApps.length > 1 && (
                        <div>
                            <label className="block text-sm font-medium text-foreground mb-1.5">
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
                        <div className="bg-background-secondary p-3 rounded-lg">
                            <div className="flex items-center gap-3">
                                <Github className="h-5 w-5" />
                                <span className="font-medium text-sm">{githubApps[0].name}</span>
                            </div>
                        </div>
                    )}
                </div>
            ) : (
                <div className="space-y-3">
                    <div className="flex items-start gap-3 p-3 bg-warning/10 rounded-lg">
                        <AlertCircle className="h-4 w-4 text-warning flex-shrink-0 mt-0.5" />
                        <div>
                            <p className="text-sm font-medium text-foreground">GitHub App Required</p>
                            <p className="text-xs text-foreground-muted mt-0.5">
                                To access your GitHub repositories, you need to create and install a GitHub App.
                            </p>
                        </div>
                    </div>
                    <div className="flex justify-center gap-3 py-2">
                        <Link href="/sources/github/create?from=boarding">
                            <Button className="gap-2" size="sm">
                                <Plus className="h-4 w-4" />
                                Create GitHub App
                            </Button>
                        </Link>
                        <a
                            href="https://docs.github.com/en/developers/apps/building-github-apps/creating-a-github-app"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <Button variant="ghost" className="gap-2" size="sm">
                                Learn More
                                <ExternalLink className="h-4 w-4" />
                            </Button>
                        </a>
                    </div>
                </div>
            )}

            <div className="mt-6 flex justify-between">
                <Button variant="outline" onClick={onBack} size="sm">
                    <ChevronLeft className="mr-2 h-4 w-4" />
                    Back
                </Button>
                <div className="flex gap-2">
                    <Button variant="outline" onClick={onSkip} size="sm">Skip</Button>
                    {hasGithubApp && (
                        <Button onClick={onNext} size="sm">
                            Continue
                            <ChevronRight className="ml-2 h-4 w-4" />
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
}

interface DeployStepProps {
    appName: string;
    setAppName: (value: string) => void;
    gitRepository: string;
    setGitRepository: (value: string) => void;
    gitBranch: string;
    setGitBranch: (value: string) => void;
    githubApps: GithubApp[];
    setSelectedGithubAppId: (value: string) => void;
    onNext: () => void;
    onBack: () => void;
    onSkip: () => void;
}

function DeployStep({
    appName, setAppName, gitRepository, setGitRepository, gitBranch, setGitBranch,
    githubApps, setSelectedGithubAppId,
    onNext, onBack, onSkip
}: DeployStepProps) {
    const handleRepoSelected = (result: RepoSelectorResult) => {
        setGitRepository(result.gitRepository);
        setGitBranch(result.gitBranch);
        if (result.githubAppId) {
            setSelectedGithubAppId(result.githubAppId.toString());
        }
        // Auto-fill app name from repo name if empty
        if (!appName && result.repoName) {
            setAppName(result.repoName);
        }
    };

    return (
        <div>
            <div className="flex items-center gap-3 mb-5">
                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                    <Rocket className="h-5 w-5 text-primary" />
                </div>
                <div>
                    <h2 className="text-xl font-bold text-foreground">Deploy Your First App</h2>
                    <p className="text-sm text-foreground-muted">Configure and deploy your application</p>
                </div>
            </div>

            <div className="space-y-3">
                <div>
                    <label className="block text-sm font-medium text-foreground mb-1.5">
                        Application Name
                    </label>
                    <Input
                        placeholder="e.g. My Awesome App"
                        value={appName}
                        onChange={(e) => setAppName(e.target.value)}
                    />
                </div>

                <RepoSelector
                    githubApps={githubApps}
                    onRepoSelected={handleRepoSelected}
                    value={gitRepository}
                    branch={gitBranch}
                />
            </div>

            <div className="mt-6 flex justify-between">
                <Button variant="outline" onClick={onBack} size="sm">
                    <ChevronLeft className="mr-2 h-4 w-4" />
                    Back
                </Button>
                <div className="flex gap-2">
                    <Button variant="outline" onClick={onSkip} size="sm">Skip</Button>
                    <Button onClick={onNext} size="sm">
                        <Rocket className="mr-2 h-4 w-4" />
                        Deploy Now
                    </Button>
                </div>
            </div>
        </div>
    );
}

function CompleteStep() {
    return (
        <div className="text-center">
            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-success/10 mb-5">
                <Check className="h-8 w-8 text-success" />
            </div>
            <h1 className="text-2xl font-bold text-foreground mb-3">
                You're All Set!
            </h1>
            <p className="text-sm text-foreground-muted mb-7 max-w-sm mx-auto">
                Your first application is now deploying. You can monitor the progress from your dashboard.
            </p>
            <div className="flex justify-center gap-3">
                <Button onClick={() => router.visit('/applications')}>
                    View Applications
                </Button>
                <Button variant="outline" onClick={() => router.visit('/dashboard')}>
                    Go to Dashboard
                </Button>
            </div>
        </div>
    );
}
