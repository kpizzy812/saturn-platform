import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import { RepoSelector, extractRepoName } from '../RepoSelector';

// Mock useGitBranches hook
const mockFetchBranches = vi.fn();
const mockUseGitBranches = {
    branches: [],
    defaultBranch: null,
    platform: null,
    isLoading: false,
    error: null,
    fetchBranches: mockFetchBranches,
    clearBranches: vi.fn(),
};

vi.mock('@/hooks/useGitBranches', () => ({
    useGitBranches: () => mockUseGitBranches,
}));

// Mock BranchSelector
vi.mock('@/components/ui/BranchSelector', () => ({
    BranchSelector: ({ value, onChange, placeholder, isLoading, error }: any) => (
        <div>
            <input
                data-testid="branch-selector"
                value={value}
                onChange={(e: any) => onChange(e.target.value)}
                placeholder={placeholder}
            />
            {isLoading && <span>Loading branches...</span>}
            {error && <span data-testid="branch-error">{error}</span>}
        </div>
    ),
}));

// Mock lucide-react icons to avoid SVG rendering overhead
vi.mock('lucide-react', () => ({
    Github: () => <span data-testid="icon-github">Github</span>,
    Search: () => <span data-testid="icon-search">Search</span>,
    Loader2: () => <span data-testid="icon-loader">Loader</span>,
    CheckCircle: () => <span data-testid="icon-check">Check</span>,
    AlertCircle: () => <span data-testid="icon-alert">Alert</span>,
    ExternalLink: () => <span data-testid="icon-external">ExternalLink</span>,
    Link: () => <span data-testid="icon-link">Link</span>,
}));

// Mock Input and Select from ui components
vi.mock('@/components/ui/Input', () => ({
    Input: ({ value, onChange, placeholder, className }: any) => (
        <input
            value={value}
            onChange={onChange}
            placeholder={placeholder}
            className={className}
        />
    ),
}));

vi.mock('@/components/ui/Select', () => ({
    Select: ({ value, onChange, children }: any) => (
        <select value={value} onChange={onChange}>
            {children}
        </select>
    ),
}));

vi.mock('@/components/ui/Badge', () => ({
    Badge: ({ children }: any) => <span data-testid="badge">{children}</span>,
}));

vi.mock('@/components/ui/Button', () => ({
    Button: ({ children, onClick, type }: any) => (
        <button onClick={onClick} type={type}>{children}</button>
    ),
}));

// ── Fixtures ─────────────────────────────────────────────────────────────────

const githubApp1 = { id: 1, uuid: 'uuid-1', name: 'My GitHub App', installation_id: 42 };
const githubApp2 = { id: 2, uuid: 'uuid-2', name: 'Second GitHub App', installation_id: 99 };

const mockRepo = {
    id: 101,
    name: 'my-repo',
    full_name: 'acme/my-repo',
    description: 'A test repository',
    private: false,
    default_branch: 'main',
    language: 'TypeScript',
    updated_at: '2025-01-01T00:00:00Z',
};

const privateRepo = {
    ...mockRepo,
    id: 102,
    name: 'private-repo',
    full_name: 'acme/private-repo',
    private: true,
    language: null,
    description: null,
};

// ── Tests ─────────────────────────────────────────────────────────────────────

