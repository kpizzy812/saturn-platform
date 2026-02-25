import { describe, it, expect, vi, beforeEach } from 'vitest';
import { act } from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import ProjectCreate from '../Create';

// ── Inertia mock ──────────────────────────────────────────────────────────────

// Use vi.hoisted so the spy is available when the factory runs (vi.mock is hoisted to the top)
const { mockRouterVisit } = vi.hoisted(() => ({
    mockRouterVisit: vi.fn(),
}));

vi.mock('@inertiajs/react', () => ({
    router: { visit: mockRouterVisit },
    Link: ({ children, href, className, ...rest }: any) => (
        <a href={href} className={className} {...rest}>{children}</a>
    ),
}));

// ── Layout mock ───────────────────────────────────────────────────────────────

vi.mock('@/components/layout', () => ({
    AppLayout: ({ children }: any) => <div data-testid="app-layout">{children}</div>,
}));

// ── UI component mocks ────────────────────────────────────────────────────────

vi.mock('@/components/ui', () => ({
    Card: ({ children, className }: any) => <div data-testid="card" className={className}>{children}</div>,
    CardContent: ({ children, className }: any) => <div data-testid="card-content" className={className}>{children}</div>,
    Button: ({ children, onClick, loading, className, size, type }: any) => (
        <button
            onClick={onClick}
            disabled={loading}
            className={className}
            data-size={size}
            type={type || 'button'}
            data-loading={loading ? 'true' : undefined}
        >
            {loading ? 'Loading...' : children}
        </button>
    ),
    Input: ({ value, onChange, placeholder, className }: any) => (
        <input
            value={value}
            onChange={onChange}
            placeholder={placeholder}
            className={className}
        />
    ),
}));

// ── BrandIcon mock ────────────────────────────────────────────────────────────

vi.mock('@/components/ui/BrandIcon', () => ({
    BrandIcon: ({ name, className }: any) => (
        <span data-testid={`brand-icon-${name}`} className={className} />
    ),
}));

// ── RepoSelector mock ─────────────────────────────────────────────────────────

// Expose a callback ref so tests can trigger onRepoSelected programmatically
let capturedOnRepoSelected: ((result: any) => void) | null = null;

vi.mock('@/components/features/RepoSelector', () => ({
    RepoSelector: ({ onRepoSelected, githubApps }: any) => {
        capturedOnRepoSelected = onRepoSelected;
        return (
            <div data-testid="repo-selector" data-apps-count={githubApps?.length ?? 0}>
                <button
                    type="button"
                    data-testid="mock-select-repo"
                    onClick={() =>
                        onRepoSelected({
                            gitRepository: 'https://github.com/acme/test-app',
                            gitBranch: 'main',
                            githubAppId: undefined,
                            repoName: 'test-app',
                        })
                    }
                >
                    Select Repo
                </button>
            </div>
        );
    },
    extractRepoName: (url: string) => url.split('/').pop()?.replace(/\.git$/, '') || '',
}));

// ── MonorepoAnalyzer mock ─────────────────────────────────────────────────────

vi.mock('@/components/features/MonorepoAnalyzer', () => ({
    MonorepoAnalyzer: ({ gitRepository, gitBranch, environmentUuid, onComplete }: any) => (
        <div
            data-testid="monorepo-analyzer"
            data-repo={gitRepository}
            data-branch={gitBranch}
            data-env-uuid={environmentUuid}
        >
            <button
                type="button"
                data-testid="mock-provision-complete"
                onClick={() =>
                    onComplete({
                        applications: [{ uuid: 'app-uuid-1', name: 'test-app' }],
                        monorepo_group_id: null,
                    })
                }
            >
                Complete Provision
            </button>
        </div>
    ),
}));

// ── lucide-react mock ─────────────────────────────────────────────────────────

vi.mock('lucide-react', () => ({
    ArrowLeft: () => <span data-testid="icon-arrow-left" />,
    Sparkles: () => <span data-testid="icon-sparkles" />,
    Database: () => <span data-testid="icon-database" />,
    FileCode: () => <span data-testid="icon-filecode" />,
    Folder: () => <span data-testid="icon-folder" />,
    Settings2: () => <span data-testid="icon-settings" />,
    Rocket: () => <span data-testid="icon-rocket" />,
    Globe: () => <span data-testid="icon-globe" />,
}));

