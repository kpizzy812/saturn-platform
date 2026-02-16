import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { useConfirm } from '@/components/ui';
import {
    Dropdown,
    DropdownTrigger,
    DropdownContent,
    DropdownItem,
    DropdownDivider,
} from '@/components/ui/Dropdown';
import {
    MailPlus,
    Mail,
    Users,
    CheckCircle,
    XCircle,
    Search,
    MoreHorizontal,
    Trash2,
    Send,
    Shield,
    ShieldCheck,
    ShieldAlert,
} from 'lucide-react';

interface Invitation {
    id: number;
    uuid: string;
    email: string;
    role: string;
    team_id: number;
    team_name: string;
    via: string;
    is_valid: boolean;
    created_at: string;
}

interface Props {
    invitations: {
        data: Invitation[];
        total: number;
    };
}

function InvitationRow({ invitation, onResend, onDelete }: { invitation: Invitation; onResend: (onFinish: () => void) => void; onDelete: () => void }) {
    const confirm = useConfirm();
    const [isResending, setIsResending] = React.useState(false);

    const getRoleConfig = (role: string) => {
        const configs: Record<string, { variant: 'primary' | 'warning' | 'success' | 'default'; icon: React.ReactNode; label: string }> = {
            owner: { variant: 'primary', icon: <ShieldAlert className="h-3 w-3" />, label: 'Owner' },
            admin: { variant: 'warning', icon: <ShieldCheck className="h-3 w-3" />, label: 'Admin' },
            developer: { variant: 'success', icon: <Shield className="h-3 w-3" />, label: 'Developer' },
            member: { variant: 'default', icon: <Shield className="h-3 w-3" />, label: 'Member' },
            viewer: { variant: 'default', icon: <Shield className="h-3 w-3" />, label: 'Viewer' },
        };
        return configs[role.toLowerCase()] || { variant: 'default' as const, icon: <Shield className="h-3 w-3" />, label: role };
    };

    const handleResend = () => {
        setIsResending(true);
        onResend(() => setIsResending(false));
    };

    const handleDelete = async () => {
        const confirmed = await confirm({
            title: 'Delete Invitation',
            description: `Delete invitation for ${invitation.email}? They will no longer be able to join the team using this link.`,
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            onDelete();
        }
    };

    const roleConfig = getRoleConfig(invitation.role);

    return (
        <div className="border-b border-border/50 py-4 last:border-0">
            <div className="flex items-start justify-between">
                <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-purple-500 text-sm font-medium text-white">
                        <Mail className="h-5 w-5" />
                    </div>
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="font-medium text-foreground">{invitation.email}</span>
                            <Badge variant={roleConfig.variant} size="sm" icon={roleConfig.icon}>
                                {roleConfig.label}
                            </Badge>
                        </div>
                        <div className="mt-1 flex items-center gap-3 text-xs text-foreground-subtle">
                            <span className="flex items-center gap-1">
                                <Users className="h-3 w-3" />
                                {invitation.team_name}
                            </span>
                            <span>via {invitation.via}</span>
                            <span>
                                Sent: {new Date(invitation.created_at).toLocaleDateString()}
                            </span>
                        </div>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    <Badge variant={invitation.is_valid ? 'success' : 'danger'} size="sm">
                        {invitation.is_valid ? 'Valid' : 'Expired'}
                    </Badge>
                    <Dropdown>
                        <DropdownTrigger>
                            <Button variant="ghost" size="sm">
                                <MoreHorizontal className="h-4 w-4" />
                            </Button>
                        </DropdownTrigger>
                        <DropdownContent align="right">
                            <DropdownItem onClick={handleResend} disabled={isResending}>
                                <Send className={`h-4 w-4 ${isResending ? 'animate-pulse' : ''}`} />
                                {isResending ? 'Sending...' : 'Resend Invitation'}
                            </DropdownItem>
                            <DropdownDivider />
                            <DropdownItem onClick={handleDelete} className="text-danger">
                                <Trash2 className="h-4 w-4" />
                                Delete
                            </DropdownItem>
                        </DropdownContent>
                    </Dropdown>
                </div>
            </div>
        </div>
    );
}

export default function AdminInvitationsIndex({ invitations: invitationsData }: Props) {
    const [searchQuery, setSearchQuery] = React.useState('');
    const [statusFilter, setStatusFilter] = React.useState<'all' | 'valid' | 'expired'>('all');

    const invitations = invitationsData?.data ?? [];
    const total = invitationsData?.total ?? 0;

    const filteredInvitations = invitations.filter((invitation) => {
        const matchesSearch =
            invitation.email.toLowerCase().includes(searchQuery.toLowerCase()) ||
            invitation.team_name.toLowerCase().includes(searchQuery.toLowerCase());
        const matchesStatus = statusFilter === 'all' ||
            (statusFilter === 'valid' && invitation.is_valid) ||
            (statusFilter === 'expired' && !invitation.is_valid);
        return matchesSearch && matchesStatus;
    });

    const validCount = invitations.filter((i) => i.is_valid).length;
    const expiredCount = invitations.filter((i) => !i.is_valid).length;

    const handleResend = (id: number, onFinish?: () => void) => {
        router.post(`/admin/invitations/${id}/resend`, {}, {
            preserveScroll: true,
            onFinish: () => onFinish?.(),
        });
    };

    const handleDelete = (id: number) => {
        router.delete(`/admin/invitations/${id}`, {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout
            title="Invitations"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Invitations' },
            ]}
        >
            <div className="mx-auto max-w-7xl p-6">
                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-2xl font-semibold text-foreground">Team Invitations</h1>
                    <p className="mt-1 text-sm text-foreground-muted">
                        Manage pending team invitations across all teams
                    </p>
                </div>

                {/* Stats */}
                <div className="mb-6 grid gap-4 sm:grid-cols-3">
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Total</p>
                                    <p className="text-2xl font-bold text-primary">{total}</p>
                                </div>
                                <MailPlus className="h-8 w-8 text-primary/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Valid</p>
                                    <p className="text-2xl font-bold text-success">{validCount}</p>
                                </div>
                                <CheckCircle className="h-8 w-8 text-success/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Expired</p>
                                    <p className="text-2xl font-bold text-danger">{expiredCount}</p>
                                </div>
                                <XCircle className="h-8 w-8 text-danger/50" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <Card variant="glass" className="mb-6">
                    <CardContent className="p-4">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="relative flex-1">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                                <Input
                                    placeholder="Search by email or team..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="pl-10"
                                />
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    variant={statusFilter === 'all' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => setStatusFilter('all')}
                                >
                                    All
                                </Button>
                                <Button
                                    variant={statusFilter === 'valid' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => setStatusFilter('valid')}
                                >
                                    Valid
                                </Button>
                                <Button
                                    variant={statusFilter === 'expired' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => setStatusFilter('expired')}
                                >
                                    Expired
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Invitations List */}
                <Card variant="glass">
                    <CardHeader>
                        <CardTitle>Pending Invitations ({filteredInvitations.length})</CardTitle>
                        <CardDescription>Invitations waiting to be accepted</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {filteredInvitations.length === 0 ? (
                            <div className="py-12 text-center">
                                <MailPlus className="mx-auto h-12 w-12 text-foreground-muted" />
                                <p className="mt-4 text-sm text-foreground-muted">
                                    {invitations.length === 0 ? 'No pending invitations' : 'No matching invitations'}
                                </p>
                                <p className="text-xs text-foreground-subtle">
                                    {invitations.length === 0
                                        ? 'Team invitations will appear here'
                                        : 'Try adjusting your search or filters'}
                                </p>
                            </div>
                        ) : (
                            <div>
                                {filteredInvitations.map((invitation) => (
                                    <InvitationRow
                                        key={invitation.id}
                                        invitation={invitation}
                                        onResend={(onFinish) => handleResend(invitation.id, onFinish)}
                                        onDelete={() => handleDelete(invitation.id)}
                                    />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
