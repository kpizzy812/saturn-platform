import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { useConfirm } from '@/components/ui';
import {
    AlertTriangle,
    ArrowRight,
    Building2,
    ChevronDown,
    ChevronRight,
    Database,
    FolderKanban,
    Layers,
    Server,
    Trash2,
    User,
    Users,
    Archive,
} from 'lucide-react';

interface TeamNode {
    id: number;
    name: string;
    type: 'team';
    personal_team: boolean;
    projects: ProjectNode[];
    servers_count: number;
    can_transfer: boolean;
}

interface ProjectNode {
    id: number;
    name: string;
    type: 'project';
    environments: EnvironmentNode[];
}

interface EnvironmentNode {
    id: number;
    name: string;
    type: string;
    applications_count: number;
    services_count: number;
    databases_count: number;
}

interface TeamDestination {
    id: number;
    name: string;
    personal_team: boolean;
    members_count: number;
}

interface UserDestination {
    id: number;
    name: string;
    email: string;
}

interface Props {
    user: {
        id: number;
        name: string;
        email: string;
        created_at: string;
    };
    resourceTree: TeamNode[];
    destinations: {
        teams: TeamDestination[];
        users: UserDestination[];
    };
    deleteCheck: {
        can_delete: boolean;
        issues: string[];
        has_resources: boolean;
    };
}

type TransferType = 'team' | 'user' | 'archive' | 'delete_all';

