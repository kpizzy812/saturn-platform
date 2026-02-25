import { useState, useEffect, useCallback } from 'react';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Badge } from '@/components/ui/Badge';
import { BranchSelector } from '@/components/ui/BranchSelector';
import { useGitBranches } from '@/hooks/useGitBranches';
import {
    Github,
    Search,
    Loader2,
    CheckCircle,
    AlertCircle,
    ExternalLink,
    Link as LinkIcon,
} from 'lucide-react';
import { Button } from '@/components/ui/Button';

// ── Types ───────────────────────────────────────────────────────────

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

export interface RepoSelectorResult {
    gitRepository: string;
    gitBranch: string;
    githubAppId?: number;
    repoName: string;
}

interface RepoSelectorProps {
    githubApps: GithubApp[];
    onRepoSelected: (result: RepoSelectorResult) => void;
    /** Current git repository URL (controlled) */
    value?: string;
    /** Current branch (controlled) */
    branch?: string;
}

// ── Helpers ─────────────────────────────────────────────────────────

const LANGUAGE_COLORS: Record<string, string> = {
    TypeScript: 'bg-blue-500',
    JavaScript: 'bg-yellow-500',
    Python: 'bg-blue-400',
    Ruby: 'bg-red-500',
    Go: 'bg-cyan-500',
    Rust: 'bg-orange-500',
    PHP: 'bg-indigo-500',
    Java: 'bg-red-600',
    HTML: 'bg-orange-400',
    CSS: 'bg-purple-500',
};

function extractRepoName(url: string): string {
    try {
        const cleaned = url.replace(/\.git$/, '').replace(/\/$/, '');
        const parts = cleaned.split('/');
        return parts[parts.length - 1] || '';
    } catch {
        return '';
    }
}

// ── Component ───────────────────────────────────────────────────────

