import * as React from 'react';
import { Link, router } from '@inertiajs/react';
import type { RouterPayload } from '@/types/inertia';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription, Button, Checkbox } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import { ChevronLeft, Save } from 'lucide-react';

interface NotificationPreferences {
    email: {
        deployments: boolean;
        team: boolean;
        billing: boolean;
        security: boolean;
    };
    inApp: {
        deployments: boolean;
        team: boolean;
        billing: boolean;
        security: boolean;
    };
    digest: 'instant' | 'daily' | 'weekly';
}

const DEFAULT_PREFERENCES: NotificationPreferences = {
    email: {
        deployments: true,
        team: true,
        billing: true,
        security: true,
    },
    inApp: {
        deployments: true,
        team: true,
        billing: true,
        security: true,
    },
    digest: 'instant',
};

interface Props {
    preferences?: NotificationPreferences;
}

export default function NotificationsPreferences({ preferences: initialPreferences }: Props) {
    const [preferences, setPreferences] = React.useState<NotificationPreferences>(
        initialPreferences || DEFAULT_PREFERENCES
    );
    const [isSaving, setIsSaving] = React.useState(false);
    const { addToast } = useToast();

    const handleEmailToggle = (category: keyof NotificationPreferences['email']) => {
        setPreferences((prev) => ({
            ...prev,
            email: {
                ...prev.email,
                [category]: !prev.email[category],
            },
        }));
    };

    const handleInAppToggle = (category: keyof NotificationPreferences['inApp']) => {
        setPreferences((prev) => ({
            ...prev,
            inApp: {
                ...prev.inApp,
                [category]: !prev.inApp[category],
            },
        }));
    };

    const handleDigestChange = (digest: 'instant' | 'daily' | 'weekly') => {
        setPreferences((prev) => ({
            ...prev,
            digest,
        }));
    };

    const handleSave = () => {
        setIsSaving(true);
        router.put('/api/v1/notifications/preferences', preferences as unknown as RouterPayload, {
            onSuccess: () => {
                addToast('success', 'Notification preferences saved successfully');
            },
            onError: () => {
                addToast('error', 'Failed to save notification preferences');
            },
            onFinish: () => {
                setIsSaving(false);
            },
        });
    };

    return (
        <AppLayout
            title="Notification Preferences"
            breadcrumbs={[
                { label: 'Notifications', href: '/notifications' },
                { label: 'Preferences' },
            ]}
        >
            {/* Header */}
            <div className="mb-6">
                <Link
                    href="/notifications"
                    className="mb-4 inline-flex items-center gap-2 text-sm text-foreground-muted transition-colors hover:text-foreground"
                >
                    <ChevronLeft className="h-4 w-4" />
                    Back to Notifications
                </Link>
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Notification Preferences</h1>
                        <p className="text-foreground-muted">
                            Manage how you receive notifications
                        </p>
                    </div>
                    <Button onClick={handleSave} loading={isSaving}>
                        <Save className="mr-2 h-4 w-4" />
                        Save Changes
                    </Button>
                </div>
            </div>

            <div className="space-y-6">
                {/* Email Notifications */}
                <Card>
                    <CardHeader>
                        <CardTitle>Email Notifications</CardTitle>
                        <CardDescription>
                            Receive notifications via email
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <NotificationToggle
                            label="Deployments"
                            description="Get notified when deployments succeed or fail"
                            checked={preferences.email.deployments}
                            onChange={() => handleEmailToggle('deployments')}
                        />
                        <NotificationToggle
                            label="Team"
                            description="Team invitations and member updates"
                            checked={preferences.email.team}
                            onChange={() => handleEmailToggle('team')}
                        />
                        <NotificationToggle
                            label="Billing"
                            description="Billing alerts, invoices, and payment updates"
                            checked={preferences.email.billing}
                            onChange={() => handleEmailToggle('billing')}
                        />
                        <NotificationToggle
                            label="Security"
                            description="Security alerts and login notifications"
                            checked={preferences.email.security}
                            onChange={() => handleEmailToggle('security')}
                        />
                    </CardContent>
                </Card>

                {/* In-App Notifications */}
                <Card>
                    <CardHeader>
                        <CardTitle>In-App Notifications</CardTitle>
                        <CardDescription>
                            Receive notifications in the application
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <NotificationToggle
                            label="Deployments"
                            description="Get notified when deployments succeed or fail"
                            checked={preferences.inApp.deployments}
                            onChange={() => handleInAppToggle('deployments')}
                        />
                        <NotificationToggle
                            label="Team"
                            description="Team invitations and member updates"
                            checked={preferences.inApp.team}
                            onChange={() => handleInAppToggle('team')}
                        />
                        <NotificationToggle
                            label="Billing"
                            description="Billing alerts, invoices, and payment updates"
                            checked={preferences.inApp.billing}
                            onChange={() => handleInAppToggle('billing')}
                        />
                        <NotificationToggle
                            label="Security"
                            description="Security alerts and login notifications"
                            checked={preferences.inApp.security}
                            onChange={() => handleInAppToggle('security')}
                        />
                    </CardContent>
                </Card>

                {/* Digest Settings */}
                <Card>
                    <CardHeader>
                        <CardTitle>Email Digest</CardTitle>
                        <CardDescription>
                            Choose how often you want to receive email notifications
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            <label className="flex items-center gap-3">
                                <input
                                    type="radio"
                                    name="digest"
                                    value="instant"
                                    checked={preferences.digest === 'instant'}
                                    onChange={() => handleDigestChange('instant')}
                                    className="h-4 w-4 border-border text-primary focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-background"
                                />
                                <div>
                                    <p className="font-medium text-foreground">Instant</p>
                                    <p className="text-sm text-foreground-muted">
                                        Receive emails immediately when events occur
                                    </p>
                                </div>
                            </label>
                            <label className="flex items-center gap-3">
                                <input
                                    type="radio"
                                    name="digest"
                                    value="daily"
                                    checked={preferences.digest === 'daily'}
                                    onChange={() => handleDigestChange('daily')}
                                    className="h-4 w-4 border-border text-primary focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-background"
                                />
                                <div>
                                    <p className="font-medium text-foreground">Daily Digest</p>
                                    <p className="text-sm text-foreground-muted">
                                        Receive a daily summary of notifications
                                    </p>
                                </div>
                            </label>
                            <label className="flex items-center gap-3">
                                <input
                                    type="radio"
                                    name="digest"
                                    value="weekly"
                                    checked={preferences.digest === 'weekly'}
                                    onChange={() => handleDigestChange('weekly')}
                                    className="h-4 w-4 border-border text-primary focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-background"
                                />
                                <div>
                                    <p className="font-medium text-foreground">Weekly Digest</p>
                                    <p className="text-sm text-foreground-muted">
                                        Receive a weekly summary every Monday
                                    </p>
                                </div>
                            </label>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function NotificationToggle({
    label,
    description,
    checked,
    onChange,
}: {
    label: string;
    description: string;
    checked: boolean;
    onChange: () => void;
}) {
    return (
        <div className="flex items-start justify-between gap-4 rounded-lg border border-border bg-background p-4 transition-colors hover:bg-background-secondary">
            <div className="flex-1">
                <p className="font-medium text-foreground">{label}</p>
                <p className="text-sm text-foreground-muted">{description}</p>
            </div>
            <Checkbox checked={checked} onChange={onChange} />
        </div>
    );
}
