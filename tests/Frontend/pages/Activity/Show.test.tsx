import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import ActivityShow from '@/pages/Activity/Show';
import type { ActivityLog } from '@/types';

const mockActivity: ActivityLog = {
    id: '1',
    action: 'deployment_started',
    description: 'started deployment for',
    user: {
        id: 1,
        name: 'John Doe',
        email: 'john@example.com',
        avatar: null,
    },
    resource: {
        type: 'application',
        name: 'api-server',
        id: 'app-1',
    },
    timestamp: '2024-01-15T10:00:00Z',
};

const mockRelatedActivities: ActivityLog[] = [
    {
        id: '2',
        action: 'deployment_completed',
        description: 'completed deployment',
        user: {
            id: 1,
            name: 'John Doe',
            email: 'john@example.com',
            avatar: null,
        },
        resource: null,
        timestamp: '2024-01-15T10:05:00Z',
    },
];

describe('Activity Show Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering', () => {
        it('should render activity details', () => {
            render(<ActivityShow activity={mockActivity} relatedActivities={mockRelatedActivities} />);

            expect(screen.getByText('started deployment for')).toBeInTheDocument();
            expect(screen.getByText('John Doe')).toBeInTheDocument();
            expect(screen.getByText('(john@example.com)')).toBeInTheDocument();
        });

        it('should render back button', () => {
            render(<ActivityShow activity={mockActivity} relatedActivities={mockRelatedActivities} />);

            const backButtons = screen.getAllByText('Back to Activity');
            expect(backButtons.length).toBeGreaterThan(0);
        });

        it('should render resource link when present', () => {
            render(<ActivityShow activity={mockActivity} relatedActivities={mockRelatedActivities} />);

            expect(screen.getByText('api-server')).toBeInTheDocument();
        });

        it('should render action type badge', () => {
            render(<ActivityShow activity={mockActivity} relatedActivities={mockRelatedActivities} />);

            expect(screen.getByText('Action Type')).toBeInTheDocument();
            expect(screen.getByText(/Deployment Started/i)).toBeInTheDocument();
        });
    });

    describe('related activities', () => {
        it('should render related activities section', () => {
            render(<ActivityShow activity={mockActivity} relatedActivities={mockRelatedActivities} />);

            expect(screen.getByText('Related Activities')).toBeInTheDocument();
        });

        it('should not render related activities section when empty', () => {
            render(<ActivityShow activity={mockActivity} relatedActivities={[]} />);

            expect(screen.queryByText('Related Activities')).not.toBeInTheDocument();
        });
    });

    describe('not found state', () => {
        it('should render not found message when activity is undefined', () => {
            render(<ActivityShow activity={undefined} relatedActivities={[]} />);

            expect(screen.getByText('Activity not found')).toBeInTheDocument();
        });
    });
});
