import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '../../utils/test-utils';
import userEvent from '@testing-library/user-event';

// Mock the @inertiajs/react module
vi.mock('@inertiajs/react', () => ({
    Head: ({ children, title }: { children?: React.ReactNode; title?: string }) => (
        <title>{title}</title>
    ),
    Link: ({ children, href, className }: { children: React.ReactNode; href: string; className?: string }) => (
        <a href={href} className={className}>{children}</a>
    ),
    usePage: () => ({
        props: {
            auth: {
                user: {
                    id: 1,
                    name: 'John Doe',
                    email: 'john@example.com',
                },
            },
        },
    }),
}));

// Import after mock
import IntegrationsSettings from '@/pages/Settings/Integrations';

describe('Integrations Settings Page', () => {
    it('renders the settings layout with sidebar', () => {
        render(<IntegrationsSettings />);
        expect(screen.getAllByText('Settings').length).toBeGreaterThan(0);
        expect(screen.getByText('Account')).toBeInTheDocument();
        expect(screen.getByText('Team')).toBeInTheDocument();
    });

    it('displays integrations section', () => {
        render(<IntegrationsSettings />);
        expect(screen.getAllByText('Integrations').length).toBeGreaterThan(0);
        expect(screen.getByText('Connect external services to enhance your workflow')).toBeInTheDocument();
    });

    it('shows GitHub integration card', () => {
        render(<IntegrationsSettings />);
        expect(screen.getByText('GitHub')).toBeInTheDocument();
        expect(screen.getByText('Connect your GitHub repositories for automatic deployments')).toBeInTheDocument();
    });

    it('shows GitLab integration card', () => {
        render(<IntegrationsSettings />);
        expect(screen.getByText('GitLab')).toBeInTheDocument();
        expect(screen.getByText('Deploy from GitLab repositories')).toBeInTheDocument();
    });

    it('shows Slack integration card', () => {
        render(<IntegrationsSettings />);
        expect(screen.getByText('Slack')).toBeInTheDocument();
        expect(screen.getByText('Get deployment notifications in Slack')).toBeInTheDocument();
    });

    it('shows Discord integration card', () => {
        render(<IntegrationsSettings />);
        expect(screen.getByText('Discord')).toBeInTheDocument();
        expect(screen.getByText('Receive webhooks for deployment events')).toBeInTheDocument();
    });

    it('displays connected status for GitHub', () => {
        render(<IntegrationsSettings />);
        const connectedBadges = screen.getAllByText('Connected');
        expect(connectedBadges.length).toBeGreaterThan(0);
    });

    it('displays not connected status for GitLab', () => {
        render(<IntegrationsSettings />);
        const notConnectedBadges = screen.getAllByText('Not Connected');
        expect(notConnectedBadges.length).toBeGreaterThan(0);
    });

    it('shows connect button for disconnected integrations', () => {
        render(<IntegrationsSettings />);
        const connectButtons = screen.getAllByRole('button').filter(btn =>
            btn.textContent === 'Connect'
        );
        expect(connectButtons.length).toBeGreaterThan(0);
    });

    it('shows settings and disconnect buttons for connected integrations', () => {
        render(<IntegrationsSettings />);
        expect(screen.getAllByText('Settings').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Disconnect').length).toBeGreaterThan(0);
    });

    it('displays about integrations section', () => {
        render(<IntegrationsSettings />);
        expect(screen.getByText('About Integrations')).toBeInTheDocument();
        expect(screen.getByText('How integrations work with Saturn')).toBeInTheDocument();
    });

    it('shows integration information', () => {
        render(<IntegrationsSettings />);
        expect(screen.getByText(/Integrations allow Saturn to connect with external services/)).toBeInTheDocument();
    });

    it('can open connect modal', async () => {
        render(<IntegrationsSettings />);
        const connectButtons = screen.getAllByRole('button').filter(btn =>
            btn.textContent === 'Connect'
        );

        if (connectButtons.length > 0) {
            fireEvent.click(connectButtons[0]);
            await waitFor(() => {
                // Modal should open with GitLab or Discord content
                expect(screen.getByRole('dialog') || screen.getByText(/Connect/)).toBeInTheDocument();
            });
        }
    });

    it('has proper integration cards structure', () => {
        render(<IntegrationsSettings />);
        // Should have 4 integration cards
        expect(screen.getByText('GitHub')).toBeInTheDocument();
        expect(screen.getByText('GitLab')).toBeInTheDocument();
        expect(screen.getByText('Slack')).toBeInTheDocument();
        expect(screen.getByText('Discord')).toBeInTheDocument();
    });
});
