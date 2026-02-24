import { useState, useCallback } from 'react';
import { router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Link } from '@inertiajs/react';
import { Card, CardContent, Button, Input, Select } from '@/components/ui';
import { RepoSelector, extractRepoName } from '@/components/features/RepoSelector';
import type { RepoSelectorResult } from '@/components/features/RepoSelector';
import { MonorepoAnalyzer } from '@/components/features/MonorepoAnalyzer';
import { DeployGuide } from '@/components/features/DeployGuide';
import {
    ArrowLeft,
    Sparkles,
    Database,
    FileCode,
    Folder,
    Settings2,
    Rocket,
    Globe,
} from 'lucide-react';
import { BrandIcon } from '@/components/ui/BrandIcon';
import type { Project } from '@/types';

// ── Types ───────────────────────────────────────────────────────────

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

interface Props {
    projects?: Project[];
    wildcardDomain?: WildcardDomain | null;
    hasGithubApp?: boolean;
    githubApps?: GithubApp[];
    /** Pre-selected project UUID (when creating from canvas) */
    preselectedProject?: string | null;
    /** Pre-selected environment UUID (when creating from canvas) */
    preselectedEnvironment?: string | null;
}

type Phase = 'repo_select' | 'analyze_deploy';

// ── Helpers ─────────────────────────────────────────────────────────

