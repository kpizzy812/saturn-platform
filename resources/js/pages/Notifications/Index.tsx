import * as React from 'react';
import { Link } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { NotificationItem } from '@/components/ui';
import type { Notification } from '@/types';
import { Button, Badge } from '@/components/ui';
import { Bell, Check, Filter, Settings, Volume2, VolumeX, Wifi, WifiOff } from 'lucide-react';
import { useNotifications } from '@/hooks';

interface Props {
    notifications?: Notification[];
}

// Mock data for demo - in production this would come from the backend
const MOCK_NOTIFICATIONS: Notification[] = [
    {
        id: '1',
        type: 'deployment_success',
        title: 'Deployment Successful',
        description: 'production-api deployed successfully to production environment',
        timestamp: new Date(Date.now() - 1000 * 60 * 30).toISOString(), // 30 mins ago
        isRead: false,
    },
    {
        id: '2',
        type: 'deployment_failure',
        title: 'Deployment Failed',
        description: 'staging-frontend failed to deploy: Build step returned exit code 1',
        timestamp: new Date(Date.now() - 1000 * 60 * 60 * 2).toISOString(), // 2 hours ago
        isRead: false,
    },
    {
        id: '3',
        type: 'team_invite',
        title: 'Team Invitation',
        description: 'John Doe invited you to join the "Acme Corp" team',
        timestamp: new Date(Date.now() - 1000 * 60 * 60 * 5).toISOString(), // 5 hours ago
        isRead: true,
    },
    {
        id: '4',
        type: 'billing_alert',
        title: 'Billing Alert',
        description: 'Your monthly invoice of $49.99 is ready',
        timestamp: new Date(Date.now() - 1000 * 60 * 60 * 24).toISOString(), // 1 day ago
        isRead: true,
    },
    {
        id: '5',
        type: 'security_alert',
        title: 'Security Alert',
        description: 'New login detected from a different location',
        timestamp: new Date(Date.now() - 1000 * 60 * 60 * 24 * 2).toISOString(), // 2 days ago
        isRead: true,
    },
    {
        id: '6',
        type: 'deployment_success',
        title: 'Database Backup Completed',
        description: 'Automated backup for postgres-prod completed successfully',
        timestamp: new Date(Date.now() - 1000 * 60 * 60 * 24 * 3).toISOString(), // 3 days ago
        isRead: true,
    },
    {
        id: '7',
        type: 'info',
        title: 'System Maintenance',
        description: 'Scheduled maintenance window: Sunday, 2AM - 4AM UTC',
        timestamp: new Date(Date.now() - 1000 * 60 * 60 * 24 * 7).toISOString(), // 1 week ago
        isRead: true,
    },
];

type FilterType = 'all' | 'unread' | 'deployment' | 'team' | 'billing' | 'security';

