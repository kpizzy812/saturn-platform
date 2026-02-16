import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import ActivityTimelinePage from './Timeline';

// Mock Inertia
vi.mock('@inertiajs/react', () => ({
    Link: ({ children, href, ...props }: any) => (
        <a href={href} {...props}>
            {children}
        </a>
    ),
}));

// Mock AppLayout
vi.mock('@/components/layout', () => ({
    AppLayout: ({ children, title }: any) => (
        <div data-testid="app-layout" data-title={title}>
            {children}
        </div>
    ),
}));

// Mock UI components
vi.mock('@/components/ui', () => ({
    Card: ({ children, className }: any) => <div className={className}>{children}</div>,
    CardContent: ({ children, className }: any) => <div className={className}>{children}</div>,
    Badge: ({ children, variant }: any) => <span data-variant={variant}>{children}</span>,
    Button: ({ children, onClick, disabled }: any) => (
        <button onClick={onClick} disabled={disabled}>
            {children}
        </button>
    ),
    Input: ({ value, onChange, placeholder, className }: any) => (
        <input value={value} onChange={onChange} placeholder={placeholder} className={className} />
    ),
    ActivityTimeline: ({ activities }: any) => (
        <div data-testid="activity-timeline">
            {activities.map((activity: any) => (
                <div key={activity.id}>{activity.description}</div>
            ))}
        </div>
    ),
}));

// Mock utils
vi.mock('@/lib/utils', () => ({
    formatRelativeTime: (_date: string) => '2 hours ago',
}));

