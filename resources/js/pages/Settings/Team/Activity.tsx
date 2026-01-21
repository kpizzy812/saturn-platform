import * as React from 'react';
import { SettingsLayout } from '../Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Select } from '@/components/ui/Select';
import { Input } from '@/components/ui/Input';
import { ActivityTimeline } from '@/components/ui/ActivityTimeline';
import { Link } from '@inertiajs/react';
import type { ActivityLog, ActivityAction } from '@/types';
import {
    ArrowLeft,
    Filter,
    Download,
    Calendar,
    Search,
    User
} from 'lucide-react';

interface Props {
    activities?: ActivityLog[];
}

const mockActivities: ActivityLog[] = [
    {
        id: '1',
        action: 'team_member_added',
        description: 'invited Charlie Brown',
        user: {
            name: 'John Doe',
            email: 'john@acme.com',
            avatar: undefined
        },
        resource: {
            type: 'team',
            name: 'Acme Corporation',
            id: '1'
        },
        timestamp: '2024-03-28T14:30:00Z'
    },
    {
        id: '2',
        action: 'deployment_completed',
        description: 'deployed',
        user: {
            name: 'Jane Smith',
            email: 'jane@acme.com',
        },
        resource: {
            type: 'application',
            name: 'api-service',
            id: '12'
        },
        timestamp: '2024-03-28T12:15:00Z'
    },
    {
        id: '3',
        action: 'settings_updated',
        description: 'updated team settings',
        user: {
            name: 'John Doe',
            email: 'john@acme.com',
        },
        resource: {
            type: 'team',
            name: 'Acme Corporation',
            id: '1'
        },
        timestamp: '2024-03-28T10:45:00Z'
    },
    {
        id: '4',
        action: 'database_created',
        description: 'created',
        user: {
            name: 'Bob Johnson',
            email: 'bob@acme.com',
        },
        resource: {
            type: 'database',
            name: 'postgres-prod',
            id: '5'
        },
        timestamp: '2024-03-27T16:20:00Z'
    },
    {
        id: '5',
        action: 'team_member_removed',
        description: 'removed David Wilson',
        user: {
            name: 'John Doe',
            email: 'john@acme.com',
        },
        resource: {
            type: 'team',
            name: 'Acme Corporation',
            id: '1'
        },
        timestamp: '2024-03-27T14:30:00Z'
    },
    {
        id: '6',
        action: 'deployment_failed',
        description: 'deployment failed',
        user: {
            name: 'Alice Williams',
            email: 'alice@acme.com',
        },
        resource: {
            type: 'application',
            name: 'web-app',
            id: '8'
        },
        timestamp: '2024-03-27T11:00:00Z'
    },
    {
        id: '7',
        action: 'server_connected',
        description: 'connected',
        user: {
            name: 'Jane Smith',
            email: 'jane@acme.com',
        },
        resource: {
            type: 'server',
            name: 'prod-server-01',
            id: '3'
        },
        timestamp: '2024-03-26T15:45:00Z'
    },
    {
        id: '8',
        action: 'environment_variable_updated',
        description: 'updated environment variables',
        user: {
            name: 'Bob Johnson',
            email: 'bob@acme.com',
        },
        resource: {
            type: 'application',
            name: 'api-service',
            id: '12'
        },
        timestamp: '2024-03-26T09:30:00Z'
    },
    {
        id: '9',
        action: 'application_restarted',
        description: 'restarted',
        user: {
            name: 'Jane Smith',
            email: 'jane@acme.com',
        },
        resource: {
            type: 'application',
            name: 'worker-service',
            id: '15'
        },
        timestamp: '2024-03-25T18:20:00Z'
    },
    {
        id: '10',
        action: 'deployment_started',
        description: 'started deployment',
        user: {
            name: 'Alice Williams',
            email: 'alice@acme.com',
        },
        resource: {
            type: 'application',
            name: 'web-app',
            id: '8'
        },
        timestamp: '2024-03-25T14:00:00Z'
    },
];

