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

// Import after mock
import SecuritySettings from '@/pages/Settings/Security';

// Mock data matching Props interface
const mockSessions = [
    {
        id: 'session-1',
        ip: '192.168.1.100',
        userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        lastActive: '2024-01-15T10:30:00Z',
        current: true,
    },
    {
        id: 'session-2',
        ip: '10.0.0.50',
        userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1',
        lastActive: '2024-01-14T08:15:00Z',
        current: false,
    },
];

const mockLoginHistory = [
    {
        id: 1,
        timestamp: '2024-01-15T10:30:00Z',
        ip: '192.168.1.100',
        userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        success: true,
        location: 'San Francisco, US',
    },
    {
        id: 2,
        timestamp: '2024-01-15T09:45:00Z',
        ip: '10.0.0.50',
        userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1',
        success: false,
        location: 'New York, US',
    },
    {
        id: 3,
        timestamp: '2024-01-14T08:15:00Z',
        ip: '10.0.0.50',
        userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1',
        success: true,
        location: 'New York, US',
    },
];

const mockIpAllowlist = [
    {
        id: 1,
        ip: '192.168.1.0/24',
        description: 'Office Network',
        createdAt: '2024-01-10T12:00:00Z',
    },
    {
        id: 2,
        ip: '10.0.0.100',
        description: 'Home Office',
        createdAt: '2024-01-12T14:30:00Z',
    },
];

const mockSecurityNotifications = {
    newLogin: true,
    failedLogin: true,
    apiAccess: false,
};

