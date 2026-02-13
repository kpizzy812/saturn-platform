import * as React from 'react';
import { Link, router } from '@inertiajs/react';
import { SettingsLayout } from './Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Input, Button, Badge, Modal, ModalFooter, Select } from '@/components/ui';
import { Dropdown, DropdownTrigger, DropdownContent, DropdownItem, DropdownDivider } from '@/components/ui/Dropdown';
import { ConfigureProjectsModal } from '@/components/team/ConfigureProjectsModal';
import { KickMemberModal } from '@/components/team/KickMemberModal';
import { useToast } from '@/components/ui/Toast';
import {
    Mail,
    UserX,
    Crown,
    Shield,
    User as UserIcon,
    Lock,
    Code,
    Copy,
    Check,
    MoreVertical,
    UserCog,
    FolderCog,
    Settings,
    Activity,
    Clock,
    Search,
    Filter,
    Archive
} from 'lucide-react';

interface TeamMember {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    role: 'owner' | 'admin' | 'developer' | 'member' | 'viewer';
    joinedAt: string;
    lastActive: string;
    invitedBy?: {
        id: number;
        name: string;
        email: string;
    };
    projectAccess?: {
        hasFullAccess: boolean;
        hasNoAccess: boolean;
        hasLimitedAccess: boolean;
        count: number;
        total: number;
    };
}

interface Invitation {
    id: number;
    email: string;
    role: 'admin' | 'developer' | 'member' | 'viewer';
    sentAt: string;
    link: string;
}

interface ReceivedInvitation {
    id: number;
    uuid: string;
    teamName: string;
    role: 'admin' | 'developer' | 'member' | 'viewer';
    sentAt: string;
    expiresAt: string;
}

interface Team {
    id: number;
    name: string;
    avatar?: string;
    memberCount: number;
}

interface Props {
    team: Team;
    members: TeamMember[];
    invitations: Invitation[];
    receivedInvitations: ReceivedInvitation[];
    currentUserRole: 'owner' | 'admin' | 'developer' | 'member' | 'viewer';
    canManageTeam: boolean;
    canManageRoles: boolean;
}

