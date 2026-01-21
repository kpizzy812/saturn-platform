import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Badge, Input, Checkbox, Modal, ModalFooter } from '@/components/ui';
import { ArrowLeft, Plus, Trash2, Eye, EyeOff, Copy, Key, Shield, UserPlus, RefreshCw } from 'lucide-react';
import type { StandaloneDatabase } from '@/types';

interface Props {
    database: StandaloneDatabase;
    users?: DatabaseUser[];
}

interface DatabaseUser {
    id: number;
    username: string;
    role: 'admin' | 'read_write' | 'read_only';
    permissions: {
        read: boolean;
        write: boolean;
        admin: boolean;
    };
    created_at: string;
}

export default function DatabaseUsers({ database, users }: Props) {
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [showResetPasswordModal, setShowResetPasswordModal] = useState(false);
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const [selectedUser, setSelectedUser] = useState<DatabaseUser | null>(null);

    const [newUsername, setNewUsername] = useState('');
    const [newPassword, setNewPassword] = useState('');
    const [showNewPassword, setShowNewPassword] = useState(false);
    const [permissions, setPermissions] = useState({
        read: true,
        write: false,
        admin: false,
    });

    // Show loading state when users data is not yet available
    if (!users) {
        return (
            <AppLayout
                title={`${database.name} - Users`}
                breadcrumbs={[
                    { label: 'Databases', href: '/databases' },
                    { label: database.name, href: `/databases/${database.uuid}` },
                    { label: 'Users' }
                ]}
            >
                <div className="flex items-center justify-center p-12">
                    <div className="text-center">
                        <Shield className="mx-auto h-12 w-12 animate-pulse text-foreground-muted" />
                        <p className="mt-4 text-foreground-muted">Loading users...</p>
                    </div>
                </div>
            </AppLayout>
        );
    }

    const generatePassword = () => {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
        const password = Array.from({ length: 20 }, () => chars[Math.floor(Math.random() * chars.length)]).join('');
        setNewPassword(password);
    };

    const handleCreateUser = () => {
        setShowCreateModal(true);
        setNewUsername('');
        setNewPassword('');
        setPermissions({ read: true, write: false, admin: false });
    };

    const confirmCreate = () => {
        if (newUsername.trim() && newPassword.trim()) {
            router.post(`/databases/${database.uuid}/users`, {
                username: newUsername,
                password: newPassword,
                permissions,
            });
            setShowCreateModal(false);
        }
    };

    const handleResetPassword = (user: DatabaseUser) => {
        setSelectedUser(user);
        setNewPassword('');
        setShowResetPasswordModal(true);
    };

    const confirmResetPassword = () => {
        if (selectedUser && newPassword.trim()) {
            router.post(`/databases/${database.uuid}/users/${selectedUser.id}/reset-password`, {
                password: newPassword,
            });
            setShowResetPasswordModal(false);
            setSelectedUser(null);
        }
    };

    const handleDelete = (user: DatabaseUser) => {
        setSelectedUser(user);
        setShowDeleteModal(true);
    };

    const confirmDelete = () => {
        if (selectedUser) {
            router.delete(`/databases/${database.uuid}/users/${selectedUser.id}`);
            setShowDeleteModal(false);
            setSelectedUser(null);
        }
    };

    const copyToClipboard = (text: string) => {
        navigator.clipboard.writeText(text);
    };

    return (
        <AppLayout
            title={`${database.name} - Users`}
            breadcrumbs={[
                { label: 'Databases', href: '/databases' },
                { label: database.name, href: `/databases/${database.uuid}` },
                { label: 'Users' }
            ]}
        >
            {/* Back Button */}
            <Link
                href={`/databases/${database.uuid}`}
                className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
            >
                <ArrowLeft className="mr-2 h-4 w-4" />
                Back to {database.name}
            </Link>

            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-foreground">Database Users</h1>
                    <p className="text-foreground-muted">Manage user access and permissions for {database.name}</p>
                </div>
                <Button onClick={handleCreateUser}>
                    <UserPlus className="mr-2 h-4 w-4" />
                    Create User
                </Button>
            </div>

            {/* Users List */}
            <div className="space-y-2">
                {users.map((user) => (
                    <UserCard
                        key={user.id}
                        user={user}
                        onResetPassword={() => handleResetPassword(user)}
                        onDelete={() => handleDelete(user)}
                    />
                ))}
            </div>

            {/* Create User Modal */}
            <Modal
                isOpen={showCreateModal}
                onClose={() => setShowCreateModal(false)}
                title="Create Database User"
                description="Create a new user with specific permissions"
                size="lg"
            >
                <div className="space-y-4">
                    <Input
                        label="Username"
                        value={newUsername}
                        onChange={(e) => setNewUsername(e.target.value)}
                        placeholder="user_name"
                    />

                    <div>
                        <label className="mb-2 block text-sm font-medium text-foreground">Password</label>
                        <div className="flex gap-2">
                            <div className="relative flex-1">
                                <Input
                                    type={showNewPassword ? 'text' : 'password'}
                                    value={newPassword}
                                    onChange={(e) => setNewPassword(e.target.value)}
                                    placeholder="••••••••••••"
                                />
                                <button
                                    onClick={() => setShowNewPassword(!showNewPassword)}
                                    className="absolute right-3 top-1/2 -translate-y-1/2 text-foreground-muted hover:text-foreground"
                                    type="button"
                                >
                                    {showNewPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                </button>
                            </div>
                            <Button variant="secondary" onClick={generatePassword}>
                                <Key className="mr-2 h-4 w-4" />
                                Generate
                            </Button>
                        </div>
                    </div>

                    <div>
                        <label className="mb-3 block text-sm font-medium text-foreground">Permissions</label>
                        <div className="space-y-3 rounded-lg border border-border bg-background-secondary p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-foreground">Read</p>
                                    <p className="text-xs text-foreground-muted">Allow SELECT queries</p>
                                </div>
                                <Checkbox
                                    checked={permissions.read}
                                    onChange={(e) => setPermissions({ ...permissions, read: e.target.checked })}
                                />
                            </div>
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-foreground">Write</p>
                                    <p className="text-xs text-foreground-muted">Allow INSERT, UPDATE, DELETE</p>
                                </div>
                                <Checkbox
                                    checked={permissions.write}
                                    onChange={(e) => setPermissions({ ...permissions, write: e.target.checked })}
                                />
                            </div>
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-foreground">Admin</p>
                                    <p className="text-xs text-foreground-muted">Full database access including schema changes</p>
                                </div>
                                <Checkbox
                                    checked={permissions.admin}
                                    onChange={(e) => setPermissions({ ...permissions, admin: e.target.checked })}
                                />
                            </div>
                        </div>
                    </div>

                    <ModalFooter>
                        <Button variant="secondary" onClick={() => setShowCreateModal(false)}>
                            Cancel
                        </Button>
                        <Button onClick={confirmCreate} disabled={!newUsername.trim() || !newPassword.trim()}>
                            Create User
                        </Button>
                    </ModalFooter>
                </div>
            </Modal>

            {/* Reset Password Modal */}
            <Modal
                isOpen={showResetPasswordModal}
                onClose={() => setShowResetPasswordModal(false)}
                title="Reset Password"
                description={`Reset password for user: ${selectedUser?.username}`}
            >
                {selectedUser && (
                    <div className="space-y-4">
                        <div>
                            <label className="mb-2 block text-sm font-medium text-foreground">New Password</label>
                            <div className="flex gap-2">
                                <div className="relative flex-1">
                                    <Input
                                        type={showNewPassword ? 'text' : 'password'}
                                        value={newPassword}
                                        onChange={(e) => setNewPassword(e.target.value)}
                                        placeholder="••••••••••••"
                                    />
                                    <button
                                        onClick={() => setShowNewPassword(!showNewPassword)}
                                        className="absolute right-3 top-1/2 -translate-y-1/2 text-foreground-muted hover:text-foreground"
                                        type="button"
                                    >
                                        {showNewPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                    </button>
                                </div>
                                <Button variant="secondary" onClick={generatePassword}>
                                    <Key className="h-4 w-4" />
                                </Button>
                            </div>
                        </div>

                        <ModalFooter>
                            <Button variant="secondary" onClick={() => setShowResetPasswordModal(false)}>
                                Cancel
                            </Button>
                            <Button onClick={confirmResetPassword} disabled={!newPassword.trim()}>
                                Reset Password
                            </Button>
                        </ModalFooter>
                    </div>
                )}
            </Modal>

            {/* Delete User Modal */}
            <Modal
                isOpen={showDeleteModal}
                onClose={() => setShowDeleteModal(false)}
                title="Delete User"
                description="Are you sure you want to delete this user?"
            >
                {selectedUser && (
                    <div className="space-y-4">
                        <div className="rounded-lg border border-border bg-background-tertiary p-4">
                            <p className="text-sm font-medium text-foreground">User Details</p>
                            <div className="mt-2 space-y-1 text-sm text-foreground-muted">
                                <p>Username: {selectedUser.username}</p>
                                <p>Role: {selectedUser.role.replace('_', ' ')}</p>
                            </div>
                        </div>

                        <div className="rounded-lg border border-danger/50 bg-danger/10 p-4">
                            <p className="text-sm font-medium text-danger">This action cannot be undone</p>
                            <p className="mt-1 text-sm text-foreground-muted">
                                This user will lose all access to the database.
                            </p>
                        </div>

                        <ModalFooter>
                            <Button variant="secondary" onClick={() => setShowDeleteModal(false)}>
                                Cancel
                            </Button>
                            <Button variant="danger" onClick={confirmDelete}>
                                Delete User
                            </Button>
                        </ModalFooter>
                    </div>
                )}
            </Modal>
        </AppLayout>
    );
}

function UserCard({
    user,
    onResetPassword,
    onDelete,
}: {
    user: DatabaseUser;
    onResetPassword: () => void;
    onDelete: () => void;
}) {
    const getRoleBadge = () => {
        switch (user.role) {
            case 'admin':
                return <Badge variant="danger">Admin</Badge>;
            case 'read_write':
                return <Badge variant="success">Read/Write</Badge>;
            case 'read_only':
                return <Badge variant="info">Read Only</Badge>;
        }
    };

    const getPermissionBadges = () => {
        const badges = [];
        if (user.permissions.read) badges.push(<Badge key="read" variant="default">Read</Badge>);
        if (user.permissions.write) badges.push(<Badge key="write" variant="default">Write</Badge>);
        if (user.permissions.admin) badges.push(<Badge key="admin" variant="danger">Admin</Badge>);
        return badges;
    };

    return (
        <Card>
            <CardContent className="p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-background-tertiary">
                            <Shield className="h-5 w-5 text-primary" />
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <h3 className="font-medium text-foreground">{user.username}</h3>
                                {getRoleBadge()}
                            </div>
                            <div className="mt-1 flex items-center gap-2">
                                {getPermissionBadges()}
                            </div>
                        </div>
                    </div>

                    <div className="flex gap-2">
                        <Button variant="secondary" size="sm" onClick={onResetPassword}>
                            <RefreshCw className="mr-2 h-4 w-4" />
                            Reset Password
                        </Button>
                        {user.username !== 'admin' && (
                            <Button variant="danger" size="sm" onClick={onDelete}>
                                <Trash2 className="h-4 w-4" />
                            </Button>
                        )}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
