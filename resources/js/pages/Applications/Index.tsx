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
    Trash2,
    ExternalLink,
    Clock,
    ScrollText,
} from 'lucide-react';
import { StaggerList, StaggerItem, FadeIn } from '@/components/animation';
import { useRealtimeStatus } from '@/hooks/useRealtimeStatus';
import { usePermissions } from '@/hooks/usePermissions';
import type { EnvironmentType } from '@/types';

interface AppStatus {
    state: string;
    health: string;
}

interface ApplicationWithRelations {
    id: number;
    uuid: string;
    name: string;
    description: string | null;
    fqdn: string | null;
    git_repository: string | null;
    git_branch: string;
    build_pack: 'nixpacks' | 'dockerfile' | 'dockercompose' | 'dockerimage';
    status: AppStatus;
    project_name?: string;
    project_uuid?: string;
    environment_name?: string;
    environment_uuid?: string | null;
    environment_type?: EnvironmentType;
    created_at: string;
    updated_at: string;
}

interface Props {
    applications: ApplicationWithRelations[];
}

// Get badge variant based on environment type (same as DatabaseCard)
const getEnvironmentVariant = (type?: EnvironmentType): 'default' | 'primary' | 'success' | 'warning' | 'info' => {
    switch (type) {
        case 'production':
            return 'warning';
        case 'uat':
            return 'warning';
        case 'development':
            return 'info';
        default:
            return 'default';
    }
};

// Helper to format time ago
const formatTimeAgo = (date?: string): string => {
    if (!date) return 'Never';
    const now = new Date();
    const then = new Date(date);
    const diff = now.getTime() - then.getTime();
    const minutes = Math.floor(diff / (1000 * 60));
    if (minutes < 1) return 'Just now';
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `${days}d ago`;
    return new Date(date).toLocaleDateString();
};

// Build GitHub URL from git_repository
const buildRepoUrl = (repo: string | null): string | null => {
    if (!repo) return null;
    if (repo.startsWith('http')) return repo;
    // Handle github.com/user/repo format
    if (repo.includes('github.com')) return `https://${repo.replace(/^\/\//, '')}`;
    return null;
};

// Normalize status from any format (string "running:healthy", object {state,health}, or unknown) to AppStatus
function normalizeStatus(status: unknown): AppStatus {
    if (status && typeof status === 'object' && 'state' in status) {
        const obj = status as { state?: string; health?: string };
        return {
            state: obj.state || 'unknown',
            health: obj.health || 'unknown',
        };
    }
    if (typeof status === 'string' && status.length > 0) {
        const parts = status.split(':');
        return {
            state: parts[0] || 'unknown',
            health: parts[1] || 'unknown',
        };
    }
    return { state: 'unknown', health: 'unknown' };
}