const actionTypes: { value: string; label: string }[] = [
    { value: 'all', label: 'All Actions' },
    { value: 'deployment_started', label: 'Deployment Started' },
    { value: 'deployment_completed', label: 'Deployment Completed' },
    { value: 'deployment_failed', label: 'Deployment Failed' },
    { value: 'settings_updated', label: 'Settings Updated' },
    { value: 'team_member_added', label: 'Member Added' },
    { value: 'team_member_removed', label: 'Member Removed' },
    { value: 'database_created', label: 'Database Created' },
    { value: 'database_deleted', label: 'Database Deleted' },
    { value: 'server_connected', label: 'Server Connected' },
    { value: 'server_disconnected', label: 'Server Disconnected' },
    { value: 'application_started', label: 'Application Started' },
    { value: 'application_stopped', label: 'Application Stopped' },
    { value: 'application_restarted', label: 'Application Restarted' },
    { value: 'environment_variable_updated', label: 'Environment Variables Updated' },
];

const dateRanges: { value: string; label: string }[] = [
    { value: 'today', label: 'Today' },
    { value: 'yesterday', label: 'Yesterday' },
    { value: 'week', label: 'Last 7 Days' },
    { value: 'month', label: 'Last 30 Days' },
    { value: 'all', label: 'All Time' },
];

