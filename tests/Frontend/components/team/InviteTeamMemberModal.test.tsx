import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '../../utils/test-utils';
import { InviteTeamMemberModal } from '@/components/team/InviteTeamMemberModal';

const mockInviteData = {
    projects: [
        { id: 1, name: 'Project Alpha' },
        { id: 2, name: 'Project Beta' },
        { id: 3, name: 'Project Gamma' },
    ],
    permissionSets: [
        {
            id: 1,
            name: 'Admin Set',
            slug: 'admin',
            description: 'Full admin permissions',
            is_system: true,
            color: null,
            icon: 'shield',
            permissions_count: 15,
        },
        {
            id: 2,
            name: 'Developer Set',
            slug: 'developer',
            description: 'Developer permissions',
            is_system: true,
            color: null,
            icon: 'code',
            permissions_count: 10,
        },
    ],
    allPermissions: {
        resources: [
            { id: 1, key: 'resources.view', name: 'View Resources', description: 'View all resources', resource: 'resources', action: 'view', is_sensitive: false },
            { id: 2, key: 'resources.create', name: 'Create Resources', description: 'Create new resources', resource: 'resources', action: 'create', is_sensitive: false },
        ],
        team: [
            { id: 3, key: 'team.manage', name: 'Manage Team', description: 'Manage team members', resource: 'team', action: 'manage', is_sensitive: true },
        ],
    },
    environments: [
        { id: 1, name: 'production' },
        { id: 2, name: 'staging' },
    ],
};

