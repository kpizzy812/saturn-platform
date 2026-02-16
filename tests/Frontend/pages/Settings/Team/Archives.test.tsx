import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '../../../utils/test-utils';
import { router } from '@inertiajs/react';
import Archives from '@/pages/Settings/Team/Archives';

vi.mock('@/pages/Settings/Index', () => ({
    SettingsLayout: ({ children }: any) => <div>{children}</div>,
}));

describe('Archives Page', () => {
    const mockArchives = [
        {
            id: 1,
            uuid: 'uuid-1',
            member_name: 'John Doe',
            member_email: 'john@example.com',
            member_role: 'admin',
            member_joined_at: '2024-01-01',
            kicked_by_name: 'Jane Smith',
            kick_reason: 'Left the company',
            total_actions: 150,
            deploy_count: 45,
            status: 'completed',
            created_at: '2024-06-01',
            deleted_at: null,
        },
        {
            id: 2,
            uuid: 'uuid-2',
            member_name: 'Bob Developer',
            member_email: 'bob@example.com',
            member_role: 'developer',
            member_joined_at: '2024-02-15',
            kicked_by_name: null,
            kick_reason: null,
            total_actions: 80,
            deploy_count: 25,
            status: 'completed',
            created_at: '2024-07-01',
            deleted_at: null,
        },
        {
            id: 3,
            uuid: 'uuid-3',
            member_name: 'Alice Viewer',
            member_email: 'alice@example.com',
            member_role: 'viewer',
            member_joined_at: null,
            kicked_by_name: 'John Admin',
            kick_reason: 'Contract ended',
            total_actions: 10,
            deploy_count: 0,
            status: 'processing',
            created_at: '2024-08-01',
            deleted_at: '2024-08-15',
        },
    ];

    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders page header with title and description', () => {
        render(<Archives archives={mockArchives} showDeleted={false} />);

        expect(screen.getByText('Member Archives')).toBeInTheDocument();
        expect(screen.getByText('History of removed team members and their contributions')).toBeInTheDocument();
    });

    it('renders back button to return to team index', () => {
        render(<Archives archives={mockArchives} showDeleted={false} />);

        const backButton = screen.getByRole('link', { name: '' });
        expect(backButton).toHaveAttribute('href', '/settings/team/index');
    });

    it('renders Show Deleted button when not showing deleted', () => {
        render(<Archives archives={mockArchives} showDeleted={false} />);

        expect(screen.getByText('Show Deleted')).toBeInTheDocument();
    });

    it('renders Hide Deleted button when showing deleted', () => {
        render(<Archives archives={mockArchives} showDeleted={true} />);

        expect(screen.getByText('Hide Deleted')).toBeInTheDocument();
    });

    it('renders Export All button when archives exist', () => {
        render(<Archives archives={mockArchives} showDeleted={false} />);

        expect(screen.getByText('Export All')).toBeInTheDocument();
    });

    it('displays archived members card with count', () => {
        render(<Archives archives={mockArchives} showDeleted={false} />);

        expect(screen.getByText('Archived Members')).toBeInTheDocument();
        expect(screen.getByText('3 members archived')).toBeInTheDocument();
    });

    it('shows deleted count when showDeleted is true', () => {
        render(<Archives archives={mockArchives} showDeleted={true} />);

        expect(screen.getByText('(1 deleted)')).toBeInTheDocument();
    });

    it('displays all archive entries with member names and emails', () => {
        render(<Archives archives={mockArchives} showDeleted={false} />);

        expect(screen.getByText('John Doe')).toBeInTheDocument();
        expect(screen.getByText('john@example.com')).toBeInTheDocument();
        expect(screen.getByText('Bob Developer')).toBeInTheDocument();
        expect(screen.getByText('bob@example.com')).toBeInTheDocument();
    });

    it('displays role badges for each archived member', () => {
        render(<Archives archives={mockArchives} showDeleted={false} />);

        expect(screen.getByText('admin')).toBeInTheDocument();
        expect(screen.getByText('developer')).toBeInTheDocument();
    });

    it('displays status badges for archives', () => {
        render(<Archives archives={mockArchives} showDeleted={false} />);

        expect(screen.getAllByText('completed').length).toBeGreaterThan(0);
    });

    it('shows member initials for archived members', () => {
        render(<Archives archives={mockArchives} showDeleted={false} />);

        expect(screen.getByText('JD')).toBeInTheDocument();
        expect(screen.getByText('BD')).toBeInTheDocument();
    });

    it('displays action and deploy counts', () => {
        render(<Archives archives={mockArchives} showDeleted={false} />);

        expect(screen.getByText('150 actions')).toBeInTheDocument();
        expect(screen.getByText('45 deploys')).toBeInTheDocument();
        expect(screen.getByText('80 actions')).toBeInTheDocument();
        expect(screen.getByText('25 deploys')).toBeInTheDocument();
    });

    it('displays kicked_by information when available', () => {
        render(<Archives archives={mockArchives} showDeleted={false} />);

        expect(screen.getByText(/By Jane Smith/)).toBeInTheDocument();
    });

    it('displays kick_reason when available', () => {
        render(<Archives archives={mockArchives} showDeleted={false} />);

        expect(screen.getByText(/Left the company/)).toBeInTheDocument();
    });

    it('renders Details button for non-deleted archives', () => {
        render(<Archives archives={mockArchives} showDeleted={false} />);

        const detailsButtons = screen.getAllByText('Details');
        expect(detailsButtons.length).toBe(2); // Only for active archives
    });

    it('renders Restore button for deleted archives when showDeleted is true', () => {
        render(<Archives archives={mockArchives} showDeleted={true} />);

        expect(screen.getByText('Restore')).toBeInTheDocument();
    });

    it('renders checkboxes for active archives only', () => {
        render(<Archives archives={mockArchives} showDeleted={false} />);

        const checkboxes = screen.getAllByRole('checkbox');
        expect(checkboxes.length).toBe(2); // Only 2 active archives
    });

    it('shows Select All button when there are multiple active archives', () => {
        render(<Archives archives={mockArchives} showDeleted={false} />);

        expect(screen.getByText('Select All')).toBeInTheDocument();
    });

    it('toggles select all functionality', async () => {
        const { user } = render(<Archives archives={mockArchives} showDeleted={false} />);

        const selectAllButton = screen.getByText('Select All');
        await user.click(selectAllButton);

        await waitFor(() => {
            expect(screen.getByText('Deselect All')).toBeInTheDocument();
        });

        await user.click(screen.getByText('Deselect All'));

        await waitFor(() => {
            expect(screen.getByText('Select All')).toBeInTheDocument();
        });
    });

    it('shows bulk action bar when archives are selected', async () => {
        const { user } = render(<Archives archives={mockArchives} showDeleted={false} />);

        const checkboxes = screen.getAllByRole('checkbox');
        await user.click(checkboxes[0]);

        await waitFor(() => {
            expect(screen.getByText('1 selected')).toBeInTheDocument();
            expect(screen.getByText('Export JSON')).toBeInTheDocument();
            expect(screen.getByText('Export CSV')).toBeInTheDocument();
        });
    });

    it('clears selection when Clear button is clicked', async () => {
        const { user } = render(<Archives archives={mockArchives} showDeleted={false} />);

        const checkboxes = screen.getAllByRole('checkbox');
        await user.click(checkboxes[0]);

        await waitFor(() => {
            expect(screen.getByText('1 selected')).toBeInTheDocument();
        });

        const clearButton = screen.getByText('Clear');
        await user.click(clearButton);

        await waitFor(() => {
            expect(screen.queryByText('1 selected')).not.toBeInTheDocument();
        });
    });

    it('calls router.post when restore button is clicked', async () => {
        const { user } = render(<Archives archives={mockArchives} showDeleted={true} />);

        const restoreButton = screen.getByText('Restore');
        await user.click(restoreButton);

        expect(router.post).toHaveBeenCalledWith('/settings/team/archives/3/restore');
    });

    it('calls router.get when toggle deleted button is clicked', async () => {
        const { user } = render(<Archives archives={mockArchives} showDeleted={false} />);

        const showDeletedButton = screen.getByText('Show Deleted');
        await user.click(showDeletedButton);

        expect(router.get).toHaveBeenCalledWith(
            '/settings/team/archives',
            { show_deleted: '1' },
            { preserveState: true }
        );
    });
});

describe('Archives - Empty State', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('shows empty state when no archives exist', () => {
        render(<Archives archives={[]} showDeleted={false} />);

        expect(screen.getByText('No archives yet')).toBeInTheDocument();
        expect(screen.getByText('When team members are removed, their contribution archives will appear here.')).toBeInTheDocument();
    });

    it('does not render Export All button when no archives exist', () => {
        render(<Archives archives={[]} showDeleted={false} />);

        expect(screen.queryByText('Export All')).not.toBeInTheDocument();
    });
});
