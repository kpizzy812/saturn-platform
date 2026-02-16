import { useState, useEffect, useCallback } from 'react';
import { Link, router } from '@inertiajs/react';
import { AuthLayout } from '@/components/layout';
import { Card, CardContent, Button, Badge, Input, Select } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import {
    Github,
    Gitlab,
    Search,
    ArrowLeft,
    ArrowRight,
    CheckCircle,
    GitBranch,
    Zap,
    Loader2,
    AlertCircle,
    Plus,
    ExternalLink,
} from 'lucide-react';
import { Bitbucket } from '@/components/icons/Bitbucket';

interface Props {
    provider?: 'github' | 'gitlab' | 'bitbucket';
    githubApps?: GithubApp[];
}

interface GithubApp {
    id: number;
    uuid: string;
    name: string;
    installation_id: number | null;
}

interface Repository {
    id: number;
    name: string;
    full_name: string;
    description: string | null;
    private: boolean;
    default_branch: string;
    language: string | null;
    updated_at: string;
}

interface Branch {
    name: string;
    protected: boolean;
}

export default function OnboardingConnectRepo({ provider = 'github', githubApps = [] }: Props) {
    const { addToast } = useToast();
    const [selectedProvider, setSelectedProvider] = useState(provider);
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedRepo, setSelectedRepo] = useState<Repository | null>(null);
    const [selectedBranch, setSelectedBranch] = useState('');
    const [buildCommand, setBuildCommand] = useState('npm run build');
    const [startCommand, setStartCommand] = useState('npm start');
    const [rootDirectory, setRootDirectory] = useState('./');

    // GitHub App state
    const [selectedGithubApp, setSelectedGithubApp] = useState<GithubApp | null>(
        githubApps.length > 0 ? githubApps[0] : null
    );
    const [repositories, setRepositories] = useState<Repository[]>([]);
    const [branches, setBranches] = useState<Branch[]>([]);
    const [isLoadingRepos, setIsLoadingRepos] = useState(false);
    const [isLoadingBranches, setIsLoadingBranches] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const loadRepositories = useCallback(async () => {
        if (!selectedGithubApp) return;

        setIsLoadingRepos(true);
        setError(null);
        setRepositories([]);

        try {
            const response = await fetch(`/web-api/github-apps/${selectedGithubApp.id}/repositories`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                const data = await response.json();
                throw new Error(data.message || 'Failed to load repositories');
            }

            const data = await response.json();
            setRepositories(data.repositories || []);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to load repositories');
        } finally {
            setIsLoadingRepos(false);
        }
    }, [selectedGithubApp]);

    const loadBranches = useCallback(async (repo: Repository) => {
        if (!selectedGithubApp || !repo) return;

        setIsLoadingBranches(true);
        setBranches([]);

        try {
            const [owner, repoName] = repo.full_name.split('/');
            const response = await fetch(
                `/web-api/github-apps/${selectedGithubApp.id}/repositories/${owner}/${repoName}/branches`,
                {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                }
            );

            if (!response.ok) {
                throw new Error('Failed to load branches');
            }

            const data = await response.json();
            setBranches(data.branches || []);

            // Auto-select default branch
            if (repo.default_branch) {
                setSelectedBranch(repo.default_branch);
            }
        } catch {
            addToast('error', 'Failed to load branches');
        } finally {
            setIsLoadingBranches(false);
        }
    }, [selectedGithubApp, addToast]);

    // Load repositories when GitHub App is selected
    useEffect(() => {
        if (selectedGithubApp && selectedProvider === 'github') {
            loadRepositories();
        }
    }, [selectedGithubApp, selectedProvider, loadRepositories]);

    // Load branches when repository is selected
    useEffect(() => {
        if (selectedRepo && selectedGithubApp) {
            loadBranches(selectedRepo);
        }
    }, [selectedRepo, selectedGithubApp, loadBranches]);

    // Filter repositories by search
    const filteredRepos = repositories.filter(
        (repo) =>
            repo.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            repo.description?.toLowerCase().includes(searchQuery.toLowerCase())
    );

    const handleDeploy = () => {
        if (!selectedRepo || !selectedBranch) return;

        router.post('/applications/deploy', {
            repository: selectedRepo.full_name,
            branch: selectedBranch,
            build_command: buildCommand,
            start_command: startCommand,
            root_directory: rootDirectory,
            github_app_id: selectedGithubApp?.id,
        });
    };

    const _getProviderIcon = (prov: string) => {
        switch (prov) {
            case 'github':
                return <Github className="h-5 w-5" />;
            case 'gitlab':
                return <Gitlab className="h-5 w-5" />;
            default:
                return <FolderGit className="h-5 w-5" />;
        }
    };

    const getLanguageColor = (language: string | null) => {
        const colors: Record<string, string> = {
            TypeScript: 'bg-blue-500',
            JavaScript: 'bg-yellow-500',
            Python: 'bg-blue-400',
            Ruby: 'bg-red-500',
            Go: 'bg-cyan-500',
            Rust: 'bg-orange-500',
            HTML: 'bg-orange-400',
            CSS: 'bg-purple-500',
            PHP: 'bg-indigo-500',
            Java: 'bg-red-600',
        };
        return colors[language || ''] || 'bg-foreground-muted';
    };

    // Check if GitHub App needs to be created
    const needsGithubApp = selectedProvider === 'github' && githubApps.length === 0;

    return (
        <AuthLayout title="Connect Repository">
            <div className="min-h-screen bg-background py-12">
                <div className="mx-auto max-w-4xl">
                    {/* Header */}
                    <div className="mb-8">
                        <Link href="/onboarding/welcome">
                            <Button variant="ghost" size="sm" className="mb-4">
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Back
                            </Button>
                        </Link>
                        <h1 className="mb-2 text-3xl font-bold text-foreground">
                            Connect Repository
                        </h1>
                        <p className="text-foreground-muted">
                            Select a repository to deploy to Saturn
                        </p>
                    </div>

                    {/* Provider Selection */}
                    <Card className="mb-6">
                        <CardContent className="p-6">
                            <h3 className="mb-4 text-lg font-semibold text-foreground">
                                Select Git Provider
                            </h3>
                            <div className="grid gap-4 md:grid-cols-3">
                                <ProviderButton
                                    provider="github"
                                    selected={selectedProvider === 'github'}
                                    icon={<Github className="h-6 w-6" />}
                                    label="GitHub"
                                    connected={githubApps.length > 0}
                                    onClick={() => setSelectedProvider('github')}
                                />
                                <ProviderButton
                                    provider="gitlab"
                                    selected={selectedProvider === 'gitlab'}
                                    icon={<Gitlab className="h-6 w-6" />}
                                    label="GitLab"
                                    connected={false}
                                    onClick={() => setSelectedProvider('gitlab')}
                                />
                                <ProviderButton
                                    provider="bitbucket"
                                    selected={selectedProvider === 'bitbucket'}
                                    icon={<Bitbucket className="h-6 w-6" />}
                                    label="Bitbucket"
                                    connected={false}
                                    onClick={() => setSelectedProvider('bitbucket')}
                                />
                            </div>
                        </CardContent>
                    </Card>

                    {/* GitHub App Setup Required */}
                    {needsGithubApp && (
                        <Card className="mb-6">
                            <CardContent className="p-6">
                                <div className="flex items-start gap-4">
                                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-warning/10">
                                        <AlertCircle className="h-6 w-6 text-warning" />
                                    </div>
                                    <div className="flex-1">
                                        <h3 className="mb-2 text-lg font-semibold text-foreground">
                                            GitHub App Required
                                        </h3>
                                        <p className="mb-4 text-foreground-muted">
                                            To access your GitHub repositories, you need to create and install a GitHub App.
                                            This allows Saturn to securely access your code and set up automatic deployments.
                                        </p>
                                        <div className="flex gap-3">
                                            <Link href="/sources/github/create">
                                                <Button>
                                                    <Plus className="mr-2 h-4 w-4" />
                                                    Create GitHub App
                                                </Button>
                                            </Link>
                                            <a
                                                href="https://docs.github.com/en/developers/apps/building-github-apps/creating-a-github-app"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                <Button variant="ghost">
                                                    Learn More
                                                    <ExternalLink className="ml-2 h-4 w-4" />
                                                </Button>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* GitLab/Bitbucket Not Supported Yet */}
                    {(selectedProvider === 'gitlab' || selectedProvider === 'bitbucket') && (
                        <Card className="mb-6">
                            <CardContent className="p-6">
                                <div className="flex items-start gap-4">
                                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-info/10">
                                        <AlertCircle className="h-6 w-6 text-info" />
                                    </div>
                                    <div className="flex-1">
                                        <h3 className="mb-2 text-lg font-semibold text-foreground">
                                            Coming Soon
                                        </h3>
                                        <p className="text-foreground-muted">
                                            {selectedProvider === 'gitlab' ? 'GitLab' : 'Bitbucket'} integration
                                            is coming soon. For now, please use GitHub or deploy using a public
                                            repository URL.
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* GitHub App Selector (if multiple apps) */}
                    {selectedProvider === 'github' && githubApps.length > 1 && (
                        <Card className="mb-6">
                            <CardContent className="p-6">
                                <h3 className="mb-4 text-lg font-semibold text-foreground">
                                    Select GitHub App
                                </h3>
                                <Select
                                    value={selectedGithubApp?.id.toString() || ''}
                                    onChange={(e) => {
                                        const app = githubApps.find(a => a.id === parseInt(e.target.value));
                                        setSelectedGithubApp(app || null);
                                        setSelectedRepo(null);
                                        setSelectedBranch('');
                                    }}
                                >
                                    {githubApps.map((app) => (
                                        <option key={app.id} value={app.id}>
                                            {app.name}
                                        </option>
                                    ))}
                                </Select>
                            </CardContent>
                        </Card>
                    )}

                    {/* Repository Selection */}
                    {selectedProvider === 'github' && githubApps.length > 0 && (
                        <Card className="mb-6">
                            <CardContent className="p-6">
                                <div className="mb-4 flex items-center justify-between">
                                    <h3 className="text-lg font-semibold text-foreground">
                                        Select Repository
                                    </h3>
                                    {!isLoadingRepos && (
                                        <Badge variant="default">{repositories.length} repositories</Badge>
                                    )}
                                </div>

                                {error && (
                                    <div className="mb-4 rounded-lg bg-danger/10 p-4 text-danger">
                                        <div className="flex items-center gap-2">
                                            <AlertCircle className="h-4 w-4" />
                                            <span>{error}</span>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={loadRepositories}
                                            className="mt-2"
                                        >
                                            Try Again
                                        </Button>
                                    </div>
                                )}

                                {isLoadingRepos ? (
                                    <div className="flex items-center justify-center py-12">
                                        <Loader2 className="h-8 w-8 animate-spin text-primary" />
                                        <span className="ml-3 text-foreground-muted">Loading repositories...</span>
                                    </div>
                                ) : (
                                    <>
                                        <div className="mb-4">
                                            <div className="relative">
                                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                                                <Input
                                                    placeholder="Search repositories..."
                                                    value={searchQuery}
                                                    onChange={(e) => setSearchQuery(e.target.value)}
                                                    className="pl-10"
                                                />
                                            </div>
                                        </div>
                                        <div className="max-h-96 space-y-2 overflow-y-auto">
                                            {filteredRepos.length === 0 && !isLoadingRepos && (
                                                <div className="py-8 text-center text-foreground-muted">
                                                    {searchQuery ? 'No repositories match your search' : 'No repositories found'}
                                                </div>
                                            )}
                                            {filteredRepos.map((repo) => (
                                                <button
                                                    key={repo.id}
                                                    onClick={() => {
                                                        setSelectedRepo(repo);
                                                        setSelectedBranch(repo.default_branch);
                                                    }}
                                                    className={`w-full rounded-lg border p-4 text-left transition-all hover:border-primary/50 ${
                                                        selectedRepo?.id === repo.id
                                                            ? 'border-primary bg-primary/5'
                                                            : 'border-border bg-background-secondary'
                                                    }`}
                                                >
                                                    <div className="flex items-start justify-between">
                                                        <div className="flex-1">
                                                            <div className="mb-1 flex items-center gap-2">
                                                                <span className="font-semibold text-foreground">
                                                                    {repo.name}
                                                                </span>
                                                                {repo.private && (
                                                                    <Badge variant="default">Private</Badge>
                                                                )}
                                                                {repo.language && (
                                                                    <div className="flex items-center gap-1.5">
                                                                        <div
                                                                            className={`h-2 w-2 rounded-full ${getLanguageColor(
                                                                                repo.language
                                                                            )}`}
                                                                        />
                                                                        <span className="text-xs text-foreground-muted">
                                                                            {repo.language}
                                                                        </span>
                                                                    </div>
                                                                )}
                                                            </div>
                                                            {repo.description && (
                                                                <p className="mb-2 text-sm text-foreground-muted">
                                                                    {repo.description}
                                                                </p>
                                                            )}
                                                            <div className="flex items-center gap-2 text-xs text-foreground-subtle">
                                                                <span>{repo.full_name}</span>
                                                                <span>•</span>
                                                                <span>
                                                                    Updated{' '}
                                                                    {new Date(
                                                                        repo.updated_at
                                                                    ).toLocaleDateString()}
                                                                </span>
                                                            </div>
                                                        </div>
                                                        {selectedRepo?.id === repo.id && (
                                                            <CheckCircle className="h-5 w-5 text-primary" />
                                                        )}
                                                    </div>
                                                </button>
                                            ))}
                                        </div>
                                    </>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    {/* Build Settings Preview */}
                    {selectedRepo && (
                        <Card className="mb-6">
                            <CardContent className="p-6">
                                <h3 className="mb-4 text-lg font-semibold text-foreground">
                                    Build Settings
                                </h3>
                                <div className="space-y-4">
                                    <div>
                                        <label className="mb-1 block text-sm font-medium text-foreground">
                                            <GitBranch className="mr-1 inline h-4 w-4" />
                                            Branch
                                        </label>
                                        {isLoadingBranches ? (
                                            <div className="flex items-center gap-2 py-2">
                                                <Loader2 className="h-4 w-4 animate-spin" />
                                                <span className="text-sm text-foreground-muted">Loading branches...</span>
                                            </div>
                                        ) : (
                                            <Select
                                                value={selectedBranch}
                                                onChange={(e) => setSelectedBranch(e.target.value)}
                                            >
                                                {branches.map((branch) => (
                                                    <option key={branch.name} value={branch.name}>
                                                        {branch.name}
                                                        {branch.name === selectedRepo.default_branch &&
                                                            ' (default)'}
                                                    </option>
                                                ))}
                                            </Select>
                                        )}
                                    </div>

                                    <div>
                                        <label className="mb-1 block text-sm font-medium text-foreground">
                                            Root Directory
                                        </label>
                                        <Input
                                            value={rootDirectory}
                                            onChange={(e) => setRootDirectory(e.target.value)}
                                            placeholder="./"
                                        />
                                        <p className="mt-1 text-xs text-foreground-muted">
                                            Path to your application code
                                        </p>
                                    </div>

                                    <div>
                                        <label className="mb-1 block text-sm font-medium text-foreground">
                                            Build Command
                                        </label>
                                        <Input
                                            value={buildCommand}
                                            onChange={(e) => setBuildCommand(e.target.value)}
                                            placeholder="npm run build"
                                        />
                                        <p className="mt-1 text-xs text-foreground-muted">
                                            Command to build your application
                                        </p>
                                    </div>

                                    <div>
                                        <label className="mb-1 block text-sm font-medium text-foreground">
                                            Start Command
                                        </label>
                                        <Input
                                            value={startCommand}
                                            onChange={(e) => setStartCommand(e.target.value)}
                                            placeholder="npm start"
                                        />
                                        <p className="mt-1 text-xs text-foreground-muted">
                                            Command to start your application
                                        </p>
                                    </div>
                                </div>

                                <div className="mt-6 rounded-lg bg-info/10 p-4">
                                    <div className="flex gap-3">
                                        <Zap className="h-5 w-5 shrink-0 text-info" />
                                        <div>
                                            <h4 className="mb-1 font-medium text-foreground">
                                                Auto-detected settings
                                            </h4>
                                            <p className="text-sm text-foreground-muted">
                                                We've automatically detected your build settings based on
                                                your repository. You can customize them above if needed.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Actions */}
                    <div className="flex items-center justify-between">
                        <Link href="/onboarding/welcome">
                            <Button variant="ghost">
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Back
                            </Button>
                        </Link>
                        <Button
                            onClick={handleDeploy}
                            disabled={!selectedRepo || !selectedBranch}
                        >
                            Continue to Deploy
                            <ArrowRight className="ml-2 h-4 w-4" />
                        </Button>
                    </div>

                    {/* Skip */}
                    <div className="mt-6 text-center">
                        <Link href="/dashboard">
                            <Button variant="ghost" size="sm">
                                Skip and go to dashboard
                            </Button>
                        </Link>
                    </div>
                </div>
            </div>
        </AuthLayout>
    );
}

function ProviderButton({
    provider: _provider,
    selected,
    icon,
    label,
    connected,
    onClick,
}: {
    provider: string;
    selected: boolean;
    icon: React.ReactNode;
    label: string;
    connected: boolean;
    onClick: () => void;
}) {
    return (
        <button
            onClick={onClick}
            className={`flex items-center gap-3 rounded-lg border p-4 transition-all ${
                selected
                    ? 'border-primary bg-primary/5'
                    : 'border-border bg-background-secondary hover:border-border/80'
            }`}
        >
            <div
                className={`flex h-10 w-10 items-center justify-center rounded-lg ${
                    selected ? 'bg-primary/10 text-primary' : 'bg-background-tertiary text-foreground-muted'
                }`}
            >
                {icon}
            </div>
            <div className="text-left">
                <div className="font-semibold text-foreground">{label}</div>
                {connected ? (
                    <div className="text-xs text-success">Connected</div>
                ) : (
                    <div className="text-xs text-foreground-muted">Not connected</div>
                )}
            </div>
        </button>
    );
}
