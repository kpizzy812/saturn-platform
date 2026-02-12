import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '../../utils/test-utils';
import { KickMemberModal } from '@/components/team/KickMemberModal';

const mockMember = {
    id: 42,
    name: 'John Doe',
    email: 'john@example.com',
    role: 'member',
};

const mockTeamMembers = [
    { id: 1, name: 'Alice', email: 'alice@example.com' },
    { id: 2, name: 'Bob', email: 'bob@example.com' },
];

const mockContributions = {
    contributions: {
        total_actions: 150,
        deploy_count: 25,
        created_count: 40,
        by_action: { create: 40, update: 60, deploy: 25, delete: 25 },
        by_resource_type: [
            { type: 'Application', full_type: 'App\\Models\\Application', count: 80 },
            { type: 'Server', full_type: 'App\\Models\\Server', count: 70 },
        ],
        top_resources: [
            {
                type: 'Application',
                full_type: 'App\\Models\\Application',
                id: 1,
                name: 'My App',
                action_count: 50,
            },
        ],
        first_action: '2025-01-15T00:00:00.000Z',
        last_action: '2026-02-10T00:00:00.000Z',
        recent_activities: [
            {
                id: 1,
                action: 'deploy',
                formatted_action: 'Deployed',
                resource_type: 'Application',
                resource_name: 'My App',
                description: null,
                created_at: '2026-02-10T00:00:00.000Z',
            },
        ],
    },
    teamMembers: mockTeamMembers,
};

describe('KickMemberModal', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        // Mock global fetch
        global.fetch = vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve(mockContributions),
        });
    });

    it('renders modal with step indicator when open', () => {
        render(
            <KickMemberModal
                isOpen={true}
                onClose={vi.fn()}
                member={mockMember}
                teamMembers={mockTeamMembers}
            />,
        );

        expect(screen.getByText('Remove Team Member')).toBeInTheDocument();
        expect(screen.getByText('Contributions')).toBeInTheDocument();
        expect(screen.getByText('Options')).toBeInTheDocument();
        expect(screen.getByText('Confirm')).toBeInTheDocument();
        expect(screen.getByText('Done')).toBeInTheDocument();
    });

    it('shows loading state initially', () => {
        render(
            <KickMemberModal
                isOpen={true}
                onClose={vi.fn()}
                member={mockMember}
                teamMembers={mockTeamMembers}
            />,
        );

        expect(screen.getByText('Loading contributions...')).toBeInTheDocument();
    });

    it('displays contributions after loading', async () => {
        render(
            <KickMemberModal
                isOpen={true}
                onClose={vi.fn()}
                member={mockMember}
                teamMembers={mockTeamMembers}
            />,
        );

        await waitFor(() => {
            expect(screen.getByText('150')).toBeInTheDocument();
        });

        expect(screen.getByText('Total Actions')).toBeInTheDocument();
        expect(screen.getByText('25')).toBeInTheDocument();
        expect(screen.getByText('Deployments')).toBeInTheDocument();
    });

    it('fetches contributions with correct URL', async () => {
        render(
            <KickMemberModal
                isOpen={true}
                onClose={vi.fn()}
                member={mockMember}
                teamMembers={mockTeamMembers}
            />,
        );

        await waitFor(() => {
            expect(global.fetch).toHaveBeenCalledWith(
                '/settings/team/members/42/contributions',
                expect.objectContaining({
                    headers: expect.objectContaining({
                        Accept: 'application/json',
                    }),
                }),
            );
        });
    });

    it('shows top resources in step 1', async () => {
        render(
            <KickMemberModal
                isOpen={true}
                onClose={vi.fn()}
                member={mockMember}
                teamMembers={mockTeamMembers}
            />,
        );

        // Wait for contributions to load
        await waitFor(() => {
            expect(screen.getByText('150')).toBeInTheDocument();
        });

        // Top resources and recent activities both show 'My App'
        await waitFor(() => {
            expect(screen.getAllByText('My App').length).toBeGreaterThanOrEqual(1);
        });

        expect(screen.getByText('Top Resources')).toBeInTheDocument();
        expect(screen.getByText('50 actions')).toBeInTheDocument();
    });

    it('advances to step 2 when Next is clicked', async () => {
        const { user } = render(
            <KickMemberModal
                isOpen={true}
                onClose={vi.fn()}
                member={mockMember}
                teamMembers={mockTeamMembers}
            />,
        );

        // Wait for loading to finish
        await waitFor(() => {
            expect(screen.getByText('150')).toBeInTheDocument();
        });

        // Click Next
        const nextButton = screen.getByRole('button', { name: /next/i });
        await user.click(nextButton);

        // Should now show step 2 content
        expect(screen.getByText('Reason for removal (optional)')).toBeInTheDocument();
    });

    it('shows error when fetch fails', async () => {
        global.fetch = vi.fn().mockResolvedValue({
            ok: false,
            json: () => Promise.resolve({}),
        });

        render(
            <KickMemberModal
                isOpen={true}
                onClose={vi.fn()}
                member={mockMember}
                teamMembers={mockTeamMembers}
            />,
        );

        await waitFor(() => {
            expect(screen.getByText('Failed to load contributions')).toBeInTheDocument();
        });
    });

    it('does not render content when closed', () => {
        render(
            <KickMemberModal
                isOpen={false}
                onClose={vi.fn()}
                member={mockMember}
                teamMembers={mockTeamMembers}
            />,
        );

        expect(screen.queryByText('Remove Team Member')).not.toBeInTheDocument();
    });
});
