import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import AcceptInvite from '@/pages/Auth/AcceptInvite';

describe('AcceptInvite Page', () => {
    const mockInvitation = {
        id: '123',
        team_name: 'Test Team',
        inviter_name: 'John Doe',
        inviter_email: 'john@example.com',
        role: 'admin',
        expires_at: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString(),
    };

    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('valid invitation', () => {
        it('should render invitation page title and subtitle', () => {
            render(<AcceptInvite invitation={mockInvitation} isAuthenticated={false} />);

            expect(screen.getByText('Team Invitation')).toBeInTheDocument();
            expect(screen.getByText("You've been invited to join a team on Saturn.")).toBeInTheDocument();
        });

        it('should display team name', () => {
            render(<AcceptInvite invitation={mockInvitation} isAuthenticated={false} />);

            expect(screen.getByText('Test Team')).toBeInTheDocument();
            expect(screen.getByText('Team Invitation')).toBeInTheDocument();
        });

        it('should display inviter information', () => {
            render(<AcceptInvite invitation={mockInvitation} isAuthenticated={false} />);

            expect(screen.getByText('John Doe')).toBeInTheDocument();
            expect(screen.getByText('john@example.com')).toBeInTheDocument();
            expect(screen.getByText('Invited by')).toBeInTheDocument();
        });

        it('should display role badge', () => {
            render(<AcceptInvite invitation={mockInvitation} isAuthenticated={false} />);

            expect(screen.getByText('admin')).toBeInTheDocument();
        });

        it('should display role permissions for admin', () => {
            render(<AcceptInvite invitation={mockInvitation} isAuthenticated={false} />);

            expect(screen.getByText('Deploy and manage applications')).toBeInTheDocument();
            expect(screen.getByText('Invite and manage team members')).toBeInTheDocument();
        });

        it('should display role permissions for owner', () => {
            const ownerInvitation = { ...mockInvitation, role: 'owner' };
            render(<AcceptInvite invitation={ownerInvitation} isAuthenticated={false} />);

            expect(screen.getByText('Full access to all team resources and settings')).toBeInTheDocument();
            expect(screen.getByText('Manage team members and billing')).toBeInTheDocument();
        });

        it('should display role permissions for member', () => {
            const memberInvitation = { ...mockInvitation, role: 'member' };
            render(<AcceptInvite invitation={memberInvitation} isAuthenticated={false} />);

            expect(screen.getByText('View team projects and deployments')).toBeInTheDocument();
            expect(screen.getByText('Deploy assigned applications')).toBeInTheDocument();
        });

        it('should render accept and decline buttons', () => {
            render(<AcceptInvite invitation={mockInvitation} isAuthenticated={false} />);

            expect(screen.getByRole('button', { name: /accept invitation/i })).toBeInTheDocument();
            expect(screen.getByRole('button', { name: /decline/i })).toBeInTheDocument();
        });

        it('should show authentication notice when not authenticated', () => {
            render(<AcceptInvite invitation={mockInvitation} isAuthenticated={false} />);

            expect(screen.getByText("You'll need to sign in or create an account to accept this invitation.")).toBeInTheDocument();
        });

        it('should not show authentication notice when authenticated', () => {
            render(<AcceptInvite invitation={mockInvitation} isAuthenticated={true} />);

            expect(screen.queryByText("You'll need to sign in or create an account to accept this invitation.")).not.toBeInTheDocument();
        });

        it('should show login link when not authenticated', () => {
            render(<AcceptInvite invitation={mockInvitation} isAuthenticated={false} />);

            expect(screen.getByText('Already have an account?')).toBeInTheDocument();
            const signInLink = screen.getByText('Sign in');
            expect(signInLink).toBeInTheDocument();
        });

        it('should not show login link when authenticated', () => {
            render(<AcceptInvite invitation={mockInvitation} isAuthenticated={true} />);

            expect(screen.queryByText('Already have an account?')).not.toBeInTheDocument();
        });
    });

    describe('invalid invitation', () => {
        it('should render error state when invitation is null', () => {
            render(<AcceptInvite invitation={null} isAuthenticated={false} />);

            // AuthLayout does not render title in DOM, only subtitle
            expect(screen.getByText('This invitation is no longer valid.')).toBeInTheDocument();
            expect(screen.getByText('This invitation has expired or is no longer valid.')).toBeInTheDocument();
        });

        it('should render error message when error prop is provided', () => {
            const errorMessage = 'This invitation has expired';
            render(<AcceptInvite invitation={null} error={errorMessage} isAuthenticated={false} />);

            expect(screen.getByText(errorMessage)).toBeInTheDocument();
        });

        it('should show go to login button when not authenticated and invitation is invalid', () => {
            render(<AcceptInvite invitation={null} isAuthenticated={false} />);

            const button = screen.getByRole('button', { name: /go to login/i });
            expect(button).toBeInTheDocument();
        });

        it('should show go to dashboard button when authenticated and invitation is invalid', () => {
            render(<AcceptInvite invitation={null} isAuthenticated={true} />);

            const button = screen.getByRole('button', { name: /go to dashboard/i });
            expect(button).toBeInTheDocument();
        });
    });

    describe('expiry date formatting', () => {
        it('should display expiry information', () => {
            render(<AcceptInvite invitation={mockInvitation} isAuthenticated={false} />);

            // Should display some expiry text
            const expiryText = screen.getByText(/expires/i);
            expect(expiryText).toBeInTheDocument();
        });

        it('should show expires in X days for future dates', () => {
            const futureDate = new Date(Date.now() + 3 * 24 * 60 * 60 * 1000).toISOString();
            const invitation = { ...mockInvitation, expires_at: futureDate };
            render(<AcceptInvite invitation={invitation} isAuthenticated={false} />);

            expect(screen.getByText(/expires in \d+ days/i)).toBeInTheDocument();
        });
    });
});
