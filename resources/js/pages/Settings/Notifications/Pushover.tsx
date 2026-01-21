import * as React from 'react';
import { SettingsLayout } from '../Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Input, Checkbox, Button, Badge } from '@/components/ui';
import { useForm } from '@inertiajs/react';
import { Send, CheckCircle2, Smartphone } from 'lucide-react';

interface PushoverNotificationSettings {
    pushover_enabled: boolean;
    pushover_user_key: string | null;
    pushover_api_token: string | null;

    deployment_success_pushover_notifications: boolean;
    deployment_failure_pushover_notifications: boolean;
    status_change_pushover_notifications: boolean;
    backup_success_pushover_notifications: boolean;
    backup_failure_pushover_notifications: boolean;
    scheduled_task_success_pushover_notifications: boolean;
    scheduled_task_failure_pushover_notifications: boolean;
    docker_cleanup_pushover_notifications: boolean;
    server_disk_usage_pushover_notifications: boolean;
    server_reachable_pushover_notifications: boolean;
    server_unreachable_pushover_notifications: boolean;
    server_patch_pushover_notifications: boolean;
    traefik_outdated_pushover_notifications: boolean;
}

interface Props {
    settings: PushoverNotificationSettings;
    lastTestAt?: string;
    lastTestStatus?: 'success' | 'error';
}

const eventOptions = [
    { id: 'deployment_success_pushover_notifications', label: 'Deployment Success', priority: 'low' },
    { id: 'deployment_failure_pushover_notifications', label: 'Deployment Failure', priority: 'high' },
    { id: 'status_change_pushover_notifications', label: 'Application Status Change', priority: 'normal' },
    { id: 'backup_success_pushover_notifications', label: 'Backup Success', priority: 'low' },
    { id: 'backup_failure_pushover_notifications', label: 'Backup Failure', priority: 'high' },
    { id: 'scheduled_task_success_pushover_notifications', label: 'Scheduled Task Success', priority: 'low' },
    { id: 'scheduled_task_failure_pushover_notifications', label: 'Scheduled Task Failure', priority: 'high' },
    { id: 'docker_cleanup_pushover_notifications', label: 'Docker Cleanup', priority: 'low' },
    { id: 'server_disk_usage_pushover_notifications', label: 'Server Disk Usage Alert', priority: 'high' },
    { id: 'server_reachable_pushover_notifications', label: 'Server Reachable', priority: 'low' },
    { id: 'server_unreachable_pushover_notifications', label: 'Server Unreachable', priority: 'emergency' },
    { id: 'server_patch_pushover_notifications', label: 'Server Patch Available', priority: 'normal' },
    { id: 'traefik_outdated_pushover_notifications', label: 'Traefik Outdated', priority: 'high' },
];

export default function PushoverNotifications({ settings, lastTestAt, lastTestStatus }: Props) {
    const { data, setData, post, processing, errors, isDirty } = useForm(settings);
    const [isTesting, setIsTesting] = React.useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/settings/notifications/pushover', {
            preserveScroll: true,
            onSuccess: () => {
                // Success handled by flash message
            },
        });
    };

    const handleTest = () => {
        setIsTesting(true);
        post('/settings/notifications/pushover/test', {
            preserveScroll: true,
            onFinish: () => setIsTesting(false),
        });
    };

    return (
        <SettingsLayout activeSection="notifications">
            <div className="space-y-6">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Pushover Notifications</CardTitle>
                                <CardDescription>
                                    Send deployment and server push notifications to your mobile devices
                                </CardDescription>
                            </div>
                            <Badge variant={data.pushover_enabled ? 'success' : 'default'}>
                                {data.pushover_enabled ? (
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
                            {/* Configuration */}
                            <div className="space-y-4">
                                <h3 className="text-sm font-medium text-foreground">Configuration</h3>

                                <Checkbox
                                    label="Enable Pushover Notifications"
                                    checked={data.pushover_enabled}
                                    onChange={(e) => setData('pushover_enabled', e.target.checked)}
                                />

                                <Input
                                    label="User Key"
                                    type="password"
                                    value={data.pushover_user_key || ''}
                                    onChange={(e) => setData('pushover_user_key', e.target.value)}
                                    placeholder="uQiRzpo4DXghDmr9QzzfQu27cmVRsG"
                                    hint="Your Pushover user key from pushover.net"
                                    error={errors.pushover_user_key}
                                    required
                                />

                                <Input
                                    label="API Token"
                                    type="password"
                                    value={data.pushover_api_token || ''}
                                    onChange={(e) => setData('pushover_api_token', e.target.value)}
                                    placeholder="azGDORePK8gMaC0QOYAMyEEuzJnyUi"
                                    hint="Create an application at pushover.net to get an API token"
                                    error={errors.pushover_api_token}
                                    required
                                />

                                <div className="rounded-lg border border-border bg-background-secondary p-4">
                                    <div className="flex items-start gap-3">
                                        <Smartphone className="mt-0.5 h-5 w-5 flex-shrink-0 text-primary" />
                                        <div className="text-sm">
                                            <p className="font-medium text-foreground">Priority Levels</p>
                                            <ul className="mt-2 space-y-1 text-foreground-subtle">
                                                <li><strong>Low:</strong> No sound or vibration (success events)</li>
                                                <li><strong>Normal:</strong> Default notification behavior</li>
                                                <li><strong>High:</strong> Bypasses quiet hours (failures)</li>
                                                <li><strong>Emergency:</strong> Requires acknowledgment (critical failures)</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
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
                                                    setData(option.id as keyof PushoverNotificationSettings, true);
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
                                                    setData(option.id as keyof PushoverNotificationSettings, false);
                                                });
                                            }}
                                        >
                                            Disable All
                                        </Button>
                                    </div>
                                </div>

                                <div className="space-y-2 rounded-lg border border-border bg-background-secondary p-4">
                                    {eventOptions.map((option) => (
                                        <div key={option.id} className="flex items-center justify-between">
                                            <Checkbox
                                                label={option.label}
                                                checked={data[option.id as keyof PushoverNotificationSettings] as boolean}
                                                onChange={(e) => setData(option.id as keyof PushoverNotificationSettings, e.target.checked)}
                                            />
                                            <Badge
                                                variant={
                                                    option.priority === 'emergency' ? 'danger' :
                                                    option.priority === 'high' ? 'warning' :
                                                    option.priority === 'low' ? 'default' :
                                                    'success'
                                                }
                                            >
                                                {option.priority}
                                            </Badge>
                                        </div>
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
                                        disabled={!data.pushover_user_key || !data.pushover_api_token || processing}
                                    >
                                        <Send className="mr-2 h-4 w-4" />
                                        Send Test Notification
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
                        <CardTitle>How to Set Up</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3 text-sm">
                            <ol className="list-inside list-decimal space-y-2 text-foreground-muted">
                                <li>Sign up for a Pushover account at pushover.net ($5 one-time fee after 30-day trial)</li>
                                <li>Download the Pushover app on your iOS or Android device</li>
                                <li>Copy your User Key from the Pushover dashboard</li>
                                <li>Create a new Application/API Token at pushover.net/apps/build</li>
                                <li>Copy the API Token</li>
                                <li>Paste both keys in the fields above and save</li>
                                <li>Click "Send Test Notification" to verify delivery</li>
                            </ol>

                            <div className="mt-4 rounded-lg border border-border bg-background-secondary p-3">
                                <p className="text-foreground-subtle">
                                    <strong>Note:</strong> Pushover supports multiple devices per user. Once configured,
                                    you'll receive notifications on all your registered devices.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    );
}
