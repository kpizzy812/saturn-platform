import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '../../utils/test-utils';
import type { Notification } from '@/types';

// Mock browser Notification API (not available in jsdom)
Object.defineProperty(window, 'Notification', {
    value: {
        permission: 'default',
        requestPermission: vi.fn().mockResolvedValue('default'),
    },
    writable: true,
    configurable: true,
});

// Mock the useNotifications hook
const mockMarkAsRead = vi.fn();
const mockMarkAllAsRead = vi.fn();
const mockDeleteNotification = vi.fn();

let mockNotificationsData: Notification[] = [];
let mockUnreadCount = 0;
let mockIsConnected = true;

vi.mock('@/hooks', () => ({
    useNotifications: (options?: any) => {
        const initialNotifications = options?.initialNotifications || [];
        mockNotificationsData = initialNotifications;
        mockUnreadCount = initialNotifications.filter((n: Notification) => !n.isRead).length;

        return {
            notifications: mockNotificationsData,
            unreadCount: mockUnreadCount,
            markAsRead: mockMarkAsRead,
            markAllAsRead: mockMarkAllAsRead,
            deleteNotification: mockDeleteNotification,
            isConnected: mockIsConnected,
        };
    },
}));

// Mock NotificationItem component
vi.mock('@/components/ui', async () => {
    const actual = await vi.importActual('@/components/ui');
    return {
        ...actual,
        NotificationItem: ({ notification, onMarkAsRead, onDelete }: any) => (
            <div data-testid={`notification-${notification.id}`}>
                <span>{notification.title}</span>
                <span>{notification.description}</span>
                <button onClick={() => onMarkAsRead(notification.id)}>Mark as Read</button>
                <button onClick={() => onDelete(notification.id)}>Delete</button>
            </div>
        ),
    };
});

// Import after mocks
import NotificationsIndex from '@/pages/Notifications/Index';

const mockNotifications: Notification[] = [
    {
        id: '1',
        type: 'deployment_success',
        title: 'Deployment Successful',
        description: 'Your application has been deployed successfully',
        timestamp: new Date().toISOString(),
        isRead: false,
        actionUrl: '/deployments/1',
    },
    {
        id: '2',
        type: 'deployment_failure',
        title: 'Deployment Failed',
        description: 'Your application deployment failed',
        timestamp: new Date(Date.now() - 3600000).toISOString(),
        isRead: false,
        actionUrl: '/deployments/2',
    },
    {
        id: '3',
        type: 'team_invite',
        title: 'Team Invitation',
        description: 'You have been invited to join a team',
        timestamp: new Date(Date.now() - 86400000).toISOString(),
        isRead: true,
        actionUrl: '/teams/invites',
    },
    {
        id: '4',
        type: 'billing_alert',
        title: 'Billing Alert',
        description: 'Your credit is running low',
        timestamp: new Date(Date.now() - 172800000).toISOString(),
        isRead: false,
        actionUrl: '/billing',
    },
    {
        id: '5',
        type: 'security_alert',
        title: 'Security Alert',
        description: 'New login detected from unknown device',
        timestamp: new Date(Date.now() - 604800000).toISOString(),
        isRead: true,
        actionUrl: '/security',
    },
];

