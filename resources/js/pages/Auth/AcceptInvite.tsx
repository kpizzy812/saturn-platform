import { useForm, Link } from '@inertiajs/react';
import { AuthLayout } from '@/components/layout';
import { Button, Badge, useConfirm } from '@/components/ui';
import { Users, UserCheck, Shield, Mail, X } from 'lucide-react';

interface Props {
    invitation: {
        id: string;
        team_name: string;
        team_logo?: string;
        inviter_name: string;
        inviter_email: string;
        role: string;
        expires_at: string;
    } | null;
    error?: string;
    isAuthenticated: boolean;
}

export default function AcceptInvite({ invitation, error, isAuthenticated }: Props) {
    const confirm = useConfirm();
    const { post, processing } = useForm();

    // Handle expired or invalid invitation
    if (!invitation || error) {
        return (
            <AuthLayout
                title="Invalid Invitation"
                subtitle="This invitation is no longer valid."
            >
                <div className="space-y-6">
                    <div className="rounded-lg border border-danger/30 bg-danger/5 p-6">
                        <p className="text-center text-foreground">
                            {error || 'This invitation has expired or is no longer valid.'}
                        </p>
                    </div>
                    <div className="text-center">
                        <Link href={isAuthenticated ? '/dashboard' : '/login'}>
                            <Button variant="default">
                                {isAuthenticated ? 'Go to Dashboard' : 'Go to Login'}
                            </Button>
                        </Link>
                    </div>
                </div>
            </AuthLayout>
        );
    }

    const handleAccept = () => {
        post(`/invitations/${invitation.id}/accept`, {
            onSuccess: () => {
                window.location.href = '/dashboard';
            },
        });
    };

    const handleDecline = async () => {
        const confirmed = await confirm({
            title: 'Decline Invitation',
            description: 'Are you sure you want to decline this invitation?',
            confirmText: 'Decline',
            variant: 'danger',
        });
        if (confirmed) {
            post(`/invitations/${invitation.id}/decline`, {
                onSuccess: () => {
                    window.location.href = isAuthenticated ? '/dashboard' : '/login';
                },
            });
        }
    };

    const getRoleBadgeVariant = (role: string) => {
        switch (role.toLowerCase()) {
            case 'owner':
                return 'default';
            case 'admin':
                return 'success';
            case 'member':
                return 'secondary';
            default:
                return 'secondary';
        }
    };

    const formatExpiryDate = (dateString: string) => {
        const date = new Date(dateString);
        const now = new Date();
        const diffTime = date.getTime() - now.getTime();
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

        if (diffDays < 0) {
            return 'Expired';
        } else if (diffDays === 0) {
            return 'Expires today';
        } else if (diffDays === 1) {
            return 'Expires tomorrow';
        } else {
            return `Expires in ${diffDays} days`;
        }
    };

    return (
        <AuthLayout
            title="Team Invitation"
            subtitle="You've been invited to join a team on Saturn."
        >
            <div className="space-y-6">
                {/* Team Info Card */}
                <div className="rounded-lg border border-border bg-background p-6">
                    <div className="flex items-start gap-4">
                        {/* Team Logo/Icon */}
                        <div className="flex h-16 w-16 flex-shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-purple-500 to-pink-500">
                            {invitation.team_logo ? (
                                <img
                                    src={invitation.team_logo}
                                    alt={invitation.team_name}
                                    className="h-full w-full rounded-lg object-cover"
                                />
                            ) : (
                                <Users className="h-8 w-8 text-white" />
                            )}
                        </div>

                        {/* Team Details */}
                        <div className="flex-1 space-y-2">
                            <div className="flex items-start justify-between">
                                <div>
                                    <h3 className="text-xl font-bold text-foreground">
                                        {invitation.team_name}
                                    </h3>
                                    <p className="text-sm text-foreground-muted">
                                        Team Invitation
                                    </p>
                                </div>
                                <Badge variant={getRoleBadgeVariant(invitation.role)}>
                                    <Shield className="mr-1 h-3 w-3" />
                                    {invitation.role}
                                </Badge>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Invitation Details */}
                <div className="space-y-4">
                    {/* Inviter Info */}
                    <div className="flex items-center gap-3 rounded-lg bg-background p-4">
                        <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-primary/10">
                            <UserCheck className="h-5 w-5 text-primary" />
                        </div>
                        <div className="flex-1">
                            <p className="text-sm text-foreground-muted">Invited by</p>
                            <p className="font-semibold text-foreground">
                                {invitation.inviter_name}
                            </p>
                        </div>
                    </div>

                    {/* Inviter Email */}
                    <div className="flex items-center gap-3 rounded-lg bg-background p-4">
                        <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-primary/10">
                            <Mail className="h-5 w-5 text-primary" />
                        </div>
                        <div className="flex-1">
                            <p className="text-sm text-foreground-muted">Email</p>
                            <p className="font-mono text-sm font-semibold text-foreground">
                                {invitation.inviter_email}
                            </p>
                        </div>
                    </div>

                    {/* Expiry Info */}
                    <div className="rounded-lg border border-yellow-500/20 bg-yellow-500/10 p-4">
                        <p className="text-sm text-yellow-600 dark:text-yellow-500">
                            {formatExpiryDate(invitation.expires_at)}
                        </p>
                    </div>
                </div>

                {/* Role Description */}
                <div className="rounded-lg border border-border bg-background p-4">
                    <h4 className="mb-2 font-semibold text-foreground">
                        As a {invitation.role}, you'll be able to:
                    </h4>
                    <ul className="space-y-1 text-sm text-foreground-muted">
                        {invitation.role.toLowerCase() === 'owner' && (
                            <>
                                <li className="flex items-start gap-2">
                                    <span className="mt-0.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-primary" />
                                    Full access to all team resources and settings
                                </li>
                                <li className="flex items-start gap-2">
                                    <span className="mt-0.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-primary" />
                                    Manage team members and billing
                                </li>
                                <li className="flex items-start gap-2">
                                    <span className="mt-0.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-primary" />
                                    Delete or transfer team ownership
                                </li>
                            </>
                        )}
                        {invitation.role.toLowerCase() === 'admin' && (
                            <>
                                <li className="flex items-start gap-2">
                                    <span className="mt-0.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-primary" />
                                    Deploy and manage applications
                                </li>
                                <li className="flex items-start gap-2">
                                    <span className="mt-0.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-primary" />
                                    Invite and manage team members
                                </li>
                                <li className="flex items-start gap-2">
                                    <span className="mt-0.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-primary" />
                                    Configure team settings
                                </li>
                            </>
                        )}
                        {invitation.role.toLowerCase() === 'member' && (
                            <>
                                <li className="flex items-start gap-2">
                                    <span className="mt-0.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-primary" />
                                    View team projects and deployments
                                </li>
                                <li className="flex items-start gap-2">
                                    <span className="mt-0.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-primary" />
                                    Deploy assigned applications
                                </li>
                                <li className="flex items-start gap-2">
                                    <span className="mt-0.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-primary" />
                                    Access granted resources
                                </li>
                            </>
                        )}
                    </ul>
                </div>

                {/* Authentication Notice */}
                {!isAuthenticated && (
                    <div className="rounded-lg border border-blue-500/20 bg-blue-500/10 p-4">
                        <p className="text-sm text-blue-600 dark:text-blue-400">
                            You'll need to sign in or create an account to accept this invitation.
                        </p>
                        <div className="mt-3 flex items-center gap-3">
                            <Link
                                href={`/login?redirect=/auth/invitations/${invitation.id}`}
                                className="text-sm font-medium text-primary hover:underline"
                            >
                                Sign in
                            </Link>
                            <span className="text-foreground-muted">or</span>
                            <Link
                                href={`/register?invite=${invitation.id}`}
                                className="text-sm font-medium text-primary hover:underline"
                            >
                                Create Account
                            </Link>
                        </div>
                    </div>
                )}

                {/* Action Buttons */}
                <div className="flex gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        className="flex-1"
                        onClick={handleDecline}
                        disabled={processing}
                    >
                        <X className="mr-2 h-4 w-4" />
                        Decline
                    </Button>
                    <Button
                        type="button"
                        className="flex-1"
                        onClick={handleAccept}
                        loading={processing}
                    >
                        <UserCheck className="mr-2 h-4 w-4" />
                        Accept Invitation
                    </Button>
                </div>

                {/* Login Prompt */}
                {!isAuthenticated && (
                    <div className="text-center">
                        <p className="text-sm text-foreground-muted">
                            Already have an account?{' '}
                            <Link
                                href={`/login?redirect=/auth/invitations/${invitation.id}`}
                                className="text-primary hover:underline"
                            >
                                Sign in
                            </Link>
                        </p>
                    </div>
                )}
            </div>
        </AuthLayout>
    );
}
