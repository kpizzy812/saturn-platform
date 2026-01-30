import * as React from 'react';
import { router } from '@inertiajs/react';
import { SettingsLayout } from './Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Input, Button, Badge, Modal, ModalFooter, Select } from '@/components/ui';
import { Mail, UserX, Crown, Shield, User as UserIcon, Lock, Code, Copy, Check } from 'lucide-react';

interface TeamMember {
    id: number;
    name: string;
    email: string;
    role: 'owner' | 'admin' | 'developer' | 'member' | 'viewer';
    joinedAt: string;
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

interface Props {
    members: TeamMember[];
    invitations: Invitation[];
    receivedInvitations: ReceivedInvitation[];
}

export default function TeamSettings({ members: initialMembers, invitations: initialInvitations, receivedInvitations: initialReceivedInvitations = [] }: Props) {
    const [members, setMembers] = React.useState<TeamMember[]>(initialMembers);
    const [invitations, setInvitations] = React.useState<Invitation[]>(initialInvitations);
    const [receivedInvitations, setReceivedInvitations] = React.useState<ReceivedInvitation[]>(initialReceivedInvitations);
    const [showInviteModal, setShowInviteModal] = React.useState(false);
    const [showRemoveModal, setShowRemoveModal] = React.useState(false);
    const [memberToRemove, setMemberToRemove] = React.useState<TeamMember | null>(null);
    const [inviteEmail, setInviteEmail] = React.useState('');
    const [inviteRole, setInviteRole] = React.useState<'admin' | 'developer' | 'member' | 'viewer'>('member');
    const [isInviting, setIsInviting] = React.useState(false);
    const [copiedLinkId, setCopiedLinkId] = React.useState<number | null>(null);
    const [processingInviteId, setProcessingInviteId] = React.useState<number | null>(null);

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
