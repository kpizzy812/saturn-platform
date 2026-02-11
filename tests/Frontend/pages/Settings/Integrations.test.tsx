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
    router: {
        delete: vi.fn(),
        post: vi.fn(),
        visit: vi.fn(),
    },
}));

// Mock route helper
global.route = vi.fn((name: string, params?: any) => {
    if (name.includes('destroy')) {
        return `/sources/${params.id}`;
    }
    return '/sources';
});

// Import after mock
import IntegrationsSettings from '@/pages/Settings/Integrations';

// Mock data matching Props interface
const mockSources = [
    {
        id: 1,
        uuid: 'github-uuid-1',
        name: 'GitHub - Personal',
        organization: null,
        type: 'github' as const,
        connected: true,
        lastSync: '2024-01-15T10:30:00Z',
        applicationsCount: 2,
    },
    {
        id: 2,
        uuid: 'gitlab-uuid-1',
        name: 'GitLab - Company',
        organization: 'Company Org',
        type: 'gitlab' as const,
        connected: false,
        lastSync: null,
        applicationsCount: 0,
    },
];

const mockNotificationChannels = {
    slack: {
        enabled: true,
        configured: true,
        channel: '#deployments',
    },
    discord: {
        enabled: false,
        configured: false,
        channel: null,
    },
};

describe('Integrations Settings Page', () => {
    it('renders the settings layout with sidebar', () => {
        render(<IntegrationsSettings sources={mockSources} notificationChannels={mockNotificationChannels} />);
        expect(screen.getAllByText('Settings').length).toBeGreaterThan(0);
        expect(screen.getByText('Account')).toBeInTheDocument();
        expect(screen.getByText('Team')).toBeInTheDocument();
    });

    it('displays git sources section', () => {
        render(<IntegrationsSettings sources={mockSources} notificationChannels={mockNotificationChannels} />);
        expect(screen.getByText('Git Sources')).toBeInTheDocument();
        expect(screen.getByText('Connect GitHub and GitLab for automatic deployments')).toBeInTheDocument();
    });

    it('shows add source button', () => {
        render(<IntegrationsSettings sources={mockSources} notificationChannels={mockNotificationChannels} />);
        const addButtons = screen.getAllByText('Add Source');
        expect(addButtons.length).toBeGreaterThan(0);
    });

    it('displays connected GitHub source', () => {
        render(<IntegrationsSettings sources={mockSources} notificationChannels={mockNotificationChannels} />);
        expect(screen.getByText('GitHub - Personal')).toBeInTheDocument();
        expect(screen.getByText('Connected')).toBeInTheDocument();
    });

    it('displays not connected GitLab source', () => {
        render(<IntegrationsSettings sources={mockSources} notificationChannels={mockNotificationChannels} />);
        expect(screen.getByText('GitLab - Company')).toBeInTheDocument();
        expect(screen.getByText('Not Connected')).toBeInTheDocument();
    });

    it('shows settings button for sources', () => {
        render(<IntegrationsSettings sources={mockSources} notificationChannels={mockNotificationChannels} />);
        // Settings text appears multiple times (sidebar + source buttons + notification section)
        const settingsElements = screen.getAllByText('Settings');
        expect(settingsElements.length).toBeGreaterThan(2);
    });

    it('displays notification channels section', () => {
        render(<IntegrationsSettings sources={mockSources} notificationChannels={mockNotificationChannels} />);
        expect(screen.getByText('Notification Channels')).toBeInTheDocument();
        expect(screen.getByText('Get deployment and server notifications in Slack or Discord')).toBeInTheDocument();
    });

    it('shows Slack integration card', () => {
        render(<IntegrationsSettings sources={mockSources} notificationChannels={mockNotificationChannels} />);
        expect(screen.getByText('Slack')).toBeInTheDocument();
        expect(screen.getByText('Get deployment notifications in Slack')).toBeInTheDocument();
    });

    it('shows Discord integration card', () => {
        render(<IntegrationsSettings sources={mockSources} notificationChannels={mockNotificationChannels} />);
        expect(screen.getByText('Discord')).toBeInTheDocument();
        expect(screen.getByText('Receive webhooks for deployment events')).toBeInTheDocument();
    });

    it('displays Slack as enabled', () => {
        render(<IntegrationsSettings sources={mockSources} notificationChannels={mockNotificationChannels} />);
        // "Enabled" badge for Slack
        expect(screen.getByText('Enabled')).toBeInTheDocument();
    });

    it('displays Discord as not configured', () => {
        render(<IntegrationsSettings sources={mockSources} notificationChannels={mockNotificationChannels} />);
        // "Not Configured" badge for Discord
        expect(screen.getByText('Not Configured')).toBeInTheDocument();
    });

    it('shows configure buttons for notification integrations', () => {
        render(<IntegrationsSettings sources={mockSources} notificationChannels={mockNotificationChannels} />);
        const configureButtons = screen.getAllByText('Configure');
        // Should have 2 configure buttons (Slack and Discord)
        expect(configureButtons.length).toBe(2);
    });

    it('displays about integrations section', () => {
        render(<IntegrationsSettings sources={mockSources} notificationChannels={mockNotificationChannels} />);
        expect(screen.getByText('About Integrations')).toBeInTheDocument();
        expect(screen.getByText('How integrations work with Saturn')).toBeInTheDocument();
    });

    it('shows integration information', () => {
        render(<IntegrationsSettings sources={mockSources} notificationChannels={mockNotificationChannels} />);
        expect(screen.getByText(/Integrations allow Saturn to connect with external services/)).toBeInTheDocument();
    });

    it('displays application count for connected source', () => {
        render(<IntegrationsSettings sources={mockSources} notificationChannels={mockNotificationChannels} />);
        expect(screen.getByText(/2 applications/)).toBeInTheDocument();
    });

    it('shows empty state when no sources', () => {
        render(<IntegrationsSettings sources={[]} notificationChannels={mockNotificationChannels} />);
        expect(screen.getByText('No Git sources connected')).toBeInTheDocument();
        expect(screen.getByText(/Connect GitHub or GitLab to enable automatic deployments/)).toBeInTheDocument();
    });

    it('has proper card structure', () => {
        render(<IntegrationsSettings sources={mockSources} notificationChannels={mockNotificationChannels} />);
        expect(screen.getByText('Git Sources')).toBeInTheDocument();
        expect(screen.getByText('Notification Channels')).toBeInTheDocument();
        expect(screen.getByText('About Integrations')).toBeInTheDocument();
    });

    it('displays manage all button for notifications', () => {
        render(<IntegrationsSettings sources={mockSources} notificationChannels={mockNotificationChannels} />);
        expect(screen.getByText('Manage All')).toBeInTheDocument();
    });

    it('shows source organization info', () => {
        render(<IntegrationsSettings sources={mockSources} notificationChannels={mockNotificationChannels} />);
        expect(screen.getByText('Personal')).toBeInTheDocument();
        expect(screen.getByText('Company Org')).toBeInTheDocument();
    });
});
