import * as React from 'react';
import { SettingsLayout } from '../Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Input, Checkbox, Button, Badge } from '@/components/ui';
import { useForm } from '@inertiajs/react';
import { Send, CheckCircle2 } from 'lucide-react';

interface DiscordNotificationSettings {
    discord_enabled: boolean;
    discord_webhook_url: string | null;
    discord_ping_enabled: boolean;

    deployment_success_discord_notifications: boolean;
    deployment_failure_discord_notifications: boolean;
    status_change_discord_notifications: boolean;
    backup_success_discord_notifications: boolean;
    backup_failure_discord_notifications: boolean;
    scheduled_task_success_discord_notifications: boolean;
    scheduled_task_failure_discord_notifications: boolean;
    docker_cleanup_discord_notifications: boolean;
    server_disk_usage_discord_notifications: boolean;
    server_reachable_discord_notifications: boolean;
    server_unreachable_discord_notifications: boolean;
    server_patch_discord_notifications: boolean;
    traefik_outdated_discord_notifications: boolean;
}

interface Props {
    settings: DiscordNotificationSettings;
    lastTestAt?: string;
    lastTestStatus?: 'success' | 'error';
}

const eventOptions = [
    { id: 'deployment_success_discord_notifications', label: 'Deployment Success', defaultEnabled: false },
    { id: 'deployment_failure_discord_notifications', label: 'Deployment Failure', defaultEnabled: true },
    { id: 'status_change_discord_notifications', label: 'Application Status Change', defaultEnabled: false },
    { id: 'backup_success_discord_notifications', label: 'Backup Success', defaultEnabled: false },
    { id: 'backup_failure_discord_notifications', label: 'Backup Failure', defaultEnabled: true },
    { id: 'scheduled_task_success_discord_notifications', label: 'Scheduled Task Success', defaultEnabled: false },
    { id: 'scheduled_task_failure_discord_notifications', label: 'Scheduled Task Failure', defaultEnabled: true },
    { id: 'docker_cleanup_discord_notifications', label: 'Docker Cleanup', defaultEnabled: false },
    { id: 'server_disk_usage_discord_notifications', label: 'Server Disk Usage Alert', defaultEnabled: true },
    { id: 'server_reachable_discord_notifications', label: 'Server Reachable', defaultEnabled: false },
    { id: 'server_unreachable_discord_notifications', label: 'Server Unreachable', defaultEnabled: true },
    { id: 'server_patch_discord_notifications', label: 'Server Patch Available', defaultEnabled: false },
    { id: 'traefik_outdated_discord_notifications', label: 'Traefik Outdated', defaultEnabled: true },
];

export default function DiscordNotifications({ settings, lastTestAt, lastTestStatus }: Props) {
    const { data, setData, post, processing, errors, isDirty } = useForm(settings);
    const [isTesting, setIsTesting] = React.useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/settings/notifications/discord', {
            preserveScroll: true,
            onSuccess: () => {
                // Success handled by flash message
            },
        });
    };

    const handleTest = () => {
        setIsTesting(true);
        post('/settings/notifications/discord/test', {
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
                                <CardTitle>Discord Notifications</CardTitle>
                                <CardDescription>
                                    Send deployment and server notifications to Discord via webhook
                                </CardDescription>
                            </div>
                            <Badge variant={data.discord_enabled ? 'success' : 'default'}>
                                {data.discord_enabled ? (
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
                                    label="Enable Discord Notifications"
                                    checked={data.discord_enabled}
                                    onChange={(e) => setData('discord_enabled', e.target.checked)}
                                />

                                <Input
                                    label="Webhook URL"
                                    type="url"
                                    value={data.discord_webhook_url || ''}
                                    onChange={(e) => setData('discord_webhook_url', e.target.value)}
                                    placeholder="https://discord.com/api/webhooks/..."
                                    hint="Get this from Discord Server Settings → Integrations → Webhooks"
                                    error={errors.discord_webhook_url}
                                    required
                                />

                                <Checkbox
                                    label="Enable @everyone ping for critical notifications"
                                    checked={data.discord_ping_enabled}
                                    onChange={(e) => setData('discord_ping_enabled', e.target.checked)}
                                />
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
                                                    setData(option.id as keyof DiscordNotificationSettings, true);
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
                                                    setData(option.id as keyof DiscordNotificationSettings, false);
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
                                            checked={data[option.id as keyof DiscordNotificationSettings] as boolean}
                                            onChange={(e) => setData(option.id as keyof DiscordNotificationSettings, e.target.checked)}
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
                                        disabled={!data.discord_webhook_url || processing}
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
                                <li>Go to your Discord server settings</li>
                                <li>Navigate to Integrations → Webhooks</li>
                                <li>Click "New Webhook" or edit an existing one</li>
                                <li>Choose the channel where you want to receive notifications</li>
                                <li>Copy the webhook URL</li>
                                <li>Paste it in the field above and save</li>
                                <li>Click "Send Test Notification" to verify it works</li>
                            </ol>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    );
}
