import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button, Input } from '@/components/ui';
import { ArrowLeft, Plus, HardDrive } from 'lucide-react';
import type { Server as ServerType } from '@/types';

interface Props {
    server: ServerType;
}

export default function CreateDestination({ server }: Props) {
    const [name, setName] = useState('');
    const [network, setNetwork] = useState('saturn');
    const [isDefault, setIsDefault] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        router.post(`/servers/${server.uuid}/destinations`, {
            name,
            network,
            is_default: isDefault,
        });
    };

    const canSubmit = name.trim() !== '' && network.trim() !== '';

    return (
        <AppLayout
            title={`${server.name} - Create Destination`}
            breadcrumbs={[
                { label: 'Servers', href: '/servers' },
                { label: server.name, href: `/servers/${server.uuid}` },
                { label: 'Destinations', href: `/servers/${server.uuid}/destinations` },
                { label: 'Create' },
            ]}
        >
            {/* Header */}
            <div className="mb-6">
                <Link
                    href={`/servers/${server.uuid}/destinations`}
                    className="mb-4 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="mr-2 h-4 w-4" />
                    Back to Destinations
                </Link>
                <div className="flex items-center gap-4">
                    <div className="flex h-14 w-14 items-center justify-center rounded-xl bg-primary/10">
                        <HardDrive className="h-7 w-7 text-primary" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Create Destination</h1>
                        <p className="text-foreground-muted">Add a new deployment destination to {server.name}</p>
                    </div>
                </div>
            </div>

            {/* Form */}
            <form onSubmit={handleSubmit}>
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle>Destination Details</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <Input
                            label="Name"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            placeholder="production-apps"
                            hint="A descriptive name for this destination"
                            required
                        />

                        <Input
                            label="Docker Network"
                            value={network}
                            onChange={(e) => setNetwork(e.target.value)}
                            placeholder="saturn"
                            hint="Docker network name for application containers"
                            required
                        />

                        <div className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-4">
                            <div>
                                <h4 className="font-medium text-foreground">Set as Default</h4>
                                <p className="mt-1 text-sm text-foreground-muted">
                                    Use this destination by default for new deployments
                                </p>
                            </div>
                            <label className="relative inline-flex cursor-pointer items-center">
                                <input
                                    type="checkbox"
                                    checked={isDefault}
                                    onChange={(e) => setIsDefault(e.target.checked)}
                                    className="peer sr-only"
                                />
                                <div className="peer h-6 w-11 rounded-full bg-background-tertiary after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-border after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-primary peer-focus:ring-offset-2"></div>
                            </label>
                        </div>
                    </CardContent>
                </Card>

                {/* Info Card */}
                <Card className="mb-6">
                    <CardContent className="p-4">
                        <div className="space-y-2 text-sm">
                            <p className="font-medium text-foreground">What is a destination?</p>
                            <p className="text-foreground-muted">
                                A destination defines a Docker network on this server where your applications will run.
                                Each destination isolates applications in their own network for better security and organization.
                            </p>
                            <ul className="ml-4 mt-2 list-disc space-y-1 text-foreground-muted">
                                <li>Applications in the same destination can communicate easily</li>
                                <li>Each destination has its own network configuration</li>
                                <li>You can have multiple destinations per server</li>
                            </ul>
                        </div>
                    </CardContent>
                </Card>

                {/* Actions */}
                <div className="flex items-center gap-3">
                    <Button type="submit" disabled={!canSubmit}>
                        <Plus className="mr-2 h-4 w-4" />
                        Create Destination
                    </Button>
                    <Link href={`/servers/${server.uuid}/destinations`}>
                        <Button type="button" variant="ghost">
                            Cancel
                        </Button>
                    </Link>
                </div>
            </form>
        </AppLayout>
    );
}
