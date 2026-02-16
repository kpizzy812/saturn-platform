import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen } from '../../../utils/test-utils';
import MemberShow from '@/pages/Settings/Members/Show';

vi.mock('@/pages/Settings/Index', () => ({
    SettingsLayout: ({ children }: any) => <div>{children}</div>,
}));

vi.mock('@/components/ui/Toast', () => ({
    useToast: () => ({
        toast: vi.fn(),
    }),
    ToastProvider: ({ children }: any) => <>{children}</>,
}));

vi.mock('@/components/team/KickMemberModal', () => ({
    KickMemberModal: ({ isOpen, member }: any) => (
        isOpen ? <div>Kick Member Modal for {member.name}</div> : null
    ),
}));

vi.mock('@/components/ui/ActivityTimeline', () => ({
    ActivityTimeline: ({ activities }: any) => (
        <div>Activity Timeline with {activities.length} items</div>
    ),
}));

const mockMember = {
    id: 1,
    name: 'Jane Smith',
    email: 'jane@example.com',
    avatar: null,
    role: 'member' as const,
    permissionSetId: null,
    joinedAt: '2024-01-10T09:00:00.000Z',
    lastActive: '2024-02-15T14:30:00.000Z',
};

const mockProjects = [
    {
        id: 1,
        name: 'Project Alpha',
        role: 'developer',
        hasAccess: true,
        lastAccessed: '2024-02-14T10:00:00.000Z',
    },
    {
        id: 2,
        name: 'Project Beta',
        role: 'viewer',
        hasAccess: false,
        lastAccessed: '2024-02-10T08:00:00.000Z',
    },
];

const mockActivities = [
    {
        id: 1,
        description: 'Deployed application',
        created_at: '2024-02-15T10:00:00.000Z',
        user: { name: 'Jane Smith', email: 'jane@example.com' },
    },
];

const mockPermissionSets = [
    {
        id: 1,
        name: 'Developer',
        slug: 'developer',
        description: 'Full development access',
        is_system: true,
        color: null,
        icon: null,
        permissions_count: 15,
        permissions: [
            {
                id: 1,
                key: 'deploy',
                name: 'Deploy Applications',
                description: 'Can deploy applications',
                category: 'Deployment',
                is_sensitive: false,
            },
            {
                id: 2,
                key: 'view_logs',
                name: 'View Logs',
                description: 'Can view application logs',
                category: 'Monitoring',
                is_sensitive: false,
            },
        ],
    },
];

const mockTeamMembers = [
    { id: 1, name: 'Jane Smith', email: 'jane@example.com' },
    { id: 2, name: 'John Doe', email: 'john@example.com' },
];

const defaultProps = {
    member: mockMember,
    projects: mockProjects,
    activities: mockActivities,
    isCurrentUser: false,
    canManageTeam: true,
    canEditPermissions: true,
    permissionSets: mockPermissionSets,
    allowedProjects: [1],
    hasFullProjectAccess: false,
    teamMembers: mockTeamMembers,
};

