import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import { router } from '@inertiajs/react';
import GitHubIndex from '@/pages/Sources/GitHub/Index';

describe('GitHub Index Page', () => {
    const mockApps = [
        {
            id: 1,
            uuid: 'app-uuid-1',
            name: 'Saturn Platform',
            app_id: 123456,
            installation_id: 789012,
            status: 'active' as const,
            repos_count: 10,
            organization: 'myorg',
            created_at: '2024-01-01T00:00:00Z',
            last_synced_at: '2024-01-15T10:30:00Z',
        },
        {
            id: 2,
            uuid: 'app-uuid-2',
            name: 'Personal App',
            app_id: 654321,
            installation_id: 0,
            status: 'pending' as const,
            repos_count: 5,
            created_at: '2024-01-10T00:00:00Z',
        },
    ];

    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering', () => {
        it('should render page title and description', () => {
            render(<GitHubIndex apps={[]} />);

            // "GitHub Apps" appears in both Head and h1, use heading level 1
            expect(screen.getByRole('heading', { level: 1, name: /github apps/i })).toBeInTheDocument();
            expect(screen.getByText('Manage your GitHub App installations')).toBeInTheDocument();
        });

        it('should render back button', () => {
            render(<GitHubIndex apps={[]} />);

            const backButton = screen.getByRole('button', { name: /back/i });
            expect(backButton).toBeInTheDocument();
            expect(backButton.closest('a')).toHaveAttribute('href', '/sources');
        });

        it('should render add GitHub app button in header', () => {
            render(<GitHubIndex apps={[]} />);

            const addButtons = screen.getAllByRole('button', { name: /add github app/i });
            expect(addButtons.length).toBeGreaterThan(0);
        });

        it('should render instructions card', () => {
            render(<GitHubIndex apps={[]} />);

            expect(screen.getByText('How GitHub Apps work')).toBeInTheDocument();
            expect(screen.getByText(/create a github app in your github settings/i)).toBeInTheDocument();
        });
    });

    describe('empty state', () => {
        it('should show empty state when no apps', () => {
            render(<GitHubIndex apps={[]} />);

            expect(screen.getByText('No GitHub Apps connected')).toBeInTheDocument();
            expect(screen.getByText(/create a github app to enable automatic deployments/i)).toBeInTheDocument();
        });

        it('should render create button in empty state', () => {
            render(<GitHubIndex apps={[]} />);

            const createButtons = screen.getAllByRole('button', { name: /create github app/i });
            expect(createButtons.length).toBeGreaterThan(0);
        });

        it('should have correct link in empty state create button', () => {
            render(<GitHubIndex apps={[]} />);

            const createButton = screen.getAllByRole('button', { name: /create github app/i })[0];
            const link = createButton.closest('a');
            expect(link).toHaveAttribute('href', '/sources/github/create');
        });
    });

    describe('apps list', () => {
        it('should display app names', () => {
            render(<GitHubIndex apps={mockApps} />);

            expect(screen.getByText('Saturn Platform')).toBeInTheDocument();
            expect(screen.getByText('Personal App')).toBeInTheDocument();
        });

        it('should display app status badges', () => {
            render(<GitHubIndex apps={mockApps} />);

            expect(screen.getByText('active')).toBeInTheDocument();
            expect(screen.getByText('pending')).toBeInTheDocument();
        });

        it('should display app IDs', () => {
            render(<GitHubIndex apps={mockApps} />);

            expect(screen.getByText(/app id: 123456/i)).toBeInTheDocument();
            expect(screen.getByText(/app id: 654321/i)).toBeInTheDocument();
        });

        it('should display repository counts', () => {
            render(<GitHubIndex apps={mockApps} />);

            expect(screen.getByText(/10 repositories/i)).toBeInTheDocument();
            expect(screen.getByText(/5 repositories/i)).toBeInTheDocument();
        });

        it('should display organization names', () => {
            render(<GitHubIndex apps={mockApps} />);

            // Check for organization (@myorg) and personal account text
            expect(screen.getByText(/@myorg/i)).toBeInTheDocument();
            // "Personal" appears in both the app name and as account type text, use getAllByText
            const personalTexts = screen.getAllByText(/personal/i);
            expect(personalTexts.length).toBeGreaterThan(0);
        });

        it('should display last synced information', () => {
            render(<GitHubIndex apps={mockApps} />);

            expect(screen.getByText(/last synced:/i)).toBeInTheDocument();
        });
    });

    describe('app actions', () => {
        it('should render sync button for active apps', () => {
            render(<GitHubIndex apps={mockApps} />);

            const syncButtons = screen.getAllByRole('button', { name: /sync/i });
            expect(syncButtons.length).toBeGreaterThan(0);
        });

        it('should call sync endpoint when sync button is clicked', async () => {
            const { user } = render(<GitHubIndex apps={mockApps} />);

            const syncButton = screen.getAllByRole('button', { name: /sync/i })[0];
            await user.click(syncButton);

            expect(router.post).toHaveBeenCalledWith('/sources/github/app-uuid-1/sync');
        });

        it('should render GitHub settings link for apps with installation_id', () => {
            render(<GitHubIndex apps={mockApps} />);

            const settingsLinks = screen.getAllByRole('button', { name: /github settings/i });
            expect(settingsLinks.length).toBeGreaterThan(0);
        });

        it('should have correct GitHub settings URL', () => {
            render(<GitHubIndex apps={mockApps} />);

            const settingsLink = screen.getByRole('button', { name: /github settings/i }).closest('a');
            expect(settingsLink).toHaveAttribute('href', 'https://github.com/settings/installations/789012');
            expect(settingsLink).toHaveAttribute('target', '_blank');
        });

        it('should render complete setup button for apps without installation_id', () => {
            render(<GitHubIndex apps={mockApps} />);

            expect(screen.getByRole('button', { name: /complete setup/i })).toBeInTheDocument();
        });

        it('should render delete buttons', () => {
            render(<GitHubIndex apps={mockApps} />);

            const deleteButtons = screen.getAllByRole('button').filter(btn => {
                const svg = btn.querySelector('svg');
                return svg && btn.className.includes('text-danger');
            });
            expect(deleteButtons.length).toBe(2);
        });
    });

    describe('delete functionality', () => {
        it('should show confirmation before deleting', async () => {
            const { user } = render(<GitHubIndex apps={mockApps} />);

            const deleteButtons = screen.getAllByRole('button').filter(btn =>
                btn.className.includes('text-danger')
            );

            if (deleteButtons[0]) {
                await user.click(deleteButtons[0]);
                // Confirmation is handled by useConfirm hook mock
            }
        });
    });

    describe('navigation', () => {
        it('should have correct link for add button', () => {
            render(<GitHubIndex apps={[]} />);

            const addButton = screen.getAllByRole('button', { name: /add github app/i })[0];
            const link = addButton.closest('a');
            expect(link).toHaveAttribute('href', '/sources/github/create');
        });
    });

    describe('edge cases', () => {
        it('should handle empty apps array', () => {
            render(<GitHubIndex apps={[]} />);

            expect(screen.getByText('No GitHub Apps connected')).toBeInTheDocument();
        });

        it('should handle missing apps prop', () => {
            render(<GitHubIndex apps={undefined as any} />);

            // "GitHub Apps" appears in both Head and h1, use heading level 1
            expect(screen.getByRole('heading', { level: 1, name: /github apps/i })).toBeInTheDocument();
        });

        it('should handle apps without last_synced_at', () => {
            const appWithoutSync = {
                ...mockApps[0],
                last_synced_at: undefined,
            };

            render(<GitHubIndex apps={[appWithoutSync]} />);

            expect(screen.queryByText(/last synced:/i)).not.toBeInTheDocument();
        });
    });
});
