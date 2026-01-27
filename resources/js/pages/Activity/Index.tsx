import * as React from 'react';
import { Link } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Badge, Input, Select } from '@/components/ui';
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
    Trash2,
    Play,
    Pause,
    RefreshCw,
    UserPlus,
    UserMinus,
    Key,
    Globe,
    Filter,
} from 'lucide-react';

interface Props {
    activities?: ActivityLog[];
}


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

type FilterAction = 'all' | 'deployment' | 'settings' | 'team' | 'database' | 'server';

export default function ActivityIndex({ activities: propActivities }: Props) {
    const [activities, setActivities] = React.useState<ActivityLog[]>(
        propActivities || []
    );
    const [filterAction, setFilterAction] = React.useState<FilterAction>('all');
    const [searchQuery, setSearchQuery] = React.useState('');

    // Filter activities
    const filteredActivities = React.useMemo(() => {
        let filtered = activities;

        // Filter by action type
        if (filterAction !== 'all') {
            filtered = filtered.filter((activity) => {
                const action = activity.action;
                switch (filterAction) {
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
                    default:
                        return true;
                }
            });
        }

        // Filter by search query
        if (searchQuery) {
            const query = searchQuery.toLowerCase();
            filtered = filtered.filter(
                (activity) =>
                    activity.description.toLowerCase().includes(query) ||
                    activity.user.name.toLowerCase().includes(query) ||
                    activity.user.email.toLowerCase().includes(query) ||
                    activity.resource?.name.toLowerCase().includes(query)
            );
        }

        return filtered;
    }, [activities, filterAction, searchQuery]);

    return (
        <AppLayout title="Activity" breadcrumbs={[{ label: 'Activity' }]}>
            {/* Header */}
            <div className="mb-6">
                <div>
                    <h1 className="text-2xl font-bold text-foreground">Activity Log</h1>
                    <p className="text-foreground-muted">
                        View all activities and changes in your workspace
                    </p>
                </div>

                {/* Filters */}
                <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="flex-1">
                        <Input
                            placeholder="Search activities..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                        />
                    </div>
                    <div className="flex items-center gap-2">
                        <Filter className="h-4 w-4 text-foreground-muted" />
                        <div className="flex gap-2 overflow-x-auto pb-1">
                            <FilterButton
                                active={filterAction === 'all'}
                                onClick={() => setFilterAction('all')}
                            >
                                All
                            </FilterButton>
                            <FilterButton
                                active={filterAction === 'deployment'}
                                onClick={() => setFilterAction('deployment')}
                            >
                                Deployments
                            </FilterButton>
                            <FilterButton
                                active={filterAction === 'team'}
                                onClick={() => setFilterAction('team')}
                            >
                                Team
                            </FilterButton>
                            <FilterButton
                                active={filterAction === 'settings'}
                                onClick={() => setFilterAction('settings')}
                            >
                                Settings
                            </FilterButton>
                            <FilterButton
                                active={filterAction === 'database'}
                                onClick={() => setFilterAction('database')}
                            >
                                Database
                            </FilterButton>
                            <FilterButton
                                active={filterAction === 'server'}
                                onClick={() => setFilterAction('server')}
                            >
                                Server
                            </FilterButton>
                        </div>
                    </div>
                </div>
            </div>

            {/* Activity Timeline */}
            {filteredActivities.length === 0 ? (
                <EmptyState />
            ) : (
                <Card>
                    <CardContent className="p-0">
                        <div className="divide-y divide-border">
                            {filteredActivities.map((activity, index) => (
                                <ActivityItem
                                    key={activity.id}
                                    activity={activity}
                                    isLast={index === filteredActivities.length - 1}
                                />
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}
        </AppLayout>
    );
}

function ActivityItem({ activity, isLast }: { activity: ActivityLog; isLast: boolean }) {
    const initials = activity.user.name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);

    return (
        <div className="relative flex gap-4 p-4 transition-colors hover:bg-background-tertiary">
            {/* Timeline line */}
            {!isLast && (
                <div className="absolute left-8 top-14 h-full w-px bg-border" />
            )}

            {/* User Avatar */}
            <div className="relative z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-purple-500 text-sm font-medium text-white shadow-sm">
                {initials}
            </div>

            {/* Content */}
            <div className="flex-1 space-y-1">
                <div className="flex items-start justify-between gap-2">
                    <div className="flex-1">
                        <div className="flex items-center gap-2">
                            <span className="font-medium text-foreground">
                                {activity.user.name}
                            </span>
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
                                className="mt-1 inline-flex items-center gap-1.5 text-sm text-primary hover:underline"
                            >
                                {getResourceIcon(activity.resource.type)}
                                {activity.resource.name}
                            </Link>
                        )}
                    </div>

                    {/* Timestamp */}
                    <span className="text-xs text-foreground-subtle">
                        {formatRelativeTime(activity.timestamp)}
                    </span>
                </div>
            </div>
        </div>
    );
}

function getResourceIcon(type: ActivityLog['resource']['type']) {
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
            className={`whitespace-nowrap rounded-lg px-3 py-1.5 text-sm font-medium transition-colors ${
                active
                    ? 'bg-primary text-white'
                    : 'bg-background-secondary text-foreground-muted hover:bg-background-tertiary hover:text-foreground'
            }`}
        >
            {children}
        </button>
    );
}

function EmptyState() {
    return (
        <div className="flex flex-col items-center justify-center rounded-lg border border-border bg-background-secondary p-12 text-center">
            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary">
                <Activity className="h-8 w-8 text-foreground-muted" />
            </div>
            <h3 className="mt-4 text-lg font-medium text-foreground">No activities found</h3>
            <p className="mt-2 text-foreground-muted">
                Try adjusting your filters or search query.
            </p>
        </div>
    );
}
