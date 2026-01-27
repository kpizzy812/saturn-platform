import * as React from 'react';
import { UAParser } from 'ua-parser-js';
import { SettingsLayout } from './Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Button, Badge, Modal, ModalFooter, Input, useToast } from '@/components/ui';
import { router } from '@inertiajs/react';
import { Monitor, Smartphone, Globe, Trash2, Shield, Bell, Plus, X } from 'lucide-react';
import { validateCIDR } from '@/lib/validation';

// Props from backend
interface Props {
    sessions: Array<{
        id: string;
        ip: string | null;
        userAgent: string | null;
        lastActive: string;
        current: boolean;
    }>;
    loginHistory: Array<{
        id: number;
        timestamp: string;
        ip: string | null;
        userAgent: string | null;
        success: boolean;
        location: string;
    }>;
    ipAllowlist: Array<{
        id: number;
        ip: string;
        description: string;
        createdAt: string;
    }>;
    securityNotifications: {
        newLogin: boolean;
        failedLogin: boolean;
        apiAccess: boolean;
    };
}

// Parsed session for display
interface ParsedSession {
    id: string;
    device: string;
    deviceType: 'desktop' | 'mobile';
    location: string;
    ip: string;
    lastActive: string;
    current: boolean;
}

// Parsed login history for display
interface ParsedLoginHistory {
    id: number;
    timestamp: string;
    ip: string;
    location: string;
    device: string;
    success: boolean;
}

// Parse user agent string into readable format
function parseUserAgent(userAgent: string | null): { device: string; deviceType: 'desktop' | 'mobile' } {
    if (!userAgent) {
        return { device: 'Unknown device', deviceType: 'desktop' };
    }

    const parser = new UAParser(userAgent);
    const browser = parser.getBrowser();
    const os = parser.getOS();
    const device = parser.getDevice();

    const browserName = browser.name || 'Unknown browser';
    const osName = os.name || 'Unknown OS';
    const deviceName = `${browserName} on ${osName}`;
    const deviceType: 'desktop' | 'mobile' = device.type === 'mobile' || device.type === 'tablet' ? 'mobile' : 'desktop';

    return { device: deviceName, deviceType };
}

