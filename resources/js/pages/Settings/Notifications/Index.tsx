import * as React from 'react';
import { SettingsLayout } from '../Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Button, Badge } from '@/components/ui';
import { Link, router } from '@inertiajs/react';
import { Bell, Mail, Webhook as WebhookIcon, Smartphone, Settings, CheckCircle2, XCircle, ChevronRight } from 'lucide-react';
import { Discord } from '@/components/icons/Discord';
import { Slack } from '@/components/icons/Slack';
import { Telegram } from '@/components/icons/Telegram';

interface NotificationChannel {
    id: string;
    name: string;
    description: string;
    icon: React.ComponentType<{ className?: string }>;
    enabled: boolean;
    configured: boolean;
    href: string;
}

interface Props {
    channels: {
        discord: { enabled: boolean; configured: boolean };
        slack: { enabled: boolean; configured: boolean };
        telegram: { enabled: boolean; configured: boolean };
        email: { enabled: boolean; configured: boolean };
        webhook: { enabled: boolean; configured: boolean };
        pushover: { enabled: boolean; configured: boolean };
    };
}

export default function NotificationsIndex({ channels }: Props) {
    const notificationChannels: NotificationChannel[] = [
        {
            id: 'discord',
            name: 'Discord',
            description: 'Send notifications to Discord channels via webhooks',
            icon: Discord,
            enabled: channels.discord.enabled,
            configured: channels.discord.configured,
            href: '/settings/notifications/discord',
        },
        {
            id: 'slack',
            name: 'Slack',
            description: 'Send notifications to Slack channels via webhooks',
            icon: Slack,
            enabled: channels.slack.enabled,
            configured: channels.slack.configured,
            href: '/settings/notifications/slack',
        },
        {
            id: 'telegram',
            name: 'Telegram',
            description: 'Send notifications to Telegram via bot',
            icon: Telegram,
            enabled: channels.telegram.enabled,
            configured: channels.telegram.configured,
            href: '/settings/notifications/telegram',
        },
        {
            id: 'email',
            name: 'Email',
            description: 'Send email notifications via SMTP or Resend',
            icon: Mail,
            enabled: channels.email.enabled,
            configured: channels.email.configured,
            href: '/settings/notifications/email',
        },
        {
            id: 'webhook',
            name: 'Webhook',
            description: 'Send custom webhook notifications to any endpoint',
            icon: WebhookIcon,
            enabled: channels.webhook.enabled,
            configured: channels.webhook.configured,
            href: '/settings/notifications/webhook',
        },
        {
            id: 'pushover',
            name: 'Pushover',
            description: 'Send push notifications to mobile devices',
            icon: Smartphone,
            enabled: channels.pushover.enabled,
            configured: channels.pushover.configured,
            href: '/settings/notifications/pushover',
        },
    ];

    const [processing, setProcessing] = React.useState(false);

    const handleQuickToggle = (channelId: string, currentlyEnabled: boolean) => {
        setProcessing(true);
        router.post(`/settings/notifications/${channelId}/toggle`, { enabled: !currentlyEnabled }, {
            onFinish: () => setProcessing(false),
        });
    };

    const enabledCount = notificationChannels.filter(c => c.enabled).length;
    const configuredCount = notificationChannels.filter(c => c.configured).length;

    return (
        <SettingsLayout activeSection="notifications">
            <div className="space-y-6">
                {/* Overview Card */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Notification Channels</CardTitle>
                                <CardDescription>
                                    Configure how you want to receive notifications about deployments, servers, and events
                                </CardDescription>
                            </div>
                            <div className="flex items-center gap-4 text-sm">
                                <div className="text-center">
                                    <div className="text-2xl font-semibold text-foreground">{enabledCount}</div>
                                    <div className="text-foreground-subtle">Enabled</div>
                                </div>
                                <div className="h-10 w-px bg-border" />
                                <div className="text-center">
                                    <div className="text-2xl font-semibold text-foreground">{configuredCount}</div>
                                    <div className="text-foreground-subtle">Configured</div>
                                </div>
                            </div>
                        </div>
                    </CardHeader>
                </Card>

                {/* Channels List */}
                <Card>
                    <CardHeader>
                        <CardTitle>Available Channels</CardTitle>
                        <CardDescription>
                            Click on a channel to configure its settings and event selection
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {notificationChannels.map((channel) => {
                                const Icon = channel.icon;
                                return (
                                    <div
                                        key={channel.id}
                                        className="group flex items-center justify-between rounded-lg border border-border bg-background p-4 transition-all hover:border-border/80 hover:bg-background-secondary"
                                    >
                                        <div className="flex items-center gap-4">
                                            <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                                <Icon className="h-6 w-6 text-primary" />
                                            </div>
                                            <div className="flex-1">
                                                <div className="flex items-center gap-2">
                                                    <p className="font-medium text-foreground">{channel.name}</p>
                                                    <div className="flex items-center gap-2">
                                                        {channel.enabled ? (
                                                            <Badge variant="success">
                                                                <CheckCircle2 className="mr-1 h-3 w-3" />
                                                                Enabled
                                                            </Badge>
                                                        ) : (
                                                            <Badge variant="default">
                                                                <XCircle className="mr-1 h-3 w-3" />
                                                                Disabled
                                                            </Badge>
                                                        )}
                                                        {!channel.configured && (
                                                            <Badge variant="warning">
                                                                Not Configured
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </div>
                                                <p className="mt-1 text-sm text-foreground-muted">
                                                    {channel.description}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {channel.configured && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => handleQuickToggle(channel.id, channel.enabled)}
                                                    disabled={processing}
                                                >
                                                    {channel.enabled ? 'Disable' : 'Enable'}
                                                </Button>
                                            )}
                                            <Link href={channel.href}>
                                                <Button
                                                    variant="secondary"
                                                    size="sm"
                                                >
                                                    <Settings className="mr-2 h-4 w-4" />
                                                    Configure
                                                    <ChevronRight className="ml-1 h-4 w-4" />
                                                </Button>
                                            </Link>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </CardContent>
                </Card>

                {/* Info Card */}
                <Card>
                    <CardHeader>
                        <CardTitle>About Notifications</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3 text-sm">
                            <p className="text-foreground-muted">
                                Saturn Platform can send notifications about important events like deployments, server status changes,
                                backups, and system alerts. You can configure multiple notification channels and customize which
                                events trigger notifications for each channel.
                            </p>

                            <div className="mt-4">
                                <h4 className="font-medium text-foreground">Available Event Types</h4>
                                <ul className="mt-2 grid grid-cols-1 gap-2 md:grid-cols-2">
                                    <li className="flex items-start gap-2 text-foreground-subtle">
                                        <Bell className="mt-0.5 h-4 w-4 flex-shrink-0" />
                                        <span>Deployment Success/Failure</span>
                                    </li>
                                    <li className="flex items-start gap-2 text-foreground-subtle">
                                        <Bell className="mt-0.5 h-4 w-4 flex-shrink-0" />
                                        <span>Application Status Changes</span>
                                    </li>
                                    <li className="flex items-start gap-2 text-foreground-subtle">
                                        <Bell className="mt-0.5 h-4 w-4 flex-shrink-0" />
                                        <span>Backup Success/Failure</span>
                                    </li>
                                    <li className="flex items-start gap-2 text-foreground-subtle">
                                        <Bell className="mt-0.5 h-4 w-4 flex-shrink-0" />
                                        <span>Scheduled Task Results</span>
                                    </li>
                                    <li className="flex items-start gap-2 text-foreground-subtle">
                                        <Bell className="mt-0.5 h-4 w-4 flex-shrink-0" />
                                        <span>Server Reachability Status</span>
                                    </li>
                                    <li className="flex items-start gap-2 text-foreground-subtle">
                                        <Bell className="mt-0.5 h-4 w-4 flex-shrink-0" />
                                        <span>Server Disk Usage Alerts</span>
                                    </li>
                                    <li className="flex items-start gap-2 text-foreground-subtle">
                                        <Bell className="mt-0.5 h-4 w-4 flex-shrink-0" />
                                        <span>Server Patch Availability</span>
                                    </li>
                                    <li className="flex items-start gap-2 text-foreground-subtle">
                                        <Bell className="mt-0.5 h-4 w-4 flex-shrink-0" />
                                        <span>Docker Cleanup Events</span>
                                    </li>
                                </ul>
                            </div>

                            <div className="mt-4">
                                <h4 className="font-medium text-foreground">Best Practices</h4>
                                <ul className="mt-2 list-inside list-disc space-y-1 text-foreground-subtle">
                                    <li>Configure at least one notification channel for critical events</li>
                                    <li>Use different channels for different event types (e.g., email for summaries, Slack for deployments)</li>
                                    <li>Test each channel after configuration to ensure it works correctly</li>
                                    <li>Avoid enabling too many success notifications to prevent alert fatigue</li>
                                </ul>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    );
}
