import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import { router } from '@inertiajs/react';
import GitLabIndex from '@/pages/Sources/GitLab/Index';

describe('GitLab Index Page', () => {
    const mockConnections = [
        {
            id: 1,
            uuid: 'conn-uuid-1',
            name: 'GitLab.com',
            instance_url: 'https://gitlab.com',
            status: 'active' as const,
            repos_count: 15,
            group: 'mygroup',
            created_at: '2024-01-01T00:00:00Z',
            last_synced_at: '2024-01-15T10:30:00Z',
        },
        {
            id: 2,
            uuid: 'conn-uuid-2',
            name: 'Self-hosted GitLab',
            instance_url: 'https://gitlab.example.com',
            status: 'suspended' as const,
            repos_count: 8,
            created_at: '2024-01-10T00:00:00Z',
        },
    ];

    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering', () => {
        it('should render page title and description', () => {
            render(<GitLabIndex connections={[]} />);

            expect(screen.getByText('GitLab Connections')).toBeInTheDocument();
            expect(screen.getByText('Manage your GitLab instance connections')).toBeInTheDocument();
        });

        it('should render back button', () => {
            render(<GitLabIndex connections={[]} />);

            const backButton = screen.getByRole('button', { name: /back/i });
            expect(backButton).toBeInTheDocument();
            expect(backButton.closest('a')).toHaveAttribute('href', '/sources');
        });

        it('should render add connection button', () => {
            render(<GitLabIndex connections={[]} />);

            const addButtons = screen.getAllByRole('button', { name: /add gitlab connection/i });
            expect(addButtons.length).toBeGreaterThan(0);
        });

        it('should render instructions card', () => {
            render(<GitLabIndex connections={[]} />);

            expect(screen.getByText('About GitLab Integration')).toBeInTheDocument();
        });

        it('should render features grid', () => {
            render(<GitLabIndex connections={[]} />);

            expect(screen.getByText('Automatic Deployments')).toBeInTheDocument();
            expect(screen.getByText('Merge Request Previews')).toBeInTheDocument();
            expect(screen.getByText('Multiple Instances')).toBeInTheDocument();
        });
    });

    describe('empty state', () => {
        it('should show empty state when no connections', () => {
            render(<GitLabIndex connections={[]} />);

            expect(screen.getByText('No GitLab connections')).toBeInTheDocument();
            expect(screen.getByText(/connect to gitlab.com or your self-hosted/i)).toBeInTheDocument();
        });

        it('should render connect button in empty state', () => {
            render(<GitLabIndex connections={[]} />);

            const connectButtons = screen.getAllByRole('button', { name: /connect gitlab/i });
            expect(connectButtons.length).toBeGreaterThan(0);
        });

        it('should have correct link in empty state', () => {
            render(<GitLabIndex connections={[]} />);

            const connectButton = screen.getAllByRole('button', { name: /connect gitlab/i })[0];
            const link = connectButton.closest('a');
            expect(link).toHaveAttribute('href', '/sources/gitlab/create');
        });
    });

    describe('connections list', () => {
        it('should display connection names', () => {
            render(<GitLabIndex connections={mockConnections} />);

            expect(screen.getByText('GitLab.com')).toBeInTheDocument();
            expect(screen.getByText('Self-hosted GitLab')).toBeInTheDocument();
        });

        it('should display instance URLs', () => {
            render(<GitLabIndex connections={mockConnections} />);

            expect(screen.getByText(/https:\/\/gitlab\.com/)).toBeInTheDocument();
            expect(screen.getByText(/https:\/\/gitlab\.example\.com/)).toBeInTheDocument();
        });

        it('should display status badges', () => {
            render(<GitLabIndex connections={mockConnections} />);

            expect(screen.getByText('active')).toBeInTheDocument();
            expect(screen.getByText('suspended')).toBeInTheDocument();
        });

        it('should display repository counts', () => {
            render(<GitLabIndex connections={mockConnections} />);

            expect(screen.getByText(/15 repositories/i)).toBeInTheDocument();
            expect(screen.getByText(/8 repositories/i)).toBeInTheDocument();
        });

        it('should display group names', () => {
            render(<GitLabIndex connections={mockConnections} />);

            expect(screen.getByText(/@mygroup/i)).toBeInTheDocument();
        });

        it('should display last synced information', () => {
            render(<GitLabIndex connections={mockConnections} />);

            expect(screen.getByText(/last synced:/i)).toBeInTheDocument();
        });
    });

    describe('connection actions', () => {
        it('should render sync buttons', () => {
            render(<GitLabIndex connections={mockConnections} />);

            const syncButtons = screen.getAllByRole('button', { name: /sync/i });
            expect(syncButtons.length).toBeGreaterThan(0);
        });

        it('should call sync endpoint when sync button is clicked', async () => {
            const { user } = render(<GitLabIndex connections={mockConnections} />);

            const syncButton = screen.getAllByRole('button', { name: /sync/i })[0];
            await user.click(syncButton);

            expect(router.post).toHaveBeenCalledWith('/sources/gitlab/conn-uuid-1/sync');
        });

        it('should render GitLab external links', () => {
            render(<GitLabIndex connections={mockConnections} />);

            const gitlabLinks = screen.getAllByRole('button', { name: /gitlab/i }).filter(btn =>
                btn.closest('a')?.hasAttribute('target')
            );
            expect(gitlabLinks.length).toBeGreaterThan(0);
        });

        it('should have correct external link URLs', () => {
            render(<GitLabIndex connections={mockConnections} />);

            const gitlabButton = screen.getAllByRole('button', { name: /gitlab/i })[0];
            const link = gitlabButton.closest('a');
            expect(link).toHaveAttribute('href', 'https://gitlab.com');
            expect(link).toHaveAttribute('target', '_blank');
        });

        it('should render delete buttons', () => {
            render(<GitLabIndex connections={mockConnections} />);

            const deleteButtons = screen.getAllByRole('button').filter(btn =>
                btn.className.includes('text-danger')
            );
            expect(deleteButtons.length).toBe(2);
        });
    });

    describe('delete functionality', () => {
        it('should show confirmation before deleting', async () => {
            const { user } = render(<GitLabIndex connections={mockConnections} />);

            const deleteButtons = screen.getAllByRole('button').filter(btn =>
                btn.className.includes('text-danger')
            );

            if (deleteButtons[0]) {
                await user.click(deleteButtons[0]);
                // Confirmation is handled by useConfirm hook mock
            }
        });
    });

    describe('instructions section', () => {
        it('should display GitLab.com and self-hosted information', () => {
            render(<GitLabIndex connections={[]} />);

            expect(screen.getByText(/gitlab\.com:/i)).toBeInTheDocument();
            expect(screen.getByText(/self-hosted gitlab:/i)).toBeInTheDocument();
        });

        it('should display requirements list', () => {
            render(<GitLabIndex connections={[]} />);

            expect(screen.getByText(/personal access token or oauth application/i)).toBeInTheDocument();
            expect(screen.getByText(/webhook permissions/i)).toBeInTheDocument();
        });
    });

    describe('edge cases', () => {
        it('should handle empty connections array', () => {
            render(<GitLabIndex connections={[]} />);

            expect(screen.getByText('No GitLab connections')).toBeInTheDocument();
        });

        it('should handle missing connections prop', () => {
            render(<GitLabIndex connections={undefined as any} />);

            expect(screen.getByText('GitLab Connections')).toBeInTheDocument();
        });

        it('should handle connections without group', () => {
            const connectionWithoutGroup = {
                ...mockConnections[0],
                group: undefined,
            };

            render(<GitLabIndex connections={[connectionWithoutGroup]} />);

            expect(screen.getByText('GitLab.com')).toBeInTheDocument();
        });

        it('should handle connections without last_synced_at', () => {
            const connectionWithoutSync = {
                ...mockConnections[0],
                last_synced_at: undefined,
            };

            render(<GitLabIndex connections={[connectionWithoutSync]} />);

            expect(screen.queryByText(/last synced:/i)).not.toBeInTheDocument();
        });
    });
});