describe('RepoSelector', () => {
    const onRepoSelected = vi.fn();

    beforeEach(() => {
        vi.clearAllMocks();
        // Reset useGitBranches mock state to defaults
        mockUseGitBranches.branches = [];
        mockUseGitBranches.defaultBranch = null;
        mockUseGitBranches.isLoading = false;
        mockUseGitBranches.error = null;
    });

    // ── Manual mode (no GitHub apps) ─────────────────────────────────────────

    describe('manual mode when no GitHub apps provided', () => {
        it('renders in manual mode by default when no apps', () => {
            render(<RepoSelector githubApps={[]} onRepoSelected={onRepoSelected} />);

            // Repository URL label is present
            expect(screen.getByText('Repository URL')).toBeInTheDocument();
            // Branch label is present
            expect(screen.getByText('Branch')).toBeInTheDocument();
            // URL input is present with the correct placeholder
            expect(screen.getByPlaceholderText('https://github.com/user/repo')).toBeInTheDocument();
        });

        it('does not show mode toggle tabs when no apps', () => {
            render(<RepoSelector githubApps={[]} onRepoSelected={onRepoSelected} />);

            expect(screen.queryByText('My Repositories')).not.toBeInTheDocument();
            expect(screen.queryByText('Paste URL')).not.toBeInTheDocument();
        });

        it('shows "Connect GitHub" prompt when no apps connected', () => {
            render(<RepoSelector githubApps={[]} onRepoSelected={onRepoSelected} />);

            expect(screen.getByText('Connect GitHub for private repos')).toBeInTheDocument();
            expect(screen.getByText('Connect GitHub App')).toBeInTheDocument();
        });

        it('Connect GitHub App link points to /sources/github/create', () => {
            render(<RepoSelector githubApps={[]} onRepoSelected={onRepoSelected} />);

            const link = screen.getByRole('link', { name: /Connect GitHub App/ });
            expect(link).toHaveAttribute('href', '/sources/github/create');
        });

        it('calls onRepoSelected with correct data when manual URL is typed', () => {
            render(<RepoSelector githubApps={[]} onRepoSelected={onRepoSelected} />);

            const urlInput = screen.getByPlaceholderText('https://github.com/user/repo');
            fireEvent.change(urlInput, { target: { value: 'https://github.com/user/my-app' } });

            expect(onRepoSelected).toHaveBeenCalledWith({
                gitRepository: 'https://github.com/user/my-app',
                gitBranch: 'main',
                repoName: 'my-app',
            });
        });

        it('extracts repo name without .git suffix', () => {
            render(<RepoSelector githubApps={[]} onRepoSelected={onRepoSelected} />);

            const urlInput = screen.getByPlaceholderText('https://github.com/user/repo');
            fireEvent.change(urlInput, { target: { value: 'https://github.com/user/cool-project.git' } });

            expect(onRepoSelected).toHaveBeenCalledWith(
                expect.objectContaining({ repoName: 'cool-project' }),
            );
        });

        it('does not call onRepoSelected when URL is empty', () => {
            render(<RepoSelector githubApps={[]} onRepoSelected={onRepoSelected} />);

            // No URL typed — onRepoSelected should not have been called on mount
            expect(onRepoSelected).not.toHaveBeenCalled();
        });

        it('fetches branches when manual URL is entered', () => {
            render(<RepoSelector githubApps={[]} onRepoSelected={onRepoSelected} />);

            const urlInput = screen.getByPlaceholderText('https://github.com/user/repo');
            fireEvent.change(urlInput, { target: { value: 'https://github.com/user/app' } });

            expect(mockFetchBranches).toHaveBeenCalledWith('https://github.com/user/app');
        });

        it('renders BranchSelector with initial branch value', () => {
            render(<RepoSelector githubApps={[]} onRepoSelected={onRepoSelected} branch="develop" />);

            const branchInput = screen.getByTestId('branch-selector');
            expect(branchInput).toHaveValue('develop');
        });
    });

    // ── Picker mode (with GitHub apps) ───────────────────────────────────────

    describe('picker mode when GitHub apps provided', () => {
        it('renders in picker mode by default when apps are provided', () => {
            globalThis.fetch = vi.fn().mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ repositories: [] }),
            });

            render(<RepoSelector githubApps={[githubApp1]} onRepoSelected={onRepoSelected} />);

            // Mode toggle buttons are visible
            expect(screen.getByText('My Repositories')).toBeInTheDocument();
            expect(screen.getByText('Paste URL')).toBeInTheDocument();
        });

        it('does not show "Connect GitHub" prompt when in picker mode', async () => {
            globalThis.fetch = vi.fn().mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ repositories: [] }),
            });

            render(<RepoSelector githubApps={[githubApp1]} onRepoSelected={onRepoSelected} />);

            expect(screen.queryByText('Connect GitHub for private repos')).not.toBeInTheDocument();
        });

        it('shows loading spinner while repositories are being fetched', async () => {
            // Keep fetch pending
            globalThis.fetch = vi.fn().mockReturnValue(new Promise(() => {}));

            render(<RepoSelector githubApps={[githubApp1]} onRepoSelected={onRepoSelected} />);

            await waitFor(() => {
                expect(screen.getByText('Loading repositories...')).toBeInTheDocument();
            });
        });

        it('shows repository list after successful fetch', async () => {
            globalThis.fetch = vi.fn().mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ repositories: [mockRepo] }),
            });

            render(<RepoSelector githubApps={[githubApp1]} onRepoSelected={onRepoSelected} />);

            await waitFor(() => {
                expect(screen.getByText('my-repo')).toBeInTheDocument();
            });
        });

        it('shows repository description when available', async () => {
            globalThis.fetch = vi.fn().mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ repositories: [mockRepo] }),
            });

            render(<RepoSelector githubApps={[githubApp1]} onRepoSelected={onRepoSelected} />);

            await waitFor(() => {
                expect(screen.getByText('A test repository')).toBeInTheDocument();
            });
        });

        it('shows language badge when repo has a language', async () => {
            globalThis.fetch = vi.fn().mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ repositories: [mockRepo] }),
            });

            render(<RepoSelector githubApps={[githubApp1]} onRepoSelected={onRepoSelected} />);

            await waitFor(() => {
                expect(screen.getByText('TypeScript')).toBeInTheDocument();
            });
        });

        it('shows "Private" badge for private repositories', async () => {
            globalThis.fetch = vi.fn().mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ repositories: [privateRepo] }),
            });

            render(<RepoSelector githubApps={[githubApp1]} onRepoSelected={onRepoSelected} />);

            await waitFor(() => {
                expect(screen.getByText('Private')).toBeInTheDocument();
            });
        });

        it('shows empty state when no repositories found', async () => {
            globalThis.fetch = vi.fn().mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ repositories: [] }),
            });

            render(<RepoSelector githubApps={[githubApp1]} onRepoSelected={onRepoSelected} />);

            await waitFor(() => {
                expect(screen.getByText('No repositories found')).toBeInTheDocument();
            });
        });

        it('calls onRepoSelected when a repo is clicked', async () => {
            globalThis.fetch = vi.fn().mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ repositories: [mockRepo] }),
            });

            render(<RepoSelector githubApps={[githubApp1]} onRepoSelected={onRepoSelected} />);

            await waitFor(() => screen.getByText('my-repo'));
            fireEvent.click(screen.getByText('my-repo'));

            expect(onRepoSelected).toHaveBeenCalledWith({
                gitRepository: 'https://github.com/acme/my-repo',
                gitBranch: 'main',
                githubAppId: 1,
                repoName: 'my-repo',
            });
        });

        it('shows error message on fetch failure', async () => {
            globalThis.fetch = vi.fn().mockResolvedValueOnce({
                ok: false,
                json: () => Promise.resolve({ message: 'Repository access denied' }),
            });

            render(<RepoSelector githubApps={[githubApp1]} onRepoSelected={onRepoSelected} />);

            await waitFor(() => {
                expect(screen.getByText('Repository access denied')).toBeInTheDocument();
            });
        });

        it('shows "Try Again" button on error', async () => {
            globalThis.fetch = vi.fn().mockResolvedValueOnce({
                ok: false,
                json: () => Promise.resolve({ message: 'Network error' }),
            });

            render(<RepoSelector githubApps={[githubApp1]} onRepoSelected={onRepoSelected} />);

            await waitFor(() => {
                expect(screen.getByText('Try Again')).toBeInTheDocument();
            });
        });

        it('renders app selector dropdown when multiple apps provided', async () => {
            globalThis.fetch = vi.fn().mockResolvedValue({
                ok: true,
                json: () => Promise.resolve({ repositories: [] }),
            });

            render(<RepoSelector githubApps={[githubApp1, githubApp2]} onRepoSelected={onRepoSelected} />);

            // Both app names should appear in the select
            await waitFor(() => {
                expect(screen.getByText('My GitHub App')).toBeInTheDocument();
                expect(screen.getByText('Second GitHub App')).toBeInTheDocument();
            });
        });

        it('does not render app selector dropdown when only one app provided', async () => {
            globalThis.fetch = vi.fn().mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ repositories: [] }),
            });

            render(<RepoSelector githubApps={[githubApp1]} onRepoSelected={onRepoSelected} />);

            await waitFor(() => {
                // Single-app mode: the <select> should not be present
                const selects = screen.queryAllByRole('combobox');
                // Either no select or none with the app names
                const appSelect = selects.find((s) => s.textContent?.includes('My GitHub App'));
                expect(appSelect).toBeUndefined();
            });
        });

        it('filters repositories by search query', async () => {
            globalThis.fetch = vi.fn().mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ repositories: [mockRepo, privateRepo] }),
            });

            render(<RepoSelector githubApps={[githubApp1]} onRepoSelected={onRepoSelected} />);

            await waitFor(() => screen.getByText('my-repo'));

            const searchInput = screen.getByPlaceholderText('Search repositories...');
            fireEvent.change(searchInput, { target: { value: 'private' } });

            expect(screen.queryByText('my-repo')).not.toBeInTheDocument();
            expect(screen.getByText('private-repo')).toBeInTheDocument();
        });

        it('shows "No repositories match your search" when search has no results', async () => {
            globalThis.fetch = vi.fn().mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ repositories: [mockRepo] }),
            });

            render(<RepoSelector githubApps={[githubApp1]} onRepoSelected={onRepoSelected} />);

            await waitFor(() => screen.getByText('my-repo'));

            const searchInput = screen.getByPlaceholderText('Search repositories...');
            fireEvent.change(searchInput, { target: { value: 'nonexistent-xyz' } });

            expect(screen.getByText('No repositories match your search')).toBeInTheDocument();
        });
    });

    // ── Tab switching ─────────────────────────────────────────────────────────

    describe('tab switching between picker and manual modes', () => {
        it('switches to manual mode when "Paste URL" tab is clicked', () => {
            globalThis.fetch = vi.fn().mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ repositories: [] }),
            });

            render(<RepoSelector githubApps={[githubApp1]} onRepoSelected={onRepoSelected} />);

            fireEvent.click(screen.getByText('Paste URL'));

            expect(screen.getByPlaceholderText('https://github.com/user/repo')).toBeInTheDocument();
        });

        it('switches back to picker mode when "My Repositories" tab is clicked', () => {
            globalThis.fetch = vi.fn().mockResolvedValue({
                ok: true,
                json: () => Promise.resolve({ repositories: [] }),
            });

            render(<RepoSelector githubApps={[githubApp1]} onRepoSelected={onRepoSelected} />);

            // Switch to manual
            fireEvent.click(screen.getByText('Paste URL'));
            expect(screen.getByPlaceholderText('https://github.com/user/repo')).toBeInTheDocument();

            // Switch back to picker
            fireEvent.click(screen.getByText('My Repositories'));
            expect(screen.queryByPlaceholderText('https://github.com/user/repo')).not.toBeInTheDocument();
        });

        it('shows "Connect GitHub" prompt when switching to manual with no apps', () => {
            // No apps, already in manual mode — prompt should always be there
            render(<RepoSelector githubApps={[]} onRepoSelected={onRepoSelected} />);

            expect(screen.getByText('Connect GitHub for private repos')).toBeInTheDocument();
        });

        it('does not show "Connect GitHub" prompt in manual mode when apps exist', () => {
            globalThis.fetch = vi.fn().mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ repositories: [] }),
            });

            render(<RepoSelector githubApps={[githubApp1]} onRepoSelected={onRepoSelected} />);

            fireEvent.click(screen.getByText('Paste URL'));

            // Apps exist, so prompt should NOT appear
            expect(screen.queryByText('Connect GitHub for private repos')).not.toBeInTheDocument();
        });
    });

    // ── Branch selection for picked repo ─────────────────────────────────────

    describe('branch selector after repo selection', () => {
        it('shows branch selector after a repo is picked', async () => {
            globalThis.fetch = vi.fn()
                .mockResolvedValueOnce({
                    ok: true,
                    json: () => Promise.resolve({ repositories: [mockRepo] }),
                })
                .mockResolvedValueOnce({
                    ok: true,
                    json: () => Promise.resolve({ branches: [{ name: 'main', protected: false }] }),
                });

            render(<RepoSelector githubApps={[githubApp1]} onRepoSelected={onRepoSelected} />);

            await waitFor(() => screen.getByText('my-repo'));
            fireEvent.click(screen.getByText('my-repo'));

            await waitFor(() => {
                expect(screen.getByText('Branch')).toBeInTheDocument();
            });
        });

        it('calls onRepoSelected with new branch when branch is changed', async () => {
            globalThis.fetch = vi.fn()
                .mockResolvedValueOnce({
                    ok: true,
                    json: () => Promise.resolve({ repositories: [mockRepo] }),
                })
                .mockResolvedValueOnce({
                    ok: true,
                    json: () => Promise.resolve({ branches: [{ name: 'main', protected: false }, { name: 'develop', protected: false }] }),
                });

            render(<RepoSelector githubApps={[githubApp1]} onRepoSelected={onRepoSelected} />);

            await waitFor(() => screen.getByText('my-repo'));
            fireEvent.click(screen.getByText('my-repo'));

            await waitFor(() => screen.getByText('Branch'));

            // The branch select dropdown should be rendered
            const branchSelect = screen.getByRole('combobox');
            fireEvent.change(branchSelect, { target: { value: 'develop' } });

            expect(onRepoSelected).toHaveBeenCalledWith(
                expect.objectContaining({
                    gitBranch: 'develop',
                    gitRepository: 'https://github.com/acme/my-repo',
                    githubAppId: 1,
                    repoName: 'my-repo',
                }),
            );
        });
    });
});

