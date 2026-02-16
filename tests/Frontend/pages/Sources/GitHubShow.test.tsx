import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import { router } from '@inertiajs/react';
import GitHubShow from '@/pages/Sources/GitHub/Show';

describe('GitHub Show Page', () => {
    const mockSource = {
        id: 1,
        uuid: 'source-uuid',
        name: 'Saturn Platform',
        app_id: 123456,
        client_id: 'Iv1.a1b2c3d4e5f6g7h8',
        installation_id: 789012,
        html_url: 'https://github.com',
        api_url: 'https://api.github.com',
        organization: 'myorg',
        is_public: false,
        is_system_wide: false,
        connected: true,
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-15T00:00:00Z',
    };

    beforeEach(() => {
        vi.clearAllMocks();
        Object.assign(navigator, {
            clipboard: { writeText: vi.fn() },
        });
    });

    describe('rendering', () => {
        it('should render source name as title', () => {
            render(<GitHubShow source={mockSource} installationPath={null} applicationsCount={0} />);

            expect(screen.getByText('Saturn Platform')).toBeInTheDocument();
        });

        it('should render back button', () => {
            render(<GitHubShow source={mockSource} installationPath={null} applicationsCount={0} />);

            const backButton = screen.getByRole('button', { name: /back/i });
            expect(backButton).toBeInTheDocument();
            expect(backButton.closest('a')).toHaveAttribute('href', '/sources/github');
        });

        it('should display connected status badge', () => {
            render(<GitHubShow source={mockSource} installationPath={null} applicationsCount={0} />);

            expect(screen.getByText('Connected')).toBeInTheDocument();
        });

        it('should display not connected status when disconnected', () => {
            const disconnectedSource = { ...mockSource, connected: false };
            render(<GitHubShow source={disconnectedSource} installationPath={null} applicationsCount={0} />);

            expect(screen.getByText('Not Connected')).toBeInTheDocument();
        });

        it('should display applications count', () => {
            render(<GitHubShow source={mockSource} installationPath={null} applicationsCount={5} />);

            expect(screen.getByText(/5 applications/i)).toBeInTheDocument();
        });

        it('should display organization name', () => {
            render(<GitHubShow source={mockSource} installationPath={null} applicationsCount={0} />);

            expect(screen.getByText(/myorg/i)).toBeInTheDocument();
        });

        it('should display personal when no organization', () => {
            const personalSource = { ...mockSource, organization: null };
            render(<GitHubShow source={personalSource} installationPath={null} applicationsCount={0} />);

            expect(screen.getByText(/personal/i)).toBeInTheDocument();
        });
    });

    describe('connection details card', () => {
        it('should render connection details section', () => {
            render(<GitHubShow source={mockSource} installationPath={null} applicationsCount={0} />);

            expect(screen.getByText('Connection Details')).toBeInTheDocument();
            expect(screen.getByText('GitHub App configuration and credentials')).toBeInTheDocument();
        });

        it('should display app ID', () => {
            render(<GitHubShow source={mockSource} installationPath={null} applicationsCount={0} />);

            expect(screen.getByText('123456')).toBeInTheDocument();
        });

        it('should display client ID', () => {
            render(<GitHubShow source={mockSource} installationPath={null} applicationsCount={0} />);

            expect(screen.getByText('Iv1.a1b2c3d4e5f6g7h8')).toBeInTheDocument();
        });

        it('should display installation ID', () => {
            render(<GitHubShow source={mockSource} installationPath={null} applicationsCount={0} />);

            expect(screen.getByText('789012')).toBeInTheDocument();
        });

        it('should display API URL', () => {
            render(<GitHubShow source={mockSource} installationPath={null} applicationsCount={0} />);

            expect(screen.getByText('https://api.github.com')).toBeInTheDocument();
        });

        it('should display visibility badge', () => {
            render(<GitHubShow source={mockSource} installationPath={null} applicationsCount={0} />);

            expect(screen.getByText('Private')).toBeInTheDocument();
        });

        it('should display public visibility for public apps', () => {
            const publicSource = { ...mockSource, is_public: true };
            render(<GitHubShow source={publicSource} installationPath={null} applicationsCount={0} />);

            expect(screen.getByText('Public')).toBeInTheDocument();
        });

        it('should display created date', () => {
            render(<GitHubShow source={mockSource} installationPath={null} applicationsCount={0} />);

            expect(screen.getByText(/1\/1\/2024/)).toBeInTheDocument();
        });
    });

    describe('edit mode', () => {
        it('should render edit button', () => {
            render(<GitHubShow source={mockSource} installationPath={null} applicationsCount={0} />);

            expect(screen.getByRole('button', { name: /edit/i })).toBeInTheDocument();
        });

        it('should switch to edit mode when edit button is clicked', async () => {
            const { user } = render(<GitHubShow source={mockSource} installationPath={null} applicationsCount={0} />);

            const editButton = screen.getByRole('button', { name: /edit/i });
            await user.click(editButton);

            expect(screen.getByRole('button', { name: /save/i })).toBeInTheDocument();
            expect(screen.getByRole('button', { name: /cancel/i })).toBeInTheDocument();
        });

        it('should show input fields in edit mode', async () => {
            const { user } = render(<GitHubShow source={mockSource} installationPath={null} applicationsCount={0} />);

            await user.click(screen.getByRole('button', { name: /edit/i }));

            const inputs = screen.getAllByRole('textbox');
            expect(inputs.length).toBeGreaterThan(0);
        });

        it('should cancel edit mode', async () => {
            const { user } = render(<GitHubShow source={mockSource} installationPath={null} applicationsCount={0} />);

            await user.click(screen.getByRole('button', { name: /edit/i }));
            await user.click(screen.getByRole('button', { name: /cancel/i }));

            expect(screen.queryByRole('button', { name: /save/i })).not.toBeInTheDocument();
        });
    });

    describe('copy functionality', () => {
        it('should render copy buttons for app ID and client ID', () => {
            render(<GitHubShow source={mockSource} installationPath={null} applicationsCount={0} />);

            const copyButtons = screen.getAllByRole('button').filter(btn => {
                const svg = btn.querySelector('svg');
                return svg && btn.textContent === '';
            });
            expect(copyButtons.length).toBeGreaterThan(0);
        });

        it('should copy app ID to clipboard', async () => {
            const clipboardWriteText = vi.fn();
            Object.assign(navigator, {
                clipboard: { writeText: clipboardWriteText },
            });

            const { user } = render(<GitHubShow source={mockSource} installationPath={null} applicationsCount={0} />);

            const copyButtons = screen.getAllByRole('button').filter(btn => {
                const svg = btn.querySelector('svg');
                return svg && btn.textContent === '';
            });

            if (copyButtons[0]) {
                await user.click(copyButtons[0]);
                expect(clipboardWriteText).toHaveBeenCalled();
            }
        });
    });

    describe('GitHub settings link', () => {
        it('should render GitHub settings button when installation_id exists', () => {
            render(<GitHubShow source={mockSource} installationPath={null} applicationsCount={0} />);

            expect(screen.getByRole('button', { name: /github settings/i })).toBeInTheDocument();
        });

        it('should have correct GitHub settings URL', () => {
            render(<GitHubShow source={mockSource} installationPath={null} applicationsCount={0} />);

            const settingsButton = screen.getByRole('button', { name: /github settings/i });
            const link = settingsButton.closest('a');
            expect(link).toHaveAttribute('href', `https://github.com/settings/installations/789012`);
            expect(link).toHaveAttribute('target', '_blank');
        });
    });

    describe('setup required warning', () => {
        it('should show setup warning when not connected', () => {
            const disconnectedSource = { ...mockSource, connected: false, app_id: null };
            render(<GitHubShow source={disconnectedSource} installationPath={null} applicationsCount={0} />);

            expect(screen.getByText('Setup Required')).toBeInTheDocument();
        });

        it('should show install app button when app_id exists but no installation_id', () => {
            const sourceNeedsInstall = { ...mockSource, installation_id: null, connected: false };
            render(<GitHubShow source={sourceNeedsInstall} installationPath="/install/path" applicationsCount={0} />);

            expect(screen.getByRole('button', { name: /install app on github/i })).toBeInTheDocument();
        });

        it('should show check connection button', () => {
            const disconnectedSource = { ...mockSource, connected: false, installation_id: null };
            render(<GitHubShow source={disconnectedSource} installationPath="/install" applicationsCount={0} />);

            expect(screen.getByRole('button', { name: /check connection/i })).toBeInTheDocument();
        });
    });

    describe('danger zone', () => {
        it('should render danger zone section', () => {
            render(<GitHubShow source={mockSource} installationPath={null} applicationsCount={0} />);

            expect(screen.getByText('Danger Zone')).toBeInTheDocument();
        });

        it('should render delete button', () => {
            render(<GitHubShow source={mockSource} installationPath={null} applicationsCount={0} />);

            expect(screen.getByRole('button', { name: /delete/i })).toBeInTheDocument();
        });

        it('should disable delete button when applications exist', () => {
            render(<GitHubShow source={mockSource} installationPath={null} applicationsCount={5} />);

            const deleteButton = screen.getByRole('button', { name: /delete/i });
            expect(deleteButton).toBeDisabled();
        });

        it('should show warning when applications exist', () => {
            render(<GitHubShow source={mockSource} installationPath={null} applicationsCount={3} />);

            expect(screen.getByText(/cannot delete: 3 application/i)).toBeInTheDocument();
        });

        it('should enable delete button when no applications', () => {
            render(<GitHubShow source={mockSource} installationPath={null} applicationsCount={0} />);

            const deleteButton = screen.getByRole('button', { name: /delete/i });
            expect(deleteButton).not.toBeDisabled();
        });
    });

    describe('edge cases', () => {
        it('should handle null values gracefully', () => {
            const sourceWithNulls = {
                ...mockSource,
                app_id: null,
                client_id: null,
                installation_id: null,
                organization: null,
            };

            render(<GitHubShow source={sourceWithNulls} installationPath={null} applicationsCount={0} />);

            expect(screen.getAllByText('—').length).toBeGreaterThan(0);
        });

        it('should handle missing dates', () => {
            const sourceWithoutDates = {
                ...mockSource,
                created_at: null,
                updated_at: null,
            };

            render(<GitHubShow source={sourceWithoutDates} installationPath={null} applicationsCount={0} />);

            expect(screen.getByText('Saturn Platform')).toBeInTheDocument();
        });
    });
});
