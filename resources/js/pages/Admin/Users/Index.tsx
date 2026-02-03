import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { useConfirm } from '@/components/ui';
import {
    Search,
    MoreHorizontal,
    UserCheck,
    UserX,
    Eye,
    Shield,
    Ban,
    ChevronLeft,
    ChevronRight,
    Download,
    Trash2,
    CheckSquare,
    Square,
} from 'lucide-react';
import { Dropdown, DropdownTrigger, DropdownContent, DropdownItem, DropdownDivider } from '@/components/ui/Dropdown';

interface User {
    id: number;
    name: string;
    email: string;
    status: 'active' | 'suspended' | 'pending';
    is_root_user: boolean;
    teams_count: number;
    servers_count: number;
    created_at: string;
    last_login_at?: string;
}

interface Props {
    users: User[];
    total: number;
    currentPage: number;
    perPage: number;
    lastPage: number;
    filters: {
        search: string;
        status: string;
        sort_by: string;
        sort_direction: string;
    };
}

const defaultUsers: User[] = [];

interface UserRowProps {
    user: User;
    isSelected: boolean;
    onToggleSelect: (id: number) => void;
}

function UserRow({ user, isSelected, onToggleSelect }: UserRowProps) {
    const confirm = useConfirm();

    const handleImpersonate = async () => {
        const confirmed = await confirm({
            title: 'Impersonate User',
            description: `Impersonate ${user.name}? You will be logged in as this user.`,
            confirmText: 'Impersonate',
            variant: 'warning',
        });
        if (confirmed) {
            router.post(`/admin/users/${user.id}/impersonate`, {}, {
                preserveState: false,
            });
        }
    };

    const handleSuspend = async () => {
        const isSuspended = user.status === 'suspended';
        const confirmed = await confirm({
            title: isSuspended ? 'Activate User' : 'Suspend User',
            description: `${isSuspended ? 'Activate' : 'Suspend'} ${user.name}?`,
            confirmText: isSuspended ? 'Activate' : 'Suspend',
            variant: isSuspended ? 'warning' : 'danger',
        });
        if (confirmed) {
            router.post(`/admin/users/${user.id}/toggle-suspension`, {}, {
                preserveState: false,
                preserveScroll: true,
            });
        }
    };

    const statusConfig = {
        active: { variant: 'success' as const, label: 'Active' },
        suspended: { variant: 'danger' as const, label: 'Suspended' },
        pending: { variant: 'warning' as const, label: 'Pending' },
    };

    const config = statusConfig[user.status];
    const canSelect = !user.is_root_user;

    return (
        <div className="flex items-center justify-between border-b border-border/50 py-4 last:border-0">
            <div className="flex items-center gap-4">
                {/* Checkbox */}
                <div className="flex items-center">
                    {canSelect ? (
                        <Checkbox
                            checked={isSelected}
                            onCheckedChange={() => onToggleSelect(user.id)}
                        />
                    ) : (
                        <div className="h-4 w-4" /> // Placeholder for alignment
                    )}
                </div>

                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-purple-500 text-sm font-medium text-white">
                    {user.name.charAt(0).toUpperCase()}
                </div>
                <div>
                    <div className="flex items-center gap-2">
                        <Link
                            href={`/admin/users/${user.id}`}
                            className="font-medium text-foreground hover:text-primary"
                        >
                            {user.name}
                        </Link>
                        {user.is_root_user && (
                            <Badge variant="primary" size="sm" icon={<Shield className="h-3 w-3" />}>
                                Admin
                            </Badge>
                        )}
                    </div>
                    <p className="text-sm text-foreground-muted">{user.email}</p>
                    <div className="mt-1 flex items-center gap-3 text-xs text-foreground-subtle">
                        <span>{user.teams_count} teams</span>
                        <span>·</span>
                        <span>{user.servers_count} servers</span>
                        <span>·</span>
                        <span>Joined {new Date(user.created_at).toLocaleDateString()}</span>
                    </div>
                </div>
            </div>

            <div className="flex items-center gap-3">
                <Badge variant={config.variant} size="sm">
                    {config.label}
                </Badge>

                <Dropdown>
                    <DropdownTrigger>
                        <Button variant="ghost" size="sm">
                            <MoreHorizontal className="h-4 w-4" />
                        </Button>
                    </DropdownTrigger>
                    <DropdownContent align="right">
                        <DropdownItem onClick={() => router.visit(`/admin/users/${user.id}`)}>
                            <Eye className="h-4 w-4" />
                            View Details
                        </DropdownItem>
                        {!user.is_root_user && (
                            <>
                                <DropdownItem onClick={handleImpersonate}>
                                    <UserCheck className="h-4 w-4" />
                                    Impersonate User
                                </DropdownItem>
                                <DropdownDivider />
                                <DropdownItem onClick={handleSuspend}>
                                    {user.status === 'suspended' ? (
                                        <>
                                            <UserCheck className="h-4 w-4" />
                                            Activate User
                                        </>
                                    ) : (
                                        <>
                                            <Ban className="h-4 w-4" />
                                            Suspend User
                                        </>
                                    )}
                                </DropdownItem>
                                <DropdownDivider />
                                <DropdownItem
                                    onClick={() => router.visit(`/admin/users/${user.id}/resources`)}
                                    className="text-danger"
                                >
                                    <Trash2 className="h-4 w-4" />
                                    Delete User
                                </DropdownItem>
                            </>
                        )}
                    </DropdownContent>
                </Dropdown>
            </div>
        </div>
    );
}