function toSubdomain(name: string): string {
    return name
        .toLowerCase()
        .replace(/[^a-z0-9-]/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
}

// ── Secondary deploy options ────────────────────────────────────────

const secondaryOptions = [
    {
        icon: <Database className="h-4 w-4" />,
        label: 'Database',
        href: '/databases/create',
        iconBg: 'bg-blue-500/10 text-blue-400',
    },
    {
        icon: <BrandIcon name="docker" className="h-4 w-4" />,
        label: 'Docker Image',
        href: '/applications/create?source=docker',
        iconBg: 'bg-cyan-500/10 text-cyan-400',
    },
    {
        icon: <FileCode className="h-4 w-4" />,
        label: 'Template',
        href: '/templates',
        iconBg: 'bg-purple-500/10 text-purple-400',
    },
    {
        icon: <Folder className="h-4 w-4" />,
        label: 'Empty Project',
        href: '/projects/create/empty',
        iconBg: 'bg-emerald-500/10 text-emerald-400',
    },
];

// ── Main Component ──────────────────────────────────────────────────

export default function ProjectCreate({ projects = [], wildcardDomain = null, hasGithubApp: _hasGithubApp = false, githubApps = [], preselectedProject = null, preselectedEnvironment = null }: Props) {
    // When project is preselected (from canvas), we skip project creation
    const hasPreselectedProject = !!(preselectedProject && preselectedEnvironment);

    const [phase, setPhase] = useState<Phase>('repo_select');

    // Repo selection
    const [gitRepository, setGitRepository] = useState('');
    const [gitBranch, setGitBranch] = useState('main');
    const [githubAppId, setGithubAppId] = useState<number | undefined>();

    // Quick config
    const preselectedProjectObj = projects.find(p => p.uuid === preselectedProject);
    const [projectName, setProjectName] = useState(preselectedProjectObj?.name || '');
    const [subdomain, setSubdomain] = useState('');

    // Created resources for MonorepoAnalyzer
    const [environmentUuid, setEnvironmentUuid] = useState(preselectedEnvironment || '');
    const [projectUuid, setProjectUuid] = useState(preselectedProject || '');

    // Project selection mode: 'new' (create new) or 'existing' (pick from list)
    const [projectMode, setProjectMode] = useState<'new' | 'existing'>(hasPreselectedProject ? 'existing' : 'new');

    // UI state
    const [isCreatingProject, setIsCreatingProject] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // Whether a repo has been selected (for showing quick config)
    const hasRepo = gitRepository.length > 0;

    const handleRepoSelected = useCallback((result: RepoSelectorResult) => {
        setGitRepository(result.gitRepository);
        setGitBranch(result.gitBranch);
        setGithubAppId(result.githubAppId);

        const name = result.repoName || extractRepoName(result.gitRepository);
        setProjectName(name);
        setSubdomain(toSubdomain(name));
    }, []);

    const handleAnalyzeAndDeploy = async () => {
        if (!gitRepository) {
            setError('Please select or enter a repository URL');
            return;
        }

        // If project is preselected (from canvas) or user picked existing, skip creation
        if (hasPreselectedProject || (projectMode === 'existing' && projectUuid && environmentUuid)) {
            setPhase('analyze_deploy');
            return;
        }

        setIsCreatingProject(true);
        setError(null);

        try {
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
            const name = projectName.trim() || extractRepoName(gitRepository);

            // Create project (auto-creates development/uat/production environments)
            const response = await fetch('/projects', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ name }),
            });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                throw new Error(data.message || 'Failed to create project');
            }

            const project = await response.json();
            const devEnv = project.environments?.find((e: { name: string }) => e.name === 'development') || project.environments?.[0];

            if (!devEnv) {
                throw new Error('No environment found in created project');
            }

            setProjectUuid(project.uuid);
            setEnvironmentUuid(devEnv.uuid);
            setPhase('analyze_deploy');
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to create project');
        } finally {
            setIsCreatingProject(false);
        }
    };

    const handleProvisionComplete = useCallback(
        (result: { applications: Array<{ uuid: string; name: string }>; monorepo_group_id: string | null }) => {
            if (projectUuid) {
                router.visit(`/projects/${projectUuid}`);
            } else if (result.applications.length === 1) {
                router.visit(`/applications/${result.applications[0].uuid}`);
            } else {
                router.visit('/dashboard');
            }
        },
        [projectUuid],
    );

    return (
        <AppLayout title="Deploy Project" showNewProject={false}>
            <div className="flex min-h-full items-start justify-center py-8">
                <div className="w-full max-w-2xl px-4">
                    {phase === 'repo_select' && (
                        <>
                            {/* Back link */}
                            <Link
                                href={hasPreselectedProject ? `/projects/${preselectedProject}` : '/dashboard'}
                                className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
                            >
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                {hasPreselectedProject ? `Back to ${preselectedProjectObj?.name || 'Project'}` : 'Back to Dashboard'}
                            </Link>

                            {/* Header */}
                            <div className="mb-6 flex items-start justify-between">
                                <div>
                                    <h1 className="text-2xl font-semibold text-foreground">Deploy your project</h1>
                                    <p className="mt-1 text-sm text-foreground-muted">Import a Git repository to get started</p>
                                </div>
                                <Link
                                    href="/applications/create"
                                    className="flex items-center gap-1.5 rounded-lg border border-border px-3 py-1.5 text-sm text-foreground-muted transition-colors hover:border-primary/50 hover:text-foreground"
                                >
                                    <Settings2 className="h-3.5 w-3.5" />
                                    Advanced
                                </Link>
                            </div>

                            {/* Main Card: Repo Selection + Quick Config */}
                            <Card>
                                <CardContent className="space-y-6 p-6">
                                    {/* Repo Selector */}
                                    <RepoSelector
                                        githubApps={githubApps}
                                        onRepoSelected={handleRepoSelected}
                                        value={gitRepository}
                                        branch={gitBranch}
                                    />

                                    {/* Deploy Guide */}
                                    <DeployGuide variant="compact" />

                                    {/* Quick Config (appears after repo is selected) */}
                                    {hasRepo && (
                                        <div className="space-y-4 border-t border-border pt-4">
                                            {/* Project selection */}
                                            <div>
                                                <label className="mb-2 block text-sm font-medium text-foreground">Project</label>
                                                {hasPreselectedProject ? (
                                                    <div className="space-y-3">
                                                        <div className="rounded-lg border border-border bg-background-secondary px-3 py-2 text-sm text-foreground">
                                                            {preselectedProjectObj?.name || 'Selected Project'}
                                                        </div>
                                                        {/* Environment select for preselected project */}
                                                        {(() => {
                                                            const envs = preselectedProjectObj?.environments || [];
                                                            return envs.length > 1 ? (
                                                                <div>
                                                                    <label className="mb-1.5 block text-xs font-medium text-foreground-muted">Environment</label>
                                                                    <Select
                                                                        value={environmentUuid}
                                                                        onChange={(e) => setEnvironmentUuid(e.target.value)}
                                                                    >
                                                                        {envs.map(env => (
                                                                            <option key={env.uuid} value={env.uuid}>{env.name}</option>
                                                                        ))}
                                                                    </Select>
                                                                </div>
                                                            ) : envs.length === 1 ? (
                                                                <p className="text-xs text-foreground-muted">Environment: {envs[0].name}</p>
                                                            ) : null;
                                                        })()}
                                                    </div>
                                                ) : (
                                                    <>
                                                        {/* Mode toggle: existing vs new */}
                                                        {projects.length > 0 && (
                                                            <div className="mb-2 flex gap-2">
                                                                <button
                                                                    type="button"
                                                                    onClick={() => setProjectMode('new')}
                                                                    className={`rounded-md px-3 py-1 text-xs font-medium transition-all ${
                                                                        projectMode === 'new'
                                                                            ? 'bg-primary/10 text-primary'
                                                                            : 'text-foreground-muted hover:text-foreground'
                                                                    }`}
                                                                >
                                                                    New project
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    onClick={() => {
                                                                        setProjectMode('existing');
                                                                        // Pre-select first project
                                                                        if (projects.length > 0 && !projectUuid) {
                                                                            setProjectUuid(projects[0].uuid);
                                                                            setEnvironmentUuid(projects[0].environments?.[0]?.uuid || '');
                                                                            setProjectName(projects[0].name);
                                                                        }
                                                                    }}
                                                                    className={`rounded-md px-3 py-1 text-xs font-medium transition-all ${
                                                                        projectMode === 'existing'
                                                                            ? 'bg-primary/10 text-primary'
                                                                            : 'text-foreground-muted hover:text-foreground'
                                                                    }`}
                                                                >
                                                                    Existing project
                                                                </button>
                                                            </div>
                                                        )}

                                                        {projectMode === 'existing' && projects.length > 0 ? (
                                                            <div className="space-y-3">
                                                                <Select
                                                                    value={projectUuid}
                                                                    onChange={(e) => {
                                                                        const proj = projects.find(p => p.uuid === e.target.value);
                                                                        if (proj) {
                                                                            setProjectUuid(proj.uuid);
                                                                            setEnvironmentUuid(proj.environments?.[0]?.uuid || '');
                                                                            setProjectName(proj.name);
                                                                        }
                                                                    }}
                                                                >
                                                                    {projects.map(p => (
                                                                        <option key={p.uuid} value={p.uuid}>{p.name}</option>
                                                                    ))}
                                                                </Select>
                                                                {/* Environment select */}
                                                                {(() => {
                                                                    const selectedProj = projects.find(p => p.uuid === projectUuid);
                                                                    const envs = selectedProj?.environments || [];
                                                                    return envs.length > 1 ? (
                                                                        <div>
                                                                            <label className="mb-1.5 block text-xs font-medium text-foreground-muted">Environment</label>
                                                                            <Select
                                                                                value={environmentUuid}
                                                                                onChange={(e) => setEnvironmentUuid(e.target.value)}
                                                                            >
                                                                                {envs.map(env => (
                                                                                    <option key={env.uuid} value={env.uuid}>{env.name}</option>
                                                                                ))}
                                                                            </Select>
                                                                        </div>
                                                                    ) : envs.length === 1 ? (
                                                                        <p className="text-xs text-foreground-muted">Environment: {envs[0].name}</p>
                                                                    ) : null;
                                                                })()}
                                                            </div>
                                                        ) : (
                                                            <div>
                                                                <Input
                                                                    value={projectName}
                                                                    onChange={(e) => setProjectName(e.target.value)}
                                                                    placeholder="my-project"
                                                                />
                                                                <p className="mt-1 text-xs text-foreground-muted">
                                                                    New project with development environment will be created
                                                                </p>
                                                            </div>
                                                        )}
                                                    </>
                                                )}
                                            </div>

                                            {/* Subdomain */}
                                            {wildcardDomain && (
                                                <div>
                                                    <label className="mb-2 flex items-center gap-1.5 text-sm font-medium text-foreground">
                                                        <Globe className="h-3.5 w-3.5" />
                                                        Domain
                                                    </label>
                                                    <div className="flex items-center gap-0">
                                                        <Input
                                                            value={subdomain}
                                                            onChange={(e) => setSubdomain(e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, ''))}
                                                            placeholder="my-app"
                                                            className="rounded-r-none"
                                                        />
                                                        <span className="inline-flex h-9 items-center whitespace-nowrap rounded-r-md border border-l-0 border-border bg-muted px-3 text-sm text-foreground-muted">
                                                            .{wildcardDomain.host}
                                                        </span>
                                                    </div>
                                                </div>
                                            )}

                                            {/* Error */}
                                            {error && (
                                                <div className="rounded-lg bg-danger/10 p-3 text-sm text-danger">{error}</div>
                                            )}

                                            {/* CTA */}
                                            <Button onClick={handleAnalyzeAndDeploy} loading={isCreatingProject} className="w-full" size="lg">
                                                <Sparkles className="mr-2 h-4 w-4" />
                                                Analyze & Deploy
                                            </Button>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Secondary Options */}
                            <div className="mt-8">
                                <div className="relative mb-4 flex items-center">
                                    <div className="flex-grow border-t border-border/50" />
                                    <span className="mx-4 text-xs text-foreground-subtle">or deploy something else</span>
                                    <div className="flex-grow border-t border-border/50" />
                                </div>
                                <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                    {secondaryOptions.map((opt) => (
                                        <Link
                                            key={opt.label}
                                            href={opt.href}
                                            className="group flex items-center gap-2.5 rounded-lg border border-border/50 bg-background-secondary/50 px-3 py-2.5 text-sm transition-all hover:-translate-y-0.5 hover:border-border hover:shadow-lg hover:shadow-black/10"
                                        >
                                            <div className={`flex h-7 w-7 items-center justify-center rounded-md ${opt.iconBg}`}>{opt.icon}</div>
                                            <span className="text-foreground-muted transition-colors group-hover:text-foreground">{opt.label}</span>
                                        </Link>
                                    ))}
                                </div>
                            </div>
                        </>
                    )}

                    {phase === 'analyze_deploy' && (
                        <>
                            {/* Back button */}
                            <button
                                type="button"
                                onClick={() => setPhase('repo_select')}
                                className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
                            >
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Back to repo selection
                            </button>

                            {/* Repo info badge */}
                            <div className="mb-4 flex items-center gap-3 rounded-lg border border-border/50 bg-background-secondary/50 px-4 py-3">
                                <Rocket className="h-5 w-5 text-primary" />
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-medium text-foreground">{projectName}</p>
                                    <p className="truncate text-xs text-foreground-muted">{gitRepository} &middot; {gitBranch}</p>
                                </div>
                            </div>

                            {/* MonorepoAnalyzer — does the heavy lifting */}
                            <MonorepoAnalyzer
                                gitRepository={gitRepository}
                                gitBranch={gitBranch}
                                githubAppId={githubAppId}
                                environmentUuid={environmentUuid}
                                destinationUuid="auto"
                                onComplete={handleProvisionComplete}
                                autoStart
                            />
                        </>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