export default function SecuritySettings({
    sessions: initialSessions,
    loginHistory: initialLoginHistory,
    ipAllowlist: initialIpAllowlist,
    securityNotifications: initialNotifications,
}: Props) {
    // Parse sessions with user agent info
    const parsedSessions = React.useMemo<ParsedSession[]>(() => {
        return initialSessions.map(session => {
            const { device, deviceType } = parseUserAgent(session.userAgent);
            return {
                id: session.id,
                device,
                deviceType,
                location: session.ip || 'Unknown',
                ip: session.ip || 'Unknown',
                lastActive: session.lastActive,
                current: session.current,
            };
        });
    }, [initialSessions]);

    // Parse login history with user agent info
    const parsedLoginHistory = React.useMemo<ParsedLoginHistory[]>(() => {
        return initialLoginHistory.map(log => {
            const { device } = parseUserAgent(log.userAgent);
            return {
                id: log.id,
                timestamp: log.timestamp,
                ip: log.ip || 'Unknown',
                location: log.location,
                device,
                success: log.success,
            };
        });
    }, [initialLoginHistory]);

    const [sessions, setSessions] = React.useState<ParsedSession[]>(parsedSessions);
    const [loginHistory] = React.useState<ParsedLoginHistory[]>(parsedLoginHistory);
    const [ipAllowlist, setIpAllowlist] = React.useState(initialIpAllowlist);
    const [showRevokeAllModal, setShowRevokeAllModal] = React.useState(false);
    const [showRevokeSessionModal, setShowRevokeSessionModal] = React.useState(false);
    const [showAddIPModal, setShowAddIPModal] = React.useState(false);
    const [sessionToRevoke, setSessionToRevoke] = React.useState<ParsedSession | null>(null);
    const [newIP, setNewIP] = React.useState({ ip: '', description: '' });
    const [ipError, setIpError] = React.useState<string>();
    const [securityNotifications, setSecurityNotifications] = React.useState(initialNotifications);
    const [isRevokingSession, setIsRevokingSession] = React.useState(false);
    const [isRevokingAll, setIsRevokingAll] = React.useState(false);
    const [isAddingIP, setIsAddingIP] = React.useState(false);
    const [isUpdatingNotifications, setIsUpdatingNotifications] = React.useState(false);
    const { addToast } = useToast();

    const handleIPChange = (value: string) => {
        setNewIP({ ...newIP, ip: value });

        if (value.trim()) {
            const { valid, error } = validateCIDR(value);
            setIpError(valid ? undefined : error);
        } else {
            setIpError(undefined);
        }
    };

    const handleCloseAddIPModal = () => {
        setShowAddIPModal(false);
        setNewIP({ ip: '', description: '' });
        setIpError(undefined);
    };

    const handleRevokeSession = () => {
        if (sessionToRevoke) {
            setIsRevokingSession(true);

            router.delete(`/settings/security/sessions/${sessionToRevoke.id}`, {
                onSuccess: () => {
                    setSessions(sessions.filter((s) => s.id !== sessionToRevoke.id));
                    addToast('success', 'Session revoked', 'The session has been revoked successfully.');
                    setSessionToRevoke(null);
                    setShowRevokeSessionModal(false);
                },
                onError: () => {
                    addToast('error', 'Failed to revoke session', 'An error occurred while revoking the session.');
                },
                onFinish: () => {
                    setIsRevokingSession(false);
                }
            });
        }
    };

    const handleRevokeAllSessions = () => {
        setIsRevokingAll(true);

        router.delete('/settings/security/sessions/all', {
            onSuccess: () => {
                setSessions(sessions.filter((s) => s.current));
                addToast('success', 'All sessions revoked', 'All other sessions have been revoked successfully.');
                setShowRevokeAllModal(false);
            },
            onError: () => {
                addToast('error', 'Failed to revoke sessions', 'An error occurred while revoking sessions.');
            },
            onFinish: () => {
                setIsRevokingAll(false);
            }
        });
    };

    const handleAddIP = (e: React.FormEvent) => {
        e.preventDefault();

        // Validate before submitting
        if (ipError) return;

        setIsAddingIP(true);

        router.post('/settings/security/ip-allowlist', { ip_address: newIP.ip, description: newIP.description }, {
            onSuccess: () => {
                const newEntry = {
                    id: ipAllowlist.length,
                    ip: newIP.ip,
                    description: newIP.description,
                    createdAt: new Date().toISOString(),
                };
                setIpAllowlist([...ipAllowlist, newEntry]);
                addToast('success', 'IP added', 'The IP address has been added to the allowlist.');
                setNewIP({ ip: '', description: '' });
                setIpError(undefined);
                setShowAddIPModal(false);
            },
            onError: () => {
                addToast('error', 'Failed to add IP', 'An error occurred while adding the IP address.');
            },
            onFinish: () => {
                setIsAddingIP(false);
            }
        });
    };

    const handleRemoveIP = (id: number) => {
        router.delete(`/settings/security/ip-allowlist/${id}`, {
            onSuccess: () => {
                setIpAllowlist(ipAllowlist.filter((entry) => entry.id !== id));
                addToast('success', 'IP removed', 'The IP address has been removed from the allowlist.');
            },
            onError: () => {
                addToast('error', 'Failed to remove IP', 'An error occurred while removing the IP address.');
            }
        });
    };

    const handleUpdateNotification = (key: keyof typeof securityNotifications, value: boolean) => {
        const newNotifications = {
            ...securityNotifications,
            [key]: value,
        };

        setIsUpdatingNotifications(true);

        router.post('/settings/security/notifications', newNotifications, {
            onSuccess: () => {
                setSecurityNotifications(newNotifications);
                addToast('success', 'Notification settings updated', 'Your notification preferences have been saved.');
            },
            onError: () => {
                addToast('error', 'Failed to update notifications', 'An error occurred while updating notification settings.');
            },
            onFinish: () => {
                setIsUpdatingNotifications(false);
            }
        });
    };

    const formatTimestamp = (timestamp: string) => {
        const date = new Date(timestamp);
        return date.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
    };

    const formatRelativeTime = (timestamp: string) => {
        const now = new Date();
        const date = new Date(timestamp);
        const diffMs = now.getTime() - date.getTime();
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins} minutes ago`;
        if (diffHours < 24) return `${diffHours} hours ago`;
        return `${diffDays} days ago`;
    };

    return (
        <SettingsLayout activeSection="security">
            <div className="space-y-6">
                {/* Active Sessions */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Active Sessions</CardTitle>
                                <CardDescription>
                                    Manage devices that are currently signed in
                                </CardDescription>
                            </div>
                            <Button
                                variant="danger"
                                onClick={() => setShowRevokeAllModal(true)}
                                disabled={sessions.filter((s) => !s.current).length === 0}
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Revoke All
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {sessions.length === 0 ? (
                            <div className="rounded-lg border-2 border-dashed border-border p-8 text-center">
                                <Monitor className="mx-auto h-12 w-12 text-foreground-muted" />
                                <h3 className="mt-4 text-sm font-medium text-foreground">No active sessions</h3>
                                <p className="mt-1 text-sm text-foreground-muted">
                                    Your session data will appear here
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-3">
                                {sessions.map((session) => {
                                    const Icon = session.deviceType === 'mobile' ? Smartphone : Monitor;
                                    return (
                                        <div
                                            key={session.id}
                                            className="flex items-center justify-between rounded-lg border border-border bg-background p-4"
                                        >
                                            <div className="flex items-center gap-4">
                                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                                    <Icon className="h-5 w-5 text-primary" />
                                                </div>
                                                <div>
                                                    <div className="flex items-center gap-2">
                                                        <p className="font-medium text-foreground">{session.device}</p>
                                                        {session.current && (
                                                            <Badge variant="success">Current</Badge>
                                                        )}
                                                    </div>
                                                    <p className="text-sm text-foreground-muted">
                                                        {session.ip}
                                                    </p>
                                                    <p className="text-xs text-foreground-subtle">
                                                        Last active {formatRelativeTime(session.lastActive)}
                                                    </p>
                                                </div>
                                            </div>
                                            {!session.current && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => {
                                                        setSessionToRevoke(session);
                                                        setShowRevokeSessionModal(true);
                                                    }}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Login History */}
                <Card>
                    <CardHeader>
                        <CardTitle>Login History</CardTitle>
                        <CardDescription>
                            Recent login attempts to your account
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {loginHistory.length === 0 ? (
                            <div className="rounded-lg border-2 border-dashed border-border p-8 text-center">
                                <Shield className="mx-auto h-12 w-12 text-foreground-muted" />
                                <h3 className="mt-4 text-sm font-medium text-foreground">No login history</h3>
                                <p className="mt-1 text-sm text-foreground-muted">
                                    Your login history will appear here after your next login
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-3">
                                {loginHistory.map((login) => (
                                    <div
                                        key={login.id}
                                        className="flex items-center justify-between rounded-lg border border-border bg-background p-4"
                                    >
                                        <div className="flex items-center gap-4">
                                            <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${
                                                login.success ? 'bg-success/10' : 'bg-danger/10'
                                            }`}>
                                                <Shield className={`h-5 w-5 ${
                                                    login.success ? 'text-success' : 'text-danger'
                                                }`} />
                                            </div>
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <p className="font-medium text-foreground">{login.device}</p>
                                                    <Badge variant={login.success ? 'success' : 'danger'}>
                                                        {login.success ? 'Success' : 'Failed'}
                                                    </Badge>
                                                </div>
                                                <p className="text-sm text-foreground-muted">
                                                    {login.location} {login.ip}
                                                </p>
                                            </div>
                                        </div>
                                        <p className="text-xs text-foreground-subtle">
                                            {formatTimestamp(login.timestamp)}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* IP Allowlist */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>API IP Allowlist</CardTitle>
                                <CardDescription>
                                    Restrict API access to specific IP addresses
                                </CardDescription>
                            </div>
                            <Button onClick={() => setShowAddIPModal(true)}>
                                <Plus className="mr-2 h-4 w-4" />
                                Add IP
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {ipAllowlist.length === 0 ? (
                            <div className="rounded-lg border-2 border-dashed border-border p-8 text-center">
                                <Globe className="mx-auto h-12 w-12 text-foreground-muted" />
                                <h3 className="mt-4 text-sm font-medium text-foreground">No IP restrictions</h3>
                                <p className="mt-1 text-sm text-foreground-muted">
                                    API can be accessed from any IP address
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-3">
                                {ipAllowlist.map((entry) => (
                                    <div
                                        key={entry.id}
                                        className="flex items-center justify-between rounded-lg border border-border bg-background p-4"
                                    >
                                        <div className="flex items-center gap-4">
                                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                                <Globe className="h-5 w-5 text-primary" />
                                            </div>
                                            <div>
                                                <p className="font-medium text-foreground">
                                                    <code>{entry.ip}</code>
                                                </p>
                                                <p className="text-sm text-foreground-muted">{entry.description}</p>
                                                <p className="text-xs text-foreground-subtle">
                                                    Added {new Date(entry.createdAt).toLocaleDateString()}
                                                </p>
                                            </div>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => handleRemoveIP(entry.id)}
                                        >
                                            <X className="h-4 w-4" />
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Security Notifications */}
                <Card>
                    <CardHeader>
                        <CardTitle>Security Notifications</CardTitle>
                        <CardDescription>
                            Choose which security events to be notified about
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <Bell className="h-5 w-5 text-foreground-muted" />
                                    <div>
                                        <p className="text-sm font-medium text-foreground">New Login</p>
                                        <p className="text-xs text-foreground-muted">
                                            Notify me when there's a new login to my account
                                        </p>
                                    </div>
                                </div>
                                <label className="relative inline-flex cursor-pointer items-center">
                                    <input
                                        type="checkbox"
                                        checked={securityNotifications.newLogin}
                                        onChange={(e) => handleUpdateNotification('newLogin', e.target.checked)}
                                        disabled={isUpdatingNotifications}
                                        className="peer sr-only"
                                    />
                                    <div className="peer h-6 w-11 rounded-full bg-background-tertiary after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full peer-focus:outline-none peer-disabled:opacity-50"></div>
                                </label>
                            </div>

                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <Bell className="h-5 w-5 text-foreground-muted" />
                                    <div>
                                        <p className="text-sm font-medium text-foreground">Failed Login Attempt</p>
                                        <p className="text-xs text-foreground-muted">
                                            Alert me about failed login attempts
                                        </p>
                                    </div>
                                </div>
                                <label className="relative inline-flex cursor-pointer items-center">
                                    <input
                                        type="checkbox"
                                        checked={securityNotifications.failedLogin}
                                        onChange={(e) => handleUpdateNotification('failedLogin', e.target.checked)}
                                        disabled={isUpdatingNotifications}
                                        className="peer sr-only"
                                    />
                                    <div className="peer h-6 w-11 rounded-full bg-background-tertiary after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full peer-focus:outline-none peer-disabled:opacity-50"></div>
                                </label>
                            </div>

                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <Bell className="h-5 w-5 text-foreground-muted" />
                                    <div>
                                        <p className="text-sm font-medium text-foreground">API Access</p>
                                        <p className="text-xs text-foreground-muted">
                                            Notify me about API token usage
                                        </p>
                                    </div>
                                </div>
                                <label className="relative inline-flex cursor-pointer items-center">
                                    <input
                                        type="checkbox"
                                        checked={securityNotifications.apiAccess}
                                        onChange={(e) => handleUpdateNotification('apiAccess', e.target.checked)}
                                        disabled={isUpdatingNotifications}
                                        className="peer sr-only"
                                    />
                                    <div className="peer h-6 w-11 rounded-full bg-background-tertiary after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full peer-focus:outline-none peer-disabled:opacity-50"></div>
                                </label>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Revoke Session Modal */}
            <Modal
                isOpen={showRevokeSessionModal}
                onClose={() => setShowRevokeSessionModal(false)}
                title="Revoke Session"
                description={`Are you sure you want to revoke the session on ${sessionToRevoke?.device}?`}
            >
                <ModalFooter>
                    <Button variant="secondary" onClick={() => setShowRevokeSessionModal(false)} disabled={isRevokingSession}>
                        Cancel
                    </Button>
                    <Button variant="danger" onClick={handleRevokeSession} loading={isRevokingSession}>
                        Revoke Session
                    </Button>
                </ModalFooter>
            </Modal>

            {/* Revoke All Sessions Modal */}
            <Modal
                isOpen={showRevokeAllModal}
                onClose={() => setShowRevokeAllModal(false)}
                title="Revoke All Sessions"
                description="This will sign you out on all devices except this one. You'll need to sign in again on those devices."
            >
                <ModalFooter>
                    <Button variant="secondary" onClick={() => setShowRevokeAllModal(false)} disabled={isRevokingAll}>
                        Cancel
                    </Button>
                    <Button variant="danger" onClick={handleRevokeAllSessions} loading={isRevokingAll}>
                        Revoke All Sessions
                    </Button>
                </ModalFooter>
            </Modal>

            {/* Add IP Modal */}
            <Modal
                isOpen={showAddIPModal}
                onClose={handleCloseAddIPModal}
                title="Add IP to Allowlist"
                description="Add an IP address or CIDR range to restrict API access"
            >
                <form onSubmit={handleAddIP}>
                    <div className="space-y-4">
                        <Input
                            label="IP Address or CIDR"
                            value={newIP.ip}
                            onChange={(e) => handleIPChange(e.target.value)}
                            placeholder="192.168.1.0/24 or 10.0.0.100"
                            error={ipError}
                            hint={!ipError ? "Single IP or CIDR notation" : undefined}
                            required
                        />
                        <Input
                            label="Description"
                            value={newIP.description}
                            onChange={(e) => setNewIP({ ...newIP, description: e.target.value })}
                            placeholder="Office Network"
                            required
                        />
                    </div>

                    <ModalFooter>
                        <Button type="button" variant="secondary" onClick={handleCloseAddIPModal} disabled={isAddingIP}>
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            loading={isAddingIP}
                            disabled={!newIP.ip || !newIP.description || !!ipError}
                        >
                            Add to Allowlist
                        </Button>
                    </ModalFooter>
                </form>
            </Modal>
        </SettingsLayout>
    );
}
