import { useState } from 'react';
import { Card, CardHeader, CardTitle, CardContent, Badge, Button, Input, Checkbox } from '@/components/ui';
import { Network, Globe, Lock, Plus, Trash2, Link2 } from 'lucide-react';
import type { Service } from '@/types';

interface Port {
    id: number;
    internal: number;
    external: number;
    protocol: 'tcp' | 'udp';
}

interface ConnectedService {
    id: number;
    name: string;
    type: string;
    status: 'connected' | 'disconnected';
}

interface Props {
    service: Service;
    ports?: Port[];
    connectedServices?: ConnectedService[];
}

export function NetworkingTab({ service, ports: propPorts = [], connectedServices: propConnectedServices = [] }: Props) {
    // Private networking
    const [privateNetworkEnabled, setPrivateNetworkEnabled] = useState(true);
    const [serviceMeshEnabled, setServiceMeshEnabled] = useState(false);

    // DNS
    const internalDNS = `${service.name}.internal.local`;

    // Ports
    const [ports, setPorts] = useState<Port[]>(propPorts);
    const [newPortInternal, setNewPortInternal] = useState('');
    const [newPortExternal, setNewPortExternal] = useState('');
    const [newPortProtocol, setNewPortProtocol] = useState<'tcp' | 'udp'>('tcp');

    // Connected services
    const [connectedServices] = useState<ConnectedService[]>(propConnectedServices);

    // Network policies
    const [allowIngressAll, setAllowIngressAll] = useState(false);
    const [allowEgressAll, setAllowEgressAll] = useState(true);

    const handleAddPort = () => {
        if (!newPortInternal || !newPortExternal) return;

        const newPort: Port = {
            id: Date.now(),
            internal: Number(newPortInternal),
            external: Number(newPortExternal),
            protocol: newPortProtocol,
        };

        setPorts([...ports, newPort]);
        setNewPortInternal('');
        setNewPortExternal('');
    };

    const handleRemovePort = (portId: number) => {
        setPorts(ports.filter((p) => p.id !== portId));
    };

    const handleSaveChanges = () => {
        console.log('Saving network configuration:', {
            privateNetworkEnabled,
            serviceMeshEnabled,
            ports,
            allowIngressAll,
            allowEgressAll,
        });
    };

    return (
        <div className="space-y-6">
            {/* Private Networking */}
            <Card>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10">
                                <Lock className="h-4 w-4 text-primary" />
                            </div>
                            <CardTitle>Private Networking</CardTitle>
                        </div>
                        <Badge variant={privateNetworkEnabled ? 'success' : 'default'}>
                            {privateNetworkEnabled ? 'Enabled' : 'Disabled'}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent>
                    <div className="space-y-4">
                        <Checkbox
                            label="Enable private networking for secure inter-service communication"
                            checked={privateNetworkEnabled}
                            onChange={(e) => setPrivateNetworkEnabled(e.target.checked)}
                        />

                        {privateNetworkEnabled && (
                            <div className="space-y-4 rounded-lg border border-border bg-background-tertiary p-4">
                                <div>
                                    <label className="text-sm font-medium text-foreground">
                                        Internal DNS Name
                                    </label>
                                    <div className="mt-2 flex items-center gap-2 rounded-lg border border-border bg-background-secondary p-3">
                                        <Globe className="h-4 w-4 text-foreground-muted" />
                                        <code className="text-sm text-foreground">{internalDNS}</code>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="ml-auto"
                                            onClick={() => navigator.clipboard.writeText(internalDNS)}
                                        >
                                            Copy
                                        </Button>
                                    </div>
                                    <p className="mt-2 text-sm text-foreground-muted">
                                        Use this DNS name to connect to this service from other services in the same network.
                                    </p>
                                </div>
                            </div>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* Port Configuration */}
            <Card>
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-info/10">
                            <Network className="h-4 w-4 text-info" />
                        </div>
                        <CardTitle>Port Configuration</CardTitle>
                    </div>
                </CardHeader>
                <CardContent>
                    <div className="space-y-4">
                        {/* Existing Ports */}
                        <div className="space-y-2">
                            {ports.map((port) => (
                                <div
                                    key={port.id}
                                    className="flex items-center justify-between rounded-lg border border-border bg-background-tertiary p-3"
                                >
                                    <div className="flex items-center gap-4">
                                        <div>
                                            <div className="text-sm font-medium text-foreground">
                                                Port Mapping
                                            </div>
                                            <div className="mt-1 flex items-center gap-2 text-sm text-foreground-muted">
                                                <span>Internal: <code className="text-foreground">{port.internal}</code></span>
                                                <span>→</span>
                                                <span>External: <code className="text-foreground">{port.external}</code></span>
                                                <Badge variant="default" className="uppercase">
                                                    {port.protocol}
                                                </Badge>
                                            </div>
                                        </div>
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => handleRemovePort(port.id)}
                                    >
                                        <Trash2 className="h-4 w-4 text-danger" />
                                    </Button>
                                </div>
                            ))}
                        </div>

                        {/* Add New Port */}
                        <div className="rounded-lg border border-border bg-background-tertiary p-4">
                            <h4 className="mb-3 text-sm font-medium text-foreground">Add Port Mapping</h4>
                            <div className="flex items-end gap-2">
                                <Input
                                    label="Internal Port"
                                    type="number"
                                    placeholder="3000"
                                    value={newPortInternal}
                                    onChange={(e) => setNewPortInternal(e.target.value)}
                                />
                                <Input
                                    label="External Port"
                                    type="number"
                                    placeholder="80"
                                    value={newPortExternal}
                                    onChange={(e) => setNewPortExternal(e.target.value)}
                                />
                                <div className="space-y-1.5">
                                    <label className="text-sm font-medium text-foreground">Protocol</label>
                                    <select
                                        value={newPortProtocol}
                                        onChange={(e) => setNewPortProtocol(e.target.value as 'tcp' | 'udp')}
                                        className="flex h-10 w-full rounded-md border border-border bg-background-secondary px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                                    >
                                        <option value="tcp">TCP</option>
                                        <option value="udp">UDP</option>
                                    </select>
                                </div>
                                <Button onClick={handleAddPort}>
                                    <Plus className="mr-1 h-4 w-4" />
                                    Add
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Service Mesh */}
            <Card>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-warning/10">
                                <Link2 className="h-4 w-4 text-warning" />
                            </div>
                            <CardTitle>Service Mesh</CardTitle>
                        </div>
                        <Badge variant={serviceMeshEnabled ? 'success' : 'default'}>
                            {serviceMeshEnabled ? 'Enabled' : 'Disabled'}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent>
                    <div className="space-y-4">
                        <Checkbox
                            label="Enable service mesh for advanced traffic management and observability"
                            checked={serviceMeshEnabled}
                            onChange={(e) => setServiceMeshEnabled(e.target.checked)}
                        />

                        {serviceMeshEnabled && (
                            <div className="rounded-lg border border-border bg-background-tertiary p-4">
                                <h4 className="text-sm font-medium text-foreground">Features Enabled:</h4>
                                <ul className="mt-2 space-y-1 text-sm text-foreground-muted">
                                    <li className="flex items-center gap-2">
                                        <div className="h-1.5 w-1.5 rounded-full bg-primary" />
                                        Mutual TLS (mTLS) encryption
                                    </li>
                                    <li className="flex items-center gap-2">
                                        <div className="h-1.5 w-1.5 rounded-full bg-primary" />
                                        Traffic routing and load balancing
                                    </li>
                                    <li className="flex items-center gap-2">
                                        <div className="h-1.5 w-1.5 rounded-full bg-primary" />
                                        Circuit breaking and retry policies
                                    </li>
                                    <li className="flex items-center gap-2">
                                        <div className="h-1.5 w-1.5 rounded-full bg-primary" />
                                        Distributed tracing
                                    </li>
                                </ul>
                            </div>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* Connected Services */}
            <Card>
                <CardHeader>
                    <CardTitle>Connected Services</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="space-y-2">
                        {connectedServices.map((connectedService) => (
                            <div
                                key={connectedService.id}
                                className="flex items-center justify-between rounded-lg border border-border bg-background-tertiary p-3"
                            >
                                <div className="flex items-center gap-3">
                                    <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${
                                        connectedService.status === 'connected'
                                            ? 'bg-primary/10'
                                            : 'bg-foreground-muted/10'
                                    }`}>
                                        <Network className={`h-5 w-5 ${
                                            connectedService.status === 'connected'
                                                ? 'text-primary'
                                                : 'text-foreground-muted'
                                        }`} />
                                    </div>
                                    <div>
                                        <div className="text-sm font-medium text-foreground">
                                            {connectedService.name}
                                        </div>
                                        <div className="text-xs text-foreground-muted">
                                            {connectedService.type}
                                        </div>
                                    </div>
                                </div>
                                <Badge
                                    variant={
                                        connectedService.status === 'connected' ? 'success' : 'default'
                                    }
                                    className="capitalize"
                                >
                                    {connectedService.status}
                                </Badge>
                            </div>
                        ))}

                        <Button variant="secondary" className="w-full">
                            <Plus className="mr-2 h-4 w-4" />
                            Connect Service
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* Network Policies */}
            <Card>
                <CardHeader>
                    <CardTitle>Network Policies</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="space-y-4">
                        <div className="rounded-lg border border-border bg-background-tertiary p-4">
                            <Checkbox
                                label="Allow all ingress traffic"
                                checked={allowIngressAll}
                                onChange={(e) => setAllowIngressAll(e.target.checked)}
                            />
                            <p className="mt-2 text-sm text-foreground-muted">
                                When disabled, only explicitly allowed sources can send traffic to this service.
                            </p>
                        </div>

                        <div className="rounded-lg border border-border bg-background-tertiary p-4">
                            <Checkbox
                                label="Allow all egress traffic"
                                checked={allowEgressAll}
                                onChange={(e) => setAllowEgressAll(e.target.checked)}
                            />
                            <p className="mt-2 text-sm text-foreground-muted">
                                When disabled, this service can only send traffic to explicitly allowed destinations.
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Save Button */}
            <div className="flex justify-end">
                <Button onClick={handleSaveChanges} size="lg">
                    Save Changes
                </Button>
            </div>
        </div>
    );
}
