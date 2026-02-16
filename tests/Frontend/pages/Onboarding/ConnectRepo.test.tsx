import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '../../utils/test-utils';
import { router } from '@inertiajs/react';
import OnboardingConnectRepo from '@/pages/Onboarding/ConnectRepo';

describe('Onboarding/ConnectRepo', () => {
    const mockGithubApps = [
        {
            id: 1,
            uuid: 'test-uuid-1',
            name: 'My GitHub App',
            installation_id: 123,
        },
    ];

    beforeEach(() => {
        vi.clearAllMocks();
        global.fetch = vi.fn();
    });

    it('renders page heading and description', () => {
        render(<OnboardingConnectRepo />);

        expect(screen.getByRole('heading', { level: 1, name: /connect repository/i })).toBeInTheDocument();
        expect(screen.getByText(/select a repository to deploy to saturn/i)).toBeInTheDocument();
    });

    it('renders back button', () => {
        render(<OnboardingConnectRepo />);

        const backLinks = screen.getAllByRole('link', { name: /back/i });
        expect(backLinks[0]).toHaveAttribute('href', '/onboarding/welcome');
    });

    it('renders select git provider heading', () => {
        render(<OnboardingConnectRepo />);

        expect(screen.getByRole('heading', { level: 3, name: /select git provider/i })).toBeInTheDocument();
    });

    it('renders all provider buttons', () => {
        render(<OnboardingConnectRepo />);

        // Provider buttons are within the provider selector, not standalone buttons
        expect(screen.getByText('GitHub')).toBeInTheDocument();
        expect(screen.getByText('GitLab')).toBeInTheDocument();
        expect(screen.getByText('Bitbucket')).toBeInTheDocument();
    });

    it('shows github as selected by default', () => {
        render(<OnboardingConnectRepo provider="github" />);

        // Find the GitHub button by combining text content
        const providerButtons = screen.getAllByRole('button');
        const githubButton = providerButtons.find(btn => btn.textContent?.includes('GitHub'));
        expect(githubButton).toBeDefined();
        expect(githubButton?.className).toContain('border-primary');
    });

    it('switches provider when clicked', async () => {
        const { user } = render(<OnboardingConnectRepo />);

        const providerButtons = screen.getAllByRole('button');
        const gitlabButton = providerButtons.find(btn => btn.textContent?.includes('GitLab'));
        expect(gitlabButton).toBeDefined();

        if (gitlabButton) {
            await user.click(gitlabButton);
            expect(gitlabButton.className).toContain('border-primary');
        }
    });

    it('shows github app required message when no apps', () => {
        render(<OnboardingConnectRepo provider="github" githubApps={[]} />);

        expect(screen.getByRole('heading', { level: 3, name: /github app required/i })).toBeInTheDocument();
        expect(screen.getByText(/to access your github repositories, you need to create and install a github app/i)).toBeInTheDocument();
    });

    it('shows create github app button when no apps', () => {
        render(<OnboardingConnectRepo provider="github" githubApps={[]} />);

        const createButton = screen.getByRole('link', { name: /create github app/i });
        expect(createButton).toBeInTheDocument();
        expect(createButton).toHaveAttribute('href', '/sources/github/create');
    });

    it('shows coming soon message for gitlab', async () => {
        const { user } = render(<OnboardingConnectRepo />);

        const providerButtons = screen.getAllByRole('button');
        const gitlabButton = providerButtons.find(btn => btn.textContent?.includes('GitLab'));
        if (gitlabButton) {
            await user.click(gitlabButton);
        }

        expect(screen.getByRole('heading', { level: 3, name: /coming soon/i })).toBeInTheDocument();
        expect(screen.getByText(/gitlab integration is coming soon/i)).toBeInTheDocument();
    });

    it('shows coming soon message for bitbucket', async () => {
        const { user } = render(<OnboardingConnectRepo />);

        const providerButtons = screen.getAllByRole('button');
        const bitbucketButton = providerButtons.find(btn => btn.textContent?.includes('Bitbucket'));
        if (bitbucketButton) {
            await user.click(bitbucketButton);
        }

        expect(screen.getByRole('heading', { level: 3, name: /coming soon/i })).toBeInTheDocument();
        expect(screen.getByText(/bitbucket integration is coming soon/i)).toBeInTheDocument();
    });

    it('loads repositories when github app is available', async () => {
        const mockRepos = [
            {
                id: 1,
                name: 'my-repo',
                full_name: 'user/my-repo',
                description: 'My awesome repo',
                private: false,
                default_branch: 'main',
                language: 'TypeScript',
                updated_at: '2024-01-15T10:00:00Z',
            },
        ];

        (global.fetch as any).mockResolvedValue({
            ok: true,
            json: async () => ({ repositories: mockRepos }),
        });

        render(<OnboardingConnectRepo provider="github" githubApps={mockGithubApps} />);

        await waitFor(() => {
            expect(screen.getByRole('heading', { level: 3, name: /select repository/i })).toBeInTheDocument();
        });
    });

    it('shows loading state while fetching repositories', () => {
        (global.fetch as any).mockImplementation(() => new Promise(() => {}));

        render(<OnboardingConnectRepo provider="github" githubApps={mockGithubApps} />);

        expect(screen.getByText(/loading repositories/i)).toBeInTheDocument();
    });

    it('displays error message when repository fetch fails', async () => {
        (global.fetch as any).mockResolvedValue({
            ok: false,
            json: async () => ({ message: 'Failed to fetch repos' }),
        });

        render(<OnboardingConnectRepo provider="github" githubApps={mockGithubApps} />);

        await waitFor(() => {
            expect(screen.getByText(/failed to fetch repos/i)).toBeInTheDocument();
        });
    });

    it('renders search repositories input', async () => {
        (global.fetch as any).mockResolvedValue({
            ok: true,
            json: async () => ({ repositories: [] }),
        });

        render(<OnboardingConnectRepo provider="github" githubApps={mockGithubApps} />);

        await waitFor(() => {
            expect(screen.getByPlaceholderText(/search repositories/i)).toBeInTheDocument();
        });
    });

    it('renders skip and go to dashboard link', () => {
        render(<OnboardingConnectRepo />);

        const skipLink = screen.getByRole('link', { name: /skip and go to dashboard/i });
        expect(skipLink).toBeInTheDocument();
        expect(skipLink).toHaveAttribute('href', '/dashboard');
    });

    it('renders continue to deploy button as disabled when no repo selected', async () => {
        (global.fetch as any).mockResolvedValue({
            ok: true,
            json: async () => ({ repositories: [] }),
        });

        render(<OnboardingConnectRepo provider="github" githubApps={mockGithubApps} />);

        await waitFor(() => {
            const continueButton = screen.getByRole('button', { name: /continue to deploy/i });
            expect(continueButton).toBeDisabled();
        });
    });

    it('shows github app selector when multiple apps exist', () => {
        const multipleApps = [
            ...mockGithubApps,
            {
                id: 2,
                uuid: 'test-uuid-2',
                name: 'Another GitHub App',
                installation_id: 456,
            },
        ];

        render(<OnboardingConnectRepo provider="github" githubApps={multipleApps} />);

        expect(screen.getByRole('heading', { level: 3, name: /select github app/i })).toBeInTheDocument();
    });

    it('submits deployment when continue is clicked with valid selection', async () => {
        const mockRepos = [
            {
                id: 1,
                name: 'my-repo',
                full_name: 'user/my-repo',
                description: 'Test repo',
                private: false,
                default_branch: 'main',
                language: 'TypeScript',
                updated_at: '2024-01-15T10:00:00Z',
            },
        ];

        const mockBranches = [
            { name: 'main', protected: false },
        ];

        (global.fetch as any)
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ repositories: mockRepos }),
            })
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ branches: mockBranches }),
            });

        const { user } = render(<OnboardingConnectRepo provider="github" githubApps={mockGithubApps} />);

        // Wait for repos to load
        await waitFor(() => {
            expect(screen.getByText('my-repo')).toBeInTheDocument();
        });

        // Select a repository by clicking on it
        const allButtons = screen.getAllByRole('button');
        const repoButton = allButtons.find(btn => btn.textContent?.includes('my-repo'));
        if (repoButton) {
            await user.click(repoButton);
        }

        // Wait for build settings to appear
        await waitFor(() => {
            expect(screen.getByRole('heading', { level: 3, name: /build settings/i })).toBeInTheDocument();
        });

        // Click continue
        const continueButton = screen.getByRole('button', { name: /continue to deploy/i });
        await user.click(continueButton);

        expect(router.post).toHaveBeenCalledWith(
            '/applications/deploy',
            expect.objectContaining({
                repository: 'user/my-repo',
                branch: 'main',
            })
        );
    });
});
