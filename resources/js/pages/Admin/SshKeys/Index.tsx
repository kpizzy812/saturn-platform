import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import {
    Search,
    KeyRound,
    Server,
    Box,
    GitBranch,
    AlertTriangle,
    X,
    Shield,
} from 'lucide-react';

interface SshKeyInfo {
    id: number;
    uuid: string;
    name: string;
    description?: string;
    fingerprint?: string;
    is_git_related: boolean;
    team_id: number;
    team_name: string;
    servers_count: number;
    applications_count: number;
    github_apps_count: number;
    gitlab_apps_count: number;
    is_in_use: boolean;
    created_at: string;
}

interface Team {
    id: number;
    name: string;
}

interface Props {
    keys: {
        data: SshKeyInfo[];
        total: number;
    };
    allTeams: Team[];
    filters?: {
        search?: string;
        team?: string;
        type?: string;
        usage?: string;
    };
}

function KeyRow({ sshKey }: { sshKey: SshKeyInfo }) {
    const totalUsage = sshKey.servers_count + sshKey.applications_count + sshKey.github_apps_count + sshKey.gitlab_apps_count;

    return (
        <div className={`border-b border-border/50 py-4 last:border-0 ${!sshKey.is_in_use ? 'bg-warning/5' : ''}`}>
            <div className="flex items-start justify-between">
                <div className="flex-1">
                    <div className="flex items-center gap-3">
                        <KeyRound className="h-5 w-5 text-foreground-muted" />
                        <div>
                            <div className="flex items-center gap-2">
                                <Link
                                    href={`/admin/ssh-keys/${sshKey.id}`}
                                    className="font-medium text-foreground hover:text-primary"
                                >
                                    {sshKey.name}
                                </Link>
                                <Badge variant={sshKey.is_git_related ? 'primary' : 'secondary'} size="sm">
                                    {sshKey.is_git_related ? 'Git Deploy' : 'SSH'}
                                </Badge>
                                {!sshKey.is_in_use && (
                                    <Badge variant="warning" size="sm">
                                        <AlertTriangle className="mr-1 h-3 w-3" />
                                        Unused
                                    </Badge>
                                )}
                            </div>
                            <p className="mt-0.5 font-mono text-xs text-foreground-muted">
                                {sshKey.fingerprint ? `SHA256:${sshKey.fingerprint.substring(0, 32)}...` : 'No fingerprint'}
                            </p>
                            <div className="mt-1 flex items-center gap-3 text-xs text-foreground-subtle">
                                <span>{sshKey.team_name}</span>
                                {sshKey.description && (
                                    <>
                                        <span>&middot;</span>
                                        <span>{sshKey.description}</span>
                                    </>
                                )}
                            </div>
                        </div>
                    </div>
                </div>

                <div className="flex flex-col items-end gap-2">
                    <div className="flex items-center gap-2">
                        {sshKey.servers_count > 0 && (
                            <Badge variant="default" size="sm" title="Servers">
                                <Server className="mr-1 h-3 w-3" />
                                {sshKey.servers_count}
                            </Badge>
                        )}
                        {sshKey.applications_count > 0 && (
                            <Badge variant="default" size="sm" title="Applications">
                                <Box className="mr-1 h-3 w-3" />
                                {sshKey.applications_count}
                            </Badge>
                        )}
                        {(sshKey.github_apps_count + sshKey.gitlab_apps_count) > 0 && (
                            <Badge variant="default" size="sm" title="Git Apps">
                                <GitBranch className="mr-1 h-3 w-3" />
                                {sshKey.github_apps_count + sshKey.gitlab_apps_count}
                            </Badge>
                        )}
                        {totalUsage === 0 && (
                            <span className="text-xs text-foreground-subtle">No usage</span>
                        )}
                    </div>
                    <span className="text-xs text-foreground-subtle">
                        {new Date(sshKey.created_at).toLocaleDateString()}
                    </span>
                </div>
            </div>
        </div>
    );
}