export default function TeamSettings({
    team,
    members: initialMembers,
    invitations: initialInvitations,
    receivedInvitations: initialReceivedInvitations = [],
    currentUserRole,
    canManageTeam,
    canManageRoles,
}: Props) {
    const [members, setMembers] = React.useState<TeamMember[]>(initialMembers);
    const [invitations, setInvitations] = React.useState<Invitation[]>(initialInvitations);
    const [receivedInvitations, setReceivedInvitations] = React.useState<ReceivedInvitation[]>(initialReceivedInvitations);

    // Sync state with props when Inertia reloads data
    React.useEffect(() => {
        setMembers(initialMembers);
    }, [initialMembers]);

    React.useEffect(() => {
        setInvitations(initialInvitations);
    }, [initialInvitations]);

    React.useEffect(() => {
        setReceivedInvitations(initialReceivedInvitations);
    }, [initialReceivedInvitations]);
    const [showInviteModal, setShowInviteModal] = React.useState(false);
    const [showKickModal, setShowKickModal] = React.useState(false);
    const [showRoleModal, setShowRoleModal] = React.useState(false);
    const [showProjectsModal, setShowProjectsModal] = React.useState(false);
    const [selectedMember, setSelectedMember] = React.useState<TeamMember | null>(null);
    const [inviteEmail, setInviteEmail] = React.useState('');
    const [inviteRole, setInviteRole] = React.useState<'admin' | 'developer' | 'member' | 'viewer'>('member');
    const [newRole, setNewRole] = React.useState<TeamMember['role']>('member');
    const [isInviting, setIsInviting] = React.useState(false);
    const [isChangingRole, setIsChangingRole] = React.useState(false);
    const [copiedLinkId, setCopiedLinkId] = React.useState<number | null>(null);
    const [processingInviteId, setProcessingInviteId] = React.useState<number | null>(null);
    const [searchQuery, setSearchQuery] = React.useState('');
    const [roleFilter, setRoleFilter] = React.useState<string>('all');
    const { toast } = useToast();

    const handleCopyLink = (invitation: Invitation) => {
        // Fallback for HTTP (clipboard API requires HTTPS)
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(invitation.link);
        } else {
            // Fallback using textarea
            const textarea = document.createElement('textarea');
            textarea.value = invitation.link;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
        }
        setCopiedLinkId(invitation.id);
        setTimeout(() => setCopiedLinkId(null), 2000);
    };

    const handleInviteMember = (e: React.FormEvent) => {
        e.preventDefault();
        setIsInviting(true);

        router.post('/settings/team/invite', {
            email: inviteEmail,
            role: inviteRole,
        }, {
            onSuccess: () => {
                setInviteEmail('');
                setInviteRole('member');
                setShowInviteModal(false);
                router.reload();
            },
            onFinish: () => {
                setIsInviting(false);
            },
        });
    };

    const handleChangeRole = () => {
        if (selectedMember) {
            setIsChangingRole(true);

            router.post(`/settings/team/members/${selectedMember.id}/role`, { role: newRole }, {
                onSuccess: () => {
                    setMembers(members.map(m =>
                        m.id === selectedMember.id ? { ...m, role: newRole } : m
                    ));
                    toast({
                        title: 'Role updated',
                        description: `${selectedMember.name}'s role has been updated to ${newRole}.`,
                    });
                    setShowRoleModal(false);
                    setSelectedMember(null);
                },
                onError: () => {
                    toast({
                        title: 'Failed to update role',
                        description: 'An error occurred while updating the member role.',
                        variant: 'error',
                    });
                },
                onFinish: () => {
                    setIsChangingRole(false);
                }
            });
        }
    };

    const handleRevokeInvitation = (invitationId: number) => {
        router.delete(`/settings/team/invitations/${invitationId}`, {
            onSuccess: () => {
                router.reload();
            },
        });
    };

    const handleAcceptInvitation = (uuid: string, invitationId: number) => {
        setProcessingInviteId(invitationId);
        router.post(`/invitations/${uuid}/accept`, {}, {
            onSuccess: () => {
                router.reload();
            },
            onFinish: () => {
                setProcessingInviteId(null);
            },
        });
    };

    const handleDeclineInvitation = (uuid: string, invitationId: number) => {
        setProcessingInviteId(invitationId);
        router.post(`/invitations/${uuid}/decline`, {}, {
            onSuccess: () => {
                router.reload({ only: ['receivedInvitations'] });
            },
            onFinish: () => {
                setProcessingInviteId(null);
            },
        });
    };

    const getRoleIcon = (role: string) => {
        switch (role) {
            case 'owner':
                return <Crown className="h-4 w-4" />;
            case 'admin':
                return <Shield className="h-4 w-4" />;
            case 'developer':
                return <Code className="h-4 w-4" />;
            case 'viewer':
                return <Lock className="h-4 w-4" />;
            default:
                return <UserIcon className="h-4 w-4" />;
        }
    };

    const getRoleBadgeVariant = (role: string): 'default' | 'success' | 'warning' | 'info' => {
        switch (role) {
            case 'owner':
                return 'warning';
            case 'admin':
                return 'success';
            case 'viewer':
                return 'info';
            default:
                return 'default';
        }
    };

    const formatLastActive = (timestamp: string) => {
        const date = new Date(timestamp);
        const now = new Date();
        const diffMs = now.getTime() - date.getTime();
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMins / 60);
        const diffDays = Math.floor(diffHours / 24);

        if (diffMins < 5) return 'Just now';
        if (diffMins < 60) return `${diffMins}m ago`;
        if (diffHours < 24) return `${diffHours}h ago`;
        if (diffDays === 1) return 'Yesterday';
        if (diffDays < 7) return `${diffDays}d ago`;
        return date.toLocaleDateString();
    };

    const getInitials = (name: string) => {
        return name
            .split(' ')
            .map(n => n[0])
            .join('')
            .toUpperCase()
            .slice(0, 2);
    };

    const isOnline = (lastActive: string) => {
        const diff = Date.now() - new Date(lastActive).getTime();
        return diff < 5 * 60 * 1000; // 5 minutes
    };

    // Filter members based on search and role filter
    const filteredMembers = React.useMemo(() => {
        return members.filter(member => {
            const matchesSearch = searchQuery === '' ||
                member.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                member.email.toLowerCase().includes(searchQuery.toLowerCase());
            const matchesRole = roleFilter === 'all' || member.role === roleFilter;
            return matchesSearch && matchesRole;
        });
    }, [members, searchQuery, roleFilter]);

    const onlineMembers = members.filter(m => isOnline(m.lastActive)).length;

    return (
        <SettingsLayout activeSection="team">
            <div className="space-y-6">
                {/* Team Overview Card */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-4">
                                <div className="flex h-16 w-16 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-purple-500 text-2xl font-semibold text-white">
                                    {team.avatar ? (
                                        <img src={team.avatar} alt={team.name} className="h-full w-full rounded-lg object-cover" />
                                    ) : (
                                        getInitials(team.name)
                                    )}
                                </div>
                                <div>
                                    <CardTitle>{team.name}</CardTitle>
                                    <CardDescription>
                                        {members.length} member{members.length !== 1 ? 's' : ''}
                                    </CardDescription>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <Link href="/settings/team/activity">
                                    <Button variant="secondary" size="sm">
                                        <Activity className="mr-2 h-4 w-4" />
                                        Activity
                                    </Button>
                                </Link>
                                {canManageTeam && (
                                    <>
                                        <Link href="/settings/team/permission-sets">
                                            <Button variant="secondary" size="sm">
                                                <Shield className="mr-2 h-4 w-4" />
                                                Permission Sets
                                            </Button>
                                        </Link>
                                        <Link href="/settings/team/archives">
                                            <Button variant="secondary" size="sm">
                                                <Archive className="mr-2 h-4 w-4" />
                                                Archives
                                            </Button>
                                        </Link>
                                    </>
                                )}
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-3 gap-4">
                            <div className="rounded-lg bg-background-tertiary p-4 text-center">
                                <p className="text-2xl font-bold text-foreground">{members.length}</p>
                                <p className="text-sm text-foreground-muted">Total Members</p>
                            </div>
                            <div className="rounded-lg bg-background-tertiary p-4 text-center">
                                <div className="flex items-center justify-center gap-2">
                                    <div className="h-2 w-2 rounded-full bg-green-500 animate-pulse" />
                                    <p className="text-2xl font-bold text-foreground">{onlineMembers}</p>
                                </div>
                                <p className="text-sm text-foreground-muted">Online Now</p>
                            </div>
                            <div className="rounded-lg bg-background-tertiary p-4 text-center">
                                <p className="text-2xl font-bold text-foreground">{invitations.length}</p>
                                <p className="text-sm text-foreground-muted">Pending Invites</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Team Members */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Team Members</CardTitle>
                                <CardDescription>
                                    Manage who has access to your team
                                </CardDescription>
                            </div>
                            {canManageTeam && (
                                <Button onClick={() => setShowInviteModal(true)}>
                                    <Mail className="mr-2 h-4 w-4" />
                                    Invite Member
                                </Button>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        {/* Search and Filter */}
                        <div className="mb-4 flex gap-3">
                            <div className="relative flex-1">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                                <Input
                                    type="text"
                                    placeholder="Search members by name or email..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="pl-10"
                                />
                            </div>
                            <Select
                                value={roleFilter}
                                onChange={(e) => setRoleFilter(e.target.value)}
                                className="w-40"
                            >
                                <option value="all">All Roles</option>
                                <option value="owner">Owner</option>
                                <option value="admin">Admin</option>
                                <option value="developer">Developer</option>
                                <option value="member">Member</option>
                                <option value="viewer">Viewer</option>
                            </Select>
                        </div>

                        {/* Member count */}
                        {searchQuery || roleFilter !== 'all' ? (
                            <p className="mb-3 text-sm text-foreground-muted">
                                Showing {filteredMembers.length} of {members.length} members
                            </p>
                        ) : null}

                        <div className="space-y-3">
                            {filteredMembers.map((member) => (
                                <div
                                    key={member.id}
                                    className="flex items-center justify-between rounded-lg border border-border bg-background p-4 transition-all hover:border-border/80 hover:shadow-sm"
                                >
                                    <div className="flex items-center gap-4">
                                        <Link href={`/settings/members/${member.id}`}>
                                            <div className="relative">
                                                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-purple-500 text-sm font-semibold text-white transition-transform hover:scale-105">
                                                    {member.avatar ? (
                                                        <img src={member.avatar} alt={member.name} className="h-full w-full rounded-full object-cover" />
                                                    ) : (
                                                        getInitials(member.name)
                                                    )}
                                                </div>
                                                {/* Online status indicator */}
                                                {isOnline(member.lastActive) && (
                                                    <div className="absolute bottom-0 right-0 h-3 w-3 rounded-full border-2 border-background bg-green-500 ring-2 ring-green-400/20" />
                                                )}
                                            </div>
                                        </Link>
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <Link href={`/settings/members/${member.id}`}>
                                                    <p className="font-medium text-foreground hover:text-primary transition-colors">
                                                        {member.name}
                                                    </p>
                                                </Link>
                                                <Badge variant={getRoleBadgeVariant(member.role)}>
                                                    <span className="mr-1">{getRoleIcon(member.role)}</span>
                                                    {member.role}
                                                </Badge>
                                                {/* Project Access Badge */}
                                                {member.role !== 'owner' && member.projectAccess && (
                                                    <>
                                                        {member.projectAccess.hasFullAccess && (
                                                            <Badge
                                                                variant="success"
                                                                className="text-[10px] cursor-help"
                                                                title="This member has access to all projects in the team"
                                                            >
                                                                <FolderCog className="h-3 w-3 mr-1" />
                                                                All Projects
                                                            </Badge>
                                                        )}
                                                        {member.projectAccess.hasLimitedAccess && (
                                                            <Badge
                                                                variant="warning"
                                                                className="text-[10px] cursor-help"
                                                                title={`This member has access to ${member.projectAccess.count} out of ${member.projectAccess.total} projects`}
                                                            >
                                                                <FolderCog className="h-3 w-3 mr-1" />
                                                                {member.projectAccess.count}/{member.projectAccess.total} Projects
                                                            </Badge>
                                                        )}
                                                        {member.projectAccess.hasNoAccess && (
                                                            <Badge
                                                                variant="danger"
                                                                className="text-[10px] cursor-help"
                                                                title="This member has no access to any projects"
                                                            >
                                                                <Lock className="h-3 w-3 mr-1" />
                                                                No Access
                                                            </Badge>
                                                        )}
                                                    </>
                                                )}
                                            </div>
                                            <div className="space-y-1">
                                                <div className="flex items-center gap-3 text-sm text-foreground-muted">
                                                    <span>{member.email}</span>
                                                    <span className="text-foreground-subtle">•</span>
                                                    <div className="flex items-center gap-1 text-foreground-subtle">
                                                        <Clock className="h-3 w-3" />
                                                        <span>{formatLastActive(member.lastActive)}</span>
                                                    </div>
                                                </div>
                                                {/* Show inviter information */}
                                                {member.invitedBy && (
                                                    <div className="flex items-center gap-1 text-xs text-foreground-subtle">
                                                        <UserIcon className="h-3 w-3" />
                                                        <span>
                                                            Invited by{' '}
                                                            <span className="font-medium text-foreground-muted">
                                                                {member.invitedBy.name}
                                                            </span>
                                                        </span>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <p className="text-xs text-foreground-subtle">
                                            Joined {new Date(member.joinedAt).toLocaleDateString()}
                                        </p>
                                        {member.role !== 'owner' && (
                                            <Dropdown>
                                                <DropdownTrigger>
                                                    <Button variant="ghost" size="icon">
                                                        <MoreVertical className="h-4 w-4" />
                                                    </Button>
                                                </DropdownTrigger>
                                                <DropdownContent>
                                                    <Link href={`/settings/members/${member.id}`}>
                                                        <DropdownItem>
                                                            <UserIcon className="h-4 w-4" />
                                                            View Profile
                                                        </DropdownItem>
                                                    </Link>
                                                    {canManageTeam && (
                                                        <>
                                                            <DropdownItem
                                                                onClick={() => {
                                                                    setSelectedMember(member);
                                                                    setNewRole(member.role);
                                                                    setShowRoleModal(true);
                                                                }}
                                                            >
                                                                <UserCog className="h-4 w-4" />
                                                                Change Role
                                                            </DropdownItem>
                                                            {/* Configure Projects - for all except owner */}
                                                            <DropdownItem
                                                                onClick={() => {
                                                                    setSelectedMember(member);
                                                                    setShowProjectsModal(true);
                                                                }}
                                                            >
                                                                <FolderCog className="h-4 w-4" />
                                                                <span className="flex items-center gap-2">
                                                                    Configure Projects
                                                                    {member.projectAccess?.hasNoAccess && (
                                                                        <Badge variant="danger" className="text-[10px] px-1 py-0">
                                                                            No Access
                                                                        </Badge>
                                                                    )}
                                                                    {member.projectAccess?.hasLimitedAccess && (
                                                                        <Badge variant="warning" className="text-[10px] px-1 py-0">
                                                                            {member.projectAccess.count}/{member.projectAccess.total}
                                                                        </Badge>
                                                                    )}
                                                                    {member.projectAccess?.hasFullAccess && (
                                                                        <Badge variant="success" className="text-[10px] px-1 py-0">
                                                                            Full
                                                                        </Badge>
                                                                    )}
                                                                </span>
                                                            </DropdownItem>
                                                            <DropdownDivider />
                                                            <DropdownItem
                                                                danger
                                                                onClick={() => {
                                                                    setSelectedMember(member);
                                                                    setShowKickModal(true);
                                                                }}
                                                            >
                                                                <UserX className="h-4 w-4" />
                                                                Remove Member
                                                            </DropdownItem>
                                                        </>
                                                    )}
                                                </DropdownContent>
                                            </Dropdown>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                {/* Pending Invitations */}
                {invitations.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Pending Invitations</CardTitle>
                            <CardDescription>
                                Invitations that haven't been accepted yet
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {invitations.map((invitation) => (
                                    <div
                                        key={invitation.id}
                                        className="flex items-center justify-between rounded-lg border border-border bg-background p-4"
                                    >
                                        <div className="flex items-center gap-4">
                                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-background-tertiary">
                                                <Mail className="h-5 w-5 text-foreground-muted" />
                                            </div>
                                            <div>
                                                <p className="font-medium text-foreground">{invitation.email}</p>
                                                <p className="text-sm text-foreground-muted">
                                                    Invited as {invitation.role}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <p className="text-xs text-foreground-subtle">
                                                Sent {new Date(invitation.sentAt).toLocaleDateString()}
                                            </p>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => handleCopyLink(invitation)}
                                                title="Copy invitation link"
                                            >
                                                {copiedLinkId === invitation.id ? (
                                                    <Check className="h-4 w-4 text-success" />
                                                ) : (
                                                    <Copy className="h-4 w-4" />
                                                )}
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => handleRevokeInvitation(invitation.id)}
                                            >
                                                Revoke
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Received Invitations */}
                {receivedInvitations.length > 0 && (
                    <Card className="border-primary/30 bg-primary/5">
                        <CardHeader>
                            <CardTitle>Team Invitations</CardTitle>
                            <CardDescription>
                                You have been invited to join {receivedInvitations.length} {receivedInvitations.length === 1 ? 'team' : 'teams'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {receivedInvitations.map((invitation) => (
                                    <div
                                        key={invitation.id}
                                        className="flex items-center justify-between rounded-lg border border-border bg-background p-4"
                                    >
                                        <div className="flex items-center gap-4">
                                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary/20">
                                                <Mail className="h-5 w-5 text-primary" />
                                            </div>
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <p className="font-medium text-foreground">{invitation.teamName}</p>
                                                    <Badge variant="default">
                                                        {invitation.role}
                                                    </Badge>
                                                </div>
                                                <p className="text-sm text-foreground-muted">
                                                    Invited {new Date(invitation.sentAt).toLocaleDateString()}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Button
                                                variant="secondary"
                                                size="sm"
                                                onClick={() => handleDeclineInvitation(invitation.uuid, invitation.id)}
                                                disabled={processingInviteId === invitation.id}
                                            >
                                                Decline
                                            </Button>
                                            <Button
                                                variant="default"
                                                size="sm"
                                                onClick={() => handleAcceptInvitation(invitation.uuid, invitation.id)}
                                                loading={processingInviteId === invitation.id}
                                            >
                                                Accept
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* Invite Member Modal */}
            <Modal
                isOpen={showInviteModal}
                onClose={() => setShowInviteModal(false)}
                title="Invite Team Member"
                description="Send an invitation to join your team"
            >
                <form onSubmit={handleInviteMember}>
                    <div className="space-y-4">
                        <Input
                            label="Email Address"
                            type="email"
                            value={inviteEmail}
                            onChange={(e) => setInviteEmail(e.target.value)}
                            placeholder="colleague@example.com"
                            required
                        />

                        <Select
                            label="Role"
                            value={inviteRole}
                            onChange={(e) => setInviteRole(e.target.value as 'admin' | 'developer' | 'member' | 'viewer')}
                        >
                            <option value="admin">Admin - Full access except billing</option>
                            <option value="developer">Developer - Deploy and manage resources</option>
                            <option value="member">Member - Can view and deploy</option>
                            <option value="viewer">Viewer - Read-only access</option>
                        </Select>
                    </div>

                    <ModalFooter>
                        <Button type="button" variant="secondary" onClick={() => setShowInviteModal(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" loading={isInviting}>
                            Send Invitation
                        </Button>
                    </ModalFooter>
                </form>
            </Modal>

            {/* Change Role Modal */}
            <Modal
                isOpen={showRoleModal}
                onClose={() => setShowRoleModal(false)}
                title="Change Member Role"
                description={`Update the role for ${selectedMember?.name}`}
            >
                <div className="space-y-4">
                    <div className="space-y-2">
                        {(['owner', 'admin', 'developer', 'member', 'viewer'] as const).map((role) => (
                            <button
                                key={role}
                                type="button"
                                onClick={() => setNewRole(role)}
                                className={`flex w-full items-center gap-3 rounded-lg border p-3 transition-all ${
                                    newRole === role
                                        ? 'border-primary bg-primary/10'
                                        : 'border-border hover:border-border/80'
                                }`}
                            >
                                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-background-tertiary">
                                    {getRoleIcon(role)}
                                </div>
                                <div className="flex-1 text-left">
                                    <p className="font-medium text-foreground capitalize">{role}</p>
                                    <p className="text-xs text-foreground-muted">
                                        {role === 'owner' && 'Full control of the team and billing'}
                                        {role === 'admin' && 'Manage team members and settings'}
                                        {role === 'developer' && 'Deploy and manage resources'}
                                        {role === 'member' && 'View resources and basic operations'}
                                        {role === 'viewer' && 'Read-only access to resources'}
                                    </p>
                                </div>
                                {selectedMember?.role === role && role !== newRole && (
                                    <Badge variant="default">Current</Badge>
                                )}
                            </button>
                        ))}
                    </div>
                </div>

                <ModalFooter>
                    <Button variant="secondary" onClick={() => setShowRoleModal(false)} disabled={isChangingRole}>
                        Cancel
                    </Button>
                    <Button onClick={handleChangeRole} disabled={newRole === selectedMember?.role} loading={isChangingRole}>
                        Update Role
                    </Button>
                </ModalFooter>
            </Modal>

            {/* Kick Member Modal */}
            {selectedMember && (
                <KickMemberModal
                    isOpen={showKickModal}
                    onClose={() => {
                        setShowKickModal(false);
                        setSelectedMember(null);
                        router.reload();
                    }}
                    member={selectedMember}
                    teamMembers={members
                        .filter(m => m.id !== selectedMember.id && m.role !== 'owner')
                        .map(m => ({ id: m.id, name: m.name, email: m.email }))}
                />
            )}

            {/* Configure Projects Modal */}
            <ConfigureProjectsModal
                isOpen={showProjectsModal}
                onClose={() => setShowProjectsModal(false)}
                member={selectedMember}
                onSuccess={() => {
                    toast({
                        title: 'Project access updated',
                        description: `${selectedMember?.name}'s project access has been updated.`,
                    });
                }}
            />
        </SettingsLayout>
    );
}
