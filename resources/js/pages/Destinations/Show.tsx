import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button, Badge, Input, useConfirm } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import {
    Network, Server, Box, Edit, Trash2, ArrowLeft,
    CheckCircle2, XCircle, Settings, ExternalLink,
    Copy, AlertTriangle, Info
} from 'lucide-react';
import { getStatusVariant, getStatusLabel } from '@/lib/statusUtils';

interface Resource {
    id: number;
    uuid: string;
    name: string;
    type: 'application' | 'database' | 'service';
    status: 'running' | 'stopped' | 'exited' | 'restarting';
    created_at: string;
}

interface Destination {
    id: number;
    uuid: string;
    name: string;
    network: string;
    server: {
        id: number;
        uuid: string;
        name: string;
        ip: string;
        port: number;
    };
    is_default: boolean;
    status: 'active' | 'inactive';
    resources_count: number;
    created_at: string;
}

interface Props {
    destination: Destination;
    resources: Resource[];
}

export default function DestinationShow({ destination, resources = [] }: Props) {
    const confirm = useConfirm();
    const { addToast } = useToast();
    const [isEditing, setIsEditing] = useState(false);
    const [name, setName] = useState(destination.name);
    const [copied, setCopied] = useState(false);

    const handleUpdate = () => {
        router.put(`/destinations/${destination.uuid}`, { name }, {
            preserveScroll: true,
            onSuccess: () => setIsEditing(false),
        });
    };

    const handleDelete = async () => {
        if (resources.length > 0) {
            addToast('error', 'Cannot delete destination with active resources. Remove all resources first.');
            return;
        }
        const confirmed = await confirm({
            title: 'Delete Destination',
            description: 'Are you sure you want to delete this destination? This action cannot be undone.',
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            router.delete(`/destinations/${destination.uuid}`);
        }
    };

    const handleSetDefault = () => {
        router.post(`/destinations/${destination.uuid}/set-default`);
    };

    const copyToClipboard = (text: string) => {
        navigator.clipboard.writeText(text);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const getResourceTypeColor = (type: string) => {
        switch (type) {
            case 'application': return 'text-primary';
            case 'database': return 'text-warning';
            case 'service': return 'text-info';
            default: return 'text-foreground-muted';
        }
    };


    return (
        <AppLayout
            title={destination.name}
            breadcrumbs={[
                { label: 'Dashboard', href: '/new' },
                { label: 'Destinations', href: '/destinations' },
                { label: destination.name },
            ]}
        >
            <Head title={`${destination.name} - Destination`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4 mb-6">
                    <Link href="/destinations">
                        <Button variant="ghost" size="sm">
                            <ArrowLeft className="h-4 w-4 mr-2" />
                            Back
                        </Button>
                    </Link>
                </div>

                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <div className="h-14 w-14 rounded-xl bg-primary/10 flex items-center justify-center">
                            <Network className="h-7 w-7 text-primary" />
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-2xl font-bold">{destination.name}</h1>
                                {destination.status === 'active' ? (
                                    <CheckCircle2 className="h-5 w-5 text-success" />
                                ) : (
                                    <XCircle className="h-5 w-5 text-danger" />
                                )}
                                {destination.is_default && (
                                    <Badge variant="info">Default</Badge>
                                )}
                            </div>
                            <p className="text-foreground-muted">
                                <code className="text-sm">{destination.network}</code>
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="secondary" onClick={() => setIsEditing(!isEditing)}>
                            <Edit className="h-4 w-4 mr-2" />
                            Edit
                        </Button>
                        <Button
                            variant="ghost"
                            className="text-danger hover:text-danger"
                            onClick={handleDelete}
                            disabled={resources.length > 0}
                        >
                            <Trash2 className="h-4 w-4 mr-2" />
                            Delete
                        </Button>
                    </div>
                </div>

                {/* Edit Form */}
                {isEditing && (
                    <Card className="border-primary/50">
                        <CardHeader>
                            <CardTitle>Edit Destination</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                <div>
                                    <label className="block text-sm font-medium mb-2">Name</label>
                                    <Input
                                        value={name}
                                        onChange={(e) => setName(e.target.value)}
                                        placeholder="Destination name"
                                    />
                                </div>
                                <div className="flex items-center gap-2">
                                    <Button onClick={handleUpdate}>Save Changes</Button>
                                    <Button variant="ghost" onClick={() => {
                                        setIsEditing(false);
                                        setName(destination.name);
                                    }}>
                                        Cancel
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Network Information */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Settings className="h-5 w-5" />
                            Network Information
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="text-sm text-foreground-muted">Network Name</label>
                                <div className="flex items-center gap-2 mt-1">
                                    <code className="text-sm font-mono bg-background-secondary px-2 py-1 rounded">
                                        {destination.network}
                                    </code>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => copyToClipboard(destination.network)}
                                    >
                                        {copied ? (
                                            <CheckCircle2 className="h-4 w-4 text-success" />
                                        ) : (
                                            <Copy className="h-4 w-4" />
                                        )}
                                    </Button>
                                </div>
                            </div>
                            <div>
                                <label className="text-sm text-foreground-muted">Status</label>
                                <div className="mt-1">
                                    <Badge variant={destination.status === 'active' ? 'success' : 'default'}>
                                        {destination.status}
                                    </Badge>
                                </div>
                            </div>
                            <div>
                                <label className="text-sm text-foreground-muted">Server</label>
                                <div className="mt-1">
                                    <Link
                                        href={`/servers/${destination.server.uuid}`}
                                        className="text-primary hover:underline flex items-center gap-1"
                                    >
                                        {destination.server.name}
                                        <ExternalLink className="h-3 w-3" />
                                    </Link>
                                    <p className="text-xs text-foreground-muted">
                                        {destination.server.ip}:{destination.server.port}
                                    </p>
                                </div>
                            </div>
                            <div>
                                <label className="text-sm text-foreground-muted">Default Destination</label>
                                <div className="mt-1">
                                    {destination.is_default ? (
                                        <Badge variant="info">Yes</Badge>
                                    ) : (
                                        <Button variant="ghost" size="sm" onClick={handleSetDefault}>
                                            Set as Default
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Resources */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Box className="h-5 w-5" />
                            Deployed Resources ({resources.length})
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {resources.length === 0 ? (
                            <div className="text-center py-8 text-foreground-muted">
                                <Box className="h-12 w-12 mx-auto mb-4 opacity-50" />
                                <p>No resources deployed to this destination</p>
                            </div>
                        ) : (
                            <div className="space-y-2">
                                {resources.map(resource => (
                                    <Link
                                        key={resource.id}
                                        href={`/${resource.type}s/${resource.uuid}`}
                                        className="block p-4 rounded-lg border border-border hover:border-primary/50 transition-colors"
                                    >
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-3">
                                                <Box className={`h-5 w-5 ${getResourceTypeColor(resource.type)}`} />
                                                <div>
                                                    <h3 className="font-medium">{resource.name}</h3>
                                                    <p className="text-sm text-foreground-muted capitalize">
                                                        {resource.type}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Badge variant={getStatusVariant(resource.status)}>{getStatusLabel(resource.status)}</Badge>
                                                <ExternalLink className="h-4 w-4 text-foreground-muted" />
                                            </div>
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Info */}
                {resources.length > 0 && (
                    <Card className="bg-warning/5 border-warning/20">
                        <CardContent className="p-4 flex items-start gap-3">
                            <AlertTriangle className="h-5 w-5 text-warning flex-shrink-0 mt-0.5" />
                            <div className="text-sm">
                                <p className="font-medium">Cannot Delete</p>
                                <p className="text-foreground-muted mt-1">
                                    This destination has {resources.length} active resource{resources.length !== 1 ? 's' : ''}.
                                    Please remove all resources before deleting this destination.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Help */}
                <Card className="bg-background-secondary">
                    <CardContent className="p-4 flex items-start gap-3">
                        <Info className="h-5 w-5 text-primary flex-shrink-0 mt-0.5" />
                        <div className="text-sm">
                            <p className="font-medium">About Destinations</p>
                            <p className="text-foreground-muted mt-1">
                                Destinations are Docker networks on your servers. Each application, database,
                                and service is deployed to a destination, allowing you to isolate different
                                environments or projects on the same server.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