export default function AdminSshKeysIndex({ keys: keysData, allTeams = [], filters = {} }: Props) {
    const items = keysData?.data ?? [];
    const total = keysData?.total ?? 0;
    const [searchQuery, setSearchQuery] = React.useState(filters.search ?? '');
    const [teamFilter, setTeamFilter] = React.useState(filters.team ?? '');
    const [typeFilter, setTypeFilter] = React.useState(filters.type ?? 'all');
    const [usageFilter, setUsageFilter] = React.useState(filters.usage ?? 'all');

    // Debounced search
    React.useEffect(() => {
        const timer = setTimeout(() => {
            if (searchQuery !== (filters.search ?? '')) {
                applyFilters({ search: searchQuery });
            }
        }, 300);
        return () => clearTimeout(timer);
    }, [searchQuery]);

    const applyFilters = (newFilters: Record<string, string | undefined>) => {
        const params = new URLSearchParams();
        const merged = {
            search: filters.search,
            team: filters.team,
            type: filters.type,
            usage: filters.usage,
            ...newFilters,
        };

        Object.entries(merged).forEach(([key, value]) => {
            if (value && value !== 'all') {
                params.set(key, value);
            }
        });

        router.get(`/admin/ssh-keys?${params.toString()}`, {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleTypeChange = (type: string) => {
        setTypeFilter(type);
        applyFilters({ type: type === 'all' ? undefined : type });
    };

    const handleUsageChange = (usage: string) => {
        setUsageFilter(usage);
        applyFilters({ usage: usage === 'all' ? undefined : usage });
    };

    const handleTeamChange = (teamId: string) => {
        setTeamFilter(teamId);
        applyFilters({ team: teamId || undefined });
    };

    const clearFilters = () => {
        setSearchQuery('');
        setTeamFilter('');
        setTypeFilter('all');
        setUsageFilter('all');
        router.get('/admin/ssh-keys');
    };

    const inUseCount = items.filter((k) => k.is_in_use).length;
    const unusedCount = items.filter((k) => !k.is_in_use).length;
    const gitKeysCount = items.filter((k) => k.is_git_related).length;

    const hasActiveFilters = filters.search || filters.team || filters.type || filters.usage;

    return (
        <AdminLayout
            title="SSH Keys"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'SSH Keys' },
            ]}
        >
            <div className="mx-auto max-w-7xl">
                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-2xl font-semibold text-foreground">SSH Key Management</h1>
                    <p className="mt-1 text-sm text-foreground-muted">
                        Overview of all SSH keys across teams with usage tracking and security audit
                    </p>
                </div>

                {/* Stats */}
                <div className="mb-6 grid gap-4 sm:grid-cols-4">
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Total Keys</p>
                                    <p className="text-2xl font-bold text-primary">{total}</p>
                                </div>
                                <KeyRound className="h-8 w-8 text-primary/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">In Use</p>
                                    <p className="text-2xl font-bold text-success">{inUseCount}</p>
                                </div>
                                <Shield className="h-8 w-8 text-success/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Unused</p>
                                    <p className="text-2xl font-bold text-warning">{unusedCount}</p>
                                </div>
                                <AlertTriangle className="h-8 w-8 text-warning/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Git Keys</p>
                                    <p className="text-2xl font-bold text-foreground">{gitKeysCount}</p>
                                </div>
                                <GitBranch className="h-8 w-8 text-foreground-muted/50" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <Card variant="glass" className="mb-6">
                    <CardContent className="p-4">
                        <div className="flex flex-col gap-4">
                            <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                                <div className="relative flex-1">
                                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                                    <Input
                                        placeholder="Search by name, description, or fingerprint..."
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
                                        className="pl-10"
                                    />
                                </div>
                                {allTeams.length > 0 && (
                                    <Select
                                        value={teamFilter}
                                        onChange={(e) => handleTeamChange(e.target.value)}
                                        options={[
                                            { value: '', label: 'All Teams' },
                                            ...allTeams.map((team) => ({
                                                value: String(team.id),
                                                label: team.name,
                                            })),
                                        ]}
                                    />
                                )}
                            </div>

                            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div className="flex gap-2">
                                    <span className="flex items-center text-sm text-foreground-muted">Type:</span>
                                    <Button
                                        variant={typeFilter === 'all' ? 'primary' : 'secondary'}
                                        size="sm"
                                        onClick={() => handleTypeChange('all')}
                                    >
                                        All
                                    </Button>
                                    <Button
                                        variant={typeFilter === 'ssh' ? 'primary' : 'secondary'}
                                        size="sm"
                                        onClick={() => handleTypeChange('ssh')}
                                    >
                                        SSH
                                    </Button>
                                    <Button
                                        variant={typeFilter === 'git' ? 'primary' : 'secondary'}
                                        size="sm"
                                        onClick={() => handleTypeChange('git')}
                                    >
                                        Git Deploy
                                    </Button>
                                </div>
                                <div className="flex gap-2">
                                    <span className="flex items-center text-sm text-foreground-muted">Usage:</span>
                                    <Button
                                        variant={usageFilter === 'all' ? 'primary' : 'secondary'}
                                        size="sm"
                                        onClick={() => handleUsageChange('all')}
                                    >
                                        All
                                    </Button>
                                    <Button
                                        variant={usageFilter === 'in_use' ? 'primary' : 'secondary'}
                                        size="sm"
                                        onClick={() => handleUsageChange('in_use')}
                                    >
                                        In Use
                                    </Button>
                                    <Button
                                        variant={usageFilter === 'unused' ? 'primary' : 'secondary'}
                                        size="sm"
                                        onClick={() => handleUsageChange('unused')}
                                    >
                                        Unused
                                    </Button>
                                </div>
                            </div>

                            {hasActiveFilters && (
                                <div className="flex items-center gap-2">
                                    <span className="text-sm text-foreground-muted">Active filters:</span>
                                    {filters.search && (
                                        <Badge variant="secondary" className="flex items-center gap-1">
                                            Search: {filters.search}
                                            <X className="h-3 w-3 cursor-pointer" onClick={() => { setSearchQuery(''); applyFilters({ search: undefined }); }} />
                                        </Badge>
                                    )}
                                    {filters.team && (
                                        <Badge variant="secondary" className="flex items-center gap-1">
                                            Team: {allTeams.find((t) => String(t.id) === filters.team)?.name ?? filters.team}
                                            <X className="h-3 w-3 cursor-pointer" onClick={() => handleTeamChange('')} />
                                        </Badge>
                                    )}
                                    {filters.type && (
                                        <Badge variant="secondary" className="flex items-center gap-1">
                                            Type: {filters.type}
                                            <X className="h-3 w-3 cursor-pointer" onClick={() => handleTypeChange('all')} />
                                        </Badge>
                                    )}
                                    {filters.usage && (
                                        <Badge variant="secondary" className="flex items-center gap-1">
                                            Usage: {filters.usage}
                                            <X className="h-3 w-3 cursor-pointer" onClick={() => handleUsageChange('all')} />
                                        </Badge>
                                    )}
                                    <Button variant="ghost" size="sm" onClick={clearFilters}>
                                        Clear all
                                    </Button>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Keys List */}
                <Card variant="glass">
                    <CardContent className="p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <p className="text-sm text-foreground-muted">
                                Showing {items.length} of {total} keys
                            </p>
                        </div>

                        {items.length === 0 ? (
                            <div className="py-12 text-center">
                                <KeyRound className="mx-auto h-12 w-12 text-foreground-muted" />
                                <p className="mt-4 text-sm text-foreground-muted">No SSH keys found</p>
                                <p className="text-xs text-foreground-subtle">
                                    Try adjusting your search or filters
                                </p>
                            </div>
                        ) : (
                            <div>
                                {items.map((sshKey) => (
                                    <KeyRow key={sshKey.id} sshKey={sshKey} />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
