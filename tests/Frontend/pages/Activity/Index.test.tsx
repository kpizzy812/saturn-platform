import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import ActivityIndex from '@/pages/Activity/Index';
import type { ActivityLog } from '@/types';

describe('Activity/Index', () => {
    const mockActivities: ActivityLog[] = [
        {
            id: 1,
            action: 'deployment_started',
            description: 'started deployment',
            timestamp: '2024-01-15T10:00:00Z',
            user: {
                id: 1,
                name: 'John Doe',
                email: 'john@example.com',
                avatar: null,
            },
            resource: {
                id: 1,
                type: 'application',
                name: 'My App',
            },
        },
        {
            id: 2,
            action: 'team_member_added',
            description: 'added team member',
            timestamp: '2024-01-15T09:00:00Z',
            user: {
                id: 2,
                name: 'Jane Smith',
                email: 'jane@example.com',
                avatar: null,
            },
            resource: {
                id: 1,
                type: 'team',
                name: 'Development Team',
            },
        },
        {
            id: 3,
            action: 'database_created',
            description: 'created database',
            timestamp: '2024-01-15T08:00:00Z',
            user: {
                id: 1,
                name: 'John Doe',
                email: 'john@example.com',
                avatar: null,
            },
            resource: {
                id: 5,
                type: 'database',
                name: 'Production DB',
            },
        },
    ];

    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders page heading and description', () => {
        render(<ActivityIndex activities={[]} />);

        expect(screen.getByRole('heading', { level: 1, name: /activity log/i })).toBeInTheDocument();
        expect(screen.getByText(/view all activities and changes in your workspace/i)).toBeInTheDocument();
    });

    it('renders search input', () => {
        render(<ActivityIndex activities={[]} />);

        expect(screen.getByPlaceholderText(/search activities/i)).toBeInTheDocument();
    });

    it('renders filter buttons', () => {
        render(<ActivityIndex activities={[]} />);

        expect(screen.getByRole('button', { name: /^all$/i })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /deployments/i })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /^team$/i })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /settings/i })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /database/i })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /server/i })).toBeInTheDocument();
    });

    it('displays empty state when no activities', () => {
        render(<ActivityIndex activities={[]} />);

        expect(screen.getByRole('heading', { level: 3, name: /no activities found/i })).toBeInTheDocument();
        expect(screen.getByText(/try adjusting your filters or search query/i)).toBeInTheDocument();
    });

    it('displays activities when provided', () => {
        render(<ActivityIndex activities={mockActivities} />);

        // "John Doe" appears in activity 1 and 3 (same user)
        expect(screen.getAllByText('John Doe').length).toBeGreaterThanOrEqual(1);
        expect(screen.getByText('Jane Smith')).toBeInTheDocument();
        expect(screen.getByText(/started deployment/i)).toBeInTheDocument();
        expect(screen.getByText(/added team member/i)).toBeInTheDocument();
        expect(screen.getByText(/created database/i)).toBeInTheDocument();
    });

    it('displays resource links', () => {
        render(<ActivityIndex activities={mockActivities} />);

        expect(screen.getByRole('link', { name: /my app/i })).toHaveAttribute('href', '/applications/1');
        expect(screen.getByRole('link', { name: /development team/i })).toHaveAttribute('href', '/teams/1');
        expect(screen.getByRole('link', { name: /production db/i })).toHaveAttribute('href', '/databases/5');
    });

    it('filters activities by search query', async () => {
        const { user } = render(<ActivityIndex activities={mockActivities} />);

        const searchInput = screen.getByPlaceholderText(/search activities/i);
        await user.type(searchInput, 'database');

        // Should show database activity
        expect(screen.getByText(/created database/i)).toBeInTheDocument();
        // Should hide other activities
        expect(screen.queryByText(/started deployment/i)).not.toBeInTheDocument();
        expect(screen.queryByText(/added team member/i)).not.toBeInTheDocument();
    });

    it('filters activities by category', async () => {
        const { user } = render(<ActivityIndex activities={mockActivities} />);

        const deploymentButton = screen.getByRole('button', { name: /deployments/i });
        await user.click(deploymentButton);

        // Should show deployment activities
        expect(screen.getByText(/started deployment/i)).toBeInTheDocument();
        // Should hide non-deployment activities
        expect(screen.queryByText(/added team member/i)).not.toBeInTheDocument();
        expect(screen.queryByText(/created database/i)).not.toBeInTheDocument();
    });

    it('filters activities by team category', async () => {
        const { user } = render(<ActivityIndex activities={mockActivities} />);

        const teamButton = screen.getByRole('button', { name: /^team$/i });
        await user.click(teamButton);

        // Should show team activities
        expect(screen.getByText(/added team member/i)).toBeInTheDocument();
        // Should hide non-team activities
        expect(screen.queryByText(/started deployment/i)).not.toBeInTheDocument();
        expect(screen.queryByText(/created database/i)).not.toBeInTheDocument();
    });

    it('shows empty state when filters match nothing', async () => {
        const { user } = render(<ActivityIndex activities={mockActivities} />);

        const searchInput = screen.getByPlaceholderText(/search activities/i);
        await user.type(searchInput, 'nonexistent');

        expect(screen.getByRole('heading', { level: 3, name: /no activities found/i })).toBeInTheDocument();
    });

    it('displays user initials', () => {
        render(<ActivityIndex activities={mockActivities} />);

        // John Doe -> JD (appears in activity 1 and 3)
        expect(screen.getAllByText('JD').length).toBeGreaterThanOrEqual(1);
        // Jane Smith -> JS
        expect(screen.getByText('JS')).toBeInTheDocument();
    });
});