describe('ActivityTimelinePage', () => {
    it('renders the activity timeline page', () => {
        render(<ActivityTimelinePage />);
        expect(screen.getByText('Activity Timeline')).toBeInTheDocument();
        expect(
            screen.getByText('View all activities and changes across your workspace')
        ).toBeInTheDocument();
    });

    it('displays activity statistics', () => {
        render(<ActivityTimelinePage />);

        expect(screen.getByText('Total Events')).toBeInTheDocument();
        expect(screen.getByText('Last 24 Hours')).toBeInTheDocument();
        expect(screen.getByText('Deployments')).toBeInTheDocument();
        expect(screen.getByText('Failed')).toBeInTheDocument();
    });

    it('displays search input', () => {
        render(<ActivityTimelinePage />);
        expect(
            screen.getByPlaceholderText('Search activities by action, user, or resource...')
        ).toBeInTheDocument();
    });

    it('displays event type filter buttons', () => {
        render(<ActivityTimelinePage />);

        expect(screen.getByText('All Events')).toBeInTheDocument();
        expect(screen.getByText('Deployments')).toBeInTheDocument();
        expect(screen.getByText('Scaling')).toBeInTheDocument();
        expect(screen.getByText('Settings')).toBeInTheDocument();
        expect(screen.getByText('Team')).toBeInTheDocument();
        expect(screen.getByText('Database')).toBeInTheDocument();
        expect(screen.getByText('Server')).toBeInTheDocument();
    });

    it('displays time range filter buttons', () => {
        render(<ActivityTimelinePage />);

        expect(screen.getByText('All Time')).toBeInTheDocument();
        expect(screen.getByText('Today')).toBeInTheDocument();
        expect(screen.getByText('Last 7 Days')).toBeInTheDocument();
        expect(screen.getByText('Last 30 Days')).toBeInTheDocument();
    });

    it('filters activities by event type', async () => {
        render(<ActivityTimelinePage />);

        // Click on Deployments filter
        const deploymentsButton = screen.getByText('Deployments');
        fireEvent.click(deploymentsButton);

        await waitFor(() => {
            // Should show deployment activities
            expect(screen.getByText('started deployment')).toBeInTheDocument();
            expect(screen.getByText('completed deployment')).toBeInTheDocument();
        });
    });

    it('filters activities by time range', async () => {
        render(<ActivityTimelinePage />);

        // Click on Today filter
        const todayButton = screen.getByText('Today');
        fireEvent.click(todayButton);

        await waitFor(() => {
            // Activities should be filtered
            expect(screen.getByText('Activity Timeline')).toBeInTheDocument();
        });
    });

    it('filters activities by search query', async () => {
        render(<ActivityTimelinePage />);

        const searchInput = screen.getByPlaceholderText(
            'Search activities by action, user, or resource...'
        );

        fireEvent.change(searchInput, { target: { value: 'deployment' } });

        await waitFor(() => {
            expect(screen.getByText('started deployment')).toBeInTheDocument();
        });
    });

    it('displays activity timeline items with user information', () => {
        render(<ActivityTimelinePage />);

        expect(screen.getByText('John Doe')).toBeInTheDocument();
        expect(screen.getByText('Jane Smith')).toBeInTheDocument();
    });

    it('displays resource links in activity items', () => {
        render(<ActivityTimelinePage />);

        expect(screen.getByText('production-api')).toBeInTheDocument();
        expect(screen.getByText('staging-frontend')).toBeInTheDocument();
    });

    it('expands activity item to show details', async () => {
        render(<ActivityTimelinePage />);

        // Find "Show details" button
        const showDetailsButtons = screen.getAllByText('Show details');
        expect(showDetailsButtons.length).toBeGreaterThan(0);

        // Click the first one
        fireEvent.click(showDetailsButtons[0]);

        await waitFor(() => {
            // Should show metadata/changes
            expect(screen.getByText('Changes:')).toBeInTheDocument();
        });

        // Button text should change
        expect(screen.getByText('Hide details')).toBeInTheDocument();
    });

    it('shows empty state when no activities match filters', async () => {
        render(<ActivityTimelinePage />);

        const searchInput = screen.getByPlaceholderText(
            'Search activities by action, user, or resource...'
        );

        // Search for something that doesn't exist
        fireEvent.change(searchInput, { target: { value: 'nonexistent activity' } });

        await waitFor(() => {
            expect(screen.getByText('No activities found')).toBeInTheDocument();
            expect(screen.getByText('Try adjusting your search query or filters')).toBeInTheDocument();
        });
    });

    it('displays pagination when totalPages > 1', () => {
        render(<ActivityTimelinePage totalPages={3} currentPage={2} />);

        expect(screen.getByText('Page 2 of 3')).toBeInTheDocument();
        expect(screen.getByText('Previous')).toBeInTheDocument();
        expect(screen.getByText('Next')).toBeInTheDocument();
    });

    it('displays correct statistics for activities', () => {
        render(<ActivityTimelinePage />);

        // Should calculate and display stats
        const totalEvents = screen.getByText('Total Events');
        expect(totalEvents).toBeInTheDocument();
        expect(totalEvents.parentElement?.textContent).toContain('12'); // Mock has 12 activities
    });

    it('displays activity icons based on action type', () => {
        render(<ActivityTimelinePage />);

        // Icons should be rendered based on action types
        // We can check if the activity descriptions are present
        expect(screen.getByText('started deployment')).toBeInTheDocument();
        expect(screen.getByText('updated environment variables')).toBeInTheDocument();
        expect(screen.getByText('added bob@example.com to the team')).toBeInTheDocument();
    });

    it('filters activities by multiple criteria simultaneously', async () => {
        render(<ActivityTimelinePage />);

        // Apply event type filter
        const deploymentsButton = screen.getByText('Deployments');
        fireEvent.click(deploymentsButton);

        // Apply search filter
        const searchInput = screen.getByPlaceholderText(
            'Search activities by action, user, or resource...'
        );
        fireEvent.change(searchInput, { target: { value: 'completed' } });

        await waitFor(() => {
            // Should show only completed deployment activities
            expect(screen.getByText('completed deployment')).toBeInTheDocument();
        });
    });

    it('displays relative timestamps for activities', () => {
        render(<ActivityTimelinePage />);

        // Should show relative time (mocked to "2 hours ago")
        const timestamps = screen.getAllByText('2 hours ago');
        expect(timestamps.length).toBeGreaterThan(0);
    });

    it('renders with project context when projectId is provided', () => {
        render(<ActivityTimelinePage projectId="project-123" />);

        expect(screen.getByText('Activity Timeline')).toBeInTheDocument();
        expect(
            screen.getByText('Track all activity and changes for this project')
        ).toBeInTheDocument();
    });
});
