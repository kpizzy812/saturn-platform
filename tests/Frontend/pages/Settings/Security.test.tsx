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
import SecuritySettings from '@/pages/Settings/Security';

describe('Security Settings Page', () => {
    it('renders the settings layout with sidebar', () => {
        render(<SecuritySettings />);
        expect(screen.getByText('Settings')).toBeInTheDocument();
        expect(screen.getByText('Account')).toBeInTheDocument();
        expect(screen.getByText('Team')).toBeInTheDocument();
    });

    it('displays active sessions section', () => {
        render(<SecuritySettings />);
        expect(screen.getByText('Active Sessions')).toBeInTheDocument();
        expect(screen.getByText('Manage devices that are currently signed in')).toBeInTheDocument();
    });

    it('shows current session badge', () => {
        render(<SecuritySettings />);
        expect(screen.getByText('Current')).toBeInTheDocument();
    });

    it('displays login history section', () => {
        render(<SecuritySettings />);
        expect(screen.getByText('Login History')).toBeInTheDocument();
        expect(screen.getByText('Recent login attempts to your account')).toBeInTheDocument();
    });

    it('shows success and failed login badges', () => {
        render(<SecuritySettings />);
        expect(screen.getAllByText('Success').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Failed').length).toBeGreaterThan(0);
    });

    it('displays IP allowlist section', () => {
        render(<SecuritySettings />);
        expect(screen.getByText('API IP Allowlist')).toBeInTheDocument();
        expect(screen.getByText('Restrict API access to specific IP addresses')).toBeInTheDocument();
    });

    it('shows add IP button', () => {
        render(<SecuritySettings />);
        const addIPButtons = screen.getAllByRole('button').filter(btn =>
            btn.textContent?.includes('Add IP')
        );
        expect(addIPButtons.length).toBeGreaterThan(0);
    });

    it('displays security notifications section', () => {
        render(<SecuritySettings />);
        expect(screen.getByText('Security Notifications')).toBeInTheDocument();
        expect(screen.getByText('Choose which security events to be notified about')).toBeInTheDocument();
    });

    it('shows notification toggles', () => {
        render(<SecuritySettings />);
        expect(screen.getByText('New Login')).toBeInTheDocument();
        expect(screen.getByText('Failed Login Attempt')).toBeInTheDocument();
        expect(screen.getByText('API Access')).toBeInTheDocument();
    });

    it('displays revoke all sessions button', () => {
        render(<SecuritySettings />);
        const revokeAllButtons = screen.getAllByRole('button').filter(btn =>
            btn.textContent?.includes('Revoke All')
        );
        expect(revokeAllButtons.length).toBeGreaterThan(0);
    });

    it('shows session devices', () => {
        render(<SecuritySettings />);
        expect(screen.getAllByText(/Chrome on macOS/).length).toBeGreaterThan(0);
    });

    it('displays IP addresses in allowlist', () => {
        render(<SecuritySettings />);
        expect(screen.getByText('192.168.1.0/24')).toBeInTheDocument();
        expect(screen.getByText('Office Network')).toBeInTheDocument();
    });

    it('can open add IP modal', async () => {
        render(<SecuritySettings />);
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
        render(<SecuritySettings />);
        // Should show multiple sessions
        expect(screen.getAllByText(/Chrome on macOS/).length).toBeGreaterThan(0);
        expect(screen.getAllByText(/Safari on iPhone/).length).toBeGreaterThan(0);
    });

    it('shows login history items', () => {
        render(<SecuritySettings />);
        // Should show multiple login history entries
        const successBadges = screen.getAllByText('Success');
        expect(successBadges.length).toBeGreaterThan(0);
    });

    it('displays notification preferences with checkboxes', () => {
        render(<SecuritySettings />);
        const checkboxes = screen.getAllByRole('checkbox');
        // Should have 3 notification toggles
        expect(checkboxes.length).toBe(3);
    });

    it('shows notification descriptions', () => {
        render(<SecuritySettings />);
        expect(screen.getByText(/Notify me when there's a new login to my account/)).toBeInTheDocument();
        expect(screen.getByText(/Alert me about failed login attempts/)).toBeInTheDocument();
        expect(screen.getByText(/Notify me about API token usage/)).toBeInTheDocument();
    });
});
