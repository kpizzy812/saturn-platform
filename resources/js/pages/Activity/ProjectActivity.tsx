import * as React from 'react';
import { Link } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Badge, Input, Select } from '@/components/ui';
import { ActivityTimeline } from '@/components/ui/ActivityTimeline';
import type { ActivityLog, Project, Environment, Application, Service } from '@/types';
import {
    Filter,
    ArrowLeft,
    GitBranch,
    Layers,
    Rocket,
    Database,
} from 'lucide-react';

interface Props {
    project?: Project;
    activities?: ActivityLog[];
    environments?: Environment[];
}

// Mock data for demo
const MOCK_PROJECT: Project = {
    id: 1,
    uuid: 'proj-1',
    name: 'E-commerce Platform',
    description: 'Main e-commerce platform',
    team_id: 1,
    environments: [],
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString(),
};

const MOCK_ENVIRONMENTS: Environment[] = [
    {
        id: 1,
        uuid: 'env-1',
        name: 'production',
        project_id: 1,
        applications: [],
        databases: [],
        services: [],
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
    },
    {
        id: 2,
        uuid: 'env-2',
        name: 'staging',
        project_id: 1,
        applications: [],
        databases: [],
        services: [],
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
    },
];

const MOCK_ACTIVITIES: ActivityLog[] = [
    {
        id: '1',
        action: 'deployment_started',
        description: 'Started deployment for production-api to production',
        user: { name: 'John Doe', email: 'john@example.com' },
        resource: { type: 'application', name: 'production-api', id: 'app-1' },
        timestamp: new Date(Date.now() - 1000 * 60 * 15).toISOString(),
    },
    {
        id: '2',
        action: 'deployment_completed',
        description: 'Deployment completed successfully',
        user: { name: 'John Doe', email: 'john@example.com' },
        resource: { type: 'application', name: 'production-api', id: 'app-1' },
        timestamp: new Date(Date.now() - 1000 * 60 * 10).toISOString(),
    },
    {
        id: '3',
        action: 'settings_updated',
        description: 'Updated environment variables',
        user: { name: 'Jane Smith', email: 'jane@example.com' },
        resource: { type: 'application', name: 'staging-frontend', id: 'app-2' },
        timestamp: new Date(Date.now() - 1000 * 60 * 60).toISOString(),
    },
    {
        id: '4',
        action: 'database_created',
        description: 'Created PostgreSQL database',
        user: { name: 'Jane Smith', email: 'jane@example.com' },
        resource: { type: 'database', name: 'postgres-prod', id: 'db-1' },
        timestamp: new Date(Date.now() - 1000 * 60 * 60 * 5).toISOString(),
    },
    {
        id: '5',
        action: 'application_restarted',
        description: 'Restarted application',
        user: { name: 'Jane Smith', email: 'jane@example.com' },
        resource: { type: 'application', name: 'analytics-service', id: 'app-3' },
        timestamp: new Date(Date.now() - 1000 * 60 * 60 * 24 * 2).toISOString(),
    },
];

type FilterType = 'all' | 'deployment' | 'settings' | 'database' | 'application';
type EnvironmentFilter = 'all' | string;