export default function DeleteWithTransfer({
    user,
    resourceTree,
    destinations,
    deleteCheck,
}: Props) {
    const confirm = useConfirm();
    const [expandedTeams, setExpandedTeams] = React.useState<Set<number>>(new Set());
    const [expandedProjects, setExpandedProjects] = React.useState<Set<number>>(new Set());
    const [transferType, setTransferType] = React.useState<TransferType | null>(null);
    const [targetTeamId, setTargetTeamId] = React.useState<number | null>(null);
    const [targetUserId, setTargetUserId] = React.useState<number | null>(null);
    const [reason, setReason] = React.useState('');
    const [isSubmitting, setIsSubmitting] = React.useState(false);

    const toggleTeam = (id: number) => {
        setExpandedTeams((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    };

    const toggleProject = (id: number) => {
        setExpandedProjects((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    };

    const handleSubmit = async () => {
        if (!transferType) return;

        if (transferType === 'team' && !targetTeamId) {
            return;
        }
        if (transferType === 'user' && !targetUserId) {
            return;
        }

        const confirmed = await confirm({
            title: 'Delete User',
            description: `Are you sure you want to delete ${user.name}? This action cannot be undone.`,
            confirmText: 'Delete User',
            variant: 'danger',
        });

        if (!confirmed) return;

        setIsSubmitting(true);
        router.post(
            `/admin/users/${user.id}/transfer-and-delete`,
            {
                transfer_type: transferType,
                target_team_id: targetTeamId,
                target_user_id: targetUserId,
                reason: reason || 'User account deletion',
            },
            {
                preserveState: false,
                onFinish: () => setIsSubmitting(false),
            }
        );
    };

    const totalResources = React.useMemo(() => {
        let projects = 0;
        let environments = 0;
        let applications = 0;
        let services = 0;
        let databases = 0;
        let servers = 0;

        resourceTree.forEach((team) => {
            servers += team.servers_count;
            team.projects.forEach((project) => {
                projects++;
                project.environments.forEach((env) => {
                    environments++;
                    applications += env.applications_count;
                    services += env.services_count;
                    databases += env.databases_count;
                });
            });
        });

        return { projects, environments, applications, services, databases, servers };
    }, [resourceTree]);

    const canSubmit =
        transferType &&
        (transferType === 'delete_all' ||
            transferType === 'archive' ||
            (transferType === 'team' && targetTeamId) ||
            (transferType === 'user' && targetUserId));

    return (
        <AdminLayout
            title={`Delete User: ${user.name}`}
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Users', href: '/admin/users' },
                { label: 'Delete User' },
            ]}
        >
            <div className="mx-auto max-w-4xl">
                {/* Header */}
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold text-foreground">Delete User</h1>
                    <p className="mt-1 text-sm text-foreground-muted">
                        Transfer or archive resources before deleting the user account
                    </p>
                </div>

                {/* User Info */}
                <Card variant="glass" className="mb-6">
                    <CardContent className="p-4">
                        <div className="flex items-center gap-4">
                            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-red-500 to-orange-500 text-lg font-medium text-white">
                                {user.name.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <h2 className="text-lg font-medium text-foreground">{user.name}</h2>
                                <p className="text-sm text-foreground-muted">{user.email}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Warning */}
                {deleteCheck.has_resources && (
                    <Card variant="glass" className="mb-6 border-warning/50 bg-warning/5">
                        <CardContent className="p-4">
                            <div className="flex items-start gap-3">
                                <AlertTriangle className="mt-0.5 h-5 w-5 text-warning" />
                                <div>
                                    <h3 className="font-medium text-foreground">
                                        This user owns resources
                                    </h3>
                                    <p className="mt-1 text-sm text-foreground-muted">
                                        Before deleting this user, you need to decide what happens to
                                        their resources. Choose a transfer option below.
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Resource Summary */}
                {deleteCheck.has_resources && (
                    <Card variant="glass" className="mb-6">
                        <CardHeader>
                            <CardTitle className="text-lg">Resource Summary</CardTitle>
                        </CardHeader>
                        <CardContent className="p-4 pt-0">
                            <div className="grid grid-cols-3 gap-4 sm:grid-cols-6">
                                <div className="text-center">
                                    <div className="text-2xl font-semibold text-foreground">
                                        {resourceTree.length}
                                    </div>
                                    <div className="text-xs text-foreground-muted">Teams</div>
                                </div>
                                <div className="text-center">
                                    <div className="text-2xl font-semibold text-foreground">
                                        {totalResources.projects}
                                    </div>
                                    <div className="text-xs text-foreground-muted">Projects</div>
                                </div>
                                <div className="text-center">
                                    <div className="text-2xl font-semibold text-foreground">
                                        {totalResources.applications}
                                    </div>
                                    <div className="text-xs text-foreground-muted">Applications</div>
                                </div>
                                <div className="text-center">
                                    <div className="text-2xl font-semibold text-foreground">
                                        {totalResources.services}
                                    </div>
                                    <div className="text-xs text-foreground-muted">Services</div>
                                </div>
                                <div className="text-center">
                                    <div className="text-2xl font-semibold text-foreground">
                                        {totalResources.databases}
                                    </div>
                                    <div className="text-xs text-foreground-muted">Databases</div>
                                </div>
                                <div className="text-center">
                                    <div className="text-2xl font-semibold text-foreground">
                                        {totalResources.servers}
                                    </div>
                                    <div className="text-xs text-foreground-muted">Servers</div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Resource Tree */}
                {deleteCheck.has_resources && resourceTree.length > 0 && (
                    <Card variant="glass" className="mb-6">
                        <CardHeader>
                            <CardTitle className="text-lg">Resources to Transfer</CardTitle>
                        </CardHeader>
                        <CardContent className="p-4 pt-0">
                            <div className="space-y-2">
                                {resourceTree.map((team) => (
                                    <div key={team.id} className="rounded-lg border border-border/50">
                                        <button
                                            onClick={() => toggleTeam(team.id)}
                                            className="flex w-full items-center gap-2 p-3 text-left hover:bg-accent/50"
                                        >
                                            {expandedTeams.has(team.id) ? (
                                                <ChevronDown className="h-4 w-4 text-foreground-muted" />
                                            ) : (
                                                <ChevronRight className="h-4 w-4 text-foreground-muted" />
                                            )}
                                            <Building2 className="h-4 w-4 text-primary" />
                                            <span className="font-medium">{team.name}</span>
                                            {team.personal_team && (
                                                <Badge variant="secondary" size="sm">
                                                    Personal
                                                </Badge>
                                            )}
                                            <span className="ml-auto text-xs text-foreground-muted">
                                                {team.projects.length} projects, {team.servers_count}{' '}
                                                servers
                                            </span>
                                        </button>

                                        {expandedTeams.has(team.id) && team.projects.length > 0 && (
                                            <div className="border-t border-border/50 bg-accent/20 p-2">
                                                {team.projects.map((project) => (
                                                    <div key={project.id} className="ml-4">
                                                        <button
                                                            onClick={() => toggleProject(project.id)}
                                                            className="flex w-full items-center gap-2 rounded p-2 text-left hover:bg-accent/50"
                                                        >
                                                            {expandedProjects.has(project.id) ? (
                                                                <ChevronDown className="h-3 w-3 text-foreground-muted" />
                                                            ) : (
                                                                <ChevronRight className="h-3 w-3 text-foreground-muted" />
                                                            )}
                                                            <FolderKanban className="h-4 w-4 text-blue-500" />
                                                            <span className="text-sm">
                                                                {project.name}
                                                            </span>
                                                            <span className="ml-auto text-xs text-foreground-muted">
                                                                {project.environments.length} envs
                                                            </span>
                                                        </button>

                                                        {expandedProjects.has(project.id) && (
                                                            <div className="ml-6 space-y-1 py-1">
                                                                {project.environments.map((env) => (
                                                                    <div
                                                                        key={env.id}
                                                                        className="flex items-center gap-2 rounded p-2 text-sm"
                                                                    >
                                                                        <Layers className="h-3 w-3 text-foreground-muted" />
                                                                        <span>{env.name}</span>
                                                                        <Badge
                                                                            variant="secondary"
                                                                            size="sm"
                                                                        >
                                                                            {env.type}
                                                                        </Badge>
                                                                        <span className="ml-auto flex items-center gap-2 text-xs text-foreground-muted">
                                                                            {env.applications_count >
                                                                                0 && (
                                                                                <span>
                                                                                    {
                                                                                        env.applications_count
                                                                                    }{' '}
                                                                                    apps
                                                                                </span>
                                                                            )}
                                                                            {env.databases_count >
                                                                                0 && (
                                                                                <span>
                                                                                    {
                                                                                        env.databases_count
                                                                                    }{' '}
                                                                                    DBs
                                                                                </span>
                                                                            )}
                                                                        </span>
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        )}
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Transfer Options */}
                <Card variant="glass" className="mb-6">
                    <CardHeader>
                        <CardTitle className="text-lg">
                            {deleteCheck.has_resources
                                ? 'Choose Transfer Destination'
                                : 'Confirm Deletion'}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-4 pt-0">
                        <div className="space-y-3">
                            {/* Transfer to Team */}
                            {deleteCheck.has_resources && destinations.teams.length > 0 && (
                                <label
                                    className={`flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition-colors ${
                                        transferType === 'team'
                                            ? 'border-primary bg-primary/5'
                                            : 'border-border/50 hover:border-border'
                                    }`}
                                >
                                    <input
                                        type="radio"
                                        name="transfer_type"
                                        value="team"
                                        checked={transferType === 'team'}
                                        onChange={() => setTransferType('team')}
                                        className="mt-1"
                                    />
                                    <div className="flex-1">
                                        <div className="flex items-center gap-2">
                                            <Users className="h-4 w-4 text-primary" />
                                            <span className="font-medium">
                                                Transfer to existing team
                                            </span>
                                            <Badge variant="success" size="sm">
                                                Recommended
                                            </Badge>
                                        </div>
                                        <p className="mt-1 text-sm text-foreground-muted">
                                            Move all projects to an existing team
                                        </p>

                                        {transferType === 'team' && (
                                            <select
                                                value={targetTeamId ?? ''}
                                                onChange={(e) =>
                                                    setTargetTeamId(
                                                        e.target.value
                                                            ? Number(e.target.value)
                                                            : null
                                                    )
                                                }
                                                className="mt-3 w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
                                            >
                                                <option value="">Select a team...</option>
                                                {destinations.teams.map((team) => (
                                                    <option key={team.id} value={team.id}>
                                                        {team.name} ({team.members_count} members)
                                                    </option>
                                                ))}
                                            </select>
                                        )}
                                    </div>
                                </label>
                            )}

                            {/* Transfer to User */}
                            {deleteCheck.has_resources && destinations.users.length > 0 && (
                                <label
                                    className={`flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition-colors ${
                                        transferType === 'user'
                                            ? 'border-primary bg-primary/5'
                                            : 'border-border/50 hover:border-border'
                                    }`}
                                >
                                    <input
                                        type="radio"
                                        name="transfer_type"
                                        value="user"
                                        checked={transferType === 'user'}
                                        onChange={() => setTransferType('user')}
                                        className="mt-1"
                                    />
                                    <div className="flex-1">
                                        <div className="flex items-center gap-2">
                                            <User className="h-4 w-4 text-blue-500" />
                                            <span className="font-medium">
                                                Transfer ownership to another user
                                            </span>
                                        </div>
                                        <p className="mt-1 text-sm text-foreground-muted">
                                            Make another user the owner of all teams
                                        </p>

                                        {transferType === 'user' && (
                                            <select
                                                value={targetUserId ?? ''}
                                                onChange={(e) =>
                                                    setTargetUserId(
                                                        e.target.value
                                                            ? Number(e.target.value)
                                                            : null
                                                    )
                                                }
                                                className="mt-3 w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
                                            >
                                                <option value="">Select a user...</option>
                                                {destinations.users.map((u) => (
                                                    <option key={u.id} value={u.id}>
                                                        {u.name} ({u.email})
                                                    </option>
                                                ))}
                                            </select>
                                        )}
                                    </div>
                                </label>
                            )}

                            {/* Archive */}
                            {deleteCheck.has_resources && (
                                <label
                                    className={`flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition-colors ${
                                        transferType === 'archive'
                                            ? 'border-primary bg-primary/5'
                                            : 'border-border/50 hover:border-border'
                                    }`}
                                >
                                    <input
                                        type="radio"
                                        name="transfer_type"
                                        value="archive"
                                        checked={transferType === 'archive'}
                                        onChange={() => setTransferType('archive')}
                                        className="mt-1"
                                    />
                                    <div className="flex-1">
                                        <div className="flex items-center gap-2">
                                            <Archive className="h-4 w-4 text-orange-500" />
                                            <span className="font-medium">
                                                Move to Archive team
                                            </span>
                                        </div>
                                        <p className="mt-1 text-sm text-foreground-muted">
                                            Resources will be moved to a system archive team for
                                            later review
                                        </p>
                                    </div>
                                </label>
                            )}

                            {/* Delete All */}
                            <label
                                className={`flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition-colors ${
                                    transferType === 'delete_all'
                                        ? 'border-danger bg-danger/5'
                                        : 'border-border/50 hover:border-border'
                                }`}
                            >
                                <input
                                    type="radio"
                                    name="transfer_type"
                                    value="delete_all"
                                    checked={transferType === 'delete_all'}
                                    onChange={() => setTransferType('delete_all')}
                                    className="mt-1"
                                />
                                <div className="flex-1">
                                    <div className="flex items-center gap-2">
                                        <Trash2 className="h-4 w-4 text-danger" />
                                        <span className="font-medium text-danger">
                                            {deleteCheck.has_resources
                                                ? 'Delete all resources'
                                                : 'Delete user account'}
                                        </span>
                                    </div>
                                    <p className="mt-1 text-sm text-foreground-muted">
                                        {deleteCheck.has_resources
                                            ? 'Permanently delete all teams, projects, and resources. This cannot be undone.'
                                            : 'Permanently delete this user account.'}
                                    </p>
                                </div>
                            </label>
                        </div>

                        {/* Reason */}
                        {transferType && transferType !== 'delete_all' && (
                            <div className="mt-4">
                                <label className="block text-sm font-medium text-foreground">
                                    Reason (optional)
                                </label>
                                <input
                                    type="text"
                                    value={reason}
                                    onChange={(e) => setReason(e.target.value)}
                                    placeholder="e.g., Employee left the company"
                                    className="mt-1 w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
                                />
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Actions */}
                <div className="flex items-center justify-between">
                    <Button
                        variant="secondary"
                        onClick={() => router.visit('/admin/users')}
                    >
                        Cancel
                    </Button>

                    <Button
                        variant="danger"
                        onClick={handleSubmit}
                        disabled={!canSubmit || isSubmitting}
                    >
                        {isSubmitting ? (
                            'Processing...'
                        ) : (
                            <>
                                <Trash2 className="mr-2 h-4 w-4" />
                                {transferType === 'delete_all'
                                    ? 'Delete User & All Resources'
                                    : 'Transfer & Delete User'}
                            </>
                        )}
                    </Button>
                </div>
            </div>
        </AdminLayout>
    );
}
