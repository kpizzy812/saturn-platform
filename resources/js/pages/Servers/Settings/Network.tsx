import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button, Input } from '@/components/ui';
import { ArrowLeft, Network as NetworkIcon, Save, Shield, Plus, Trash2 } from 'lucide-react';
import type { Server as ServerType } from '@/types';

interface Props {
    server: ServerType;
}

export default function ServerNetworkSettings({ server }: Props) {
    const [allowedIps, setAllowedIps] = useState<string[]>(['0.0.0.0/0']);
    const [firewallEnabled, setFirewallEnabled] = useState(true);
    const [customPorts, setCustomPorts] = useState('80,443,22');

    const handleSave = () => {
        router.patch(`/servers/${server.uuid}/settings/network`, {
            allowed_ips: allowedIps,
            firewall_enabled: firewallEnabled,
            custom_ports: customPorts,
        });
    };

    const addIpAddress = () => {
        setAllowedIps([...allowedIps, '']);
    };

    const removeIpAddress = (index: number) => {
        setAllowedIps(allowedIps.filter((_, i) => i !== index));
    };

    const updateIpAddress = (index: number, value: string) => {
        const newIps = [...allowedIps];
        newIps[index] = value;
        setAllowedIps(newIps);
    };

    return (
        <AppLayout
            title={`${server.name} - Network Settings`}
            breadcrumbs={[
                { label: 'Servers', href: '/servers' },
                { label: server.name, href: `/servers/${server.uuid}` },
                { label: 'Settings', href: `/servers/${server.uuid}/settings` },
                { label: 'Network' },
            ]}
        >
            {/* Header */}
            <div className="mb-6">
                <Link
                    href={`/servers/${server.uuid}/settings`}
                    className="mb-4 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="mr-2 h-4 w-4" />
                    Back to Settings
                </Link>
                <div className="flex items-center gap-4">
                    <div className="flex h-14 w-14 items-center justify-center rounded-xl bg-success/10">
                        <NetworkIcon className="h-7 w-7 text-success" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Network Settings</h1>
                        <p className="text-foreground-muted">{server.name}</p>
                    </div>
                </div>
            </div>

            {/* Connection Details */}
            <Card className="mb-6">
                <CardHeader>
                    <CardTitle>Connection Details</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid gap-4 md:grid-cols-2">
                        <Input
                            label="IP Address"
                            value={server.ip}
                            disabled
                            hint="This is read-only. Edit from server settings."
                        />
                        <Input
                            label="SSH Port"
                            value={String(server.port)}
                            disabled
                            hint="This is read-only. Edit from server settings."
                        />
                    </div>

                    <Input
                        label="SSH User"
                        value={server.user}
                        disabled
                        hint="This is read-only. Edit from server settings."
                    />
                </CardContent>
            </Card>

            {/* Firewall Settings */}
            <Card className="mb-6">
                <CardHeader>
                    <CardTitle>Firewall Settings</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                <Shield className="h-5 w-5 text-primary" />
                            </div>
                            <div>
                                <h4 className="font-medium text-foreground">Enable Firewall</h4>
                                <p className="mt-1 text-sm text-foreground-muted">
                                    Protect your server with firewall rules
                                </p>
                            </div>
                        </div>
                        <label className="relative inline-flex cursor-pointer items-center">
                            <input
                                type="checkbox"
                                checked={firewallEnabled}
                                onChange={(e) => setFirewallEnabled(e.target.checked)}
                                className="peer sr-only"
                            />
                            <div className="peer h-6 w-11 rounded-full bg-background-tertiary after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-border after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-primary peer-focus:ring-offset-2"></div>
                        </label>
                    </div>

                    <Input
                        label="Allowed Ports"
                        value={customPorts}
                        onChange={(e) => setCustomPorts(e.target.value)}
                        placeholder="80,443,22"
                        hint="Comma-separated list of ports to allow through firewall"
                    />
                </CardContent>
            </Card>

            {/* IP Allowlist */}
            <Card className="mb-6">
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <CardTitle>IP Allowlist</CardTitle>
                        <Button size="sm" variant="secondary" onClick={addIpAddress}>
                            <Plus className="mr-2 h-4 w-4" />
                            Add IP
                        </Button>
                    </div>
                </CardHeader>
                <CardContent className="space-y-3">
                    <p className="text-sm text-foreground-muted">
                        Only allow connections from these IP addresses. Use 0.0.0.0/0 to allow all.
                    </p>
                    {allowedIps.map((ip, index) => (
                        <div key={index} className="flex items-center gap-2">
                            <Input
                                value={ip}
                                onChange={(e) => updateIpAddress(index, e.target.value)}
                                placeholder="192.168.1.1 or 10.0.0.0/24"
                            />
                            {allowedIps.length > 1 && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => removeIpAddress(index)}
                                >
                                    <Trash2 className="h-4 w-4 text-danger" />
                                </Button>
                            )}
                        </div>
                    ))}
                </CardContent>
            </Card>

            {/* Save Button */}
            <div className="flex items-center gap-3">
                <Button onClick={handleSave}>
                    <Save className="mr-2 h-4 w-4" />
                    Save Network Settings
                </Button>
            </div>
        </AppLayout>
    );
}
