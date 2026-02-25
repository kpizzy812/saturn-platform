import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Button, Input, Select } from '@/components/ui';
import { Plus, Database as DatabaseIcon, Search, Filter } from 'lucide-react';
import { StaggerList, StaggerItem, FadeIn } from '@/components/animation';
import { DatabaseCard } from '@/components/features/DatabaseCard';
import { useRealtimeStatus } from '@/hooks/useRealtimeStatus';
import type { StandaloneDatabase } from '@/types';

interface Props {
    databases: StandaloneDatabase[];
}

export default function DatabasesIndex({ databases = [] }: Props) {
    const urlParams = new URLSearchParams(window.location.search);
    const initialProject = urlParams.get('project') || 'all';

    const [searchQuery, setSearchQuery] = useState('');
    const [filterProject, setFilterProject] = useState<string>(initialProject);
    const [filterType, setFilterType] = useState<string>('all');
    const [filterStatus, setFilterStatus] = useState<string>('all');

    // Real-time status updates via WebSocket
    useRealtimeStatus({
        onDatabaseStatusChange: () => {
            router.reload({ only: ['databases'] });
        },
    });

    // Filter databases
    const filteredDatabases = databases.filter(db => {
        const matchesSearch = db.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            db.description?.toLowerCase().includes(searchQuery.toLowerCase());
        const matchesProject = filterProject === 'all' || db.project_name === filterProject;
        const matchesType = filterType === 'all' || db.database_type === filterType;
        const statusState = typeof db.status === 'object' ? db.status?.state : String(db.status || '').split(':')[0];
        const matchesStatus = filterStatus === 'all' || statusState === filterStatus;
        return matchesSearch && matchesProject && matchesType && matchesStatus;
    });

    // Get unique projects for filter
    const projects = Array.from(new Set(databases.map(db => db.project_name).filter(Boolean))) as string[];

    // Get unique database types for filter
    const dbTypes = Array.from(new Set(databases.map(db => db.database_type)));

    const formatDbType = (type: string): string => {
        const map: Record<string, string> = {
            postgresql: 'PostgreSQL',
            mysql: 'MySQL',
            mariadb: 'MariaDB',
            mongodb: 'MongoDB',
            redis: 'Redis',
            keydb: 'KeyDB',
            dragonfly: 'Dragonfly',
            clickhouse: 'ClickHouse',
        };
        return map[type] || type;
    };

    return (
        <AppLayout
            title="Databases"
            breadcrumbs={[{ label: 'Databases' }]}
        >
            <div className="mx-auto max-w-7xl">
            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-foreground">Databases</h1>
                    <p className="text-foreground-muted">Manage your database instances</p>
                </div>
                <Link href="/databases/create">
                    <Button className="group">
                        <Plus className="mr-2 h-4 w-4 group-hover:animate-wiggle" />
                        New Database
                    </Button>
                </Link>
            </div>

            {/* Filters */}
            {databases.length > 0 && (
                <div className="mb-6 flex flex-wrap gap-3">
                    <div className="relative flex-1 min-w-[250px]">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                        <Input
                            placeholder="Search databases..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="pl-9"
                        />
                    </div>
                    <Select
                        value={filterProject}
                        onChange={(e) => setFilterProject(e.target.value)}
                        className="min-w-[180px]"
                    >
                        <option value="all">All Projects</option>
                        {projects.map(project => (
                            <option key={project} value={project}>{project}</option>
                        ))}
                    </Select>
                    <Select
                        value={filterType}
                        onChange={(e) => setFilterType(e.target.value)}
                        className="min-w-[150px]"
                    >
                        <option value="all">All Types</option>
                        {dbTypes.map(type => (
                            <option key={type} value={type}>{formatDbType(type)}</option>
                        ))}
                    </Select>
                    <Select
                        value={filterStatus}
                        onChange={(e) => setFilterStatus(e.target.value)}
                        className="min-w-[150px]"
                    >
                        <option value="all">All Status</option>
                        <option value="running">Running</option>
                        <option value="stopped">Stopped</option>
                        <option value="exited">Exited</option>
                    </Select>
                </div>
            )}

            {/* Databases Grid */}
            {filteredDatabases.length === 0 ? (
                databases.length === 0 ? <EmptyState /> : <NoResults />
            ) : (
                <StaggerList className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {filteredDatabases.map((database, i) => (
                        <StaggerItem key={database.id} index={i}>
                            <DatabaseCard database={database} />
                        </StaggerItem>
                    ))}
                </StaggerList>
            )}
            </div>
        </AppLayout>
    );
}

function EmptyState() {
    return (
        <FadeIn>
            <div className="flex flex-col items-center justify-center rounded-xl border border-border/50 bg-background-secondary/30 py-16">
                <div className="flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary/50">
                    <DatabaseIcon className="h-8 w-8 text-foreground-muted animate-pulse-soft" />
                </div>
                <h3 className="mt-4 text-lg font-medium text-foreground">No databases yet</h3>
                <p className="mt-1 text-sm text-foreground-muted">
                    Create your first database to get started with PostgreSQL, MySQL, MongoDB, or Redis.
                </p>
                <Link href="/databases/create" className="mt-6">
                    <Button>
                        <Plus className="mr-2 h-4 w-4" />
                        Create Database
                    </Button>
                </Link>
            </div>
        </FadeIn>
    );
}

function NoResults() {
    return (
        <FadeIn>
            <div className="flex flex-col items-center justify-center rounded-xl border border-border/50 bg-background-secondary/30 py-16">
                <div className="flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary/50">
                    <Filter className="h-8 w-8 text-foreground-muted animate-pulse-soft" />
                </div>
                <h3 className="mt-4 text-lg font-medium text-foreground">No databases found</h3>
                <p className="mt-1 text-sm text-foreground-muted">
                    Try adjusting your filters or search query.
                </p>
            </div>
        </FadeIn>
    );
}
