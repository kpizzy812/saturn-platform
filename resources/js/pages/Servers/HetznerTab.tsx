import * as React from 'react';
import { router } from '@inertiajs/react';
import { Card, CardContent, Button, Input, Select } from '@/components/ui';
import { Server, Key, MapPin, Cpu } from 'lucide-react';
import type { RouterPayload } from '@/types/inertia';
import type { CloudProviderToken, HetznerLocation, HetznerServerType, PrivateKey } from '@/types/models';

interface Props {
    cloudTokens: CloudProviderToken[];
    privateKeys: PrivateKey[];
}

type KeyMode = 'existing' | 'new';

export default function HetznerTab({ cloudTokens, privateKeys }: Props) {
    const [step, setStep] = React.useState<1 | 2 | 3>(1);
    const [tokenUuid, setTokenUuid] = React.useState(cloudTokens[0]?.uuid ?? '');
    const [locations, setLocations] = React.useState<HetznerLocation[]>([]);
    const [serverTypes, setServerTypes] = React.useState<HetznerServerType[]>([]);
    const [loading, setLoading] = React.useState(false);
    const [location, setLocation] = React.useState('');
    const [serverType, setServerType] = React.useState('');
    const [serverName, setServerName] = React.useState('');
    const [keyMode, setKeyMode] = React.useState<KeyMode>(privateKeys.length > 0 ? 'existing' : 'new');
    const [selectedKeyId, setSelectedKeyId] = React.useState<number | null>(privateKeys[0]?.id ?? null);
    const [privateKey, setPrivateKey] = React.useState('');
    const [submitting, setSubmitting] = React.useState(false);

    const loadOptions = React.useCallback(async (uuid: string) => {
        if (!uuid) return;
        setLoading(true);
        try {
            const resp = await fetch(`/servers/hetzner/options?token_uuid=${encodeURIComponent(uuid)}`, {
                headers: { Accept: 'application/json' },
            });
            if (resp.ok) {
                const data = await resp.json();
                setLocations(data.locations ?? []);
                setServerTypes(data.server_types ?? []);
                setLocation(data.locations?.[0]?.name ?? '');
                setServerType(data.server_types?.[0]?.name ?? '');
            }
        } finally {
            setLoading(false);
        }
    }, []);

    React.useEffect(() => {
        if (tokenUuid) loadOptions(tokenUuid);
    }, [tokenUuid, loadOptions]);

    const handleSubmit = () => {
        setSubmitting(true);
        const payload: Record<string, unknown> = {
            cloud_provider_token_uuid: tokenUuid,
            location,
            server_type: serverType,
            name: serverName,
        };
        if (keyMode === 'existing' && selectedKeyId) {
            payload.private_key_id = selectedKeyId;
        } else {
            payload.private_key = privateKey;
        }
        router.post('/servers/hetzner', payload as unknown as RouterPayload, {
            onFinish: () => setSubmitting(false),
        });
    };

    if (cloudTokens.length === 0) {
        return (
            <Card>
                <CardContent className="py-12 text-center">
                    <Server className="mx-auto mb-4 h-12 w-12 text-foreground-muted" />
                    <p className="text-foreground-muted">No Hetzner tokens configured.</p>
                    <p className="mt-1 text-sm text-foreground-subtle">
                        Add a Hetzner API token in{' '}
                        <a href="/settings/cloud-providers" className="text-primary underline">
                            Settings → Cloud Providers
                        </a>
                        .
                    </p>
                </CardContent>
            </Card>
        );
    }

    return (
        <div className="space-y-4">
            {/* Step 1: Token */}
            {step === 1 && (
                <Card>
                    <CardContent className="p-6">
                        <div className="mb-6 flex items-center gap-3">
                            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-orange-500 to-red-500 text-white shadow-lg">
                                <Server className="h-5 w-5" />
                            </div>
                            <div>
                                <h3 className="font-medium text-foreground">Cloud Provider</h3>
                                <p className="text-sm text-foreground-muted">Select your Hetzner token</p>
                            </div>
                        </div>

                        <div className="space-y-4">
                            <div className="space-y-1">
                                <label className="block text-sm font-medium text-foreground">
                                    Hetzner Token
                                </label>
                                <select
                                    value={tokenUuid}
                                    onChange={(e) => setTokenUuid(e.target.value)}
                                    className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                                >
                                    {cloudTokens.map((t) => (
                                        <option key={t.uuid} value={t.uuid}>
                                            {t.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div className="mt-6">
                            <Button
                                onClick={() => setStep(2)}
                                disabled={!tokenUuid || loading}
                                className="w-full"
                            >
                                {loading ? 'Loading options…' : 'Continue'}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Step 2: Location + Server Type + Name */}
            {step === 2 && (
                <Card>
                    <CardContent className="p-6">
                        <div className="mb-6 flex items-center gap-3">
                            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 text-white shadow-lg">
                                <MapPin className="h-5 w-5" />
                            </div>
                            <div>
                                <h3 className="font-medium text-foreground">Server Configuration</h3>
                                <p className="text-sm text-foreground-muted">Choose location and server type</p>
                            </div>
                        </div>

                        <div className="space-y-4">
                            <Input
                                label="Server Name"
                                placeholder="my-hetzner-server"
                                value={serverName}
                                onChange={(e) => setServerName(e.target.value)}
                                hint="A friendly name for your server"
                            />

                            {/* Location */}
                            <div className="space-y-1">
                                <label className="block text-sm font-medium text-foreground">
                                    Location
                                </label>
                                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                    {locations.map((loc) => (
                                        <button
                                            key={loc.id}
                                            type="button"
                                            onClick={() => setLocation(loc.name)}
                                            className={`rounded-lg border p-3 text-left text-sm transition-colors ${
                                                location === loc.name
                                                    ? 'border-primary bg-primary/10 text-foreground'
                                                    : 'border-border bg-background text-foreground-muted hover:border-primary/50'
                                            }`}
                                        >
                                            <p className="font-medium">{loc.name}</p>
                                            <p className="text-xs">{loc.city}, {loc.country}</p>
                                        </button>
                                    ))}
                                </div>
                            </div>

                            {/* Server Type */}
                            <div className="space-y-1">
                                <label className="block text-sm font-medium text-foreground">
                                    Server Type
                                </label>
                                <div className="grid grid-cols-2 gap-2">
                                    {serverTypes.map((st) => (
                                        <button
                                            key={st.id}
                                            type="button"
                                            onClick={() => setServerType(st.name)}
                                            className={`rounded-lg border p-3 text-left text-sm transition-colors ${
                                                serverType === st.name
                                                    ? 'border-primary bg-primary/10 text-foreground'
                                                    : 'border-border bg-background text-foreground-muted hover:border-primary/50'
                                            }`}
                                        >
                                            <div className="flex items-center gap-1">
                                                <Cpu className="h-3.5 w-3.5" />
                                                <p className="font-medium">{st.name}</p>
                                            </div>
                                            <p className="mt-1 text-xs">
                                                {st.cores} vCPU · {st.memory} GB RAM · {st.disk} GB
                                            </p>
                                        </button>
                                    ))}
                                </div>
                            </div>
                        </div>

                        <div className="mt-6 flex gap-3">
                            <Button variant="secondary" onClick={() => setStep(1)}>
                                Back
                            </Button>
                            <Button
                                onClick={() => setStep(3)}
                                disabled={!serverName || !location || !serverType}
                                className="flex-1"
                            >
                                Continue
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Step 3: SSH Key + Create */}
            {step === 3 && (
                <Card>
                    <CardContent className="p-6">
                        <div className="mb-6 flex items-center gap-3">
                            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-green-500 to-emerald-600 text-white shadow-lg">
                                <Key className="h-5 w-5" />
                            </div>
                            <div>
                                <h3 className="font-medium text-foreground">SSH Access</h3>
                                <p className="text-sm text-foreground-muted">Configure SSH key for server access</p>
                            </div>
                        </div>

                        <div className="space-y-4">
                            {/* SSH Key Mode */}
                            <div className="space-y-3">
                                <label className="block text-sm font-medium text-foreground">
                                    SSH Key
                                </label>
                                {privateKeys.length > 0 && (
                                    <div className="flex gap-2">
                                        <Button
                                            type="button"
                                            variant={keyMode === 'existing' ? 'default' : 'secondary'}
                                            size="sm"
                                            onClick={() => setKeyMode('existing')}
                                        >
                                            <Key className="mr-2 h-4 w-4" />
                                            Use Existing Key
                                        </Button>
                                        <Button
                                            type="button"
                                            variant={keyMode === 'new' ? 'default' : 'secondary'}
                                            size="sm"
                                            onClick={() => setKeyMode('new')}
                                        >
                                            Add New Key
                                        </Button>
                                    </div>
                                )}

                                {keyMode === 'existing' && privateKeys.length > 0 ? (
                                    <Select
                                        value={selectedKeyId?.toString() ?? ''}
                                        onChange={(e) => setSelectedKeyId(parseInt(e.target.value))}
                                    >
                                        {privateKeys.map((key) => (
                                            <option key={key.id} value={key.id}>
                                                {key.name}
                                            </option>
                                        ))}
                                    </Select>
                                ) : (
                                    <textarea
                                        placeholder={`-----BEGIN OPENSSH PRIVATE KEY-----\n...\n-----END OPENSSH PRIVATE KEY-----`}
                                        value={privateKey}
                                        onChange={(e) => setPrivateKey(e.target.value)}
                                        rows={8}
                                        className="w-full rounded-md border border-border bg-background px-3 py-2 font-mono text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                                    />
                                )}
                            </div>

                            {/* Review summary */}
                            <div className="rounded-lg border border-border bg-background-secondary p-4 text-sm">
                                <p className="mb-2 font-medium text-foreground">Review</p>
                                <div className="space-y-1 text-foreground-muted">
                                    <div className="flex justify-between">
                                        <span>Name:</span>
                                        <span className="font-medium text-foreground">{serverName}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span>Location:</span>
                                        <span className="font-medium text-foreground">{location}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span>Server type:</span>
                                        <span className="font-medium text-foreground">{serverType}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="mt-6 flex gap-3">
                            <Button variant="secondary" onClick={() => setStep(2)}>
                                Back
                            </Button>
                            <Button
                                onClick={handleSubmit}
                                disabled={
                                    submitting ||
                                    (keyMode === 'existing' ? !selectedKeyId : !privateKey)
                                }
                                className="flex-1"
                            >
                                {submitting ? 'Creating…' : 'Create Server'}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
