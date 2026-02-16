import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import ActivityTimeline from '@/pages/Activity/Timeline';
import type { ActivityLog } from '@/types';

const mockActivities: ActivityLog[] = [
    {
        id: '1',
        action: 'deployment_started',
        description: 'started deployment',
        user: {
            id: 1,
            name: 'John Doe',
            email: 'john@example.com',
            avatar: null,
        },
        resource: null,
        timestamp: '2024-01-15T10:00:00Z',
    },
];

describe('Activity Timeline Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering', () => {
        it('should render page title', () => {
            render(<ActivityTimeline activities={mockActivities} />);

            expect(screen.getByText('Activity Timeline')).toBeInTheDocument();
        });

        it('should render statistics cards', () => {
            render(<ActivityTimeline activities={mockActivities} />);

            expect(screen.getByText('Total Events')).toBeInTheDocument();
            expect(screen.getByText('Last 24 Hours')).toBeInTheDocument();
            expect(screen.getByText('Deployments')).toBeInTheDocument();
            expect(screen.getByText('Failed')).toBeInTheDocument();
        });

        it('should render search input', () => {
            render(<ActivityTimeline activities={mockActivities} />);

            expect(screen.getByPlaceholderText('Search activities by action, user, or resource...')).toBeInTheDocument();
        });

        it('should render event type filters', () => {
            render(<ActivityTimeline activities={mockActivities} />);

            expect(screen.getByText('All Events')).toBeInTheDocument();
            expect(screen.getByText('Deployments')).toBeInTheDocument();
            expect(screen.getByText('Scaling')).toBeInTheDocument();
        });

        it('should render time range filters', () => {
            render(<ActivityTimeline activities={mockActivities} />);

            expect(screen.getByText('All Time')).toBeInTheDocument();
            expect(screen.getByText('Today')).toBeInTheDocument();
            expect(screen.getByText('Last 7 Days')).toBeInTheDocument();
            expect(screen.getByText('Last 30 Days')).toBeInTheDocument();
        });
    });

    describe('pagination', () => {
        it('should render pagination when totalPages > 1', () => {
            render(<ActivityTimeline activities={mockActivities} totalPages={3} currentPage={1} />);

            expect(screen.getByText('Page 1 of 3')).toBeInTheDocument();
            expect(screen.getByText('Previous')).toBeInTheDocument();
            expect(screen.getByText('Next')).toBeInTheDocument();
        });

        it('should not render pagination when totalPages = 1', () => {
            render(<ActivityTimeline activities={mockActivities} totalPages={1} currentPage={1} />);

            expect(screen.queryByText('Page 1 of 1')).not.toBeInTheDocument();
        });
    });

    describe('empty state', () => {
        it('should render empty state when no activities', () => {
            render(<ActivityTimeline activities={[]} />);

            expect(screen.getByText('No activities found')).toBeInTheDocument();
        });
    });
});