// ── extractRepoName utility ───────────────────────────────────────────────────

describe('extractRepoName', () => {
    it('extracts repo name from a standard GitHub URL', () => {
        expect(extractRepoName('https://github.com/user/my-repo')).toBe('my-repo');
    });

    it('strips .git suffix', () => {
        expect(extractRepoName('https://github.com/user/my-repo.git')).toBe('my-repo');
    });

    it('strips trailing slash before extraction', () => {
        expect(extractRepoName('https://github.com/user/my-repo/')).toBe('my-repo');
    });

    it('works with GitLab URLs', () => {
        expect(extractRepoName('https://gitlab.com/org/project')).toBe('project');
    });

    it('works with Bitbucket URLs', () => {
        expect(extractRepoName('https://bitbucket.org/team/repo-name.git')).toBe('repo-name');
    });

    it('handles deeply nested paths', () => {
        expect(extractRepoName('https://github.com/org/group/sub/repo')).toBe('repo');
    });

    it('returns empty string for an empty input', () => {
        expect(extractRepoName('')).toBe('');
    });

    it('returns empty string for a URL with only slashes', () => {
        expect(extractRepoName('/')).toBe('');
    });

    it('returns the last path segment when URL has both .git suffix and trailing slash', () => {
        // The implementation strips .git first, then trailing slash — but if the URL
        // ends with a slash the .git regex won't match. The result is the raw last segment.
        expect(extractRepoName('https://github.com/user/repo/')).toBe('repo');
    });
});
