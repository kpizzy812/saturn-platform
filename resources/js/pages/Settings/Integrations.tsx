
import { SettingsLayout } from './Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Button, Badge } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import { usePermissions } from '@/hooks/usePermissions';
import { Github, GitlabIcon as Gitlab, MessageSquare, Zap, Settings, CheckCircle2, XCircle, AlertCircle, Plus, ExternalLink, Trash2 } from 'lucide-react';
import { Link, router } from '@inertiajs/react';

declare function route(name: string, params?: Record<string, any>): string;

interface Source {
    id: number;
    uuid: string;
    name: string;
    organization: string | null;
    type: 'github' | 'gitlab';
    connected: boolean;
    lastSync: string | null;
    applicationsCount: number;
}

interface NotificationChannel {
    enabled: boolean;
    configured: boolean;
    channel: string | null;
}

interface Props {
    sources: Source[];
    notificationChannels: {
        slack: NotificationChannel;
        discord: NotificationChannel;
    };
}

const sourceIcons = {
    github: Github,
    gitlab: Gitlab,
};

export default function IntegrationsSettings({ sources = [], notificationChannels }: Props) {
    const { addToast } = useToast();
    const { can } = usePermissions();
    const canManageIntegrations = can('settings.integrations');

    const handleDeleteSource = (source: Source) => {
        if (source.applicationsCount > 0) {
            addToast('error', `Cannot delete: ${source.applicationsCount} application(s) using this source`);
            return;
        }

        const routeName = source.type === 'github' ? 'sources.github.destroy' : 'sources.gitlab.destroy';

        router.delete(route(routeName, { id: source.id }), {
            onSuccess: () => {
                addToast('success', `${source.name} deleted successfully`);
            },
            onError: () => {
                addToast('error', `Failed to delete ${source.name}`);
            },
        });
    };

    return (
        <SettingsLayout activeSection="integrations">
            <div className="space-y-6">
                {/* Git Sources */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Git Sources</CardTitle>
                                <CardDescription>
                                    Connect GitHub and GitLab for automatic deployments
                                </CardDescription>
                            </div>
                            {canManageIntegrations && (
                                <Link href="/sources">
                                    <Button size="sm">
                                        <Plus className="mr-2 h-4 w-4" />
                                        Add Source
                                    </Button>
                                </Link>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        {sources.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-8 text-center">
                                <Github className="h-12 w-12 text-foreground-muted" />
                                <h3 className="mt-4 text-lg font-medium text-foreground">No Git sources connected</h3>
                                <p className="mt-2 text-sm text-foreground-muted">
                                    Connect GitHub or GitLab to enable automatic deployments from your repositories.
                                </p>
                                {canManageIntegrations && (
                                    <Link href="/sources" className="mt-4">
                                        <Button>
                                            <Plus className="mr-2 h-4 w-4" />
                                            Add Git Source
                                        </Button>
                                    </Link>
                                )}
                            </div>
                        ) : (
                            <div className="space-y-3">
                                {sources.map((source) => {
                                    const Icon = sourceIcons[source.type];
                                    const showRoute = source.type === 'github'
                                        ? `/sources/github/${source.id}`
                                        : `/sources/gitlab/${source.id}`;

                                    return (
                                        <div
                                            key={`${source.type}-${source.id}`}
                                            className="flex items-center justify-between rounded-lg border border-border bg-background p-4 transition-colors hover:border-border/80"
                                        >
                                            <div className="flex items-center gap-4">
                                                <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                                    <Icon className="h-6 w-6 text-primary" />
                                                </div>
                                                <div className="flex-1">
                                                    <div className="flex items-center gap-2">
                                                        <p className="font-medium text-foreground">{source.name}</p>
                                                        <Badge variant={source.connected ? 'success' : 'warning'}>
                                                            {source.connected ? (
                                                                <>
                                                                    <CheckCircle2 className="mr-1 h-3 w-3" />
                                                                    Connected
                                                                </>
                                                            ) : (
                                                                <>
                                                                    <AlertCircle className="mr-1 h-3 w-3" />
                                                                    Not Connected
                                                                </>
                                                            )}
                                                        </Badge>
                                                        <Badge variant="secondary">
                                                            {source.type === 'github' ? 'GitHub' : 'GitLab'}
                                                        </Badge>
                                                    </div>
                                                    <p className="mt-1 text-sm text-foreground-muted">
                                                        {source.organization || 'Personal'}
                                                        {source.applicationsCount > 0 && (
                                                            <span className="ml-2 text-foreground-subtle">
                                                                ({source.applicationsCount} application{source.applicationsCount !== 1 ? 's' : ''})
                                                            </span>
                                                        )}
                                                    </p>
                                                    {source.lastSync && (
                                                        <p className="text-xs text-foreground-subtle">
                                                            Last sync: {new Date(source.lastSync).toLocaleDateString()}
                                                        </p>
                                                    )}
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Link href={showRoute}>
                                                    <Button variant="secondary" size="sm">
                                                        <Settings className="mr-2 h-4 w-4" />
                                                        Settings
                                                    </Button>
                                                </Link>
                                                {canManageIntegrations && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => handleDeleteSource(source)}
                                                        disabled={source.applicationsCount > 0}
                                                        title={source.applicationsCount > 0 ? 'Remove all applications first' : 'Delete source'}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Notification Integrations */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Notification Channels</CardTitle>
                                <CardDescription>
                                    Get deployment and server notifications in Slack or Discord
                                </CardDescription>
                            </div>
                            <Link href="/settings/notifications">
                                <Button variant="secondary" size="sm">
                                    <ExternalLink className="mr-2 h-4 w-4" />
                                    Manage All
                                </Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {/* Slack */}
                            <div className="flex items-center justify-between rounded-lg border border-border bg-background p-4 transition-colors hover:border-border/80">
                                <div className="flex items-center gap-4">
                                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                        <MessageSquare className="h-6 w-6 text-primary" />
                                    </div>
                                    <div className="flex-1">
                                        <div className="flex items-center gap-2">
                                            <p className="font-medium text-foreground">Slack</p>
                                            {notificationChannels?.slack?.configured ? (
                                                <Badge variant={notificationChannels.slack.enabled ? 'success' : 'default'}>
                                                    {notificationChannels.slack.enabled ? (
                                                        <>
                                                            <CheckCircle2 className="mr-1 h-3 w-3" />
                                                            Enabled
                                                        </>
                                                    ) : (
                                                        <>
                                                            <XCircle className="mr-1 h-3 w-3" />
                                                            Disabled
                                                        </>
                                                    )}
                                                </Badge>
                                            ) : (
                                                <Badge variant="default">
                                                    <XCircle className="mr-1 h-3 w-3" />
                                                    Not Configured
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="mt-1 text-sm text-foreground-muted">
                                            Get deployment notifications in Slack
                                        </p>
                                    </div>
                                </div>
                                <Link href="/settings/notifications/slack">
                                    <Button variant="secondary" size="sm">
                                        <Settings className="mr-2 h-4 w-4" />
                                        Configure
                                    </Button>
                                </Link>
                            </div>

                            {/* Discord */}
                            <div className="flex items-center justify-between rounded-lg border border-border bg-background p-4 transition-colors hover:border-border/80">
                                <div className="flex items-center gap-4">
                                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                        <Zap className="h-6 w-6 text-primary" />
                                    </div>
                                    <div className="flex-1">
                                        <div className="flex items-center gap-2">
                                            <p className="font-medium text-foreground">Discord</p>
                                            {notificationChannels?.discord?.configured ? (
                                                <Badge variant={notificationChannels.discord.enabled ? 'success' : 'default'}>
                                                    {notificationChannels.discord.enabled ? (
                                                        <>
                                                            <CheckCircle2 className="mr-1 h-3 w-3" />
                                                            Enabled
                                                        </>
                                                    ) : (
                                                        <>
                                                            <XCircle className="mr-1 h-3 w-3" />
                                                            Disabled
                                                        </>
                                                    )}
                                                </Badge>
                                            ) : (
                                                <Badge variant="default">
                                                    <XCircle className="mr-1 h-3 w-3" />
                                                    Not Configured
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="mt-1 text-sm text-foreground-muted">
                                            Receive webhooks for deployment events
                                        </p>
                                    </div>
                                </div>
                                <Link href="/settings/notifications/discord">
                                    <Button variant="secondary" size="sm">
                                        <Settings className="mr-2 h-4 w-4" />
                                        Configure
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Integration Info */}
                <Card>
                    <CardHeader>
                        <CardTitle>About Integrations</CardTitle>
                        <CardDescription>
                            How integrations work with Saturn
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3 text-sm">
                            <p className="text-foreground-muted">
                                Integrations allow Saturn to connect with external services and automate your deployment workflow.
                            </p>
                            <ul className="list-inside list-disc space-y-2 text-foreground-subtle">
                                <li>GitHub and GitLab integrations enable automatic deployments on code push</li>
                                <li>Slack and Discord integrations send real-time deployment notifications</li>
                                <li>All integrations use secure OAuth or API tokens</li>
                                <li>You can disconnect integrations at any time</li>
                            </ul>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    );
}
