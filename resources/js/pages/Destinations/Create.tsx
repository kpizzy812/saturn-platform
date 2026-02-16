import { Head, Link, useForm } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button, Input, Select, Checkbox } from '@/components/ui';
import { ArrowLeft, Save, Network } from 'lucide-react';

interface Props {
    servers: { id: number; uuid: string; name: string; ip: string }[];
}

export default function DestinationsCreate({ servers = [] }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        server_uuid: servers[0]?.uuid || '',
        network: 'saturn',
        is_default: false,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/destinations');
    };

    return (
        <AppLayout
            title="Create Destination"
            breadcrumbs={[
                { label: 'Dashboard', href: '/new' },
                { label: 'Destinations', href: '/destinations' },
                { label: 'Create' },
            ]}
        >
            <Head title="Create Destination" />

            <div className="max-w-2xl mx-auto space-y-6">
                <div className="flex items-center gap-4">
                    <Link href="/destinations">
                        <Button variant="ghost" size="sm">
                            <ArrowLeft className="h-4 w-4 mr-2" />
                            Back
                        </Button>
                    </Link>
                    <h1 className="text-2xl font-bold">Create Destination</h1>
                </div>

                <form onSubmit={handleSubmit}>
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Network className="h-5 w-5" />
                                Destination Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {/* Server Selection */}
                            <div>
                                <label className="text-sm font-medium">Server</label>
                                <Select
                                    value={data.server_uuid}
                                    onChange={(e) => setData('server_uuid', e.target.value)}
                                >
                                    {servers.length === 0 ? (
                                        <option value="">No servers available</option>
                                    ) : (
                                        servers.map(server => (
                                            <option key={server.uuid} value={server.uuid}>
                                                {server.name} ({server.ip})
                                            </option>
                                        ))
                                    )}
                                </Select>
                                {errors.server_uuid && <p className="text-sm text-danger mt-1">{errors.server_uuid}</p>}
                                <p className="text-xs text-foreground-muted mt-1">
                                    The server where this Docker network will be created
                                </p>
                            </div>

                            {/* Name */}
                            <div>
                                <label className="text-sm font-medium">Name</label>
                                <Input
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="Production, Staging, etc."
                                />
                                {errors.name && <p className="text-sm text-danger mt-1">{errors.name}</p>}
                            </div>

                            {/* Network Name */}
                            <div>
                                <label className="text-sm font-medium">Docker Network Name</label>
                                <Input
                                    value={data.network}
                                    onChange={(e) => setData('network', e.target.value.toLowerCase().replace(/[^a-z0-9_-]/g, '-'))}
                                    placeholder="saturn"
                                    className="font-mono"
                                />
                                {errors.network && <p className="text-sm text-danger mt-1">{errors.network}</p>}
                                <p className="text-xs text-foreground-muted mt-1">
                                    This will be the name of the Docker network on the server
                                </p>
                            </div>

                            {/* Default */}
                            <div className="flex items-center gap-3">
                                <Checkbox
                                    id="is_default"
                                    checked={data.is_default}
                                    onChange={(e) => setData('is_default', e.target.checked)}
                                />
                                <label htmlFor="is_default" className="text-sm">
                                    Set as default destination for this server
                                </label>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Info Card */}
                    <Card className="mt-6 bg-background-secondary">
                        <CardContent className="p-6">
                            <h3 className="font-semibold mb-2">What happens next?</h3>
                            <ul className="text-sm text-foreground-muted space-y-2">
                                <li>• A new Docker network will be created on the selected server</li>
                                <li>• You can deploy applications, databases, and services to this destination</li>
                                <li>• Resources within the same destination can communicate via the network</li>
                            </ul>
                        </CardContent>
                    </Card>

                    <div className="flex justify-end gap-3 mt-6">
                        <Link href="/destinations">
                            <Button variant="ghost">Cancel</Button>
                        </Link>
                        <Button type="submit" disabled={processing || servers.length === 0}>
                            <Save className="h-4 w-4 mr-2" />
                            Create Destination
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
