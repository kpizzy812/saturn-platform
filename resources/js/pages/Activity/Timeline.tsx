import * as React from 'react';
import { Link } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Badge, Button, Input } from '@/components/ui';
import { ActivityTimeline as ActivityTimelineComponent } from '@/components/ui/ActivityTimeline';
import { formatRelativeTime } from '@/lib/utils';
import type { ActivityLog, ActivityAction } from '@/types';
import {
    Activity,
    Rocket,
    Settings,
    Users,
    Database,
    Server,
    GitBranch,
    Play,
    Pause,
    RefreshCw,
    UserPlus,
    UserMinus,
    Key,
    Trash2,
    Filter,
    Search,
    Calendar,
    TrendingUp,
    Clock,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';

interface Props {
    activities?: ActivityLog[];
    projectId?: string;
    currentPage?: number;
    totalPages?: number;
    filters?: {
        eventType?: string;
        dateRange?: string;
    };
}

// Extended activity type with additional fields
interface ExtendedActivityLog extends ActivityLog {
    metadata?: {
        before?: any;
        after?: any;
        changes?: string[];
    };
}

// Mock data for demo
const MOCK_ACTIVITIES: ExtendedActivityLog[] = [
    {
        id: '1',
        action: 'deployment_started',
        description: 'started deployment',
        user: { name: 'John Doe', email: 'john@example.com' },
        resource: { type: 'application', name: 'production-api', id: 'app-1' },
        timestamp: new Date(Date.now() - 1000 * 60 * 15).toISOString(),
        metadata: {
            changes: ['Commit: a1b2c3d4', 'Branch: main', 'Trigger: push'],
        },
    },
    {
        id: '2',
        action: 'deployment_completed',
        description: 'completed deployment',
        user: { name: 'John Doe', email: 'john@example.com' },
        resource: { type: 'application', name: 'production-api', id: 'app-1' },
        timestamp: new Date(Date.now() - 1000 * 60 * 10).toISOString(),
        metadata: {
            changes: ['Duration: 3m 45s', 'Status: success'],
        },
    },
    {
        id: '3',
        action: 'settings_updated',
        description: 'updated environment variables',
        user: { name: 'Jane Smith', email: 'jane@example.com' },
        resource: { type: 'application', name: 'staging-frontend', id: 'app-2' },
        timestamp: new Date(Date.now() - 1000 * 60 * 30).toISOString(),
        metadata: {
            changes: ['Added: API_KEY', 'Updated: DATABASE_URL', 'Removed: OLD_CONFIG'],
        },
    },
    {
        id: '4',
        action: 'application_restarted',
        description: 'restarted application',
        user: { name: 'Jane Smith', email: 'jane@example.com' },
        resource: { type: 'application', name: 'staging-frontend', id: 'app-2' },
        timestamp: new Date(Date.now() - 1000 * 60 * 45).toISOString(),
    },
    {
        id: '5',
        action: 'team_member_added',
        description: 'added bob@example.com to the team',
        user: { name: 'John Doe', email: 'john@example.com' },
        resource: { type: 'team', name: 'Acme Corp', id: 'team-1' },
        timestamp: new Date(Date.now() - 1000 * 60 * 60).toISOString(),
        metadata: {
            changes: ['Role: Developer', 'Access: Read/Write'],
        },
    },
    {
        id: '6',
        action: 'database_created',
        description: 'created database',
        user: { name: 'Jane Smith', email: 'jane@example.com' },
        resource: { type: 'database', name: 'postgres-prod', id: 'db-1' },
        timestamp: new Date(Date.now() - 1000 * 60 * 60 * 2).toISOString(),
        metadata: {
            changes: ['Type: PostgreSQL 15', 'Size: 10GB', 'Backups: Enabled'],
        },
    },
    {
        id: '7',
        action: 'server_connected',
        description: 'connected server to workspace',
        user: { name: 'John Doe', email: 'john@example.com' },
        resource: { type: 'server', name: 'prod-server-1', id: 'server-1' },
        timestamp: new Date(Date.now() - 1000 * 60 * 60 * 5).toISOString(),
        metadata: {
            changes: ['IP: 192.168.1.100', 'Region: us-east-1'],
        },
    },
    {
        id: '8',
        action: 'deployment_failed',
        description: 'deployment failed',
        user: { name: 'Bob Johnson', email: 'bob@example.com' },
        resource: { type: 'application', name: 'analytics-service', id: 'app-3' },
        timestamp: new Date(Date.now() - 1000 * 60 * 60 * 12).toISOString(),
        metadata: {
            changes: ['Error: Build step failed', 'Duration: 2m 15s'],
        },
    },
    {
        id: '9',
        action: 'environment_variable_updated',
        description: 'updated environment variable',
        user: { name: 'Jane Smith', email: 'jane@example.com' },
        resource: { type: 'application', name: 'production-api', id: 'app-1' },
        timestamp: new Date(Date.now() - 1000 * 60 * 60 * 24).toISOString(),
        metadata: {
            changes: ['Variable: DATABASE_URL', 'Action: Updated'],
        },
    },
    {
        id: '10',
        action: 'application_stopped',
        description: 'stopped application',
        user: { name: 'John Doe', email: 'john@example.com' },
        resource: { type: 'application', name: 'staging-frontend', id: 'app-2' },
        timestamp: new Date(Date.now() - 1000 * 60 * 60 * 24 * 2).toISOString(),
    },
    {
        id: '11',
        action: 'database_deleted',
        description: 'deleted database',
        user: { name: 'Jane Smith', email: 'jane@example.com' },
        resource: { type: 'database', name: 'test-redis', id: 'db-2' },
        timestamp: new Date(Date.now() - 1000 * 60 * 60 * 24 * 3).toISOString(),
        metadata: {
            changes: ['Type: Redis', 'Backup created before deletion'],
        },
    },
    {
        id: '12',
        action: 'team_member_removed',
        description: 'removed alice@example.com from the team',
        user: { name: 'John Doe', email: 'john@example.com' },
        resource: { type: 'team', name: 'Acme Corp', id: 'team-1' },
        timestamp: new Date(Date.now() - 1000 * 60 * 60 * 24 * 5).toISOString(),
    },
];

type EventType = 'all' | 'deployment' | 'settings' | 'team' | 'database' | 'server' | 'scaling';

export default function ActivityTimelinePage({ activities: propActivities, projectId, currentPage = 1, totalPages = 3, filters: initialFilters }: Props) {
    const [activities, setActivities] = React.useState<ExtendedActivityLog[]>(
        propActivities as ExtendedActivityLog[] || MOCK_ACTIVITIES
    );
    const [eventType, setEventType] = React.useState<EventType>(
        (initialFilters?.eventType as EventType) || 'all'
    );
    const [searchQuery, setSearchQuery] = React.useState('');
    const [dateRange, setDateRange] = React.useState<'today' | 'week' | 'month' | 'all'>(
        (initialFilters?.dateRange as any) || 'all'
    );

    // Filter activities
    const filteredActivities = React.useMemo(() => {
        let filtered = activities;

        // Filter by event type
        if (eventType !== 'all') {
            filtered = filtered.filter((activity) => {
                const action = activity.action;
                switch (eventType) {
                    case 'deployment':
                        return action.startsWith('deployment_');
                    case 'settings':
                        return action.includes('settings') || action.includes('environment_variable');
                    case 'team':
                        return action.includes('team_member');
                    case 'database':
                        return action.includes('database_');
                    case 'server':
                        return action.includes('server_');
                    case 'scaling':
                        return action.includes('application_started') ||
                               action.includes('application_stopped') ||
                               action.includes('application_restarted');
                    default:
                        return true;
                }
            });
        }

        // Filter by date range
        if (dateRange !== 'all') {
            const now = new Date();
            const cutoff = new Date();
            switch (dateRange) {
                case 'today':
                    cutoff.setHours(0, 0, 0, 0);
                    break;
                case 'week':
                    cutoff.setDate(now.getDate() - 7);
                    break;
                case 'month':
                    cutoff.setDate(now.getDate() - 30);
                    break;
            }
            filtered = filtered.filter(a => new Date(a.timestamp) >= cutoff);
        }

        // Filter by search query
        if (searchQuery) {
            const query = searchQuery.toLowerCase();
            filtered = filtered.filter(
                (activity) =>
                    activity.description.toLowerCase().includes(query) ||
                    activity.user.name.toLowerCase().includes(query) ||
                    activity.user.email.toLowerCase().includes(query) ||
                    activity.resource?.name.toLowerCase().includes(query) ||
                    activity.action.toLowerCase().includes(query)
            );
        }

        return filtered;
    }, [activities, eventType, dateRange, searchQuery]);

    // Activity statistics
    const stats = React.useMemo(() => {
        const now = new Date();
        const last24h = new Date(now.getTime() - 24 * 60 * 60 * 1000);

        return {
            total: activities.length,
            last24h: activities.filter(a => new Date(a.timestamp) >= last24h).length,
            deployments: activities.filter(a => a.action.startsWith('deployment_')).length,
            failed: activities.filter(a => a.action === 'deployment_failed').length,
        };
    }, [activities]);

    return (
        <AppLayout
            title={projectId ? `Activity - ${projectId}` : 'Activity Timeline'}
            breadcrumbs={
                projectId
                    ? [
                          { label: 'Projects', href: '/projects' },
                          { label: projectId, href: `/projects/${projectId}` },
                          { label: 'Activity' },
                      ]
                    : [{ label: 'Activity Timeline' }]
            }
        >
            {/* Header */}
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-foreground">Activity Timeline</h1>
                <p className="text-foreground-muted">
                    {projectId
                        ? 'Track all activity and changes for this project'
                        : 'View all activities and changes across your workspace'}
                </p>
            </div>

            {/* Statistics */}
            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatsCard
                    icon={<Activity className="h-5 w-5 text-primary" />}
                    label="Total Events"
                    value={stats.total.toString()}
                />
                <StatsCard
                    icon={<Clock className="h-5 w-5 text-info" />}
                    label="Last 24 Hours"
                    value={stats.last24h.toString()}
                />
                <StatsCard
                    icon={<Rocket className="h-5 w-5 text-primary" />}
                    label="Deployments"
                    value={stats.deployments.toString()}
                />
                <StatsCard
                    icon={<TrendingUp className="h-5 w-5 text-danger" />}
                    label="Failed"
                    value={stats.failed.toString()}
                    variant={stats.failed > 0 ? 'danger' : 'default'}
                />
            </div>

            {/* Filters */}
            <Card className="mb-6">
                <CardContent className="p-4">
                    <div className="flex flex-col gap-4">
                        {/* Search */}
                        <div className="relative">
                            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                            <Input
                                placeholder="Search activities by action, user, or resource..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="pl-10"
                            />
                        </div>

                        {/* Filter Buttons */}
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="flex items-center gap-1.5 text-sm text-foreground-muted">
                                <Filter className="h-4 w-4" />
                                Event Type:
                            </span>
                            <FilterButton active={eventType === 'all'} onClick={() => setEventType('all')}>
                                All Events
                            </FilterButton>
                            <FilterButton active={eventType === 'deployment'} onClick={() => setEventType('deployment')}>
                                <Rocket className="mr-1.5 h-3.5 w-3.5" />
                                Deployments
                            </FilterButton>
                            <FilterButton active={eventType === 'scaling'} onClick={() => setEventType('scaling')}>
                                <TrendingUp className="mr-1.5 h-3.5 w-3.5" />
                                Scaling
                            </FilterButton>
                            <FilterButton active={eventType === 'settings'} onClick={() => setEventType('settings')}>
                                <Settings className="mr-1.5 h-3.5 w-3.5" />
                                Settings
                            </FilterButton>
                            <FilterButton active={eventType === 'team'} onClick={() => setEventType('team')}>
                                <Users className="mr-1.5 h-3.5 w-3.5" />
                                Team
                            </FilterButton>
                            <FilterButton active={eventType === 'database'} onClick={() => setEventType('database')}>
                                <Database className="mr-1.5 h-3.5 w-3.5" />
                                Database
                            </FilterButton>
                            <FilterButton active={eventType === 'server'} onClick={() => setEventType('server')}>
                                <Server className="mr-1.5 h-3.5 w-3.5" />
                                Server
                            </FilterButton>

                            <div className="ml-4 h-4 w-px bg-border" />

                            <span className="flex items-center gap-1.5 text-sm text-foreground-muted">
                                <Calendar className="h-4 w-4" />
                                Time Range:
                            </span>
                            <FilterButton active={dateRange === 'all'} onClick={() => setDateRange('all')}>
                                All Time
                            </FilterButton>
                            <FilterButton active={dateRange === 'today'} onClick={() => setDateRange('today')}>
                                Today
                            </FilterButton>
                            <FilterButton active={dateRange === 'week'} onClick={() => setDateRange('week')}>
                                Last 7 Days
                            </FilterButton>
                            <FilterButton active={dateRange === 'month'} onClick={() => setDateRange('month')}>
                                Last 30 Days
                            </FilterButton>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Activity Timeline */}
            {filteredActivities.length === 0 ? (
                <EmptyState searchQuery={searchQuery} eventType={eventType} />
            ) : (
                <>
                    <Card>
                        <CardContent className="p-0">
                            <div className="divide-y divide-border">
                                {filteredActivities.map((activity, index) => (
                                    <ActivityTimelineItem
                                        key={activity.id}
                                        activity={activity}
                                        isLast={index === filteredActivities.length - 1}
                                    />
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Pagination */}
                    {totalPages > 1 && (
                        <Card className="mt-6">
                            <CardContent className="p-4">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-foreground-muted">
                                        Page {currentPage} of {totalPages}
                                    </span>
                                    <div className="flex items-center gap-2">
                                        <Button variant="secondary" size="sm" disabled={currentPage === 1}>
                                            <ChevronLeft className="mr-1 h-4 w-4" />
                                            Previous
                                        </Button>
                                        <Button variant="secondary" size="sm" disabled={currentPage === totalPages}>
                                            Next
                                            <ChevronRight className="ml-1 h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </>
            )}
        </AppLayout>
    );
}

function StatsCard({
    icon,
    label,
    value,
    variant = 'default',
}: {
    icon: React.ReactNode;
    label: string;
    value: string;
    variant?: 'default' | 'danger';
}) {
    return (
        <Card>
            <CardContent className="p-4">
                <div className="flex items-center gap-3">
                    <div className={`rounded-lg p-2 ${
                        variant === 'danger' ? 'bg-danger/10' : 'bg-primary/10'
                    }`}>
                        {icon}
                    </div>
                    <div>
                        <div className="text-xs text-foreground-muted">{label}</div>
                        <div className={`text-xl font-bold ${
                            variant === 'danger' ? 'text-danger' : 'text-foreground'
                        }`}>
                            {value}
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function ActivityTimelineItem({ activity, isLast }: { activity: ExtendedActivityLog; isLast: boolean }) {
    const [expanded, setExpanded] = React.useState(false);
    const hasMetadata = activity.metadata && activity.metadata.changes && activity.metadata.changes.length > 0;

    const initials = activity.user.name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);

    const actionIcons: Record<ActivityAction, React.ReactNode> = {
        deployment_started: <Rocket className="h-4 w-4 text-info" />,
        deployment_completed: <Rocket className="h-4 w-4 text-primary" />,
        deployment_failed: <Rocket className="h-4 w-4 text-danger" />,
        settings_updated: <Settings className="h-4 w-4 text-foreground-muted" />,
        team_member_added: <UserPlus className="h-4 w-4 text-primary" />,
        team_member_removed: <UserMinus className="h-4 w-4 text-danger" />,
        database_created: <Database className="h-4 w-4 text-primary" />,
        database_deleted: <Trash2 className="h-4 w-4 text-danger" />,
        server_connected: <Server className="h-4 w-4 text-primary" />,
        server_disconnected: <Server className="h-4 w-4 text-danger" />,
        application_started: <Play className="h-4 w-4 text-primary" />,
        application_stopped: <Pause className="h-4 w-4 text-warning" />,
        application_restarted: <RefreshCw className="h-4 w-4 text-info" />,
        environment_variable_updated: <Key className="h-4 w-4 text-foreground-muted" />,
    };

    const getResourceIcon = (type: ActivityLog['resource']['type']) => {
        switch (type) {
            case 'project':
                return <GitBranch className="h-3.5 w-3.5" />;
            case 'application':
                return <Rocket className="h-3.5 w-3.5" />;
            case 'database':
                return <Database className="h-3.5 w-3.5" />;
            case 'server':
                return <Server className="h-3.5 w-3.5" />;
            case 'team':
                return <Users className="h-3.5 w-3.5" />;
            default:
                return null;
        }
    };

    return (
        <div className="relative p-4 transition-colors hover:bg-background-tertiary">
            {/* Timeline line */}
            {!isLast && <div className="absolute left-8 top-14 h-full w-px bg-border" />}

            <div className="flex gap-4">
                {/* User Avatar */}
                <div className="relative z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-purple-500 text-sm font-medium text-white shadow-sm ring-4 ring-background">
                    {activity.user.avatar ? (
                        <img
                            src={activity.user.avatar}
                            alt={activity.user.name}
                            className="h-full w-full rounded-full object-cover"
                        />
                    ) : (
                        initials
                    )}
                </div>

                {/* Content */}
                <div className="flex-1 space-y-2">
                    <div className="flex items-start justify-between gap-2">
                        <div className="flex-1">
                            <div className="flex items-center gap-2 flex-wrap">
                                <span className="font-medium text-foreground">{activity.user.name}</span>
                                <div className="flex items-center gap-1.5">
                                    {actionIcons[activity.action]}
                                    <span className="text-sm text-foreground-muted">
                                        {activity.description}
                                    </span>
                                </div>
                            </div>

                            {/* Resource Link */}
                            {activity.resource && (
                                <Link
                                    href={`/${activity.resource.type}s/${activity.resource.id}`}
                                    className="mt-2 inline-flex items-center gap-1.5 rounded-md border border-border bg-background-secondary px-2 py-1 text-sm text-foreground-muted transition-colors hover:border-primary hover:text-primary"
                                >
                                    {getResourceIcon(activity.resource.type)}
                                    <span>{activity.resource.name}</span>
                                </Link>
                            )}

                            {/* Metadata */}
                            {hasMetadata && expanded && (
                                <div className="mt-3 space-y-1 rounded-lg border border-border bg-background-secondary p-3">
                                    <div className="text-xs font-medium text-foreground-muted">Changes:</div>
                                    {activity.metadata!.changes!.map((change, idx) => (
                                        <div key={idx} className="flex items-start gap-2 text-sm text-foreground">
                                            <span className="text-primary">•</span>
                                            <span>{change}</span>
                                        </div>
                                    ))}
                                </div>
                            )}

                            {/* Expand button */}
                            {hasMetadata && (
                                <button
                                    onClick={() => setExpanded(!expanded)}
                                    className="mt-2 text-xs text-primary hover:underline"
                                >
                                    {expanded ? 'Hide details' : 'Show details'}
                                </button>
                            )}
                        </div>

                        {/* Timestamp */}
                        <span className="text-xs text-foreground-subtle whitespace-nowrap">
                            {formatRelativeTime(activity.timestamp)}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    );
}

function FilterButton({
    children,
    active,
    onClick,
}: {
    children: React.ReactNode;
    active: boolean;
    onClick: () => void;
}) {
    return (
        <button
            onClick={onClick}
            className={`flex items-center whitespace-nowrap rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                active
                    ? 'bg-primary text-white'
                    : 'bg-background-secondary text-foreground-muted hover:bg-background-tertiary hover:text-foreground'
            }`}
        >
            {children}
        </button>
    );
}

function EmptyState({ searchQuery, eventType }: { searchQuery: string; eventType: EventType }) {
    return (
        <Card>
            <CardContent className="flex flex-col items-center justify-center py-16">
                <div className="flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary">
                    <Activity className="h-8 w-8 text-foreground-muted" />
                </div>
                <h3 className="mt-4 text-lg font-medium text-foreground">No activities found</h3>
                <p className="mt-2 text-center text-sm text-foreground-muted">
                    {searchQuery
                        ? 'Try adjusting your search query or filters'
                        : eventType !== 'all'
                        ? `No ${eventType} events found`
                        : 'No activities have been recorded yet'}
                </p>
            </CardContent>
        </Card>
    );
}
