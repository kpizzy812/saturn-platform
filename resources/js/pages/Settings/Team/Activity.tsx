import * as React from 'react';
import { SettingsLayout } from '../Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Select } from '@/components/ui/Select';
import { Input } from '@/components/ui/Input';
import { ActivityTimeline } from '@/components/ui/ActivityTimeline';
import { Link } from '@inertiajs/react';
import { useTeamActivity } from '@/hooks/useTeamActivity';
import type { ActivityLog } from '@/types';
import {
    ArrowLeft,
    Filter,
    Download,
    RefreshCw,
    Loader2,
    AlertCircle,
} from 'lucide-react';

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
    { value: 'all', label: 'All Time' },
    { value: 'today', label: 'Today' },
    { value: 'yesterday', label: 'Yesterday' },
    { value: 'week', label: 'Last 7 Days' },
    { value: 'month', label: 'Last 30 Days' },
];

export default function TeamActivity() {
    const {
        activities,
        loading,
        error,
        meta,
        filters,
        setFilters,
        refresh,
        loadMore,
        hasMore,
    } = useTeamActivity({ autoRefresh: true, refreshInterval: 60000 });

    // Local filter state for debouncing search
    const [searchQuery, setSearchQuery] = React.useState('');
    const [selectedMember, setSelectedMember] = React.useState('all');
    const [selectedAction, setSelectedAction] = React.useState('all');
    const [selectedDateRange, setSelectedDateRange] = React.useState('all');
    const [showFilters, setShowFilters] = React.useState(false);

    // Debounce search
    React.useEffect(() => {
        const timer = setTimeout(() => {
            setFilters(prev => ({
                ...prev,
                search: searchQuery || undefined,
            }));
        }, 300);

        return () => clearTimeout(timer);
    }, [searchQuery, setFilters]);

    // Update filters when select values change
    React.useEffect(() => {
        setFilters({
            search: searchQuery || undefined,
            member: selectedMember !== 'all' ? selectedMember : undefined,
            action: selectedAction !== 'all' ? selectedAction : undefined,
            dateRange: selectedDateRange !== 'all' ? selectedDateRange : undefined,
        });
    }, [selectedMember, selectedAction, selectedDateRange, searchQuery, setFilters]);

    // Get unique team members from activities
    const teamMembers = React.useMemo(() => {
        const uniqueMembers = new Map<string, { name: string; email: string }>();
        activities.forEach((activity: ActivityLog) => {
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

    const handleExport = () => {
        // Export to CSV
        const csv = [
            ['Timestamp', 'User', 'Action', 'Description', 'Resource Type', 'Resource Name'],
            ...activities.map((activity: ActivityLog) => [
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

    const clearFilters = () => {
        setSearchQuery('');
        setSelectedMember('all');
        setSelectedAction('all');
        setSelectedDateRange('all');
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
                            variant="ghost"
                            size="icon"
                            onClick={refresh}
                            disabled={loading}
                        >
                            <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                        </Button>
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
                            disabled={activities.length === 0}
                        >
                            <Download className="mr-2 h-4 w-4" />
                            Export
                        </Button>
                    </div>
                </div>

                {/* Error Alert */}
                {error && (
                    <div className="flex items-center gap-2 rounded-lg border border-red-500/20 bg-red-500/10 p-4 text-red-500">
                        <AlertCircle className="h-5 w-5" />
                        <span>{error.message}</span>
                        <Button variant="ghost" size="sm" onClick={refresh}>
                            Retry
                        </Button>
                    </div>
                )}

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
                                        Showing {activities.length} of {meta.total} activities
                                    </p>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={clearFilters}
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
                                    {meta.total} {meta.total === 1 ? 'activity' : 'activities'}
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {loading && activities.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12">
                                <Loader2 className="h-8 w-8 animate-spin text-foreground-muted" />
                                <p className="mt-4 text-sm text-foreground-muted">Loading activities...</p>
                            </div>
                        ) : activities.length > 0 ? (
                            <>
                                <ActivityTimeline
                                    activities={activities}
                                    showDateSeparators={true}
                                />
                                {hasMore && (
                                    <div className="mt-6 flex justify-center">
                                        <Button
                                            variant="secondary"
                                            onClick={loadMore}
                                            disabled={loading}
                                        >
                                            {loading ? (
                                                <>
                                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                    Loading...
                                                </>
                                            ) : (
                                                'Load More'
                                            )}
                                        </Button>
                                    </div>
                                )}
                            </>
                        ) : (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <div className="flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary mb-4">
                                    <Filter className="h-8 w-8 text-foreground-muted" />
                                </div>
                                <p className="text-lg font-medium text-foreground">No activities found</p>
                                <p className="mt-1 text-sm text-foreground-muted">
                                    {activeFiltersCount > 0
                                        ? 'Try adjusting your filters or search query'
                                        : 'Team activity will appear here as actions are performed'}
                                </p>
                                {activeFiltersCount > 0 && (
                                    <Button
                                        variant="secondary"
                                        className="mt-4"
                                        onClick={clearFilters}
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
