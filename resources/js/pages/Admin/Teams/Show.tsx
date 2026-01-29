import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { useConfirm } from '@/components/ui';
import {
    Dropdown,
    DropdownTrigger,
    DropdownContent,
    DropdownItem,
    DropdownDivider,
} from '@/components/ui/Dropdown';
import {
    Users,
    Server,
    FolderKanban,
    Calendar,
    MoreHorizontal,
    Eye,
    UserMinus,
    Shield,
    ShieldCheck,
    ShieldAlert,
    Trash2,
    ExternalLink,
} from 'lucide-react';

interface TeamMember {
    id: number;
    name: string;
    email: string;
    role: string;
    created_at: string;
}

interface TeamServer {
    id: number;
    uuid: string;
    name: string;
    ip: string;
    is_reachable: boolean;
}

interface TeamProject {
    id: number;
    uuid: string;
    name: string;
    environments_count: number;
}

interface TeamDetails {
    id: number;
    name: string;
    description?: string;
    personal_team: boolean;
    created_at: string;
    updated_at: string;
    members: TeamMember[];
    servers: TeamServer[];
    projects: TeamProject[];
}

interface Props {
    team: TeamDetails;
}

function MemberRow({ member, teamId, isPersonalTeam }: { member: TeamMember; teamId: number; isPersonalTeam: boolean }) {
    const confirm = useConfirm();

    const handleRemoveMember = async () => {
        const confirmed = await confirm({
            title: 'Remove Member',
            description: `Remove ${member.name} from this team? They will lose access to all team resources.`,
            confirmText: 'Remove',
            variant: 'danger',
        });
        if (confirmed) {
            router.post(`/admin/teams/${teamId}/members/${member.id}/remove`);
        }
    };

    const handleChangeRole = async (newRole: string) => {
        router.post(`/admin/teams/${teamId}/members/${member.id}/role`, { role: newRole });
    };

    const roleConfig: Record<string, { variant: 'primary' | 'success' | 'warning' | 'danger' | 'default'; icon: React.ReactNode }> = {
        owner: { variant: 'primary', icon: <ShieldAlert className="h-3 w-3" /> },
        admin: { variant: 'warning', icon: <ShieldCheck className="h-3 w-3" /> },
        member: { variant: 'default', icon: <Shield className="h-3 w-3" /> },
    };

    const config = roleConfig[member.role] || roleConfig.member;

    return (
        <div className="flex items-center justify-between border-b border-border/50 py-3 last:border-0">
            <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-purple-500 text-sm font-medium text-white">
                    {member.name.charAt(0).toUpperCase()}
                </div>
                <div>
                    <div className="flex items-center gap-2">
                        <Link
                            href={`/admin/users/${member.id}`}
                            className="font-medium text-foreground hover:text-primary"
                        >
                            {member.name}
                        </Link>
                        <Badge variant={config.variant} size="sm" icon={config.icon}>
                            {member.role}
                        </Badge>
                    </div>
                    <p className="text-sm text-foreground-muted">{member.email}</p>
                </div>
            </div>

            <div className="flex items-center gap-2">
                <Dropdown>
                    <DropdownTrigger>
                        <Button variant="ghost" size="sm">
                            <MoreHorizontal className="h-4 w-4" />
                        </Button>
                    </DropdownTrigger>
                    <DropdownContent align="right">
                        <DropdownItem onClick={() => router.visit(`/admin/users/${member.id}`)}>
                            <Eye className="h-4 w-4" />
                            View User
                        </DropdownItem>
                        <DropdownDivider />
                        {member.role !== 'owner' && (
                            <DropdownItem onClick={() => handleChangeRole('owner')}>
                                <ShieldAlert className="h-4 w-4" />
                                Make Owner
                            </DropdownItem>
                        )}
                        {member.role !== 'admin' && (
                            <DropdownItem onClick={() => handleChangeRole('admin')}>
                                <ShieldCheck className="h-4 w-4" />
                                Make Admin
                            </DropdownItem>
                        )}
                        {member.role !== 'member' && (
                            <DropdownItem onClick={() => handleChangeRole('member')}>
                                <Shield className="h-4 w-4" />
                                Make Member
                            </DropdownItem>
                        )}
                        {!isPersonalTeam && member.role !== 'owner' && (
                            <>
                                <DropdownDivider />
                                <DropdownItem onClick={handleRemoveMember} className="text-danger">
                                    <UserMinus className="h-4 w-4" />
                                    Remove from Team
                                </DropdownItem>
                            </>
                        )}
                    </DropdownContent>
                </Dropdown>
            </div>
        </div>
    );
}

