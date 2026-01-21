import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { AuthLayout } from '@/components/layout';
import { Card, CardContent, Button, Badge, Input, Select } from '@/components/ui';
import {
    Github,
    Gitlab,
    Search,
    ArrowLeft,
    ArrowRight,
    CheckCircle,
    GitBranch,
    FolderGit,
    Settings,
    Zap,
} from 'lucide-react';

interface Props {
    provider?: 'github' | 'gitlab' | 'bitbucket';
    repositories?: Repository[];
}

interface Repository {
    id: string;
    name: string;
    full_name: string;
    description: string | null;
    private: boolean;
    default_branch: string;
    language: string | null;
    updated_at: string;
    branches: string[];
}

// Mock repositories
const mockRepositories: Repository[] = [
    {
        id: '1',
        name: 'my-awesome-app',
        full_name: 'johndoe/my-awesome-app',
        description: 'A modern web application built with React and Node.js',
        private: false,
        default_branch: 'main',
        language: 'TypeScript',
        updated_at: new Date(Date.now() - 86400000).toISOString(),
        branches: ['main', 'develop', 'staging'],
    },
    {
        id: '2',
        name: 'api-backend',
        full_name: 'johndoe/api-backend',
        description: 'RESTful API backend with Express and PostgreSQL',
        private: true,
        default_branch: 'main',
        language: 'JavaScript',
        updated_at: new Date(Date.now() - 86400000 * 3).toISOString(),
        branches: ['main', 'develop', 'feature/auth'],
    },
    {
        id: '3',
        name: 'portfolio-website',
        full_name: 'johndoe/portfolio-website',
        description: 'Personal portfolio and blog',
        private: false,
        default_branch: 'master',
        language: 'HTML',
        updated_at: new Date(Date.now() - 86400000 * 7).toISOString(),
        branches: ['master'],
    },
    {
        id: '4',
        name: 'e-commerce-platform',
        full_name: 'johndoe/e-commerce-platform',
        description: 'Full-stack e-commerce solution',
        private: true,
        default_branch: 'main',
        language: 'TypeScript',
        updated_at: new Date(Date.now() - 86400000 * 14).toISOString(),
        branches: ['main', 'develop', 'staging', 'production'],
    },
    {
        id: '5',
        name: 'mobile-app',
        full_name: 'johndoe/mobile-app',
        description: 'React Native mobile application',
        private: false,
        default_branch: 'main',
        language: 'JavaScript',
        updated_at: new Date(Date.now() - 86400000 * 2).toISOString(),
        branches: ['main', 'develop'],
    },
];

export default function OnboardingConnectRepo({ provider = 'github', repositories: propRepositories }: Props) {
    const repositories = propRepositories || mockRepositories;
    const [selectedProvider, setSelectedProvider] = useState(provider);
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedRepo, setSelectedRepo] = useState<Repository | null>(null);
    const [selectedBranch, setSelectedBranch] = useState('');
    const [buildCommand, setBuildCommand] = useState('npm run build');
    const [startCommand, setStartCommand] = useState('npm start');
    const [rootDirectory, setRootDirectory] = useState('./');

    // Filter repositories by search
    const filteredRepos = repositories.filter(
        (repo) =>
            repo.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            repo.description?.toLowerCase().includes(searchQuery.toLowerCase())
    );

    const handleConnect = () => {
        // In real app, this would trigger OAuth flow
        router.visit(`/auth/${selectedProvider}/redirect`);
    };

    const handleDeploy = () => {
        if (!selectedRepo || !selectedBranch) return;

        router.post('/applications/deploy', {
            repository: selectedRepo.full_name,
            branch: selectedBranch,
            build_command: buildCommand,
            start_command: startCommand,
            root_directory: rootDirectory,
        });
    };

    const getProviderIcon = (prov: string) => {
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
        };
        return colors[language || ''] || 'bg-foreground-muted';
    };

    return (
        <AuthLayout>
            <div className="min-h-screen bg-background py-12">
                <div className="mx-auto max-w-4xl px-4">
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
                                    onClick={() => setSelectedProvider('github')}
                                />
                                <ProviderButton
                                    provider="gitlab"
                                    selected={selectedProvider === 'gitlab'}
                                    icon={<Gitlab className="h-6 w-6" />}
                                    label="GitLab"
                                    onClick={() => setSelectedProvider('gitlab')}
                                />
                                <ProviderButton
                                    provider="bitbucket"
                                    selected={selectedProvider === 'bitbucket'}
                                    icon={<FolderGit className="h-6 w-6" />}
                                    label="Bitbucket"
                                    onClick={() => setSelectedProvider('bitbucket')}
                                />
                            </div>
                            <div className="mt-4 flex justify-end">
                                <Button onClick={handleConnect}>
                                    {getProviderIcon(selectedProvider)}
                                    <span className="ml-2">Connect {selectedProvider}</span>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Repository Selection */}
                    <Card className="mb-6">
                        <CardContent className="p-6">
                            <div className="mb-4 flex items-center justify-between">
                                <h3 className="text-lg font-semibold text-foreground">
                                    Select Repository
                                </h3>
                                <Badge variant="default">{repositories.length} repositories</Badge>
                            </div>
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
                        </CardContent>
                    </Card>

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
                                        <Select
                                            value={selectedBranch}
                                            onChange={(e) => setSelectedBranch(e.target.value)}
                                        >
                                            {selectedRepo.branches.map((branch) => (
                                                <option key={branch} value={branch}>
                                                    {branch}
                                                    {branch === selectedRepo.default_branch &&
                                                        ' (default)'}
                                                </option>
                                            ))}
                                        </Select>
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
    provider,
    selected,
    icon,
    label,
    onClick,
}: {
    provider: string;
    selected: boolean;
    icon: React.ReactNode;
    label: string;
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
                {selected && (
                    <div className="text-xs text-primary">Connected</div>
                )}
            </div>
        </button>
    );
}
