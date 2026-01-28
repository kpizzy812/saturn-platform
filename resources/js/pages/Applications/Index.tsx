import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Button, Badge, Input, Select, StatusBadge, useConfirm, useToast } from '@/components/ui';
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
    const [deletedUuids, setDeletedUuids] = useState<Set<string>>(new Set());

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

    // Filter applications (exclude optimistically deleted ones)
    const filteredApplications = applications.filter(app => {
        if (deletedUuids.has(app.uuid)) return false;
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
            <div className="mx-auto max-w-7xl">
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
                                onDeleted={(uuid) => setDeletedUuids(prev => new Set(prev).add(uuid))}
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
    onDeleted: (uuid: string) => void;
}

function ApplicationCard({ application, currentStatus, onDeleted }: ApplicationCardProps) {
    const confirm = useConfirm();
    const { toast } = useToast();

    const handleDelete = async (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        const confirmed = await confirm({
            title: 'Delete Application',
            description: `Are you sure you want to delete "${application.name}"? This action cannot be undone.`,
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            onDeleted(application.uuid);
            router.delete(`/applications/${application.uuid}`, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    toast({
                        title: 'Application deletion queued',
                        description: 'The application will be removed shortly.',
                        variant: 'success',
                    });
                },
                onError: () => {
                    toast({
                        title: 'Failed to delete application',
                        description: 'An error occurred while deleting the application.',
                        variant: 'error',
                    });
                },
            });
        }
    };

    const handleAction = (action: 'start' | 'stop' | 'restart' | 'deploy', e: React.MouseEvent) => {
        e.preventDefault();
        router.post(`/applications/${application.uuid}/${action}`, {}, {
            preserveScroll: true,
        });
    };

    return (
        <Link
            href={`/applications/${application.uuid}`}
            className="group relative flex flex-col rounded-xl border border-border/50 bg-gradient-to-br from-background-secondary to-background-secondary/50 p-5 transition-all duration-300 hover:-translate-y-1 hover:border-border hover:shadow-xl hover:shadow-black/20"
        >
            {/* Subtle gradient overlay on hover */}
            <div className="absolute inset-0 rounded-xl bg-gradient-to-br from-white/[0.02] to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100" />

            {/* Header */}
            <div className="relative flex items-start justify-between">
                <div className="flex items-center gap-3 flex-1 min-w-0">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 transition-colors group-hover:bg-primary/20">
                        <Rocket className="h-5 w-5 text-primary" />
                    </div>
                    <div className="min-w-0 flex-1">
                        <h3 className="font-medium text-foreground truncate transition-colors group-hover:text-white">{application.name}</h3>
                        <p className="text-sm text-foreground-muted truncate">
                            {application.project_name} / {application.environment_name}
                        </p>
                    </div>
                </div>
                <Dropdown>
                    <DropdownTrigger>
                        <button
                            className="rounded-md p-1.5 opacity-0 transition-all duration-200 hover:bg-white/10 group-hover:opacity-100"
                            onClick={(e) => e.preventDefault()}
                        >
                            <MoreVertical className="h-4 w-4 text-foreground-muted" />
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
                                    onClick={handleDelete}
                                    danger
                                >
                                    Delete
                                </DropdownItem>
                            </DropdownContent>
                        </Dropdown>
                    </div>

            {/* Status & Info */}
            <div className="relative mt-4 space-y-2">
                <div className="flex items-center gap-2">
                    <StatusBadge status={currentStatus} />
                    {application.fqdn && (
                        <a
                            href={application.fqdn.startsWith('http') ? application.fqdn : `https://${application.fqdn}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-xs text-primary hover:underline flex items-center gap-1 min-w-0 flex-1"
                            onClick={(e) => e.stopPropagation()}
                        >
                            <Globe className="h-3 w-3 shrink-0" />
                            <span className="truncate">{application.fqdn.replace(/^https?:\/\//, '')}</span>
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
            <p className="relative mt-4 text-xs text-foreground-subtle">
                Updated {new Date(application.updated_at).toLocaleDateString()}
            </p>
        </Link>
    );
}

function EmptyState() {
    return (
        <div className="flex flex-col items-center justify-center rounded-xl border border-border/50 bg-background-secondary/30 py-16">
            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary/50">
                <Rocket className="h-8 w-8 text-foreground-muted" />
            </div>
            <h3 className="mt-4 text-lg font-medium text-foreground">No applications yet</h3>
            <p className="mt-1 text-sm text-foreground-muted">
                Deploy your first application from Git or Docker image.
            </p>
            <Link href="/applications/create" className="mt-6">
                <Button>
                    <Plus className="mr-2 h-4 w-4" />
                    Create Application
                </Button>
            </Link>
        </div>
    );
}

function NoResults() {
    return (
        <div className="flex flex-col items-center justify-center rounded-xl border border-border/50 bg-background-secondary/30 py-16">
            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary/50">
                <Filter className="h-8 w-8 text-foreground-muted" />
            </div>
            <h3 className="mt-4 text-lg font-medium text-foreground">No applications found</h3>
            <p className="mt-1 text-sm text-foreground-muted">
                Try adjusting your filters or search query.
            </p>
        </div>
    );
}
