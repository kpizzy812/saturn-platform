import * as React from 'react';
import { SettingsLayout } from '../Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Input, Checkbox, Button, Badge, Select, Tabs } from '@/components/ui';
import { useForm } from '@inertiajs/react';
import { Send, CheckCircle2, Mail } from 'lucide-react';

interface EmailNotificationSettings {
    smtp_enabled: boolean;
    smtp_from_address: string | null;
    smtp_from_name: string | null;
    smtp_recipients: string | null;
    smtp_host: string | null;
    smtp_port: number | null;
    smtp_encryption: string | null;
    smtp_username: string | null;
    smtp_password: string | null;
    smtp_timeout: number | null;

    resend_enabled: boolean;
    resend_api_key: string | null;

    use_instance_email_settings: boolean;

    deployment_success_email_notifications: boolean;
    deployment_failure_email_notifications: boolean;
    status_change_email_notifications: boolean;
    backup_success_email_notifications: boolean;
    backup_failure_email_notifications: boolean;
    scheduled_task_success_email_notifications: boolean;
    scheduled_task_failure_email_notifications: boolean;
    server_disk_usage_email_notifications: boolean;
    server_patch_email_notifications: boolean;
    traefik_outdated_email_notifications: boolean;
}

interface Props {
    settings: EmailNotificationSettings;
    lastTestAt?: string;
    lastTestStatus?: 'success' | 'error';
    canUseInstanceSettings?: boolean;
}

const eventOptions = [
    { id: 'deployment_success_email_notifications', label: 'Deployment Success' },
    { id: 'deployment_failure_email_notifications', label: 'Deployment Failure' },
    { id: 'status_change_email_notifications', label: 'Application Status Change' },
    { id: 'backup_success_email_notifications', label: 'Backup Success' },
    { id: 'backup_failure_email_notifications', label: 'Backup Failure' },
    { id: 'scheduled_task_success_email_notifications', label: 'Scheduled Task Success' },
    { id: 'scheduled_task_failure_email_notifications', label: 'Scheduled Task Failure' },
    { id: 'server_disk_usage_email_notifications', label: 'Server Disk Usage Alert' },
    { id: 'server_patch_email_notifications', label: 'Server Patch Available' },
    { id: 'traefik_outdated_email_notifications', label: 'Traefik Outdated' },
];