export default function NotificationsIndex({ notifications: propNotifications }: Props) {
    const initialNotifications = propNotifications || MOCK_NOTIFICATIONS;

    // Use the notifications hook
    const {
        notifications,
        unreadCount: hookUnreadCount,
        markAsRead,
        markAllAsRead,
        deleteNotification,
        isConnected,
    } = useNotifications({
        initialNotifications,
        autoRefresh: false,
    });

    const [filter, setFilter] = React.useState<FilterType>('all');
    const [soundEnabled, setSoundEnabled] = React.useState(() => {
        const saved = localStorage.getItem('notifications-sound-enabled');
        return saved ? JSON.parse(saved) : true;
    });
    const [desktopNotificationsEnabled, setDesktopNotificationsEnabled] = React.useState(() => {
        return Notification.permission === 'granted';
    });

    // Handle sound notification toggle
    const toggleSound = React.useCallback(() => {
        setSoundEnabled((prev: boolean) => {
            const newValue = !prev;
            localStorage.setItem('notifications-sound-enabled', JSON.stringify(newValue));
            return newValue;
        });
    }, []);

    // Request desktop notification permission
    const requestDesktopPermission = React.useCallback(async () => {
        if ('Notification' in window && Notification.permission === 'default') {
            const permission = await Notification.requestPermission();
            setDesktopNotificationsEnabled(permission === 'granted');
        }
    }, []);

    // Play notification sound
    const playNotificationSound = React.useCallback(() => {
        if (soundEnabled) {
            // Create a simple notification sound using Web Audio API
            const audioContext = new (window.AudioContext || (window as any).webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);

            oscillator.frequency.value = 800;
            oscillator.type = 'sine';

            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);

            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.5);
        }
    }, [soundEnabled]);

    // Show desktop notification
    const showDesktopNotification = React.useCallback((notification: Notification) => {
        if (desktopNotificationsEnabled && 'Notification' in window && Notification.permission === 'granted') {
            new Notification(notification.title, {
                body: notification.description,
                icon: '/favicon.ico',
                tag: notification.id,
            });
        }
    }, [desktopNotificationsEnabled]);

    // Monitor for new notifications
    const previousCountRef = React.useRef(notifications.length);
    React.useEffect(() => {
        if (notifications.length > previousCountRef.current) {
            const newNotification = notifications[0];
            playNotificationSound();
            showDesktopNotification(newNotification);
        }
        previousCountRef.current = notifications.length;
    }, [notifications, playNotificationSound, showDesktopNotification]);

    const handleMarkAsRead = React.useCallback((id: string) => {
        markAsRead(id);
    }, [markAsRead]);

    const handleMarkAllAsRead = React.useCallback(() => {
        markAllAsRead();
    }, [markAllAsRead]);

    const handleDelete = React.useCallback((id: string) => {
        deleteNotification(id);
    }, [deleteNotification]);

    // Filter notifications
    const filteredNotifications = React.useMemo(() => {
        let filtered = notifications;

        switch (filter) {
            case 'unread':
                filtered = notifications.filter((n) => !n.isRead);
                break;
            case 'deployment':
                filtered = notifications.filter((n) =>
                    n.type.startsWith('deployment')
                );
                break;
            case 'team':
                filtered = notifications.filter((n) => n.type === 'team_invite');
                break;
            case 'billing':
                filtered = notifications.filter((n) => n.type === 'billing_alert');
                break;
            case 'security':
                filtered = notifications.filter((n) => n.type === 'security_alert');
                break;
        }

        return filtered;
    }, [notifications, filter]);

    // Group notifications by date
    const groupedNotifications = React.useMemo(() => {
        const groups: Record<string, Notification[]> = {
            Today: [],
            Yesterday: [],
            'This Week': [],
            Earlier: [],
        };

        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const yesterday = new Date(today.getTime() - 24 * 60 * 60 * 1000);
        const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);

        filteredNotifications.forEach((notification) => {
            const date = new Date(notification.timestamp);
            if (date >= today) {
                groups.Today.push(notification);
            } else if (date >= yesterday) {
                groups.Yesterday.push(notification);
            } else if (date >= weekAgo) {
                groups['This Week'].push(notification);
            } else {
                groups.Earlier.push(notification);
            }
        });

        // Remove empty groups
        return Object.entries(groups).filter(([_, items]) => items.length > 0);
    }, [filteredNotifications]);

    const unreadCount = notifications.filter((n) => !n.isRead).length;

    return (
        <AppLayout
            title="Notifications"
            breadcrumbs={[{ label: 'Notifications' }]}
        >
            <div className="mx-auto max-w-6xl px-6 py-8">
            {/* Header */}
            <div className="mb-6">
                <div className="flex items-center justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-bold text-foreground">Notifications</h1>
                            {/* WebSocket Connection Indicator */}
                            <div className="flex items-center gap-1.5 rounded-full border border-border bg-background-secondary px-2.5 py-1">
                                {isConnected ? (
                                    <>
                                        <div className="h-2 w-2 rounded-full bg-primary animate-pulse" />
                                        <Wifi className="h-3.5 w-3.5 text-primary" />
                                        <span className="text-xs text-foreground-muted">Live</span>
                                    </>
                                ) : (
                                    <>
                                        <div className="h-2 w-2 rounded-full bg-foreground-subtle" />
                                        <WifiOff className="h-3.5 w-3.5 text-foreground-subtle" />
                                        <span className="text-xs text-foreground-subtle">Offline</span>
                                    </>
                                )}
                            </div>
                        </div>
                        <p className="text-foreground-muted">
                            {unreadCount > 0 ? `${unreadCount} unread notification${unreadCount !== 1 ? 's' : ''}` : 'All caught up!'}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        {/* Sound Toggle */}
                        <Button
                            variant="secondary"
                            size="sm"
                            onClick={toggleSound}
                            title={soundEnabled ? 'Disable sound notifications' : 'Enable sound notifications'}
                        >
                            {soundEnabled ? (
                                <Volume2 className="h-4 w-4" />
                            ) : (
                                <VolumeX className="h-4 w-4" />
                            )}
                        </Button>

                        {/* Desktop Notifications */}
                        {!desktopNotificationsEnabled && 'Notification' in window && (
                            <Button
                                variant="secondary"
                                size="sm"
                                onClick={requestDesktopPermission}
                                title="Enable desktop notifications"
                            >
                                <Bell className="mr-2 h-4 w-4" />
                                Enable Desktop
                            </Button>
                        )}

                        <Link href="/notifications/preferences">
                            <Button variant="secondary" size="sm">
                                <Settings className="mr-2 h-4 w-4" />
                                Preferences
                            </Button>
                        </Link>
                        {unreadCount > 0 && (
                            <Button variant="secondary" size="sm" onClick={handleMarkAllAsRead}>
                                <Check className="mr-2 h-4 w-4" />
                                Mark All as Read
                            </Button>
                        )}
                    </div>
                </div>

                {/* Filters */}
                <div className="mt-4 flex items-center gap-2 overflow-x-auto pb-2">
                    <Filter className="h-4 w-4 text-foreground-muted" />
                    <button
                        onClick={() => setFilter('all')}
                        className={`whitespace-nowrap rounded-lg px-3 py-1.5 text-sm font-medium transition-colors ${
                            filter === 'all'
                                ? 'bg-primary text-white'
                                : 'bg-background-secondary text-foreground-muted hover:bg-background-tertiary hover:text-foreground'
                        }`}
                    >
                        All
                    </button>
                    <button
                        onClick={() => setFilter('unread')}
                        className={`whitespace-nowrap rounded-lg px-3 py-1.5 text-sm font-medium transition-colors ${
                            filter === 'unread'
                                ? 'bg-primary text-white'
                                : 'bg-background-secondary text-foreground-muted hover:bg-background-tertiary hover:text-foreground'
                        }`}
                    >
                        Unread
                        {unreadCount > 0 && (
                            <Badge variant="default" className="ml-2">
                                {unreadCount}
                            </Badge>
                        )}
                    </button>
                    <button
                        onClick={() => setFilter('deployment')}
                        className={`whitespace-nowrap rounded-lg px-3 py-1.5 text-sm font-medium transition-colors ${
                            filter === 'deployment'
                                ? 'bg-primary text-white'
                                : 'bg-background-secondary text-foreground-muted hover:bg-background-tertiary hover:text-foreground'
                        }`}
                    >
                        Deployments
                    </button>
                    <button
                        onClick={() => setFilter('team')}
                        className={`whitespace-nowrap rounded-lg px-3 py-1.5 text-sm font-medium transition-colors ${
                            filter === 'team'
                                ? 'bg-primary text-white'
                                : 'bg-background-secondary text-foreground-muted hover:bg-background-tertiary hover:text-foreground'
                        }`}
                    >
                        Team
                    </button>
                    <button
                        onClick={() => setFilter('billing')}
                        className={`whitespace-nowrap rounded-lg px-3 py-1.5 text-sm font-medium transition-colors ${
                            filter === 'billing'
                                ? 'bg-primary text-white'
                                : 'bg-background-secondary text-foreground-muted hover:bg-background-tertiary hover:text-foreground'
                        }`}
                    >
                        Billing
                    </button>
                    <button
                        onClick={() => setFilter('security')}
                        className={`whitespace-nowrap rounded-lg px-3 py-1.5 text-sm font-medium transition-colors ${
                            filter === 'security'
                                ? 'bg-primary text-white'
                                : 'bg-background-secondary text-foreground-muted hover:bg-background-tertiary hover:text-foreground'
                        }`}
                    >
                        Security
                    </button>
                </div>
            </div>

            {/* Notifications List */}
            {groupedNotifications.length === 0 ? (
                <EmptyState filter={filter} />
            ) : (
                <div className="space-y-6">
                    {groupedNotifications.map(([group, items]) => (
                        <div key={group}>
                            <div className="sticky top-0 z-10 mb-3 bg-background py-2">
                                <h2 className="text-sm font-semibold text-foreground-muted">{group}</h2>
                            </div>
                            <div className="space-y-2">
                                {items.map((notification) => (
                                    <Link
                                        key={notification.id}
                                        href={`/notifications/${notification.id}`}
                                    >
                                        <NotificationItem
                                            notification={notification}
                                            onMarkAsRead={handleMarkAsRead}
                                            onDelete={handleDelete}
                                        />
                                    </Link>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            )}
            </div>
        </AppLayout>
    );
}

function EmptyState({ filter }: { filter: FilterType }) {
    const getEmptyMessage = () => {
        switch (filter) {
            case 'unread':
                return 'No unread notifications';
            case 'deployment':
                return 'No deployment notifications';
            case 'team':
                return 'No team notifications';
            case 'billing':
                return 'No billing notifications';
            case 'security':
                return 'No security notifications';
            default:
                return 'No notifications';
        }
    };

    return (
        <div className="flex flex-col items-center justify-center rounded-lg border border-border bg-background-secondary p-12 text-center">
            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary">
                <Bell className="h-8 w-8 text-foreground-muted" />
            </div>
            <h3 className="mt-4 text-lg font-medium text-foreground">{getEmptyMessage()}</h3>
            <p className="mt-2 text-foreground-muted">
                You're all caught up! Check back later for new updates.
            </p>
        </div>
    );
}
