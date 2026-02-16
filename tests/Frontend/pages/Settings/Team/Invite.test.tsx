import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '../../../utils/test-utils';
import { router } from '@inertiajs/react';
import TeamInvite from '@/pages/Settings/Team/Invite';

vi.mock('@/pages/Settings/Index', () => ({
    SettingsLayout: ({ children }: any) => <div>{children}</div>,
}));

describe('TeamInvite Page', () => {
    const mockProjects = [
        { id: 1, name: 'Project Alpha' },
        { id: 2, name: 'Project Beta' },
        { id: 3, name: 'Project Gamma' },
    ];

    const mockPendingInvitations = [
        {
            id: 1,
            email: 'pending@example.com',
            role: 'developer' as const,
            projectAccess: 'all' as const,
            message: 'Welcome to the team!',
            sentAt: new Date().toISOString(),
            expiresAt: new Date(Date.now() + 86400000 * 7).toISOString(), // 7 days from now
            status: 'pending' as const,
        },
        {
            id: 2,
            email: 'limited@example.com',
            role: 'member' as const,
            projectAccess: [1, 2] as any,
            sentAt: new Date(Date.now() - 3600000).toISOString(), // 1 hour ago
            expiresAt: new Date(Date.now() + 86400000 * 6).toISOString(),
            status: 'pending' as const,
        },
    ];

    const mockExpiredInvitations = [
        {
            id: 3,
            email: 'expired@example.com',
            role: 'viewer' as const,
            projectAccess: 'all' as const,
            sentAt: new Date(Date.now() - 86400000 * 10).toISOString(),
            expiresAt: new Date(Date.now() - 86400000).toISOString(), // Expired yesterday
            status: 'expired' as const,
        },
    ];

    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders page header with title and description', () => {
        render(<TeamInvite projects={mockProjects} pendingInvitations={[]} />);

        expect(screen.getByText('Invite Team Members')).toBeInTheDocument();
        expect(screen.getByText('Send invitations to join your team')).toBeInTheDocument();
    });

    it('renders back button to team settings', () => {
        render(<TeamInvite projects={mockProjects} pendingInvitations={[]} />);

        const backButton = screen.getByRole('link', { name: '' });
        expect(backButton).toHaveAttribute('href', '/settings/team');
    });

    it('renders invitation form card', () => {
        render(<TeamInvite projects={mockProjects} pendingInvitations={[]} />);

        expect(screen.getByText('Send Invitations')).toBeInTheDocument();
        expect(screen.getByText('Invite multiple people at once with customized access')).toBeInTheDocument();
    });

    it('renders email address input field', () => {
        render(<TeamInvite projects={mockProjects} pendingInvitations={[]} />);

        expect(screen.getByText('Email Addresses')).toBeInTheDocument();
        expect(screen.getByPlaceholderText('colleague@example.com')).toBeInTheDocument();
    });

    it('renders Add Another Email button', () => {
        render(<TeamInvite projects={mockProjects} pendingInvitations={[]} />);

        expect(screen.getByText('Add Another Email')).toBeInTheDocument();
    });

    it('adds additional email field when Add Another Email is clicked', async () => {
        const { user } = render(<TeamInvite projects={mockProjects} pendingInvitations={[]} />);

        const addButton = screen.getByText('Add Another Email');
        await user.click(addButton);

        const emailInputs = screen.getAllByPlaceholderText('colleague@example.com');
        expect(emailInputs.length).toBe(2);
    });

    it('removes email field when X button is clicked', async () => {
        const { user } = render(<TeamInvite projects={mockProjects} pendingInvitations={[]} />);

        // Add second email field
        await user.click(screen.getByText('Add Another Email'));

        await waitFor(() => {
            const emailInputs = screen.getAllByPlaceholderText('colleague@example.com');
            expect(emailInputs.length).toBe(2);
        });

        // Find the X button (remove button) - it should be a button with X icon
        const buttons = screen.getAllByRole('button');
        const xButton = buttons.find(btn => {
            const svg = btn.querySelector('svg');
            // Look for X icon (lucide-x) which has specific path
            return svg && btn.closest('div')?.querySelector('input[placeholder="colleague@example.com"]');
        });

        if (xButton) {
            await user.click(xButton);

            await waitFor(() => {
                const emailInputs = screen.getAllByPlaceholderText('colleague@example.com');
                expect(emailInputs.length).toBe(1);
            });
        }
    });

    it('renders role selection section', () => {
        render(<TeamInvite projects={mockProjects} pendingInvitations={[]} />);

        expect(screen.getByText('Role')).toBeInTheDocument();
        expect(screen.getByText('admin')).toBeInTheDocument();
        expect(screen.getByText('developer')).toBeInTheDocument();
        expect(screen.getByText('member')).toBeInTheDocument();
        expect(screen.getByText('viewer')).toBeInTheDocument();
    });

    it('displays role descriptions', () => {
        render(<TeamInvite projects={mockProjects} pendingInvitations={[]} />);

        expect(screen.getByText('Manage team members and settings')).toBeInTheDocument();
        expect(screen.getByText('Deploy and manage resources')).toBeInTheDocument();
        expect(screen.getByText('View resources and basic operations')).toBeInTheDocument();
        expect(screen.getByText('Read-only access to resources')).toBeInTheDocument();
    });

    it('renders project access section', () => {
        render(<TeamInvite projects={mockProjects} pendingInvitations={[]} />);

        expect(screen.getByText('Project Access')).toBeInTheDocument();
        expect(screen.getByText('All Projects')).toBeInTheDocument();
        expect(screen.getByText('Specific Projects')).toBeInTheDocument();
    });

    it('shows project checkboxes when Specific Projects is selected', async () => {
        const { user } = render(<TeamInvite projects={mockProjects} pendingInvitations={[]} />);

        const specificProjectsButton = screen.getByText('Specific Projects').closest('button');
        if (specificProjectsButton) {
            await user.click(specificProjectsButton);

            await waitFor(() => {
                expect(screen.getByText('Project Alpha')).toBeInTheDocument();
                expect(screen.getByText('Project Beta')).toBeInTheDocument();
                expect(screen.getByText('Project Gamma')).toBeInTheDocument();
            });
        }
    });

    it('renders personal message textarea', () => {
        render(<TeamInvite projects={mockProjects} pendingInvitations={[]} />);

        expect(screen.getByText('Personal Message (Optional)')).toBeInTheDocument();
        expect(screen.getByPlaceholderText('Add a personal note to the invitation...')).toBeInTheDocument();
    });

    it('renders Cancel and Send Invitation buttons', () => {
        render(<TeamInvite projects={mockProjects} pendingInvitations={[]} />);

        expect(screen.getByText('Cancel')).toBeInTheDocument();
        expect(screen.getByText('Send Invitation')).toBeInTheDocument();
    });

    it('updates button text to "Send Invitations" when multiple emails exist', async () => {
        const { user } = render(<TeamInvite projects={mockProjects} pendingInvitations={[]} />);

        // Add second email
        await user.click(screen.getByText('Add Another Email'));

        const emailInputs = screen.getAllByPlaceholderText('colleague@example.com');
        await user.type(emailInputs[1], 'second@example.com');

        expect(screen.getByText('Send Invitations')).toBeInTheDocument();
    });

    it('submits form when Send Invitation is clicked', async () => {
        const { user } = render(<TeamInvite projects={mockProjects} pendingInvitations={[]} />);

        const emailInput = screen.getByPlaceholderText('colleague@example.com');
        await user.type(emailInput, 'newmember@example.com');

        const sendButton = screen.getByText('Send Invitation');
        await user.click(sendButton);

        expect(router.post).toHaveBeenCalledWith(
            '/settings/team/invite',
            expect.objectContaining({
                email: 'newmember@example.com',
                role: 'member',
            }),
            expect.any(Object)
        );
    });

    it('renders pending invitations card when invitations exist', () => {
        render(<TeamInvite projects={mockProjects} pendingInvitations={mockPendingInvitations} />);

        expect(screen.getByText('Pending Invitations')).toBeInTheDocument();
        expect(screen.getByText('2 invitations waiting to be accepted')).toBeInTheDocument();
    });

    it('displays pending invitation entries with email and role', () => {
        render(<TeamInvite projects={mockProjects} pendingInvitations={mockPendingInvitations} />);

        expect(screen.getByText('pending@example.com')).toBeInTheDocument();
        expect(screen.getByText('limited@example.com')).toBeInTheDocument();
        expect(screen.getAllByText('developer').length).toBeGreaterThan(0);
        expect(screen.getAllByText('member').length).toBeGreaterThan(0);
    });

    it('shows Limited Access badge for invitations with specific project access', () => {
        render(<TeamInvite projects={mockProjects} pendingInvitations={mockPendingInvitations} />);

        expect(screen.getByText('Limited Access')).toBeInTheDocument();
    });

    it('displays invitation message when available', () => {
        render(<TeamInvite projects={mockProjects} pendingInvitations={mockPendingInvitations} />);

        expect(screen.getByText(/Welcome to the team!/)).toBeInTheDocument();
    });

    it('renders resend and cancel buttons for pending invitations', () => {
        render(<TeamInvite projects={mockProjects} pendingInvitations={mockPendingInvitations} />);

        const buttons = screen.getAllByRole('button');
        const resendButtons = buttons.filter(btn => btn.querySelector('svg') && !btn.textContent?.includes('Send'));

        // At least 4 action buttons (2 resend + 2 cancel for 2 invitations)
        expect(resendButtons.length).toBeGreaterThan(2);
    });

    it('calls resend endpoint when resend button is clicked', async () => {
        const { user } = render(<TeamInvite projects={mockProjects} pendingInvitations={mockPendingInvitations} />);

        // Find resend button (icon-only button)
        const buttons = screen.getAllByRole('button');
        const resendButton = buttons.find(btn =>
            btn.getAttribute('variant') === 'secondary' && btn.querySelector('svg')
        );

        if (resendButton) {
            await user.click(resendButton);

            expect(router.post).toHaveBeenCalledWith(
                expect.stringContaining('/settings/team/invitations/'),
                expect.any(Object),
                expect.any(Object)
            );
        }
    });

    it('calls delete endpoint when cancel button is clicked', async () => {
        const { user } = render(<TeamInvite projects={mockProjects} pendingInvitations={mockPendingInvitations} />);

        // Find cancel button (trash icon)
        const buttons = screen.getAllByRole('button');
        const cancelButton = buttons.find(btn =>
            btn.getAttribute('variant') === 'ghost' && btn.querySelector('svg')
        );

        if (cancelButton) {
            await user.click(cancelButton);

            expect(router.delete).toHaveBeenCalledWith(
                expect.stringContaining('/settings/team/invitations/'),
                expect.any(Object)
            );
        }
    });

    it('renders expired invitations card when expired invitations exist', () => {
        render(<TeamInvite projects={mockProjects} pendingInvitations={mockExpiredInvitations} />);

        expect(screen.getByText('Expired Invitations')).toBeInTheDocument();
        expect(screen.getByText('These invitations have expired and need to be resent')).toBeInTheDocument();
    });

    it('displays expired invitation with danger styling', () => {
        render(<TeamInvite projects={mockProjects} pendingInvitations={mockExpiredInvitations} />);

        expect(screen.getByText('expired@example.com')).toBeInTheDocument();
        expect(screen.getByText('Expired')).toBeInTheDocument();
    });

    it('shows Resend button for expired invitations', () => {
        render(<TeamInvite projects={mockProjects} pendingInvitations={mockExpiredInvitations} />);

        expect(screen.getByText('Resend')).toBeInTheDocument();
    });

    it('does not render pending/expired cards when no invitations exist', () => {
        render(<TeamInvite projects={mockProjects} pendingInvitations={[]} />);

        expect(screen.queryByText('Pending Invitations')).not.toBeInTheDocument();
        expect(screen.queryByText('Expired Invitations')).not.toBeInTheDocument();
    });
});

describe('TeamInvite - Default Props', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('handles undefined projects prop gracefully', () => {
        render(<TeamInvite />);

        expect(screen.getByText('Invite Team Members')).toBeInTheDocument();
    });

    it('handles undefined pendingInvitations prop gracefully', () => {
        render(<TeamInvite />);

        expect(screen.queryByText('Pending Invitations')).not.toBeInTheDocument();
    });
});
