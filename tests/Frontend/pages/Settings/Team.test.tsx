import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '../../utils/test-utils';
import userEvent from '@testing-library/user-event';
import * as InertiaReact from '@inertiajs/react';

// Import after mock setup
import TeamSettings from '@/pages/Settings/Team';

// Mock usePage to return full auth object
const mockUsePage = vi.fn(() => ({
    url: '/settings/team',
    props: {
        auth: {
            id: 1,
            name: 'John Doe',
            email: 'john@example.com',
            avatar: null,
            email_verified_at: '2024-01-01T00:00:00Z',
            is_superadmin: false,
            two_factor_enabled: false,
            role: 'owner',
            permissions: {
                isAdmin: false,
                isOwner: true,
                isMember: false,
                isDeveloper: false,
                isViewer: false,
            },
        },
        team: { id: 1, name: 'Test Team', personal_team: true },
        teams: [],
        flash: {},
        appName: 'Saturn',
        aiChatEnabled: false,
    },
}));

vi.spyOn(InertiaReact, 'usePage').mockImplementation(mockUsePage);

const mockTeam = {
    id: 1,
    name: 'Test Team',
    avatar: null,
    memberCount: 3,
};

const mockMembers = [
    {
        id: 1,
        name: 'John Doe',
        email: 'john@example.com',
        avatar: null,
        role: 'owner' as const,
        joinedAt: '2024-01-01T00:00:00Z',
        lastActive: new Date().toISOString(),
        invitedBy: undefined,
        projectAccess: {
            hasFullAccess: true,
            hasNoAccess: false,
            hasLimitedAccess: false,
            count: 5,
            total: 5,
        },
    },
    {
        id: 2,
        name: 'Jane Smith',
        email: 'jane@example.com',
        avatar: null,
        role: 'admin' as const,
        joinedAt: '2024-01-10T00:00:00Z',
        lastActive: '2024-01-15T10:30:00Z',
        invitedBy: {
            id: 1,
            name: 'John Doe',
            email: 'john@example.com',
        },
        projectAccess: {
            hasFullAccess: false,
            hasNoAccess: false,
            hasLimitedAccess: true,
            count: 3,
            total: 5,
        },
    },
    {
        id: 3,
        name: 'Bob Developer',
        email: 'bob@example.com',
        avatar: null,
        role: 'developer' as const,
        joinedAt: '2024-01-15T00:00:00Z',
        lastActive: '2024-01-20T00:00:00Z',
        invitedBy: {
            id: 1,
            name: 'John Doe',
            email: 'john@example.com',
        },
        projectAccess: {
            hasFullAccess: false,
            hasNoAccess: true,
            hasLimitedAccess: false,
            count: 0,
            total: 5,
        },
    },
];

const mockInvitations = [
    {
        id: 1,
        email: 'newmember@example.com',
        role: 'member' as const,
        sentAt: '2024-01-20T00:00:00Z',
        link: 'https://saturn.app/invitations/abc123',
    },
];

const mockReceivedInvitations = [
    {
        id: 1,
        uuid: 'xyz789',
        teamName: 'Another Team',
        role: 'developer' as const,
        sentAt: '2024-01-18T00:00:00Z',
        expiresAt: '2024-02-18T00:00:00Z',
    },
];