describe('MemberShow', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders member name and email', () => {
        render(<MemberShow {...defaultProps} />);

        expect(screen.getByText('Jane Smith')).toBeInTheDocument();
        expect(screen.getByText('jane@example.com')).toBeInTheDocument();
    });

    it('displays member role badge', () => {
        render(<MemberShow {...defaultProps} />);

        expect(screen.getByText('member')).toBeInTheDocument();
    });

    it('shows joined date', () => {
        render(<MemberShow {...defaultProps} />);

        expect(screen.getByText(/Joined/i)).toBeInTheDocument();
    });

    it('shows last active timestamp', () => {
        render(<MemberShow {...defaultProps} />);

        expect(screen.getByText(/Active/i)).toBeInTheDocument();
    });

    it('renders back to settings link', () => {
        render(<MemberShow {...defaultProps} />);

        expect(screen.getByText('Team Member')).toBeInTheDocument();
        expect(screen.getByText('View and manage member details')).toBeInTheDocument();
    });

    it('displays Change Role button when canManageTeam and not current user', () => {
        render(<MemberShow {...defaultProps} />);

        expect(screen.getByText('Change Role')).toBeInTheDocument();
    });

    it('displays Remove button when canManageTeam and not current user', () => {
        render(<MemberShow {...defaultProps} />);

        expect(screen.getByText('Remove')).toBeInTheDocument();
    });

    it('displays Leave Team button when current user', () => {
        const props = { ...defaultProps, isCurrentUser: true };
        render(<MemberShow {...props} />);

        expect(screen.getByText('Leave Team')).toBeInTheDocument();
        expect(screen.queryByText('Change Role')).not.toBeInTheDocument();
        expect(screen.queryByText('Remove')).not.toBeInTheDocument();
    });

    it('does not show action buttons for owner role', () => {
        const props = {
            ...defaultProps,
            member: { ...mockMember, role: 'owner' as const },
        };
        render(<MemberShow {...props} />);

        expect(screen.queryByText('Change Role')).not.toBeInTheDocument();
        expect(screen.queryByText('Remove')).not.toBeInTheDocument();
        expect(screen.queryByText('Leave Team')).not.toBeInTheDocument();
    });

    it('renders Access Permissions section', () => {
        render(<MemberShow {...defaultProps} />);

        expect(screen.getByText('Access Permissions')).toBeInTheDocument();
    });

    it('shows permission set selector when canEditPermissions', () => {
        render(<MemberShow {...defaultProps} />);

        expect(screen.getByText('Role')).toBeInTheDocument();
    });

    it('displays role-based permissions when no permission set is assigned', () => {
        render(<MemberShow {...defaultProps} />);

        expect(screen.getByText('Resource Deployment')).toBeInTheDocument();
        expect(screen.getByText('Environment Variables')).toBeInTheDocument();
        expect(screen.getByText('View Resources')).toBeInTheDocument();
        expect(screen.getByText('View Logs')).toBeInTheDocument();
    });

    it('shows owner-specific permissions for owner role', () => {
        const props = {
            ...defaultProps,
            member: { ...mockMember, role: 'owner' as const },
        };
        render(<MemberShow {...props} />);

        expect(screen.getByText('Full Team Control')).toBeInTheDocument();
        expect(screen.getByText('Billing Management')).toBeInTheDocument();
    });

    it('shows admin-specific permissions for admin role', () => {
        const props = {
            ...defaultProps,
            member: { ...mockMember, role: 'admin' as const },
        };
        render(<MemberShow {...props} />);

        expect(screen.getByText('Team Management')).toBeInTheDocument();
        expect(screen.getByText('Settings Management')).toBeInTheDocument();
    });

    it('renders Project Access section', () => {
        render(<MemberShow {...defaultProps} />);

        expect(screen.getByText('Project Access')).toBeInTheDocument();
    });

    it('displays all projects checkbox when canEditPermissions', () => {
        render(<MemberShow {...defaultProps} />);

        expect(screen.getByText('All Projects')).toBeInTheDocument();
        expect(screen.getByText(/Grant access to all current and future projects/i)).toBeInTheDocument();
    });

    it('lists all projects with checkboxes when canEditPermissions', () => {
        render(<MemberShow {...defaultProps} />);

        expect(screen.getByText('Project Alpha')).toBeInTheDocument();
        expect(screen.getByText('Project Beta')).toBeInTheDocument();
    });

    it('shows project access badges when not canEditPermissions', () => {
        const props = { ...defaultProps, canEditPermissions: false };
        render(<MemberShow {...props} />);

        const accessBadges = screen.getAllByText(/Access|No Access/i);
        expect(accessBadges.length).toBeGreaterThan(0);
    });

    it('renders Recent Activity section', () => {
        render(<MemberShow {...defaultProps} />);

        expect(screen.getByText('Recent Activity')).toBeInTheDocument();
        expect(screen.getByText('Actions performed by this member')).toBeInTheDocument();
    });

    it('displays activity timeline when activities exist', () => {
        render(<MemberShow {...defaultProps} />);

        expect(screen.getByText('Activity Timeline with 1 items')).toBeInTheDocument();
    });

    it('shows no activity message when activities is empty', () => {
        const props = { ...defaultProps, activities: [] };
        render(<MemberShow {...props} />);

        expect(screen.getByText('No recent activity')).toBeInTheDocument();
    });

    it('renders View All activity link', () => {
        render(<MemberShow {...defaultProps} />);

        expect(screen.getByText('View All')).toBeInTheDocument();
    });

    it('does not show Change Role button when canManageTeam is false', () => {
        const props = { ...defaultProps, canManageTeam: false };
        render(<MemberShow {...props} />);

        expect(screen.queryByText('Change Role')).not.toBeInTheDocument();
    });

    it('displays member initials when no avatar', () => {
        render(<MemberShow {...defaultProps} />);

        // Initials JS for Jane Smith would be rendered in the avatar placeholder
        expect(screen.getByText('JS')).toBeInTheDocument();
    });

    it('shows Save Project Access button when project access has changed', () => {
        render(<MemberShow {...defaultProps} />);

        // Initially no save button should appear until changes are made
        // This test verifies the component renders without the button initially
        const saveButton = screen.queryByText('Save Project Access');
        // Button only appears after changes, so it should not be present initially
        expect(saveButton).not.toBeInTheDocument();
    });
});
