import * as React from 'react';
import { SettingsLayout } from '../Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Input, Checkbox, Button, Badge } from '@/components/ui';
import { useForm } from '@inertiajs/react';
import { Send, CheckCircle2, Info } from 'lucide-react';

interface TelegramNotificationSettings {
    telegram_enabled: boolean;
    telegram_token: string | null;
    telegram_chat_id: string | null;

    deployment_success_telegram_notifications: boolean;
    deployment_failure_telegram_notifications: boolean;
    status_change_telegram_notifications: boolean;
    backup_success_telegram_notifications: boolean;
    backup_failure_telegram_notifications: boolean;
    scheduled_task_success_telegram_notifications: boolean;
    scheduled_task_failure_telegram_notifications: boolean;
    docker_cleanup_telegram_notifications: boolean;
    server_disk_usage_telegram_notifications: boolean;
    server_reachable_telegram_notifications: boolean;
    server_unreachable_telegram_notifications: boolean;
    server_patch_telegram_notifications: boolean;
    traefik_outdated_telegram_notifications: boolean;

    telegram_notifications_deployment_success_thread_id: string | null;
    telegram_notifications_deployment_failure_thread_id: string | null;
    telegram_notifications_status_change_thread_id: string | null;
    telegram_notifications_backup_success_thread_id: string | null;
    telegram_notifications_backup_failure_thread_id: string | null;
    telegram_notifications_scheduled_task_success_thread_id: string | null;
    telegram_notifications_scheduled_task_failure_thread_id: string | null;
    telegram_notifications_docker_cleanup_thread_id: string | null;
    telegram_notifications_server_disk_usage_thread_id: string | null;
    telegram_notifications_server_reachable_thread_id: string | null;
    telegram_notifications_server_unreachable_thread_id: string | null;
    telegram_notifications_server_patch_thread_id: string | null;
    telegram_notifications_traefik_outdated_thread_id: string | null;
}

interface Props {
    settings: TelegramNotificationSettings;
    lastTestAt?: string;
    lastTestStatus?: 'success' | 'error';
}

const eventOptions = [
    { id: 'deployment_success_telegram_notifications', label: 'Deployment Success', threadId: 'telegram_notifications_deployment_success_thread_id' },
    { id: 'deployment_failure_telegram_notifications', label: 'Deployment Failure', threadId: 'telegram_notifications_deployment_failure_thread_id' },
    { id: 'status_change_telegram_notifications', label: 'Application Status Change', threadId: 'telegram_notifications_status_change_thread_id' },
    { id: 'backup_success_telegram_notifications', label: 'Backup Success', threadId: 'telegram_notifications_backup_success_thread_id' },
    { id: 'backup_failure_telegram_notifications', label: 'Backup Failure', threadId: 'telegram_notifications_backup_failure_thread_id' },
    { id: 'scheduled_task_success_telegram_notifications', label: 'Scheduled Task Success', threadId: 'telegram_notifications_scheduled_task_success_thread_id' },
    { id: 'scheduled_task_failure_telegram_notifications', label: 'Scheduled Task Failure', threadId: 'telegram_notifications_scheduled_task_failure_thread_id' },
    { id: 'docker_cleanup_telegram_notifications', label: 'Docker Cleanup', threadId: 'telegram_notifications_docker_cleanup_thread_id' },
    { id: 'server_disk_usage_telegram_notifications', label: 'Server Disk Usage Alert', threadId: 'telegram_notifications_server_disk_usage_thread_id' },
    { id: 'server_reachable_telegram_notifications', label: 'Server Reachable', threadId: 'telegram_notifications_server_reachable_thread_id' },
    { id: 'server_unreachable_telegram_notifications', label: 'Server Unreachable', threadId: 'telegram_notifications_server_unreachable_thread_id' },
    { id: 'server_patch_telegram_notifications', label: 'Server Patch Available', threadId: 'telegram_notifications_server_patch_thread_id' },
    { id: 'traefik_outdated_telegram_notifications', label: 'Traefik Outdated', threadId: 'telegram_notifications_traefik_outdated_thread_id' },
];