describe('Team Settings Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders the settings layout with sidebar', () => {
        render(
            <TeamSettings
                team={mockTeam}
                members={mockMembers}
                invitations={[]}
                receivedInvitations={[]}
                currentUserRole="owner"
                canManageTeam={true}
                canManageRoles={true}
            />
        );
        expect(screen.getAllByText('Settings').length).toBeGreaterThan(0);
    });

    it('displays team overview section', () => {
        render(
            <TeamSettings
                team={mockTeam}
                members={mockMembers}
                invitations={[]}
                receivedInvitations={[]}
                currentUserRole="owner"
                canManageTeam={true}
                canManageRoles={true}
            />
        );
        expect(screen.getByText('Test Team')).toBeInTheDocument();
    });

    it('shows team member count', () => {
        render(
            <TeamSettings
                team={mockTeam}
                members={mockMembers}
                invitations={[]}
                receivedInvitations={[]}
                currentUserRole="owner"
                canManageTeam={true}
                canManageRoles={true}
            />
        );
        expect(screen.getByText(/3 members/)).toBeInTheDocument();
    });

    it('displays total members stat', () => {
        render(
            <TeamSettings
                team={mockTeam}
                members={mockMembers}
                invitations={[]}
                receivedInvitations={[]}
                currentUserRole="owner"
                canManageTeam={true}
                canManageRoles={true}
            />
        );
        expect(screen.getByText('Total Members')).toBeInTheDocument();
    });

    it('shows online members count', () => {
        render(
            <TeamSettings
                team={mockTeam}
                members={mockMembers}
                invitations={[]}
                receivedInvitations={[]}
                currentUserRole="owner"
                canManageTeam={true}
                canManageRoles={true}
            />
        );
        expect(screen.getByText('Online Now')).toBeInTheDocument();
    });

    it('shows pending invites count', () => {
        render(
            <TeamSettings
                team={mockTeam}
                members={mockMembers}
                invitations={mockInvitations}
                receivedInvitations={[]}
                currentUserRole="owner"
                canManageTeam={true}
                canManageRoles={true}
            />
        );
        expect(screen.getByText('Pending Invites')).toBeInTheDocument();
    });

    it('displays team members section', () => {
        render(
            <TeamSettings
                team={mockTeam}
                members={mockMembers}
                invitations={[]}
                receivedInvitations={[]}
                currentUserRole="owner"
                canManageTeam={true}
                canManageRoles={true}
            />
        );
        expect(screen.getByText('Team Members')).toBeInTheDocument();
        expect(screen.getByText('Manage who has access to your team')).toBeInTheDocument();
    });

    it('shows invite member button when user can manage team', () => {
        render(
            <TeamSettings
                team={mockTeam}
                members={mockMembers}
                invitations={[]}
                receivedInvitations={[]}
                currentUserRole="owner"
                canManageTeam={true}
                canManageRoles={true}
            />
        );
        expect(screen.getByRole('button', { name: /Invite Member/i })).toBeInTheDocument();
    });

    it('hides invite member button when user cannot manage team', () => {
        render(
            <TeamSettings
                team={mockTeam}
                members={mockMembers}
                invitations={[]}
                receivedInvitations={[]}
                currentUserRole="member"
                canManageTeam={false}
                canManageRoles={false}
            />
        );
        expect(screen.queryByRole('button', { name: /Invite Member/i })).not.toBeInTheDocument();
    });

    it('displays all team members', () => {
        render(
            <TeamSettings
                team={mockTeam}
                members={mockMembers}
                invitations={[]}
                receivedInvitations={[]}
                currentUserRole="owner"
                canManageTeam={true}
                canManageRoles={true}
            />
        );
        const johnDoeElements = screen.getAllByText('John Doe');
        expect(johnDoeElements.length).toBeGreaterThan(0);
        expect(screen.getByText('Jane Smith')).toBeInTheDocument();
        expect(screen.getByText('Bob Developer')).toBeInTheDocument();
    });

    it('shows member emails', () => {
        render(
            <TeamSettings
                team={mockTeam}
                members={mockMembers}
                invitations={[]}
                receivedInvitations={[]}
                currentUserRole="owner"
                canManageTeam={true}
                canManageRoles={true}
            />
        );
        expect(screen.getByText('john@example.com')).toBeInTheDocument();
        expect(screen.getByText('jane@example.com')).toBeInTheDocument();
        expect(screen.getByText('bob@example.com')).toBeInTheDocument();
    });

    it('displays member roles with badges', () => {
        render(
            <TeamSettings
                team={mockTeam}
                members={mockMembers}
                invitations={[]}
                receivedInvitations={[]}
                currentUserRole="owner"
                canManageTeam={true}
                canManageRoles={true}
            />
        );
        expect(screen.getByText('owner')).toBeInTheDocument();
        expect(screen.getByText('admin')).toBeInTheDocument();
        expect(screen.getByText('developer')).toBeInTheDocument();
    });

    it('shows project access badges for members', () => {
        render(
            <TeamSettings
                team={mockTeam}
                members={mockMembers}
                invitations={[]}
                receivedInvitations={[]}
                currentUserRole="owner"
                canManageTeam={true}
                canManageRoles={true}
            />
        );
        // Check for project access indicators in the DOM
        const projectAccessElements = document.querySelectorAll('[title*="project"]');
        expect(projectAccessElements.length).toBeGreaterThan(0);
    });

    it('shows invited by information for members', () => {
        render(
            <TeamSettings
                team={mockTeam}
                members={mockMembers}
                invitations={[]}
                receivedInvitations={[]}
                currentUserRole="owner"
                canManageTeam={true}
                canManageRoles={true}
            />
        );
        const invitedByElements = screen.getAllByText(/Invited by/);
        expect(invitedByElements.length).toBeGreaterThan(0);
    });

    it('has search input for filtering members', () => {
        render(
            <TeamSettings
                team={mockTeam}
                members={mockMembers}
                invitations={[]}
                receivedInvitations={[]}
                currentUserRole="owner"
                canManageTeam={true}
                canManageRoles={true}
            />
        );
        expect(screen.getByPlaceholderText('Search members by name or email...')).toBeInTheDocument();
    });

    it('has role filter dropdown', () => {
        render(
            <TeamSettings
                team={mockTeam}
                members={mockMembers}
                invitations={[]}
                receivedInvitations={[]}
                currentUserRole="owner"
                canManageTeam={true}
                canManageRoles={true}
            />
        );
        const roleSelect = screen.getByDisplayValue('All Roles');
        expect(roleSelect).toBeInTheDocument();
    });

    it('can filter members by search query', async () => {
        const user = userEvent.setup();
        render(
            <TeamSettings
                team={mockTeam}
                members={mockMembers}
                invitations={[]}
                receivedInvitations={[]}
                currentUserRole="owner"
                canManageTeam={true}
                canManageRoles={true}
            />
        );

        const searchInput = screen.getByPlaceholderText('Search members by name or email...');
        await user.type(searchInput, 'Jane');

        await waitFor(() => {
            expect(screen.getByText('Jane Smith')).toBeInTheDocument();
            expect(screen.queryByText('Bob Developer')).not.toBeInTheDocument();
        });
    });

    it('opens invite modal when invite button is clicked', async () => {
        const user = userEvent.setup();
        render(
            <TeamSettings
                team={mockTeam}
                members={mockMembers}
                invitations={[]}
                receivedInvitations={[]}
                currentUserRole="owner"
                canManageTeam={true}
                canManageRoles={true}
            />
        );

        const inviteButton = screen.getByRole('button', { name: /Invite Member/i });
        await user.click(inviteButton);

        await waitFor(() => {
            expect(screen.getByText('Invite Team Member')).toBeInTheDocument();
            expect(screen.getByText('Send an invitation to join your team')).toBeInTheDocument();
        });
    });

    it('renders invite form fields', async () => {
        const user = userEvent.setup();
        render(
            <TeamSettings
                team={mockTeam}
                members={mockMembers}
                invitations={[]}
                receivedInvitations={[]}
                currentUserRole="owner"
                canManageTeam={true}
                canManageRoles={true}
            />
        );

        const inviteButton = screen.getByRole('button', { name: /Invite Member/i });
        await user.click(inviteButton);

        await waitFor(() => {
            expect(screen.getByLabelText('Email Address')).toBeInTheDocument();
            expect(screen.getByLabelText('Role')).toBeInTheDocument();
        });
    });

    it('displays pending invitations section when invitations exist', () => {
        render(
            <TeamSettings
                team={mockTeam}
                members={mockMembers}
                invitations={mockInvitations}
                receivedInvitations={[]}
                currentUserRole="owner"
                canManageTeam={true}
                canManageRoles={true}
            />
        );
        expect(screen.getByText('Pending Invitations')).toBeInTheDocument();
        expect(screen.getByText("Invitations that haven't been accepted yet")).toBeInTheDocument();
    });

    it('shows pending invitation details', () => {
        render(
            <TeamSettings
                team={mockTeam}
                members={mockMembers}
                invitations={mockInvitations}
                receivedInvitations={[]}
                currentUserRole="owner"
                canManageTeam={true}
                canManageRoles={true}
            />
        );
        expect(screen.getByText('newmember@example.com')).toBeInTheDocument();
        expect(screen.getByText(/Invited as member/)).toBeInTheDocument();
    });

    it('has revoke button for pending invitations', () => {
        render(
            <TeamSettings
                team={mockTeam}
                members={mockMembers}
                invitations={mockInvitations}
                receivedInvitations={[]}
                currentUserRole="owner"
                canManageTeam={true}
                canManageRoles={true}
            />
        );
        expect(screen.getByRole('button', { name: /Revoke/i })).toBeInTheDocument();
    });

    it('displays received invitations section when invitations exist', () => {
        render(
            <TeamSettings
                team={mockTeam}
                members={mockMembers}
                invitations={[]}
                receivedInvitations={mockReceivedInvitations}
                currentUserRole="owner"
                canManageTeam={true}
                canManageRoles={true}
            />
        );
        expect(screen.getByText('Team Invitations')).toBeInTheDocument();
        expect(screen.getByText(/You have been invited to join 1 team/)).toBeInTheDocument();
    });

    it('shows received invitation details', () => {
        render(
            <TeamSettings
                team={mockTeam}
                members={mockMembers}
                invitations={[]}
                receivedInvitations={mockReceivedInvitations}
                currentUserRole="owner"
                canManageTeam={true}
                canManageRoles={true}
            />
        );
        expect(screen.getByText('Another Team')).toBeInTheDocument();
    });

    it('has accept and decline buttons for received invitations', () => {
        render(
            <TeamSettings
                team={mockTeam}
                members={mockMembers}
                invitations={[]}
                receivedInvitations={mockReceivedInvitations}
                currentUserRole="owner"
                canManageTeam={true}
                canManageRoles={true}
            />
        );
        expect(screen.getByRole('button', { name: /Accept/i })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Decline/i })).toBeInTheDocument();
    });

    it('has dropdown menu for non-owner members', async () => {
        render(
            <TeamSettings
                team={mockTeam}
                members={mockMembers}
                invitations={[]}
                receivedInvitations={[]}
                currentUserRole="owner"
                canManageTeam={true}
                canManageRoles={true}
            />
        );

        // Find dropdown trigger buttons for non-owner members
        const dropdownTriggers = screen.getAllByRole('button').filter(btn => {
            const svg = btn.querySelector('svg');
            return svg && btn.getAttribute('aria-haspopup') === 'menu';
        });

        // Should have dropdown menus for non-owner members (Jane and Bob)
        expect(dropdownTriggers.length).toBeGreaterThan(0);
    });

    it('shows joined date for members', () => {
        render(
            <TeamSettings
                team={mockTeam}
                members={mockMembers}
                invitations={[]}
                receivedInvitations={[]}
                currentUserRole="owner"
                canManageTeam={true}
                canManageRoles={true}
            />
        );
        const joinedElements = screen.getAllByText(/Joined/);
        expect(joinedElements.length).toBeGreaterThan(0);
    });

    it('displays activity and permission sets buttons for team owners', () => {
        render(
            <TeamSettings
                team={mockTeam}
                members={mockMembers}
                invitations={[]}
                receivedInvitations={[]}
                currentUserRole="owner"
                canManageTeam={true}
                canManageRoles={true}
            />
        );
        expect(screen.getByRole('button', { name: /Activity/i })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Roles/i })).toBeInTheDocument();
    });

    it('can close invite modal', async () => {
        const user = userEvent.setup();
        render(
            <TeamSettings
                team={mockTeam}
                members={mockMembers}
                invitations={[]}
                receivedInvitations={[]}
                currentUserRole="owner"
                canManageTeam={true}
                canManageRoles={true}
            />
        );

        const inviteButton = screen.getByRole('button', { name: /Invite Member/i });
        await user.click(inviteButton);

        await waitFor(() => {
            expect(screen.getByText('Invite Team Member')).toBeInTheDocument();
        });

        const cancelButton = screen.getByRole('button', { name: /Cancel/i });
        await user.click(cancelButton);

        await waitFor(() => {
            expect(screen.queryByText('Invite Team Member')).not.toBeInTheDocument();
        });
    });

    it('shows online status indicator for recently active members', () => {
        render(
            <TeamSettings
                team={mockTeam}
                members={mockMembers}
                invitations={[]}
                receivedInvitations={[]}
                currentUserRole="owner"
                canManageTeam={true}
                canManageRoles={true}
            />
        );
        // Online indicator is a visual element (green dot)
        const onlineIndicators = document.querySelectorAll('.bg-green-500');
        expect(onlineIndicators.length).toBeGreaterThan(0);
    });

    it('filters members by role', async () => {
        const user = userEvent.setup();
        render(
            <TeamSettings
                team={mockTeam}
                members={mockMembers}
                invitations={[]}
                receivedInvitations={[]}
                currentUserRole="owner"
                canManageTeam={true}
                canManageRoles={true}
            />
        );

        const roleSelect = screen.getByDisplayValue('All Roles');
        await user.selectOptions(roleSelect, 'admin');

        await waitFor(() => {
            expect(screen.getByText('Jane Smith')).toBeInTheDocument();
            expect(screen.queryByText('Bob Developer')).not.toBeInTheDocument();
        });
    });

    it('shows filter count when filtering members', async () => {
        const user = userEvent.setup();
        render(
            <TeamSettings
                team={mockTeam}
                members={mockMembers}
                invitations={[]}
                receivedInvitations={[]}
                currentUserRole="owner"
                canManageTeam={true}
                canManageRoles={true}
            />
        );

        const searchInput = screen.getByPlaceholderText('Search members by name or email...');
        await user.type(searchInput, 'Jane');

        await waitFor(() => {
            expect(screen.getByText(/Showing 1 of 3 members/)).toBeInTheDocument();
        });
    });
});