export function RepoSelector({ githubApps, onRepoSelected, value = '', branch = 'main' }: RepoSelectorProps) {
    const hasApps = githubApps.length > 0;
    const [repoMode, setRepoMode] = useState<'picker' | 'manual'>(hasApps ? 'picker' : 'manual');
    const [selectedGithubApp, setSelectedGithubApp] = useState<GithubApp | null>(hasApps ? githubApps[0] : null);

    // GitHub App repo picker state
    const [ghRepos, setGhRepos] = useState<GithubRepository[]>([]);
    const [ghBranches, setGhBranches] = useState<GithubBranch[]>([]);
    const [selectedRepo, setSelectedRepo] = useState<GithubRepository | null>(null);
    const [isLoadingRepos, setIsLoadingRepos] = useState(false);
    const [isLoadingGhBranches, setIsLoadingGhBranches] = useState(false);
    const [repoSearchQuery, setRepoSearchQuery] = useState('');
    const [repoError, setRepoError] = useState<string | null>(null);

    // Manual mode state
    const [manualUrl, setManualUrl] = useState(value);
    const [manualBranch, setManualBranch] = useState(branch);

    // Manual branch fetching
    const {
        branches,
        defaultBranch,
        isLoading: isBranchesLoading,
        error: branchesError,
        fetchBranches,
    } = useGitBranches({ debounceMs: 600 });

    // Fetch branches when manual URL changes
    useEffect(() => {
        if (manualUrl && repoMode === 'manual') {
            fetchBranches(manualUrl);
        }
    }, [manualUrl, repoMode, fetchBranches]);

    // Set default branch when loaded
    useEffect(() => {
        if (defaultBranch && branches.length > 0) {
            setManualBranch(defaultBranch);
        }
    }, [defaultBranch, branches.length]);

    // Notify parent on manual URL/branch change
    useEffect(() => {
        if (repoMode === 'manual' && manualUrl) {
            onRepoSelected({
                gitRepository: manualUrl,
                gitBranch: manualBranch,
                repoName: extractRepoName(manualUrl),
            });
        }
    }, [manualUrl, manualBranch, repoMode]); // eslint-disable-line react-hooks/exhaustive-deps

    // Load repos from GitHub App
    const loadGhRepos = useCallback(async () => {
        if (!selectedGithubApp) return;
        setIsLoadingRepos(true);
        setRepoError(null);
        setGhRepos([]);
        try {
            const response = await fetch(`/web-api/github-apps/${selectedGithubApp.id}/repositories`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
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

    // Load branches for selected repo
    const loadGhBranches = useCallback(
        async (repo: GithubRepository) => {
            if (!selectedGithubApp || !repo) return;
            setIsLoadingGhBranches(true);
            setGhBranches([]);
            try {
                const [owner, repoName] = repo.full_name.split('/');
                const response = await fetch(`/web-api/github-apps/${selectedGithubApp.id}/repositories/${owner}/${repoName}/branches`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!response.ok) throw new Error('Failed to load branches');
                const data = await response.json();
                setGhBranches(data.branches || []);
            } catch {
                setGhBranches([]);
            } finally {
                setIsLoadingGhBranches(false);
            }
        },
        [selectedGithubApp],
    );

    // Load repos on mount / app change
    useEffect(() => {
        if (repoMode === 'picker' && selectedGithubApp) {
            loadGhRepos();
        }
    }, [repoMode, selectedGithubApp, loadGhRepos]);

    // Load branches when repo is selected
    useEffect(() => {
        if (selectedRepo && selectedGithubApp) {
            loadGhBranches(selectedRepo);
        }
    }, [selectedRepo, selectedGithubApp, loadGhBranches]);

    const handleRepoSelect = (repo: GithubRepository) => {
        setSelectedRepo(repo);
        const gitUrl = `https://github.com/${repo.full_name}`;
        onRepoSelected({
            gitRepository: gitUrl,
            gitBranch: repo.default_branch,
            githubAppId: selectedGithubApp?.id,
            repoName: repo.name,
        });
    };

    const handleGhBranchSelect = (branchName: string) => {
        if (!selectedRepo) return;
        onRepoSelected({
            gitRepository: `https://github.com/${selectedRepo.full_name}`,
            gitBranch: branchName,
            githubAppId: selectedGithubApp?.id,
            repoName: selectedRepo.name,
        });
    };

    const filteredGhRepos = ghRepos.filter(
        (repo) =>
            repo.name.toLowerCase().includes(repoSearchQuery.toLowerCase()) ||
            repo.full_name.toLowerCase().includes(repoSearchQuery.toLowerCase()) ||
            repo.description?.toLowerCase().includes(repoSearchQuery.toLowerCase()),
    );

    return (
        <div className="space-y-4">
            {/* Mode toggle */}
            {hasApps && (
                <div className="flex gap-2">
                    <button
                        type="button"
                        onClick={() => setRepoMode('picker')}
                        className={`flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium transition-all ${
                            repoMode === 'picker' ? 'border-primary bg-primary/10 text-primary' : 'border-border text-foreground-muted hover:border-primary/50'
                        }`}
                    >
                        <Github className="h-4 w-4" />
                        My Repositories
                    </button>
                    <button
                        type="button"
                        onClick={() => setRepoMode('manual')}
                        className={`flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium transition-all ${
                            repoMode === 'manual' ? 'border-primary bg-primary/10 text-primary' : 'border-border text-foreground-muted hover:border-primary/50'
                        }`}
                    >
                        <LinkIcon className="h-4 w-4" />
                        Paste URL
                    </button>
                </div>
            )}

            {/* GitHub App repo picker */}
            {repoMode === 'picker' && hasApps ? (
                <div className="space-y-4">
                    {/* App selector (multiple apps) */}
                    {githubApps.length > 1 && (
                        <Select
                            value={selectedGithubApp?.id.toString() || ''}
                            onChange={(e) => {
                                const app = githubApps.find((a) => a.id === parseInt(e.target.value));
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
                    )}

                    {/* Error */}
                    {repoError && (
                        <div className="rounded-lg bg-danger/10 p-3 text-sm text-danger">
                            <div className="flex items-center gap-2">
                                <AlertCircle className="h-4 w-4 shrink-0" />
                                <span>{repoError}</span>
                            </div>
                            <Button type="button" variant="ghost" size="sm" onClick={loadGhRepos} className="mt-2">
                                Try Again
                            </Button>
                        </div>
                    )}

                    {/* Loading repos */}
                    {isLoadingRepos ? (
                        <div className="flex items-center justify-center rounded-lg border border-border bg-background-secondary py-8">
                            <Loader2 className="h-6 w-6 animate-spin text-primary" />
                            <span className="ml-3 text-sm text-foreground-muted">Loading repositories...</span>
                        </div>
                    ) : (
                        <>
                            {/* Search */}
                            <div className="relative">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                                <Input
                                    placeholder="Search repositories..."
                                    value={repoSearchQuery}
                                    onChange={(e) => setRepoSearchQuery(e.target.value)}
                                    className="pl-10"
                                />
                            </div>

                            {/* Repo list */}
                            <div className="max-h-64 space-y-1.5 overflow-y-auto rounded-lg border border-border bg-background-secondary p-2">
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
                                            selectedRepo?.id === repo.id ? 'border-primary bg-primary/5' : 'border-transparent hover:bg-background-tertiary'
                                        }`}
                                    >
                                        <div className="flex items-center justify-between">
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center gap-2">
                                                    <span className="truncate text-sm font-medium text-foreground">{repo.name}</span>
                                                    {repo.private && (
                                                        <Badge variant="default" className="px-1.5 py-0 text-[10px]">
                                                            Private
                                                        </Badge>
                                                    )}
                                                    {repo.language && (
                                                        <div className="flex items-center gap-1">
                                                            <div className={`h-2 w-2 rounded-full ${LANGUAGE_COLORS[repo.language] || 'bg-foreground-muted'}`} />
                                                            <span className="text-xs text-foreground-muted">{repo.language}</span>
                                                        </div>
                                                    )}
                                                </div>
                                                {repo.description && <p className="mt-0.5 truncate text-xs text-foreground-muted">{repo.description}</p>}
                                            </div>
                                            {selectedRepo?.id === repo.id && <CheckCircle className="ml-2 h-4 w-4 shrink-0 text-primary" />}
                                        </div>
                                    </button>
                                ))}
                            </div>
                        </>
                    )}

                    {/* Branch selector for picked repo */}
                    {selectedRepo && (
                        <div>
                            <label className="mb-2 block text-sm font-medium text-foreground">Branch</label>
                            {isLoadingGhBranches ? (
                                <div className="flex items-center gap-2 py-2">
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                    <span className="text-sm text-foreground-muted">Loading branches...</span>
                                </div>
                            ) : (
                                <Select value={branch} onChange={(e) => handleGhBranchSelect(e.target.value)}>
                                    {ghBranches.map((b) => (
                                        <option key={b.name} value={b.name}>
                                            {b.name}
                                            {selectedRepo && b.name === selectedRepo.default_branch ? ' (default)' : ''}
                                        </option>
                                    ))}
                                </Select>
                            )}
                        </div>
                    )}
                </div>
            ) : (
                /* Manual URL mode */
                <div className="space-y-4">
                    {/* Connect GitHub prompt when no apps */}
                    {!hasApps && (
                        <div className="rounded-lg border border-primary/20 bg-primary/5 p-4">
                            <div className="flex items-start gap-3">
                                <Github className="mt-0.5 h-5 w-5 shrink-0 text-primary" />
                                <div className="flex-1">
                                    <p className="text-sm font-medium text-foreground">Connect GitHub for private repos</p>
                                    <p className="mt-1 text-sm text-foreground-muted">
                                        Connect a GitHub App to browse repositories, deploy private repos, and enable auto-deploy on push.
                                    </p>
                                    <a
                                        href="/sources/github/create"
                                        className="mt-3 inline-flex items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-primary/90"
                                    >
                                        <Github className="h-4 w-4" />
                                        Connect GitHub App
                                        <ExternalLink className="h-3 w-3" />
                                    </a>
                                </div>
                            </div>
                        </div>
                    )}

                    <div>
                        <label className="mb-2 block text-sm font-medium text-foreground">Repository URL</label>
                        <Input
                            value={manualUrl}
                            onChange={(e) => setManualUrl(e.target.value)}
                            placeholder="https://github.com/user/repo"
                        />
                    </div>

                    <div>
                        <label className="mb-2 block text-sm font-medium text-foreground">Branch</label>
                        <BranchSelector
                            value={manualBranch}
                            onChange={(val) => setManualBranch(val)}
                            branches={branches}
                            isLoading={isBranchesLoading}
                            error={branchesError}
                            placeholder="main"
                        />
                    </div>
                </div>
            )}
        </div>
    );
}

export { extractRepoName };