export default function ApplicationsIndex({ applications = [] }: Props) {
    const { can } = usePermissions();
    const urlParams = new URLSearchParams(window.location.search);
    const initialProject = urlParams.get('project') || 'all';
    const [searchQuery, setSearchQuery] = useState('');
    const [filterProject, setFilterProject] = useState<string>(initialProject);
    const [filterStatus, setFilterStatus] = useState<string>('all');
    const [filterEnvironment, setFilterEnvironment] = useState<string>('all');
    const [appStatuses, setAppStatuses] = useState<Record<number, AppStatus>>({});
    const [deletedUuids, setDeletedUuids] = useState<Set<string>>(new Set());

    // Real-time status updates (WebSocket sends colon-separated string)
    useRealtimeStatus({
        onApplicationStatusChange: (data) => {
            setAppStatuses(prev => ({
                ...prev,
                [data.applicationId]: normalizeStatus(data.status),
            }));
        },
    });

    // Get current status for an application (handles both string and object formats)
    const getAppStatus = (app: ApplicationWithRelations): AppStatus => {
        return appStatuses[app.id] || normalizeStatus(app.status);
    };

    // Filter applications (exclude optimistically deleted ones)
    const filteredApplications = applications.filter(app => {
        if (deletedUuids.has(app.uuid)) return false;
        const matchesSearch = app.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            app.git_repository?.toLowerCase().includes(searchQuery.toLowerCase());
        const matchesProject = filterProject === 'all' || app.project_name === filterProject;
        const status = getAppStatus(app);
        const matchesStatus = filterStatus === 'all' || status.state === filterStatus;
        const matchesEnvironment = filterEnvironment === 'all' || app.environment_name === filterEnvironment;

        return matchesSearch && matchesProject && matchesStatus && matchesEnvironment;
    });

    // Get unique projects for filter
    const projects = Array.from(new Set(applications.map(app => app.project_name).filter(Boolean)));

    // Get unique environments for filter
    const environments = Array.from(
        new Map(
            applications
                .filter(app => app.environment_name)
                .map(app => [app.environment_name!, { name: app.environment_name!, type: app.environment_type }])
        ).values()
    );

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
                    {can('applications.create') && (
                        <Link href="/applications/create">
                            <Button className="group">
                                <Plus className="mr-2 h-4 w-4 group-hover:animate-wiggle" />
                                New Application
                            </Button>
                        </Link>
                    )}
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
                        value={filterEnvironment}
                        onChange={(e) => setFilterEnvironment(e.target.value)}
                        className="min-w-[180px]"
                    >
                        <option value="all">All Environments</option>
                        {environments.map(env => (
                            <option key={env.name} value={env.name}>{env.name}</option>
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
                    applications.length === 0 ? <EmptyState canCreate={can('applications.create')} /> : <NoResults />
                ) : (
                    <StaggerList className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {filteredApplications.map((application, i) => (
                            <StaggerItem key={application.id} index={i}>
                                <ApplicationCard
                                    application={application}
                                    currentStatus={getAppStatus(application)}
                                    onDeleted={(uuid) => setDeletedUuids(prev => new Set(prev).add(uuid))}
                                    can={can}
                                />
                            </StaggerItem>
                        ))}
                    </StaggerList>
                )}
            </div>
        </AppLayout>
    );
}

interface ApplicationCardProps {
    application: ApplicationWithRelations;
    currentStatus: AppStatus;
    onDeleted: (uuid: string) => void;
    can: (permission: string) => boolean;
}