export default function AdminUsersIndex({
    users = defaultUsers,
    total = 0,
    currentPage = 1,
    lastPage = 1,
    filters
}: Props) {
    const confirm = useConfirm();
    const [searchQuery, setSearchQuery] = React.useState(filters.search || '');
    const [statusFilter, setStatusFilter] = React.useState<'all' | 'active' | 'suspended' | 'pending'>(
        (filters.status as 'all' | 'active' | 'suspended' | 'pending') || 'all'
    );
    const [selectedUserIds, setSelectedUserIds] = React.useState<Set<number>>(new Set());

    // Get selectable users (exclude root/superadmin)
    const selectableUsers = React.useMemo(
        () => users.filter(u => !u.is_root_user),
        [users]
    );

    const allSelected = selectableUsers.length > 0 && selectableUsers.every(u => selectedUserIds.has(u.id));
    const someSelected = selectedUserIds.size > 0;

    // Clear selection when users change (page change, filter, etc.)
    React.useEffect(() => {
        setSelectedUserIds(new Set());
    }, [users]);

    // Debounced search to avoid excessive requests
    React.useEffect(() => {
        const timer = setTimeout(() => {
            updateFilters({ search: searchQuery });
        }, 300);
        return () => clearTimeout(timer);
    }, [searchQuery]);

    const updateFilters = (newFilters: Record<string, string>) => {
        router.get('/admin/users', {
            ...filters,
            ...newFilters,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleStatusFilter = (status: 'all' | 'active' | 'suspended' | 'pending') => {
        setStatusFilter(status);
        updateFilters({ status });
    };

    const handlePageChange = (page: number) => {
        router.get('/admin/users', {
            ...filters,
            page: page.toString(),
        }, {
            preserveState: true,
            preserveScroll: false,
        });
    };

    const toggleSelectUser = (id: number) => {
        setSelectedUserIds(prev => {
            const newSet = new Set(prev);
            if (newSet.has(id)) {
                newSet.delete(id);
            } else {
                newSet.add(id);
            }
            return newSet;
        });
    };

    const toggleSelectAll = () => {
        if (allSelected) {
            setSelectedUserIds(new Set());
        } else {
            setSelectedUserIds(new Set(selectableUsers.map(u => u.id)));
        }
    };

    const handleBulkSuspend = async () => {
        const confirmed = await confirm({
            title: 'Suspend Selected Users',
            description: `Are you sure you want to suspend ${selectedUserIds.size} user(s)? They will not be able to log in.`,
            confirmText: 'Suspend All',
            variant: 'danger',
        });
        if (confirmed) {
            router.post('/admin/users/bulk-suspend', {
                user_ids: Array.from(selectedUserIds),
                reason: 'Bulk suspension by admin',
            }, {
                preserveState: false,
                preserveScroll: true,
                onSuccess: () => setSelectedUserIds(new Set()),
            });
        }
    };

    const handleBulkActivate = async () => {
        const confirmed = await confirm({
            title: 'Activate Selected Users',
            description: `Are you sure you want to activate ${selectedUserIds.size} user(s)?`,
            confirmText: 'Activate All',
            variant: 'warning',
        });
        if (confirmed) {
            router.post('/admin/users/bulk-activate', {
                user_ids: Array.from(selectedUserIds),
            }, {
                preserveState: false,
                preserveScroll: true,
                onSuccess: () => setSelectedUserIds(new Set()),
            });
        }
    };

    const handleBulkDelete = async () => {
        const confirmed = await confirm({
            title: 'Delete Selected Users',
            description: `Are you sure you want to permanently delete ${selectedUserIds.size} user(s)? This action cannot be undone.`,
            confirmText: 'Delete All',
            variant: 'danger',
        });
        if (confirmed) {
            router.delete('/admin/users/bulk-delete', {
                data: { user_ids: Array.from(selectedUserIds) },
                preserveState: false,
                preserveScroll: true,
                onSuccess: () => setSelectedUserIds(new Set()),
            });
        }
    };

    const handleExport = () => {
        // Build URL with current filters
        const params = new URLSearchParams();
        if (filters.search) params.set('search', filters.search);
        if (filters.status && filters.status !== 'all') params.set('status', filters.status);

        window.location.href = `/admin/users/export?${params.toString()}`;
    };

    return (
        <AdminLayout
            title="Users"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Users' },
            ]}
        >
            <div className="mx-auto max-w-7xl">
                {/* Header */}
                <div className="mb-8 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">User Management</h1>
                        <p className="mt-1 text-sm text-foreground-muted">
                            Manage all users across your Saturn Platform instance
                        </p>
                    </div>
                    <Button variant="secondary" onClick={handleExport}>
                        <Download className="mr-2 h-4 w-4" />
                        Export CSV
                    </Button>
                </div>

                {/* Bulk Action Bar */}
                {someSelected && (
                    <Card variant="glass" className="mb-4 border-primary/50 bg-primary/5">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <CheckSquare className="h-5 w-5 text-primary" />
                                    <span className="font-medium text-foreground">
                                        {selectedUserIds.size} user{selectedUserIds.size !== 1 ? 's' : ''} selected
                                    </span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        onClick={handleBulkActivate}
                                    >
                                        <UserCheck className="mr-2 h-4 w-4" />
                                        Activate
                                    </Button>
                                    <Button
                                        variant="warning"
                                        size="sm"
                                        onClick={handleBulkSuspend}
                                    >
                                        <Ban className="mr-2 h-4 w-4" />
                                        Suspend
                                    </Button>
                                    <Button
                                        variant="danger"
                                        size="sm"
                                        onClick={handleBulkDelete}
                                    >
                                        <Trash2 className="mr-2 h-4 w-4" />
                                        Delete
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setSelectedUserIds(new Set())}
                                    >
                                        Clear
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Filters */}
                <Card variant="glass" className="mb-6">
                    <CardContent className="p-4">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="relative flex-1">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                                <Input
                                    placeholder="Search users by name or email..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="pl-10"
                                />
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    variant={statusFilter === 'all' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => handleStatusFilter('all')}
                                >
                                    All
                                </Button>
                                <Button
                                    variant={statusFilter === 'active' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => handleStatusFilter('active')}
                                >
                                    Active
                                </Button>
                                <Button
                                    variant={statusFilter === 'suspended' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => handleStatusFilter('suspended')}
                                >
                                    Suspended
                                </Button>
                                <Button
                                    variant={statusFilter === 'pending' ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => handleStatusFilter('pending')}
                                >
                                    Pending
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Users List */}
                <Card variant="glass">
                    <CardContent className="p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <div className="flex items-center gap-4">
                                {selectableUsers.length > 0 && (
                                    <button
                                        onClick={toggleSelectAll}
                                        className="flex items-center gap-2 text-sm text-foreground-muted hover:text-foreground"
                                    >
                                        {allSelected ? (
                                            <CheckSquare className="h-4 w-4 text-primary" />
                                        ) : (
                                            <Square className="h-4 w-4" />
                                        )}
                                        {allSelected ? 'Deselect all' : 'Select all'}
                                    </button>
                                )}
                                <p className="text-sm text-foreground-muted">
                                    Showing {users.length} of {total} users
                                </p>
                            </div>
                            {lastPage > 1 && (
                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => handlePageChange(currentPage - 1)}
                                        disabled={currentPage === 1}
                                    >
                                        <ChevronLeft className="h-4 w-4" />
                                        Previous
                                    </Button>
                                    <span className="text-sm text-foreground-muted">
                                        Page {currentPage} of {lastPage}
                                    </span>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => handlePageChange(currentPage + 1)}
                                        disabled={currentPage === lastPage}
                                    >
                                        Next
                                        <ChevronRight className="h-4 w-4" />
                                    </Button>
                                </div>
                            )}
                        </div>

                        {users.length === 0 ? (
                            <div className="py-12 text-center">
                                <UserX className="mx-auto h-12 w-12 text-foreground-muted" />
                                <p className="mt-4 text-sm text-foreground-muted">No users found</p>
                                <p className="text-xs text-foreground-subtle">Try adjusting your search or filters</p>
                            </div>
                        ) : (
                            <div>
                                {users.map((user) => (
                                    <UserRow
                                        key={user.id}
                                        user={user}
                                        isSelected={selectedUserIds.has(user.id)}
                                        onToggleSelect={toggleSelectUser}
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
