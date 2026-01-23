import * as React from 'react';
import { SettingsLayout } from '../Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Button, Badge, Input, Select, Textarea, Checkbox } from '@/components/ui';
import { Link, router } from '@inertiajs/react';
import {
    Mail,
    X,
    Send,
    ArrowLeft,
    Clock,
    RotateCw,
    Trash2,
    Crown,
    Shield,
    User as UserIcon,
    Lock
} from 'lucide-react';

interface Project {
    id: number;
    name: string;
}

interface Invitation {
    id: number;
    email: string;
    role: 'admin' | 'member' | 'viewer';
    projectAccess: 'all' | number[];
    message?: string;
    sentAt: string;
    expiresAt: string;
    status: 'pending' | 'accepted' | 'expired';
}

interface Props {
    projects?: Project[];
    pendingInvitations?: Invitation[];
}

export default function TeamInvite({ projects = [], pendingInvitations = [] }: Props) {
    const [invitations, setInvitations] = React.useState<Invitation[]>(pendingInvitations);

    // Form state
    const [emails, setEmails] = React.useState<string[]>(['']);
    const [role, setRole] = React.useState<'admin' | 'member' | 'viewer'>('member');
    const [projectAccess, setProjectAccess] = React.useState<'all' | 'specific'>('all');
    const [selectedProjects, setSelectedProjects] = React.useState<number[]>([]);
    const [message, setMessage] = React.useState('');
    const [isSending, setIsSending] = React.useState(false);

    const addEmailField = () => {
        setEmails([...emails, '']);
    };

    const removeEmailField = (index: number) => {
        setEmails(emails.filter((_, i) => i !== index));
    };

    const updateEmail = (index: number, value: string) => {
        const newEmails = [...emails];
        newEmails[index] = value;
        setEmails(newEmails);
    };

    const toggleProject = (projectId: number) => {
        setSelectedProjects(prev =>
            prev.includes(projectId)
                ? prev.filter(id => id !== projectId)
                : [...prev, projectId]
        );
    };

    const handleSendInvitations = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSending(true);

        const validEmails = emails.filter(email => email.trim() !== '');

        router.post('/api/v1/teams/invitations/bulk', {
            emails: validEmails,
            role,
            project_access: projectAccess === 'all' ? 'all' : selectedProjects,
            message: message || undefined,
        }, {
            onSuccess: () => {
                // Reset form
                setEmails(['']);
                setRole('member');
                setProjectAccess('all');
                setSelectedProjects([]);
                setMessage('');
                router.reload({ only: ['pendingInvitations'] });
            },
            onFinish: () => {
                setIsSending(false);
            },
        });
    };

    const handleResendInvitation = (invitationId: number) => {
        router.post(`/api/v1/teams/invitations/${invitationId}/resend`, {}, {
            onSuccess: () => {
                router.reload({ only: ['pendingInvitations'] });
            },
        });
    };

    const handleCancelInvitation = (invitationId: number) => {
        router.delete(`/api/v1/teams/invitations/${invitationId}`, {
            onSuccess: () => {
                router.reload({ only: ['pendingInvitations'] });
            },
        });
    };

    const getRoleIcon = (role: string) => {
        switch (role) {
            case 'admin':
                return <Shield className="h-4 w-4" />;
            case 'viewer':
                return <Lock className="h-4 w-4" />;
            default:
                return <UserIcon className="h-4 w-4" />;
        }
    };

    const formatDate = (dateString: string) => {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now.getTime() - date.getTime();
        const diffDays = Math.floor(diffMs / (24 * 60 * 60 * 1000));

        if (diffDays === 0) return 'Today';
        if (diffDays === 1) return 'Yesterday';
        if (diffDays < 7) return `${diffDays} days ago`;
        return date.toLocaleDateString();
    };

    const isExpired = (expiresAt: string) => {
        return new Date(expiresAt) < new Date();
    };

    const pendingInvites = invitations.filter(inv => inv.status === 'pending' && !isExpired(inv.expiresAt));
    const expiredInvites = invitations.filter(inv => inv.status === 'expired' || isExpired(inv.expiresAt));

    return (
        <SettingsLayout activeSection="team">
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Link href="/settings/team">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft className="h-4 w-4" />
                        </Button>
                    </Link>
                    <div>
                        <h2 className="text-2xl font-semibold text-foreground">Invite Team Members</h2>
                        <p className="text-sm text-foreground-muted">
                            Send invitations to join your team
                        </p>
                    </div>
                </div>

                {/* Invitation Form */}
                <Card>
                    <CardHeader>
                        <CardTitle>Send Invitations</CardTitle>
                        <CardDescription>
                            Invite multiple people at once with customized access
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSendInvitations} className="space-y-6">
                            {/* Email Addresses */}
                            <div className="space-y-3">
                                <label className="text-sm font-medium text-foreground">
                                    Email Addresses
                                </label>
                                {emails.map((email, index) => (
                                    <div key={index} className="flex gap-2">
                                        <Input
                                            type="email"
                                            value={email}
                                            onChange={(e) => updateEmail(index, e.target.value)}
                                            placeholder="colleague@example.com"
                                            required={index === 0}
                                        />
                                        {emails.length > 1 && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => removeEmailField(index)}
                                            >
                                                <X className="h-4 w-4" />
                                            </Button>
                                        )}
                                    </div>
                                ))}
                                <Button
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    onClick={addEmailField}
                                >
                                    Add Another Email
                                </Button>
                            </div>

                            {/* Role Selection */}
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-foreground">
                                    Role
                                </label>
                                <div className="grid gap-3">
                                    {(['admin', 'member', 'viewer'] as const).map((r) => (
                                        <button
                                            key={r}
                                            type="button"
                                            onClick={() => setRole(r)}
                                            className={`flex items-center gap-3 rounded-lg border p-3 text-left transition-all ${
                                                role === r
                                                    ? 'border-primary bg-primary/10'
                                                    : 'border-border hover:border-border/80'
                                            }`}
                                        >
                                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-background-tertiary">
                                                {getRoleIcon(r)}
                                            </div>
                                            <div className="flex-1">
                                                <p className="font-medium text-foreground capitalize">{r}</p>
                                                <p className="text-xs text-foreground-muted">
                                                    {r === 'admin' && 'Manage team members and settings'}
                                                    {r === 'member' && 'Deploy and manage resources'}
                                                    {r === 'viewer' && 'Read-only access to resources'}
                                                </p>
                                            </div>
                                        </button>
                                    ))}
                                </div>
                            </div>

                            {/* Project Access */}
                            <div className="space-y-3">
                                <label className="text-sm font-medium text-foreground">
                                    Project Access
                                </label>
                                <div className="space-y-2">
                                    <button
                                        type="button"
                                        onClick={() => setProjectAccess('all')}
                                        className={`flex w-full items-center gap-3 rounded-lg border p-3 text-left transition-all ${
                                            projectAccess === 'all'
                                                ? 'border-primary bg-primary/10'
                                                : 'border-border hover:border-border/80'
                                        }`}
                                    >
                                        <div className="flex-1">
                                            <p className="font-medium text-foreground">All Projects</p>
                                            <p className="text-xs text-foreground-muted">
                                                Access to all current and future projects
                                            </p>
                                        </div>
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setProjectAccess('specific')}
                                        className={`flex w-full items-center gap-3 rounded-lg border p-3 text-left transition-all ${
                                            projectAccess === 'specific'
                                                ? 'border-primary bg-primary/10'
                                                : 'border-border hover:border-border/80'
                                        }`}
                                    >
                                        <div className="flex-1">
                                            <p className="font-medium text-foreground">Specific Projects</p>
                                            <p className="text-xs text-foreground-muted">
                                                Choose which projects to grant access to
                                            </p>
                                        </div>
                                    </button>
                                </div>

                                {projectAccess === 'specific' && (
                                    <div className="ml-4 space-y-2 border-l-2 border-border pl-4">
                                        {projects.map((project) => (
                                            <label
                                                key={project.id}
                                                className="flex items-center gap-3 cursor-pointer"
                                            >
                                                <Checkbox
                                                    checked={selectedProjects.includes(project.id)}
                                                    onChange={() => toggleProject(project.id)}
                                                />
                                                <span className="text-sm text-foreground">{project.name}</span>
                                            </label>
                                        ))}
                                    </div>
                                )}
                            </div>

                            {/* Personal Message */}
                            <Textarea
                                label="Personal Message (Optional)"
                                value={message}
                                onChange={(e) => setMessage(e.target.value)}
                                placeholder="Add a personal note to the invitation..."
                                rows={3}
                            />

                            {/* Submit */}
                            <div className="flex justify-end gap-3">
                                <Link href="/settings/team">
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </Link>
                                <Button type="submit" loading={isSending}>
                                    <Send className="mr-2 h-4 w-4" />
                                    Send {emails.filter(e => e.trim()).length > 1 ? 'Invitations' : 'Invitation'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* Pending Invitations */}
                {pendingInvites.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Pending Invitations</CardTitle>
                            <CardDescription>
                                {pendingInvites.length} invitation{pendingInvites.length !== 1 ? 's' : ''} waiting to be accepted
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {pendingInvites.map((invitation) => (
                                    <div
                                        key={invitation.id}
                                        className="flex items-center justify-between rounded-lg border border-border bg-background p-4"
                                    >
                                        <div className="flex items-center gap-4">
                                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-background-tertiary">
                                                <Mail className="h-5 w-5 text-foreground-muted" />
                                            </div>
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <p className="font-medium text-foreground">{invitation.email}</p>
                                                    <Badge variant="default">
                                                        <span className="mr-1">{getRoleIcon(invitation.role)}</span>
                                                        {invitation.role}
                                                    </Badge>
                                                    {invitation.projectAccess !== 'all' && (
                                                        <Badge variant="info">
                                                            Limited Access
                                                        </Badge>
                                                    )}
                                                </div>
                                                <div className="flex items-center gap-2 text-sm text-foreground-muted">
                                                    <Clock className="h-3 w-3" />
                                                    <span>Sent {formatDate(invitation.sentAt)}</span>
                                                    <span>•</span>
                                                    <span>Expires {formatDate(invitation.expiresAt)}</span>
                                                </div>
                                                {invitation.message && (
                                                    <p className="mt-1 text-xs text-foreground-subtle italic">
                                                        "{invitation.message}"
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Button
                                                variant="secondary"
                                                size="sm"
                                                onClick={() => handleResendInvitation(invitation.id)}
                                            >
                                                <RotateCw className="h-4 w-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => handleCancelInvitation(invitation.id)}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Expired Invitations */}
                {expiredInvites.length > 0 && (
                    <Card className="border-danger/30">
                        <CardHeader>
                            <CardTitle className="text-danger">Expired Invitations</CardTitle>
                            <CardDescription>
                                These invitations have expired and need to be resent
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {expiredInvites.map((invitation) => (
                                    <div
                                        key={invitation.id}
                                        className="flex items-center justify-between rounded-lg border border-danger/30 bg-danger/5 p-4"
                                    >
                                        <div className="flex items-center gap-4">
                                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-danger/20">
                                                <Mail className="h-5 w-5 text-danger" />
                                            </div>
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <p className="font-medium text-foreground">{invitation.email}</p>
                                                    <Badge variant="danger">Expired</Badge>
                                                </div>
                                                <p className="text-sm text-foreground-muted">
                                                    Expired {formatDate(invitation.expiresAt)}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Button
                                                variant="secondary"
                                                size="sm"
                                                onClick={() => handleResendInvitation(invitation.id)}
                                            >
                                                <RotateCw className="mr-2 h-4 w-4" />
                                                Resend
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => handleCancelInvitation(invitation.id)}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </SettingsLayout>
    );
}