export default function EmailNotifications({ settings, lastTestAt, lastTestStatus, canUseInstanceSettings }: Props) {
    const { data, setData, post, processing, errors, isDirty } = useForm(settings);
    const [isTesting, setIsTesting] = React.useState(false);
    const [activeTab, setActiveTab] = React.useState<'smtp' | 'resend' | 'instance'>('smtp');

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/settings/notifications/email', {
            preserveScroll: true,
            onSuccess: () => {
                // Success handled by flash message
            },
        });
    };

    const handleTest = () => {
        setIsTesting(true);
        post('/settings/notifications/email/test', {
            preserveScroll: true,
            onFinish: () => setIsTesting(false),
        });
    };

    const isEnabled = data.smtp_enabled || data.resend_enabled || data.use_instance_email_settings;

    return (
        <SettingsLayout activeSection="notifications">
            <div className="space-y-6">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Email Notifications</CardTitle>
                                <CardDescription>
                                    Send deployment and server notifications via email
                                </CardDescription>
                            </div>
                            <Badge variant={isEnabled ? 'success' : 'default'}>
                                {isEnabled ? (
                                    <>
                                        <CheckCircle2 className="mr-1 h-3 w-3" />
                                        Enabled
                                    </>
                                ) : (
                                    'Disabled'
                                )}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            {/* Email Provider Selection */}
                            <div className="space-y-4">
                                <h3 className="text-sm font-medium text-foreground">Email Provider</h3>

                                <Tabs
                                    tabs={[
                                        { id: 'smtp', label: 'SMTP' },
                                        { id: 'resend', label: 'Resend' },
                                        ...(canUseInstanceSettings ? [{ id: 'instance', label: 'Instance Settings' }] : []),
                                    ]}
                                    activeTab={activeTab}
                                    onChange={(tab) => setActiveTab(tab as 'smtp' | 'resend' | 'instance')}
                                />

                                {/* SMTP Configuration */}
                                {activeTab === 'smtp' && (
                                    <div className="space-y-4 rounded-lg border border-border bg-background-secondary p-4">
                                        <Checkbox
                                            label="Enable SMTP Email Notifications"
                                            checked={data.smtp_enabled}
                                            onChange={(e) => setData('smtp_enabled', e.target.checked)}
                                        />

                                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <Input
                                                label="From Address"
                                                type="email"
                                                value={data.smtp_from_address || ''}
                                                onChange={(e) => setData('smtp_from_address', e.target.value)}
                                                placeholder="noreply@example.com"
                                                error={errors.smtp_from_address}
                                            />

                                            <Input
                                                label="From Name"
                                                type="text"
                                                value={data.smtp_from_name || ''}
                                                onChange={(e) => setData('smtp_from_name', e.target.value)}
                                                placeholder="Saturn Platform Notifications"
                                                error={errors.smtp_from_name}
                                            />
                                        </div>

                                        <Input
                                            label="Recipients"
                                            type="text"
                                            value={data.smtp_recipients || ''}
                                            onChange={(e) => setData('smtp_recipients', e.target.value)}
                                            placeholder="admin@example.com, team@example.com"
                                            hint="Comma-separated list of email addresses"
                                            error={errors.smtp_recipients}
                                        />

                                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                            <Input
                                                label="SMTP Host"
                                                type="text"
                                                value={data.smtp_host || ''}
                                                onChange={(e) => setData('smtp_host', e.target.value)}
                                                placeholder="smtp.example.com"
                                                error={errors.smtp_host}
                                            />

                                            <Input
                                                label="Port"
                                                type="number"
                                                value={data.smtp_port?.toString() || ''}
                                                onChange={(e) => setData('smtp_port', parseInt(e.target.value) || null)}
                                                placeholder="587"
                                                error={errors.smtp_port}
                                            />

                                            <div className="space-y-2">
                                                <label className="text-sm font-medium text-foreground">Encryption</label>
                                                <select
                                                    value={data.smtp_encryption || ''}
                                                    onChange={(e) => setData('smtp_encryption', e.target.value)}
                                                    className="flex h-10 w-full rounded-lg border border-white/[0.08] bg-background px-3 py-2 text-sm text-foreground focus:border-primary/50 focus:outline-none focus:ring-2 focus:ring-primary/20"
                                                >
                                                    <option value="">None</option>
                                                    <option value="tls">TLS</option>
                                                    <option value="ssl">SSL</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <Input
                                                label="Username"
                                                type="text"
                                                value={data.smtp_username || ''}
                                                onChange={(e) => setData('smtp_username', e.target.value)}
                                                placeholder="smtp_user"
                                                error={errors.smtp_username}
                                            />

                                            <Input
                                                label="Password"
                                                type="password"
                                                value={data.smtp_password || ''}
                                                onChange={(e) => setData('smtp_password', e.target.value)}
                                                placeholder="••••••••"
                                                error={errors.smtp_password}
                                            />
                                        </div>

                                        <Input
                                            label="Timeout (seconds)"
                                            type="number"
                                            value={data.smtp_timeout?.toString() || ''}
                                            onChange={(e) => setData('smtp_timeout', parseInt(e.target.value) || null)}
                                            placeholder="30"
                                            hint="Connection timeout in seconds"
                                            error={errors.smtp_timeout}
                                        />
                                    </div>
                                )}

                                {/* Resend Configuration */}
                                {activeTab === 'resend' && (
                                    <div className="space-y-4 rounded-lg border border-border bg-background-secondary p-4">
                                        <Checkbox
                                            label="Enable Resend Email Notifications"
                                            checked={data.resend_enabled}
                                            onChange={(e) => setData('resend_enabled', e.target.checked)}
                                        />

                                        <Input
                                            label="Resend API Key"
                                            type="password"
                                            value={data.resend_api_key || ''}
                                            onChange={(e) => setData('resend_api_key', e.target.value)}
                                            placeholder="re_xxxxxxxxxxxx"
                                            hint="Get your API key from resend.com/api-keys"
                                            error={errors.resend_api_key}
                                        />

                                        <Input
                                            label="Recipients"
                                            type="text"
                                            value={data.smtp_recipients || ''}
                                            onChange={(e) => setData('smtp_recipients', e.target.value)}
                                            placeholder="admin@example.com, team@example.com"
                                            hint="Comma-separated list of email addresses"
                                            error={errors.smtp_recipients}
                                        />
                                    </div>
                                )}

                                {/* Instance Settings */}
                                {activeTab === 'instance' && canUseInstanceSettings && (
                                    <div className="space-y-4 rounded-lg border border-border bg-background-secondary p-4">
                                        <Checkbox
                                            label="Use Instance Email Settings"
                                            checked={data.use_instance_email_settings}
                                            onChange={(e) => setData('use_instance_email_settings', e.target.checked)}
                                        />

                                        <p className="text-sm text-foreground-muted">
                                            Use the email configuration set up at the instance level. This is managed by your Saturn Platform administrator.
                                        </p>

                                        <Input
                                            label="Recipients"
                                            type="text"
                                            value={data.smtp_recipients || ''}
                                            onChange={(e) => setData('smtp_recipients', e.target.value)}
                                            placeholder="admin@example.com, team@example.com"
                                            hint="Comma-separated list of email addresses"
                                            error={errors.smtp_recipients}
                                        />
                                    </div>
                                )}
                            </div>

                            {/* Event Selection */}
                            <div className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <h3 className="text-sm font-medium text-foreground">Event Selection</h3>
                                    <div className="flex gap-2">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => {
                                                eventOptions.forEach(option => {
                                                    setData(option.id as keyof EmailNotificationSettings, true);
                                                });
                                            }}
                                        >
                                            Enable All
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => {
                                                eventOptions.forEach(option => {
                                                    setData(option.id as keyof EmailNotificationSettings, false);
                                                });
                                            }}
                                        >
                                            Disable All
                                        </Button>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-3 rounded-lg border border-border bg-background-secondary p-4 md:grid-cols-2">
                                    {eventOptions.map((option) => (
                                        <Checkbox
                                            key={option.id}
                                            label={option.label}
                                            checked={data[option.id as keyof EmailNotificationSettings] as boolean}
                                            onChange={(e) => setData(option.id as keyof EmailNotificationSettings, e.target.checked)}
                                        />
                                    ))}
                                </div>
                            </div>

                            {/* Test & Save */}
                            <div className="flex items-center justify-between border-t border-border pt-6">
                                <div className="flex items-center gap-3">
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={handleTest}
                                        loading={isTesting}
                                        disabled={!isEnabled || processing}
                                    >
                                        <Send className="mr-2 h-4 w-4" />
                                        Send Test Email
                                    </Button>
                                    {lastTestAt && (
                                        <div className="text-sm text-foreground-subtle">
                                            Last test: {new Date(lastTestAt).toLocaleString()}
                                            {lastTestStatus === 'success' && (
                                                <span className="ml-2 text-success">✓ Success</span>
                                            )}
                                            {lastTestStatus === 'error' && (
                                                <span className="ml-2 text-danger">✗ Failed</span>
                                            )}
                                        </div>
                                    )}
                                </div>

                                <Button
                                    type="submit"
                                    loading={processing}
                                    disabled={!isDirty}
                                >
                                    Save Settings
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* Help Card */}
                <Card>
                    <CardHeader>
                        <CardTitle>Email Provider Options</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4 text-sm">
                            <div>
                                <h4 className="font-medium text-foreground">SMTP</h4>
                                <p className="mt-1 text-foreground-muted">
                                    Use any SMTP server (Gmail, Outlook, Sendgrid, Mailgun, etc.)
                                </p>
                            </div>
                            <div>
                                <h4 className="font-medium text-foreground">Resend</h4>
                                <p className="mt-1 text-foreground-muted">
                                    Modern email API with simple setup. Sign up at resend.com
                                </p>
                            </div>
                            {canUseInstanceSettings && (
                                <div>
                                    <h4 className="font-medium text-foreground">Instance Settings</h4>
                                    <p className="mt-1 text-foreground-muted">
                                        Use the email configuration managed by your Saturn Platform administrator
                                    </p>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    );
}