describe('Security Settings Page', () => {
    it('renders the settings layout with sidebar', () => {
        render(<SecuritySettings
            sessions={mockSessions}
            loginHistory={mockLoginHistory}
            ipAllowlist={mockIpAllowlist}
            securityNotifications={mockSecurityNotifications}
        />);
        expect(screen.getByText('Settings')).toBeInTheDocument();
        expect(screen.getByText('Account')).toBeInTheDocument();
        expect(screen.getByText('Team')).toBeInTheDocument();
    });

    it('displays active sessions section', () => {
        render(<SecuritySettings
            sessions={mockSessions}
            loginHistory={mockLoginHistory}
            ipAllowlist={mockIpAllowlist}
            securityNotifications={mockSecurityNotifications}
        />);
        expect(screen.getByText('Active Sessions')).toBeInTheDocument();
        expect(screen.getByText('Manage devices that are currently signed in')).toBeInTheDocument();
    });

    it('shows current session badge', () => {
        render(<SecuritySettings
            sessions={mockSessions}
            loginHistory={mockLoginHistory}
            ipAllowlist={mockIpAllowlist}
            securityNotifications={mockSecurityNotifications}
        />);
        expect(screen.getByText('Current')).toBeInTheDocument();
    });

    it('displays login history section', () => {
        render(<SecuritySettings
            sessions={mockSessions}
            loginHistory={mockLoginHistory}
            ipAllowlist={mockIpAllowlist}
            securityNotifications={mockSecurityNotifications}
        />);
        expect(screen.getByText('Login History')).toBeInTheDocument();
        expect(screen.getByText('Recent login attempts to your account')).toBeInTheDocument();
    });

    it('shows success and failed login badges', () => {
        render(<SecuritySettings
            sessions={mockSessions}
            loginHistory={mockLoginHistory}
            ipAllowlist={mockIpAllowlist}
            securityNotifications={mockSecurityNotifications}
        />);
        expect(screen.getAllByText('Success').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Failed').length).toBeGreaterThan(0);
    });

    it('displays IP allowlist section', () => {
        render(<SecuritySettings
            sessions={mockSessions}
            loginHistory={mockLoginHistory}
            ipAllowlist={mockIpAllowlist}
            securityNotifications={mockSecurityNotifications}
        />);
        expect(screen.getByText('API IP Allowlist')).toBeInTheDocument();
        expect(screen.getByText('Restrict API access to specific IP addresses')).toBeInTheDocument();
    });

    it('shows add IP button', () => {
        render(<SecuritySettings
            sessions={mockSessions}
            loginHistory={mockLoginHistory}
            ipAllowlist={mockIpAllowlist}
            securityNotifications={mockSecurityNotifications}
        />);
        const addIPButtons = screen.getAllByRole('button').filter(btn =>
            btn.textContent?.includes('Add IP')
        );
        expect(addIPButtons.length).toBeGreaterThan(0);
    });

    it('displays security notifications section', () => {
        render(<SecuritySettings
            sessions={mockSessions}
            loginHistory={mockLoginHistory}
            ipAllowlist={mockIpAllowlist}
            securityNotifications={mockSecurityNotifications}
        />);
        expect(screen.getByText('Security Notifications')).toBeInTheDocument();
        expect(screen.getByText('Choose which security events to be notified about')).toBeInTheDocument();
    });

    it('shows notification toggles', () => {
        render(<SecuritySettings
            sessions={mockSessions}
            loginHistory={mockLoginHistory}
            ipAllowlist={mockIpAllowlist}
            securityNotifications={mockSecurityNotifications}
        />);
        expect(screen.getByText('New Login')).toBeInTheDocument();
        expect(screen.getByText('Failed Login Attempt')).toBeInTheDocument();
        expect(screen.getByText('API Access')).toBeInTheDocument();
    });

    it('displays revoke all sessions button', () => {
        render(<SecuritySettings
            sessions={mockSessions}
            loginHistory={mockLoginHistory}
            ipAllowlist={mockIpAllowlist}
            securityNotifications={mockSecurityNotifications}
        />);
        const revokeAllButtons = screen.getAllByRole('button').filter(btn =>
            btn.textContent?.includes('Revoke All')
        );
        expect(revokeAllButtons.length).toBeGreaterThan(0);
    });

    it('shows session devices', () => {
        render(<SecuritySettings
            sessions={mockSessions}
            loginHistory={mockLoginHistory}
            ipAllowlist={mockIpAllowlist}
            securityNotifications={mockSecurityNotifications}
        />);
        // Chrome on macOS from user agent parsing
        expect(screen.getAllByText(/Chrome on macOS/).length).toBeGreaterThan(0);
    });

    it('displays IP addresses in allowlist', () => {
        render(<SecuritySettings
            sessions={mockSessions}
            loginHistory={mockLoginHistory}
            ipAllowlist={mockIpAllowlist}
            securityNotifications={mockSecurityNotifications}
        />);
        expect(screen.getByText('192.168.1.0/24')).toBeInTheDocument();
        expect(screen.getByText('Office Network')).toBeInTheDocument();
    });

    it('can open add IP modal', async () => {
        render(<SecuritySettings
            sessions={mockSessions}
            loginHistory={mockLoginHistory}
            ipAllowlist={mockIpAllowlist}
            securityNotifications={mockSecurityNotifications}
        />);
        const addIPButtons = screen.getAllByRole('button').filter(btn =>
            btn.textContent?.includes('Add IP')
        );

        if (addIPButtons.length > 0) {
            fireEvent.click(addIPButtons[0]);
            await waitFor(() => {
                expect(screen.getByText('Add IP to Allowlist')).toBeInTheDocument();
            });
        }
    });

    it('has session list items', () => {
        render(<SecuritySettings
            sessions={mockSessions}
            loginHistory={mockLoginHistory}
            ipAllowlist={mockIpAllowlist}
            securityNotifications={mockSecurityNotifications}
        />);
        // Should show Chrome on macOS and Mobile Safari (iPhone may be parsed as Mobile Safari)
        expect(screen.getAllByText(/Chrome on macOS/).length).toBeGreaterThan(0);
        // Mobile device text may vary based on user agent parsing
        const mobileElements = screen.queryAllByText(/Safari|Mobile/);
        expect(mobileElements.length).toBeGreaterThan(0);
    });

    it('shows login history items', () => {
        render(<SecuritySettings
            sessions={mockSessions}
            loginHistory={mockLoginHistory}
            ipAllowlist={mockIpAllowlist}
            securityNotifications={mockSecurityNotifications}
        />);
        // Should show multiple login history entries
        const successBadges = screen.getAllByText('Success');
        expect(successBadges.length).toBeGreaterThan(0);
    });

    it('displays notification preferences with checkboxes', () => {
        render(<SecuritySettings
            sessions={mockSessions}
            loginHistory={mockLoginHistory}
            ipAllowlist={mockIpAllowlist}
            securityNotifications={mockSecurityNotifications}
        />);
        const checkboxes = screen.getAllByRole('checkbox');
        // Should have 3 notification toggles
        expect(checkboxes.length).toBe(3);
    });

    it('shows notification descriptions', () => {
        render(<SecuritySettings
            sessions={mockSessions}
            loginHistory={mockLoginHistory}
            ipAllowlist={mockIpAllowlist}
            securityNotifications={mockSecurityNotifications}
        />);
        expect(screen.getByText(/Notify me when there's a new login to my account/)).toBeInTheDocument();
        expect(screen.getByText(/Alert me about failed login attempts/)).toBeInTheDocument();
        expect(screen.getByText(/Notify me about API token usage/)).toBeInTheDocument();
    });

    it('shows empty state for sessions when none provided', () => {
        render(<SecuritySettings
            sessions={[]}
            loginHistory={mockLoginHistory}
            ipAllowlist={mockIpAllowlist}
            securityNotifications={mockSecurityNotifications}
        />);
        expect(screen.getByText('No active sessions')).toBeInTheDocument();
    });

    it('shows empty state for IP allowlist when none provided', () => {
        render(<SecuritySettings
            sessions={mockSessions}
            loginHistory={mockLoginHistory}
            ipAllowlist={[]}
            securityNotifications={mockSecurityNotifications}
        />);
        expect(screen.getByText('No IP restrictions')).toBeInTheDocument();
        expect(screen.getByText('API can be accessed from any IP address')).toBeInTheDocument();
    });

    it('displays location information in login history', () => {
        render(<SecuritySettings
            sessions={mockSessions}
            loginHistory={mockLoginHistory}
            ipAllowlist={mockIpAllowlist}
            securityNotifications={mockSecurityNotifications}
        />);
        // Location is displayed along with IP address
        expect(screen.getByText(/San Francisco, US/)).toBeInTheDocument();
        // New York appears twice but check for at least one occurrence
        const newYorkElements = screen.getAllByText(/New York, US/);
        expect(newYorkElements.length).toBeGreaterThan(0);
    });
});
