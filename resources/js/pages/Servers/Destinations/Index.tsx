import { Link } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Badge } from '@/components/ui';
import { ArrowLeft, Plus, HardDrive, Network, CheckCircle } from 'lucide-react';
import type { Server as ServerType } from '@/types';
import { StaggerList, StaggerItem, FadeIn } from '@/components/animation';

interface Props {
    server: ServerType;
    destinations?: Destination[];
}

interface Destination {
    id: number;
    uuid: string;
    name: string;
    network: string;
    server_id: number;
    is_default: boolean;
    created_at: string;
}

export default function ServerDestinationsIndex({ server, destinations = [] }: Props) {
    return (
        <AppLayout
            title={`${server.name} - Destinations`}
            breadcrumbs={[
                { label: 'Servers', href: '/servers' },
                { label: server.name, href: `/servers/${server.uuid}` },
                { label: 'Destinations' },
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
                            <HardDrive className="h-7 w-7 text-primary" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold text-foreground">Destinations</h1>
                            <p className="text-foreground-muted">Manage deployment destinations for {server.name}</p>
                        </div>
                    </div>
                    <Link href={`/servers/${server.uuid}/destinations/create`}>
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            New Destination
                        </Button>
                    </Link>
                </div>
            </div>

            {/* Info Card */}
            <Card className="mb-6">
                <CardContent className="p-4">
                    <div className="flex items-start gap-3">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-info/10">
                            <Network className="h-5 w-5 text-info" />
                        </div>
                        <div>
                            <h4 className="font-medium text-foreground">About Destinations</h4>
                            <p className="mt-1 text-sm text-foreground-muted">
                                Destinations define where your applications will be deployed on this server. Each destination
                                represents a Docker network that can host multiple applications.
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Destinations List */}
            {destinations.length > 0 ? (
                <StaggerList className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {destinations.map((destination, i) => (
                        <StaggerItem key={destination.uuid} index={i}>
                            <Link href={`/servers/${server.uuid}/destinations/${destination.uuid}`}>
                                <Card className="cursor-pointer transition-all hover:border-primary/50">
                                    <CardContent className="p-5">
                                        <div className="mb-3 flex items-start justify-between">
                                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                                <HardDrive className="h-5 w-5 text-primary" />
                                            </div>
                                            {destination.is_default && (
                                                <Badge variant="success" size="sm">Default</Badge>
                                            )}
                                        </div>
                                        <h3 className="font-semibold text-foreground">{destination.name}</h3>
                                        <p className="mt-1 text-sm text-foreground-muted">Network: {destination.network}</p>
                                        <div className="mt-3 flex items-center gap-2">
                                            <CheckCircle className="h-4 w-4 text-success" />
                                            <span className="text-xs text-foreground-muted">Active</span>
                                        </div>
                                    </CardContent>
                                </Card>
                            </Link>
                        </StaggerItem>
                    ))}
                </StaggerList>
            ) : (
                <FadeIn>
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-16">
                            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-background-secondary">
                                <HardDrive className="h-8 w-8 animate-pulse-soft text-foreground-subtle" />
                            </div>
                            <h3 className="mt-4 font-medium text-foreground">No destinations yet</h3>
                            <p className="mt-1 text-sm text-foreground-muted">
                                Create your first destination to start deploying applications
                            </p>
                            <Link href={`/servers/${server.uuid}/destinations/create`} className="mt-4">
                                <Button>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Create Destination
                                </Button>
                            </Link>
                        </CardContent>
                    </Card>
                </FadeIn>
            )}
        </AppLayout>
    );
}
