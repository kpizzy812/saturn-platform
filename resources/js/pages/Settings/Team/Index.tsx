import * as React from 'react';
import { SettingsLayout } from '../Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Dropdown, DropdownTrigger, DropdownContent, DropdownItem, DropdownDivider } from '@/components/ui/Dropdown';
import { Modal, ModalFooter } from '@/components/ui/Modal';
import { Input } from '@/components/ui/Input';
import { useToast } from '@/components/ui/Toast';
import { Link, router } from '@inertiajs/react';
import {
    Users,
    Mail,
    UserX,
    Crown,
    Shield,
    User as UserIcon,
    MoreVertical,
    UserCog,
    Settings,
    Activity,
    Lock
} from 'lucide-react';

interface TeamMember {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    role: 'owner' | 'admin' | 'member' | 'viewer';
    joinedAt: string;
    lastActive: string;
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
}

export default function TeamIndex({ team, members: initialMembers }: Props) {
    const [members, setMembers] = React.useState<TeamMember[]>(initialMembers);
    const [showRemoveModal, setShowRemoveModal] = React.useState(false);
    const [showRoleModal, setShowRoleModal] = React.useState(false);
    const [selectedMember, setSelectedMember] = React.useState<TeamMember | null>(null);
    const [newRole, setNewRole] = React.useState<TeamMember['role']>('member');
    const [isChangingRole, setIsChangingRole] = React.useState(false);
    const [isRemoving, setIsRemoving] = React.useState(false);
    const { addToast } = useToast();

    const getRoleIcon = (role: string) => {
        switch (role) {
            case 'owner':
                return <Crown className="h-4 w-4" />;
            case 'admin':
                return <Shield className="h-4 w-4" />;
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

    const handleChangeRole = () => {
        if (selectedMember) {
            setIsChangingRole(true);

            router.post(`/settings/team/members/${selectedMember.id}/role`, { role: newRole }, {
                onSuccess: () => {
                    setMembers(members.map(m =>
                        m.id === selectedMember.id ? { ...m, role: newRole } : m
                    ));
                    addToast({
                        title: 'Role updated',
                        description: `${selectedMember.name}'s role has been updated to ${newRole}.`,
                    });
                    setShowRoleModal(false);
                    setSelectedMember(null);
                },
                onError: (errors) => {
                    addToast({
                        title: 'Failed to update role',
                        description: 'An error occurred while updating the member role.',
                        variant: 'danger',
                    });
                    console.error(errors);
                },
                onFinish: () => {
                    setIsChangingRole(false);
                }
            });
        }
    };

    const handleRemoveMember = () => {
        if (selectedMember) {
            setIsRemoving(true);

            router.delete(`/settings/team/members/${selectedMember.id}`, {
                onSuccess: () => {
                    setMembers(members.filter(m => m.id !== selectedMember.id));
                    addToast({
                        title: 'Member removed',
                        description: `${selectedMember.name} has been removed from the team.`,
                    });
                    setShowRemoveModal(false);
                    setSelectedMember(null);
                },
                onError: (errors) => {
                    addToast({
                        title: 'Failed to remove member',
                        description: 'An error occurred while removing the team member.',
                        variant: 'danger',
                    });
                    console.error(errors);
                },
                onFinish: () => {
                    setIsRemoving(false);
                }
            });
        }
    };

    const getInitials = (name: string) => {
        return name
            .split(' ')
            .map(n => n[0])
            .join('')
            .toUpperCase()
            .slice(0, 2);
    };

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
                                <Link href="/settings/team/roles">
                                    <Button variant="secondary" size="sm">
                                        <Settings className="mr-2 h-4 w-4" />
                                        Roles
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </CardHeader>
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
                            <Link href="/settings/team/invite">
                                <Button>
                                    <Mail className="mr-2 h-4 w-4" />
                                    Invite Member
                                </Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {members.map((member) => (
                                <div
                                    key={member.id}
                                    className="flex items-center justify-between rounded-lg border border-border bg-background p-4 transition-all hover:border-border/80 hover:shadow-sm"
                                >
                                    <div className="flex items-center gap-4">
                                        <Link href={`/settings/members/${member.id}`}>
                                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-purple-500 text-sm font-semibold text-white transition-transform hover:scale-105">
                                                {member.avatar ? (
                                                    <img src={member.avatar} alt={member.name} className="h-full w-full rounded-full object-cover" />
                                                ) : (
                                                    getInitials(member.name)
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
                                            </div>
                                            <div className="flex items-center gap-3 text-sm text-foreground-muted">
                                                <span>{member.email}</span>
                                                <span className="text-foreground-subtle">•</span>
                                                <span className="text-foreground-subtle">
                                                    Active {formatLastActive(member.lastActive)}
                                                </span>
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
                                                    <DropdownDivider />
                                                    <DropdownItem
                                                        danger
                                                        onClick={() => {
                                                            setSelectedMember(member);
                                                            setShowRemoveModal(true);
                                                        }}
                                                    >
                                                        <UserX className="h-4 w-4" />
                                                        Remove Member
                                                    </DropdownItem>
                                                </DropdownContent>
                                            </Dropdown>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Change Role Modal */}
            <Modal
                isOpen={showRoleModal}
                onClose={() => setShowRoleModal(false)}
                title="Change Member Role"
                description={`Update the role for ${selectedMember?.name}`}
            >
                <div className="space-y-4">
                    <div className="space-y-2">
                        {(['owner', 'admin', 'member', 'viewer'] as const).map((role) => (
                            <button
                                key={role}
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
                                        {role === 'member' && 'Deploy and manage resources'}
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

            {/* Remove Member Modal */}
            <Modal
                isOpen={showRemoveModal}
                onClose={() => setShowRemoveModal(false)}
                title="Remove Team Member"
                description={`Are you sure you want to remove ${selectedMember?.name} from the team? They will lose access to all team resources.`}
            >
                <ModalFooter>
                    <Button variant="secondary" onClick={() => setShowRemoveModal(false)} disabled={isRemoving}>
                        Cancel
                    </Button>
                    <Button variant="danger" onClick={handleRemoveMember} loading={isRemoving}>
                        Remove Member
                    </Button>
                </ModalFooter>
            </Modal>
        </SettingsLayout>
    );
}
