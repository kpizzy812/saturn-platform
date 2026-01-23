import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, Button } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import { Save, Trash2, AlertTriangle, Loader2 } from 'lucide-react';
import type { Service } from '@/types';

interface Props {
    service: Service;
}

export function SettingsTab({ service }: Props) {
    const [name, setName] = useState(service.name);
    const [description, setDescription] = useState(service.description || '');
    const [dockerCompose, setDockerCompose] = useState(service.docker_compose_raw || '');
    const [isSavingGeneral, setIsSavingGeneral] = useState(false);
    const [isSavingCompose, setIsSavingCompose] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);
    const { addToast } = useToast();

    const handleSaveGeneral = () => {
        setIsSavingGeneral(true);
        router.patch(`/api/v1/services/${service.uuid}`, {
            name,
            description,
        }, {
            onSuccess: () => {
                addToast('success', 'General settings saved successfully');
            },
            onError: () => {
                addToast('error', 'Failed to save general settings');
            },
            onFinish: () => {
                setIsSavingGeneral(false);
            },
        });
    };

    const handleSaveDockerCompose = () => {
        setIsSavingCompose(true);
        // API requires docker_compose_raw to be base64 encoded
        const encodedCompose = btoa(dockerCompose);
        router.patch(`/api/v1/services/${service.uuid}`, {
            docker_compose_raw: encodedCompose,
        }, {
            onSuccess: () => {
                addToast('success', 'Docker Compose configuration saved successfully');
            },
            onError: () => {
                addToast('error', 'Failed to save Docker Compose configuration');
            },
            onFinish: () => {
                setIsSavingCompose(false);
            },
        });
    };

    const handleDelete = () => {
        if (!confirm('Are you sure you want to delete this service? This action cannot be undone.')) {
            return;
        }
        if (!confirm('Please confirm again. All data, deployments, and configurations will be permanently deleted!')) {
            return;
        }

        setIsDeleting(true);
        router.delete(`/api/v1/services/${service.uuid}`, {
            data: {
                delete_volumes: true,
                delete_connected_networks: true,
                delete_configurations: true,
                docker_cleanup: true,
            },
            onSuccess: () => {
                addToast('success', 'Service deletion request queued');
                // Navigate to services list
                router.visit('/services');
            },
            onError: () => {
                addToast('error', 'Failed to delete service');
                setIsDeleting(false);
            },
        });
    };

    return (
        <div className="space-y-6">
            {/* General Settings */}
            <Card>
                <CardHeader>
                    <CardTitle>General Settings</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-foreground">
                            Service Name
                        </label>
                        <input
                            type="text"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            className="mt-1 w-full rounded-md border border-border bg-background px-3 py-2 text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                            placeholder="my-service"
                        />
                        <p className="mt-1 text-xs text-foreground-muted">
                            A unique name for your service
                        </p>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-foreground">
                            Description
                        </label>
                        <textarea
                            value={description}
                            onChange={(e) => setDescription(e.target.value)}
                            className="mt-1 w-full rounded-md border border-border bg-background px-3 py-2 text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                            placeholder="Service description"
                            rows={3}
                        />
                        <p className="mt-1 text-xs text-foreground-muted">
                            Optional description for your service
                        </p>
                    </div>

                    <div className="flex justify-end">
                        <Button onClick={handleSaveGeneral} disabled={isSavingGeneral}>
                            {isSavingGeneral ? (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            ) : (
                                <Save className="mr-2 h-4 w-4" />
                            )}
                            {isSavingGeneral ? 'Saving...' : 'Save Changes'}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* Docker Compose Configuration */}
            <Card>
                <CardHeader>
                    <CardTitle>Docker Compose Configuration</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-foreground">
                            Docker Compose YAML
                        </label>
                        <textarea
                            value={dockerCompose}
                            onChange={(e) => setDockerCompose(e.target.value)}
                            className="mt-1 h-64 w-full rounded-md border border-border bg-[#0d1117] p-4 font-mono text-sm text-gray-300 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                            placeholder="version: '3.8'&#10;services:&#10;  app:&#10;    image: nginx:latest&#10;    ports:&#10;      - '80:80'"
                        />
                        <p className="mt-1 text-xs text-foreground-muted">
                            Define your service using Docker Compose syntax
                        </p>
                    </div>

                    <div className="flex justify-end">
                        <Button onClick={handleSaveDockerCompose} disabled={isSavingCompose}>
                            {isSavingCompose ? (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            ) : (
                                <Save className="mr-2 h-4 w-4" />
                            )}
                            {isSavingCompose ? 'Saving...' : 'Save Configuration'}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* Service Information */}
            <Card>
                <CardHeader>
                    <CardTitle>Service Information</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                    <InfoRow label="Service UUID" value={service.uuid} />
                    <InfoRow label="Environment ID" value={String(service.environment_id)} />
                    <InfoRow label="Destination ID" value={String(service.destination_id)} />
                    <InfoRow
                        label="Created"
                        value={new Date(service.created_at).toLocaleString()}
                    />
                    <InfoRow
                        label="Last Updated"
                        value={new Date(service.updated_at).toLocaleString()}
                    />
                </CardContent>
            </Card>

            {/* Resource Limits */}
            <Card>
                <CardHeader>
                    <CardTitle>Resource Limits</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-foreground">
                            Memory Limit
                        </label>
                        <div className="mt-1 flex gap-2">
                            <input
                                type="number"
                                className="w-full rounded-md border border-border bg-background px-3 py-2 text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                                placeholder="512"
                            />
                            <select className="rounded-md border border-border bg-background px-3 py-2 text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                                <option>MB</option>
                                <option>GB</option>
                            </select>
                        </div>
                        <p className="mt-1 text-xs text-foreground-muted">
                            Maximum memory allocation for the service
                        </p>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-foreground">
                            CPU Limit
                        </label>
                        <input
                            type="number"
                            step="0.1"
                            className="mt-1 w-full rounded-md border border-border bg-background px-3 py-2 text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                            placeholder="1.0"
                        />
                        <p className="mt-1 text-xs text-foreground-muted">
                            CPU cores allocated (e.g., 1.0 = 1 core, 0.5 = half a core)
                        </p>
                    </div>

                    <div className="flex justify-end">
                        {/* TODO: Resource limits API not implemented yet in ServicesController */}
                        <Button disabled title="Resource limits API not yet implemented">
                            <Save className="mr-2 h-4 w-4" />
                            Save Limits
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* Webhooks */}
            <Card>
                <CardHeader>
                    <CardTitle>Webhooks</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-foreground">
                            Deployment Webhook URL
                        </label>
                        <div className="mt-1 flex gap-2">
                            <input
                                type="text"
                                className="w-full rounded-md border border-border bg-background px-3 py-2 font-mono text-sm text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                                value={`https://api.example.com/webhooks/${service.uuid}`}
                                readOnly
                            />
                            <Button
                                variant="secondary"
                                size="sm"
                                onClick={() => navigator.clipboard.writeText(`https://api.example.com/webhooks/${service.uuid}`)}
                            >
                                Copy
                            </Button>
                        </div>
                        <p className="mt-1 text-xs text-foreground-muted">
                            Use this webhook URL to trigger deployments from external services
                        </p>
                    </div>
                </CardContent>
            </Card>

            {/* Danger Zone */}
            <Card className="border-danger/50">
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <AlertTriangle className="h-5 w-5 text-danger" />
                        <CardTitle className="text-danger">Danger Zone</CardTitle>
                    </div>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="rounded-lg border border-danger/20 bg-danger/5 p-4">
                        <h4 className="font-medium text-foreground">Delete Service</h4>
                        <p className="mt-1 text-sm text-foreground-muted">
                            Once you delete a service, there is no going back. All data, deployments,
                            and configurations will be permanently deleted.
                        </p>
                        <Button
                            variant="danger"
                            size="sm"
                            className="mt-3"
                            onClick={handleDelete}
                            disabled={isDeleting}
                        >
                            {isDeleting ? (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            ) : (
                                <Trash2 className="mr-2 h-4 w-4" />
                            )}
                            {isDeleting ? 'Deleting...' : 'Delete Service'}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

function InfoRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between">
            <span className="text-sm text-foreground-muted">{label}</span>
            <span className="text-sm font-medium text-foreground">{value}</span>
        </div>
    );
}