export default function TelegramNotifications({ settings, lastTestAt, lastTestStatus }: Props) {
    const { data, setData, post, processing, errors, isDirty } = useForm(settings);
    const [isTesting, setIsTesting] = React.useState(false);
    const [showAdvanced, setShowAdvanced] = React.useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/settings/notifications/telegram', {
            preserveScroll: true,
            onSuccess: () => {
                // Success handled by flash message
            },
        });
    };

    const handleTest = () => {
        setIsTesting(true);
        post('/settings/notifications/telegram/test', {
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
                                <CardTitle>Telegram Notifications</CardTitle>
                                <CardDescription>
                                    Send deployment and server notifications to Telegram via bot
                                </CardDescription>
                            </div>
                            <Badge variant={data.telegram_enabled ? 'success' : 'default'}>
                                {data.telegram_enabled ? (
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
                                    label="Enable Telegram Notifications"
                                    checked={data.telegram_enabled}
                                    onChange={(e) => setData('telegram_enabled', e.target.checked)}
                                />

                                <Input
                                    label="Bot Token"
                                    type="password"
                                    value={data.telegram_token || ''}
                                    onChange={(e) => setData('telegram_token', e.target.value)}
                                    placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz"
                                    hint="Get this from BotFather on Telegram"
                                    error={errors.telegram_token}
                                    required
                                />

                                <Input
                                    label="Chat ID"
                                    type="text"
                                    value={data.telegram_chat_id || ''}
                                    onChange={(e) => setData('telegram_chat_id', e.target.value)}
                                    placeholder="-1001234567890"
                                    hint="The chat ID where notifications will be sent"
                                    error={errors.telegram_chat_id}
                                    required
                                />

                                <div className="rounded-lg border border-border bg-background-secondary p-3 text-sm text-foreground-subtle">
                                    <div className="flex items-start gap-2">
                                        <Info className="mt-0.5 h-4 w-4 flex-shrink-0" />
                                        <div>
                                            <p className="font-medium text-foreground">How to get your Chat ID:</p>
                                            <ol className="mt-1 list-inside list-decimal space-y-1">
                                                <li>Add your bot to a group or channel</li>
                                                <li>Send a message in that chat</li>
                                                <li>Visit: https://api.telegram.org/bot&lt;YOUR_BOT_TOKEN&gt;/getUpdates</li>
                                                <li>Look for &quot;chat&quot;:{`{"id":-1001234567890}`}</li>
                                            </ol>
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
                                                    setData(option.id as keyof TelegramNotificationSettings, true);
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
                                                    setData(option.id as keyof TelegramNotificationSettings, false);
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
                                            checked={data[option.id as keyof TelegramNotificationSettings] as boolean}
                                            onChange={(e) => setData(option.id as keyof TelegramNotificationSettings, e.target.checked)}
                                        />
                                    ))}
                                </div>
                            </div>

                            {/* Advanced: Thread IDs */}
                            <div className="space-y-4">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setShowAdvanced(!showAdvanced)}
                                >
                                    {showAdvanced ? 'Hide' : 'Show'} Advanced Settings (Thread IDs)
                                </Button>

                                {showAdvanced && (
                                    <div className="space-y-3 rounded-lg border border-border bg-background-secondary p-4">
                                        <p className="text-sm text-foreground-subtle">
                                            Optionally specify thread IDs for each event type to organize notifications in forum groups
                                        </p>
                                        {eventOptions.map((option) => (
                                            <Input
                                                key={option.threadId}
                                                label={`${option.label} Thread ID`}
                                                type="text"
                                                value={data[option.threadId as keyof TelegramNotificationSettings] as string || ''}
                                                onChange={(e) => setData(option.threadId as keyof TelegramNotificationSettings, e.target.value)}
                                                placeholder="Optional thread ID"
                                            />
                                        ))}
                                    </div>
                                )}
                            </div>

                            {/* Test & Save */}
                            <div className="flex items-center justify-between border-t border-border pt-6">
                                <div className="flex items-center gap-3">
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={handleTest}
                                        loading={isTesting}
                                        disabled={!data.telegram_token || !data.telegram_chat_id || processing}
                                    >
                                        <Send className="mr-2 h-4 w-4" />
                                        Send Test Message
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
                                <li>Open Telegram and search for "BotFather"</li>
                                <li>Send /newbot and follow the instructions</li>
                                <li>Copy the bot token provided</li>
                                <li>Add your bot to a group or channel</li>
                                <li>Get the chat ID using the method described above</li>
                                <li>Paste both values in the fields above and save</li>
                                <li>Click "Send Test Message" to verify it works</li>
                            </ol>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    );
}
