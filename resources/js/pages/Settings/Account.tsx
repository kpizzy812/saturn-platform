import * as React from 'react';
import { SettingsLayout } from './Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Input, Button, Modal, ModalFooter, useToast } from '@/components/ui';
import { usePage, router } from '@inertiajs/react';
import { Shield, Trash2, Upload, AlertCircle, X } from 'lucide-react';
import { validatePassword, validatePasswordMatch } from '@/lib/validation';
import type { AuthUser } from '@/types/inertia';

/**
 * Extract first error message from Inertia errors object
 */
function getFirstErrorMessage(errors: Record<string, string>): string {
    const firstKey = Object.keys(errors)[0];
    return firstKey ? errors[firstKey] : 'An unexpected error occurred.';
}

export default function AccountSettings() {
    const { props } = usePage<{ auth: AuthUser | null }>();
    const currentUser = props.auth;

    const [profile, setProfile] = React.useState({
        name: currentUser?.name ?? '',
        email: currentUser?.email ?? '',
    });

    const [password, setPassword] = React.useState({
        current: '',
        new: '',
        confirm: '',
    });

    const [passwordErrors, setPasswordErrors] = React.useState({
        new: undefined as string | undefined,
        confirm: undefined as string | undefined,
    });

    const [passwordStrength, setPasswordStrength] = React.useState<'weak' | 'medium' | 'strong'>('weak');

    const [twoFactorEnabled, setTwoFactorEnabled] = React.useState(currentUser?.two_factor_enabled ?? false);
    const [showDeleteModal, setShowDeleteModal] = React.useState(false);
    const [isSavingProfile, setIsSavingProfile] = React.useState(false);
    const [isSavingPassword, setIsSavingPassword] = React.useState(false);
    const [isToggling2FA, setIsToggling2FA] = React.useState(false);
    const [isDeletingAccount, setIsDeletingAccount] = React.useState(false);
    const [isUploadingAvatar, setIsUploadingAvatar] = React.useState(false);
    const { addToast } = useToast();

    const handleAvatarUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;

        // Validate file size (2MB max)
        if (file.size > 2 * 1024 * 1024) {
            addToast('error', 'File too large', 'Please select an image smaller than 2MB.');
            return;
        }

        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            addToast('error', 'Invalid file type', 'Please select a JPEG, PNG, GIF, or WebP image.');
            return;
        }

        setIsUploadingAvatar(true);
        router.post('/settings/account/avatar', { avatar: file }, {
            forceFormData: true,
            onSuccess: () => {
                addToast('success', 'Avatar updated', 'Your avatar has been updated successfully.');
            },
            onError: (errors) => {
                addToast('error', 'Failed to upload avatar', getFirstErrorMessage(errors));
            },
            onFinish: () => {
                setIsUploadingAvatar(false);
                // Reset the input
                e.target.value = '';
            }
        });
    };

    const handleRemoveAvatar = () => {
        setIsUploadingAvatar(true);
        router.delete('/settings/account/avatar', {
            onSuccess: () => {
                addToast('success', 'Avatar removed', 'Your avatar has been removed successfully.');
            },
            onError: (errors) => {
                addToast('error', 'Failed to remove avatar', getFirstErrorMessage(errors));
            },
            onFinish: () => {
                setIsUploadingAvatar(false);
            }
        });
    };

    const handleNewPasswordChange = (value: string) => {
        setPassword({ ...password, new: value });

        if (value) {
            const { valid, error, strength } = validatePassword(value);
            setPasswordErrors({ ...passwordErrors, new: error });
            if (strength) {
                setPasswordStrength(strength);
            }

            // Also re-validate confirmation if it exists
            if (password.confirm) {
                const matchResult = validatePasswordMatch(value, password.confirm);
                setPasswordErrors((prev) => ({ ...prev, confirm: matchResult.error }));
            }
        } else {
            setPasswordErrors({ ...passwordErrors, new: undefined });
            setPasswordStrength('weak');
        }
    };

    const handleConfirmPasswordChange = (value: string) => {
        setPassword({ ...password, confirm: value });

        if (value && password.new) {
            const { valid, error } = validatePasswordMatch(password.new, value);
            setPasswordErrors({ ...passwordErrors, confirm: error });
        } else {
            setPasswordErrors({ ...passwordErrors, confirm: undefined });
        }
    };

    const handleProfileSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSavingProfile(true);

        router.post('/settings/account/profile', profile, {
            onSuccess: () => {
                addToast('success', 'Profile updated', 'Your profile has been saved successfully.');
            },
            onError: (errors) => {
                addToast('error', 'Failed to save profile', getFirstErrorMessage(errors));
            },
            onFinish: () => {
                setIsSavingProfile(false);
            }
        });
    };

    const handlePasswordSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSavingPassword(true);

        router.post('/settings/account/password', password, {
            onSuccess: () => {
                setPassword({ current: '', new: '', confirm: '' });
                addToast('success', 'Password updated', 'Your password has been changed successfully.');
            },
            onError: (errors) => {
                addToast('error', 'Failed to update password', getFirstErrorMessage(errors));
            },
            onFinish: () => {
                setIsSavingPassword(false);
            }
        });
    };

    const handleToggle2FA = () => {
        const newState = !twoFactorEnabled;
        setIsToggling2FA(true);

        router.post('/settings/account/2fa', { enabled: newState }, {
            onSuccess: () => {
                setTwoFactorEnabled(newState);
                addToast('success', newState ? '2FA enabled' : '2FA disabled', newState
                        ? 'Two-factor authentication has been enabled.'
                        : 'Two-factor authentication has been disabled.');
            },
            onError: (errors) => {
                addToast('error', 'Failed to update 2FA', getFirstErrorMessage(errors));
            },
            onFinish: () => {
                setIsToggling2FA(false);
            }
        });
    };

    const handleDeleteAccount = () => {
        setIsDeletingAccount(true);

        router.delete('/settings/account', {
            onSuccess: () => {
                addToast('success', 'Account deleted', 'Your account has been deleted successfully.');
                setShowDeleteModal(false);
                // User will likely be redirected to login page by the backend
            },
            onError: (errors) => {
                addToast('error', 'Failed to delete account', getFirstErrorMessage(errors));
            },
            onFinish: () => {
                setIsDeletingAccount(false);
            }
        });
    };

    // Show error state if user is not authenticated
    if (!currentUser) {
        return (
            <SettingsLayout activeSection="account">
                <Card>
                    <CardContent className="py-12">
                        <div className="flex flex-col items-center justify-center text-center">
                            <AlertCircle className="h-12 w-12 text-danger mb-4" />
                            <h3 className="text-lg font-semibold text-foreground mb-2">
                                Authentication Required
                            </h3>
                            <p className="text-foreground-muted">
                                Please log in to access your account settings.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </SettingsLayout>
        );
    }

    return (
        <SettingsLayout activeSection="account">
            <div className="space-y-6">
                {/* Profile Section */}
                <Card>
                    <CardHeader>
                        <CardTitle>Profile</CardTitle>
                        <CardDescription>
                            Update your personal information
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleProfileSubmit} className="space-y-4">
                            {/* Avatar */}
                            <div>
                                <label className="mb-2 block text-sm font-medium text-foreground">
                                    Avatar
                                </label>
                                <div className="flex items-center gap-4">
                                    <div className="relative">
                                        {currentUser.avatar ? (
                                            <img
                                                src={currentUser.avatar}
                                                alt={currentUser.name}
                                                className="h-16 w-16 rounded-full object-cover"
                                            />
                                        ) : (
                                            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-primary text-2xl font-semibold text-white">
                                                {currentUser.name.charAt(0).toUpperCase()}
                                            </div>
                                        )}
                                        {currentUser.avatar && (
                                            <button
                                                type="button"
                                                onClick={handleRemoveAvatar}
                                                disabled={isUploadingAvatar}
                                                className="absolute -right-1 -top-1 flex h-5 w-5 items-center justify-center rounded-full bg-danger text-white hover:bg-danger/80 disabled:opacity-50"
                                                title="Remove avatar"
                                            >
                                                <X className="h-3 w-3" />
                                            </button>
                                        )}
                                    </div>
                                    <div>
                                        <input
                                            type="file"
                                            id="avatar-upload"
                                            accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                                            className="hidden"
                                            onChange={handleAvatarUpload}
                                            disabled={isUploadingAvatar}
                                        />
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            size="sm"
                                            onClick={() => document.getElementById('avatar-upload')?.click()}
                                            loading={isUploadingAvatar}
                                        >
                                            <Upload className="mr-2 h-4 w-4" />
                                            {currentUser.avatar ? 'Change Avatar' : 'Upload Avatar'}
                                        </Button>
                                        <p className="mt-1 text-xs text-foreground-subtle">
                                            Max 2MB. JPEG, PNG, GIF, or WebP.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <Input
                                label="Name"
                                value={profile.name}
                                onChange={(e) => setProfile({ ...profile, name: e.target.value })}
                                placeholder="Your name"
                            />

                            <Input
                                label="Email"
                                type="email"
                                value={profile.email}
                                onChange={(e) => setProfile({ ...profile, email: e.target.value })}
                                placeholder="your@email.com"
                            />

                            <div className="flex justify-end">
                                <Button type="submit" loading={isSavingProfile}>
                                    Save Changes
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* Password Section */}
                <Card>
                    <CardHeader>
                        <CardTitle>Change Password</CardTitle>
                        <CardDescription>
                            Update your password to keep your account secure
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handlePasswordSubmit} className="space-y-4">
                            <Input
                                label="Current Password"
                                type="password"
                                value={password.current}
                                onChange={(e) => setPassword({ ...password, current: e.target.value })}
                                placeholder="Enter current password"
                            />

                            <div>
                                <Input
                                    label="New Password"
                                    type="password"
                                    value={password.new}
                                    onChange={(e) => handleNewPasswordChange(e.target.value)}
                                    placeholder="Enter new password"
                                    error={passwordErrors.new}
                                />
                                {password.new && !passwordErrors.new && (
                                    <div className="mt-2">
                                        <div className="mb-1 flex items-center justify-between text-xs">
                                            <span className="text-foreground-muted">Password strength:</span>
                                            <span className={`font-medium ${
                                                passwordStrength === 'strong' ? 'text-green-500' :
                                                passwordStrength === 'medium' ? 'text-yellow-500' :
                                                'text-red-500'
                                            }`}>
                                                {passwordStrength.charAt(0).toUpperCase() + passwordStrength.slice(1)}
                                            </span>
                                        </div>
                                        <div className="flex gap-1">
                                            <div className={`h-1 flex-1 rounded ${
                                                passwordStrength === 'weak' ? 'bg-red-500' : 'bg-green-500'
                                            }`}></div>
                                            <div className={`h-1 flex-1 rounded ${
                                                passwordStrength === 'medium' || passwordStrength === 'strong'
                                                    ? passwordStrength === 'medium' ? 'bg-yellow-500' : 'bg-green-500'
                                                    : 'bg-border'
                                            }`}></div>
                                            <div className={`h-1 flex-1 rounded ${
                                                passwordStrength === 'strong' ? 'bg-green-500' : 'bg-border'
                                            }`}></div>
                                        </div>
                                    </div>
                                )}
                            </div>

                            <Input
                                label="Confirm New Password"
                                type="password"
                                value={password.confirm}
                                onChange={(e) => handleConfirmPasswordChange(e.target.value)}
                                placeholder="Confirm new password"
                                error={passwordErrors.confirm}
                            />

                            <div className="flex justify-end">
                                <Button
                                    type="submit"
                                    loading={isSavingPassword}
                                    disabled={
                                        !password.current ||
                                        !password.new ||
                                        !password.confirm ||
                                        !!passwordErrors.new ||
                                        !!passwordErrors.confirm
                                    }
                                >
                                    Update Password
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* Two-Factor Authentication */}
                <Card>
                    <CardHeader>
                        <CardTitle>Two-Factor Authentication</CardTitle>
                        <CardDescription>
                            Add an extra layer of security to your account
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                    <Shield className="h-5 w-5 text-primary" />
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-foreground">
                                        {twoFactorEnabled ? 'Enabled' : 'Disabled'}
                                    </p>
                                    <p className="text-xs text-foreground-muted">
                                        {twoFactorEnabled
                                            ? 'Your account is protected with 2FA'
                                            : 'Enable 2FA for better security'
                                        }
                                    </p>
                                </div>
                            </div>
                            <Button
                                variant={twoFactorEnabled ? 'danger' : 'default'}
                                onClick={handleToggle2FA}
                                loading={isToggling2FA}
                            >
                                {twoFactorEnabled ? 'Disable' : 'Enable'}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {/* Danger Zone */}
                <Card className="border-danger/50">
                    <CardHeader>
                        <CardTitle className="text-danger">Danger Zone</CardTitle>
                        <CardDescription>
                            Irreversible actions that affect your account
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-danger/10">
                                    <Trash2 className="h-5 w-5 text-danger" />
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-foreground">Delete Account</p>
                                    <p className="text-xs text-foreground-muted">
                                        Permanently delete your account and all data
                                    </p>
                                </div>
                            </div>
                            <Button variant="danger" onClick={() => setShowDeleteModal(true)}>
                                Delete Account
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Delete Confirmation Modal */}
            <Modal
                isOpen={showDeleteModal}
                onClose={() => setShowDeleteModal(false)}
                title="Delete Account"
                description="This action cannot be undone. This will permanently delete your account and all associated data."
            >
                <ModalFooter>
                    <Button variant="secondary" onClick={() => setShowDeleteModal(false)} disabled={isDeletingAccount}>
                        Cancel
                    </Button>
                    <Button variant="danger" onClick={handleDeleteAccount} loading={isDeletingAccount}>
                        Delete Account
                    </Button>
                </ModalFooter>
            </Modal>
        </SettingsLayout>
    );
}