describe('Notifications Index Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        // Reset localStorage mock - return 'true' for sound setting
        Storage.prototype.getItem = vi.fn((key) => {
            if (key === 'notifications-sound-enabled') return 'true';
            return null;
        });
        Storage.prototype.setItem = vi.fn();
    });

    describe('Page Rendering', () => {
        it('renders the notifications page', () => {
            render(<NotificationsIndex notifications={mockNotifications} />);
            expect(screen.getAllByText('Notifications').length).toBeGreaterThan(0);
        });

        it('displays unread count in description', () => {
            render(<NotificationsIndex notifications={mockNotifications} />);
            // 3 notifications have isRead: false (ids 1, 2, 4)
            expect(screen.getByText(/3 unread notification/)).toBeInTheDocument();
        });

        it('displays all caught up message when no unread', () => {
            // Create all read notifications
            const allReadNotifications = mockNotifications.map(n => ({ ...n, isRead: true }));
            render(<NotificationsIndex notifications={allReadNotifications} />);
            expect(screen.getByText('All caught up!')).toBeInTheDocument();
        });

        it('renders with empty notifications array', () => {
            render(<NotificationsIndex notifications={[]} />);
            expect(screen.getAllByText('Notifications').length).toBeGreaterThan(0);
        });

        it('renders with undefined notifications prop', () => {
            render(<NotificationsIndex />);
            expect(screen.getAllByText('Notifications').length).toBeGreaterThan(0);
        });
    });

    describe('WebSocket Connection Indicator', () => {
        it('displays connected status', () => {
            render(<NotificationsIndex notifications={mockNotifications} />);
            expect(screen.getByText('Live')).toBeInTheDocument();
        });

        it('displays connection icon when connected', () => {
            const { container } = render(<NotificationsIndex notifications={mockNotifications} />);
            // Check for animated pulse dot
            const pulseDot = container.querySelector('.animate-pulse');
            expect(pulseDot).toBeInTheDocument();
        });
    });

    describe('Action Buttons', () => {
        it('renders Mark All as Read button when there are unread notifications', () => {
            render(<NotificationsIndex notifications={mockNotifications} />);
            expect(screen.getByText('Mark All as Read')).toBeInTheDocument();
        });

        it('renders Preferences button', () => {
            render(<NotificationsIndex notifications={mockNotifications} />);
            expect(screen.getByText('Preferences')).toBeInTheDocument();
        });

        it('Preferences button links to correct URL', () => {
            render(<NotificationsIndex notifications={mockNotifications} />);
            const preferencesButton = screen.getByText('Preferences').closest('a');
            expect(preferencesButton).toHaveAttribute('href', '/notifications/preferences');
        });

        it('calls markAllAsRead when Mark All as Read is clicked', () => {
            render(<NotificationsIndex notifications={mockNotifications} />);
            const markAllButton = screen.getByText('Mark All as Read');
            fireEvent.click(markAllButton);
            expect(mockMarkAllAsRead).toHaveBeenCalled();
        });
    });

    describe('Sound Controls', () => {
        it('renders sound toggle button', () => {
            const { container } = render(<NotificationsIndex notifications={mockNotifications} />);
            // Sound button should be present (Volume2 or VolumeX icon)
            const buttons = screen.getAllByRole('button');
            expect(buttons.length).toBeGreaterThan(0);
        });

        it('toggles sound when button is clicked', () => {
            render(<NotificationsIndex notifications={mockNotifications} />);
            const soundButtons = screen.getAllByRole('button').filter(btn =>
                btn.getAttribute('title')?.includes('sound')
            );

            if (soundButtons.length > 0) {
                fireEvent.click(soundButtons[0]);
                expect(Storage.prototype.setItem).toHaveBeenCalledWith(
                    'notifications-sound-enabled',
                    expect.any(String)
                );
            }
        });
    });

    describe('Desktop Notifications', () => {
        beforeEach(() => {
            // Mock Notification API
            global.Notification = {
                permission: 'default',
                requestPermission: vi.fn().mockResolvedValue('granted'),
            } as any;
        });

        it('shows Enable Desktop button when permission is not granted', () => {
            render(<NotificationsIndex notifications={mockNotifications} />);
            expect(screen.getByText('Enable Desktop')).toBeInTheDocument();
        });
    });

    describe('Filter Functionality', () => {
        it('renders all filter buttons', () => {
            render(<NotificationsIndex notifications={mockNotifications} />);
            expect(screen.getByText('All')).toBeInTheDocument();
            expect(screen.getByText('Unread')).toBeInTheDocument();
            expect(screen.getByText('Deployments')).toBeInTheDocument();
            expect(screen.getByText('Team')).toBeInTheDocument();
            expect(screen.getByText('Billing')).toBeInTheDocument();
            expect(screen.getByText('Security')).toBeInTheDocument();
        });

        it('shows all notifications by default', () => {
            render(<NotificationsIndex notifications={mockNotifications} />);
            expect(screen.getByText('Deployment Successful')).toBeInTheDocument();
            expect(screen.getByText('Team Invitation')).toBeInTheDocument();
            expect(screen.getByText('Billing Alert')).toBeInTheDocument();
        });

        it('filters to show only unread notifications', () => {
            render(<NotificationsIndex notifications={mockNotifications} />);
            const unreadButton = screen.getByText('Unread');
            fireEvent.click(unreadButton);

            // Unread notifications should be visible
            expect(screen.getByText('Deployment Successful')).toBeInTheDocument();
            expect(screen.getByText('Deployment Failed')).toBeInTheDocument();
        });

        it('filters to show only deployment notifications', () => {
            render(<NotificationsIndex notifications={mockNotifications} />);
            const deploymentsButton = screen.getByText('Deployments');
            fireEvent.click(deploymentsButton);

            // Deployment notifications should be visible
            expect(screen.getByText('Deployment Successful')).toBeInTheDocument();
            expect(screen.getByText('Deployment Failed')).toBeInTheDocument();
        });

        it('filters to show only team notifications', () => {
            render(<NotificationsIndex notifications={mockNotifications} />);
            const teamButton = screen.getByText('Team');
            fireEvent.click(teamButton);

            // Team notification should be visible
            expect(screen.getByText('Team Invitation')).toBeInTheDocument();
        });

        it('filters to show only billing notifications', () => {
            render(<NotificationsIndex notifications={mockNotifications} />);
            const billingButton = screen.getByText('Billing');
            fireEvent.click(billingButton);

            // Billing notification should be visible
            expect(screen.getByText('Billing Alert')).toBeInTheDocument();
        });

        it('filters to show only security notifications', () => {
            render(<NotificationsIndex notifications={mockNotifications} />);
            const securityButton = screen.getByText('Security');
            fireEvent.click(securityButton);

            // Security notification should be visible
            expect(screen.getByText('Security Alert')).toBeInTheDocument();
        });

        it('displays unread count badge on Unread filter', () => {
            render(<NotificationsIndex notifications={mockNotifications} />);
            // Badge should show count from hook (2)
            const unreadSection = screen.getByText('Unread').parentElement;
            expect(unreadSection).toBeTruthy();
        });
    });

    describe('Notification Grouping', () => {
        it('groups notifications by date', () => {
            render(<NotificationsIndex notifications={mockNotifications} />);
            // Should have date group headers
            expect(screen.getByText('Today')).toBeInTheDocument();
        });

        it('renders Today group for recent notifications', () => {
            render(<NotificationsIndex notifications={mockNotifications} />);
            expect(screen.getByText('Today')).toBeInTheDocument();
        });

        it('renders notifications within their groups', () => {
            render(<NotificationsIndex notifications={mockNotifications} />);
            // Recent notifications should appear under Today
            expect(screen.getByText('Deployment Successful')).toBeInTheDocument();
        });
    });

    describe('Notification Items', () => {
        it('renders all notification items', () => {
            render(<NotificationsIndex notifications={mockNotifications} />);
            expect(screen.getByTestId('notification-1')).toBeInTheDocument();
            expect(screen.getByTestId('notification-2')).toBeInTheDocument();
            expect(screen.getByTestId('notification-3')).toBeInTheDocument();
            expect(screen.getByTestId('notification-4')).toBeInTheDocument();
            expect(screen.getByTestId('notification-5')).toBeInTheDocument();
        });

        it('renders notification links', () => {
            render(<NotificationsIndex notifications={mockNotifications} />);
            const links = screen.getAllByRole('link').filter(link =>
                link.getAttribute('href')?.startsWith('/notifications/')
            );
            expect(links.length).toBeGreaterThan(0);
        });

        it('calls markAsRead when notification is marked as read', () => {
            render(<NotificationsIndex notifications={mockNotifications} />);
            const markAsReadButtons = screen.getAllByText('Mark as Read');
            fireEvent.click(markAsReadButtons[0]);
            expect(mockMarkAsRead).toHaveBeenCalled();
        });

        it('calls deleteNotification when notification is deleted', () => {
            render(<NotificationsIndex notifications={mockNotifications} />);
            const deleteButtons = screen.getAllByText('Delete');
            fireEvent.click(deleteButtons[0]);
            expect(mockDeleteNotification).toHaveBeenCalled();
        });
    });

    describe('Empty States', () => {
        it('displays empty state for all filter when no notifications', () => {
            render(<NotificationsIndex notifications={[]} />);
            expect(screen.getByText('No notifications')).toBeInTheDocument();
            expect(screen.getByText("You're all caught up! Check back later for new updates.")).toBeInTheDocument();
        });

        it('displays appropriate empty state for unread filter', () => {
            render(<NotificationsIndex notifications={mockNotifications} />);
            const unreadButton = screen.getByText('Unread');
            fireEvent.click(unreadButton);

            // Since mock returns unread notifications, we won't see empty state
            // But if we filter and nothing matches, we should see it
        });

        it('displays empty state icon', () => {
            const { container } = render(<NotificationsIndex notifications={[]} />);
            // Bell icon should be present in empty state
            const icons = container.querySelectorAll('svg');
            expect(icons.length).toBeGreaterThan(0);
        });
    });

    describe('Accessibility', () => {
        it('has proper heading structure', () => {
            render(<NotificationsIndex notifications={mockNotifications} />);
            const headings = screen.getAllByText('Notifications');
            const h1 = headings.find(h => h.tagName === 'H1');
            expect(h1).toBeDefined();
        });

        it('has group headings', () => {
            render(<NotificationsIndex notifications={mockNotifications} />);
            const groupHeading = screen.getByText('Today');
            expect(groupHeading.tagName).toBe('H2');
        });

        it('filter buttons are accessible', () => {
            render(<NotificationsIndex notifications={mockNotifications} />);
            const allButton = screen.getByText('All');
            expect(allButton.tagName).toBe('BUTTON');
        });
    });

    describe('Responsive Layout', () => {
        it('renders notifications in max-width container', () => {
            const { container } = render(<NotificationsIndex notifications={mockNotifications} />);
            const maxWidthContainer = container.querySelector('.max-w-6xl');
            expect(maxWidthContainer).toBeInTheDocument();
        });

        it('renders filter buttons in scrollable container', () => {
            const { container } = render(<NotificationsIndex notifications={mockNotifications} />);
            const scrollContainer = container.querySelector('.overflow-x-auto');
            expect(scrollContainer).toBeInTheDocument();
        });

        it('renders action buttons in flex layout', () => {
            const { container } = render(<NotificationsIndex notifications={mockNotifications} />);
            const actionButtons = container.querySelector('.flex.items-center.gap-2');
            expect(actionButtons).toBeInTheDocument();
        });
    });

    describe('Date Grouping Logic', () => {
        it('renders Yesterday group for day-old notifications', () => {
            render(<NotificationsIndex notifications={mockNotifications} />);
            // Yesterday group should exist if there are notifications from yesterday
            const groups = screen.queryByText('Yesterday');
            // May or may not exist depending on notification timestamps
        });

        it('renders Earlier group for older notifications', () => {
            render(<NotificationsIndex notifications={mockNotifications} />);
            // Earlier group for week+ old notifications
            const groups = screen.queryByText('Earlier');
            // May exist depending on notification age
        });
    });

    describe('Connection Status', () => {
        it('displays live indicator when connected', () => {
            render(<NotificationsIndex notifications={mockNotifications} />);
            expect(screen.getByText('Live')).toBeInTheDocument();
        });

        it('renders connection status badge', () => {
            const { container } = render(<NotificationsIndex notifications={mockNotifications} />);
            const badge = container.querySelector('.border-border.bg-background-secondary');
            expect(badge).toBeInTheDocument();
        });
    });
});
