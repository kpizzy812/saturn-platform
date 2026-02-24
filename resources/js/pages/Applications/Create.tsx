import { useState, useEffect, useRef, useCallback } from 'react';
import { router } from '@inertiajs/react';
import type { RouterPayload } from '@/types/inertia';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, CardHeader, CardTitle, Button, Input, Select, Textarea, BranchSelector, Badge } from '@/components/ui';
import { Github, Gitlab, ChevronRight, Check, AlertCircle, Sparkles, Key, ExternalLink, Zap, Webhook, Search, Loader2, CheckCircle, Link as LinkIcon } from 'lucide-react';
import { Bitbucket } from '@/components/icons/Bitbucket';
import { useGitBranches } from '@/hooks/useGitBranches';
import { MonorepoAnalyzer } from '@/components/features/MonorepoAnalyzer';
import { DeployGuide } from '@/components/features/DeployGuide';
import type { Project, Server } from '@/types';

interface WildcardDomain {
    host: string;
    scheme: string;
}

interface GithubApp {
    id: number;
    uuid: string;
    name: string;
    installation_id: number | null;
}

interface GithubRepository {
    id: number;
    name: string;
    full_name: string;
    description: string | null;
    private: boolean;
    default_branch: string;
    language: string | null;
    updated_at: string;
}

interface GithubBranch {
    name: string;
    protected: boolean;
}

interface Props {
    projects?: Project[];
    localhost?: Server;
    userServers?: Server[];
    needsProject?: boolean;
    preselectedSource?: SourceType | null;
    wildcardDomain?: WildcardDomain | null;
    hasGithubApp?: boolean;
    githubApps?: GithubApp[];
}

type SourceType = 'github' | 'gitlab' | 'bitbucket' | 'docker';
type BuildPack = 'nixpacks' | 'dockerfile' | 'dockercompose' | 'dockerimage';
type ApplicationType = 'web' | 'worker' | 'both';

interface FormData {
    name: string;
    source_type: SourceType | null;
    git_repository: string;
    git_branch: string;
    build_pack: BuildPack;
    application_type: ApplicationType;
    project_uuid: string;
    environment_uuid: string;
    server_uuid: string;
    fqdn: string;
    description: string;
    docker_image?: string;
}

