import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { router } from '@inertiajs/react';
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
import { Cloud, Trash2, CheckCircle, XCircle, Eye, EyeOff, Plus } from 'lucide-react';

interface Team {
    id: number;
    name: string;
}

interface CloudToken {
    uuid: string;
    name: string;
    provider: string;
    team_id: number;
    servers_count?: number;
    team?: Team;
}

interface Props {
    tokens: CloudToken[];
    teams: Team[];
}

export default function AdminCloudProvidersIndex({ tokens, teams }: Props) {
    const { toast } = useToast();
    const getCsrf = () =>
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    // Form state
    const [showForm, setShowForm] = React.useState(false);
    const [teamId, setTeamId] = React.useState<string>('');
    const [name, setName] = React.useState('');
    const [provider, setProvider] = React.useState<'hetzner' | 'digitalocean'>('hetzner');
    const [token, setToken] = React.useState('');
    const [showToken, setShowToken] = React.useState(false);

    // Filter state
    const [filterTeamId, setFilterTeamId] = React.useState<string>('');

    // Per-token action state
    const [validating, setValidating] = React.useState<string | null>(null);
    const [validationResults, setValidationResults] = React.useState<Record<string, boolean>>({});
    const [deleting, setDeleting] = React.useState<string | null>(null);

    const filteredTokens = filterTeamId
        ? tokens.filter((t) => String(t.team_id) === filterTeamId)
        : tokens;

    const handleCreate = (e: React.FormEvent) => {
        e.preventDefault();
        router.post(
            '/admin/cloud-providers',
            { team_id: parseInt(teamId), name, provider, token },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setShowForm(false);
                    setName('');
                    setToken('');
                    setTeamId('');
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
            const resp = await fetch(`/admin/cloud-providers/${uuid}/validate`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrf(),
                },
            });
            const result: { valid: boolean; message: string } = await resp.json();
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
        if (!confirm('Delete this cloud provider token? This action cannot be undone.')) return;
        setDeleting(uuid);
        router.delete(`/admin/cloud-providers/${uuid}`, {
            preserveScroll: true,
            onSuccess: () => toast({ title: 'Token deleted', variant: 'success' }),
            onError: (errors) => {
                const msg = Object.values(errors)[0] as string;
                toast({ title: msg ?? 'Failed to delete token', variant: 'error' });
            },
            onFinish: () => setDeleting(null),
        });
    };

    return (
        <AdminLayout
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Cloud Providers' },
            ]}
        >
            <div className="mx-auto max-w-5xl">
                {/* Header */}
                <div className="mb-8 flex items-start justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">Cloud Providers</h1>
                        <p className="mt-1 text-sm text-foreground-muted">
                            Manage cloud provider API tokens for server provisioning across all teams
                        </p>
                    </div>
                    <Button onClick={() => setShowForm((v) => !v)}>
                        <Plus className="h-4 w-4" />
                        Add Token
                    </Button>
                </div>

                {/* Add token form */}
                {showForm && (
                    <Card variant="glass" className="mb-6">
                        <CardHeader>
                            <CardTitle>Add Cloud Provider Token</CardTitle>
                            <CardDescription>
                                Enter API token details for the cloud provider
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleCreate} className="space-y-4">
                                {/* Team */}
                                <div className="space-y-1">
                                    <label className="block text-sm font-medium text-foreground">
                                        Team
                                    </label>
                                    <select
                                        value={teamId}
                                        onChange={(e) => setTeamId(e.target.value)}
                                        required
                                        className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                                    >
                                        <option value="">— Select team —</option>
                                        {teams.map((t) => (
                                            <option key={t.id} value={t.id}>
                                                {t.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                {/* Provider */}
                                <div className="space-y-1">
                                    <label className="block text-sm font-medium text-foreground">
                                        Provider
                                    </label>
                                    <select
                                        value={provider}
                                        onChange={(e) => setProvider(e.target.value as 'hetzner' | 'digitalocean')}
                                        className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                                    >
                                        <option value="hetzner">Hetzner</option>
                                        <option value="digitalocean">DigitalOcean</option>
                                    </select>
                                </div>

                                {/* Name */}
                                <Input
                                    label="Name"
                                    placeholder="My Hetzner Token"
                                    value={name}
                                    onChange={(e) => setName(e.target.value)}
                                    required
                                />

                                {/* Token */}
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
                                    <Button type="submit" disabled={!teamId || !name || !token}>
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

                {/* Team filter */}
                {tokens.length > 0 && (
                    <div className="mb-4">
                        <select
                            value={filterTeamId}
                            onChange={(e) => setFilterTeamId(e.target.value)}
                            className="rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                        >
                            <option value="">All teams</option>
                            {teams.map((t) => (
                                <option key={t.id} value={t.id}>
                                    {t.name}
                                </option>
                            ))}
                        </select>
                    </div>
                )}

                {/* Tokens list */}
                {filteredTokens.length === 0 && !showForm ? (
                    <Card variant="glass">
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
                        {filteredTokens.map((t) => (
                            <Card key={t.uuid} variant="glass">
                                <CardContent className="flex items-center justify-between p-4">
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-background-secondary">
                                            <Cloud className="h-5 w-5 text-foreground-muted" />
                                        </div>
                                        <div>
                                            <p className="font-medium text-foreground">{t.name}</p>
                                            <div className="mt-1 flex items-center gap-2">
                                                <Badge
                                                    variant={t.provider === 'hetzner' ? 'primary' : 'secondary'}
                                                >
                                                    {t.provider === 'hetzner' ? 'Hetzner' : 'DigitalOcean'}
                                                </Badge>
                                                {t.team && (
                                                    <span className="text-xs text-foreground-muted">
                                                        {t.team.name}
                                                    </span>
                                                )}
                                                <span className="text-xs text-foreground-subtle">
                                                    {t.servers_count ?? 0} server{(t.servers_count ?? 0) !== 1 ? 's' : ''}
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
        </AdminLayout>
    );
}
