import * as React from 'react';
import { router } from '@inertiajs/react';
import { SettingsLayout } from './Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Input, Button, Badge, Modal, ModalFooter, Select } from '@/components/ui';
import { Mail, UserX, Crown, Shield, User as UserIcon } from 'lucide-react';

interface TeamMember {
    id: number;
    name: string;
    email: string;
    role: 'owner' | 'admin' | 'member';
    joinedAt: string;
}

interface Invitation {
    id: number;
    email: string;
    role: 'admin' | 'member';
    sentAt: string;
}

interface Props {
    members: TeamMember[];
    invitations: Invitation[];
}

export default function TeamSettings({ members: initialMembers, invitations: initialInvitations }: Props) {
    const [members, setMembers] = React.useState<TeamMember[]>(initialMembers);
    const [invitations, setInvitations] = React.useState<Invitation[]>(initialInvitations);
    const [showInviteModal, setShowInviteModal] = React.useState(false);
    const [showRemoveModal, setShowRemoveModal] = React.useState(false);
    const [memberToRemove, setMemberToRemove] = React.useState<TeamMember | null>(null);
    const [inviteEmail, setInviteEmail] = React.useState('');
    const [inviteRole, setInviteRole] = React.useState<'admin' | 'member'>('member');
    const [isInviting, setIsInviting] = React.useState(false);

    const handleInviteMember = (e: React.FormEvent) => {
        e.preventDefault();
        setIsInviting(true);

        router.post('/api/v1/teams/invitations', {
            email: inviteEmail,
            role: inviteRole,
        }, {
            onSuccess: () => {
                setInviteEmail('');
                setInviteRole('member');
                setShowInviteModal(false);
                router.reload({ only: ['invitations'] });
            },
            onFinish: () => {
                setIsInviting(false);
            },
        });
    };

    const handleRemoveMember = () => {
        if (memberToRemove) {
            router.delete(`/api/v1/teams/members/${memberToRemove.id}`, {
                onSuccess: () => {
                    setMemberToRemove(null);
                    setShowRemoveModal(false);
                    router.reload({ only: ['members'] });
                },
            });
        }
    };

    const handleRevokeInvitation = (invitationId: number) => {
        router.delete(`/api/v1/teams/invitations/${invitationId}`, {
            onSuccess: () => {
                router.reload({ only: ['invitations'] });
            },
        });
    };

    const getRoleIcon = (role: string) => {
        switch (role) {
            case 'owner':
                return <Crown className="h-4 w-4" />;
            case 'admin':
                return <Shield className="h-4 w-4" />;
            default:
                return <UserIcon className="h-4 w-4" />;
        }
    };

    const getRoleBadgeVariant = (role: string): 'default' | 'success' | 'warning' => {
        switch (role) {
            case 'owner':
                return 'warning';
            case 'admin':
                return 'success';
            default:
                return 'default';
        }
    };

    return (
        <SettingsLayout activeSection="team">
            <div className="space-y-6">
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
                            <Button onClick={() => setShowInviteModal(true)}>
                                <Mail className="mr-2 h-4 w-4" />
                                Invite Member
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {members.map((member) => (
                                <div
                                    key={member.id}
                                    className="flex items-center justify-between rounded-lg border border-border bg-background p-4 transition-colors hover:border-border/80"
                                >
                                    <div className="flex items-center gap-4">
                                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary text-sm font-semibold text-white">
                                            {member.name.charAt(0).toUpperCase()}
                                        </div>
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <p className="font-medium text-foreground">{member.name}</p>
                                                <Badge variant={getRoleBadgeVariant(member.role)}>
                                                    <span className="mr-1">{getRoleIcon(member.role)}</span>
                                                    {member.role}
                                                </Badge>
                                            </div>
                                            <p className="text-sm text-foreground-muted">{member.email}</p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <p className="text-xs text-foreground-subtle">
                                            Joined {new Date(member.joinedAt).toLocaleDateString()}
                                        </p>
                                        {member.role !== 'owner' && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => {
                                                    setMemberToRemove(member);
                                                    setShowRemoveModal(true);
                                                }}
                                            >
                                                <UserX className="h-4 w-4" />
                                            </Button>
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
                                        <div className="flex items-center gap-3">
                                            <p className="text-xs text-foreground-subtle">
                                                Sent {new Date(invitation.sentAt).toLocaleDateString()}
                                            </p>
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
                            onChange={(e) => setInviteRole(e.target.value as 'admin' | 'member')}
                        >
                            <option value="member">Member - Can view and deploy</option>
                            <option value="admin">Admin - Full access except billing</option>
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

            {/* Remove Member Modal */}
            <Modal
                isOpen={showRemoveModal}
                onClose={() => setShowRemoveModal(false)}
                title="Remove Team Member"
                description={`Are you sure you want to remove ${memberToRemove?.name} from the team? They will lose access to all team resources.`}
            >
                <ModalFooter>
                    <Button variant="secondary" onClick={() => setShowRemoveModal(false)}>
                        Cancel
                    </Button>
                    <Button variant="danger" onClick={handleRemoveMember}>
                        Remove Member
                    </Button>
                </ModalFooter>
            </Modal>
        </SettingsLayout>
    );
}
