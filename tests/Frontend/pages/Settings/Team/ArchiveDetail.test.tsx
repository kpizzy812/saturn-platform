import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '../../../utils/test-utils';
import { router } from '@inertiajs/react';
import ArchiveDetail from '@/pages/Settings/Team/ArchiveDetail';

vi.mock('@/pages/Settings/Index', () => ({
    SettingsLayout: ({ children }: any) => <div>{children}</div>,
}));

vi.mock('@/components/ui/Toast', () => ({
    useToast: () => ({ addToast: vi.fn() }),
    ToastProvider: ({ children }: any) => <>{children}</>,
}));

vi.mock('@/components/ui/ConfirmationModal', () => ({
    useConfirmation: ({ onConfirm }: any) => ({
        open: vi.fn(),
        ConfirmationDialog: () => <div data-testid="confirmation-dialog" />,
    }),
    ConfirmationProvider: ({ children }: any) => <>{children}</>,
}));

describe('ArchiveDetail Page', () => {
    const mockArchive = {
        id: 1,
        uuid: 'uuid-1',
        member_name: 'John Doe',
        member_email: 'john@example.com',
        member_role: 'admin',
        member_joined_at: '2024-01-01',
        kicked_by_name: 'Jane Smith',
        kick_reason: 'Left the company',
        contribution_summary: {
            total_actions: 150,
            deploy_count: 45,
            created_count: 20,
            by_action: {
                deployment_started: 45,
                deployment_completed: 42,
                settings_updated: 15,
            },
            by_resource_type: [
                { type: 'App', full_type: 'Application', count: 50 },
                { type: 'DB', full_type: 'Database', count: 30 },
            ],
            top_resources: [
                { type: 'App', full_type: 'Application', id: 1, name: 'app-1', action_count: 30 },
                { type: 'DB', full_type: 'Database', id: 2, name: 'db-1', action_count: 20 },
            ],
            first_action: '2024-01-15',
            last_action: '2024-06-01',
        },
        access_snapshot: {
            role: 'admin',
            allowed_projects: null,
            permission_set_id: null,
        },
        status: 'completed',
        notes: 'Great team member',
        created_at: '2024-06-01',
    };

    const mockTransfers = [
        {
            id: 1,
            resource_type: 'Application',
            resource_name: 'app-1',
            to_user: 'Jane Smith',
            status: 'completed',
            completed_at: '2024-06-02',
        },
    ];

    const mockTeamMembers = [
        { id: 2, name: 'Jane Smith', email: 'jane@example.com' },
        { id: 3, name: 'Bob Developer', email: 'bob@example.com' },
    ];

    const mockMemberResources = [
        { type: 'App', full_type: 'Application', id: 10, name: 'app-10', action_count: 5 },
        { type: 'DB', full_type: 'Database', id: 20, name: 'db-20', action_count: 3 },
    ];

    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders page header with member name', () => {
        render(
            <ArchiveDetail
                archive={mockArchive}
                transfers={mockTransfers}
                teamMembers={mockTeamMembers}
                memberResources={mockMemberResources}
            />
        );

        expect(screen.getByText('Member Archive')).toBeInTheDocument();
        expect(screen.getByText('Archived data for John Doe')).toBeInTheDocument();
    });

    it('renders back button to archives list', () => {
        render(
            <ArchiveDetail
                archive={mockArchive}
                transfers={mockTransfers}
                teamMembers={mockTeamMembers}
                memberResources={mockMemberResources}
            />
        );

        const backButton = screen.getByRole('link', { name: '' });
        expect(backButton).toHaveAttribute('href', '/settings/team/archives');
    });

    it('displays archive status badge', () => {
        render(
            <ArchiveDetail
                archive={mockArchive}
                transfers={mockTransfers}
                teamMembers={mockTeamMembers}
                memberResources={mockMemberResources}
            />
        );

        const completedBadges = screen.getAllByText('completed');
        expect(completedBadges.length).toBeGreaterThan(0);
    });

    it('renders member profile card with frozen information', () => {
        render(
            <ArchiveDetail
                archive={mockArchive}
                transfers={mockTransfers}
                teamMembers={mockTeamMembers}
                memberResources={mockMemberResources}
            />
        );

        expect(screen.getByText('Member Profile')).toBeInTheDocument();
        expect(screen.getByText('Information captured at time of removal')).toBeInTheDocument();
        expect(screen.getByText('John Doe')).toBeInTheDocument();
        expect(screen.getByText('john@example.com')).toBeInTheDocument();
    });

    it('displays member initials', () => {
        render(
            <ArchiveDetail
                archive={mockArchive}
                transfers={mockTransfers}
                teamMembers={mockTeamMembers}
                memberResources={mockMemberResources}
            />
        );

        expect(screen.getByText('JD')).toBeInTheDocument();
    });

    it('shows kick information with kicked_by and kick_reason', () => {
        render(
            <ArchiveDetail
                archive={mockArchive}
                transfers={mockTransfers}
                teamMembers={mockTeamMembers}
                memberResources={mockMemberResources}
            />
        );

        expect(screen.getByText(/by Jane Smith/)).toBeInTheDocument();
        expect(screen.getByText(/"Left the company"/)).toBeInTheDocument();
    });

    it('renders contributions summary card', () => {
        render(
            <ArchiveDetail
                archive={mockArchive}
                transfers={mockTransfers}
                teamMembers={mockTeamMembers}
                memberResources={mockMemberResources}
            />
        );

        expect(screen.getByText('Contributions')).toBeInTheDocument();
        expect(screen.getByText('Summary of all team activity')).toBeInTheDocument();
    });

    it('displays contribution stat cards', () => {
        render(
            <ArchiveDetail
                archive={mockArchive}
                transfers={mockTransfers}
                teamMembers={mockTeamMembers}
                memberResources={mockMemberResources}
            />
        );

        expect(screen.getByText('Total Actions')).toBeInTheDocument();
        expect(screen.getByText('150')).toBeInTheDocument();
        expect(screen.getByText('Deployments')).toBeInTheDocument();
        expect(screen.getByText('45')).toBeInTheDocument();
        expect(screen.getByText('Created')).toBeInTheDocument();
        expect(screen.getByText('20')).toBeInTheDocument();
    });

    it('shows action breakdown badges', () => {
        render(
            <ArchiveDetail
                archive={mockArchive}
                transfers={mockTransfers}
                teamMembers={mockTeamMembers}
                memberResources={mockMemberResources}
            />
        );

        expect(screen.getByText('deployment_started: 45')).toBeInTheDocument();
        expect(screen.getByText('deployment_completed: 42')).toBeInTheDocument();
        expect(screen.getByText('settings_updated: 15')).toBeInTheDocument();
    });

    it('displays top resources section', () => {
        render(
            <ArchiveDetail
                archive={mockArchive}
                transfers={mockTransfers}
                teamMembers={mockTeamMembers}
                memberResources={mockMemberResources}
            />
        );

        // Check for app-1 and db-1 in top resources
        const resourceNames = screen.getAllByText(/app-1|db-1/);
        expect(resourceNames.length).toBeGreaterThan(0);
        expect(screen.getAllByText(/actions/).length).toBeGreaterThan(0);
    });

    it('shows resource type breakdown', () => {
        render(
            <ArchiveDetail
                archive={mockArchive}
                transfers={mockTransfers}
                teamMembers={mockTeamMembers}
                memberResources={mockMemberResources}
            />
        );

        expect(screen.getByText('App: 50')).toBeInTheDocument();
        expect(screen.getByText('DB: 30')).toBeInTheDocument();
    });

    it('renders notes card with existing notes', () => {
        render(
            <ArchiveDetail
                archive={mockArchive}
                transfers={mockTransfers}
                teamMembers={mockTeamMembers}
                memberResources={mockMemberResources}
            />
        );

        expect(screen.getByText('Notes')).toBeInTheDocument();
        expect(screen.getByText('Great team member')).toBeInTheDocument();
        expect(screen.getByText('Edit')).toBeInTheDocument();
    });

    it('shows Add Note button when no notes exist', () => {
        const archiveWithoutNotes = { ...mockArchive, notes: null };
        render(
            <ArchiveDetail
                archive={archiveWithoutNotes}
                transfers={mockTransfers}
                teamMembers={mockTeamMembers}
                memberResources={mockMemberResources}
            />
        );

        expect(screen.getByText('Add Note')).toBeInTheDocument();
        expect(screen.getByText('No notes yet')).toBeInTheDocument();
    });

    it('enables note editing when Edit button is clicked', async () => {
        const { user } = render(
            <ArchiveDetail
                archive={mockArchive}
                transfers={mockTransfers}
                teamMembers={mockTeamMembers}
                memberResources={mockMemberResources}
            />
        );

        const editButton = screen.getByText('Edit');
        await user.click(editButton);

        await waitFor(() => {
            expect(screen.getByPlaceholderText('Add notes about this member...')).toBeInTheDocument();
            expect(screen.getByText('Save')).toBeInTheDocument();
            expect(screen.getByText('Cancel')).toBeInTheDocument();
        });
    });

    it('saves notes when Save button is clicked', async () => {
        const { user } = render(
            <ArchiveDetail
                archive={mockArchive}
                transfers={mockTransfers}
                teamMembers={mockTeamMembers}
                memberResources={mockMemberResources}
            />
        );

        const editButton = screen.getByText('Edit');
        await user.click(editButton);

        const textarea = screen.getByPlaceholderText('Add notes about this member...');
        await user.clear(textarea);
        await user.type(textarea, 'Updated note');

        const saveButton = screen.getByText('Save');
        await user.click(saveButton);

        expect(router.patch).toHaveBeenCalledWith(
            '/settings/team/archives/1/notes',
            { notes: 'Updated note' },
            expect.any(Object)
        );
    });

    it('renders resource transfers card', () => {
        render(
            <ArchiveDetail
                archive={mockArchive}
                transfers={mockTransfers}
                teamMembers={mockTeamMembers}
                memberResources={mockMemberResources}
            />
        );

        expect(screen.getByText('Resource Transfers')).toBeInTheDocument();
        expect(screen.getByText('Resources attributed to other team members')).toBeInTheDocument();
    });

    it('displays existing transfer entries', () => {
        render(
            <ArchiveDetail
                archive={mockArchive}
                transfers={mockTransfers}
                teamMembers={mockTeamMembers}
                memberResources={mockMemberResources}
            />
        );

        expect(screen.getByText(/Transferred to Jane Smith/)).toBeInTheDocument();
    });

    it('shows "No resource transfers" when transfers list is empty', () => {
        render(
            <ArchiveDetail
                archive={mockArchive}
                transfers={[]}
                teamMembers={mockTeamMembers}
                memberResources={mockMemberResources}
            />
        );

        expect(screen.getByText('No resource transfers')).toBeInTheDocument();
    });

    it('renders transfer resources card when member resources exist', () => {
        render(
            <ArchiveDetail
                archive={mockArchive}
                transfers={mockTransfers}
                teamMembers={mockTeamMembers}
                memberResources={mockMemberResources}
            />
        );

        expect(screen.getByText('Transfer Resources')).toBeInTheDocument();
        expect(screen.getByText(/Reassign remaining resources from John Doe/)).toBeInTheDocument();
    });

    it('displays member resources with select dropdowns', () => {
        render(
            <ArchiveDetail
                archive={mockArchive}
                transfers={mockTransfers}
                teamMembers={mockTeamMembers}
                memberResources={mockMemberResources}
            />
        );

        expect(screen.getByText('app-10')).toBeInTheDocument();
        expect(screen.getByText('db-20')).toBeInTheDocument();

        const selects = screen.getAllByRole('combobox');
        expect(selects.length).toBeGreaterThan(0);
    });

    it('enables Transfer Selected button when resources are selected', async () => {
        const { user } = render(
            <ArchiveDetail
                archive={mockArchive}
                transfers={mockTransfers}
                teamMembers={mockTeamMembers}
                memberResources={mockMemberResources}
            />
        );

        const selects = screen.getAllByRole('combobox');
        if (selects[0]) {
            await user.selectOptions(selects[0], '2'); // Select Jane Smith

            const transferButton = screen.getByText('Transfer Selected');
            expect(transferButton).not.toBeDisabled();
        }
    });

    it('renders access snapshot card when access data exists', () => {
        render(
            <ArchiveDetail
                archive={mockArchive}
                transfers={mockTransfers}
                teamMembers={mockTeamMembers}
                memberResources={mockMemberResources}
            />
        );

        expect(screen.getByText('Access Snapshot')).toBeInTheDocument();
        expect(screen.getByText('Permissions at time of removal')).toBeInTheDocument();
    });

    it('displays access snapshot role and project access', () => {
        render(
            <ArchiveDetail
                archive={mockArchive}
                transfers={mockTransfers}
                teamMembers={mockTeamMembers}
                memberResources={mockMemberResources}
            />
        );

        expect(screen.getByText(/Project Access:/)).toBeInTheDocument();
        expect(screen.getByText(/Full access/)).toBeInTheDocument();
    });

    it('shows limited project access when allowed_projects is an array', () => {
        const archiveWithLimitedAccess = {
            ...mockArchive,
            access_snapshot: {
                role: 'developer',
                allowed_projects: ['proj-1', 'proj-2'],
                permission_set_id: null,
            },
        };

        render(
            <ArchiveDetail
                archive={archiveWithLimitedAccess}
                transfers={mockTransfers}
                teamMembers={mockTeamMembers}
                memberResources={mockMemberResources}
            />
        );

        expect(screen.getByText(/2 projects/)).toBeInTheDocument();
    });

    it('renders action dropdown with export and delete options', async () => {
        const { user } = render(
            <ArchiveDetail
                archive={mockArchive}
                transfers={mockTransfers}
                teamMembers={mockTeamMembers}
                memberResources={mockMemberResources}
            />
        );

        const dropdownButtons = screen.getAllByRole('button');
        const moreVerticalButton = dropdownButtons.find(btn => {
            const svg = btn.querySelector('svg');
            return svg && btn.getAttribute('variant') === 'secondary';
        });

        if (moreVerticalButton) {
            await user.click(moreVerticalButton);

            await waitFor(() => {
                expect(screen.queryByText('Download JSON')).toBeInTheDocument();
                expect(screen.queryByText('Download CSV')).toBeInTheDocument();
                expect(screen.queryByText('Delete Archive')).toBeInTheDocument();
            });
        }
    });
});

describe('ArchiveDetail - No Contributions', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('shows no activity message when contribution_summary is null', () => {
        const archiveNoContributions = {
            id: 1,
            uuid: 'uuid-1',
            member_name: 'John Doe',
            member_email: 'john@example.com',
            member_role: 'viewer',
            member_joined_at: '2024-01-01',
            kicked_by_name: null,
            kick_reason: null,
            contribution_summary: null,
            access_snapshot: null,
            status: 'completed',
            notes: null,
            created_at: '2024-06-01',
        };

        render(
            <ArchiveDetail
                archive={archiveNoContributions}
                transfers={[]}
                teamMembers={[]}
                memberResources={[]}
            />
        );

        expect(screen.getByText('No activity recorded')).toBeInTheDocument();
    });
});