export default function TeamActivity({ activities: propActivities }: Props) {
    const [activities, setActivities] = React.useState<ActivityLog[]>(propActivities || mockActivities);
    const [filteredActivities, setFilteredActivities] = React.useState<ActivityLog[]>(activities);

    // Filters
    const [searchQuery, setSearchQuery] = React.useState('');
    const [selectedMember, setSelectedMember] = React.useState('all');
    const [selectedAction, setSelectedAction] = React.useState('all');
    const [selectedDateRange, setSelectedDateRange] = React.useState('all');
    const [showFilters, setShowFilters] = React.useState(false);

    // Get unique team members from activities
    const teamMembers = React.useMemo(() => {
        const uniqueMembers = new Map<string, { name: string; email: string }>();
        activities.forEach(activity => {
            if (!uniqueMembers.has(activity.user.email)) {
                uniqueMembers.set(activity.user.email, {
                    name: activity.user.name,
                    email: activity.user.email
                });
            }
        });
        return [
            { value: 'all', label: 'All Members' },
            ...Array.from(uniqueMembers.values()).map(member => ({
                value: member.email,
                label: member.name
            }))
        ];
    }, [activities]);

    // Apply filters
    React.useEffect(() => {
        let filtered = activities;

        // Search filter
        if (searchQuery) {
            const query = searchQuery.toLowerCase();
            filtered = filtered.filter(activity =>
                activity.user.name.toLowerCase().includes(query) ||
                activity.user.email.toLowerCase().includes(query) ||
                activity.description.toLowerCase().includes(query) ||
                activity.resource?.name.toLowerCase().includes(query)
            );
        }

        // Member filter
        if (selectedMember !== 'all') {
            filtered = filtered.filter(activity => activity.user.email === selectedMember);
        }

        // Action type filter
        if (selectedAction !== 'all') {
            filtered = filtered.filter(activity => activity.action === selectedAction);
        }

        // Date range filter
        if (selectedDateRange !== 'all') {
            const now = new Date();
            const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            const yesterday = new Date(today.getTime() - 24 * 60 * 60 * 1000);
            const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
            const monthAgo = new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000);

            filtered = filtered.filter(activity => {
                const activityDate = new Date(activity.timestamp);
                switch (selectedDateRange) {
                    case 'today':
                        return activityDate >= today;
                    case 'yesterday':
                        return activityDate >= yesterday && activityDate < today;
                    case 'week':
                        return activityDate >= weekAgo;
                    case 'month':
                        return activityDate >= monthAgo;
                    default:
                        return true;
                }
            });
        }

        setFilteredActivities(filtered);
    }, [searchQuery, selectedMember, selectedAction, selectedDateRange, activities]);

    const handleExport = () => {
        // Export to CSV
        const csv = [
            ['Timestamp', 'User', 'Action', 'Description', 'Resource Type', 'Resource Name'],
            ...filteredActivities.map(activity => [
                new Date(activity.timestamp).toLocaleString(),
                activity.user.name,
                activity.action,
                activity.description,
                activity.resource?.type || '',
                activity.resource?.name || ''
            ])
        ].map(row => row.join(',')).join('\n');

        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `team-activity-${new Date().toISOString().split('T')[0]}.csv`;
        a.click();
        URL.revokeObjectURL(url);
    };

    const activeFiltersCount = [
        searchQuery,
        selectedMember !== 'all' ? selectedMember : null,
        selectedAction !== 'all' ? selectedAction : null,
        selectedDateRange !== 'all' ? selectedDateRange : null
    ].filter(Boolean).length;

    return (
        <SettingsLayout activeSection="team">
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link href="/settings/team">
                            <Button variant="ghost" size="icon">
                                <ArrowLeft className="h-4 w-4" />
                            </Button>
                        </Link>
                        <div>
                            <h2 className="text-2xl font-semibold text-foreground">Team Activity</h2>
                            <p className="text-sm text-foreground-muted">
                                All team member actions and events
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            variant="secondary"
                            size="sm"
                            onClick={() => setShowFilters(!showFilters)}
                        >
                            <Filter className="mr-2 h-4 w-4" />
                            Filters
                            {activeFiltersCount > 0 && (
                                <Badge variant="primary" className="ml-2">
                                    {activeFiltersCount}
                                </Badge>
                            )}
                        </Button>
                        <Button
                            variant="secondary"
                            size="sm"
                            onClick={handleExport}
                            disabled={filteredActivities.length === 0}
                        >
                            <Download className="mr-2 h-4 w-4" />
                            Export
                        </Button>
                    </div>
                </div>

                {/* Filters */}
                {showFilters && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Filter Activity</CardTitle>
                            <CardDescription>
                                Narrow down the activity log by member, action type, or date
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                                <Input
                                    label="Search"
                                    placeholder="Search activity..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                />
                                <Select
                                    label="Team Member"
                                    value={selectedMember}
                                    onChange={(e) => setSelectedMember(e.target.value)}
                                    options={teamMembers}
                                />
                                <Select
                                    label="Action Type"
                                    value={selectedAction}
                                    onChange={(e) => setSelectedAction(e.target.value)}
                                    options={actionTypes}
                                />
                                <Select
                                    label="Date Range"
                                    value={selectedDateRange}
                                    onChange={(e) => setSelectedDateRange(e.target.value)}
                                    options={dateRanges}
                                />
                            </div>
                            {activeFiltersCount > 0 && (
                                <div className="mt-4 flex items-center justify-between rounded-lg border border-border bg-background-tertiary p-3">
                                    <p className="text-sm text-foreground">
                                        Showing {filteredActivities.length} of {activities.length} activities
                                    </p>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => {
                                            setSearchQuery('');
                                            setSelectedMember('all');
                                            setSelectedAction('all');
                                            setSelectedDateRange('all');
                                        }}
                                    >
                                        Clear Filters
                                    </Button>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Activity Timeline */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Activity Log</CardTitle>
                                <CardDescription>
                                    {filteredActivities.length} {filteredActivities.length === 1 ? 'activity' : 'activities'}
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {filteredActivities.length > 0 ? (
                            <ActivityTimeline
                                activities={filteredActivities}
                                showDateSeparators={true}
                            />
                        ) : (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <div className="flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary mb-4">
                                    <Filter className="h-8 w-8 text-foreground-muted" />
                                </div>
                                <p className="text-lg font-medium text-foreground">No activities found</p>
                                <p className="mt-1 text-sm text-foreground-muted">
                                    Try adjusting your filters or search query
                                </p>
                                {activeFiltersCount > 0 && (
                                    <Button
                                        variant="secondary"
                                        className="mt-4"
                                        onClick={() => {
                                            setSearchQuery('');
                                            setSelectedMember('all');
                                            setSelectedAction('all');
                                            setSelectedDateRange('all');
                                        }}
                                    >
                                        Clear Filters
                                    </Button>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    );
}
