import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import ActivityShow from '@/pages/Activity/Show';
import type { ActivityLog } from '@/types';

describe('Activity/Show', () => {
    const mockActivity: ActivityLog = {
        id: 1,
        action: 'deployment_completed',
        description: 'completed deployment to production',
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
    };

    const mockRelatedActivities: ActivityLog[] = [
        {
            id: 2,
            action: 'deployment_started',
            description: 'started deployment',
            timestamp: '2024-01-15T09:55:00Z',
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
    ];

    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders back to activity button', () => {
        render(<ActivityShow activity={mockActivity} />);

        const backButton = screen.getByRole('link', { name: /back to activity/i });
        expect(backButton).toHaveAttribute('href', '/activity');
    });

    it('displays activity description as heading', () => {
        render(<ActivityShow activity={mockActivity} />);

        expect(screen.getByRole('heading', { level: 1, name: /completed deployment to production/i })).toBeInTheDocument();
    });

    it('displays user information', () => {
        render(<ActivityShow activity={mockActivity} />);

        expect(screen.getByText('John Doe')).toBeInTheDocument();
        expect(screen.getByText(/john@example\.com/i)).toBeInTheDocument();
    });

    it('displays resource link', () => {
        render(<ActivityShow activity={mockActivity} />);

        expect(screen.getByRole('link', { name: /my app/i })).toHaveAttribute('href', '/applications/1');
    });

    it('displays action type badge', () => {
        render(<ActivityShow activity={mockActivity} />);

        expect(screen.getByText(/Deployment Completed/i)).toBeInTheDocument();
    });

    it('displays related activities when provided', () => {
        render(<ActivityShow activity={mockActivity} relatedActivities={mockRelatedActivities} />);

        expect(screen.getByRole('heading', { level: 3, name: /related activities/i })).toBeInTheDocument();
    });

    it('does not show related activities section when empty', () => {
        render(<ActivityShow activity={mockActivity} relatedActivities={[]} />);

        expect(screen.queryByRole('heading', { level: 3, name: /related activities/i })).not.toBeInTheDocument();
    });

    it('shows not found state when activity is missing', () => {
        render(<ActivityShow activity={undefined} />);

        expect(screen.getByRole('heading', { level: 3, name: /activity not found/i })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /back to activity/i })).toBeInTheDocument();
    });

    it('displays user initials when no avatar', () => {
        render(<ActivityShow activity={mockActivity} />);

        expect(screen.getByText('JD')).toBeInTheDocument();
    });

    it('displays user avatar when provided', () => {
        const activityWithAvatar = {
            ...mockActivity,
            user: {
                ...mockActivity.user,
                avatar: 'https://example.com/avatar.jpg',
            },
        };

        render(<ActivityShow activity={activityWithAvatar} />);

        const avatar = screen.getByAltText('John Doe');
        expect(avatar).toBeInTheDocument();
        expect(avatar).toHaveAttribute('src', 'https://example.com/avatar.jpg');
    });

    it('displays timestamp', () => {
        render(<ActivityShow activity={mockActivity} />);

        // Should show both relative and absolute time
        expect(screen.getByText(/2024/i)).toBeInTheDocument();
    });
});