export default function ApplicationsCreate({ projects = [], localhost, userServers = [], needsProject = false, preselectedSource = null, wildcardDomain = null, hasGithubApp = false, githubApps = [] }: Props) {
    const validSources: SourceType[] = ['github', 'gitlab', 'bitbucket', 'docker'];
    const initialSource = preselectedSource && validSources.includes(preselectedSource) ? preselectedSource : null;

    const [step, setStep] = useState<1 | 2 | 3 | 'analyze'>(initialSource ? 2 : 1);
    const [projectList, setProjectList] = useState<Project[]>(projects);
    const [showCreateProject, setShowCreateProject] = useState(needsProject);
    const [newProjectName, setNewProjectName] = useState('');
    const [isCreatingProject, setIsCreatingProject] = useState(false);
    const [showCreateEnvironment, setShowCreateEnvironment] = useState(false);
    const [newEnvironmentName, setNewEnvironmentName] = useState('');
    const [isCreatingEnvironment, setIsCreatingEnvironment] = useState(false);
    const [useMonorepoAnalyzer, setUseMonorepoAnalyzer] = useState(false);

    // GitHub App repo picker state
    const [repoMode, setRepoMode] = useState<'picker' | 'manual'>(githubApps.length > 0 ? 'picker' : 'manual');
    const [selectedGithubApp, setSelectedGithubApp] = useState<GithubApp | null>(githubApps.length > 0 ? githubApps[0] : null);
    const [ghRepos, setGhRepos] = useState<GithubRepository[]>([]);
    const [ghBranches, setGhBranches] = useState<GithubBranch[]>([]);
    const [selectedRepo, setSelectedRepo] = useState<GithubRepository | null>(null);
    const [isLoadingRepos, setIsLoadingRepos] = useState(false);
    const [isLoadingGhBranches, setIsLoadingGhBranches] = useState(false);
    const [repoSearchQuery, setRepoSearchQuery] = useState('');
    const [repoError, setRepoError] = useState<string | null>(null);

    const [formData, setFormData] = useState<FormData>({
        name: '',
        source_type: initialSource,
        git_repository: '',
        git_branch: 'main',
        build_pack: 'nixpacks',
        application_type: 'web',
        project_uuid: projects[0]?.uuid || '',
        environment_uuid: projects[0]?.environments[0]?.uuid || '',
        server_uuid: 'auto',
        fqdn: '',
        description: '',
    });
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const formRef = useRef<HTMLFormElement>(null);

    // Git branches fetching
    const {
        branches,
        defaultBranch,
        isLoading: isBranchesLoading,
        error: branchesError,
        fetchBranches,
    } = useGitBranches({ debounceMs: 600 });

    // Fetch branches when repository URL changes (only in manual mode)
    useEffect(() => {
        if (formData.git_repository && formData.source_type !== 'docker' && repoMode === 'manual') {
            fetchBranches(formData.git_repository);
        }
    }, [formData.git_repository, formData.source_type, repoMode, fetchBranches]);

    // Set default branch when branches are loaded
    useEffect(() => {
        if (defaultBranch && branches.length > 0) {
            setFormData(prev => ({ ...prev, git_branch: defaultBranch }));
        }
    }, [defaultBranch, branches.length]);

    // Load repositories from GitHub App
    const loadGhRepos = useCallback(async () => {
        if (!selectedGithubApp) return;
        setIsLoadingRepos(true);
        setRepoError(null);
        setGhRepos([]);
        try {
            const response = await fetch(`/web-api/github-apps/${selectedGithubApp.id}/repositories`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                const data = await response.json();
                throw new Error(data.message || 'Failed to load repositories');
            }
            const data = await response.json();
            setGhRepos(data.repositories || []);
        } catch (err) {
            setRepoError(err instanceof Error ? err.message : 'Failed to load repositories');
        } finally {
            setIsLoadingRepos(false);
        }
    }, [selectedGithubApp]);

    // Load branches for selected repo from GitHub App
    const loadGhBranches = useCallback(async (repo: GithubRepository) => {
        if (!selectedGithubApp || !repo) return;
        setIsLoadingGhBranches(true);
        setGhBranches([]);
        try {
            const [owner, repoName] = repo.full_name.split('/');
            const response = await fetch(
                `/web-api/github-apps/${selectedGithubApp.id}/repositories/${owner}/${repoName}/branches`,
                {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                }
            );
            if (!response.ok) throw new Error('Failed to load branches');
            const data = await response.json();
            setGhBranches(data.branches || []);
        } catch {
            setGhBranches([]);
        } finally {
            setIsLoadingGhBranches(false);
        }
    }, [selectedGithubApp]);

    // Load repos when GitHub App is selected and mode is picker
    useEffect(() => {
        if (repoMode === 'picker' && selectedGithubApp && formData.source_type === 'github') {
            loadGhRepos();
        }
    }, [repoMode, selectedGithubApp, formData.source_type, loadGhRepos]);

    // Load branches when repo is selected
    useEffect(() => {
        if (selectedRepo && selectedGithubApp) {
            loadGhBranches(selectedRepo);
        }
    }, [selectedRepo, selectedGithubApp, loadGhBranches]);

    // Handle repo selection from picker
    const handleRepoSelect = (repo: GithubRepository) => {
        setSelectedRepo(repo);
        setFormData(prev => ({
            ...prev,
            git_repository: `https://github.com/${repo.full_name}`,
            git_branch: repo.default_branch,
        }));
    };

    // Handle branch selection from picker
    const handleGhBranchSelect = (branchName: string) => {
        setFormData(prev => ({ ...prev, git_branch: branchName }));
    };

    const filteredGhRepos = ghRepos.filter(
        (repo) =>
            repo.name.toLowerCase().includes(repoSearchQuery.toLowerCase()) ||
            repo.full_name.toLowerCase().includes(repoSearchQuery.toLowerCase()) ||
            repo.description?.toLowerCase().includes(repoSearchQuery.toLowerCase())
    );

    const getLanguageColor = (language: string | null) => {
        const colors: Record<string, string> = {
            TypeScript: 'bg-blue-500', JavaScript: 'bg-yellow-500', Python: 'bg-blue-400',
            Ruby: 'bg-red-500', Go: 'bg-cyan-500', Rust: 'bg-orange-500',
            PHP: 'bg-indigo-500', Java: 'bg-red-600', HTML: 'bg-orange-400', CSS: 'bg-purple-500',
        };
        return colors[language || ''] || 'bg-foreground-muted';
    };

    const handleCreateProject = async () => {
        if (!newProjectName.trim()) {
            setErrors({ newProjectName: 'Project name is required' });
            return;
        }
        setIsCreatingProject(true);
        setErrors({});
        try {
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
            const response = await fetch('/projects', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ name: newProjectName }),
            });
            if (response.ok) {
                const newProject = await response.json();
                setProjectList(prev => [...prev, newProject]);
                setFormData(prev => ({
                    ...prev,
                    project_uuid: newProject.uuid,
                    environment_uuid: newProject.environments?.[0]?.uuid || '',
                }));
                setShowCreateProject(false);
                setNewProjectName('');
            } else {
                const errorData = await response.json().catch(() => ({}));
                setErrors({ newProjectName: errorData.message || 'Failed to create project' });
            }
        } catch {
            setErrors({ newProjectName: 'Failed to create project' });
        }
        setIsCreatingProject(false);
    };

    const handleCreateEnvironment = async () => {
        if (!newEnvironmentName.trim()) {
            setErrors({ newEnvironmentName: 'Environment name is required' });
            return;
        }
        if (!formData.project_uuid) {
            setErrors({ newEnvironmentName: 'Please select a project first' });
            return;
        }
        setIsCreatingEnvironment(true);
        setErrors({});
        try {
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(`/projects/${formData.project_uuid}/environments`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ name: newEnvironmentName }),
            });
            if (response.ok) {
                const newEnvironment = await response.json();
                setProjectList(prev => prev.map(p => {
                    if (p.uuid === formData.project_uuid) {
                        return {
                            ...p,
                            environments: [...(p.environments || []), newEnvironment],
                        };
                    }
                    return p;
                }));
                setFormData(prev => ({
                    ...prev,
                    environment_uuid: newEnvironment.uuid,
                }));
                setShowCreateEnvironment(false);
                setNewEnvironmentName('');
            } else {
                const errorData = await response.json().catch(() => ({}));
                setErrors({ newEnvironmentName: errorData.message || 'Failed to create environment' });
            }
        } catch {
            setErrors({ newEnvironmentName: 'Failed to create environment' });
        }
        setIsCreatingEnvironment(false);
    };

    const selectedProject = projectList.find(p => p.uuid === formData.project_uuid);
    const environments = selectedProject?.environments || [];

    const handleSourceSelect = (sourceType: SourceType) => {
        setFormData(prev => ({ ...prev, source_type: sourceType }));
        setStep(2);
    };

    const handleMonorepoComplete = (result: { applications: Array<{ uuid: string; name: string }>; monorepo_group_id: string | null }) => {
        if (formData.project_uuid) {
            router.visit(`/projects/${formData.project_uuid}`);
        } else if (result.applications.length === 1) {
            router.visit(`/applications/${result.applications[0].uuid}`);
        } else {
            router.visit('/applications');
        }
    };

    const validateForm = (mode: 'deploy' | 'analyze'): Record<string, string> => {
        const newErrors: Record<string, string> = {};

        if (mode === 'deploy') {
            if (!formData.name) newErrors.name = 'Application name is required';
        }
        if (!formData.source_type) newErrors.source_type = 'Source type is required';
        if (formData.source_type !== 'docker' && !formData.git_repository) {
            newErrors.git_repository = 'Repository URL is required';
        }
        if (formData.source_type === 'docker' && !formData.docker_image) {
            newErrors.docker_image = 'Docker image is required';
        }
        if (!formData.project_uuid) newErrors.project_uuid = 'Project is required';
        if (!formData.environment_uuid) newErrors.environment_uuid = 'Environment is required';

        return newErrors;
    };

    const scrollToFirstError = () => {
        requestAnimationFrame(() => {
            const firstError = formRef.current?.querySelector('[data-error="true"]');
            firstError?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    };

    const handleStartAnalysis = () => {
        const newErrors = validateForm('analyze');
        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            scrollToFirstError();
            return;
        }
        setErrors({});
        setUseMonorepoAnalyzer(true);
        setStep('analyze');
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        const newErrors = validateForm('deploy');
        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            scrollToFirstError();
            return;
        }

        setIsSubmitting(true);
        setErrors({});

        router.post('/applications', formData as unknown as RouterPayload, {
            onError: (serverErrors) => {
                setErrors(serverErrors as Record<string, string>);
                setIsSubmitting(false);
            },
        });
    };

    return (
        <AppLayout
            title="Create Application"
            breadcrumbs={[
                { label: 'Applications', href: '/applications' },
                { label: 'Create' },
            ]}
        >
            <div className="mx-auto max-w-4xl">
                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-2xl font-bold text-foreground">Create Application</h1>
                    <p className="mt-1 text-foreground-muted">Deploy from Git or Docker image</p>
                </div>

                {/* Progress Steps */}
                <div className="mb-8 flex items-center justify-center gap-2 flex-wrap">
                    <StepIndicator number={1} label="Source" active={step === 1 || step === 2 || step === 3 || step === 'analyze'} completed={step !== 1} />
                    <ChevronRight className="h-4 w-4 text-foreground-subtle" />
                    <StepIndicator number={2} label="Configure" active={step === 2 || step === 3 || step === 'analyze'} completed={step === 3 || step === 'analyze'} />
                    <ChevronRight className="h-4 w-4 text-foreground-subtle" />
                    {useMonorepoAnalyzer ? (
                        <StepIndicator number={3} label="Analyze" active={step === 'analyze'} completed={false} />
                    ) : (
                        <StepIndicator number={3} label="Deploy" active={step === 3} completed={false} />
                    )}
                </div>

                <form ref={formRef} onSubmit={handleSubmit}>
                    {/* Step 1: Git Provider Selection */}
                    {step === 1 && (
                        <div className="space-y-4">
                            <h2 className="text-lg font-semibold text-foreground">Select Git Provider</h2>
                            <div className="grid gap-4 md:grid-cols-3">
                                <SourceCard
                                    icon={<Github className="h-6 w-6" />}
                                    title="GitHub"
                                    description="Deploy from GitHub repository"
                                    hint="Private repos require GitHub App connection"
                                    onClick={() => handleSourceSelect('github')}
                                    selected={formData.source_type === 'github'}
                                />
                                <SourceCard
                                    icon={<Gitlab className="h-6 w-6" />}
                                    title="GitLab"
                                    description="Deploy from GitLab repository"
                                    onClick={() => handleSourceSelect('gitlab')}
                                    selected={formData.source_type === 'gitlab'}
                                />
                                <SourceCard
                                    icon={<Bitbucket className="h-6 w-6" />}
                                    title="Bitbucket"
                                    description="Deploy from Bitbucket repository"
                                    onClick={() => handleSourceSelect('bitbucket')}
                                    selected={formData.source_type === 'bitbucket'}
                                />
                            </div>
                        </div>
                    )}

                    {/* Step 2: Configuration */}
                    {step === 2 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Configure Application</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                {/* Basic Info */}
                                <div className="space-y-4">
                                    <div data-error={!!errors.name || undefined}>
                                        <label className="block text-sm font-medium text-foreground mb-2">
                                            Application Name *
                                        </label>
                                        <Input
                                            value={formData.name}
                                            onChange={(e) => {
                                                setFormData(prev => ({ ...prev, name: e.target.value }));
                                                if (errors.name) setErrors(prev => { const { name: _, ...rest } = prev; return rest; });
                                            }}
                                            placeholder="my-awesome-app"
                                            error={errors.name}
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-foreground mb-2">
                                            Description
                                        </label>
                                        <Textarea
                                            value={formData.description}
                                            onChange={(e) => setFormData(prev => ({ ...prev, description: e.target.value }))}
                                            placeholder="Brief description of your application"
                                            rows={3}
                                        />
                                    </div>
                                </div>

                                {/* Source Configuration */}
                                {formData.source_type !== 'docker' ? (
                                    <div className="space-y-4">
                                        {/* Connect GitHub App prompt */}
                                        {formData.source_type === 'github' && githubApps.length === 0 && (
                                            <div className="rounded-lg bg-primary/5 border border-primary/20 p-4">
                                                <div className="flex items-start gap-3">
                                                    <Github className="h-5 w-5 text-primary shrink-0 mt-0.5" />
                                                    <div className="flex-1">
                                                        <p className="text-sm font-medium text-foreground">Connect GitHub for private repos & auto-deploy</p>
                                                        <p className="text-sm text-foreground-muted mt-1">
                                                            Connect a GitHub App to browse your repositories, deploy private repos, and enable automatic deploys on push.
                                                        </p>
                                                        <a
                                                            href="/sources/github/create"
                                                            className="inline-flex items-center gap-1.5 mt-3 rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-primary/90 transition-colors"
                                                        >
                                                            <Github className="h-4 w-4" />
                                                            Connect GitHub App
                                                            <ExternalLink className="h-3 w-3" />
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        )}

                                        {/* Mode toggle: picker vs manual */}
                                        {formData.source_type === 'github' && githubApps.length > 0 && (
                                            <div className="flex gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() => setRepoMode('picker')}
                                                    className={`flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium transition-all ${
                                                        repoMode === 'picker'
                                                            ? 'border-primary bg-primary/10 text-primary'
                                                            : 'border-border text-foreground-muted hover:border-primary/50'
                                                    }`}
                                                >
                                                    <Github className="h-4 w-4" />
                                                    My Repositories
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => setRepoMode('manual')}
                                                    className={`flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium transition-all ${
                                                        repoMode === 'manual'
                                                            ? 'border-primary bg-primary/10 text-primary'
                                                            : 'border-border text-foreground-muted hover:border-primary/50'
                                                    }`}
                                                >
                                                    <LinkIcon className="h-4 w-4" />
                                                    Manual URL
                                                </button>
                                            </div>
                                        )}

                                        {/* GitHub App selector (if multiple apps) */}
                                        {repoMode === 'picker' && githubApps.length > 1 && (
                                            <div>
                                                <label className="block text-sm font-medium text-foreground mb-2">
                                                    GitHub App
                                                </label>
                                                <Select
                                                    value={selectedGithubApp?.id.toString() || ''}
                                                    onChange={(e) => {
                                                        const app = githubApps.find(a => a.id === parseInt(e.target.value));
                                                        setSelectedGithubApp(app || null);
                                                        setSelectedRepo(null);
                                                        setGhBranches([]);
                                                    }}
                                                >
                                                    {githubApps.map((app) => (
                                                        <option key={app.id} value={app.id}>
                                                            {app.name}
                                                        </option>
                                                    ))}
                                                </Select>
                                            </div>
                                        )}

                                        {/* GitHub App repo picker */}
                                        {repoMode === 'picker' && formData.source_type === 'github' && githubApps.length > 0 ? (
                                            <div className="space-y-4">
                                                <div>
                                                    <label className="block text-sm font-medium text-foreground mb-2">
                                                        Repository *
                                                    </label>
                                                    {repoError && (
                                                        <div className="mb-3 rounded-lg bg-danger/10 p-3 text-danger text-sm">
                                                            <div className="flex items-center gap-2">
                                                                <AlertCircle className="h-4 w-4 shrink-0" />
                                                                <span>{repoError}</span>
                                                            </div>
                                                            <Button type="button" variant="ghost" size="sm" onClick={loadGhRepos} className="mt-2">
                                                                Try Again
                                                            </Button>
                                                        </div>
                                                    )}

                                                    {isLoadingRepos ? (
                                                        <div className="flex items-center justify-center py-8 rounded-lg border border-border bg-background-secondary">
                                                            <Loader2 className="h-6 w-6 animate-spin text-primary" />
                                                            <span className="ml-3 text-sm text-foreground-muted">Loading repositories...</span>
                                                        </div>
                                                    ) : (
                                                        <>
                                                            <div className="relative mb-3">
                                                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                                                                <Input
                                                                    placeholder="Search repositories..."
                                                                    value={repoSearchQuery}
                                                                    onChange={(e) => setRepoSearchQuery(e.target.value)}
                                                                    className="pl-10"
                                                                />
                                                            </div>
                                                            <div className="max-h-64 space-y-1.5 overflow-y-auto rounded-lg border border-border p-2 bg-background-secondary">
                                                                {filteredGhRepos.length === 0 && (
                                                                    <div className="py-6 text-center text-sm text-foreground-muted">
                                                                        {repoSearchQuery ? 'No repositories match your search' : 'No repositories found'}
                                                                    </div>
                                                                )}
                                                                {filteredGhRepos.map((repo) => (
                                                                    <button
                                                                        type="button"
                                                                        key={repo.id}
                                                                        onClick={() => handleRepoSelect(repo)}
                                                                        className={`w-full rounded-md border p-3 text-left transition-all hover:border-primary/50 ${
                                                                            selectedRepo?.id === repo.id
                                                                                ? 'border-primary bg-primary/5'
                                                                                : 'border-transparent hover:bg-background-tertiary'
                                                                        }`}
                                                                    >
                                                                        <div className="flex items-center justify-between">
                                                                            <div className="flex-1 min-w-0">
                                                                                <div className="flex items-center gap-2">
                                                                                    <span className="font-medium text-sm text-foreground truncate">{repo.name}</span>
                                                                                    {repo.private && <Badge variant="default" className="text-[10px] px-1.5 py-0">Private</Badge>}
                                                                                    {repo.language && (
                                                                                        <div className="flex items-center gap-1">
                                                                                            <div className={`h-2 w-2 rounded-full ${getLanguageColor(repo.language)}`} />
                                                                                            <span className="text-xs text-foreground-muted">{repo.language}</span>
                                                                                        </div>
                                                                                    )}
                                                                                </div>
                                                                                {repo.description && (
                                                                                    <p className="text-xs text-foreground-muted mt-0.5 truncate">{repo.description}</p>
                                                                                )}
                                                                            </div>
                                                                            {selectedRepo?.id === repo.id && (
                                                                                <CheckCircle className="h-4 w-4 text-primary shrink-0 ml-2" />
                                                                            )}
                                                                        </div>
                                                                    </button>
                                                                ))}
                                                            </div>
                                                        </>
                                                    )}
                                                </div>

                                                {/* Branch selector for picked repo */}
                                                {selectedRepo && (
                                                    <div>
                                                        <label className="block text-sm font-medium text-foreground mb-2">
                                                            Branch
                                                        </label>
                                                        {isLoadingGhBranches ? (
                                                            <div className="flex items-center gap-2 py-2">
                                                                <Loader2 className="h-4 w-4 animate-spin" />
                                                                <span className="text-sm text-foreground-muted">Loading branches...</span>
                                                            </div>
                                                        ) : (
                                                            <Select
                                                                value={formData.git_branch}
                                                                onChange={(e) => handleGhBranchSelect(e.target.value)}
                                                            >
                                                                {ghBranches.map((branch) => (
                                                                    <option key={branch.name} value={branch.name}>
                                                                        {branch.name}{branch.name === selectedRepo.default_branch ? ' (default)' : ''}
                                                                    </option>
                                                                ))}
                                                            </Select>
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        ) : repoMode === 'manual' || formData.source_type !== 'github' || githubApps.length === 0 ? (
                                            <div className="space-y-4">
                                                <div data-error={!!errors.git_repository || undefined}>
                                                    <label className="block text-sm font-medium text-foreground mb-2">
                                                        Repository URL *
                                                    </label>
                                                    <Input
                                                        value={formData.git_repository}
                                                        onChange={(e) => {
                                                            setFormData(prev => ({ ...prev, git_repository: e.target.value }));
                                                            if (errors.git_repository) setErrors(prev => { const { git_repository: _, ...rest } = prev; return rest; });
                                                        }}
                                                        placeholder="https://github.com/user/repo"
                                                        error={errors.git_repository}
                                                    />
                                                </div>

                                                <div>
                                                    <label className="block text-sm font-medium text-foreground mb-2">
                                                        Branch
                                                    </label>
                                                    <BranchSelector
                                                        value={formData.git_branch}
                                                        onChange={(value) => setFormData(prev => ({ ...prev, git_branch: value }))}
                                                        branches={branches}
                                                        isLoading={isBranchesLoading}
                                                        error={branchesError}
                                                        placeholder="main"
                                                    />
                                                </div>

                                                {/* Private Repository Help */}
                                                {branchesError && branchesError.toLowerCase().includes('private') && (
                                                    <div className="rounded-lg bg-amber-500/10 border border-amber-500/30 p-4">
                                                        <div className="flex items-start gap-3">
                                                            <Key className="h-5 w-5 text-amber-500 shrink-0 mt-0.5" />
                                                            <div className="flex-1 space-y-3">
                                                                <div>
                                                                    <p className="text-sm font-medium text-foreground">Private Repository?</p>
                                                                    <p className="text-sm text-foreground-muted mt-1">
                                                                        Connect your GitHub account to access private repositories with automatic webhooks.
                                                                    </p>
                                                                </div>
                                                                <div className="flex flex-wrap gap-2">
                                                                    <a
                                                                        href="/sources/github/create"
                                                                        target="_blank"
                                                                        rel="noopener noreferrer"
                                                                        className="inline-flex items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-primary/90 transition-colors"
                                                                    >
                                                                        <Github className="h-4 w-4" />
                                                                        Connect GitHub
                                                                        <ExternalLink className="h-3 w-3" />
                                                                    </a>
                                                                    <a
                                                                        href="/sources/gitlab/create"
                                                                        target="_blank"
                                                                        rel="noopener noreferrer"
                                                                        className="inline-flex items-center gap-1.5 rounded-md bg-background-tertiary px-3 py-1.5 text-sm font-medium text-foreground hover:bg-background-secondary transition-colors border border-border"
                                                                    >
                                                                        <Gitlab className="h-4 w-4" />
                                                                        Connect GitLab
                                                                        <ExternalLink className="h-3 w-3" />
                                                                    </a>
                                                                </div>
                                                                <p className="text-xs text-foreground-subtle">
                                                                    Or type branch name manually above and use SSH deploy key
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        ) : null}

                                        <div>
                                            <label className="block text-sm font-medium text-foreground mb-2">
                                                Build Pack
                                            </label>
                                            <Select
                                                value={formData.build_pack}
                                                onChange={(e) => setFormData(prev => ({ ...prev, build_pack: e.target.value as BuildPack }))}
                                            >
                                                <option value="nixpacks">Nixpacks (Auto-detect)</option>
                                                <option value="dockerfile">Dockerfile</option>
                                                <option value="dockercompose">Docker Compose</option>
                                            </Select>
                                            <p className="mt-1 text-xs text-foreground-muted">
                                                Nixpacks will automatically detect your application type
                                            </p>
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-foreground mb-2">
                                                Application Type
                                            </label>
                                            <Select
                                                value={formData.application_type}
                                                onChange={(e) => setFormData(prev => ({ ...prev, application_type: e.target.value as ApplicationType }))}
                                            >
                                                <option value="web">Web (HTTP service with port)</option>
                                                <option value="worker">Worker (background process, no port)</option>
                                                <option value="both">Both (web + worker)</option>
                                            </Select>
                                            <p className="mt-1 text-xs text-foreground-muted">
                                                {formData.application_type === 'worker'
                                                    ? 'Workers run without HTTP port. No domain or health check needed. Perfect for bots, queue workers, scrapers.'
                                                    : formData.application_type === 'both'
                                                        ? 'Application serves HTTP and runs background processes.'
                                                        : 'Standard web application with HTTP port and domain.'}
                                            </p>
                                        </div>
                                    </div>
                                ) : (
                                    <div data-error={!!errors.docker_image || undefined}>
                                        <label className="block text-sm font-medium text-foreground mb-2">
                                            Docker Image *
                                        </label>
                                        <Input
                                            value={formData.docker_image || ''}
                                            onChange={(e) => {
                                                setFormData(prev => ({ ...prev, docker_image: e.target.value }));
                                                if (errors.docker_image) setErrors(prev => { const { docker_image: _, ...rest } = prev; return rest; });
                                            }}
                                            placeholder="nginx:latest"
                                            error={errors.docker_image}
                                        />
                                    </div>
                                )}

                                {/* Deployment Configuration */}
                                <div className="space-y-4">
                                    <div data-error={!!errors.project_uuid || undefined}>
                                        <label className="block text-sm font-medium text-foreground mb-2">
                                            Project *
                                        </label>
                                        {showCreateProject || projectList.length === 0 ? (
                                            <div className="space-y-3">
                                                <Input
                                                    value={newProjectName}
                                                    onChange={(e) => setNewProjectName(e.target.value)}
                                                    placeholder="Enter project name"
                                                    error={errors.newProjectName}
                                                />
                                                {errors.newProjectName && (
                                                    <p className="text-sm text-destructive">{errors.newProjectName}</p>
                                                )}
                                                <div className="flex gap-2">
                                                    <Button
                                                        type="button"
                                                        onClick={handleCreateProject}
                                                        disabled={isCreatingProject}
                                                    >
                                                        {isCreatingProject ? 'Creating...' : 'Create Project'}
                                                    </Button>
                                                    {projectList.length > 0 && (
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            onClick={() => setShowCreateProject(false)}
                                                        >
                                                            Cancel
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="space-y-2">
                                                <Select
                                                    value={formData.project_uuid}
                                                    onChange={(e) => {
                                                        const newProjectUuid = e.target.value;
                                                        const newProject = projectList.find(p => p.uuid === newProjectUuid);
                                                        setFormData(prev => ({
                                                            ...prev,
                                                            project_uuid: newProjectUuid,
                                                            environment_uuid: newProject?.environments[0]?.uuid || '',
                                                        }));
                                                        if (errors.project_uuid) setErrors(prev => { const { project_uuid: _, ...rest } = prev; return rest; });
                                                    }}
                                                    error={errors.project_uuid}
                                                >
                                                    {projectList.map(project => (
                                                        <option key={project.uuid} value={project.uuid}>
                                                            {project.name}
                                                        </option>
                                                    ))}
                                                </Select>
                                                <button
                                                    type="button"
                                                    onClick={() => setShowCreateProject(true)}
                                                    className="text-sm text-primary hover:underline"
                                                >
                                                    + Create new project
                                                </button>
                                            </div>
                                        )}
                                    </div>

                                    <div data-error={!!errors.environment_uuid || undefined}>
                                        <label className="block text-sm font-medium text-foreground mb-2">
                                            Environment *
                                        </label>
                                        {showCreateEnvironment ? (
                                            <div className="space-y-2">
                                                <Input
                                                    value={newEnvironmentName}
                                                    onChange={(e) => setNewEnvironmentName(e.target.value)}
                                                    placeholder="e.g. development, staging"
                                                    error={errors.newEnvironmentName}
                                                    onKeyDown={(e) => {
                                                        if (e.key === 'Enter') {
                                                            e.preventDefault();
                                                            handleCreateEnvironment();
                                                        }
                                                    }}
                                                />
                                                {errors.newEnvironmentName && (
                                                    <p className="text-sm text-destructive">{errors.newEnvironmentName}</p>
                                                )}
                                                <div className="flex gap-2">
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        onClick={handleCreateEnvironment}
                                                        disabled={isCreatingEnvironment}
                                                    >
                                                        {isCreatingEnvironment ? 'Creating...' : 'Create Environment'}
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() => {
                                                            setShowCreateEnvironment(false);
                                                            setNewEnvironmentName('');
                                                            setErrors({});
                                                        }}
                                                    >
                                                        Cancel
                                                    </Button>
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="space-y-2">
                                                <Select
                                                    value={formData.environment_uuid}
                                                    onChange={(e) => {
                                                        setFormData(prev => ({ ...prev, environment_uuid: e.target.value }));
                                                        if (errors.environment_uuid) setErrors(prev => { const { environment_uuid: _, ...rest } = prev; return rest; });
                                                    }}
                                                    disabled={!formData.project_uuid}
                                                    error={errors.environment_uuid}
                                                >
                                                    {environments.map(env => (
                                                        <option key={env.uuid} value={env.uuid}>
                                                            {env.name}
                                                        </option>
                                                    ))}
                                                </Select>
                                                <button
                                                    type="button"
                                                    onClick={() => setShowCreateEnvironment(true)}
                                                    disabled={!formData.project_uuid}
                                                    className="text-sm text-primary hover:underline disabled:opacity-50 disabled:cursor-not-allowed"
                                                >
                                                    + Create new environment
                                                </button>
                                            </div>
                                        )}
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-foreground mb-2">
                                            Server
                                        </label>
                                        <Select
                                            value={formData.server_uuid}
                                            onChange={(e) => setFormData(prev => ({ ...prev, server_uuid: e.target.value }))}
                                        >
                                            <option value="auto">Auto (Recommended)</option>
                                            {localhost && (
                                                <option value={localhost.uuid}>
                                                    {localhost.name} (Master)
                                                </option>
                                            )}
                                            {userServers.map(server => (
                                                <option key={server.uuid} value={server.uuid}>
                                                    {server.name} ({server.ip})
                                                </option>
                                            ))}
                                        </Select>
                                        <p className="mt-1 text-xs text-foreground-muted flex items-center gap-1">
                                            <Zap className="h-3 w-3" />
                                            Auto selects the server with most available resources
                                        </p>
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-foreground mb-2">
                                            {wildcardDomain ? 'Domain (subdomain)' : 'Domain (FQDN)'}
                                        </label>
                                        {wildcardDomain ? (
                                            <div className="flex items-center gap-0">
                                                <Input
                                                    value={formData.fqdn}
                                                    onChange={(e) => setFormData(prev => ({ ...prev, fqdn: e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '') }))}
                                                    placeholder="my-app"
                                                    className="rounded-r-none"
                                                />
                                                <span className="inline-flex items-center px-3 h-9 rounded-r-md border border-l-0 border-border bg-muted text-sm text-foreground-muted whitespace-nowrap">
                                                    .{wildcardDomain.host}
                                                </span>
                                            </div>
                                        ) : (
                                            <Input
                                                value={formData.fqdn}
                                                onChange={(e) => setFormData(prev => ({ ...prev, fqdn: e.target.value }))}
                                                placeholder="app.example.com"
                                            />
                                        )}
                                        <p className="mt-1 text-xs text-foreground-muted">
                                            Leave empty for auto-generated domain
                                        </p>
                                    </div>
                                </div>

                                {/* Actions */}
                                <div className="flex flex-col gap-4 pt-4 border-t border-border">
                                    {/* Smart Deploy Option */}
                                    {formData.source_type !== 'docker' && (
                                        <div className="rounded-lg bg-primary/5 border border-primary/20 p-4">
                                            <div className="flex items-start gap-3">
                                                <Sparkles className="h-5 w-5 text-primary shrink-0 mt-0.5" />
                                                <div className="flex-1">
                                                    <p className="text-sm font-medium text-foreground">Smart Deploy (Recommended)</p>
                                                    <p className="text-sm text-foreground-muted mt-1">
                                                        Automatically detect monorepo structure, frameworks, databases and create everything with one click.
                                                    </p>
                                                    <DeployGuide variant="compact" className="mt-3" />
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        className="mt-3"
                                                        onClick={handleStartAnalysis}
                                                    >
                                                        <Sparkles className="h-4 w-4 mr-1" />
                                                        Analyze & Auto-Deploy
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    )}

                                    <div className="flex items-center justify-between">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setStep(1)}
                                        >
                                            Back
                                        </Button>
                                        <Button
                                            type="button"
                                            onClick={() => setStep(3)}
                                        >
                                            Manual Setup
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Step: Analyze (Monorepo) — auto-starts analysis */}
                    {step === 'analyze' && (
                        <div className="space-y-4">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setUseMonorepoAnalyzer(false);
                                    setStep(2);
                                }}
                            >
                                &larr; Back to Configuration
                            </Button>
                            <MonorepoAnalyzer
                                gitRepository={formData.git_repository}
                                gitBranch={formData.git_branch}
                                githubAppId={repoMode === 'picker' ? selectedGithubApp?.id : undefined}
                                environmentUuid={formData.environment_uuid}
                                destinationUuid={formData.server_uuid}
                                onComplete={handleMonorepoComplete}
                                autoStart
                            />
                        </div>
                    )}

                    {/* Step 3: Review & Deploy */}
                    {step === 3 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Review & Deploy</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <div className="rounded-lg bg-background-secondary p-4 space-y-3">
                                    <ReviewItem label="Application Name" value={formData.name} />
                                    <ReviewItem label="Source" value={formData.source_type || ''} />
                                    <ReviewItem label="Application Type" value={formData.application_type === 'worker' ? 'Worker (no port)' : formData.application_type === 'both' ? 'Web + Worker' : 'Web'} />
                                    {formData.source_type !== 'docker' ? (
                                        <>
                                            <ReviewItem label="Repository" value={formData.git_repository} />
                                            <ReviewItem label="Branch" value={formData.git_branch} />
                                            <ReviewItem label="Build Pack" value={formData.build_pack} />
                                        </>
                                    ) : (
                                        <ReviewItem label="Docker Image" value={formData.docker_image || ''} />
                                    )}
                                    <ReviewItem label="Project" value={selectedProject?.name || ''} />
                                    <ReviewItem label="Environment" value={environments.find(e => e.uuid === formData.environment_uuid)?.name || ''} />
                                    <ReviewItem label="Server" value={
                                        formData.server_uuid === 'auto'
                                            ? 'Auto (Best Available)'
                                            : localhost?.uuid === formData.server_uuid
                                            ? `${localhost.name} (Master)`
                                            : userServers.find(s => s.uuid === formData.server_uuid)?.name || 'Auto'
                                    } />
                                    {formData.fqdn && <ReviewItem label="Domain" value={formData.fqdn} />}
                                </div>

                                {/* Auto-deploy status indicator */}
                                {formData.source_type !== 'docker' && (
                                    <div className={`rounded-lg p-4 flex gap-3 ${
                                        hasGithubApp
                                            ? 'bg-green-500/10 border border-green-500/30'
                                            : 'bg-amber-500/10 border border-amber-500/30'
                                    }`}>
                                        {hasGithubApp ? (
                                            <Zap className="h-5 w-5 text-green-500 shrink-0 mt-0.5" />
                                        ) : (
                                            <Webhook className="h-5 w-5 text-amber-500 shrink-0 mt-0.5" />
                                        )}
                                        <div className="space-y-1">
                                            <p className="text-sm font-medium text-foreground">
                                                {hasGithubApp ? 'Auto-deploy will be enabled' : 'Manual webhook for auto-deploy'}
                                            </p>
                                            <p className="text-sm text-foreground-muted">
                                                {hasGithubApp
                                                    ? 'Pushes to this repo will automatically trigger deploys via your connected GitHub App.'
                                                    : 'A webhook secret will be generated. Set up a webhook in your Git provider to enable auto-deploy.'
                                                }
                                            </p>
                                        </div>
                                    </div>
                                )}

                                <div className="rounded-lg bg-blue-500/10 border border-blue-500/20 p-4 flex gap-3">
                                    <AlertCircle className="h-5 w-5 text-blue-500 shrink-0 mt-0.5" />
                                    <div className="space-y-1">
                                        <p className="text-sm font-medium text-foreground">Ready to deploy</p>
                                        <p className="text-sm text-foreground-muted">
                                            Your application will be created and the first deployment will start automatically.
                                        </p>
                                    </div>
                                </div>

                                <div className="flex items-center justify-between pt-4 border-t border-border">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setStep(2)}
                                        disabled={isSubmitting}
                                    >
                                        Back
                                    </Button>
                                    <Button
                                        type="submit"
                                        loading={isSubmitting}
                                    >
                                        Create & Deploy
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </form>
            </div>
        </AppLayout>
    );
}

interface StepIndicatorProps {
    number: number;
    label: string;
    active: boolean;
    completed: boolean;
}

function StepIndicator({ number, label, active, completed }: StepIndicatorProps) {
    return (
        <div className="flex items-center gap-2">
            <div
                className={`flex h-8 w-8 items-center justify-center rounded-full border-2 text-sm font-medium transition-colors ${
                    completed
                        ? 'border-primary bg-primary text-white'
                        : active
                        ? 'border-primary text-primary'
                        : 'border-border text-foreground-muted'
                }`}
            >
                {completed ? <Check className="h-4 w-4" /> : number}
            </div>
            <span
                className={`text-sm font-medium ${
                    active ? 'text-foreground' : 'text-foreground-muted'
                }`}
            >
                {label}
            </span>
        </div>
    );
}

interface SourceCardProps {
    icon: React.ReactNode;
    title: string;
    description: string;
    hint?: string;
    onClick: () => void;
    selected: boolean;
}

function SourceCard({ icon, title, description, hint, onClick, selected }: SourceCardProps) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`relative flex items-start gap-4 rounded-lg border-2 p-4 text-left transition-all hover:border-primary/50 hover:bg-background-secondary ${
                selected ? 'border-primary bg-primary/5' : 'border-border'
            }`}
        >
            {selected && (
                <div className="absolute top-2 right-2 flex h-5 w-5 items-center justify-center rounded-full bg-primary">
                    <Check className="h-3 w-3 text-white" />
                </div>
            )}
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                {icon}
            </div>
            <div className="flex-1 min-w-0">
                <h3 className="font-medium text-foreground">{title}</h3>
                <p className="mt-1 text-sm text-foreground-muted">{description}</p>
                {hint && (
                    <p className="mt-1 text-xs text-foreground-subtle">{hint}</p>
                )}
            </div>
        </button>
    );
}

interface ReviewItemProps {
    label: string;
    value: string;
}

function ReviewItem({ label, value }: ReviewItemProps) {
    return (
        <div className="flex justify-between items-start gap-4">
            <span className="text-sm text-foreground-muted">{label}</span>
            <span className="text-sm font-medium text-foreground text-right">{value}</span>
        </div>
    );
}
