import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { router, useForm } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { useConfirm } from '@/components/ui';
import {
    UserPlus,
    Search,
    Trash2,
    Copy,
    Check,
    CheckCircle,
    XCircle,
    Link as LinkIcon,
    Mail,
} from 'lucide-react';

interface PlatformInvite {
    id: number;
    uuid: string;
    email: string;
    link: string;
    created_by_name: string;
    is_valid: boolean;
    used_at: string | null;
    created_at: string;
}

interface Props {
    invites: {
        data: PlatformInvite[];
        total: number;
    };
}

function CopyButton({ text }: { text: string }) {
    const [copied, setCopied] = React.useState(false);

    const handleCopy = async () => {
        await navigator.clipboard.writeText(text);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <Button variant="ghost" size="sm" onClick={handleCopy} title="Copy invite link">
            {copied ? <Check className="h-4 w-4 text-success" /> : <Copy className="h-4 w-4" />}
        </Button>
    );
}

function InviteRow({ invite, onDelete }: { invite: PlatformInvite; onDelete: () => void }) {
    const confirm = useConfirm();

    const handleDelete = async () => {
        const confirmed = await confirm({
            title: 'Delete Invite',
            description: `Delete platform invite for ${invite.email}? They will no longer be able to register using this link.`,
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            onDelete();
        }
    };

    const getStatus = () => {
        if (invite.used_at) {
            return { label: 'Used', variant: 'default' as const };
        }
        if (invite.is_valid) {
            return { label: 'Pending', variant: 'warning' as const };
        }
        return { label: 'Expired', variant: 'danger' as const };
    };

    const status = getStatus();

    return (
        <div className="border-b border-border/50 py-4 last:border-0">
            <div className="flex items-start justify-between">
                <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-emerald-500 to-teal-500 text-sm font-medium text-white">
                        <Mail className="h-5 w-5" />
                    </div>
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="font-medium text-foreground">{invite.email}</span>
                            <Badge variant={status.variant} size="sm">
                                {status.label}
                            </Badge>
                        </div>
                        <div className="mt-1 flex items-center gap-3 text-xs text-foreground-subtle">
                            <span>by {invite.created_by_name}</span>
                            <span>
                                {new Date(invite.created_at).toLocaleDateString()}
                            </span>
                            {invite.used_at && (
                                <span className="text-success">
                                    Registered: {new Date(invite.used_at).toLocaleDateString()}
                                </span>
                            )}
                        </div>
                    </div>
                </div>

                <div className="flex items-center gap-1">
                    {invite.is_valid && !invite.used_at && (
                        <CopyButton text={invite.link} />
                    )}
                    <Button variant="ghost" size="sm" onClick={handleDelete} title="Delete">
                        <Trash2 className="h-4 w-4 text-danger" />
                    </Button>
                </div>
            </div>
        </div>
    );
}

function CreateInviteForm() {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/platform-invites', {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <Card variant="glass">
            <CardHeader>
                <CardTitle>Invite to Platform</CardTitle>
                <CardDescription>
                    Generate a registration link for a new user. They will be able to create an account even if public registration is disabled.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={handleSubmit} className="flex items-start gap-3">
                    <div className="flex-1">
                        <Input
                            type="email"
                            placeholder="user@example.com"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            error={errors.email}
                            required
                        />
                    </div>
                    <Button type="submit" loading={processing}>
                        <LinkIcon className="mr-2 h-4 w-4" />
                        Generate Invite
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

export default function AdminPlatformInvitesIndex({ invites: invitesData }: Props) {
    const [searchQuery, setSearchQuery] = React.useState('');
    const [statusFilter, setStatusFilter] = React.useState<'all' | 'pending' | 'used' | 'expired'>('all');

    const invites = invitesData?.data ?? [];
    const total = invitesData?.total ?? 0;

    const filteredInvites = invites.filter((invite) => {
        const matchesSearch = invite.email.toLowerCase().includes(searchQuery.toLowerCase());
        const matchesStatus = statusFilter === 'all' ||
            (statusFilter === 'pending' && invite.is_valid && !invite.used_at) ||
            (statusFilter === 'used' && invite.used_at !== null) ||
            (statusFilter === 'expired' && !invite.is_valid && !invite.used_at);
        return matchesSearch && matchesStatus;
    });

    const pendingCount = invites.filter((i) => i.is_valid && !i.used_at).length;
    const usedCount = invites.filter((i) => i.used_at !== null).length;
    const expiredCount = invites.filter((i) => !i.is_valid && !i.used_at).length;

    const handleDelete = (id: number) => {
        router.delete(`/admin/platform-invites/${id}`, {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout
            title="Platform Invites"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Platform Invites' },
            ]}
        >
            <div className="mx-auto max-w-7xl p-6">
                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-2xl font-semibold text-foreground">Platform Invites</h1>
                    <p className="mt-1 text-sm text-foreground-muted">
                        Allow specific people to register on the platform when public registration is disabled
                    </p>
                </div>

                {/* Create Form */}
                <div className="mb-6">
                    <CreateInviteForm />
                </div>

                {/* Stats */}
                <div className="mb-6 grid gap-4 sm:grid-cols-4">
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Total</p>
                                    <p className="text-2xl font-bold text-primary">{total}</p>
                                </div>
                                <UserPlus className="h-8 w-8 text-primary/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Pending</p>
                                    <p className="text-2xl font-bold text-warning">{pendingCount}</p>
                                </div>
                                <LinkIcon className="h-8 w-8 text-warning/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Used</p>
                                    <p className="text-2xl font-bold text-success">{usedCount}</p>
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
                                    placeholder="Search by email..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="pl-10"
                                />
                            </div>
                            <div className="flex gap-2">
                                {(['all', 'pending', 'used', 'expired'] as const).map((filter) => (
                                    <Button
                                        key={filter}
                                        variant={statusFilter === filter ? 'primary' : 'secondary'}
                                        size="sm"
                                        onClick={() => setStatusFilter(filter)}
                                    >
                                        {filter.charAt(0).toUpperCase() + filter.slice(1)}
                                    </Button>
                                ))}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Invites List */}
                <Card variant="glass">
                    <CardHeader>
                        <CardTitle>Invitations ({filteredInvites.length})</CardTitle>
                        <CardDescription>Platform registration invites</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {filteredInvites.length === 0 ? (
                            <div className="py-12 text-center">
                                <UserPlus className="mx-auto h-12 w-12 text-foreground-muted" />
                                <p className="mt-4 text-sm text-foreground-muted">
                                    {invites.length === 0 ? 'No platform invites yet' : 'No matching invites'}
                                </p>
                                <p className="text-xs text-foreground-subtle">
                                    {invites.length === 0
                                        ? 'Create an invite above to allow someone to register'
                                        : 'Try adjusting your search or filters'}
                                </p>
                            </div>
                        ) : (
                            <div>
                                {filteredInvites.map((invite) => (
                                    <InviteRow
                                        key={invite.id}
                                        invite={invite}
                                        onDelete={() => handleDelete(invite.id)}
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
