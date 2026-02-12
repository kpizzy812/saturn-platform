import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '../../utils/test-utils';
import type { ActivityLog } from '@/types';

// Mock the @inertiajs/react module
vi.mock('@inertiajs/react', () => ({
    Head: ({ children, title }: { children?: React.ReactNode; title?: string }) => (
        <title>{title}</title>
    ),
    Link: ({ children, href }: { children: React.ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
    usePage: () => ({
        props: {
            auth: {
                user: { id: 1, name: 'Test User', email: 'test@example.com' },
            },
        },
    }),
}));

// Import after mocks
import ActivityIndex from '@/pages/Activity/Index';

const mockActivities: ActivityLog[] = [
    {
        id: '1',
        action: 'deployment_started',
        description: 'started deployment for',
        user: {
            name: 'John Doe',
            email: 'john@example.com',
        },
        resource: {
            type: 'application',
            name: 'api-server',
            id: 'app-1',
        },
        timestamp: new Date().toISOString(),
    },
    {
        id: '2',
        action: 'database_created',
        description: 'created database',
        user: {
            name: 'Jane Smith',
            email: 'jane@example.com',
        },
        resource: {
            type: 'database',
            name: 'postgres-main',
            id: 'db-1',
        },
        timestamp: new Date(Date.now() - 86400000).toISOString(), // Yesterday
    },
    {
        id: '3',
        action: 'team_member_added',
        description: 'added team member',
        user: {
            name: 'Admin User',
            email: 'admin@example.com',
        },
        resource: {
            type: 'team',
            name: 'Development Team',
            id: 'team-1',
        },
        timestamp: new Date(Date.now() - 172800000).toISOString(), // 2 days ago
    },
    {
        id: '4',
        action: 'settings_updated',
        description: 'updated settings for',
        user: {
            name: 'John Doe',
            email: 'john@example.com',
        },
        resource: {
            type: 'project',
            name: 'Production API',
            id: 'proj-1',
        },
        timestamp: new Date(Date.now() - 3600000).toISOString(), // 1 hour ago
    },
];

describe('Activity Index Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('Page Rendering', () => {
        it('renders the activity log page', () => {
            render(<ActivityIndex activities={mockActivities} />);
            expect(screen.getByText('Activity Log')).toBeInTheDocument();
        });

        it('renders the page description', () => {
            render(<ActivityIndex activities={mockActivities} />);
            expect(screen.getByText('View all activities and changes in your workspace')).toBeInTheDocument();
        });

        it('renders with empty activities array', () => {
            render(<ActivityIndex activities={[]} />);
            expect(screen.getByText('Activity Log')).toBeInTheDocument();
            expect(screen.getByText('No activities found')).toBeInTheDocument();
        });

        it('renders with undefined activities prop', () => {
            render(<ActivityIndex />);
            expect(screen.getByText('Activity Log')).toBeInTheDocument();
            expect(screen.getByText('No activities found')).toBeInTheDocument();
        });
    });

    describe('Filter Functionality', () => {
        it('renders all filter buttons', () => {
            render(<ActivityIndex activities={mockActivities} />);
            expect(screen.getByText('All')).toBeInTheDocument();
            expect(screen.getByText('Deployments')).toBeInTheDocument();
            expect(screen.getByText('Team')).toBeInTheDocument();
            expect(screen.getByText('Settings')).toBeInTheDocument();
            expect(screen.getByText('Database')).toBeInTheDocument();
            expect(screen.getByText('Server')).toBeInTheDocument();
        });

        it('filters activities by deployment type', () => {
            render(<ActivityIndex activities={mockActivities} />);

            // Click on Deployments filter
            const deploymentsButton = screen.getByText('Deployments');
            fireEvent.click(deploymentsButton);

            // Should show deployment activity
            expect(screen.getByText('started deployment for')).toBeInTheDocument();

            // Should not show non-deployment activities
            expect(screen.queryByText('created database')).not.toBeInTheDocument();
        });

        it('filters activities by team type', () => {
            render(<ActivityIndex activities={mockActivities} />);

            // Click on Team filter
            const teamButton = screen.getByText('Team');
            fireEvent.click(teamButton);

            // Should show team activity
            expect(screen.getByText('added team member')).toBeInTheDocument();

            // Should not show non-team activities
            expect(screen.queryByText('started deployment for')).not.toBeInTheDocument();
        });

        it('filters activities by database type', () => {
            render(<ActivityIndex activities={mockActivities} />);

            // Click on Database filter
            const databaseButton = screen.getByText('Database');
            fireEvent.click(databaseButton);

            // Should show database activity
            expect(screen.getByText('created database')).toBeInTheDocument();

            // Should not show non-database activities
            expect(screen.queryByText('started deployment for')).not.toBeInTheDocument();
        });

        it('filters activities by settings type', () => {
            render(<ActivityIndex activities={mockActivities} />);

            // Click on Settings filter
            const settingsButton = screen.getByText('Settings');
            fireEvent.click(settingsButton);

            // Should show settings activity
            expect(screen.getByText('updated settings for')).toBeInTheDocument();

            // Should not show non-settings activities
            expect(screen.queryByText('created database')).not.toBeInTheDocument();
        });

        it('shows all activities when "All" filter is active', () => {
            render(<ActivityIndex activities={mockActivities} />);

            // All activities should be visible by default
            expect(screen.getByText('started deployment for')).toBeInTheDocument();
            expect(screen.getByText('created database')).toBeInTheDocument();
            expect(screen.getByText('added team member')).toBeInTheDocument();
            expect(screen.getByText('updated settings for')).toBeInTheDocument();
        });
    });

    describe('Search Functionality', () => {
        it('renders search input', () => {
            render(<ActivityIndex activities={mockActivities} />);
            const searchInput = screen.getByPlaceholderText('Search activities...');
            expect(searchInput).toBeInTheDocument();
        });

        it('filters activities by search query (description)', () => {
            render(<ActivityIndex activities={mockActivities} />);
            const searchInput = screen.getByPlaceholderText('Search activities...');

            fireEvent.change(searchInput, { target: { value: 'deployment' } });

            // Should show deployment activity
            expect(screen.getByText('started deployment for')).toBeInTheDocument();

            // Should not show non-matching activities
            expect(screen.queryByText('created database')).not.toBeInTheDocument();
        });

        it('filters activities by search query (user name)', () => {
            render(<ActivityIndex activities={mockActivities} />);
            const searchInput = screen.getByPlaceholderText('Search activities...');

            fireEvent.change(searchInput, { target: { value: 'Jane' } });

            // Should show activities by Jane
            expect(screen.getByText('created database')).toBeInTheDocument();

            // Should not show activities by other users
            expect(screen.queryByText('started deployment for')).not.toBeInTheDocument();
        });

        it('filters activities by search query (resource name)', () => {
            render(<ActivityIndex activities={mockActivities} />);
            const searchInput = screen.getByPlaceholderText('Search activities...');

            fireEvent.change(searchInput, { target: { value: 'postgres' } });

            // Should show postgres-related activity
            expect(screen.getByText('created database')).toBeInTheDocument();

            // Should not show unrelated activities
            expect(screen.queryByText('started deployment for')).not.toBeInTheDocument();
        });

        it('combines search and filter', () => {
            render(<ActivityIndex activities={mockActivities} />);
            const searchInput = screen.getByPlaceholderText('Search activities...');

            // Apply filter first
            const databaseButton = screen.getByText('Database');
            fireEvent.click(databaseButton);

            // Then search
            fireEvent.change(searchInput, { target: { value: 'postgres' } });

            // Should show matching activity
            expect(screen.getByText('created database')).toBeInTheDocument();
        });
    });

    describe('Activity Items Display', () => {
        it('displays user names for activities', () => {
            render(<ActivityIndex activities={mockActivities} />);
            expect(screen.getAllByText('John Doe').length).toBeGreaterThan(0);
            expect(screen.getByText('Jane Smith')).toBeInTheDocument();
            expect(screen.getByText('Admin User')).toBeInTheDocument();
        });

        it('displays activity descriptions', () => {
            render(<ActivityIndex activities={mockActivities} />);
            expect(screen.getByText('started deployment for')).toBeInTheDocument();
            expect(screen.getByText('created database')).toBeInTheDocument();
            expect(screen.getByText('added team member')).toBeInTheDocument();
            expect(screen.getByText('updated settings for')).toBeInTheDocument();
        });

        it('displays resource names', () => {
            render(<ActivityIndex activities={mockActivities} />);
            expect(screen.getByText('api-server')).toBeInTheDocument();
            expect(screen.getByText('postgres-main')).toBeInTheDocument();
            expect(screen.getByText('Development Team')).toBeInTheDocument();
            expect(screen.getByText('Production API')).toBeInTheDocument();
        });

        it('renders resource links with correct href', () => {
            render(<ActivityIndex activities={mockActivities} />);
            const links = screen.getAllByRole('link');

            // Find the api-server link
            const apiServerLink = links.find(link =>
                link.textContent?.includes('api-server')
            );
            expect(apiServerLink).toHaveAttribute('href', '/applications/app-1');
        });

        it('displays user initials in avatars', () => {
            const { container } = render(<ActivityIndex activities={mockActivities} />);
            // User initials should be rendered in avatar circles
            expect(screen.getAllByText('JD').length).toBeGreaterThan(0); // John Doe appears twice
            expect(screen.getByText('JS')).toBeInTheDocument(); // Jane Smith
            expect(screen.getByText('AU')).toBeInTheDocument(); // Admin User
        });
    });

    describe('Empty State', () => {
        it('displays empty state when no activities', () => {
            render(<ActivityIndex activities={[]} />);
            expect(screen.getByText('No activities found')).toBeInTheDocument();
            expect(screen.getByText('Try adjusting your filters or search query.')).toBeInTheDocument();
        });

        it('displays empty state when filter returns no results', () => {
            render(<ActivityIndex activities={mockActivities} />);

            // Click on Server filter (no server activities in mock data)
            const serverButton = screen.getByText('Server');
            fireEvent.click(serverButton);

            expect(screen.getByText('No activities found')).toBeInTheDocument();
        });

        it('displays empty state when search returns no results', () => {
            render(<ActivityIndex activities={mockActivities} />);
            const searchInput = screen.getByPlaceholderText('Search activities...');

            fireEvent.change(searchInput, { target: { value: 'nonexistent' } });

            expect(screen.getByText('No activities found')).toBeInTheDocument();
        });
    });

    describe('Timeline Display', () => {
        it('renders timeline line for all but last item', () => {
            const { container } = render(<ActivityIndex activities={mockActivities} />);
            // Timeline lines should be present (vertical connectors)
            const timelineLines = container.querySelectorAll('.absolute.left-8');
            expect(timelineLines.length).toBeGreaterThan(0);
        });

        it('renders activity icons', () => {
            const { container } = render(<ActivityIndex activities={mockActivities} />);
            // Icons should be rendered for each activity
            const icons = container.querySelectorAll('svg');
            expect(icons.length).toBeGreaterThan(0);
        });
    });

    describe('Accessibility', () => {
        it('has proper heading structure', () => {
            render(<ActivityIndex activities={mockActivities} />);
            const heading = screen.getByText('Activity Log');
            expect(heading.tagName).toBe('H1');
        });

        it('search input is accessible', () => {
            render(<ActivityIndex activities={mockActivities} />);
            const searchInput = screen.getByPlaceholderText('Search activities...');
            expect(searchInput).toBeInTheDocument();
        });

        it('filter buttons are accessible', () => {
            render(<ActivityIndex activities={mockActivities} />);
            const allButton = screen.getByText('All');
            expect(allButton.tagName).toBe('BUTTON');
        });
    });

    describe('Responsive Behavior', () => {
        it('renders filters in horizontal layout', () => {
            const { container } = render(<ActivityIndex activities={mockActivities} />);
            const filterContainer = container.querySelector('.flex.gap-2.overflow-x-auto');
            expect(filterContainer).toBeInTheDocument();
        });

        it('renders search and filters in flex layout', () => {
            const { container } = render(<ActivityIndex activities={mockActivities} />);
            const filterRow = container.querySelector('.flex.flex-col.gap-3.sm\\:flex-row');
            expect(filterRow).toBeInTheDocument();
        });
    });
});