function ApplicationCard({ application, currentStatus, onDeleted, can }: ApplicationCardProps) {
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
        e.stopPropagation();
        router.post(`/applications/${application.uuid}/${action}`, {}, {
            preserveScroll: true,
        });
    };

    const repoUrl = buildRepoUrl(application.git_repository);
    const branchUrl = repoUrl && application.git_branch
        ? `${repoUrl.replace(/\.git$/, '')}/tree/${application.git_branch}`
        : null;

    // Status-based glow effect
    const statusGlow = currentStatus.state === 'running'
        ? 'group-hover:shadow-success/10'
        : currentStatus.state === 'building' || currentStatus.state === 'deploying'
            ? 'group-hover:shadow-warning/10'
            : '';

    return (
        <div
            className={`group relative flex flex-col rounded-xl border border-border/50 bg-gradient-to-br from-background-secondary to-background-secondary/50 p-5 transition-all duration-300 hover:-translate-y-1 hover:border-border hover:shadow-xl hover:shadow-black/20 ${statusGlow}`}
        >
            {/* Subtle gradient overlay on hover */}
            <div className="absolute inset-0 rounded-xl bg-gradient-to-br from-white/[0.02] to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100" />

            {/* Header */}
            <div className="relative flex items-start justify-between">
                <Link
                    href={`/applications/${application.uuid}`}
                    className="flex items-center gap-3 flex-1 min-w-0 transition-colors"
                >
                    <div className="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 transition-colors group-hover:bg-primary/20">
                        <Rocket className="h-5 w-5 text-primary transition-transform duration-200 group-hover:scale-110 group-hover:animate-wiggle" />
                        {/* Status dot on icon */}
                        <span className={`absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full ring-2 ring-background-secondary transition-transform duration-200 group-hover:scale-125 ${
                            currentStatus.state === 'running' ? 'bg-success shadow-success/50 shadow-sm' :
                            currentStatus.state === 'building' || currentStatus.state === 'deploying' ? 'bg-warning shadow-warning/50 shadow-sm animate-pulse' :
                            currentStatus.state === 'stopped' ? 'bg-foreground-subtle' :
                            'bg-danger shadow-danger/50 shadow-sm'
                        }`} />
                    </div>
                    <div className="min-w-0 flex-1">
                        <h3 className="font-medium text-foreground truncate transition-colors hover:text-white">{application.name}</h3>
                        <p className="text-sm text-foreground-muted truncate">
                            {application.project_name}
                        </p>
                    </div>
                </Link>
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
                        {can('applications.deploy') && (
                            <DropdownItem
                                icon={<Rocket className="h-4 w-4" />}
                                onClick={(e) => handleAction('deploy', e)}
                            >
                                Deploy
                            </DropdownItem>
                        )}
                        <DropdownItem
                            icon={<RotateCw className="h-4 w-4" />}
                            onClick={(e) => handleAction('restart', e)}
                        >
                            Restart
                        </DropdownItem>
                        <DropdownDivider />
                        {currentStatus.state === 'running' ? (
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
                            icon={<ScrollText className="h-4 w-4" />}
                            onClick={(e) => {
                                e.preventDefault();
                                router.visit(`/applications/${application.uuid}/logs`);
                            }}
                        >
                            Logs
                        </DropdownItem>
                        <DropdownItem
                            icon={<Settings className="h-4 w-4" />}
                            onClick={(e) => {
                                e.preventDefault();
                                router.visit(`/applications/${application.uuid}/settings`);
                            }}
                        >
                            Settings
                        </DropdownItem>
                        {can('applications.delete') && (
                            <>
                                <DropdownDivider />
                                <DropdownItem
                                    icon={<Trash2 className="h-4 w-4" />}
                                    onClick={handleDelete}
                                    danger
                                >
                                    Delete
                                </DropdownItem>
                            </>
                        )}
                    </DropdownContent>
                </Dropdown>
            </div>

            {/* Status, Health & Environment badges — all clickable */}
            <div className="relative mt-4 flex flex-wrap items-center gap-2">
                <Link
                    href={`/applications/${application.uuid}/logs`}
                    className="inline-flex transition-transform duration-200 hover:scale-110"
                    onClick={(e) => e.stopPropagation()}
                >
                    <StatusBadge status={currentStatus.state} size="sm" />
                </Link>
                <Link
                    href={`/applications/${application.uuid}`}
                    className="inline-flex transition-transform duration-200 hover:scale-110"
                    onClick={(e) => e.stopPropagation()}
                >
                    <StatusBadge status={currentStatus.health} size="sm" />
                </Link>
                {application.environment_name && application.project_uuid && (
                    <Link
                        href={`/projects/${application.project_uuid}?env=${encodeURIComponent(application.environment_name)}`}
                        className="inline-flex transition-transform duration-200 hover:scale-110"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <Badge
                            variant={getEnvironmentVariant(application.environment_type)}
                            size="sm"
                            className="cursor-pointer transition-all duration-200 hover:shadow-md hover:brightness-110"
                        >
                            {application.environment_name}
                        </Badge>
                    </Link>
                )}
            </div>

            {/* Info — interactive links */}
            <div className="relative mt-3 space-y-2">
                {application.fqdn && (
                    <a
                        href={application.fqdn.startsWith('http') ? application.fqdn : `https://${application.fqdn}`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="group/link flex items-center gap-1.5 rounded-md px-1.5 py-0.5 -mx-1.5 text-xs text-primary transition-all duration-200 hover:bg-white/10 hover:scale-[1.02] min-w-0"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <Globe className="h-3 w-3 shrink-0" />
                        <span className="truncate">{application.fqdn.replace(/^https?:\/\//, '')}</span>
                        <ExternalLink className="h-2.5 w-2.5 shrink-0 opacity-0 transition-opacity duration-200 group-hover/link:opacity-100" />
                    </a>
                )}

                {/* Repository Info — clickable */}
                {application.git_repository && (
                    repoUrl ? (
                        <a
                            href={repoUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="group/repo flex items-center gap-1.5 rounded-md px-1.5 py-0.5 -mx-1.5 text-xs text-foreground-muted transition-all duration-200 hover:bg-white/10 hover:text-foreground hover:scale-[1.02]"
                            onClick={(e) => e.stopPropagation()}
                        >
                            <FolderGit2 className="h-3 w-3 shrink-0" />
                            <span className="truncate">{application.git_repository.replace(/^https?:\/\//, '')}</span>
                            <ExternalLink className="h-2.5 w-2.5 shrink-0 opacity-0 transition-opacity duration-200 group-hover/repo:opacity-100" />
                        </a>
                    ) : (
                        <div className="flex items-center gap-1.5 px-1.5 py-0.5 -mx-1.5 text-xs text-foreground-muted">
                            <FolderGit2 className="h-3 w-3 shrink-0" />
                            <span className="truncate">{application.git_repository}</span>
                        </div>
                    )
                )}

                {/* Branch — clickable */}
                {application.git_branch && (
                    <div className="flex items-center gap-2 px-1.5 py-0.5 -mx-1.5 text-xs text-foreground-muted">
                        <GitBranch className="h-3 w-3 shrink-0" />
                        {branchUrl ? (
                            <a
                                href={branchUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="group/branch flex items-center gap-1 transition-all duration-200 hover:text-foreground"
                                onClick={(e) => e.stopPropagation()}
                            >
                                <span>{application.git_branch}</span>
                                <ExternalLink className="h-2.5 w-2.5 shrink-0 opacity-0 transition-opacity duration-200 group-hover/branch:opacity-100" />
                            </a>
                        ) : (
                            <span>{application.git_branch}</span>
                        )}
                        <span className="mx-0.5">&bull;</span>
                        <Badge variant="outline" className="text-xs">
                            {application.build_pack}
                        </Badge>
                    </div>
                )}
            </div>

            {/* Last updated — with relative time */}
            <div className="relative mt-3 flex items-center gap-1.5 border-t border-border/30 pt-3">
                <Clock className="h-3 w-3 text-foreground-subtle" />
                <span className="text-xs text-foreground-subtle">{formatTimeAgo(application.updated_at)}</span>
            </div>
        </div>
    );
}

function EmptyState({ canCreate }: { canCreate: boolean }) {
    return (
        <FadeIn>
            <div className="flex flex-col items-center justify-center rounded-xl border border-border/50 bg-background-secondary/30 py-16">
                <div className="flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary/50">
                    <Rocket className="h-8 w-8 text-foreground-muted animate-pulse-soft" />
                </div>
                <h3 className="mt-4 text-lg font-medium text-foreground">No applications yet</h3>
                <p className="mt-1 text-sm text-foreground-muted">
                    {canCreate
                        ? 'Deploy your first application from Git or Docker image.'
                        : 'No applications have been deployed yet. Contact a team admin to create one.'}
                </p>
                {canCreate && (
                    <Link href="/applications/create" className="mt-6">
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            Create Application
                        </Button>
                    </Link>
                )}
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
                <h3 className="mt-4 text-lg font-medium text-foreground">No applications found</h3>
                <p className="mt-1 text-sm text-foreground-muted">
                    Try adjusting your filters or search query.
                </p>
            </div>
        </FadeIn>
    );
}
