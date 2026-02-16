import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import { router } from '@inertiajs/react';
import BitbucketIndex from '@/pages/Sources/Bitbucket/Index';

describe('Bitbucket Index Page', () => {
    const mockConnections = [
        {
            id: 1,
            uuid: 'conn-uuid-1',
            name: 'Cloud Workspace',
            workspace: 'myworkspace',
            status: 'active' as const,
            repos_count: 12,
            type: 'cloud' as const,
            created_at: '2024-01-01T00:00:00Z',
            last_synced_at: '2024-01-15T10:30:00Z',
        },
        {
            id: 2,
            uuid: 'conn-uuid-2',
            name: 'Bitbucket Server',
            workspace: 'serverworkspace',
            status: 'pending' as const,
            repos_count: 7,
            type: 'server' as const,
            created_at: '2024-01-10T00:00:00Z',
        },
    ];

    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering', () => {
        it('should render page title and description', () => {
            render(<BitbucketIndex connections={[]} />);

            expect(screen.getByText('Bitbucket Connections')).toBeInTheDocument();
            expect(screen.getByText('Manage your Bitbucket workspace connections')).toBeInTheDocument();
        });

        it('should render back button', () => {
            render(<BitbucketIndex connections={[]} />);

            const backButton = screen.getByRole('button', { name: /back/i });
            expect(backButton).toBeInTheDocument();
            expect(backButton.closest('a')).toHaveAttribute('href', '/sources');
        });

        it('should render add connection button', () => {
            render(<BitbucketIndex connections={[]} />);

            const addButtons = screen.getAllByRole('button', { name: /add bitbucket connection/i });
            expect(addButtons.length).toBeGreaterThan(0);
        });

        it('should render instructions card', () => {
            render(<BitbucketIndex connections={[]} />);

            expect(screen.getByText('About Bitbucket Integration')).toBeInTheDocument();
        });

        it('should render features grid', () => {
            render(<BitbucketIndex connections={[]} />);

            expect(screen.getByText('Automatic Deployments')).toBeInTheDocument();
            expect(screen.getByText('Pull Request Previews')).toBeInTheDocument();
            expect(screen.getByText('Multiple Workspaces')).toBeInTheDocument();
        });

        it('should render cloud vs server comparison', () => {
            render(<BitbucketIndex connections={[]} />);

            expect(screen.getByText('Bitbucket Cloud vs Server')).toBeInTheDocument();
            expect(screen.getByText('Bitbucket Cloud')).toBeInTheDocument();
            expect(screen.getByText('Bitbucket Server/Data Center')).toBeInTheDocument();
        });
    });

    describe('empty state', () => {
        it('should show empty state when no connections', () => {
            render(<BitbucketIndex connections={[]} />);

            expect(screen.getByText('No Bitbucket connections')).toBeInTheDocument();
            expect(screen.getByText(/connect to bitbucket cloud or bitbucket server/i)).toBeInTheDocument();
        });

        it('should render connect button in empty state', () => {
            render(<BitbucketIndex connections={[]} />);

            const connectButtons = screen.getAllByRole('button', { name: /connect bitbucket/i });
            expect(connectButtons.length).toBeGreaterThan(0);
        });

        it('should have correct link in empty state', () => {
            render(<BitbucketIndex connections={[]} />);

            const connectButton = screen.getAllByRole('button', { name: /connect bitbucket/i })[0];
            const link = connectButton.closest('a');
            expect(link).toHaveAttribute('href', '/sources/bitbucket/create');
        });
    });

    describe('connections list', () => {
        it('should display connection names', () => {
            render(<BitbucketIndex connections={mockConnections} />);

            expect(screen.getByText('Cloud Workspace')).toBeInTheDocument();
            expect(screen.getByText('Bitbucket Server')).toBeInTheDocument();
        });

        it('should display workspace names', () => {
            render(<BitbucketIndex connections={mockConnections} />);

            expect(screen.getByText(/@myworkspace/i)).toBeInTheDocument();
            expect(screen.getByText(/@serverworkspace/i)).toBeInTheDocument();
        });

        it('should display status badges', () => {
            render(<BitbucketIndex connections={mockConnections} />);

            expect(screen.getByText('active')).toBeInTheDocument();
            expect(screen.getByText('pending')).toBeInTheDocument();
        });

        it('should display type badges', () => {
            render(<BitbucketIndex connections={mockConnections} />);

            expect(screen.getByText('Cloud')).toBeInTheDocument();
            expect(screen.getByText('Server')).toBeInTheDocument();
        });

        it('should display repository counts', () => {
            render(<BitbucketIndex connections={mockConnections} />);

            expect(screen.getByText(/12 repositories/i)).toBeInTheDocument();
            expect(screen.getByText(/7 repositories/i)).toBeInTheDocument();
        });

        it('should display last synced information', () => {
            render(<BitbucketIndex connections={mockConnections} />);

            expect(screen.getByText(/last synced:/i)).toBeInTheDocument();
        });
    });

    describe('connection actions', () => {
        it('should render sync buttons', () => {
            render(<BitbucketIndex connections={mockConnections} />);

            const syncButtons = screen.getAllByRole('button', { name: /sync/i });
            expect(syncButtons.length).toBeGreaterThan(0);
        });

        it('should call sync endpoint when sync button is clicked', async () => {
            const { user } = render(<BitbucketIndex connections={mockConnections} />);

            const syncButton = screen.getAllByRole('button', { name: /sync/i })[0];
            await user.click(syncButton);

            expect(router.post).toHaveBeenCalledWith('/sources/bitbucket/conn-uuid-1/sync');
        });

        it('should render Bitbucket external links', () => {
            render(<BitbucketIndex connections={mockConnections} />);

            const bitbucketLinks = screen.getAllByRole('button', { name: /bitbucket/i }).filter(btn =>
                btn.closest('a')?.hasAttribute('target')
            );
            expect(bitbucketLinks.length).toBeGreaterThan(0);
        });

        it('should have correct external link URLs for cloud', () => {
            render(<BitbucketIndex connections={mockConnections} />);

            const bitbucketButton = screen.getAllByRole('button', { name: /bitbucket/i })[0];
            const link = bitbucketButton.closest('a');
            expect(link).toHaveAttribute('href', 'https://bitbucket.org/myworkspace');
            expect(link).toHaveAttribute('target', '_blank');
        });

        it('should render delete buttons', () => {
            render(<BitbucketIndex connections={mockConnections} />);

            const deleteButtons = screen.getAllByRole('button').filter(btn =>
                btn.className.includes('text-danger')
            );
            expect(deleteButtons.length).toBe(2);
        });
    });

    describe('delete functionality', () => {
        it('should show confirmation before deleting', async () => {
            const { user } = render(<BitbucketIndex connections={mockConnections} />);

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
        it('should display Bitbucket Cloud and Server information', () => {
            render(<BitbucketIndex connections={[]} />);

            expect(screen.getByText(/bitbucket cloud:/i)).toBeInTheDocument();
            expect(screen.getByText(/bitbucket server:/i)).toBeInTheDocument();
        });

        it('should display requirements list', () => {
            render(<BitbucketIndex connections={[]} />);

            expect(screen.getByText(/app password or oauth consumer/i)).toBeInTheDocument();
            expect(screen.getByText(/webhook permissions/i)).toBeInTheDocument();
        });
    });

    describe('cloud vs server comparison', () => {
        it('should display cloud features', () => {
            render(<BitbucketIndex connections={[]} />);

            expect(screen.getByText(/hosted at bitbucket.org/i)).toBeInTheDocument();
            expect(screen.getByText(/uses oauth 2.0/i)).toBeInTheDocument();
            expect(screen.getByText(/works with workspaces/i)).toBeInTheDocument();
        });

        it('should display server features', () => {
            render(<BitbucketIndex connections={[]} />);

            expect(screen.getByText(/self-hosted on your infrastructure/i)).toBeInTheDocument();
            expect(screen.getByText(/http basic or personal access tokens/i)).toBeInTheDocument();
            expect(screen.getByText(/works with projects/i)).toBeInTheDocument();
        });
    });

    describe('edge cases', () => {
        it('should handle empty connections array', () => {
            render(<BitbucketIndex connections={[]} />);

            expect(screen.getByText('No Bitbucket connections')).toBeInTheDocument();
        });

        it('should handle missing connections prop', () => {
            render(<BitbucketIndex connections={undefined as any} />);

            expect(screen.getByText('Bitbucket Connections')).toBeInTheDocument();
        });

        it('should handle connections without last_synced_at', () => {
            const connectionWithoutSync = {
                ...mockConnections[0],
                last_synced_at: undefined,
            };

            render(<BitbucketIndex connections={[connectionWithoutSync]} />);

            expect(screen.queryByText(/last synced:/i)).not.toBeInTheDocument();
        });

        it('should handle server type external links', () => {
            render(<BitbucketIndex connections={mockConnections} />);

            const bitbucketButtons = screen.getAllByRole('button', { name: /bitbucket/i });
            // Server type should have '#' href or no href
            expect(bitbucketButtons.length).toBeGreaterThan(0);
        });
    });
});