export default function ProjectActivity({
    project: propProject,
    activities: propActivities,
    environments: propEnvironments,
}: Props) {
    const project = propProject || MOCK_PROJECT;
    const activities = propActivities || MOCK_ACTIVITIES;
    const environments = propEnvironments || MOCK_ENVIRONMENTS;

    const [filterType, setFilterType] = React.useState<FilterType>('all');
    const [environmentFilter, setEnvironmentFilter] = React.useState<EnvironmentFilter>('all');
    const [searchQuery, setSearchQuery] = React.useState('');
    const [page, setPage] = React.useState(1);
    const itemsPerPage = 10;

    // Filter activities
    const filteredActivities = React.useMemo(() => {
        let filtered = activities;

        // Filter by type
        if (filterType !== 'all') {
            filtered = filtered.filter((activity) => {
                const action = activity.action;
                switch (filterType) {
                    case 'deployment':
                        return action.startsWith('deployment_');
                    case 'settings':
                        return action.includes('settings') || action.includes('environment_variable');
                    case 'database':
                        return action.includes('database_');
                    case 'application':
                        return action.includes('application_');
                    default:
                        return true;
                }
            });
        }

        // Filter by environment (based on resource name matching)
        if (environmentFilter !== 'all') {
            filtered = filtered.filter((activity) =>
                activity.resource?.name.toLowerCase().includes(environmentFilter.toLowerCase())
            );
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
    }, [activities, filterType, environmentFilter, searchQuery]);

    // Paginate activities
    const paginatedActivities = React.useMemo(() => {
        const start = (page - 1) * itemsPerPage;
        const end = start + itemsPerPage;
        return filteredActivities.slice(start, end);
    }, [filteredActivities, page]);

    const hasMore = page * itemsPerPage < filteredActivities.length;

    // Activity stats
    const stats = React.useMemo(() => {
        const deployments = activities.filter((a) => a.action.startsWith('deployment_')).length;
        const settingsChanges = activities.filter((a) =>
            a.action.includes('settings') || a.action.includes('environment_variable')
        ).length;
        const databases = activities.filter((a) => a.action.includes('database_')).length;

        return { deployments, settingsChanges, databases };
    }, [activities]);

    return (
        <AppLayout
            title={`${project.name} - Activity`}
            breadcrumbs={[
                { label: 'Projects', href: '/projects' },
                { label: project.name, href: `/projects/${project.uuid}` },
                { label: 'Activity' },
            ]}
        >
            {/* Header */}
            <div className="mb-6">
                <Link href={`/projects/${project.uuid}`}>
                    <Button variant="secondary" size="sm" className="mb-4">
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Back to Project
                    </Button>
                </Link>

                <div className="flex items-start justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-purple-500">
                                <GitBranch className="h-6 w-6 text-white" />
                            </div>
                            <div>
                                <h1 className="text-2xl font-bold text-foreground">{project.name}</h1>
                                <p className="text-foreground-muted">Project Activity Timeline</p>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Stats */}
                <div className="mt-6 grid gap-4 sm:grid-cols-3">
                    <Card>
                        <CardContent className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                <Rocket className="h-5 w-5 text-primary" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold text-foreground">{stats.deployments}</p>
                                <p className="text-sm text-foreground-muted">Deployments</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-info/10">
                                <Layers className="h-5 w-5 text-info" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold text-foreground">{stats.settingsChanges}</p>
                                <p className="text-sm text-foreground-muted">Settings Changes</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-warning/10">
                                <Database className="h-5 w-5 text-warning" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold text-foreground">{stats.databases}</p>
                                <p className="text-sm text-foreground-muted">Database Actions</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <div className="mt-6 space-y-3">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <div className="flex-1">
                            <Input
                                placeholder="Search activities..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                            />
                        </div>
                        <div className="w-full sm:w-48">
                            <Select
                                value={environmentFilter}
                                onChange={(e) => setEnvironmentFilter(e.target.value)}
                            >
                                <option value="all">All Environments</option>
                                {environments.map((env) => (
                                    <option key={env.id} value={env.name}>
                                        {env.name}
                                    </option>
                                ))}
                            </Select>
                        </div>
                    </div>

                    <div className="flex items-center gap-2 overflow-x-auto pb-2">
                        <Filter className="h-4 w-4 text-foreground-muted" />
                        <FilterButton
                            active={filterType === 'all'}
                            onClick={() => setFilterType('all')}
                        >
                            All Activities
                        </FilterButton>
                        <FilterButton
                            active={filterType === 'deployment'}
                            onClick={() => setFilterType('deployment')}
                        >
                            Deployments
                        </FilterButton>
                        <FilterButton
                            active={filterType === 'application'}
                            onClick={() => setFilterType('application')}
                        >
                            Applications
                        </FilterButton>
                        <FilterButton
                            active={filterType === 'database'}
                            onClick={() => setFilterType('database')}
                        >
                            Databases
                        </FilterButton>
                        <FilterButton
                            active={filterType === 'settings'}
                            onClick={() => setFilterType('settings')}
                        >
                            Settings
                        </FilterButton>
                    </div>
                </div>
            </div>

            {/* Activity Timeline */}
            {filteredActivities.length === 0 ? (
                <EmptyState filter={filterType} />
            ) : (
                <Card>
                    <CardContent className="p-0">
                        <ActivityTimeline
                            activities={paginatedActivities}
                            showDateSeparators={true}
                            onLoadMore={() => setPage((p) => p + 1)}
                            hasMore={hasMore}
                        />
                    </CardContent>
                </Card>
            )}

            {/* Results count */}
            {filteredActivities.length > 0 && (
                <div className="mt-4 text-center text-sm text-foreground-muted">
                    Showing {paginatedActivities.length} of {filteredActivities.length} activities
                </div>
            )}
        </AppLayout>
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

function EmptyState({ filter }: { filter: FilterType }) {
    const getMessage = () => {
        switch (filter) {
            case 'deployment':
                return 'No deployment activities found';
            case 'settings':
                return 'No settings changes found';
            case 'database':
                return 'No database activities found';
            case 'application':
                return 'No application activities found';
            default:
                return 'No activities found';
        }
    };

    return (
        <div className="flex flex-col items-center justify-center rounded-lg border border-border bg-background-secondary p-12 text-center">
            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary">
                <GitBranch className="h-8 w-8 text-foreground-muted" />
            </div>
            <h3 className="mt-4 text-lg font-medium text-foreground">{getMessage()}</h3>
            <p className="mt-2 text-foreground-muted">
                Try adjusting your filters or search query.
            </p>
        </div>
    );
}