export default function AdminTeamShow({ team }: Props) {
    const confirm = useConfirm();
    const members = team?.members ?? [];
    const servers = team?.servers ?? [];
    const projects = team?.projects ?? [];

    const handleDeleteTeam = async () => {
        const confirmed = await confirm({
            title: 'Delete Team',
            description: `Are you sure you want to delete "${team.name}"? This will remove all team resources and cannot be undone.`,
            confirmText: 'Delete Team',
            variant: 'danger',
        });
        if (confirmed) {
            router.delete(`/admin/teams/${team.id}`);
        }
    };

    return (
        <AdminLayout
            title={team.name}
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Teams', href: '/admin/teams' },
                { label: team.name },
            ]}
        >
            <div className="mx-auto max-w-7xl">
                {/* Header */}
                <div className="mb-8">
                    <div className="flex items-start justify-between">
                        <div className="flex items-center gap-4">
                            <div className="flex h-16 w-16 items-center justify-center rounded-lg bg-gradient-to-br from-purple-500 to-pink-500 text-2xl font-medium text-white">
                                {team.name.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <div className="flex items-center gap-2">
                                    <h1 className="text-2xl font-semibold text-foreground">{team.name}</h1>
                                    {team.personal_team && (
                                        <Badge variant="default">Personal Team</Badge>
                                    )}
                                </div>
                                {team.description && (
                                    <p className="mt-1 text-sm text-foreground-muted">{team.description}</p>
                                )}
                            </div>
                        </div>
                        {!team.personal_team && team.id !== 0 && (
                            <Button variant="danger" onClick={handleDeleteTeam}>
                                <Trash2 className="h-4 w-4" />
                                Delete Team
                            </Button>
                        )}
                    </div>
                </div>

                {/* Stats */}
                <div className="mb-6 grid gap-4 sm:grid-cols-4">
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Members</p>
                                    <p className="text-2xl font-bold text-primary">{members.length}</p>
                                </div>
                                <Users className="h-8 w-8 text-primary/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Servers</p>
                                    <p className="text-2xl font-bold text-success">{servers.length}</p>
                                </div>
                                <Server className="h-8 w-8 text-success/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Projects</p>
                                    <p className="text-2xl font-bold text-warning">{projects.length}</p>
                                </div>
                                <FolderKanban className="h-8 w-8 text-warning/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Created</p>
                                    <p className="text-lg font-bold text-foreground">
                                        {new Date(team.created_at).toLocaleDateString()}
                                    </p>
                                </div>
                                <Calendar className="h-8 w-8 text-foreground-muted/50" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Members */}
                <Card variant="glass" className="mb-6">
                    <CardHeader>
                        <CardTitle>Members ({members.length})</CardTitle>
                        <CardDescription>Team members and their roles</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {members.length === 0 ? (
                            <p className="py-4 text-center text-sm text-foreground-muted">No members</p>
                        ) : (
                            members.map((member) => (
                                <MemberRow
                                    key={member.id}
                                    member={member}
                                    teamId={team.id}
                                    isPersonalTeam={team.personal_team}
                                />
                            ))
                        )}
                    </CardContent>
                </Card>

                {/* Servers */}
                <Card variant="glass" className="mb-6">
                    <CardHeader>
                        <CardTitle>Servers ({servers.length})</CardTitle>
                        <CardDescription>Servers owned by this team</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {servers.length === 0 ? (
                            <p className="py-4 text-center text-sm text-foreground-muted">No servers</p>
                        ) : (
                            <div className="space-y-2">
                                {servers.map((server) => (
                                    <div
                                        key={server.id}
                                        className="flex items-center justify-between border-b border-border/50 py-3 last:border-0"
                                    >
                                        <div className="flex items-center gap-3">
                                            <Server className="h-5 w-5 text-foreground-muted" />
                                            <div>
                                                <Link
                                                    href={`/server/${server.uuid}`}
                                                    className="font-medium text-foreground hover:text-primary"
                                                >
                                                    {server.name}
                                                </Link>
                                                <p className="text-sm text-foreground-muted">{server.ip}</p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Badge
                                                variant={server.is_reachable ? 'success' : 'danger'}
                                                size="sm"
                                            >
                                                {server.is_reachable ? 'Reachable' : 'Unreachable'}
                                            </Badge>
                                            <Link href={`/server/${server.uuid}`}>
                                                <Button variant="ghost" size="sm">
                                                    <ExternalLink className="h-4 w-4" />
                                                </Button>
                                            </Link>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Projects */}
                <Card variant="glass">
                    <CardHeader>
                        <CardTitle>Projects ({projects.length})</CardTitle>
                        <CardDescription>Projects owned by this team</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {projects.length === 0 ? (
                            <p className="py-4 text-center text-sm text-foreground-muted">No projects</p>
                        ) : (
                            <div className="space-y-2">
                                {projects.map((project) => (
                                    <div
                                        key={project.id}
                                        className="flex items-center justify-between border-b border-border/50 py-3 last:border-0"
                                    >
                                        <div className="flex items-center gap-3">
                                            <FolderKanban className="h-5 w-5 text-foreground-muted" />
                                            <div>
                                                <Link
                                                    href={`/project/${project.uuid}`}
                                                    className="font-medium text-foreground hover:text-primary"
                                                >
                                                    {project.name}
                                                </Link>
                                                <p className="text-sm text-foreground-muted">
                                                    {project.environments_count} environments
                                                </p>
                                            </div>
                                        </div>
                                        <Link href={`/project/${project.uuid}`}>
                                            <Button variant="ghost" size="sm">
                                                <ExternalLink className="h-4 w-4" />
                                            </Button>
                                        </Link>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