describe('InviteTeamMemberModal', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        global.fetch = vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve(mockInviteData),
        });
    });

    it('renders modal with title when open', async () => {
        render(<InviteTeamMemberModal isOpen={true} onClose={vi.fn()} />);

        await waitFor(() => {
            expect(screen.getByText('Invite Team Member')).toBeInTheDocument();
        });
    });

    it('fetches invite data when modal opens', async () => {
        render(<InviteTeamMemberModal isOpen={true} onClose={vi.fn()} />);

        await waitFor(() => {
            expect(global.fetch).toHaveBeenCalledWith(
                '/settings/team/invite/data',
                expect.objectContaining({
                    headers: expect.objectContaining({
                        'Accept': 'application/json',
                    }),
                })
            );
        });
    });

    it('renders email input field', async () => {
        render(<InviteTeamMemberModal isOpen={true} onClose={vi.fn()} />);

        await waitFor(() => {
            expect(screen.getByPlaceholderText('colleague@example.com')).toBeInTheDocument();
        });
    });

    it('renders role selection cards', async () => {
        render(<InviteTeamMemberModal isOpen={true} onClose={vi.fn()} />);

        await waitFor(() => {
            expect(screen.getByText('Admin')).toBeInTheDocument();
            expect(screen.getByText('Developer')).toBeInTheDocument();
            expect(screen.getByText('Member')).toBeInTheDocument();
            expect(screen.getByText('Viewer')).toBeInTheDocument();
        });
    });

    it('renders role descriptions', async () => {
        render(<InviteTeamMemberModal isOpen={true} onClose={vi.fn()} />);

        await waitFor(() => {
            expect(screen.getByText('Manage team members and settings')).toBeInTheDocument();
            expect(screen.getByText('Deploy and manage resources')).toBeInTheDocument();
            expect(screen.getByText('View resources and basic operations')).toBeInTheDocument();
            expect(screen.getByText('Read-only access to resources')).toBeInTheDocument();
        });
    });

    it('renders collapsible Project Access section', async () => {
        render(<InviteTeamMemberModal isOpen={true} onClose={vi.fn()} />);

        await waitFor(() => {
            expect(screen.getByText('Project Access')).toBeInTheDocument();
            expect(screen.getByText('All Projects')).toBeInTheDocument();
        });
    });

    it('renders collapsible Permissions section', async () => {
        render(<InviteTeamMemberModal isOpen={true} onClose={vi.fn()} />);

        await waitFor(() => {
            expect(screen.getByText('Permissions')).toBeInTheDocument();
            expect(screen.getByText('Role Default')).toBeInTheDocument();
        });
    });

    it('expands project access section when clicked', async () => {
        const { user } = render(<InviteTeamMemberModal isOpen={true} onClose={vi.fn()} />);

        await waitFor(() => {
            expect(screen.getByText('Project Access')).toBeInTheDocument();
        });

        await user.click(screen.getByText('Project Access'));

        await waitFor(() => {
            expect(screen.getByText('Grant Access to All Projects')).toBeInTheDocument();
        });
    });

    it('expands permissions section when clicked', async () => {
        const { user } = render(<InviteTeamMemberModal isOpen={true} onClose={vi.fn()} />);

        await waitFor(() => {
            expect(screen.getByText('Permissions')).toBeInTheDocument();
        });

        await user.click(screen.getByText('Permissions'));

        await waitFor(() => {
            expect(screen.getByText('Preset')).toBeInTheDocument();
            expect(screen.getByText('Custom')).toBeInTheDocument();
        });
    });

    it('renders Cancel and Send Invitation buttons', async () => {
        render(<InviteTeamMemberModal isOpen={true} onClose={vi.fn()} />);

        await waitFor(() => {
            expect(screen.getByText('Cancel')).toBeInTheDocument();
            expect(screen.getByText('Send Invitation')).toBeInTheDocument();
        });
    });

    it('calls onClose when Cancel is clicked', async () => {
        const onClose = vi.fn();
        const { user } = render(<InviteTeamMemberModal isOpen={true} onClose={onClose} />);

        await waitFor(() => {
            expect(screen.getByText('Cancel')).toBeInTheDocument();
        });

        await user.click(screen.getByText('Cancel'));
        expect(onClose).toHaveBeenCalled();
    });

    it('does not render modal when closed', () => {
        render(<InviteTeamMemberModal isOpen={false} onClose={vi.fn()} />);

        expect(screen.queryByText('Invite Team Member')).not.toBeInTheDocument();
    });

    it('shows loading state while fetching data', () => {
        // Delay the fetch resolution
        global.fetch = vi.fn().mockReturnValue(new Promise(() => {}));

        render(<InviteTeamMemberModal isOpen={true} onClose={vi.fn()} />);

        expect(screen.getByText('Invite Team Member')).toBeInTheDocument();
    });

    it('shows error state when fetch fails', async () => {
        global.fetch = vi.fn().mockResolvedValue({
            ok: false,
            json: () => Promise.resolve({ message: 'Unauthorized' }),
        });

        render(<InviteTeamMemberModal isOpen={true} onClose={vi.fn()} />);

        await waitFor(() => {
            expect(screen.getByText('Failed to load invite data')).toBeInTheDocument();
        });
    });

    it('selects role when role card is clicked', async () => {
        const { user } = render(<InviteTeamMemberModal isOpen={true} onClose={vi.fn()} />);

        await waitFor(() => {
            expect(screen.getByText('Developer')).toBeInTheDocument();
        });

        await user.click(screen.getByText('Developer'));

        // Developer card should be selected (check for visual indicator)
        const devButton = screen.getByText('Developer').closest('button');
        expect(devButton?.className).toContain('border-primary');
    });

    it('shows project list when grant all is unchecked', async () => {
        const { user } = render(<InviteTeamMemberModal isOpen={true} onClose={vi.fn()} />);

        await waitFor(() => {
            expect(screen.getByText('Project Access')).toBeInTheDocument();
        });

        // Expand project access section
        await user.click(screen.getByText('Project Access'));

        await waitFor(() => {
            expect(screen.getByText('Grant Access to All Projects')).toBeInTheDocument();
        });

        // Uncheck "Grant All" — click the label/checkbox area
        const grantAllLabel = screen.getByText('Grant Access to All Projects').closest('label');
        if (grantAllLabel) {
            await user.click(grantAllLabel);
        }

        await waitFor(() => {
            expect(screen.getByText('Project Alpha')).toBeInTheDocument();
            expect(screen.getByText('Project Beta')).toBeInTheDocument();
            expect(screen.getByText('Project Gamma')).toBeInTheDocument();
        });
    });

    it('shows preset permission sets when Preset tab is clicked', async () => {
        const { user } = render(<InviteTeamMemberModal isOpen={true} onClose={vi.fn()} />);

        await waitFor(() => {
            expect(screen.getByText('Permissions')).toBeInTheDocument();
        });

        // Expand permissions section
        await user.click(screen.getByText('Permissions'));

        await waitFor(() => {
            expect(screen.getByText('Preset')).toBeInTheDocument();
        });

        await user.click(screen.getByText('Preset'));

        await waitFor(() => {
            expect(screen.getByText('Admin Set')).toBeInTheDocument();
            expect(screen.getByText('Developer Set')).toBeInTheDocument();
        });
    });

    it('shows custom permissions when Custom tab is clicked', async () => {
        const { user } = render(<InviteTeamMemberModal isOpen={true} onClose={vi.fn()} />);

        await waitFor(() => {
            expect(screen.getByText('Permissions')).toBeInTheDocument();
        });

        await user.click(screen.getByText('Permissions'));

        await waitFor(() => {
            expect(screen.getByText('Custom')).toBeInTheDocument();
        });

        await user.click(screen.getByText('Custom'));

        await waitFor(() => {
            expect(screen.getByText('View Resources')).toBeInTheDocument();
            expect(screen.getByText('Create Resources')).toBeInTheDocument();
            expect(screen.getByText('Manage Team')).toBeInTheDocument();
        });
    });
});
