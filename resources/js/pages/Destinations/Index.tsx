import { Head, Link } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button, Badge } from '@/components/ui';
import { Plus, Network, Server, CheckCircle2, XCircle, Box } from 'lucide-react';

interface Destination {
    id: number;
    uuid: string;
    name: string;
    network: string;
    server: { id: number; name: string; ip: string };
    is_default: boolean;
    status: 'active' | 'inactive';
    resources_count: number;
}

interface Props {
    destinations: Destination[];
}

export default function DestinationsIndex({ destinations = [] }: Props) {
    // Group by server
    const groupedByServer = destinations.reduce((acc, dest) => {
        const serverId = dest.server.id;
        if (!acc[serverId]) {
            acc[serverId] = { server: dest.server, destinations: [] };
        }
        acc[serverId].destinations.push(dest);
        return acc;
    }, {} as Record<number, { server: { id: number; name: string; ip: string }; destinations: Destination[] }>);

    return (
        <AppLayout
            title="Destinations"
            breadcrumbs={[
                { label: 'Dashboard', href: '/new' },
                { label: 'Destinations' },
            ]}
        >
            <Head title="Destinations" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Destinations</h1>
                        <p className="text-foreground-muted mt-1">
                            Docker networks where your applications are deployed
                        </p>
                    </div>
                    <Link href="/destinations/create">
                        <Button>
                            <Plus className="h-4 w-4 mr-2" />
                            Add Destination
                        </Button>
                    </Link>
                </div>

                {/* Empty State */}
                {destinations.length === 0 ? (
                    <Card>
                        <CardContent className="p-12 text-center">
                            <Network className="h-16 w-16 mx-auto mb-4 opacity-50" />
                            <h3 className="text-lg font-semibold mb-2">No destinations configured</h3>
                            <p className="text-foreground-muted mb-6">
                                Create a destination to define where your applications will be deployed
                            </p>
                            <Link href="/destinations/create">
                                <Button>
                                    <Plus className="h-4 w-4 mr-2" />
                                    Create Destination
                                </Button>
                            </Link>
                        </CardContent>
                    </Card>
                ) : (
                    /* Grouped by Server */
                    <div className="space-y-8">
                        {Object.values(groupedByServer).map(({ server, destinations: serverDests }) => (
                            <div key={server.id}>
                                <div className="flex items-center gap-3 mb-4">
                                    <Server className="h-5 w-5 text-foreground-muted" />
                                    <h2 className="text-lg font-semibold">{server.name}</h2>
                                    <span className="text-sm text-foreground-muted">{server.ip}</span>
                                </div>
                                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                    {serverDests.map(dest => (
                                        <Link key={dest.id} href={`/destinations/${dest.uuid}`}>
                                            <Card className="h-full hover:border-primary/50 transition-colors cursor-pointer">
                                                <CardContent className="p-6">
                                                    <div className="flex items-start justify-between mb-4">
                                                        <div className="flex items-center gap-3">
                                                            <div className="h-10 w-10 rounded-lg bg-primary/10 flex items-center justify-center">
                                                                <Network className="h-5 w-5 text-primary" />
                                                            </div>
                                                            <div>
                                                                <h3 className="font-semibold">{dest.name}</h3>
                                                                <code className="text-xs text-foreground-muted">{dest.network}</code>
                                                            </div>
                                                        </div>
                                                        {dest.is_default && (
                                                            <Badge variant="info">Default</Badge>
                                                        )}
                                                    </div>
                                                    <div className="flex items-center justify-between text-sm">
                                                        <div className="flex items-center gap-2">
                                                            {dest.status === 'active' ? (
                                                                <CheckCircle2 className="h-4 w-4 text-success" />
                                                            ) : (
                                                                <XCircle className="h-4 w-4 text-danger" />
                                                            )}
                                                            <span className="capitalize">{dest.status}</span>
                                                        </div>
                                                        <div className="flex items-center gap-1 text-foreground-muted">
                                                            <Box className="h-4 w-4" />
                                                            <span>{dest.resources_count} resources</span>
                                                        </div>
                                                    </div>
                                                </CardContent>
                                            </Card>
                                        </Link>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {/* Info */}
                <Card className="bg-info/5 border-info/20">
                    <CardContent className="p-6">
                        <h3 className="font-semibold mb-2">About Destinations</h3>
                        <p className="text-sm text-foreground-muted">
                            Destinations are Docker networks on your servers. Each application, database, and service
                            is deployed to a destination, allowing you to isolate different environments or projects.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
