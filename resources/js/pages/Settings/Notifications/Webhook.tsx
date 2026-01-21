import * as React from 'react';
import { SettingsLayout } from '../Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Input, Checkbox, Button, Badge, Textarea } from '@/components/ui';
import { useForm } from '@inertiajs/react';
import { Send, CheckCircle2, Code } from 'lucide-react';

interface WebhookNotificationSettings {
    webhook_enabled: boolean;
    webhook_url: string | null;

    deployment_success_webhook_notifications: boolean;
    deployment_failure_webhook_notifications: boolean;
    status_change_webhook_notifications: boolean;
    backup_success_webhook_notifications: boolean;
    backup_failure_webhook_notifications: boolean;
    scheduled_task_success_webhook_notifications: boolean;
    scheduled_task_failure_webhook_notifications: boolean;
    docker_cleanup_success_webhook_notifications: boolean;
    docker_cleanup_failure_webhook_notifications: boolean;
    server_disk_usage_webhook_notifications: boolean;
    server_reachable_webhook_notifications: boolean;
    server_unreachable_webhook_notifications: boolean;
    server_patch_webhook_notifications: boolean;
    traefik_outdated_webhook_notifications: boolean;
}

interface Props {
    settings: WebhookNotificationSettings;
    lastTestAt?: string;
    lastTestStatus?: 'success' | 'error';
}

const eventOptions = [
    { id: 'deployment_success_webhook_notifications', label: 'Deployment Success' },
    { id: 'deployment_failure_webhook_notifications', label: 'Deployment Failure' },
    { id: 'status_change_webhook_notifications', label: 'Application Status Change' },
    { id: 'backup_success_webhook_notifications', label: 'Backup Success' },
    { id: 'backup_failure_webhook_notifications', label: 'Backup Failure' },
    { id: 'scheduled_task_success_webhook_notifications', label: 'Scheduled Task Success' },
    { id: 'scheduled_task_failure_webhook_notifications', label: 'Scheduled Task Failure' },
    { id: 'docker_cleanup_success_webhook_notifications', label: 'Docker Cleanup Success' },
    { id: 'docker_cleanup_failure_webhook_notifications', label: 'Docker Cleanup Failure' },
    { id: 'server_disk_usage_webhook_notifications', label: 'Server Disk Usage Alert' },
    { id: 'server_reachable_webhook_notifications', label: 'Server Reachable' },
    { id: 'server_unreachable_webhook_notifications', label: 'Server Unreachable' },
    { id: 'server_patch_webhook_notifications', label: 'Server Patch Available' },
    { id: 'traefik_outdated_webhook_notifications', label: 'Traefik Outdated' },
];

const examplePayload = `{
  "event": "deployment_success",
  "timestamp": "2024-03-28T10:30:00Z",
  "application": {
    "name": "my-app",
    "uuid": "abc123",
    "url": "https://my-app.example.com"
  },
  "deployment": {
    "commit": "a1b2c3d",
    "branch": "main",
    "message": "Deploy to production"
  },
  "server": {
    "name": "prod-server-1",
    "ip": "192.168.1.100"
  }
}`;

export default function WebhookNotifications({ settings, lastTestAt, lastTestStatus }: Props) {
    const { data, setData, post, processing, errors, isDirty } = useForm(settings);
    const [isTesting, setIsTesting] = React.useState(false);
    const [showPayloadExample, setShowPayloadExample] = React.useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/settings/notifications/webhook', {
            preserveScroll: true,
            onSuccess: () => {
                // Success handled by flash message
            },
        });
    };

    const handleTest = () => {
        setIsTesting(true);
        post('/settings/notifications/webhook/test', {
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
                                <CardTitle>Webhook Notifications</CardTitle>
                                <CardDescription>
                                    Send deployment and server event data to a custom webhook endpoint
                                </CardDescription>
                            </div>
                            <Badge variant={data.webhook_enabled ? 'success' : 'default'}>
                                {data.webhook_enabled ? (
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
                                    label="Enable Webhook Notifications"
                                    checked={data.webhook_enabled}
                                    onChange={(e) => setData('webhook_enabled', e.target.checked)}
                                />

                                <Input
                                    label="Webhook URL"
                                    type="url"
                                    value={data.webhook_url || ''}
                                    onChange={(e) => setData('webhook_url', e.target.value)}
                                    placeholder="https://api.example.com/webhooks/saturn"
                                    hint="POST requests will be sent to this URL with JSON payload"
                                    error={errors.webhook_url}
                                    required
                                />

                                <div className="rounded-lg border border-border bg-background-secondary p-4">
                                    <div className="flex items-center justify-between">
                                        <h4 className="text-sm font-medium text-foreground">Payload Format</h4>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setShowPayloadExample(!showPayloadExample)}
                                        >
                                            <Code className="mr-2 h-4 w-4" />
                                            {showPayloadExample ? 'Hide' : 'Show'} Example
                                        </Button>
                                    </div>

                                    {showPayloadExample && (
                                        <div className="mt-3">
                                            <pre className="overflow-x-auto rounded-lg bg-background p-3 text-xs text-foreground-muted">
                                                {examplePayload}
                                            </pre>
                                        </div>
                                    )}

                                    <div className="mt-3 space-y-2 text-sm text-foreground-subtle">
                                        <p><strong>Method:</strong> POST</p>
                                        <p><strong>Content-Type:</strong> application/json</p>
                                        <p><strong>Timeout:</strong> 10 seconds</p>
                                        <p><strong>Retries:</strong> 3 attempts with exponential backoff</p>
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
                                                    setData(option.id as keyof WebhookNotificationSettings, true);
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
                                                    setData(option.id as keyof WebhookNotificationSettings, false);
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
                                            checked={data[option.id as keyof WebhookNotificationSettings] as boolean}
                                            onChange={(e) => setData(option.id as keyof WebhookNotificationSettings, e.target.checked)}
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
                                        disabled={!data.webhook_url || processing}
                                    >
                                        <Send className="mr-2 h-4 w-4" />
                                        Send Test Webhook
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
                        <CardTitle>Webhook Integration Guide</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3 text-sm">
                            <div>
                                <h4 className="font-medium text-foreground">Setting up your endpoint</h4>
                                <ol className="mt-2 list-inside list-decimal space-y-1 text-foreground-muted">
                                    <li>Create a POST endpoint that accepts JSON</li>
                                    <li>Return a 200-299 status code to acknowledge receipt</li>
                                    <li>Process the webhook asynchronously if possible</li>
                                    <li>Implement proper error handling and logging</li>
                                </ol>
                            </div>

                            <div className="mt-4">
                                <h4 className="font-medium text-foreground">Security Recommendations</h4>
                                <ul className="mt-2 list-inside list-disc space-y-1 text-foreground-muted">
                                    <li>Use HTTPS for your webhook endpoint</li>
                                    <li>Implement webhook signature verification (coming soon)</li>
                                    <li>Rate limit your endpoint to prevent abuse</li>
                                    <li>Validate the payload structure before processing</li>
                                </ul>
                            </div>

                            <div className="mt-4">
                                <h4 className="font-medium text-foreground">Common Use Cases</h4>
                                <ul className="mt-2 list-inside list-disc space-y-1 text-foreground-muted">
                                    <li>Trigger CI/CD pipelines on deployment events</li>
                                    <li>Update monitoring dashboards with deployment status</li>
                                    <li>Send custom notifications to internal systems</li>
                                    <li>Log events to external analytics platforms</li>
                                </ul>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    );
}
