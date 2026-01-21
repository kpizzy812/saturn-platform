import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button, Badge } from '@/components/ui';
import { ArrowLeft, Plus, FileText, CheckCircle, XCircle, Trash2, ExternalLink } from 'lucide-react';
import type { Server as ServerType } from '@/types';

interface Props {
    server: ServerType;
    logDrains?: LogDrain[];
}

interface LogDrain {
    id: number;
    uuid: string;
    name: string;
    type: 'http' | 'tcp' | 'syslog';
    endpoint: string;
    enabled: boolean;
    created_at: string;
}

export default function ServerLogDrainsIndex({ server, logDrains = [] }: Props) {
    const handleDelete = (logDrain: LogDrain) => {
        if (confirm(`Are you sure you want to delete log drain "${logDrain.name}"?`)) {
            router.delete(`/servers/${server.uuid}/log-drains/${logDrain.uuid}`);
        }
    };

    const handleToggle = (logDrain: LogDrain) => {
        router.patch(`/servers/${server.uuid}/log-drains/${logDrain.uuid}`, {
            enabled: !logDrain.enabled,
        });
    };

    return (
        <AppLayout
            title={`${server.name} - Log Drains`}
            breadcrumbs={[
                { label: 'Servers', href: '/servers' },
                { label: server.name, href: `/servers/${server.uuid}` },
                { label: 'Log Drains' },
            ]}
        >
            {/* Header */}
            <div className="mb-6">
                <Link
                    href={`/servers/${server.uuid}`}
                    className="mb-4 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="mr-2 h-4 w-4" />
                    Back to Server
                </Link>
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <div className="flex h-14 w-14 items-center justify-center rounded-xl bg-primary/10">
                            <FileText className="h-7 w-7 text-primary" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold text-foreground">Log Drains</h1>
                            <p className="text-foreground-muted">Forward server logs to external services</p>
                        </div>
                    </div>
                    <Button onClick={() => router.visit(`/servers/${server.uuid}/log-drains/create`)}>
                        <Plus className="mr-2 h-4 w-4" />
                        New Log Drain
                    </Button>
                </div>
            </div>

            {/* Info Card */}
            <Card className="mb-6">
                <CardContent className="p-4">
                    <div className="flex items-start gap-3">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-info/10">
                            <FileText className="h-5 w-5 text-info" />
                        </div>
                        <div>
                            <h4 className="font-medium text-foreground">About Log Drains</h4>
                            <p className="mt-1 text-sm text-foreground-muted">
                                Log drains allow you to forward server and application logs to external logging services
                                like Datadog, New Relic, Papertrail, or your own logging infrastructure.
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Log Drains List */}
            {logDrains.length > 0 ? (
                <div className="space-y-3">
                    {logDrains.map((drain) => (
                        <Card key={drain.uuid}>
                            <CardContent className="p-5">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-4">
                                        <div className={`flex h-12 w-12 items-center justify-center rounded-lg ${
                                            drain.enabled ? 'bg-success/10' : 'bg-background-tertiary'
                                        }`}>
                                            <FileText className={`h-6 w-6 ${
                                                drain.enabled ? 'text-success' : 'text-foreground-subtle'
                                            }`} />
                                        </div>
                                        <div className="flex-1">
                                            <div className="flex items-center gap-2">
                                                <h3 className="font-semibold text-foreground">{drain.name}</h3>
                                                <Badge variant={drain.enabled ? 'success' : 'secondary'} size="sm">
                                                    {drain.enabled ? 'Enabled' : 'Disabled'}
                                                </Badge>
                                                <Badge variant="secondary" size="sm">
                                                    {drain.type.toUpperCase()}
                                                </Badge>
                                            </div>
                                            <p className="mt-1 text-sm text-foreground-muted">
                                                {drain.endpoint}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => handleToggle(drain)}
                                        >
                                            {drain.enabled ? (
                                                <XCircle className="h-4 w-4 text-foreground-muted" />
                                            ) : (
                                                <CheckCircle className="h-4 w-4 text-foreground-muted" />
                                            )}
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => handleDelete(drain)}
                                        >
                                            <Trash2 className="h-4 w-4 text-danger" />
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            ) : (
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-16">
                        <div className="flex h-16 w-16 items-center justify-center rounded-full bg-background-secondary">
                            <FileText className="h-8 w-8 text-foreground-subtle" />
                        </div>
                        <h3 className="mt-4 font-medium text-foreground">No log drains configured</h3>
                        <p className="mt-1 text-sm text-foreground-muted">
                            Set up your first log drain to start forwarding logs
                        </p>
                        <Button
                            className="mt-4"
                            onClick={() => router.visit(`/servers/${server.uuid}/log-drains/create`)}
                        >
                            <Plus className="mr-2 h-4 w-4" />
                            Create Log Drain
                        </Button>
                    </CardContent>
                </Card>
            )}

            {/* Popular Services */}
            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>Popular Log Services</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
                        {[
                            { name: 'Datadog', url: 'https://www.datadoghq.com' },
                            { name: 'New Relic', url: 'https://newrelic.com' },
                            { name: 'Papertrail', url: 'https://www.papertrail.com' },
                            { name: 'Logtail', url: 'https://betterstack.com/logtail' },
                        ].map((service) => (
                            <a
                                key={service.name}
                                href={service.url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-3 transition-colors hover:border-primary/50"
                            >
                                <span className="text-sm font-medium text-foreground">{service.name}</span>
                                <ExternalLink className="h-4 w-4 text-foreground-muted" />
                            </a>
                        ))}
                    </div>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
