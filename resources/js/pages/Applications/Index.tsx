import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Badge, Input, Select, StatusBadge } from '@/components/ui';
import { Dropdown, DropdownTrigger, DropdownContent, DropdownItem, DropdownDivider } from '@/components/ui/Dropdown';
import {
    Plus,
    Search,
    Filter,
    Play,
    Square,
    RotateCw,
    Rocket,
    MoreVertical,
    GitBranch,
    Globe,
    Server as ServerIcon,
    FolderGit2,
    Settings,
    Trash2
} from 'lucide-react';
import { useRealtimeStatus } from '@/hooks/useRealtimeStatus';
import type { Application, ApplicationStatus } from '@/types';

interface ApplicationWithRelations extends Application {
    project_name?: string;
    environment_name?: string;
}

interface Props {
    applications: ApplicationWithRelations[];
}

export default function ApplicationsIndex({ applications = [] }: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [filterProject, setFilterProject] = useState<string>('all');
    const [filterStatus, setFilterStatus] = useState<string>('all');
    const [appStatuses, setAppStatuses] = useState<Record<number, ApplicationStatus>>({});

    // Real-time status updates
    useRealtimeStatus({
        onApplicationStatusChange: (data) => {
            setAppStatuses(prev => ({
                ...prev,
                [data.applicationId]: data.status,
            }));
        },
    });

    // Get current status for an application
    const getAppStatus = (app: ApplicationWithRelations): ApplicationStatus => {
        return appStatuses[app.id] || app.status;
    };

    // Filter applications
    const filteredApplications = applications.filter(app => {
        const matchesSearch = app.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            app.git_repository?.toLowerCase().includes(searchQuery.toLowerCase());
        const matchesProject = filterProject === 'all' || app.project_name === filterProject;
        const matchesStatus = filterStatus === 'all' || getAppStatus(app) === filterStatus;

        return matchesSearch && matchesProject && matchesStatus;
    });

    // Get unique projects for filter
    const projects = Array.from(new Set(applications.map(app => app.project_name).filter(Boolean)));

    return (
        <AppLayout
            title="Applications"
            breadcrumbs={[{ label: 'Applications' }]}
        >
            <div className="mx-auto max-w-7xl px-6 py-8">
                {/* Header */}
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Applications</h1>
                        <p className="text-foreground-muted">Manage your deployed applications</p>
                    </div>
                    <Link href="/applications/create">
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            New Application
                        </Button>
                    </Link>
                </div>

                {/* Filters */}
                <div className="mb-6 flex flex-wrap gap-3">
                    <div className="relative flex-1 min-w-[250px]">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                        <Input
                            placeholder="Search applications..."
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
                        value={filterStatus}
                        onChange={(e) => setFilterStatus(e.target.value)}
                        className="min-w-[150px]"
                    >
                        <option value="all">All Status</option>
                        <option value="running">Running</option>
                        <option value="stopped">Stopped</option>
                        <option value="deploying">Deploying</option>
                        <option value="failed">Failed</option>
                    </Select>
                </div>

                {/* Applications Grid */}
                {filteredApplications.length === 0 ? (
                    applications.length === 0 ? <EmptyState /> : <NoResults />
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {filteredApplications.map((application) => (
                            <ApplicationCard
                                key={application.id}
                                application={application}
                                currentStatus={getAppStatus(application)}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

interface ApplicationCardProps {
    application: ApplicationWithRelations;
    currentStatus: ApplicationStatus;
}

function ApplicationCard({ application, currentStatus }: ApplicationCardProps) {
    const handleAction = (action: 'start' | 'stop' | 'restart' | 'deploy', e: React.MouseEvent) => {
        e.preventDefault();
        router.post(`/applications/${application.uuid}/${action}`, {}, {
            preserveScroll: true,
        });
    };

    return (
        <Link href={`/applications/${application.uuid}`}>
            <Card className="transition-all hover:border-primary/50 hover:shadow-lg">
                <CardContent className="p-4">
                    {/* Header */}
                    <div className="flex items-start justify-between">
                        <div className="flex items-center gap-3 flex-1 min-w-0">
                            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                                <Rocket className="h-5 w-5 text-primary" />
                            </div>
                            <div className="min-w-0 flex-1">
                                <h3 className="font-medium text-foreground truncate">{application.name}</h3>
                                <p className="text-sm text-foreground-muted truncate">
                                    {application.project_name} / {application.environment_name}
                                </p>
                            </div>
                        </div>
                        <Dropdown>
                            <DropdownTrigger>
                                <button
                                    className="rounded-md p-1 text-foreground-muted hover:bg-background-tertiary hover:text-foreground"
                                    onClick={(e) => e.preventDefault()}
                                >
                                    <MoreVertical className="h-4 w-4" />
                                </button>
                            </DropdownTrigger>
                            <DropdownContent align="right">
                                <DropdownItem
                                    icon={<Rocket className="h-4 w-4" />}
                                    onClick={(e) => handleAction('deploy', e)}
                                >
                                    Deploy
                                </DropdownItem>
                                <DropdownItem
                                    icon={<RotateCw className="h-4 w-4" />}
                                    onClick={(e) => handleAction('restart', e)}
                                >
                                    Restart
                                </DropdownItem>
                                <DropdownDivider />
                                {currentStatus === 'running' ? (
                                    <DropdownItem
                                        icon={<Square className="h-4 w-4" />}
                                        onClick={(e) => handleAction('stop', e)}
                                    >
                                        Stop
                                    </DropdownItem>
                                ) : (
                                    <DropdownItem
                                        icon={<Play className="h-4 w-4" />}
                                        onClick={(e) => handleAction('start', e)}
                                    >
                                        Start
                                    </DropdownItem>
                                )}
                                <DropdownItem
                                    icon={<Settings className="h-4 w-4" />}
                                    onClick={(e) => {
                                        e.preventDefault();
                                        router.visit(`/applications/${application.uuid}/settings`);
                                    }}
                                >
                                    Settings
                                </DropdownItem>
                                <DropdownDivider />
                                <DropdownItem
                                    icon={<Trash2 className="h-4 w-4" />}
                                    onClick={(e) => {
                                        e.preventDefault();
                                        if (confirm(`Are you sure you want to delete "${application.name}"? This action cannot be undone.`)) {
                                            router.delete(`/applications/${application.uuid}`);
                                        }
                                    }}
                                    danger
                                >
                                    Delete
                                </DropdownItem>
                            </DropdownContent>
                        </Dropdown>
                    </div>

                    {/* Status & Info */}
                    <div className="mt-4 space-y-2">
                        <div className="flex items-center gap-2">
                            <StatusBadge status={currentStatus} />
                            {application.fqdn && (
                                <a
                                    href={`https://${application.fqdn}`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-xs text-primary hover:underline flex items-center gap-1 min-w-0 flex-1"
                                    onClick={(e) => e.stopPropagation()}
                                >
                                    <Globe className="h-3 w-3 shrink-0" />
                                    <span className="truncate">{application.fqdn}</span>
                                </a>
                            )}
                        </div>

                        {/* Repository Info */}
                        {application.git_repository && (
                            <div className="flex items-center gap-2 text-xs text-foreground-muted">
                                <FolderGit2 className="h-3 w-3" />
                                <span className="truncate">{application.git_repository}</span>
                            </div>
                        )}

                        {/* Branch */}
                        {application.git_branch && (
                            <div className="flex items-center gap-2 text-xs text-foreground-muted">
                                <GitBranch className="h-3 w-3" />
                                <span>{application.git_branch}</span>
                                <span className="mx-1">•</span>
                                <Badge variant="outline" className="text-xs">
                                    {application.build_pack}
                                </Badge>
                            </div>
                        )}
                    </div>

                    {/* Last updated */}
                    <p className="mt-4 text-xs text-foreground-subtle">
                        Updated {new Date(application.updated_at).toLocaleDateString()}
                    </p>
                </CardContent>
            </Card>
        </Link>
    );
}

function EmptyState() {
    return (
        <Card className="p-12 text-center">
            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary">
                <Rocket className="h-8 w-8 text-foreground-muted" />
            </div>
            <h3 className="mt-4 text-lg font-medium text-foreground">No applications yet</h3>
            <p className="mt-2 text-foreground-muted">
                Deploy your first application from Git or Docker image.
            </p>
            <Link href="/applications/create" className="mt-6 inline-block">
                <Button>
                    <Plus className="mr-2 h-4 w-4" />
                    Create Application
                </Button>
            </Link>
        </Card>
    );
}

function NoResults() {
    return (
        <Card className="p-12 text-center">
            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary">
                <Filter className="h-8 w-8 text-foreground-muted" />
            </div>
            <h3 className="mt-4 text-lg font-medium text-foreground">No applications found</h3>
            <p className="mt-2 text-foreground-muted">
                Try adjusting your filters or search query.
            </p>
        </Card>
    );
}
