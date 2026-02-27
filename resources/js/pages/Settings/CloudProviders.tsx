import * as React from 'react';
import { SettingsLayout } from './Index';
import {
    Card,
    CardHeader,
    CardTitle,
    CardDescription,
    CardContent,
    Input,
    Button,
    Badge,
    useToast,
} from '@/components/ui';
import { router } from '@inertiajs/react';
import { Cloud, Trash2, CheckCircle, XCircle, Eye, EyeOff, Plus } from 'lucide-react';
import type { CloudProviderToken } from '@/types/models';
import { useCloudTokens } from '@/hooks/useCloudTokens';

interface Props {
    tokens: CloudProviderToken[];
}

export default function CloudProvidersSettings({ tokens }: Props) {
    const { validateToken } = useCloudTokens();
    const { toast } = useToast();

    const [showForm, setShowForm] = React.useState(false);
    const [name, setName] = React.useState('');
    const [provider, setProvider] = React.useState<'hetzner' | 'digitalocean'>('hetzner');
    const [token, setToken] = React.useState('');
    const [showToken, setShowToken] = React.useState(false);
    const [validating, setValidating] = React.useState<string | null>(null);
    const [validationResults, setValidationResults] = React.useState<Record<string, boolean>>({});
    const [deleting, setDeleting] = React.useState<string | null>(null);

    const handleCreate = (e: React.FormEvent) => {
        e.preventDefault();
        router.post(
            '/settings/cloud-tokens',
            { name, provider, token },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setShowForm(false);
                    setName('');
                    setToken('');
                    toast({ title: 'Token added', variant: 'success' });
                },
                onError: (errors) => {
                    const msg = Object.values(errors)[0] as string;
                    toast({ title: msg ?? 'Failed to add token', variant: 'error' });
                },
            },
        );
    };

    const handleValidate = async (uuid: string) => {
        setValidating(uuid);
        try {
            const result = await validateToken(uuid);
            setValidationResults((prev) => ({ ...prev, [uuid]: result.valid }));
            toast({
                title: result.valid ? 'Token is valid' : result.message,
                variant: result.valid ? 'success' : 'error',
            });
        } catch {
            toast({ title: 'Validation failed', variant: 'error' });
        } finally {
            setValidating(null);
        }
    };

    const handleDelete = (uuid: string) => {
        if (!confirm('Delete this cloud provider token?')) return;
        setDeleting(uuid);
        router.delete(`/settings/cloud-tokens/${uuid}`, {
            preserveScroll: true,
            onSuccess: () => toast({ title: 'Token deleted', variant: 'success' }),
            onError: () => toast({ title: 'Failed to delete token', variant: 'error' }),
            onFinish: () => setDeleting(null),
        });
    };

    return (
        <SettingsLayout activeSection="cloud-providers">
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-lg font-semibold text-foreground">Cloud Providers</h2>
                        <p className="text-sm text-foreground-muted">
                            Manage API tokens for cloud providers like Hetzner
                        </p>
                    </div>
                    <Button onClick={() => setShowForm((v) => !v)} size="sm">
                        <Plus className="mr-2 h-4 w-4" />
                        Add Token
                    </Button>
                </div>

                {/* Add token form */}
                {showForm && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Add Cloud Provider Token</CardTitle>
                            <CardDescription>
                                Enter your API token for the cloud provider
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleCreate} className="space-y-4">
                                <Input
                                    label="Name"
                                    placeholder="My Hetzner Token"
                                    value={name}
                                    onChange={(e) => setName(e.target.value)}
                                    required
                                />

                                <div className="space-y-1">
                                    <label className="block text-sm font-medium text-foreground">
                                        Provider
                                    </label>
                                    <select
                                        value={provider}
                                        onChange={(e) =>
                                            setProvider(e.target.value as 'hetzner' | 'digitalocean')
                                        }
                                        className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                                    >
                                        <option value="hetzner">Hetzner</option>
                                        <option value="digitalocean">DigitalOcean</option>
                                    </select>
                                </div>

                                <div className="space-y-1">
                                    <label className="block text-sm font-medium text-foreground">
                                        API Token
                                    </label>
                                    <div className="relative">
                                        <input
                                            type={showToken ? 'text' : 'password'}
                                            value={token}
                                            onChange={(e) => setToken(e.target.value)}
                                            placeholder="Enter your API token"
                                            required
                                            className="w-full rounded-md border border-border bg-background px-3 py-2 pr-10 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowToken((v) => !v)}
                                            className="absolute right-3 top-1/2 -translate-y-1/2 text-foreground-muted hover:text-foreground"
                                        >
                                            {showToken ? (
                                                <EyeOff className="h-4 w-4" />
                                            ) : (
                                                <Eye className="h-4 w-4" />
                                            )}
                                        </button>
                                    </div>
                                </div>

                                <div className="flex gap-3">
                                    <Button type="submit" disabled={!name || !token}>
                                        Add Token
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={() => setShowForm(false)}
                                    >
                                        Cancel
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {/* Tokens list */}
                {tokens.length === 0 && !showForm ? (
                    <Card>
                        <CardContent className="py-12 text-center">
                            <Cloud className="mx-auto mb-4 h-12 w-12 text-foreground-muted" />
                            <p className="text-foreground-muted">No cloud provider tokens yet.</p>
                            <p className="mt-1 text-sm text-foreground-subtle">
                                Add a token to enable cloud server provisioning.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-3">
                        {tokens.map((t) => (
                            <Card key={t.uuid}>
                                <CardContent className="flex items-center justify-between p-4">
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-background-secondary">
                                            <Cloud className="h-5 w-5 text-foreground-muted" />
                                        </div>
                                        <div>
                                            <p className="font-medium text-foreground">{t.name}</p>
                                            <div className="mt-1 flex items-center gap-2">
                                                <Badge
                                                    variant={
                                                        t.provider === 'hetzner'
                                                            ? 'primary'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {t.provider === 'hetzner'
                                                        ? 'Hetzner'
                                                        : 'DigitalOcean'}
                                                </Badge>
                                                <span className="text-xs text-foreground-muted">
                                                    {t.servers_count ?? 0} server
                                                    {(t.servers_count ?? 0) !== 1 ? 's' : ''}
                                                </span>
                                                {t.uuid in validationResults && (
                                                    <span>
                                                        {validationResults[t.uuid] ? (
                                                            <CheckCircle className="h-4 w-4 text-green-500" />
                                                        ) : (
                                                            <XCircle className="h-4 w-4 text-red-500" />
                                                        )}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Button
                                            variant="secondary"
                                            size="sm"
                                            onClick={() => handleValidate(t.uuid)}
                                            disabled={validating === t.uuid}
                                        >
                                            {validating === t.uuid ? 'Validating…' : 'Validate'}
                                        </Button>
                                        <Button
                                            variant="danger"
                                            size="sm"
                                            onClick={() => handleDelete(t.uuid)}
                                            disabled={deleting === t.uuid}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </SettingsLayout>
    );
}