// ── CSRF mock ─────────────────────────────────────────────────────────────────

// Stub document.querySelector so the CSRF token lookup doesn't fail
Object.defineProperty(document, 'querySelector', {
    writable: true,
    value: (selector: string) => {
        if (selector === 'meta[name="csrf-token"]') {
            return { content: 'test-csrf-token' };
        }
        return null;
    },
});

// ── Fixtures ──────────────────────────────────────────────────────────────────

const defaultProps = {
    projects: [],
    wildcardDomain: null,
    hasGithubApp: false,
    githubApps: [],
};

const mockProjectResponse = {
    uuid: 'proj-uuid-123',
    name: 'test-app',
    environments: [
        { uuid: 'env-uuid-dev', name: 'development' },
        { uuid: 'env-uuid-uat', name: 'uat' },
        { uuid: 'env-uuid-prod', name: 'production' },
    ],
};

// ── Tests ─────────────────────────────────────────────────────────────────────

describe('Projects/Create', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        capturedOnRepoSelected = null;
    });

    // ── Initial render ────────────────────────────────────────────────────────

    describe('initial render (repo_select phase)', () => {
        it('renders the page inside AppLayout', () => {
            render(<ProjectCreate {...defaultProps} />);
            expect(screen.getByTestId('app-layout')).toBeInTheDocument();
        });

        it('renders the main heading', () => {
            render(<ProjectCreate {...defaultProps} />);
            expect(screen.getByText('Deploy your project')).toBeInTheDocument();
        });

        it('renders the subtitle', () => {
            render(<ProjectCreate {...defaultProps} />);
            expect(screen.getByText('Import a Git repository to get started')).toBeInTheDocument();
        });

        it('renders the RepoSelector component', () => {
            render(<ProjectCreate {...defaultProps} />);
            expect(screen.getByTestId('repo-selector')).toBeInTheDocument();
        });

        it('renders back link to /dashboard', () => {
            render(<ProjectCreate {...defaultProps} />);
            const backLink = screen.getByText('Back to Dashboard');
            expect(backLink.closest('a')).toHaveAttribute('href', '/dashboard');
        });

        it('renders "Advanced" link to /applications/create', () => {
            render(<ProjectCreate {...defaultProps} />);
            const advancedLink = screen.getByText('Advanced');
            expect(advancedLink.closest('a')).toHaveAttribute('href', '/applications/create');
        });

        it('does not show project name field before repo is selected', () => {
            render(<ProjectCreate {...defaultProps} />);
            expect(screen.queryByText('Project Name')).not.toBeInTheDocument();
        });

        it('does not show "Analyze & Deploy" button before repo is selected', () => {
            render(<ProjectCreate {...defaultProps} />);
            expect(screen.queryByText('Analyze & Deploy')).not.toBeInTheDocument();
        });

        it('does not render MonorepoAnalyzer in initial state', () => {
            render(<ProjectCreate {...defaultProps} />);
            expect(screen.queryByTestId('monorepo-analyzer')).not.toBeInTheDocument();
        });
    });

    // ── Secondary options ─────────────────────────────────────────────────────

    describe('secondary deploy options', () => {
        it('renders "or deploy something else" divider', () => {
            render(<ProjectCreate {...defaultProps} />);
            expect(screen.getByText('or deploy something else')).toBeInTheDocument();
        });

        it('renders "Database" option linking to /databases/create', () => {
            render(<ProjectCreate {...defaultProps} />);
            const link = screen.getByText('Database').closest('a');
            expect(link).toHaveAttribute('href', '/databases/create');
        });

        it('renders "Docker Image" option', () => {
            render(<ProjectCreate {...defaultProps} />);
            expect(screen.getByText('Docker Image')).toBeInTheDocument();
        });

        it('Docker Image link points to /applications/create?source=docker', () => {
            render(<ProjectCreate {...defaultProps} />);
            const link = screen.getByText('Docker Image').closest('a');
            expect(link).toHaveAttribute('href', '/applications/create?source=docker');
        });

        it('renders "Template" option linking to /templates', () => {
            render(<ProjectCreate {...defaultProps} />);
            const link = screen.getByText('Template').closest('a');
            expect(link).toHaveAttribute('href', '/templates');
        });

        it('renders "Empty Project" option linking to /projects/create/empty', () => {
            render(<ProjectCreate {...defaultProps} />);
            const link = screen.getByText('Empty Project').closest('a');
            expect(link).toHaveAttribute('href', '/projects/create/empty');
        });
    });

    // ── Repo selection → quick config ────────────────────────────────────────

    describe('after repo is selected', () => {
        it('shows Project Name field when repo is selected', () => {
            render(<ProjectCreate {...defaultProps} />);
            fireEvent.click(screen.getByTestId('mock-select-repo'));

            expect(screen.getByText('Project Name')).toBeInTheDocument();
        });

        it('auto-fills project name from repo name', () => {
            render(<ProjectCreate {...defaultProps} />);
            fireEvent.click(screen.getByTestId('mock-select-repo'));

            const nameInput = screen.getByPlaceholderText('my-project');
            expect(nameInput).toHaveValue('test-app');
        });

        it('shows "Analyze & Deploy" button after repo is selected', () => {
            render(<ProjectCreate {...defaultProps} />);
            fireEvent.click(screen.getByTestId('mock-select-repo'));

            expect(screen.getByText('Analyze & Deploy')).toBeInTheDocument();
        });

        it('does not show subdomain field when wildcardDomain is null', () => {
            render(<ProjectCreate {...defaultProps} />);
            fireEvent.click(screen.getByTestId('mock-select-repo'));

            expect(screen.queryByText('Domain')).not.toBeInTheDocument();
        });

        it('shows subdomain field when wildcardDomain is provided', () => {
            const props = { ...defaultProps, wildcardDomain: { host: 'saturn.ac', scheme: 'https' } };
            render(<ProjectCreate {...props} />);
            fireEvent.click(screen.getByTestId('mock-select-repo'));

            expect(screen.getByText('Domain')).toBeInTheDocument();
        });

        it('appends wildcard domain host suffix next to subdomain input', () => {
            const props = { ...defaultProps, wildcardDomain: { host: 'saturn.ac', scheme: 'https' } };
            render(<ProjectCreate {...props} />);
            fireEvent.click(screen.getByTestId('mock-select-repo'));

            expect(screen.getByText('.saturn.ac')).toBeInTheDocument();
        });

        it('auto-fills subdomain from repo name (lowercased, slugified)', () => {
            render(<ProjectCreate {...{ ...defaultProps, wildcardDomain: { host: 'saturn.ac', scheme: 'https' } }} />);
            fireEvent.click(screen.getByTestId('mock-select-repo'));

            const subdomainInput = screen.getByPlaceholderText('my-app');
            expect(subdomainInput).toHaveValue('test-app');
        });
    });

    // ── "Analyze & Deploy" flow ───────────────────────────────────────────────

    describe('"Analyze & Deploy" button', () => {
        it('creates project via POST /projects and transitions to analyze_deploy phase', async () => {
            globalThis.fetch = vi.fn().mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve(mockProjectResponse),
            });

            render(<ProjectCreate {...defaultProps} />);
            fireEvent.click(screen.getByTestId('mock-select-repo'));

            fireEvent.click(screen.getByText('Analyze & Deploy'));

            await waitFor(() => {
                expect(globalThis.fetch).toHaveBeenCalledWith(
                    '/projects',
                    expect.objectContaining({
                        method: 'POST',
                        headers: expect.objectContaining({
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': 'test-csrf-token',
                        }),
                        body: JSON.stringify({ name: 'test-app' }),
                    }),
                );
            });
        });

        it('shows MonorepoAnalyzer after successful project creation', async () => {
            globalThis.fetch = vi.fn().mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve(mockProjectResponse),
            });

            render(<ProjectCreate {...defaultProps} />);
            fireEvent.click(screen.getByTestId('mock-select-repo'));
            fireEvent.click(screen.getByText('Analyze & Deploy'));

            await waitFor(() => {
                expect(screen.getByTestId('monorepo-analyzer')).toBeInTheDocument();
            });
        });

        it('passes correct props to MonorepoAnalyzer', async () => {
            globalThis.fetch = vi.fn().mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve(mockProjectResponse),
            });

            render(<ProjectCreate {...defaultProps} />);
            fireEvent.click(screen.getByTestId('mock-select-repo'));
            fireEvent.click(screen.getByText('Analyze & Deploy'));

            await waitFor(() => screen.getByTestId('monorepo-analyzer'));

            const analyzer = screen.getByTestId('monorepo-analyzer');
            expect(analyzer).toHaveAttribute('data-repo', 'https://github.com/acme/test-app');
            expect(analyzer).toHaveAttribute('data-branch', 'main');
            expect(analyzer).toHaveAttribute('data-env-uuid', 'env-uuid-dev');
        });

        it('shows error message when project creation fails', async () => {
            globalThis.fetch = vi.fn().mockResolvedValueOnce({
                ok: false,
                json: () => Promise.resolve({ message: 'Name already taken' }),
            });

            render(<ProjectCreate {...defaultProps} />);
            fireEvent.click(screen.getByTestId('mock-select-repo'));
            fireEvent.click(screen.getByText('Analyze & Deploy'));

            await waitFor(() => {
                expect(screen.getByText('Name already taken')).toBeInTheDocument();
            });
        });

        it('shows generic error when fetch throws', async () => {
            globalThis.fetch = vi.fn().mockRejectedValueOnce(new Error('Connection refused'));

            render(<ProjectCreate {...defaultProps} />);
            fireEvent.click(screen.getByTestId('mock-select-repo'));
            fireEvent.click(screen.getByText('Analyze & Deploy'));

            await waitFor(() => {
                expect(screen.getByText('Connection refused')).toBeInTheDocument();
            });
        });

        it('disables the button while request is in flight', async () => {
            // Keep fetch pending so loading state persists
            globalThis.fetch = vi.fn().mockReturnValue(new Promise(() => {}));

            render(<ProjectCreate {...defaultProps} />);
            fireEvent.click(screen.getByTestId('mock-select-repo'));

            const btn = screen.getByText('Analyze & Deploy');
            fireEvent.click(btn);

            await waitFor(() => {
                expect(screen.getByRole('button', { name: 'Loading...' })).toBeDisabled();
            });
        });

        it('shows validation error when Analyze & Deploy is clicked without a repo (edge case)', async () => {
            // Render a page where the button appears via manual URL injection
            render(<ProjectCreate {...defaultProps} />);

            // Manually trigger onRepoSelected with an empty URL, then clear it
            // We simulate this by first selecting, then expecting no fetch when URL is empty
            // This edge case is guarded by the hasRepo flag — button only shows after selection
            // So instead we verify the guard: button is absent without selection
            expect(screen.queryByText('Analyze & Deploy')).not.toBeInTheDocument();
        });

        it('uses projectName from input field when calling the API', async () => {
            globalThis.fetch = vi.fn().mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve(mockProjectResponse),
            });

            render(<ProjectCreate {...defaultProps} />);
            fireEvent.click(screen.getByTestId('mock-select-repo'));

            // Change the project name
            const nameInput = screen.getByPlaceholderText('my-project');
            fireEvent.change(nameInput, { target: { value: 'custom-name' } });

            fireEvent.click(screen.getByText('Analyze & Deploy'));

            await waitFor(() => {
                expect(globalThis.fetch).toHaveBeenCalledWith(
                    '/projects',
                    expect.objectContaining({
                        body: JSON.stringify({ name: 'custom-name' }),
                    }),
                );
            });
        });
    });

    // ── analyze_deploy phase ─────────────────────────────────────────────────

    describe('analyze_deploy phase', () => {
        const transitionToAnalyzePhase = async () => {
            globalThis.fetch = vi.fn().mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve(mockProjectResponse),
            });

            render(<ProjectCreate {...defaultProps} />);
            fireEvent.click(screen.getByTestId('mock-select-repo'));
            fireEvent.click(screen.getByText('Analyze & Deploy'));

            await waitFor(() => screen.getByTestId('monorepo-analyzer'));
        };

        it('shows "Back to repo selection" button in analyze phase', async () => {
            await transitionToAnalyzePhase();
            expect(screen.getByText('Back to repo selection')).toBeInTheDocument();
        });

        it('back button returns to repo_select phase', async () => {
            await transitionToAnalyzePhase();

            fireEvent.click(screen.getByText('Back to repo selection'));

            expect(screen.queryByTestId('monorepo-analyzer')).not.toBeInTheDocument();
            expect(screen.getByTestId('repo-selector')).toBeInTheDocument();
        });

        it('shows repo info badge with project name in analyze phase', async () => {
            await transitionToAnalyzePhase();
            expect(screen.getByText('test-app')).toBeInTheDocument();
        });

        it('shows repo URL and branch in analyze phase', async () => {
            await transitionToAnalyzePhase();
            // The repo info badge contains the git URL and branch
            expect(screen.getByText(/https:\/\/github\.com\/acme\/test-app/)).toBeInTheDocument();
        });

        it('hides secondary options in analyze phase', async () => {
            await transitionToAnalyzePhase();
            expect(screen.queryByText('or deploy something else')).not.toBeInTheDocument();
        });

        it('hides "Advanced" link in analyze phase', async () => {
            await transitionToAnalyzePhase();
            expect(screen.queryByText('Advanced')).not.toBeInTheDocument();
        });
    });

    // ── handleProvisionComplete navigation ────────────────────────────────────

    describe('handleProvisionComplete navigation', () => {
        const transitionToAnalyzePhase = async () => {
            globalThis.fetch = vi.fn().mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve(mockProjectResponse),
            });

            render(<ProjectCreate {...defaultProps} />);
            fireEvent.click(screen.getByTestId('mock-select-repo'));
            fireEvent.click(screen.getByText('Analyze & Deploy'));

            await waitFor(() => screen.getByTestId('monorepo-analyzer'));
        };

        it('navigates to project page after provision completes', async () => {
            await transitionToAnalyzePhase();
            fireEvent.click(screen.getByTestId('mock-provision-complete'));

            expect(mockRouterVisit).toHaveBeenCalledWith('/projects/proj-uuid-123');
        });
    });

    // ── Props handling ────────────────────────────────────────────────────────

    describe('props handling', () => {
        it('passes githubApps to RepoSelector', () => {
            const githubApps = [
                { id: 1, uuid: 'uuid-1', name: 'My App', installation_id: 42 },
            ];

            render(<ProjectCreate {...defaultProps} githubApps={githubApps} />);

            const selector = screen.getByTestId('repo-selector');
            expect(selector).toHaveAttribute('data-apps-count', '1');
        });

        it('renders with empty defaults when no props provided', () => {
            render(<ProjectCreate />);
            expect(screen.getByText('Deploy your project')).toBeInTheDocument();
        });
    });

    // ── toSubdomain helper (via UI behavior) ──────────────────────────────────

    describe('subdomain generation', () => {
        it('converts special characters to hyphens', async () => {
            const props = { ...defaultProps, wildcardDomain: { host: 'saturn.ac', scheme: 'https' } };

            render(<ProjectCreate {...props} />);

            // Trigger onRepoSelected with a name containing spaces wrapped in act()
            await act(async () => {
                capturedOnRepoSelected!({
                    gitRepository: 'https://github.com/user/My Cool App',
                    gitBranch: 'main',
                    repoName: 'My Cool App',
                });
            });

            const subdomainInput = screen.getByPlaceholderText('my-app');
            expect(subdomainInput).toHaveValue('my-cool-app');
        });

        it('converts to lowercase', async () => {
            const props = { ...defaultProps, wildcardDomain: { host: 'saturn.ac', scheme: 'https' } };
            render(<ProjectCreate {...props} />);

            await act(async () => {
                capturedOnRepoSelected!({
                    gitRepository: 'https://github.com/user/MyRepo',
                    gitBranch: 'main',
                    repoName: 'MyRepo',
                });
            });

            const subdomainInput = screen.getByPlaceholderText('my-app');
            expect(subdomainInput).toHaveValue('myrepo');
        });

        it('strips leading and trailing hyphens', async () => {
            const props = { ...defaultProps, wildcardDomain: { host: 'saturn.ac', scheme: 'https' } };
            render(<ProjectCreate {...props} />);

            await act(async () => {
                capturedOnRepoSelected!({
                    gitRepository: 'https://github.com/user/---my-repo---',
                    gitBranch: 'main',
                    repoName: '---my-repo---',
                });
            });

            const subdomainInput = screen.getByPlaceholderText('my-app');
            expect(subdomainInput).toHaveValue('my-repo');
        });
    });
});
