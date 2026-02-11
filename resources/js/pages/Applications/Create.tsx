import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, CardHeader, CardTitle, Button, Input, Select, Textarea, Badge, BranchSelector } from '@/components/ui';
import { Github, Gitlab, Package, ChevronRight, Check, AlertCircle, Sparkles, Key, ExternalLink, Zap } from 'lucide-react';
import { useGitBranches } from '@/hooks/useGitBranches';
import { MonorepoAnalyzer } from '@/components/features/MonorepoAnalyzer';
import type { Project, Environment, Server } from '@/types';

interface WildcardDomain {
    host: string;
    scheme: string;
}

interface Props {
    projects?: Project[];
    localhost?: Server;
    userServers?: Server[];
    needsProject?: boolean;
    preselectedSource?: SourceType | null;
    wildcardDomain?: WildcardDomain | null;
}

type SourceType = 'github' | 'gitlab' | 'bitbucket' | 'docker';
type BuildPack = 'nixpacks' | 'dockerfile' | 'dockercompose' | 'dockerimage';

interface FormData {
    name: string;
    source_type: SourceType | null;
    git_repository: string;
    git_branch: string;
    build_pack: BuildPack;
    project_uuid: string;
    environment_uuid: string;
    server_uuid: string;
    fqdn: string;
    description: string;
    docker_image?: string;
}

export default function ApplicationsCreate({ projects = [], localhost, userServers = [], needsProject = false, preselectedSource = null, wildcardDomain = null }: Props) {
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

    const [formData, setFormData] = useState<FormData>({
        name: '',
        source_type: initialSource,
        git_repository: '',
        git_branch: 'main',
        build_pack: 'nixpacks',
        project_uuid: projects[0]?.uuid || '',
        environment_uuid: projects[0]?.environments[0]?.uuid || '',
        server_uuid: 'auto',
        fqdn: '',
        description: '',
    });
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    // Git branches fetching
    const {
        branches,
        defaultBranch,
        isLoading: isBranchesLoading,
        error: branchesError,
        fetchBranches,
    } = useGitBranches({ debounceMs: 600 });

    // Fetch branches when repository URL changes
    useEffect(() => {
        if (formData.git_repository && formData.source_type !== 'docker') {
            fetchBranches(formData.git_repository);
        }
    }, [formData.git_repository, formData.source_type, fetchBranches]);

    // Set default branch when branches are loaded
    useEffect(() => {
        if (defaultBranch && branches.length > 0) {
            setFormData(prev => ({ ...prev, git_branch: defaultBranch }));
        }
    }, [defaultBranch, branches.length]);

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
                // Update the project in projectList with new environment
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
        // Redirect to project page where all created apps are visible
        if (formData.project_uuid) {
            router.visit(`/projects/${formData.project_uuid}`);
        } else if (result.applications.length === 1) {
            // Single app - go directly to it
            router.visit(`/applications/${result.applications[0].uuid}`);
        } else {
            router.visit('/applications');
        }
    };

    const handleStartAnalysis = () => {
        if (!formData.git_repository) {
            setErrors({ git_repository: 'Repository URL is required for analysis' });
            return;
        }
        if (!formData.project_uuid || !formData.environment_uuid) {
            setErrors({ project_uuid: 'Please select project and environment first' });
            return;
        }
        setUseMonorepoAnalyzer(true);
        setStep('analyze');
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        // Basic validation
        const newErrors: Record<string, string> = {};
        if (!formData.name) newErrors.name = 'Application name is required';
        if (!formData.source_type) newErrors.source_type = 'Source type is required';
        if (formData.source_type !== 'docker' && !formData.git_repository) {
            newErrors.git_repository = 'Repository URL is required';
        }
        if (formData.source_type === 'docker' && !formData.docker_image) {
            newErrors.docker_image = 'Docker image is required';
        }
        if (!formData.project_uuid) newErrors.project_uuid = 'Project is required';
        if (!formData.environment_uuid) newErrors.environment_uuid = 'Environment is required';
        // server_uuid is optional - 'auto' or empty triggers smart selection

        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            return;
        }

        setIsSubmitting(true);

        router.post('/applications', formData as any, {
            onError: (errors) => {
                setErrors(errors as Record<string, string>);
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
                        <>
                            <StepIndicator number={3} label="Analyze" active={step === 'analyze'} completed={false} />
                        </>
                    ) : (
                        <StepIndicator number={3} label="Deploy" active={step === 3} completed={false} />
                    )}
                </div>

                <form onSubmit={handleSubmit}>
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
                                    icon={<Package className="h-6 w-6" />}
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
                                    <div>
                                        <label className="block text-sm font-medium text-foreground mb-2">
                                            Application Name *
                                        </label>
                                        <Input
                                            value={formData.name}
                                            onChange={(e) => setFormData(prev => ({ ...prev, name: e.target.value }))}
                                            placeholder="my-awesome-app"
                                            error={errors.name}
                                        />
                                        {errors.name && (
                                            <p className="mt-1 text-sm text-destructive">{errors.name}</p>
                                        )}
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
                                        <div>
                                            <label className="block text-sm font-medium text-foreground mb-2">
                                                Repository URL *
                                            </label>
                                            <Input
                                                value={formData.git_repository}
                                                onChange={(e) => setFormData(prev => ({ ...prev, git_repository: e.target.value }))}
                                                placeholder="https://github.com/user/repo"
                                                error={errors.git_repository}
                                            />
                                            {errors.git_repository && (
                                                <p className="mt-1 text-sm text-destructive">{errors.git_repository}</p>
                                            )}
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
                                    </div>
                                ) : (
                                    <div>
                                        <label className="block text-sm font-medium text-foreground mb-2">
                                            Docker Image *
                                        </label>
                                        <Input
                                            value={formData.docker_image || ''}
                                            onChange={(e) => setFormData(prev => ({ ...prev, docker_image: e.target.value }))}
                                            placeholder="nginx:latest"
                                            error={errors.docker_image}
                                        />
                                        {errors.docker_image && (
                                            <p className="mt-1 text-sm text-destructive">{errors.docker_image}</p>
                                        )}
                                    </div>
                                )}

                                {/* Deployment Configuration */}
                                <div className="space-y-4">
                                    <div>
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
                                                    }}
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

                                    <div>
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
                                                    onChange={(e) => setFormData(prev => ({ ...prev, environment_uuid: e.target.value }))}
                                                    disabled={!formData.project_uuid}
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
                                    {formData.source_type !== 'docker' && formData.git_repository && (
                                        <div className="rounded-lg bg-primary/5 border border-primary/20 p-4">
                                            <div className="flex items-start gap-3">
                                                <Sparkles className="h-5 w-5 text-primary shrink-0 mt-0.5" />
                                                <div className="flex-1">
                                                    <p className="text-sm font-medium text-foreground">Smart Deploy (Recommended)</p>
                                                    <p className="text-sm text-foreground-muted mt-1">
                                                        Automatically detect monorepo structure, frameworks, databases and create everything with one click.
                                                    </p>
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

                    {/* Step: Analyze (Monorepo) */}
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
                                ← Back to Configuration
                            </Button>
                            <MonorepoAnalyzer
                                gitRepository={formData.git_repository}
                                gitBranch={formData.git_branch}
                                environmentUuid={formData.environment_uuid}
                                destinationUuid={formData.server_uuid}
                                onComplete={handleMonorepoComplete}
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
                                        disabled={isSubmitting}
                                    >
                                        {isSubmitting ? 'Creating...' : 'Create & Deploy'}
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
